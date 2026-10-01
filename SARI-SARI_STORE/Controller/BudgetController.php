<?php
// Controller/BudgetController.php
class BudgetController {
    private $model;

    public function __construct($model) {
        $this->model = $model;
    }

    public function index() {
        $this->authorizeFinance();
        $budgets = $this->model->getAllBudgets();
        $departments = $this->model->getDepartments();
        require_once __DIR__ . '/../View/Finance_employee/budgets.php';
    }

    private function authorizeFinance() {
        if (!isset($_SESSION['role']) || (strtolower($_SESSION['role']) !== 'admin' && strtolower($_SESSION['role']) !== 'finance officer')) {
            http_response_code(403);
            exit('Unauthorized Access');
        }
    }

    public function handleAction($action) {
        $this->authorizeFinance();
        $user_id = $_SESSION['user_id'] ?? 0;
        
        switch ($action) {
            case 'create':
                $result = $this->model->createBudget($_POST, $user_id);
                if ($result) {
                    $_SESSION['success_msg'] = "Budget created successfully.";
                } else {
                    $_SESSION['error_msg'] = "Failed to create budget.";
                }
                break;
            case 'update':
                $id = $_POST['budget_id'];
                $result = $this->model->updateBudget($id, $_POST);
                if ($result) {
                    $_SESSION['success_msg'] = "Budget updated successfully.";
                } else {
                    $_SESSION['error_msg'] = "Failed to update budget.";
                }
                break;
            case 'submit':
                $id = $_POST['budget_id'];
                $result = $this->model->submitBudget($id);
                if ($result) {
                    $_SESSION['success_msg'] = "Budget submitted for approval.";
                } else {
                    $_SESSION['error_msg'] = "Failed to submit budget.";
                }
                break;
            case 'cancel':
                $id = $_POST['budget_id'];
                $result = $this->model->cancelBudget($id);
                if ($result) {
                    $_SESSION['success_msg'] = "Budget cancelled.";
                } else {
                    $_SESSION['error_msg'] = "Failed to cancel budget.";
                }
                break;
            case 'approve':
                $id = $_POST['budget_id'];
                $notes = $_POST['notes'];
                $signature = $_POST['signature_data'];
                $emp_name = $_SESSION['full_name'] ?? $_SESSION['username'];
                
                // Verify signature against profile here if needed, assuming valid for now
                // In real implementation we'd check against registered_signatures
                
                $result = $this->model->approveBudget($id, $notes, $signature, $user_id, $emp_name);
                if ($result['success']) {
                    $_SESSION['success_msg'] = "Budget approved successfully.";
                } else {
                    $_SESSION['error_msg'] = "Failed to approve budget: " . $result['error'];
                }
                break;
            default:
                $_SESSION['error_msg'] = "Unknown action.";
                break;
        }
        
        header("Location: ?route=finance_budgets");
        exit;
    }
}
