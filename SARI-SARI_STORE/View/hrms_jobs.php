<?php
session_start();
require_once '../Model/database.php';

$admin_id = $_SESSION['user_id'] ?? 0;

// Verifies the currently logged-in admin's password against the users table.
function verifyAdminPassword($conn, $admin_id, $password){
    if(empty($password)) return false;
    $admin_id = (int)$admin_id;
    $res = mysqli_query($conn, "SELECT password FROM users WHERE user_id = $admin_id LIMIT 1");
    $row = mysqli_fetch_assoc($res);
    if(!$row || empty($row['password'])) return false;
    return password_verify($password, $row['password']);
}

/*=========================================================
    HELPER: Insert HRMS notification + activity log
==========================================================*/
function hrmsNotify($conn, $title, $message, $type = 'HRMS'){
    $title   = mysqli_real_escape_string($conn, $title);
    $message = mysqli_real_escape_string($conn, $message);
    $type    = mysqli_real_escape_string($conn, $type);
    
    // Ensure type fits standard or custom notifications.type enum
    $allowed_types = ['Low Stock', 'Approval', 'System', 'Sales', 'HRMS'];
    if(!in_array($type, $allowed_types)) {
        $type = 'HRMS';
    }

    mysqli_query($conn,"
        INSERT INTO notifications (title, message, type)
        VALUES ('$title', '$message', '$type')
    ");
}

function hrmsLog($conn, $userId, $action, $table, $recordId, $desc){
    $userId   = (int)$userId;
    $action   = mysqli_real_escape_string($conn, $action);
    $table    = mysqli_real_escape_string($conn, $table);
    $recordId = (int)$recordId;
    $desc     = mysqli_real_escape_string($conn, $desc);

    // Fallback if DB doesn't have custom actions yet
    $allowed_actions = ['Create', 'Update', 'Delete', 'Login', 'Logout', 'Void', 'Status Change', 'Approve', 'Reject'];
    if(!in_array($action, $allowed_actions)) {
        $action = 'Update';
    }

    mysqli_query($conn,"
        INSERT INTO audit_logs (user_id, action, table_name, record_id, description)
        VALUES ($userId, '$action', '$table', $recordId, '$desc')
    ");
}

/*=========================================================
    AJAX ACTIONS
==========================================================*/

// CREATE JOB POSTING
if(isset($_POST['action']) && $_POST['action'] == 'create_job'){
    if(!verifyAdminPassword($conn, $admin_id, $_POST['password'] ?? '')){
        ob_clean();
        echo 'error: Incorrect password. Job posting was not created.';
        exit();
    }

    $position_name   = mysqli_real_escape_string($conn, $_POST['position_name']);
    $department_id   = (int)$_POST['department_id'];
    $employment_type = mysqli_real_escape_string($conn, $_POST['employment_type']);
    $slots           = (int)$_POST['slots'];
    $salary_min      = (float)$_POST['salary_min'];
    $salary_max      = (float)$_POST['salary_max'];
    $requirements    = mysqli_real_escape_string($conn, $_POST['requirements']);
    $status          = mysqli_real_escape_string($conn, $_POST['status']);

    // Duplicate check
    $dup = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT position_id FROM positions WHERE position_name='$position_name' AND department_id=$department_id"
    ));
    if($dup){
        ob_clean();
        echo 'duplicate';
        exit();
    }

    $q = mysqli_query($conn,"
        INSERT INTO positions (department_id, position_name, employment_type, slots, salary_min, salary_max, requirements, status)
        VALUES ($department_id, '$position_name', '$employment_type', $slots, $salary_min, $salary_max, '$requirements', '$status')
    ");

    if($q){
        $newId = mysqli_insert_id($conn);
       $desc = "Created job posting: $position_name ($employment_type, $slots slots, ₱{$salary_min}–₱{$salary_max}, $status)";
        hrmsNotify($conn, 'New Job Posting', "Position '$position_name' has been created.", 'HRMS');
        hrmsLog($conn, $admin_id, 'Create', 'positions', $newId, $desc);
    }

    ob_clean();
    echo $q ? 'success' : 'error: '.mysqli_error($conn);
    exit();
}

// UPDATE JOB POSTING
if(isset($_POST['action']) && $_POST['action'] == 'update_job'){
    if(!verifyAdminPassword($conn, $admin_id, $_POST['password'] ?? '')){
        ob_clean();
        echo 'error: Incorrect password. Changes were not saved.';
        exit();
    }

    $position_id     = (int)$_POST['position_id'];
    $position_name   = mysqli_real_escape_string($conn, $_POST['position_name']);
    $department_id   = (int)$_POST['department_id'];
    $employment_type = mysqli_real_escape_string($conn, $_POST['employment_type']);
    $slots           = (int)$_POST['slots'];
    $salary_min      = (float)$_POST['salary_min'];
    $salary_max      = (float)$_POST['salary_max'];
    $requirements    = mysqli_real_escape_string($conn, $_POST['requirements']);
    $status          = mysqli_real_escape_string($conn, $_POST['status']);

    // Duplicate check (exclude self)
    $dup = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT position_id FROM positions WHERE position_name='$position_name' AND department_id=$department_id AND position_id != $position_id"
    ));
    if($dup){
        ob_clean();
        echo 'duplicate';
        exit();
    }

    // Fetch old values for logging
    $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM positions WHERE position_id=$position_id"));
    $oldSummary = $old ? ($old['position_name'].' | '.$old['employment_type'].' | '.$old['slots'].' slots | ₱'.$old['salary_min'].'–₱'.$old['salary_max'].' | '.$old['status']) : '';

    $q = mysqli_query($conn,"
        UPDATE positions SET
            position_name   = '$position_name',
            department_id   = $department_id,
            employment_type = '$employment_type',
            slots           = $slots,
            salary_min      = $salary_min,
            salary_max      = $salary_max,
            requirements    = '$requirements',
            status          = '$status'
        WHERE position_id = $position_id
    ");

    if($q){
        $$newSummary = "$position_name | $employment_type | $slots slots | ₱{$salary_min}–₱{$salary_max} | $status";
        $desc = "Updated job posting: $position_name. Changes: ($oldSummary) -> ($newSummary)";
        hrmsNotify($conn, 'Job Posting Updated', "Position '$position_name' has been updated.", 'HRMS');
        hrmsLog($conn, $admin_id, 'Update', 'positions', $position_id, $desc);
    }

    ob_clean();
    echo $q ? 'success' : 'error: '.mysqli_error($conn);
    exit();
}

// CHANGE STATUS
if(isset($_POST['action']) && $_POST['action'] == 'change_status'){
    if(!verifyAdminPassword($conn, $admin_id, $_POST['password'] ?? '')){
        ob_clean();
        echo 'error: Incorrect password. Status was not changed.';
        exit();
    }

    $position_id = (int)$_POST['position_id'];
    $new_status  = mysqli_real_escape_string($conn, $_POST['new_status']);

    // Fetch old status & name for logging
    $oldRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT position_name, status FROM positions WHERE position_id=$position_id"));
    $old_status = $oldRow['status'] ?? '';
    $pos_name   = $oldRow['position_name'] ?? '';

    $q = mysqli_query($conn,
        "UPDATE positions SET status='$new_status' WHERE position_id=$position_id"
    );

    if($q){
        $desc = "Changed status of '$pos_name' from $old_status to $new_status.";
        hrmsNotify($conn, 'Job Status Changed', $desc, 'HRMS');
        hrmsLog($conn, $admin_id, 'Status Change', 'positions', $position_id, $desc);
    }

    ob_clean();
    echo $q ? 'success' : 'error: '.mysqli_error($conn);
    exit();
}

// DELETE JOB POSTING
if(isset($_POST['action']) && $_POST['action'] == 'delete_job'){
    if(!verifyAdminPassword($conn, $admin_id, $_POST['password'] ?? '')){
        ob_clean();
        echo 'error: Incorrect password. Job posting was not deleted.';
        exit();
    }

    $position_id = (int)$_POST['position_id'];

    // Safety check — do not delete if applicants are linked
    $linked = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS total FROM applicants WHERE position_id=$position_id"
    ))['total'];

    if($linked > 0){
        ob_clean();
        echo 'has_applicants:'.$linked;
        exit();
    }

    // Also check if employees are linked
    $empLinked = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS total FROM employees WHERE position_id=$position_id"
    ))['total'];

    if($empLinked > 0){
        ob_clean();
        echo 'has_employees:'.$empLinked;
        exit();
    }

    // Fetch name for logging before delete
    $delRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT position_name FROM positions WHERE position_id=$position_id"));
    $del_name = $delRow['position_name'] ?? 'Unknown';

    $q = mysqli_query($conn,
        "DELETE FROM positions WHERE position_id=$position_id"
    );

    if($q){
        $desc = "Deleted job posting: $del_name";
        hrmsNotify($conn, 'Job Posting Deleted', "Position '$del_name' has been removed from the system.", 'HRMS');
        hrmsLog($conn, $admin_id, 'Delete', 'positions', $position_id, $desc);
    }

    ob_clean();
    echo $q ? 'success' : 'error: '.mysqli_error($conn);
    exit();
}

/*=========================================================
    FETCH DATA
==========================================================*/

// All positions with department names
$positions = mysqli_query($conn,"
    SELECT p.*, d.department_name,
           (SELECT COUNT(*) FROM applicants a WHERE a.position_id = p.position_id AND a.stage NOT IN ('Approved','Rejected')) AS active_applicants
    FROM positions p
    LEFT JOIN departments d ON p.department_id = d.department_id
    ORDER BY p.created_at DESC
");

$positionList = [];
while($row = mysqli_fetch_assoc($positions)){
    $positionList[] = $row;
}

// All departments for dropdown
$departments = mysqli_query($conn,"SELECT * FROM departments ORDER BY department_name ASC");
$deptList = [];
while($d = mysqli_fetch_assoc($departments)){
    $deptList[] = $d;
}

// Summary counts
$totalPositions = count($positionList);
$openCount      = 0;
$closedCount    = 0;
$onHoldCount    = 0;
foreach($positionList as $p){
    if($p['status'] == 'Open')    $openCount++;
    if($p['status'] == 'Closed')  $closedCount++;
    if($p['status'] == 'On Hold') $onHoldCount++;
}
?>

<style>
/*====================================================
    JOB POSTING PAGE STYLES
====================================================*/
.jobs-stat {
    background: white;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
    height: 100%;
    transition: transform .2s, box-shadow .2s;
}
.jobs-stat:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,.1);
}
.jobs-stat .icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; color: white; flex-shrink: 0;
}

.job-badge {
    font-size: 11px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    letter-spacing: .3px;
}
.badge-open    { background: #d1fae5; color: #065f46; }
.badge-closed  { background: #fee2e2; color: #991b1b; }
.badge-onhold  { background: #fef3c7; color: #92400e; }

.emp-type-badge {
    font-size: 10px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 6px;
    background: #ede9fe;
    color: #5b21b6;
}

.salary-range {
    font-size: 12px;
    font-weight: 600;
    color: #059669;
}

.action-btn {
    width: 32px; height: 32px;
    border-radius: 8px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    cursor: pointer;
    transition: all .2s;
    margin: 0 2px;
}
.action-btn:hover { transform: scale(1.1); }
.btn-view   { background: #dbeafe; color: #1d4ed8; }
.btn-edit   { background: #fef3c7; color: #92400e; }
.btn-status { background: #d1fae5; color: #065f46; }
.btn-delete { background: #fee2e2; color: #dc2626; }

.modal-header-custom {
    background: linear-gradient(135deg, #1a3c5e 0%, #2563eb 100%);
    color: white;
    border-radius: 8px 8px 0 0;
}
.modal-header-custom .btn-close {
    filter: brightness(0) invert(1);
}

.detail-label {
    font-size: 11px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 4px;
}
.detail-value {
    font-size: 14px;
    font-weight: 500;
    color: #1f2937;
    margin-bottom: 14px;
}

.requirements-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px;
    font-size: 13px;
    color: #475569;
    line-height: 1.6;
    white-space: pre-wrap;
}

.table-actions { white-space: nowrap; }

/* Empty state */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}
.empty-state i { font-size: 56px; margin-bottom: 12px; display: block; }
.empty-state p { font-size: 14px; margin-top: 6px; }
</style>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">

    <div class="col-xl-3 col-md-6">
        <div class="jobs-stat">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div style="font-size:12px;color:#6c757d;">Total Positions</div>
                    <div style="font-size:28px;font-weight:800;line-height:1.2;margin:6px 0;">
                        <?= $totalPositions; ?>
                    </div>
                    <span class="badge bg-secondary">All Jobs</span>
                </div>
                <div class="icon" style="background:#6366f1;"><i class="bi bi-briefcase-fill"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="jobs-stat">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div style="font-size:12px;color:#6c757d;">Open Positions</div>
                    <div style="font-size:28px;font-weight:800;color:#059669;line-height:1.2;margin:6px 0;">
                        <?= $openCount; ?>
                    </div>
                    <span class="badge bg-success">Hiring</span>
                </div>
                <div class="icon bg-success"><i class="bi bi-check-circle-fill"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="jobs-stat">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div style="font-size:12px;color:#6c757d;">Closed Positions</div>
                    <div style="font-size:28px;font-weight:800;color:#dc2626;line-height:1.2;margin:6px 0;">
                        <?= $closedCount; ?>
                    </div>
                    <span class="badge bg-danger">Filled</span>
                </div>
                <div class="icon bg-danger"><i class="bi bi-x-circle-fill"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="jobs-stat">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div style="font-size:12px;color:#6c757d;">On Hold</div>
                    <div style="font-size:28px;font-weight:800;color:#d97706;line-height:1.2;margin:6px 0;">
                        <?= $onHoldCount; ?>
                    </div>
                    <span class="badge bg-warning text-dark">Paused</span>
                </div>
                <div class="icon bg-warning"><i class="bi bi-pause-circle-fill"></i></div>
            </div>
        </div>
    </div>

</div>

<!-- JOB POSTINGS TABLE -->
<div class="page-card">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0" style="font-weight:700;color:#1a3c5e;">
            <i class="bi bi-briefcase-fill me-2"></i>Job Postings
        </h5>
        <button class="btn btn-primary btn-sm" onclick="openAddModal()" style="border-radius:8px;font-weight:600;">
            <i class="bi bi-plus-lg me-1"></i>Add Job Posting
        </button>
    </div>

    <?php if(count($positionList) == 0){ ?>
    <div class="empty-state">
        <i class="bi bi-briefcase"></i>
        <h6>No Job Postings Yet</h6>
        <p>Click "Add Job Posting" to create your first position.</p>
    </div>
    <?php } else { ?>
    <div class="table-responsive">
        <table class="table table-hover datatable" id="jobsTable" style="width:100%;">
            <thead>
                <tr style="font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;">
                    <th>#</th>
                    <th>Position</th>
                    <th>Department</th>
                    <th>Type</th>
                    <th>Slots</th>
                    <th>Salary Range</th>
                    <th>Applicants</th>
                    <th>Status</th>
                    <th>Posted</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $n=1; foreach($positionList as $pos){ ?>
                <tr>
                    <td style="font-weight:600;color:#6b7280;font-size:13px;"><?= $n++; ?></td>
                    <td>
                        <div style="font-weight:600;font-size:13px;color:#1f2937;">
                            <?= htmlspecialchars($pos['position_name']); ?>
                        </div>
                    </td>
                    <td>
                        <span style="font-size:12px;color:#6b7280;">
                            <?= htmlspecialchars($pos['department_name'] ?? 'N/A'); ?>
                        </span>
                    </td>
                    <td>
                        <span class="emp-type-badge"><?= $pos['employment_type']; ?></span>
                    </td>
                    <td style="font-weight:600;text-align:center;"><?= $pos['slots']; ?></td>
                    <td>
                        <span class="salary-range">
                            ₱<?= number_format($pos['salary_min'],0); ?> – ₱<?= number_format($pos['salary_max'],0); ?>
                        </span>
                    </td>
                    <td style="text-align:center;">
                        <?php if($pos['active_applicants'] > 0){ ?>
                            <span class="badge bg-primary" style="font-size:11px;">
                                <?= $pos['active_applicants']; ?> active
                            </span>
                        <?php } else { ?>
                            <span style="font-size:12px;color:#9ca3af;">—</span>
                        <?php } ?>
                    </td>
                    <td>
                        <?php
                            $badgeClass = 'badge-open';
                            if($pos['status'] == 'Closed')  $badgeClass = 'badge-closed';
                            if($pos['status'] == 'On Hold') $badgeClass = 'badge-onhold';
                        ?>
                        <span class="job-badge <?= $badgeClass; ?>"><?= $pos['status']; ?></span>
                    </td>
                    <td style="font-size:12px;color:#6b7280;">
                        <?= date('M d, Y', strtotime($pos['created_at'])); ?>
                    </td>
                    <td class="table-actions text-center">
                        <button class="action-btn btn-view" title="View Details"
                                onclick='viewJob(<?= json_encode($pos); ?>)'>
                            <i class="bi bi-eye-fill"></i>
                        </button>
                        <button class="action-btn btn-edit" title="Edit"
                                onclick='editJob(<?= json_encode($pos); ?>)'>
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                        <button class="action-btn btn-status" title="Change Status"
                                onclick="changeStatus(<?= $pos['position_id']; ?>, '<?= $pos['status']; ?>')">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                        <button class="action-btn btn-delete" title="Delete"
                                onclick="deleteJob(<?= $pos['position_id']; ?>, '<?= htmlspecialchars($pos['position_name'], ENT_QUOTES); ?>')">
                            <i class="bi bi-trash-fill"></i>
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
     ADD / EDIT MODAL
============================================================= -->
<div class="modal fade" id="jobModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border:none;border-radius:12px;overflow:hidden;">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title" id="jobModalTitle">
                    <i class="bi bi-briefcase-fill me-2"></i>Add Job Posting
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:24px;">
                <form id="jobForm">
                    <input type="hidden" name="action" id="formAction" value="create_job">
                    <input type="hidden" name="position_id" id="formPositionId" value="">

                    <div class="row g-3">
                        <!-- Position Name -->
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:12px;font-weight:600;color:#374151;">
                                Position Name <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="position_name" id="positionName" required
                                    style="border-radius:8px;font-size:13px;">
                                <option value="">Pick a department first</option>
                            </select>
                        </div>

                        <!-- Department -->
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:12px;font-weight:600;color:#374151;">
                                Department <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="department_id" id="departmentId" required
                                    style="border-radius:8px;font-size:13px;">
                                <option value="">Select Department</option>
                                <?php foreach($deptList as $dept){ ?>
                                    <option value="<?= $dept['department_id']; ?>">
                                        <?= htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <!-- Employment Type -->
                        <div class="col-md-4">
                            <label class="form-label" style="font-size:12px;font-weight:600;color:#374151;">
                                Employment Type <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="employment_type" id="employmentType" required
                                    style="border-radius:8px;font-size:13px;">
                                <option value="Full-time">Full-time</option>
                                <option value="Part-time">Part-time</option>
                                <option value="Contractual">Contractual</option>
                                <option value="Probationary">Probationary</option>
                            </select>
                        </div>

                        <!-- Slots -->
                        <div class="col-md-4">
                            <label class="form-label" style="font-size:12px;font-weight:600;color:#374151;">
                                Slots <span class="text-danger">*</span>
                            </label>
                            <input type="number" class="form-control" name="slots" id="slots"
                                   required min="1" value="1"
                                   style="border-radius:8px;font-size:13px;">
                        </div>

                        <!-- Status -->
                        <div class="col-md-4">
                            <label class="form-label" style="font-size:12px;font-weight:600;color:#374151;">
                                Status
                            </label>
                            <select class="form-select" name="status" id="jobStatus"
                                    style="border-radius:8px;font-size:13px;">
                                <option value="Open">Open</option>
                                <option value="Closed">Closed</option>
                                <option value="On Hold">On Hold</option>
                            </select>
                        </div>

                        <!-- Salary Min -->
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:12px;font-weight:600;color:#374151;">
                                Salary Minimum (₱)
                            </label>
                            <input type="number" class="form-control" name="salary_min" id="salaryMin"
                                   min="0" step="0.01" value="0"
                                   style="border-radius:8px;font-size:13px;">
                        </div>

                        <!-- Salary Max -->
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:12px;font-weight:600;color:#374151;">
                                Salary Maximum (₱)
                            </label>
                            <input type="number" class="form-control" name="salary_max" id="salaryMax"
                                   min="0" step="0.01" value="0"
                                   style="border-radius:8px;font-size:13px;">
                        </div>

                        <!-- Requirements -->
                        <div class="col-12">
                            <label class="form-label" style="font-size:12px;font-weight:600;color:#374151;">
                                Requirements / Job Description
                            </label>
                            <textarea class="form-control" name="requirements" id="requirements"
                                      rows="4" placeholder="List the qualifications, responsibilities, and requirements for this position..."
                                      style="border-radius:8px;font-size:13px;resize:vertical;"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:14px 24px;">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal"
                        style="border-radius:8px;font-weight:600;">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="submitJob()"
                        id="btnSubmitJob" style="border-radius:8px;font-weight:600;">
                    <i class="bi bi-check-lg me-1"></i>Save Job Posting
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     VIEW DETAILS MODAL
============================================================= -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border:none;border-radius:12px;overflow:hidden;">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title">
                    <i class="bi bi-eye-fill me-2"></i>Job Posting Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:24px;">

                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h4 id="viewPositionName" style="font-weight:700;color:#1a3c5e;margin-bottom:4px;"></h4>
                        <span id="viewDepartment" style="font-size:13px;color:#6b7280;"></span>
                    </div>
                    <span id="viewStatusBadge" class="job-badge" style="font-size:13px;"></span>
                </div>

                <hr style="border-color:#e5e7eb;">

                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="detail-label">Employment Type</div>
                        <div class="detail-value" id="viewType"></div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-label">Available Slots</div>
                        <div class="detail-value" id="viewSlots"></div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-label">Active Applicants</div>
                        <div class="detail-value" id="viewApplicants"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Salary Range</div>
                        <div class="detail-value" id="viewSalary" style="color:#059669;font-weight:700;"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Date Posted</div>
                        <div class="detail-value" id="viewDate"></div>
                    </div>
                </div>

                <div class="detail-label">Requirements / Job Description</div>
                <div class="requirements-box" id="viewRequirements">
                    —
                </div>

            </div>
            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:14px 24px;">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal"
                        style="border-radius:8px;font-weight:600;">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
/*====================================================
    FIX: Bootstrap modal steals focus from SweetAlert2
    inputs rendered on top of it (password prompts).
====================================================*/
$('#jobModal').on('shown.bs.modal', function () {
    $(document).off('focusin.bs.modal');
});

/*====================================================
    OPEN ADD MODAL
====================================================*/
function openAddModal(){
    $('#jobModalTitle').html('<i class="bi bi-briefcase-fill me-2"></i>Add Job Posting');
    $('#btnSubmitJob').html('<i class="bi bi-check-lg me-1"></i>Save Job Posting');
    $('#formAction').val('create_job');
    $('#formPositionId').val('');
    $('#jobForm')[0].reset();
    $('#jobStatus').val('Open');
    new bootstrap.Modal(document.getElementById('jobModal')).show();
}

/*====================================================
    OPEN EDIT MODAL
====================================================*/
function editJob(job){
    $('#jobModalTitle').html('<i class="bi bi-pencil-fill me-2"></i>Edit Job Posting');
    $('#btnSubmitJob').html('<i class="bi bi-check-lg me-1"></i>Update Job Posting');
    $('#formAction').val('update_job');
    $('#formPositionId').val(job.position_id);
    
    // Set department first and trigger change event to dynamically populate positions dropdown
    $('#departmentId').val(job.department_id).trigger('change');
    
    // Set position select option
    $('#positionName').val(job.position_name);

    $('#employmentType').val(job.employment_type);
    $('#slots').val(job.slots);
    $('#salaryMin').val(job.salary_min);
    $('#salaryMax').val(job.salary_max);
    $('#requirements').val(job.requirements || '');
    $('#jobStatus').val(job.status);
    new bootstrap.Modal(document.getElementById('jobModal')).show();
}

/*====================================================
    SUBMIT (CREATE / UPDATE)
====================================================*/
function submitJob(){
    let form = $('#jobForm')[0];
    if(!form.checkValidity()){
        form.reportValidity();
        return;
    }

    let salaryMin = parseFloat($('#salaryMin').val()) || 0;
    let salaryMax = parseFloat($('#salaryMax').val()) || 0;
    if(salaryMax > 0 && salaryMax < salaryMin){
        Swal.fire('Invalid Salary','Maximum salary cannot be less than minimum.','warning');
        return;
    }

    let action = $('#formAction').val();

    Swal.fire({
        title: 'Confirm Your Password',
        html: action === 'create_job'
              ? 'Enter your account password to post this job.'
              : 'Enter your account password to save these changes.',
        input: 'password',
        inputPlaceholder: 'Password',
        inputAttributes: { autocapitalize: 'off', autocorrect: 'off' },
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        confirmButtonText: 'Confirm',
        cancelButtonText: 'Cancel',
        inputValidator: (value) => {
            if(!value) return 'Password is required to proceed.';
        }
    }).then((confirmResult) => {
        if(!confirmResult.isConfirmed) return;

        let formData = $('#jobForm').serialize() + '&password=' + encodeURIComponent(confirmResult.value);
        let btnText  = action === 'create_job' ? 'Saving...' : 'Updating...';

        $('#btnSubmitJob').prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>' + btnText);

        $.ajax({
            url:  'hrms_jobs.php',
            type: 'POST',
            data: formData,
            success: function(response){
                response = response.trim();

                if(response === 'success'){
                    clearBackdrop();
                    $('#jobModal').modal('hide');
                    Swal.fire({
                        icon:  'success',
                        title: action === 'create_job' ? 'Job Posted!' : 'Job Updated!',
                        text:  action === 'create_job'
                               ? 'New position has been created successfully.'
                               : 'Position details have been updated.',
                        timer: 1800,
                        showConfirmButton: false
                    });
                    setTimeout(() => loadPage('hrms_jobs.php'), 1200);
                } else if(response === 'duplicate'){
                    Swal.fire('Duplicate','A position with the same name already exists in this department.','warning');
                } else {
                    Swal.fire('Error', response, 'error');
                }
            },
            error: function(){
                Swal.fire('Error','Failed to connect to the server.','error');
            },
            complete: function(){
                let label = action === 'create_job' ? 'Save Job Posting' : 'Update Job Posting';
                $('#btnSubmitJob').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>' + label);
            }
        });
    });
}

/*====================================================
    VIEW DETAILS
====================================================*/
function viewJob(job){
    $('#viewPositionName').text(job.position_name);
    $('#viewDepartment').html('<i class="bi bi-diagram-3 me-1"></i>' + (job.department_name || 'N/A'));
    $('#viewType').text(job.employment_type);
    $('#viewSlots').text(job.slots);
    $('#viewApplicants').html(
        job.active_applicants > 0
        ? '<span class="badge bg-primary">' + job.active_applicants + ' in pipeline</span>'
        : '<span style="color:#9ca3af;">None</span>'
    );
    $('#viewSalary').text('₱' + Number(job.salary_min).toLocaleString() + ' – ₱' + Number(job.salary_max).toLocaleString());

    // Format date
    let d = new Date(job.created_at);
    let dateStr = d.toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
    $('#viewDate').text(dateStr);

    // Requirements
    $('#viewRequirements').text(job.requirements || 'No requirements listed.');

    // Status badge
    let badgeClass = 'badge-open';
    if(job.status === 'Closed')  badgeClass = 'badge-closed';
    if(job.status === 'On Hold') badgeClass = 'badge-onhold';
    $('#viewStatusBadge').attr('class', 'job-badge ' + badgeClass).text(job.status);

    new bootstrap.Modal(document.getElementById('viewModal')).show();
}

/*====================================================
    CHANGE STATUS
====================================================*/
function changeStatus(positionId, currentStatus){
    let options = {};
    if(currentStatus !== 'Open')    options['Open']    = 'Open — Actively hiring';
    if(currentStatus !== 'Closed')  options['Closed']  = 'Closed — Position filled';
    if(currentStatus !== 'On Hold') options['On Hold'] = 'On Hold — Temporarily paused';

    Swal.fire({
        title: 'Change Status',
        html: '<p style="font-size:13px;color:#6b7280;margin-bottom:12px;">Current status: <strong>' + currentStatus + '</strong></p>',
        input: 'select',
        inputOptions: options,
        inputPlaceholder: 'Select new status',
        showCancelButton: true,
        confirmButtonText: 'Next',
        confirmButtonColor: '#2563eb',
        inputValidator: (value) => {
            if(!value) return 'Please select a status.';
        }
    }).then(result => {
        if(!result.isConfirmed) return;
        let newStatus = result.value;

        Swal.fire({
            title: 'Confirm Your Password',
            html: 'Enter your account password to change status to <strong>' + newStatus + '</strong>.',
            input: 'password',
            inputPlaceholder: 'Password',
            inputAttributes: { autocapitalize: 'off', autocorrect: 'off' },
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            inputValidator: (value) => {
                if(!value) return 'Password is required to proceed.';
            }
        }).then(pwResult => {
            if(!pwResult.isConfirmed) return;

            $.ajax({
                url:  'hrms_jobs.php',
                type: 'POST',
                data: {
                    action: 'change_status',
                    position_id: positionId,
                    new_status: newStatus,
                    password: pwResult.value
                },
                success: function(response){
                    response = response.trim();
                    if(response === 'success'){
                        Swal.fire({
                            icon: 'success',
                            title: 'Status Updated',
                            text: 'Position status changed to ' + newStatus + '.',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        setTimeout(() => loadPage('hrms_jobs.php'), 1200);
                    } else {
                        Swal.fire('Error', response, 'error');
                    }
                }
            });
        });
    });
}

/*====================================================
    DELETE JOB
====================================================*/
function deleteJob(positionId, positionName){
    Swal.fire({
        title: 'Delete Position?',
        html: 'Are you sure you want to delete <strong>' + positionName + '</strong>?<br><small class="text-muted">This action cannot be undone.</small>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'Cancel'
    }).then(result => {
        if(result.isConfirmed){
            $.ajax({
                url:  'hrms_jobs.php',
                type: 'POST',
                data: {
                    action: 'delete_job',
                    position_id: positionId
                },
                success: function(response){
                    response = response.trim();

                    if(response === 'success'){
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: positionName + ' has been removed.',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        setTimeout(() => loadPage('hrms_jobs.php'), 1200);
                    } else if(response.startsWith('has_applicants')){
                        let count = response.split(':')[1];
                        Swal.fire({
                            icon: 'error',
                            title: 'Cannot Delete',
                            html: 'This position has <strong>' + count + ' applicant(s)</strong> linked to it.<br>Please remove or reassign them first.'
                        });
                    } else if(response.startsWith('has_employees')){
                        let count = response.split(':')[1];
                        Swal.fire({
                            icon: 'error',
                            title: 'Cannot Delete',
                            html: 'This position has <strong>' + count + ' employee(s)</strong> assigned to it.<br>Please reassign them first.'
                        });
                    } else {
                        Swal.fire('Error', response, 'error');
                    }
                }
            });
        }
    });
}

/*====================================================
    INITIALIZE DATATABLE
====================================================*/
$(document).ready(function(){
    if($.fn.DataTable && $('#jobsTable').length){
        if(!$.fn.DataTable.isDataTable('#jobsTable')){
            $('#jobsTable').DataTable({
                responsive: true,
                pageLength: 10,
                lengthChange: false,
                ordering: true,
                searching: true,
                order: [[8, 'desc']], // Sort by date posted descending
                columnDefs: [
                    { orderable: false, targets: [9] } // Disable sort on Actions column
                ]
            });
        }
    }

    // Dynamic positions selection based on chosen department
    $('#departmentId').on('change', function(){
        const deptName = $(this).find('option:selected').text().trim();
        const posSelect = $('#positionName');
        posSelect.empty();

        if(!$(this).val() || deptName === 'Select Department'){
            posSelect.append('<option value="">Pick a department first</option>');
            return;
        }

        const positions = getPositionsForDepartment(deptName);
        posSelect.append('<option value="">-- Select Position --</option>');
        positions.forEach(pos => {
            posSelect.append(`<option value="${pos}">${pos}</option>`);
        });
    });

    // Helper to fetch list of positions based on department name match
    function getPositionsForDepartment(deptName) {
        if (!deptName) return [];
        const name = deptName.toLowerCase();
        
        if (name.includes('executive') || name.includes('admin')) {
            return ["Owner", "General Manager", "Store Manager"];
        }
        if (name.includes('human') || name.includes('resource') || name.includes('hr')) {
            return ["HR Manager", "HR Officer", "HR Assistant", "Payroll Officer"];
        }
        if (name.includes('finance') || name.includes('account')) {
            return ["Finance Manager", "Accountant", "Bookkeeper", "Accounting Assistant"];
        }
        if (name.includes('sales') || name.includes('cashier') || name.includes('operation')) {
            return ["Sales Supervisor", "Senior Cashier", "Cashier", "Sales Associate"];
        }
        if (name.includes('inventory') || name.includes('warehouse')) {
            return ["Inventory Manager", "Inventory Officer", "Inventory Clerk", "Stock Controller", "Warehouse Staff"];
        }
        if (name.includes('information') || name.includes('technology') || name.includes('it')) {
            return ["System Administrator", "Database Administrator", "Software Developer", "IT Support Specialist"];
        }
        
        return ["General Staff"];
    }
});
</script>
