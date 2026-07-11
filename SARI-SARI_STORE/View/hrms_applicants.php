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

// DELETE — archive first then delete
if(isset($_POST['action']) && $_POST['action'] == 'delete'){
    $id     = (int)$_POST['applicant_id'];
    $reason = mysqli_real_escape_string($conn, trim($_POST['reason'] ?? 'Removed'));

    $app = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM applicants WHERE applicant_id = $id"
    ));

    if(!$app){
        echo 'error: Applicant not found.';
        exit();
    }

    $full_name  = mysqli_real_escape_string($conn, $app['full_name']);
    $email      = mysqli_real_escape_string($conn, $app['email'] ?? '');
    $phone      = mysqli_real_escape_string($conn, $app['phone'] ?? '');
    $address    = mysqli_real_escape_string($conn, $app['address'] ?? '');
    $resume     = mysqli_real_escape_string($conn, $app['resume'] ?? '');
    $notes      = mysqli_real_escape_string($conn, $app['notes'] ?? '');
    $stage      = mysqli_real_escape_string($conn, $app['stage']);
    $applied_at = $app['applied_at'] ? "'{$app['applied_at']}'" : 'NULL';

    // Archive the applicant
    mysqli_query($conn,"
        INSERT INTO applicants_archive
            (applicant_id, position_id, full_name, email, phone, address,
             resume, stage, notes, applied_at, archive_reason, archived_by)
        VALUES
            ($id, {$app['position_id']}, '$full_name', '$email', '$phone', '$address',
             '$resume', '$stage', '$notes', $applied_at, '$reason', $admin_id)
    ");

    $q = mysqli_query($conn, "DELETE FROM applicants WHERE applicant_id = $id");
    if($q){
        logAction($conn, $admin_id, 'Delete', 'applicants', $id,
            "Archived & removed applicant: $full_name — Reason: $reason");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// PHPMailer welcome email helper
function sendEmployeePasswordResetEmail($gmail, $name, $password) {
    require_once __DIR__ . '/../Assets/PHPMailer/Exception.php';
    require_once __DIR__ . '/../Assets/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/../Assets/PHPMailer/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'edonnarao06@gmail.com';
        $mail->Password = 'pqda kqsx qnxo pqsp';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom('edonnarao06@gmail.com', 'O-cart! HRMS');
        $mail->addAddress($gmail);

        $mail->isHTML(true);
        $mail->Subject = 'Your Employee Portal Password Was Updated - O-cart!';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px; max-width: 500px;'>
                <h2 style='color: #1a3c5e;'>O-cart! E-Portal</h2>
                <p>Hello <strong>$name</strong>,</p>
                <p>You have been hired, and since this Gmail already had a portal account, its password has been reset.</p>
                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                <p><strong>Your Updated Credentials:</strong></p>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 5px 0; color: #666;'>Portal URL:</td>
                        <td><a href='http://localhost/SARI-SARI_STORE/View/login.php'>Login Here</a></td>
                    </tr>
                    <tr>
                        <td style='padding: 5px 0; color: #666;'>Username (Email):</td>
                        <td><strong>$gmail</strong></td>
                    </tr>
                    <tr>
                        <td style='padding: 5px 0; color: #666;'>New Password:</td>
                        <td><code style='background: #f4f6f5; padding: 3px 6px; border-radius: 3px; font-weight: bold;'>$password</code></td>
                    </tr>
                </table>
                <p style='margin-top: 25px; font-size: 12px; color: #888;'>If you did not expect this change, please contact your HR department immediately.</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Password Reset Email Error: " . $mail->ErrorInfo);
        return 'ERR: ' . $mail->ErrorInfo;
    }
}

function sendEmployeeWelcomeEmail($gmail, $name, $password) {
    require_once __DIR__ . '/../Assets/PHPMailer/Exception.php';
    require_once __DIR__ . '/../Assets/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/../Assets/PHPMailer/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'edonnarao06@gmail.com';
        $mail->Password = 'pqda kqsx qnxo pqsp';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom('edonnarao06@gmail.com', 'Sari-Sari Store HRMS');
        $mail->addAddress($gmail);

        $mail->isHTML(true);
        $mail->Subject = 'Your Employee Portal Credentials - Sari-Sari Store';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px; max-width: 500px;'>
                <h2 style='color: #1a3c5e;'>Sari-Sari Store Employee Portal</h2>
                <p>Hello <strong>$name</strong>,</p>
                <p>Welcome to our team! An employee account has been created for you. You can now log into your employee portal to manage your schedule, view payslips, and request leaves.</p>
                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                <p><strong>Your Login Credentials:</strong></p>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 5px 0; color: #666;'>Portal URL:</td>
                        <td><a href='http://localhost/SARI-SARI_STORE/View/login.php'>Login Here</a></td>
                    </tr>
                    <tr>
                        <td style='padding: 5px 0; color: #666;'>Username (Email):</td>
                        <td><strong>$gmail</strong></td>
                    </tr>
                    <tr>
                        <td style='padding: 5px 0; color: #666;'>Password:</td>
                        <td><code style='background: #f4f6f5; padding: 3px 6px; border-radius: 3px; font-weight: bold;'>$password</code></td>
                    </tr>
                </table>
                <p style='margin-top: 25px; font-size: 12px; color: #888;'>For security reasons, please change your password after logging in for the first time.</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Welcome Email Error: " . $mail->ErrorInfo);
        return 'ERR: ' . $mail->ErrorInfo;
    }
}

// CONVERT TO EMPLOYEE
if(isset($_POST['action']) && $_POST['action'] == 'convert'){
    $applicant_id   = (int)$_POST['applicant_id'];
    $position_id    = (int)$_POST['position_id'];
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
    $portal_password = isset($_POST['portal_password']) ? trim($_POST['portal_password']) : '';

    if(!empty($portal_password) && empty($email)) {
        ob_clean();
        echo 'error: Email is required to generate a portal account.';
        exit();
    }

    // Enforce position slot capacity — count active employees already holding this position
    $slotCheck = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT slots FROM positions WHERE position_id = $position_id LIMIT 1"
    ));
    if(!$slotCheck){
        ob_clean();
        echo 'error: Selected position no longer exists.';
        exit();
    }
    $totalSlots = (int)$slotCheck['slots'];
    $filledSlots = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS cnt FROM employees WHERE position_id = $position_id AND status = 'Active'"
    ))['cnt'];
    if($filledSlots >= $totalSlots){
        ob_clean();
        echo "error: This position is already fully filled ($filledSlots/$totalSlots slots taken). Increase the slot count in Positions before hiring another applicant into this role.";
        exit();
    }

    // Generate employee number
    $last = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT employee_no FROM employees ORDER BY employee_id DESC LIMIT 1"
    ));
    $next_num = $last ? (intval(substr($last['employee_no'], 4)) + 1) : 1;
    $emp_no = 'EMP-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);

    $q = mysqli_query($conn,"
        INSERT INTO employees (
            position_id, applicant_id, employee_no,
            full_name, email, phone, address, birthdate, gender,
            civil_status, date_hired, employment_type, basic_salary,
            sss_no, philhealth_no, pagibig_no, tin_no
        ) VALUES (
            $position_id, $applicant_id, '$emp_no',
            '$full_name', '$email', '$phone', '$address', '$birthdate',
            '$gender', '$civil_status', '$date_hired', '$emp_type',
            $basic_salary, '$sss_no', '$philhealth_no', '$pagibig_no', '$tin_no'
        )
    ");

    if($q){
        $emp_id = mysqli_insert_id($conn);

        // Archive applicant as Hired then remove from active list
        $appRow = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT * FROM applicants WHERE applicant_id = $applicant_id"
        ));
        if($appRow){
            $aName    = mysqli_real_escape_string($conn, $appRow['full_name']);
            $aEmail   = mysqli_real_escape_string($conn, $appRow['email'] ?? '');
            $aPhone   = mysqli_real_escape_string($conn, $appRow['phone'] ?? '');
            $aAddr    = mysqli_real_escape_string($conn, $appRow['address'] ?? '');
            $aResume  = mysqli_real_escape_string($conn, $appRow['resume'] ?? '');
            $aNotes   = mysqli_real_escape_string($conn, $appRow['notes'] ?? '');
            $aApplied = $appRow['applied_at'] ? "'{$appRow['applied_at']}'" : 'NULL';

            mysqli_query($conn,"
                INSERT INTO applicants_archive
                    (applicant_id, position_id, full_name, email, phone, address,
                     resume, stage, notes, applied_at, archive_reason, archived_by)
                VALUES
                    ($applicant_id, {$appRow['position_id']}, '$aName', '$aEmail',
                     '$aPhone', '$aAddr', '$aResume', 'Approved', '$aNotes',
                     $aApplied, 'Hired', $admin_id)
            ");
            mysqli_query($conn, "DELETE FROM applicants WHERE applicant_id = $applicant_id");
        }

// Auto-close position if all slots are now filled
        $slotRow = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT slots FROM positions WHERE position_id = $position_id"
        ));
        $nowFilled = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) AS cnt FROM employees 
             WHERE position_id = $position_id AND status = 'Active'"
        ))['cnt'];
        if($slotRow && (int)$nowFilled >= (int)$slotRow['slots']){
            mysqli_query($conn,
                "UPDATE positions SET status = 'Closed' WHERE position_id = $position_id"
            );
        }

        // Sync with users table
        $mail_status = '';
        if(!empty($email) && !empty($portal_password)){
            $hashed_password = password_hash($portal_password, PASSWORD_BCRYPT);
            $user_exists = mysqli_query($conn, "SELECT user_id FROM users WHERE gmail = '$email'");
            if(mysqli_num_rows($user_exists) == 0){
                mysqli_query($conn, "
                    INSERT INTO users (gmail, password, full_name, role, status)
                    VALUES ('$email', '$hashed_password', '$full_name', 'Cashier', 'Active')
                ");
                $sent = sendEmployeeWelcomeEmail($email, $full_name, $portal_password);
                $mail_status = ($sent === true) ? '' : '|Email failed - ' . $sent;
            } else {
                mysqli_query($conn, "UPDATE users SET password = '$hashed_password' WHERE gmail = '$email'");
                $sent = sendEmployeePasswordResetEmail($email, $full_name, $portal_password);
                $mail_status = ($sent === true)
                    ? '|This Gmail already had a portal account, so its password was reset and emailed.'
                    : '|Gmail already had an account. Password was reset but email failed - ' . $sent;
            }
        }

        echo 'success:' . $emp_no . $mail_status;
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

/*=========================================================
    FETCH DATA
==========================================================*/

$applicants = mysqli_query($conn,"
    SELECT a.*, p.position_name, p.employment_type
    FROM applicants a
    LEFT JOIN positions p ON a.position_id = p.position_id
    ORDER BY a.applied_at DESC
");

$positions = mysqli_query($conn,
    "SELECT * FROM positions ORDER BY position_name ASC"
);
$positionList = [];
while($p = mysqli_fetch_assoc($positions)){
    $positionList[] = $p;
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
/* Ensure modals render above AJAX content wrapper */
.modal { z-index: 1055 !important; }
.modal-backdrop { z-index: 1054 !important; }
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
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" onclick="openApplicantArchive()">
            <i class="bi bi-archive-fill me-1"></i> Archive
            <?php
            $archResult = mysqli_query($conn, "SELECT COUNT(*) AS c FROM applicants_archive");
            $archCount  = $archResult ? (mysqli_fetch_assoc($archResult)['c'] ?? 0) : 0;
            if($archCount > 0) echo '<span class="badge bg-danger ms-1">'.$archCount.'</span>';
            ?>
        </button>
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="bi bi-person-plus-fill me-1"></i> Add Applicant
        </button>
    </div>
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

                    <div class="col-md-6">
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
                    <div class="col-md-6">
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
                    <!-- PORTAL CREDENTIALS -->
                    <div class="col-12 mt-3">
                        <div class="fw-bold text-muted mb-2" style="font-size:12px;
                             letter-spacing:1px;text-transform:uppercase;">
                            Portal Account Credentials
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Auto-Generated Portal Password</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="conv_portal_password" placeholder="Will be emailed to employee">
                            <button class="btn btn-outline-secondary" type="button" onclick="regenerateConvPassword()">
                                <i class="bi bi-arrow-clockwise"></i> Generate
                            </button>
                        </div>
                        <small class="text-muted">You can edit this password. Portal credentials will be sent to the employee's Gmail.</small>
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


<!-- APPLICANTS ARCHIVE MODAL -->
<div class="modal fade" id="applicantArchiveModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-archive-fill me-2"></i>Applicants Archive
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php
                $archivedApps = mysqli_query($conn,"
                    SELECT aa.*, p.position_name
                    FROM applicants_archive aa
                    LEFT JOIN positions p ON aa.position_id = p.position_id
                    ORDER BY aa.archived_at DESC
                ");
                if(!$archivedApps || mysqli_num_rows($archivedApps) == 0){ ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-archive" style="font-size:40px;"></i>
                        <p class="mt-3">No archived applicants yet.</p>
                    </div>
                <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm">
                        <thead class="table-secondary">
                            <tr>
                                <th>#</th>
                                <th>Full Name</th>
                                <th>Position Applied</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Last Stage</th>
                                <th>Reason</th>
                                <th>Archived On</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $ai = 1; while($ar = mysqli_fetch_assoc($archivedApps)){ ?>
                            <tr>
                                <td><?= $ai++; ?></td>
                                <td><?= htmlspecialchars($ar['full_name']); ?></td>
                                <td><?= htmlspecialchars($ar['position_name'] ?? '—'); ?></td>
                                <td><?= htmlspecialchars($ar['email'] ?? '—'); ?></td>
                                <td><?= htmlspecialchars($ar['phone'] ?? '—'); ?></td>
                                <td>
                                    <?php
                                    $sc = [
                                        'Approved'          => 'success',
                                        'Rejected'          => 'danger',
                                        'Final Interview'   => 'primary',
                                        'First Interview'   => 'info',
                                        'Initial Screening' => 'secondary',
                                    ][$ar['stage']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $sc ?>"><?= htmlspecialchars($ar['stage']); ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $ar['archive_reason'] === 'Hired' ? 'bg-success' : 'bg-danger' ?>">
                                        <?= htmlspecialchars($ar['archive_reason']); ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y h:i A', strtotime($ar['archived_at'])); ?></td>
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
    // Step 1 — Reason
    Swal.fire({
        title: 'Remove ' + name + '?',
        html: `<p class="text-muted mb-2" style="font-size:13px;">This will archive the applicant record.</p>
               <input id="appDelReason" class="swal2-input" placeholder="Reason e.g. No show, Withdrew application...">`,
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Next',
        preConfirm: () => {
            const r = document.getElementById('appDelReason').value.trim();
            if(!r){ Swal.showValidationMessage('Please provide a reason.'); return false; }
            return r;
        }
    }).then(reasonResult => {
        if(!reasonResult.isConfirmed) return;
        const reason = reasonResult.value;

        // Step 2 — Confirm
        Swal.fire({
            title: 'Are you sure?',
            html: `<strong>${name}</strong> will be moved to the archive.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, Archive & Remove'
        }).then(result => {
            if(!result.isConfirmed) return;
            $.post('hrms_applicants.php', {
                action:       'delete',
                applicant_id: id,
                reason:       reason
            }, function(response){
                if(response.trim() == 'success'){
                    Swal.fire({ icon:'success', title:'Archived & Removed!',
                        text: name + ' has been moved to the archive.',
                        showConfirmButton:false, timer:1800 })
                    .then(() => loadPage('hrms_applicants.php'));
                } else {
                    Swal.fire('Error', response, 'error');
                }
            });
        });
    });
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

function regenerateConvPassword() {
    $('#conv_portal_password').val(generateRandomPassword());
}
function openApplicantArchive(){
    const el = document.getElementById('applicantArchiveModal');
    // Move modal to body so it renders above the AJAX content wrapper
    if(el.parentNode !== document.body){
        document.body.appendChild(el);
    }
    new bootstrap.Modal(el).show();
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
    regenerateConvPassword(); // generate initial password
    new bootstrap.Modal(document.getElementById('convertModal')).show();
}

function submitConvert(){
    const name     = $("#conv_name").val().trim();
    const position = $("#conv_position").val();
    const hired    = $("#conv_datehired").val();
    const salary   = $("#conv_salary").val();

    if(!name){
        Swal.fire('Missing Field', 'Employee name is required.', 'warning'); return;
    }
    if(!position){
        Swal.fire('Missing Field', 'Please select a position. If none appear, check that positions exist in the system.', 'warning'); return;
    }
    if(!hired){
        Swal.fire('Missing Field', 'Date hired is required.', 'warning'); return;
    }
    if(!salary){
        Swal.fire('Missing Field', 'Basic salary is required.', 'warning'); return;
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
            tin_no:          $("#conv_tin").val(),
            portal_password: $("#conv_portal_password").val()
        }, function(response){
            if(response.startsWith('success:')){
                const rest = response.split(':')[1];
                const [empNo, mailNote] = rest.split('|');
                let html = `<strong>${name}</strong> is now Employee <strong>#${empNo}</strong>`;
                if(mailNote) html += `<br><small style="color:#92400e">${mailNote}</small>`;
                Swal.fire({
                    icon: 'success',
                    title: 'Employee Hired! 🎉',
                    html: html,
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