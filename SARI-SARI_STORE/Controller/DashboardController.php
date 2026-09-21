<?php
// Controller/DashboardController.php

class DashboardController {
    private $model;

    public function __construct($model) {
        $this->model = $model;
    }

    public function index() {
        // Prepare data from the model
        $stats = $this->model->getSummaryStats();
        $salesChart = $this->model->getSalesChartData();
        $categoryChart = $this->model->getCategoryChartData();

        // Pass these variables to the view
        $productData = ['totalProducts' => $stats['totalProducts']];
        $lowStockData = ['totalLowStock' => $stats['totalLowStock']];
        $orderData = ['totalOrders' => $stats['totalOrders']];
        $todayNetSales = $stats['todayNetSales'];
        $salesData = ['todaysSales' => $stats['todaysSales']];
        $todayRestockDash = ['total' => $stats['todayRestockTotal']];
        
        $chartLabels = $salesChart['labels'];
        $chartData = $salesChart['data'];

        $catLabels = $categoryChart['labels'];
        $catData = $categoryChart['data'];

        $recentSales = $this->model->getRecentSales();
        $unreadCount = $this->model->getUnreadNotificationCount();
        $latestNotifs = $this->model->getLatestNotifications();

        // Load the view file
        require_once __DIR__ . '/../View/dashboard.php';
    }
}
