<?php
// Model/AccountsPayableModel.php

class AccountsPayableModel {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function initializeTable() {
        mysqli_query($this->conn, "
            CREATE TABLE IF NOT EXISTS accounts_payable (
                id INT AUTO_INCREMENT PRIMARY KEY,
                invoice_number VARCHAR(50) NOT NULL UNIQUE,
                supplier_name VARCHAR(100) NOT NULL,
                supplier_order_id INT NOT NULL,
                purchase_request_id INT NULL,
                invoice_date DATE NOT NULL,
                due_date DATE NOT NULL,
                subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                tax DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                verification_status VARCHAR(50) DEFAULT 'Generated',
                payment_status VARCHAR(50) DEFAULT 'Unpaid',
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    public function getInvoices($statusFilter = null) {
        $q = "SELECT ap.*, so.order_code, pr.purchase_code 
              FROM accounts_payable ap
              LEFT JOIN supplier_orders so ON ap.supplier_order_id = so.order_id
              LEFT JOIN stock_purchase_requests pr ON ap.purchase_request_id = pr.purchase_id ";
        
        if ($statusFilter) {
            $status = mysqli_real_escape_string($this->conn, $statusFilter);
            $q .= " WHERE ap.verification_status = '$status' ";
        }
        $q .= " ORDER BY ap.created_at DESC";
        
        return mysqli_query($this->conn, $q);
    }

    public function getInvoiceById($id) {
        $id = (int)$id;
        $q = "SELECT ap.*, so.order_code, so.ordered_qty, so.arrived_at, pr.purchase_code, p.product_name
              FROM accounts_payable ap
              LEFT JOIN supplier_orders so ON ap.supplier_order_id = so.order_id
              LEFT JOIN stock_purchase_requests pr ON ap.purchase_request_id = pr.purchase_id
              LEFT JOIN products p ON so.product_id = p.product_id
              WHERE ap.id = $id LIMIT 1";
        
        $res = mysqli_query($this->conn, $q);
        return $res ? mysqli_fetch_assoc($res) : null;
    }

    public function updateVerificationStatus($id, $status, $notes, $user_id) {
        $id = (int)$id;
        $status = mysqli_real_escape_string($this->conn, $status);
        $notes = mysqli_real_escape_string($this->conn, $notes);
        
        $q = "UPDATE accounts_payable SET verification_status = '$status', notes = CONCAT(IFNULL(notes,''), '\nVerification: $notes') WHERE id = $id";
        return mysqli_query($this->conn, $q);
    }
}
