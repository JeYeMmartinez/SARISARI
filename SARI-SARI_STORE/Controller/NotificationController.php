<?php
// Controller/NotificationController.php

class NotificationController {
    private $model;

    public function __construct($model) {
        $this->model = $model;
    }

    public function index() {
        // Fetch data for the view
        $notifications = $this->model->getAllNotifications();
        $unreadCount = $this->model->getUnreadCount();
        $totalCount = $this->model->getTotalCount();

        require_once __DIR__ . '/../View/notification.php';
    }

    public function handleAction($action) {
        switch ($action) {
            case 'get_unread_count':
                // It was originally handled via GET action for this specific call in notification.php
                echo $this->model->getUnreadCount();
                break;
            case 'mark_read':
                $id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
                $this->model->markAsRead($id);
                echo 'success';
                break;
            case 'mark_all_read':
                $this->model->markAllAsRead();
                echo 'success';
                break;
            case 'delete':
                $id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
                $this->model->deleteNotification($id);
                echo 'success';
                break;
            case 'delete_read':
                $this->model->deleteAllRead();
                echo 'success';
                break;
            default:
                http_response_code(400);
                echo 'Invalid action';
                break;
        }
    }
}
