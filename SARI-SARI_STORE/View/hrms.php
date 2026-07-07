<?php
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

$current_user = $_SESSION['user_id'];
$current_name = $_SESSION['full_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS — Sari-Sari Store</title>

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
    background: #F0F4F8;
    font-family: 'Segoe UI', sans-serif;
    overflow: hidden;
}

/*==========================
    SIDEBAR
==========================*/
.sidebar {
    position: fixed;
    left: 0; top: 0;
    width: 260px;
    height: 100vh;
    background: #1a3c5e;
    color: white;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.sidebar::-webkit-scrollbar { width: 4px; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.2); border-radius: 4px; }

.logo {
    padding: 20px 24px;
    border-bottom: 1px solid rgba(255,255,255,.1);
    display: flex;
    align-items: center;
    gap: 12px;
}

.logo-icon {
    width: 42px; height: 42px;
    background: #2563eb;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
}

.logo-text { line-height: 1.2; }
.logo-text strong { font-size: 15px; display: block; }
.logo-text small { font-size: 11px; opacity: .6; letter-spacing: .5px; text-transform: uppercase; }

.sidebar-section {
    padding: 14px 20px 6px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    opacity: .45;
}

.menu {
    list-style: none;
    padding: 0 10px;
}

.menu li { border-radius: 8px; transition: .2s; margin-bottom: 2px; }
.menu li:hover { background: rgba(255,255,255,.08); }
.menu li.active { background: #2563eb; }

.menu li a {
    display: flex;
    align-items: center;
    gap: 12px;
    color: rgba(255,255,255,.85);
    text-decoration: none;
    padding: 11px 14px;
    font-size: 13.5px;
    border-radius: 8px;
}

.menu li.active a { color: white; font-weight: 600; }
.menu li a i { font-size: 17px; width: 20px; text-align: center; }

.menu-badge {
    margin-left: auto;
    background: #dc3545;
    color: white;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 7px;
    border-radius: 10px;
}

.sidebar-footer {
    margin-top: auto;
    padding: 16px;
    border-top: 1px solid rgba(255,255,255,.1);
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
    background: #2563eb;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; flex-shrink: 0;
}

.user-name { font-size: 13px; font-weight: 600; line-height: 1.2; }
.user-role { font-size: 11px; opacity: .6; }

.back-link {
    display: flex;
    align-items: center;
    gap: 8px;
    color: rgba(255,255,255,.6);
    text-decoration: none;
    font-size: 13px;
    padding: 8px 12px;
    border-radius: 8px;
    transition: .2s;
}
.back-link:hover { background: rgba(255,255,255,.08); color: white; }

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
    box-shadow: 0 1px 6px rgba(0,0,0,.07);
    flex-shrink: 0;
}

.topbar-title {
    font-size: 22px;
    font-weight: 700;
    color: #1a3c5e;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 14px;
}

#clock {
    font-size: 13px;
    font-weight: 600;
    color: #2563eb;
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
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
    margin-bottom: 22px;
}

.stat-card {
    background: white;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
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
        <div class="logo-icon">👥</div>
        <div class="logo-text">
            <strong>HRMS</strong>
            <small>Human Resource</small>
        </div>
    </div>

    <!-- MAIN MODULES -->
    <div class="sidebar-section">Main</div>
    <ul class="menu">
        <li class="active">
            <a href="#" onclick="loadPage('hrms_dashboard.php', this)">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
    </ul>

    <!-- RECRUITMENT -->
    <div class="sidebar-section">Recruitment</div>
    <ul class="menu">
        <li>
            <a href="#" onclick="loadPage('hrms_jobs.php', this)">
                <i class="bi bi-briefcase-fill"></i> Job Postings
            </a>
        </li>
        <li>
            <a href="#" onclick="loadPage('hrms_applicants.php', this)">
                <i class="bi bi-person-lines-fill"></i> Applicants
            </a>
        </li>
    </ul>

    <!-- EMPLOYEES -->
    <div class="sidebar-section">Workforce</div>
    <ul class="menu">
        <li>
            <a href="#" onclick="loadPage('hrms_employees.php', this)">
                <i class="bi bi-people-fill"></i> Employees
            </a>
        </li>
        <li>
            <a href="#" onclick="loadPage('hrms_attendance.php', this)">
                <i class="bi bi-calendar-check-fill"></i> Attendance
            </a>
        </li>
        <li>
            <a href="#" onclick="loadPage('hrms_leaves.php', this)">
                <i class="bi bi-calendar-x-fill"></i> Leave Requests
            </a>
        </li>
    </ul>

    <!-- PAYROLL -->
    <div class="sidebar-section">Payroll</div>
    <ul class="menu">
        <li>
            <a href="#" onclick="loadPage('hrms_payroll.php', this)">
                <i class="bi bi-cash-coin"></i> Payroll
            </a>
        </li>
        <li>
            <a href="#" onclick="loadPage('hrms_payslip.php', this)">
                <i class="bi bi-file-earmark-text-fill"></i> Payslips
            </a>
        </li>
    </ul>

    <!-- SETTINGS -->
    <div class="sidebar-section">Setup</div>
    <ul class="menu">
        <li>
            <a href="#" onclick="loadPage('hrms_departments.php', this)">
                <i class="bi bi-diagram-3-fill"></i> Departments
            </a>
        </li>
        <li>
            <a href="#" onclick="loadPage('hrms_positions.php', this)">
                <i class="bi bi-tag-fill"></i> Positions
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><i class="bi bi-person-fill"></i></div>
            <div>
                <div class="user-name"><?= htmlspecialchars($current_name); ?></div>
                <div class="user-role">Administrator</div>
            </div>
        </div>
        <a href="admin.php" class="back-link">
            <i class="bi bi-arrow-left-circle"></i>
            Back to POS System
        </a>
    </div>

</div>

<!-- MAIN -->
<div class="main">

    <div class="topbar">
        <div class="topbar-title" id="pageTitle">Dashboard</div>
        <div class="topbar-right">
            <div id="clock"></div>
            <div class="dropdown">
                <button class="btn btn-outline-primary btn-sm dropdown-toggle"
                        data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= htmlspecialchars($current_name); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text text-muted" style="font-size:12px;">
                        Role: Administrator
                    </span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="admin.php">
                        <i class="bi bi-shop me-2"></i>Go to POS System
                    </a></li>
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
    'hrms_dashboard.php':   'HRMS Dashboard',
    'hrms_jobs.php':        'Job Postings',
    'hrms_applicants.php':  'Applicants',
    'hrms_employees.php':   'Employees',
    'hrms_attendance.php':  'Attendance',
    'hrms_leaves.php':      'Leave Requests',
    'hrms_payroll.php':     'Payroll',
    'hrms_payslip.php':     'Payslips',
    'hrms_departments.php': 'Departments',
    'hrms_positions.php':   'Positions',
};

function changeTitle(page){
    $("#pageTitle").text(pageTitles[page] || 'HRMS');
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
    CLEAR BACKDROP HELPER
====================================================*/
function clearBackdrop(){
    $(".modal-backdrop").remove();
    $("body").removeClass("modal-open").css("padding-right","");
}

/*====================================================
    LOGOUT
====================================================*/
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

/*====================================================
    LOAD DEFAULT PAGE
====================================================*/
$(document).ready(function(){
    loadPage('hrms_dashboard.php');
});

</script>
</body>
</html>