<?php
// Model/ProcurementModel.php

class ProcurementModel {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function initializeTables() {
        mysqli_query($this->conn, "
            CREATE TABLE IF NOT EXISTS suppliers (
                supplier_id INT AUTO_INCREMENT PRIMARY KEY,
                supplier_name VARCHAR(150) NOT NULL UNIQUE,
                contact_person VARCHAR(100),
                contact_number VARCHAR(50),
                email VARCHAR(100),
                address TEXT,
                status ENUM('Active', 'Inactive') DEFAULT 'Active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    /**
     * Get PRs that are Pending Procurement (need supplier/quote)
     */
    public function getPendingRequests() {
        $q = "SELECT pr.*, p.product_name, COALESCE(p.barcode, CONCAT('PRD-', p.product_id)) AS product_code, p.image, COALESCE(p.selling_price, 0) AS price
              FROM stock_purchase_requests pr
              JOIN products p ON pr.product_id = p.product_id
              WHERE pr.status = 'Pending Procurement'
              ORDER BY pr.created_at DESC";
        return mysqli_query($this->conn, $q);
    }

    /**
     * Get PRs that are Approved by Finance (ready for PO generation)
     */
    public function getApprovedRequests() {
        $q = "SELECT pr.*, p.product_name, COALESCE(p.barcode, CONCAT('PRD-', p.product_id)) AS product_code, p.image, COALESCE(p.selling_price, 0) AS price
              FROM stock_purchase_requests pr
              JOIN products p ON pr.product_id = p.product_id
              WHERE pr.status = 'Approved by Finance'
              ORDER BY pr.created_at DESC";
        return mysqli_query($this->conn, $q);
    }

    /**
     * Update quotation and push to Finance
     */
    public function updateRequestQuotation($purchase_id, $supplier_name, $estimated_cost) {
        $pid = (int)$purchase_id;
        $supplier = mysqli_real_escape_string($this->conn, $supplier_name);
        $cost = (float)$estimated_cost;
        
        $q = "UPDATE stock_purchase_requests 
              SET supplier_name = '$supplier', estimated_cost = $cost, status = 'Pending Finance Approval' 
              WHERE purchase_id = $pid AND status = 'Pending Procurement'";
        return mysqli_query($this->conn, $q);
    }

    /**
     * Generate PO from an Approved PR
     */
    public function generatePurchaseOrder($purchase_id) {
        $pid = (int)$purchase_id;

        // 1. Check if PR exists and is approved
        $q = "SELECT * FROM stock_purchase_requests WHERE purchase_id = $pid AND status = 'Approved by Finance' LIMIT 1";
        $res = mysqli_query($this->conn, $q);
        if (!$res || mysqli_num_rows($res) === 0) {
            return ['success' => false, 'message' => 'Purchase Request not found or not approved by Finance.'];
        }
        $pr = mysqli_fetch_assoc($res);

        // 2. Prevent duplicate POs
        $checkPO = mysqli_query($this->conn, "SELECT order_id FROM supplier_orders WHERE purchase_id = $pid LIMIT 1");
        if (mysqli_num_rows($checkPO) > 0) {
            return ['success' => false, 'message' => 'A Purchase Order already exists for this Request.'];
        }

        // 3. Create the PO
        $po_code = 'PO-' . date('Ymd') . '-' . rand(1000, 9999);
        $supplier = mysqli_real_escape_string($this->conn, $pr['supplier_name']);
        
        $ins = mysqli_query($this->conn, "
            INSERT INTO supplier_orders (order_code, purchase_id, product_id, ordered_qty, supplier_name, expected_date, status)
            VALUES ('$po_code', $pid, {$pr['product_id']}, {$pr['requested_qty']}, '$supplier', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'Not Arrived')
        ");

        if ($ins) {
            // Update PR status to indicate PO was generated
            mysqli_query($this->conn, "UPDATE stock_purchase_requests SET status = 'PO Generated' WHERE purchase_id = $pid");
            return ['success' => true, 'message' => "Purchase Order $po_code successfully generated and sent to supplier!"];
        }

        return ['success' => false, 'message' => 'Failed to insert into supplier_orders.'];
    }

    /**
     * Fetch all POs
     */
    public function getPurchaseOrders() {
        $q = "SELECT so.*, p.product_name, COALESCE(p.barcode, CONCAT('PRD-', p.product_id)) AS product_code, p.image, pr.estimated_cost
              FROM supplier_orders so
              JOIN products p ON so.product_id = p.product_id
              LEFT JOIN stock_purchase_requests pr ON so.purchase_id = pr.purchase_id
              ORDER BY so.created_at DESC";
        return mysqli_query($this->conn, $q);
    }

    // --- Supplier Information Management (SIM) ---

    public function getSuppliers($status = 'Active') {
        $q = "SELECT * FROM suppliers";
        if ($status !== 'All') {
            $q .= " WHERE status = '" . mysqli_real_escape_string($this->conn, $status) . "'";
        }
        $q .= " ORDER BY supplier_name ASC";
        return mysqli_query($this->conn, $q);
    }

    public function addSupplier($name, $contact, $number, $email, $address) {
        $name = mysqli_real_escape_string($this->conn, $name);
        $contact = mysqli_real_escape_string($this->conn, $contact);
        $number = mysqli_real_escape_string($this->conn, $number);
        $email = mysqli_real_escape_string($this->conn, $email);
        $address = mysqli_real_escape_string($this->conn, $address);

        $q = "INSERT INTO suppliers (supplier_name, contact_person, contact_number, email, address) 
              VALUES ('$name', '$contact', '$number', '$email', '$address')";
        return mysqli_query($this->conn, $q);
    }

    public function updateSupplier($id, $name, $contact, $number, $email, $address, $status) {
        $id = (int)$id;
        $name = mysqli_real_escape_string($this->conn, $name);
        $contact = mysqli_real_escape_string($this->conn, $contact);
        $number = mysqli_real_escape_string($this->conn, $number);
        $email = mysqli_real_escape_string($this->conn, $email);
        $address = mysqli_real_escape_string($this->conn, $address);
        $status = mysqli_real_escape_string($this->conn, $status);

        $q = "UPDATE suppliers SET 
              supplier_name = '$name', contact_person = '$contact', contact_number = '$number', 
              email = '$email', address = '$address', status = '$status' 
              WHERE supplier_id = $id";
        return mysqli_query($this->conn, $q);
    }

    // --- Dashboard & History ---

    public function getDashboardMetrics() {
        $metrics = [
            'pending_sourcing' => 0,
            'pending_finance' => 0,
            'ready_po' => 0,
            'active_po' => 0,
            'completed' => 0
        ];

        // pending sourcing
        $q1 = mysqli_query($this->conn, "SELECT COUNT(*) as c FROM stock_purchase_requests WHERE status = 'Pending Procurement'");
        if($r = mysqli_fetch_assoc($q1)) $metrics['pending_sourcing'] = (int)$r['c'];

        // pending finance
        $q2 = mysqli_query($this->conn, "SELECT COUNT(*) as c FROM stock_purchase_requests WHERE status = 'Pending Finance Approval'");
        if($r = mysqli_fetch_assoc($q2)) $metrics['pending_finance'] = (int)$r['c'];

        // ready for PO
        $q3 = mysqli_query($this->conn, "SELECT COUNT(*) as c FROM stock_purchase_requests WHERE status = 'Approved by Finance'");
        if($r = mysqli_fetch_assoc($q3)) $metrics['ready_po'] = (int)$r['c'];

        // active PO
        $q4 = mysqli_query($this->conn, "SELECT COUNT(*) as c FROM supplier_orders WHERE status = 'Not Arrived'");
        if($r = mysqli_fetch_assoc($q4)) $metrics['active_po'] = (int)$r['c'];

        // completed PO
        $q5 = mysqli_query($this->conn, "SELECT COUNT(*) as c FROM supplier_orders WHERE status = 'Arrived'");
        if($r = mysqli_fetch_assoc($q5)) $metrics['completed'] = (int)$r['c'];

        return $metrics;
    }

    public function getRecentPurchaseRequests($limit = 5) {
        $q = "SELECT pr.*, p.product_name, COALESCE(p.selling_price, 0) AS price
              FROM stock_purchase_requests pr
              JOIN products p ON pr.product_id = p.product_id
              ORDER BY pr.created_at DESC LIMIT " . (int)$limit;
        return mysqli_query($this->conn, $q);
    }

    public function getRecentPurchaseOrders($limit = 5) {
        $q = "SELECT so.*, p.product_name, pr.estimated_cost
              FROM supplier_orders so
              JOIN products p ON so.product_id = p.product_id
              LEFT JOIN stock_purchase_requests pr ON so.purchase_id = pr.purchase_id
              ORDER BY so.created_at DESC LIMIT " . (int)$limit;
        return mysqli_query($this->conn, $q);
    }

    public function getProcurementHistory($search = '', $supplier = '', $status = '', $startDate = '', $endDate = '') {
        $where = ["1=1"];
        
        if ($search !== '') {
            $s = mysqli_real_escape_string($this->conn, $search);
            $where[] = "(pr.purchase_code LIKE '%$s%' OR so.order_code LIKE '%$s%' OR p.product_name LIKE '%$s%')";
        }
        
        if ($supplier !== '') {
            $sup = mysqli_real_escape_string($this->conn, $supplier);
            $where[] = "pr.supplier_name = '$sup'";
        }
        
        if ($status !== '') {
            $st = mysqli_real_escape_string($this->conn, $status);
            $where[] = "(so.status = '$st' OR (so.status IS NULL AND pr.status = '$st'))";
        }

        if ($startDate !== '') {
            $sd = mysqli_real_escape_string($this->conn, $startDate);
            $where[] = "DATE(pr.created_at) >= '$sd'";
        }

        if ($endDate !== '') {
            $ed = mysqli_real_escape_string($this->conn, $endDate);
            $where[] = "DATE(pr.created_at) <= '$ed'";
        }

        $where_clause = implode(" AND ", $where);

        $q = "SELECT pr.purchase_code, pr.supplier_name, pr.requested_qty, pr.estimated_cost, pr.status as pr_status, pr.created_at as pr_date,
                     so.order_code, so.status as po_status, so.arrived_at, so.created_at as po_date,
                     p.product_name
              FROM stock_purchase_requests pr
              JOIN products p ON pr.product_id = p.product_id
              LEFT JOIN supplier_orders so ON pr.purchase_id = so.purchase_id
              WHERE $where_clause
              ORDER BY pr.created_at DESC";
              
        return mysqli_query($this->conn, $q);
    }
}
