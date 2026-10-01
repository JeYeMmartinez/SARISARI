<?php
// Controller/ExpenseController.php
class ExpenseController {
    private $model;

    public function __construct($model) {
        $this->model = $model;
    }

    public function index() {
        $this->authorizeFinance();
        $expenses = $this->model->getAllExpenses();
        $categories = $this->model->getCategories();
        $budgets = $this->model->getApprovedBudgets();
        
        // For remaining budget display
        global $conn;
        require_once __DIR__ . '/../Model/BudgetModel.php';
        $budget_model = new BudgetModel($conn);
        
        require_once __DIR__ . '/../View/Finance_employee/expenses.php';
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
                $result = $this->model->createExpense($_POST, $user_id);
                if ($result) {
                    $_SESSION['success_msg'] = "Expense draft created.";
                } else {
                    $_SESSION['error_msg'] = "Failed to create expense draft.";
                }
                break;
            case 'update':
                $id = $_POST['expense_id'];
                $result = $this->model->updateExpense($id, $_POST);
                if ($result) {
                    $_SESSION['success_msg'] = "Expense updated.";
                } else {
                    $_SESSION['error_msg'] = "Failed to update expense.";
                }
                break;
            case 'submit':
                $id = $_POST['expense_id'];
                $result = $this->model->submitExpense($id);
                if ($result) {
                    $_SESSION['success_msg'] = "Expense submitted for approval.";
                } else {
                    $_SESSION['error_msg'] = "Failed to submit expense.";
                }
                break;
            case 'cancel':
                $id = $_POST['expense_id'];
                $result = $this->model->cancelExpense($id, $user_id);
                if ($result['success']) {
                    $_SESSION['success_msg'] = "Expense cancelled.";
                } else {
                    $_SESSION['error_msg'] = "Failed to cancel expense: " . $result['error'];
                }
                break;
            case 'reject':
                $id = $_POST['expense_id'];
                $result = $this->model->rejectExpense($id);
                if ($result) {
                    $_SESSION['success_msg'] = "Expense rejected.";
                } else {
                    $_SESSION['error_msg'] = "Failed to reject expense.";
                }
                break;
            case 'approve':
                $id = $_POST['expense_id'];
                $notes = $_POST['notes'];
                $signature = $_POST['signature_data'];
                $emp_name = $_SESSION['full_name'] ?? $_SESSION['username'];
                
                $result = $this->model->approveExpense($id, $notes, $signature, $user_id, $emp_name);
                if ($result['success']) {
                    $_SESSION['success_msg'] = "Expense approved successfully.";
                } else {
                    $_SESSION['error_msg'] = "Failed to approve expense: " . $result['error'];
                }
                break;
            case 'mark_paid':
                $id = $_POST['expense_id'];
                $result = $this->model->markPaid($id, $_POST, $user_id);
                if ($result['success']) {
                    $_SESSION['success_msg'] = "Expense marked as Paid.";
                } else {
                    $_SESSION['error_msg'] = "Failed to mark paid: " . $result['error'];
                }
                break;
            default:
                $_SESSION['error_msg'] = "Unknown action.";
                break;
        }
        
        header("Location: ?route=finance_expenses");
        exit;
    }
}
