<?php
// Model/FinanceModel.php
class FinanceModel {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // --- Sales / Financial Reporting ---
    public function getSalesProducts() {
        $q = "SELECT DISTINCT p.product_id, p.product_name
              FROM sale_items si
              INNER JOIN products p ON si.product_id = p.product_id
              ORDER BY p.product_name ASC";
        return mysqli_query($this->conn, $q);
    }

    public function getSalesCategories() {
        $q = "SELECT DISTINCT c.category_id, c.category_name
              FROM sale_items si
              INNER JOIN products p ON si.product_id = p.product_id
              INNER JOIN categories c ON p.category_id = c.category_id";
        return mysqli_query($this->conn, $q);
    }

    // --- Stock Purchase Requests ---
    public function getPurchaseRequests() {
        $q = "SELECT pr.*, p.product_name, COALESCE(p.barcode, CONCAT('PRD-', p.product_id)) AS product_code, p.image, COALESCE(p.selling_price, 0) AS price
              FROM stock_purchase_requests pr
              JOIN products p ON pr.product_id = p.product_id
              ORDER BY pr.created_at DESC";
        return mysqli_query($this->conn, $q);
    }

    public function getPurchaseRequestById($pid) {
        $pid = (int)$pid;
        $q = "SELECT pr.*, p.product_name 
              FROM stock_purchase_requests pr 
              JOIN products p ON pr.product_id = p.product_id 
              WHERE pr.purchase_id = $pid LIMIT 1";
        $res = mysqli_query($this->conn, $q);
        return $res ? mysqli_fetch_assoc($res) : null;
    }
    
    public function getSignedLetter($pid) {
        $pid = (int)$pid;
        $q = "SELECT fa.*, pr.purchase_code, pr.requested_qty, pr.supplier_name, pr.estimated_cost, pr.requested_by, p.product_name 
              FROM finance_approvals fa 
              JOIN stock_purchase_requests pr ON fa.related_id = pr.purchase_id
              JOIN products p ON pr.product_id = p.product_id
              WHERE fa.document_type = 'Stock Purchase' AND fa.related_id = $pid LIMIT 1";
        $res = mysqli_query($this->conn, $q);
        return $res ? mysqli_fetch_assoc($res) : null;
    }

    public function approvePurchaseRequest($pid, $notes, $signature, $emp_user, $emp_name) {
        $pid = (int)$pid;
        $notes = mysqli_real_escape_string($this->conn, $notes);
        $signature = mysqli_real_escape_string($this->conn, $signature);
        
        $pr = $this->getPurchaseRequestById($pid);
        if (!$pr) return false;

        $role = 'Finance Officer';
        $ref = 'FIN-APP-' . date('Ymd') . '-' . rand(1000, 9999);

        // 1. Insert into finance_approvals
        mysqli_query($this->conn, "
            INSERT INTO finance_approvals (approval_ref, document_type, related_id, approved_by, approver_name, approver_role, decision, e_signature, notes)
            VALUES ('$ref', 'Stock Purchase', $pid, $emp_user, '$emp_name', '$role', 'Approved', '$signature', '$notes')
        ");
        
        // 2. Update purchase request status
        mysqli_query($this->conn, "UPDATE stock_purchase_requests SET status = 'Approved by Finance', finance_notes = '$notes' WHERE purchase_id = $pid");
        
        // 3. Create Order in Order Monitoring (Warehouse)
        $po_code = 'PO-' . date('Ymd') . '-' . rand(1000, 9999);
        $supplier = mysqli_real_escape_string($this->conn, $pr['supplier_name']);
        $est_cost = (float)($pr['estimated_cost'] ?? 0);
        $req_qty  = (int)($pr['requested_qty'] ?? 1);
        
        mysqli_query($this->conn, "
            INSERT INTO supplier_orders (order_code, purchase_id, product_id, ordered_qty, supplier_name, expected_date, status)
            VALUES ('$po_code', $pid, {$pr['product_id']}, {$pr['requested_qty']}, '$supplier', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'Not Arrived')
        ");

        // 4. Log restock expense entry in restock_logs for Finance & Sales reporting
        mysqli_query($this->conn, "
            INSERT INTO restock_logs (product_id, boxes_received, units_per_box, pieces_added, cost_per_box, total_cost, new_cost_per_piece, new_selling_price, supplier, delivery_note, restocked_by, restocked_at)
            VALUES ({$pr['product_id']}, 1, $req_qty, $req_qty, $est_cost, $est_cost, 0, 0, '$supplier', 'Finance Approved Stock Purchase Request #{$pr['purchase_code']}', $emp_user, NOW())
        ");

        return ['po_code' => $po_code, 'purchase_code' => $pr['purchase_code']];
    }

    public function rejectPurchaseRequest($pid, $notes) {
        $pid = (int)$pid;
        $notes = mysqli_real_escape_string($this->conn, $notes);
        mysqli_query($this->conn, "UPDATE stock_purchase_requests SET status = 'Rejected by Finance', finance_notes = '$notes' WHERE purchase_id = $pid");
        
        $pr = $this->getPurchaseRequestById($pid);
        return $pr ? $pr['purchase_code'] : null;
    }

    // --- Restocking (Direct Requisitions) ---
    public function getPendingRestockRequisitions() {
        $q = "SELECT r.*, p.product_name, p.barcode, p.selling_price, p.cost_price, p.description,
                     c.category_name, i.quantity AS current_stock, i.minimum_stock
              FROM stock_requisitions r
              JOIN products p ON r.product_id = p.product_id
              LEFT JOIN inventory i ON p.product_id = i.product_id
              LEFT JOIN categories c ON p.category_id = c.category_id
              WHERE r.status = 'Pending Procurement' OR r.status = 'Procurement Processing'
              ORDER BY r.created_at DESC";
        return mysqli_query($this->conn, $q);
    }
    
    public function getHistoryRestockRequisitions($status_filter) {
        $q = "SELECT r.*, p.product_name, p.barcode, p.selling_price, p.cost_price,
                     c.category_name, i.quantity AS current_stock
              FROM stock_requisitions r
              JOIN products p ON r.product_id = p.product_id
              LEFT JOIN inventory i ON p.product_id = i.product_id
              LEFT JOIN categories c ON p.category_id = c.category_id
              WHERE $status_filter
              ORDER BY r.created_at DESC";
        return mysqli_query($this->conn, $q);
    }

    public function getRestockRequisitionById($req_id) {
        $req_id = (int)$req_id;
        $q = "SELECT * FROM stock_requisitions WHERE requisition_id = $req_id LIMIT 1";
        $res = mysqli_query($this->conn, $q);
        return $res ? mysqli_fetch_assoc($res) : null;
    }

    public function updateRestockRequisitionStatus($req_id, $new_status, $notes, $ref_no) {
        $req_id = (int)$req_id;
        $new_status = mysqli_real_escape_string($this->conn, $new_status);
        $notes = mysqli_real_escape_string($this->conn, $notes);
        $ref_no = mysqli_real_escape_string($this->conn, $ref_no);
        
        $reqRes = $this->getRestockRequisitionById($req_id);
        if(!$reqRes) return false;

        $product_id = (int)$reqRes['product_id'];
        $qty_added = (int)$reqRes['requested_qty'];

        mysqli_query($this->conn, "UPDATE stock_requisitions SET status = '$new_status' WHERE requisition_id = $req_id");

        if ($new_status === 'Approved Finance') {
            // 1. Check or insert into inventory table
            $invCheck = mysqli_query($this->conn, "SELECT inventory_id FROM inventory WHERE product_id = $product_id LIMIT 1");
            if(mysqli_num_rows($invCheck) > 0){
                $invRow = mysqli_fetch_assoc($invCheck);
                $inventory_id = $invRow['inventory_id'];
                mysqli_query($this->conn, "UPDATE inventory SET quantity = quantity + $qty_added, last_restock = NOW() WHERE inventory_id = $inventory_id");
            } else {
                mysqli_query($this->conn, "INSERT INTO inventory (product_id, quantity, minimum_stock, last_restock) VALUES ($product_id, $qty_added, 5, NOW())");
                $inventory_id = mysqli_insert_id($this->conn);
            }

            // 2. Update Product status
            mysqli_query($this->conn, "UPDATE products SET status = 'Available' WHERE product_id = $product_id");

            // 3. Insert into stock_movements table for audit trail
            mysqli_query($this->conn, "INSERT INTO stock_movements (inventory_id, type, quantity, reference_no, supplier, notes, moved_by, moved_at) VALUES ($inventory_id, 'Stock In', $qty_added, '$ref_no', 'Approved Restock', '$notes', 1, NOW())");
        }
        return true;
    }

    // --- Payroll Approvals ---
    public function getPendingPayrollDrafts() {
        // Based on the query in finance_payroll.php, HRMS module
        // We'll require the exact query format as previously present, using payroll_periods 
        $q = "SELECT p.*, COUNT(s.id) as employee_count, SUM(s.net_pay) as total_amount 
              FROM payroll_periods p 
              LEFT JOIN payroll s ON p.id = s.period_id 
              WHERE p.status = 'Draft' OR p.status = 'Approved Finance' OR p.status = 'Rejected Finance'
              GROUP BY p.id ORDER BY p.id DESC";
        // NOTE: Actually, we need to check if these tables exist. 
        // We'll run the query directly as written in finance_payroll.php
        return mysqli_query($this->conn, $q);
    }
    
    public function getPayrollPeriodById($period_id) {
        $period_id = (int)$period_id;
        $q = "SELECT p.*, COUNT(s.id) as employee_count, SUM(s.net_pay) as total_amount 
              FROM payroll_periods p 
              LEFT JOIN payroll s ON p.id = s.period_id 
              WHERE p.id = $period_id GROUP BY p.id LIMIT 1";
        $res = mysqli_query($this->conn, $q);
        return $res ? mysqli_fetch_assoc($res) : null;
    }

    public function getPayrollSignedLetter($period_id) {
        $period_id = (int)$period_id;
        $q = "SELECT fa.*, p.period_start, p.period_end 
              FROM finance_approvals fa 
              JOIN payroll_periods p ON fa.related_id = p.id
              WHERE fa.document_type = 'Payroll' AND fa.related_id = $period_id LIMIT 1";
        $res = mysqli_query($this->conn, $q);
        return $res ? mysqli_fetch_assoc($res) : null;
    }

    public function approvePayroll($period_id, $notes, $signature, $emp_user, $emp_name) {
        $period_id = (int)$period_id;
        $notes = mysqli_real_escape_string($this->conn, $notes);
        $signature = mysqli_real_escape_string($this->conn, $signature);
        
        $role = 'Finance Officer';
        $ref = 'PAY-APP-' . date('Ymd') . '-' . rand(1000, 9999);

        // 1. Insert into finance_approvals
        mysqli_query($this->conn, "
            INSERT INTO finance_approvals (approval_ref, document_type, related_id, approved_by, approver_name, approver_role, decision, e_signature, notes)
            VALUES ('$ref', 'Payroll', $period_id, $emp_user, '$emp_name', '$role', 'Approved', '$signature', '$notes')
        ");
        
        // 2. Update status
        mysqli_query($this->conn, "UPDATE payroll_periods SET status = 'Approved Finance', finance_notes = '$notes' WHERE id = $period_id");
        return true;
    }

    public function rejectPayroll($period_id, $notes) {
        $period_id = (int)$period_id;
        $notes = mysqli_real_escape_string($this->conn, $notes);
        mysqli_query($this->conn, "UPDATE payroll_periods SET status = 'Rejected Finance', finance_notes = '$notes' WHERE id = $period_id");
        return true;
    }

    // --- Profile / Signature ---
    public function getSignature($emp_user, $account_type) {
        $emp_user = (int)$emp_user;
        $account_type = mysqli_real_escape_string($this->conn, $account_type);
        $q = "SELECT e_signature FROM registered_signatures WHERE account_id = $emp_user AND account_type = '$account_type' LIMIT 1";
        $res = mysqli_query($this->conn, $q);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        return $row ? $row['e_signature'] : null;
    }

    public function saveSignature($emp_user, $account_type, $signature) {
        $emp_user = (int)$emp_user;
        $account_type = mysqli_real_escape_string($this->conn, $account_type);
        $signature = mysqli_real_escape_string($this->conn, $signature);
        
        $q = "INSERT INTO registered_signatures (account_id, account_type, e_signature) 
              VALUES ($emp_user, '$account_type', '$signature')
              ON DUPLICATE KEY UPDATE e_signature = VALUES(e_signature), updated_at = NOW()";
        return mysqli_query($this->conn, $q);
    }
}
