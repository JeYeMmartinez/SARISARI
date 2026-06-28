<?php

require_once '../Model/database.php';

// Total Products
$productQuery = mysqli_query($conn, "SELECT COUNT(*) AS totalProducts FROM products WHERE status = 'Available'");
$productData = mysqli_fetch_assoc($productQuery);

// Low Stock
$lowStockQuery = mysqli_query($conn, "SELECT COUNT(*) AS totalLowStock FROM inventory WHERE quantity <= minimum_stock");
$lowStockData = mysqli_fetch_assoc($lowStockQuery);

// Total Orders
$orderQuery = mysqli_query($conn, "SELECT COUNT(*) AS totalOrders FROM sales");
$orderData = mysqli_fetch_assoc($orderQuery);

// Today's Sales
$salesQuery = mysqli_query($conn, "SELECT IFNULL(SUM(total_amount),0) AS todaysSales FROM sales WHERE DATE(created_at)=CURDATE()");
$salesData = mysqli_fetch_assoc($salesQuery);

// Sales last 7 days for chart
$chartLabels = [];
$chartData   = [];
for($i = 6; $i >= 0; $i--){
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('D M d', strtotime($date));
    $row = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT IFNULL(SUM(total_amount),0) AS total
        FROM sales WHERE status='Completed' AND DATE(created_at)='$date'
    "));
    $chartLabels[] = $label;
    $chartData[]   = (float)$row['total'];
}

// Sales by Category for pie chart
$categoryChartQuery = mysqli_query($conn,"
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

?>

<style>
.dashboard-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    height: 100%;
}
.dashboard-card .icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: white;
    flex-shrink: 0;
}
.chart-card, .table-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    margin-bottom: 20px;
}
</style>

<!-- SUMMARY CARDS -->
<div class="row g-3 mb-4">

    <div class="col-lg-3 col-md-6">
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <small class="text-muted">Today's Sales</small>
                    <h3 class="fw-bold mt-2">₱<?= number_format($salesData['todaysSales'],2); ?></h3>
                    <span class="badge bg-success mt-1">Today</span>
                </div>
                <div class="icon bg-success"><i class="bi bi-cash-stack"></i></div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <small class="text-muted">Orders Today</small>
                    <h3 class="fw-bold mt-2"><?= $orderData['totalOrders']; ?></h3>
                    <span class="badge bg-primary mt-1">Completed</span>
                </div>
                <div class="icon bg-primary"><i class="bi bi-cart-check"></i></div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <small class="text-muted">Products</small>
                    <h3 class="fw-bold mt-2"><?= $productData['totalProducts']; ?></h3>
                    <span class="badge bg-warning text-dark mt-1">Available</span>
                </div>
                <div class="icon bg-warning"><i class="bi bi-box-seam"></i></div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <small class="text-muted">Low Stock</small>
                    <h3 class="fw-bold mt-2 text-danger"><?= $lowStockData['totalLowStock']; ?></h3>
                    <span class="badge bg-danger mt-1">Needs Attention</span>
                </div>
                <div class="icon bg-danger"><i class="bi bi-exclamation-triangle"></i></div>
            </div>
        </div>
    </div>

</div>

<!-- CHARTS -->
<div class="row mb-4">

    <div class="col-lg-9">
        <div class="chart-card">
            <h5 class="mb-3">Sales Overview</h5>
            <canvas id="salesChart" height="100"></canvas>
        </div>
    </div>

    <div class="col-lg-3">
        <div class="chart-card">
            <h5 class="mb-3">Sales Categories</h5>
            <canvas id="pieChart"></canvas>
        </div>
    </div>

</div>

<!-- TABLES -->
<div class="row">

    <div class="col-lg-8">
        <div class="table-card">
            <h5 class="mb-3">Recent Sales</h5>
            <table class="table table-bordered table-striped datatable" id="salesTable">
                <thead>
                    <tr>
                        <th>Sale ID</th>
                        <th>Cashier</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $recentSales = mysqli_query($conn,"
                        SELECT sales.sale_id, users.full_name, sales.total_amount, sales.status, sales.created_at
                        FROM sales
                        INNER JOIN users ON sales.cashier_id = users.user_id
                        ORDER BY sales.created_at DESC
                        LIMIT 10
                    ");
                    while($sale = mysqli_fetch_assoc($recentSales)){
                    ?>
                    <tr>
                        <td><?= $sale['sale_id']; ?></td>
                        <td><?= $sale['full_name']; ?></td>
                        <td>₱<?= number_format($sale['total_amount'],2); ?></td>
                        <td>
                            <?php if($sale['status']=="Completed"){ ?>
                                <span class="badge bg-success">Completed</span>
                            <?php } else { ?>
                                <span class="badge bg-warning text-dark"><?= $sale['status']; ?></span>
                            <?php } ?>
                        </td>
                        <td><?= date("M d, Y",strtotime($sale['created_at'])); ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- LIVE NOTIFICATIONS -->
    <div class="col-lg-4">
        <div class="table-card">
            <h5 class="mb-3">
                Notifications
                <?php
                $unread = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT COUNT(*) AS total FROM notifications WHERE is_read = 0"
                ));
                if($unread['total'] > 0){
                    echo '<span class="badge bg-success ms-1">'.$unread['total'].' new</span>';
                }
                ?>
            </h5>

            <?php
            $notifs = mysqli_query($conn,"
                SELECT * FROM notifications
                ORDER BY is_read ASC, created_at DESC
                LIMIT 5
            ");

            if(mysqli_num_rows($notifs) == 0){ ?>
                <div class="text-center text-muted py-3">
                    <i class="bi bi-bell-slash" style="font-size:32px;"></i>
                    <p class="mt-2 mb-0" style="font-size:13px;">No notifications</p>
                </div>
            <?php } else {
                while($notif = mysqli_fetch_assoc($notifs)){
                    switch($notif['type']){
                        case 'Low Stock': $color = '#ffc107'; $icon = 'bi-exclamation-triangle-fill'; break;
                        case 'Approval':  $color = '#0d6efd'; $icon = 'bi-check-circle-fill'; break;
                        case 'Sales':     $color = '#198754'; $icon = 'bi-graph-up-arrow'; break;
                        default:          $color = '#6c757d'; $icon = 'bi-info-circle-fill';
                    }
                    $isUnread = $notif['is_read'] == 0;
            ?>
                <div style="
                    display:flex;
                    align-items:flex-start;
                    gap:10px;
                    padding:10px;
                    border-radius:8px;
                    margin-bottom:8px;
                    background:<?= $isUnread ? '#f0faf4' : '#f8f9fa'; ?>;
                    border-left:3px solid <?= $isUnread ? '#198754' : 'transparent'; ?>;
                ">
                    <i class="bi <?= $icon; ?>"
                       style="color:<?= $color; ?>;font-size:16px;margin-top:2px;"></i>
                    <div style="flex:1;">
                        <div style="font-size:13px;font-weight:<?= $isUnread ? '700' : '500'; ?>;">
                            <?= htmlspecialchars($notif['title']); ?>
                        </div>
                        <div style="font-size:11px;color:#6c757d;">
                            <?= htmlspecialchars($notif['message']); ?>
                        </div>
                    </div>
                    <?php if($isUnread){ ?>
                    <div style="width:7px;height:7px;border-radius:50%;
                                background:#198754;margin-top:5px;flex-shrink:0;"></div>
                    <?php } ?>
                </div>
            <?php } ?>

            <div class="text-end mt-2">
                <a href="#" onclick="loadPage('notifications.php')"
                   style="font-size:12px;color:#198754;text-decoration:none;">
                    View all notifications →
                </a>
            </div>

            <?php } ?>
        </div>
    </div>

</div>

<script>

function buildDashboardCharts(){

    new Chart(document.getElementById("salesChart"), {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels); ?>,
            datasets: [{
                label: 'Sales (₱)',
                data: <?= json_encode($chartData); ?>,
                borderColor: '#198754',
                backgroundColor: 'rgba(25,135,84,.15)',
                fill: true,
                tension: .4,
                pointBackgroundColor: '#198754'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => '₱' + v.toLocaleString() }
                }
            }
        }
    });

    new Chart(document.getElementById("pieChart"), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($catLabels); ?>,
            datasets: [{
                data: <?= json_encode($catData); ?>,
                backgroundColor: [
                    '#198754','#20c997','#ffc107',
                    '#dc3545','#0d6efd','#6c757d','#fd7e14','#6610f2'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } }
        }
    });

}

if(typeof Chart !== 'undefined'){
    buildDashboardCharts();
} else {
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
    s.onload = () => buildDashboardCharts();
    document.head.appendChild(s);
}

</script>