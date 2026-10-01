<?php
// Controller/AccountsPayableController.php

class AccountsPayableController {
    private $model;

    public function __construct($model) {
        $this->model = $model;
    }

    private function authorizeFinance() {
        if (!isset($_SESSION['role']) || (strtolower($_SESSION['role']) !== 'admin' && strtolower($_SESSION['role']) !== 'finance officer')) {
            http_response_code(403);
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                echo json_encode(['status' => 'error', 'message' => 'Unauthorized Access']);
            } else {
                echo "<div class='alert alert-danger'>Access Denied. Finance clearance required.</div>";
            }
            exit();
        }
    }

    public function index() {
        $this->authorizeFinance();
        
        // Ensure table exists
        $this->model->initializeTable();
        
        $statusFilter = $_GET['filter'] ?? null;
        if ($statusFilter === 'All') $statusFilter = null;
        
        $invoices_q = $this->model->getInvoices($statusFilter);
        $invoices = [];
        if ($invoices_q) {
            while ($row = mysqli_fetch_assoc($invoices_q)) {
                $invoices[] = $row;
            }
        }
        
        $message = $_SESSION['ap_msg'] ?? '';
        $msg_type = $_SESSION['ap_msg_type'] ?? '';
        unset($_SESSION['ap_msg'], $_SESSION['ap_msg_type']);

        require_once __DIR__ . '/../View/Finance_employee/accounts_payable.php';
    }

    public function handleAction($action) {
        $this->authorizeFinance();
        $user_id = $_SESSION['user_id'] ?? 1;

        switch ($action) {
            case 'verify':
            case 'reject':
                $id = intval($_POST['invoice_id']);
                $notes = $_POST['notes'] ?? '';
                $status = ($action === 'verify') ? 'Verified' : 'Rejected';
                
                $res = $this->model->updateVerificationStatus($id, $status, $notes, $user_id);
                if ($res) {
                    $_SESSION['ap_msg'] = "Invoice #{$id} marked as {$status}.";
                    $_SESSION['ap_msg_type'] = $action === 'verify' ? 'success' : 'warning';
                } else {
                    $_SESSION['ap_msg'] = "Failed to update invoice.";
                    $_SESSION['ap_msg_type'] = 'danger';
                }
                
                echo "<script>
                        if(typeof window.parent.loadPage === 'function') {
                            window.parent.loadPage('../router.php?route=finance_ap');
                        } else {
                            window.location.href = 'admin_finance.php?page=finance_ap';
                        }
                      </script>";
                break;
                
            case 'get_details':
                $id = intval($_GET['invoice_id']);
                $details = $this->model->getInvoiceById($id);
                header('Content-Type: application/json');
                echo json_encode($details);
                break;
        }
    }
}
