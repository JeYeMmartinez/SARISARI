<?php

// Get sale items for modal
if(isset($_POST['action']) && $_POST['action'] == 'get_items'){
    $sale_id = (int)$_POST['sale_id'];
    $items = mysqli_query($conn,"
        SELECT si.*, p.product_name
        FROM sale_items si
        INNER JOIN products p ON si.product_id = p.product_id
        WHERE si.sale_id = $sale_id
    ");
    $sale = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM sales WHERE sale_id = $sale_id"
    ));

    echo '<table class="table table-sm table-bordered">';
    echo '<thead class="table-success"><tr>
            <th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th>
          </tr></thead><tbody>';

    while($item = mysqli_fetch_assoc($items)){
        echo '<tr>
            <td>'.htmlspecialchars($item['product_name']).'</td>
            <td>'.$item['quantity'].'</td>
            <td>₱'.number_format($item['selling_price'],2).'</td>
            <td>₱'.number_format($item['subtotal'],2).'</td>
        </tr>';
    }

    echo '</tbody></table>';
    echo '<hr>';
    echo '<div class="d-flex justify-content-between"><strong>Total</strong>
          <strong>₱'.number_format($sale['total_amount'],2).'</strong></div>';
    echo '<div class="d-flex justify-content-between"><span>Cash</span>
          <span>₱'.number_format($sale['payment'],2).'</span></div>';
    echo '<div class="d-flex justify-content-between"><span>Change</span>
          <span>₱'.number_format($sale['change_amount'],2).'</span></div>';
    exit();
}


require_once '../Model/database.php';

/*=========================================================
    FETCH SUMMARY STATS
==========================================================*/

// Total Revenue
$revenueData = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT IFNULL(SUM(total_amount),0) AS total FROM sales WHERE status='Completed'
"));

// Today's Revenue
$todayData = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT IFNULL(SUM(total_amount),0) AS total FROM sales
    WHERE status='Completed' AND DATE(created_at)=CURDATE()
"));

// Total Orders
$ordersData = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) AS total FROM sales WHERE status='Completed'
"));

// Average Order Value
$avgData = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT IFNULL(AVG(total_amount),0) AS total FROM sales WHERE status='Completed'
"));

// Best Selling Product
$bestProduct = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT p.product_name, SUM(si.quantity) AS total_sold
    FROM sale_items si
    INNER JOIN products p ON si.product_id = p.product_id
    GROUP BY si.product_id
    ORDER BY total_sold DESC
    LIMIT 1
"));

/*=========================================================
    CHART DATA — LAST 7 DAYS
==========================================================*/

$last7 = [];
for($i = 6; $i >= 0; $i--){
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('D M d', strtotime($date));
    $row = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT IFNULL(SUM(total_amount),0) AS total
        FROM sales WHERE status='Completed' AND DATE(created_at)='$date'
    "));
    $last7['labels'][] = $label;
    $last7['data'][]   = (float)$row['total'];
}

/*=========================================================
    CHART DATA — LAST 30 DAYS
==========================================================*/

$last30 = [];
for($i = 29; $i >= 0; $i--){
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('M d', strtotime($date));
    $row = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT IFNULL(SUM(total_amount),0) AS total
        FROM sales WHERE status='Completed' AND DATE(created_at)='$date'
    "));
    $last30['labels'][] = $label;
    $last30['data'][]   = (float)$row['total'];
}

/*=========================================================
    CHART DATA — LAST 12 MONTHS
==========================================================*/

$last12 = [];
for($i = 11; $i >= 0; $i--){
    $month = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $row = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT IFNULL(SUM(total_amount),0) AS total
        FROM sales WHERE status='Completed'
        AND DATE_FORMAT(created_at,'%Y-%m')='$month'
    "));
    $last12['labels'][] = $label;
    $last12['data'][]   = (float)$row['total'];
}

/*=========================================================
    TOP PRODUCTS
==========================================================*/

$topProducts = mysqli_query($conn,"
    SELECT p.product_name, SUM(si.quantity) AS total_sold,
           SUM(si.subtotal) AS total_revenue
    FROM sale_items si
    INNER JOIN products p ON si.product_id = p.product_id
    GROUP BY si.product_id
    ORDER BY total_sold DESC
    LIMIT 5
");

/*=========================================================
    RECENT SALES
==========================================================*/

$recentSales = mysqli_query($conn,"
    SELECT s.*, u.full_name
    FROM sales s
    INNER JOIN users u ON s.cashier_id = u.user_id
    WHERE s.status='Completed'
    ORDER BY s.created_at DESC
    LIMIT 20
");

?>

<style>
.sales-card {
    background: white;
    border-radius: 12px;
    padding: 18px 22px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    height: 100%;
}
.sales-card .icon {
    width: 46px;
    height: 46px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
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
.toggle-btn {
    border: 1px solid #198754;
    color: #198754;
    background: white;
    padding: 4px 14px;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    transition: .2s;
}
.toggle-btn.active, .toggle-btn:hover {
    background: #198754;
    color: white;
}
.sale-row { cursor: pointer; }
.sale-row:hover { background: #f0faf4 !important; }
</style>

<!-- SUMMARY CARDS -->
<div class="row g-3 mb-4">

    <div class="col-lg-3 col-md-6">
        <div class="sales-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <small class="text-muted">Total Revenue</small>
                    <h3 class="fw-bold mt-1">₱<?= number_format($revenueData['total'],2); ?></h3>
                    <span class="badge bg-success mt-1">All Time</span>
                </div>
                <div class="icon bg-success"><i class="bi bi-cash-stack"></i></div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="sales-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <small class="text-muted">Today's Revenue</small>
                    <h3 class="fw-bold mt-1">₱<?= number_format($todayData['total'],2); ?></h3>
                    <span class="badge bg-primary mt-1">Today</span>
                </div>
                <div class="icon bg-primary"><i class="bi bi-graph-up-arrow"></i></div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="sales-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <small class="text-muted">Total Orders</small>
                    <h3 class="fw-bold mt-1"><?= $ordersData['total']; ?></h3>
                    <span class="badge bg-warning text-dark mt-1">Completed</span>
                </div>
                <div class="icon bg-warning"><i class="bi bi-receipt"></i></div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="sales-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <small class="text-muted">Avg Order Value</small>
                    <h3 class="fw-bold mt-1">₱<?= number_format($avgData['total'],2); ?></h3>
                    <span class="badge bg-secondary mt-1">Per Sale</span>
                </div>
                <div class="icon bg-secondary"><i class="bi bi-calculator"></i></div>
            </div>
        </div>
    </div>

</div>

<!-- SALES CHART -->
<div class="chart-card mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Sales Overview</h5>
        <div class="d-flex gap-2">
            <button class="toggle-btn active" onclick="switchChart('7days',this)">7 Days</button>
            <button class="toggle-btn" onclick="switchChart('30days',this)">30 Days</button>
            <button class="toggle-btn" onclick="switchChart('12months',this)">12 Months</button>
        </div>
    </div>
    <canvas id="salesChart" height="80"></canvas>
</div>

<div class="row">

    <!-- TOP PRODUCTS -->
    <div class="col-lg-4">
        <div class="table-card">
            <h5 class="mb-3">Top Products</h5>
            <?php if(mysqli_num_rows($topProducts) == 0){ ?>
            <p class="text-muted text-center py-3">No sales data yet</p>
            <?php } else { while($p = mysqli_fetch_assoc($topProducts)){ ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <div style="font-size:13px;font-weight:600;">
                        <?= htmlspecialchars($p['product_name']); ?>
                    </div>
                    <small class="text-muted"><?= $p['total_sold']; ?> sold</small>
                </div>
                <span class="badge bg-success">
                    ₱<?= number_format($p['total_revenue'],2); ?>
                </span>
            </div>
            <?php } } ?>
        </div>

        <?php if($bestProduct){ ?>
        <div class="table-card">
            <h5 class="mb-2">🏆 Best Seller</h5>
            <h4 class="fw-bold text-success">
                <?= htmlspecialchars($bestProduct['product_name']); ?>
            </h4>
            <small class="text-muted">
                <?= $bestProduct['total_sold']; ?> units sold total
            </small>
        </div>
        <?php } ?>
    </div>

    <!-- RECENT SALES TABLE -->
    <div class="col-lg-8">
        <div class="table-card">
            <h5 class="mb-3">Recent Sales</h5>
            <table class="table table-bordered table-striped datatable">
                <thead class="table-success">
                    <tr>
                        <th>Sale ID</th>
                        <th>Cashier</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Change</th>
                        <th>Date</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($sale = mysqli_fetch_assoc($recentSales)){ ?>
                    <tr>
                        <td>#<?= $sale['sale_id']; ?></td>
                        <td><?= htmlspecialchars($sale['full_name']); ?></td>
                        <td>₱<?= number_format($sale['total_amount'],2); ?></td>
                        <td>₱<?= number_format($sale['payment'],2); ?></td>
                        <td>₱<?= number_format($sale['change_amount'],2); ?></td>
                        <td><?= date("M d, Y h:i A", strtotime($sale['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-success"
                                onclick="viewSaleItems(<?= $sale['sale_id']; ?>)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- SALE ITEMS MODAL -->
<div class="modal fade" id="saleItemsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-receipt me-2"></i>Sale Items
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="saleItemsBody">
                <div class="text-center py-3">
                    <div class="spinner-border text-success"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>

// Chart data from PHP
const chartData = {
    '7days': {
        labels: <?= json_encode($last7['labels']); ?>,
        data:   <?= json_encode($last7['data']); ?>
    },
    '30days': {
        labels: <?= json_encode($last30['labels']); ?>,
        data:   <?= json_encode($last30['data']); ?>
    },
    '12months': {
        labels: <?= json_encode($last12['labels']); ?>,
        data:   <?= json_encode($last12['data']); ?>
    }
};

let salesChart;

function buildChart(key){
    const ctx = document.getElementById('salesChart');
    if(salesChart) salesChart.destroy();

    salesChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData[key].labels,
            datasets: [{
                label: 'Sales (₱)',
                data: chartData[key].data,
                backgroundColor: 'rgba(25,135,84,.2)',
                borderColor: '#198754',
                borderWidth: 2,
                borderRadius: 6,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => '₱' + ctx.parsed.y.toLocaleString()
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => '₱' + v.toLocaleString() }
                },
                x: {
                    ticks: { maxRotation: 45 }
                }
            }
        }
    });
}

function switchChart(key, btn){
    $(".toggle-btn").removeClass("active");
    $(btn).addClass("active");
    buildChart(key);
}

// Build default chart
// Wait for Chart.js to be ready
if(typeof Chart !== 'undefined'){
    buildChart('7days');
} else {
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
    script.onload = () => buildChart('7days');
    document.head.appendChild(script);
}

// View sale items
function viewSaleItems(saleId){
    $("#saleItemsBody").html(
        '<div class="text-center py-3"><div class="spinner-border text-success"></div></div>'
    );
    new bootstrap.Modal(document.getElementById('saleItemsModal')).show();

    $.post('sales.php', {
        action: 'get_items',
        sale_id: saleId
    }, function(response){
        $("#saleItemsBody").html(response);
    });
}

</script>