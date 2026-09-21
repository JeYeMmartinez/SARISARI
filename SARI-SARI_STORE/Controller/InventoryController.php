<?php
// Controller/InventoryController.php

class InventoryController {
    private $model;

    public function __construct($model) {
        $this->model = $model;
    }

    /**
     * Display the main inventory view
     */
    public function index() {
        // Fetch all required data for the view
        $inventory = $this->model->getInventoryList();
        $unstockedList = $this->model->getUnstockedProducts();
        
        $counts = $this->model->getInventorySummaryCounts();
        $totalData = ['total' => $counts['total']];
        $lowData = ['total' => $counts['low']];
        $outData = ['total' => $counts['out']];
        $healthyData = ['total' => $counts['healthy']];

        // Ensure current_user is passed or available
        $current_user = $_SESSION['user_id'] ?? 1;

        // Load the view file
        require_once __DIR__ . '/../View/inventory.php';
    }

    /**
     * Handle AJAX actions for inventory
     */
    public function handleAction($action) {
        $current_user = $_SESSION['user_id'] ?? 1;

        switch ($action) {
            case 'add_stock':
                echo $this->model->addStock($_POST, $current_user);
                break;
            case 'restock':
                echo $this->model->restockInventoryItem($_POST, $current_user);
                break;
            case 'remove_stock':
                $inventory_id = $_POST['inventory_id'] ?? 0;
                $remove_quantity = $_POST['remove_quantity'] ?? 0;
                echo $this->model->removeStock($inventory_id, $remove_quantity, $current_user);
                break;
            default:
                echo 'error: Unknown action.';
                break;
        }
        exit(); // Since these are AJAX requests, always exit after handling
    }
}
