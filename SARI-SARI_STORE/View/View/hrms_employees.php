<?php
require_once '../Model/database.php';
require_once '../Model/logger.php';

if(session_status() === PHP_SESSION_NONE){
    session_start();
}
$admin_id = $_SESSION['user_id'] ?? 1;

define('EMPLOYEE_UPLOAD_DIR', __DIR__ . '/uploads/employees/');
define('EMPLOYEE_UPLOAD_URL', 'uploads/employees/');

if(!is_dir(EMPLOYEE_UPLOAD_DIR)){
    mkdir(EMPLOYEE_UPLOAD_DIR, 0755, true);
}

// Helpers
function getInitials($name) {
    $words = explode(" ", preg_replace('/[^a-zA-Z0-9\s]/', '', $name));
    $initials = "";
    foreach ($words as $w) {
        $initials .= strtoupper(substr($w, 0, 1));
        if(strlen($initials) >= 2) break;
    }
    return $initials ?: "?";
}

function handleEmployeeImageUpload($file, &$error){
    $allowedExt  = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize     = 2 * 1024 * 1024; // 2MB

    if($file['error'] !== UPLOAD_ERR_OK){
        $error = 'Image upload failed. Please try again.';
        return false;
    }
    if($file['size'] > $maxSize){
        $error = 'Image must be smaller than 2MB.';
        return false;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if(!in_array($ext, $allowedExt)){
        $error = 'Only JPG, PNG, or WEBP images are allowed.';
        return false;
    }

    $mime = mime_content_type($file['tmp_name']);
    if(!in_array($mime, $allowedMime)){
        $error = 'Invalid image file.';
        return false;
    }

    $newName = 'emp_' . uniqid() . '.' . $ext;
    if(!move_uploaded_file($file['tmp_name'], EMPLOYEE_UPLOAD_DIR . $newName)){
        $error = 'Could not save the uploaded image.';
        return false;
    }
    return $newName;
}

/*=========================================================
    ACTIONS (POST)
==========================================================*/

// CREATE
if(isset($_POST['action']) && $_POST['action'] == 'create'){
    $position_id     = (int)$_POST['position_id'];
    $department_id   = (int)$_POST['department_id'];
    $full_name       = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $email           = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone           = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $address         = mysqli_real_escape_string($conn, trim($_POST['address']));
    $birthdate       = !empty($_POST['birthdate']) ? mysqli_real_escape_string($conn, $_POST['birthdate']) : NULL;
    $gender          = mysqli_real_escape_string($conn, $_POST['gender']);
    $civil_status    = mysqli_real_escape_string($conn, $_POST['civil_status']);
    $date_hired      = !empty($_POST['date_hired']) ? mysqli_real_escape_string($conn, $_POST['date_hired']) : date('Y-m-d');
    $employment_type = mysqli_real_escape_string($conn, $_POST['employment_type']);
    $basic_salary    = (float)$_POST['basic_salary'];
    $sss_no          = mysqli_real_escape_string($conn, trim($_POST['sss_no']));
    $philhealth_no   = mysqli_real_escape_string($conn, trim($_POST['philhealth_no']));
    $pagibig_no      = mysqli_real_escape_string($conn, trim($_POST['pagibig_no']));
    $tin_no          = mysqli_real_escape_string($conn, trim($_POST['tin_no']));

    // Handle photo upload
    $photo = '';
    if(isset($_FILES['photo']) && $_FILES['photo']['size'] > 0){
        $error = '';
        $uploaded = handleEmployeeImageUpload($_FILES['photo'], $error);
        if(!$uploaded){
            ob_clean();
            echo 'error: ' . $error;
            exit();
        }
        $photo = $uploaded;
    }

    // Generate employee number
    $last = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT employee_no FROM employees ORDER BY employee_id DESC LIMIT 1"
    ));
    $next_num = $last ? (intval(substr($last['employee_no'], 4)) + 1) : 1;
    $emp_no = 'EMP-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);

    $birthdate_val = $birthdate ? "'$birthdate'" : "NULL";
    $photo_val = $photo ? "'$photo'" : "NULL";

    $q = mysqli_query($conn, "
        INSERT INTO employees (
            position_id, department_id, employee_no, full_name, email, phone, address,
            birthdate, gender, civil_status, date_hired, employment_type, basic_salary,
            sss_no, philhealth_no, pagibig_no, tin_no, photo, status
        ) VALUES (
            $position_id, $department_id, '$emp_no', '$full_name', '$email', '$phone', '$address',
            $birthdate_val, '$gender', '$civil_status', '$date_hired', '$employment_type', $basic_salary,
            '$sss_no', '$philhealth_no', '$pagibig_no', '$tin_no', $photo_val, 'Active'
        )
    ");

    ob_clean();
    if($q){
        $new_id = mysqli_insert_id($conn);
        logAction($conn, $admin_id, 'Create', 'employees', $new_id,
            "Added employee: $full_name (#$emp_no)");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// UPDATE
if(isset($_POST['action']) && $_POST['action'] == 'update'){
    $id              = (int)$_POST['employee_id'];
    $position_id     = (int)$_POST['position_id'];
    $department_id   = (int)$_POST['department_id'];
    $full_name       = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $email           = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone           = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $address         = mysqli_real_escape_string($conn, trim($_POST['address']));
    $birthdate       = !empty($_POST['birthdate']) ? mysqli_real_escape_string($conn, $_POST['birthdate']) : NULL;
    $gender          = mysqli_real_escape_string($conn, $_POST['gender']);
    $civil_status    = mysqli_real_escape_string($conn, $_POST['civil_status']);
    $date_hired      = !empty($_POST['date_hired']) ? mysqli_real_escape_string($conn, $_POST['date_hired']) : date('Y-m-d');
    $employment_type = mysqli_real_escape_string($conn, $_POST['employment_type']);
    $basic_salary    = (float)$_POST['basic_salary'];
    $sss_no          = mysqli_real_escape_string($conn, trim($_POST['sss_no']));
    $philhealth_no   = mysqli_real_escape_string($conn, trim($_POST['philhealth_no']));
    $pagibig_no      = mysqli_real_escape_string($conn, trim($_POST['pagibig_no']));
    $tin_no          = mysqli_real_escape_string($conn, trim($_POST['tin_no']));
    $status          = mysqli_real_escape_string($conn, $_POST['status']);

    // Check if new photo uploaded
    $photo_query = "";
    if(isset($_FILES['photo']) && $_FILES['photo']['size'] > 0){
        $error = '';
        $uploaded = handleEmployeeImageUpload($_FILES['photo'], $error);
        if(!$uploaded){
            ob_clean();
            echo 'error: ' . $error;
            exit();
        }
        $photo_query = ", photo = '$uploaded'";
    }

    $birthdate_val = $birthdate ? "'$birthdate'" : "NULL";

    $q = mysqli_query($conn, "
        UPDATE employees SET
            position_id = $position_id,
            department_id = $department_id,
            full_name = '$full_name',
            email = '$email',
            phone = '$phone',
            address = '$address',
            birthdate = $birthdate_val,
            gender = '$gender',
            civil_status = '$civil_status',
            date_hired = '$date_hired',
            employment_type = '$employment_type',
            basic_salary = $basic_salary,
            sss_no = '$sss_no',
            philhealth_no = '$philhealth_no',
            pagibig_no = '$pagibig_no',
            tin_no = '$tin_no',
            status = '$status'
            $photo_query
        WHERE employee_id = $id
    ");

    ob_clean();
    if($q){
        logAction($conn, $admin_id, 'Update', 'employees', $id,
            "Updated details of employee: $full_name");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// CHANGE STATUS
if(isset($_POST['action']) && $_POST['action'] == 'change_status'){
    $id     = (int)$_POST['employee_id'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $allowed = ['Active', 'Inactive', 'Resigned', 'Terminated'];
    if(!in_array($status, $allowed)){
        ob_clean();
        echo 'error: Invalid status';
        exit();
    }

    $q = mysqli_query($conn, "UPDATE employees SET status='$status' WHERE employee_id=$id");
    ob_clean();
    if($q){
        $name = mysqli_fetch_assoc(mysqli_query($conn, "SELECT full_name FROM employees WHERE employee_id=$id"))['full_name'];
        logAction($conn, $admin_id, 'Status Change', 'employees', $id,
            "Changed status of $name to: $status");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// DELETE
if(isset($_POST['action']) && $_POST['action'] == 'delete'){
    $id = (int)$_POST['employee_id'];
    
    $emp = mysqli_fetch_assoc(mysqli_query($conn, "SELECT full_name, employee_no FROM employees WHERE employee_id=$id"));
    
    $q = mysqli_query($conn, "DELETE FROM employees WHERE employee_id = $id");
    ob_clean();
    if($q){
        logAction($conn, $admin_id, 'Delete', 'employees', $id,
            "Deleted employee: " . ($emp['full_name'] ?? 'Unknown') . " (#" . ($emp['employee_no'] ?? '') . ")");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

/*=========================================================
    FETCH DATA
==========================================================*/

$employees = mysqli_query($conn, "
    SELECT e.*, p.position_name, d.department_name
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.position_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    ORDER BY e.employee_no ASC
");

$empList = [];
while($row = mysqli_fetch_assoc($employees)){
    $empList[] = $row;
}

// Departments
$departments = mysqli_query($conn, "SELECT * FROM departments ORDER BY department_name ASC");
$departmentList = [];
while($d = mysqli_fetch_assoc($departments)){
    $departmentList[] = $d;
}

// Positions (with department ID mapped for dynamic dropdown filter)
$positions = mysqli_query($conn, "
    SELECT p.*, d.department_name 
    FROM positions p
    LEFT JOIN departments d ON p.department_id = d.department_id
    ORDER BY p.position_name ASC
");
$positionList = [];
while($p = mysqli_fetch_assoc($positions)){
    $positionList[] = $p;
}

// Stats metrics
$totalCount    = count($empList);
$activeCount   = 0;
$inactiveCount = 0;
$totalSalary   = 0.0;
foreach($empList as $e){
    if($e['status'] == 'Active') {
        $activeCount++;
        $totalSalary += (float)$e['basic_salary'];
    } else {
        $inactiveCount++;
    }
}
$avgSalary = $activeCount > 0 ? ($totalSalary / $activeCount) : 0;
?>

<style>
/* Custom Styles for Employees Module */
.page-card {
    background: white;
    border-radius: 14px;
    padding: 22px 24px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
    margin-bottom: 22px;
}
.stat-card {
    background: white;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
    height: 100%;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.stat-icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; color: white; flex-shrink: 0;
}
.stat-label { font-size: 11px; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.stat-value { font-size: 24px; font-weight: 800; line-height: 1.2; margin-top: 4px; }

/* Status Badges */
.badge-status {
    font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px;
}
.badge-active { background: #d1fae5; color: #065f46; }
.badge-inactive { background: #f3f4f6; color: #374151; }
.badge-resigned { background: #fef3c7; color: #92400e; }
.badge-terminated { background: #fee2e2; color: #991b1b; }

/* Profile View Card inside Modal */
.profile-modal-header {
    background: linear-gradient(135deg, #1a3c5e, #2b5c8f);
    color: white;
    padding: 30px 24px;
    border-top-left-radius: .4rem;
    border-top-right-radius: .4rem;
}
.profile-avatar-large {
    width: 100px; height: 100px;
    border-radius: 50%;
    border: 4px solid rgba(255,255,255,0.25);
    background: #2563eb;
    color: white;
    font-weight: 800;
    font-size: 36px;
    display: flex; align-items: center; justify-content: center;
    object-fit: cover;
}
.profile-meta-item {
    font-size: 13px; color: rgba(255,255,255,0.85);
}
.info-section-title {
    font-size: 12px; font-weight: 700; color: #6b7280;
    text-transform: uppercase; letter-spacing: 1px;
    border-bottom: 2px solid #e5e7eb; padding-bottom: 6px; margin-bottom: 12px;
}
</style>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #1a3c5e;">Employees</h4>
        <small class="text-muted">Manage workforce details, salaries, and statutory info</small>
    </div>
    <button class="btn btn-primary" onclick="openAddModal()">
        <i class="bi bi-person-plus-fill me-1"></i> Add Employee
    </button>
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
                <div class="stat-label">Avg Active Salary</div>
                <div class="stat-value">₱<?= number_format($avgSalary, 2); ?></div>
            </div>
            <div class="stat-icon bg-info"><i class="bi bi-cash-stack"></i></div>
        </div>
    </div>
</div>

<!-- TABLE GRID -->
<div class="page-card animate__animated animate__fadeIn">
    <div class="table-responsive">
        <table class="table table-hover align-middle datatable w-100" id="employeesTable">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">Photo</th>
                    <th style="width: 100px;">Employee ID</th>
                    <th>Full Name</th>
                    <th>Department & Position</th>
                    <th>Type</th>
                    <th>Basic Salary</th>
                    <th>Hired Date</th>
                    <th>Status</th>
                    <th style="width: 140px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($empList as $row) { 
                    $badgeClass = 'badge-active';
                    if($row['status'] == 'Inactive') $badgeClass = 'badge-inactive';
                    elseif($row['status'] == 'Resigned') $badgeClass = 'badge-resigned';
                    elseif($row['status'] == 'Terminated') $badgeClass = 'badge-terminated';
                ?>
                <tr>
                    <td>
                        <?php if(!empty($row['photo']) && file_exists(EMPLOYEE_UPLOAD_DIR . $row['photo'])){ ?>
                            <img src="<?= EMPLOYEE_UPLOAD_URL . $row['photo']; ?>?t=<?= time(); ?>" class="rounded-circle" style="width:38px;height:38px;object-fit:cover;border: 1px solid #ddd;">
                        <?php } else { ?>
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:38px;height:38px;font-size:12px;font-weight:700;">
                                <?= getInitials($row['full_name']); ?>
                            </div>
                        <?php } ?>
                    </td>
                    <td class="fw-bold text-secondary"><?= htmlspecialchars($row['employee_no']); ?></td>
                    <td>
                        <div class="fw-semibold text-dark"><?= htmlspecialchars($row['full_name']); ?></div>
                        <div class="text-muted" style="font-size:11px;">
                            <i class="bi bi-telephone-fill me-1"></i><?= htmlspecialchars($row['phone'] ?: 'N/A'); ?>
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold" style="font-size:13.5px;"><?= htmlspecialchars($row['position_name'] ?: 'N/A'); ?></div>
                        <div class="text-muted" style="font-size:11.5px;"><?= htmlspecialchars($row['department_name'] ?: 'N/A'); ?></div>
                    </td>
                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['employment_type'] ?: 'Full-time'); ?></span></td>
                    <td class="fw-bold text-success">₱<?= number_format($row['basic_salary'], 2); ?></td>
                    <td><?= $row['date_hired'] ? date("M d, Y", strtotime($row['date_hired'])) : 'N/A'; ?></td>
                    <td><span class="badge-status <?= $badgeClass; ?>"><?= htmlspecialchars($row['status']); ?></span></td>
                    <td>
                        <div class="d-flex justify-content-center gap-1">
                            <!-- VIEW -->
                            <button class="btn btn-sm btn-outline-primary" 
                                    onclick="viewProfile(<?= htmlspecialchars(json_encode($row)); ?>)" 
                                    title="View Profile">
                                <i class="bi bi-eye-fill"></i>
                            </button>
                            <!-- EDIT -->
                            <button class="btn btn-sm btn-outline-warning" 
                                    onclick="openEditModal(<?= htmlspecialchars(json_encode($row)); ?>)" 
                                    title="Edit Employee">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <!-- DELETE -->
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="deleteEmployee(<?= $row['employee_id']; ?>, '<?= addslashes($row['full_name']); ?>')" 
                                    title="Delete Employee">
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
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="profile-modal-header d-flex align-items-center gap-3">
                <div id="v_avatar_container"></div>
                <div class="flex-grow-1">
                    <h4 class="fw-bold mb-1" id="v_name"></h4>
                    <div id="v_emp_no" class="badge bg-white text-dark fw-bold mb-1"></div>
                    <div class="profile-meta-item">
                        <i class="bi bi-briefcase-fill me-1"></i><span id="v_job_title"></span> | <span id="v_department"></span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white align-self-start" data-bs-dismiss="modal"></button>
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
                                <td class="fw-semibold"><span class="badge bg-light text-dark border" id="v_type"></span></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Basic Salary:</th>
                                <td class="fw-semibold text-success" id="v_salary"></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Current Status:</th>
                                <td class="fw-semibold" id="v_status_badge"></td>
                            </tr>
                        </table>
                    </div>

                    <!-- STATUTORY INFORMATION -->
                    <div class="col-12 mt-2">
                        <div class="info-section-title">Statutory Details & Government IDs</div>
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
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close Profile</button>
            </div>
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
                        <div class="col-12"><div class="fw-bold text-primary mb-1 uppercase" style="font-size:12px;letter-spacing:.5px;">Personal Details</div></div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" required placeholder="e.g. Maria Clara">
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
                            <input type="text" class="form-control" name="address" placeholder="Complete physical address">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Profile Photo</label>
                            <input type="file" class="form-control" name="photo" accept="image/*">
                        </div>

                        <!-- EMPLOYMENT -->
                        <div class="col-12 mt-3"><div class="fw-bold text-primary mb-1 uppercase" style="font-size:12px;letter-spacing:.5px;">Employment & Salary Details</div></div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Department <span class="text-danger">*</span></label>
                            <select class="form-select" name="department_id" id="add_dept" required>
                                <option value="">-- Select Department --</option>
                                <?php foreach($departmentList as $d) { ?>
                                <option value="<?= $d['department_id']; ?>"><?= htmlspecialchars($d['department_name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Position <span class="text-danger">*</span></label>
                            <select class="form-select" name="position_id" id="add_pos" required>
                                <option value="">-- Select Position --</option>
                                <?php foreach($positionList as $p) { ?>
                                <option value="<?= $p['position_id']; ?>" data-dept="<?= $p['department_id']; ?>">
                                    <?= htmlspecialchars($p['position_name']); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Employment Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="employment_type">
                                <option value="Full-time">Full-time</option>
                                <option value="Part-time">Part-time</option>
                                <option value="Contractual">Contractual</option>
                                <option value="Probationary">Probationary</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Date Hired <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_hired" required value="<?= date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Basic Salary (Monthly PHP) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" class="form-control" name="basic_salary" required placeholder="0.00" min="0">
                            </div>
                        </div>

                        <!-- STATUTORY -->
                        <div class="col-12 mt-3"><div class="fw-bold text-primary mb-1 uppercase" style="font-size:12px;letter-spacing:.5px;">Statutory Identifications</div></div>
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
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Employee</button>
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
                        <div class="col-12"><div class="fw-bold text-primary mb-1 uppercase" style="font-size:12px;letter-spacing:.5px;">Personal Details</div></div>
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

                        <!-- EMPLOYMENT -->
                        <div class="col-12 mt-3"><div class="fw-bold text-primary mb-1 uppercase" style="font-size:12px;letter-spacing:.5px;">Employment & Salary Details</div></div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Department <span class="text-danger">*</span></label>
                            <select class="form-select" name="department_id" id="edit_dept" required>
                                <option value="">-- Select Department --</option>
                                <?php foreach($departmentList as $d) { ?>
                                <option value="<?= $d['department_id']; ?>"><?= htmlspecialchars($d['department_name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Position <span class="text-danger">*</span></label>
                            <select class="form-select" name="position_id" id="edit_pos" required>
                                <option value="">-- Select Position --</option>
                                <?php foreach($positionList as $p) { ?>
                                <option value="<?= $p['position_id']; ?>" data-dept="<?= $p['department_id']; ?>">
                                    <?= htmlspecialchars($p['position_name']); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Employment Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="employment_type" id="edit_emptype">
                                <option value="Full-time">Full-time</option>
                                <option value="Part-time">Part-time</option>
                                <option value="Contractual">Contractual</option>
                                <option value="Probationary">Probationary</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Employment Status <span class="text-danger">*</span></label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Resigned">Resigned</option>
                                <option value="Terminated">Terminated</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Date Hired <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_hired" id="edit_datehired" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Basic Salary (Monthly PHP) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" class="form-control" name="basic_salary" id="edit_salary" required min="0">
                            </div>
                        </div>

                        <!-- STATUTORY -->
                        <div class="col-12 mt-3"><div class="fw-bold text-primary mb-1 uppercase" style="font-size:12px;letter-spacing:.5px;">Statutory Identifications</div></div>
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
                    <button type="submit" class="btn btn-warning"><i class="bi bi-check-lg me-1"></i>Update Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    // Initialize standard plugins
    if($.fn.DataTable) {
        $('#employeesTable').DataTable({
            responsive: true,
            pageLength: 10,
            lengthChange: false,
            ordering: true,
            searching: true,
            destroy: true // prevents multiple initialization error
        });
    }

    // Dynamic filtering of position dropdowns depending on selected department
    $('#add_dept').on('change', function(){
        const deptId = $(this).val();
        $('#add_pos').val(''); // clear position select
        $('#add_pos option').each(function(){
            const optionDeptId = $(this).data('dept');
            if(!optionDeptId) return; // skip placeholder
            if(!deptId || optionDeptId == deptId){
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    $('#edit_dept').on('change', function(){
        const deptId = $(this).val();
        $('#edit_pos').val(''); // clear position select
        $('#edit_pos option').each(function(){
            const optionDeptId = $(this).data('dept');
            if(!optionDeptId) return; // skip placeholder
            if(!deptId || optionDeptId == deptId){
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // Handle ADD form submission via AJAX
    $('#addForm').on('submit', function(e){
        e.preventDefault();
        
        let formData = new FormData(this);
        formData.append('action', 'create');

        $.ajax({
            url: 'hrms_employees.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response){
                if(response.trim() === 'success'){
                    Swal.fire({
                        icon: 'success',
                        title: 'Employee Added!',
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
            error: function(){
                Swal.fire('Error', 'Server communication failure.', 'error');
            }
        });
    });

    // Handle EDIT form submission via AJAX
    $('#editForm').on('submit', function(e){
        e.preventDefault();

        let formData = new FormData(this);
        formData.append('action', 'update');

        $.ajax({
            url: 'hrms_employees.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response){
                if(response.trim() === 'success'){
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
            error: function(){
                Swal.fire('Error', 'Server communication failure.', 'error');
            }
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
    OPEN ADD MODAL
====================================================*/
function openAddModal(){
    $('#addForm')[0].reset();
    $('#add_dept').trigger('change'); // trigger filter reset
    new bootstrap.Modal(document.getElementById('addModal')).show();
}

/*====================================================
    VIEW EMPLOYEE PROFILE
====================================================*/
function viewProfile(emp){
    // Render Avatar Large or Initials
    let avatarHtml = '';
    if(emp.photo && emp.photo !== ''){
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
    $('#v_department').text(emp.department_name || 'N/A');
    
    $('#v_birthdate').text(emp.birthdate ? formatDate(emp.birthdate) : 'N/A');
    $('#v_gender').text(emp.gender || 'N/A');
    $('#v_civil').text(emp.civil_status || 'N/A');
    $('#v_phone').text(emp.phone || 'N/A');
    $('#v_email').text(emp.email || 'N/A');
    $('#v_address').text(emp.address || 'N/A');
    
    $('#v_hired').text(emp.date_hired ? formatDate(emp.date_hired) : 'N/A');
    $('#v_type').text(emp.employment_type || 'Full-time');
    $('#v_salary').text('₱' + parseFloat(emp.basic_salary).toLocaleString('en-US', {minimumFractionDigits: 2}));
    
    // Status badge rendering
    let statusClass = 'badge bg-success';
    if(emp.status === 'Inactive') statusClass = 'badge bg-secondary';
    else if(emp.status === 'Resigned') statusClass = 'badge bg-warning text-dark';
    else if(emp.status === 'Terminated') statusClass = 'badge bg-danger';
    $('#v_status_badge').html(`<span class="${statusClass}">${emp.status}</span>`);

    // Government IDs
    $('#v_sss').text(emp.sss_no || 'N/A');
    $('#v_philhealth').text(emp.philhealth_no || 'N/A');
    $('#v_pagibig').text(emp.pagibig_no || 'N/A');
    $('#v_tin').text(emp.tin_no || 'N/A');

    new bootstrap.Modal(document.getElementById('viewModal')).show();
}

/*====================================================
    OPEN EDIT MODAL
====================================================*/
function openEditModal(emp){
    $('#editForm')[0].reset();
    
    $('#edit_id').val(emp.employee_id);
    $('#edit_name').val(emp.full_name);
    $('#edit_email').val(emp.email);
    $('#edit_phone').val(emp.phone);
    $('#edit_birthdate').val(emp.birthdate);
    $('#edit_gender').val(emp.gender || 'Female');
    $('#edit_civil').val(emp.civil_status || 'Single');
    $('#edit_address').val(emp.address);
    
    // Pre-select and filter positions dropdown
    $('#edit_dept').val(emp.department_id);
    
    // Trigger dynamic filter to show appropriate positions for the selected department
    const deptId = emp.department_id;
    $('#edit_pos option').each(function(){
        const optionDeptId = $(this).data('dept');
        if(!optionDeptId) return; // skip placeholder
        if(!deptId || optionDeptId == deptId){
            $(this).show();
        } else {
            $(this).hide();
        }
    });
    
    $('#edit_pos').val(emp.position_id);
    $('#edit_emptype').val(emp.employment_type || 'Full-time');
    $('#edit_status').val(emp.status || 'Active');
    $('#edit_datehired').val(emp.date_hired);
    $('#edit_salary').val(emp.basic_salary);
    
    $('#edit_sss').val(emp.sss_no);
    $('#edit_philhealth').val(emp.philhealth_no);
    $('#edit_pagibig').val(emp.pagibig_no);
    $('#edit_tin').val(emp.tin_no);

    // Show preview avatar
    let previewHtml = '';
    if(emp.photo && emp.photo !== ''){
        previewHtml = `<img src="uploads/employees/${emp.photo}?t=${Date.now()}" class="rounded-circle border" style="width:50px;height:50px;object-fit:cover;">`;
    } else {
        const initials = getInitialsFromJS(emp.full_name);
        previewHtml = `<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width:50px;height:50px;font-size:16px;">${initials}</div>`;
    }
    $('#edit_avatar_prev').html(previewHtml);

    new bootstrap.Modal(document.getElementById('editModal')).show();
}

/*====================================================
    DELETE EMPLOYEE
====================================================*/
function deleteEmployee(id, name){
    Swal.fire({
        title: 'Delete Employee?',
        html: `Are you sure you want to delete employee record for <strong>${name}</strong>? This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Delete Record'
    }).then(result => {
        if(!result.isConfirmed) return;

        $.post('hrms_employees.php', {
            action: 'delete',
            employee_id: id
        }, function(response){
            if(response.trim() === 'success'){
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    loadPage('hrms_employees.php');
                });
            } else {
                Swal.fire('Error', response, 'error');
            }
        });
    });
}

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

function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}
</script>
