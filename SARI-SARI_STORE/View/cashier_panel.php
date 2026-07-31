<?php
session_start();
require_once("../Model/database.php");

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

if($_SESSION['role'] != 'Cashier'){
    header("Location: login.php");
    exit();
}

$cashier_id   = $_SESSION['user_id'];
$cashier_name = $_SESSION['full_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Panel — Sari-Sari Store</title>

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
        background: #F4F6F9;
        font-family: 'Segoe UI', sans-serif;
        overflow: hidden;
    }

    /*==========================
            SIDEBAR
    ==========================*/

    .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        width: 250px;
        height: 100vh;
        background: #1E5631;
        color: white;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    .logo {
        padding: 25px;
        text-align: center;
        font-size: 20px;
        font-weight: bold;
        border-bottom: 1px solid rgba(255,255,255,.15);
    }

    .logo small {
        display: block;
        font-size: 11px;
        opacity: .7;
        font-weight: 400;
        margin-top: 4px;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    .cashier-info {
        padding: 16px 22px;
        background: rgba(0,0,0,.15);
        border-bottom: 1px solid rgba(255,255,255,.1);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .cashier-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #2E7D32;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }

    .cashier-name {
        font-size: 13px;
        font-weight: 600;
    }

    .cashier-role {
        font-size: 11px;
        opacity: .7;
    }

    .menu {
        list-style: none;
        padding: 10px 0;
        flex: 1;
    }

    .menu li { transition: .3s; }
    .menu li:hover { background: #2E7D32; }
    .menu li.active { background: #2E7D32; }

    .menu li a {
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
        text-decoration: none;
        padding: 14px 22px;
        font-size: 14px;
    }

    .menu li a i { font-size: 18px; }

    .notif-badge {
        margin-left: auto;
        background: #dc3545;
        color: white;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 10px;
        min-width: 18px;
        text-align: center;
    }

    .sidebar-footer {
        padding: 16px 22px;
        border-top: 1px solid rgba(255,255,255,.1);
    }

    .sidebar-footer a {
        display: flex;
        align-items: center;
        gap: 10px;
        color: rgba(255,255,255,.7);
        text-decoration: none;
        font-size: 13px;
        transition: .2s;
    }

    .sidebar-footer a:hover { color: white; }

    /*==========================
            MAIN
    ==========================*/

    .main {
        margin-left: 250px;
        width: calc(100% - 250px);
        height: 100vh;
        display: flex;
        flex-direction: column;
    }

    /*==========================
            NAVBAR
    ==========================*/

    .navbar-custom {
        height: 70px;
        background: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,.08);
        flex-shrink: 0;
    }

    .navbar-title {
        font-size: 22px;
        font-weight: 700;
        color: #1E5631;
    }

    #clock {
        font-weight: 600;
        color: #198754;
        text-align: right;
        font-size: 13px;
    }

    /*==========================
            CONTENT
    ==========================*/

    #content {
        flex: 1;
        overflow: auto;
        padding: 20px;
    }

    </style>

</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <div class="logo">
        🏪 Sari-Sari Store
        <small>Cashier Panel</small>
    </div>

    <div class="cashier-info">
        <div class="cashier-avatar">
            <i class="bi bi-person-fill"></i>
        </div>
        <div>
            <div class="cashier-name"><?= htmlspecialchars($cashier_name); ?></div>
            <div class="cashier-role">Cashier</div>
        </div>
    </div>

    <ul class="menu">

        <li class="active">
            <a href="#" onclick="loadPage('cashier.php', this)">
                <i class="bi bi-calculator-fill"></i>
                Cashier / POS
            </a>
        </li>

        <li>
            <a href="#" onclick="loadPage('pending_carts.php', this)">
                <i class="bi bi-hourglass-split"></i>
                Pending Carts
                <?php
                $pendingOrders = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT COUNT(*) AS total FROM orders WHERE status = 'Pending'"
                ));
                if($pendingOrders['total'] > 0){
                    echo '<span class="notif-badge" id="pendingBadge">'.$pendingOrders['total'].'</span>';
                }
                ?>
            </a>
        </li>

        <li>
            <a href="#" onclick="loadPage('approved_carts.php', this)">
                <i class="bi bi-check-circle-fill"></i>
                Approved Carts
            </a>
        </li>

        <li>
            <a href="#" onclick="loadPage('cashier_history.php', this)">
                <i class="bi bi-clock-history"></i>
                Transactions
            </a>
        </li>

        <li>
            <a href="#" onclick="loadPage('cashier_notifications.php', this)">
                <i class="bi bi-bell-fill"></i>
                Notifications
                <?php
                $unread = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT COUNT(*) AS total FROM notifications WHERE is_read = 0"
                ));
                if($unread['total'] > 0){
                    echo '<span class="notif-badge" id="notifBadge">'.$unread['total'].'</span>';
                }
                ?>
            </a>
        </li>

    </ul>

    <div class="sidebar-footer">
        <a href="#" class="logout-link">
            <i class="bi bi-box-arrow-right"></i>
            Logout
        </a>
    </div>

</div>

<!-- MAIN -->
<div class="main">

    <div class="navbar-custom">
        <div class="navbar-title" id="pageTitle">Cashier / POS</div>
        <div id="clock"></div>
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
    PAGE TITLE
====================================================*/
function changeTitle(page){
    switch(page){
        case 'cashier.php':              $("#pageTitle").text("Cashier / POS"); break;
        case 'cashier_pos.php':          $("#pageTitle").text("Cashier / POS"); break;
        case 'pending_carts.php':        $("#pageTitle").text("Pending Carts"); break;
        case 'approved_carts.php':       $("#pageTitle").text("Approved Carts"); break;
        case 'cashier_history.php':      $("#pageTitle").text("My Transactions"); break;
        case 'cashier_notifications.php':$("#pageTitle").text("Notifications"); break;
        default: $("#pageTitle").text("Cashier Panel");
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
    LOAD PAGE VIA AJAX
====================================================*/
function loadPage(page, element = null){
    $("#content").fadeOut(120, function(){
        $("#content").load(page, function(response, status, xhr){
            if(status == "error"){
                $("#content").html(
                    "<div class='alert alert-danger mt-3'>" +
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
    if(element != null) activeMenu(element);
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
    REFRESH NOTIFICATION BADGE
====================================================*/
function refreshNotifBadge(){
    $.get('cashier_notifications.php', { action: 'get_unread_count' }, function(count){
        count = parseInt(count);
        if(count > 0){
            if($("#notifBadge").length){
                $("#notifBadge").text(count);
            } else {
                $(".menu li a[onclick*='cashier_notifications']")
                    .append('<span class="notif-badge" id="notifBadge">'+count+'</span>');
            }
        } else {
            $("#notifBadge").remove();
        }
    });
}

// Refresh badge every 30 seconds
setInterval(refreshNotifBadge, 30000);

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
    loadPage('cashier.php');
});

</script>

</body>
</html>