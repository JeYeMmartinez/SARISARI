<?php
session_start();
require_once("../../Model/database.php");

if(!isset($_SESSION['emp_id'])){
    header("Location: emp_login.php");
    exit();
}

$current_emp_id = $_SESSION['emp_id'];
$current_name   = $_SESSION['emp_name'];
$current_no     = $_SESSION['emp_no'];
$current_email  = $_SESSION['emp_email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Portal — O-CART!</title>

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
    SIDEBAR (Green Theme)
==========================*/
.sidebar {
    position: fixed;
    left: 0; top: 0;
    width: 260px;
    height: 100vh;
    background: #0f5132;
    color: white;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
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
    background: #198754;
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
.menu li.active { background: #198754; }

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
    background: #198754;
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
    color: #0f5132;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 14px;
}

#clock {
    font-size: 13px;
    font-weight: 600;
    color: #198754;
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

/*==========================
    GLOBAL PAGE CARDS
==========================*/
.page-card {
    background: white;
    border-radius: 14px;
    padding: 22px 24px;
    box-shadow: 0 2px 10px rgba(0,0,0,.04);
    margin-bottom: 22px;
}

.stat-card {
    background: white;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.04);
    height: 100%;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.stat-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; color: white; flex-shrink: 0;
}

.stat-label { font-size: 12px; color: #6c757d; margin-bottom: 4px; }
.stat-value { font-size: 26px; font-weight: 800; line-height: 1; }
</style>

</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <div class="logo">
        <div class="logo-icon">🌿</div>
        <div class="logo-text">
            <strong>O-CART!</strong>
            <small>Employee Portal</small>
        </div>
    </div>

    <!-- MAIN MODULES -->
    <div class="sidebar-section">Main</div>
    <ul class="menu">
        <li class="active">
            <a href="#" onclick="loadPage('emp_home.php', this)">
                <i class="bi bi-house-door-fill"></i> Home
            </a>
        </li>
        <li>
            <a href="#" onclick="loadPage('emp_profile.php', this)">
                <i class="bi bi-person-badge-fill"></i> My Profile
            </a>
        </li>
    </ul>

    <!-- WORKLOGS -->
    <div class="sidebar-section">Records</div>
    <ul class="menu">
        <li>
            <a href="#" onclick="loadPage('emp_attendance.php', this)">
                <i class="bi bi-calendar2-check-fill"></i> My Attendance
            </a>
        </li>
        <li>
            <a href="#" onclick="loadPage('emp_payslips.php', this)">
                <i class="bi bi-file-earmark-text-fill"></i> My Payslips
            </a>
        </li>
    </ul>

    <!-- SELF SERVICE -->
    <div class="sidebar-section">Requests</div>
    <ul class="menu">
        <li>
            <a href="#" onclick="loadPage('emp_leaves.php', this)">
                <i class="bi bi-calendar-x-fill"></i> File Leave
            </a>
        </li>
        <li>
            <a href="#" onclick="loadPage('emp_resignation.php', this)">
                <i class="bi bi-door-open-fill"></i> Resignation
            </a>
        </li>
    </ul>

    <!-- SECURITY -->
    <div class="sidebar-section">Security</div>
    <ul class="menu">
        <li>
            <a href="#" onclick="loadPage('emp_change_password.php', this)">
                <i class="bi bi-shield-lock-fill"></i> Change Password
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr($current_name, 0, 1)); ?></div>
            <div>
                <div class="user-name"><?= htmlspecialchars($current_name); ?></div>
                <div class="user-role"><?= htmlspecialchars($current_no); ?></div>
            </div>
        </div>
        <a href="#" class="btn btn-sm btn-outline-light w-100 logout-link">
            <i class="bi bi-box-arrow-right me-1"></i> Logout
        </a>
    </div>

</div>

<!-- MAIN -->
<div class="main">

    <div class="topbar">
        <div class="topbar-title" id="pageTitle">Home</div>
        <div class="topbar-right">
            <div id="clock"></div>
            <div class="dropdown">
                <button class="btn btn-outline-success btn-sm dropdown-toggle"
                        data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= htmlspecialchars($current_name); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text text-muted" style="font-size:12px;">
                        Role: Associate
                    </span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger logout-link" href="#">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a></li>
                </ul>
            </div>
        </div>
    </div>

    <div id="content"></div>

</div>

<script>

/*====================================================
    LIVE CLOCK
====================================================*/
function updateClock(){
    const now = new Date();
    const options = { weekday:'long', year:'numeric', month:'long', day:'numeric' };
    const date = now.toLocaleDateString('en-US', options);
    const time = now.toLocaleTimeString();
    $("#clock").html(date + "<br><small>" + time + "</small>");
}
setInterval(updateClock, 1000);
updateClock();

/*====================================================
    PAGE TITLES
====================================================*/
const pageTitles = {
    'emp_home.php':            'Dashboard Home',
    'emp_profile.php':         'My Profile',
    'emp_attendance.php':      'My Attendance History',
    'emp_payslips.php':        'My Payslips',
    'emp_leaves.php':          'My Leave Requests',
    'emp_resignation.php':     'Resignation Filing',
    'emp_change_password.php': 'Change Password',
};

function changeTitle(page){
    $("#pageTitle").text(pageTitles[page] || 'Employee Portal');
}

/*====================================================
    SIDEBAR ACTIVE
====================================================*/
function activeMenu(element){
    $(".menu li").removeClass("active");
    $(element).parent().addClass("active");
}

/*====================================================
    LOAD PAGE VIA AJAX
====================================================*/
function loadPage(page, element = null){
    $("#content").fadeOut(100, function(){
        $("#content").load(page, function(response, status, xhr){
            if(status == "error"){
                $("#content").html(
                    "<div class='alert alert-danger m-3'>" +
                    "<h5>Unable to load page.</h5>" +
                    "<p>" + xhr.status + " " + xhr.statusText + "</p>" +
                    "</div>"
                );
            } else {
                initializePlugins();
                changeTitle(page);
            }
            $("#content").fadeIn(150);
        });
    });
    if(element) activeMenu(element);
}

/*====================================================
    DATATABLE INITIALIZER
====================================================*/
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

/*====================================================
    LOGOUT
====================================================*/
$(document).on('click', '.logout-link', function(e){
    e.preventDefault();
    Swal.fire({
        title: 'Log out?',
        text: 'You will be signed out of the Employee Portal.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, log out'
    }).then(result => {
        if(result.isConfirmed) window.location.href = 'emp_logout.php';
    });
});

/*====================================================
    LOAD DEFAULT PAGE
====================================================*/
$(document).ready(function(){
    loadPage('emp_home.php');
});

</script>
</body>
</html>
