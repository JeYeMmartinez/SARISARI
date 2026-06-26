<?php

require_once '../Model/database.php';

/*=========================================================
    DASHBOARD STATISTICS
==========================================================*/

// Total Products
$productQuery = mysqli_query($conn, "
    SELECT COUNT(*) AS totalProducts
    FROM products
");
$productData = mysqli_fetch_assoc($productQuery);

// Low Stock Products
$lowStockQuery = mysqli_query($conn, "
    SELECT COUNT(*) AS totalLowStock
    FROM inventory
    WHERE quantity <= minimum_stock
");
$lowStockData = mysqli_fetch_assoc($lowStockQuery);

// Total Orders
$orderQuery = mysqli_query($conn, "
    SELECT COUNT(*) AS totalOrders
    FROM sales
");
$orderData = mysqli_fetch_assoc($orderQuery);

// Today's Sales
$salesQuery = mysqli_query($conn, "
    SELECT IFNULL(SUM(total_amount),0) AS todaysSales
    FROM sales
    WHERE DATE(created_at)=CURDATE()
");
$salesData = mysqli_fetch_assoc($salesQuery);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard</title>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="../Assets/bootstrap.min.css">

    <!-- Animate -->
    <link rel="stylesheet" href="../Assets/animate.min.css">

    <!-- DataTables -->
    <link rel="stylesheet" href="../Assets/datatables.css">

    <!-- SweetAlert -->
    <link rel="stylesheet" href="../Assets/sweetalert2.min.css">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Chart JS -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        body{
            background:#f3f6fb;
            font-family:Segoe UI,sans-serif;
            overflow-x:hidden;
        }

        /*==============================
                SIDEBAR
        ==============================*/

        .sidebar{

            position:fixed;
            left:0;
            top:0;

            width:260px;
            height:100vh;

            background:#155724;

            color:white;

            padding-top:20px;

        }

        .sidebar h3{

            text-align:center;
            margin-bottom:40px;
            font-weight:bold;

        }

        .sidebar ul{

            list-style:none;
            padding:0;

        }

        .sidebar ul li{

            padding:15px 25px;
            cursor:pointer;
            transition:.3s;

        }

        .sidebar ul li:hover{

            background:#218838;

        }

        .sidebar ul li i{

            margin-right:10px;

        }

        /*==============================
                MAIN
        ==============================*/

        .main{

            margin-left:260px;

            padding:20px 25px;

        }

        /*==============================
                TOP BAR
        ==============================*/

        .topbar{

            background:white;

            border-radius:15px;

            padding:20px;

            display:flex;

            justify-content:space-between;

            align-items:center;

            margin-bottom:30px;

            box-shadow:0 5px 15px rgba(0,0,0,.08);

        }

        .clock{

            font-weight:bold;
            color:#198754;

        }

        /*==============================
                CARDS
        ==============================*/

        .dashboard-card{

            background:#fff;

            border-radius:16px;

            padding:18px;

            height:130px;

            border:1px solid #ececec;

            box-shadow:0 4px 12px rgba(0,0,0,.05);

            transition:.25s;

            display:flex;

            justify-content:space-between;

            align-items:center;

        }

        .dashboard-card:hover{

            transform:translateY(-6px);

            box-shadow:0 15px 35px rgba(0,0,0,.12);

        }

        .icon{

            width:52px;

            height:52px;

            border-radius:14px;

            display:flex;

            justify-content:center;

            align-items:center;

            color:white;

            font-size:22px;

        }

        .dashboard-card h2{

            font-size:30px;

            font-weight:700;

            margin-top:15px;

        }

        .dashboard-card h6{

            color:#777;

            margin-top:5px;

        }

        .dashboard-card small{

            color:#999;

        }

        /*==============================
                CHART
        ==============================*/

        .chart-card{

            background:white;

            margin-top:20px;

            border-radius:16px;

            padding:20px;

            height:450px;

            border:1px solid #ececec;

            box-shadow:0 5px 15px rgba(0,0,0,.05);

        }

        /*==============================
                TABLE
        ==============================*/

        .table-card{

            margin-top:25px;

            background:white;

            border-radius:20px;

            padding:25px;

            box-shadow:0 5px 15px rgba(0,0,0,.08);

        }

        .notification{

            background:#f8f9fa;

            padding:12px;

            border-radius:10px;

            margin-bottom:10px;

            border-left:5px solid #198754;

        }

    </style>

</head>

<body>

<!--=====================================================
                    SIDEBAR
======================================================-->

<div class="sidebar animate__animated animate__fadeInLeft">

    <h3>Sari-Sari Store</h3>

    <ul>

        <li><i class="bi bi-speedometer2"></i> Dashboard</li>

        <li><i class="bi bi-bar-chart-line"></i> Sales Analytics</li>

        <li><i class="bi bi-box-seam"></i> Inventory</li>

        <li><i class="bi bi-bag"></i> Products</li>

        <li><i class="bi bi-cart3"></i> Orders</li>

        <li><i class="bi bi-check-circle"></i> Approvals</li>

        <li><i class="bi bi-calculator"></i> Cashier</li>

        <li><i class="bi bi-bell"></i> Notifications</li>

        <li><i class="bi bi-gear"></i> Settings</li>

        <li><i class="bi bi-box-arrow-right"></i> Logout</li>

    </ul>

</div>

<!--=====================================================
                    MAIN CONTENT
======================================================-->

<div class="main">

    <!--==========================
            TOPBAR
    ===========================-->

    <div class="topbar animate__animated animate__fadeInDown">

        <div>

            <h3 class="fw-bold mb-0">
                Dashboard
            </h3>

            <small class="text-muted">
                Monitor your store's daily activity
            </small>

        </div>

        <div class="clock text-end">

            <div id="clock"></div>

        </div>

    </div>

    <!--==========================
            SUMMARY CARDS
    ===========================-->

    <div class="row g-4 mb-4">

        <!-- Today's Sales -->
        <div class="col-lg-3 col-md-6 mb-3">

            <div class="dashboard-card">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <small class="text-muted">
                            Today's Sales
                        </small>

                        <h3 class="fw-bold mt-2">
                            ₱<?= number_format($salesData['todaysSales'],2); ?>
                        </h3>

                        <span class="badge bg-success mt-2">
                            +12%
                        </span>

                    </div>

                    <div class="icon bg-success">

                        <i class="bi bi-cash-stack"></i>

                    </div>

                </div>

            </div>

        </div>

        <!-- Orders -->

        <div class="col-xl-3 col-md-6">

            <div class="dashboard-card">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <small class="text-muted">
                            Orders Today
                        </small>

                        <h3 class="fw-bold mt-2">
                            <?= $orderData['totalOrders']; ?>
                        </h3>

                        <span class="badge bg-primary">
                            Completed
                        </span>

                    </div>

                    <div class="icon bg-primary">

                        <i class="bi bi-cart-check"></i>

                    </div>

                </div>

            </div>

        </div>

        <!-- Products -->

        <div class="col-xl-3 col-md-6">

            <div class="dashboard-card">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <small class="text-muted">
                            Products
                        </small>

                        <h3 class="fw-bold mt-2">
                            <?= $productData['totalProducts']; ?>
                        </h3>

                        <span class="badge bg-warning text-dark">
                            In Stock
                        </span>

                    </div>

                    <div class="icon bg-warning">

                        <i class="bi bi-box-seam"></i>

                    </div>

                </div>

            </div>

        </div>

        <!-- Low Stock -->

        <div class="col-xl-3 col-md-6">

            <div class="dashboard-card">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <small class="text-muted">
                            Low Stock
                        </small>

                        <h3 class="fw-bold mt-2 text-danger">
                            <?= $lowStockData['totalLowStock']; ?>
                        </h3>

                        <span class="badge bg-danger">
                            Needs Attention
                        </span>

                    </div>

                    <div class="icon bg-danger">

                        <i class="bi bi-exclamation-triangle"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!--==========================
            CHARTS
    ===========================-->

    <div class="row">

        <div class="col-lg-9">

            <div class="chart-card">

                <h5>Sales Overview</h5>

                <canvas id="salesChart"></canvas>

            </div>

        </div>

        <div class="col-lg-3">

            <div class="chart-card">

                <h5>Sales Categories</h5>

                <canvas id="pieChart"></canvas>

            </div>

        </div>

    </div>

    <!--==========================
            TABLES
    ===========================-->

    <div class="row">

        <div class="col-lg-8">

            <div class="table-card">

                <h5 class="mb-4">Recent Sales</h5>

                <table class="table table-bordered table-striped" id="salesTable">

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
                        SELECT
                            sales.sale_id,
                            users.full_name,
                            sales.total_amount,
                            sales.status,
                            sales.created_at
                        FROM sales
                        INNER JOIN users
                        ON sales.cashier_id = users.user_id
                        ORDER BY sales.created_at DESC
                        LIMIT 10
                        ");

                        while($sale = mysqli_fetch_assoc($recentSales))
                        {

                        ?>

                        <tr>

                            <td><?= $sale['sale_id']; ?></td>

                            <td><?= $sale['full_name']; ?></td>

                            <td>₱<?= number_format($sale['total_amount'],2); ?></td>

                            <td>

                                <?php

                                if($sale['status']=="Completed")
                                {

                                    echo '<span class="badge bg-success">Completed</span>';

                                }
                                else
                                {

                                    echo '<span class="badge bg-warning text-dark">'.$sale['status'].'</span>';

                                }

                                ?>

                            </td>

                            <td><?= date("M d, Y",strtotime($sale['created_at'])); ?></td>

                        </tr>

                        <?php

                        }

                        ?>

                    </tbody>

                </table>

            </div>

        </div>

        <div class="col-lg-4">

            <div class="table-card">

                <h5>Notifications</h5>

                <div class="notification">

                    Testing notification card!
                </div>

                <div class="notification">

                    Testing notification card!

                </div>

                <div class="notification">

                    Testing notification card!

                </div>

                <div class="notification">

                    Testing notification card!

                </div>

            </div>

        </div>

    </div>

</div>

<!--=====================================================
                JAVASCRIPT
======================================================-->

<script src="../Assets/jquery.min.js"></script>

<script src="../Assets/bootstrap.min.js"></script>

<script src="../Assets/datatables.min.js"></script>

<script src="../Assets/sweetalert2.min.js"></script>

<script>

$("#salesTable").DataTable();

function updateClock(){

    const now = new Date();

    document.getElementById("clock").innerHTML =
        now.toLocaleDateString() + " | " +
        now.toLocaleTimeString();

}

setInterval(updateClock,1000);

updateClock();

const ctx=document.getElementById("salesChart");

new Chart(ctx,{

type:'line',

data:{

labels:['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],

datasets:[{

label:'Sales',

data:[500,900,700,1300,900,1500,1700],

borderColor:'#198754',

backgroundColor:'rgba(25,135,84,.2)',

fill:true,

tension:.4

}]

}

});

const pie=document.getElementById("pieChart");

new Chart(pie,{

type:'doughnut',

data:{

labels:['Beverages','Snacks','Canned','Noodles'],

datasets:[{

data:[35,25,20,20],

backgroundColor:[

'#198754',

'#20c997',

'#ffc107',

'#dc3545'

]

}]

}

});

</script>

</body>

</html>