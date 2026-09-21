<?php
// Controller/WarehouseController.php

class WarehouseController {
    private $model;

    public function __construct($model) {
        $this->model = $model;
    }

    /**
     * Display the warehouse storage view
     */
    public function storageIndex() {
        // Initialize/seed table if needed
        $this->model->initializeStorageTable();

        // Handle success/error messages from redirect
        $message = $_SESSION['warehouse_msg'] ?? '';
        $msg_type = $_SESSION['warehouse_msg_type'] ?? '';
        unset($_SESSION['warehouse_msg']);
        unset($_SESSION['warehouse_msg_type']);

        // Fetch data
        $items = $this->model->getWarehouseStorage();
        
        // Calculate counts
        $total_products = count($items);
        $total_units = 0;
        $low_stock_count = 0;
        
        foreach ($items as $row) {
            $qty = intval($row['storage_qty']);
            $total_units += $qty;
            if ($qty <= intval($row['min_reorder'])) {
                $low_stock_count++;
            }
        }

        // Load the view file
        require_once __DIR__ . '/../View/warehouse/warehouse_storage.php';
    }

    /**
     * Handle actions for warehouse (e.g. POST from forms)
     */
    public function handleAction($action) {
        if ($action === 'adjust_storage_stock') {
            $pid = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $new_qty = (isset($_POST['quantity']) && $_POST['quantity'] !== '') ? intval($_POST['quantity']) : 0;
            $new_min = (isset($_POST['min_reorder_level']) && $_POST['min_reorder_level'] !== '') ? intval($_POST['min_reorder_level']) : 20;

            if ($pid > 0) {
                $result = $this->model->adjustStorageStock($pid, $new_qty, $new_min);
                $_SESSION['warehouse_msg'] = $result['message'];
                $_SESSION['warehouse_msg_type'] = $result['success'] ? 'success' : 'danger';
            } else {
                $_SESSION['warehouse_msg'] = "Invalid Product ID.";
                $_SESSION['warehouse_msg_type'] = "danger";
            }
            
            // Redirect back to the storage view via the router
            // Since this is loaded via AJAX `loadPage()`, we should respond appropriately.
            // Wait, does it use normal POST redirect or AJAX? Let's check `warehouse_storage.php` again.
        } else {
            $_SESSION['warehouse_msg'] = "Unknown action.";
            $_SESSION['warehouse_msg_type'] = "danger";
        }
        
        // The original code was self-submitting the form to warehouse_storage.php and showing the message.
        // We will redirect back to the wrapper page so the layout is preserved.
        header("Location: View/admin_warehouse.php?page=warehouse/warehouse_storage.php");
        exit();
    }
}
