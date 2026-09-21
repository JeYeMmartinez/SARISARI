<?php
// Controller/POSController.php

class POSController {
    private $model;

    public function __construct($model) {
        $this->model = $model;
    }

    public function index() {
        if (!defined('IN_APP')) {
            http_response_code(403);
            exit('Direct access denied.');
        }

        $cashier_id = $_SESSION['user_id'] ?? $_SESSION['emp_id'] ?? 1;
        $products = $this->model->getAvailableProducts();
        $categoryFilter = $this->model->getAvailableCategories();

        $productList = [];
        if ($products) {
            while ($row = mysqli_fetch_assoc($products)) {
                $productList[] = $row;
            }
        }

        // Include the view, passing the variables along
        require_once __DIR__ . '/../View/cashier_pos.php';
    }

    public function handleAction($action) {
        $cashier_id = $_SESSION['user_id'] ?? $_SESSION['emp_id'] ?? 1;

        if ($action === 'process_sale') {
            $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];
            $total = isset($_POST['total']) ? (float)$_POST['total'] : 0;
            $payment = isset($_POST['payment']) ? (float)$_POST['payment'] : 0;

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($items)) {
                echo 'error: Invalid item data.';
                exit();
            }

            $result = $this->model->processSale($cashier_id, $items, $total, $payment);
            echo $result;
            exit();
        } else {
            echo 'error: Unknown action.';
            exit();
        }
    }
}
