<?php

require_once '../Model/database.php';

// Get sale items for modal
if(isset($_POST['action']) && $_POST['action'] == 'get_items'){
    $sale_id = (int)$_POST['sale_id'];
    $items = mysqli_query($conn,"
        SELECT si.*, p.product_name
        FROM sale_items si
        LEFT JOIN products p ON si.product_id = p.product_id
        WHERE si.sale_id = $sale_id
    ");
    $sale = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM sales WHERE sale_id = $sale_id"
    ));

    if(!$sale){
        echo '<p class="text-danger text-center py-3">Sale not found.</p>';
        exit();
    }

    echo '<div style="font-family:monospace;font-size:13px;">';
    echo '<div class="text-center mb-3">
            <strong style="font-size:15px;">🏪 Sari-Sari Store</strong><br>
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

/*=========================================================
    FETCH SUMMARY STATS
==========================================================*/

// Total Revenue (Gross — from sales only)
$revenueData = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT IFNULL(SUM(total_amount),0) AS total FROM sales WHERE status='Completed'
"));

// Today's Revenue (Gross)
$todayData = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT IFNULL(SUM(total_amount),0) AS total FROM sales
    WHERE status='Completed' AND DATE(created_at)=CURDATE()
"));

// Total Restock Expenses (All Time)
$restockExpenseData = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT IFNULL(SUM(total_cost),0) AS total FROM restock_logs
"));

// Today's Restock Expenses
$todayRestockData = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT IFNULL(SUM(total_cost),0) AS total FROM restock_logs
    WHERE DATE(restocked_at)=CURDATE()
"));

// Net Revenue = Gross Sales - Restock Expenses
$netRevenue      = max(0, (float)$revenueData['total'] - (float)$restockExpenseData['total']);
$todayNetRevenue = max(0, (float)$todayData['total']   - (float)$todayRestockData['total']);

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
    $date  = date('Y-m-d', strtotime("-$i days"));
    $label = date('D M d', strtotime($date));
    $row   = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT IFNULL(SUM(total_amount),0) AS total
        FROM sales WHERE status='Completed' AND DATE(created_at)='$date'
    "));
    $units = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT IFNULL(SUM(si.quantity),0) AS total
        FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.sale_id
        WHERE s.status='Completed' AND DATE(s.created_at)='$date'
    "));
    $last7['labels'][] = $label;
    $last7['data'][]   = (float)$row['total'];
    $last7['units'][]  = (int)$units['total'];
}

/*=========================================================
    CHART DATA — LAST 30 DAYS
==========================================================*/

$last30 = [];
for($i = 29; $i >= 0; $i--){
    $date  = date('Y-m-d', strtotime("-$i days"));
    $label = date('M d', strtotime($date));
    $row   = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT IFNULL(SUM(total_amount),0) AS total
        FROM sales WHERE status='Completed' AND DATE(created_at)='$date'
    "));
    $units = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT IFNULL(SUM(si.quantity),0) AS total
        FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.sale_id
        WHERE s.status='Completed' AND DATE(s.created_at)='$date'
    "));
    $last30['labels'][] = $label;
    $last30['data'][]   = (float)$row['total'];
    $last30['units'][]  = (int)$units['total'];
}

/*=========================================================
    CHART DATA — LAST 12 MONTHS
==========================================================*/

$last12 = [];
for($i = 11; $i >= 0; $i--){
    $month = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $row   = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT IFNULL(SUM(total_amount),0) AS total
        FROM sales WHERE status='Completed'
        AND DATE_FORMAT(created_at,'%Y-%m')='$month'
    "));
    $units = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT IFNULL(SUM(si.quantity),0) AS total
        FROM sale_items si
        INNER JOIN sales s ON si.sale_id = s.sale_id
        WHERE s.status='Completed'
        AND DATE_FORMAT(s.created_at,'%Y-%m')='$month'
    "));
    $last12['labels'][] = $label;
    $last12['data'][]   = (float)$row['total'];
    $last12['units'][]  = (int)$units['total'];
}

/*=========================================================
    AJAX: GET FILTERED CHART DATA
==========================================================*/
if(isset($_POST['action']) && $_POST['action'] == 'get_chart_data'){
    $period      = $_POST['period']      ?? '7days';
    $product_id  = (int)($_POST['product_id']  ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $mode        = $_POST['mode'] ?? 'peso';

    $productFilter  = $product_id  ? "AND si.product_id = $product_id"                           : '';
    $categoryFilter = $category_id ? "AND p.category_id = $category_id"                          : '';
    $joinProducts   = ($category_id || $product_id) ? "INNER JOIN products p ON si.product_id = p.product_id" : '';

    $labels = [];
    $data   = [];

    if($period === '7days'){
        for($i = 6; $i >= 0; $i--){
            $date     = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('D M d', strtotime($date));
            $row = mysqli_fetch_assoc(mysqli_query($conn,"
                SELECT IFNULL(SUM(" . ($mode==='units' ? 'si.quantity' : 'si.subtotal') . "),0) AS total
                FROM sale_items si
                INNER JOIN sales s ON si.sale_id = s.sale_id
                $joinProducts
                WHERE s.status='Completed'
                AND DATE(s.created_at)='$date'
                $productFilter $categoryFilter
            "));
            $data[] = $mode === 'units' ? (int)$row['total'] : (float)$row['total'];
        }
    } elseif($period === '30days'){
        for($i = 29; $i >= 0; $i--){
            $date     = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M d', strtotime($date));
            $row = mysqli_fetch_assoc(mysqli_query($conn,"
                SELECT IFNULL(SUM(" . ($mode==='units' ? 'si.quantity' : 'si.subtotal') . "),0) AS total
                FROM sale_items si
                INNER JOIN sales s ON si.sale_id = s.sale_id
                $joinProducts
                WHERE s.status='Completed'
                AND DATE(s.created_at)='$date'
                $productFilter $categoryFilter
            "));
            $data[] = $mode === 'units' ? (int)$row['total'] : (float)$row['total'];
        }
    } else {
        for($i = 11; $i >= 0; $i--){
            $month    = date('Y-m', strtotime("-$i months"));
            $labels[] = date('M Y', strtotime("-$i months"));
            $row = mysqli_fetch_assoc(mysqli_query($conn,"
                SELECT IFNULL(SUM(" . ($mode==='units' ? 'si.quantity' : 'si.subtotal') . "),0) AS total
                FROM sale_items si
                INNER JOIN sales s ON si.sale_id = s.sale_id
                $joinProducts
                WHERE s.status='Completed'
                AND DATE_FORMAT(s.created_at,'%Y-%m')='$month'
                $productFilter $categoryFilter
            "));
            $data[] = $mode === 'units' ? (int)$row['total'] : (float)$row['total'];
        }
    }

    echo json_encode(['labels' => $labels, 'data' => $data]);
    exit();
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

    <!-- Gross Revenue -->
    <div class="col-lg-3 col-md-6">
        <div class="sales-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <small class="text-muted">Gross Revenue</small>
                    <h3 class="fw-bold mt-1">₱<?= number_format($revenueData['total'],2); ?></h3>
                    <span class="badge bg-success mt-1">All-Time Sales</span>
                </div>
                <div class="icon bg-success"><i class="bi bi-cash-stack"></i></div>
            </div>
        </div>
    </div>

    <!-- Restock Expenses -->
    <div class="col-lg-3 col-md-6">
        <div class="sales-card" style="border-left:4px solid #dc3545;">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <small class="text-muted">Restock Expenses</small>
                    <h3 class="fw-bold mt-1 text-danger">−₱<?= number_format($restockExpenseData['total'],2); ?></h3>
                    <span class="badge bg-danger mt-1">Capital Spent</span>
                </div>
                <div class="icon bg-danger"><i class="bi bi-boxes"></i></div>
            </div>
        </div>
    </div>

    <!-- Net Revenue -->
    <div class="col-lg-3 col-md-6">
        <div class="sales-card" style="border-left:4px solid #0d6efd;">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <small class="text-muted">Net Revenue</small>
                    <h3 class="fw-bold mt-1 text-primary">₱<?= number_format($netRevenue,2); ?></h3>
                    <span class="badge bg-primary mt-1">After Restock Cost</span>
                </div>
                <div class="icon bg-primary"><i class="bi bi-graph-up-arrow"></i></div>
            </div>
        </div>
    </div>

    <!-- Today's Net Revenue -->
    <div class="col-lg-3 col-md-6">
        <div class="sales-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <small class="text-muted">Today's Net Revenue</small>
                    <h3 class="fw-bold mt-1">₱<?= number_format($todayNetRevenue,2); ?></h3>
                    <div style="font-size:11px;" class="text-muted mt-1">
                        Sales ₱<?= number_format($todayData['total'],2); ?>
                        <?php if($todayRestockData['total'] > 0){ ?>
                        <span class="text-danger ms-1">− Restock ₱<?= number_format($todayRestockData['total'],2); ?></span>
                        <?php } ?>
                    </div>
                    <span class="badge bg-info text-dark mt-1">Today</span>
                </div>
                <div class="icon bg-info"><i class="bi bi-calendar-check"></i></div>
            </div>
        </div>
    </div>

</div>

<!-- SALES CHART -->
<div class="chart-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h5 class="mb-0">Sales Overview</h5>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <!-- Units / Peso toggle -->
            <div class="btn-group btn-group-sm">
                <button class="toggle-btn active" id="btnPeso" onclick="switchMode('peso',this)">₱ Peso</button>
                <button class="toggle-btn" id="btnUnits" onclick="switchMode('units',this)">📦 Units</button>
            </div>
            <!-- Period toggle -->
            <div class="btn-group btn-group-sm">
                <button class="toggle-btn active" id="btn7" onclick="switchPeriod('7days',this)">7 Days</button>
                <button class="toggle-btn" id="btn30" onclick="switchPeriod('30days',this)">30 Days</button>
                <button class="toggle-btn" id="btn12" onclick="switchPeriod('12months',this)">12 Months</button>
            </div>
            <!-- Product filter -->
            <select class="form-select form-select-sm" id="productFilter" style="width:160px;"
                    onchange="applyFilters()">
                <option value="">All Products</option>
                <?php
                $allProds = mysqli_query($conn,"
                    SELECT DISTINCT p.product_id, p.product_name
                    FROM sale_items si
                    INNER JOIN products p ON si.product_id = p.product_id
                    ORDER BY p.product_name ASC
                ");
                while($pr = mysqli_fetch_assoc($allProds)){
                    echo '<option value="'.$pr['product_id'].'">'.htmlspecialchars($pr['product_name']).'</option>';
                }
                ?>
            </select>
            <!-- Category filter -->
            <select class="form-select form-select-sm" id="categoryFilter" style="width:160px;"
                    onchange="applyFilters()">
                <option value="">All Categories</option>
                <?php
                $allCats = mysqli_query($conn,"
                    SELECT DISTINCT c.category_id, c.category_name
                    FROM sale_items si
                    INNER JOIN products p ON si.product_id = p.product_id
                    INNER JOIN categories c ON p.category_id = c.category_id
                    ORDER BY c.category_name ASC
                ");
                while($cat = mysqli_fetch_assoc($allCats)){
                    echo '<option value="'.$cat['category_id'].'">'.htmlspecialchars($cat['category_name']).'</option>';
                }
                ?>
            </select>
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

// PHP chart data embedded
const chartData = {
    '7days': {
        labels: <?= json_encode($last7['labels']); ?>,
        data:   <?= json_encode($last7['data']); ?>,
        units:  <?= json_encode($last7['units']); ?>
    },
    '30days': {
        labels: <?= json_encode($last30['labels']); ?>,
        data:   <?= json_encode($last30['data']); ?>,
        units:  <?= json_encode($last30['units']); ?>
    },
    '12months': {
        labels: <?= json_encode($last12['labels']); ?>,
        data:   <?= json_encode($last12['data']); ?>,
        units:  <?= json_encode($last12['units']); ?>
    }
};

let salesChart  = null;
let curPeriod   = '7days';
let curMode     = 'peso'; // 'peso' or 'units'

function buildChart(period, mode, filteredLabels, filteredData){
    const ctx = document.getElementById('salesChart');
    if(!ctx) return;

    if(salesChart){ salesChart.destroy(); salesChart = null; }

    const labels = filteredLabels || chartData[period].labels;
    const data   = filteredData  || (mode === 'units' ? chartData[period].units : chartData[period].data);
    const isPeso = mode === 'peso';

    salesChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: isPeso ? 'Sales (₱)' : 'Units Sold',
                data: data,
                backgroundColor: isPeso ? 'rgba(25,135,84,.2)' : 'rgba(13,110,253,.2)',
                borderColor:     isPeso ? '#198754' : '#0d6efd',
                borderWidth: 2,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => isPeso
                            ? '₱' + ctx.parsed.y.toLocaleString()
                            : ctx.parsed.y + ' units'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => isPeso ? '₱' + v.toLocaleString() : v + ' u'
                    }
                },
                x: { ticks: { maxRotation: 45 } }
            }
        }
    });
}

function switchPeriod(period, btn){
    curPeriod = period;
    $('.toggle-btn[id^="btn7"], .toggle-btn[id^="btn3"], .toggle-btn[id^="btn1"]').removeClass('active');
    // easier: reset period buttons only
    $('#btn7,#btn30,#btn12').removeClass('active');
    $(btn).addClass('active');
    applyFilters();
}

function switchMode(mode, btn){
    curMode = mode;
    $('#btnPeso,#btnUnits').removeClass('active');
    $(btn).addClass('active');
    applyFilters();
}

function applyFilters(){
    const productId  = $('#productFilter').val();
    const categoryId = $('#categoryFilter').val();

    if(!productId && !categoryId){
        buildChart(curPeriod, curMode);
        return;
    }

    // AJAX fetch filtered data
    $.post('sales.php', {
        action:      'get_chart_data',
        period:      curPeriod,
        product_id:  productId,
        category_id: categoryId,
        mode:        curMode
    }, function(res){
        try {
            const d = JSON.parse(res);
            buildChart(curPeriod, curMode, d.labels, d.data);
        } catch(e){
            buildChart(curPeriod, curMode);
        }
    });
}

// Init — destroy any existing chart first to handle AJAX reload
if(typeof Chart !== 'undefined'){
    // Destroy orphan if any
    const existing = Chart.getChart('salesChart');
    if(existing) existing.destroy();
    buildChart('7days', 'peso');
} else {
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
    s.onload = () => buildChart('7days', 'peso');
    document.head.appendChild(s);
}

// View sale receipt
function viewSaleItems(saleId){
    $("#saleItemsBody").html(
        '<div class="text-center py-3"><div class="spinner-border text-success"></div></div>'
    );
    new bootstrap.Modal(document.getElementById('saleItemsModal')).show();
    $.post('sales.php', { action: 'get_items', sale_id: saleId }, function(response){
        $("#saleItemsBody").html(response);
    });
}

</script>