<?php
// Controller/OrderController.php

class OrderController {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Get list of pending orders
     */
    public function getPendingOrdersList() {
        return mysqli_query($this->conn, "
            SELECT o.*, u.full_name, u.gmail,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) AS item_count,
                o.total AS order_total
            FROM orders o
            LEFT JOIN users u ON o.cashier_id = u.user_id
            WHERE o.status = 'Pending'
            ORDER BY o.created_at ASC
        ");
    }

    /**
     * Get list of completed/approved orders
     */
    public function getApprovedOrdersList() {
        return mysqli_query($this->conn, "
            SELECT o.*, u.full_name, u.gmail,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) AS item_count,
                o.total AS order_total
            FROM orders o
            LEFT JOIN users u ON o.cashier_id = u.user_id
            WHERE o.status = 'Completed'
            ORDER BY o.created_at DESC
        ");
    }

    /**
     * Get count of pending orders
     */
    public function getPendingCount() {
        $res = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT COUNT(*) AS total FROM orders WHERE status = 'Pending'"
        ));
        return $res['total'] ?? 0;
    }

    /**
     * Get approved orders counts and total sales value
     */
    public function getApprovedSummaryStats() {
        $count = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT COUNT(*) AS total FROM orders WHERE status = 'Completed'"
        ))['total'] ?? 0;

        $total = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT COALESCE(SUM(total),0) AS total FROM orders WHERE status = 'Completed'"
        ))['total'] ?? 0;

        return [
            'approved_count' => $count,
            'approved_total' => $total
        ];
    }

    /**
     * Fetch order items
     */
    public function getOrderItems($order_id) {
        $order_id = (int)$order_id;
        return mysqli_query($this->conn, "
            SELECT oi.product_id,
                   COALESCE(NULLIF(oi.product_name, ''), p.product_name, CONCAT('Product #', oi.product_id)) AS product_name,
                   oi.quantity, oi.selling_price, oi.subtotal
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.product_id
            WHERE oi.order_id = $order_id
        ");
    }

    /**
     * Approve a pending order and register a sale
     */
    public function approveOrder($order_id, $note, $current_user) {
        $order_id = (int)$order_id;
        $note = mysqli_real_escape_string($this->conn, trim($note));

        if ($note === '') {
            return 'error: A note is required to approve this order.';
        }

        $orderRow = mysqli_fetch_assoc(mysqli_query($this->conn, "
            SELECT o.*, u.full_name, u.gmail
            FROM orders o
            LEFT JOIN users u ON o.cashier_id = u.user_id
            WHERE o.order_id = $order_id AND o.status = 'Pending'
        "));

        if (!$orderRow) {
            return 'error: Order not found or already processed.';
        }

        // Fetch order items to process
        $itemsQuery = mysqli_query($this->conn, "
            SELECT oi.*, p.product_name AS pname
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.product_id
            WHERE oi.order_id = $order_id
        ");

        $items = [];
        while ($row = mysqli_fetch_assoc($itemsQuery)) {
            $items[] = $row;
        }

        if (empty($items)) {
            return 'error: Order has no items.';
        }

        mysqli_begin_transaction($this->conn);
        try {
            // Validate stock
            foreach ($items as $item) {
                $pid = (int)$item['product_id'];
                $qty = (int)$item['quantity'];
                $stock = mysqli_fetch_assoc(mysqli_query($this->conn,
                    "SELECT quantity FROM inventory WHERE product_id = $pid"
                ));
                if (!$stock || (int)$stock['quantity'] < $qty) {
                    throw new Exception("Insufficient stock for '{$item['product_name']}'. Available: " . ($stock['quantity'] ?? 0));
                }
            }

            $total = (float)$orderRow['total'];

            // Create sale
            $saleInsert = mysqli_query($this->conn, "
                INSERT INTO sales (cashier_id, total_amount, payment, change_amount, status, created_at)
                VALUES ($current_user, $total, $total, 0, 'Completed', NOW())
            ");
            if (!$saleInsert) {
                throw new Exception("Failed to create sale: " . mysqli_error($this->conn));
            }

            $sale_id = mysqli_insert_id($this->conn);

            // Deduct stock & record sale items
            foreach ($items as $item) {
                $pid      = (int)$item['product_id'];
                $qty      = (int)$item['quantity'];
                $price    = (float)$item['selling_price'];
                $subtotal = (float)$item['subtotal'];

                // Stock was already deducted when the customer placed the order/cart.
                // We only record the sale items without deducting stock a second time.
                mysqli_query($this->conn, "
                    INSERT INTO sale_items (sale_id, product_id, quantity, selling_price, subtotal)
                    VALUES ($sale_id, $pid, $qty, $price, $subtotal)
                ");
            }

            // Mark order Completed
            mysqli_query($this->conn, "UPDATE orders SET status = 'Completed' WHERE order_id = $order_id");

            $custName = mysqli_real_escape_string($this->conn, $orderRow['full_name']);

            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $current_user, 'Create', 'sales', $sale_id,
                "Approved order #$order_id for $custName → Sale #$sale_id created — Note: $note");

            mysqli_query($this->conn, "
                INSERT INTO notifications (title, message, type, is_read)
                VALUES ('Order Approved', 'Order #$order_id for $custName approved → Sale #$sale_id created', 'Approval', 0)
            ");

            mysqli_commit($this->conn);
            return 'success';
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return 'error: ' . $e->getMessage();
        }
    }

    /**
     * Cancel/Void a pending order
     */
    public function cancelOrder($order_id, $reason, $current_user) {
        $order_id = (int)$order_id;
        $reason = mysqli_real_escape_string($this->conn, trim($reason));

        if ($reason === '') {
            return 'error: A reason is required to cancel this order.';
        }

        $orderRow = mysqli_fetch_assoc(mysqli_query($this->conn, "
            SELECT o.*, u.full_name
            FROM orders o
            LEFT JOIN users u ON o.cashier_id = u.user_id
            WHERE o.order_id = $order_id AND o.status = 'Pending'
        "));

        if (!$orderRow) {
            return 'error: Order not found or already processed.';
        }

        // Restore inventory stock for cancelled order
        $itemsQuery = mysqli_query($this->conn, "SELECT product_id, quantity FROM order_items WHERE order_id = $order_id");
        if ($itemsQuery) {
            while ($item = mysqli_fetch_assoc($itemsQuery)) {
                $pid = (int)$item['product_id'];
                $qty = (int)$item['quantity'];
                mysqli_query($this->conn, "UPDATE inventory SET quantity = quantity + $qty WHERE product_id = $pid");
                mysqli_query($this->conn, "UPDATE products SET status = 'Available' WHERE product_id = $pid");
            }
        }

        $query = mysqli_query($this->conn, "UPDATE orders SET status = 'Voided' WHERE order_id = $order_id");

        if ($query) {
            $custName = mysqli_real_escape_string($this->conn, $orderRow['full_name']);

            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $current_user, 'Void', 'orders', $order_id,
                "Cancelled order #$order_id for $custName — Reason: $reason");

            mysqli_query($this->conn, "
                INSERT INTO notifications (title, message, type, is_read)
                VALUES ('Order Cancelled', 'Order #$order_id for $custName was cancelled', 'Approval', 0)
            ");

            return 'success';
        } else {
            return 'error: ' . mysqli_error($this->conn);
        }
    }
}
