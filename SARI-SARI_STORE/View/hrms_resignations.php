<?php
require_once '../Model/database.php';
require_once '../Model/logger.php';

if(session_status() === PHP_SESSION_NONE){
    session_start();
}
$admin_id = $_SESSION['user_id'] ?? 1;

// Ensure resignations_archive table exists
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS resignations_archive (
        archive_id INT AUTO_INCREMENT PRIMARY KEY,
        resignation_id INT,
        employee_id INT,
        resignation_type VARCHAR(100),
        date_filed DATE,
        last_day DATE,
        reason TEXT,
        remarks TEXT,
        status VARCHAR(50),
        archive_reason TEXT,
        archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/*=========================================================
    ACTIONS (POST / AJAX)
==========================================================*/

// ARCHIVE RESIGNATION
if(isset($_POST['action']) && $_POST['action'] === 'archive_resignation'){
    $resignation_id = (int)$_POST['resignation_id'];
    $reason = mysqli_real_escape_string($conn, trim($_POST['reason'] ?? ''));

    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM resignations WHERE resignation_id = $resignation_id"));
    if ($r) {
        $reason_esc = mysqli_real_escape_string($conn, $r['reason'] ?? '');
        $remarks_esc = mysqli_real_escape_string($conn, $r['remarks'] ?? '');

        $ins = mysqli_query($conn, "
            INSERT INTO resignations_archive (resignation_id, employee_id, resignation_type, date_filed, last_day, reason, remarks, status, archive_reason)
            VALUES ({$r['resignation_id']}, {$r['employee_id']}, '{$r['resignation_type']}', '{$r['date_filed']}', '{$r['last_day']}', '$reason_esc', '$remarks_esc', '{$r['status']}', '$reason')
        ");
        if ($ins) {
            mysqli_query($conn, "DELETE FROM resignations WHERE resignation_id = $resignation_id");
            logActivity($conn, $admin_id, 'Resignation Archived', "Archived resignation #$resignation_id. Reason: $reason");
            ob_clean(); echo 'success'; exit;
        }
    }
    ob_clean(); echo 'error: Failed to archive resignation record.'; exit;
}

// RESTORE RESIGNATION
if(isset($_POST['action']) && $_POST['action'] === 'restore_resignation'){
    $archive_id = (int)$_POST['archive_id'];
    $arch = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM resignations_archive WHERE archive_id = $archive_id"));
    if ($arch) {
        $reason_esc = mysqli_real_escape_string($conn, $arch['reason'] ?? '');
        $remarks_esc = mysqli_real_escape_string($conn, $arch['remarks'] ?? '');

        $ins = mysqli_query($conn, "
            INSERT INTO resignations (employee_id, resignation_type, date_filed, last_day, reason, remarks, status, created_by)
            VALUES ({$arch['employee_id']}, '{$arch['resignation_type']}', '{$arch['date_filed']}', '{$arch['last_day']}', '$reason_esc', '$remarks_esc', '{$arch['status']}', $admin_id)
        ");
        if ($ins) {
            mysqli_query($conn, "DELETE FROM resignations_archive WHERE archive_id = $archive_id");
            logActivity($conn, $admin_id, 'Resignation Restored', "Restored resignation #{$arch['resignation_id']} from archive");
            ob_clean(); echo 'success'; exit;
        }
    }
    ob_clean(); echo 'error: Failed to restore resignation record.'; exit;
}

// CREATE RESIGNATION
if(isset($_POST['action']) && $_POST['action'] == 'create'){
    $employee_id    = (int)$_POST['employee_id'];
    $date_filed     = mysqli_real_escape_string($conn, $_POST['date_filed']);
    $last_day       = mysqli_real_escape_string($conn, $_POST['last_day']);
    $reason         = mysqli_real_escape_string($conn, trim($_POST['reason']));
    $resignation_type = mysqli_real_escape_string($conn, $_POST['resignation_type'] ?? 'Voluntary');
    $status         = 'Pending';

    // Prevent duplicate pending/acknowledged resignation for same employee
    $dupCheck = mysqli_query($conn,
        "SELECT resignation_id FROM resignations
         WHERE employee_id=$employee_id AND status IN ('Pending','Acknowledged')
         LIMIT 1"
    );
    if(mysqli_num_rows($dupCheck) > 0){
        ob_clean();
        echo 'error: This employee already has an active resignation on file.';
        exit();
    }

    $q = mysqli_query($conn,
        "INSERT INTO resignations (employee_id, date_filed, last_day, reason, resignation_type, status, created_by)
         VALUES ($employee_id, '$date_filed', '$last_day', '$reason', '$resignation_type', '$status', $admin_id)"
    );

    if($q){
        $rid = mysqli_insert_id($conn);
        $emp = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT full_name, employee_no FROM employees WHERE employee_id=$employee_id"
        ));
        logActivity($conn, $admin_id, 'Resignation Filed',
            "Filed resignation for {$emp['full_name']} ({$emp['employee_no']}) — Last Day: $last_day");
        ob_clean();
        echo 'success:' . $rid;
    } else {
        ob_clean();
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// UPDATE STATUS (Acknowledge / Approve / Reject)
if(isset($_POST['action']) && $_POST['action'] == 'update_status'){
    $resignation_id = (int)$_POST['resignation_id'];
    $status         = mysqli_real_escape_string($conn, $_POST['status']);
    $remarks        = mysqli_real_escape_string($conn, trim($_POST['remarks'] ?? ''));
    $processedBy    = $admin_id;
    $processedAt    = date('Y-m-d H:i:s');

    $q = mysqli_query($conn,
        "UPDATE resignations
         SET status='$status', remarks='$remarks',
             processed_by=$processedBy, processed_at='$processedAt'
         WHERE resignation_id=$resignation_id"
    );

    if($q){
        $row = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT r.*, e.full_name, e.employee_no, e.status AS emp_status
             FROM resignations r
             JOIN employees e ON r.employee_id = e.employee_id
             WHERE r.resignation_id=$resignation_id"
        ));
        if ($row) {
            if($status === 'Approved'){
                mysqli_query($conn,
                    "UPDATE employees SET status='Resigned' WHERE employee_id={$row['employee_id']}"
                );
                logActivity($conn, $admin_id, 'Resignation Approved',
                    "Approved resignation of {$row['full_name']} ({$row['employee_no']}) — Effective: {$row['last_day']}");
            } else {
                if ($row['emp_status'] === 'Resigned' && in_array($status, ['Rejected', 'Cancelled', 'Pending', 'Acknowledged'])) {
                    mysqli_query($conn,
                        "UPDATE employees SET status='Active' WHERE employee_id={$row['employee_id']}"
                    );
                }
                logActivity($conn, $admin_id, "Resignation $status",
                    "Resignation #{$resignation_id} for {$row['full_name']} marked as $status");
            }
        }
        ob_clean();
        echo 'success';
    } else {
        ob_clean();
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// EDIT RESIGNATION
if(isset($_POST['action']) && $_POST['action'] == 'edit'){
    $resignation_id   = (int)$_POST['resignation_id'];
    $date_filed       = mysqli_real_escape_string($conn, $_POST['date_filed']);
    $last_day         = mysqli_real_escape_string($conn, $_POST['last_day']);
    $reason           = mysqli_real_escape_string($conn, trim($_POST['reason']));
    $resignation_type = mysqli_real_escape_string($conn, $_POST['resignation_type']);
    $status           = mysqli_real_escape_string($conn, $_POST['status']);

    $q = mysqli_query($conn,
        "UPDATE resignations
         SET date_filed='$date_filed', last_day='$last_day',
             reason='$reason', resignation_type='$resignation_type', status='$status'
         WHERE resignation_id=$resignation_id"
    );
    if ($q) {
        $row = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT r.*, e.status AS emp_status
             FROM resignations r
             JOIN employees e ON r.employee_id = e.employee_id
             WHERE r.resignation_id=$resignation_id"
        ));
        if ($row) {
            if ($status === 'Approved') {
                mysqli_query($conn, "UPDATE employees SET status='Resigned' WHERE employee_id={$row['employee_id']}");
            } else if ($row['emp_status'] === 'Resigned' && in_array($status, ['Rejected', 'Cancelled', 'Pending', 'Acknowledged'])) {
                mysqli_query($conn, "UPDATE employees SET status='Active' WHERE employee_id={$row['employee_id']}");
            }
        }
    }
    ob_clean();
    echo $q ? 'success' : 'error: ' . mysqli_error($conn);
    exit();
}

// DELETE RESIGNATION
if(isset($_POST['action']) && $_POST['action'] == 'delete'){
    $resignation_id = (int)$_POST['resignation_id'];
    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT r.*, e.full_name FROM resignations r
         JOIN employees e ON r.employee_id = e.employee_id
         WHERE r.resignation_id=$resignation_id"
    ));
    $q = mysqli_query($conn, "DELETE FROM resignations WHERE resignation_id=$resignation_id");
    ob_clean();
    if($q){
        if($row) logActivity($conn, $admin_id, 'Resignation Deleted',
            "Deleted resignation record #{$resignation_id} for {$row['full_name']}");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

/*=========================================================
    FETCH DATA
==========================================================*/

$empResult = mysqli_query($conn,
    "SELECT employee_id, full_name, employee_no
     FROM employees WHERE status='Active' ORDER BY full_name ASC"
);
$employeeList = [];
while($e = mysqli_fetch_assoc($empResult)) $employeeList[] = $e;

$resignResult = mysqli_query($conn,
    "SELECT r.*, e.full_name, e.employee_no, e.employment_type, e.basic_salary,
            p.full_name AS processed_by_name
     FROM resignations r
     JOIN employees e ON r.employee_id = e.employee_id
     LEFT JOIN users p ON r.processed_by = p.user_id
     ORDER BY r.created_at DESC"
);
$resignList = [];
while($r = mysqli_fetch_assoc($resignResult)) $resignList[] = $r;

$total      = count($resignList);
$pending    = 0; $acknowledged = 0; $approved = 0; $rejected = 0;
foreach($resignList as $r){
    if($r['status'] === 'Pending')      $pending++;
    if($r['status'] === 'Acknowledged') $acknowledged++;
    if($r['status'] === 'Approved')     $approved++;
    if($r['status'] === 'Rejected')     $rejected++;
}
?>

<style>
.swal2-container { z-index: 99999 !important; }

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

.rs-badge { font-size: 11px; font-weight: 600; padding: 4px 11px; border-radius: 20px; display: inline-block; }
.rs-pending      { background: #fef9c3; color: #854d0e; }
.rs-acknowledged { background: #dbeafe; color: #1d4ed8; }
.rs-approved     { background: #d1fae5; color: #065f46; }
.rs-rejected     { background: #fee2e2; color: #991b1b; }

.rs-type {
    font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 12px;
    background: #fdf4ff; color: #7e22ce; display: inline-block;
}
.modal-header-resign { background: linear-gradient(135deg, #4a0e0e, #dc2626); color: white; }
.modal-header-resign .btn-close { filter: invert(1); }
.modal-header-view   { background: linear-gradient(135deg, #1a3c5e, #2563eb); color: white; }
.modal-header-view .btn-close { filter: invert(1); }
.action-btn-group { display: flex; gap: 4px; justify-content: center; }

.notice-days {
    font-size: 12px; font-weight: 700; padding: 3px 10px;
    border-radius: 20px; background: #f3f4f6; color: #374151;
}
</style>

<!-- ===== PAGE HEADER ===== -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color:#1a3c5e;">
            <i class="bi bi-door-open-fill me-2" style="color:#dc2626;"></i>Resignation Management
        </h4>
        <p class="text-muted mb-0" style="font-size:13px;">
            Track and process employee resignations
        </p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm px-3" onclick="openArchiveResignationModal()">
            <i class="bi bi-archive-fill me-1"></i>Archive
            <?php
            $archResCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM resignations_archive"))['c'];
            if ($archResCount > 0)
                echo '<span class="badge bg-danger ms-1">' . $archResCount . '</span>';
            ?>
        </button>
        <button class="btn btn-outline-danger btn-sm px-3" onclick="openRejectedResignationModal()">
            <i class="bi bi-x-circle-fill me-1"></i>Rejected
            <?php
            $rejResCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM resignations WHERE status = 'Rejected'"))['c'];
            if ($rejResCount > 0)
                echo '<span class="badge bg-danger ms-1">' . $rejResCount . '</span>';
            ?>
        </button>
        <button class="btn btn-danger btn-sm px-3" onclick="openAddModal()">
            <i class="bi bi-plus-lg me-1"></i>File Resignation
        </button>
    </div>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="row g-3 mb-4">

    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Total</div>
                <div class="stat-value"><?= $total; ?></div>
                <span style="font-size:11px;color:#6c757d;">All Records</span>
            </div>
            <div class="stat-icon" style="background:#6c757d;">
                <i class="bi bi-person-dash-fill"></i>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Pending</div>
                <div class="stat-value" style="color:#854d0e;"><?= $pending; ?></div>
                <span style="font-size:11px;color:#6c757d;">Needs Action</span>
            </div>
            <div class="stat-icon" style="background:#d97706;">
                <i class="bi bi-hourglass-split"></i>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Acknowledged</div>
                <div class="stat-value" style="color:#1d4ed8;"><?= $acknowledged; ?></div>
                <span style="font-size:11px;color:#6c757d;">In Progress</span>
            </div>
            <div class="stat-icon" style="background:#2563eb;">
                <i class="bi bi-check-circle"></i>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div>
                <div class="stat-label">Approved</div>
                <div class="stat-value" style="color:#065f46;"><?= $approved; ?></div>
                <span style="font-size:11px;color:#6c757d;">Processed</span>
            </div>
            <div class="stat-icon" style="background:#16a34a;">
                <i class="bi bi-person-check-fill"></i>
            </div>
        </div>
    </div>

</div>

<!-- ===== TABLE ===== -->
<div class="page-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0" style="color:#1a3c5e;">
            <i class="bi bi-table me-2"></i>All Resignation Records
        </h6>
        <div class="d-flex gap-2">
            <span class="badge rounded-pill" style="background:#fef9c3;color:#854d0e;font-size:11px;padding:6px 12px;">
                <?= $pending; ?> Pending
            </span>
        </div>
    </div>

    <div class="table-responsive">
       <table class="table table-hover <?= !empty($resignList) ? 'datatable' : ''; ?>" style="font-size:13px;">
            <thead style="background:#f8fafc;">
                <tr>
                    <th>#</th>
                    <th>Employee</th>
                    <th>Type</th>
                    <th>Date Filed</th>
                    <th>Last Day</th>
                    <th>Notice Days</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($resignList)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="bi bi-inbox" style="font-size:36px;display:block;margin-bottom:8px;"></i>
                        No resignation records yet.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach($resignList as $i => $r):
                    $noticeDays = (new DateTime($r['date_filed']))->diff(new DateTime($r['last_day']))->days;
                    $badgeClass = match($r['status']){
                        'Pending'      => 'rs-pending',
                        'Acknowledged' => 'rs-acknowledged',
                        'Approved'     => 'rs-approved',
                        'Rejected'     => 'rs-rejected',
                        default        => 'rs-pending'
                    };
                    $rJson = htmlspecialchars(json_encode($r), ENT_QUOTES);
                ?>
                <tr>
                    <td><?= $i + 1; ?></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($r['full_name']); ?></div>
                        <small class="text-muted"><?= htmlspecialchars($r['employee_no']); ?></small>
                    </td>
                    <td><span class="rs-type"><?= htmlspecialchars($r['resignation_type']); ?></span></td>
                    <td><?= date('M d, Y', strtotime($r['date_filed'])); ?></td>
                    <td><?= date('M d, Y', strtotime($r['last_day'])); ?></td>
                    <td><span class="notice-days"><?= $noticeDays; ?> days</span></td>
                    <td><span class="rs-badge <?= $badgeClass; ?>"><?= $r['status']; ?></span></td>
                    <td>
                        <div class="action-btn-group">
                            <button class="btn btn-sm btn-outline-primary" title="View"
                                onclick="viewResignation(<?= $rJson; ?>)">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if($r['status'] === 'Pending'): ?>
                            <button class="btn btn-sm btn-outline-info" title="Acknowledge"
                                onclick="quickStatus(<?= $r['resignation_id']; ?>, 'Acknowledged')">
                                <i class="bi bi-check-circle"></i>
                            </button>
                            <?php endif; ?>
                            <?php if(in_array($r['status'], ['Pending', 'Acknowledged'])): ?>
                            <button class="btn btn-sm btn-outline-warning" title="Edit"
                                onclick="openEditModal(<?= $rJson; ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!--=========================================================
    ADD RESIGNATION MODAL
==========================================================-->
<div class="modal fade" id="addResignModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-resign">
                <h5 class="modal-title">
                    <i class="bi bi-door-open-fill me-2"></i>File Resignation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">

                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Employee <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_employee_id" required>
                            <option value="">-- Select Employee --</option>
                            <?php foreach($employeeList as $emp): ?>
                            <option value="<?= $emp['employee_id']; ?>">
                                <?= htmlspecialchars($emp['full_name']); ?> (<?= $emp['employee_no']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Resignation Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_resignation_type">
                            <option value="Voluntary">Voluntary</option>
                            <option value="Constructive">Constructive</option>
                            <option value="Mutual Agreement">Mutual Agreement</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Date Filed <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="add_date_filed"
                               value="<?= date('Y-m-d'); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Last Day of Work <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="add_last_day"
                               oninput="calcNoticeDays('add')">
                        <div class="form-text">Must be at least 30 days from date filed.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Notice Period</label>
                        <div class="form-control bg-light text-muted" id="add_notice_display">—</div>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Reason / Remarks <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="add_reason" rows="3"
                                  placeholder="State the reason for resignation..."></textarea>
                    </div>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitAddResignation()">
                    <i class="bi bi-send me-1"></i>Submit Resignation
                </button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    EDIT RESIGNATION MODAL
==========================================================-->
<div class="modal fade" id="editResignModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-resign">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square me-2"></i>Edit Resignation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit_resignation_id">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Resignation Type</label>
                        <select class="form-select" id="edit_resignation_type">
                            <option value="Voluntary">Voluntary</option>
                            <option value="Constructive">Constructive</option>
                            <option value="Mutual Agreement">Mutual Agreement</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Status</label>
                        <select class="form-select" id="edit_status">
                            <option value="Pending">Pending</option>
                            <option value="Acknowledged">Acknowledged</option>
                            <option value="Approved">Approved</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Date Filed</label>
                        <input type="date" class="form-control" id="edit_date_filed">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Last Day of Work</label>
                        <input type="date" class="form-control" id="edit_last_day"
                               oninput="calcNoticeDays('edit')">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Notice Period</label>
                        <div class="form-control bg-light text-muted" id="edit_notice_display">—</div>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Reason / Remarks</label>
                        <textarea class="form-control" id="edit_reason" rows="3"></textarea>
                    </div>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning text-white" onclick="submitEditResignation()">
                    <i class="bi bi-save me-1"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    VIEW RESIGNATION MODAL
==========================================================-->
<div class="modal fade" id="viewResignModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-view">
                <h5 class="modal-title">
                    <i class="bi bi-file-earmark-person me-2"></i>Resignation Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- Employee Banner -->
                <div class="d-flex align-items-center gap-3 p-3 mb-4 rounded-3"
                     style="background:#f0f4ff;">
                    <div id="v_avatar"
                         style="width:50px;height:50px;border-radius:50%;background:#2563eb;
                                color:white;font-size:20px;font-weight:700;display:flex;
                                align-items:center;justify-content:center;flex-shrink:0;">
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold fs-6" id="v_name"></div>
                        <small class="text-muted" id="v_empno"></small>
                    </div>
                    <div id="v_status_badge"></div>
                </div>

                <!-- Details Grid -->
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="p-3 rounded-3" style="background:#f8fafc;">
                            <div class="text-muted" style="font-size:11px;font-weight:600;text-transform:uppercase;">Type</div>
                            <div class="fw-semibold mt-1" id="v_type"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-3" style="background:#f8fafc;">
                            <div class="text-muted" style="font-size:11px;font-weight:600;text-transform:uppercase;">Date Filed</div>
                            <div class="fw-semibold mt-1" id="v_date_filed"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-3" style="background:#fff7ed;">
                            <div class="text-muted" style="font-size:11px;font-weight:600;text-transform:uppercase;">Last Day</div>
                            <div class="fw-semibold mt-1 text-danger" id="v_last_day"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-3" style="background:#f8fafc;">
                            <div class="text-muted" style="font-size:11px;font-weight:600;text-transform:uppercase;">Notice Period</div>
                            <div class="fw-semibold mt-1" id="v_notice"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-3" style="background:#f8fafc;">
                            <div class="text-muted" style="font-size:11px;font-weight:600;text-transform:uppercase;">Processed By</div>
                            <div class="fw-semibold mt-1" id="v_processed_by"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-3" style="background:#f8fafc;">
                            <div class="text-muted" style="font-size:11px;font-weight:600;text-transform:uppercase;">Processed On</div>
                            <div class="fw-semibold mt-1" id="v_processed_at"></div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="p-3 rounded-3" style="background:#f8fafc;">
                            <div class="text-muted mb-1" style="font-size:11px;font-weight:600;text-transform:uppercase;">
                                Reason / Remarks
                            </div>
                            <div id="v_reason" style="font-size:14px;line-height:1.6;"></div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer gap-2">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-info text-white" id="v_btn_acknowledge" onclick="quickStatus(window._currentViewId, 'Acknowledged')">
                    <i class="bi bi-check-circle me-1"></i>Acknowledge
                </button>
                <button class="btn btn-success" id="v_btn_approve" onclick="quickStatus(window._currentViewId, 'Approved')">
                    <i class="bi bi-check2-all me-1"></i>Approve
                </button>
                <button class="btn btn-danger" id="v_btn_reject" onclick="quickStatus(window._currentViewId, 'Rejected')">
                    <i class="bi bi-x-circle me-1"></i>Reject
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
let _currentViewId = null;

function clearBackdropHrms(){
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open').css('padding-right','');
}

/*====================================================
    NOTICE DAYS CALCULATOR
====================================================*/
function calcNoticeDays(prefix){
    const filedEl   = document.getElementById(prefix + '_date_filed');
    const lastDayEl = document.getElementById(prefix + '_last_day');
    const display   = document.getElementById(prefix + '_notice_display');
    if(!filedEl || !lastDayEl || !display) return;

    const filed   = filedEl.value;
    const lastDay = lastDayEl.value;

    if(filed && lastDay){
        const d1   = new Date(filed);
        const d2   = new Date(lastDay);
        const days = Math.round((d2 - d1) / (1000 * 60 * 60 * 24));
        if(days < 0){
            display.textContent = '⚠ Last day is before date filed';
            display.style.color = '#dc2626';
        } else {
            display.textContent = days + ' day' + (days !== 1 ? 's' : '') + ' notice';
            display.style.color = days >= 30 ? '#16a34a' : '#d97706';
        }
    } else {
        display.textContent = '—';
        display.style.color = '';
    }
}

/*====================================================
    OPEN ADD MODAL
====================================================*/
function openAddModal(){
    const empEl  = document.getElementById('add_employee_id');
    const typeEl = document.getElementById('add_resignation_type');
    const dateEl = document.getElementById('add_date_filed');
    const lastEl = document.getElementById('add_last_day');
    const reasEl = document.getElementById('add_reason');
    const dispEl = document.getElementById('add_notice_display');

    if(empEl)  empEl.value  = '';
    if(typeEl) typeEl.value = 'Voluntary';
    if(dateEl) dateEl.value = new Date().toISOString().split('T')[0];
    if(lastEl) lastEl.value = '';
    if(reasEl) reasEl.value = '';
    if(dispEl) {
        dispEl.textContent = '—';
        dispEl.style.color = '';
    }

    const modalEl = document.getElementById('addResignModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }
}

/*====================================================
    SUBMIT ADD
====================================================*/
function submitAddResignation(){
    const emp_id           = document.getElementById('add_employee_id')?.value;
    const resignation_type = document.getElementById('add_resignation_type')?.value;
    const date_filed       = document.getElementById('add_date_filed')?.value;
    const last_day         = document.getElementById('add_last_day')?.value;
    const reason           = document.getElementById('add_reason')?.value.trim();

    if(!emp_id || !date_filed || !last_day || !reason){
        Swal.fire('Missing Fields', 'Please fill in all required fields.', 'warning');
        return;
    }
    if(last_day <= date_filed){
        Swal.fire('Invalid Dates', 'Last day must be after the date filed.', 'warning');
        return;
    }

    const days = Math.round((new Date(last_day) - new Date(date_filed)) / 86400000);
    if(days < 30){
        Swal.fire({
            title: 'Short Notice Period',
            text: `Only ${days} days notice. The standard is 30 days. Continue anyway?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Submit',
            confirmButtonColor: '#dc2626'
        }).then(result => {
            if(!result.isConfirmed) return;
            _doSubmitAdd(emp_id, resignation_type, date_filed, last_day, reason);
        });
        return;
    }

    _doSubmitAdd(emp_id, resignation_type, date_filed, last_day, reason);
}

function _doSubmitAdd(emp_id, resignation_type, date_filed, last_day, reason){
    $.post('hrms_resignations.php', {
        action: 'create',
        employee_id: emp_id,
        resignation_type, date_filed, last_day, reason
    }, function(res){
        res = res.trim();
        if(res.startsWith('success')){
            const modalEl = document.getElementById('addResignModal');
            if(modalEl) bootstrap.Modal.getInstance(modalEl)?.hide();
            Swal.fire({
                icon: 'success', title: 'Resignation Filed!',
                text: 'The resignation has been recorded successfully.',
                timer: 1600, showConfirmButton: false
            }).then(() => { clearBackdropHrms(); loadPage('hrms_resignations.php'); });
        } else {
            Swal.fire('Error', res.replace('error:', '').trim(), 'error');
        }
    });
}

/*====================================================
    OPEN EDIT MODAL
====================================================*/
function openEditModal(r){
    document.getElementById('edit_resignation_id').value   = r.resignation_id;
    document.getElementById('edit_resignation_type').value = r.resignation_type;
    document.getElementById('edit_status').value           = r.status;
    document.getElementById('edit_date_filed').value       = r.date_filed;
    document.getElementById('edit_last_day').value         = r.last_day;
    document.getElementById('edit_reason').value           = r.reason || '';
    calcNoticeDays('edit');
    const modalEl = document.getElementById('editResignModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }
}

/*====================================================
    SUBMIT EDIT
====================================================*/
function submitEditResignation(){
    const resignation_id   = document.getElementById('edit_resignation_id').value;
    const resignation_type = document.getElementById('edit_resignation_type').value;
    const status           = document.getElementById('edit_status').value;
    const date_filed       = document.getElementById('edit_date_filed').value;
    const last_day         = document.getElementById('edit_last_day').value;
    const reason           = document.getElementById('edit_reason').value;

    if(!date_filed || !last_day){
        Swal.fire('Missing Fields', 'Please fill in all date fields.', 'warning');
        return;
    }
    if(last_day <= date_filed){
        Swal.fire('Invalid Dates', 'Last day must be after the date filed.', 'warning');
        return;
    }

    $.post('hrms_resignations.php', {
        action: 'edit', resignation_id,
        resignation_type, status, date_filed, last_day, reason
    }, function(res){
        if(res.trim() === 'success'){
            const modalEl = document.getElementById('editResignModal');
            if(modalEl) bootstrap.Modal.getInstance(modalEl)?.hide();
            Swal.fire({ icon:'success', title:'Updated!', timer:1500, showConfirmButton:false
            }).then(() => { clearBackdropHrms(); loadPage('hrms_resignations.php'); });
        } else {
            Swal.fire('Error', res.replace('error:', '').trim(), 'error');
        }
    });
}

/*====================================================
    VIEW DETAILS
====================================================*/
function viewResignation(r){
    window._currentViewId = r.resignation_id;
    _currentViewId = r.resignation_id;

    const words  = (r.full_name || '').replace(/[^a-zA-Z0-9\s]/g,'').split(' ');
    let initials = '';
    words.forEach(w => { if(initials.length < 2 && w.length) initials += w[0].toUpperCase(); });
    document.getElementById('v_avatar').textContent = initials || '?';

    document.getElementById('v_name').textContent       = r.full_name;
    document.getElementById('v_empno').textContent      = r.employee_no;
    document.getElementById('v_type').textContent       = r.resignation_type;
    document.getElementById('v_date_filed').textContent = formatDatePH(r.date_filed);
    document.getElementById('v_last_day').textContent   = formatDatePH(r.last_day);
    document.getElementById('v_reason').textContent     = r.reason || '(No reason provided)';
    document.getElementById('v_processed_by').textContent = r.processed_by_name || '—';
    document.getElementById('v_processed_at').textContent = r.processed_at
        ? formatDatePH(r.processed_at.substring(0,10)) : '—';

    const days = Math.round((new Date(r.last_day) - new Date(r.date_filed)) / 86400000);
    document.getElementById('v_notice').textContent = days + ' day' + (days !== 1 ? 's' : '');

    const badgeMap = {
        Pending:      '<span class="rs-badge rs-pending">Pending</span>',
        Acknowledged: '<span class="rs-badge rs-acknowledged">Acknowledged</span>',
        Approved:     '<span class="rs-badge rs-approved">Approved</span>',
        Rejected:     '<span class="rs-badge rs-rejected">Rejected</span>',
    };
    document.getElementById('v_status_badge').innerHTML = badgeMap[r.status] || r.status;

    const btnAck = document.getElementById('v_btn_acknowledge');
    const btnApp = document.getElementById('v_btn_approve');
    const btnRej = document.getElementById('v_btn_reject');

    if(btnAck) btnAck.style.display = (r.status === 'Pending') ? '' : 'none';
    if(btnApp) btnApp.style.display = (r.status !== 'Approved')  ? '' : 'none';
    if(btnRej) btnRej.style.display = (r.status !== 'Rejected' && r.status !== 'Approved') ? '' : 'none';

    const modalEl = document.getElementById('viewResignModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }
}

/*====================================================
    QUICK STATUS UPDATE
====================================================*/
function quickStatus(id, status){
    if (!id || typeof id === 'object') {
        id = window._currentViewId || _currentViewId;
    }
    const labels = {
        Acknowledged: { title:'Acknowledge this Resignation?', icon:'question',  color:'#2563eb', btn:'Yes, Acknowledge' },
        Approved:     { title:'Approve this Resignation?',     icon:'question',  color:'#16a34a', btn:'Yes, Approve' },
        Rejected:     { title:'Reject this Resignation?',      icon:'warning',   color:'#dc2626', btn:'Yes, Reject' },
    };
    const cfg = labels[status] || {};

    const viewModalEl = document.getElementById('viewResignModal');
    if(viewModalEl) {
        const instance = bootstrap.Modal.getInstance(viewModalEl);
        if(instance) instance.hide();
    }
    clearBackdropHrms();

    let htmlContent = `<textarea id="swal_remarks" class="form-control mt-2" rows="3" style="font-size:13px;" placeholder="Add remarks (optional)..."></textarea>`;
    if (status === 'Rejected') {
        htmlContent = `
            <p class="text-muted mb-2" style="font-size:13px;">Select reason for rejecting this resignation request:</p>
            <div class="text-start mb-2">
                <label class="form-label fw-bold text-dark" style="font-size:12px;">Rejection Reason <span class="text-danger">*</span></label>
                <select id="swal_remarks" class="form-select" style="font-size:13px;">
                    <option value="Non-Compliance with 30-Day Notice Period">Non-Compliance with 30-Day Notice Period</option>
                    <option value="Unresolved Financial / Property Clearances">Unresolved Financial / Property Clearances</option>
                    <option value="Ongoing Administrative / Disciplinary Investigation">Ongoing Administrative / Disciplinary Investigation</option>
                    <option value="Invalid / Unofficial Resignation Filing">Invalid / Unofficial Resignation Filing</option>
                    <option value="Retained under Executive Agreement">Retained under Executive Agreement</option>
                </select>
            </div>
        `;
    }

    Swal.fire({
        title: cfg.title,
        html: htmlContent,
        icon: cfg.icon,
        showCancelButton: true,
        confirmButtonColor: cfg.color,
        confirmButtonText: cfg.btn,
        preConfirm: () => {
            const val = document.getElementById('swal_remarks').value;
            if (status === 'Rejected' && !val) { Swal.showValidationMessage('Please select a rejection reason.'); return false; }
            return val;
        }
    }).then(result => {
        if(!result.isConfirmed) return;
        $.post('hrms_resignations.php', {
            action: 'update_status',
            resignation_id: id,
            status: status,
            remarks: result.value || ''
        }, function(res){
            if(res.trim() === 'success'){
                Swal.fire({
                    icon: 'success',
                    title: `Resignation ${status}!`,
                    text: status === 'Approved' ? 'Employee status has been updated to Resigned.' : '',
                    timer: 1800, showConfirmButton: false
                }).then(() => { clearBackdropHrms(); loadPage('hrms_resignations.php'); });
            } else {
                Swal.fire('Error', res.replace('error:','').trim(), 'error');
            }
        });
    });
}

/*====================================================
    REMOVE RESIGNATION RECORD
====================================================*/
function deleteResignation(id, name){
    Swal.fire({
        title: 'Remove Resignation Record?',
        html: `
            <p class="text-muted mb-2" style="font-size:13px;">Select reason for removing resignation record for <strong>${name}</strong>:</p>
            <div class="text-start mb-2">
                <label class="form-label fw-bold text-dark" style="font-size:12px;">Reason for Removal <span class="text-danger">*</span></label>
                <select id="removeResignReasonSelect" class="form-select" style="font-size:13px;">
                    <option value="Employee Rescinded / Withdrew Resignation">Employee Rescinded / Withdrew Resignation</option>
                    <option value="Retained via Counter-Offer">Retained via Counter-Offer</option>
                    <option value="Duplicate Resignation Record">Duplicate Resignation Record</option>
                    <option value="Filed under Incorrect Dates">Filed under Incorrect Dates</option>
                    <option value="Management Administrative Cleanup">Management Administrative Cleanup</option>
                </select>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Remove Record',
        preConfirm: () => {
            const r = document.getElementById('removeResignReasonSelect').value;
            if (!r) { Swal.showValidationMessage('Please select a reason.'); return false; }
            return r;
        }
    }).then(result => {
        if(!result.isConfirmed) return;
        $.post('hrms_resignations.php', { action:'delete', resignation_id: id, reason: result.value }, function(res){
            if(res.trim() === 'success'){
                Swal.fire({ icon:'success', title:'Resignation Record Removed!', timer:1500, showConfirmButton:false
                }).then(() => { clearBackdropHrms(); loadPage('hrms_resignations.php'); });
            } else {
                Swal.fire('Error', res.replace('error:','').trim(), 'error');
            }
        });
    });
}

function formatDatePH(dateStr){
    if(!dateStr) return '—';
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' });
}

function openArchiveResignationModal() {
    const modalEl = document.getElementById('archiveResignationModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }
}

function openRejectedResignationModal() {
    const modalEl = document.getElementById('rejectedResignationModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }
}

function archiveResignationRecord(id, name) {
    Swal.fire({
        title: 'Archive Resignation Record?',
        html: `<p class="text-muted mb-2" style="font-size:13px;">Archive resignation record for <strong>${name}</strong>.</p>
               <input id="archReasonRes" class="swal2-input" placeholder="Reason e.g. Record finalized, Retained employee...">`,
        showCancelButton: true,
        confirmButtonColor: '#6c757d',
        confirmButtonText: 'Archive Record',
        preConfirm: () => {
            const r = document.getElementById('archReasonRes').value.trim();
            if (!r) { Swal.showValidationMessage('Please provide a reason.'); return false; }
            return r;
        }
    }).then(result => {
        if (!result.isConfirmed) return;
        $.post('hrms_resignations.php', { action: 'archive_resignation', resignation_id: id, reason: result.value }, function (res) {
            if (res.trim() === 'success') {
                Swal.fire({ icon: 'success', title: 'Resignation Archived!', timer: 1500, showConfirmButton: false })
                    .then(() => loadPage('hrms_resignations.php'));
            } else {
                Swal.fire('Error', res, 'error');
            }
        });
    });
}

function restoreResignationRecord(archiveId, name) {
    Swal.fire({
        title: 'Restore Resignation Record?',
        html: `Restore resignation record for <strong>${name}</strong> back to active list?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Yes, Restore'
    }).then(result => {
        if (!result.isConfirmed) return;
        $.post('hrms_resignations.php', { action: 'restore_resignation', archive_id: archiveId }, function (res) {
            if (res.trim() === 'success') {
                Swal.fire({ icon: 'success', title: 'Restored!', timer: 1500, showConfirmButton: false })
                    .then(() => { clearBackdropHrms(); loadPage('hrms_resignations.php'); });
            } else {
                Swal.fire('Error', res, 'error');
            }
        });
    });
}

window.openAddModal                 = openAddModal;
window.openEditModal                = openEditModal;
window.submitAddResignation         = submitAddResignation;
window.submitEditResignation        = submitEditResignation;
window.viewResignation              = viewResignation;
window.quickStatus                  = quickStatus;
window.deleteResignation            = deleteResignation;
window.calcNoticeDays               = calcNoticeDays;
window.openArchiveResignationModal  = openArchiveResignationModal;
window.openRejectedResignationModal = openRejectedResignationModal;
window.archiveResignationRecord     = archiveResignationRecord;
window.restoreResignationRecord     = restoreResignationRecord;
window.clearBackdropHrms            = clearBackdropHrms;
})();
</script>

<!-- ARCHIVE RESIGNATION MODAL -->
<div class="modal fade" id="archiveResignationModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-archive-fill me-2"></i>Archived Resignation Records</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100 fs-7">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>Date Filed</th>
                                <th>Last Day</th>
                                <th>Archival Reason</th>
                                <th>Archived At</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $archResQuery = mysqli_query($conn, "
                                SELECT a.*, e.full_name, e.employee_no 
                                FROM resignations_archive a 
                                LEFT JOIN employees e ON a.employee_id = e.employee_id 
                                ORDER BY a.archived_at DESC
                            ");
                            if (mysqli_num_rows($archResQuery) > 0) {
                                while ($archRow = mysqli_fetch_assoc($archResQuery)) {
                                    echo '<tr>';
                                    echo '<td><span class="badge bg-secondary font-monospace">#' . $archRow['archive_id'] . '</span></td>';
                                    echo '<td><div class="fw-bold">' . htmlspecialchars($archRow['full_name'] ?? 'N/A') . '</div><small class="text-muted">' . htmlspecialchars($archRow['employee_no'] ?? '') . '</small></td>';
                                    echo '<td><span class="rs-type">' . htmlspecialchars($archRow['resignation_type']) . '</span></td>';
                                    echo '<td>' . date('M d, Y', strtotime($archRow['date_filed'])) . '</td>';
                                    echo '<td>' . date('M d, Y', strtotime($archRow['last_day'])) . '</td>';
                                    echo '<td><span class="text-danger fw-semibold"><i class="bi bi-chat-left-quote me-1"></i>' . htmlspecialchars($archRow['archive_reason'] ?: 'No reason provided') . '</span></td>';
                                    echo '<td><small class="text-muted">' . date('M d, Y h:i A', strtotime($archRow['archived_at'])) . '</small></td>';
                                    echo '<td class="text-center"><button class="btn btn-sm btn-success" onclick="restoreResignationRecord(' . $archRow['archive_id'] . ', \'' . addslashes($archRow['full_name']) . '\')"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</button></td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="8" class="text-center text-muted py-4"><i class="bi bi-inbox me-1"></i>No archived resignation records found.</td></tr>';
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

<!-- REJECTED RESIGNATION MODAL -->
<div class="modal fade" id="rejectedResignationModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-x-circle-fill me-2"></i>Rejected Resignation Records</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100 fs-7">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>Date Filed</th>
                                <th>Proposed Last Day</th>
                                <th>Resignation Reason</th>
                                <th>Rejection Reasoning / Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rejResQuery = mysqli_query($conn, "
                                SELECT r.*, e.full_name, e.employee_no 
                                FROM resignations r 
                                JOIN employees e ON r.employee_id = e.employee_id 
                                WHERE r.status = 'Rejected' 
                                ORDER BY r.created_at DESC
                            ");
                            if (mysqli_num_rows($rejResQuery) > 0) {
                                while ($rejRow = mysqli_fetch_assoc($rejResQuery)) {
                                    echo '<tr>';
                                    echo '<td><span class="badge bg-danger font-monospace">#' . $rejRow['resignation_id'] . '</span></td>';
                                    echo '<td><div class="fw-bold">' . htmlspecialchars($rejRow['full_name']) . '</div><small class="text-muted">' . htmlspecialchars($rejRow['employee_no']) . '</small></td>';
                                    echo '<td><span class="rs-type">' . htmlspecialchars($rejRow['resignation_type']) . '</span></td>';
                                    echo '<td>' . date('M d, Y', strtotime($rejRow['date_filed'])) . '</td>';
                                    echo '<td>' . date('M d, Y', strtotime($rejRow['last_day'])) . '</td>';
                                    echo '<td>' . htmlspecialchars($rejRow['reason'] ?: '—') . '</td>';
                                    echo '<td><span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i>' . htmlspecialchars($rejRow['remarks'] ?: 'Rejected by HR Management') . '</span></td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-check-circle me-1"></i>No rejected resignation records found.</td></tr>';
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