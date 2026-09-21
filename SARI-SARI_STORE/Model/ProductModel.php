<?php
// Model/ProductModel.php

class ProductModel {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getProductsList() {
        return mysqli_query($this->conn, "
            SELECT p.*, c.category_name,
                   COALESCE(i.quantity, 0) AS stock_qty,
                   i.minimum_stock
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN inventory  i ON i.product_id  = p.product_id
            WHERE p.deleted_at IS NULL
            ORDER BY p.created_at DESC
        ");
    }

    public function getTrashedProductsList() {
        return mysqli_query($this->conn, "
            SELECT p.*, c.category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE p.deleted_at IS NOT NULL
            ORDER BY p.deleted_at DESC
        ");
    }

    public function getTrashedCount() {
        $res = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT COUNT(*) AS total FROM products WHERE deleted_at IS NOT NULL"
        ));
        return $res['total'] ?? 0;
    }

    public function getCategories() {
        return mysqli_query($this->conn, "SELECT * FROM categories ORDER BY category_name ASC");
    }

    public function getRestockLogs($product_id) {
        $product_id = (int)$product_id;
        return mysqli_query($this->conn, "
            SELECT r.*, u.full_name AS restocked_by_name
            FROM restock_logs r
            LEFT JOIN users u ON r.restocked_by = u.user_id
            WHERE r.product_id = $product_id
            ORDER BY r.restocked_at DESC
            LIMIT 20
        ");
    }

    public function isBarcodeUsed($barcode, $exclude_product_id = 0) {
        $barcode = mysqli_real_escape_string($this->conn, $barcode);
        $exclude_sql = $exclude_product_id > 0 ? "AND product_id != " . (int)$exclude_product_id : "";
        $q = mysqli_query($this->conn, "SELECT product_id FROM products WHERE barcode='$barcode' $exclude_sql AND deleted_at IS NULL");
        return ($q && mysqli_num_rows($q) > 0);
    }

    public function createProduct($category, $name, $barcode, $desc, $sell, $cost_per_piece, $units_per_box, $cost_per_box, $imageName, $status, $current_user) {
        mysqli_begin_transaction($this->conn);
        try {
            $q = mysqli_query($this->conn, "
                INSERT INTO products
                    (category_id, product_name, barcode, description,
                     selling_price, cost_price, units_per_box, cost_per_box,
                     image, status, added_by)
                VALUES
                    ($category, '$name', '$barcode', '$desc',
                     $sell, $cost_per_piece, $units_per_box, $cost_per_box,
                     '$imageName', '$status', $current_user)
            ");

            if (!$q) {
                throw new Exception("Product insertion failed: " . mysqli_error($this->conn));
            }

            $pid = mysqli_insert_id($this->conn);

            $inv = mysqli_query($this->conn, "INSERT INTO inventory (product_id, quantity, minimum_stock, last_restock) VALUES ($pid, 0, 5, NULL)");
            if (!$inv) {
                throw new Exception("Inventory registration failed: " . mysqli_error($this->conn));
            }

            require_once __DIR__ . '/logger.php';
            logAction($this->conn, $current_user, 'Create', 'products', $pid, "Added product: $name (units/box: $units_per_box, cost/box: P$cost_per_box)");
            mysqli_query($this->conn, "INSERT INTO notifications (title, message, type, is_read) VALUES ('Product Added','New product: $name','Products',0)");

            mysqli_commit($this->conn);
            return 'success';
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return 'error: ' . $e->getMessage();
        }
    }

    public function restockProduct($product_id, $boxes, $units_per_box, $pieces_added, $cost_per_box, $total_cost, $new_cost_per_piece, $new_sell, $supplier, $note, $current_user) {
        $sup_sql  = $supplier !== '' ? "'$supplier'" : "NULL";
        $note_sql = $note !== '' ? "'$note'" : "NULL";

        mysqli_begin_transaction($this->conn);
        try {
            $logQuery = mysqli_query($this->conn, "
                INSERT INTO restock_logs
                    (product_id, boxes_received, units_per_box, pieces_added,
                     cost_per_box, total_cost, new_cost_per_piece, new_selling_price,
                     supplier, delivery_note, restocked_by)
                VALUES
                    ($product_id, $boxes, $units_per_box, $pieces_added,
                     $cost_per_box, $total_cost, $new_cost_per_piece, $new_sell,
                     $sup_sql, $note_sql, $current_user)
            ");

            if (!$logQuery) {
                throw new Exception("Restock log failed: " . mysqli_error($this->conn));
            }

            $inv = mysqli_query($this->conn, "SELECT inventory_id FROM inventory WHERE product_id = $product_id LIMIT 1");
            if ($inv && mysqli_num_rows($inv) > 0) {
                mysqli_query($this->conn, "UPDATE inventory SET quantity = quantity + $pieces_added, last_restock = NOW() WHERE product_id = $product_id");
            } else {
                mysqli_query($this->conn, "INSERT INTO inventory (product_id, quantity, minimum_stock, last_restock) VALUES ($product_id, $pieces_added, 5, NOW())");
            }

            $prodUpdate = mysqli_query($this->conn, "
                UPDATE products SET
                    cost_price    = $new_cost_per_piece,
                    cost_per_box  = $cost_per_box,
                    units_per_box = $units_per_box,
                    selling_price = $new_sell,
                    status        = 'Available'
                WHERE product_id = $product_id
            ");

            if (!$prodUpdate) {
                throw new Exception("Product details update failed: " . mysqli_error($this->conn));
            }

            $prow  = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT product_name FROM products WHERE product_id = $product_id"));
            $pname = $prow['product_name'] ?? 'Unknown';

            require_once __DIR__ . '/logger.php';
            logAction($this->conn, $current_user, 'Restock', 'products', $product_id,
                "Restocked '$pname': $boxes box(es) x $units_per_box pcs = $pieces_added pcs. Total: P$total_cost");

            mysqli_query($this->conn, "INSERT INTO notifications (title, message, type, is_read) VALUES ('Restocked','$pname: +$pieces_added pcs','Products',0)");

            mysqli_commit($this->conn);
            return 'success';
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return 'error: ' . $e->getMessage();
        }
    }

    public function updateProduct($id, $category, $name, $barcode, $desc, $sell, $cost_per_piece, $units_per_box, $cost_per_box, $imageName, $status, $reason, $current_user) {
        $imgSql = $imageName !== '' ? "'$imageName'" : "NULL";

        $q = mysqli_query($this->conn, "
            UPDATE products SET
                category_id   = $category,
                product_name  = '$name',
                barcode       = '$barcode',
                description   = '$desc',
                selling_price = $sell,
                cost_price    = $cost_per_piece,
                units_per_box = $units_per_box,
                cost_per_box  = $cost_per_box,
                image         = IF($imgSql IS NULL, image, $imgSql),
                status        = '$status'
            WHERE product_id = $id
        ");

        if ($q) {
            require_once __DIR__ . '/logger.php';
            logAction($this->conn, $current_user, 'Update', 'products', $id, "Updated product '$name' - Reason: $reason");
            mysqli_query($this->conn, "INSERT INTO notifications (title, message, type, is_read) VALUES ('Product Updated','Updated: $name','Products',0)");
            return 'success';
        } else {
            return 'error: ' . mysqli_error($this->conn);
        }
    }

    public function deleteProduct($id, $reason, $current_user) {
        $nameRow = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT product_name FROM products WHERE product_id=$id"));
        $name = $nameRow ? $nameRow['product_name'] : 'Unknown';

        $q = mysqli_query($this->conn, "UPDATE products SET deleted_at=NOW(), deleted_reason='$reason', status='Unavailable' WHERE product_id=$id");
        if ($q) {
            require_once __DIR__ . '/logger.php';
            logAction($this->conn, $current_user, 'Trash', 'products', $id, "Archived '$name' - Reason: $reason");
            mysqli_query($this->conn, "INSERT INTO notifications (title, message, type, is_read) VALUES ('Product Archived','Archived: $name','Products',0)");
            return 'success';
        } else {
            return 'error: ' . mysqli_error($this->conn);
        }
    }

    public function restoreProduct($id, $reason, $current_user) {
        $q = mysqli_query($this->conn, "UPDATE products SET deleted_at=NULL, deleted_reason=NULL WHERE product_id=$id");
        if ($q) {
            $nameRow = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT product_name FROM products WHERE product_id=$id"));
            $name = $nameRow ? $nameRow['product_name'] : 'Unknown';

            require_once __DIR__ . '/logger.php';
            logAction($this->conn, $current_user, 'Restore', 'products', $id, "Restored product '$name' - Reason: $reason");
            mysqli_query($this->conn, "INSERT INTO notifications (title, message, type, is_read) VALUES ('Product Restored','Restored: $name','Products',0)");
            return 'success';
        } else {
            return 'error: ' . mysqli_error($this->conn);
        }
    }
}
