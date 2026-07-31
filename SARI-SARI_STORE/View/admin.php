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

$current_user    = $_SESSION['user_id'];
$current_name    = $_SESSION['full_name'];
$current_role    = $_SESSION['role'];
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Sari-Sari Store Management System</title>

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
    <style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{

    background:#F4F6F9;

    font-family:Segoe UI;

    overflow:hidden;

}

/*==========================
        SIDEBAR
==========================*/

.sidebar{

    position:fixed;

    left:0;

    top:0;

    width:250px;

    height:100vh;

    background:#1E5631;

    color:white;

    overflow-y:auto;

}

.sidebar-section {
    padding: 14px 20px 6px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: rgba(255,255,255,.6);
}

.logo{

    padding:25px;

    text-align:center;

    font-size:24px;

    font-weight:bold;

    border-bottom:1px solid rgba(255,255,255,.15);

}

.menu{

    list-style:none;

    padding:0;

}

.menu li{

    transition:.3s;

}

.menu li:hover{

    background:#2E7D32;

}

.menu li.active{

    background:#2E7D32;

}

.menu li a{

    display:block;

    color:white;

    text-decoration:none;

    padding:16px 22px;

}

.menu li a i{

    margin-right:10px;

}

#notifBadge{
    display:none;
    margin-left:8px;
    background:#dc3545;
    color:white;
    font-size:11px;
    font-weight:700;
    padding:2px 7px;
    border-radius:10px;
}

/*==========================
        MAIN
==========================*/

.main{

    margin-left:250px;

    width:calc(100% - 250px);

    height:100vh;

    display:flex;

    flex-direction:column;

}

/*==========================
        NAVBAR
==========================*/

.navbar-custom{

    height:70px;

    background:white;

    display:flex;

    justify-content:space-between;

    align-items:center;

    padding:0 25px;

    box-shadow:0 2px 8px rgba(0,0,0,.08);

}

.navbar-title{

    font-size:25px;

    font-weight:600;

}

#clock{

    font-weight:600;

    color:#198754;

}

/*==========================
        CONTENT
==========================*/

#content{

    flex:1;

    overflow:auto;

    padding:20px;

}

</style>

</head>

<body>

<!-- =========================
        SIDEBAR
========================== -->

<div class="sidebar">

    <div class="logo">

        🏪

        <br>

        O-Cart!

    </div>

    <!-- MAIN -->
    <div class="sidebar-section">Main</div>
    <ul class="menu">
        <li class="active">
            <a href="#" onclick="loadPage('dashboard.php',this)">
                <i class="bi bi-speedometer2"></i>
                Dashboard
            </a>
        </li>
    </ul>

    <!-- INVENTORY & STORE -->
    <div class="sidebar-section">Inventory &amp; Store</div>
    <ul class="menu">
        <li>
            <a href="#" onclick="loadPage('products.php',this)">
                <i class="bi bi-box-seam"></i>
                Products
            </a>
        </li>
        <li>
            <a href="#" onclick="loadPage('inventory.php',this)">
                <i class="bi bi-boxes"></i>
                Inventory
            </a>
        </li>
    </ul>

    <!-- POS & TRANSACTIONS -->
    <div class="sidebar-section">POS &amp; Transactions</div>
    <ul class="menu">
        <li>
            <a href="#" onclick="loadPage('cashier.php',this)">
                <i class="bi bi-calculator-fill"></i>
                Cashier
            </a>
        </li>
        <li>
            <a href="#" onclick="loadPage('sales.php',this)">
                <i class="bi bi-graph-up-arrow"></i>
                Sales
            </a>
        </li>
        <li>
            <a href="#" onclick="loadPage('pending_carts.php',this)">
                <i class="bi bi-cart-fill"></i>
                Pending Carts
            </a>
        </li>
        <li>
            <a href="#" onclick="loadPage('approved_carts.php',this)">
                <i class="bi bi-check-circle-fill"></i>
                Approved Carts
            </a>
        </li>
    </ul>

    <!-- SYSTEM & ACTIVITY -->
    <div class="sidebar-section">System &amp; Activity</div>
    <ul class="menu">
        <li>
            <a href="#" onclick="loadPage('notification.php',this)">
                <i class="bi bi-bell-fill"></i>
                Notifications
                <span id="notifBadge"></span>
            </a>
        </li>
        <li>
            <a href="#" onclick="loadPage('audit_logs.php',this)">
                <i class="bi bi-clock-history"></i>
                Activity Logs
            </a>
        </li>
        <li>
            <a href="hrms.php">
                <i class="bi bi-people-fill"></i>
                HRMS
            </a>
        </li>
    </ul>

    <!-- ACCOUNT -->
    <div class="sidebar-section">Account</div>
    <ul class="menu">
        <li>
            <a href="#" class="logout-link">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </li>
    </ul>

</div>

<!-- =========================
        MAIN
========================== -->

<div class="main">

    <div class="navbar-custom">

        <div class="navbar-title"
             id="pageTitle">

            Dashboard

        </div>

        <div class="d-flex align-items-center gap-3">
    <div id="clock" class="text-end"></div>
        <div class="dropdown">
            <button class="btn btn-outline-success btn-sm dropdown-toggle" 
                    data-bs-toggle="dropdown">
                <i class="bi bi-person-circle me-1"></i>
                <?= $_SESSION['full_name']; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><span class="dropdown-item-text text-muted" style="font-size:12px;">
                    Role: <?= $_SESSION['role']; ?>
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

    </div>

</div>

<!--=====================================
            JAVASCRIPT
======================================-->

<script>

/*====================================================
                LIVE CLOCK
====================================================*/

function updateClock(){

    const now = new Date();

    const options = {

        weekday: 'long',

        year: 'numeric',

        month: 'long',

        day: 'numeric'

    };

    const date = now.toLocaleDateString('en-US', options);

    const time = now.toLocaleTimeString();

    $("#clock").html(

        date +

        "<br><small>" +

        time +

        "</small>"

    );

}

setInterval(updateClock,1000);

updateClock();


/*====================================================
            CHANGE PAGE TITLE
====================================================*/

function changeTitle(page){

    switch(page){

        case "dashboard.php":

            $("#pageTitle").text("Dashboard");

        break;

        case "products.php":

            $("#pageTitle").text("Products");

        break;

        case "inventory.php":

            $("#pageTitle").text("Inventory");

        break;

        case "sales.php":

            $("#pageTitle").text("Sales");

        break;

        case "pending_carts.php":

            $("#pageTitle").text("Pending Carts");

        break;

        case "cashier.php":

            $("#pageTitle").text("Cashier");

        break;

        case "approved_carts.php":

            $("#pageTitle").text("Approved Carts");

        break;

        case "notification.php":

            $("#pageTitle").text("Notifications");

        break;

        case "register.php":
            
            $("#pageTitle").text("User Management");
        break;

        default:

            $("#pageTitle").text("Sari-Sari Store");

    }

}


/*====================================================
            SIDEBAR ACTIVE
====================================================*/

function activeMenu(element){

    $(".menu li").removeClass("active");

    $(element).parent().addClass("active");

}


/*====================================================
            LOAD PAGE USING AJAX
====================================================*/

function loadPage(page,element=null){

    $("#content").fadeOut(120,function(){

        $("#content").load(page,function(response,status,xhr){

            if(status=="error"){

                $("#content").html(

                    "<div class='alert alert-danger mt-3'>" +

                    "<h5>Unable to load page.</h5>" +

                    "<p>"+xhr.status+" "+xhr.statusText+"</p>" +

                    "</div>"

                );

            }

            else{

                initializePlugins();

                changeTitle(page);

            }

            $("#content").fadeIn(150);

        });

    });

    if(element!=null){

        activeMenu(element);

    }

}


/*====================================================
            DATATABLE INITIALIZER
====================================================*/

function initializePlugins(){

    if($.fn.DataTable){

        $("table.datatable").each(function(){

            const hasEmptyState = $(this).find('tbody tr td[colspan]').length > 0;

            if(!$.fn.DataTable.isDataTable(this) && !hasEmptyState){

                $(this).DataTable({

                    responsive:true,

                    pageLength:10,

                    lengthChange:false,

                    ordering:true,

                    searching:true

                });

            }

        });

    }

}


/*====================================================
            DASHBOARD AUTO REFRESH
====================================================*/

function refreshDashboard(){

    if($("#salesChart").length){

        loadPage("dashboard.php");

    }

}


/*====================================================
            NOTIFICATION BADGE + TOAST
====================================================*/

let lastNotifCount = 0;

function refreshNotifBadge(){
    $.get('notification.php', { action: 'get_unread_count' }, function(count){
        count = parseInt(count) || 0;

        if(count > 0){
            $("#notifBadge").text(count).show();
        } else {
            $("#notifBadge").hide();
        }

        if(count > lastNotifCount && lastNotifCount !== 0){
            if(window.Swal){
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'info',
                    title: 'You have a new notification',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            }
        }
        lastNotifCount = count;
    });
}

setInterval(refreshNotifBadge, 10000);
refreshNotifBadge();

/*====================================================
            LOGOUT CONFIRMATION
====================================================*/

$(document).on('click', '.logout-link', function(e){
    e.preventDefault();
    Swal.fire({
        title: 'Log out?',
        text: 'You will be signed out of your account.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, log out',
        cancelButtonText: 'Cancel'
    }).then(result => {
        if(result.isConfirmed){
            window.location.href = 'logout.php';
        }
    });
});

/*====================================================
            LOAD DEFAULT PAGE
====================================================*/

$(document).ready(function(){

    loadPage("dashboard.php");

});


</script>

</body>

</html>