<?php
error_reporting(E_ALL & ~E_NOTICE);
require_once("../Model/database.php");

require_once __DIR__ . "/../Model/admin_auth_guard.php";

$current_name = $_SESSION['full_name'] ?? 'Admin';
$current_role = 'Admin';

$page = $_GET['page'] ?? 'procurement/procurement_requests.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procurement Portal — O-CART!</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/animate.min.css">
    <link rel="stylesheet" href="../assets/datatables.min.css">
    <link rel="stylesheet" href="../assets/sweetalert2.min.css">

    <script src="../assets/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/datatables.min.js"></script>
    <script src="../assets/sweetalert2.all.min.js"></script>
    <script src="../assets/chart.js"></script>

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
        <div class="logo-icon bg-warning text-dark"><i class="bi bi-cart4"></i></div>
        <div class="logo-text">
            <strong>O-CART!</strong>
            <small>Procurement</small>
        </div>
    </div>

    <!-- OVERVIEW -->
    <div class="sidebar-section">Overview</div>
    <ul class="menu">
        <li <?= (strpos($page, 'procurement_dashboard.php') !== false) ? 'class="active"' : '' ?>>
            <a href="javascript:void(0)" onclick="loadPage('procurement/procurement_dashboard.php', this)">
                <i class="bi bi-speedometer2"></i> Procurement Dashboard
            </a>
        </li>
    </ul>

    <!-- SUPPLIER MANAGEMENT -->
    <div class="sidebar-section">Supplier Management</div>
    <ul class="menu">
        <li <?= (strpos($page, 'supplier_management.php') !== false) ? 'class="active"' : '' ?>>
            <a href="javascript:void(0)" onclick="loadPage('procurement/supplier_management.php', this)">
                <i class="bi bi-person-lines-fill"></i> Supplier Directory
            </a>
        </li>
    </ul>

    <!-- PURCHASING -->
    <div class="sidebar-section">Purchasing</div>
    <ul class="menu">
        <li <?= (strpos($page, 'procurement_requests.php') !== false) ? 'class="active"' : '' ?>>
            <a href="javascript:void(0)" onclick="loadPage('procurement/procurement_requests.php', this)">
                <i class="bi bi-file-earmark-text"></i> Purchase Requests
            </a>
        </li>
        <li <?= (strpos($page, 'procurement_orders.php') !== false) ? 'class="active"' : '' ?>>
            <a href="javascript:void(0)" onclick="loadPage('procurement/procurement_orders.php', this)">
                <i class="bi bi-receipt"></i> Purchase Orders
            </a>
        </li>
    </ul>

    <!-- REPORTS -->
    <div class="sidebar-section">Reports</div>
    <ul class="menu">
        <li <?= (strpos($page, 'procurement_history.php') !== false) ? 'class="active"' : '' ?>>
            <a href="javascript:void(0)" onclick="loadPage('procurement/procurement_history.php', this)">
                <i class="bi bi-clock-history"></i> Procurement History
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
        <a href="admin" class="btn btn-sm btn-outline-light w-100 mb-2">
            <i class="bi bi-arrow-left-circle me-1"></i> Back to Main Menu
        </a>
        <a href="javascript:void(0)" class="btn btn-sm btn-outline-danger w-100 logout-link">
            <i class="bi bi-box-arrow-right me-1"></i> Logout
        </a>
    </div>

</div>

<!-- MAIN -->
<div class="main">

    <div class="topbar">
        <div class="topbar-title" id="pageTitle">Procurement Management</div>
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
                    <li><a class="dropdown-item text-danger logout-link" href="javascript:void(0)">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a></li>
                </ul>
            </div>
        </div>
    </div>

    <div id="content">
        <?php
        $targetPage = basename($page);
        if ($page === 'procurement/procurement_dashboard.php') {
            $_GET['route'] = 'procurement_dashboard';
            chdir(__DIR__ . '/..');
            include 'router.php';
            chdir(__DIR__);
        } else if ($page === 'procurement/procurement_requests.php') {
            $_GET['route'] = 'procurement_requests';
            chdir(__DIR__ . '/..');
            include 'router.php';
            chdir(__DIR__);
        } else if ($page === 'procurement/procurement_orders.php') {
            $_GET['route'] = 'procurement_purchase_orders';
            chdir(__DIR__ . '/..');
            include 'router.php';
            chdir(__DIR__);
        } else if ($page === 'procurement/supplier_management.php') {
            $_GET['route'] = 'procurement_suppliers';
            chdir(__DIR__ . '/..');
            include 'router.php';
            chdir(__DIR__);
        } else if ($page === 'procurement/procurement_history.php') {
            $_GET['route'] = 'procurement_history';
            chdir(__DIR__ . '/..');
            include 'router.php';
            chdir(__DIR__);
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
    'procurement_dashboard.php': 'Procurement Dashboard',
    'supplier_management.php':  'Supplier Directory',
    'procurement_requests.php': 'Purchase Requests',
    'procurement_orders.php':   'Purchase Orders',
    'procurement_history.php':  'Procurement History'
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
    let loadUrl = page;
    if (page === 'procurement/procurement_requests.php') {
        loadUrl = '../router?route=procurement_requests';
    } else if (page === 'procurement/procurement_orders.php') {
        loadUrl = '../router?route=procurement_purchase_orders';
    } else if (page === 'procurement/supplier_management.php') {
        loadUrl = '../router?route=procurement_suppliers';
    } else if (page === 'procurement/procurement_dashboard.php') {
        loadUrl = '../router?route=procurement_dashboard';
    } else if (page === 'procurement/procurement_history.php') {
        loadUrl = '../router?route=procurement_history';
    }

    $.ajax({
        url: loadUrl,
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

$(document).on('click', '.sidebar a[href="javascript:void(0)"], .menu a[href="javascript:void(0)"]', function(e) {
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
        if(r.isConfirmed) window.location.href = 'logout';
    });
});
</script>

</body>
</html>

