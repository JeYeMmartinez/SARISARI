<?php
// Model/ExpenseModel.php
class ExpenseModel {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getCategories() {
        $q = "SELECT * FROM expense_categories ORDER BY category_name ASC";
        return mysqli_query($this->conn, $q);
    }

    public function getApprovedBudgets() {
        $q = "SELECT b.budget_id, b.reference_no, b.title, b.allocated_budget, d.department_name 
              FROM budgets b
              JOIN departments d ON b.department_id = d.department_id
              WHERE b.status = 'Approved' 
              ORDER BY b.created_at DESC";
        return mysqli_query($this->conn, $q);
    }

    public function getAllExpenses() {
        $q = "SELECT e.*, c.category_name, b.reference_no as budget_ref, b.title as budget_title, u.full_name as requester_name
              FROM expenses e
              JOIN expense_categories c ON e.category_id = c.category_id
              JOIN budgets b ON e.budget_id = b.budget_id
              JOIN users u ON e.requested_by = u.user_id
              ORDER BY e.created_at DESC";
        return mysqli_query($this->conn, $q);
    }

    public function getExpenseById($id) {
        $id = (int)$id;
        $q = "SELECT e.*, c.category_name, b.reference_no as budget_ref, b.title as budget_title, u.full_name as requester_name
              FROM expenses e
              JOIN expense_categories c ON e.category_id = c.category_id
              JOIN budgets b ON e.budget_id = b.budget_id
              JOIN users u ON e.requested_by = u.user_id
              WHERE e.expense_id = $id LIMIT 1";
        $res = mysqli_query($this->conn, $q);
        return $res ? mysqli_fetch_assoc($res) : null;
    }

    public function createExpense($data, $user_id) {
        $ref = 'EXP-' . date('Ymd') . '-' . rand(1000, 9999);
        $budget_id = (int)$data['budget_id'];
        $category_id = (int)$data['category_id'];
        $amount = (float)$data['amount'];
        $expense_date = mysqli_real_escape_string($this->conn, $data['expense_date']);
        $description = mysqli_real_escape_string($this->conn, $data['description']);
        $payee = mysqli_real_escape_string($this->conn, $data['payee']);
        $receipt_no = mysqli_real_escape_string($this->conn, $data['receipt_no'] ?? '');
        $user_id = (int)$user_id;

        if ($amount <= 0) {
            return false;
        }

        $q = "INSERT INTO expenses (reference_no, budget_id, category_id, amount, expense_date, description, payee, receipt_no, requested_by)
              VALUES ('$ref', $budget_id, $category_id, $amount, '$expense_date', '$description', '$payee', '$receipt_no', $user_id)";
        return mysqli_query($this->conn, $q);
    }

    public function updateExpense($id, $data) {
        $id = (int)$id;
        $budget_id = (int)$data['budget_id'];
        $category_id = (int)$data['category_id'];
        $amount = (float)$data['amount'];
        $expense_date = mysqli_real_escape_string($this->conn, $data['expense_date']);
        $description = mysqli_real_escape_string($this->conn, $data['description']);
        $payee = mysqli_real_escape_string($this->conn, $data['payee']);
        $receipt_no = mysqli_real_escape_string($this->conn, $data['receipt_no'] ?? '');

        if ($amount <= 0) {
            return false;
        }

        $q = "UPDATE expenses SET budget_id = $budget_id, category_id = $category_id, amount = $amount, 
              expense_date = '$expense_date', description = '$description', payee = '$payee', receipt_no = '$receipt_no'
              WHERE expense_id = $id AND status = 'Draft'";
        return mysqli_query($this->conn, $q);
    }

    public function submitExpense($id) {
        $id = (int)$id;
        $q = "UPDATE expenses SET status = 'For Approval' WHERE expense_id = $id AND status = 'Draft'";
        return mysqli_query($this->conn, $q);
    }

    public function approveExpense($id, $notes, $signature, $emp_user, $emp_name) {
        $id = (int)$id;
        $notes = mysqli_real_escape_string($this->conn, $notes);
        $signature = mysqli_real_escape_string($this->conn, $signature);
        $emp_user = (int)$emp_user;
        $emp_name = mysqli_real_escape_string($this->conn, $emp_name);

        mysqli_begin_transaction($this->conn);
        try {
            // Lock expense
            $resExp = mysqli_query($this->conn, "SELECT status, budget_id, amount, reference_no FROM expenses WHERE expense_id = $id FOR UPDATE");
            if (!$resExp || mysqli_num_rows($resExp) === 0) {
                throw new Exception("Expense not found.");
            }
            $exp = mysqli_fetch_assoc($resExp);
            if ($exp['status'] !== 'For Approval') {
                throw new Exception("Expense is not For Approval.");
            }
            $budget_id = (int)$exp['budget_id'];
            $expense_amount = (float)$exp['amount'];

            // Lock budget
            $resBud = mysqli_query($this->conn, "SELECT status, allocated_budget FROM budgets WHERE budget_id = $budget_id FOR UPDATE");
            if (!$resBud || mysqli_num_rows($resBud) === 0) {
                throw new Exception("Budget not found.");
            }
            $bud = mysqli_fetch_assoc($resBud);
            if ($bud['status'] !== 'Approved') {
                throw new Exception("Budget is not Approved.");
            }

            // Calculate remaining budget
            $resSum = mysqli_query($this->conn, "SELECT SUM(amount) as total FROM expenses WHERE budget_id = $budget_id AND status IN ('Approved', 'Paid')");
            $sumRow = mysqli_fetch_assoc($resSum);
            $total_spent = (float)($sumRow['total'] ?? 0);
            
            $remaining = (float)$bud['allocated_budget'] - $total_spent;

            if ($expense_amount > $remaining) {
                throw new Exception("Insufficient budget remaining.");
            }

            $ref = 'FIN-EXP-' . date('Ymd') . '-' . rand(1000, 9999);
            $role = 'Finance Officer';

            $q1 = "INSERT INTO finance_approvals (approval_ref, document_type, related_id, approved_by, approver_name, approver_role, decision, e_signature, notes)
                   VALUES ('$ref', 'Expense', $id, $emp_user, '$emp_name', '$role', 'Approved', '$signature', '$notes')";
            
            if (!mysqli_query($this->conn, $q1)) {
                throw new Exception("Failed to insert approval.");
            }

            $q2 = "UPDATE expenses SET status = 'Approved' WHERE expense_id = $id";
            if (!mysqli_query($this->conn, $q2)) {
                throw new Exception("Failed to update expense status.");
            }

            mysqli_commit($this->conn);
            
            // Audit log
            mysqli_query($this->conn, "INSERT INTO audit_logs (user_id, action, details) VALUES ($emp_user, 'Approve Expense', 'Approved expense {$exp['reference_no']}')");

            return ['success' => true, 'reference_no' => $exp['reference_no']];
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function rejectExpense($id) {
        $id = (int)$id;
        $q = "UPDATE expenses SET status = 'Rejected' WHERE expense_id = $id AND status = 'For Approval'";
        return mysqli_query($this->conn, $q);
    }

    public function cancelExpense($id, $user_id) {
        $id = (int)$id;
        $user_id = (int)$user_id;

        mysqli_begin_transaction($this->conn);
        try {
            $res = mysqli_query($this->conn, "SELECT status, reference_no, budget_id FROM expenses WHERE expense_id = $id FOR UPDATE");
            if (!$res || mysqli_num_rows($res) === 0) {
                throw new Exception("Expense not found.");
            }
            $exp = mysqli_fetch_assoc($res);
            $status = $exp['status'];
            $budget_id = $exp['budget_id'];

            if ($status === 'Paid' || $status === 'Rejected' || $status === 'Cancelled') {
                throw new Exception("Cannot cancel expense in $status status.");
            }

            if ($status === 'Approved') {
                // Lock budget to safely adjust remaining context
                mysqli_query($this->conn, "SELECT budget_id FROM budgets WHERE budget_id = $budget_id FOR UPDATE");
            }

            $q = "UPDATE expenses SET status = 'Cancelled' WHERE expense_id = $id";
            if (!mysqli_query($this->conn, $q)) {
                throw new Exception("Failed to cancel expense.");
            }

            mysqli_commit($this->conn);
            mysqli_query($this->conn, "INSERT INTO audit_logs (user_id, action, details) VALUES ($user_id, 'Cancel Expense', 'Cancelled expense {$exp['reference_no']}')");
            return ['success' => true];
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function markPaid($id, $data, $user_id) {
        $id = (int)$id;
        $payment_date = mysqli_real_escape_string($this->conn, $data['payment_date']);
        $payment_method = mysqli_real_escape_string($this->conn, $data['payment_method']);
        $payment_reference = mysqli_real_escape_string($this->conn, $data['payment_reference'] ?? '');
        $user_id = (int)$user_id;

        mysqli_begin_transaction($this->conn);
        try {
            $res = mysqli_query($this->conn, "SELECT status, reference_no FROM expenses WHERE expense_id = $id FOR UPDATE");
            if (!$res || mysqli_num_rows($res) === 0) {
                throw new Exception("Expense not found.");
            }
            $exp = mysqli_fetch_assoc($res);
            if ($exp['status'] !== 'Approved') {
                throw new Exception("Expense must be Approved to be marked Paid.");
            }

            $q = "UPDATE expenses SET status = 'Paid', payment_date = '$payment_date', payment_method = '$payment_method', payment_reference = '$payment_reference' WHERE expense_id = $id";
            if (!mysqli_query($this->conn, $q)) {
                throw new Exception("Failed to update expense status.");
            }

            mysqli_commit($this->conn);
            mysqli_query($this->conn, "INSERT INTO audit_logs (user_id, action, details) VALUES ($user_id, 'Pay Expense', 'Marked expense {$exp['reference_no']} as Paid')");
            return ['success' => true];
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
