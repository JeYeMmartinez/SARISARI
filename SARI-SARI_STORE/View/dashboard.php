<?php

require_once '../Model/database.php';

// Total Products
$productQuery = mysqli_query($conn, "SELECT COUNT(*) AS totalProducts FROM products");
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
                    <span class="badge bg-success mt-1">+12%</span>
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
                    <span class="badge bg-warning text-dark mt-1">In Stock</span>
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

    <div class="col-lg-4">
        <div class="table-card">
            <h5 class="mb-3">Notifications</h5>
            <div class="list-group list-group-flush">
                <div class="list-group-item px-0"><i class="bi bi-bell text-warning me-2"></i> Testing notification!</div>
                <div class="list-group-item px-0"><i class="bi bi-bell text-warning me-2"></i> Testing notification!</div>
                <div class="list-group-item px-0"><i class="bi bi-bell text-warning me-2"></i> Testing notification!</div>
                <div class="list-group-item px-0"><i class="bi bi-bell text-warning me-2"></i> Testing notification!</div>
            </div>
        </div>
    </div>

</div>

<!-- CHARTS SCRIPT (no <script src> tags — already loaded in admin.php) -->
<script>

new Chart(document.getElementById("salesChart"), {
    type: 'line',
    data: {
        labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
        datasets: [{
            label: 'Sales (₱)',
            data: [500,900,700,1300,900,1500,1700],
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
        scales: { y: { beginAtZero: true, ticks: { callback: v => '₱' + v } } }
    }
});

new Chart(document.getElementById("pieChart"), {
    type: 'doughnut',
    data: {
        labels: ['Beverages','Snacks','Canned','Noodles'],
        datasets: [{
            data: [35,25,20,20],
            backgroundColor: ['#198754','#20c997','#ffc107','#dc3545']
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});

</script>