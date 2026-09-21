<?php
// router.php - The Front Controller
define('IN_APP', true); // Security constant

// Handle session correctly via existing helper
require_once __DIR__ . '/Model/session_helper.php';

// Authentication and Authorization check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized access');
}

$route = $_GET['route'] ?? '';

// Ensure the user is authorized to access the route
if ($_SESSION['role'] !== 'Admin') {
    if ($_SESSION['role'] === 'Cashier' && in_array($route, ['cashier_pos', 'cashier_pos_action'])) {
        // Allow Cashier to access POS routes
    } else {
        http_response_code(403);
        exit('Access Denied');
    }
}

// Require database for Models
require_once __DIR__ . '/Model/database.php';

// Direct browser access handling


switch ($route) {
    case 'dashboard':
        require_once __DIR__ . '/Model/DashboardModel.php';
        require_once __DIR__ . '/Controller/DashboardController.php';
        
        $model = new DashboardModel($conn);
        $controller = new DashboardController($model);
        
        // Execute the controller action
        $controller->index();
        break;

    case 'inventory':
        require_once __DIR__ . '/Model/InventoryModel.php';
        require_once __DIR__ . '/Controller/InventoryController.php';
        
        $model = new InventoryModel($conn);
        $controller = new InventoryController($model);
        
        $controller->index();
        break;

    case 'inventory_action':
        require_once __DIR__ . '/Model/InventoryModel.php';
        require_once __DIR__ . '/Controller/InventoryController.php';
        
        $model = new InventoryModel($conn);
        $controller = new InventoryController($model);
        
        $action = $_POST['action'] ?? '';
        $controller->handleAction($action);
        break;

    case 'warehouse_storage':
        require_once __DIR__ . '/Model/WarehouseModel.php';
        require_once __DIR__ . '/Controller/WarehouseController.php';
        
        $model = new WarehouseModel($conn);
        $controller = new WarehouseController($model);
        
        $controller->storageIndex();
        break;

    case 'warehouse_action':
        require_once __DIR__ . '/Model/WarehouseModel.php';
        require_once __DIR__ . '/Controller/WarehouseController.php';
        
        $model = new WarehouseModel($conn);
        $controller = new WarehouseController($model);
        
        $action = $_POST['action'] ?? '';
        $controller->handleAction($action);
        break;

    case 'products':
        require_once __DIR__ . '/Model/ProductModel.php';
        require_once __DIR__ . '/Controller/ProductController.php';
        
        $model = new ProductModel($conn);
        $controller = new ProductController($model);
        
        $controller->index();
        break;

    case 'products_action':
        require_once __DIR__ . '/Model/ProductModel.php';
        require_once __DIR__ . '/Controller/ProductController.php';
        
        $model = new ProductModel($conn);
        $controller = new ProductController($model);
        
        $action = $_POST['action'] ?? '';
        $controller->handleAction($action);
        break;

    case 'cashier_pos':
        require_once __DIR__ . '/Model/POSModel.php';
        require_once __DIR__ . '/Controller/POSController.php';
        
        $model = new POSModel($conn);
        $controller = new POSController($model);
        
        $controller->index();
        break;

    case 'cashier_pos_action':
        require_once __DIR__ . '/Model/POSModel.php';
        require_once __DIR__ . '/Controller/POSController.php';
        
        $model = new POSModel($conn);
        $controller = new POSController($model);
        
        $action = $_POST['action'] ?? '';
        $controller->handleAction($action);
        break;

    case 'sales':
        require_once __DIR__ . '/Model/SalesModel.php';
        require_once __DIR__ . '/Controller/SalesController.php';
        
        $model = new SalesModel($conn);
        $controller = new SalesController($model);
        
        $controller->index();
        break;

    case 'sales_action':
        require_once __DIR__ . '/Model/SalesModel.php';
        require_once __DIR__ . '/Controller/SalesController.php';
        
        $model = new SalesModel($conn);
        $controller = new SalesController($model);
        
        $action = $_POST['action'] ?? '';
        $controller->action($action);
        break;

    case 'notifications':
        require_once __DIR__ . '/Model/NotificationModel.php';
        require_once __DIR__ . '/Controller/NotificationController.php';
        
        $model = new NotificationModel($conn);
        $controller = new NotificationController($model);
        
        $controller->index();
        break;

    case 'notification_action':
        require_once __DIR__ . '/Model/NotificationModel.php';
        require_once __DIR__ . '/Controller/NotificationController.php';
        
        $model = new NotificationModel($conn);
        $controller = new NotificationController($model);
        
        $action = $_POST['action'] ?? $_GET['action'] ?? '';
        $controller->handleAction($action);
        break;

    case 'finance_sales':
    case 'finance_stock_requests':
    case 'finance_restock':
    case 'finance_payroll':
    case 'finance_signature_profile':
        require_once __DIR__ . '/Model/FinanceModel.php';
        require_once __DIR__ . '/Controller/FinanceController.php';
        
        $model = new FinanceModel($conn);
        $controller = new FinanceController($model);
        
        if ($route === 'finance_sales') { $controller->sales(); }
        elseif ($route === 'finance_stock_requests') { $controller->stockRequests(); }
        elseif ($route === 'finance_restock') { $controller->restock(); }
        elseif ($route === 'finance_payroll') { $controller->payroll(); }
        elseif ($route === 'finance_signature_profile') { $controller->signatureProfile(); }
        break;

    case 'finance_action':
        require_once __DIR__ . '/Model/FinanceModel.php';
        require_once __DIR__ . '/Controller/FinanceController.php';
        
        $model = new FinanceModel($conn);
        $controller = new FinanceController($model);
        
        $action = $_POST['action'] ?? $_GET['action'] ?? '';
        $controller->handleAction($action);
        break;

    default:
        http_response_code(404);
        echo "<div class='alert alert-danger'>Route not found or not yet migrated to MVC.</div>";
        break;
}
