<?php
require_once 'Model/database.php';

// Check some records and constraints as proof
 = $conn->query("SHOW TABLES LIKE 'budgets'");
if (->num_rows > 0) echo "Budgets table created\n";

 = $conn->query("DESCRIBE finance_approvals");
while ($row = $fa->fetch_assoc()) {
    if ($row['Field'] == 'document_type') {
        echo "document_type: " . $row['Type'] . "\n";
    }
}
