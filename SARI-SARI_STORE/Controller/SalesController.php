<?php
// Controller/SalesController.php

class SalesController {
    private $model;

    public function __construct($model) {
        $this->model = $model;
    }

    public function index() {
        // Fetch summary stats
        $stats = $this->model->getSummaryStats();
        
        $revenueData = ['total' => $stats['gross_revenue']];
        $todayData = ['total' => $stats['today_gross_revenue']];
        $restockExpenseData = ['total' => $stats['restock_expenses']];
        $todayRestockData = ['total' => $stats['today_restock_expenses']];
        
        $netRevenue = $stats['net_revenue'];
        $todayNetRevenue = $stats['today_net_revenue'];
        
        $bestProduct = $this->model->getBestSellingProduct();
        
        // Chart Data Seed Values
        $last7  = $this->model->getChartDataSeries('7days', 'peso');
        $last7_units = $this->model->getChartDataSeries('7days', 'units');
        $last7['units'] = $last7_units['data'];
        
        $last30 = $this->model->getChartDataSeries('30days', 'peso');
        $last30_units = $this->model->getChartDataSeries('30days', 'units');
        $last30['units'] = $last30_units['data'];
        
        $last12 = $this->model->getChartDataSeries('12months', 'peso');
        $last12_units = $this->model->getChartDataSeries('12months', 'units');
        $last12['units'] = $last12_units['data'];
        
        // Top Products & Recent Sales
        $topProducts = $this->model->getTopProductsList(5);
        $recentSales = $this->model->getRecentSalesList(20);

        global $conn; // needed if the view relies on it for dropdowns
        require_once __DIR__ . '/../View/sales.php';
    }

    public function action($action) {
        if ($action === 'get_items') {
            $sale_id = (int)$_POST['sale_id'];
            $sale = $this->model->getSaleRecord($sale_id);
        
            if(!$sale){
                echo '<p class="text-danger text-center py-3">Sale not found.</p>';
                exit();
            }
        
            $items = $this->model->getSaleItems($sale_id);
        
            echo '<div style="font-family:monospace;font-size:13px;">';
            echo '<div class="text-center mb-3">
                    <strong style="font-size:15px;">🛒 O-CART!</strong><br>
                    <small class="text-muted">Sale #'.$sale_id.' — '.date("M d, Y h:i A", strtotime($sale['created_at'])).'</small>
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
            exit();
        } 
        elseif ($action === 'get_chart_data') {
            $period      = $_POST['period']      ?? '7days';
            $product_id  = (int)($_POST['product_id']  ?? 0);
            $category_id = (int)($_POST['category_id'] ?? 0);
            $mode        = $_POST['mode'] ?? 'peso';
        
            $chartData = $this->model->getChartDataSeries($period, $mode, $product_id, $category_id);
            echo json_encode($chartData);
            exit();
        }
    }
}
