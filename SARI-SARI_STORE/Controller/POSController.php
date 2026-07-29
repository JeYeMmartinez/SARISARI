<?php
// Controller/POSController.php

class POSController {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Fetch all available products with stock details
     */
    public function getAvailableProducts() {
        return mysqli_query($this->conn, "
            SELECT p.product_id, p.product_name, p.selling_price, p.image,
                   c.category_name, c.category_id,
                   IFNULL(i.quantity, 0) AS stock
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN inventory i ON p.product_id = i.product_id
            WHERE p.status = 'Available' AND p.deleted_at IS NULL
            ORDER BY p.product_name ASC
        ");
    }

    /**
     * Fetch categories that have active products
     */
    public function getAvailableCategories() {
        return mysqli_query($this->conn, "
            SELECT DISTINCT c.category_id, c.category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE p.status = 'Available' AND p.deleted_at IS NULL
            ORDER BY c.category_name ASC
        ");
    }

    /**
     * Process a sale transaction
     */
    public function processSale($cashier_id, $items, $total, $payment) {
        $total   = (float)$total;
        $payment = (float)$payment;
        $change  = $payment - $total;

        if ($payment < $total) {
            return 'insufficient';
        }
        if (empty($items)) {
            return 'empty';
        }

        // Start transaction
        mysqli_begin_transaction($this->conn);

        try {
            $saleQuery = mysqli_query($this->conn, "
                INSERT INTO sales (cashier_id, total_amount, payment, change_amount, status)
                VALUES ($cashier_id, $total, $payment, $change, 'Completed')
            ");

            if (!$saleQuery) {
                throw new Exception("Sale insertion failed: " . mysqli_error($this->conn));
            }

            $sale_id = mysqli_insert_id($this->conn);

            foreach ($items as $item) {
                $product_id = (int)$item['product_id'];
                $quantity   = (int)$item['quantity'];
                $price      = (float)$item['price'];
                $subtotal   = $price * $quantity;

                $itemQuery = mysqli_query($this->conn, "
                    INSERT INTO sale_items (sale_id, product_id, quantity, selling_price, subtotal)
                    VALUES ($sale_id, $product_id, $quantity, $price, $subtotal)
                ");

                if (!$itemQuery) {
                    throw new Exception("Sale item insertion failed: " . mysqli_error($this->conn));
                }

                $stockQuery = mysqli_query($this->conn, "
                    UPDATE inventory SET quantity = GREATEST(0, quantity - $quantity)
                    WHERE product_id = $product_id
                ");

                if (!$stockQuery) {
                    throw new Exception("Stock update failed: " . mysqli_error($this->conn));
                }

                // Auto update product status
                mysqli_query($this->conn, "
                    UPDATE products SET status =
                        CASE WHEN (SELECT quantity FROM inventory WHERE product_id = $product_id) = 0
                        THEN 'Unavailable' ELSE 'Available' END
                    WHERE product_id = $product_id
                ");

                // Low stock notification
                $inv = mysqli_fetch_assoc(mysqli_query($this->conn,
                    "SELECT quantity, minimum_stock FROM inventory WHERE product_id = $product_id"
                ));
                $prod = mysqli_fetch_assoc(mysqli_query($this->conn,
                    "SELECT product_name FROM products WHERE product_id = $product_id"
                ));

                if ($inv && $inv['quantity'] <= $inv['minimum_stock']) {
                    $pname = mysqli_real_escape_string($this->conn, $prod['product_name']);
                    $msg   = $inv['quantity'] == 0
                        ? "\"$pname\" is now OUT OF STOCK."
                        : "\"$pname\" is running LOW — only {$inv['quantity']} left.";
                    mysqli_query($this->conn, "
                        INSERT INTO notifications (title, message, type)
                        VALUES ('Low Stock Alert', '$msg', 'Low Stock')
                    ");
                }
            }

            // Log action
            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $cashier_id, 'Create', 'sales', $sale_id,
                "Processed sale #$sale_id — Total: ₱$total");

            // Notification for Sale Completion
            $formattedTotal = number_format($total, 2);
            mysqli_query($this->conn, "
                INSERT INTO notifications (title, message, type, is_read)
                VALUES ('Sale Completed', 'Sale #$sale_id processed successfully — Total: ₱{$formattedTotal}', 'Sales', 0)
            ");

            mysqli_commit($this->conn);
            return 'success:' . $sale_id . ':' . $change;

        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return 'error: ' . $e->getMessage();
        }
    }
}
