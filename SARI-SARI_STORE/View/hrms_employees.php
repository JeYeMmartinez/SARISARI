<?php
require_once '../Model/database.php';
require_once '../Model/logger.php';

if(session_status() === PHP_SESSION_NONE){
    session_start();
}
$admin_id = $_SESSION['user_id'] ?? 1;

// Verifies the currently logged-in admin's password against the users table.
function verifyAdminPassword($conn, $admin_id, $password){
    if(empty($password)) return false;
    $admin_id = (int)$admin_id;
    $res = mysqli_query($conn, "SELECT password FROM users WHERE user_id = $admin_id LIMIT 1");
    $row = mysqli_fetch_assoc($res);
    if(!$row || empty($row['password'])) return false;
    return password_verify($password, $row['password']);
}

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
    PHPMailer Email Sending Helpers
==========================================================*/
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

        $mail->setFrom('edonnarao06@gmail.com', 'O-cart! HRMS');
        $mail->addAddress($gmail);

        $mail->isHTML(true);
        $mail->Subject = 'Your Employee Portal Credentials - O-cart!';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px; max-width: 500px;'>
                <h2 style='color: #1a3c5e;'>O-cart! E-Portal</h2>
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

        $mail->setFrom('edonnarao06@gmail.com', 'O-Cart! Store HRMS');
        $mail->addAddress($gmail);

        $mail->isHTML(true);
        $mail->Subject = 'Your Employee Portal Password Was Updated - O-Cart!';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px; max-width: 500px;'>
                <h2 style='color: #1a3c5e;'>O-Cart! Employee Portal</h2>
                <p>Hello <strong>$name</strong>,</p>
                <p>Your employee portal account password has been updated by the HR administrator.</p>
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
                <p style='margin-top: 25px; font-size: 12px; color: #888;'>If you did not request or expect this change, please contact your HR department immediately.</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Password Reset Email Error: " . $mail->ErrorInfo);
        return false;
    }
}

/*=========================================================
    ACTIONS (POST)
==========================================================*/

// CREATE
if(isset($_POST['action']) && $_POST['action'] == 'create'){
    if(!verifyAdminPassword($conn, $admin_id, $_POST['password'] ?? '')){
        ob_clean();
        echo 'error: Incorrect password. Employee was not added.';
        exit();
    }

    $position_id     = (int)$_POST['position_id'];
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
    $portal_password = isset($_POST['portal_password']) ? trim($_POST['portal_password']) : '';

    if(!empty($portal_password) && empty($email)) {
        ob_clean();
        echo 'error: Email is required to generate a portal account.';
        exit();
    }

    // Enforce position slot capacity
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
        echo "error: This position is already fully filled ($filledSlots/$totalSlots slots taken). Increase the slot count in Positions before adding another employee to this role.";
        exit();
    }

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
            position_id, employee_no, full_name, email, phone, address,
            birthdate, gender, civil_status, date_hired, employment_type, basic_salary,
            sss_no, philhealth_no, pagibig_no, tin_no, photo, status
        ) VALUES (
            $position_id, '$emp_no', '$full_name', '$email', '$phone', '$address',
            $birthdate_val, '$gender', '$civil_status', '$date_hired', '$employment_type', $basic_salary,
            '$sss_no', '$philhealth_no', '$pagibig_no', '$tin_no', $photo_val, 'Active'
        )
    ");

    ob_clean();
    if($q){
        $new_id = mysqli_insert_id($conn);
        logAction($conn, $admin_id, 'Create', 'employees', $new_id,
            "Added employee: $full_name (#$emp_no)");

        // Process Portal Account Creation
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
                $mail_status = ($sent === true) ? '' : '|warning:Email failed - ' . $sent;
            } else {
                // Gmail already has a portal account — reset its password instead of skipping silently
                mysqli_query($conn, "UPDATE users SET password = '$hashed_password' WHERE gmail = '$email'");
                $sent = sendEmployeePasswordResetEmail($email, $full_name, $portal_password);
                $mail_status = $sent
                    ? '|notice:This Gmail already had a portal account, so its password was reset and emailed.'
                    : '|warning:This Gmail already had a portal account. Password was reset but the email failed to send.';
            }
        }
        echo 'success' . $mail_status;
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// UPDATE
if(isset($_POST['action']) && $_POST['action'] == 'update'){
    if(!verifyAdminPassword($conn, $admin_id, $_POST['password'] ?? '')){
        ob_clean();
        echo 'error: Incorrect password. Changes were not saved.';
        exit();
    }

    $id              = (int)$_POST['employee_id'];
    $position_id     = (int)$_POST['position_id'];
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
    $portal_password = isset($_POST['portal_password']) ? trim($_POST['portal_password']) : '';

    if(!empty($portal_password) && empty($email)) {
        ob_clean();
        echo 'error: Email is required to generate or reset a portal account.';
        exit();
    }

    // Fetch old employee data (email for portal sync, position for slot check)
    $old_emp = mysqli_fetch_assoc(mysqli_query($conn, "SELECT email, full_name, position_id, status FROM employees WHERE employee_id = $id"));
    $old_email = $old_emp ? $old_emp['email'] : '';

    // Enforce slot capacity only if the employee is being moved into a different position (or reactivated as Active)
    $movingPosition = $old_emp && ((int)$old_emp['position_id'] !== $position_id);
    $becomingActive = $status === 'Active' && $old_emp && $old_emp['status'] !== 'Active';
    if($movingPosition || $becomingActive){
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
            "SELECT COUNT(*) AS cnt FROM employees WHERE position_id = $position_id AND status = 'Active' AND employee_id != $id"
        ))['cnt'];
        if($filledSlots >= $totalSlots){
            ob_clean();
            echo "error: This position is already fully filled ($filledSlots/$totalSlots slots taken). Increase the slot count in Positions before assigning another employee to this role.";
            exit();
        }
    }

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

        // Sync with users table
        if(!empty($email)){
            // If email changed, sync in users table first
            if(!empty($old_email) && $old_email !== $email){
                mysqli_query($conn, "UPDATE users SET gmail = '$email', full_name = '$full_name' WHERE gmail = '$old_email'");
            }
            
            if(!empty($portal_password)){
                $hashed_password = password_hash($portal_password, PASSWORD_BCRYPT);
                $user_exists = mysqli_query($conn, "SELECT user_id FROM users WHERE gmail = '$email'");
                if(mysqli_num_rows($user_exists) > 0){
                    mysqli_query($conn, "UPDATE users SET password = '$hashed_password', full_name = '$full_name' WHERE gmail = '$email'");
                    sendEmployeePasswordResetEmail($email, $full_name, $portal_password);
                } else {
                    mysqli_query($conn, "
                        INSERT INTO users (gmail, password, full_name, role, status)
                        VALUES ('$email', '$hashed_password', '$full_name', 'Cashier', 'Active')
                    ");
                    sendEmployeeWelcomeEmail($email, $full_name, $portal_password);
                }
            } else {
                mysqli_query($conn, "UPDATE users SET full_name = '$full_name' WHERE gmail = '$email'");
            }
        }
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

// DELETE — archives employee then soft-deletes
if(isset($_POST['action']) && $_POST['action'] == 'delete'){
    if(!verifyAdminPassword($conn, $admin_id, $_POST['password'] ?? '')){
        ob_clean();
        echo 'error: Incorrect password. Employee was not deleted.';
        exit();
    }

    $id     = (int)$_POST['employee_id'];
    $reason = mysqli_real_escape_string($conn, trim($_POST['reason'] ?? 'No reason provided'));

    // Fetch full employee info including position/department names
    $emp = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT e.*, p.position_name, d.department_name
        FROM employees e
        LEFT JOIN positions p ON e.position_id = p.position_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE e.employee_id = $id
    "));

    if(!$emp){
        ob_clean();
        echo 'error: Employee not found.';
        exit();
    }

    // Archive the employee record first
    $full_name      = mysqli_real_escape_string($conn, $emp['full_name']);
    $email          = mysqli_real_escape_string($conn, $emp['email'] ?? '');
    $phone          = mysqli_real_escape_string($conn, $emp['phone'] ?? '');
    $address        = mysqli_real_escape_string($conn, $emp['address'] ?? '');
    $sss            = mysqli_real_escape_string($conn, $emp['sss_no'] ?? '');
    $philhealth     = mysqli_real_escape_string($conn, $emp['philhealth_no'] ?? '');
    $pagibig        = mysqli_real_escape_string($conn, $emp['pagibig_no'] ?? '');
    $tin            = mysqli_real_escape_string($conn, $emp['tin_no'] ?? '');
    $position_name  = mysqli_real_escape_string($conn, $emp['position_name'] ?? '');
    $dept_name      = mysqli_real_escape_string($conn, $emp['department_name'] ?? '');
    $emp_no         = mysqli_real_escape_string($conn, $emp['employee_no']);
    $salary         = (float)$emp['basic_salary'];
    $birthdate      = $emp['birthdate'] ? "'{$emp['birthdate']}'" : 'NULL';
    $date_hired     = $emp['date_hired'] ? "'{$emp['date_hired']}'" : 'NULL';
    $photo          = mysqli_real_escape_string($conn, $emp['photo'] ?? '');

    mysqli_query($conn,"
        INSERT INTO employees_archive
            (employee_id, employee_no, full_name, email, phone, address,
             birthdate, gender, civil_status, date_hired, employment_type,
             basic_salary, status, photo, sss_no, philhealth_no, pagibig_no,
             tin_no, position_name, department_name, deleted_by, deleted_reason)
        VALUES
            ($id, '$emp_no', '$full_name', '$email', '$phone', '$address',
             $birthdate, '{$emp['gender']}', '{$emp['civil_status']}', $date_hired,
             '{$emp['employment_type']}', $salary, '{$emp['status']}', '$photo',
             '$sss', '$philhealth', '$pagibig', '$tin',
             '$position_name', '$dept_name', $admin_id, '$reason')
    ");

    // Delete dependent records
    mysqli_query($conn, "DELETE FROM payroll WHERE employee_id = $id");
    mysqli_query($conn, "DELETE FROM leave_requests WHERE employee_id = $id");
    mysqli_query($conn, "DELETE FROM attendance WHERE employee_id = $id");

    // Soft-delete linked user account (set status to Inactive) instead of hard delete
    // Hard delete would break audit_logs FK
    if(!empty($emp['email'])){
        $safeEmail = mysqli_real_escape_string($conn, $emp['email']);
        mysqli_query($conn, "UPDATE users SET role = 'Inactive' WHERE gmail = '$safeEmail'");
    }

    $q = mysqli_query($conn, "DELETE FROM employees WHERE employee_id = $id");
    ob_clean();
    if($q){
        logAction($conn, $admin_id, 'Delete', 'employees', $id,
            "Archived & deleted employee: {$emp['full_name']} (#{$emp['employee_no']}) — Reason: $reason");
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
    SELECT e.*, p.position_name
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.position_id
    ORDER BY e.employee_no ASC
");

$empList = [];
while($row = mysqli_fetch_assoc($employees)){
    $empList[] = $row;
}

// Positions
$positions = mysqli_query($conn, "
    SELECT p.*
    FROM positions p
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
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
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
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
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
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
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
        <button class="btn btn-outline-secondary" onclick="openArchiveModal()">
            <i class="bi bi-archive-fill me-1"></i>Archive
            <?php
            $archCount = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM employees_archive"))['c'];
            if($archCount > 0) echo '<span class="badge bg-danger ms-1">'.$archCount.'</span>';
            ?>
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
                    <th>Position</th>
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
                            <!-- ID CARD -->
                            <button class="btn btn-sm btn-outline-success" 
                                    onclick="generateEmployeeIDCard(<?= htmlspecialchars(json_encode($row)); ?>)" 
                                    title="Generate ID Card">
                                <i class="bi bi-qr-code"></i>
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
                $archived = mysqli_query($conn,"SELECT * FROM employees_archive ORDER BY deleted_at DESC");
                if(mysqli_num_rows($archived) == 0){
                    echo '<div class="text-center text-muted py-5"><i class="bi bi-archive" style="font-size:40px;"></i><p class="mt-3">No archived employees yet.</p></div>';
                } else {
                ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm">
                        <thead class="table-secondary">
                            <tr>
                                <th>#</th>
                                <th>Employee No</th>
                                <th>Full Name</th>
                                <th>Position</th>
                                <th>Department</th>
                                <th>Email</th>
                                <th>Salary</th>
                                <th>Status</th>
                                <th>Reason</th>
                                <th>Deleted On</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $ai=1; while($ar = mysqli_fetch_assoc($archived)){ ?>
                            <tr>
                                <td><?= $ai++; ?></td>
                                <td><?= htmlspecialchars($ar['employee_no']); ?></td>
                                <td><?= htmlspecialchars($ar['full_name']); ?></td>
                                <td><?= htmlspecialchars($ar['position_name'] ?? '—'); ?></td>
                                <td><?= htmlspecialchars($ar['department_name'] ?? '—'); ?></td>
                                <td><?= htmlspecialchars($ar['email'] ?? '—'); ?></td>
                                <td>₱<?= number_format($ar['basic_salary'],2); ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($ar['status']); ?></span></td>
                                <td><small class="text-muted"><?= htmlspecialchars($ar['deleted_reason']); ?></small></td>
                                <td><?= date('M d, Y h:i A', strtotime($ar['deleted_at'])); ?></td>
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
                                    <img id="id_card_photo" src="" class="id-photo" alt="Employee Photo" style="display:none;">
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

                        <!-- PORTAL CREDENTIALS -->
                        <div class="col-12 mt-3"><div class="fw-bold text-primary mb-1 uppercase" style="font-size:12px;letter-spacing:.5px;">Portal Account Credentials</div></div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Auto-Generated Portal Password</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="portal_password" id="add_portal_password" placeholder="Will be emailed to employee">
                                <button class="btn btn-outline-secondary" type="button" onclick="regenerateAddPassword()">
                                    <i class="bi bi-arrow-clockwise"></i> Generate
                                </button>
                            </div>
                            <small class="text-muted">You can edit this password. Portal login link and credentials will be sent to the employee's Gmail.</small>
                        </div>

                        <!-- EMPLOYMENT -->
                        <div class="col-12 mt-3"><div class="fw-bold text-primary mb-1 uppercase" style="font-size:12px;letter-spacing:.5px;">Employment & Salary Details</div></div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Position <span class="text-danger">*</span></label>
                            <select class="form-select" name="position_id" id="add_pos" required>
                                <option value="">-- Select Position --</option>
                                <?php foreach($positionList as $p) { ?>
                                <option value="<?= $p['position_id']; ?>">
                                    <?= htmlspecialchars($p['position_name']); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-6">
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

                        <!-- PORTAL CREDENTIALS -->
                        <div class="col-12 mt-3"><div class="fw-bold text-primary mb-1 uppercase" style="font-size:12px;letter-spacing:.5px;">Portal Account Credentials</div></div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Reset/Update Password</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="portal_password" id="edit_portal_password" placeholder="Leave blank to keep current">
                                <button class="btn btn-outline-secondary" type="button" onclick="regenerateEditPassword()">
                                    <i class="bi bi-arrow-clockwise"></i> Generate
                                </button>
                            </div>
                            <small class="text-muted">Fill this in to update/reset the employee's portal credentials and notify them via Gmail.</small>
                        </div>

                        <!-- EMPLOYMENT -->
                        <div class="col-12 mt-3"><div class="fw-bold text-primary mb-1 uppercase" style="font-size:12px;letter-spacing:.5px;">Employment & Salary Details</div></div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Position <span class="text-danger">*</span></label>
                            <select class="form-select" name="position_id" id="edit_pos" required>
                                <option value="">-- Select Position --</option>
                                <?php foreach($positionList as $p) { ?>
                                <option value="<?= $p['position_id']; ?>">
                                    <?= htmlspecialchars($p['position_name']); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Employment Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="employment_type" id="edit_emptype">
                                <option value="Full-time">Full-time</option>
                                <option value="Part-time">Part-time</option>
                                <option value="Contractual">Contractual</option>
                                <option value="Probationary">Probationary</option>
                            </select>
                        </div>
                        <div class="col-md-4">
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

   // Handle ADD form submission via AJAX
    $('#addForm').on('submit', function(e){
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
                if(!value) return 'Password is required to proceed.';
            }
        }).then((confirmResult) => {
            if(!confirmResult.isConfirmed) return;

            let formData = new FormData(formEl);
            formData.append('action', 'create');
            formData.append('password', confirmResult.value);

            $.ajax({
                url: 'hrms_employees.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response){
                    response = response.trim();
                    if(response.startsWith('success')){
                        let parts = response.split('|');
                        let icon = 'success';
                        let title = 'Employee Added!';
                        let text = '';
                        if(parts[1]){
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
                error: function(){
                    Swal.fire('Error', 'Server communication failure.', 'error');
                }
            });
        });
    });

    // Handle EDIT form submission via AJAX
    $('#editForm').on('submit', function(e){
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
                if(!value) return 'Password is required to proceed.';
            }
        }).then((confirmResult) => {
            if(!confirmResult.isConfirmed) return;

            let formData = new FormData(formEl);
            formData.append('action', 'update');
            formData.append('password', confirmResult.value);

            $.ajax({
                url: 'hrms_employees.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response){
                    response = response.trim();
                    if(response === 'success'){
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
    OPEN ADD MODAL
====================================================*/
function openAddModal(){
    $('#addForm')[0].reset();
    regenerateAddPassword(); // generate initial password
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
    $('#edit_portal_password').val(''); // blank password input by default

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
    // Step 1 — Reason
    Swal.fire({
        title: 'Delete ' + name + '?',
        html: `<p class="text-muted mb-2" style="font-size:13px;">This will archive the employee record before deletion.</p>
               <input id="delReason" class="swal2-input" placeholder="Reason e.g. Resigned, Terminated...">`,
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Next',
        preConfirm: () => {
            const r = document.getElementById('delReason').value.trim();
            if(!r){ Swal.showValidationMessage('Please provide a reason.'); return false; }
            return r;
        }
    }).then(reasonResult => {
        if(!reasonResult.isConfirmed) return;
        const reason = reasonResult.value;

        // Step 2 — Password
        Swal.fire({
            title: 'Confirm Your Password',
            html: `Enter your password to confirm deleting <strong>${name}</strong>.`,
            input: 'password',
            inputPlaceholder: 'Enter your password',
            inputAttributes: { autocapitalize: 'off', autocorrect: 'off' },
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, Delete Record',
            cancelButtonText: 'Cancel',
            inputValidator: (value) => {
                if(!value) return 'Password is required to proceed.';
            }
        }).then(result => {
            if(!result.isConfirmed) return;

            $.post('hrms_employees.php', {
                action:      'delete',
                employee_id: id,
                password:    result.value,
                reason:      reason
            }, function(response){
                response = response.trim();
                if(response === 'success'){
                    Swal.fire({
                        icon: 'success',
                        title: 'Archived & Deleted!',
                        text: name + ' has been moved to the archive.',
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
    $('#id_card_dept').text('SARI-SARI STORE');
    $('#id_card_emp_no').text(emp.employee_no);

    // Load QR Code library and generate the QR code
    loadQRCodeLib(function() {
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

    // Show ID Card Modal
    new bootstrap.Modal(document.getElementById('idCardModal')).show();
}

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

function openArchiveModal(){
    new bootstrap.Modal(document.getElementById('archiveModal')).show();
}
</script>