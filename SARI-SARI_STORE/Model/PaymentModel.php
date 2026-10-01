<?php
// Model/PaymentModel.php

class PaymentModel {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function initializeTable() {
        mysqli_query($this->conn, "
            CREATE TABLE IF NOT EXISTS payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                accounts_payable_id INT NOT NULL,
                payment_reference VARCHAR(50) NOT NULL UNIQUE,
                payment_method VARCHAR(50) NOT NULL,
                payment_date DATE NULL,
                amount DECIMAL(10,2) NOT NULL,
                status VARCHAR(50) DEFAULT 'For Approval',
                requested_by INT NOT NULL,
                approved_by INT NULL,
                approved_at DATETIME NULL,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (accounts_payable_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    public function getPayments($statusFilter = null) {
        $q = "SELECT p.*, ap.invoice_number, ap.supplier_name, ap.total_amount as invoice_total 
              FROM payments p
              JOIN accounts_payable ap ON p.accounts_payable_id = ap.id ";
        
        if ($statusFilter) {
            $status = mysqli_real_escape_string($this->conn, $statusFilter);
            $q .= " WHERE p.status = '$status' ";
        }
        $q .= " ORDER BY p.created_at DESC";
        
        return mysqli_query($this->conn, $q);
    }

    public function getPaymentDetails($id) {
        $id = (int)$id;
        $q = "SELECT p.*, ap.invoice_number, ap.supplier_name, ap.invoice_date, ap.total_amount as invoice_total 
              FROM payments p
              JOIN accounts_payable ap ON p.accounts_payable_id = ap.id
              WHERE p.id = $id LIMIT 1";
        $res = mysqli_query($this->conn, $q);
        return $res ? mysqli_fetch_assoc($res) : null;
    }

    public function getPayableBalance($ap_id) {
        $ap_id = (int)$ap_id;
        // MUST be called inside a transaction if used for concurrent protection
        
        // 1. Get AP invoice total
        $ap_res = mysqli_query($this->conn, "SELECT total_amount FROM accounts_payable WHERE id = $ap_id FOR UPDATE");
        if (!$ap_res || mysqli_num_rows($ap_res) == 0) return -1; // Invalid AP
        
        $ap_row = mysqli_fetch_assoc($ap_res);
        $total_amount = (float)$ap_row['total_amount'];

        // 2. Get sum of non-cancelled payments
        $pay_res = mysqli_query($this->conn, "SELECT COALESCE(SUM(amount), 0) as paid_sum FROM payments WHERE accounts_payable_id = $ap_id AND status != 'Cancelled'");
        $pay_row = mysqli_fetch_assoc($pay_res);
        $paid_sum = (float)$pay_row['paid_sum'];

        return round($total_amount - $paid_sum, 2);
    }

    public function getActualPaidTotal($ap_id) {
        $ap_id = (int)$ap_id;
        // Gets only the amount actually Paid (for AP status updates)
        $pay_res = mysqli_query($this->conn, "SELECT COALESCE(SUM(amount), 0) as actually_paid FROM payments WHERE accounts_payable_id = $ap_id AND status = 'Paid'");
        $pay_row = mysqli_fetch_assoc($pay_res);
        return (float)$pay_row['actually_paid'];
    }

    public function createPaymentRequest($ap_id, $amount, $method, $notes, $user_id) {
        $ap_id = (int)$ap_id;
        $amount = (float)$amount;
        $method = mysqli_real_escape_string($this->conn, $method);
        $notes = mysqli_real_escape_string($this->conn, $notes);
        $user_id = (int)$user_id;

        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Invalid payment amount.'];
        }

        mysqli_begin_transaction($this->conn);
        try {
            // Check eligibility
            $ap_res = mysqli_query($this->conn, "SELECT verification_status, payment_status FROM accounts_payable WHERE id = $ap_id");
            $ap_row = mysqli_fetch_assoc($ap_res);
            if (!$ap_row || $ap_row['verification_status'] !== 'Verified') {
                throw new Exception("Invoice is not verified or does not exist.");
            }
            if ($ap_row['payment_status'] === 'Paid' || $ap_row['payment_status'] === 'Cancelled') {
                throw new Exception("Invoice is already paid or cancelled.");
            }

            // Concurrency check
            $remaining = $this->getPayableBalance($ap_id);
            if ($remaining < 0) {
                throw new Exception("Invalid AP invoice.");
            }
            if ($amount > $remaining) {
                throw new Exception("Payment amount (₱" . number_format($amount, 2) . ") exceeds remaining balance (₱" . number_format($remaining, 2) . ").");
            }

            // Create
            $ref = 'PAY-' . date('Ymd') . '-' . rand(1000, 9999);
            // Ensure unique reference
            while (mysqli_num_rows(mysqli_query($this->conn, "SELECT id FROM payments WHERE payment_reference = '$ref' LIMIT 1")) > 0) {
                $ref = 'PAY-' . date('Ymd') . '-' . rand(1000, 9999);
            }

            if (!mysqli_query($this->conn, "
                INSERT INTO payments (accounts_payable_id, payment_reference, payment_method, amount, status, requested_by, notes)
                VALUES ($ap_id, '$ref', '$method', $amount, 'For Approval', $user_id, '$notes')
            ")) {
                throw new Exception("Database error while creating payment.");
            }
            
            // Notification
            $ap_num_res = mysqli_query($this->conn, "SELECT invoice_number FROM accounts_payable WHERE id=$ap_id");
            $ap_num = mysqli_fetch_assoc($ap_num_res)['invoice_number'];
            mysqli_query($this->conn, "
                INSERT INTO notifications (title, message, type, is_read)
                VALUES (
                    'New Payment Request',
                    'Payment request $ref for Invoice $ap_num is awaiting your approval.',
                    'Finance',
                    0
                )
            ");

            mysqli_commit($this->conn);
            return ['success' => true, 'message' => "Payment request $ref created successfully."];

        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function approvePayment($payment_id, $notes, $signature, $emp_user, $emp_name) {
        $payment_id = (int)$payment_id;
        $notes = mysqli_real_escape_string($this->conn, $notes);
        $signature = mysqli_real_escape_string($this->conn, $signature);
        
        $payment = $this->getPaymentDetails($payment_id);
        if (!$payment || $payment['status'] !== 'For Approval') return false;

        $role = 'Finance Officer';
        $ref = 'PAY-APP-' . date('Ymd') . '-' . rand(1000, 9999);

        mysqli_begin_transaction($this->conn);
        try {
            // 1. Insert into finance_approvals
            if (!mysqli_query($this->conn, "
                INSERT INTO finance_approvals (approval_ref, document_type, related_id, approved_by, approver_name, approver_role, decision, e_signature, notes)
                VALUES ('$ref', 'AP Payment', $payment_id, $emp_user, '$emp_name', '$role', 'Approved', '$signature', '$notes')
            ")) throw new Exception("Failed to record approval.");
            
            // 2. Update status
            if (!mysqli_query($this->conn, "
                UPDATE payments 
                SET status = 'Approved', approved_by = $emp_user, approved_at = NOW(), notes = CONCAT(IFNULL(notes,''), '\nApproval: $notes') 
                WHERE id = $payment_id
            ")) throw new Exception("Failed to update payment status.");
            
            mysqli_commit($this->conn);
            return true;
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return false;
        }
    }

    public function rejectPayment($payment_id, $notes) {
        $payment_id = (int)$payment_id;
        $notes = mysqli_real_escape_string($this->conn, $notes);
        
        $q = "UPDATE payments SET status = 'Cancelled', notes = CONCAT(IFNULL(notes,''), '\nRejected: $notes') WHERE id = $payment_id AND status = 'For Approval'";
        return mysqli_query($this->conn, $q);
    }
    
    public function cancelPayment($payment_id, $notes) {
        $payment_id = (int)$payment_id;
        $notes = mysqli_real_escape_string($this->conn, $notes);
        
        $q = "UPDATE payments SET status = 'Cancelled', notes = CONCAT(IFNULL(notes,''), '\nCancelled: $notes') WHERE id = $payment_id AND status IN ('For Approval', 'Approved')";
        return mysqli_query($this->conn, $q);
    }

    public function recordPaymentPaid($payment_id) {
        $payment_id = (int)$payment_id;

        mysqli_begin_transaction($this->conn);
        try {
            // 1. Lock payment & check status
            $p_res = mysqli_query($this->conn, "SELECT accounts_payable_id, amount, status, payment_reference FROM payments WHERE id = $payment_id FOR UPDATE");
            if (!$p_res || mysqli_num_rows($p_res) == 0) throw new Exception("Payment not found.");
            $payment = mysqli_fetch_assoc($p_res);
            
            if ($payment['status'] !== 'Approved') {
                throw new Exception("Only approved payments can be marked as Paid.");
            }

            $ap_id = (int)$payment['accounts_payable_id'];
            $pay_amount = (float)$payment['amount'];

            // 2. Recheck balance
            // Because getPayableBalance does a SELECT FOR UPDATE on AP, it safely locks it
            $remaining = $this->getPayableBalance($ap_id);
            if ($remaining < 0) throw new Exception("Invalid AP invoice.");
            
            // The getPayableBalance calculation SUBTRACTS all active payments, INCLUDING this one which is currently 'Approved'.
            // So we don't check `$pay_amount > $remaining` here because `$remaining` already has this `$pay_amount` subtracted.
            // Wait, if we check `$remaining < 0`, that means active payments exceed invoice total, which shouldn't happen.
            if ($remaining < 0) throw new Exception("Critical Error: Total active payments exceed invoice total.");

            // 3. Mark payment as Paid
            if (!mysqli_query($this->conn, "
                UPDATE payments 
                SET status = 'Paid', payment_date = CURDATE() 
                WHERE id = $payment_id
            ")) throw new Exception("Failed to update payment status.");

            // 4. Recalculate AP Status
            $actually_paid = $this->getActualPaidTotal($ap_id);
            
            // Get invoice total
            $ap_res = mysqli_query($this->conn, "SELECT total_amount FROM accounts_payable WHERE id = $ap_id");
            $invoice_total = (float)mysqli_fetch_assoc($ap_res)['total_amount'];
            
            $new_ap_status = 'Unpaid';
            if ($actually_paid >= $invoice_total) {
                $new_ap_status = 'Paid';
            } elseif ($actually_paid > 0) {
                $new_ap_status = 'Partially Paid';
            }
            
            if (!mysqli_query($this->conn, "
                UPDATE accounts_payable 
                SET payment_status = '$new_ap_status' 
                WHERE id = $ap_id
            ")) throw new Exception("Failed to update AP status.");
            
            // 5. Notify
            mysqli_query($this->conn, "
                INSERT INTO notifications (title, message, type, is_read)
                VALUES (
                    'Payment Disbursed',
                    'Payment {$payment['payment_reference']} has been recorded as Paid.',
                    'Finance',
                    0
                )
            ");

            mysqli_commit($this->conn);
            return ['success' => true, 'message' => "Payment successfully recorded as Paid."];

        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
