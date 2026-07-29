<?php
// Controller/ProductController.php

class ProductController {
    private $conn;
    const DEFAULT_MARKUP = 0.20; // 20% retail markup on cost_per_piece

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Fetch list of active products
     */
    public function getProductsList() {
        return mysqli_query($this->conn, "
            SELECT p.*, c.category_name,
                   COALESCE(i.quantity, 0) AS stock_qty,
                   i.minimum_stock
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN inventory  i ON i.product_id  = p.product_id
            WHERE p.deleted_at IS NULL
            ORDER BY p.created_at DESC
        ");
    }

    /**
     * Fetch list of soft-deleted products
     */
    public function getTrashedProductsList() {
        return mysqli_query($this->conn, "
            SELECT p.*, c.category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE p.deleted_at IS NOT NULL
            ORDER BY p.deleted_at DESC
        ");
    }

    /**
     * Get count of soft-deleted products
     */
    public function getTrashedCount() {
        $res = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT COUNT(*) AS total FROM products WHERE deleted_at IS NOT NULL"
        ));
        return $res['total'] ?? 0;
    }

    /**
     * Get all categories
     */
    public function getCategories() {
        return mysqli_query($this->conn, "SELECT * FROM categories ORDER BY category_name ASC");
    }

    /**
     * Get restock logs for a product
     */
    public function getRestockLogs($product_id) {
        $product_id = (int)$product_id;
        return mysqli_query($this->conn, "
            SELECT r.*, u.full_name AS restocked_by_name
            FROM restock_logs r
            LEFT JOIN users u ON r.restocked_by = u.user_id
            WHERE r.product_id = $product_id
            ORDER BY r.restocked_at DESC
            LIMIT 20
        ");
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

    /**
     * Create a new product
     */
    public function createProduct($postData, $fileData, $current_user, $uploadDir) {
        $name          = mysqli_real_escape_string($this->conn, trim($postData['product_name'] ?? ''));
        $category      = (int)($postData['category_id'] ?? 0);
        $barcode       = mysqli_real_escape_string($this->conn, trim($postData['barcode'] ?? ''));
        $desc          = mysqli_real_escape_string($this->conn, trim($postData['description'] ?? ''));
        $units_per_box = max(1, (int)($postData['units_per_box'] ?? 1));
        $cost_per_box  = (float)($postData['cost_per_box'] ?? 0);
        $cost_per_piece= round($cost_per_box / $units_per_box, 4);
        $sell          = (float)($postData['selling_price'] ?? 0);
        $status        = $postData['status'] ?? 'Available';

        if ($barcode === '' || !preg_match('/^\d{13}$/', $barcode)) {
            return 'error: Barcode must be exactly 13 digits.';
        }
        $dup = mysqli_query($this->conn, "SELECT product_id FROM products WHERE barcode='$barcode' AND deleted_at IS NULL");
        if ($dup && mysqli_num_rows($dup) > 0) {
            return 'error: Barcode already in use.';
        }
        if ($desc === '') {
            return 'error: Description is required.';
        }
        if ($cost_per_box <= 0) {
            return 'error: Cost per box must be greater than zero.';
        }
        if ($sell <= 0) {
            $sell = round($cost_per_piece * (1 + self::DEFAULT_MARKUP), 2);
        }

        if (!isset($fileData['image']) || $fileData['image']['error'] === UPLOAD_ERR_NO_FILE) {
            return 'error: A product image is required.';
        }

        $uploadError = '';
        $imageName = $this->handleImageUpload($fileData['image'], $uploadDir, $uploadError);
        if ($imageName === false) {
            return 'error: ' . $uploadError;
        }

        // Start transaction
        mysqli_begin_transaction($this->conn);
        try {
            $q = mysqli_query($this->conn, "
                INSERT INTO products
                    (category_id, product_name, barcode, description,
                     selling_price, cost_price, units_per_box, cost_per_box,
                     image, status, added_by)
                VALUES
                    ($category, '$name', '$barcode', '$desc',
                     $sell, $cost_per_piece, $units_per_box, $cost_per_box,
                     '$imageName', '$status', $current_user)
            ");

            if (!$q) {
                throw new Exception("Product insertion failed: " . mysqli_error($this->conn));
            }

            $pid = mysqli_insert_id($this->conn);

            $inv = mysqli_query($this->conn, "INSERT INTO inventory (product_id, quantity, minimum_stock, last_restock) VALUES ($pid, 0, 5, NULL)");
            if (!$inv) {
                throw new Exception("Inventory registration failed: " . mysqli_error($this->conn));
            }

            // Log Action
            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $current_user, 'Create', 'products', $pid, "Added product: $name (units/box: $units_per_box, cost/box: P$cost_per_box)");

            mysqli_query($this->conn, "INSERT INTO notifications (title, message, type, is_read) VALUES ('Product Added','New product: $name','Products',0)");

            mysqli_commit($this->conn);
            return 'success';
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return 'error: ' . $e->getMessage();
        }
    }

    /**
     * Restock an existing product
     */
    public function restockProduct($postData, $current_user) {
        $product_id    = (int)($postData['product_id'] ?? 0);
        $boxes         = max(1, (int)($postData['boxes_received'] ?? 0));
        $units_per_box = max(1, (int)($postData['units_per_box'] ?? 1));
        $cost_per_box  = (float)($postData['cost_per_box'] ?? 0);
        $new_sell      = (float)($postData['selling_price'] ?? 0);
        $supplier      = mysqli_real_escape_string($this->conn, trim($postData['supplier'] ?? ''));
        $note          = mysqli_real_escape_string($this->conn, trim($postData['delivery_note'] ?? ''));

        if (!$product_id) {
            return 'error: Product ID is missing.';
        }
        if ($boxes < 1) {
            return 'error: Boxes received must be at least 1.';
        }
        if ($cost_per_box <= 0) {
            return 'error: Cost per box must be greater than zero.';
        }
        if ($new_sell <= 0) {
            return 'error: Selling price must be greater than zero.';
        }

        $pieces_added      = $boxes * $units_per_box;
        $total_cost        = round($boxes * $cost_per_box, 2);
        $new_cost_per_piece= round($cost_per_box / $units_per_box, 4);
        $sup_sql           = $supplier !== '' ? "'$supplier'" : "NULL";
        $note_sql          = $note !== '' ? "'$note'" : "NULL";

        mysqli_begin_transaction($this->conn);
        try {
            $logQuery = mysqli_query($this->conn, "
                INSERT INTO restock_logs
                    (product_id, boxes_received, units_per_box, pieces_added,
                     cost_per_box, total_cost, new_cost_per_piece, new_selling_price,
                     supplier, delivery_note, restocked_by)
                VALUES
                    ($product_id, $boxes, $units_per_box, $pieces_added,
                     $cost_per_box, $total_cost, $new_cost_per_piece, $new_sell,
                     $sup_sql, $note_sql, $current_user)
            ");

            if (!$logQuery) {
                throw new Exception("Restock log failed: " . mysqli_error($this->conn));
            }

            $inv = mysqli_query($this->conn, "SELECT inventory_id FROM inventory WHERE product_id = $product_id LIMIT 1");
            if ($inv && mysqli_num_rows($inv) > 0) {
                mysqli_query($this->conn, "UPDATE inventory SET quantity = quantity + $pieces_added, last_restock = NOW() WHERE product_id = $product_id");
            } else {
                mysqli_query($this->conn, "INSERT INTO inventory (product_id, quantity, minimum_stock, last_restock) VALUES ($product_id, $pieces_added, 5, NOW())");
            }

            $prodUpdate = mysqli_query($this->conn, "
                UPDATE products SET
                    cost_price    = $new_cost_per_piece,
                    cost_per_box  = $cost_per_box,
                    units_per_box = $units_per_box,
                    selling_price = $new_sell,
                    status        = 'Available'
                WHERE product_id = $product_id
            ");

            if (!$prodUpdate) {
                throw new Exception("Product details update failed: " . mysqli_error($this->conn));
            }

            $prow  = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT product_name FROM products WHERE product_id = $product_id"));
            $pname = $prow['product_name'] ?? 'Unknown';

            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $current_user, 'Restock', 'products', $product_id,
                "Restocked '$pname': $boxes box(es) x $units_per_box pcs = $pieces_added pcs. Total: P$total_cost");

            mysqli_query($this->conn, "INSERT INTO notifications (title, message, type, is_read) VALUES ('Restocked','$pname: +$pieces_added pcs','Products',0)");

            mysqli_commit($this->conn);
            return 'success';
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return 'error: ' . $e->getMessage();
        }
    }

    /**
     * Update an existing product
     */
    public function updateProduct($postData, $fileData, $current_user, $uploadDir) {
        $id            = (int)$postData['product_id'];
        $name          = mysqli_real_escape_string($this->conn, trim($postData['product_name'] ?? ''));
        $category      = (int)($postData['category_id'] ?? 0);
        $barcode       = mysqli_real_escape_string($this->conn, trim($postData['barcode'] ?? ''));
        $desc          = mysqli_real_escape_string($this->conn, trim($postData['description'] ?? ''));
        $units_per_box = max(1, (int)($postData['units_per_box'] ?? 1));
        $cost_per_box  = (float)($postData['cost_per_box'] ?? 0);
        $cost_per_piece= round($cost_per_box / $units_per_box, 4);
        $sell          = (float)($postData['selling_price'] ?? 0);
        $status        = $postData['status'] ?? 'Available';
        $reason        = mysqli_real_escape_string($this->conn, trim($postData['reason'] ?? ''));

        if ($barcode === '' || !preg_match('/^\d{13}$/', $barcode)) {
            return 'error: Barcode must be exactly 13 digits.';
        }
        $dup = mysqli_query($this->conn, "SELECT product_id FROM products WHERE barcode='$barcode' AND product_id != $id AND deleted_at IS NULL");
        if ($dup && mysqli_num_rows($dup) > 0) {
            return 'error: Barcode already in use.';
        }
        if ($desc === '') {
            return 'error: Description is required.';
        }
        if ($cost_per_box <= 0) {
            return 'error: Cost per box must be greater than zero.';
        }
        if ($reason === '') {
            return 'error: A reason is required to update this product.';
        }
        if ($sell <= 0) {
            $sell = round($cost_per_piece * (1 + self::DEFAULT_MARKUP), 2);
        }

        $existingImage = mysqli_real_escape_string($this->conn, $postData['existing_image'] ?? '');
        $imageName = $existingImage;
        if (isset($fileData['image']) && $fileData['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadError = '';
            $newImg = $this->handleImageUpload($fileData['image'], $uploadDir, $uploadError);
            if ($newImg === false) {
                return 'error: ' . $uploadError;
            }
            if ($existingImage !== '' && file_exists($uploadDir . $existingImage)) {
                @unlink($uploadDir . $existingImage);
            }
            $imageName = $newImg;
        }
        $imgSql = $imageName !== '' ? "'$imageName'" : "NULL";

        $q = mysqli_query($this->conn, "
            UPDATE products SET
                category_id   = $category,
                product_name  = '$name',
                barcode       = '$barcode',
                description   = '$desc',
                selling_price = $sell,
                cost_price    = $cost_per_piece,
                units_per_box = $units_per_box,
                cost_per_box  = $cost_per_box,
                image         = $imgSql,
                status        = '$status'
            WHERE product_id = $id
        ");

        if ($q) {
            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $current_user, 'Update', 'products', $id, "Updated product '$name' - Reason: $reason");
            mysqli_query($this->conn, "INSERT INTO notifications (title, message, type, is_read) VALUES ('Product Updated','Updated: $name','Products',0)");
            return 'success';
        } else {
            return 'error: ' . mysqli_error($this->conn);
        }
    }

    /**
     * Soft delete/archive a product
     */
    public function deleteProduct($product_id, $reason, $current_user) {
        $id = (int)$product_id;
        $reason = mysqli_real_escape_string($this->conn, trim($reason));
        if ($reason === '') {
            return 'error: A reason is required.';
        }

        $nameRow = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT product_name FROM products WHERE product_id=$id"));
        $name = $nameRow ? $nameRow['product_name'] : 'Unknown';

        $q = mysqli_query($this->conn, "UPDATE products SET deleted_at=NOW(), deleted_reason='$reason', status='Unavailable' WHERE product_id=$id");
        if ($q) {
            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $current_user, 'Trash', 'products', $id, "Archived '$name' - Reason: $reason");
            mysqli_query($this->conn, "INSERT INTO notifications (title, message, type, is_read) VALUES ('Product Archived','Archived: $name','Products',0)");
            return 'success';
        } else {
            return 'error: ' . mysqli_error($this->conn);
        }
    }

    /**
     * Restore a soft-deleted product
     */
    public function restoreProduct($product_id, $reason, $current_user) {
        $id = (int)$product_id;
        $reason = mysqli_real_escape_string($this->conn, trim($reason));
        if ($reason === '') {
            return 'error: A reason is required.';
        }

        $q = mysqli_query($this->conn, "UPDATE products SET deleted_at=NULL, deleted_reason=NULL WHERE product_id=$id");
        if ($q) {
            $nameRow = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT product_name FROM products WHERE product_id=$id"));
            $name = $nameRow ? $nameRow['product_name'] : 'Unknown';

            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $current_user, 'Restore', 'products', $id, "Restored product '$name' - Reason: $reason");
            mysqli_query($this->conn, "INSERT INTO notifications (title, message, type, is_read) VALUES ('Product Restored','Restored: $name','Products',0)");
            return 'success';
        } else {
            return 'error: ' . mysqli_error($this->conn);
        }
    }
}
