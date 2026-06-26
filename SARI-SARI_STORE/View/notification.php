<?php
require_once '../Model/database.php';

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

// Mark all as read
if(isset($_POST['action']) && $_POST['action'] == 'mark_all_read'){
    mysqli_query($conn, "UPDATE notifications SET is_read = 1");
    echo 'success';
    exit();
}

// Delete single
if(isset($_POST['action']) && $_POST['action'] == 'delete'){
    $id = (int)$_POST['notification_id'];
    mysqli_query($conn, "DELETE FROM notifications WHERE notification_id = $id");
    echo 'success';
    exit();
}

// Delete all read
if(isset($_POST['action']) && $_POST['action'] == 'delete_read'){
    mysqli_query($conn, "DELETE FROM notifications WHERE is_read = 1");
    echo 'success';
    exit();
}

/*=========================================================
    FETCH DATA
==========================================================*/

$notifications = mysqli_query($conn,"
    SELECT * FROM notifications
    ORDER BY is_read ASC, created_at DESC
");

$unreadCount = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM notifications WHERE is_read = 0"
))['total'];

$totalCount = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM notifications"
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

.notif-item.unread .notif-title {
    font-weight: 700;
}

.notif-item:hover {
    background: #e9ecef;
}

.notif-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: white;
    flex-shrink: 0;
}

.notif-body { flex: 1; }

.notif-title {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 2px;
}

.notif-message {
    font-size: 13px;
    color: #6c757d;
    margin-bottom: 4px;
}

.notif-meta {
    font-size: 11px;
    color: #adb5bd;
}

.notif-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

.unread-dot {
    width: 8px;
    height: 8px;
    background: #198754;
    border-radius: 50%;
    margin-top: 6px;
    flex-shrink: 0;
}

.summary-card {
    background: white;
    border-radius: 12px;
    padding: 16px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    height: 100%;
}
</style>

<!-- SUMMARY CARDS -->
<div class="row g-3 mb-4">

    <div class="col-md-4">
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

    <div class="col-md-4">
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

    <div class="col-md-4">
        <div class="summary-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Read</small>
                    <h3 class="fw-bold mb-0 text-muted"><?= $totalCount - $unreadCount; ?></h3>
                </div>
                <div style="width:42px;height:42px;border-radius:10px;background:#adb5bd;display:flex;align-items:center;justify-content:center;color:white;font-size:18px;">
                    <i class="bi bi-check2-all"></i>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- HEADER + ACTION BUTTONS -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        All Notifications
        <?php if($unreadCount > 0){ ?>
        <span class="badge bg-success ms-1"><?= $unreadCount; ?> new</span>
        <?php } ?>
    </h5>
    <div class="d-flex gap-2">
        <?php if($unreadCount > 0){ ?>
        <button class="btn btn-sm btn-outline-success" onclick="markAllRead()">
            <i class="bi bi-check2-all me-1"></i>Mark All Read
        </button>
        <?php } ?>
        <button class="btn btn-sm btn-outline-danger" onclick="deleteRead()">
            <i class="bi bi-trash me-1"></i>Clear Read
        </button>
    </div>
</div>

<!-- NOTIFICATIONS LIST -->
<div class="notif-card">

    <?php if($totalCount == 0){ ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-bell-slash" style="font-size:48px;"></i>
        <p class="mt-3 mb-0">No notifications yet</p>
    </div>

    <?php } else {
        while($notif = mysqli_fetch_assoc($notifications)){
            $isUnread = $notif['is_read'] == 0;

            // Icon and color per type
            switch($notif['type']){
                case 'Low Stock':
                    $icon = 'bi-exclamation-triangle-fill';
                    $color = '#ffc107';
                    break;
                case 'Approval':
                    $icon = 'bi-check-circle-fill';
                    $color = '#0d6efd';
                    break;
                case 'Sales':
                    $icon = 'bi-graph-up-arrow';
                    $color = '#198754';
                    break;
                default:
                    $icon = 'bi-info-circle-fill';
                    $color = '#6c757d';
            }

            $timeAgo = timeAgo($notif['created_at']);
    ?>

    <div class="notif-item <?= $isUnread ? 'unread' : ''; ?>"
         id="notif-<?= $notif['notification_id']; ?>">

        <div class="notif-icon" style="background:<?= $color; ?>">
            <i class="bi <?= $icon; ?>"></i>
        </div>

        <div class="notif-body">
            <div class="notif-title"><?= htmlspecialchars($notif['title']); ?></div>
            <div class="notif-message"><?= htmlspecialchars($notif['message']); ?></div>
            <div class="notif-meta">
                <span class="badge" style="background:<?= $color; ?>">
                    <?= $notif['type']; ?>
                </span>
                &nbsp;<?= $timeAgo; ?>
            </div>
        </div>

        <div class="notif-actions">
            <?php if($isUnread){ ?>
            <button class="btn btn-sm btn-outline-success" title="Mark as Read"
                    onclick="markRead(<?= $notif['notification_id']; ?>)">
                <i class="bi bi-check2"></i>
            </button>
            <?php } ?>
            <button class="btn btn-sm btn-outline-danger" title="Delete"
                    onclick="deleteNotif(<?= $notif['notification_id']; ?>)">
                <i class="bi bi-trash"></i>
            </button>
        </div>

        <?php if($isUnread){ ?>
        <div class="unread-dot"></div>
        <?php } ?>

    </div>

    <?php } } ?>

</div>

<?php
/*=========================================================
    TIME AGO HELPER
==========================================================*/
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

<!--=========================================================
    JAVASCRIPT
==========================================================-->
<script>

function markRead(id){
    $.post('notifications.php', {
        action: 'mark_read',
        notification_id: id
    }, function(response){
        if(response == 'success'){
            const item = $("#notif-" + id);
            item.removeClass('unread');
            item.find('.unread-dot').remove();
            item.find('.btn-outline-success').remove();
            // Update unread badge in sidebar (if you add one later)
        }
    });
}

function markAllRead(){
    $.post('notifications.php', {
        action: 'mark_all_read'
    }, function(response){
        if(response == 'success'){
            Swal.fire({ icon:'success', title:'All marked as read!',
                showConfirmButton:false, timer:1200 })
            .then(() => { loadPage('notifications.php'); });
        }
    });
}

function deleteNotif(id){
    $.post('notifications.php', {
        action: 'delete',
        notification_id: id
    }, function(response){
        if(response == 'success'){
            $("#notif-" + id).fadeOut(300, function(){ $(this).remove(); });
        }
    });
}

function deleteRead(){
    Swal.fire({
        title: 'Clear all read notifications?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Clear'
    }).then(result => {
        if(result.isConfirmed){
            $.post('notifications.php', {
                action: 'delete_read'
            }, function(response){
                if(response == 'success'){
                    Swal.fire({ icon:'success', title:'Cleared!',
                        showConfirmButton:false, timer:1200 })
                    .then(() => { loadPage('notifications.php'); });
                }
            });
        }
    });
}

</script>