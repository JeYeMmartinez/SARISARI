<?php
// Controller/ProductController.php

class ProductController {
    private $model;
    const DEFAULT_MARKUP = 0.20; // 20% retail markup on cost_per_piece

    public function __construct($model) {
        $this->model = $model;
    }

    /**
     * Default index action (View)
     */
    public function index() {
        if (!defined('IN_APP')) {
            http_response_code(403);
            exit('Direct access denied.');
        }

        $products = $this->model->getProductsList();
        $trashCount = $this->model->getTrashedCount();
        $trashedProducts = $this->model->getTrashedProductsList();
        $categories = $this->model->getCategories();
        
        $categoriesList = [];
        if ($categories) {
            while($cat = mysqli_fetch_assoc($categories)){ 
                $categoriesList[] = $cat; 
            }
            mysqli_data_seek($categories, 0); // Reset pointer for other uses if needed
        }

        // Pass variables to view
        require_once __DIR__ . '/../View/products.php';
    }

    /**
     * Action handler for AJAX POST requests
     */
    public function handleAction($action) {
        $current_user = $_SESSION['user_id'] ?? 1;

        if ($action === 'create') {
            $this->createProduct($_POST, $_FILES, $current_user);
        } elseif ($action === 'restock') {
            $this->restockProduct($_POST, $current_user);
        } elseif ($action === 'update') {
            $this->updateProduct($_POST, $_FILES, $current_user);
        } elseif ($action === 'delete') {
            $this->deleteProduct($_POST, $current_user);
        } elseif ($action === 'restore') {
            $this->restoreProduct($_POST, $current_user);
        } elseif ($action === 'get_restock_logs') {
            $this->getRestockLogs($_POST);
        } elseif (isset($_GET['action']) && $_GET['action'] === 'get_restock_logs') {
            $this->getRestockLogs($_GET);
        } else {
            echo 'error: Unknown action.';
        }
    }

    /**
     * Helper to process image uploads
     */
    private function handleImageUpload($file, $uploadDir, &$error) {
        $allowedExt  = ['jpg', 'jpeg', 'png', 'webp'];
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize     = 2 * 1024 * 1024;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Image upload failed.';
            return false;
        }
        if ($file['size'] > $maxSize) {
            $error = 'Image must be smaller than 2MB.';
            return false;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) {
            $error = 'Only JPG, PNG, or WEBP images are allowed.';
            return false;
        }
        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowedMime)) {
            $error = 'Invalid image file.';
            return false;
        }
        $newName = 'prod_' . uniqid() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
            $error = 'Could not save image.';
            return false;
        }
        return $newName;
    }

    private function getUploadDir() {
        $dir = __DIR__ . '/../View/uploads/products/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private function createProduct($postData, $fileData, $current_user) {
        $name          = trim($postData['product_name'] ?? '');
        $category      = (int)($postData['category_id'] ?? 0);
        $barcode       = trim($postData['barcode'] ?? '');
        $desc          = trim($postData['description'] ?? '');
        $units_per_box = max(1, (int)($postData['units_per_box'] ?? 1));
        $cost_per_box  = (float)($postData['cost_per_box'] ?? 0);
        $cost_per_piece= round($cost_per_box / $units_per_box, 4);
        $sell          = (float)($postData['selling_price'] ?? 0);
        $status        = $postData['status'] ?? 'Available';

        if ($barcode === '' || !preg_match('/^\d{13}$/', $barcode)) {
            echo 'error: Barcode must be exactly 13 digits.'; exit();
        }
        if ($this->model->isBarcodeUsed($barcode)) {
            echo 'error: Barcode already in use.'; exit();
        }
        if ($desc === '') {
            echo 'error: Description is required.'; exit();
        }
        if ($cost_per_box <= 0) {
            echo 'error: Cost per box must be greater than zero.'; exit();
        }
        if ($sell <= 0) {
            $sell = round($cost_per_piece * (1 + self::DEFAULT_MARKUP), 2);
        }

        if (!isset($fileData['image']) || $fileData['image']['error'] === UPLOAD_ERR_NO_FILE) {
            echo 'error: A product image is required.'; exit();
        }

        $uploadDir = $this->getUploadDir();
        $uploadError = '';
        $imageName = $this->handleImageUpload($fileData['image'], $uploadDir, $uploadError);
        if ($imageName === false) {
            echo 'error: ' . $uploadError; exit();
        }

        echo $this->model->createProduct($category, $name, $barcode, $desc, $sell, $cost_per_piece, $units_per_box, $cost_per_box, $imageName, $status, $current_user);
    }

    private function restockProduct($postData, $current_user) {
        $product_id    = (int)($postData['product_id'] ?? 0);
        $boxes         = max(1, (int)($postData['boxes_received'] ?? 0));
        $units_per_box = max(1, (int)($postData['units_per_box'] ?? 1));
        $cost_per_box  = (float)($postData['cost_per_box'] ?? 0);
        $new_sell      = (float)($postData['selling_price'] ?? 0);
        $supplier      = trim($postData['supplier'] ?? '');
        $note          = trim($postData['delivery_note'] ?? '');

        if (!$product_id) {
            echo 'error: Product ID is missing.'; exit();
        }
        if ($boxes < 1) {
            echo 'error: Boxes received must be at least 1.'; exit();
        }
        if ($cost_per_box <= 0) {
            echo 'error: Cost per box must be greater than zero.'; exit();
        }
        if ($new_sell <= 0) {
            echo 'error: Selling price must be greater than zero.'; exit();
        }

        $pieces_added      = $boxes * $units_per_box;
        $total_cost        = round($boxes * $cost_per_box, 2);
        $new_cost_per_piece= round($cost_per_box / $units_per_box, 4);

        echo $this->model->restockProduct($product_id, $boxes, $units_per_box, $pieces_added, $cost_per_box, $total_cost, $new_cost_per_piece, $new_sell, $supplier, $note, $current_user);
    }

    private function updateProduct($postData, $fileData, $current_user) {
        $id            = (int)($postData['product_id'] ?? 0);
        $name          = trim($postData['product_name'] ?? '');
        $category      = (int)($postData['category_id'] ?? 0);
        $barcode       = trim($postData['barcode'] ?? '');
        $desc          = trim($postData['description'] ?? '');
        $units_per_box = max(1, (int)($postData['units_per_box'] ?? 1));
        $cost_per_box  = (float)($postData['cost_per_box'] ?? 0);
        $cost_per_piece= round($cost_per_box / $units_per_box, 4);
        $sell          = (float)($postData['selling_price'] ?? 0);
        $status        = $postData['status'] ?? 'Available';
        $reason        = trim($postData['reason'] ?? '');

        if ($barcode === '' || !preg_match('/^\d{13}$/', $barcode)) {
            echo 'error: Barcode must be exactly 13 digits.'; exit();
        }
        if ($this->model->isBarcodeUsed($barcode, $id)) {
            echo 'error: Barcode already in use.'; exit();
        }
        if ($desc === '') {
            echo 'error: Description is required.'; exit();
        }
        if ($cost_per_box <= 0) {
            echo 'error: Cost per box must be greater than zero.'; exit();
        }
        if ($reason === '') {
            echo 'error: A reason is required to update this product.'; exit();
        }
        if ($sell <= 0) {
            $sell = round($cost_per_piece * (1 + self::DEFAULT_MARKUP), 2);
        }

        $existingImage = $postData['existing_image'] ?? '';
        $imageName = '';
        $uploadDir = $this->getUploadDir();

        if (isset($fileData['image']) && $fileData['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadError = '';
            $newImg = $this->handleImageUpload($fileData['image'], $uploadDir, $uploadError);
            if ($newImg === false) {
                echo 'error: ' . $uploadError; exit();
            }
            if ($existingImage !== '' && file_exists($uploadDir . $existingImage)) {
                @unlink($uploadDir . $existingImage);
            }
            $imageName = $newImg;
        }

        echo $this->model->updateProduct($id, $category, $name, $barcode, $desc, $sell, $cost_per_piece, $units_per_box, $cost_per_box, $imageName, $status, $reason, $current_user);
    }

    private function deleteProduct($postData, $current_user) {
        $id = (int)($postData['product_id'] ?? 0);
        $reason = trim($postData['reason'] ?? '');
        if ($reason === '') {
            echo 'error: A reason is required.'; exit();
        }

        echo $this->model->deleteProduct($id, $reason, $current_user);
    }

    private function restoreProduct($postData, $current_user) {
        $id = (int)($postData['product_id'] ?? 0);
        $reason = trim($postData['reason'] ?? '');
        if ($reason === '') {
            echo 'error: A reason is required.'; exit();
        }

        echo $this->model->restoreProduct($id, $reason, $current_user);
    }

    private function getRestockLogs($data) {
        $id = (int)($data['product_id'] ?? 0);
        if (!$id) {
            header('Content-Type: application/json');
            echo json_encode([]);
            exit();
        }

        $res = $this->model->getRestockLogs($id);
        $rows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
        
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($rows);
        exit();
    }
}
