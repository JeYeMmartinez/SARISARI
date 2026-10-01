<?php
// Controller/ProcurementController.php

class ProcurementController {
    private $model;

    public function __construct($model) {
        $this->model = $model;
        $this->model->initializeTables();
    }

    private function authorizeProcurement() {
        if (!isset($_SESSION['user_id']) || (strtolower($_SESSION['role']) !== 'admin' && strtolower($_SESSION['role']) !== 'procurement officer')) {
            header("Location: /login");
            exit;
        }
    }

    public function dashboard() {
        if (!defined('IN_APP')) exit;
        $this->authorizeProcurement();
        
        $metrics = $this->model->getDashboardMetrics();
        $recentPRs = $this->model->getRecentPurchaseRequests(5);
        $recentPOs = $this->model->getRecentPurchaseOrders(5);
        
        include __DIR__ . '/../View/procurement/procurement_dashboard.php';
    }

    public function requests() {
        if (!defined('IN_APP')) exit;
        $this->authorizeProcurement();
        
        $pendingRequests = $this->model->getPendingRequests();
        $approvedRequests = $this->model->getApprovedRequests();
        $suppliers = $this->model->getSuppliers('Active');
        
        include __DIR__ . '/../View/procurement/procurement_requests.php';
    }

    public function suppliers() {
        if (!defined('IN_APP')) exit;
        $this->authorizeProcurement();
        
        $suppliers = $this->model->getSuppliers('All');
        
        include __DIR__ . '/../View/procurement/supplier_management.php';
    }

    public function purchaseOrders() {
        if (!defined('IN_APP')) exit;
        $this->authorizeProcurement();
        
        $purchaseOrders = $this->model->getPurchaseOrders();
        
        include __DIR__ . '/../View/procurement/procurement_orders.php';
    }

    public function history() {
        if (!defined('IN_APP')) exit;
        $this->authorizeProcurement();
        
        $search = $_GET['search'] ?? '';
        $supplier = $_GET['supplier'] ?? '';
        $status = $_GET['status'] ?? '';
        $start_date = $_GET['start_date'] ?? '';
        $end_date = $_GET['end_date'] ?? '';

        $historyRecords = $this->model->getProcurementHistory($search, $supplier, $status, $start_date, $end_date);
        $suppliers = $this->model->getSuppliers('All');
        
        include __DIR__ . '/../View/procurement/procurement_history.php';
    }

    public function handleAction() {
        if (!defined('IN_APP')) exit;
        $this->authorizeProcurement();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            
            if ($action === 'update_quote') {
                $pid = $_POST['purchase_id'];
                $supplier = $_POST['supplier_name'];
                $unit_cost = (float)($_POST['unit_cost'] ?? 0);
                $qty = (int)($_POST['requested_qty'] ?? 1);
                $cost = $unit_cost * $qty;
                
                $res = $this->model->updateRequestQuotation($pid, $supplier, $cost);
                if ($res) {
                    $_SESSION['proc_msg'] = "Quotation updated. Request pushed to Finance for Budget Approval.";
                    $_SESSION['proc_msg_type'] = "success";
                } else {
                    $_SESSION['proc_msg'] = "Failed to update quotation.";
                    $_SESSION['proc_msg_type'] = "danger";
                }
                header("Location: /View/admin_procurement.php?page=procurement/procurement_requests.php");
                exit;
            }
            
            if ($action === 'generate_po') {
                $pid = $_POST['purchase_id'];
                
                $res = $this->model->generatePurchaseOrder($pid);
                if ($res['success']) {
                    $_SESSION['proc_msg'] = $res['message'];
                    $_SESSION['proc_msg_type'] = "success";
                } else {
                    $_SESSION['proc_msg'] = $res['message'];
                    $_SESSION['proc_msg_type'] = "danger";
                }
                header("Location: /View/admin_procurement.php?page=procurement/procurement_requests.php");
                exit;
            }

            if ($action === 'add_supplier') {
                $res = $this->model->addSupplier($_POST['supplier_name'], $_POST['contact_person'], $_POST['contact_number'], $_POST['email'], $_POST['address']);
                if ($res) {
                    $_SESSION['proc_msg'] = "Supplier added successfully.";
                    $_SESSION['proc_msg_type'] = "success";
                } else {
                    $_SESSION['proc_msg'] = "Failed to add supplier.";
                    $_SESSION['proc_msg_type'] = "danger";
                }
                header("Location: /View/admin_procurement.php?page=procurement/supplier_management.php");
                exit;
            }

            if ($action === 'update_supplier') {
                $res = $this->model->updateSupplier($_POST['supplier_id'], $_POST['supplier_name'], $_POST['contact_person'], $_POST['contact_number'], $_POST['email'], $_POST['address'], $_POST['status']);
                if ($res) {
                    $_SESSION['proc_msg'] = "Supplier updated successfully.";
                    $_SESSION['proc_msg_type'] = "success";
                } else {
                    $_SESSION['proc_msg'] = "Failed to update supplier.";
                    $_SESSION['proc_msg_type'] = "danger";
                }
                header("Location: /View/admin_procurement.php?page=procurement/supplier_management.php");
                exit;
            }
        }
    }
}
