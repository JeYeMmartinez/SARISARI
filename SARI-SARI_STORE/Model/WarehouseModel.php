<?php
// Model/WarehouseModel.php

class WarehouseModel {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Ensure the warehouse_storage table exists and is seeded with products.
     */
    public function initializeStorageTable() {
        // Auto-create warehouse_storage table
        mysqli_query($this->conn, "
            CREATE TABLE IF NOT EXISTS warehouse_storage (
                storage_id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL UNIQUE,
                quantity INT DEFAULT 100,
                min_reorder_level INT DEFAULT 20,
                last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Seed products into warehouse_storage if missing
        mysqli_query($this->conn, "
            INSERT IGNORE INTO warehouse_storage (product_id, quantity, min_reorder_level)
            SELECT product_id, 150, 30 FROM products
        ");
    }

    /**
     * Fetch products joined with warehouse storage
     */
    public function getWarehouseStorage() {
        $query = "
            SELECT p.product_id, p.product_name, p.image,
                   COALESCE(p.barcode, CONCAT('PRD-', p.product_id)) AS product_code,
                   COALESCE(c.category_name, 'General') AS category,
                   COALESCE(p.selling_price, 0) AS price,
                   COALESCE(ws.quantity, 0) AS storage_qty,
                   COALESCE(ws.min_reorder_level, 20) AS min_reorder,
                   ws.last_updated
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN warehouse_storage ws ON p.product_id = ws.product_id
            WHERE p.deleted_at IS NULL
            ORDER BY p.product_name ASC
        ";
        $result = mysqli_query($this->conn, $query);
        $items = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $items[] = $row;
            }
        }
        
        return $items;
    }

    /**
     * Adjust stock quantity and minimum reorder level
     */
    public function adjustStorageStock($pid, $new_qty, $new_min) {
        $stmt = $this->conn->prepare("INSERT INTO warehouse_storage (product_id, quantity, min_reorder_level) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), min_reorder_level = VALUES(min_reorder_level)");
        if ($stmt) {
            $stmt->bind_param("iii", $pid, $new_qty, $new_min);
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Warehouse storage stock updated successfully.'];
            } else {
                return ['success' => false, 'message' => 'Failed to update storage stock: ' . $this->conn->error];
            }
        }
        return ['success' => false, 'message' => 'Database prepare statement failed.'];
    }
}
