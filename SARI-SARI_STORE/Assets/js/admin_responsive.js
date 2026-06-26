/* ==============================================
   UNIVERSAL RESPONSIVE ADMIN DASHBOARD SCRIPT
   Save as: ../Assets/js/admin_responsive.js
   ============================================== */

$(document).ready(function() {
    // Add hamburger button and overlay to page
    $('body').prepend('<button class="menu-toggle">☰</button>');
    $('body').prepend('<div class="sidebar-overlay"></div>');

    // Toggle sidebar when hamburger is clicked
    $('.menu-toggle').on('click', function() {
        $('.sidebar').toggleClass('active');
        $('.sidebar-overlay').toggleClass('active');
        $('body').toggleClass('menu-open');
    });

    // Close sidebar when overlay is clicked
    $('.sidebar-overlay').on('click', function() {
        $('.sidebar').removeClass('active');
        $('.sidebar-overlay').removeClass('active');
        $('body').removeClass('menu-open');
    });

    // Close sidebar when a nav link is clicked (mobile only)
    $('.sidebar .nav-link').on('click', function() {
        if ($(window).width() <= 768) {
            $('.sidebar').removeClass('active');
            $('.sidebar-overlay').removeClass('active');
            $('body').removeClass('menu-open');
            }
        });

    // Handle window resize
    $(window).on('resize', function() {
        if ($(window).width() > 768) {
            $('.sidebar').removeClass('active');
            $('.sidebar-overlay').removeClass('active');
            $('body').removeClass('menu-open');
        }
    });
});