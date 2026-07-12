<?php
require_once '../Model/database.php';

/*=========================================================
    AJAX ACTIONS
==========================================================*/

// Mark single as read
if(isset($_POST['action']) && $_POST['action'] == 'mark_read'){
    $id = (int)$_POST['notification_id'];
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE notification_id = $id");
    ob_clean();
    echo 'success';
    exit();
}

// Mark all as read
if(isset($_POST['action']) && $_POST['action'] == 'mark_all_read'){
    mysqli_query($conn, "UPDATE notifications SET is_read = 1");
    ob_clean();
    echo 'success';
    exit();
}

// Delete single
if(isset($_POST['action']) && $_POST['action'] == 'delete'){
    $id = (int)$_POST['notification_id'];
    mysqli_query($conn, "DELETE FROM notifications WHERE notification_id = $id");
    ob_clean();
    echo 'success';
    exit();
}

// Delete all read
if(isset($_POST['action']) && $_POST['action'] == 'delete_read'){
    mysqli_query($conn, "DELETE FROM notifications WHERE is_read = 1");
    ob_clean();
    echo 'success';
    exit();
}

// Get unread count (for sidebar badge polling)
if(isset($_GET['action']) && $_GET['action'] == 'get_unread_count'){
    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS total FROM notifications WHERE is_read = 0"
    ));
    ob_clean();
    echo $row['total'];
    exit();
}

/*=========================================================
    FETCH DATA
==========================================================*/

$notifications = mysqli_query($conn,"
    SELECT * FROM notifications
    ORDER BY is_read ASC, created_at DESC
");

$notifList = [];
while($n = mysqli_fetch_assoc($notifications)){
    $notifList[] = $n;
}

$totalCount  = count($notifList);
$unreadCount = 0;
$typeCounts  = [];
foreach($notifList as $n){
    if($n['is_read'] == 0) $unreadCount++;
    $type = $n['type'];
    $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
}
$readCount = $totalCount - $unreadCount;

/*=========================================================
    TIME AGO HELPER
==========================================================*/
function hrmsTimeAgo($datetime){
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

<style>
/*====================================================
    HRMS NOTIFICATIONS STYLES
====================================================*/
.hrms-notif-stat {
    background: white;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
    height: 100%;
    transition: transform .2s, box-shadow .2s;
}
.hrms-notif-stat:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,.1);
}
.hrms-notif-stat .icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; color: white; flex-shrink: 0;
}

.notif-filter-tabs {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.notif-filter-btn {
    padding: 6px 14px;
    border-radius: 20px;
    border: 1px solid #e5e7eb;
    background: white;
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
    cursor: pointer;
    transition: all .2s;
}
.notif-filter-btn:hover {
    border-color: #2563eb;
    color: #2563eb;
}
.notif-filter-btn.active {
    background: #2563eb;
    border-color: #2563eb;
    color: white;
}
.notif-filter-btn .filter-count {
    display: inline-block;
    background: rgba(0,0,0,.1);
    padding: 0 6px;
    border-radius: 10px;
    font-size: 10px;
    margin-left: 4px;
}
.notif-filter-btn.active .filter-count {
    background: rgba(255,255,255,.25);
}

.hrms-notif-card {
    background: white;
    border-radius: 14px;
    padding: 20px 24px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}

.hrms-notif-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 8px;
    border-left: 4px solid transparent;
    transition: all .2s;
    background: #f8fafc;
}
.hrms-notif-item.unread {
    background: #eff6ff;
    border-left-color: #2563eb;
}
.hrms-notif-item.unread .hn-title {
    font-weight: 700;
}
.hrms-notif-item:hover {
    background: #e8edf2;
}

.hn-icon {
    width: 42px; height: 42px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: white; flex-shrink: 0;
}

.hn-body { flex: 1; min-width: 0; }
.hn-title { font-size: 14px; font-weight: 600; margin-bottom: 2px; color: #1f2937; }
.hn-message { font-size: 13px; color: #6b7280; margin-bottom: 4px; }
.hn-meta { font-size: 11px; color: #9ca3af; display: flex; align-items: center; gap: 8px; }

.hn-type-badge {
    font-size: 10px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 10px;
    color: white;
}

.hn-actions {
    display: flex;
    gap: 4px;
    flex-shrink: 0;
}
.hn-actions .btn { padding: 4px 8px; font-size: 12px; }

.hn-unread-dot {
    width: 8px; height: 8px;
    background: #2563eb;
    border-radius: 50%;
    margin-top: 6px;
    flex-shrink: 0;
}

.empty-notif {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}
.empty-notif i { font-size: 56px; margin-bottom: 12px; display: block; }
</style>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">

    <div class="col-xl-4 col-md-4">
        <div class="hrms-notif-stat">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div style="font-size:12px;color:#6c757d;">Total</div>
                    <div style="font-size:28px;font-weight:800;line-height:1.2;margin:6px 0;" id="statTotal">
                        <?= $totalCount; ?>
                    </div>
                    <span class="badge bg-secondary">All Notifications</span>
                </div>
                <div class="icon" style="background:#6366f1;"><i class="bi bi-bell-fill"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-4">
        <div class="hrms-notif-stat">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div style="font-size:12px;color:#6c757d;">Unread</div>
                    <div style="font-size:28px;font-weight:800;color:#2563eb;line-height:1.2;margin:6px 0;" id="statUnread">
                        <?= $unreadCount; ?>
                    </div>
                    <span class="badge bg-primary">New</span>
                </div>
                <div class="icon bg-primary"><i class="bi bi-bell-fill"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-4">
        <div class="hrms-notif-stat">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div style="font-size:12px;color:#6c757d;">Read</div>
                    <div style="font-size:28px;font-weight:800;color:#9ca3af;line-height:1.2;margin:6px 0;" id="statRead">
                        <?= $readCount; ?>
                    </div>
                    <span class="badge bg-secondary">Viewed</span>
                </div>
                <div class="icon" style="background:#9ca3af;"><i class="bi bi-check2-all"></i></div>
            </div>
        </div>
    </div>

</div>

<!-- HEADER + ACTIONS -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0" style="font-weight:700;color:#1a3c5e;">
        <i class="bi bi-bell-fill me-2"></i>HRMS Notifications
        <?php if($unreadCount > 0){ ?>
        <span class="badge bg-primary ms-1" id="headerNewBadge" style="font-size:12px;"><?= $unreadCount; ?> new</span>
        <?php } ?>
    </h5>
    <div class="d-flex gap-2">
        <?php if($unreadCount > 0){ ?>
        <button class="btn btn-sm btn-outline-primary" id="btnMarkAllRead" onclick="hrmsMarkAllRead()" style="border-radius:8px;font-weight:600;">
            <i class="bi bi-check2-all me-1"></i>Mark All Read
        </button>
        <?php } ?>
        <?php if($readCount > 0){ ?>
        <button class="btn btn-sm btn-outline-danger" id="btnClearRead" onclick="hrmsClearRead()" style="border-radius:8px;font-weight:600;">
            <i class="bi bi-trash me-1"></i>Clear Read
        </button>
        <?php } ?>
    </div>
</div>

<!-- FILTER TABS -->
<div class="notif-filter-tabs">
    <button class="notif-filter-btn active" onclick="filterNotifs('all', this)">
        All <span class="filter-count" id="filterCountAll"><?= $totalCount; ?></span>
    </button>
    <?php
    $typeConfig = [
        'HRMS'        => ['icon'=>'bi-person-badge-fill','color'=>'#6366f1'],
        'Low Stock'   => ['icon'=>'bi-exclamation-triangle-fill','color'=>'#eab308'],
        'Approval'    => ['icon'=>'bi-check-circle-fill','color'=>'#3b82f6'],
        'Sales'       => ['icon'=>'bi-graph-up-arrow','color'=>'#10b981'],
        'System'      => ['icon'=>'bi-gear-fill','color'=>'#6b7280'],
    ];
    foreach($typeConfig as $type => $cfg){
        $count = $typeCounts[$type] ?? 0;
    ?>
    <button class="notif-filter-btn" onclick="filterNotifs('<?= $type; ?>', this)">
        <i class="bi <?= $cfg['icon']; ?> me-1" style="font-size:11px;"></i><?= $type; ?>
        <span class="filter-count" id="filterCount_<?= preg_replace('/\s+/', '', $type); ?>"><?= $count; ?></span>
    </button>
    <?php } ?>
</div>

<!-- NOTIFICATION LIST -->
<div class="hrms-notif-card">

    <?php if($totalCount == 0){ ?>
    <div class="empty-notif">
        <i class="bi bi-bell-slash"></i>
        <h6>No Notifications Yet</h6>
        <p>HRMS notifications will appear here as you manage jobs, employees, and payroll.</p>
    </div>

    <?php } else {
        foreach($notifList as $notif){
            $isUnread = $notif['is_read'] == 0;

            // Icon & color per type
            $cfg = $typeConfig[$notif['type']] ?? ['icon'=>'bi-info-circle-fill','color'=>'#6b7280'];
            $timeAgo = hrmsTimeAgo($notif['created_at']);
    ?>

    <div class="hrms-notif-item <?= $isUnread ? 'unread' : ''; ?>"
         id="hn-<?= $notif['notification_id']; ?>"
         data-type="<?= htmlspecialchars($notif['type']); ?>">

        <div class="hn-icon" style="background:<?= $cfg['color']; ?>">
            <i class="bi <?= $cfg['icon']; ?>"></i>
        </div>

        <div class="hn-body">
            <div class="hn-title"><?= htmlspecialchars($notif['title']); ?></div>
            <div class="hn-message"><?= htmlspecialchars($notif['message']); ?></div>
            <div class="hn-meta">
                <span class="hn-type-badge" style="background:<?= $cfg['color']; ?>">
                    <?= $notif['type']; ?>
                </span>
                <span><i class="bi bi-clock me-1"></i><?= $timeAgo; ?></span>
            </div>
        </div>

        <div class="hn-actions">
            <?php if($isUnread){ ?>
            <button class="btn btn-sm btn-outline-primary" title="Mark as Read"
                    onclick="hrmsMarkRead(<?= $notif['notification_id']; ?>)">
                <i class="bi bi-check2"></i>
            </button>
            <?php } ?>
            <button class="btn btn-sm btn-outline-danger" title="Delete"
                    onclick="hrmsDeleteNotif(<?= $notif['notification_id']; ?>)">
                <i class="bi bi-trash"></i>
            </button>
        </div>

        <?php if($isUnread){ ?>
        <div class="hn-unread-dot"></div>
        <?php } ?>

    </div>

    <?php } } ?>

</div>

<!--=========================================================
    JAVASCRIPT
==========================================================-->
<script>

/*====================================================
    FILTER TABS
====================================================*/
function filterNotifs(type, btn){
    $('.notif-filter-btn').removeClass('active');
    $(btn).addClass('active');

    if(type === 'all'){
        $('.hrms-notif-item').show();
    } else {
        $('.hrms-notif-item').hide();
        $('.hrms-notif-item[data-type="' + type + '"]').show();
    }
}

/*====================================================
    MARK SINGLE AS READ
====================================================*/
function hrmsMarkRead(id){
    $.post('hrms_notifications.php', {
        action: 'mark_read',
        notification_id: id
    }, function(response){
        if(response.trim() == 'success'){
            let item = $('#hn-' + id);
            item.removeClass('unread');
            item.find('.hn-unread-dot').remove();
            item.find('.btn-outline-primary').remove();

            // Live-update the counters instead of waiting for a full reload
            const newUnread = Math.max(0, (parseInt($('#statUnread').text()) || 0) - 1);
            const newRead   = (parseInt($('#statRead').text()) || 0) + 1;
            $('#statUnread').text(newUnread);
            $('#statRead').text(newRead);

            if(newUnread === 0){
                $('#headerNewBadge').remove();
                $('#btnMarkAllRead').remove();
            } else {
                $('#headerNewBadge').text(newUnread + ' new');
            }
            if(newRead > 0 && $('#btnClearRead').length === 0){
                $('#btnMarkAllRead').after(
                    '<button class="btn btn-sm btn-outline-danger" id="btnClearRead" onclick="hrmsClearRead()" style="border-radius:8px;font-weight:600;"><i class="bi bi-trash me-1"></i>Clear Read</button>'
                );
            }
        }
    });
}

/*====================================================
    MARK ALL AS READ
====================================================*/
function hrmsMarkAllRead(){
    Swal.fire({
        title: 'Mark all notifications as read?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        confirmButtonText: 'Yes, Mark All Read',
        cancelButtonText: 'Cancel'
    }).then(result => {
        if(!result.isConfirmed) return;
        $.post('hrms_notifications.php', {
            action: 'mark_all_read'
        }, function(response){
            if(response.trim() == 'success'){
                Swal.fire({
                    icon: 'success',
                    title: 'All Marked as Read!',
                    showConfirmButton: false,
                    timer: 1200
                });
                setTimeout(() => loadPage('hrms_notifications.php'), 1200);
            }
        });
    });
}

/*====================================================
    DELETE SINGLE
====================================================*/
function hrmsDeleteNotif(id){
    Swal.fire({
        title: 'Delete this notification?',
        text: 'This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel'
    }).then(result => {
        if(!result.isConfirmed) return;
        const item = $('#hn-' + id);
        const wasUnread = item.hasClass('unread');
        const type = item.data('type');

        $.post('hrms_notifications.php', {
            action: 'delete',
            notification_id: id
        }, function(response){
            if(response.trim() == 'success'){
                item.fadeOut(300, function(){ $(this).remove(); });

                $('#statTotal').text(Math.max(0, (parseInt($('#statTotal').text()) || 0) - 1));
                if(wasUnread){
                    const newUnread = Math.max(0, (parseInt($('#statUnread').text()) || 0) - 1);
                    $('#statUnread').text(newUnread);
                    if(newUnread === 0){ $('#headerNewBadge').remove(); $('#btnMarkAllRead').remove(); }
                    else { $('#headerNewBadge').text(newUnread + ' new'); }
                } else {
                    $('#statRead').text(Math.max(0, (parseInt($('#statRead').text()) || 0) - 1));
                }
                $('#filterCountAll').text(Math.max(0, (parseInt($('#filterCountAll').text()) || 0) - 1));
                const typeEl = $('#filterCount_' + type.replace(/\s+/g, ''));
                typeEl.text(Math.max(0, (parseInt(typeEl.text()) || 0) - 1));
            }
        });
    });
}

/*====================================================
    CLEAR ALL READ
====================================================*/
function hrmsClearRead(){
    Swal.fire({
        title: 'Clear all read notifications?',
        text: 'This will remove all notifications you have already read.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Yes, Clear',
        cancelButtonText: 'Cancel'
    }).then(result => {
        if(result.isConfirmed){
            $.post('hrms_notifications.php', {
                action: 'delete_read'
            }, function(response){
                if(response.trim() == 'success'){
                    Swal.fire({
                        icon: 'success',
                        title: 'Cleared!',
                        showConfirmButton: false,
                        timer: 1200
                    });
                    setTimeout(() => loadPage('hrms_notifications.php'), 1200);
                }
            });
        }
    });
}

</script>
