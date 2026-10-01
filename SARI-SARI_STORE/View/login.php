<?php
// Since this is the Customer side deployment, the admin login is disabled here.
// Redirect any attempts to access this page back to the customer store (index.php).
header("Location: index");
exit();
?>
