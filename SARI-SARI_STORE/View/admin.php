<?php
session_start();

require_once("../Model/database.php");

// Uncomment after login system is finished
/*
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}
*/
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

        Sari-Sari Store

    </div>

    <ul class="menu">

        <li class="active">

            <a href="#"
               onclick="loadPage('dashboard.php',this)">

                <i class="bi bi-speedometer2"></i>

                Dashboard

            </a>

        </li>

        <li>

            <a href="#"
               onclick="loadPage('products.php',this)">

                <i class="bi bi-box-seam"></i>

                Products

            </a>

        </li>

        <li>

            <a href="#"
               onclick="loadPage('inventory.php',this)">

                <i class="bi bi-boxes"></i>

                Inventory

            </a>

        </li>

        <li>

            <a href="#"
               onclick="loadPage('sales.php',this)">

                <i class="bi bi-graph-up-arrow"></i>

                Sales

            </a>

        </li>

        <li>

            <a href="#"
               onclick="loadPage('cart.php',this)">

                <i class="bi bi-cart-fill"></i>

                Cart

            </a>

        </li>

        <li>

            <a href="#"
               onclick="loadPage('cashier.php',this)">

                <i class="bi bi-calculator-fill"></i>

                Cashier

            </a>

        </li>

        <li>

            <a href="#"
               onclick="loadPage('approval.php',this)">

                <i class="bi bi-check-circle-fill"></i>

                Approval

            </a>

        </li>

        <li>

            <a href="#"
               onclick="loadPage('notification.php',this)">

                <i class="bi bi-bell-fill"></i>

                Notifications

            </a>

        </li>

        <li>

            <a href="logout.php">

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

        <div id="clock"></div>

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

        case "cart.php":

            $("#pageTitle").text("Cart Records");

        break;

        case "cashier.php":

            $("#pageTitle").text("Cashier");

        break;

        case "approval.php":

            $("#pageTitle").text("Approval");

        break;

        case "notification.php":

            $("#pageTitle").text("Notifications");

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

            if(!$.fn.DataTable.isDataTable(this)){

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
            LOAD DEFAULT PAGE
====================================================*/

$(document).ready(function(){

    loadPage("dashboard.php");

});


</script>

</body>

</html>