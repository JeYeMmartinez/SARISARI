<?php
// Controller/FinanceController.php

class FinanceController {
    private $model;

    public function __construct($model) {
        $this->model = $model;
    }

    private function getEmpUser() {
        return intval($_SESSION['user_id'] ?? $_SESSION['emp_id'] ?? 0);
    }

    private function getAccountType() {
        return isset($_SESSION['user_id']) ? 'User' : 'Employee';
    }

    private function authorizeFinance() {
        if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Finance Officer')) {
            http_response_code(403);
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                echo json_encode(['status' => 'error', 'message' => 'Unauthorized Access']);
            } else {
                echo "<div class='alert alert-danger'>Access Denied. Finance clearance required.</div>";
            }
            exit();
        }
    }

    public function sales() {
        $this->authorizeFinance();
        
        // Requires SalesModel for chart generation
        require_once __DIR__ . '/../Model/SalesModel.php';
        // Hack to get conn from FinanceModel
        $reflection = new ReflectionClass($this->model);
        $property = $reflection->getProperty('conn');
        $property->setAccessible(true);
        $conn = $property->getValue($this->model);
        
        $salesController = new SalesModel($conn);
        
        $allProds = $this->model->getSalesProducts();
        $allCats = $this->model->getSalesCategories();
        
        $stats = $salesController->getSummaryStats();

        $revenueData = ['total' => $stats['gross_revenue']];
        $todayData = ['total' => $stats['today_gross_revenue']];
        $restockExpenseData = ['total' => $stats['restock_expenses']];
        $todayRestockData = ['total' => $stats['today_restock_expenses']];

        $netRevenue = $stats['net_revenue'];
        $todayNetRevenue = $stats['today_net_revenue'];
        $ordersData = ['total' => $stats['total_orders']];
        $avgData = ['total' => $stats['avg_order_value']];

        $bestProduct = $salesController->getBestSellingProduct();

        $last7  = $salesController->getChartDataSeries('7days', 'peso');
        $last7_units = $salesController->getChartDataSeries('7days', 'units');
        $last7['units'] = $last7_units['data'];

        $last30 = $salesController->getChartDataSeries('30days', 'peso');
        $last30_units = $salesController->getChartDataSeries('30days', 'units');
        $last30['units'] = $last30_units['data'];

        $last12 = $salesController->getChartDataSeries('12months', 'peso');
        $last12_units = $salesController->getChartDataSeries('12months', 'units');
        $last12['units'] = $last12_units['data'];

        $topProducts = $salesController->getTopProductsList(5);
        $recentSales = $salesController->getRecentSalesList(20);

        require_once __DIR__ . '/../View/Finance_employee/finance_sales.php';
    }

    public function stockRequests() {
        $this->authorizeFinance();
        
        $requests_q = $this->model->getPurchaseRequests();
        $requests = [];
        $total_pending_cost = 0;
        if ($requests_q) {
            while ($r = mysqli_fetch_assoc($requests_q)) {
                $requests[] = $r;
                if ($r['status'] === 'Pending Finance Approval') {
                    $total_pending_cost += floatval($r['estimated_cost']);
                }
            }
        }
        
        $message = $_SESSION['finance_msg'] ?? '';
        $msg_type = $_SESSION['finance_msg_type'] ?? '';
        unset($_SESSION['finance_msg'], $_SESSION['finance_msg_type']);

        require_once __DIR__ . '/../View/Finance_employee/finance_stock_requests.php';
    }

    public function restock() {
        $this->authorizeFinance();
        
        $requests_query = $this->model->getPendingRestockRequisitions();
        $rows = [];
        if ($requests_query) {
            while ($r = mysqli_fetch_assoc($requests_query)) {
                $rows[] = $r;
            }
        }
        
        require_once __DIR__ . '/../View/Finance_employee/finance_restock.php';
    }

    public function payroll() {
        $this->authorizeFinance();
        
        // Ensure HRMSController functions are accessible if needed by the view
        $hrmsControllerPath = __DIR__ . '/HRMSController.php';
        if (file_exists($hrmsControllerPath)) {
            require_once $hrmsControllerPath;
        }

        $periods_q = $this->model->getPendingPayrollDrafts();
        $periods = [];
        if ($periods_q) {
            while ($p = mysqli_fetch_assoc($periods_q)) {
                $periods[] = $p;
            }
        }

        $message = $_SESSION['finance_msg'] ?? '';
        $msg_type = $_SESSION['finance_msg_type'] ?? '';
        unset($_SESSION['finance_msg'], $_SESSION['finance_msg_type']);

        require_once __DIR__ . '/../View/Finance_employee/finance_payroll.php';
    }

    public function signatureProfile() {
        $this->authorizeFinance();
        
        $emp_user = $this->getEmpUser();
        $account_type = $this->getAccountType();
        $current_signature = $this->model->getSignature($emp_user, $account_type);

        $emp_name = $_SESSION['emp_name'] ?? $_SESSION['full_name'] ?? 'Finance User';
        $emp_role = $_SESSION['emp_role'] ?? $_SESSION['role'] ?? 'Finance Employee';

        $message = $_SESSION['finance_msg'] ?? '';
        $msg_type = $_SESSION['finance_msg_type'] ?? '';
        unset($_SESSION['finance_msg'], $_SESSION['finance_msg_type']);

        require_once __DIR__ . '/../View/Finance_employee/finance_signature_profile.php';
    }

    public function handleAction($action) {
        $this->authorizeFinance();
        $emp_user = $this->getEmpUser();
        $account_type = $this->getAccountType();
        $emp_name = $_SESSION['emp_name'] ?? $_SESSION['full_name'] ?? 'Finance User';

        switch ($action) {
            // STOCK REQUESTS ACTIONS
            case 'sign_and_approve_finance':
                $pid = intval($_POST['purchase_id']);
                $notes = $_POST['finance_notes'] ?? 'Budget Approved by Finance';
                
                $signature = $this->model->getSignature($emp_user, $account_type);
                if (empty($signature)) {
                    $_SESSION['finance_msg'] = "A registered E-Signature is required.";
                    $_SESSION['finance_msg_type'] = "danger";
                } else {
                    $res = $this->model->approvePurchaseRequest($pid, $notes, $signature, $emp_user, $emp_name);
                    if ($res) {
                        $_SESSION['finance_msg'] = "Purchase Request {$res['purchase_code']} formally signed and approved! Supplier Purchase Order #{$res['po_code']} generated.";
                        $_SESSION['finance_msg_type'] = "success";
                    }
                }
                
                // Return script to trigger ajax load since it's an AJAX submitted form?
                // Actually, these forms were submitted normally (not AJAX) and reloaded the page, 
                // OR if it's via admin.php, they are injected. Wait. The previous system did POST to the same PHP file and output JS if inside AJAX.
                // In Phase 9, if we redirect, we need to handle the SPA nature.
                // Since `View/Finance_employee/finance_stock_requests.php` form does a standard POST to the file... Wait!
                // We'll return JS to reload the page via `loadPage`.
                echo "<script>
                        if(typeof window.parent.loadPage === 'function') {
                            window.parent.loadPage('../router.php?route=finance_stock_requests');
                        } else {
                            window.location.href = 'admin_finance.php?page=finance_stock_requests.php';
                        }
                      </script>";
                break;

            case 'reject_finance':
                $pid = intval($_POST['purchase_id']);
                $notes = $_POST['finance_notes'] ?? 'Budget Rejected by Finance';
                $code = $this->model->rejectPurchaseRequest($pid, $notes);
                if ($code) {
                    $_SESSION['finance_msg'] = "Purchase Request {$code} rejected.";
                    $_SESSION['finance_msg_type'] = "danger";
                }
                echo "<script>
                        if(typeof window.parent.loadPage === 'function') {
                            window.parent.loadPage('../router.php?route=finance_stock_requests');
                        } else {
                            window.location.href = 'admin_finance.php?page=finance_stock_requests.php';
                        }
                      </script>";
                break;
                
            case 'get_signed_letter': // Stock purchase request letter
                $pid = intval($_GET['purchase_id']);
                $letter = $this->model->getSignedLetter($pid);
                header('Content-Type: application/json');
                echo json_encode($letter);
                break;

            // RESTOCK (Requisitions) ACTIONS
            case 'fetch_history_requests':
                $status_param = $_GET['status'] ?? 'Approved Finance';
                $status_filter = ($status_param === 'Rejected') ? "r.status = 'Rejected'" : "r.status = 'Approved Finance'";
                $res = $this->model->getHistoryRestockRequisitions($status_filter);
                $rows = [];
                if ($res) {
                    while ($r = mysqli_fetch_assoc($res)) {
                        $rows[] = $r;
                    }
                }
                header('Content-Type: application/json');
                echo json_encode($rows);
                break;
                
            case 'update_status': // Restock
                $req_id = intval($_POST['requisition_id'] ?? 0);
                $new_status = $_POST['status'] ?? '';
                $notes = $_POST['finance_notes'] ?? '';
                $ref_no = 'RSTK-' . date('Ymd') . '-' . rand(1000, 9999);
                
                if ($req_id > 0 && in_array($new_status, ['Approved Finance', 'Rejected'])) {
                    if ($new_status === 'Approved Finance') {
                        $signature = $this->model->getSignature($emp_user, $account_type);
                        if (empty($signature)) {
                            header('Content-Type: application/json');
                            echo json_encode(['status' => 'error', 'message' => 'E-Signature required for approval.']);
                            exit;
                        }
                    }
                    $res = $this->model->updateRestockRequisitionStatus($req_id, $new_status, $notes, $ref_no);
                    header('Content-Type: application/json');
                    if ($res) {
                        echo json_encode(['status' => 'success']);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'Failed to update']);
                    }
                }
                break;

            // PAYROLL ACTIONS
            case 'sign_and_approve_payroll':
                $period_id = intval($_POST['period_id']);
                $notes = $_POST['finance_notes'] ?? 'Payroll Approved by Finance';
                
                $signature = $this->model->getSignature($emp_user, $account_type);
                if (empty($signature)) {
                    $_SESSION['finance_msg'] = "A registered E-Signature is required to approve payroll.";
                    $_SESSION['finance_msg_type'] = "danger";
                } else {
                    $this->model->approvePayroll($period_id, $notes, $signature, $emp_user, $emp_name);
                    $_SESSION['finance_msg'] = "Payroll period formally signed and approved! Funds are released.";
                    $_SESSION['finance_msg_type'] = "success";
                }
                echo "<script>
                        if(typeof window.parent.loadPage === 'function') {
                            window.parent.loadPage('../router.php?route=finance_payroll');
                        } else {
                            window.location.href = 'admin_finance.php?page=finance_payroll.php';
                        }
                      </script>";
                break;

            case 'reject_payroll':
                $period_id = intval($_POST['period_id']);
                $notes = $_POST['finance_notes'] ?? 'Payroll Rejected by Finance';
                $this->model->rejectPayroll($period_id, $notes);
                $_SESSION['finance_msg'] = "Payroll period rejected and returned to HR.";
                $_SESSION['finance_msg_type'] = "danger";
                echo "<script>
                        if(typeof window.parent.loadPage === 'function') {
                            window.parent.loadPage('../router.php?route=finance_payroll');
                        } else {
                            window.location.href = 'admin_finance.php?page=finance_payroll.php';
                        }
                      </script>";
                break;
                
            case 'get_signed_payroll_letter':
                $period_id = intval($_GET['period_id']);
                $letter = $this->model->getPayrollSignedLetter($period_id);
                header('Content-Type: application/json');
                echo json_encode($letter);
                break;

            // SIGNATURE PROFILE ACTIONS
            case 'save_signature':
                $signature = $_POST['e_signature'] ?? '';
                if (!empty($signature)) {
                    $this->model->saveSignature($emp_user, $account_type, $signature);
                    $_SESSION['finance_msg'] = "Your E-Signature has been securely saved and registered.";
                    $_SESSION['finance_msg_type'] = "success";
                } else {
                    $_SESSION['finance_msg'] = "Signature data was empty.";
                    $_SESSION['finance_msg_type'] = "danger";
                }
                echo "<script>
                        if(typeof window.parent.loadPage === 'function') {
                            window.parent.loadPage('../router.php?route=finance_signature_profile');
                        } else {
                            window.location.href = 'admin_finance.php?page=finance_signature_profile.php';
                        }
                      </script>";
                break;

            // SALES ACTIONS
            case 'get_items':
                require_once __DIR__ . '/../Model/SalesModel.php';
                $reflection = new ReflectionClass($this->model);
                $property = $reflection->getProperty('conn');
                $property->setAccessible(true);
                $conn = $property->getValue($this->model);
                $salesController = new SalesModel($conn);

                $sale_id = (int)$_POST['sale_id'];
                $sale = $salesController->getSaleRecord($sale_id);

                if(!$sale){
                    echo '<p class="text-danger text-center py-3">Sale not found.</p>';
                    exit();
                }

                $items = $salesController->getSaleItems($sale_id);

                echo '<div style="font-family:monospace;font-size:13px;">';
                echo '<div class="text-center mb-3">
                        <strong style="font-size:15px;">🛒 O-CART!</strong><br>
                        <small class="text-muted">Sale #'.$sale_id.' • '.date("M d, Y h:i A", strtotime($sale['created_at'])).'</small>
                      </div>';
                echo '<table class="table table-sm table-bordered">';
                echo '<thead class="table-success"><tr>
                        <th>Product</th><th class="text-center">Qty</th>
                        <th class="text-end">Price</th><th class="text-end">Subtotal</th>
                      </tr></thead><tbody>';

                while($item = mysqli_fetch_assoc($items)){
                    echo '<tr>
                        <td>'.htmlspecialchars($item['product_name'] ?? '—').'</td>
                        <td class="text-center">'.$item['quantity'].'</td>
                        <td class="text-end">₱'.number_format($item['selling_price'],2).'</td>
                        <td class="text-end">₱'.number_format($item['subtotal'],2).'</td>
                    </tr>';
                }

                echo '</tbody></table>';
                echo '<hr style="border-style:dashed;">';
                echo '<div class="d-flex justify-content-between mb-1">
                        <strong>Total</strong>
                        <strong>₱'.number_format($sale['total_amount'],2).'</strong>
                      </div>';
                echo '<div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Cash Paid</span>
                        <span>₱'.number_format($sale['payment'],2).'</span>
                      </div>';
                $change = (float)$sale['change_amount'];
                echo '<div class="d-flex justify-content-between">
                        <span class="text-muted">Change</span>
                        <span class="'.($change > 0 ? 'text-success fw-bold' : 'text-muted').'">
                            ₱'.number_format($change,2).'
                        </span>
                      </div>';
                echo '</div>';
                break;

            case 'get_chart_data':
                require_once __DIR__ . '/../Model/SalesModel.php';
                $reflection = new ReflectionClass($this->model);
                $property = $reflection->getProperty('conn');
                $property->setAccessible(true);
                $conn = $property->getValue($this->model);
                $salesController = new SalesModel($conn);

                $period      = $_POST['period']      ?? '7days';
                $product_id  = (int)($_POST['product_id']  ?? 0);
                $category_id = (int)($_POST['category_id'] ?? 0);
                $mode        = $_POST['mode'] ?? 'peso';

                $chartData = $salesController->getChartDataSeries($period, $mode, $product_id, $category_id);
                header('Content-Type: application/json');
                echo json_encode($chartData);
                break;

            default:
                http_response_code(400);
                echo "Invalid action.";
                break;
        }
    }
}
