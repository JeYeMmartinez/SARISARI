<?php
require_once '../Model/database.php';
require_once '../Controller/HRMSController.php';

if(session_status() === PHP_SESSION_NONE){
    session_start();
}
$admin_id = $_SESSION['user_id'] ?? 1;

$hrmsController = new HRMSController($conn);

define('LEAVE_UPLOAD_DIR', __DIR__ . '/uploads/leave_docs/');

/*=========================================================
    ACTIONS (POST / AJAX)
==========================================================*/

// ARCHIVE LEAVE
if(isset($_POST['action']) && $_POST['action'] === 'archive_leave'){
    $result = $hrmsController->archiveLeave($_POST['leave_id'], $_POST['reason'] ?? '', $admin_id);
    ob_clean(); echo $result; exit;
}

// RESTORE LEAVE
if(isset($_POST['action']) && $_POST['action'] === 'restore_leave'){
    $result = $hrmsController->restoreLeave($_POST['archive_id'], $admin_id);
    ob_clean(); echo $result; exit;
}

// CREATE LEAVE
if(isset($_POST['action']) && $_POST['action'] == 'create'){
    $result = $hrmsController->createLeave($_POST, $_FILES, $admin_id, LEAVE_UPLOAD_DIR);
    ob_clean(); echo $result; exit;
}

// UPDATE STATUS (Approve / Reject)
if(isset($_POST['action']) && $_POST['action'] == 'update_status'){
    $result = $hrmsController->updateLeaveStatus($_POST['leave_id'], $_POST['status'], $_POST['remarks'] ?? '', $admin_id);
    ob_clean(); echo $result; exit;
}

// DELETE LEAVE
if(isset($_POST['action']) && $_POST['action'] == 'delete'){
    $result = $hrmsController->deleteLeave($_POST['leave_id'], $admin_id);
    ob_clean(); echo $result; exit;
}

// EDIT LEAVE
if(isset($_POST['action']) && $_POST['action'] == 'edit'){
    $result = $hrmsController->updateLeave($_POST, $_FILES, $admin_id, LEAVE_UPLOAD_DIR);
    ob_clean(); echo $result; exit;
}

/*=========================================================
    FETCH DATA
==========================================================*/

$empResult = mysqli_query($conn,
    "SELECT employee_id, full_name, employee_no, email
     FROM employees WHERE status='Active' ORDER BY full_name ASC"
);
$employeeList = [];
while($e = mysqli_fetch_assoc($empResult)) $employeeList[] = $e;

$leavesResult = $hrmsController->getLeaveRequestsList();
$leaveList = [];
while($r = mysqli_fetch_assoc($leavesResult)) $leaveList[] = $r;

$totalLeaves   = count($leaveList);
$pendingCount  = 0; $approvedCount = 0; $rejectedCount = 0;
foreach($leaveList as $l){
    if($l['status'] === 'Pending')  $pendingCount++;
    if($l['status'] === 'Approved') $approvedCount++;
    if($l['status'] === 'Rejected') $rejectedCount++;
}
?>

<style>
.page-card {
    background: white; border-radius: 14px;
    padding: 22px 24px; box-shadow: 0 2px 10px rgba(0,0,0,.06); margin-bottom: 22px;
}
.stat-card {
    background: white; border-radius: 14px; padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06); height: 100%;
    display: flex; justify-content: space-between; align-items: flex-start;
}
.stat-icon {
    width: 44px; height: 44px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; color: white; flex-shrink: 0;
}
.stat-label { font-size: 11px; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
.stat-value { font-size: 26px; font-weight: 800; line-height: 1.2; margin-top: 4px; }

.lv-badge { font-size: 11px; font-weight: 600; padding: 4px 11px; border-radius: 20px; display: inline-block; }
.lv-pending  { background: #fef9c3; color: #854d0e; }
.lv-approved { background: #d1fae5; color: #065f46; }
.lv-rejected { background: #fee2e2; color: #991b1b; }

.lv-type {
    font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 12px;
    background: #eff6ff; color: #1d4ed8; display: inline-block;
}
.days-pill {
    background: #f0f4ff; color: #1d4ed8; font-weight: 700;
    border-radius: 20px; padding: 2px 10px; font-size: 12px;
}
.modal-header-primary { background: linear-gradient(135deg, #1a3c5e, #2563eb); color: white; }
.modal-header-primary .btn-close { filter: invert(1); }
.action-btn-group { display: flex; gap: 4px; justify-content: center; }
</style>

<!-- ===== PAGE HEADER ===== -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color:#1a3c5e;">
            <i class="bi bi-calendar2-check-fill me-2" style="color:#2563eb;"></i>Leave Management
        </h4>
        <small class="text-muted">HR files and tracks employee leave requests submitted via physical form</small>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" onclick="openArchiveLeaveModal()">
            <i class="bi bi-archive-fill me-1"></i>Archive
            <?php
            $archLvCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM leave_requests_archive"))['c'];
            if ($archLvCount > 0)
                echo '<span class="badge bg-danger ms-1">' . $archLvCount . '</span>';
            ?>
        </button>
        <button class="btn btn-outline-danger" onclick="openRejectedLeaveModal()">
            <i class="bi bi-x-circle-fill me-1"></i>Rejected
            <?php
            $rejLvCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM leave_requests WHERE status = 'Rejected'"))['c'];
            if ($rejLvCount > 0)
                echo '<span class="badge bg-danger ms-1">' . $rejLvCount . '</span>';
            ?>
        </button>
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="bi bi-plus-lg me-1"></i> File Leave Request
        </button>
    </div>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="row g-3 mb-4 animate__animated animate__fadeIn">
    <div class="col-md-3">
        <div class="stat-card border-start border-primary border-4">
            <div>
                <div class="stat-label">Total Records</div>
                <div class="stat-value"><?= $totalLeaves; ?></div>
            </div>
            <div class="stat-icon bg-primary"><i class="bi bi-calendar2-fill"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card border-start border-warning border-4">
            <div>
                <div class="stat-label">Pending</div>
                <div class="stat-value text-warning"><?= $pendingCount; ?></div>
            </div>
            <div class="stat-icon" style="background:#f59e0b;"><i class="bi bi-hourglass-split"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card border-start border-success border-4">
            <div>
                <div class="stat-label">Approved</div>
                <div class="stat-value text-success"><?= $approvedCount; ?></div>
            </div>
            <div class="stat-icon bg-success"><i class="bi bi-check-circle-fill"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card border-start border-danger border-4">
            <div>
                <div class="stat-label">Rejected</div>
                <div class="stat-value text-danger"><?= $rejectedCount; ?></div>
            </div>
            <div class="stat-icon bg-danger"><i class="bi bi-x-circle-fill"></i></div>
        </div>
    </div>
</div>

<!-- ===== LEAVE RECORDS TABLE ===== -->
<div class="page-card animate__animated animate__fadeIn">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0" style="color:#1a3c5e;">
            <i class="bi bi-table me-2"></i>Leave Records
        </h5>
        <span class="text-muted" style="font-size:12px;">
            All employee leave requests filed by HR
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle datatable w-100" id="leavesTable">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Date From</th>
                    <th>Date To</th>
                    <th>Days</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Filed On</th>
                    <th style="text-align:center; width:130px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($leaveList as $i => $lr) {
                    $badgeClass = match($lr['status']){
                        'Approved' => 'lv-approved',
                        'Rejected' => 'lv-rejected',
                        default    => 'lv-pending',
                    };
                ?>
                <tr>
                    <td class="text-muted fw-semibold"><?= $i + 1; ?></td>
                    <td>
                        <div class="fw-semibold text-dark"><?= htmlspecialchars($lr['full_name']); ?></div>
                        <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($lr['employee_no']); ?></div>
                    </td>
                    <td><span class="lv-type"><?= htmlspecialchars($lr['leave_type']); ?></span></td>
                    <td><?= date('M d, Y', strtotime($lr['date_from'])); ?></td>
                    <td><?= date('M d, Y', strtotime($lr['date_to'])); ?></td>
                    <td><span class="days-pill"><?= $lr['days']; ?> day<?= $lr['days'] > 1 ? 's' : ''; ?></span></td>
                    <td style="max-width:180px;">
                        <span style="font-size:12px; color:#374151;" title="<?= htmlspecialchars($lr['reason']); ?>">
                            <?= htmlspecialchars(strlen($lr['reason']) > 45 ? substr($lr['reason'],0,45).'…' : ($lr['reason'] ?: '—')); ?>
                        </span>
                    </td>
                    <td><span class="lv-badge <?= $badgeClass; ?>"><?= $lr['status']; ?></span></td>
                    <td style="font-size:12px; color:#6c757d;"><?= date('M d, Y', strtotime($lr['created_at'])); ?></td>
                    <td>
                        <div class="action-btn-group">
                            <button class="btn btn-sm btn-outline-primary"
                                    onclick="viewLeave(<?= htmlspecialchars(json_encode($lr)); ?>)"
                                    title="View Details">
                                <i class="bi bi-eye-fill"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning"
                                    onclick="openEditModal(<?= htmlspecialchars(json_encode($lr)); ?>)"
                                    title="Edit">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                    onclick="deleteLeave(<?= $lr['leave_id']; ?>, '<?= addslashes($lr['full_name']); ?>')"
                                    title="Remove">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<!--=========================================================
    FILE LEAVE MODAL (ADD)
==========================================================-->
<div class="modal fade" id="addLeaveModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header modal-header-primary">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-calendar2-plus me-2"></i>File Leave Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-4" style="font-size:13px;">
                    <i class="bi bi-info-circle-fill"></i>
                    HR is encoding this request on behalf of the employee who submitted a physical form.
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Employee <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_employee_id" required>
                            <option value="">— Select Employee —</option>
                            <?php foreach($employeeList as $e){ ?>
                            <option value="<?= $e['employee_id']; ?>">
                                <?= htmlspecialchars($e['full_name']); ?> (<?= htmlspecialchars($e['employee_no']); ?>)
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Leave Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_leave_type" required>
                            <option value="">— Select Type —</option>
                            <option value="Sick Leave">Sick Leave</option>
                            <option value="Vacation Leave">Vacation Leave</option>
                            <option value="Emergency Leave">Emergency Leave</option>
                            <option value="Maternity">Maternity Leave</option>
                            <option value="Paternity">Paternity Leave</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Initial Status</label>
                        <select class="form-select" id="add_status">
                            <option value="Pending">Pending (for review)</option>
                            <option value="Approved">Approved immediately</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Date From <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="add_date_from" oninput="calcDays('add')">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Date To <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="add_date_to" oninput="calcDays('add')">
                    </div>
                    <div class="col-md-2 d-flex flex-column justify-content-end">
                        <label class="form-label fw-semibold text-muted" style="font-size:11px;">DAYS</label>
                        <div class="form-control text-center fw-bold text-primary" id="add_days_display"
                             style="background:#f0f4ff; border-color:#c7d7f8;">—</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Reason / Notes</label>
                        <textarea class="form-control" id="add_reason" rows="3"
                                  placeholder="Reason stated in the physical letter…"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Supporting Document <span class="text-muted fw-normal">(medical cert, etc. — optional)</span></label>
                        <input type="file" class="form-control" id="add_document" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary px-4" onclick="submitAddLeave()">
                    <i class="bi bi-check-lg me-1"></i>File Request
                </button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    EDIT LEAVE MODAL
==========================================================-->
<div class="modal fade" id="editLeaveModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background:#1a3c5e; color:white;">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-pencil-square me-2"></i>Edit Leave Record
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="edit_leave_id">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Employee <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_employee_id">
                            <?php foreach($employeeList as $e){ ?>
                            <option value="<?= $e['employee_id']; ?>">
                                <?= htmlspecialchars($e['full_name']); ?> (<?= htmlspecialchars($e['employee_no']); ?>)
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Leave Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_leave_type">
                            <option value="Sick Leave">Sick Leave</option>
                            <option value="Vacation Leave">Vacation Leave</option>
                            <option value="Emergency Leave">Emergency Leave</option>
                            <option value="Maternity">Maternity Leave</option>
                            <option value="Paternity">Paternity Leave</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Status</label>
                        <select class="form-select" id="edit_status">
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Date From</label>
                        <input type="date" class="form-control" id="edit_date_from" oninput="calcDays('edit')">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Date To</label>
                        <input type="date" class="form-control" id="edit_date_to" oninput="calcDays('edit')">
                    </div>
                    <div class="col-md-2 d-flex flex-column justify-content-end">
                        <label class="form-label fw-semibold text-muted" style="font-size:11px;">DAYS</label>
                        <div class="form-control text-center fw-bold text-primary" id="edit_days_display"
                             style="background:#f0f4ff; border-color:#c7d7f8;">—</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Reason / Notes</label>
                        <textarea class="form-control" id="edit_reason" rows="3"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Supporting Document <span class="text-muted fw-normal">(leave blank to keep existing file)</span></label>
                        <input type="file" class="form-control" id="edit_document" accept=".pdf,.jpg,.jpeg,.png">
                        <div id="edit_document_current" class="mt-1" style="font-size:12px;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary px-4" onclick="submitEditLeave()">
                    <i class="bi bi-check-lg me-1"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    VIEW DETAILS MODAL
==========================================================-->
<div class="modal fade" id="viewLeaveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header modal-header-primary">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-eye-fill me-2"></i>Leave Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div style="background:#f8fafc; padding:20px 24px; border-bottom:1px solid #e5e7eb;">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:52px;height:52px;border-radius:50%;
                                    background:linear-gradient(135deg,#1a3c5e,#2563eb);
                                    color:white;font-size:20px;font-weight:800;
                                    display:flex;align-items:center;justify-content:center;" id="v_avatar">?</div>
                        <div>
                            <div class="fw-bold" style="font-size:16px;" id="v_name">—</div>
                            <div class="text-muted" style="font-size:12px;" id="v_empno">—</div>
                        </div>
                        <div class="ms-auto" id="v_status_badge">—</div>
                    </div>
                </div>
                <div class="p-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Leave Type</div>
                            <div class="fw-semibold mt-1" id="v_type">—</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Duration</div>
                            <div class="fw-semibold mt-1" id="v_days">—</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Date From</div>
                            <div class="fw-semibold mt-1" id="v_from">—</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Date To</div>
                            <div class="fw-semibold mt-1" id="v_to">—</div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Reason / Notes</div>
                            <div class="mt-1 p-3 rounded" style="background:#f8fafc;font-size:13px;min-height:50px;" id="v_reason">—</div>
                        </div>
                        <div class="col-12" id="v_document_wrap" style="display:none;">
                            <div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Supporting Document</div>
                            <a href="#" id="v_document_link" target="_blank" class="mt-1 d-inline-flex align-items-center gap-1 fw-semibold" style="font-size:13px;">
                                <i class="bi bi-file-earmark-text"></i> View Document
                            </a>
                        </div>
                        <div class="col-6">
                            <div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Filed On</div>
                            <div class="fw-semibold mt-1" id="v_filed">—</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted" style="font-size:11px;font-weight:700;text-transform:uppercase;">Processed By</div>
                            <div class="fw-semibold mt-1" id="v_approver">—</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 d-flex gap-2">
                <button class="btn btn-success flex-fill" id="v_btn_approve" onclick="quickStatus('approve')">
                    <i class="bi bi-check-circle-fill me-1"></i>Approve
                </button>
                <button class="btn btn-danger flex-fill" id="v_btn_reject" onclick="quickStatus('reject')">
                    <i class="bi bi-x-circle-fill me-1"></i>Reject
                </button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ARCHIVE LEAVE MODAL -->
<div class="modal fade" id="archiveLeaveModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-archive-fill me-2"></i>Archived Leave Requests</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100 fs-7">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Employee</th>
                                <th>Leave Type</th>
                                <th>Date Range</th>
                                <th>Days</th>
                                <th>Archival Reason</th>
                                <th>Archived At</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $archLvQuery = mysqli_query($conn, "
                                SELECT a.*, e.full_name, e.employee_no 
                                FROM leave_requests_archive a 
                                LEFT JOIN employees e ON a.employee_id = e.employee_id 
                                ORDER BY a.archived_at DESC
                            ");
                            if (mysqli_num_rows($archLvQuery) > 0) {
                                while ($archRow = mysqli_fetch_assoc($archLvQuery)) {
                                    echo '<tr>';
                                    echo '<td><span class="badge bg-secondary font-monospace">#' . $archRow['archive_id'] . '</span></td>';
                                    echo '<td><div class="fw-bold">' . htmlspecialchars($archRow['full_name'] ?? 'N/A') . '</div><small class="text-muted">' . htmlspecialchars($archRow['employee_no'] ?? '') . '</small></td>';
                                    echo '<td><span class="lv-type">' . htmlspecialchars($archRow['leave_type']) . '</span></td>';
                                    echo '<td>' . date('M d, Y', strtotime($archRow['date_from'])) . ' - ' . date('M d, Y', strtotime($archRow['date_to'])) . '</td>';
                                    echo '<td><span class="days-pill">' . $archRow['days'] . ' day(s)</span></td>';
                                    echo '<td><span class="text-danger fw-semibold"><i class="bi bi-chat-left-quote me-1"></i>' . htmlspecialchars($archRow['archive_reason'] ?: 'No reason provided') . '</span></td>';
                                    echo '<td><small class="text-muted">' . date('M d, Y h:i A', strtotime($archRow['archived_at'])) . '</small></td>';
                                    echo '<td class="text-center"><button class="btn btn-sm btn-success" onclick="restoreLeaveRecord(' . $archRow['archive_id'] . ', \'' . addslashes($archRow['full_name']) . '\')"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</button></td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="8" class="text-center text-muted py-4"><i class="bi bi-inbox me-1"></i>No archived leave records found.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- REJECTED LEAVE MODAL -->
<div class="modal fade" id="rejectedLeaveModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-x-circle-fill me-2"></i>Rejected Leave Requests</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100 fs-7">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Employee</th>
                                <th>Leave Type</th>
                                <th>Dates</th>
                                <th>Days</th>
                                <th>Application Reason</th>
                                <th>Rejection Reasoning / Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rejLvQuery = mysqli_query($conn, "
                                SELECT lr.*, e.full_name, e.employee_no 
                                FROM leave_requests lr 
                                JOIN employees e ON lr.employee_id = e.employee_id 
                                WHERE lr.status = 'Rejected' 
                                ORDER BY lr.created_at DESC
                            ");
                            if (mysqli_num_rows($rejLvQuery) > 0) {
                                while ($rejRow = mysqli_fetch_assoc($rejLvQuery)) {
                                    echo '<tr>';
                                    echo '<td><span class="badge bg-danger font-monospace">#' . $rejRow['leave_id'] . '</span></td>';
                                    echo '<td><div class="fw-bold">' . htmlspecialchars($rejRow['full_name']) . '</div><small class="text-muted">' . htmlspecialchars($rejRow['employee_no']) . '</small></td>';
                                    echo '<td><span class="lv-type">' . htmlspecialchars($rejRow['leave_type']) . '</span></td>';
                                    echo '<td>' . date('M d, Y', strtotime($rejRow['date_from'])) . ' - ' . date('M d, Y', strtotime($rejRow['date_to'])) . '</td>';
                                    echo '<td><span class="days-pill">' . $rejRow['days'] . ' day(s)</span></td>';
                                    echo '<td>' . htmlspecialchars($rejRow['reason'] ?: '—') . '</td>';
                                    echo '<td><span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i>' . htmlspecialchars($rejRow['remarks'] ?: 'Rejected by HR Management') . '</span></td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-check-circle me-1"></i>No rejected leave requests found.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
/*====================================================
    DATATABLE INIT
====================================================*/
$(document).ready(function(){
    if($.fn.DataTable){
        $('#leavesTable').DataTable({
            destroy: true,
            pageLength: 15,
            order: [[8, 'desc']],
            columnDefs: [{ orderable: false, targets: [9] }],
            language: {
                emptyTable: 'No leave records found. Click "File Leave Request" to add one.',
                search: 'Search records:',
                lengthMenu: 'Show _MENU_ records'
            }
        });
    }
});

function clearBackdropHrms(){
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open').css('padding-right','');
}

/*====================================================
    DAY CALCULATOR
====================================================*/
function calcDays(prefix){
    const from    = document.getElementById(prefix+'_date_from').value;
    const to      = document.getElementById(prefix+'_date_to').value;
    const display = document.getElementById(prefix+'_days_display');
    if(from && to && to >= from){
        const diff = Math.round((new Date(to) - new Date(from)) / 86400000) + 1;
        display.textContent = diff + (diff === 1 ? ' day' : ' days');
    } else {
        display.textContent = '—';
    }
}

/*====================================================
    OPEN ADD MODAL
====================================================*/
function openAddModal(){
    ['add_employee_id','add_leave_type','add_date_from','add_date_to','add_reason','add_document'].forEach(id => {
        const el = document.getElementById(id);
        if(el) el.value = '';
    });
    const st = document.getElementById('add_status');
    if(st) st.value = 'Pending';
    const disp = document.getElementById('add_days_display');
    if(disp) disp.textContent = '—';
    const modalEl = document.getElementById('addLeaveModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }
}

/*====================================================
    SUBMIT ADD LEAVE
====================================================*/
function submitAddLeave(){
    const emp_id     = document.getElementById('add_employee_id').value;
    const leave_type = document.getElementById('add_leave_type').value;
    const date_from  = document.getElementById('add_date_from').value;
    const date_to    = document.getElementById('add_date_to').value;
    const status     = document.getElementById('add_status').value;
    const reason     = document.getElementById('add_reason').value;
    const docFile    = document.getElementById('add_document').files[0];

    if(!emp_id || !leave_type || !date_from || !date_to){
        Swal.fire('Missing Fields', 'Please fill in all required fields.', 'warning');
        return;
    }
    if(date_to < date_from){
        Swal.fire('Invalid Dates', '"Date To" cannot be before "Date From".', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'create');
    formData.append('employee_id', emp_id);
    formData.append('leave_type', leave_type);
    formData.append('date_from', date_from);
    formData.append('date_to', date_to);
    formData.append('status', status);
    formData.append('reason', reason);
    if(docFile) formData.append('document', docFile);

    $.ajax({
        url: 'hrms_leaves.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res){
            if(res.trim().startsWith('success')){
                bootstrap.Modal.getInstance(document.getElementById('addLeaveModal'))?.hide();
                Swal.fire({
                    icon: 'success', title: 'Leave Filed!',
                    text: 'The leave request has been recorded.', timer: 1500, showConfirmButton: false
                }).then(() => { clearBackdropHrms(); loadPage('hrms_leaves.php'); });
            } else {
                Swal.fire('Error', res, 'error');
            }
        }
    });
}

/*====================================================
    OPEN EDIT MODAL
====================================================*/
function openEditModal(lr){
    document.getElementById('edit_leave_id').value    = lr.leave_id;
    document.getElementById('edit_employee_id').value  = lr.employee_id;
    document.getElementById('edit_leave_type').value   = lr.leave_type;
    document.getElementById('edit_status').value       = lr.status;
    document.getElementById('edit_date_from').value    = lr.date_from;
    document.getElementById('edit_date_to').value      = lr.date_to;
    document.getElementById('edit_reason').value       = lr.reason || '';
    document.getElementById('edit_days_display').textContent =
        lr.days + (parseInt(lr.days) === 1 ? ' day' : ' days');
    document.getElementById('edit_document').value = '';
    document.getElementById('edit_document_current').innerHTML = lr.document
        ? `<a href="uploads/leave_docs/${lr.document}" target="_blank"><i class="bi bi-file-earmark-text"></i> Current file: ${lr.document}</a>`
        : '<span class="text-muted">No file uploaded yet</span>';
    const modalEl = document.getElementById('editLeaveModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }
}

/*====================================================
    SUBMIT EDIT LEAVE
====================================================*/
function submitEditLeave(){
    const leave_id   = document.getElementById('edit_leave_id').value;
    const emp_id     = document.getElementById('edit_employee_id').value;
    const leave_type = document.getElementById('edit_leave_type').value;
    const date_from  = document.getElementById('edit_date_from').value;
    const date_to    = document.getElementById('edit_date_to').value;
    const status     = document.getElementById('edit_status').value;
    const reason     = document.getElementById('edit_reason').value;
    const docFile    = document.getElementById('edit_document').files[0];

    if(!date_from || !date_to || date_to < date_from){
        Swal.fire('Invalid Dates', 'Please check the date range.', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'edit');
    formData.append('leave_id', leave_id);
    formData.append('employee_id', emp_id);
    formData.append('leave_type', leave_type);
    formData.append('date_from', date_from);
    formData.append('date_to', date_to);
    formData.append('status', status);
    formData.append('reason', reason);
    if(docFile) formData.append('document', docFile);

    $.ajax({
        url: 'hrms_leaves.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res){
            if(res.trim() === 'success'){
                bootstrap.Modal.getInstance(document.getElementById('editLeaveModal'))?.hide();
                Swal.fire({ icon:'success', title:'Updated!', timer:1500, showConfirmButton:false
                }).then(() => { clearBackdropHrms(); loadPage('hrms_leaves.php'); });
            } else {
                Swal.fire('Error', res, 'error');
            }
        }
    });
}

/*====================================================
    VIEW LEAVE DETAILS
====================================================*/
let _currentViewId = null;

function viewLeave(lr){
    _currentViewId = lr.leave_id;

    const words = (lr.full_name || '').replace(/[^a-zA-Z0-9\s]/g,'').split(' ');
    let initials = '';
    words.forEach(w => { if(initials.length < 2 && w.length) initials += w[0].toUpperCase(); });
    document.getElementById('v_avatar').textContent  = initials || '?';
    document.getElementById('v_name').textContent    = lr.full_name;
    document.getElementById('v_empno').textContent   = lr.employee_no;
    document.getElementById('v_type').textContent    = lr.leave_type;
    document.getElementById('v_days').textContent    = lr.days + (lr.days == 1 ? ' day' : ' days');
    document.getElementById('v_from').textContent    = formatDatePH(lr.date_from);
    document.getElementById('v_to').textContent      = formatDatePH(lr.date_to);
    document.getElementById('v_reason').textContent  = lr.reason || '(No reason provided)';
    document.getElementById('v_filed').textContent   = formatDatePH((lr.created_at||'').substring(0,10));
    document.getElementById('v_approver').textContent = lr.approved_by_name || '—';

    if(lr.document){
        document.getElementById('v_document_wrap').style.display = '';
        document.getElementById('v_document_link').href = 'uploads/leave_docs/' + lr.document;
    } else {
        document.getElementById('v_document_wrap').style.display = 'none';
    }

    const badgeMap = {
        Pending:  '<span class="lv-badge lv-pending">Pending</span>',
        Approved: '<span class="lv-badge lv-approved">Approved</span>',
        Rejected: '<span class="lv-badge lv-rejected">Rejected</span>',
    };
    document.getElementById('v_status_badge').innerHTML = badgeMap[lr.status] || lr.status;
    document.getElementById('v_btn_approve').style.display = (lr.status !== 'Approved') ? '' : 'none';
    document.getElementById('v_btn_reject').style.display  = (lr.status !== 'Rejected') ? '' : 'none';

    const modalEl = document.getElementById('viewLeaveModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }
}

function openArchiveLeaveModal() {
    const modalEl = document.getElementById('archiveLeaveModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }
}

function openRejectedLeaveModal() {
    const modalEl = document.getElementById('rejectedLeaveModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }
}

function archiveLeaveRecord(id, name) {
    Swal.fire({
        title: 'Archive Leave Record?',
        html: `
            <p class="text-muted mb-2" style="font-size:13px;">Select reason for archiving leave request for <strong>${name}</strong>:</p>
            <div class="text-start mb-2">
                <label class="form-label fw-bold text-dark" style="font-size:12px;">Archival Reason <span class="text-danger">*</span></label>
                <select id="archReasonLeave" class="form-select" style="font-size:13px;">
                    <option value="Leave Period Completed / Past Record Finalized">Leave Period Completed / Past Record Finalized</option>
                    <option value="Duplicate Request / Administrative Cleanup">Duplicate Request / Administrative Cleanup</option>
                    <option value="Leave Request Withdrawn by Employee">Leave Request Withdrawn by Employee</option>
                    <option value="Historical Audit / System Archival">Historical Audit / System Archival</option>
                </select>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#6c757d',
        confirmButtonText: 'Archive Record',
        preConfirm: () => {
            const r = document.getElementById('archReasonLeave').value;
            if (!r) { Swal.showValidationMessage('Please select a reason.'); return false; }
            return r;
        }
    }).then(result => {
        if (!result.isConfirmed) return;
        $.post('hrms_leaves.php', { action: 'archive_leave', leave_id: id, reason: result.value }, function (res) {
            if (res.trim() === 'success') {
                Swal.fire({ icon: 'success', title: 'Leave Archived!', timer: 1500, showConfirmButton: false })
                    .then(() => { clearBackdropHrms(); loadPage('hrms_leaves.php'); });
            } else {
                Swal.fire('Error', res, 'error');
            }
        });
    });
}

function restoreLeaveRecord(archiveId, name) {
    Swal.fire({
        title: 'Restore Leave Record?',
        html: `Restore leave request for <strong>${name}</strong> back to active records?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Yes, Restore'
    }).then(result => {
        if (!result.isConfirmed) return;
        $.post('hrms_leaves.php', { action: 'restore_leave', archive_id: archiveId }, function (res) {
            if (res.trim() === 'success') {
                Swal.fire({ icon: 'success', title: 'Restored!', timer: 1500, showConfirmButton: false })
                    .then(() => { clearBackdropHrms(); loadPage('hrms_leaves.php'); });
            } else {
                Swal.fire('Error', res, 'error');
            }
        });
    });
}

function quickStatus(action){
    const status = action === 'approve' ? 'Approved' : 'Rejected';
    const color  = action === 'approve' ? '#16a34a' : '#dc2626';

    const viewModalEl = document.getElementById('viewLeaveModal');
    if (viewModalEl) {
        const inst = bootstrap.Modal.getInstance(viewModalEl);
        if (inst) inst.hide();
    }
    clearBackdropHrms();

    if (action === 'reject') {
        Swal.fire({
            title: 'Reject Leave Request?',
            html: `
                <p class="text-muted mb-2" style="font-size:13px;">Select a reason for rejecting this leave application:</p>
                <div class="text-start mb-2">
                    <label class="form-label fw-bold text-dark" style="font-size:12px;">Rejection Reason <span class="text-danger">*</span></label>
                    <select id="quickRejReason" class="form-select" style="font-size:13px;">
                        <option value="Overlapping Leave Schedule / Insufficient Staff Coverage">Overlapping Leave Schedule / Insufficient Staff Coverage</option>
                        <option value="Peak Business Operations / Critical Workload">Peak Business Operations / Critical Workload</option>
                        <option value="Insufficient Leave Credits / Balance">Insufficient Leave Credits / Balance</option>
                        <option value="Short Notice / Untimely Submission">Short Notice / Untimely Submission</option>
                        <option value="Medical Certificate / Supporting Documents Missing">Medical Certificate / Supporting Documents Missing</option>
                        <option value="Unapproved / Invalid Leave Request">Unapproved / Invalid Leave Request</option>
                    </select>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: color,
            confirmButtonText: 'Reject Leave',
            preConfirm: () => {
                const r = document.getElementById('quickRejReason').value;
                if(!r) { Swal.showValidationMessage('Please select a rejection reason.'); return false; }
                return r;
            }
        }).then(result => {
            if(!result.isConfirmed) return;
            $.post('hrms_leaves.php', { action:'update_status', leave_id: _currentViewId, status: status, remarks: result.value }, function(res){
                if(res.trim() === 'success'){
                    Swal.fire({ icon:'success', title:'Leave Rejected!', timer:1500, showConfirmButton:false
                    }).then(() => { clearBackdropHrms(); loadPage('hrms_leaves.php'); });
                } else {
                    Swal.fire('Error', res, 'error');
                }
            });
        });
        return;
    }
    Swal.fire({
        title: `Approve this Leave?`,
        icon:  'question',
        showCancelButton: true,
        confirmButtonColor: color,
        confirmButtonText: `Yes, Approve it`
    }).then(result => {
        if(!result.isConfirmed) return;
        $.post('hrms_leaves.php', { action:'update_status', leave_id: _currentViewId, status: status }, function(res){
            if(res.trim() === 'success'){
                Swal.fire({ icon:'success', title:`Leave Approved!`, timer:1500, showConfirmButton:false
                }).then(() => { clearBackdropHrms(); loadPage('hrms_leaves.php'); });
            } else {
                Swal.fire('Error', res, 'error');
            }
        });
    });
}

function deleteLeave(id, name){
    Swal.fire({
        title: 'Remove Leave Record?',
        html: `
            <p class="text-muted mb-2" style="font-size:13px;">Select reason for removing leave record for <strong>${name}</strong>:</p>
            <div class="text-start mb-2">
                <label class="form-label fw-bold text-dark" style="font-size:12px;">Reason for Removal <span class="text-danger">*</span></label>
                <select id="removeLeaveReasonSelect" class="form-select" style="font-size:13px;">
                    <option value="Employee Cancelled Leave Request">Employee Cancelled Leave Request</option>
                    <option value="Filed under Incorrect Leave Type">Filed under Incorrect Leave Type</option>
                    <option value="Duplicate Leave Request">Duplicate Leave Request</option>
                    <option value="Leave Date Schedule Adjustment Required">Leave Date Schedule Adjustment Required</option>
                    <option value="Documentation / Certificate Not Provided">Documentation / Certificate Not Provided</option>
                    <option value="Other Administrative Adjustment">Other Administrative Adjustment</option>
                </select>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Remove Leave',
        preConfirm: () => {
            const r = document.getElementById('removeLeaveReasonSelect').value;
            if (!r) { Swal.showValidationMessage('Please select a reason.'); return false; }
            return r;
        }
    }).then(result => {
        if(!result.isConfirmed) return;
        $.post('hrms_leaves.php', { action:'delete', leave_id: id, reason: result.value }, function(res){
            if(res.trim() === 'success'){
                Swal.fire({ icon:'success', title:'Leave Record Removed!', timer:1500, showConfirmButton:false
                }).then(() => { clearBackdropHrms(); loadPage('hrms_leaves.php'); });
            } else {
                Swal.fire('Error', res, 'error');
            }
        });
    });
}

function formatDatePH(dateStr){
    if(!dateStr) return '—';
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' });
}

window.openAddModal            = openAddModal;
window.openEditModal           = openEditModal;
window.submitAddLeave          = submitAddLeave;
window.submitEditLeave         = submitEditLeave;
window.viewLeave               = viewLeave;
window.quickStatus             = quickStatus;
window.deleteLeave             = deleteLeave;
window.calcDays                = calcDays;
window.openArchiveLeaveModal   = openArchiveLeaveModal;
window.openRejectedLeaveModal  = openRejectedLeaveModal;
window.archiveLeaveRecord      = archiveLeaveRecord;
window.restoreLeaveRecord      = restoreLeaveRecord;
window.clearBackdropHrms       = clearBackdropHrms;
})();
</script>
