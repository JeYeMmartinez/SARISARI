<?php
// Model/NotificationModel.php

class NotificationModel {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getUnreadCount() {
        $query = "SELECT COUNT(*) AS total FROM notifications WHERE is_read = 0";
        $result = mysqli_query($this->conn, $query);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            return (int)$row['total'];
        }
        return 0;
    }

    public function getTotalCount() {
        $query = "SELECT COUNT(*) AS total FROM notifications";
        $result = mysqli_query($this->conn, $query);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            return (int)$row['total'];
        }
        return 0;
    }

    public function getAllNotifications() {
        $query = "SELECT * FROM notifications ORDER BY is_read ASC, created_at DESC";
        return mysqli_query($this->conn, $query);
    }

    public function markAsRead($id) {
        $id = (int)$id;
        $query = "UPDATE notifications SET is_read = 1 WHERE notification_id = $id";
        return mysqli_query($this->conn, $query);
    }

    public function markAllAsRead() {
        $query = "UPDATE notifications SET is_read = 1";
        return mysqli_query($this->conn, $query);
    }

    public function deleteNotification($id) {
        $id = (int)$id;
        $query = "DELETE FROM notifications WHERE notification_id = $id";
        return mysqli_query($this->conn, $query);
    }

    public function deleteAllRead() {
        $query = "DELETE FROM notifications WHERE is_read = 1";
        return mysqli_query($this->conn, $query);
    }
}
