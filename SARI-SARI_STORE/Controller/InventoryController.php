<?php
// Controller/InventoryController.php

class InventoryController {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Get quick stats for the inventory dashboard
     */
    public function getDashboardStats() {
        $total_skus = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT COUNT(*) AS total FROM products WHERE status != 'Unavailable'"))['total'] ?? 0;

        $low_stock_count = mysqli_fetch_assoc(mysqli_query($this->conn, "
            SELECT COUNT(*) AS total 
            FROM inventory i
            JOIN products p ON i.product_id = p.product_id
            WHERE i.quantity <= i.minimum_stock AND i.quantity > 0 AND p.status != 'Unavailable'
        "))['total'] ?? 0;

        $out_of_stock_count = mysqli_fetch_assoc(mysqli_query($this->conn, "
            SELECT COUNT(*) AS total 
            FROM inventory i
            JOIN products p ON i.product_id = p.product_id
            WHERE i.quantity = 0 OR p.status = 'Unavailable'
        "))['total'] ?? 0;

        $total_inventory_val = mysqli_fetch_assoc(mysqli_query($this->conn, "
            SELECT SUM(i.quantity * p.cost_price) AS total_val 
            FROM inventory i
            JOIN products p ON i.product_id = p.product_id
            WHERE p.status != 'Unavailable'
        "))['total_val'] ?? 0;

        return [
            'total_skus' => $total_skus,
            'low_stock_count' => $low_stock_count,
            'out_of_stock_count' => $out_of_stock_count,
            'total_inventory_val' => $total_inventory_val
        ];
    }

    /**
     * Get recent audit logs related to inventory operations
     */
    public function getRecentLogs($limit = 6) {
        $limit = (int)$limit;
        return mysqli_query($this->conn, "
            SELECT log_id, action, description, created_at 
            FROM audit_logs 
            WHERE table_name IN ('inventory', 'products') 
            ORDER BY created_at DESC 
            LIMIT $limit
        ");
    }

    /**
     * Get preview list of low stock items
     */
    public function getLowStockItems($limit = 5) {
        $limit = (int)$limit;
        return mysqli_query($this->conn, "
            SELECT p.product_name, p.barcode, i.quantity, i.minimum_stock, i.aisle
            FROM inventory i
            JOIN products p ON i.product_id = p.product_id
            WHERE i.quantity <= i.minimum_stock
            ORDER BY i.quantity ASC
            LIMIT $limit
        ");
    }

    /**
     * Get full inventory list joined with product and category info
     */
    public function getInventoryList() {
        return mysqli_query($this->conn, "
            SELECT
                i.*,
                p.product_id,
                p.product_name,
                p.barcode,
                p.selling_price,
                p.cost_price,
                p.cost_per_box,
                p.units_per_box,
                p.status AS product_status,
                c.category_name
            FROM inventory i
            INNER JOIN products p ON i.product_id = p.product_id
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE p.deleted_at IS NULL
            ORDER BY p.product_name ASC
        ");
    }

    /**
     * Fetch products that do not have inventory records registered yet
     */
    public function getUnstockedProducts() {
        return mysqli_query($this->conn, "
            SELECT p.product_id, p.product_name, p.units_per_box, p.cost_per_box, p.cost_price, p.selling_price, c.category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN inventory i ON p.product_id = i.product_id
            WHERE i.inventory_id IS NULL
            AND p.deleted_at IS NULL
            ORDER BY p.product_name ASC
        ");
    }

    /**
     * Calculate counts of total, low, out, and healthy stock items
     */
    public function getInventorySummaryCounts() {
        $total = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT COUNT(*) AS total FROM inventory i INNER JOIN products p ON i.product_id = p.product_id WHERE p.deleted_at IS NULL"))['total'] ?? 0;
        $low   = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT COUNT(*) AS total FROM inventory i INNER JOIN products p ON i.product_id = p.product_id WHERE p.deleted_at IS NULL AND i.quantity <= i.minimum_stock AND i.quantity > 0"))['total'] ?? 0;
        $out   = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT COUNT(*) AS total FROM inventory i INNER JOIN products p ON i.product_id = p.product_id WHERE p.deleted_at IS NULL AND i.quantity = 0"))['total'] ?? 0;
        $healthy = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT COUNT(*) AS total FROM inventory i INNER JOIN products p ON i.product_id = p.product_id WHERE p.deleted_at IS NULL AND i.quantity > i.minimum_stock"))['total'] ?? 0;

        return [
            'total' => $total,
            'low' => $low,
            'out' => $out,
            'healthy' => $healthy
        ];
    }

    /**
     * Initial stocking for products that don't have active inventory records
     */
    public function addStock($postData, $current_user) {
        $product_id    = (int)($postData['product_id'] ?? 0);
        $boxes         = max(0, (int)($postData['boxes_received'] ?? 0));
        $units_per_box = max(1, (int)($postData['units_per_box'] ?? 1));
        $cost_per_box  = (float)($postData['cost_per_box'] ?? 0);
        $sell_price    = (float)($postData['selling_price'] ?? 0);
        $quantity      = $boxes > 0 ? ($boxes * $units_per_box) : max(0, (int)($postData['quantity'] ?? 0));
        $minimum_stock = max(0, (int)($postData['minimum_stock'] ?? 5));
        $maximum_stock = max(0, (int)($postData['maximum_stock'] ?? 100));
        $aisle         = mysqli_real_escape_string($this->conn, trim($postData['aisle'] ?? ''));

        if (!$product_id) {
            return 'error: Product ID is missing.';
        }

        // Check if product already has inventory record
        $check = mysqli_query($this->conn, "SELECT inventory_id FROM inventory WHERE product_id = $product_id");
        if (mysqli_num_rows($check) > 0) {
            return 'exists';
        }

        mysqli_begin_transaction($this->conn);
        try {
            $lastRestock = $quantity > 0 ? "NOW()" : "NULL";
            $query = mysqli_query($this->conn, "
                INSERT INTO inventory (product_id, quantity, minimum_stock, maximum_Stock, aisle, last_restock)
                VALUES ($product_id, $quantity, $minimum_stock, $maximum_stock, '$aisle', $lastRestock)
            ");

            if (!$query) {
                throw new Exception("Inventory insert failed: " . mysqli_error($this->conn));
            }

            $inventory_id = mysqli_insert_id($this->conn);

            // If boxes were entered, update product pricing & log restock
            if ($boxes > 0 && $cost_per_box > 0) {
                $cost_per_piece = round($cost_per_box / $units_per_box, 4);
                $total_cost     = round($boxes * $cost_per_box, 2);

                mysqli_query($this->conn, "
                    UPDATE products SET
                        units_per_box = $units_per_box,
                        cost_per_box  = $cost_per_box,
                        cost_price    = $cost_per_piece,
                        selling_price = IF($sell_price > 0, $sell_price, selling_price),
                        status        = 'Available'
                    WHERE product_id = $product_id
                ");

                mysqli_query($this->conn, "
                    INSERT INTO restock_logs
                        (product_id, boxes_received, units_per_box, pieces_added,
                         cost_per_box, total_cost, new_cost_per_piece, new_selling_price,
                         restocked_by)
                    VALUES
                        ($product_id, $boxes, $units_per_box, $quantity,
                         $cost_per_box, $total_cost, $cost_per_piece, $sell_price,
                         $current_user)
                ");
            } else if ($quantity > 0) {
                mysqli_query($this->conn, "UPDATE products SET status = 'Available' WHERE product_id = $product_id");
            }

            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $current_user, 'Create', 'inventory', $inventory_id,
                "Added product ID $product_id to inventory with $quantity pcs");

            mysqli_commit($this->conn);
            return 'success';
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return 'error: ' . $e->getMessage();
        }
    }

    /**
     * Restock an inventory item
     */
    public function restockInventoryItem($postData, $current_user) {
        $product_id      = (int)($postData['product_id'] ?? 0);
        $inventory_id    = (int)($postData['inventory_id'] ?? 0);
        $boxes_received  = max(1, (int)($postData['boxes_received'] ?? 1));
        $units_per_box   = max(1, (int)($postData['units_per_box'] ?? 1));
        $cost_per_box    = (float)($postData['cost_per_box'] ?? 0);
        $new_sell        = (float)($postData['selling_price'] ?? 0);
        $minimum_stock   = max(0, (int)($postData['minimum_stock'] ?? 5));
        $maximum_stock   = max(0, (int)($postData['maximum_stock'] ?? 100));
        $aisle           = mysqli_real_escape_string($this->conn, trim($postData['aisle'] ?? ''));
        $supplier        = mysqli_real_escape_string($this->conn, trim($postData['supplier'] ?? ''));
        $delivery_note   = mysqli_real_escape_string($this->conn, trim($postData['delivery_note'] ?? ''));

        // Resolve product_id from inventory_id if missing
        if (!$product_id && $inventory_id) {
            $invRes = mysqli_query($this->conn, "SELECT product_id FROM inventory WHERE inventory_id = $inventory_id");
            if ($invRes && mysqli_num_rows($invRes) > 0) {
                $product_id = (int)mysqli_fetch_assoc($invRes)['product_id'];
            }
        }

        if (!$product_id) {
            return 'error: Product ID is missing or invalid.';
        }
        if ($boxes_received < 1) {
            return 'error: Boxes received must be at least 1.';
        }
        if ($cost_per_box <= 0) {
            return 'error: Cost per box must be greater than zero.';
        }
        if ($new_sell <= 0) {
            return 'error: Selling price must be greater than zero.';
        }

        $pieces_added       = $boxes_received * $units_per_box;
        $total_cost         = round($boxes_received * $cost_per_box, 2);
        $new_cost_per_piece = $units_per_box > 0 ? round($cost_per_box / $units_per_box, 4) : 0;
        $sup_sql            = $supplier !== '' ? "'$supplier'" : "NULL";
        $note_sql           = $delivery_note !== '' ? "'$delivery_note'" : "NULL";

        mysqli_begin_transaction($this->conn);
        try {
            // 1. Log restock
            $logQuery = mysqli_query($this->conn, "
                INSERT INTO restock_logs
                    (product_id, boxes_received, units_per_box, pieces_added,
                     cost_per_box, total_cost, new_cost_per_piece, new_selling_price,
                     supplier, delivery_note, restocked_by)
                VALUES
                    ($product_id, $boxes_received, $units_per_box, $pieces_added,
                     $cost_per_box, $total_cost, $new_cost_per_piece, $new_sell,
                     $sup_sql, $note_sql, $current_user)
            ");

            if (!$logQuery) {
                throw new Exception("Restock logging failed: " . mysqli_error($this->conn));
            }

            // 2. Update inventory
            if ($inventory_id > 0) {
                $invUpdate = mysqli_query($this->conn, "
                    UPDATE inventory SET
                        quantity      = quantity + $pieces_added,
                        minimum_stock = $minimum_stock,
                        maximum_Stock = $maximum_stock,
                        aisle         = '$aisle',
                        last_restock  = NOW()
                    WHERE inventory_id = $inventory_id
                ");
            } else {
                $invUpdate = mysqli_query($this->conn, "
                    UPDATE inventory SET
                        quantity      = quantity + $pieces_added,
                        minimum_stock = $minimum_stock,
                        maximum_Stock = $maximum_stock,
                        aisle         = '$aisle',
                        last_restock  = NOW()
                    WHERE product_id = $product_id
                ");
            }

            if (!$invUpdate) {
                throw new Exception("Inventory update failed: " . mysqli_error($this->conn));
            }

            // 3. Update products
            mysqli_query($this->conn, "
                UPDATE products SET
                    status        = 'Available',
                    cost_price    = $new_cost_per_piece,
                    cost_per_box  = $cost_per_box,
                    units_per_box = $units_per_box,
                    selling_price = $new_sell
                WHERE product_id = $product_id
            ");

            $prow  = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT product_name FROM products WHERE product_id = $product_id"));
            $pname = $prow['product_name'] ?? 'Unknown';

            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $current_user, 'Update', 'inventory', $inventory_id,
                "Restocked '$pname': $boxes_received box(es) × $units_per_box pcs = $pieces_added pcs added. Total cost: ₱$total_cost");

            mysqli_query($this->conn, "
                INSERT INTO notifications (title, message, type, is_read)
                VALUES ('Stock Restocked', 'Restocked $pieces_added pcs of $pname via Inventory', 'Products', 0)
            ");

            mysqli_commit($this->conn);
            return 'success';
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return 'error: ' . $e->getMessage();
        }
    }

    /**
     * Remove stock manually
     */
    public function removeStock($inventory_id, $remove_quantity, $current_user) {
        $inventory_id   = (int)$inventory_id;
        $remove_quantity = max(1, (int)$remove_quantity);

        mysqli_begin_transaction($this->conn);
        try {
            $query = mysqli_query($this->conn, "
                UPDATE inventory SET
                    quantity = GREATEST(0, quantity - $remove_quantity)
                WHERE inventory_id = $inventory_id
            ");

            if (!$query) {
                throw new Exception("Removing stock database update failed: " . mysqli_error($this->conn));
            }

            $inv = mysqli_fetch_assoc(mysqli_query($this->conn,
                "SELECT product_id, quantity FROM inventory WHERE inventory_id = $inventory_id"
            ));
            $pid = (int)$inv['product_id'];
            $qty = (int)$inv['quantity'];

            mysqli_query($this->conn, "
                UPDATE products SET status = CASE WHEN $qty = 0 THEN 'Unavailable' ELSE 'Available' END
                WHERE product_id = $pid
            ");

            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $current_user, 'Update', 'inventory', $inventory_id,
                "Removed $remove_quantity units from inventory ID $inventory_id (New Qty: $qty)");

            mysqli_commit($this->conn);
            return 'success';
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return 'error: ' . $e->getMessage();
        }
    }
}
