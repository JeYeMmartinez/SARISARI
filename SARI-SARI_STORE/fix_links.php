<?php
$files = [
    'View/admin.php',
    'View/admin_warehouse.php',
    'View/admin_inventory.php',
    'View/admin_procurement.php',
    'View/admin_pos.php',
    'View/admin_finance.php',
    'View/hrms.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $content = str_replace('href="admin_warehouse.php"', 'href="admin_warehouse"', $content);
        $content = str_replace('href="admin_inventory.php"', 'href="admin_inventory"', $content);
        $content = str_replace('href="admin_procurement.php"', 'href="admin_procurement"', $content);
        $content = str_replace('href="admin_pos.php"', 'href="admin_pos"', $content);
        $content = str_replace('href="admin_finance.php"', 'href="admin_finance"', $content);
        $content = str_replace('href="hrms.php"', 'href="hrms"', $content);
        $content = str_replace('href="admin.php"', 'href="admin"', $content);
        
        file_put_contents($file, $content);
        echo "Updated $file\n";
    } else {
        echo "File not found: $file\n";
    }
}
?>
