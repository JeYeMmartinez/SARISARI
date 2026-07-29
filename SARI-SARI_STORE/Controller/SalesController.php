<?php
// Controller/SalesController.php

class SalesController {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Get sale record details
     */
    public function getSaleRecord($sale_id) {
        $sale_id = (int)$sale_id;
        return mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT * FROM sales WHERE sale_id = $sale_id"
        ));
    }

    /**
     * Get purchased items for a sale
     */
    public function getSaleItems($sale_id) {
        $sale_id = (int)$sale_id;
        return mysqli_query($this->conn, "
            SELECT si.*, p.product_name
            FROM sale_items si
            LEFT JOIN products p ON si.product_id = p.product_id
            WHERE si.sale_id = $sale_id
        ");
    }

    /**
     * Fetch key summary metrics for sales
     */
    public function getSummaryStats() {
        // Total Revenue (Gross)
        $revenueData = mysqli_fetch_assoc(mysqli_query($this->conn, "
            SELECT IFNULL(SUM(total_amount), 0) AS total FROM sales WHERE status='Completed'
        "));

        // Today's Revenue (Gross)
        $todayData = mysqli_fetch_assoc(mysqli_query($this->conn, "
            SELECT IFNULL(SUM(total_amount), 0) AS total FROM sales
            WHERE status='Completed' AND DATE(created_at) = CURDATE()
        "));

        // Total Restock Expenses (All Time)
        $restockExpenseData = mysqli_fetch_assoc(mysqli_query($this->conn, "
            SELECT IFNULL(SUM(total_cost), 0) AS total FROM restock_logs
        "));

        // Today's Restock Expenses
        $todayRestockData = mysqli_fetch_assoc(mysqli_query($this->conn, "
            SELECT IFNULL(SUM(total_cost), 0) AS total FROM restock_logs
            WHERE DATE(restocked_at) = CURDATE()
        "));

        // Total Orders
        $ordersData = mysqli_fetch_assoc(mysqli_query($this->conn, "
            SELECT COUNT(*) AS total FROM sales WHERE status='Completed'
        "));

        // Average Order Value
        $avgData = mysqli_fetch_assoc(mysqli_query($this->conn, "
            SELECT IFNULL(AVG(total_amount), 0) AS total FROM sales WHERE status='Completed'
        "));

        $gross = (float)$revenueData['total'];
        $restock = (float)$restockExpenseData['total'];
        $net = max(0, $gross - $restock);

        $todayGross = (float)$todayData['total'];
        $todayRestock = (float)$todayRestockData['total'];
        $todayNet = max(0, $todayGross - $todayRestock);

        return [
            'gross_revenue' => $gross,
            'today_gross_revenue' => $todayGross,
            'restock_expenses' => $restock,
            'today_restock_expenses' => $todayRestock,
            'net_revenue' => $net,
            'today_net_revenue' => $todayNet,
            'total_orders' => (int)$ordersData['total'],
            'avg_order_value' => (float)$avgData['total']
        ];
    }

    /**
     * Get the best-selling product
     */
    public function getBestSellingProduct() {
        return mysqli_fetch_assoc(mysqli_query($this->conn, "
            SELECT p.product_name, SUM(si.quantity) AS total_sold
            FROM sale_items si
            INNER JOIN products p ON si.product_id = p.product_id
            GROUP BY si.product_id
            ORDER BY total_sold DESC
            LIMIT 1
        "));
    }

    /**
     * Get recent sales checkouts list
     */
    public function getRecentSalesList($limit = 20) {
        $limit = (int)$limit;
        return mysqli_query($this->conn, "
            SELECT s.*, u.full_name
            FROM sales s
            INNER JOIN users u ON s.cashier_id = u.user_id
            WHERE s.status='Completed'
            ORDER BY s.created_at DESC
            LIMIT $limit
        ");
    }

    /**
     * Get top products by volume sold
     */
    public function getTopProductsList($limit = 5) {
        $limit = (int)$limit;
        return mysqli_query($this->conn, "
            SELECT p.product_name, SUM(si.quantity) AS total_sold,
                   SUM(si.subtotal) AS total_revenue
            FROM sale_items si
            INNER JOIN products p ON si.product_id = p.product_id
            GROUP BY si.product_id
            ORDER BY total_sold DESC
            LIMIT $limit
        ");
    }

    /**
     * Calculate coordinate datasets for graphs/charts
     */
    public function getChartDataSeries($period, $mode = 'peso', $product_id = 0, $category_id = 0) {
        $product_id  = (int)$product_id;
        $category_id = (int)$category_id;

        $productFilter  = $product_id  ? "AND si.product_id = $product_id" : '';
        $categoryFilter = $category_id ? "AND p.category_id = $category_id" : '';
        $joinProducts   = ($category_id || $product_id) ? "INNER JOIN products p ON si.product_id = p.product_id" : '';

        $labels = [];
        $data   = [];

        if ($period === '7days') {
            for ($i = 6; $i >= 0; $i--) {
                $date     = date('Y-m-d', strtotime("-$i days"));
                $labels[] = date('D M d', strtotime($date));
                $row = mysqli_fetch_assoc(mysqli_query($this->conn, "
                    SELECT IFNULL(SUM(" . ($mode === 'units' ? 'si.quantity' : 'si.subtotal') . "), 0) AS total
                    FROM sale_items si
                    INNER JOIN sales s ON si.sale_id = s.sale_id
                    $joinProducts
                    WHERE s.status='Completed'
                    AND DATE(s.created_at) = '$date'
                    $productFilter $categoryFilter
                "));
                $data[] = $mode === 'units' ? (int)$row['total'] : (float)$row['total'];
            }
        } elseif ($period === '30days') {
            for ($i = 29; $i >= 0; $i--) {
                $date     = date('Y-m-d', strtotime("-$i days"));
                $labels[] = date('M d', strtotime($date));
                $row = mysqli_fetch_assoc(mysqli_query($this->conn, "
                    SELECT IFNULL(SUM(" . ($mode === 'units' ? 'si.quantity' : 'si.subtotal') . "), 0) AS total
                    FROM sale_items si
                    INNER JOIN sales s ON si.sale_id = s.sale_id
                    $joinProducts
                    WHERE s.status='Completed'
                    AND DATE(s.created_at) = '$date'
                    $productFilter $categoryFilter
                "));
                $data[] = $mode === 'units' ? (int)$row['total'] : (float)$row['total'];
            }
        } else { // 12months
            for ($i = 11; $i >= 0; $i--) {
                $month    = date('Y-m', strtotime("-$i months"));
                $labels[] = date('M Y', strtotime("-$i months"));
                $row = mysqli_fetch_assoc(mysqli_query($this->conn, "
                    SELECT IFNULL(SUM(" . ($mode === 'units' ? 'si.quantity' : 'si.subtotal') . "), 0) AS total
                    FROM sale_items si
                    INNER JOIN sales s ON si.sale_id = s.sale_id
                    $joinProducts
                    WHERE s.status='Completed'
                    AND DATE_FORMAT(s.created_at, '%Y-%m') = '$month'
                    $productFilter $categoryFilter
                "));
                $data[] = $mode === 'units' ? (int)$row['total'] : (float)$row['total'];
            }
        }

        return ['labels' => $labels, 'data' => $data];
    }
}
