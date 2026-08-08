<?php
error_reporting(E_ALL & ~E_NOTICE);
require_once("../Model/database.php");

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

if($_SESSION['role'] != 'Admin'){
    header("Location: login.php");
    exit();
}

$current_name = $_SESSION['full_name'] ?? 'Admin';
$current_role = 'Admin';

$page = $_GET['page'] ?? 'Inventory_employee/inv_records.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Portal — O-CART!</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
* { margin:0; padding:0; box-sizing:border-box; }

body {
    background: #F4F7F6;
    font-family: 'Segoe UI', sans-serif;
    overflow: hidden;
}

/*==========================
    SIDEBAR (Inventory Blue/Teal Theme)
==========================*/
.sidebar {
    position: fixed;
    left: 0; top: 0;
    width: 260px;
    height: 100vh;
    background: #0f4c81;
    color: white;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    z-index: 1000;
}

.sidebar::-webkit-scrollbar { width: 4px; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.2); border-radius: 4px; }

.logo {
    padding: 20px 24px;
    border-bottom: 1px solid rgba(255,255,255,.15);
    display: flex;
    align-items: center;
    gap: 12px;
}

.logo-icon {
    width: 42px; height: 42px;
    background: #0d6efd;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
}

.logo-text { line-height: 1.2; }
.logo-text strong { font-size: 15px; display: block; }
.logo-text small { font-size: 11px; opacity: .7; letter-spacing: .5px; text-transform: uppercase; }

.sidebar-section {
    padding: 14px 20px 6px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    opacity: .5;
}

.menu {
    list-style: none;
    padding: 0 10px;
}

.menu li { border-radius: 8px; transition: .2s; margin-bottom: 2px; }
.menu li:hover { background: rgba(255,255,255,.08); }
.menu li.active { background: #0d6efd; }

.menu li a {
    display: flex;
    align-items: center;
    gap: 12px;
    color: rgba(255,255,255,.9);
    text-decoration: none;
    padding: 11px 14px;
    font-size: 13.5px;
    border-radius: 8px;
}

.menu li.active a { color: white; font-weight: 600; }
.menu li a i { font-size: 17px; width: 20px; text-align: center; }

.sidebar-footer {
    margin-top: auto;
    padding: 16px;
    border-top: 1px solid rgba(255,255,255,.15);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: rgba(255,255,255,.07);
    border-radius: 10px;
    margin-bottom: 10px;
}

.user-avatar {
    width: 34px; height: 34px;
    background: #0d6efd;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; flex-shrink: 0;
    font-weight: bold;
    color: white;
}

.user-name { font-size: 13px; font-weight: 600; line-height: 1.2; }
.user-role { font-size: 11px; opacity: .7; }

/*==========================
    MAIN
==========================*/
.main {
    margin-left: 260px;
    width: calc(100% - 260px);
    height: 100vh;
    display: flex;
    flex-direction: column;
}

/*==========================
    TOPBAR
==========================*/
.topbar {
    height: 65px;
    background: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 28px;
    box-shadow: 0 1px 6px rgba(0,0,0,.05);
    flex-shrink: 0;
}

.topbar-title {
    font-size: 22px;
    font-weight: 700;
    color: #0f4c81;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 14px;
}

#clock {
    font-size: 13px;
    font-weight: 600;
    color: #0d6efd;
    text-align: right;
}

/*==========================
    CONTENT
==========================*/
#content {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
}

.page-card {
    background: white;
    border-radius: 14px;
    padding: 22px 24px;
    box-shadow: 0 2px 10px rgba(0,0,0,.04);
    margin-bottom: 22px;
}
</style>

</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <div class="logo">
        <div class="logo-icon">📦</div>
        <div class="logo-text">
            <strong>O-CART!</strong>
            <small>Inventory Portal</small>
        </div>
    </div>

    <!-- INVENTORY -->
    <div class="sidebar-section">Inventory</div>
    <ul class="menu">
        <li <?= (strpos($page, 'inv_records.php') !== false) ? 'class="active"' : '' ?>>
            <a href="#" onclick="loadPage('Inventory_employee/inv_records.php', this)">
                <i class="bi bi-archive-fill"></i> Inventory Records
            </a>
        </li>
        <li <?= (strpos($page, 'products.php') !== false) ? 'class="active"' : '' ?>>
            <a href="#" onclick="loadPage('products.php', this)">
                <i class="bi bi-tags-fill"></i> Product Catalog
            </a>
        </li>
    </ul>

    <!-- STOCK OPERATIONS -->
    <div class="sidebar-section">Stock Operations</div>
    <ul class="menu">
        <li <?= (strpos($page, 'inv_stock_in.php') !== false) ? 'class="active"' : '' ?>>
            <a href="#" onclick="loadPage('Inventory_employee/inv_stock_in.php', this)">
                <i class="bi bi-box-arrow-in-down-right"></i> Stock In
            </a>
        </li>
        <li <?= (strpos($page, 'inv_stock_out.php') !== false) ? 'class="active"' : '' ?>>
            <a href="#" onclick="loadPage('Inventory_employee/inv_stock_out.php', this)">
                <i class="bi bi-box-arrow-up-right"></i> Stock Out
            </a>
        </li>
        <li <?= (strpos($page, 'inv_adjustment.php') !== false) ? 'class="active"' : '' ?>>
            <a href="#" onclick="loadPage('Inventory_employee/inv_adjustment.php', this)">
                <i class="bi bi-sliders"></i> Stock Adjustment
            </a>
        </li>
        <li <?= (strpos($page, 'inv_transfer.php') !== false) ? 'class="active"' : '' ?>>
            <a href="#" onclick="loadPage('Inventory_employee/inv_transfer.php', this)">
                <i class="bi bi-arrow-left-right"></i> Receive Transfers
            </a>
        </li>
        <li <?= (strpos($page, 'warehouse_dispatches.php') !== false) ? 'class="active"' : '' ?>>
            <a href="#" onclick="loadPage('warehouse/warehouse_dispatches.php?portal=inventory', this)">
                <i class="bi bi-box-seam-fill"></i> Warehouse Dispatches
            </a>
        </li>
    </ul>

    <!-- MONITORING -->
    <div class="sidebar-section">Monitoring</div>
    <ul class="menu">
        <li <?= (strpos($page, 'inv_low_stock.php') !== false) ? 'class="active"' : '' ?>>
            <a href="#" onclick="loadPage('Inventory_employee/inv_low_stock.php', this)">
                <i class="bi bi-exclamation-triangle-fill"></i> Low Stock Alert
            </a>
        </li>
        <li <?= (strpos($page, 'inv_movement.php') !== false) ? 'class="active"' : '' ?>>
            <a href="#" onclick="loadPage('Inventory_employee/inv_movement.php', this)">
                <i class="bi bi-clock-history"></i> Stock Movement History
            </a>
        </li>
        <li <?= (strpos($page, 'notification.php') !== false) ? 'class="active"' : '' ?>>
            <a href="#" onclick="loadPage('notification.php', this)">
                <i class="bi bi-bell-fill"></i> Notifications
            </a>
        </li>
    </ul>

    <!-- LOGS -->
    <div class="sidebar-section">Logs &amp; Audit</div>
    <ul class="menu">
        <li <?= (strpos($page, 'inv_logs.php') !== false) ? 'class="active"' : '' ?>>
            <a href="#" onclick="loadPage('Inventory_employee/inv_logs.php', this)">
                <i class="bi bi-journal-text"></i> Inventory Audit Logs
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr($current_name, 0, 1)); ?></div>
            <div>
                <div class="user-name"><?= htmlspecialchars($current_name); ?></div>
                <div class="user-role"><?= htmlspecialchars($current_role); ?></div>
            </div>
        </div>
        <a href="admin.php" class="btn btn-sm btn-outline-light w-100 mb-2">
            <i class="bi bi-arrow-left-circle me-1"></i> Back to Main Menu
        </a>
        <a href="#" class="btn btn-sm btn-outline-danger w-100 logout-link">
            <i class="bi bi-box-arrow-right me-1"></i> Logout
        </a>
    </div>

</div>

<!-- MAIN -->
<div class="main">

    <div class="topbar">
        <div class="topbar-title" id="pageTitle">Dashboard Home</div>
        <div class="topbar-right">
            <div id="clock"></div>
            <div class="dropdown">
                <button class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= htmlspecialchars($current_name); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text text-muted" style="font-size:12px;">
                        Role: Admin
                    </span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger logout-link" href="inv_logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a></li>
                </ul>
            </div>
        </div>
    </div>

    <div id="content">
        <?php
        $targetPage = basename($page);
        $invDir = __DIR__ . '/Inventory_employee';
        $whDir  = __DIR__ . '/warehouse';
        if (file_exists($invDir . '/' . $targetPage)) {
            chdir($invDir);
            include $targetPage;
            chdir(__DIR__);
        } else if (file_exists($whDir . '/' . $targetPage)) {
            chdir($whDir);
            include $targetPage;
            chdir(__DIR__);
        } else if (file_exists(__DIR__ . '/' . $targetPage)) {
            include __DIR__ . '/' . $targetPage;
        } else {
            echo "<div class='alert alert-danger m-3'><h5>Unable to load page.</h5><p>404 Not Found (" . htmlspecialchars($targetPage) . ")</p></div>";
        }
        ?>
    </div>

</div>

<script>
/* LIVE CLOCK */
function updateClock(){
    const now = new Date();
    const options = { weekday:'long', year:'numeric', month:'long', day:'numeric' };
    const date = now.toLocaleDateString('en-US', options);
    const time = now.toLocaleTimeString();
    $("#clock").html(date + "<br><small>" + time + "</small>");
}
setInterval(updateClock, 1000);
updateClock();

/* PAGE TITLES */
const pageTitles = {
    'inv_home.php':         'Dashboard Home',
    'inv_records.php':      'Inventory Records',
    'products.php':         'Product Catalog',
    'inv_stock_in.php':     'Stock In',
    'inv_stock_out.php':    'Stock Out',
    'inv_adjustment.php':   'Stock Adjustment',
    'inv_transfer.php':     'Stock Transfer',
    'warehouse.php':        'Warehouse Dispatches',
    'inv_low_stock.php':    'Low Stock Alert',
    'inv_movement.php':     'Stock Movement History',
    'inv_logs.php':         'Inventory Audit Logs'
};

const currentSubPage = '<?= basename($page); ?>';
if (pageTitles[currentSubPage]) {
    $("#pageTitle").text(pageTitles[currentSubPage]);
}

/* SIDEBAR ACTIVE */
function activeMenu(element){
    $(".menu li").removeClass("active");
    $(element).parent().addClass("active");
}

/* LOAD PAGE VIA AJAX */
function loadPage(page, element = null){
    if (window.event && window.event.preventDefault) {
        window.event.preventDefault();
    }
    if (element) activeMenu(element);
    const subPage = page.split('/').pop();
    if (pageTitles[subPage]) {
        $("#pageTitle").text(pageTitles[subPage]);
    }
    $("#content").html(`
        <div class="d-flex justify-content-center align-items-center" style="min-height:300px;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `);
    $.ajax({
        url: page,
        type: 'GET',
        success: function(data){
            $("#content").html(data);
            initializePlugins();
        },
        error: function(){
            $("#content").html('<div class="alert alert-danger m-3">Error loading page content.</div>');
        }
    });
}

$(document).on('click', '.sidebar a[href="#"], .menu a[href="#"]', function(e) {
    e.preventDefault();
});

/* DATATABLES INITIALIZER */
function initializePlugins(){
    if($.fn.DataTable){
        $("table.datatable").each(function(){
            if(!$.fn.DataTable.isDataTable(this)){
                $(this).DataTable({
                    responsive: true,
                    pageLength: 10,
                    lengthChange: false,
                    ordering: true,
                    searching: true
                });
            }
        });
    }
}

/* LOGOUT */
$(document).on('click', '.logout-link', function(e){
    e.preventDefault();
    Swal.fire({
        title: 'Log out?',
        text: 'You will be signed out of the Inventory Portal.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, log out'
    }).then(result => {
        if(result.isConfirmed) window.location.href = 'inv_logout.php';
    });
});

/* INITIAL LOAD */
$(document).ready(function(){
    initializePlugins();
});
</script>
</body>
</html>
