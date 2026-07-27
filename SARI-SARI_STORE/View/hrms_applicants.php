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

    // Block applying to a position that is closed or already fully filled
    $posCheck = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT slots, status FROM positions WHERE position_id = $position_id LIMIT 1"
    ));
    if(!$posCheck || $posCheck['status'] != 'Open'){
        ob_clean(); echo 'error: This position is not open for applications.'; exit();
    }
    $filledCheck = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS cnt FROM employees WHERE position_id = $position_id AND status = 'Active'"
    ))['cnt'];
    if((int)$filledCheck >= (int)$posCheck['slots']){
        ob_clean(); echo 'error: This position is already fully filled.'; exit();
    }

    // Phone: must start with 09, exactly 11 digits
    if(!preg_match('/^09\d{9}$/', $phone)){
        ob_clean(); echo 'error: Phone number must start with 09 and be exactly 11 digits.'; exit();
    }

    // Email: basic format + only one @
    if(!filter_var($email, FILTER_VALIDATE_EMAIL) || substr_count($email, '@') !== 1){
        ob_clean(); echo 'error: Please enter a valid email address with exactly one @.'; exit();
    }

    // Resume upload
    $resume = '';
    if(isset($_FILES['resume']) && $_FILES['resume']['error'] === 0){
        $allowed = ['application/pdf'];
        $fileType = mime_content_type($_FILES['resume']['tmp_name']);
        if(!in_array($fileType, $allowed)){
            ob_clean(); echo 'error: Resume must be a PDF file.'; exit();
        }
        if($_FILES['resume']['size'] > 5 * 1024 * 1024){
            ob_clean(); echo 'error: Resume file must not exceed 5MB.'; exit();
        }
        $uploadDir = __DIR__ . '/uploads/resumes/';
        if(!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['resume']['name']);
        move_uploaded_file($_FILES['resume']['tmp_name'], $uploadDir . $fileName);
        $resume = mysqli_real_escape_string($conn, $fileName);
    }

    $q = mysqli_query($conn,"
        INSERT INTO applicants (position_id, full_name, email, phone, address, notes, resume)
        VALUES ($position_id, '$full_name', '$email', '$phone', '$address', '$notes', '$resume')
    ");

    if($q){
        $new_id = mysqli_insert_id($conn);
        logAction($conn, $admin_id, 'Create', 'applicants', $new_id,
            "Added applicant: $full_name");

        if(!empty($email)){
            sendApplicantStageEmail($email, $full_name, 'Application Received');
        }

        ob_clean(); echo 'success';
    } else {
        ob_clean(); echo 'error: ' . mysqli_error($conn);
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
    $id                  = (int)$_POST['applicant_id'];
    $stage               = mysqli_real_escape_string($conn, $_POST['stage']);
    $interview_date_raw  = trim($_POST['interview_date'] ?? '');
    $interview_date_sql  = $interview_date_raw !== '' ? mysqli_real_escape_string($conn, $interview_date_raw) : '';
    $rejection_reason    = trim($_POST['rejection_reason'] ?? '');
    $rejection_reason_sql= mysqli_real_escape_string($conn, $rejection_reason);

    $allowed = ['Initial Screening','First Interview','Final Interview','Approved','Rejected'];
    if(!in_array($stage, $allowed)){
        echo 'error: Invalid stage';
        exit();
    }

    $setSql = "stage='$stage'";
    $setSql .= $interview_date_sql !== '' ? ", next_interview='$interview_date_sql'" : ", next_interview=NULL";

    if($stage === 'Rejected'){
        $currStgRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT stage FROM applicants WHERE applicant_id = $id"));
        $failedStage = $currStgRow['stage'] ?? 'Initial Screening';
        $failedStage_sql = mysqli_real_escape_string($conn, $failedStage);
        
        $setSql .= ", notes = CONCAT(IFNULL(notes,''), IF(LENGTH(IFNULL(notes,''))>0, '\n', ''), '[Failed Stage]: ', '$failedStage_sql')";
        if(!empty($rejection_reason_sql)){
            $setSql .= ", notes = CONCAT(IFNULL(notes,''), '\n[Rejection Reason]: ', '$rejection_reason_sql')";
        }
    }

    $q = mysqli_query($conn,
        "UPDATE applicants SET $setSql WHERE applicant_id=$id"
    );

    if($q){
        $app  = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT full_name, email FROM applicants WHERE applicant_id=$id"
        ));
        $name = $app['full_name'];

        if(!empty($app['email'])){
            sendApplicantStageEmail($app['email'], $name, $stage, $interview_date_raw, $rejection_reason);
        }

        $logMsg = "Advanced $name to stage: $stage";
        if($stage === 'Rejected' && !empty($rejection_reason)){
            $logMsg .= " — Reason: $rejection_reason";
        }
        logAction($conn, $admin_id, 'Update', 'applicants', $id, $logMsg);
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

// RESTORE — restore applicant from archive back to active pool
if(isset($_POST['action']) && $_POST['action'] == 'restore'){
    $archive_id = (int)$_POST['archive_id'];

    $arch = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM applicants_archive WHERE archive_id = $archive_id"
    ));

    if(!$arch){
        echo 'error: Archived applicant record not found.';
        exit();
    }

    $pos_id     = (int)$arch['position_id'];
    $full_name  = mysqli_real_escape_string($conn, $arch['full_name']);
    $email      = mysqli_real_escape_string($conn, $arch['email'] ?? '');
    $phone      = mysqli_real_escape_string($conn, $arch['phone'] ?? '');
    $address    = mysqli_real_escape_string($conn, $arch['address'] ?? '');
    $resume     = mysqli_real_escape_string($conn, $arch['resume'] ?? '');
    $stage      = mysqli_real_escape_string($conn, $arch['stage'] ?? 'Initial Screening');
    $notes      = mysqli_real_escape_string($conn, $arch['notes'] ?? '');
    $applied_at = $arch['applied_at'] ? "'{$arch['applied_at']}'" : 'NOW()';

    // If stage was Approved or archive reason was Hired, reset to Initial Screening upon restore
    if($stage === 'Approved' || $arch['archive_reason'] === 'Hired'){
        $stage = 'Initial Screening';
    }

    $q = mysqli_query($conn, "
        INSERT INTO applicants (position_id, full_name, email, phone, address, resume, stage, notes, applied_at)
        VALUES ($pos_id, '$full_name', '$email', '$phone', '$address', '$resume', '$stage', '$notes', $applied_at)
    ");

    if($q){
        $new_id = mysqli_insert_id($conn);
        mysqli_query($conn, "DELETE FROM applicants_archive WHERE archive_id = $archive_id");
        logAction($conn, $admin_id, 'Restore', 'applicants', $new_id,
            "Restored applicant: $full_name from archive");
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

function sendEmployeeWelcomeEmail($gmail, $name, $password, $contractStart = '', $contractEnd = '') {
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

        $mail->setFrom('edonnarao06@gmail.com', 'O-Cart! HRMS');
        $mail->addAddress($gmail);

        $mail->isHTML(true);
        $mail->Subject = 'Your Employee Portal Credentials & Employment Contract - O-Cart!';
        $contractStartFmt = !empty($contractStart) ? date('F j, Y', strtotime($contractStart)) : 'Date of Hire';
        $contractEndFmt = !empty($contractEnd) ? date('F j, Y', strtotime($contractEnd)) : '6 Months from Hire Date';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px; max-width: 550px;'>
                <h2 style='color: #1a3c5e; margin-top:0;'>O-Cart! Employee Portal</h2>
                <p>Hello <strong>$name</strong>,</p>
                <p>Welcome to our team! Your employee account has been activated and your <strong>6-Month Employment Contract</strong> is officially on record.</p>
                
                <div style='background:#f0f7ff; border-left:4px solid #2563eb; padding:12px 15px; margin:15px 0; border-radius:4px;'>
                    <strong style='color:#1e40af;'>📄 Employment Contract Summary (6 Months):</strong><br>
                    <span style='font-size:13px; color:#334155;'>
                    • Contract Term: <strong>6 Months</strong><br>
                    • Start Date: <strong>$contractStartFmt</strong><br>
                    • Expiry / End Date: <strong>$contractEndFmt</strong><br>
                    • Status: <strong>Signed &amp; Active</strong>
                    </span>
                </div>

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

// Notifies an applicant by Gmail when their application status/stage changes
function sendApplicantStageEmail($gmail, $name, $stage, $interviewDate = '', $rejectionReason = '') {
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

        $mail->setFrom('edonnarao06@gmail.com', 'O-Cart! HRMS');
        $mail->addAddress($gmail);
        $mail->isHTML(true);

        $formattedDate = '';
        if(!empty($interviewDate)){
            $ts = strtotime($interviewDate);
            if($ts !== false){
                $formattedDate = date('F j, Y \a\t g:i A', $ts);
            }
        }

        switch($stage){
            case 'Application Received':
                $mail->Subject = 'Application Received - O-Cart!';
                $intro = "Thank you for applying with us! We've received your application and it is now under review. We'll notify you by email as your application progresses.";
                break;
            case 'First Interview':
                $mail->Subject = 'You Passed Initial Screening - O-Cart!';
                $intro = "Congratulations! You've passed the initial screening and are being invited to the <strong>First Interview</strong>.";
                break;
            case 'Final Interview':
                $mail->Subject = 'You Passed the First Interview - O-Cart!';
                $intro = "Great news! You've passed the first interview and are being invited to the <strong>Final Interview</strong>.";
                break;
            case 'Approved':
                $mail->Subject = 'Congratulations - You Are Hired! - O-Cart!';
                $intro = "We're pleased to inform you that you've <strong>passed the final interview</strong> and are being offered the position. Welcome to the team!";
                break;
            case 'Rejected':
                $mail->Subject = 'Application Update - O-Cart!';
                $reasonDetails = !empty($rejectionReason) ? "<br><br><em>Feedback / Remarks: " . htmlspecialchars($rejectionReason) . "</em>" : "";
                $intro = "Thank you for taking the time to apply and interview with us. After careful consideration, we've decided to move forward with other candidates at this time." . $reasonDetails;
                break;
            default:
                $mail->Subject = 'Application Update - O-Cart!';
                $intro = "There's an update to your application status: <strong>$stage</strong>.";
        }

        $dateBlock = '';
        if($formattedDate !== '' && ($stage === 'First Interview' || $stage === 'Final Interview')){
            $dateBlock = "
                <table style='width: 100%; border-collapse: collapse; margin-top:15px;'>
                    <tr>
                        <td style='padding: 5px 0; color: #666;'>Scheduled Date &amp; Time:</td>
                        <td><strong>$formattedDate</strong></td>
                    </tr>
                </table>
            ";
        }

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px; max-width: 500px;'>
                <h2 style='color: #1a3c5e;'>O-Cart! Recruitment</h2>
                <p>Hello <strong>$name</strong>,</p>
                <p>$intro</p>
                $dateBlock
                <p style='margin-top: 25px; font-size: 12px; color: #888;'>If you have any questions, feel free to reply to this email.</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Applicant Stage Email Error: " . $mail->ErrorInfo);
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
    $schedule       = mysqli_real_escape_string($conn, trim($_POST['schedule'] ?? ''));
    $rest_day       = mysqli_real_escape_string($conn, trim($_POST['rest_day'] ?? ''));
    $emp_type       = mysqli_real_escape_string($conn, trim($_POST['employment_type'] ?? 'Full-time'));
   $basic_salary   = (float)$_POST['basic_salary'];
    $sal_min        = (float)($_POST['sal_min'] ?? 0);
    $sal_max        = (float)($_POST['sal_max'] ?? 0);
    if($sal_min > 0 && $sal_max > 0){
        if($basic_salary < $sal_min || $basic_salary > $sal_max){
            ob_clean();
            echo "error: Salary must be between ₱" . number_format($sal_min,2) . " and ₱" . number_format($sal_max,2) . " based on the job posting range.";
            exit();
        }
    }
    $sss_no         = mysqli_real_escape_string($conn, trim($_POST['sss_no']));
    $philhealth_no  = mysqli_real_escape_string($conn, trim($_POST['philhealth_no']));
    $pagibig_no     = mysqli_real_escape_string($conn, trim($_POST['pagibig_no']));
    $tin_no         = mysqli_real_escape_string($conn, trim($_POST['tin_no']));

    // Validate government number formats (optional fields — only checked if filled in)
    $govtPatterns = [
        'SSS No.'        => [$sss_no,        '/^\d{2}-\d{7}-\d{1}$/'],
        'PhilHealth No.' => [$philhealth_no, '/^\d{4}-\d{4}-\d{4}$/'],
        'Pag-IBIG No.'   => [$pagibig_no,    '/^\d{4}-\d{4}-\d{4}$/'],
        'TIN No.'        => [$tin_no,        '/^\d{3}-\d{3}-\d{3}-\d{3}$/'],
    ];
    foreach($govtPatterns as $label => $check){
        [$value, $regex] = $check;
        if($value !== '' && !preg_match($regex, $value)){
            ob_clean();
            echo "error: $label is incomplete or invalid.";
            exit();
        }
    }
    // Password is NOT shown to HR — system sends it to employee's Gmail only
    $portal_password = bin2hex(random_bytes(5)); // 10-char random, never shown in UI

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

    $hashed_portal_password_val = "NULL";
    if(!empty($portal_password)){
        $hashed_portal_password_val = "'" . mysqli_real_escape_string($conn, password_hash($portal_password, PASSWORD_BCRYPT)) . "'";
    }

    // 6-Month Contract Start & End Date
    $contract_start = !empty($_POST['contract_start']) ? $_POST['contract_start'] : $date_hired;
    $contract_end   = !empty($_POST['contract_end']) ? $_POST['contract_end'] : date('Y-m-d', strtotime('+6 months', strtotime($contract_start)));
    $contract_signed = isset($_POST['contract_signed']) && $_POST['contract_signed'] == 1 ? 1 : 1;

    $q = mysqli_query($conn,"
        INSERT INTO employees (
            position_id, applicant_id, employee_no,
            full_name, email, phone, address, birthdate, gender,
            civil_status, date_hired, employment_type, basic_salary,
            sss_no, philhealth_no, pagibig_no, tin_no, password,
            schedule, rest_day, contract_start, contract_end, contract_signed, contract_signed_at
        ) VALUES (
            $position_id, $applicant_id, '$emp_no',
            '$full_name', '$email', '$phone', '$address', '$birthdate',
            '$gender', '$civil_status', '$date_hired', '$emp_type',
            $basic_salary, '$sss_no', '$philhealth_no', '$pagibig_no', '$tin_no', $hashed_portal_password_val,
            '$schedule', '$rest_day', '$contract_start', '$contract_end', $contract_signed, NOW()
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
                $sent = sendEmployeeWelcomeEmail($email, $full_name, $portal_password, $contract_start, $contract_end);
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
    WHERE a.stage != 'Rejected'
    ORDER BY a.applied_at DESC
");

$positions = mysqli_query($conn,
    "SELECT p.*,
            (SELECT COUNT(*) FROM employees e WHERE e.position_id = p.position_id AND e.status = 'Active') AS filled_slots
     FROM positions p ORDER BY p.position_name ASC"
);
$positionList = [];
$openPositionList = [];
while($p = mysqli_fetch_assoc($positions)){
    $positionList[] = $p;
    if($p['status'] == 'Open' && (int)$p['filled_slots'] < (int)$p['slots']){
        $openPositionList[] = $p;
    }
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
        <button class="btn btn-outline-danger" onclick="openRejectedApplicantsModal()">
            <i class="bi bi-x-circle-fill me-1"></i> Rejected
            <?php
            $rejCountResult = mysqli_query($conn, "
                SELECT 
                    (SELECT COUNT(*) FROM applicants WHERE stage = 'Rejected') + 
                    (SELECT COUNT(*) FROM applicants_archive WHERE stage = 'Rejected' OR archive_reason = 'Rejected') AS c
            ");
            $rejCount = $rejCountResult ? (mysqli_fetch_assoc($rejCountResult)['c'] ?? 0) : 0;
            if($rejCount > 0) echo '<span class="badge bg-danger ms-1">'.$rejCount.'</span>';
            ?>
        </button>
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

<?php
// Helper: Render stages that applicant has passed
function renderFinishedStages($conn, $row) {
    $currentStage = $row['stage'];
    $appId = (int)$row['applicant_id'];

    $stageLevels = [
        'Initial Screening' => 1,
        'First Interview'   => 2,
        'Final Interview'   => 3,
        'Approved'          => 4
    ];

    $passed = [];

    if ($currentStage === 'Rejected') {
        $logRes = mysqli_query($conn, "
            SELECT description FROM audit_logs 
            WHERE table_name = 'applicants' AND record_id = $appId AND action = 'Update'
            ORDER BY log_id ASC
        ");
        $reached = ['Initial Screening'];
        if ($logRes) {
            while ($l = mysqli_fetch_assoc($logRes)) {
                if (preg_match('/to stage:\s*(.+)/i', $l['description'], $m)) {
                    $stg = trim($m[1]);
                    if (in_array($stg, ['First Interview', 'Final Interview', 'Approved']) && !in_array($stg, $reached)) {
                        $reached[] = $stg;
                    }
                }
            }
        }
        array_pop($reached);
        $passed = $reached;
    } else {
        $level = $stageLevels[$currentStage] ?? 1;
        if ($level >= 2) $passed[] = 'Initial Screening';
        if ($level >= 3) $passed[] = 'First Interview';
        if ($level >= 4) $passed[] = 'Final Interview';
    }

    if (empty($passed)) {
        return '<span class="badge bg-light text-muted border fw-normal" style="font-size:11px;">None</span>';
    }

    $html = '';
    foreach ($passed as $p) {
        $bBg = '#e2e8f0';
        $bColor = '#334155';
        if ($p === 'Initial Screening') { $bBg = '#e2e8f0'; $bColor = '#334155'; }
        if ($p === 'First Interview')   { $bBg = '#dbeafe'; $bColor = '#1e40af'; }
        if ($p === 'Final Interview')   { $bBg = '#f3e8ff'; $bColor = '#6b21a8'; }

        $html .= '<span class="badge me-1 mb-1" style="background:' . $bBg . '; color:' . $bColor . '; font-size:10px; font-weight:600;"><i class="bi bi-check2 me-1"></i>' . htmlspecialchars($p) . '</span> ';
    }
    return $html;
}

// Helper: Extract clean rejection or archive reason
function getRejectionReasonText($notes, $archiveReason = '') {
    if(!empty($notes) && preg_match('/\[Rejection Reason\]:\s*(.+)/i', $notes, $m)) {
        return trim($m[1]);
    }
    if(!empty($archiveReason) && !in_array($archiveReason, ['Hired', 'Removed'])) {
        return $archiveReason;
    }
    if(!empty($notes) && trim($notes) !== '') {
        return trim($notes);
    }
    return 'No reason specified';
}

// Helper: Extract stage where applicant failed
function getFailedStageText($conn, $appId, $notes) {
    if(!empty($notes) && preg_match('/\[Failed Stage\]:\s*(.+)/i', $notes, $m)) {
        return trim($m[1]);
    }
    if($appId > 0 && $conn) {
        $logRes = mysqli_query($conn, "
            SELECT description FROM audit_logs 
            WHERE table_name = 'applicants' AND record_id = $appId AND action = 'Update'
            ORDER BY log_id DESC
        ");
        if($logRes) {
            while($l = mysqli_fetch_assoc($logRes)) {
                if(preg_match('/to stage:\s*(.+)/i', $l['description'], $m)) {
                    $stg = trim($m[1]);
                    if($stg !== 'Rejected' && in_array($stg, ['Initial Screening', 'First Interview', 'Final Interview'])) {
                        return $stg;
                    }
                }
            }
        }
    }
    return 'Initial Screening';
}
?>

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
                <th>Finished stage</th>
                <th>Stage</th>
                <th>Resume</th>
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
            $appJson = json_encode([
                'id'               => (int)$row['applicant_id'],
                'name'             => $row['full_name'],
                'positionName'     => $row['position_name'] ?? '—',
                'empType'          => $row['employment_type'] ?? '',
                'email'            => $row['email'] ?? '',
                'phone'            => $row['phone'] ?? '',
                'address'          => $row['address'] ?? '',
                'stage'            => $row['stage'],
                'notes'            => $row['notes'] ?? '',
                'appliedAt'        => date("M d, Y", strtotime($row['applied_at'])),
                'resume'           => $row['resume'] ?? '',
                'positionId'       => (int)($row['position_id'] ?? 0),
                'passedStagesHtml' => renderFinishedStages($conn, $row)
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        ?>
        <tr>
            <td><?= $i++; ?></td>
            <td>
                <a href="#" class="fw-semibold text-decoration-none text-primary"
                   onclick='openApplicantDetailsJson(<?= $appJson; ?>); return false;'>
                    <?= htmlspecialchars($row['full_name']); ?>
                </a>
                <br><small class="text-muted"><?= htmlspecialchars($row['email'] ?? '—'); ?></small>
            </td>
            <td>
                <?= htmlspecialchars($row['position_name'] ?? '—'); ?>
                <br><small class="text-muted"><?= $row['employment_type'] ?? ''; ?></small>
            </td>
            <td><?= htmlspecialchars($row['phone'] ?? '—'); ?></td>
            <td>
                <?= renderFinishedStages($conn, $row); ?>
            </td>
            <td>
                <span class="stage-badge"
                      style="background:<?= $sBg; ?>;color:<?= $sColor; ?>;">
                    <?= $row['stage']; ?>
                </span>
            </td>
            <td>
                <?php if(!empty($row['resume'])){ ?>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="previewResume('<?= htmlspecialchars(addslashes($row['resume']), ENT_QUOTES); ?>', '<?= htmlspecialchars(addslashes($row['full_name']), ENT_QUOTES); ?>')">
                    <i class="bi bi-file-earmark-pdf-fill"></i> View
                </button>
                <?php } else { ?>
                <span class="text-muted" style="font-size:12px;">No file</span>
                <?php } ?>
            </td>
            <td><?= date("M d, Y", strtotime($row['applied_at'])); ?></td>
            <td>
                <!-- VIEW APPLICANT INFO, RESUME & ACTIONS -->
                <button type="button" class="btn btn-sm btn-outline-info"
                        onclick='openApplicantDetailsJson(<?= $appJson; ?>)'
                        title="View Applicant Information, Resume & Actions">
                    <i class="bi bi-eye-fill me-1"></i> View
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
                <form id="addApplicantForm" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_name"
                               placeholder="e.g. Juan Dela Cruz">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Position Applied <span class="text-danger">*</span></label>
                        <select class="form-select" id="add_position" onchange="onAddPositionChange(this)">
                            <option value="">-- Select Position --</option>
                            <?php foreach($openPositionList as $p){ ?>
                            <option value="<?= $p['position_id']; ?>"
                                    data-salmin="<?= $p['salary_min']; ?>"
                                    data-salmax="<?= $p['salary_max']; ?>">
                                <?= htmlspecialchars($p['position_name']); ?>
                                (<?= $p['employment_type']; ?>)
                            </option>
                            <?php } ?>
                        </select>
                        <small class="text-muted" id="add_salary_range" style="font-size:11px;"></small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="add_email"
                               placeholder="e.g. juan@gmail.com">
                        <div class="invalid-feedback">Enter a valid email with exactly one @.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_phone"
                               placeholder="09XXXXXXXXX" maxlength="11"
                               oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,11)">
                        <small class="text-muted">Must start with 09, exactly 11 digits</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Address</label>
                        <input type="text" class="form-control" id="add_address"
                               placeholder="Complete address">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Resume (PDF only, max 5MB)</label>
                        <input type="file" class="form-control" id="add_resume"
                               accept=".pdf" name="resume">
                        <small class="text-muted">Upload applicant's resume in PDF format</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Notes / Remarks</label>
                        <textarea class="form-control" id="add_notes" rows="3"
                                  placeholder="Initial observations, referral source, etc."></textarea>
                    </div>
                </div>
                </form>
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
                        <label class="form-label fw-semibold">Birthdate <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="conv_birthdate"
                               id="conv_birthdate">
                        <small class="text-muted">Minimum age: 18 years old</small>
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
                            <option value="<?= $p['position_id']; ?>"
                                    data-salmin="<?= $p['salary_min']; ?>"
                                    data-salmax="<?= $p['salary_max']; ?>">
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
                        <label class="form-label fw-semibold">Work Schedule <span class="text-danger">*</span></label>
                        <select class="form-select" id="conv_schedule" onchange="onScheduleChange(this)">
                            <option value="">-- Select Schedule --</option>
                            <optgroup label="Standard Office">
                                <option value="Monday–Friday 8:00 AM–5:00 PM">Mon–Fri  8:00 AM – 5:00 PM  (Regular Day Shift)</option>
                                <option value="Monday–Friday 9:00 AM–6:00 PM">Mon–Fri  9:00 AM – 6:00 PM  (Flexi Day Shift)</option>
                                <option value="Monday–Saturday 8:00 AM–5:00 PM">Mon–Sat  8:00 AM – 5:00 PM  (6-Day Work Week)</option>
                            </optgroup>
                            <optgroup label="Retail / Store Hours">
                                <option value="Monday–Sunday 8:00 AM–5:00 PM (1 Rest Day)">Mon–Sun  8:00 AM – 5:00 PM  (Retail – 1 Rest Day)</option>
                                <option value="Monday–Sunday 10:00 AM–7:00 PM (1 Rest Day)">Mon–Sun  10:00 AM – 7:00 PM  (Retail – 1 Rest Day)</option>
                                <option value="Monday–Sunday 12:00 PM–9:00 PM (1 Rest Day)">Mon–Sun  12:00 PM – 9:00 PM  (Retail – 1 Rest Day)</option>
                            </optgroup>
                            <optgroup label="Shifting / Rotating Schedules">
                                <option value="Shifting – Morning 6:00 AM–2:00 PM">Shifting – Morning  6:00 AM – 2:00 PM</option>
                                <option value="Shifting – Afternoon 2:00 PM–10:00 PM">Shifting – Afternoon  2:00 PM – 10:00 PM</option>
                                <option value="Shifting – Night 10:00 PM–6:00 AM">Shifting – Night  10:00 PM – 6:00 AM</option>
                                <option value="Rotating Shift (4-Day On / 2-Day Off)">Rotating Shift  (4-Day On / 2-Day Off)</option>
                            </optgroup>
                            <optgroup label="Part-Time / Compressed">
                                <option value="Monday–Friday 8:00 AM–12:00 PM (Half Day)">Mon–Fri  8:00 AM – 12:00 PM  (Half Day)</option>
                                <option value="Compressed Week – Monday–Thursday 7:00 AM–6:00 PM">Compressed Week  Mon–Thu  7:00 AM – 6:00 PM</option>
                                <option value="Weekends Only 8:00 AM–5:00 PM">Weekends Only  8:00 AM – 5:00 PM</option>
                            </optgroup>
                            <optgroup label="Other">
                                <option value="custom">📝 Custom Schedule (type below)</option>
                            </optgroup>
                        </select>
                        <!-- Custom schedule text input, hidden by default -->
                        <input type="text" class="form-control mt-2" id="conv_schedule_custom"
                               placeholder="e.g. Tue–Sat 7:00 AM–4:00 PM"
                               style="display:none;" oninput="onCustomScheduleInput(this)">
                        <small class="text-muted" id="conv_schedule_hint" style="font-size:11px;"></small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Rest Day <span class="text-danger">*</span>
                            <span class="badge bg-info text-white ms-1" id="conv_restday_auto_badge"
                                  style="font-size:10px; display:none;">Auto-suggested</span>
                        </label>
                        <select class="form-select" id="conv_restday">
                            <option value="">-- Select Rest Day --</option>
                            <optgroup label="Weekend Rest (Most Common)">
                                <option value="Sunday &amp; Saturday">Sunday &amp; Saturday  (Full Weekend Off)</option>
                                <option value="Sunday">Sunday only</option>
                                <option value="Saturday">Saturday only</option>
                            </optgroup>
                            <optgroup label="Weekday Rest">
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                            </optgroup>
                            <optgroup label="Rotating / Flexible">
                                <option value="Rotating (as scheduled)">Rotating (as scheduled)</option>
                                <option value="To be determined">To be determined</option>
                            </optgroup>
                        </select>
                        <small class="text-muted" id="conv_restday_hint" style="font-size:11px;"></small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Date Hired <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="conv_datehired" onchange="updateContractEndDate()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Basic Monthly Salary <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0"
                                   class="form-control" id="conv_salary" oninput="clampSalaryInput(this)">
                        </div>
                        <input type="range" class="form-range mt-2" id="conv_salary_slider" step="500"
                               style="display:none;" oninput="syncSalaryFromSlider(this)">
                        <small class="text-muted" id="conv_salary_range" style="font-size:11px;color:#059669;"></small>
                        <input type="hidden" id="conv_sal_min">
                        <input type="hidden" id="conv_sal_max">
                    </div>

                    <!-- EMPLOYMENT CONTRACT (6 MONTHS) -->
                    <div class="col-12 mt-3">
                        <div class="card border-primary shadow-sm" style="border-radius:12px;">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-2">
                                <span class="fw-bold" style="font-size:13px;">
                                    <i class="bi bi-file-earmark-check-fill me-2"></i>Employment Contract Signing (6 Months Term)
                                </span>
                                <span class="badge bg-light text-primary fw-bold" style="font-size:11px;">Fixed 6-Month Contract</span>
                            </div>
                            <div class="card-body p-3 bg-light-subtle">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Contract Start Date</label>
                                        <input type="date" class="form-control bg-light" id="conv_contract_start" readonly>
                                        <small class="text-muted" style="font-size:11px;">Auto-synced with Date Hired</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Contract Expiry / End Date (6 Months)</label>
                                        <input type="date" class="form-control bg-light" id="conv_contract_end" readonly>
                                        <small class="text-primary fw-semibold" style="font-size:11px;" id="conv_contract_duration_text"><i class="bi bi-clock-history me-1"></i>Good for 6 months</small>
                                    </div>
                                    <div class="col-12">
                                        <div class="p-3 bg-white border rounded" style="font-size:12px; line-height:1.6;">
                                            <div class="fw-bold text-dark mb-1"><i class="bi bi-shield-check text-success me-1"></i> Standard 6-Month Contract Terms &amp; Conditions:</div>
                                            <ul class="mb-0 ps-3 text-muted">
                                                <li><strong>6-Month Duration:</strong> Contract is valid for exactly six (6) calendar months from start date.</li>
                                                <li><strong>Probationary Review:</strong> Performance evaluation is conducted before the 6-month term ends for regular status or renewal.</li>
                                                <li><strong>Store Governance:</strong> Subject to O-Cart! HR policies and Philippine Labor Code standards.</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check p-3 bg-primary-subtle border border-primary rounded d-flex align-items-center gap-2">
                                            <input class="form-check-input ms-0 me-2" type="checkbox" id="conv_contract_signed" style="transform:scale(1.2); cursor:pointer;">
                                            <label class="form-check-label fw-bold text-primary mb-0" for="conv_contract_signed" style="cursor:pointer; font-size:13px;">
                                                I confirm that the candidate and HR Representative have agreed to and signed the 6-Month Employment Contract.
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
                        <input type="text" class="form-control" id="conv_sss" inputmode="numeric"
                               maxlength="12" placeholder="XX-XXXXXXX-X" oninput="maskNumericGroups(this, [2,7,1])">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">PhilHealth No.</label>
                        <input type="text" class="form-control" id="conv_philhealth" inputmode="numeric"
                               maxlength="14" placeholder="XXXX-XXXX-XXXX" oninput="maskNumericGroups(this, [4,4,4])">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Pag-IBIG No.</label>
                        <input type="text" class="form-control" id="conv_pagibig" inputmode="numeric"
                               maxlength="14" placeholder="XXXX-XXXX-XXXX" oninput="maskNumericGroups(this, [4,4,4])">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">TIN No.</label>
                        <input type="text" class="form-control" id="conv_tin" inputmode="numeric"
                               maxlength="15" placeholder="XXX-XXX-XXX-XXX" oninput="maskNumericGroups(this, [3,3,3,3])">
                    <!-- PORTAL CREDENTIALS NOTE -->
                    <div class="col-12 mt-3">
                        <div class="alert alert-success" style="font-size:12px;">
                            <i class="bi bi-envelope-fill me-2"></i>
                            A secure portal password will be <strong>auto-generated and emailed</strong>
                            directly to the employee's Gmail. HR will not see the password.
                        </div>
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


<!-- REJECTED APPLICANTS MODAL -->
<div class="modal fade" id="rejectedApplicantsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:12px; overflow:hidden;">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-x-circle-fill me-2"></i>Rejected Applicants
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <?php
                $rejectedApps = mysqli_query($conn,"
                    SELECT a.applicant_id, 0 AS archive_id, a.full_name, a.email, a.phone, a.address, a.resume, a.notes, a.stage, a.applied_at, '' AS archive_reason, 'Active Pool' AS source, p.position_name
                    FROM applicants a
                    LEFT JOIN positions p ON a.position_id = p.position_id
                    WHERE a.stage = 'Rejected'
                    UNION ALL
                    SELECT aa.applicant_id, aa.archive_id, aa.full_name, aa.email, aa.phone, aa.address, aa.resume, aa.notes, aa.stage, aa.applied_at, aa.archive_reason, 'Archived' AS source, p.position_name
                    FROM applicants_archive aa
                    LEFT JOIN positions p ON aa.position_id = p.position_id
                    WHERE aa.stage = 'Rejected' OR aa.archive_reason = 'Rejected'
                    ORDER BY applied_at DESC
                ");
                if(!$rejectedApps || mysqli_num_rows($rejectedApps) == 0){ ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-person-x" style="font-size:40px;"></i>
                        <p class="mt-3">No rejected applicants found.</p>
                    </div>
                <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-danger">
                            <tr>
                                <th style="width: 60px;" class="text-center">#</th>
                                <th>Full Name</th>
                                <th>Position Applied</th>
                                <th style="width: 170px;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $ri = 1; while($ra = mysqli_fetch_assoc($rejectedApps)){ 
                            $raJson = json_encode([
                                'id'               => (int)$ra['applicant_id'],
                                'name'             => $ra['full_name'],
                                'positionName'     => $ra['position_name'] ?? '—',
                                'empType'          => '',
                                'email'            => $ra['email'] ?? '',
                                'phone'            => $ra['phone'] ?? '',
                                'address'          => $ra['address'] ?? '',
                                'stage'            => $ra['stage'],
                                'notes'            => $ra['notes'] ?? '',
                                'appliedAt'        => date("M d, Y", strtotime($ra['applied_at'])),
                                'resume'           => $ra['resume'] ?? '',
                                'positionId'       => 0,
                                'passedStagesHtml' => renderFinishedStages($conn, $ra)
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        ?>
                            <tr>
                                <td class="text-center fw-semibold"><?= $ri++; ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($ra['full_name']); ?></div>
                                    <?php if(!empty($ra['email'])){ ?>
                                        <small class="text-muted"><?= htmlspecialchars($ra['email']); ?></small>
                                    <?php } ?>
                                </td>
                                <td>
                                    <span class="fw-medium text-dark"><?= htmlspecialchars($ra['position_name'] ?? '—'); ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button type="button" class="btn btn-sm btn-outline-info"
                                                onclick='openApplicantDetailsJson(<?= $raJson; ?>)'
                                                title="View Applicant Profile & Resume">
                                            <i class="bi bi-eye-fill me-1"></i> View
                                        </button>
                                        <?php if((int)$ra['archive_id'] > 0){ ?>
                                        <button class="btn btn-sm btn-outline-success"
                                                onclick="restoreApplicant(<?= $ra['archive_id']; ?>, '<?= htmlspecialchars(addslashes($ra['full_name']), ENT_QUOTES); ?>')"
                                                title="Restore Applicant">
                                            <i class="bi bi-arrow-counterclockwise"></i> Restore
                                        </button>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>
            <div class="modal-footer bg-light">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- APPLICANTS ARCHIVE MODAL -->
<div class="modal fade" id="applicantArchiveModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:12px; overflow:hidden;">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-archive-fill me-2"></i>Applicants Archive
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
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
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-secondary">
                            <tr>
                                <th style="width: 60px;" class="text-center">#</th>
                                <th>Full Name</th>
                                <th>Position Applied</th>
                                <th style="width: 170px;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $ai = 1; while($ar = mysqli_fetch_assoc($archivedApps)){ 
                            $arJson = json_encode([
                                'id'               => (int)$ar['applicant_id'],
                                'name'             => $ar['full_name'],
                                'positionName'     => $ar['position_name'] ?? '—',
                                'empType'          => '',
                                'email'            => $ar['email'] ?? '',
                                'phone'            => $ar['phone'] ?? '',
                                'address'          => $ar['address'] ?? '',
                                'stage'            => $ar['stage'],
                                'notes'            => $ar['notes'] ?? '',
                                'appliedAt'        => date("M d, Y", strtotime($ar['applied_at'])),
                                'resume'           => $ar['resume'] ?? '',
                                'positionId'       => 0,
                                'passedStagesHtml' => renderFinishedStages($conn, $ar)
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        ?>
                            <tr>
                                <td class="text-center fw-semibold"><?= $ai++; ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($ar['full_name']); ?></div>
                                    <?php if(!empty($ar['email'])){ ?>
                                        <small class="text-muted"><?= htmlspecialchars($ar['email']); ?></small>
                                    <?php } ?>
                                </td>
                                <td>
                                    <span class="fw-medium text-dark"><?= htmlspecialchars($ar['position_name'] ?? '—'); ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button type="button" class="btn btn-sm btn-outline-info"
                                                onclick='openApplicantDetailsJson(<?= $arJson; ?>)'
                                                title="View Applicant Profile & Resume">
                                            <i class="bi bi-eye-fill me-1"></i> View
                                        </button>
                                        <button class="btn btn-sm btn-outline-success"
                                                onclick="restoreApplicant(<?= $ar['archive_id']; ?>, '<?= htmlspecialchars(addslashes($ar['full_name']), ENT_QUOTES); ?>')"
                                                title="Restore Applicant">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Restore
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>
            <div class="modal-footer bg-light">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    APPLICANT DETAILS & RESUME MODAL
==========================================================-->
<div class="modal fade" id="applicantDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 92%;">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header text-white" style="background:#1a3c5e;">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <i class="bi bi-person-vcard-fill text-info"></i>
                    <span>Applicant Profile — <span id="det_fullName"></span></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="row g-4">
                    <!-- APPLICANT INFO CARD -->
                    <div class="col-lg-4 col-md-5">
                        <div class="card border-0 shadow-sm h-100" style="border-radius:10px;">
                            <div class="card-body p-4">
                                <div class="text-center mb-3">
                                    <div class="avatar-circle mx-auto mb-2 bg-primary text-white d-flex align-items-center justify-content-center"
                                         style="width: 64px; height: 64px; border-radius: 50%; font-size: 24px; font-weight: 700;">
                                        <span id="det_avatarInitials"></span>
                                    </div>
                                    <h5 class="fw-bold mb-0 text-dark" id="det_nameHeader"></h5>
                                    <span class="badge mt-2" id="det_stageBadge" style="font-size: 12px; padding: 5px 12px;"></span>
                                </div>
                                <hr>
                                <div class="vstack gap-3" style="font-size: 13px;">
                                    <div>
                                        <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 10px; letter-spacing: 0.5px;">Position Applied</small>
                                        <span class="fw-semibold text-dark" id="det_position"></span>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 10px; letter-spacing: 0.5px;">Email Address</small>
                                        <span class="fw-semibold text-dark" id="det_email"></span>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 10px; letter-spacing: 0.5px;">Phone Number</small>
                                        <span class="fw-semibold text-dark" id="det_phone"></span>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 10px; letter-spacing: 0.5px;">Address</small>
                                        <span class="fw-semibold text-dark" id="det_address"></span>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 10px; letter-spacing: 0.5px;">Date Applied</small>
                                        <span class="fw-semibold text-dark" id="det_appliedAt"></span>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 10px; letter-spacing: 0.5px;">Passed Stages</small>
                                        <div class="mt-1" id="det_passedStages"></div>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 10px; letter-spacing: 0.5px;">Notes / Remarks</small>
                                        <div class="p-2 bg-light border rounded text-muted mt-1" id="det_notes" style="font-style: italic;"></div>
                                    </div>
                                    <div class="pt-2 border-top">
                                        <small class="text-muted d-block text-uppercase fw-semibold mb-2" style="font-size: 10px; letter-spacing: 0.5px;">Applicant Actions</small>
                                        <div id="det_actionsArea"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RESUME PREVIEW CARD -->
                    <div class="col-lg-8 col-md-7">
                        <div class="card border-0 shadow-sm h-100" style="border-radius:10px; overflow: hidden;">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                                    <i class="bi bi-file-earmark-pdf-fill text-danger fs-5"></i>
                                    <span>Resume Preview</span>
                                </h6>
                                <a id="det_downloadResumeBtn" href="#" target="_blank" class="btn btn-sm btn-outline-primary" style="display:none;">
                                    <i class="bi bi-download me-1"></i> Open / Download PDF
                                </a>
                            </div>
                            <div class="card-body p-0 bg-dark position-relative d-flex align-items-center justify-content-center" style="min-height: 520px;">
                                <iframe id="det_resumeIframe" src="" style="width: 100%; height: 550px; border: none; display: none;" title="Resume Preview"></iframe>
                                <div id="det_noResumeBox" class="text-center text-white-50 p-5" style="display: none;">
                                    <i class="bi bi-file-earmark-x" style="font-size: 50px;"></i>
                                    <p class="mt-3 mb-0">No resume attached for this applicant.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    RESUME PREVIEW MODAL
==========================================================-->
<div class="modal fade" id="resumePreviewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header text-white" style="background:#1a3c5e;">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-pdf-fill text-danger"></i>
                    <span>Resume Preview — <span id="resumeApplicantName"></span></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="background: #525659; height: 80vh; position: relative;">
                <iframe id="resumeIframe" src="" style="width:100%; height:100%; border:none;" title="Resume Preview"></iframe>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <a id="resumeDownloadBtn" href="#" target="_blank" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-download me-1"></i> Open in New Tab / Download
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
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

function onAddPositionChange(sel){
    const opt = sel.options[sel.selectedIndex];
    const min = parseFloat(opt.getAttribute('data-salmin')) || 0;
    const max = parseFloat(opt.getAttribute('data-salmax')) || 0;
    if(min > 0 || max > 0){
        $("#add_salary_range").html(
            `<i class="bi bi-info-circle me-1"></i>Salary range: ₱${min.toLocaleString()} – ₱${max.toLocaleString()}/month`
        );
    } else {
        $("#add_salary_range").text('');
    }
}

function validatePhone(phone){
    return /^09\d{9}$/.test(phone);
}

function validateEmail(email){
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) && (email.match(/@/g)||[]).length === 1;
}

function submitAdd(){
    const name     = $("#add_name").val().trim();
    const position = $("#add_position").val();
    const email    = $("#add_email").val().trim();
    const phone    = $("#add_phone").val().trim();

    if(!name || !position){
        Swal.fire('Missing Fields','Name and Position are required.','warning'); return;
    }
    if(email && !validateEmail(email)){
        Swal.fire('Invalid Email','Email must have exactly one @ and a valid domain.','warning'); return;
    }
    if(phone && !validatePhone(phone)){
        Swal.fire('Invalid Phone','Phone must start with 09 and be exactly 11 digits.','warning'); return;
    }

    // Resume file check
    const resumeFile = $("#add_resume")[0].files[0];
    if(resumeFile){
        if(resumeFile.type !== 'application/pdf'){
            Swal.fire('Invalid File','Resume must be a PDF file.','warning'); return;
        }
        if(resumeFile.size > 5 * 1024 * 1024){
            Swal.fire('File Too Large','Resume must not exceed 5MB.','warning'); return;
        }
    }

    // Confirmation before saving
    Swal.fire({
        title: 'Add this applicant?',
        html: `<strong>${name}</strong> will be added to the applicant pool.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1a3c5e',
        confirmButtonText: 'Yes, Add'
    }).then(result => {
        if(!result.isConfirmed) return;

        const formData = new FormData();
        formData.append('action', 'create');
        formData.append('full_name', name);
        formData.append('position_id', position);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('address', $("#add_address").val());
        formData.append('notes', $("#add_notes").val());
        if(resumeFile) formData.append('resume', resumeFile);

        $.ajax({
            url: 'hrms_applicants.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response){
                if(response.trim() == 'success'){
                    Swal.fire({ icon:'success', title:'Applicant Added!',
                        showConfirmButton:false, timer:1500 })
                    .then(() => { clearBackdropHrms(); loadPage('hrms_applicants.php'); });
                } else {
                    Swal.fire('Error', response.replace('error:','').trim(), 'error');
                }
            }
        });
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
    const needsDate = (stage === 'First Interview' || stage === 'Final Interview');

    let extraHtml = '';
    if(needsDate){
        extraHtml = `<div class="mt-3 text-start">
            <label class="form-label fw-semibold" style="font-size:13px;">Interview Date &amp; Time</label>
            <input type="datetime-local" id="swalInterviewDate" class="swal2-input" style="margin:0;width:100%;">
            <div style="font-size:11px;color:#6c757d;margin-top:4px;">The applicant will be emailed this schedule automatically.</div>
        </div>`;
    } else if(stage === 'Rejected'){
        extraHtml = `<div class="mt-3 text-start">
            <label class="form-label fw-semibold text-danger" style="font-size:13px;">Reason for Rejection <span class="text-danger">*</span></label>
            <select id="swalRejectReason" class="swal2-select" style="margin:0; width:100%; font-size:13px;" onchange="if(this.value==='Other'){ $('#swalCustomRejectBlock').show(); } else { $('#swalCustomRejectBlock').hide(); }">
                <option value="">-- Select Rejection Reason --</option>
                <optgroup label="Skills &amp; Qualifications">
                    <option value="Does Not Meet Minimum Work Experience Requirements">Does Not Meet Minimum Work Experience Requirements</option>
                    <option value="Lack of Required Technical Skills / Domain Knowledge">Lack of Required Technical Skills / Domain Knowledge</option>
                    <option value="Unsatisfactory Technical Assessment / Skill Test Result">Unsatisfactory Technical Assessment / Skill Test Result</option>
                </optgroup>
                <optgroup label="Interview Performance &amp; Communication">
                    <option value="Poor Communication / Interpersonal Skills">Poor Communication / Interpersonal Skills</option>
                    <option value="Lacks Problem-Solving / Analytical Abilities">Lacks Problem-Solving / Analytical Abilities</option>
                    <option value="Unprofessional Conduct During Interview">Unprofessional Conduct During Interview</option>
                    <option value="Lack of Motivation / Interest in the Role">Lack of Motivation / Interest in the Role</option>
                </optgroup>
                <optgroup label="Schedule &amp; Availability">
                    <option value="Unable to Work Required Shift Schedule / Rest Days">Unable to Work Required Shift Schedule / Rest Days</option>
                    <option value="Salary Expectation Exceeds Offered Range">Salary Expectation Exceeds Offered Range</option>
                    <option value="Notice Period Too Long / Cannot Start Immediately">Notice Period Too Long / Cannot Start Immediately</option>
                </optgroup>
                <optgroup label="Attendance &amp; Credentials">
                    <option value="No-Show / Failed to Attend Scheduled Interview">No-Show / Failed to Attend Scheduled Interview</option>
                    <option value="Unverifiable Qualifications / Background Check Discrepancy">Unverifiable Qualifications / Background Check Discrepancy</option>
                    <option value="Candidate Withdrew Application">Candidate Withdrew Application</option>
                </optgroup>
                <optgroup label="Other">
                    <option value="Other">Other / Custom Reason (Specify below)</option>
                </optgroup>
            </select>
            <div id="swalCustomRejectBlock" class="mt-2" style="display:none;">
                <input type="text" id="swalCustomRejectReason" class="swal2-input" style="margin:0; width:100%; font-size:13px;" placeholder="Specify custom rejection reason...">
            </div>
            <div style="font-size:11px;color:#6c757d;margin-top:4px;">Recorded in applicant notes and email notification.</div>
        </div>`;
    }

    Swal.fire({
        title: `Move ${name} to:`,
        html: `<strong>${icons[stage] || ''} ${stage}</strong>${extraHtml}`,
        icon: stage == 'Rejected' ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: colors[stage] || '#2563eb',
        confirmButtonText: stage == 'Rejected' ? 'Confirm Rejection' : 'Yes, Move',
        preConfirm: () => {
            if(needsDate){
                const val = $('#swalInterviewDate').val();
                if(!val){
                    Swal.showValidationMessage('Please set the interview date & time');
                    return false;
                }
                return { interview_date: val };
            }
            if(stage === 'Rejected'){
                let reason = $('#swalRejectReason').val();
                if(!reason){
                    Swal.showValidationMessage('Please select a reason for rejection');
                    return false;
                }
                if(reason === 'Other'){
                    reason = $('#swalCustomRejectReason').val().trim();
                    if(!reason){
                        Swal.showValidationMessage('Please type the custom rejection reason');
                        return false;
                    }
                }
                return { rejection_reason: reason };
            }
            return {};
        }
    }).then(result => {
        if(!result.isConfirmed) return;
        const resVal = result.value || {};
        $.post('hrms_applicants.php', {
            action:           'advance_stage',
            applicant_id:     id,
            stage:            stage,
            interview_date:   resVal.interview_date || '',
            rejection_reason: resVal.rejection_reason || ''
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
function restoreApplicant(archiveId, name){
    Swal.fire({
        title: 'Restore Applicant?',
        html: `<strong>${name}</strong> will be restored from archive back to active applicants.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Yes, Restore'
    }).then(result => {
        if(!result.isConfirmed) return;
        $.post('hrms_applicants.php', {
            action: 'restore',
            archive_id: archiveId
        }, function(response){
            if(response.trim() == 'success'){
                Swal.fire({
                    icon: 'success',
                    title: 'Applicant Restored!',
                    text: name + ' has been moved back to the applicant pool.',
                    showConfirmButton: false,
                    timer: 1600
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

function openRejectedApplicantsModal(){
    const el = document.getElementById('rejectedApplicantsModal');
    if(el.parentNode !== document.body){
        document.body.appendChild(el);
    }
    new bootstrap.Modal(el).show();
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
    APPLICANT DETAILS & RESUME MODAL
====================================================*/
function openApplicantDetailsJson(data){
    if(!data) return;
    openApplicantDetailsModal(
        data.id,
        data.name,
        data.positionName,
        data.empType,
        data.email,
        data.phone,
        data.address,
        data.stage,
        data.notes,
        data.appliedAt,
        data.resume,
        data.positionId,
        data.passedStagesHtml
    );
}

function openApplicantDetailsModal(id, name, positionName, empType, email, phone, address, stage, notes, appliedAt, resumeFileName, positionId, passedStagesHtml){
    // Hide any currently visible modal (e.g. rejectedApplicantsModal or applicantArchiveModal)
    $('.modal.show').each(function(){
        if(this.id !== 'applicantDetailsModal'){
            const inst = bootstrap.Modal.getInstance(this);
            if(inst) inst.hide();
        }
    });

    const el = document.getElementById('applicantDetailsModal');
    if(el.parentNode !== document.body){
        document.body.appendChild(el);
    }

    const initials = name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() || 'A';
    $('#det_avatarInitials').text(initials);
    $('#det_fullName, #det_nameHeader').text(name);
    $('#det_position').text(positionName + (empType ? ' (' + empType + ')' : ''));
    $('#det_email').text(email || '—');
    $('#det_phone').text(phone || '—');
    $('#det_address').text(address || '—');
    $('#det_appliedAt').text(appliedAt || '—');
    $('#det_notes').text(notes || 'No remarks provided.');
    $('#det_passedStages').html(passedStagesHtml || '<span class="badge bg-light text-muted border fw-normal" style="font-size:11px;">None</span>');

    const stageStyles = {
        'Initial Screening': { bg: '#e9ecef', color: '#495057' },
        'First Interview':   { bg: '#cfe2ff', color: '#084298' },
        'Final Interview':   { bg: '#e0cffc', color: '#3d0a91' },
        'Approved':          { bg: '#d1e7dd', color: '#0a3622' },
        'Rejected':          { bg: '#f8d7da', color: '#842029' }
    };
    const style = stageStyles[stage] || { bg: '#e9ecef', color: '#495057' };
    $('#det_stageBadge').text(stage).css({'background-color': style.bg, 'color': style.color});

    // Generate action buttons for this applicant
    let actionsHtml = '';
    const safeName = name.replace(/'/g, "\\'");
    const safeEmail = (email || '').replace(/'/g, "\\'");
    const safePhone = (phone || '').replace(/'/g, "\\'");
    const safeAddr = (address || '').replace(/'/g, "\\'");

    // Advance Stage buttons if stage is not Approved or Rejected
    const nextStages = {
        'Initial Screening': ['First Interview', 'Rejected'],
        'First Interview':   ['Final Interview', 'Rejected'],
        'Final Interview':   ['Approved', 'Rejected']
    };
    const next = nextStages[stage] || [];
    if(next.length > 0){
        actionsHtml += `<div class="btn-group w-100 mb-2">
            <button type="button" class="btn btn-sm btn-success dropdown-toggle w-100" data-bs-toggle="dropdown">
                <i class="bi bi-arrow-right-circle me-1"></i> Move to Next Stage
            </button>
            <ul class="dropdown-menu w-100">`;
        next.forEach(ns => {
            const icon = (ns === 'Rejected') ? 'bi-x-circle text-danger' : 'bi-arrow-right-circle text-success';
            actionsHtml += `<li><a class="dropdown-item" href="#" onclick="advanceStageFromModal(${id}, '${ns}', '${safeName}'); return false;">
                <i class="bi ${icon} me-2"></i> ${ns}
            </a></li>`;
        });
        actionsHtml += `</ul></div>`;
    }

    // Convert / Hire button if Approved
    if(stage === 'Approved'){
        actionsHtml += `<button class="btn btn-sm btn-success w-100 mb-2" onclick="openConvertFromModal(${id}, ${positionId || 0}, '${safeName}', '${safeEmail}', '${safePhone}', '${safeAddr}')">
            <i class="bi bi-person-check-fill me-1"></i> Hire / Convert to Employee
        </button>`;
    }

    // Remove / Archive button
    actionsHtml += `<button class="btn btn-sm btn-outline-danger w-100" onclick="deleteApplicantFromModal(${id}, '${safeName}')">
        <i class="bi bi-trash-fill me-1"></i> Remove / Archive Applicant
    </button>`;

    $('#det_actionsArea').html(actionsHtml);

    if(resumeFileName && resumeFileName.trim() !== ''){
        const fileUrl = 'uploads/resumes/' + encodeURIComponent(resumeFileName);
        $('#det_resumeIframe').attr('src', fileUrl).show();
        $('#det_downloadResumeBtn').attr('href', fileUrl).show();
        $('#det_noResumeBox').hide();
    } else {
        $('#det_resumeIframe').attr('src', '').hide();
        $('#det_downloadResumeBtn').hide();
        $('#det_noResumeBox').show();
    }

    new bootstrap.Modal(el).show();
}

function advanceStageFromModal(id, stage, name){
    const el = document.getElementById('applicantDetailsModal');
    const modal = bootstrap.Modal.getInstance(el);
    if (modal) modal.hide();
    setTimeout(() => { advanceStage(id, stage, name); }, 300);
}

function openConvertFromModal(applicantId, positionId, name, email, phone, address){
    const el = document.getElementById('applicantDetailsModal');
    const modal = bootstrap.Modal.getInstance(el);
    if (modal) modal.hide();
    setTimeout(() => { openConvertModal(applicantId, positionId, name, email, phone, address); }, 300);
}

function deleteApplicantFromModal(id, name){
    const el = document.getElementById('applicantDetailsModal');
    const modal = bootstrap.Modal.getInstance(el);
    if (modal) modal.hide();
    setTimeout(() => { deleteApplicant(id, name); }, 300);
}

$(document).on('hidden.bs.modal', '#applicantDetailsModal', function () {
    $('#det_resumeIframe').attr('src', '');
});

/*====================================================
    RESUME PREVIEW
====================================================*/
function previewResume(fileName, applicantName){
    const el = document.getElementById('resumePreviewModal');
    if(el.parentNode !== document.body){
        document.body.appendChild(el);
    }
    const fileUrl = 'uploads/resumes/' + encodeURIComponent(fileName);
    $('#resumeApplicantName').text(applicantName);
    $('#resumeIframe').attr('src', fileUrl);
    $('#resumeDownloadBtn').attr('href', fileUrl);

    new bootstrap.Modal(el).show();
}

$(document).on('hidden.bs.modal', '#resumePreviewModal', function () {
    $('#resumeIframe').attr('src', '');
});

/*====================================================
    INPUT HELPERS (salary slider + ID number masking)
====================================================*/
function syncSalaryFromSlider(el){
    $('#conv_salary').val(el.value);
}
function clampSalaryInput(el){
    const min = parseFloat($('#conv_sal_min').val()) || 0;
    const max = parseFloat($('#conv_sal_max').val()) || 0;
    if(max > 0) $('#conv_salary_slider').val(Math.min(Math.max(parseFloat(el.value) || min, min), max));
}
function maskNumericGroups(el, groups){
    let digits = el.value.replace(/\D/g, '');
    const maxDigits = groups.reduce((a,b) => a + b, 0);
    digits = digits.slice(0, maxDigits);
    let parts = [], idx = 0;
    groups.forEach(len => {
        if(digits.length > idx){ parts.push(digits.slice(idx, idx + len)); idx += len; }
    });
    el.value = parts.join('-');
}

/*====================================================
    WORK SCHEDULE & REST DAY SMART SUGGESTIONS
====================================================*/
// Map each schedule option to its suggested rest day
const SCHEDULE_REST_MAP = {
    'Monday\u2013Friday 8:00 AM\u20135:00 PM':                              { rest: 'Sunday & Saturday', hint: 'Standard Mon\u2013Fri: Sat & Sun are rest days.' },
    'Monday\u2013Friday 9:00 AM\u20136:00 PM':                              { rest: 'Sunday & Saturday', hint: 'Standard Mon\u2013Fri: Sat & Sun are rest days.' },
    'Monday\u2013Saturday 8:00 AM\u20135:00 PM':                            { rest: 'Sunday',            hint: 'Mon\u2013Sat schedule: Sunday is the rest day.' },
    'Monday\u2013Sunday 8:00 AM\u20135:00 PM (1 Rest Day)':                 { rest: '',                  hint: 'Choose which day of the week this employee is off.' },
    'Monday\u2013Sunday 10:00 AM\u20137:00 PM (1 Rest Day)':                { rest: '',                  hint: 'Choose which day of the week this employee is off.' },
    'Monday\u2013Sunday 12:00 PM\u20139:00 PM (1 Rest Day)':                { rest: '',                  hint: 'Choose which day of the week this employee is off.' },
    'Shifting \u2013 Morning 6:00 AM\u20132:00 PM':                        { rest: 'Rotating (as scheduled)', hint: 'Shifting employees typically follow a rotating rest day.' },
    'Shifting \u2013 Afternoon 2:00 PM\u201310:00 PM':                      { rest: 'Rotating (as scheduled)', hint: 'Shifting employees typically follow a rotating rest day.' },
    'Shifting \u2013 Night 10:00 PM\u20136:00 AM':                          { rest: 'Rotating (as scheduled)', hint: 'Night shift: rest day is rotation-based.' },
    'Rotating Shift (4-Day On / 2-Day Off)':                               { rest: 'Rotating (as scheduled)', hint: '4-day cycle: 2 consecutive rest days rotate weekly.' },
    'Monday\u2013Friday 8:00 AM\u201312:00 PM (Half Day)':                  { rest: 'Sunday & Saturday', hint: 'Half-day schedule: weekends are typically off.' },
    'Compressed Week \u2013 Monday\u2013Thursday 7:00 AM\u20136:00 PM':     { rest: 'Friday',            hint: 'Compressed 4-day week: Friday\u2013Sunday are off.' },
    'Weekends Only 8:00 AM\u20135:00 PM':                                  { rest: 'Monday',            hint: 'Weekend-only workers often rest on a weekday.' },
};

function onScheduleChange(el) {
    const val = el.value;
    const customInput = $('#conv_schedule_custom');
    const hint = $('#conv_schedule_hint');
    const restSelect = $('#conv_restday');
    const restBadge = $('#conv_restday_auto_badge');
    const restHint = $('#conv_restday_hint');

    if (val === 'custom') {
        customInput.show().focus();
        hint.text('Enter a custom schedule in the field above.');
        restBadge.hide();
        restHint.text('');
        return;
    }

    customInput.hide().val('');

    const mapping = SCHEDULE_REST_MAP[val];
    if (mapping) {
        hint.text('\u2713 ' + mapping.hint);
        if (mapping.rest) {
            restSelect.val(mapping.rest);
            restBadge.show();
            restHint.text('Auto-suggested based on schedule. You may change this if needed.');
        } else {
            restSelect.val('');
            restBadge.hide();
            restHint.text(mapping.hint);
        }
    } else {
        hint.text('');
        restBadge.hide();
        restHint.text('');
    }
}

function onCustomScheduleInput(el) {
    // Mirror custom text into conv_schedule select value isn't applicable,
    // but we capture it on submit via a data attribute on the select
    $('#conv_schedule').data('custom-value', el.value.trim());
}

/*====================================================
    CONTRACT DATES HELPER
====================================================*/
function updateContractEndDate() {
    const hiredVal = $('#conv_datehired').val();
    if (hiredVal) {
        $('#conv_contract_start').val(hiredVal);
        const dt = new Date(hiredVal);
        dt.setMonth(dt.getMonth() + 6);
        const yyyy = dt.getFullYear();
        const mm = String(dt.getMonth() + 1).padStart(2, '0');
        const dd = String(dt.getDate()).padStart(2, '0');
        const endDateStr = `${yyyy}-${mm}-${dd}`;
        $('#conv_contract_end').val(endDateStr);
        $('#conv_contract_duration_text').html('<i class="bi bi-clock-history me-1"></i>Good for 6 months (' + hiredVal + ' to ' + endDateStr + ')');
    }
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
    $("#conv_contract_signed").prop('checked', false);
    updateContractEndDate();
    // Reset schedule & rest day
    $("#conv_schedule").val('');
    $("#conv_schedule_custom").hide().val('');
    $("#conv_schedule_hint").text('');
    $("#conv_restday").val('');
    $("#conv_restday_hint").text('');
    $("#conv_restday_auto_badge").hide();

    // Set birthdate max to 18 years ago
    const maxBirth = new Date();
    maxBirth.setFullYear(maxBirth.getFullYear() - 18);
    $("#conv_birthdate").attr('max', maxBirth.toISOString().split('T')[0]);

    // Load salary range from position
    const posOpt = $("#conv_position option[value='" + positionId + "']");
    const salMin = parseFloat(posOpt.attr('data-salmin')) || 0;
    const salMax = parseFloat(posOpt.attr('data-salmax')) || 0;
    $("#conv_sal_min").val(salMin);
    $("#conv_sal_max").val(salMax);

    if(salMin > 0 && salMax > 0 && salMin === salMax){
        // Fixed salary for this position — lock the field, no typing needed
        $("#conv_salary").val(salMin).attr('min', salMin).attr('max', salMax).prop('disabled', true);
        $("#conv_salary_slider").hide();
        $("#conv_salary_range").html(
            `<i class="bi bi-lock-fill me-1"></i>Fixed salary for this position: ₱${salMin.toLocaleString()}/month`
        );
    } else if(salMin > 0 || salMax > 0){
        // Range-based salary — drag the slider or type a value, both stay locked to the range
        $("#conv_salary").val(salMin || '').attr('min', salMin).attr('max', salMax).prop('disabled', false);
        const step = Math.max(1, Math.round((salMax - salMin) / 50));
        $("#conv_salary_slider").attr('min', salMin).attr('max', salMax).attr('step', step)
            .val(salMin).show();
        $("#conv_salary_range").html(
            `<i class="bi bi-info-circle me-1"></i>Range: ₱${salMin.toLocaleString()} – ₱${salMax.toLocaleString()}/month`
        );
    } else {
        $("#conv_salary").prop('disabled', false).removeAttr('min').removeAttr('max');
        $("#conv_salary_slider").hide();
        $("#conv_salary_range").text('');
    }

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

    // Birthdate — must be at least 18 years old
    const birthdate = $("#conv_birthdate").val();
    if(birthdate){
        const birth = new Date(birthdate);
        const today = new Date();
        const age = today.getFullYear() - birth.getFullYear() -
            (today < new Date(today.getFullYear(), birth.getMonth(), birth.getDate()) ? 1 : 0);
        if(age < 18){
            Swal.fire('Invalid Birthdate','Employee must be at least 18 years old.','warning'); return;
        }
        if(birth > today){
            Swal.fire('Invalid Birthdate','Birthdate cannot be in the future.','warning'); return;
        }
    }

    // Salary range check
    const salMin = parseFloat($("#conv_sal_min").val()) || 0;
    const salMax = parseFloat($("#conv_sal_max").val()) || 0;
    const salVal = parseFloat(salary);
    if(salMin > 0 && salMax > 0 && (salVal < salMin || salVal > salMax)){
        Swal.fire('Salary Out of Range',
            `Salary must be between ₱${salMin.toLocaleString()} and ₱${salMax.toLocaleString()}.`,
            'warning');
        return;
    }

    // Government numbers — optional, but if filled in they must be a complete number
    const govtChecks = [
        { id: 'conv_sss',        label: 'SSS No.',        pattern: /^\d{2}-\d{7}-\d{1}$/,       example: 'XX-XXXXXXX-X' },
        { id: 'conv_philhealth', label: 'PhilHealth No.', pattern: /^\d{4}-\d{4}-\d{4}$/,       example: 'XXXX-XXXX-XXXX' },
        { id: 'conv_pagibig',    label: 'Pag-IBIG No.',   pattern: /^\d{4}-\d{4}-\d{4}$/,       example: 'XXXX-XXXX-XXXX' },
        { id: 'conv_tin',        label: 'TIN No.',        pattern: /^\d{3}-\d{3}-\d{3}-\d{3}$/, example: 'XXX-XXX-XXX-XXX' }
    ];
    for(const g of govtChecks){
        const val = $('#' + g.id).val().trim();
        if(val && !g.pattern.test(val)){
            Swal.fire('Incomplete ' + g.label, `Please finish entering the full number, e.g. ${g.example}.`, 'warning');
            return;
        }
    }

    // Contract signing validation
    if(!$('#conv_contract_signed').is(':checked')){
        Swal.fire('Contract Signing Required', 'Please confirm that the 6-Month Employment Contract has been agreed upon and signed by checking the agreement checkbox.', 'warning');
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
            full_name:       name,
            email:           $("#conv_email").val(),
            phone:           $("#conv_phone").val(),
            address:         $("#conv_address").val(),
            birthdate:       $("#conv_birthdate").val(),
            gender:          $("#conv_gender").val(),
            civil_status:    $("#conv_civil").val(),
            date_hired:      hired,
            employment_type: $("#conv_emptype").val(),
            schedule:        $("#conv_schedule").val() === 'custom'
                                 ? ($("#conv_schedule").data('custom-value') || $("#conv_schedule_custom").val().trim())
                                 : $("#conv_schedule").val(),
            rest_day:        $("#conv_restday").val(),
            basic_salary:    salary,
            contract_start:  $("#conv_contract_start").val(),
            contract_end:    $("#conv_contract_end").val(),
            contract_signed: $("#conv_contract_signed").is(':checked') ? 1 : 0,
            sss_no:          $("#conv_sss").val(),
            philhealth_no:   $("#conv_philhealth").val(),
            pagibig_no:      $("#conv_pagibig").val(),
            tin_no:          $("#conv_tin").val()
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