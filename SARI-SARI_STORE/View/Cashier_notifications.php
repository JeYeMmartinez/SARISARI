<?php
session_start();
require_once '../Model/database.php';

if(!isset($_SESSION['user_id'])){
    echo 'unauthorized';
    exit();
}

/*=========================================================
    GET UNREAD COUNT (used by sidebar badge polling)
==========================================================*/
if(isset($_GET['action']) && $_GET['action'] == 'get_unread_count'){
    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS total FROM notifications WHERE is_read = 0 AND type = 'Sales'"
    ));
    echo $row['total'];
    exit();
}

/*=========================================================
    ACTIONS
==========================================================*/

// Mark single as read
if(isset($_POST['action']) && $_POST['action'] == 'mark_read'){
    $id = (int)$_POST['notification_id'];
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE notification_id = $id");
    echo 'success';
    exit();
}

// Mark all as read (only Sales-type, cashier shouldn't touch admin-only notifs)
if(isset($_POST['action']) && $_POST['action'] == 'mark_all_read'){
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE type = 'Sales'");
    echo 'success';
    exit();
}

/*=========================================================
    FETCH DATA — only Sales-related notifications
==========================================================*/

$notifications = mysqli_query($conn,"
    SELECT * FROM notifications
    WHERE type = 'Sales'
    ORDER BY is_read ASC, created_at DESC
");

$unreadCount = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM notifications WHERE is_read = 0 AND type = 'Sales'"
))['total'];

$totalCount = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM notifications WHERE type = 'Sales'"
))['total'];

?>

<style>
.notif-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    margin-bottom: 20px;
}
.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 10px;
    border-left: 4px solid transparent;
    transition: .2s;
    background: #f8f9fa;
}
.notif-item.unread {
    background: #f0faf4;
    border-left-color: #198754;
}
.notif-item.unread .notif-title { font-weight: 700; }
.notif-item:hover { background: #e9ecef; }
.notif-icon {
    width: 42px; height: 42px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: white; flex-shrink: 0;
    background: #198754;
}
.notif-body { flex: 1; }
.notif-title { font-size: 14px; font-weight: 600; margin-bottom: 2px; }
.notif-message { font-size: 13px; color: #6c757d; margin-bottom: 4px; }
.notif-meta { font-size: 11px; color: #adb5bd; }
.unread-dot {
    width: 8px; height: 8px; background: #198754;
    border-radius: 50%; margin-top: 6px; flex-shrink: 0;
}
.summary-card {
    background: white; border-radius: 12px; padding: 16px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06); height: 100%;
}
</style>

<!-- SUMMARY CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="summary-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Total</small>
                    <h3 class="fw-bold mb-0"><?= $totalCount; ?></h3>
                </div>
                <div style="width:42px;height:42px;border-radius:10px;background:#6c757d;display:flex;align-items:center;justify-content:center;color:white;font-size:18px;">
                    <i class="bi bi-bell"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="summary-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Unread</small>
                    <h3 class="fw-bold mb-0 text-success"><?= $unreadCount; ?></h3>
                </div>
                <div style="width:42px;height:42px;border-radius:10px;background:#198754;display:flex;align-items:center;justify-content:center;color:white;font-size:18px;">
                    <i class="bi bi-bell-fill"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        Sales Notifications
        <?php if($unreadCount > 0){ ?>
        <span class="badge bg-success ms-1"><?= $unreadCount; ?> new</span>
        <?php } ?>
    </h5>
    <?php if($unreadCount > 0){ ?>
    <button class="btn btn-sm btn-outline-success" onclick="markAllReadCashier()">
        <i class="bi bi-check2-all me-1"></i>Mark All Read
    </button>
    <?php } ?>
</div>

<!-- LIST -->
<div class="notif-card">

    <?php if($totalCount == 0){ ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-bell-slash" style="font-size:48px;"></i>
        <p class="mt-3 mb-0">No notifications yet</p>
    </div>

    <?php } else {
        while($notif = mysqli_fetch_assoc($notifications)){
            $isUnread = $notif['is_read'] == 0;
            $timeAgo = timeAgo($notif['created_at']);
    ?>

    <div class="notif-item <?= $isUnread ? 'unread' : ''; ?>" id="cnotif-<?= $notif['notification_id']; ?>">

        <div class="notif-icon">
            <i class="bi bi-receipt"></i>
        </div>

        <div class="notif-body">
            <div class="notif-title"><?= htmlspecialchars($notif['title']); ?></div>
            <div class="notif-message"><?= htmlspecialchars($notif['message']); ?></div>
            <div class="notif-meta"><?= $timeAgo; ?></div>
        </div>

        <?php if($isUnread){ ?>
        <button class="btn btn-sm btn-outline-success" title="Mark as Read"
                onclick="markReadCashier(<?= $notif['notification_id']; ?>)">
            <i class="bi bi-check2"></i>
        </button>
        <div class="unread-dot"></div>
        <?php } ?>

    </div>

    <?php } } ?>

</div>

<?php
function timeAgo($datetime){
    $now  = new DateTime();
    $ago  = new DateTime($datetime);
    $diff = $now->diff($ago);

    if($diff->d == 0){
        if($diff->h == 0){
            return $diff->i == 0 ? 'Just now' : $diff->i . ' min ago';
        }
        return $diff->h . ' hr ago';
    } elseif($diff->d == 1){
        return 'Yesterday';
    } elseif($diff->d < 7){
        return $diff->d . ' days ago';
    } else {
        return date("M d, Y", strtotime($datetime));
    }
}
?>

<script>
function markReadCashier(id){
    $.post('cashier_notifications.php', {
        action: 'mark_read',
        notification_id: id
    }, function(response){
        if(response.trim() == 'success'){
            const item = $("#cnotif-" + id);
            item.removeClass('unread');
            item.find('.unread-dot').remove();
            item.find('.btn-outline-success').remove();
            if(typeof refreshNotifBadge === 'function') refreshNotifBadge();
        }
    });
}

function markAllReadCashier(){
    $.post('cashier_notifications.php', {
        action: 'mark_all_read'
    }, function(response){
        if(response.trim() == 'success'){
            Swal.fire({ icon:'success', title:'All marked as read!',
                showConfirmButton:false, timer:1200 })
            .then(() => {
                loadPage('cashier_notifications.php');
                if(typeof refreshNotifBadge === 'function') refreshNotifBadge();
            });
        }
    });
}
</script>