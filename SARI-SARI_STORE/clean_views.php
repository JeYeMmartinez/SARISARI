<?php
$files = glob(__DIR__ . "/View/Finance_employee/*.php");
foreach($files as $f) {
    if (strpos($f, 'finance_sales.php') !== false) {
        // finance_sales already done manually
        continue;
    }
    $c = file_get_contents($f);
    
    // Remove the first <?php block completely
    $c = preg_replace("/^<\?php.*?\?>\s*/s", "", $c);
    
    // Replace AJAX URLs
    $c = str_replace("url: 'Finance_employee/finance_sales.php'", "url: '../router.php?route=finance_action'", $c);
    $c = str_replace("url: 'Finance_employee/finance_stock_requests.php'", "url: '../router.php?route=finance_action'", $c);
    $c = str_replace("url: 'Finance_employee/finance_restock.php'", "url: '../router.php?route=finance_action'", $c);
    $c = str_replace("url: 'Finance_employee/finance_payroll.php'", "url: '../router.php?route=finance_action'", $c);
    $c = str_replace("url: 'Finance_employee/finance_signature_profile.php'", "url: '../router.php?route=finance_action'", $c);
    
    // Replace form actions
    $c = str_replace("action=\"Finance_employee/finance_stock_requests.php\"", "action=\"../router.php?route=finance_action\"", $c);
    $c = str_replace("action=\"Finance_employee/finance_payroll.php\"", "action=\"../router.php?route=finance_action\"", $c);
    $c = str_replace("action=\"Finance_employee/finance_signature_profile.php\"", "action=\"../router.php?route=finance_action\"", $c);
    
    file_put_contents($f, $c);
}
echo "Done";
