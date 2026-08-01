<?php
error_reporting(E_ALL & ~E_NOTICE);
session_start();
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

$page = $_GET['page'] ?? 'Finance_employee/finance_sales.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Portal — O-CART!</title>

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
    SIDEBAR (Finance Purple Theme)
==========================*/
.sidebar {
    position: fixed;
    left: 0; top: 0;
    width: 260px;
    height: 100vh;
    background: #2b1055;
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
    background: #7b2cbf;
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

.menu li { margin-bottom: 2px; }

.menu li a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    color: rgba(255,255,255,.8);
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 500;
    border-radius: 8px;
    transition: .15s;
}

.menu li a:hover {
    background: rgba(255,255,255,.1);
    color: white;
}

.menu li.active a {
    background: #7b2cbf;
    color: white;
    font-weight: 600;
}

.sidebar-footer {
    margin-top: auto;
    padding: 16px;
    border-top: 1px solid rgba(255,255,255,.1);
    background: rgba(0,0,0,.15);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.user-avatar {
    width: 34px; height: 34px;
    border-radius: 50%;
    background: #7b2cbf;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 14px;
}

.user-name { font-size: 13px; font-weight: 600; }
.user-role { font-size: 11px; opacity: .6; }

/*==========================
    MAIN LAYOUT
==========================*/
.main {
    margin-left: 260px;
    width: calc(100% - 260px);
    height: 100vh;
    display: flex;
    flex-direction: column;
}

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
    color: #2b1055;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 14px;
}

#clock {
    font-size: 13px;
    font-weight: 600;
    color: #7b2cbf;
    text-align: right;
}

#content {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <div class="logo">
        <div class="logo-icon">💰</div>
        <div class="logo-text">
            <strong>O-CART!</strong>
            <small>Finance Portal</small>
        </div>
    </div>

    <!-- SALES & ANALYTICS -->
    <div class="sidebar-section">Sales &amp; Analytics</div>
    <ul class="menu">
        <li <?= (strpos($page, 'finance_sales.php') !== false || strpos($page, 'sales.php') !== false) ? 'class="active"' : '' ?>>
            <a href="#" onclick="loadPage('Finance_employee/finance_sales.php', this)">
                <i class="bi bi-graph-up-arrow"></i> Sales Reports
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
        <div class="topbar-title" id="pageTitle">Sales Reports</div>
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
                    <li><a class="dropdown-item text-primary" href="admin.php">
                        <i class="bi bi-speedometer2 me-2"></i>Admin Dashboard
                    </a></li>
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
        $finDir = __DIR__ . '/Finance_employee';
        if (file_exists($finDir . '/' . $targetPage)) {
            chdir($finDir);
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
    'finance_sales.php': 'Sales Reports',
    'sales.php':         'Sales Reports'
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

/* LOAD PAGE VIA FULL REDIRECT */
function loadPage(page, element = null){
    window.location.href = 'admin_finance.php?page=' + encodeURIComponent(page);
}

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

/* LOGOUT CONFIRMATION */
$(document).on('click', '.logout-link', function(e){
    e.preventDefault();
    Swal.fire({
        title: 'Log out?',
        text: 'You will be signed out.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, log out'
    }).then(result => {
        if(result.isConfirmed) window.location.href = 'logout.php';
    });
});

/* INITIAL LOAD */
$(document).ready(function(){
    initializePlugins();
});
</script>
</body>
</html>
