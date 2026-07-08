<?php
require_once '../Model/database.php';
require_once '../Model/logger.php';

if(session_status() === PHP_SESSION_NONE){
    session_start();
}
$admin_id = $_SESSION['user_id'] ?? 1;

/*=========================================================
    ACTIONS
==========================================================*/

// CREATE
if(isset($_POST['action']) && $_POST['action'] == 'create'){
    $position_id = (int)$_POST['position_id'];
    $full_name   = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $email       = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone       = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $address     = mysqli_real_escape_string($conn, trim($_POST['address']));
    $notes       = mysqli_real_escape_string($conn, trim($_POST['notes']));

    $q = mysqli_query($conn,"
        INSERT INTO applicants (position_id, full_name, email, phone, address, notes)
        VALUES ($position_id, '$full_name', '$email', '$phone', '$address', '$notes')
    ");

    if($q){
        $new_id = mysqli_insert_id($conn);
        logAction($conn, $admin_id, 'Create', 'applicants', $new_id,
            "Added applicant: $full_name");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// UPDATE
if(isset($_POST['action']) && $_POST['action'] == 'update'){
    $id          = (int)$_POST['applicant_id'];
    $position_id = (int)$_POST['position_id'];
    $full_name   = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $email       = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone       = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $address     = mysqli_real_escape_string($conn, trim($_POST['address']));
    $notes       = mysqli_real_escape_string($conn, trim($_POST['notes']));

    $q = mysqli_query($conn,"
        UPDATE applicants SET
            position_id  = $position_id,
            full_name    = '$full_name',
            email        = '$email',
            phone        = '$phone',
            address      = '$address',
            notes        = '$notes'
        WHERE applicant_id = $id
    ");

    if($q){
        logAction($conn, $admin_id, 'Update', 'applicants', $id,
            "Updated applicant: $full_name");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// ADVANCE STAGE
if(isset($_POST['action']) && $_POST['action'] == 'advance_stage'){
    $id    = (int)$_POST['applicant_id'];
    $stage = mysqli_real_escape_string($conn, $_POST['stage']);

    $allowed = ['Initial Screening','First Interview','Final Interview','Approved','Rejected'];
    if(!in_array($stage, $allowed)){
        echo 'error: Invalid stage';
        exit();
    }

    $q = mysqli_query($conn,
        "UPDATE applicants SET stage='$stage' WHERE applicant_id=$id"
    );

    if($q){
        $name = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT full_name FROM applicants WHERE applicant_id=$id"
        ))['full_name'];
        logAction($conn, $admin_id, 'Update', 'applicants', $id,
            "Advanced $name to stage: $stage");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// DELETE
if(isset($_POST['action']) && $_POST['action'] == 'delete'){
    $id = (int)$_POST['applicant_id'];
    $name = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT full_name FROM applicants WHERE applicant_id=$id"
    ))['full_name'];

    $q = mysqli_query($conn, "DELETE FROM applicants WHERE applicant_id=$id");
    if($q){
        logAction($conn, $admin_id, 'Delete', 'applicants', $id,
            "Deleted applicant: $name");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// CONVERT TO EMPLOYEE
if(isset($_POST['action']) && $_POST['action'] == 'convert'){
    $applicant_id   = (int)$_POST['applicant_id'];
    $position_id    = (int)$_POST['position_id'];
    $department_id  = (int)$_POST['department_id'];
    $full_name      = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $email          = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone          = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $address        = mysqli_real_escape_string($conn, trim($_POST['address']));
    $birthdate      = $_POST['birthdate'];
    $gender         = $_POST['gender'];
    $civil_status   = $_POST['civil_status'];
    $date_hired     = $_POST['date_hired'];
    $emp_type       = $_POST['employment_type'];
    $basic_salary   = (float)$_POST['basic_salary'];
    $sss_no         = mysqli_real_escape_string($conn, trim($_POST['sss_no']));
    $philhealth_no  = mysqli_real_escape_string($conn, trim($_POST['philhealth_no']));
    $pagibig_no     = mysqli_real_escape_string($conn, trim($_POST['pagibig_no']));
    $tin_no         = mysqli_real_escape_string($conn, trim($_POST['tin_no']));

    // Generate employee number
    $last = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT employee_no FROM employees ORDER BY employee_id DESC LIMIT 1"
    ));
    $next_num = $last ? (intval(substr($last['employee_no'], 4)) + 1) : 1;
    $emp_no = 'EMP-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);

    $q = mysqli_query($conn,"
        INSERT INTO employees (
            position_id, department_id, applicant_id, employee_no,
            full_name, email, phone, address, birthdate, gender,
            civil_status, date_hired, employment_type, basic_salary,
            sss_no, philhealth_no, pagibig_no, tin_no
        ) VALUES (
            $position_id, $department_id, $applicant_id, '$emp_no',
            '$full_name', '$email', '$phone', '$address', '$birthdate',
            '$gender', '$civil_status', '$date_hired', '$emp_type',
            $basic_salary, '$sss_no', '$philhealth_no', '$pagibig_no', '$tin_no'
        )
    ");

    if($q){
        $emp_id = mysqli_insert_id($conn);
        // Mark applicant as Approved
        mysqli_query($conn,
            "UPDATE applicants SET stage='Approved' WHERE applicant_id=$applicant_id"
        );
        logAction($conn, $admin_id, 'Create', 'employees', $emp_id,
            "Converted applicant $full_name to Employee #$emp_no");
        echo 'success:' . $emp_no;
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

/*=========================================================
    FETCH DATA
==========================================================*/

$applicants = mysqli_query($conn,"
    SELECT a.*, p.position_name, p.employment_type, d.department_name
    FROM applicants a
    LEFT JOIN positions p ON a.position_id = p.position_id
    LEFT JOIN departments d ON p.department_id = d.department_id
    ORDER BY a.applied_at DESC
");

$positions = mysqli_query($conn,
    "SELECT * FROM positions WHERE status='Open' ORDER BY position_name ASC"
);
$positionList = [];
while($p = mysqli_fetch_assoc($positions)){
    $positionList[] = $p;
}

$departments = mysqli_query($conn,
    "SELECT * FROM departments ORDER BY department_name ASC"
);
$departmentList = [];
while($d = mysqli_fetch_assoc($departments)){
    $departmentList[] = $d;
}

// Stage counts
$stageCounts = [];
$stages = ['Initial Screening','First Interview','Final Interview','Approved','Rejected'];
foreach($stages as $stage){
    $s = mysqli_real_escape_string($conn, $stage);
    $stageCounts[$stage] = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS total FROM applicants WHERE stage='$s'"
    ))['total'];
}
?>

<style>
.page-card { background:white; border-radius:14px; padding:22px 24px;
             box-shadow:0 2px 10px rgba(0,0,0,.06); margin-bottom:22px; }
.stage-card { background:white; border-radius:12px; padding:16px 18px;
              box-shadow:0 2px 8px rgba(0,0,0,.06); text-align:center; height:100%; }
.stage-badge { display:inline-block; padding:3px 10px; border-radius:20px;
               font-size:11px; font-weight:600; }
.pipeline-step {
    display:flex; align-items:center; gap:8px; padding:10px 14px;
    border-radius:8px; cursor:pointer; font-size:13px; font-weight:600;
    transition:.2s; border:2px solid transparent;
}
.pipeline-step:hover { border-color: currentColor; }
.pipeline-step.active { background:rgba(37,99,235,.1); border-color:#2563eb; }
</style>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Applicants</h4>
        <small class="text-muted">Manage recruitment pipeline and screening stages</small>
    </div>
    <button class="btn btn-primary" onclick="openAddModal()">
        <i class="bi bi-person-plus-fill me-1"></i> Add Applicant
    </button>
</div>

<!-- STAGE SUMMARY CARDS -->
<div class="row g-3 mb-4">

    <div class="col">
        <div class="stage-card">
            <div style="font-size:22px;font-weight:800;color:#6c757d;">
                <?= $stageCounts['Initial Screening']; ?>
            </div>
            <div style="font-size:11px;color:#6c757d;margin-top:4px;">Initial Screening</div>
            <span class="stage-badge mt-2" style="background:#e9ecef;color:#495057;">
                <i class="bi bi-funnel-fill"></i> Screening
            </span>
        </div>
    </div>

    <div class="col">
        <div class="stage-card">
            <div style="font-size:22px;font-weight:800;color:#0d6efd;">
                <?= $stageCounts['First Interview']; ?>
            </div>
            <div style="font-size:11px;color:#6c757d;margin-top:4px;">First Interview</div>
            <span class="stage-badge mt-2" style="background:#cfe2ff;color:#084298;">
                <i class="bi bi-person-badge"></i> Interview 1
            </span>
        </div>
    </div>

    <div class="col">
        <div class="stage-card">
            <div style="font-size:22px;font-weight:800;color:#6610f2;">
                <?= $stageCounts['Final Interview']; ?>
            </div>
            <div style="font-size:11px;color:#6c757d;margin-top:4px;">Final Interview</div>
            <span class="stage-badge mt-2" style="background:#e0cffc;color:#3d0a91;">
                <i class="bi bi-star-fill"></i> Interview 2
            </span>
        </div>
    </div>

    <div class="col">
        <div class="stage-card">
            <div style="font-size:22px;font-weight:800;color:#198754;">
                <?= $stageCounts['Approved']; ?>
            </div>
            <div style="font-size:11px;color:#6c757d;margin-top:4px;">Approved / Hired</div>
            <span class="stage-badge mt-2" style="background:#d1e7dd;color:#0a3622;">
                <i class="bi bi-check-circle-fill"></i> Hired
            </span>
        </div>
    </div>

    <div class="col">
        <div class="stage-card">
            <div style="font-size:22px;font-weight:800;color:#dc3545;">
                <?= $stageCounts['Rejected']; ?>
            </div>
            <div style="font-size:11px;color:#6c757d;margin-top:4px;">Rejected</div>
            <span class="stage-badge mt-2" style="background:#f8d7da;color:#842029;">
                <i class="bi bi-x-circle-fill"></i> Rejected
            </span>
        </div>
    </div>

</div>

<!-- APPLICANTS TABLE -->
<div class="page-card">
    <h5 class="mb-3">All Applicants</h5>
    <table class="table table-bordered table-hover datatable">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Position Applied</th>
                <th>Department</th>
                <th>Contact</th>
                <th>Stage</th>
                <th>Applied</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $i = 1;
        while($row = mysqli_fetch_assoc($applicants)){
            // Stage badge style
            switch($row['stage']){
                case 'Initial Screening': $sColor='#6c757d'; $sBg='#e9ecef'; break;
                case 'First Interview':   $sColor='#084298'; $sBg='#cfe2ff'; break;
                case 'Final Interview':   $sColor='#3d0a91'; $sBg='#e0cffc'; break;
                case 'Approved':          $sColor='#0a3622'; $sBg='#d1e7dd'; break;
                case 'Rejected':          $sColor='#842029'; $sBg='#f8d7da'; break;
                default:                  $sColor='#495057'; $sBg='#e9ecef';
            }
        ?>
        <tr>
            <td><?= $i++; ?></td>
            <td>
                <div class="fw-semibold"><?= htmlspecialchars($row['full_name']); ?></div>
                <small class="text-muted"><?= htmlspecialchars($row['email'] ?? '—'); ?></small>
            </td>
            <td>
                <?= htmlspecialchars($row['position_name'] ?? '—'); ?>
                <br><small class="text-muted"><?= $row['employment_type'] ?? ''; ?></small>
            </td>
            <td><?= htmlspecialchars($row['department_name'] ?? '—'); ?></td>
            <td><?= htmlspecialchars($row['phone'] ?? '—'); ?></td>
            <td>
                <span class="stage-badge"
                      style="background:<?= $sBg; ?>;color:<?= $sColor; ?>;">
                    <?= $row['stage']; ?>
                </span>
            </td>
            <td><?= date("M d, Y", strtotime($row['applied_at'])); ?></td>
            <td>
                <!-- VIEW/EDIT -->
                <button class="btn btn-sm btn-outline-primary me-1"
                        onclick="openEditModal(
                            <?= $row['applicant_id']; ?>,
                            <?= $row['position_id']; ?>,
                            '<?= addslashes($row['full_name']); ?>',
                            '<?= addslashes($row['email'] ?? ''); ?>',
                            '<?= addslashes($row['phone'] ?? ''); ?>',
                            '<?= addslashes($row['address'] ?? ''); ?>',
                            '<?= addslashes($row['notes'] ?? ''); ?>'
                        )">
                    <i class="bi bi-pencil-fill"></i>
                </button>

                <!-- ADVANCE STAGE (not shown if Approved or Rejected) -->
                <?php if(!in_array($row['stage'], ['Approved','Rejected'])){ ?>
                <div class="btn-group me-1">
                    <button type="button"
                            class="btn btn-sm btn-outline-success dropdown-toggle"
                            data-bs-toggle="dropdown">
                        <i class="bi bi-arrow-right-circle"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <?php
                        $nextStages = [
                            'Initial Screening' => ['First Interview','Rejected'],
                            'First Interview'   => ['Final Interview','Rejected'],
                            'Final Interview'   => ['Approved','Rejected'],
                        ];
                        $next = $nextStages[$row['stage']] ?? [];
                        foreach($next as $ns){
                            $icon = $ns == 'Rejected'
                                ? 'bi-x-circle text-danger'
                                : 'bi-arrow-right-circle text-success';
                            echo '<li><a class="dropdown-item" href="#"
                                onclick="advanceStage('.$row['applicant_id'].',\''.$ns.'\',\''.addslashes($row['full_name']).'\')">
                                <i class="bi '.$icon.' me-2"></i>'.$ns.'
                            </a></li>';
                        }
                        ?>
                    </ul>
                </div>
                <?php } ?>

                <!-- CONVERT TO EMPLOYEE (only if Approved) -->
                <?php if($row['stage'] == 'Approved'){ ?>
                <button class="btn btn-sm btn-success me-1"
                        onclick="openConvertModal(
                            <?= $row['applicant_id']; ?>,
                            <?= $row['position_id'] ?? 0; ?>,
                            '<?= addslashes($row['full_name']); ?>',
                            '<?= addslashes($row['email'] ?? ''); ?>',
                            '<?= addslashes($row['phone'] ?? ''); ?>',
                            '<?= addslashes($row['address'] ?? ''); ?>'
                        )"
                        title="Convert to Employee">
                    <i class="bi bi-person-check-fill"></i> Hire
                </button>
                <?php } ?>

                <!-- DELETE -->
                <button class="btn btn-sm btn-outline-danger"
                        onclick="deleteApplicant(<?= $row['applicant_id']; ?>, '<?= addslashes($row['full_name']); ?>')">
                    <i class="bi bi-trash-fill"></i>
                </button>
            </td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
</div>

<!--=========================================================
    ADD MODAL
==========================================================-->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a3c5e;color:white;">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus me-2"></i>Add Applicant
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_name"
                               placeholder="e.g. Juan Dela Cruz">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Position Applied <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_position">
                            <option value="">-- Select Position --</option>
                            <?php foreach($positionList as $p){ ?>
                            <option value="<?= $p['position_id']; ?>">
                                <?= htmlspecialchars($p['position_name']); ?>
                                (<?= $p['employment_type']; ?>)
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" class="form-control" id="add_email"
                               placeholder="e.g. juan@gmail.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone</label>
                        <input type="text" class="form-control" id="add_phone"
                               placeholder="e.g. 09XX-XXX-XXXX">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Address</label>
                        <input type="text" class="form-control" id="add_address"
                               placeholder="Complete address">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Notes / Remarks</label>
                        <textarea class="form-control" id="add_notes" rows="3"
                                  placeholder="Initial observations, referral source, etc."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="submitAdd()">
                    <i class="bi bi-check-lg me-1"></i>Save Applicant
                </button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    EDIT MODAL
==========================================================-->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square me-2"></i>Edit Applicant
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit_id">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Position Applied</label>
                        <select class="form-select" id="edit_position">
                            <option value="">-- Select Position --</option>
                            <?php foreach($positionList as $p){ ?>
                            <option value="<?= $p['position_id']; ?>">
                                <?= htmlspecialchars($p['position_name']); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" class="form-control" id="edit_email">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone</label>
                        <input type="text" class="form-control" id="edit_phone">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Address</label>
                        <input type="text" class="form-control" id="edit_address">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Notes / Remarks</label>
                        <textarea class="form-control" id="edit_notes" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-warning" onclick="submitEdit()">
                    <i class="bi bi-check-lg me-1"></i>Update Applicant
                </button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    CONVERT TO EMPLOYEE MODAL
==========================================================-->
<div class="modal fade" id="convertModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-person-check-fill me-2"></i>
                    Convert to Employee — <span id="conv_name_title"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="conv_applicant_id">
                <div class="alert alert-info" style="font-size:13px;">
                    <i class="bi bi-info-circle me-2"></i>
                    Fill in the employee details below. Fields marked with
                    <span class="text-danger">*</span> are required.
                    Government numbers can be added later.
                </div>

                <div class="row g-3">

                    <!-- PERSONAL INFO -->
                    <div class="col-12">
                        <div class="fw-bold text-muted mb-2" style="font-size:12px;
                             letter-spacing:1px;text-transform:uppercase;">
                            Personal Information
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="conv_name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" class="form-control" id="conv_email">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Phone</label>
                        <input type="text" class="form-control" id="conv_phone">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Birthdate</label>
                        <input type="date" class="form-control" id="conv_birthdate">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Gender</label>
                        <select class="form-select" id="conv_gender">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Civil Status</label>
                        <select class="form-select" id="conv_civil">
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Widowed">Widowed</option>
                            <option value="Separated">Separated</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Address</label>
                        <input type="text" class="form-control" id="conv_address">
                    </div>

                    <!-- EMPLOYMENT INFO -->
                    <div class="col-12 mt-2">
                        <div class="fw-bold text-muted mb-2" style="font-size:12px;
                             letter-spacing:1px;text-transform:uppercase;">
                            Employment Details
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Department <span class="text-danger">*</span></label>
                        <select class="form-select" id="conv_dept">
                            <option value="">-- Select --</option>
                            <?php foreach($departmentList as $d){ ?>
                            <option value="<?= $d['department_id']; ?>">
                                <?= htmlspecialchars($d['department_name']); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Position <span class="text-danger">*</span></label>
                        <select class="form-select" id="conv_position">
                            <option value="">-- Select --</option>
                            <?php foreach($positionList as $p){ ?>
                            <option value="<?= $p['position_id']; ?>">
                                <?= htmlspecialchars($p['position_name']); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Employment Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="conv_emptype">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Contractual">Contractual</option>
                            <option value="Probationary">Probationary</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Date Hired <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="conv_datehired">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Basic Monthly Salary <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0"
                                   class="form-control" id="conv_salary">
                        </div>
                    </div>

                    <!-- GOVERNMENT NUMBERS -->
                    <div class="col-12 mt-2">
                        <div class="fw-bold text-muted mb-2" style="font-size:12px;
                             letter-spacing:1px;text-transform:uppercase;">
                            Government Numbers
                            <span class="text-muted fw-normal">(optional — can fill later)</span>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">SSS No.</label>
                        <input type="text" class="form-control" id="conv_sss"
                               placeholder="XX-XXXXXXX-X">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">PhilHealth No.</label>
                        <input type="text" class="form-control" id="conv_philhealth"
                               placeholder="XXXX-XXXX-XXXX">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Pag-IBIG No.</label>
                        <input type="text" class="form-control" id="conv_pagibig"
                               placeholder="XXXX-XXXX-XXXX">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">TIN No.</label>
                        <input type="text" class="form-control" id="conv_tin"
                               placeholder="XXX-XXX-XXX-XXX">
                    </div>

                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" onclick="submitConvert()">
                    <i class="bi bi-person-check-fill me-1"></i>Confirm & Hire Employee
                </button>
            </div>
        </div>
    </div>
</div>

<script>

function clearBackdropHrms(){
    $(".modal-backdrop").remove();
    $("body").removeClass("modal-open").css("padding-right","");
}

/*====================================================
    ADD
====================================================*/
function openAddModal(){
    $("#add_name, #add_email, #add_phone, #add_address, #add_notes").val('');
    $("#add_position").val('');
    new bootstrap.Modal(document.getElementById('addModal')).show();
}

function submitAdd(){
    const name     = $("#add_name").val().trim();
    const position = $("#add_position").val();
    if(!name || !position){
        Swal.fire('Missing Fields','Name and Position are required.','warning');
        return;
    }
    $.post('hrms_applicants.php', {
        action:      'create',
        full_name:   name,
        position_id: position,
        email:       $("#add_email").val(),
        phone:       $("#add_phone").val(),
        address:     $("#add_address").val(),
        notes:       $("#add_notes").val()
    }, function(response){
        if(response == 'success'){
            Swal.fire({ icon:'success', title:'Applicant Added!',
                showConfirmButton:false, timer:1500 })
            .then(() => { clearBackdropHrms(); loadPage('hrms_applicants.php'); });
        } else {
            Swal.fire('Error', response, 'error');
        }
    });
}

/*====================================================
    EDIT
====================================================*/
function openEditModal(id, position, name, email, phone, address, notes){
    $("#edit_id").val(id);
    $("#edit_name").val(name);
    $("#edit_position").val(position);
    $("#edit_email").val(email);
    $("#edit_phone").val(phone);
    $("#edit_address").val(address);
    $("#edit_notes").val(notes);
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function submitEdit(){
    const name = $("#edit_name").val().trim();
    if(!name){
        Swal.fire('Missing Fields','Name is required.','warning');
        return;
    }
    $.post('hrms_applicants.php', {
        action:       'update',
        applicant_id: $("#edit_id").val(),
        full_name:    name,
        position_id:  $("#edit_position").val(),
        email:        $("#edit_email").val(),
        phone:        $("#edit_phone").val(),
        address:      $("#edit_address").val(),
        notes:        $("#edit_notes").val()
    }, function(response){
        if(response == 'success'){
            Swal.fire({ icon:'success', title:'Applicant Updated!',
                showConfirmButton:false, timer:1500 })
            .then(() => { clearBackdropHrms(); loadPage('hrms_applicants.php'); });
        } else {
            Swal.fire('Error', response, 'error');
        }
    });
}

/*====================================================
    ADVANCE STAGE
====================================================*/
function advanceStage(id, stage, name){
    const icons = {
        'First Interview':  '🎤',
        'Final Interview':  '⭐',
        'Approved':         '✅',
        'Rejected':         '❌'
    };
    const colors = {
        'Approved': '#198754',
        'Rejected': '#dc3545',
    };

    Swal.fire({
        title: `Move ${name} to:`,
        html: `<strong>${icons[stage] || ''} ${stage}</strong>`,
        icon: stage == 'Rejected' ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: colors[stage] || '#2563eb',
        confirmButtonText: 'Yes, Move'
    }).then(result => {
        if(!result.isConfirmed) return;
        $.post('hrms_applicants.php', {
            action:       'advance_stage',
            applicant_id: id,
            stage:        stage
        }, function(response){
            if(response == 'success'){
                Swal.fire({ icon:'success',
                    title: stage == 'Rejected' ? 'Applicant Rejected' : 'Stage Updated!',
                    showConfirmButton:false, timer:1500 })
                .then(() => loadPage('hrms_applicants.php'));
            } else {
                Swal.fire('Error', response, 'error');
            }
        });
    });
}

/*====================================================
    DELETE
====================================================*/
function deleteApplicant(id, name){
    Swal.fire({
        title: 'Delete ' + name + '?',
        text: 'This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Delete'
    }).then(result => {
        if(!result.isConfirmed) return;
        $.post('hrms_applicants.php', {
            action: 'delete', applicant_id: id
        }, function(response){
            if(response == 'success'){
                Swal.fire({ icon:'success', title:'Deleted!',
                    showConfirmButton:false, timer:1500 })
                .then(() => loadPage('hrms_applicants.php'));
            } else {
                Swal.fire('Error', response, 'error');
            }
        });
    });
}

/*====================================================
    CONVERT TO EMPLOYEE
====================================================*/
function openConvertModal(applicantId, positionId, name, email, phone, address){
    $("#conv_applicant_id").val(applicantId);
    $("#conv_name_title").text(name);
    $("#conv_name").val(name);
    $("#conv_email").val(email);
    $("#conv_phone").val(phone);
    $("#conv_address").val(address);
    $("#conv_position").val(positionId);
    $("#conv_datehired").val(new Date().toISOString().split('T')[0]);
    $("#conv_birthdate, #conv_sss, #conv_philhealth, #conv_pagibig, #conv_tin, #conv_salary").val('');
    $("#conv_gender").val('Male');
    $("#conv_civil").val('Single');
    $("#conv_emptype").val('Full-time');
    $("#conv_dept").val('');
    new bootstrap.Modal(document.getElementById('convertModal')).show();
}

function submitConvert(){
    const name     = $("#conv_name").val().trim();
    const dept     = $("#conv_dept").val();
    const position = $("#conv_position").val();
    const hired    = $("#conv_datehired").val();
    const salary   = $("#conv_salary").val();

    if(!name || !dept || !position || !hired || !salary){
        Swal.fire('Missing Fields',
            'Name, Department, Position, Date Hired, and Salary are required.','warning');
        return;
    }

    Swal.fire({
        title: 'Confirm Hiring?',
        html: `<strong>${name}</strong> will be officially added as an employee.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Yes, Hire!'
    }).then(result => {
        if(!result.isConfirmed) return;

        $.post('hrms_applicants.php', {
            action:          'convert',
            applicant_id:    $("#conv_applicant_id").val(),
            position_id:     position,
            department_id:   dept,
            full_name:       name,
            email:           $("#conv_email").val(),
            phone:           $("#conv_phone").val(),
            address:         $("#conv_address").val(),
            birthdate:       $("#conv_birthdate").val(),
            gender:          $("#conv_gender").val(),
            civil_status:    $("#conv_civil").val(),
            date_hired:      hired,
            employment_type: $("#conv_emptype").val(),
            basic_salary:    salary,
            sss_no:          $("#conv_sss").val(),
            philhealth_no:   $("#conv_philhealth").val(),
            pagibig_no:      $("#conv_pagibig").val(),
            tin_no:          $("#conv_tin").val()
        }, function(response){
            if(response.startsWith('success:')){
                const empNo = response.split(':')[1];
                Swal.fire({
                    icon: 'success',
                    title: 'Employee Hired! 🎉',
                    html: `<strong>${name}</strong> is now Employee <strong>#${empNo}</strong>`,
                    confirmButtonColor: '#198754'
                }).then(() => {
                    clearBackdropHrms();
                    loadPage('hrms_applicants.php');
                });
            } else {
                Swal.fire('Error', response, 'error');
            }
        });
    });
}

</script>