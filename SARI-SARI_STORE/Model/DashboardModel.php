<?php
// Model/DashboardModel.php

class DashboardModel {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getSummaryStats() {
        // Total Products
        $productQuery = mysqli_query($this->conn, "SELECT COUNT(*) AS totalProducts FROM products WHERE status = 'Available'");
        $productData = mysqli_fetch_assoc($productQuery);

        // Low Stock
        $lowStockQuery = mysqli_query($this->conn, "SELECT COUNT(*) AS totalLowStock FROM inventory WHERE quantity <= minimum_stock");
        $lowStockData = mysqli_fetch_assoc($lowStockQuery);

        // Total Orders
        $orderQuery = mysqli_query($this->conn, "SELECT COUNT(*) AS totalOrders FROM sales");
        $orderData = mysqli_fetch_assoc($orderQuery);

        // Today's Sales (Gross)
        $salesQuery = mysqli_query($this->conn, "SELECT IFNULL(SUM(total_amount),0) AS todaysSales FROM sales WHERE DATE(created_at)=CURDATE()");
        $salesData = mysqli_fetch_assoc($salesQuery);

        // Today's Restock Expenses
        $todayRestockDash = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT IFNULL(SUM(total_cost),0) AS total FROM restock_logs WHERE DATE(restocked_at)=CURDATE()"
        ));
        
        // Today's Net = Gross Sales − Today's Restock Cost
        $todayNetSales = max(0, (float)$salesData['todaysSales'] - (float)$todayRestockDash['total']);

        return [
            'totalProducts' => $productData['totalProducts'] ?? 0,
            'totalLowStock' => $lowStockData['totalLowStock'] ?? 0,
            'totalOrders' => $orderData['totalOrders'] ?? 0,
            'todaysSales' => $salesData['todaysSales'] ?? 0,
            'todayRestockTotal' => $todayRestockDash['total'] ?? 0,
            'todayNetSales' => $todayNetSales
        ];
    }

    public function getSalesChartData() {
        $chartLabels = [];
        $chartData   = [];
        for($i = 6; $i >= 0; $i--){
            $date = date('Y-m-d', strtotime("-$i days"));
            $label = date('D M d', strtotime($date));
            $row = mysqli_fetch_assoc(mysqli_query($this->conn,"
                SELECT IFNULL(SUM(total_amount),0) AS total
                FROM sales WHERE status='Completed' AND DATE(created_at)='$date'
            "));
            $chartLabels[] = $label;
            $chartData[]   = (float)$row['total'];
        }

        return [
            'labels' => $chartLabels,
            'data' => $chartData
        ];
    }

    public function getCategoryChartData() {
        $categoryChartQuery = mysqli_query($this->conn,"
            SELECT c.category_name, IFNULL(SUM(si.subtotal),0) AS total
            FROM categories c
            LEFT JOIN products p ON p.category_id = c.category_id
            LEFT JOIN sale_items si ON si.product_id = p.product_id
            GROUP BY c.category_id
            ORDER BY total DESC
        ");
        $catLabels = [];
        $catData   = [];
        while($cat = mysqli_fetch_assoc($categoryChartQuery)){
            $catLabels[] = $cat['category_name'];
            $catData[]   = (float)$cat['total'];
        }

        return [
            'labels' => $catLabels,
            'data' => $catData
        ];
    }

    public function getRecentSales() {
        $recentSales = mysqli_query($this->conn,"
            SELECT sales.sale_id, users.full_name, sales.total_amount, sales.status, sales.created_at
            FROM sales
            INNER JOIN users ON sales.cashier_id = users.user_id
            ORDER BY sales.created_at DESC
            LIMIT 10
        ");
        
        $salesList = [];
        if ($recentSales) {
            while($sale = mysqli_fetch_assoc($recentSales)){
                $salesList[] = $sale;
            }
        }
        return $salesList;
    }

    public function getUnreadNotificationCount() {
        $unread = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT COUNT(*) AS total FROM notifications WHERE is_read = 0"
        ));
        return $unread['total'] ?? 0;
    }

    public function getLatestNotifications() {
        $notifs = mysqli_query($this->conn,"
            SELECT * FROM notifications
            ORDER BY is_read ASC, created_at DESC
            LIMIT 5
        ");
        $list = [];
        if ($notifs) {
            while($n = mysqli_fetch_assoc($notifs)){
                $list[] = $n;
            }
        }
        return $list;
    }
}
