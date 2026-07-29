<?php
require_once '../Model/database.php';
require_once '../Controller/HRMSController.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$admin_id = $_SESSION['user_id'] ?? 1;

define('EMPLOYEE_UPLOAD_DIR', __DIR__ . '/uploads/employees/');
define('EMPLOYEE_UPLOAD_URL', 'uploads/employees/');

if (!is_dir(EMPLOYEE_UPLOAD_DIR)) {
    mkdir(EMPLOYEE_UPLOAD_DIR, 0755, true);
}

$hrmsController = new HRMSController($conn);

function getInitials($name) {
    global $hrmsController;
    return $hrmsController->getInitials($name);
}

/*=========================================================
    ACTIONS (POST)
==========================================================*/

// CREATE
if (isset($_POST['action']) && $_POST['action'] == 'create') {
    $result = $hrmsController->createEmployee($_POST, $_FILES, $admin_id, EMPLOYEE_UPLOAD_DIR);
    ob_clean();
    echo $result;
    exit();
}

// UPDATE
if (isset($_POST['action']) && $_POST['action'] == 'update') {
    $result = $hrmsController->updateEmployee($_POST, $_FILES, $admin_id, EMPLOYEE_UPLOAD_DIR);
    ob_clean();
    echo $result;
    exit();
}

// RENEW CONTRACT
if (isset($_POST['action']) && $_POST['action'] == 'renew_contract') {
    $result = $hrmsController->renewContract($_POST, $admin_id);
    ob_clean();
    echo $result;
    exit();
}

// CHANGE STATUS
if (isset($_POST['action']) && $_POST['action'] == 'change_status') {
    $result = $hrmsController->changeEmployeeStatus($_POST['employee_id'], $_POST['status'], $admin_id);
    ob_clean();
    echo $result;
    exit();
}

// DELETE — archives employee then soft-deletes
if (isset($_POST['action']) && $_POST['action'] == 'delete') {
    $result = $hrmsController->terminateEmployee($_POST, $admin_id);
    ob_clean();
    echo $result;
    exit();
}

// RESTORE EMPLOYEE FROM ARCHIVE
if (isset($_POST['action']) && $_POST['action'] == 'restore_employee') {
    $result = $hrmsController->restoreEmployeeFromArchive($_POST['archive_id'], $admin_id);
    ob_clean();
    echo $result;
    exit();
}

// GET RENEWAL HISTORY (AJAX)
if (isset($_GET['action']) && $_GET['action'] == 'get_renewal_history') {
    ob_clean();
    $id = (int) ($_GET['employee_id'] ?? 0);
    $historyQuery = $hrmsController->getContractRenewalHistory($id);
    $rows = [];
    while ($row = mysqli_fetch_assoc($historyQuery)) {
        $rows[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode($rows);
    exit();
}

// RESET PORTAL PASSWORD
if (isset($_POST['action']) && $_POST['action'] == 'reset_portal_password') {
    $result = $hrmsController->resetPortalPassword($_POST, $admin_id);
    ob_clean();
    echo $result;
    exit();
}

/*=========================================================
    FETCH DATA
==========================================================*/
$employees = $hrmsController->getEmployeesList();
$empList = [];
while ($row = mysqli_fetch_assoc($employees)) {
    $empList[] = $row;
}

$positions = $hrmsController->getPositionsList();
$positionList = [];
while ($p = mysqli_fetch_assoc($positions)) {
    $positionList[] = $p;
}

$deptsResult = $hrmsController->getDepartmentsList();
$departmentList = [];
while ($dept = mysqli_fetch_assoc($deptsResult)) {
    $departmentList[] = $dept;
}

// Stats metrics
$totalCount = count($empList);
$activeCount = 0;
$inactiveCount = 0;
$totalSalary = 0.0;
foreach ($empList as $e) {
    if ($e['status'] == 'Active') {
        $activeCount++;
        $totalSalary += (float) $e['basic_salary'];
    } else {
        $inactiveCount++;
    }
}
?>

<style>
    /* Custom Styles for Employees Module */
    .page-card {
        background: white;
        border-radius: 14px;
        padding: 22px 24px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, .06);
        margin-bottom: 22px;
    }

    .stat-card {
        background: white;
        border-radius: 14px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, .06);
        height: 100%;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: white;
        flex-shrink: 0;
    }

    .stat-label {
        font-size: 11px;
        color: #6c757d;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-value {
        font-size: 24px;
        font-weight: 800;
        line-height: 1.2;
        margin-top: 4px;
    }

    /* Status Badges */
    .badge-status {
        font-size: 11px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 20px;
    }

    .badge-active {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-inactive {
        background: #f3f4f6;
        color: #374151;
    }

    .badge-resigned {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-terminated {
        background: #fee2e2;
        color: #991b1b;
    }

    /* Profile View Card inside Modal */
    .profile-modal-header {
        background: linear-gradient(135deg, #1a3c5e, #2b5c8f);
        color: white;
        padding: 30px 24px;
        border-top-left-radius: .4rem;
        border-top-right-radius: .4rem;
    }

    .profile-avatar-large {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 4px solid rgba(255, 255, 255, 0.25);
        background: #2563eb;
        color: white;
        font-weight: 800;
        font-size: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        object-fit: cover;
    }

    .profile-meta-item {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.85);
    }

    .info-section-title {
        font-size: 12px;
        font-weight: 700;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 6px;
        margin-bottom: 12px;
    }

    /* ID Card Styles */
    .id-card-wrapper {
        padding: 10px;
        background: transparent;
    }

    .id-card {
        width: 320px;
        height: 480px;
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        position: relative;
        border: 1px solid #e0e0e0;
    }

    .id-card-header {
        background: linear-gradient(135deg, #1a3c5e 0%, #2b5c8f 100%);
        color: white;
        padding: 18px 15px;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        border-bottom: 4px solid #2563eb;
    }

    .id-card-logo {
        font-size: 24px;
        margin-bottom: 4px;
    }

    .id-card-company-name {
        font-size: 14px;
        font-weight: 800;
        letter-spacing: 2px;
        text-transform: uppercase;
    }

    .id-card-body {
        flex-grow: 1;
        padding: 24px 20px;
        display: flex;
        flex-direction: column;
        align-items: center;
        background: radial-gradient(circle at 50% 50%, #ffffff 0%, #f9fafb 100%);
    }

    .id-pictures-row {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 24px;
        margin-bottom: 25px;
        width: 100%;
    }

    .id-photo-container {
        width: 100px;
        height: 100px;
        border-radius: 12px;
        overflow: hidden;
        border: 3px solid #2563eb;
        background: #f3f4f6;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    .id-photo {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .id-initials {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #2563eb;
        color: white;
        font-size: 32px;
        font-weight: 800;
    }

    .id-qr-container {
        width: 100px;
        height: 100px;
        border-radius: 12px;
        overflow: hidden;
        border: 3px solid #e5e7eb;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 6px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }

    #id_card_qrcode img {
        width: 100% !important;
        height: 100% !important;
    }

    .id-info-container {
        text-align: center;
        width: 100%;
    }

    .id-name {
        font-size: 20px;
        font-weight: 800;
        color: #111827;
        margin-bottom: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .id-role {
        font-size: 13px;
        font-weight: 700;
        color: #2563eb;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 2px;
    }

    .id-department {
        font-size: 11px;
        color: #6b7280;
        margin-bottom: 18px;
        font-weight: 500;
    }

    .id-number-badge {
        display: inline-flex;
        align-items: center;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        padding: 6px 14px;
        border-radius: 30px;
        font-family: monospace;
        font-size: 14px;
    }

    .id-number-badge .label {
        color: #6b7280;
        font-weight: 600;
        margin-right: 6px;
    }

    .id-number-badge .value {
        color: #111827;
        font-weight: 800;
    }

    .id-card-footer {
        background: #f9fafb;
        border-top: 1px dashed #e5e7eb;
        padding: 12px;
        text-align: center;
        font-size: 9px;
        font-weight: 700;
        color: #9ca3af;
        letter-spacing: 1px;
    }
</style>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:22px;font-weight:700;color:#1e293b;">Employees</h2>
        <p style="color:#6b7280;font-size:13px;margin:0;">Manage workforce details, salaries, and status.</p>
    </div>
    <div class="d-flex gap-2">
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle fw-semibold" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-printer-fill me-1"></i>Batch Print <span class="badge bg-primary text-white ms-1" id="batchCountBadge" style="display:none;">0</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                <li>
                    <a class="dropdown-item py-2 fw-semibold" href="#" onclick="printBatchIDs(); return false;">
                        <i class="bi bi-person-badge text-primary me-2 fs-6"></i>Print Selected Employee IDs
                    </a>
                </li>
                <li>
                    <a class="dropdown-item py-2 fw-semibold" href="#" onclick="printBatchContracts(); return false;">
                        <i class="bi bi-file-earmark-text text-success me-2 fs-6"></i>Print Selected Contracts
                    </a>
                </li>
            </ul>
        </div>
        <button class="btn btn-outline-secondary" onclick="openArchiveModal()">
            <i class="bi bi-archive-fill me-1"></i>Archive
            <?php
            $archCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM employees_archive"))['c'];
            if ($archCount > 0)
                echo '<span class="badge bg-danger ms-1">' . $archCount . '</span>';
            ?>
        </button>
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="bi bi-plus-lg me-1"></i>Add Employee
        </button>
    </div>
</div>

<!-- STATS -->
<div class="row g-3 mb-4 animate__animated animate__fadeIn">
    <div class="col-md-3">
        <div class="stat-card border-start border-primary border-4">
            <div>
                <div class="stat-label">Total Workforce</div>
                <div class="stat-value"><?= $totalCount; ?></div>
            </div>
            <div class="stat-icon bg-primary"><i class="bi bi-people-fill"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card border-start border-success border-4">
            <div>
                <div class="stat-label">Active Employees</div>
                <div class="stat-value"><?= $activeCount; ?></div>
            </div>
            <div class="stat-icon bg-success"><i class="bi bi-person-check-fill"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card border-start border-secondary border-4">
            <div>
                <div class="stat-label">Inactive/Resigned</div>
                <div class="stat-value"><?= $inactiveCount; ?></div>
            </div>
            <div class="stat-icon bg-secondary"><i class="bi bi-person-x-fill"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card border-start border-info border-4">
            <div>
                <div class="stat-label">Total Active Salary</div>
                <div class="stat-value">₱<?= number_format($totalSalary, 2); ?></div>
            </div>
            <div class="stat-icon bg-info"><i class="bi bi-cash-stack"></i></div>
        </div>
    </div>
</div>

<!-- TABLE GRID -->
<div class="page-card animate__animated animate__fadeIn">
    <div class="table-responsive">
        <table class="table table-hover align-middle datatable w-100" id="employeesTable">
            <thead class="table-dark">
                <tr>
                    <th style="width: 40px;" class="text-center">
                        <input type="checkbox" class="form-check-input" id="selectAllEmps" onclick="toggleSelectAllEmployees(this)" title="Select All Employees">
                    </th>
                    <th style="width: 55px;" class="text-center">Photo</th>
                    <th>Employee</th>
                    <th>Position &amp; Department</th>
                    <th>Employment &amp; Salary</th>
                    <th>Contract Term</th>
                    <th>Status</th>
                    <th style="width: 150px;" class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($empList as $row) {
                    $badgeClass = 'badge-active';
                    if ($row['status'] == 'Inactive')
                        $badgeClass = 'badge-inactive';
                    elseif ($row['status'] == 'Resigned')
                        $badgeClass = 'badge-resigned';
                    elseif ($row['status'] == 'Terminated')
                        $badgeClass = 'badge-terminated';

                    $empJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input emp-checkbox" data-emp="<?= $empJson; ?>" onchange="updateBatchBtnState()">
                        </td>
                        <td class="text-center">
                            <?php if (!empty($row['photo']) && file_exists(EMPLOYEE_UPLOAD_DIR . $row['photo'])) { ?>
                                <img src="<?= EMPLOYEE_UPLOAD_URL . $row['photo']; ?>?t=<?= time(); ?>"
                                    class="rounded-circle shadow-sm"
                                    style="width:38px;height:38px;object-fit:cover;border: 1.5px solid #ddd;">
                            <?php } else { ?>
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto shadow-sm"
                                    style="width:38px;height:38px;font-size:12px;font-weight:700;">
                                    <?= getInitials($row['full_name']); ?>
                                </div>
                            <?php } ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-dark-subtle text-dark border font-monospace"
                                    style="font-size:11px;"><?= htmlspecialchars($row['employee_no']); ?></span>
                                <a href="#" class="fw-bold text-dark text-decoration-none btn-view-emp"
                                    data-emp="<?= $empJson; ?>">
                                    <?= htmlspecialchars($row['full_name']); ?>
                                </a>
                            </div>
                            <div class="text-muted small mt-1" style="font-size:11px;">
                                <i class="bi bi-telephone-fill me-1"></i><?= htmlspecialchars($row['phone'] ?: 'N/A'); ?>
                                <?php if (!empty($row['email'])) { ?>
                                    &bull; <i class="bi bi-envelope-fill me-1"></i><?= htmlspecialchars($row['email']); ?>
                                <?php } ?>
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">
                                <?= htmlspecialchars($row['position_name'] ?: 'Unassigned'); ?>
                            </div>
                            <span class="badge bg-secondary-subtle text-dark border border-secondary-subtle fw-medium mt-1"
                                style="font-size:10px;">
                                <i
                                    class="bi bi-building me-1 text-secondary"></i><?= htmlspecialchars($row['department_name'] ?? 'General Operations'); ?>
                            </span>
                        </td>
                        <td>
                            <div class="fw-bold text-success">₱<?= number_format($row['basic_salary'], 2); ?></div>
                            <span class="badge bg-light text-dark border mt-1"
                                style="font-size:10px;"><?= htmlspecialchars($row['employment_type'] ?: 'Full-time'); ?></span>
                        </td>
                        <td>
                            <?php if (!empty($row['contract_start']) && !empty($row['contract_end'])) { ?>
                                <span
                                    class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold d-inline-block"
                                    style="font-size:11px;" title="Contract Period">
                                    <i
                                        class="bi bi-file-earmark-check me-1"></i><?= date('M d, Y', strtotime($row['contract_start'])); ?>
                                    &ndash; <?= date('M d, Y', strtotime($row['contract_end'])); ?>
                                </span>
                                <?php if (!empty($row['renewal_count']) && $row['renewal_count'] > 0) { ?>
                                    <br><span
                                        class="badge bg-success-subtle text-success border border-success-subtle fw-semibold mt-1"
                                        style="font-size:10px;">
                                        <i class="bi bi-arrow-repeat me-1"></i>Renewed <?= $row['renewal_count']; ?>x
                                    </span>
                                <?php } ?>
                            <?php } else { ?>
                                <span class="badge bg-light text-muted border">6 Months</span>
                            <?php } ?>
                        </td>
                        <td>
                            <span class="badge-status <?= $badgeClass; ?>"><?= htmlspecialchars($row['status']); ?></span>
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <!-- VIEW PROFILE -->
                                <button class="btn btn-sm btn-outline-info btn-view-emp"
                                    data-emp="<?= $empJson; ?>"
                                    title="View Full Profile">
                                    <i class="bi bi-eye-fill me-1"></i> View
                                </button>
                                <!-- EDIT -->
                                <button class="btn btn-sm btn-outline-warning btn-edit-emp"
                                    data-emp="<?= $empJson; ?>"
                                    title="Edit Employee">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                                <!-- DELETE / ARCHIVE -->
                                <button class="btn btn-sm btn-outline-danger"
                                    onclick="deleteEmployee(<?= $row['employee_id']; ?>, '<?= addslashes($row['full_name']); ?>')"
                                    title="Remove Employee">
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
    VIEW PROFILE MODAL
==========================================================-->
<!-- EMPLOYEES ARCHIVE MODAL -->
<div class="modal fade" id="archiveModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-archive-fill me-2"></i>Deleted Employees Archive</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php
                $archived = mysqli_query($conn, "SELECT * FROM employees_archive ORDER BY deleted_at DESC");
                if (mysqli_num_rows($archived) == 0) {
                    echo '<div class="text-center text-muted py-5"><i class="bi bi-archive" style="font-size:40px;"></i><p class="mt-3">No archived employees yet.</p></div>';
                } else {
                    ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle table-sm mb-0">
                            <thead class="table-secondary">
                                <tr>
                                    <th>#</th>
                                    <th>Employee No</th>
                                    <th>Full Name</th>
                                    <th>Position</th>
                                    <th>Department</th>
                                    <th>Email</th>
                                    <th>Salary</th>
                                    <th>Reason</th>
                                    <th>Deleted On</th>
                                    <th style="width: 110px;" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $ai = 1;
                                while ($ar = mysqli_fetch_assoc($archived)) { ?>
                                    <tr>
                                        <td class="text-center fw-semibold"><?= $ai++; ?></td>
                                        <td><span
                                                class="badge bg-dark-subtle text-dark border font-monospace"><?= htmlspecialchars($ar['employee_no']); ?></span>
                                        </td>
                                        <td class="fw-semibold text-dark"><?= htmlspecialchars($ar['full_name']); ?></td>
                                        <td><?= htmlspecialchars($ar['position_name'] ?? '—'); ?></td>
                                        <td><?= htmlspecialchars($ar['department_name'] ?? '—'); ?></td>
                                        <td><?= htmlspecialchars($ar['email'] ?? '—'); ?></td>
                                        <td class="fw-bold text-success">₱<?= number_format($ar['basic_salary'], 2); ?></td>
                                        <td><small
                                                class="text-muted"><?= htmlspecialchars($ar['deleted_reason'] ?? 'Archived'); ?></small>
                                        </td>
                                        <td><?= date('M d, Y h:i A', strtotime($ar['deleted_at'])); ?></td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-success"
                                                onclick="restoreEmployee(<?= $ar['archive_id']; ?>, '<?= htmlspecialchars(addslashes($ar['full_name']), ENT_QUOTES); ?>')"
                                                title="Restore Employee to Active Workforce">
                                                <i class="bi bi-arrow-counterclockwise me-1"></i> Restore
                                            </button>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="profile-modal-header d-flex align-items-center gap-3">
                <div id="v_avatar_container"></div>
                <div class="flex-grow-1">
                    <h4 class="fw-bold mb-1" id="v_name"></h4>
                    <div id="v_emp_no" class="badge bg-white text-dark fw-bold mb-1"></div>
                    <div class="profile-meta-item">
                        <i class="bi bi-briefcase-fill me-1"></i><span id="v_job_title"></span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white align-self-start"
                    data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <!-- PERSONAL DETAILS -->
                    <div class="col-md-6">
                        <div class="info-section-title">Personal Details</div>
                        <table class="table table-sm table-borderless fs-7 mb-0">
                            <tr>
                                <th class="text-muted fw-normal" style="width: 120px;">Birthdate:</th>
                                <td class="fw-semibold" id="v_birthdate"></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Gender:</th>
                                <td class="fw-semibold" id="v_gender"></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Civil Status:</th>
                                <td class="fw-semibold" id="v_civil"></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Phone:</th>
                                <td class="fw-semibold" id="v_phone"></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Email:</th>
                                <td class="fw-semibold" id="v_email"></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Address:</th>
                                <td class="fw-semibold" id="v_address"></td>
                            </tr>
                        </table>
                    </div>

                    <!-- EMPLOYMENT DETAILS -->
                    <div class="col-md-6">
                        <div class="info-section-title">Employment & Payroll</div>
                        <table class="table table-sm table-borderless fs-7 mb-0">
                            <tr>
                                <th class="text-muted fw-normal" style="width: 120px;">Date Hired:</th>
                                <td class="fw-semibold" id="v_hired"></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Employment Type:</th>
                                <td class="fw-semibold"><span class="badge bg-light text-dark border"
                                        id="v_type"></span></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Basic Salary:</th>
                                <td class="fw-semibold text-success" id="v_salary"></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Contract Term:</th>
                                <td class="fw-semibold"><span class="badge bg-primary text-white" id="v_contract_term">6
                                        Months</span></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Contract Dates:</th>
                                <td class="fw-semibold" id="v_contract_dates"></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Contract Status:</th>
                                <td class="fw-semibold" id="v_contract_status"></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Current Status:</th>
                                <td class="fw-semibold" id="v_status_badge"></td>
                            </tr>
                        </table>
                    </div>

                    <!-- STATUTORY INFORMATION -->
                    <div class="col-12 mt-2">
                        <div class="info-section-title">Statutory Details &amp; Government IDs</div>
                        <div class="row text-center g-2">
                            <div class="col-6 col-sm-3">
                                <div class="p-2 border rounded bg-light">
                                    <small class="text-muted d-block">SSS Number</small>
                                    <span class="fw-bold text-dark" id="v_sss"></span>
                                </div>
                            </div>
                            <div class="col-6 col-sm-3">
                                <div class="p-2 border rounded bg-light">
                                    <small class="text-muted d-block">PhilHealth ID</small>
                                    <span class="fw-bold text-dark" id="v_philhealth"></span>
                                </div>
                            </div>
                            <div class="col-6 col-sm-3">
                                <div class="p-2 border rounded bg-light">
                                    <small class="text-muted d-block">Pag-IBIG ID</small>
                                    <span class="fw-bold text-dark" id="v_pagibig"></span>
                                </div>
                            </div>
                            <div class="col-6 col-sm-3">
                                <div class="p-2 border rounded bg-light">
                                    <small class="text-muted d-block">TIN Number</small>
                                    <span class="fw-bold text-dark" id="v_tin"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- QUICK ACTIONS -->
                    <div class="col-12 mt-3 pt-3 border-top">
                        <small class="text-muted d-block text-uppercase fw-semibold mb-2"
                            style="font-size: 10px; letter-spacing: 0.5px;">Employee Quick Actions</small>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-success"
                                onclick="triggerProfileRenew()">
                                <i class="bi bi-arrow-repeat me-1"></i> Renew Contract
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info"
                                onclick="triggerProfileContract()">
                                <i class="bi bi-file-earmark-text-fill me-1"></i> View 6-Month Contract
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                onclick="triggerProfileIDCard()">
                                <i class="bi bi-qr-code me-1"></i> Generate ID Card
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="triggerProfileEdit()">
                                <i class="bi bi-pencil-fill me-1"></i> Edit Details
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close Profile</button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    EMPLOYEE ID CARD MODAL WITH QR CODE
==========================================================-->
<div class="modal fade" id="idCardModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header" style="background:#1a3c5e;color:white;">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-card-image me-2"></i>Employee ID Card
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 d-flex justify-content-center align-items-center bg-light">
                <div id="idCardPrintArea" class="id-card-wrapper">
                    <div class="id-card animate__animated animate__fadeIn">
                        <div class="id-card-header">
                            <div class="id-card-logo">🏪</div>
                            <div class="id-card-company-name">O-Cart!</div>
                        </div>
                        <div class="id-card-body">
                            <div class="id-pictures-row">
                                <div class="id-photo-container">
                                    <img id="id_card_photo" src="" class="id-photo" alt="Employee Photo"
                                        style="display:none;">
                                    <div id="id_card_initials" class="id-initials" style="display:none;"></div>
                                </div>
                                <div class="id-qr-container">
                                    <div id="id_card_qrcode"></div>
                                </div>
                            </div>
                            <div class="id-info-container">
                                <h3 id="id_card_name" class="id-name"></h3>
                                <div id="id_card_role" class="id-role"></div>
                                <div id="id_card_dept" class="id-department"></div>
                                <div class="id-number-badge mb-2">
                                    <span class="label">EMP ID:</span>
                                    <span id="id_card_emp_no" class="value"></span>
                                </div>
                            </div>
                        </div>
                        <div class="id-card-footer">
                            AUTHORIZED SIGNATURE
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-success btn-sm" onclick="downloadIDCard()">
                    <i class="bi bi-download me-1"></i> Download PNG
                </button>
                <button class="btn btn-primary btn-sm" onclick="printIDCard()">
                    <i class="bi bi-printer-fill me-1"></i> Print ID Card
                </button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    EMPLOYEE CONTRACT VIEW MODAL
==========================================================-->
<div class="modal fade" id="employeeContractModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-file-earmark-check-fill me-2"></i>6-Month Employment Contract
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light-subtle">
                <div id="contractPrintArea" class="bg-white p-4 border rounded shadow-sm">
                    <!-- Contract Header -->
                    <div class="text-center pb-3 mb-3 border-bottom">
                        <div class="fs-3">🏪</div>
                        <h4 class="fw-bold text-dark mb-0" style="letter-spacing:1px;">O-CART! STORE MANAGEMENT</h4>
                        <div class="text-muted small">Human Resource Department &bull; Employment Contract Agreement
                        </div>
                    </div>

                    <!-- Contract Title -->
                    <div class="text-center mb-4">
                        <h5 class="fw-bold text-primary text-uppercase" style="letter-spacing:1px;">6-MONTH FIXED-TERM
                            EMPLOYMENT CONTRACT</h5>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1">Valid
                            &amp; Active Contract</span>
                    </div>

                    <!-- Employee & Contract Details -->
                    <div class="row g-3 mb-4 p-3 bg-light rounded border">
                        <div class="col-md-6">
                            <small class="text-muted d-block text-uppercase fw-semibold"
                                style="font-size:10px;">Employee Name</small>
                            <span class="fw-bold text-dark fs-6" id="c_emp_name"></span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block text-uppercase fw-semibold"
                                style="font-size:10px;">Employee No.</small>
                            <span class="fw-bold text-secondary fs-6" id="c_emp_no"></span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block text-uppercase fw-semibold"
                                style="font-size:10px;">Position / Designation</small>
                            <span class="fw-semibold text-dark" id="c_emp_position"></span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block text-uppercase fw-semibold"
                                style="font-size:10px;">Employment Type</small>
                            <span class="badge bg-light text-dark border" id="c_emp_type"></span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block text-uppercase fw-semibold"
                                style="font-size:10px;">Contract Duration</small>
                            <span class="fw-bold text-primary" id="c_contract_duration">6 Months</span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block text-uppercase fw-semibold"
                                style="font-size:10px;">Contract Validity Period</small>
                            <span class="fw-semibold text-dark" id="c_contract_period"></span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size:10px;">Basic
                                Monthly Salary</small>
                            <span class="fw-bold text-success" id="c_emp_salary"></span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size:10px;">Work
                                Schedule &amp; Rest Day</small>
                            <span class="fw-semibold text-dark" id="c_emp_schedule"></span>
                        </div>
                    </div>

                    <!-- Contract Clauses -->
                    <div class="mb-4" style="font-size:12.5px; line-height:1.7; color:#334155;">
                        <h6 class="fw-bold text-dark mb-2">Terms &amp; Stipulations:</h6>
                        <ol class="ps-3 mb-0">
                            <li class="mb-2"><strong>Period of Employment:</strong> This Contract shall be valid for a
                                fixed term of <strong>six (6) calendar months</strong> commencing on the Start Date
                                specified above.</li>
                            <li class="mb-2"><strong>Probationary &amp; Performance Review:</strong> Prior to the
                                completion of the 6-month term, an evaluation will be conducted to assess performance,
                                attendance, and adherence to store standards for regular status eligibility.</li>
                            <li class="mb-2"><strong>Duties &amp; Responsibilities:</strong> The Employee agrees to
                                perform all assigned duties efficiently and faithfully according to company policies and
                                position guidelines.</li>
                            <li class="mb-2"><strong>Compensation &amp; Benefits:</strong> The Employee shall receive
                                basic salary and statutory benefits (SSS, PhilHealth, Pag-IBIG, 13th month pay) in
                                accordance with PH Labor Code rules.</li>
                        </ol>
                    </div>

                    <!-- Signatures Stamp -->
                    <div class="row pt-3 mt-4 border-top text-center">
                        <div class="col-6">
                            <div class="text-muted small mb-1">EMPLOYEE SIGNATURE</div>
                            <div class="fw-bold text-dark border-bottom d-inline-block px-4 pb-1" id="c_sig_employee">
                            </div>
                            <div class="text-success small mt-1" id="c_signed_badge"><i
                                    class="bi bi-check-circle-fill me-1"></i>Digitally Signed &amp; Verified</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small mb-1">AUTHORIZED HR REPRESENTATIVE</div>
                            <div class="fw-bold text-dark border-bottom d-inline-block px-4 pb-1">O-Cart! HR Management
                            </div>
                            <div class="text-primary small mt-1"><i class="bi bi-patch-check-fill me-1"></i>Official
                                Store Record</div>
                        </div>
                    </div>

                    <!-- Renewal History Section -->
                    <div class="mt-4 pt-3 border-top" id="contract_renewal_history_wrap">
                        <h6 class="fw-bold text-success mb-2"><i class="bi bi-arrow-repeat me-1"></i>Contract Renewal
                            History</h6>
                        <div id="contract_renewal_history_body">
                            <div class="text-muted small p-2"><i class="bi bi-hourglass-split me-1"></i>Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-primary btn-sm" onclick="printContract()">
                    <i class="bi bi-printer-fill me-1"></i> Print / Download Contract
                </button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    RENEW CONTRACT MODAL
==========================================================-->
<div class="modal fade" id="renewContractModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-arrow-repeat me-2"></i>Renew Employment Contract &mdash; <span
                        id="renew_emp_name_title"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="renewContractForm">
                <input type="hidden" name="employee_id" id="renew_employee_id">
                <div class="modal-body p-4 bg-light-subtle">
                    <!-- Alert / Overview Banner -->
                    <div class="alert alert-success d-flex align-items-center gap-2 mb-3 py-2"
                        style="font-size:12.5px;">
                        <i class="bi bi-patch-check-fill fs-5"></i>
                        <div>
                            Renewing contract for <strong id="renew_emp_name"></strong> (<span id="renew_emp_no"></span>
                            &bull; <span id="renew_emp_pos"></span>).
                            <span class="badge bg-success ms-1" id="renew_count_badge"></span>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Previous Contract Expiry</label>
                            <input type="date" class="form-control bg-light" id="renew_old_end" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Renewal Duration <span
                                    class="text-danger">*</span></label>
                            <select class="form-select" name="duration_months" id="renew_duration"
                                onchange="onRenewalDurationChange()">
                                <option value="6" selected>6 Months (Standard)</option>
                                <option value="12">12 Months (1 Year)</option>
                                <option value="3">3 Months (Short Term)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">New Contract Start Date</label>
                            <input type="date" class="form-control bg-light" name="contract_start"
                                id="renew_contract_start" readonly>
                            <small class="text-muted" style="font-size:11px;"><i
                                    class="bi bi-calendar-check me-1"></i>Auto-set: 1 day after contract expiry</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">New Contract Expiry Date</label>
                            <input type="date" class="form-control bg-light" name="contract_end" id="renew_contract_end"
                                readonly>
                            <small class="text-success fw-semibold" style="font-size:11px;" id="renew_contract_hint"><i
                                    class="bi bi-clock-history me-1"></i>New 6-Month Term</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Employment Type <span
                                    class="text-danger">*</span></label>
                            <select class="form-select" name="employment_type" id="renew_emptype">
                                <option value="Full-time">Full-time</option>
                                <option value="Part-time">Part-time</option>
                                <option value="Contractual">Contractual</option>
                                <option value="Probationary">Probationary</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Basic Monthly Salary (PHP) <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" min="0" class="form-control" name="basic_salary"
                                    id="renew_salary" required>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">HR Renewal Notes / Remarks</label>
                            <textarea class="form-control" name="notes" id="renew_notes" rows="2"
                                placeholder="e.g. Contract renewed based on satisfactory 6-month performance evaluation."></textarea>
                        </div>

                        <div class="col-12">
                            <div
                                class="form-check p-3 bg-success-subtle border border-success rounded d-flex align-items-center gap-2">
                                <input class="form-check-input ms-0 me-2" type="checkbox" id="renew_confirm_check"
                                    checked style="transform:scale(1.2); cursor:pointer;">
                                <label class="form-check-label fw-bold text-success mb-0" for="renew_confirm_check"
                                    style="cursor:pointer; font-size:13px;">
                                    I confirm that the employee has agreed to and signed the Renewal Contract.
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="submitContractRenewal()">
                        <i class="bi bi-arrow-repeat me-1"></i>Confirm Contract Renewal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!--=========================================================
    ADD EMPLOYEE MODAL
==========================================================-->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header" style="background:#1a3c5e;color:white;">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-person-plus-fill me-2"></i>Add Employee
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addForm" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- PERSONAL -->
                        <div class="col-12">
                            <div class="fw-bold text-primary mb-1 uppercase"
                                style="font-size:12px;letter-spacing:.5px;">Personal Details</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" required
                                placeholder="e.g. Maria Clara">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control" name="email" placeholder="e.g. maria@gmail.com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" class="form-control" name="phone" placeholder="e.g. 0917XXXXXXX">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Birthdate</label>
                            <input type="date" class="form-control" name="birthdate">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gender</label>
                            <select class="form-select" name="gender">
                                <option value="Male">Male</option>
                                <option value="Female" selected>Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Civil Status</label>
                            <select class="form-select" name="civil_status">
                                <option value="Single" selected>Single</option>
                                <option value="Married">Married</option>
                                <option value="Widowed">Widowed</option>
                                <option value="Separated">Separated</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Address</label>
                            <input type="text" class="form-control" name="address"
                                placeholder="Complete physical address">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Profile Photo</label>
                            <input type="file" class="form-control" name="photo" accept="image/*">
                        </div>

                        <!-- PORTAL CREDENTIALS -->
                        <div class="col-12 mt-3">
                            <div class="fw-bold text-primary mb-1 uppercase"
                                style="font-size:12px;letter-spacing:.5px;">Portal Account Credentials</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Auto-Generated Portal Password</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="portal_password" id="add_portal_password"
                                    placeholder="Will be emailed to employee">
                                <button class="btn btn-outline-secondary" type="button"
                                    onclick="regenerateAddPassword()">
                                    <i class="bi bi-arrow-clockwise"></i> Generate
                                </button>
                            </div>
                            <small class="text-muted">You can edit this password. Portal login link and credentials will
                                be sent to the employee's Gmail.</small>
                        </div>

                        <!-- EMPLOYMENT -->
                        <div class="col-12 mt-3">
                            <div class="fw-bold text-primary mb-1 uppercase"
                                style="font-size:12px;letter-spacing:.5px;">Employment & Salary Details</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Position <span class="text-danger">*</span></label>
                            <select class="form-select" name="position_id" id="add_pos" required
                                onchange="autoFillAddDept(this.value)">
                                <option value="">-- Select Position --</option>
                                <?php foreach ($positionList as $p) { ?>
                                    <option value="<?= $p['position_id']; ?>"
                                        data-dept="<?= (int) ($p['department_id'] ?? 0); ?>">
                                        <?= htmlspecialchars($p['position_name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Department</label>
                            <select class="form-select" name="department_id" id="add_dept">
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departmentList as $dept) { ?>
                                    <option value="<?= $dept['department_id']; ?>">
                                        <?= htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <small class="text-muted">Auto-filled from position. You can override if needed.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Employment Type <span
                                    class="text-danger">*</span></label>
                            <select class="form-select" name="employment_type">
                                <option value="Full-time">Full-time</option>
                                <option value="Part-time">Part-time</option>
                                <option value="Contractual">Contractual</option>
                                <option value="Probationary">Probationary</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Date Hired <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_hired" required
                                value="<?= date('Y-m-d'); ?>" onchange="updateAddContractEnd()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Basic Salary (Monthly PHP) <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" class="form-control" name="basic_salary" required
                                    placeholder="0.00" min="0">
                            </div>
                        </div>

                        <!-- CONTRACT (6 MONTHS) -->
                        <div class="col-12 mt-3">
                            <div class="fw-bold text-primary mb-1 uppercase"
                                style="font-size:12px;letter-spacing:.5px;">Employment Contract Details (6 Months Term)
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contract Start Date</label>
                            <input type="date" class="form-control bg-light" name="contract_start"
                                id="add_contract_start" readonly>
                            <small class="text-muted" style="font-size:11px;">Auto-synced with Date Hired</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contract End Date (6 Months)</label>
                            <input type="date" class="form-control bg-light" name="contract_end" id="add_contract_end"
                                readonly>
                            <small class="text-primary fw-semibold" style="font-size:11px;" id="add_contract_hint"><i
                                    class="bi bi-clock-history me-1"></i>Good for 6 months</small>
                        </div>
                        <div class="col-12">
                            <div class="form-check p-2 bg-light border rounded ms-1">
                                <input class="form-check-input" type="checkbox" name="contract_signed"
                                    id="add_contract_signed" value="1" checked>
                                <label class="form-check-label fw-semibold text-dark" for="add_contract_signed"
                                    style="font-size:12.5px;">
                                    6-Month Employment Contract agreed upon and signed
                                </label>
                            </div>
                        </div>

                        <!-- STATUTORY -->
                        <div class="col-12 mt-3">
                            <div class="fw-bold text-primary mb-1 uppercase"
                                style="font-size:12px;letter-spacing:.5px;">Statutory Identifications</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">SSS Number</label>
                            <input type="text" class="form-control" name="sss_no" placeholder="XX-XXXXXXX-X">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">PhilHealth ID</label>
                            <input type="text" class="form-control" name="philhealth_no" placeholder="XXXXXXXXXXXX">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Pag-IBIG ID</label>
                            <input type="text" class="form-control" name="pagibig_no" placeholder="XXXXXXXXXXXX">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">TIN Number</label>
                            <input type="text" class="form-control" name="tin_no" placeholder="XXX-XXX-XXX-000">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save
                        Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!--=========================================================
    EDIT EMPLOYEE MODAL
==========================================================-->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-pencil-square me-2"></i>Edit Employee Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editForm" enctype="multipart/form-data">
                <input type="hidden" name="employee_id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- PERSONAL -->
                        <div class="col-12">
                            <div class="fw-bold text-primary mb-1 uppercase"
                                style="font-size:12px;letter-spacing:.5px;">Personal Details</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" id="edit_name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" class="form-control" name="phone" id="edit_phone">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Birthdate</label>
                            <input type="date" class="form-control" name="birthdate" id="edit_birthdate">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gender</label>
                            <select class="form-select" name="gender" id="edit_gender">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Civil Status</label>
                            <select class="form-select" name="civil_status" id="edit_civil">
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Widowed">Widowed</option>
                                <option value="Separated">Separated</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Address</label>
                            <input type="text" class="form-control" name="address" id="edit_address">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Replace Photo</label>
                            <input type="file" class="form-control" name="photo" accept="image/*">
                        </div>
                        <div class="col-md-2 d-flex align-items-end justify-content-center">
                            <div id="edit_avatar_prev"></div>
                        </div>

                        <!-- PORTAL CREDENTIALS -->
                        <div class="col-12 mt-3">
                            <div class="fw-bold text-primary mb-1 uppercase"
                                style="font-size:12px;letter-spacing:.5px;">Portal Account Credentials</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Reset/Update Password</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="portal_password" id="edit_portal_password"
                                    placeholder="Leave blank to keep current">
                                <button class="btn btn-outline-secondary" type="button"
                                    onclick="regenerateEditPassword()">
                                    <i class="bi bi-arrow-clockwise"></i> Generate
                                </button>
                            </div>
                            <small class="text-muted">Fill this in to update/reset the employee's portal credentials and
                                notify them via Gmail.</small>
                        </div>

                        <!-- EMPLOYMENT -->
                        <div class="col-12 mt-3">
                            <div class="fw-bold text-primary mb-1 uppercase"
                                style="font-size:12px;letter-spacing:.5px;">Employment & Salary Details</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Position <span class="text-danger">*</span></label>
                            <select class="form-select" name="position_id" id="edit_pos" required>
                                <option value="">-- Select Position --</option>
                                <?php foreach ($positionList as $p) { ?>
                                    <option value="<?= $p['position_id']; ?>">
                                        <?= htmlspecialchars($p['position_name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Department</label>
                            <select class="form-select" name="department_id" id="edit_dept">
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departmentList as $dept) { ?>
                                    <option value="<?= $dept['department_id']; ?>">
                                        <?= htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Employment Type <span
                                    class="text-danger">*</span></label>
                            <select class="form-select" name="employment_type" id="edit_emptype">
                                <option value="Full-time">Full-time</option>
                                <option value="Part-time">Part-time</option>
                                <option value="Contractual">Contractual</option>
                                <option value="Probationary">Probationary</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Employment Status <span
                                    class="text-danger">*</span></label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Resigned">Resigned</option>
                                <option value="Terminated">Terminated</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Date Hired <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_hired" id="edit_datehired" required
                                onchange="updateEditContractEnd()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Basic Salary (Monthly PHP) <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" class="form-control" name="basic_salary"
                                    id="edit_salary" required min="0">
                            </div>
                        </div>

                        <!-- CONTRACT (6 MONTHS) -->
                        <div class="col-12 mt-3">
                            <div class="fw-bold text-primary mb-1 uppercase"
                                style="font-size:12px;letter-spacing:.5px;">Employment Contract Details (6 Months Term)
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contract Start Date</label>
                            <input type="date" class="form-control bg-light" name="contract_start"
                                id="edit_contract_start" readonly>
                            <small class="text-muted" style="font-size:11px;">Auto-synced with Date Hired</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contract End Date (6 Months)</label>
                            <input type="date" class="form-control bg-light" name="contract_end" id="edit_contract_end"
                                readonly>
                            <small class="text-primary fw-semibold" style="font-size:11px;" id="edit_contract_hint"><i
                                    class="bi me-1"></i>Good for 6 months</small>
                        </div>
                        <div class="col-12">
                            <div class="form-check p-2 bg-light border rounded ms-1">
                                <input class="form-check-input" type="checkbox" name="contract_signed"
                                    id="edit_contract_signed" value="1">
                                <label class="form-check-label fw-semibold text-dark" for="edit_contract_signed"
                                    style="font-size:12.5px;">
                                    6-Month Employment Contract agreed upon and signed
                                </label>
                            </div>
                        </div>

                        <!-- STATUTORY -->
                        <div class="col-12 mt-3">
                            <div class="fw-bold text-primary mb-1 uppercase"
                                style="font-size:12px;letter-spacing:.5px;">Statutory Identifications</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">SSS Number</label>
                            <input type="text" class="form-control" name="sss_no" id="edit_sss">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">PhilHealth ID</label>
                            <input type="text" class="form-control" name="philhealth_no" id="edit_philhealth">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Pag-IBIG ID</label>
                            <input type="text" class="form-control" name="pagibig_no" id="edit_pagibig">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">TIN Number</label>
                            <input type="text" class="form-control" name="tin_no" id="edit_tin">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-check-lg me-1"></i>Update
                        Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Initialize standard plugins
        if ($.fn.DataTable) {
            $('#employeesTable').DataTable({
                responsive: true,
                pageLength: 10,
                lengthChange: false,
                ordering: true,
                searching: true,
                destroy: true,
                columnDefs: [
                    { orderable: false, targets: [0, 1, 7] }
                ]
            });
        }

        // Event delegation for view/edit buttons (works with DataTables pagination)
        $(document).off('click.emp-view').on('click.emp-view', '.btn-view-emp', function (e) {
            e.preventDefault();
            const emp = $(this).data('emp');
            if (emp) viewProfile(emp);
        });
        $(document).off('click.emp-edit').on('click.emp-edit', '.btn-edit-emp', function (e) {
            e.preventDefault();
            const emp = $(this).data('emp');
            if (emp) openEditModal(emp);
        });

        // Handle ADD form submission via AJAX
        $('#addForm').on('submit', function (e) {
            e.preventDefault();

            let formEl = this;

            Swal.fire({
                target: document.getElementById('addModal'),
                title: 'Confirm Your Password',
                html: 'Enter your account password to add this new employee.',
                input: 'password',
                inputPlaceholder: 'Password',
                inputAttributes: { autocapitalize: 'off', autocorrect: 'off' },
                showCancelButton: true,
                confirmButtonColor: '#2563eb',
                confirmButtonText: 'Confirm & Add',
                cancelButtonText: 'Cancel',
                inputValidator: (value) => {
                    if (!value) return 'Password is required to proceed.';
                }
            }).then((confirmResult) => {
                if (!confirmResult.isConfirmed) return;

                let formData = new FormData(formEl);
                formData.append('action', 'create');
                formData.append('password', confirmResult.value);

                $.ajax({
                    url: 'hrms_employees.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        response = response.trim();
                        if (response.startsWith('success')) {
                            let parts = response.split('|');
                            let icon = 'success';
                            let title = 'Employee Added!';
                            let text = '';
                            if (parts[1]) {
                                let [type, msg] = [parts[1].split(':')[0], parts[1].split(':').slice(1).join(':')];
                                icon = (type === 'warning') ? 'warning' : 'success';
                                text = msg;
                            }
                            Swal.fire({
                                icon: icon,
                                title: title,
                                text: text,
                                showConfirmButton: !!text,
                                timer: text ? undefined : 1500
                            }).then(() => {
                                clearBackdropHrms();
                                loadPage('hrms_employees.php');
                            });
                        } else {
                            Swal.fire('Error', response, 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Server communication failure.', 'error');
                    }
                });
            });
        });

        // Handle EDIT form submission via AJAX
        $('#editForm').on('submit', function (e) {
            e.preventDefault();

            let formEl = this;

            Swal.fire({
                target: document.getElementById('editModal'),
                title: 'Confirm Your Password',
                html: 'Enter your account password to save these changes.',
                input: 'password',
                inputPlaceholder: 'Password',
                inputAttributes: { autocapitalize: 'off', autocorrect: 'off' },
                showCancelButton: true,
                confirmButtonColor: '#f59e0b',
                confirmButtonText: 'Confirm & Save',
                cancelButtonText: 'Cancel',
                inputValidator: (value) => {
                    if (!value) return 'Password is required to proceed.';
                }
            }).then((confirmResult) => {
                if (!confirmResult.isConfirmed) return;

                let formData = new FormData(formEl);
                formData.append('action', 'update');
                formData.append('password', confirmResult.value);

                $.ajax({
                    url: 'hrms_employees.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        response = response.trim();
                        if (response === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Employee Updated!',
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                clearBackdropHrms();
                                loadPage('hrms_employees.php');
                            });
                        } else {
                            Swal.fire('Error', response, 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'Server communication failure.', 'error');
                    }
                });
            });
        });
    });

    /*====================================================
        HELPER TO CLEAR MODAL BACKDROPS ON PAGE SWAP
    ====================================================*/
    function clearBackdropHrms() {
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('padding-right', '');
    }

    /*====================================================
        PORTAL PASSWORD GENERATION HELPERS
    ====================================================*/
    function generateRandomPassword(length = 8) {
        const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        let password = "";
        for (let i = 0; i < length; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return password;
    }
    function regenerateAddPassword() {
        $('#add_portal_password').val(generateRandomPassword());
    }
    function regenerateEditPassword() {
        $('#edit_portal_password').val(generateRandomPassword());
    }

    /*====================================================
        FIX: Bootstrap modal steals focus from SweetAlert2
        inputs rendered on top of it (password prompts).
    ====================================================*/
    $('#addModal').on('shown.bs.modal', function () {
        $(document).off('focusin.bs.modal');
    });
    $('#editModal').on('shown.bs.modal', function () {
        $(document).off('focusin.bs.modal');
    });

    /*====================================================
        CONTRACT DATE CALCULATION HELPERS
    ====================================================*/
    function updateAddContractEnd() {
        const hiredVal = $('#addForm [name="date_hired"]').val();
        if (hiredVal) {
            $('#add_contract_start').val(hiredVal);
            const dt = new Date(hiredVal);
            dt.setMonth(dt.getMonth() + 6);
            const yyyy = dt.getFullYear();
            const mm = String(dt.getMonth() + 1).padStart(2, '0');
            const dd = String(dt.getDate()).padStart(2, '0');
            const endDateStr = `${yyyy}-${mm}-${dd}`;
            $('#add_contract_end').val(endDateStr);
            $('#add_contract_hint').html('<i class="bi bi-clock-history me-1"></i>Good for 6 months (' + hiredVal + ' to ' + endDateStr + ')');
        }
    }

    function updateEditContractEnd() {
        const hiredVal = $('#edit_datehired').val();
        if (hiredVal) {
            $('#edit_contract_start').val(hiredVal);
            const dt = new Date(hiredVal);
            dt.setMonth(dt.getMonth() + 6);
            const yyyy = dt.getFullYear();
            const mm = String(dt.getMonth() + 1).padStart(2, '0');
            const dd = String(dt.getDate()).padStart(2, '0');
            const endDateStr = `${yyyy}-${mm}-${dd}`;
            $('#edit_contract_end').val(endDateStr);
            $('#edit_contract_hint').html('<i class="bi bi-clock-history me-1"></i>Good for 6 months (' + hiredVal + ' to ' + endDateStr + ')');
        }
    }

    /*====================================================
        OPEN ADD MODAL
    ====================================================*/
    function autoFillAddDept(positionId) {
        if (!positionId) return;
        const opt = document.querySelector('#add_pos option[value="' + positionId + '"]');
        if (opt) {
            const deptId = opt.getAttribute('data-dept');
            if (deptId && deptId !== '0') {
                $('#add_dept').val(deptId);
            }
        }
    }

    function openAddModal() {
        $('#addForm')[0].reset();
        $('#add_dept').val('');
        regenerateAddPassword(); // generate initial password
        updateAddContractEnd();
        const modalEl = document.getElementById('addModal');
        if (modalEl) {
            document.body.appendChild(modalEl);
            (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
        }
    }
    window.openAddModal = openAddModal;

    /*====================================================
        VIEW EMPLOYEE PROFILE
    ====================================================*/
    let currentEmpProfile = null;

    function triggerProfileRenew() {
        if (!currentEmpProfile) return;
        const m = bootstrap.Modal.getInstance(document.getElementById('viewModal'));
        if (m) m.hide();
        openRenewalModal(currentEmpProfile);
    }

    function triggerProfileContract() {
        if (!currentEmpProfile) return;
        const m = bootstrap.Modal.getInstance(document.getElementById('viewModal'));
        if (m) m.hide();
        viewContractModal(currentEmpProfile);
    }

    function triggerProfileIDCard() {
        if (!currentEmpProfile) return;
        const m = bootstrap.Modal.getInstance(document.getElementById('viewModal'));
        if (m) m.hide();
        generateEmployeeIDCard(currentEmpProfile);
    }

    function triggerProfileEdit() {
        if (!currentEmpProfile) return;
        const m = bootstrap.Modal.getInstance(document.getElementById('viewModal'));
        if (m) m.hide();
        openEditModal(currentEmpProfile);
    }

    function viewProfile(emp) {
        currentEmpProfile = emp;
        // Render Avatar Large or Initials
        let avatarHtml = '';
        if (emp.photo && emp.photo !== '') {
            avatarHtml = `<img src="uploads/employees/${emp.photo}?t=${Date.now()}" class="profile-avatar-large">`;
        } else {
            const initials = getInitialsFromJS(emp.full_name);
            avatarHtml = `<div class="profile-avatar-large">${initials}</div>`;
        }
        $('#v_avatar_container').html(avatarHtml);

        // Bind info
        $('#v_name').text(emp.full_name);
        $('#v_emp_no').text(emp.employee_no);
        $('#v_job_title').text(emp.position_name || 'N/A');

        $('#v_birthdate').text(emp.birthdate ? formatDate(emp.birthdate) : 'N/A');
        $('#v_gender').text(emp.gender || 'N/A');
        $('#v_civil').text(emp.civil_status || 'N/A');
        $('#v_phone').text(emp.phone || 'N/A');
        $('#v_email').text(emp.email || 'N/A');
        $('#v_address').text(emp.address || 'N/A');

        $('#v_hired').text(emp.date_hired ? formatDate(emp.date_hired) : 'N/A');
        $('#v_type').text(emp.employment_type || 'Full-time');
        $('#v_salary').text('₱' + parseFloat(emp.basic_salary).toLocaleString('en-US', { minimumFractionDigits: 2 }));

        // Contract details
        $('#v_contract_term').text('6 Months');
        if (emp.contract_start && emp.contract_end) {
            let contractBadge = `<span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold">${formatDate(emp.contract_start)} &ndash; ${formatDate(emp.contract_end)}</span>`;
            if (emp.renewal_count && parseInt(emp.renewal_count) > 0) {
                contractBadge += ` <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size:10px;"><i class="bi bi-arrow-repeat me-1"></i>Renewed ${emp.renewal_count}x</span>`;
            }
            $('#v_contract_dates').html(contractBadge);
        } else {
            $('#v_contract_dates').html('<span class="text-muted">N/A</span>');
        }
        if (emp.contract_signed == 1 || emp.contract_start) {
            $('#v_contract_status').html('<span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Signed &amp; Active</span>');
        } else {
            $('#v_contract_status').html('<span class="badge bg-secondary">Unsigned / Pending</span>');
        }

        // Status badge rendering
        let statusClass = 'badge bg-success';
        if (emp.status === 'Inactive') statusClass = 'badge bg-secondary';
        else if (emp.status === 'Resigned') statusClass = 'badge bg-warning text-dark';
        else if (emp.status === 'Terminated') statusClass = 'badge bg-danger';
        $('#v_status_badge').html(`<span class="${statusClass}">${emp.status}</span>`);

        // Government IDs
        $('#v_sss').text(emp.sss_no || 'N/A');
        $('#v_philhealth').text(emp.philhealth_no || 'N/A');
        $('#v_pagibig').text(emp.pagibig_no || 'N/A');
        $('#v_tin').text(emp.tin_no || 'N/A');

        const modalEl = document.getElementById('viewModal');
        if (modalEl) {
            document.body.appendChild(modalEl);
            (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
        }
    }
    window.viewProfile = viewProfile;

    /*====================================================
        OPEN EDIT MODAL
    ====================================================*/
    function openEditModal(emp) {
        $('#editForm')[0].reset();

        $('#edit_id').val(emp.employee_id);
        $('#edit_name').val(emp.full_name);
        $('#edit_email').val(emp.email);
        $('#edit_phone').val(emp.phone);
        $('#edit_birthdate').val(emp.birthdate);
        $('#edit_gender').val(emp.gender || 'Female');
        $('#edit_civil').val(emp.civil_status || 'Single');
        $('#edit_address').val(emp.address);
        $('#edit_portal_password').val(''); // blank password input by default

        $('#edit_pos').val(emp.position_id);
        $('#edit_dept').val(emp.department_id || '');
        $('#edit_emptype').val(emp.employment_type || 'Full-time');
        $('#edit_status').val(emp.status || 'Active');
        $('#edit_datehired').val(emp.date_hired);
        $('#edit_salary').val(emp.basic_salary);

        // Contract fields
        const cStart = emp.contract_start || emp.date_hired || '';
        $('#edit_contract_start').val(cStart);
        $('#edit_contract_signed').prop('checked', emp.contract_signed == 1 || !!emp.contract_start);
        updateEditContractEnd();

        $('#edit_sss').val(emp.sss_no);
        $('#edit_philhealth').val(emp.philhealth_no);
        $('#edit_pagibig').val(emp.pagibig_no);
        $('#edit_tin').val(emp.tin_no);

        // Show preview avatar
        let previewHtml = '';
        if (emp.photo && emp.photo !== '') {
            previewHtml = `<img src="uploads/employees/${emp.photo}?t=${Date.now()}" class="rounded-circle border" style="width:50px;height:50px;object-fit:cover;">`;
        } else {
            const initials = getInitialsFromJS(emp.full_name);
            previewHtml = `<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width:50px;height:50px;font-size:16px;">${initials}</div>`;
        }
        $('#edit_avatar_prev').html(previewHtml);

        const modalEl = document.getElementById('editModal');
        if (modalEl) {
            document.body.appendChild(modalEl);
            (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
        }
    }
    window.openEditModal = openEditModal;

    /*====================================================
        REMOVE EMPLOYEE
    ====================================================*/
    function deleteEmployee(id, name) {
        // Step 1 — Select Reason from Dropdown
        Swal.fire({
            title: 'Remove ' + name + '?',
            html: `
                <p class="text-muted mb-2" style="font-size:13px;">Select the primary reason for removing this employee from active records:</p>
                <div class="text-start mb-2">
                    <label class="form-label fw-bold text-dark" style="font-size:12px;">Reason for Removal <span class="text-danger">*</span></label>
                    <select id="delEmpReasonSelect" class="form-select" style="font-size:13px;">
                        <option value="End of Employment Contract">End of Employment Contract</option>
                        <option value="Voluntary Resignation">Voluntary Resignation</option>
                        <option value="Termination / Disciplinary Action">Termination / Disciplinary Action</option>
                        <option value="Retirement">Retirement</option>
                        <option value="Redundancy / Layoff">Redundancy / Layoff</option>
                        <option value="Data Entry Error / Test Record">Data Entry Error / Test Record</option>
                    </select>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Next: Confirm Password',
            preConfirm: () => {
                const r = document.getElementById('delEmpReasonSelect').value;
                if (!r) { Swal.showValidationMessage('Please select a reason.'); return false; }
                return r;
            }
        }).then(reasonResult => {
            if (!reasonResult.isConfirmed) return;
            const reason = reasonResult.value;

            // Step 2 — Password
            Swal.fire({
                title: 'Confirm Admin Password',
                html: `Enter your administrator password to confirm removal of <strong>${name}</strong>.`,
                input: 'password',
                inputPlaceholder: 'Enter your password',
                inputAttributes: { autocapitalize: 'off', autocorrect: 'off' },
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, Remove Record',
                cancelButtonText: 'Cancel',
                inputValidator: (value) => {
                    if (!value) return 'Password is required to proceed.';
                }
            }).then(result => {
                if (!result.isConfirmed) return;

                $.post('hrms_employees.php', {
                    action: 'delete',
                    employee_id: id,
                    password: result.value,
                    reason: reason
                }, function (response) {
                    response = response.trim();
                    if (response === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Employee Removed!',
                            text: name + ' has been archived successfully.',
                            showConfirmButton: false,
                            timer: 1800
                        }).then(() => { loadPage('hrms_employees.php'); });
                    } else {
                        Swal.fire('Error', response, 'error');
                    }
                });
            });
        });
    }
    window.deleteEmployee = deleteEmployee;

    /*====================================================
        RESTORE EMPLOYEE FROM ARCHIVE
    ====================================================*/
    function restoreEmployee(archiveId, name) {
        Swal.fire({
            title: 'Restore Employee?',
            html: `Are you sure you want to restore <strong>${name}</strong> back to active workforce?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            confirmButtonText: 'Yes, Restore Employee'
        }).then(result => {
            if (!result.isConfirmed) return;
            $.post('hrms_employees.php', {
                action: 'restore_employee',
                archive_id: archiveId
            }, function (response) {
                if (response.trim() == 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Employee Restored!',
                        text: `${name} has been restored to active workforce.`,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        clearBackdropHrms();
                        loadPage('hrms_employees.php');
                    });
                } else {
                    Swal.fire('Error', response.replace('error:', '').trim(), 'error');
                }
            });
        });
    }
    window.restoreEmployee = restoreEmployee;

    /*====================================================
        BATCH SELECTION & BATCH PRINTING HELPERS
    ====================================================*/
    function toggleSelectAllEmployees(masterCheckbox) {
        const isChecked = $(masterCheckbox).is(':checked');
        $('.emp-checkbox').prop('checked', isChecked);
        updateBatchBtnState();
    }
    window.toggleSelectAllEmployees = toggleSelectAllEmployees;

    function updateBatchBtnState() {
        const count = $('.emp-checkbox:checked').length;
        if (count > 0) {
            $('#batchCountBadge').text(count).show();
        } else {
            $('#batchCountBadge').hide();
        }
    }
    window.updateBatchBtnState = updateBatchBtnState;

    function getSelectedEmployees() {
        const selected = [];
        $('.emp-checkbox:checked').each(function () {
            const raw = $(this).attr('data-emp');
            if (raw) {
                try {
                    selected.push(JSON.parse(raw));
                } catch (e) {
                    console.error("Failed to parse employee JSON:", e);
                }
            }
        });
        return selected;
    }
    window.getSelectedEmployees = getSelectedEmployees;

    /*====================================================
        BATCH PRINT EMPLOYEE IDS
    ====================================================*/
    function printBatchIDs() {
        const selectedEmps = getSelectedEmployees();
        if (!selectedEmps || selectedEmps.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'No Employee Selected',
                text: 'Please select at least one employee from the table checkboxes to print IDs.',
                confirmButtonColor: '#2563eb'
            });
            return;
        }

        let cardsHtml = '';
        selectedEmps.forEach(emp => {
            const initials = getInitialsFromJS(emp.full_name);
            let photoHtml = '';
            if (emp.photo && emp.photo !== '') {
                photoHtml = `<img src="uploads/employees/${emp.photo}" class="id-photo" crossorigin="anonymous">`;
            } else {
                photoHtml = `<div class="id-initials">${initials}</div>`;
            }

            cardsHtml += `
            <div class="id-card">
                <div class="id-card-header">
                    <div class="id-card-logo">🏪</div>
                    <div class="id-card-company-name">O-CART! STORE</div>
                </div>
                <div class="id-card-body">
                    <div class="id-pictures-row">
                        <div class="id-photo-container">
                            ${photoHtml}
                        </div>
                        <div class="id-qr-container">
                            <div class="qr-target" data-code="${emp.employee_no}"></div>
                        </div>
                    </div>
                    <div class="id-info-container">
                        <div class="id-name">${emp.full_name}</div>
                        <div class="id-role">${emp.position_name || 'STAFF'}</div>
                        <div class="id-department">${emp.department_name || 'O-CART!'}</div>
                        <div class="id-number-badge">
                            <span class="label">ID NO:</span>
                            <span class="value">${emp.employee_no}</span>
                        </div>
                    </div>
                </div>
                <div class="id-card-footer">
                    OFFICIAL IDENTIFICATION CARD &bull; AUTHORIZED PERSONNEL
                </div>
            </div>`;
        });

        const printWindow = window.open('', '_blank', 'width=900,height=900');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Batch ID Card Print (${selectedEmps.length} Employees)</title>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
                <style>
                    @page { size: A4; margin: 15mm; }
                    body { margin: 0; padding: 20px; background: #ffffff; font-family: 'Segoe UI', system-ui, sans-serif; }
                    .batch-id-grid {
                        display: grid;
                        grid-template-columns: repeat(2, 320px);
                        gap: 25px 30px;
                        justify-content: center;
                    }
                    .id-card {
                        width: 320px;
                        height: 480px;
                        background: #ffffff;
                        border-radius: 16px;
                        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                        display: flex;
                        flex-direction: column;
                        overflow: hidden;
                        position: relative;
                        border: 1px solid #d1d5db;
                        page-break-inside: avoid;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .id-card-header {
                        background: linear-gradient(135deg, #1a3c5e 0%, #2b5c8f 100%) !important;
                        color: white !important;
                        padding: 16px 12px;
                        text-align: center;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        border-bottom: 4px solid #2563eb !important;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .id-card-logo { font-size: 22px; margin-bottom: 2px; }
                    .id-card-company-name { font-size: 13px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; }
                    .id-card-body {
                        flex-grow: 1;
                        padding: 20px 16px;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        background: radial-gradient(circle at 50% 50%, #ffffff 0%, #f9fafb 100%) !important;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .id-pictures-row {
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        gap: 20px;
                        margin-bottom: 20px;
                        width: 100%;
                    }
                    .id-photo-container {
                        width: 95px;
                        height: 95px;
                        border-radius: 12px;
                        overflow: hidden;
                        border: 3px solid #2563eb !important;
                        background: #f3f4f6 !important;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .id-photo { width: 100%; height: 100%; object-fit: cover; }
                    .id-initials {
                        width: 100%;
                        height: 100%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        background: #2563eb !important;
                        color: white !important;
                        font-size: 30px;
                        font-weight: 800;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .id-qr-container {
                        width: 95px;
                        height: 95px;
                        border-radius: 12px;
                        overflow: hidden;
                        border: 3px solid #e5e7eb !important;
                        background: white !important;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        padding: 6px;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .qr-target img { width: 100% !important; height: 100% !important; }
                    .id-info-container { text-align: center; width: 100%; }
                    .id-name { font-size: 18px; font-weight: 800; color: #111827 !important; margin-bottom: 4px; }
                    .id-role { font-size: 12px; font-weight: 700; color: #2563eb !important; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
                    .id-department { font-size: 11px; color: #6b7280 !important; margin-bottom: 14px; font-weight: 500; }
                    .id-number-badge {
                        display: inline-flex;
                        align-items: center;
                        background: #f3f4f6 !important;
                        border: 1px solid #e5e7eb !important;
                        padding: 5px 12px;
                        border-radius: 30px;
                        font-family: monospace;
                        font-size: 13px;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .id-number-badge .label { color: #6b7280 !important; font-weight: 600; margin-right: 6px; }
                    .id-number-badge .value { color: #111827 !important; font-weight: 800; }
                    .id-card-footer {
                        background: #f9fafb !important;
                        border-top: 1px dashed #e5e7eb !important;
                        padding: 10px;
                        text-align: center;
                        font-size: 9px;
                        font-weight: 700;
                        color: #9ca3af !important;
                        letter-spacing: 1px;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    @media print {
                        body { padding: 0; background: none; }
                        .id-card { box-shadow: none; }
                    }
                </style>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"><\/script>
            </head>
            <body>
                <div class="batch-id-grid">
                    ${cardsHtml}
                </div>
                <script>
                    window.onload = function() {
                        document.querySelectorAll('.qr-target').forEach(el => {
                            const code = el.getAttribute('data-code');
                            if (code && typeof QRCode !== 'undefined') {
                                new QRCode(el, { text: code, width: 80, height: 80, colorDark: "#111827", colorLight: "#ffffff" });
                            }
                        });
                        setTimeout(function() {
                            window.print();
                            window.close();
                        }, 750);
                    };
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }
    window.printBatchIDs = printBatchIDs;

    /*====================================================
        BATCH PRINT EMPLOYMENT CONTRACTS
    ====================================================*/
    function printBatchContracts() {
        const selectedEmps = getSelectedEmployees();
        if (!selectedEmps || selectedEmps.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'No Employee Selected',
                text: 'Please select at least one employee from the table checkboxes to print contracts.',
                confirmButtonColor: '#2563eb'
            });
            return;
        }

        let contractsHtml = '';
        selectedEmps.forEach((emp, index) => {
            const startFmt = emp.contract_start ? formatDate(emp.contract_start) : (emp.date_hired ? formatDate(emp.date_hired) : 'Date of Hire');
            const endFmt = emp.contract_end ? formatDate(emp.contract_end) : '6 Months from Hire Date';
            const salaryFmt = '₱' + parseFloat(emp.basic_salary).toLocaleString('en-US', { minimumFractionDigits: 2 });
            const schedStr = (emp.schedule || 'Standard Schedule') + (emp.rest_day ? ' (Rest Day: ' + emp.rest_day + ')' : '');

            contractsHtml += `
            <div class="contract-page ${index < selectedEmps.length - 1 ? 'page-break' : ''}">
                <div class="text-center mb-4">
                    <div style="font-size:24px;font-weight:900;color:#1e3a8a;letter-spacing:1px;">O-CART! STORE HRMS</div>
                    <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:2px;font-weight:700;">Human Resource Management & Workforce Division</div>
                    <div style="width:80px;height:3px;background:#2563eb;margin:8px auto 0;"></div>
                </div>

                <h4 class="text-center fw-bold mb-4" style="color:#0f172a;letter-spacing:0.5px;text-transform:uppercase;">
                    Employment Contract Agreement
                </h4>

                <p style="font-size:13px;line-height:1.7;color:#334155;text-align:justify;">
                    This Employment Contract Agreement is entered into by and between <strong>O-CART! STORE</strong> ("Employer") 
                    and <strong>${emp.full_name}</strong> ("Employee"), with Employee ID <strong>#${emp.employee_no}</strong>.
                </p>

                <div class="card p-3 mb-4 bg-light border-0 shadow-sm" style="font-size:13px;">
                    <div class="row g-2">
                        <div class="col-6"><strong>Employee Name:</strong> ${emp.full_name}</div>
                        <div class="col-6"><strong>Employee No:</strong> #${emp.employee_no}</div>
                        <div class="col-6"><strong>Position:</strong> ${emp.position_name || 'Staff'}</div>
                        <div class="col-6"><strong>Department:</strong> ${emp.department_name || 'General Operations'}</div>
                        <div class="col-6"><strong>Employment Type:</strong> ${emp.employment_type || 'Full-time'}</div>
                        <div class="col-6"><strong>Basic Monthly Salary:</strong> ${salaryFmt}</div>
                        <div class="col-6"><strong>Contract Term:</strong> ${startFmt} &ndash; ${endFmt}</div>
                        <div class="col-6"><strong>Work Schedule:</strong> ${schedStr}</div>
                    </div>
                </div>

                <div style="font-size:12.5px;line-height:1.7;color:#334155;">
                    <h6 class="fw-bold text-dark mt-3">1. Scope of Employment</h6>
                    <p>The Employee agrees to perform all duties and responsibilities associated with the position of <strong>${emp.position_name || 'Staff'}</strong> faithfully, diligently, and to the best of their ability.</p>

                    <h6 class="fw-bold text-dark mt-3">2. Compensation & Benefits</h6>
                    <p>The Employer agrees to pay the Employee a monthly basic salary of <strong>${salaryFmt}</strong>, subject to applicable statutory deductions (SSS, PhilHealth, Pag-IBIG, and Income Tax withholding).</p>

                    <h6 class="fw-bold text-dark mt-3">3. Company Policies & Code of Conduct</h6>
                    <p>The Employee agrees to adhere strictly to all store policies, safety guidelines, and workplace rules established by O-CART! Store Management.</p>
                </div>

                <div class="row mt-5 pt-4" style="font-size:13px;">
                    <div class="col-6 text-center">
                        <div style="border-bottom:1px solid #0f172a;width:80%;margin:0 auto 6px;font-weight:700;">
                            ${emp.full_name}
                        </div>
                        <div class="text-muted small fw-semibold">Employee Signature</div>
                    </div>
                    <div class="col-6 text-center">
                        <div style="border-bottom:1px solid #0f172a;width:80%;margin:0 auto 6px;font-weight:700;">
                            AUTHORIZED HR MANAGEMENT
                        </div>
                        <div class="text-muted small fw-semibold">O-CART! Store Representative</div>
                    </div>
                </div>
            </div>`;
        });

        const printWindow = window.open('', '_blank', 'width=900,height=900');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Batch Contract Print (${selectedEmps.length} Contracts)</title>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
                <style>
                    @page { size: A4; margin: 20mm; }
                    body { background: #fff; font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; padding: 20px; }
                    .contract-page { padding: 20px 10px; }
                    .page-break { page-break-after: always; break-after: page; }
                </style>
            </head>
            <body>
                ${contractsHtml}
                <script>
                    window.onload = function() {
                        setTimeout(function() {
                            window.print();
                            window.close();
                        }, 500);
                    };
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }
    window.printBatchContracts = printBatchContracts;

/*====================================================
    HELPER UTILS
====================================================*/
function getInitialsFromJS(name) {
    if (!name) return "?";
    let words = name.replace(/[^a-zA-Z0-9\s]/g, "").split(" ");
    let initials = "";
    for (let i = 0; i < words.length; i++) {
        if (words[i].length > 0) {
            initials += words[i].substring(0, 1).toUpperCase();
        }
        if (initials.length >= 2) break;
    }
    return initials || "?";
}
window.getInitialsFromJS = getInitialsFromJS;

function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}
window.formatDate = formatDate;

/*====================================================
    DYNAMIC QR CODE LIBRARY LOADER
====================================================*/
function loadQRCodeLib(callback) {
    if (typeof QRCode !== 'undefined') {
        callback();
        return;
    }
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
    script.onload = callback;
    document.head.appendChild(script);
}
window.loadQRCodeLib = loadQRCodeLib;

/*====================================================
    GENERATE EMPLOYEE ID CARD WITH QR CODE
====================================================*/
function generateEmployeeIDCard(emp) {
    // Clear previous QR code
    $('#id_card_qrcode').empty();

    // Set Photo or Initials
    if (emp.photo && emp.photo !== '') {
        $('#id_card_photo').attr('src', 'uploads/employees/' + emp.photo + '?t=' + Date.now()).show();
        $('#id_card_initials').hide();
    } else {
        const initials = getInitialsFromJS(emp.full_name);
        $('#id_card_initials').text(initials).show();
        $('#id_card_photo').hide();
    }

    // Bind text fields
    $('#id_card_name').text(emp.full_name);
    $('#id_card_role').text(emp.position_name || 'STAFF');
    $('#id_card_dept').text('O-CART!');
    $('#id_card_emp_no').text(emp.employee_no);

    // Load QR Code library and generate the QR code
    loadQRCodeLib(function () {
        const qrContent = emp.employee_no;
        new QRCode(document.getElementById("id_card_qrcode"), {
            text: qrContent,
            width: 88,
            height: 88,
            colorDark: "#111827",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M
        });
    });

    const modalEl = document.getElementById('idCardModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }
}
window.generateEmployeeIDCard = generateEmployeeIDCard;

/*====================================================
    PRINT ID CARD FUNCTIONALITY
====================================================*/
function printIDCard() {
    const cardContent = document.getElementById('idCardPrintArea').innerHTML;
    const printWindow = window.open('', '_blank', 'width=600,height=700');

    // Write out document content with all needed CSS styles
    printWindow.document.write(`
        <html>
        <head>
            <title>Print ID Card</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
            <style>
                body {
                    margin: 0;
                    padding: 40px 20px;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    background: #ffffff;
                    font-family: 'Segoe UI', system-ui, sans-serif;
                }
                .id-card-wrapper {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                }
                .id-card {
                    width: 320px;
                    height: 480px;
                    background: #ffffff;
                    border-radius: 16px;
                    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
                    display: flex;
                    flex-direction: column;
                    overflow: hidden;
                    position: relative;
                    border: 1px solid #e0e0e0;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .id-card-header {
                    background: linear-gradient(135deg, #1a3c5e 0%, #2b5c8f 100%) !important;
                    color: white !important;
                    padding: 18px 15px;
                    text-align: center;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    border-bottom: 4px solid #2563eb !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .id-card-logo {
                    font-size: 24px;
                    margin-bottom: 4px;
                }
                .id-card-company-name {
                    font-size: 14px;
                    font-weight: 800;
                    letter-spacing: 2px;
                    text-transform: uppercase;
                }
                .id-card-body {
                    flex-grow: 1;
                    padding: 24px 20px;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    background: radial-gradient(circle at 50% 50%, #ffffff 0%, #f9fafb 100%) !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .id-pictures-row {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    gap: 24px;
                    margin-bottom: 25px;
                    width: 100%;
                }
                .id-photo-container {
                    width: 100px;
                    height: 100px;
                    border-radius: 12px;
                    overflow: hidden;
                    border: 3px solid #2563eb !important;
                    background: #f3f4f6 !important;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .id-photo {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }
                .id-initials {
                    width: 100%;
                    height: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: #2563eb !important;
                    color: white !important;
                    font-size: 32px;
                    font-weight: 800;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .id-qr-container {
                    width: 100px;
                    height: 100px;
                    border-radius: 12px;
                    overflow: hidden;
                    border: 3px solid #e5e7eb !important;
                    background: white !important;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 6px;
                    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                #id_card_qrcode img {
                    width: 100% !important;
                    height: 100% !important;
                }
                .id-info-container {
                    text-align: center;
                    width: 100%;
                }
                .id-name {
                    font-size: 20px;
                    font-weight: 800;
                    color: #111827 !important;
                    margin-bottom: 4px;
                }
                .id-role {
                    font-size: 13px;
                    font-weight: 700;
                    color: #2563eb !important;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 2px;
                }
                .id-department {
                    font-size: 11px;
                    color: #6b7280 !important;
                    margin-bottom: 18px;
                    font-weight: 500;
                }
                .id-number-badge {
                    display: inline-flex;
                    align-items: center;
                    background: #f3f4f6 !important;
                    border: 1px solid #e5e7eb !important;
                    padding: 6px 14px;
                    border-radius: 30px;
                    font-family: monospace;
                    font-size: 14px;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .id-number-badge .label {
                    color: #6b7280 !important;
                    font-weight: 600;
                    margin-right: 6px;
                }
                .id-number-badge .value {
                    color: #111827 !important;
                    font-weight: 800;
                }
                .id-card-footer {
                    background: #f9fafb !important;
                    border-top: 1px dashed #e5e7eb !important;
                    padding: 12px;
                    text-align: center;
                    font-size: 9px;
                    font-weight: 700;
                    color: #9ca3af !important;
                    letter-spacing: 1px;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                @media print {
                    body {
                        padding: 0;
                        background: none;
                    }
                    .id-card {
                        border: none;
                        box-shadow: none;
                    }
                }
            </style>
        </head>
        <body>
            <div class="id-card-wrapper">${cardContent}</div>
            <script>
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                        window.close();
                    }, 500);
                };
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}
window.printIDCard = printIDCard;

/*====================================================
    DOWNLOAD ID CARD IN PNG FORMAT
====================================================*/
function downloadIDCard() {
    const cardElement = document.querySelector('#idCardPrintArea .id-card');
    if (!cardElement) return;

    if (typeof html2canvas !== 'undefined') {
        executeCapture();
    } else {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
        script.onload = () => {
            executeCapture();
        };
        document.head.appendChild(script);
    }

    function executeCapture() {
        Swal.fire({
            title: 'Generating Image...',
            html: 'Please wait while we render the PNG file.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Clone the card element offscreen to body to prevent bootstrap modal opacity/animation transfer issues
        const clone = cardElement.cloneNode(true);
        clone.style.position = 'fixed';
        clone.style.top = '-9999px';
        clone.style.left = '-9999px';
        clone.style.opacity = '1';
        clone.style.transform = 'none';
        clone.style.animation = 'none';

        // Override child styles for transparency/transitions
        clone.querySelectorAll('*').forEach(el => {
            el.style.opacity = '1';
            el.style.transform = 'none';
            el.style.transition = 'none';
            el.style.animation = 'none';
        });

        document.body.appendChild(clone);

        // Ensure child images load properly
        const cloneImg = clone.querySelector('#id_card_photo');
        if (cloneImg && cloneImg.src) {
            cloneImg.crossOrigin = 'anonymous';
        }

        setTimeout(() => {
            html2canvas(clone, {
                useCORS: true,
                allowTaint: false,
                scale: 3,
                backgroundColor: '#ffffff' // Solid white background
            }).then(canvas => {
                document.body.removeChild(clone);
                Swal.close();

                const link = document.createElement('a');
                const empNo = $('#id_card_emp_no').text() || 'employee';
                const empName = $('#id_card_name').text() || '';
                link.download = `ID_${empNo}_${empName.trim().replace(/\s+/g, '_')}.png`;
                link.href = canvas.toDataURL('image/png');
                link.click();
            }).catch(err => {
                console.error("html2canvas render error:", err);
                if (document.body.contains(clone)) {
                    document.body.removeChild(clone);
                }
                Swal.close();
                Swal.fire('Error', 'Failed to generate PNG image.', 'error');
            });
        }, 200);
    }
}
window.downloadIDCard = downloadIDCard;

function openArchiveModal() {
    const modalEl = document.getElementById('archiveModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.show();
    }
}
window.openArchiveModal = openArchiveModal;

/*====================================================
    VIEW EMPLOYEE CONTRACT MODAL
====================================================*/
function viewContractModal(emp) {
    $('#c_emp_name').text(emp.full_name);
    $('#c_emp_no').text('#' + emp.employee_no);
    $('#c_emp_position').text(emp.position_name || 'N/A');
    $('#c_emp_type').text(emp.employment_type || 'Full-time');

    let startFmt = emp.contract_start ? formatDate(emp.contract_start) : (emp.date_hired ? formatDate(emp.date_hired) : 'Date of Hire');
    let endFmt = emp.contract_end ? formatDate(emp.contract_end) : '6 Months from Hire Date';
    $('#c_contract_period').text(`${startFmt} – ${endFmt}`);

    $('#c_emp_salary').text('₱' + parseFloat(emp.basic_salary).toLocaleString('en-US', { minimumFractionDigits: 2 }) + '/month');

    let schedStr = (emp.schedule || 'Standard Schedule') + (emp.rest_day ? ' (Rest Day: ' + emp.rest_day + ')' : '');
    $('#c_emp_schedule').text(schedStr);

    $('#c_sig_employee').text(emp.full_name);

    // Update contract duration label if renewed
    const renewalCount = parseInt(emp.renewal_count) || 0;
    if (renewalCount > 0) {
        $('#c_contract_duration').html(`Active <span class="badge bg-success ms-1" style="font-size:11px;"><i class="bi bi-arrow-repeat me-1"></i>Renewed ${renewalCount}x</span>`);
    } else {
        $('#c_contract_duration').text('6 Months');
    }

    // Load renewal history
    $('#contract_renewal_history_body').html('<div class="text-muted small p-2"><i class="bi bi-hourglass-split me-1"></i>Loading...</div>');
    $.getJSON('hrms_employees.php', { action: 'get_renewal_history', employee_id: emp.employee_id }, function (rows) {
        if (!rows || rows.length === 0) {
            $('#contract_renewal_history_body').html('<div class="text-muted small p-2"><i class="bi bi-dash-circle me-1"></i>No renewals on record.</div>');
            return;
        }
        let html = '<div class="table-responsive"><table class="table table-sm table-bordered fs-7 mb-0">';
        html += '<thead class="table-success"><tr><th>#</th><th>New Start</th><th>New Expiry</th><th>Duration</th><th>Salary</th><th>Notes</th><th>Renewed At</th></tr></thead><tbody>';
        rows.forEach(function (r, i) {
            const rn = rows.length - i;
            html += `<tr>
                <td><span class="badge bg-success">Renewal #${rn}</span></td>
                <td>${r.new_contract_start || '-'}</td>
                <td>${r.new_contract_end || '-'}</td>
                <td>${r.duration_months} mo.</td>
                <td class="text-success fw-bold">${r.basic_salary ? '₱' + parseFloat(r.basic_salary).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '-'}</td>
                <td class="text-muted" style="font-size:11px;">${r.notes || '<em>none</em>'}</td>
                <td style="font-size:11px;">${r.renewed_at || '-'}</td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        $('#contract_renewal_history_body').html(html);
    }).fail(function () {
        $('#contract_renewal_history_body').html('<div class="text-danger small p-2"><i class="bi bi-exclamation-triangle me-1"></i>Failed to load renewal history.</div>');
    });

    const modalEl = document.getElementById('employeeContractModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }
}
window.viewContractModal = viewContractModal;

function printContract() {
    const printContents = document.getElementById('contractPrintArea').innerHTML;
    const originalContents = document.body.innerHTML;
    document.body.innerHTML = `
        <div style="padding:40px;">
            ${printContents}
        </div>`;
    window.print();
    document.body.innerHTML = originalContents;
    location.reload();
}
window.printContract = printContract;

/*====================================================
    RENEW CONTRACT JS HELPERS
====================================================*/
function openRenewalModal(emp) {
    $('#renew_employee_id').val(emp.employee_id);
    $('#renew_emp_name_title').text(emp.full_name);
    $('#renew_emp_name').text(emp.full_name);
    $('#renew_emp_no').text('#' + emp.employee_no);
    $('#renew_emp_pos').text(emp.position_name || 'Staff');

    const count = (parseInt(emp.renewal_count) || 0) + 1;
    $('#renew_count_badge').text('Renewal #' + count);

    $('#renew_old_end').val(emp.contract_end || emp.date_hired || '');

    // Default new start date = 1 day after old_end (or today if old_end is missing)
    let startDate = new Date();
    if (emp.contract_end) {
        startDate = new Date(emp.contract_end);
        startDate.setDate(startDate.getDate() + 1);
    }
    const yyyy = startDate.getFullYear();
    const mm = String(startDate.getMonth() + 1).padStart(2, '0');
    const dd = String(startDate.getDate()).padStart(2, '0');
    const startDateStr = `${yyyy}-${mm}-${dd}`;

    $('#renew_contract_start').val(startDateStr);
    $('#renew_duration').val(6);
    $('#renew_emptype').val(emp.employment_type || 'Full-time');
    $('#renew_salary').val(emp.basic_salary);
    $('#renew_notes').val('');
    $('#renew_confirm_check').prop('checked', true);

    calculateRenewalEndDate();
    
    const modalEl = document.getElementById('renewContractModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }
}
window.openRenewalModal = openRenewalModal;

function onRenewalDurationChange() {
    calculateRenewalEndDate();
}
window.onRenewalDurationChange = onRenewalDurationChange;

function calculateRenewalEndDate() {
    const startVal = $('#renew_contract_start').val();
    const duration = parseInt($('#renew_duration').val()) || 6;
    if (startVal) {
        const dt = new Date(startVal);
        dt.setMonth(dt.getMonth() + duration);
        const yyyy = dt.getFullYear();
        const mm = String(dt.getMonth() + 1).padStart(2, '0');
        const dd = String(dt.getDate()).padStart(2, '0');
        const endDateStr = `${yyyy}-${mm}-${dd}`;
        $('#renew_contract_end').val(endDateStr);
        $('#renew_contract_hint').html('<i class="bi bi-clock-history me-1"></i>New term: ' + duration + ' months (' + startVal + ' to ' + endDateStr + ')');
    }
}
window.calculateRenewalEndDate = calculateRenewalEndDate;

function submitContractRenewal() {
    if (!$('#renew_confirm_check').is(':checked')) {
        Swal.fire('Confirmation Required', 'Please check the contract renewal agreement box before proceeding.', 'warning');
        return;
    }

    const empId = $('#renew_employee_id').val();
    const duration = $('#renew_duration').val();
    const start = $('#renew_contract_start').val();
    const end = $('#renew_contract_end').val();
    const salary = $('#renew_salary').val();
    const emptype = $('#renew_emptype').val();
    const notes = $('#renew_notes').val();

    if (!start || !end || !salary) {
        Swal.fire('Missing Fields', 'Please fill in all required contract fields.', 'warning');
        return;
    }

    Swal.fire({
        target: document.getElementById('renewContractModal'),
        title: 'Confirm Admin Password',
        html: 'Enter your administrator password to confirm contract renewal.',
        input: 'password',
        inputPlaceholder: 'Password',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        confirmButtonText: 'Confirm Renewal',
        cancelButtonText: 'Cancel',
        inputValidator: (val) => {
            if (!val) return 'Password is required to confirm.';
        }
    }).then(confirmResult => {
        if (!confirmResult.isConfirmed) return;

        $.ajax({
            url: 'hrms_employees.php',
            type: 'POST',
            data: {
                action: 'renew_contract',
                employee_id: empId,
                duration_months: duration,
                contract_start: start,
                contract_end: end,
                basic_salary: salary,
                employment_type: emptype,
                notes: notes,
                password: confirmResult.value
            },
            success: function (res) {
                res = res.trim();
                if (res === 'success') {
                    clearBackdropHrms();
                    $('#renewContractModal').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Contract Renewed! 🎉',
                        text: 'Employment contract has been renewed successfully.',
                        timer: 1800,
                        showConfirmButton: false
                    }).then(() => {
                        loadPage('hrms_employees.php');
                    });
                } else {
                    Swal.fire('Error', res.replace(/^error:\s*/i, ''), 'error');
                }
            },
            error: function () {
                Swal.fire('Error', 'Server communication failure.', 'error');
            }
        });
    });
}
window.submitContractRenewal = submitContractRenewal;
</script>