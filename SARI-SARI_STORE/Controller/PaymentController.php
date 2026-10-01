<?php
// Controller/PaymentController.php

class PaymentController {
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
        
        $this->model->initializeTable();
        
        $statusFilter = $_GET['filter'] ?? null;
        if ($statusFilter === 'All') $statusFilter = null;
        
        $payments_q = $this->model->getPayments($statusFilter);
        $payments = [];
        if ($payments_q) {
            while ($row = mysqli_fetch_assoc($payments_q)) {
                $payments[] = $row;
            }
        }
        
        $message = $_SESSION['pay_msg'] ?? '';
        $msg_type = $_SESSION['pay_msg_type'] ?? '';
        unset($_SESSION['pay_msg'], $_SESSION['pay_msg_type']);

        require_once __DIR__ . '/../View/Finance_employee/payments.php';
    }

    public function handleAction($action) {
        $this->authorizeFinance();
        $user_id = $_SESSION['user_id'] ?? $_SESSION['emp_id'] ?? 1;

        switch ($action) {
            case 'create_request':
                $ap_id = intval($_POST['invoice_id']);
                $amount = floatval($_POST['amount']);
                $method = $_POST['payment_method'] ?? 'Other';
                $notes = $_POST['notes'] ?? '';
                
                $res = $this->model->createPaymentRequest($ap_id, $amount, $method, $notes, $user_id);
                $_SESSION['pay_msg'] = $res['message'];
                $_SESSION['pay_msg_type'] = $res['success'] ? 'success' : 'danger';
                
                $redirect = $_POST['redirect'] ?? 'finance_payments';
                echo "<script>
                        if(typeof window.parent.loadPage === 'function') {
                            window.parent.loadPage('../router.php?route={$redirect}');
                        } else {
                            window.location.href = 'admin_finance.php?page={$redirect}';
                        }
                      </script>";
                break;

            case 'approve':
                $id = intval($_POST['payment_id']);
                $notes = $_POST['notes'] ?? '';
                $signature = $_POST['e_signature'] ?? '';
                
                // Fetch user data for signature
                $emp_name = $_SESSION['emp_name'] ?? $_SESSION['full_name'] ?? 'Finance User';
                $account_type = isset($_SESSION['user_id']) ? 'User' : 'Employee';
                $account_id = $user_id;
                
                // Verify signature against registered_signatures
                // Instantiate FinanceModel to reuse getSignature method
                require_once __DIR__ . '/../Model/FinanceModel.php';
                global $conn; // Getting connection from router
                $finModel = new FinanceModel($conn);
                $stored_sig = $finModel->getSignature($account_id, $account_type);
                
                if (empty($stored_sig)) {
                    $_SESSION['pay_msg'] = "A registered E-Signature is required to approve payments. Please set it up in your Profile / Signature.";
                    $_SESSION['pay_msg_type'] = 'danger';
                } else {
                    $res = $this->model->approvePayment($id, $notes, $stored_sig, $account_id, $emp_name);
                    if ($res) {
                        $_SESSION['pay_msg'] = "Payment officially approved.";
                        $_SESSION['pay_msg_type'] = 'success';
                    } else {
                        $_SESSION['pay_msg'] = "Failed to approve payment.";
                        $_SESSION['pay_msg_type'] = 'danger';
                    }
                }
                echo "<script>
                        if(typeof window.parent.loadPage === 'function') {
                            window.parent.loadPage('../router.php?route=finance_payments');
                        }
                      </script>";
                break;

            case 'reject':
                $id = intval($_POST['payment_id']);
                $notes = $_POST['notes'] ?? '';
                $res = $this->model->rejectPayment($id, $notes);
                if ($res) {
                    $_SESSION['pay_msg'] = "Payment request rejected.";
                    $_SESSION['pay_msg_type'] = 'warning';
                }
                echo "<script>if(typeof window.parent.loadPage === 'function') { window.parent.loadPage('../router.php?route=finance_payments'); }</script>";
                break;

            case 'cancel':
                $id = intval($_POST['payment_id']);
                $notes = $_POST['notes'] ?? '';
                $res = $this->model->cancelPayment($id, $notes);
                if ($res) {
                    $_SESSION['pay_msg'] = "Payment cancelled.";
                    $_SESSION['pay_msg_type'] = 'warning';
                }
                echo "<script>if(typeof window.parent.loadPage === 'function') { window.parent.loadPage('../router.php?route=finance_payments'); }</script>";
                break;

            case 'mark_paid':
                $id = intval($_POST['payment_id']);
                $res = $this->model->recordPaymentPaid($id);
                $_SESSION['pay_msg'] = $res['message'];
                $_SESSION['pay_msg_type'] = $res['success'] ? 'success' : 'danger';
                
                echo "<script>if(typeof window.parent.loadPage === 'function') { window.parent.loadPage('../router.php?route=finance_payments'); }</script>";
                break;

            case 'get_details':
                $id = intval($_GET['payment_id']);
                $details = $this->model->getPaymentDetails($id);
                header('Content-Type: application/json');
                echo json_encode($details);
                break;
                
            case 'get_ap_balance':
                $ap_id = intval($_GET['invoice_id']);
                $bal = $this->model->getPayableBalance($ap_id);
                header('Content-Type: application/json');
                echo json_encode(['remaining_balance' => $bal]);
                break;
        }
    }
}
