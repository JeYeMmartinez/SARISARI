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

$page = $_GET['page'] ?? 'warehouse/warehouse_dispatches.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Portal — O-CART!</title>

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
    LAYOUT
==========================*/
.sidebar {
    width: 250px;
    height: 100vh;
    background: #0f172a;
    color: #fff;
    position: fixed;
    top: 0; left: 0;
    display: flex;
    flex-direction: column;
    z-index: 100;
    overflow-y: auto;
}
.sidebar::-webkit-scrollbar { width: 4px; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.2); border-radius: 4px; }

.main {
    margin-left: 250px;
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: #f8fafc;
}

/*==========================
    SIDEBAR COMPONENT
==========================*/
.sidebar .logo {
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid rgba(255,255,255,.08);
}
.sidebar .logo-icon {
    font-size: 26px;
    background: #2563eb;
    width: 44px; height: 44px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
}
.sidebar .logo-text strong { display: block; font-size: 16px; color: #fff; }
.sidebar .logo-text small { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; }

.sidebar-section {
    padding: 16px 20px 6px;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    color: #64748b;
    font-weight: 700;
}

.menu { list-style: none; padding: 0 10px; }
.menu li { margin-bottom: 3px; }
.menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    color: #94a3b8;
    text-decoration: none;
    font-size: 13.5px;
    border-radius: 8px;
    transition: all .2s;
}
.menu a:hover {
    background: rgba(255,255,255,.06);
    color: #fff;
}
.menu li.active a {
    background: #2563eb;
    color: #fff;
    font-weight: 600;
}
.menu a i { font-size: 16px; width: 20px; text-align: center; }

.sidebar-footer {
    margin-top: auto;
    padding: 16px;
    border-top: 1px solid rgba(255,255,255,.08);
}
.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}
.user-avatar {
    width: 36px; height: 36px;
    background: #334155;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; color: #60a5fa; font-size: 14px;
}
.user-name { font-size: 13px; font-weight: 600; color: #fff; line-height: 1.2; }
.user-role { font-size: 11px; color: #94a3b8; }

/*==========================
    TOPBAR
==========================*/
.topbar {
    height: 64px;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    padding: 0 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.topbar-title {
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
}
.topbar-right {
    display: flex;
    align-items: center;
    gap: 16px;
}
#clock {
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
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
        <div class="logo-icon">🏢</div>
        <div class="logo-text">
            <strong>O-CART!</strong>
            <small>Warehouse Portal</small>
        </div>
    </div>

    <!-- CENTRAL STORAGE & REQUESTS -->
    <div class="sidebar-section">Storage &amp; Procurement</div>
    <ul class="menu">
        <li <?= (strpos($page, 'warehouse_storage.php') !== false) ? 'class="active"' : '' ?>>
            <a href="#" onclick="loadPage('warehouse/warehouse_storage.php', this)">
                <i class="bi bi-boxes"></i> Warehouse Storage
            </a>
        </li>
        <li <?= (strpos($page, 'transfer_requests.php') !== false) ? 'class="active"' : '' ?>>
            <a href="#" onclick="loadPage('warehouse/transfer_requests.php', this)">
                <i class="bi bi-arrow-left-right"></i> Transfer Requests
            </a>
        </li>
        <li <?= (strpos($page, 'order_monitoring.php') !== false) ? 'class="active"' : '' ?>>
            <a href="#" onclick="loadPage('warehouse/order_monitoring.php', this)">
                <i class="bi bi-truck-flatbed"></i> Order Monitoring
            </a>
        </li>
    </ul>

    <!-- CENTRAL WAREHOUSE SHIPPING -->
    <div class="sidebar-section">Warehouse Shipping</div>
    <ul class="menu">
        <li <?= (strpos($page, 'warehouse_dispatches.php') !== false) ? 'class="active"' : '' ?>>
            <a href="#" onclick="loadPage('warehouse/warehouse_dispatches.php?portal=warehouse', this)">
                <i class="bi bi-box-seam-fill"></i> Warehouse Dispatches
            </a>
        </li>
    </ul>

    <!-- MONITORING -->
    <div class="sidebar-section">Warehouse Monitoring</div>
    <ul class="menu">
        <li <?= (strpos($page, 'transfer_monitoring.php') !== false) ? 'class="active"' : '' ?>>
            <a href="#" onclick="loadPage('warehouse/transfer_monitoring.php', this)">
                <i class="bi bi-activity"></i> Stock Transport Monitoring
            </a>
        </li>
        <li <?= (strpos($page, 'products.php') !== false) ? 'class="active"' : '' ?>>
            <a href="#" onclick="loadPage('products.php', this)">
                <i class="bi bi-tags-fill"></i> Product Catalog
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
        <div class="topbar-title" id="pageTitle">Warehouse Management</div>
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
                    <li><a class="dropdown-item text-danger logout-link" href="#">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a></li>
                </ul>
            </div>
        </div>
    </div>

    <div id="content">
        <?php
        $targetPage = basename($page);
        $whDir = __DIR__ . '/warehouse';
        if (file_exists($whDir . '/' . $targetPage)) {
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
function updateClock(){
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const dateStr = now.toLocaleDateString('en-US', options);
    let hours = now.getHours();
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    const timeStr = `${hours}:${minutes}:${seconds} ${ampm}`;
    document.getElementById('clock').innerHTML = `<div>${dateStr}</div><div style="font-size:11px;color:#0d6efd;text-align:right;">${timeStr}</div>`;
}
setInterval(updateClock, 1000);
updateClock();

/* PAGE TITLES */
const pageTitles = {
    'warehouse_dispatches.php': 'Warehouse Dispatches (Shipping)',
    'transfer_monitoring.php':  'Transfer Monitoring (Warehouse Side)',
    'products.php':             'Product Catalog'
};

const currentSubPage = '<?= basename($page); ?>';
if (pageTitles[currentSubPage]) {
    $("#pageTitle").text(pageTitles[currentSubPage]);
}

function activeMenu(element){
    $(".menu li").removeClass("active");
    if(element) $(element).parent().addClass("active");
}

function loadPage(page, element=null){
    if (window.event && window.event.preventDefault) {
        window.event.preventDefault();
    }
    if(element) activeMenu(element);
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
            reinitDataTables();
        },
        error: function(){
            $("#content").html('<div class="alert alert-danger m-3">Error loading page content.</div>');
        }
    });
}

$(document).on('click', '.sidebar a[href="#"], .menu a[href="#"]', function(e) {
    e.preventDefault();
});

function reinitDataTables() {
    $('.datatable').each(function() {
        if (!$.fn.DataTable.isDataTable(this)) {
            $(this).DataTable({
                responsive: true,
                language: { search: "_INPUT_", searchPlaceholder: "Search records..." }
            });
        }
    });
}

$(document).on('click', '.logout-link', function(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Sign Out?',
        text: 'Are you sure you want to log out of your session?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Logout'
    }).then((r) => {
        if(r.isConfirmed) window.location.href = 'logout.php';
    });
});
</script>

</body>
</html>
