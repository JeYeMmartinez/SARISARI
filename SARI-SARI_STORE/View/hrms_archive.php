<?php
require_once '../Model/database.php';

/*=========================================================
    AJAX ACTIONS
==========================================================*/

// Clear all logs
if(isset($_POST['action']) && $_POST['action'] == 'clear_all'){
    mysqli_query($conn, "DELETE FROM audit_logs");
    ob_clean();
    echo 'success';
    exit();
}

/*=========================================================
    FETCH DATA
==========================================================*/

$logs = mysqli_query($conn,"
    SELECT al.*, u.full_name, u.role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    ORDER BY al.created_at DESC
");

$logList = [];
while($row = mysqli_fetch_assoc($logs)){
    $logList[] = $row;
}

$totalLogs = count($logList);

// Count by action
$actionCounts = [];
$moduleCounts = [];

function getModuleFromTable($table_name){
    $hrms_tables = ['positions', 'applicants', 'employees', 'attendance', 'leave_requests', 'payroll', 'payroll_periods', 'departments'];
    if(in_array($table_name, $hrms_tables)){
        return 'HRMS';
    }
    return 'POS';
}

foreach($logList as $log){
    $a = $log['action'];
    $m = getModuleFromTable($log['table_name']);
    $actionCounts[$a] = ($actionCounts[$a] ?? 0) + 1;
    $moduleCounts[$m] = ($moduleCounts[$m] ?? 0) + 1;
}
?>

<style>
/*====================================================
    HRMS ARCHIVE STYLES
====================================================*/
.archive-stat {
    background: white;
    border-radius: 14px;
    padding: 16px 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
    text-align: center;
    height: 100%;
    transition: transform .2s, box-shadow .2s;
}
.archive-stat:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,.1);
}
.archive-stat .stat-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 18px; color: white; margin-bottom: 8px;
}
.archive-stat h4 { font-weight: 800; margin-bottom: 2px; }
.archive-stat small { font-size: 11px; color: #6b7280; font-weight: 600; }

.archive-card {
    background: white;
    border-radius: 14px;
    padding: 22px 24px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
    margin-bottom: 22px;
}

.action-badge {
    font-size: 11px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    letter-spacing: .3px;
}
.badge-create  { background: #d1fae5; color: #065f46; }
.badge-update  { background: #fef3c7; color: #92400e; }
.badge-delete  { background: #fee2e2; color: #991b1b; }
.badge-status  { background: #dbeafe; color: #1e40af; }
.badge-approve { background: #d1fae5; color: #065f46; }
.badge-reject  { background: #fee2e2; color: #991b1b; }
.badge-login   { background: #e0f2fe; color: #0369a1; }
.badge-logout  { background: #f3f4f6; color: #374151; }
.badge-void    { background: #ffedd5; color: #c2410c; }

.module-badge {
    font-size: 10px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 6px;
}
.badge-hrms { background: #e0e7ff; color: #3730a3; }
.badge-pos  { background: #fef3c7; color: #92400e; }

.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    align-items: center;
}
.filter-bar select {
    font-size: 12px;
    border-radius: 8px;
    padding: 6px 12px;
    border: 1px solid #e5e7eb;
}

.empty-archive {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}
.empty-archive i { font-size: 56px; margin-bottom: 12px; display: block; }

/* Detail modal */
.detail-row { margin-bottom: 12px; }
.detail-row .label {
    font-size: 11px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 2px;
}
.detail-row .value {
    font-size: 14px;
    font-weight: 500;
    color: #1f2937;
}

.modal-header-archive {
    background: linear-gradient(135deg, #1a3c5e 0%, #6366f1 100%);
    color: white;
    border-radius: 8px 8px 0 0;
}
.modal-header-archive .btn-close {
    filter: brightness(0) invert(1);
}
</style>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <?php
    $actionConfig = [
        'Create'        => ['color'=>'#059669','icon'=>'bi-plus-circle-fill'],
        'Update'        => ['color'=>'#d97706','icon'=>'bi-pencil-fill'],
        'Delete'        => ['color'=>'#dc2626','icon'=>'bi-trash-fill'],
        'Login'         => ['color'=>'#0284c7','icon'=>'bi-box-arrow-in-right'],
        'Logout'        => ['color'=>'#4b5563','icon'=>'bi-box-arrow-right'],
        'Void'          => ['color'=>'#ea580c','icon'=>'bi-slash-circle-fill'],
    ];
    foreach($actionConfig as $action => $cfg){
        $count = $actionCounts[$action] ?? 0;
    ?>
    <div class="col-lg-2 col-md-4 col-6">
        <div class="archive-stat">
            <div class="stat-icon" style="background:<?= $cfg['color']; ?>">
                <i class="bi <?= $cfg['icon']; ?>"></i>
            </div>
            <h4><?= $count; ?></h4>
            <small><?= $action; ?></small>
        </div>
    </div>
    <?php } ?>
</div>

<!-- HEADER + ACTIONS -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0" style="font-weight:700;color:#1a3c5e;">
        <i class="bi bi-archive-fill me-2"></i>Universal Activity Archive
        <span class="badge bg-secondary ms-1" style="font-size:12px;"><?= $totalLogs; ?> records</span>
    </h5>
    <?php if($totalLogs > 0){ ?>
    <button class="btn btn-sm btn-outline-danger" onclick="clearArchive()" style="border-radius:8px;font-weight:600;">
        <i class="bi bi-trash me-1"></i>Clear All Logs
    </button>
    <?php } ?>
</div>

<!-- FILTER BAR -->
<?php if($totalLogs > 0){ ?>
<div class="filter-bar">
    <span style="font-size:12px;font-weight:600;color:#6b7280;">Filter:</span>
    <select id="filterModule" onchange="applyArchiveFilters()">
        <option value="">All Modules</option>
        <option value="HRMS">HRMS (<?= $moduleCounts['HRMS'] ?? 0; ?>)</option>
        <option value="POS">POS (<?= $moduleCounts['POS'] ?? 0; ?>)</option>
    </select>
    <select id="filterAction" onchange="applyArchiveFilters()">
        <option value="">All Actions</option>
        <?php foreach($actionConfig as $action => $cfg){ ?>
        <option value="<?= $action; ?>"><?= $action; ?> (<?= $actionCounts[$action] ?? 0; ?>)</option>
        <?php } ?>
    </select>
</div>
<?php } ?>

<!-- LOGS TABLE -->
<div class="archive-card">

    <?php if($totalLogs == 0){ ?>
    <div class="empty-archive">
        <i class="bi bi-archive"></i>
        <h6>No Activity Logs Yet</h6>
        <p>Activity will be tracked here as users interact with the system.</p>
    </div>

    <?php } else { ?>
    <div class="table-responsive">
        <table class="table table-hover" id="archiveTable" style="width:100%;">
            <thead>
                <tr style="font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;">
                    <th>#</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>Table</th>
                    <th>Description</th>
                    <th>Date & Time</th>
                    <th class="text-center">Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php $n=1; foreach($logList as $log){
                    $mod = getModuleFromTable($log['table_name']);
                    
                    // Action badge class
                    $badgeClass = 'badge-update';
                    if($log['action'] == 'Create') $badgeClass = 'badge-create';
                    if($log['action'] == 'Delete') $badgeClass = 'badge-delete';
                    if($log['action'] == 'Login')  $badgeClass = 'badge-login';
                    if($log['action'] == 'Logout') $badgeClass = 'badge-logout';
                    if($log['action'] == 'Void')   $badgeClass = 'badge-void';
                ?>
                <tr data-module="<?= $mod; ?>"
                    data-action="<?= htmlspecialchars($log['action']); ?>">
                    <td style="font-weight:600;color:#6b7280;font-size:13px;"><?= $n++; ?></td>
                    <td>
                        <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($log['full_name'] ?? 'System'); ?></div>
                        <span class="badge <?= ($log['role'] ?? '') == 'Admin' ? 'bg-success' : 'bg-primary'; ?>" style="font-size:9px;">
                            <?= $log['role'] ?? 'N/A'; ?>
                        </span>
                    </td>
                    <td><span class="action-badge <?= $badgeClass; ?>"><?= $log['action']; ?></span></td>
                    <td><span class="module-badge <?= $mod == 'HRMS' ? 'badge-hrms' : 'badge-pos'; ?>"><?= $mod; ?></span></td>
                    <td><code><?= htmlspecialchars($log['table_name']); ?></code></td>
                    <td style="font-size:13px;color:#374151;max-width:320px;">
                        <?= htmlspecialchars($log['description']); ?>
                    </td>
                    <td style="font-size:12px;color:#6b7280;white-space:nowrap;">
                        <?= date("M d, Y", strtotime($log['created_at'])); ?><br>
                        <small><?= date("h:i A", strtotime($log['created_at'])); ?></small>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary" style="border-radius:8px;"
                                onclick='viewLogDetail(<?= json_encode(array_merge($log, ["module" => $mod])); ?>)'>
                            <i class="bi bi-eye-fill"></i>
                        </button>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php } ?>

</div>

<!-- ============================================================
     LOG DETAIL MODAL
============================================================= -->
<div class="modal fade" id="logDetailModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content" style="border:none;border-radius:12px;overflow:hidden;">
            <div class="modal-header modal-header-archive">
                <h5 class="modal-title">
                    <i class="bi bi-clock-history me-2"></i>Activity Detail
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:24px;">
                <div class="row">
                    <div class="col-md-6">
                        <div class="detail-row">
                            <div class="label">User</div>
                            <div class="value" id="detailUser"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-row">
                            <div class="label">Date & Time</div>
                            <div class="value" id="detailDate"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-row">
                            <div class="label">Action</div>
                            <div class="value" id="detailAction"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-row">
                            <div class="label">Module</div>
                            <div class="value" id="detailModule"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-row">
                            <div class="label">Table Name</div>
                            <div class="value" id="detailTable"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-row">
                            <div class="label">Record ID</div>
                            <div class="value" id="detailRecordId"></div>
                        </div>
                    </div>
                </div>

                <hr style="border-color:#e5e7eb; margin: 10px 0 15px 0;">

                <div class="detail-row">
                    <div class="label">Log Description</div>
                    <div id="detailDescription" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; font-size:13.5px; color:#334155; line-height:1.6; white-space:pre-wrap;">
                        —
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:14px 24px;">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal"
                        style="border-radius:8px;font-weight:600;">Close</button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    JAVASCRIPT
==========================================================-->
<script>

/*====================================================
    INITIALIZE DATATABLE
====================================================*/
$(document).ready(function(){
    if($.fn.DataTable && $('#archiveTable').length){
        if(!$.fn.DataTable.isDataTable('#archiveTable')){
            window.archiveDT = $('#archiveTable').DataTable({
                responsive: true,
                pageLength: 15,
                lengthChange: false,
                ordering: true,
                searching: true,
                order: [[6, 'desc']],
                columnDefs: [
                    { orderable: false, targets: [7] }
                ]
            });
        }
    }
});

/*====================================================
    CUSTOM FILTERS (Module + Action dropdowns)
====================================================*/
function applyArchiveFilters(){
    if(window.archiveDT){
        window.archiveDT.columns(3).search($('#filterModule').val());
        window.archiveDT.columns(2).search($('#filterAction').val());
        window.archiveDT.draw();
    }
}

/*====================================================
    VIEW LOG DETAIL
====================================================*/
function viewLogDetail(log){
    $('#detailUser').text(log.full_name || 'System');
    
    // Action badge style in details
    let badgeClass = 'badge-update';
    if(log.action == 'Create') badgeClass = 'badge-create';
    if(log.action == 'Delete') badgeClass = 'badge-delete';
    if(log.action == 'Login')  badgeClass = 'badge-login';
    if(log.action == 'Logout') badgeClass = 'badge-logout';
    if(log.action == 'Void')   badgeClass = 'badge-void';
    
    $('#detailAction').html('<span class="action-badge ' + badgeClass + '">' + log.action + '</span>');
    $('#detailModule').html('<span class="module-badge ' + (log.module == 'HRMS' ? 'badge-hrms' : 'badge-pos') + '">' + log.module + '</span>');
    $('#detailTable').html('<code>' + (log.table_name || '—') + '</code>');
    $('#detailRecordId').text(log.record_id || '—');
    $('#detailDescription').text(log.description || '—');

    let d = new Date(log.created_at);
    $('#detailDate').text(d.toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' }) + ' — ' + d.toLocaleTimeString());

    new bootstrap.Modal(document.getElementById('logDetailModal')).show();
}

/*====================================================
    CLEAR ALL LOGS
====================================================*/
function clearArchive(){
    Swal.fire({
        title: 'Clear all activity logs?',
        html: 'This will permanently remove <strong>all</strong> universal activity records.<br><small class="text-muted">This action cannot be undone.</small>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Yes, Clear All',
        cancelButtonText: 'Cancel'
    }).then(result => {
        if(result.isConfirmed){
            $.post('hrms_archive.php', { action: 'clear_all' }, function(response){
                if(response.trim() == 'success'){
                    Swal.fire({
                        icon: 'success',
                        title: 'Logs Cleared!',
                        showConfirmButton: false,
                        timer: 1200
                    });
                    setTimeout(() => loadPage('hrms_archive.php'), 1200);
                }
            });
        }
    });
}

</script>
