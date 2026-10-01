<?php
// Model/BudgetModel.php
class BudgetModel {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getDepartments() {
        $q = "SELECT department_id, department_name FROM departments ORDER BY department_name ASC";
        return mysqli_query($this->conn, $q);
    }

    public function getAllBudgets() {
        $q = "SELECT b.*, d.department_name, u.full_name as creator_name 
              FROM budgets b
              JOIN departments d ON b.department_id = d.department_id
              JOIN users u ON b.created_by = u.user_id
              ORDER BY b.created_at DESC";
        return mysqli_query($this->conn, $q);
    }

    public function getBudgetById($id) {
        $id = (int)$id;
        $q = "SELECT b.*, d.department_name, u.full_name as creator_name 
              FROM budgets b
              JOIN departments d ON b.department_id = d.department_id
              JOIN users u ON b.created_by = u.user_id
              WHERE b.budget_id = $id LIMIT 1";
        $res = mysqli_query($this->conn, $q);
        return $res ? mysqli_fetch_assoc($res) : null;
    }

    public function createBudget($data, $user_id) {
        $ref = 'BUD-' . date('Ymd') . '-' . rand(1000, 9999);
        $dept_id = (int)$data['department_id'];
        $title = mysqli_real_escape_string($this->conn, $data['title']);
        $amount = (float)$data['allocated_budget'];
        $user_id = (int)$user_id;

        $q = "INSERT INTO budgets (reference_no, department_id, title, allocated_budget, created_by)
              VALUES ('$ref', $dept_id, '$title', $amount, $user_id)";
        return mysqli_query($this->conn, $q);
    }

    public function updateBudget($id, $data) {
        $id = (int)$id;
        $dept_id = (int)$data['department_id'];
        $title = mysqli_real_escape_string($this->conn, $data['title']);
        $amount = (float)$data['allocated_budget'];

        $q = "UPDATE budgets SET department_id = $dept_id, title = '$title', allocated_budget = $amount
              WHERE budget_id = $id AND status = 'Draft'";
        return mysqli_query($this->conn, $q);
    }

    public function submitBudget($id) {
        $id = (int)$id;
        $q = "UPDATE budgets SET status = 'For Approval' WHERE budget_id = $id AND status = 'Draft'";
        return mysqli_query($this->conn, $q);
    }

    public function cancelBudget($id) {
        $id = (int)$id;
        $q = "UPDATE budgets SET status = 'Cancelled' WHERE budget_id = $id AND status IN ('Draft', 'For Approval')";
        return mysqli_query($this->conn, $q);
    }

    public function approveBudget($id, $notes, $signature, $emp_user, $emp_name) {
        $id = (int)$id;
        $notes = mysqli_real_escape_string($this->conn, $notes);
        $signature = mysqli_real_escape_string($this->conn, $signature);
        $emp_user = (int)$emp_user;
        $emp_name = mysqli_real_escape_string($this->conn, $emp_name);

        mysqli_begin_transaction($this->conn);
        try {
            // Lock budget
            $res = mysqli_query($this->conn, "SELECT status, reference_no FROM budgets WHERE budget_id = $id FOR UPDATE");
            if (!$res || mysqli_num_rows($res) === 0) {
                throw new Exception("Budget not found.");
            }
            $row = mysqli_fetch_assoc($res);
            if ($row['status'] !== 'For Approval') {
                throw new Exception("Budget is not For Approval.");
            }

            $ref = 'FIN-BUD-' . date('Ymd') . '-' . rand(1000, 9999);
            $role = 'Finance Officer';

            $q1 = "INSERT INTO finance_approvals (approval_ref, document_type, related_id, approved_by, approver_name, approver_role, decision, e_signature, notes)
                   VALUES ('$ref', 'Budget', $id, $emp_user, '$emp_name', '$role', 'Approved', '$signature', '$notes')";
            
            if (!mysqli_query($this->conn, $q1)) {
                throw new Exception("Failed to insert approval.");
            }

            $q2 = "UPDATE budgets SET status = 'Approved' WHERE budget_id = $id";
            if (!mysqli_query($this->conn, $q2)) {
                throw new Exception("Failed to update budget status.");
            }

            mysqli_commit($this->conn);
            
            // Audit log
            mysqli_query($this->conn, "INSERT INTO audit_logs (user_id, action, details) VALUES ($emp_user, 'Approve Budget', 'Approved budget {$row['reference_no']}')");
            
            return ['success' => true, 'reference_no' => $row['reference_no']];
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getRemainingBudget($budget_id) {
        $budget_id = (int)$budget_id;
        $res = mysqli_query($this->conn, "SELECT allocated_budget FROM budgets WHERE budget_id = $budget_id");
        if (!$res || mysqli_num_rows($res) === 0) return 0;
        
        $budget = mysqli_fetch_assoc($res);
        $allocated = (float)$budget['allocated_budget'];

        $res2 = mysqli_query($this->conn, "SELECT SUM(amount) as total_expenses FROM expenses WHERE budget_id = $budget_id AND status IN ('Approved', 'Paid')");
        $expense = mysqli_fetch_assoc($res2);
        $total_expenses = (float)($expense['total_expenses'] ?? 0);

        return $allocated - $total_expenses;
    }
}
