<?php
// Controller/HRMSController.php

class HRMSController {
    private $conn;
    const EMPLOYEE_UPLOAD_URL = 'uploads/employees/';

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Verifies the admin password
     */
    public function verifyAdminPassword($admin_id, $password) {
        if (empty($password)) return false;
        $admin_id = (int)$admin_id;
        $res = mysqli_query($this->conn, "SELECT password FROM users WHERE user_id = $admin_id LIMIT 1");
        $row = mysqli_fetch_assoc($res);
        if (!$row || empty($row['password'])) return false;
        return password_verify($password, $row['password']);
    }

    /**
     * Helper to get initials
     */
    public function getInitials($name) {
        $words = explode(" ", preg_replace('/[^a-zA-Z0-9\s]/', '', $name));
        $initials = "";
        foreach ($words as $w) {
            $initials .= strtoupper(substr($w, 0, 1));
            if (strlen($initials) >= 2) break;
        }
        return $initials ?: "?";
    }

    /**
     * Helper to process image uploads
     */
    private function handleImageUpload($file, $uploadDir, &$error) {
        $allowedExt  = ['jpg', 'jpeg', 'png', 'webp'];
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize     = 2 * 1024 * 1024;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Image upload failed. Please try again.';
            return false;
        }
        if ($file['size'] > $maxSize) {
            $error = 'Image must be smaller than 2MB.';
            return false;
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) {
            $error = 'Only JPG, PNG, or WEBP images are allowed.';
            return false;
        }
        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowedMime)) {
            $error = 'Invalid image file.';
            return false;
        }
        $newName = 'emp_' . uniqid() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
            $error = 'Could not save the uploaded image.';
            return false;
        }
        return $newName;
    }

    /**
     * PHPMailer: Welcome Email
     */
    private function sendWelcomeEmail($gmail, $name, $password) {
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

    /**
     * PHPMailer: Reset Password Email
     */
    private function sendPasswordResetEmail($gmail, $name, $password) {
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
            error_log("PHPMailer Password Reset Error: " . $mail->ErrorInfo);
            return 'ERR: ' . $mail->ErrorInfo;
        }
    }

    /**
     * PHPMailer: Contract Renewal Email
     */
    private function sendContractRenewalEmail($gmail, $name, $startDate, $endDate, $months, $salary) {
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
            $mail->Subject = 'Employment Contract Renewed - O-Cart!';
            $startFmt = date('F j, Y', strtotime($startDate));
            $endFmt = date('F j, Y', strtotime($endDate));
            $salFmt = number_format($salary, 2);

            $mail->Body = "
                <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px; max-width: 550px;'>
                    <h2 style='color: #1a3c5e; margin-top:0;'>O-Cart! HR Management</h2>
                    <p>Hello <strong>$name</strong>,</p>
                    <p>We are pleased to inform you that your employment contract with <strong>O-Cart! Store</strong> has been officially <strong>renewed</strong>!</p>
                    
                    <div style='background:#f0fdf4; border-left:4px solid #16a34a; padding:15px; margin:15px 0; border-radius:4px;'>
                        <strong style='color:#15803d;'>🎉 Renewal Contract Details:</strong><br>
                        <span style='font-size:13px; color:#334155; line-height:1.6;'>
                        • Renewal Duration: <strong>$months Months</strong><br>
                        • Effective Start Date: <strong>$startFmt</strong><br>
                        • New Expiry Date: <strong>$endFmt</strong><br>
                        • Basic Monthly Salary: <strong>₱$salFmt</strong><br>
                        • Contract Status: <strong>Signed &amp; Renewed</strong>
                        </span>
                    </div>

                    <p style='font-size:13px; color:#475569;'>Thank you for your continued dedication and hard work. You can view your updated profile and contract details in your employee portal.</p>
                    <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #888;'>This is an automated notification from O-Cart! HR System.</p>
                </div>
            ";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Contract Renewal Email Error: " . $mail->ErrorInfo);
            return 'ERR: ' . $mail->ErrorInfo;
        }
    }

    /**
     * Get quick stats for the HRMS dashboard
     */
    public function getDashboardStats() {
        $totalEmployees = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT COUNT(*) AS total FROM employees WHERE status='Active'"
        ))['total'] ?? 0;

        $totalApplicants = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT COUNT(*) AS total FROM applicants WHERE stage NOT IN ('Approved','Rejected')"
        ))['total'] ?? 0;

        $openJobs = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT COUNT(*) AS total FROM positions WHERE status='Open'"
        ))['total'] ?? 0;

        $pendingLeaves = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT COUNT(*) AS total FROM leave_requests WHERE status='Pending'"
        ))['total'] ?? 0;

        $todayPresent = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT COUNT(*) AS total FROM attendance WHERE date=CURDATE() AND status='Present'"
        ))['total'] ?? 0;

        $todayAbsent = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT COUNT(*) AS total FROM attendance WHERE date=CURDATE() AND status='Absent'"
        ))['total'] ?? 0;

        $draftPayroll = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT COUNT(*) AS total FROM payroll_periods WHERE status='Draft'"
        ))['total'] ?? 0;

        $totalDepartments = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT COUNT(*) AS total FROM departments"
        ))['total'] ?? 0;

        return [
            'totalEmployees' => $totalEmployees,
            'totalApplicants' => $totalApplicants,
            'openJobs' => $openJobs,
            'pendingLeaves' => $pendingLeaves,
            'todayPresent' => $todayPresent,
            'todayAbsent' => $todayAbsent,
            'draftPayroll' => $draftPayroll,
            'totalDepartments' => $totalDepartments
        ];
    }

    /**
     * Get employee counts broken down by department
     */
    public function getDepartmentBreakdown() {
        return mysqli_query($this->conn, "
            SELECT d.department_name, COUNT(e.employee_id) AS emp_count
            FROM departments d
            LEFT JOIN employees e ON d.department_id = e.department_id AND e.status = 'Active'
            GROUP BY d.department_id
            ORDER BY emp_count DESC
        ");
    }

    /**
     * Get recent active employees
     */
    public function getRecentEmployees($limit = 5) {
        $limit = (int)$limit;
        return mysqli_query($this->conn, "
            SELECT e.*, p.position_name, d.department_name
            FROM employees e
            LEFT JOIN positions p ON e.position_id = p.position_id
            LEFT JOIN departments d ON e.department_id = d.department_id
            WHERE e.status = 'Active'
            ORDER BY e.created_at DESC
            LIMIT $limit
        ");
    }

    /**
     * Get count of applicants per stage
     */
    public function getApplicantStageData() {
        $stages = ['Initial Screening', 'First Interview', 'Final Interview'];
        $stageData = [];
        foreach ($stages as $stage) {
            $s = mysqli_real_escape_string($this->conn, $stage);
            $stageData[] = mysqli_fetch_assoc(mysqli_query($this->conn,
                "SELECT COUNT(*) AS total FROM applicants WHERE stage='$s'"
            ))['total'] ?? 0;
        }
        return $stageData;
    }

    /**
     * Get list of employees joined with departments and positions
     */
    public function getEmployeesList() {
        return mysqli_query($this->conn, "
            SELECT e.*, p.position_name, d.department_name
            FROM employees e
            LEFT JOIN positions p ON e.position_id = p.position_id
            LEFT JOIN departments d ON e.department_id = d.department_id
            ORDER BY e.employee_no ASC
        ");
    }

    /**
     * Fetch positions catalog list
     */
    public function getPositionsList() {
        return mysqli_query($this->conn, "
            SELECT p.*
            FROM positions p
            ORDER BY p.position_name ASC
        ");
    }

    /**
     * Fetch departments catalog list
     */
    public function getDepartmentsList() {
        return mysqli_query($this->conn, "SELECT * FROM departments ORDER BY department_name ASC");
    }

    /**
     * Register a new hired employee record
     */
    public function createEmployee($postData, $fileData, $admin_id, $uploadDir) {
        if (!$this->verifyAdminPassword($admin_id, $postData['password'] ?? '')) {
            return 'error: Incorrect password. Employee was not added.';
        }

        $position_id = (int)$postData['position_id'];
        $full_name = mysqli_real_escape_string($this->conn, trim($postData['full_name']));
        $email = mysqli_real_escape_string($this->conn, trim($postData['email']));
        $phone = mysqli_real_escape_string($this->conn, trim($postData['phone']));
        $address = mysqli_real_escape_string($this->conn, trim($postData['address']));
        $birthdate = !empty($postData['birthdate']) ? mysqli_real_escape_string($this->conn, $postData['birthdate']) : NULL;
        $gender = mysqli_real_escape_string($this->conn, $postData['gender']);
        $civil_status = mysqli_real_escape_string($this->conn, $postData['civil_status']);
        $date_hired = !empty($postData['date_hired']) ? mysqli_real_escape_string($this->conn, $postData['date_hired']) : date('Y-m-d');
        $employment_type = mysqli_real_escape_string($this->conn, $postData['employment_type']);
        $basic_salary = (float)$postData['basic_salary'];
        $sss_no = mysqli_real_escape_string($this->conn, trim($postData['sss_no']));
        $philhealth_no = mysqli_real_escape_string($this->conn, trim($postData['philhealth_no']));
        $pagibig_no = mysqli_real_escape_string($this->conn, trim($postData['pagibig_no']));
        $tin_no = mysqli_real_escape_string($this->conn, trim($postData['tin_no']));
        $portal_password = isset($postData['portal_password']) ? trim($postData['portal_password']) : '';

        if (!empty($portal_password) && empty($email)) {
            return 'error: Email is required to generate a portal account.';
        }

        // Validate duplicates
        $checkEmail = mysqli_query($this->conn, "SELECT employee_id FROM employees WHERE email='$email' LIMIT 1");
        if ($email !== '' && mysqli_num_rows($checkEmail) > 0) {
            return 'error: Email is already registered.';
        }

        // Enforce position slots capacity
        $slotCheck = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT slots FROM positions WHERE position_id = $position_id LIMIT 1"));
        if (!$slotCheck) {
            return 'error: Selected position no longer exists.';
        }
        $totalSlots = (int)$slotCheck['slots'];
        $filledSlots = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT COUNT(*) AS cnt FROM employees WHERE position_id = $position_id AND status = 'Active'"))['cnt'];
        if ($filledSlots >= $totalSlots) {
            return "error: This position is already fully filled ($filledSlots/$totalSlots slots taken). Increase the slot count in Positions before adding another employee to this role.";
        }

        // Handle profile picture
        $photoName = '';
        if (isset($fileData['photo']) && $fileData['photo']['size'] > 0) {
            $uploadError = '';
            $photoName = $this->handleImageUpload($fileData['photo'], $uploadDir, $uploadError);
            if ($photoName === false) {
                return 'error: ' . $uploadError;
            }
        }
        $photo_val = $photoName !== '' ? "'$photoName'" : "NULL";

        // Generate Employee Number (EMP-YYYY-XXXX)
        $last = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT employee_no FROM employees ORDER BY employee_id DESC LIMIT 1"));
        $next_num = $last ? (intval(substr($last['employee_no'], 4)) + 1) : 1;
        $emp_no = 'EMP-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);

        // Generate hash password if portal is checked
        $pwdHash = 'NULL';
        if (!empty($portal_password)) {
            $pwdHash = "'" . mysqli_real_escape_string($this->conn, password_hash($portal_password, PASSWORD_BCRYPT)) . "'";
        }

        $contract_start = !empty($postData['contract_start']) ? mysqli_real_escape_string($this->conn, $postData['contract_start']) : $date_hired;
        $contract_end = !empty($postData['contract_end']) ? mysqli_real_escape_string($this->conn, $postData['contract_end']) : date('Y-m-d', strtotime('+6 months', strtotime($contract_start)));
        $contract_signed = isset($postData['contract_signed']) ? 1 : 1;

        // Use manually selected department_id if posted, else derive from position
        if (!empty($postData['department_id'])) {
            $department_id_val = (int)$postData['department_id'];
        } else {
            $deptRes = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT department_id FROM positions WHERE position_id = $position_id LIMIT 1"));
            $department_id_val = ($deptRes && $deptRes['department_id']) ? (int)$deptRes['department_id'] : "NULL";
        }

        $birthdate_val = $birthdate ? "'$birthdate'" : "NULL";

        mysqli_begin_transaction($this->conn);
        try {
            $q = mysqli_query($this->conn, "
                INSERT INTO employees (
                    position_id, department_id, employee_no, full_name, email, phone, address,
                    birthdate, gender, civil_status, date_hired, employment_type, basic_salary,
                    sss_no, philhealth_no, pagibig_no, tin_no, photo, status, password,
                    contract_start, contract_end, contract_signed, contract_signed_at
                ) VALUES (
                    $position_id, $department_id_val, '$emp_no', '$full_name', '$email', '$phone', '$address',
                    $birthdate_val, '$gender', '$civil_status', '$date_hired', '$employment_type', $basic_salary,
                    '$sss_no', '$philhealth_no', '$pagibig_no', '$tin_no', $photo_val, 'Active', $pwdHash,
                    '$contract_start', '$contract_end', $contract_signed, NOW()
                )
            ");

            if (!$q) {
                throw new Exception("Inserting employee record failed: " . mysqli_error($this->conn));
            }

            $new_id = mysqli_insert_id($this->conn);

            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $admin_id, 'Create', 'employees', $new_id, "Added employee: $full_name (#$emp_no)");

            // Process Portal Account Creation
            $mail_status = '';
            if (!empty($email) && !empty($portal_password)) {
                $hashed_password = password_hash($portal_password, PASSWORD_BCRYPT);
                $user_exists = mysqli_query($this->conn, "SELECT user_id FROM users WHERE gmail = '$email'");
                if (mysqli_num_rows($user_exists) == 0) {
                    mysqli_query($this->conn, "
                        INSERT INTO users (gmail, password, full_name, role, status)
                        VALUES ('$email', '$hashed_password', '$full_name', 'Cashier', 'Active')
                    ");
                    $sent = $this->sendWelcomeEmail($email, $full_name, $portal_password);
                    $mail_status = ($sent === true) ? '' : '|warning:Email failed - ' . $sent;
                } else {
                    mysqli_query($this->conn, "UPDATE users SET password = '$hashed_password' WHERE gmail = '$email'");
                    $sent = $this->sendPasswordResetEmail($email, $full_name, $portal_password);
                    $mail_status = $sent === true
                        ? '|notice:This Gmail already had a portal account, so its password was reset and emailed.'
                        : '|warning:This Gmail already had a portal account. Password was reset but the email failed to send.';
                }
            }

            mysqli_commit($this->conn);
            return 'success' . $mail_status;
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            if ($photoName !== '' && file_exists($uploadDir . $photoName)) {
                @unlink($uploadDir . $photoName);
            }
            return 'error: ' . $e->getMessage();
        }
    }

    /**
     * Update an employee profile
     */
    public function updateEmployee($postData, $fileData, $admin_id, $uploadDir) {
        if (!$this->verifyAdminPassword($admin_id, $postData['password'] ?? '')) {
            return 'error: Incorrect password. Changes were not saved.';
        }

        $id = (int)$postData['employee_id'];
        $position_id = (int)$postData['position_id'];
        $full_name = mysqli_real_escape_string($this->conn, trim($postData['full_name']));
        $email = mysqli_real_escape_string($this->conn, trim($postData['email']));
        $phone = mysqli_real_escape_string($this->conn, trim($postData['phone']));
        $address = mysqli_real_escape_string($this->conn, trim($postData['address']));
        $birthdate = !empty($postData['birthdate']) ? mysqli_real_escape_string($this->conn, $postData['birthdate']) : NULL;
        $gender = mysqli_real_escape_string($this->conn, $postData['gender']);
        $civil_status = mysqli_real_escape_string($this->conn, $postData['civil_status']);
        $date_hired = !empty($postData['date_hired']) ? mysqli_real_escape_string($this->conn, $postData['date_hired']) : date('Y-m-d');
        $employment_type = mysqli_real_escape_string($this->conn, $postData['employment_type']);
        $basic_salary = (float)$postData['basic_salary'];
        $sss_no = mysqli_real_escape_string($this->conn, trim($postData['sss_no']));
        $philhealth_no = mysqli_real_escape_string($this->conn, trim($postData['philhealth_no']));
        $pagibig_no = mysqli_real_escape_string($this->conn, trim($postData['pagibig_no']));
        $tin_no = mysqli_real_escape_string($this->conn, trim($postData['tin_no']));
        $status = mysqli_real_escape_string($this->conn, $postData['status']);
        $portal_password = isset($postData['portal_password']) ? trim($postData['portal_password']) : '';

        if (!empty($portal_password) && empty($email)) {
            return 'error: Email is required to generate or reset a portal account.';
        }

        // Fetch old employee data (email for portal sync, position for slot check)
        $old_emp = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT email, full_name, position_id, status FROM employees WHERE employee_id = $id"));
        $old_email = $old_emp ? $old_emp['email'] : '';

        // Enforce slot capacity only if the employee is being moved into a different position (or reactivated as Active)
        $movingPosition = $old_emp && ((int)$old_emp['position_id'] !== $position_id);
        $becomingActive = $status === 'Active' && $old_emp && $old_emp['status'] !== 'Active';
        if ($movingPosition || $becomingActive) {
            $slotCheck = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT slots FROM positions WHERE position_id = $position_id LIMIT 1"));
            if (!$slotCheck) {
                return 'error: Selected position no longer exists.';
            }
            $totalSlots = (int)$slotCheck['slots'];
            $filledSlots = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT COUNT(*) AS cnt FROM employees WHERE position_id = $position_id AND status = 'Active' AND employee_id != $id"))['cnt'];
            if ($filledSlots >= $totalSlots) {
                return "error: This position is already fully filled ($filledSlots/$totalSlots slots taken). Increase the slot count in Positions before assigning another employee to this role.";
            }
        }

        // Check if new photo uploaded
        $photo_query = "";
        if (isset($fileData['photo']) && $fileData['photo']['size'] > 0) {
            $uploadError = '';
            $uploaded = $this->handleImageUpload($fileData['photo'], $uploadDir, $uploadError);
            if (!$uploaded) {
                return 'error: ' . $uploadError;
            }
            $existingImage = $postData['existing_image'] ?? '';
            if ($existingImage !== '' && file_exists($uploadDir . $existingImage)) {
                @unlink($uploadDir . $existingImage);
            }
            $photo_query = ", photo = '$uploaded'";
        }

        $birthdate_val = $birthdate ? "'$birthdate'" : "NULL";

        $contract_start = !empty($postData['contract_start']) ? mysqli_real_escape_string($this->conn, $postData['contract_start']) : $date_hired;
        $contract_end = !empty($postData['contract_end']) ? mysqli_real_escape_string($this->conn, $postData['contract_end']) : date('Y-m-d', strtotime('+6 months', strtotime($contract_start)));
        $contract_signed = isset($postData['contract_signed']) ? 1 : 0;

        // Use manually selected department_id if posted, else derive from position
        if (!empty($postData['department_id'])) {
            $department_id_val = (int)$postData['department_id'];
        } else {
            $deptRes = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT department_id FROM positions WHERE position_id = $position_id LIMIT 1"));
            $department_id_val = ($deptRes && $deptRes['department_id']) ? (int)$deptRes['department_id'] : "NULL";
        }

        $q = mysqli_query($this->conn, "
            UPDATE employees SET
                position_id = $position_id,
                department_id = $department_id_val,
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
                status = '$status',
                contract_start = '$contract_start',
                contract_end = '$contract_end',
                contract_signed = $contract_signed
                $photo_query
            WHERE employee_id = $id
        ");

        if ($q) {
            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $admin_id, 'Update', 'employees', $id, "Updated details of employee: $full_name");

            // Sync with users table
            if (!empty($email)) {
                if (!empty($old_email) && $old_email !== $email) {
                    mysqli_query($this->conn, "UPDATE users SET gmail = '$email', full_name = '$full_name' WHERE gmail = '$old_email'");
                }

                if (!empty($portal_password)) {
                    $hashed_password = password_hash($portal_password, PASSWORD_BCRYPT);
                    mysqli_query($this->conn, "UPDATE employees SET password = '$hashed_password' WHERE employee_id = $id");

                    $user_exists = mysqli_query($this->conn, "SELECT user_id FROM users WHERE gmail = '$email'");
                    if (mysqli_num_rows($user_exists) > 0) {
                        mysqli_query($this->conn, "UPDATE users SET password = '$hashed_password', full_name = '$full_name' WHERE gmail = '$email'");
                        $this->sendPasswordResetEmail($email, $full_name, $portal_password);
                    } else {
                        mysqli_query($this->conn, "
                            INSERT INTO users (gmail, password, full_name, role, status)
                            VALUES ('$email', '$hashed_password', '$full_name', 'Cashier', 'Active')
                        ");
                        $this->sendWelcomeEmail($email, $full_name, $portal_password);
                    }
                } else {
                    mysqli_query($this->conn, "UPDATE users SET full_name = '$full_name' WHERE gmail = '$email'");
                }
            }
            return 'success';
        } else {
            return 'error: ' . mysqli_error($this->conn);
        }
    }

    /**
     * Terminate / Archive employee record
     */
    public function terminateEmployee($postData, $admin_id) {
        if (!$this->verifyAdminPassword($admin_id, $postData['password'] ?? '')) {
            return 'error: Incorrect password. Employee was not deleted.';
        }

        $id = (int)$postData['employee_id'];
        $reason = mysqli_real_escape_string($this->conn, trim($postData['reason'] ?? 'No reason provided'));

        // Fetch full employee info
        $emp = mysqli_fetch_assoc(mysqli_query($this->conn, "
            SELECT e.*, p.position_name, d.department_name
            FROM employees e
            LEFT JOIN positions p ON e.position_id = p.position_id
            LEFT JOIN departments d ON e.department_id = d.department_id
            WHERE e.employee_id = $id
        "));

        if (!$emp) {
            return 'error: Employee not found.';
        }

        // Archive the employee record first
        $full_name = mysqli_real_escape_string($this->conn, $emp['full_name']);
        $email = mysqli_real_escape_string($this->conn, $emp['email'] ?? '');
        $phone = mysqli_real_escape_string($this->conn, $emp['phone'] ?? '');
        $address = mysqli_real_escape_string($this->conn, $emp['address'] ?? '');
        $sss = mysqli_real_escape_string($this->conn, $emp['sss_no'] ?? '');
        $philhealth = mysqli_real_escape_string($this->conn, $emp['philhealth_no'] ?? '');
        $pagibig = mysqli_real_escape_string($this->conn, $emp['pagibig_no'] ?? '');
        $tin = mysqli_real_escape_string($this->conn, $emp['tin_no'] ?? '');
        $position_name = mysqli_real_escape_string($this->conn, $emp['position_name'] ?? '');
        $dept_name = mysqli_real_escape_string($this->conn, $emp['department_name'] ?? '');
        $emp_no = mysqli_real_escape_string($this->conn, $emp['employee_no']);
        $salary = (float)$emp['basic_salary'];
        $birthdate = $emp['birthdate'] ? "'{$emp['birthdate']}'" : 'NULL';
        $date_hired = $emp['date_hired'] ? "'{$emp['date_hired']}'" : 'NULL';
        $photo = mysqli_real_escape_string($this->conn, $emp['photo'] ?? '');

        mysqli_begin_transaction($this->conn);
        try {
            mysqli_query($this->conn, "
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
            mysqli_query($this->conn, "DELETE FROM payroll WHERE employee_id = $id");
            mysqli_query($this->conn, "DELETE FROM leave_requests WHERE employee_id = $id");
            mysqli_query($this->conn, "DELETE FROM attendance WHERE employee_id = $id");

            // Soft-delete linked user account (set status to Inactive) instead of hard delete
            if (!empty($emp['email'])) {
                $safeEmail = mysqli_real_escape_string($this->conn, $emp['email']);
                mysqli_query($this->conn, "UPDATE users SET role = 'Inactive' WHERE gmail = '$safeEmail'");
            }

            $q = mysqli_query($this->conn, "DELETE FROM employees WHERE employee_id = $id");

            if (!$q) {
                throw new Exception(mysqli_error($this->conn));
            }

            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $admin_id, 'Delete', 'employees', $id,
                "Archived & deleted employee: {$emp['full_name']} (#{$emp['employee_no']}) — Reason: $reason"
            );

            mysqli_commit($this->conn);
            return 'success';
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return 'error: ' . $e->getMessage();
        }
    }

    /**
     * Renew employee contract
     */
    public function renewContract($postData, $admin_id) {
        if (!$this->verifyAdminPassword($admin_id, $postData['password'] ?? '')) {
            return 'error: Incorrect password. Contract was not renewed.';
        }

        $id = (int)$postData['employee_id'];
        $duration = (int)($postData['duration_months'] ?? 6);
        $new_start = mysqli_real_escape_string($this->conn, trim($postData['contract_start']));
        $new_end = mysqli_real_escape_string($this->conn, trim($postData['contract_end']));
        $new_salary = (float)$postData['basic_salary'];
        $emp_type = mysqli_real_escape_string($this->conn, trim($postData['employment_type']));
        $notes = mysqli_real_escape_string($this->conn, trim($postData['notes'] ?? ''));

        $empRow = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT * FROM employees WHERE employee_id = $id LIMIT 1"));
        if (!$empRow) {
            return 'error: Employee not found.';
        }

        $old_end = $empRow['contract_end'];
        $old_renewal_count = (int)($empRow['renewal_count'] ?? 0);
        $new_renewal_count = $old_renewal_count + 1;

        mysqli_begin_transaction($this->conn);
        try {
            // Log in contract_renewals table
            mysqli_query($this->conn, "
                INSERT INTO contract_renewals 
                    (employee_id, old_contract_end, new_contract_start, new_contract_end, duration_months, renewed_by, notes)
                VALUES 
                    ($id, " . ($old_end ? "'$old_end'" : "NULL") . ", '$new_start', '$new_end', $duration, $admin_id, '$notes')
            ");

            // Update employee record
            mysqli_query($this->conn, "
                UPDATE employees SET
                    contract_start     = '$new_start',
                    contract_end       = '$new_end',
                    contract_signed    = 1,
                    contract_signed_at = NOW(),
                    renewal_count      = $new_renewal_count,
                    basic_salary       = $new_salary,
                    employment_type    = '$emp_type'
                WHERE employee_id = $id
            ");

            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $admin_id, 'Update', 'employees', $id,
                "Renewed contract for {$empRow['full_name']} (#{$empRow['employee_no']}) for $duration months ($new_start to $new_end). Renewal #$new_renewal_count."
            );

            if (!empty($empRow['email'])) {
                $this->sendContractRenewalEmail($empRow['email'], $empRow['full_name'], $new_start, $new_end, $duration, $new_salary);
            }

            mysqli_commit($this->conn);
            return 'success';
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return 'error: ' . $e->getMessage();
        }
    }

    /**
     * Change status toggles
     */
    public function changeEmployeeStatus($employee_id, $status, $admin_id) {
        $employee_id = (int)$employee_id;
        $status = mysqli_real_escape_string($this->conn, $status);

        $allowed = ['Active', 'Inactive', 'Resigned', 'Terminated'];
        if (!in_array($status, $allowed)) {
            return 'error: Invalid status';
        }

        $q = mysqli_query($this->conn, "UPDATE employees SET status='$status' WHERE employee_id=$employee_id");
        if ($q) {
            $name = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT full_name FROM employees WHERE employee_id=$employee_id"))['full_name'];
            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $admin_id, 'Status Change', 'employees', $employee_id, "Changed status of $name to: $status");
            return 'success';
        } else {
            return 'error: ' . mysqli_error($this->conn);
        }
    }

    /**
     * Restore employee from archive
     */
    public function restoreEmployeeFromArchive($archive_id, $admin_id) {
        $archive_id = (int)$archive_id;
        $arcRow = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT * FROM employees_archive WHERE archive_id = $archive_id LIMIT 1"));

        if (!$arcRow) {
            return 'error: Archived employee record not found.';
        }

        $posName = mysqli_real_escape_string($this->conn, $arcRow['position_name'] ?? '');
        $deptName = mysqli_real_escape_string($this->conn, $arcRow['department_name'] ?? '');

        // Match position_id
        $posRow = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT position_id, department_id FROM positions WHERE position_name = '$posName' LIMIT 1"));
        if (!$posRow) {
            $posRow = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT position_id, department_id FROM positions ORDER BY position_id ASC LIMIT 1"));
        }
        $position_id = $posRow ? (int)$posRow['position_id'] : 1;

        // Match department_id
        $deptRow = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT department_id FROM departments WHERE department_name = '$deptName' LIMIT 1"));
        $department_id = $deptRow ? (int)$deptRow['department_id'] : ($posRow && $posRow['department_id'] ? (int)$posRow['department_id'] : 1);

        // Check if employee_no already exists in active employees table
        $empNo = mysqli_real_escape_string($this->conn, $arcRow['employee_no']);
        $checkNo = mysqli_query($this->conn, "SELECT employee_id FROM employees WHERE employee_no = '$empNo'");
        if (mysqli_num_rows($checkNo) > 0) {
            $numRow = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT MAX(employee_id) AS max_id FROM employees"));
            $nextNum = ($numRow ? (int)$numRow['max_id'] : 0) + 1;
            $empNo = 'EMP-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        }

        $fullName = mysqli_real_escape_string($this->conn, $arcRow['full_name']);
        $email = mysqli_real_escape_string($this->conn, $arcRow['email']);
        $phone = mysqli_real_escape_string($this->conn, $arcRow['phone']);
        $address = mysqli_real_escape_string($this->conn, $arcRow['address']);
        $birthdate = $arcRow['birthdate'] ? "'{$arcRow['birthdate']}'" : 'NULL';
        $gender = mysqli_real_escape_string($this->conn, $arcRow['gender'] ?? 'Female');
        $civil = mysqli_real_escape_string($this->conn, $arcRow['civil_status'] ?? 'Single');
        $dateHired = $arcRow['date_hired'] ? "'{$arcRow['date_hired']}'" : "'" . date('Y-m-d') . "'";
        $empType = mysqli_real_escape_string($this->conn, $arcRow['employment_type'] ?? 'Full-time');
        $basicSalary = (float)($arcRow['basic_salary'] ?? 0);
        $photo = mysqli_real_escape_string($this->conn, $arcRow['photo'] ?? '');
        $sss = mysqli_real_escape_string($this->conn, $arcRow['sss_no'] ?? '');
        $philhealth = mysqli_real_escape_string($this->conn, $arcRow['philhealth_no'] ?? '');
        $pagibig = mysqli_real_escape_string($this->conn, $arcRow['pagibig_no'] ?? '');
        $tin = mysqli_real_escape_string($this->conn, $arcRow['tin_no'] ?? '');
        $contractStart = date('Y-m-d');
        $contractEnd = date('Y-m-d', strtotime('+6 months'));

        mysqli_begin_transaction($this->conn);
        try {
            $insertQ = mysqli_query($this->conn, "
                INSERT INTO employees (
                    employee_no, position_id, department_id, full_name, email, phone, address,
                    birthdate, gender, civil_status, date_hired, employment_type, basic_salary,
                    status, photo, sss_no, philhealth_no, pagibig_no, tin_no,
                    contract_start, contract_end, contract_signed
                ) VALUES (
                    '$empNo', $position_id, $department_id, '$fullName', '$email', '$phone', '$address',
                    $birthdate, '$gender', '$civil', $dateHired, '$empType', $basicSalary,
                    'Active', '$photo', '$sss', '$philhealth', '$pagibig', '$tin',
                    '$contractStart', '$contractEnd', 1
                )
            ");

            if (!$insertQ) {
                throw new Exception(mysqli_error($this->conn));
            }

            $new_emp_id = mysqli_insert_id($this->conn);
            mysqli_query($this->conn, "DELETE FROM employees_archive WHERE archive_id = $archive_id");
            if (!empty($email)) {
                $safeEmail = mysqli_real_escape_string($this->conn, $email);
                mysqli_query($this->conn, "UPDATE users SET role = 'Cashier', status = 'Active' WHERE gmail = '$safeEmail'");
            }

            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $admin_id, 'Restore', 'employees', $new_emp_id, "Restored employee $fullName (#$empNo) from archive");

            mysqli_commit($this->conn);
            return 'success';
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return 'error: ' . $e->getMessage();
        }
    }

    /**
     * Get contract renewal logs
     */
    public function getContractRenewalHistory($employee_id) {
        $employee_id = (int)$employee_id;
        return mysqli_query($this->conn, "
            SELECT r.*, u.full_name AS renewed_by_name
            FROM contract_renewals r
            LEFT JOIN users u ON r.renewed_by = u.user_id
            WHERE r.employee_id = $employee_id
            ORDER BY r.renewed_at DESC
        ");
    }

    /**
     * Calculate hours worked and overtime
     */
    private function calcHours($time_in, $time_out) {
        if (!$time_in || !$time_out) return [0, 0];
        $in  = new DateTime($time_in);
        $out = new DateTime($time_out);
        if ($out < $in) return [0, 0];
        $diff = $in->diff($out);
        $hours = round($diff->h + ($diff->i / 60), 2);
        $overtime = $hours > 8 ? round($hours - 8, 2) : 0;
        return [$hours, $overtime];
    }

    /**
     * Get list of all attendance records
     */
    public function getAttendanceLogsList() {
        return mysqli_query($this->conn, "
            SELECT a.*, e.employee_no, e.full_name, e.photo AS emp_photo
            FROM attendance a
            JOIN employees e ON a.employee_id = e.employee_id
            ORDER BY a.date DESC, a.time_in DESC
        ");
    }

    /**
     * Archive an attendance log record
     */
    public function archiveAttendance($attendance_id, $reason, $admin_id) {
        $attendance_id = (int)$attendance_id;
        $reason = mysqli_real_escape_string($this->conn, trim($reason));

        $att = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT * FROM attendance WHERE attendance_id = $attendance_id"));
        if ($att) {
            $ti = $att['time_in'] ? "'{$att['time_in']}'" : 'NULL';
            $to = $att['time_out'] ? "'{$att['time_out']}'" : 'NULL';
            $photo = $att['photo'] ? "'{$att['photo']}'" : 'NULL';
            $notes = mysqli_real_escape_string($this->conn, $att['notes'] ?? '');
            $status = mysqli_real_escape_string($this->conn, $att['status'] ?? 'Present');

            mysqli_begin_transaction($this->conn);
            try {
                $ins = mysqli_query($this->conn, "
                    INSERT INTO attendance_archive (attendance_id, employee_id, date, time_in, time_out, hours_worked, overtime_hours, status, notes, photo, archive_reason)
                    VALUES ({$att['attendance_id']}, {$att['employee_id']}, '{$att['date']}', $ti, $to, {$att['hours_worked']}, {$att['overtime_hours']}, '$status', '$notes', $photo, '$reason')
                ");
                if (!$ins) {
                    throw new Exception("Inserting to archive table failed: " . mysqli_error($this->conn));
                }
                mysqli_query($this->conn, "DELETE FROM attendance WHERE attendance_id = $attendance_id");
                
                require_once __DIR__ . '/../Model/logger.php';
                logAction($this->conn, $admin_id, 'Archive', 'attendance', $attendance_id, "Archived attendance log #$attendance_id. Reason: $reason");

                mysqli_commit($this->conn);
                return 'success';
            } catch (Exception $e) {
                mysqli_rollback($this->conn);
                return 'error: ' . $e->getMessage();
            }
        }
        return 'error: Record not found.';
    }

    /**
     * Restore archived attendance log record
     */
    public function restoreAttendance($archive_id, $admin_id) {
        $archive_id = (int)$archive_id;
        $arch = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT * FROM attendance_archive WHERE archive_id = $archive_id"));
        if ($arch) {
            $ti = $arch['time_in'] ? "'{$arch['time_in']}'" : 'NULL';
            $to = $arch['time_out'] ? "'{$arch['time_out']}'" : 'NULL';
            $photo = $arch['photo'] ? "'{$arch['photo']}'" : 'NULL';
            $notes = mysqli_real_escape_string($this->conn, $arch['notes'] ?? '');
            $status = mysqli_real_escape_string($this->conn, $arch['status'] ?? 'Present');

            mysqli_begin_transaction($this->conn);
            try {
                $ins = mysqli_query($this->conn, "
                    INSERT INTO attendance (employee_id, date, time_in, time_out, hours_worked, overtime_hours, status, notes, photo)
                    VALUES ({$arch['employee_id']}, '{$arch['date']}', $ti, $to, {$arch['hours_worked']}, {$arch['overtime_hours']}, '$status', '$notes', $photo)
                ");
                if (!$ins) {
                    throw new Exception("Restoring to active table failed: " . mysqli_error($this->conn));
                }
                mysqli_query($this->conn, "DELETE FROM attendance_archive WHERE archive_id = $archive_id");
                
                require_once __DIR__ . '/../Model/logger.php';
                logAction($this->conn, $admin_id, 'Restore', 'attendance', $arch['attendance_id'], "Restored attendance log #{$arch['attendance_id']} from archive");

                mysqli_commit($this->conn);
                return 'success';
            } catch (Exception $e) {
                mysqli_rollback($this->conn);
                return 'error: ' . $e->getMessage();
            }
        }
        return 'error: Record not found.';
    }

    /**
     * Reject an attendance log request/entry
     */
    public function rejectAttendance($attendance_id, $reason, $admin_id) {
        $attendance_id = (int)$attendance_id;
        $reason = mysqli_real_escape_string($this->conn, trim($reason));

        $q = mysqli_query($this->conn, "
            UPDATE attendance SET status = 'Rejected', notes = '$reason' WHERE attendance_id = $attendance_id
        ");
        if ($q) {
            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $admin_id, 'Reject', 'attendance', $attendance_id, "Rejected attendance log #$attendance_id. Reason: $reason");
            return 'success';
        }
        return 'error: Failed to reject record.';
    }

    /**
     * Create manual attendance record
     */
    public function createAttendanceManual($postData, $admin_id) {
        if (!$this->verifyAdminPassword($admin_id, $postData['password'] ?? '')) {
            return 'error: Incorrect password.';
        }
        $employee_id = (int)$postData['employee_id'];
        $date        = mysqli_real_escape_string($this->conn, $postData['date']);
        $time_in     = !empty($postData['time_in'])  ? mysqli_real_escape_string($this->conn, $postData['time_in'])  : 'NULL';
        $time_out    = !empty($postData['time_out']) ? mysqli_real_escape_string($this->conn, $postData['time_out']) : 'NULL';
        $status      = mysqli_real_escape_string($this->conn, $postData['status']);
        $notes       = mysqli_real_escape_string($this->conn, trim($postData['notes'] ?? ''));

        [$hours, $ot] = $this->calcHours($postData['time_in'] ?? null, $postData['time_out'] ?? null);

        $ti_val = $time_in  !== 'NULL' ? "'$time_in'"  : 'NULL';
        $to_val = $time_out !== 'NULL' ? "'$time_out'" : 'NULL';

        $q = mysqli_query($this->conn, "
            INSERT INTO attendance (employee_id, date, time_in, time_out, hours_worked, overtime_hours, status, notes)
            VALUES ($employee_id, '$date', $ti_val, $to_val, $hours, $ot, '$status', '$notes')
        ");
        if ($q) {
            $new_id = mysqli_insert_id($this->conn);
            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $admin_id, 'Create', 'attendance', $new_id, "Manual attendance entry for employee ID $employee_id on $date");
            return 'success';
        } else {
            return 'error: ' . mysqli_error($this->conn);
        }
    }

    /**
     * Update attendance record details
     */
    public function updateAttendanceManual($postData, $admin_id) {
        if (!$this->verifyAdminPassword($admin_id, $postData['password'] ?? '')) {
            return 'error: Incorrect password.';
        }
        $id       = (int)$postData['attendance_id'];
        $date     = mysqli_real_escape_string($this->conn, $postData['date']);
        $time_in  = !empty($postData['time_in'])  ? mysqli_real_escape_string($this->conn, $postData['time_in'])  : null;
        $time_out = !empty($postData['time_out']) ? mysqli_real_escape_string($this->conn, $postData['time_out']) : null;
        $status   = mysqli_real_escape_string($this->conn, $postData['status']);
        $notes    = mysqli_real_escape_string($this->conn, trim($postData['notes'] ?? ''));

        [$hours, $ot] = $this->calcHours($time_in, $time_out);
        $ti_val = $time_in  ? "'$time_in'"  : 'NULL';
        $to_val = $time_out ? "'$time_out'" : 'NULL';

        $q = mysqli_query($this->conn, "
            UPDATE attendance SET
                date = '$date',
                time_in = $ti_val,
                time_out = $to_val,
                hours_worked = $hours,
                overtime_hours = $ot,
                status = '$status',
                notes = '$notes'
            WHERE attendance_id = $id
        ");
        if ($q) {
            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $admin_id, 'Update', 'attendance', $id, "Updated attendance log #$id");
            return 'success';
        } else {
            return 'error: ' . mysqli_error($this->conn);
        }
    }

    /**
     * Delete an attendance record
     */
    public function deleteAttendance($attendance_id, $admin_id, $password) {
        if (!$this->verifyAdminPassword($admin_id, $password)) {
            return 'error: Incorrect password.';
        }
        $attendance_id = (int)$attendance_id;
        $q  = mysqli_query($this->conn, "DELETE FROM attendance WHERE attendance_id = $attendance_id");
        if ($q) {
            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $admin_id, 'Delete', 'attendance', $attendance_id, "Deleted attendance log #$attendance_id");
            return 'success';
        } else {
            return 'error: ' . mysqli_error($this->conn);
        }
    }

    /**
     * Send leave status notification email
     */
    private function sendLeaveStatusEmail($gmail, $name, $leaveType, $dateFrom, $dateTo, $days, $status) {
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

            $fromFmt = date('F j, Y', strtotime($dateFrom));
            $toFmt   = date('F j, Y', strtotime($dateTo));

            switch($status){
                case 'Pending':
                    $mail->Subject = 'Leave Request Received - O-Cart!';
                    $intro = "This is to confirm your <strong>$leaveType</strong> request has been filed and is now <strong>pending approval</strong>.";
                    $color = '#b45309';
                    break;
                case 'Approved':
                    $mail->Subject = 'Leave Request Approved - O-Cart!';
                    $intro = "Good news! Your <strong>$leaveType</strong> request has been <strong>approved</strong>.";
                    $color = '#15803d';
                    break;
                case 'Rejected':
                    $mail->Subject = 'Leave Request Rejected - O-Cart!';
                    $intro = "We regret to inform you that your <strong>$leaveType</strong> request has been <strong>rejected</strong>. Please reach out to HR for details.";
                    $color = '#b91c1c';
                    break;
                default:
                    $mail->Subject = 'Leave Request Update - O-Cart!';
                    $intro = "There's an update on your <strong>$leaveType</strong> request: <strong>$status</strong>.";
                    $color = '#1a3c5e';
            }

            $mail->Body = "
                <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px; max-width: 500px;'>
                    <h2 style='color: #1a3c5e;'>O-Cart! Leave Management</h2>
                    <p>Hello <strong>$name</strong>,</p>
                    <p>$intro</p>
                    <table style='width: 100%; border-collapse: collapse; margin-top:15px;'>
                        <tr>
                            <td style='padding: 5px 0; color: #666;'>Dates:</td>
                            <td><strong>$fromFmt &ndash; $toFmt</strong> ($days day(s))</td>
                        </tr>
                        <tr>
                            <td style='padding: 5px 0; color: #666;'>Status:</td>
                            <td><strong style='color:$color;'>$status</strong></td>
                        </tr>
                    </table>
                    <p style='margin-top: 25px; font-size: 12px; color: #888;'>If you have any questions, please contact HR.</p>
                </div>
            ";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Leave Status Email Error: " . $mail->ErrorInfo);
            return 'ERR: ' . $mail->ErrorInfo;
        }
    }

    /**
     * Get list of leave requests
     */
    public function getLeaveRequestsList() {
        return mysqli_query($this->conn, "
            SELECT lr.*, e.full_name, e.employee_no,
                   u.full_name AS approved_by_name
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.employee_id
            LEFT JOIN users u ON lr.approved_by = u.user_id
            ORDER BY lr.created_at DESC
        ");
    }

    /**
     * Archive leave request
     */
    public function archiveLeave($leave_id, $reason, $admin_id) {
        $leave_id = (int)$leave_id;
        $reason = mysqli_real_escape_string($this->conn, trim($reason));

        $lr = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT * FROM leave_requests WHERE leave_id = $leave_id"));
        if ($lr) {
            $doc = $lr['document'] ? "'{$lr['document']}'" : 'NULL';
            $reason_esc = mysqli_real_escape_string($this->conn, $lr['reason'] ?? '');
            $remarks_esc = mysqli_real_escape_string($this->conn, $lr['remarks'] ?? '');

            mysqli_begin_transaction($this->conn);
            try {
                $ins = mysqli_query($this->conn, "
                    INSERT INTO leave_requests_archive (leave_id, employee_id, leave_type, date_from, date_to, days, reason, remarks, document, status, archive_reason)
                    VALUES ({$lr['leave_id']}, {$lr['employee_id']}, '{$lr['leave_type']}', '{$lr['date_from']}', '{$lr['date_to']}', {$lr['days']}, '$reason_esc', '$remarks_esc', $doc, '{$lr['status']}', '$reason')
                ");
                if (!$ins) {
                    throw new Exception("Archive insert failed: " . mysqli_error($this->conn));
                }
                mysqli_query($this->conn, "DELETE FROM leave_requests WHERE leave_id = $leave_id");
                
                require_once __DIR__ . '/../Model/logger.php';
                logAction($this->conn, $admin_id, 'Archive', 'leave_requests', $leave_id, "Archived leave request #$leave_id. Reason: $reason");

                mysqli_commit($this->conn);
                return 'success';
            } catch (Exception $e) {
                mysqli_rollback($this->conn);
                return 'error: ' . $e->getMessage();
            }
        }
        return 'error: Failed to archive leave record.';
    }

    /**
     * Restore archived leave request
     */
    public function restoreLeave($archive_id, $admin_id) {
        $archive_id = (int)$archive_id;
        $arch = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT * FROM leave_requests_archive WHERE archive_id = $archive_id"));
        if ($arch) {
            $doc = $arch['document'] ? "'{$arch['document']}'" : 'NULL';
            $reason_esc = mysqli_real_escape_string($this->conn, $arch['reason'] ?? '');
            $remarks_esc = mysqli_real_escape_string($this->conn, $arch['remarks'] ?? '');

            mysqli_begin_transaction($this->conn);
            try {
                $ins = mysqli_query($this->conn, "
                    INSERT INTO leave_requests (employee_id, leave_type, date_from, date_to, days, reason, remarks, document, status, approved_by)
                    VALUES ({$arch['employee_id']}, '{$arch['leave_type']}', '{$arch['date_from']}', '{$arch['date_to']}', {$arch['days']}, '$reason_esc', '$remarks_esc', $doc, '{$arch['status']}', $admin_id)
                ");
                if (!$ins) {
                    throw new Exception("Restore insert failed: " . mysqli_error($this->conn));
                }
                mysqli_query($this->conn, "DELETE FROM leave_requests_archive WHERE archive_id = $archive_id");
                
                require_once __DIR__ . '/../Model/logger.php';
                logAction($this->conn, $admin_id, 'Restore', 'leave_requests', $arch['leave_id'], "Restored leave request #{$arch['leave_id']} from archive");

                mysqli_commit($this->conn);
                return 'success';
            } catch (Exception $e) {
                mysqli_rollback($this->conn);
                return 'error: ' . $e->getMessage();
            }
        }
        return 'error: Failed to restore leave record.';
    }

    /**
     * File a new leave request
     */
    public function createLeave($postData, $fileData, $admin_id, $uploadDir) {
        $employee_id = (int)$postData['employee_id'];
        $leave_type  = mysqli_real_escape_string($this->conn, $postData['leave_type']);
        $date_from   = mysqli_real_escape_string($this->conn, $postData['date_from']);
        $date_to     = mysqli_real_escape_string($this->conn, $postData['date_to']);
        $reason      = mysqli_real_escape_string($this->conn, trim($postData['reason']));
        $status      = mysqli_real_escape_string($this->conn, $postData['status'] ?? 'Pending');

        $d1   = new DateTime($date_from);
        $d2   = new DateTime($date_to);
        $days = (int)$d1->diff($d2)->days + 1;

        $document = '';
        if (isset($fileData['document']) && $fileData['document']['error'] === 0) {
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
            $fileType = mime_content_type($fileData['document']['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) {
                return 'error: Supporting document must be a PDF, JPG, or PNG file.';
            }
            if ($fileData['document']['size'] > 5 * 1024 * 1024) {
                return 'error: Supporting document must not exceed 5MB.';
            }
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileData['document']['name']);
            move_uploaded_file($fileData['document']['tmp_name'], $uploadDir . $fileName);
            $document = mysqli_real_escape_string($this->conn, $fileName);
        }

        $approvedBy = ($status !== 'Pending') ? $admin_id : 'NULL';
        
        $q = mysqli_query($this->conn, "
            INSERT INTO leave_requests (employee_id, leave_type, date_from, date_to, days, reason, document, status, approved_by)
            VALUES ($employee_id, '$leave_type', '$date_from', '$date_to', $days, '$reason', '$document', '$status', $approvedBy)
        ");

        if ($q) {
            $new_id = mysqli_insert_id($this->conn);
            $emp = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT full_name, email FROM employees WHERE employee_id=$employee_id"));
            
            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $admin_id, 'Create', 'leave_requests', $new_id,
                "Filed $leave_type for {$emp['full_name']} ($date_from to $date_to, $days day(s)) — Status: $status"
            );

            if (!empty($emp['email'])) {
                $this->sendLeaveStatusEmail($emp['email'], $emp['full_name'], $leave_type, $date_from, $date_to, $days, $status);
            }
            return 'success:' . $new_id;
        } else {
            return 'error: ' . mysqli_error($this->conn);
        }
    }

    /**
     * Update leave request status (Approve / Reject)
     */
    public function updateLeaveStatus($leave_id, $status, $remarks, $admin_id) {
        $leave_id    = (int)$leave_id;
        $status      = mysqli_real_escape_string($this->conn, $status);
        $remarks     = mysqli_real_escape_string($this->conn, trim($remarks));
        $approvedBy  = ($status !== 'Pending') ? $admin_id : 'NULL';

        $q = mysqli_query($this->conn, "
            UPDATE leave_requests SET status='$status', remarks='$remarks', approved_by=$approvedBy WHERE leave_id=$leave_id
        ");

        if ($q) {
            $row = mysqli_fetch_assoc(mysqli_query($this->conn, "
                SELECT lr.*, e.full_name, e.email FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.employee_id
                WHERE lr.leave_id = $leave_id
            "));

            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $admin_id, 'Update', 'leave_requests', $leave_id,
                "Leave #{$leave_id} for {$row['full_name']} ({$row['leave_type']}) marked as $status"
            );

            if (!empty($row['email'])) {
                $this->sendLeaveStatusEmail($row['email'], $row['full_name'], $row['leave_type'],
                    $row['date_from'], $row['date_to'], $row['days'], $status);
            }
            return 'success';
        } else {
            return 'error: ' . mysqli_error($this->conn);
        }
    }

    /**
     * Delete leave request record
     */
    public function deleteLeave($leave_id, $admin_id) {
        $leave_id = (int)$leave_id;
        $row = mysqli_fetch_assoc(mysqli_query($this->conn, "
            SELECT lr.*, e.full_name FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.employee_id
            WHERE lr.leave_id = $leave_id
        "));

        $q = mysqli_query($this->conn, "DELETE FROM leave_requests WHERE leave_id=$leave_id");
        if ($q) {
            if ($row) {
                require_once __DIR__ . '/../Model/logger.php';
                logAction($this->conn, $admin_id, 'Delete', 'leave_requests', $leave_id,
                    "Deleted leave #{$leave_id} ({$row['leave_type']}) for {$row['full_name']}"
                );
            }
            return 'success';
        } else {
            return 'error: ' . mysqli_error($this->conn);
        }
    }

    /**
     * Edit leave request details
     */
    public function updateLeave($postData, $fileData, $admin_id, $uploadDir) {
        $leave_id    = (int)$postData['leave_id'];
        $employee_id = (int)$postData['employee_id'];
        $leave_type  = mysqli_real_escape_string($this->conn, $postData['leave_type']);
        $date_from   = mysqli_real_escape_string($this->conn, $postData['date_from']);
        $date_to     = mysqli_real_escape_string($this->conn, $postData['date_to']);
        $reason      = mysqli_real_escape_string($this->conn, trim($postData['reason']));
        $status      = mysqli_real_escape_string($this->conn, $postData['status']);
        
        $d1   = new DateTime($date_from);
        $d2   = new DateTime($date_to);
        $days = (int)$d1->diff($d2)->days + 1;
        $approvedBy = ($status !== 'Pending') ? $admin_id : 'NULL';

        $prevRow = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT status FROM leave_requests WHERE leave_id=$leave_id"));
        $prevStatus = $prevRow['status'] ?? null;

        $documentSql = '';
        if (isset($fileData['document']) && $fileData['document']['error'] === 0) {
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
            $fileType = mime_content_type($fileData['document']['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) {
                return 'error: Supporting document must be a PDF, JPG, or PNG file.';
            }
            if ($fileData['document']['size'] > 5 * 1024 * 1024) {
                return 'error: Supporting document must not exceed 5MB.';
            }
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileData['document']['name']);
            move_uploaded_file($fileData['document']['tmp_name'], $uploadDir . $fileName);
            $document = mysqli_real_escape_string($this->conn, $fileName);
            $documentSql = ", document='$document'";
        }

        $q = mysqli_query($this->conn, "
            UPDATE leave_requests
            SET employee_id=$employee_id, leave_type='$leave_type',
                date_from='$date_from', date_to='$date_to', days=$days,
                reason='$reason', status='$status', approved_by=$approvedBy
                $documentSql
            WHERE leave_id=$leave_id
        ");

        if ($q) {
            if ($prevStatus !== null && $prevStatus !== $status) {
                $emp = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT full_name, email FROM employees WHERE employee_id=$employee_id"));
                if (!empty($emp['email'])) {
                    $this->sendLeaveStatusEmail($emp['email'], $emp['full_name'], $leave_type, $date_from, $date_to, $days, $status);
                }
            }
            return 'success';
        } else {
            return 'error: ' . mysqli_error($this->conn);
        }
    }

    /**
     * Fetch positions detailed list including filled count
     */
    public function getPositionsDetailedList() {
        return mysqli_query($this->conn, "
            SELECT p.*, d.department_name,
                   (SELECT COUNT(*) FROM employees e WHERE e.position_id = p.position_id AND e.status = 'Active') AS filled_slots
            FROM positions p
            LEFT JOIN departments d ON p.department_id = d.department_id
            ORDER BY p.position_name ASC
        ");
    }

    /**
     * Get position details
     */
    public function getPositionDetails($position_id) {
        $position_id = (int)$position_id;
        return mysqli_fetch_assoc(mysqli_query($this->conn, "
            SELECT p.*, d.department_name
            FROM positions p
            LEFT JOIN departments d ON p.department_id = d.department_id
            WHERE p.position_id = $position_id
        "));
    }

    /**
     * Create a new job position slot
     */
    public function createPosition($postData, $admin_id) {
        $position_name   = mysqli_real_escape_string($this->conn, trim($postData['position_name']));
        $department_id   = !empty($postData['department_id']) ? (int)$postData['department_id'] : "NULL";
        $employment_type = mysqli_real_escape_string($this->conn, $postData['employment_type']);
        $slots           = (int)$postData['slots'];
        $salary_min      = (float)$postData['salary_min'];
        $salary_max      = (float)$postData['salary_max'];
        $requirements    = mysqli_real_escape_string($this->conn, trim($postData['requirements'] ?? ''));
        $status          = mysqli_real_escape_string($this->conn, $postData['status'] ?? 'Open');

        if (empty($position_name) || empty($employment_type)) {
            return 'error: Please fill in all required fields.';
        }

        $q = mysqli_query($this->conn, "
            INSERT INTO positions (department_id, position_name, employment_type, slots, salary_min, salary_max, requirements, status)
            VALUES ($department_id, '$position_name', '$employment_type', $slots, $salary_min, $salary_max, '$requirements', '$status')
        ");

        if ($q) {
            $new_id = mysqli_insert_id($this->conn);
            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $admin_id, 'Create', 'positions', $new_id, "Created new position: $position_name ($employment_type)");
            return 'success';
        } else {
            return 'error: ' . mysqli_error($this->conn);
        }
    }

    /**
     * Update position details
     */
    public function updatePosition($postData, $admin_id) {
        $position_id     = (int)$postData['position_id'];
        $position_name   = mysqli_real_escape_string($this->conn, trim($postData['position_name']));
        $department_id   = !empty($postData['department_id']) ? (int)$postData['department_id'] : "NULL";
        $employment_type = mysqli_real_escape_string($this->conn, $postData['employment_type']);
        $slots           = (int)$postData['slots'];
        $salary_min      = (float)$postData['salary_min'];
        $salary_max      = (float)$postData['salary_max'];
        $requirements    = mysqli_real_escape_string($this->conn, trim($postData['requirements'] ?? ''));
        $status          = mysqli_real_escape_string($this->conn, $postData['status']);

        if ($position_id <= 0 || empty($position_name) || empty($employment_type)) {
            return 'error: Please fill in all required fields.';
        }

        $q = mysqli_query($this->conn, "
            UPDATE positions SET
                position_name='$position_name',
                department_id=$department_id,
                employment_type='$employment_type',
                slots=$slots,
                salary_min=$salary_min,
                salary_max=$salary_max,
                requirements='$requirements',
                status='$status'
            WHERE position_id=$position_id
        ");

        if ($q) {
            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $admin_id, 'Update', 'positions', $position_id, "Updated position: $position_name ($employment_type)");
            return 'success';
        } else {
            return 'error: ' . mysqli_error($this->conn);
        }
    }

    /**
     * Delete position slots
     */
    public function deletePosition($position_id, $admin_id) {
        $position_id = (int)$position_id;

        if ($position_id <= 0) {
            return 'error: Invalid Position ID.';
        }

        // Check if position is assigned to any active employee
        $check_emp = mysqli_query($this->conn, "SELECT employee_id FROM employees WHERE position_id=$position_id LIMIT 1");
        if (mysqli_num_rows($check_emp) > 0) {
            return 'error: Cannot delete this position because it is currently assigned to one or more employees.';
        }

        $pos_info = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT position_name FROM positions WHERE position_id=$position_id"));
        $position_name = $pos_info ? $pos_info['position_name'] : 'Unknown';

        $q = mysqli_query($this->conn, "DELETE FROM positions WHERE position_id=$position_id");

        if ($q) {
            require_once __DIR__ . '/../Model/logger.php';
            logAction($this->conn, $admin_id, 'Delete', 'positions', $position_id, "Deleted position: $position_name");
            return 'success';
        } else {
            return 'error: ' . mysqli_error($this->conn);
        }
    }

    // =====================================================
    // PAYROLL METHODS
    // =====================================================

    private function computeSSS($monthly_salary) {
        if($monthly_salary < 4250)  return 180.00;
        if($monthly_salary < 4750)  return 202.50;
        if($monthly_salary < 5250)  return 225.00;
        if($monthly_salary < 5750)  return 247.50;
        if($monthly_salary < 6250)  return 270.00;
        if($monthly_salary < 6750)  return 292.50;
        if($monthly_salary < 7250)  return 315.00;
        if($monthly_salary < 7750)  return 337.50;
        if($monthly_salary < 8250)  return 360.00;
        if($monthly_salary < 8750)  return 382.50;
        if($monthly_salary < 9250)  return 405.00;
        if($monthly_salary < 9750)  return 427.50;
        if($monthly_salary < 10250) return 450.00;
        if($monthly_salary < 10750) return 472.50;
        if($monthly_salary < 11250) return 495.00;
        if($monthly_salary < 11750) return 517.50;
        if($monthly_salary < 12250) return 540.00;
        if($monthly_salary < 12750) return 562.50;
        if($monthly_salary < 13250) return 585.00;
        if($monthly_salary < 13750) return 607.50;
        if($monthly_salary < 14250) return 630.00;
        if($monthly_salary < 14750) return 652.50;
        if($monthly_salary < 15250) return 675.00;
        if($monthly_salary < 15750) return 697.50;
        if($monthly_salary < 16250) return 720.00;
        if($monthly_salary < 16750) return 742.50;
        if($monthly_salary < 17250) return 765.00;
        if($monthly_salary < 17750) return 787.50;
        if($monthly_salary < 18250) return 810.00;
        if($monthly_salary < 18750) return 832.50;
        if($monthly_salary < 19250) return 855.00;
        if($monthly_salary < 19750) return 877.50;
        return 900.00;
    }

    private function computePhilHealth($monthly_salary) {
        $employee = ($monthly_salary * 0.05) / 2;
        return max(250.00, min(1250.00, $employee));
    }

    private function computePagIbig($monthly_salary) {
        $comp = min($monthly_salary, 5000);
        return $comp * ($monthly_salary <= 1500 ? 0.01 : 0.02);
    }

    private function computeWithholdingTax($monthly_salary, $sss, $philhealth, $pagibig) {
        $taxable_monthly = $monthly_salary - $sss - $philhealth - $pagibig;
        $annual_taxable  = $taxable_monthly * 12;
        if($annual_taxable <= 250000)       $annual_tax = 0;
        elseif($annual_taxable <= 400000)   $annual_tax = ($annual_taxable - 250000) * 0.15;
        elseif($annual_taxable <= 800000)   $annual_tax = 22500  + ($annual_taxable - 400000) * 0.20;
        elseif($annual_taxable <= 2000000)  $annual_tax = 102500 + ($annual_taxable - 800000) * 0.25;
        elseif($annual_taxable <= 8000000)  $annual_tax = 402500 + ($annual_taxable - 2000000) * 0.30;
        else                                $annual_tax = 2202500 + ($annual_taxable - 8000000) * 0.35;
        return round($annual_tax / 12, 2);
    }

    /**
     * Compute deductions for a given employee and work inputs
     */
    public function computePayrollDeductions($employee_id, $days_worked, $hours_per_day = 8, $overtime = 0) {
        $employee_id = (int)$employee_id;
        $emp = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT * FROM employees WHERE employee_id = $employee_id"));
        if (!$emp) return ['error' => 'Employee not found'];

        $monthly     = (float)$emp['basic_salary'];
        $daily_rate  = $monthly / 26;
        $hourly_rate = $daily_rate / 8;

        $basic_pay    = $hourly_rate * $hours_per_day * $days_worked;
        $overtime_pay = $hourly_rate * 1.25 * $overtime;
        $gross_pay    = $basic_pay + $overtime_pay;

        $sss        = $this->computeSSS($monthly);
        $philhealth = $this->computePhilHealth($monthly);
        $pagibig    = $this->computePagIbig($monthly);
        $wtax       = $this->computeWithholdingTax($monthly, $sss, $philhealth, $pagibig);

        $total_deductions = $sss + $philhealth + $pagibig + $wtax;
        $net_pay          = $gross_pay - $total_deductions;

        return [
            'basic_pay'        => round($basic_pay, 2),
            'overtime_pay'     => round($overtime_pay, 2),
            'gross_pay'        => round($gross_pay, 2),
            'sss'              => round($sss, 2),
            'philhealth'       => round($philhealth, 2),
            'pagibig'          => round($pagibig, 2),
            'withholding_tax'  => round($wtax, 2),
            'total_deductions' => round($total_deductions, 2),
            'net_pay'          => round($net_pay, 2),
            'daily_rate'       => round($daily_rate, 2),
            'hourly_rate'      => round($hourly_rate, 2),
        ];
    }

    /**
     * Get attendance summary for a payroll period
     */
    public function getAttendanceSummaryForPeriod($employee_id, $period_id) {
        $employee_id = (int)$employee_id;
        $period_id   = (int)$period_id;

        $period = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT date_from, date_to FROM payroll_periods WHERE period_id = $period_id"
        ));
        if (!$period) return ['error' => 'Payroll period not found'];

        $res = mysqli_query($this->conn, "
            SELECT status, hours_worked, overtime_hours
            FROM attendance
            WHERE employee_id = $employee_id
              AND date BETWEEN '{$period['date_from']}' AND '{$period['date_to']}'
        ");

        $days_worked = 0; $total_hours = 0; $worked_records = 0; $overtime_total = 0;
        while($row = mysqli_fetch_assoc($res)) {
            if($row['status'] === 'Half Day') $days_worked += 0.5;
            elseif(in_array($row['status'], ['Present','Late'])) $days_worked += 1;
            if((float)$row['hours_worked'] > 0) { $total_hours += (float)$row['hours_worked']; $worked_records++; }
            $overtime_total += (float)$row['overtime_hours'];
        }

        return [
            'days_worked'    => round($days_worked, 1),
            'hours_per_day'  => $worked_records > 0 ? round($total_hours / $worked_records, 2) : 0,
            'overtime_hours' => round($overtime_total, 2),
        ];
    }

    /**
     * Save (create or update) a payroll record
     */
    public function savePayroll($postData, $admin_id) {
        $period_id        = (int)$postData['period_id'];
        $employee_id      = (int)$postData['employee_id'];
        $basic_salary     = (float)$postData['basic_salary'];
        $days_worked      = (float)$postData['days_worked'];
        $hours_per_day    = (float)($postData['hours_per_day'] ?? 8);
        $overtime_pay     = (float)$postData['overtime_pay'];
        $gross_pay        = (float)$postData['gross_pay'];
        $sss              = (float)$postData['sss'];
        $philhealth       = (float)$postData['philhealth'];
        $pagibig          = (float)$postData['pagibig'];
        $wtax             = (float)$postData['withholding_tax'];
        $other_ded        = (float)$postData['other_deductions'];
        $ded_notes        = mysqli_real_escape_string($this->conn, $postData['deduction_notes'] ?? '');
        $total_deductions = $sss + $philhealth + $pagibig + $wtax + $other_ded;
        $net_pay          = $gross_pay - $total_deductions;

        $exists = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT payroll_id FROM payroll WHERE period_id=$period_id AND employee_id=$employee_id"
        ));

        if ($exists) {
            $pid = $exists['payroll_id'];
            $q = mysqli_query($this->conn, "
                UPDATE payroll SET
                    basic_salary=$basic_salary, days_worked=$days_worked, hours_per_day=$hours_per_day,
                    overtime_pay=$overtime_pay, gross_pay=$gross_pay,
                    sss=$sss, philhealth=$philhealth, pagibig=$pagibig,
                    withholding_tax=$wtax, other_deductions=$other_ded,
                    deduction_notes='$ded_notes', total_deductions=$total_deductions,
                    net_pay=$net_pay
                WHERE payroll_id=$pid
            ");
        } else {
            $q = mysqli_query($this->conn, "
                INSERT INTO payroll (period_id, employee_id, basic_salary, days_worked, hours_per_day,
                    overtime_pay, gross_pay, sss, philhealth, pagibig, withholding_tax,
                    other_deductions, deduction_notes, total_deductions, net_pay)
                VALUES ($period_id, $employee_id, $basic_salary, $days_worked, $hours_per_day,
                    $overtime_pay, $gross_pay, $sss, $philhealth, $pagibig, $wtax,
                    $other_ded, '$ded_notes', $total_deductions, $net_pay)
            ");
        }

        return $q ? 'success' : 'error: ' . mysqli_error($this->conn);
    }

    /**
     * Get the suggested next payroll period dates
     */
    public function getNextPeriodDates() {
        $last = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT date_from, date_to FROM payroll_periods ORDER BY date_to DESC LIMIT 1"
        ));

        if ($last) {
            $nextStart = new DateTime($last['date_to']);
            $nextStart->modify('+1 day');
        } else {
            $today = new DateTime();
            $nextStart = new DateTime($today->format('Y-m-01'));
            if((int)$today->format('d') > 15) $nextStart = new DateTime($today->format('Y-m-16'));
        }

        $day = (int)$nextStart->format('d');
        $year = (int)$nextStart->format('Y');

        if ($day <= 15) {
            $fromDate = $nextStart->format('Y-m-01');
            $toDate   = $nextStart->format('Y-m-15');
            $payDate  = $nextStart->format('Y-m-17');
            $label    = $nextStart->format('F') . ' 1–15, ' . $year;
        } else {
            $lastDay  = (int)$nextStart->format('t');
            $fromDate = $nextStart->format('Y-m-16');
            $toDate   = $nextStart->format('Y-m-') . str_pad($lastDay, 2, '0', STR_PAD_LEFT);
            $payDt = new DateTime($toDate);
            $payDt->modify('+2 days');
            $payDate = $payDt->format('Y-m-d');
            $label   = $nextStart->format('F') . ' 16–' . $lastDay . ', ' . $year;
        }

        return [
            'period_name' => $label . ' Payroll',
            'date_from'   => $fromDate,
            'date_to'     => $toDate,
            'pay_date'    => $payDate,
        ];
    }

    /**
     * Create a new payroll period
     */
    public function createPayrollPeriod($postData, $admin_id) {
        $name     = mysqli_real_escape_string($this->conn, $postData['period_name']);
        $from     = $postData['date_from'];
        $to       = $postData['date_to'];
        $pay_date = $postData['pay_date'];

        $diffDays = (int)((strtotime($to) - strtotime($from)) / 86400) + 1;
        if ($diffDays < 13 || $diffDays > 17) {
            return 'error: Payroll periods must follow the 15-day semi-monthly cycle (1st–15th or 16th–end of month).';
        }

        $dupCheck = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT period_id FROM payroll_periods
             WHERE ('$from' BETWEEN date_from AND date_to)
                OR ('$to' BETWEEN date_from AND date_to)
             LIMIT 1"
        ));
        if ($dupCheck) return 'error: A payroll period already exists that overlaps these dates.';

        $q = mysqli_query($this->conn, "
            INSERT INTO payroll_periods (period_name, date_from, date_to, pay_date, created_by)
            VALUES ('$name', '$from', '$to', '$pay_date', $admin_id)
        ");

        return $q ? 'success:' . mysqli_insert_id($this->conn) : 'error: ' . mysqli_error($this->conn);
    }

    /**
     * Approve a payroll period
     */
    public function approvePeriod($period_id) {
        $period_id = (int)$period_id;
        $q = mysqli_query($this->conn, "UPDATE payroll_periods SET status='Approved' WHERE period_id=$period_id");
        return $q ? 'success' : 'error: ' . mysqli_error($this->conn);
    }

    /**
     * Mark payroll period and all its records as Paid
     */
    public function markPeriodPaid($period_id) {
        $period_id = (int)$period_id;
        mysqli_query($this->conn, "UPDATE payroll_periods SET status='Paid' WHERE period_id=$period_id");
        mysqli_query($this->conn, "UPDATE payroll SET status='Paid' WHERE period_id=$period_id");
        return 'success';
    }

    /**
     * Delete a payroll period and its records
     */
    public function deletePeriod($period_id) {
        $period_id = (int)$period_id;
        mysqli_query($this->conn, "DELETE FROM payroll WHERE period_id=$period_id");
        $q = mysqli_query($this->conn, "DELETE FROM payroll_periods WHERE period_id=$period_id");
        return $q ? 'success' : 'error: ' . mysqli_error($this->conn);
    }

    /**
     * Get all payroll records for a specific period
     */
    public function getPeriodRecords($period_id) {
        $period_id = (int)$period_id;
        return mysqli_query($this->conn,
            "SELECT p.*, e.full_name, e.employee_no
             FROM payroll p
             JOIN employees e ON p.employee_id = e.employee_id
             WHERE p.period_id = $period_id
             ORDER BY e.full_name ASC"
        );
    }

    /**
     * Archive payroll record
     */
    public function archivePayroll($payroll_id, $reason, $admin_id) {
        $payroll_id = (int)$payroll_id;
        $reason = mysqli_real_escape_string($this->conn, trim($reason));

        $p = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT * FROM payroll WHERE payroll_id = $payroll_id"));
        if ($p) {
            mysqli_begin_transaction($this->conn);
            try {
                $ins = mysqli_query($this->conn, "
                    INSERT INTO payroll_archive (payroll_id, period_id, employee_id, basic_salary, gross_pay, total_deductions, net_pay, status, archive_reason)
                    VALUES ({$p['payroll_id']}, {$p['period_id']}, {$p['employee_id']}, {$p['basic_salary']}, {$p['gross_pay']}, {$p['total_deductions']}, {$p['net_pay']}, '{$p['status']}', '$reason')
                ");
                if (!$ins) throw new Exception(mysqli_error($this->conn));
                mysqli_query($this->conn, "DELETE FROM payroll WHERE payroll_id = $payroll_id");
                mysqli_commit($this->conn);
                return 'success';
            } catch (Exception $e) {
                mysqli_rollback($this->conn);
                return 'error: ' . $e->getMessage();
            }
        }
        return 'error: Failed to archive payroll record.';
    }

    /**
     * Restore archived payroll record
     */
    public function restorePayroll($archive_id) {
        $archive_id = (int)$archive_id;
        $arch = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT * FROM payroll_archive WHERE archive_id = $archive_id"));
        if ($arch) {
            mysqli_begin_transaction($this->conn);
            try {
                $ins = mysqli_query($this->conn, "
                    INSERT INTO payroll (period_id, employee_id, basic_salary, gross_pay, total_deductions, net_pay, status)
                    VALUES ({$arch['period_id']}, {$arch['employee_id']}, {$arch['basic_salary']}, {$arch['gross_pay']}, {$arch['total_deductions']}, {$arch['net_pay']}, '{$arch['status']}')
                ");
                if (!$ins) throw new Exception(mysqli_error($this->conn));
                mysqli_query($this->conn, "DELETE FROM payroll_archive WHERE archive_id = $archive_id");
                mysqli_commit($this->conn);
                return 'success';
            } catch (Exception $e) {
                mysqli_rollback($this->conn);
                return 'error: ' . $e->getMessage();
            }
        }
        return 'error: Failed to restore payroll record.';
    }

    /**
     * Reject a payroll record (revert to Draft)
     */
    public function rejectPayroll($payroll_id, $reason) {
        $payroll_id = (int)$payroll_id;
        $reason = mysqli_real_escape_string($this->conn, trim($reason));
        $q = mysqli_query($this->conn, "
            UPDATE payroll SET status='Draft', rejection_notes='$reason' WHERE payroll_id=$payroll_id
        ");
        return $q ? 'success' : 'error: ' . mysqli_error($this->conn);
    }

    /**
     * Get payroll periods list with summary aggregates
     */
    public function getPayrollPeriodsList() {
        return mysqli_query($this->conn, "
            SELECT pp.*, u.full_name AS created_by_name,
                   COUNT(p.payroll_id) AS employee_count,
                   IFNULL(SUM(p.net_pay),0) AS total_net
            FROM payroll_periods pp
            LEFT JOIN users u ON pp.created_by = u.user_id
            LEFT JOIN payroll p ON pp.period_id = p.period_id
            GROUP BY pp.period_id
            ORDER BY pp.created_at DESC
        ");
    }

    /**
     * Get active employees with position and department
     */
    public function getActiveEmployeesWithPosition() {
        return mysqli_query($this->conn, "
            SELECT e.*, pos.position_name, d.department_name
            FROM employees e
            LEFT JOIN positions pos ON e.position_id = pos.position_id
            LEFT JOIN departments d ON e.department_id = d.department_id
            WHERE e.status = 'Active'
            ORDER BY e.full_name ASC
        ");
    }

    // =====================================================
    // APPLICANTS METHODS
    // =====================================================

    /**
     * Send stage notification email to applicant
     */
    public function sendApplicantStageEmail($gmail, $name, $stage, $interviewDate = '', $rejectionReason = '') {
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
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
            $mail->setFrom('edonnarao06@gmail.com', 'O-Cart! HRMS');
            $mail->addAddress($gmail);
            $mail->isHTML(true);

            $formattedDate = '';
            if (!empty($interviewDate)) {
                $ts = strtotime($interviewDate);
                if ($ts !== false) $formattedDate = date('F j, Y \a\t g:i A', $ts);
            }

            switch($stage) {
                case 'Application Received':
                    $mail->Subject = 'Application Received - O-Cart!';
                    $intro = "Thank you for applying with us! We've received your application and it is now under review.";
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
                    $reasonDetails = !empty($rejectionReason) ? '<br><br><em>Feedback / Remarks: ' . htmlspecialchars($rejectionReason) . '</em>' : '';
                    $intro = "Thank you for your time. After careful consideration, we've decided to move forward with other candidates." . $reasonDetails;
                    break;
                default:
                    $mail->Subject = 'Application Update - O-Cart!';
                    $intro = "There's an update to your application status: <strong>$stage</strong>.";
            }

            $dateBlock = '';
            if ($formattedDate !== '' && in_array($stage, ['First Interview', 'Final Interview'])) {
                $dateBlock = "<table style='width:100%; border-collapse:collapse; margin-top:15px;'><tr><td style='padding:5px 0; color:#666;'>Scheduled Date &amp; Time:</td><td><strong>$formattedDate</strong></td></tr></table>";
            }

            $mail->Body = "<div style='font-family:Arial,sans-serif;padding:20px;border:1px solid #eee;border-radius:10px;max-width:500px;'><h2 style='color:#1a3c5e;'>O-Cart! Recruitment</h2><p>Hello <strong>$name</strong>,</p><p>$intro</p>$dateBlock<p style='margin-top:25px;font-size:12px;color:#888;'>If you have any questions, feel free to reply to this email.</p></div>";
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Applicant Stage Email Error: " . $mail->ErrorInfo);
            return 'ERR: ' . $mail->ErrorInfo;
        }
    }

    /**
     * Send employee welcome email with credentials
     */
    public function sendEmployeeWelcomeEmail($gmail, $name, $password, $contractStart = '', $contractEnd = '') {
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
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
            $mail->setFrom('edonnarao06@gmail.com', 'O-Cart! HRMS');
            $mail->addAddress($gmail);
            $mail->isHTML(true);
            $mail->Subject = 'Your Employee Portal Credentials & Employment Contract - O-Cart!';
            $contractStartFmt = !empty($contractStart) ? date('F j, Y', strtotime($contractStart)) : 'Date of Hire';
            $contractEndFmt   = !empty($contractEnd) ? date('F j, Y', strtotime($contractEnd)) : '6 Months from Hire Date';
            $mail->Body = "<div style='font-family:Arial,sans-serif;padding:20px;border:1px solid #eee;border-radius:10px;max-width:550px;'><h2 style='color:#1a3c5e;margin-top:0;'>O-Cart! Employee Portal</h2><p>Hello <strong>$name</strong>,</p><p>Welcome to our team! Your employee account has been activated.</p><div style='background:#f0f7ff;border-left:4px solid #2563eb;padding:12px 15px;margin:15px 0;border-radius:4px;'><strong style='color:#1e40af;'>📄 Employment Contract Summary:</strong><br><span style='font-size:13px;color:#334155;'>• Start Date: <strong>$contractStartFmt</strong><br>• End Date: <strong>$contractEndFmt</strong><br>• Status: <strong>Active</strong></span></div><p>Your login: <strong>$gmail</strong> / Password: <code style='background:#f4f6f5;padding:3px 6px;border-radius:3px;'>$password</code></p><p style='font-size:12px;color:#888;'>Please change your password after first login.</p></div>";
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Welcome Email Error: " . $mail->ErrorInfo);
            return 'ERR: ' . $mail->ErrorInfo;
        }
    }

    /**
     * Send password reset notification to employee
     */
    public function sendEmployeePasswordResetEmail($gmail, $name, $password) {
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
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
            $mail->setFrom('edonnarao06@gmail.com', 'O-cart! HRMS');
            $mail->addAddress($gmail);
            $mail->isHTML(true);
            $mail->Subject = 'Your Employee Portal Password Was Updated - O-cart!';
            $mail->Body = "<div style='font-family:Arial,sans-serif;padding:20px;border:1px solid #eee;border-radius:10px;max-width:500px;'><h2 style='color:#1a3c5e;'>O-cart! E-Portal</h2><p>Hello <strong>$name</strong>,</p><p>Your portal password has been reset.</p><p>New Password: <code style='background:#f4f6f5;padding:3px 6px;border-radius:3px;'>$password</code></p><p style='font-size:12px;color:#888;'>If you did not expect this change, contact HR immediately.</p></div>";
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Password Reset Email Error: " . $mail->ErrorInfo);
            return 'ERR: ' . $mail->ErrorInfo;
        }
    }

    /**
     * Create a new applicant
     */
    public function createApplicant($postData, $fileData, $admin_id) {
        $position_id = (int)$postData['position_id'];
        $full_name   = mysqli_real_escape_string($this->conn, trim($postData['full_name']));
        $email       = mysqli_real_escape_string($this->conn, trim($postData['email']));
        $phone       = mysqli_real_escape_string($this->conn, trim($postData['phone']));
        $address     = mysqli_real_escape_string($this->conn, trim($postData['address']));
        $notes       = mysqli_real_escape_string($this->conn, trim($postData['notes']));

        $posCheck = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT slots, status FROM positions WHERE position_id = $position_id LIMIT 1"
        ));
        if (!$posCheck || $posCheck['status'] != 'Open')
            return 'error: This position is not open for applications.';

        $filledCheck = mysqli_fetch_assoc(mysqli_query($this->conn,
            "SELECT COUNT(*) AS cnt FROM employees WHERE position_id = $position_id AND status = 'Active'"
        ))['cnt'];
        if ((int)$filledCheck >= (int)$posCheck['slots'])
            return 'error: This position is already fully filled.';

        if (!preg_match('/^09\d{9}$/', $phone))
            return 'error: Phone number must start with 09 and be exactly 11 digits.';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || substr_count($email, '@') !== 1)
            return 'error: Please enter a valid email address with exactly one @.';

        $resume = '';
        if (isset($fileData['resume']) && $fileData['resume']['error'] === 0) {
            $fileType = mime_content_type($fileData['resume']['tmp_name']);
            if ($fileType !== 'application/pdf') return 'error: Resume must be a PDF file.';
            if ($fileData['resume']['size'] > 5 * 1024 * 1024) return 'error: Resume file must not exceed 5MB.';
            $uploadDir = __DIR__ . '/../View/uploads/resumes/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileData['resume']['name']);
            move_uploaded_file($fileData['resume']['tmp_name'], $uploadDir . $fileName);
            $resume = mysqli_real_escape_string($this->conn, $fileName);
        }

        $q = mysqli_query($this->conn, "
            INSERT INTO applicants (position_id, full_name, email, phone, address, notes, resume)
            VALUES ($position_id, '$full_name', '$email', '$phone', '$address', '$notes', '$resume')
        ");

        if ($q) {
            $new_id = mysqli_insert_id($this->conn);
            logAction($this->conn, $admin_id, 'Create', 'applicants', $new_id, "Added applicant: $full_name");
            if (!empty($email)) $this->sendApplicantStageEmail($email, $full_name, 'Application Received');
            return 'success';
        }
        return 'error: ' . mysqli_error($this->conn);
    }

    /**
     * Update an applicant record
     */
    public function updateApplicant($postData, $admin_id) {
        $id          = (int)$postData['applicant_id'];
        $position_id = (int)$postData['position_id'];
        $full_name   = mysqli_real_escape_string($this->conn, trim($postData['full_name']));
        $email       = mysqli_real_escape_string($this->conn, trim($postData['email']));
        $phone       = mysqli_real_escape_string($this->conn, trim($postData['phone']));
        $address     = mysqli_real_escape_string($this->conn, trim($postData['address']));
        $notes       = mysqli_real_escape_string($this->conn, trim($postData['notes']));

        $q = mysqli_query($this->conn, "
            UPDATE applicants SET
                position_id='$position_id', full_name='$full_name', email='$email',
                phone='$phone', address='$address', notes='$notes'
            WHERE applicant_id=$id
        ");

        if ($q) {
            logAction($this->conn, $admin_id, 'Update', 'applicants', $id, "Updated applicant: $full_name");
            return 'success';
        }
        return 'error: ' . mysqli_error($this->conn);
    }

    /**
     * Advance applicant to a new stage
     */
    public function advanceApplicantStage($postData, $admin_id) {
        $id                  = (int)$postData['applicant_id'];
        $stage               = mysqli_real_escape_string($this->conn, $postData['stage']);
        $interview_date_raw  = trim($postData['interview_date'] ?? '');
        $interview_date_sql  = $interview_date_raw !== '' ? mysqli_real_escape_string($this->conn, $interview_date_raw) : '';
        $rejection_reason    = trim($postData['rejection_reason'] ?? '');
        $rejection_reason_sql= mysqli_real_escape_string($this->conn, $rejection_reason);

        $allowed = ['Initial Screening','First Interview','Final Interview','Approved','Rejected'];
        if (!in_array($stage, $allowed)) return 'error: Invalid stage';

        $setSql = "stage='$stage'";
        $setSql .= $interview_date_sql !== '' ? ", next_interview='$interview_date_sql'" : ", next_interview=NULL";

        if ($stage === 'Rejected') {
            $currStgRow = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT stage FROM applicants WHERE applicant_id=$id"));
            $failedStage = mysqli_real_escape_string($this->conn, $currStgRow['stage'] ?? 'Initial Screening');
            $setSql .= ", notes = CONCAT(IFNULL(notes,''), IF(LENGTH(IFNULL(notes,''))>0, '\n', ''), '[Failed Stage]: ', '$failedStage')";
            if (!empty($rejection_reason_sql))
                $setSql .= ", notes = CONCAT(IFNULL(notes,''), '\n[Rejection Reason]: ', '$rejection_reason_sql')";
        }

        $q = mysqli_query($this->conn, "UPDATE applicants SET $setSql WHERE applicant_id=$id");

        if ($q) {
            $app  = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT full_name, email FROM applicants WHERE applicant_id=$id"));
            $name = $app['full_name'];
            if (!empty($app['email'])) $this->sendApplicantStageEmail($app['email'], $name, $stage, $interview_date_raw, $rejection_reason);
            $logMsg = "Advanced $name to stage: $stage";
            if ($stage === 'Rejected' && !empty($rejection_reason)) $logMsg .= " — Reason: $rejection_reason";
            logAction($this->conn, $admin_id, 'Update', 'applicants', $id, $logMsg);
            return 'success';
        }
        return 'error: ' . mysqli_error($this->conn);
    }

    /**
     * Delete (archive then remove) an applicant
     */
    public function deleteApplicant($applicant_id, $reason, $admin_id) {
        $id     = (int)$applicant_id;
        $reason = mysqli_real_escape_string($this->conn, trim($reason));

        $app = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT * FROM applicants WHERE applicant_id=$id"));
        if (!$app) return 'error: Applicant not found.';

        $full_name  = mysqli_real_escape_string($this->conn, $app['full_name']);
        $email      = mysqli_real_escape_string($this->conn, $app['email'] ?? '');
        $phone      = mysqli_real_escape_string($this->conn, $app['phone'] ?? '');
        $address    = mysqli_real_escape_string($this->conn, $app['address'] ?? '');
        $resume     = mysqli_real_escape_string($this->conn, $app['resume'] ?? '');
        $notes      = mysqli_real_escape_string($this->conn, $app['notes'] ?? '');
        $stage      = mysqli_real_escape_string($this->conn, $app['stage']);
        $applied_at = $app['applied_at'] ? "'{$app['applied_at']}'" : 'NULL';

        mysqli_query($this->conn, "
            INSERT INTO applicants_archive
                (applicant_id, position_id, full_name, email, phone, address, resume, stage, notes, applied_at, archive_reason, archived_by)
            VALUES
                ($id, {$app['position_id']}, '$full_name', '$email', '$phone', '$address', '$resume', '$stage', '$notes', $applied_at, '$reason', $admin_id)
        ");

        $q = mysqli_query($this->conn, "DELETE FROM applicants WHERE applicant_id=$id");
        if ($q) {
            logAction($this->conn, $admin_id, 'Delete', 'applicants', $id, "Archived & removed applicant: $full_name — Reason: $reason");
            return 'success';
        }
        return 'error: ' . mysqli_error($this->conn);
    }

    /**
     * Restore an archived applicant
     */
    public function restoreApplicant($archive_id, $admin_id) {
        $archive_id = (int)$archive_id;
        $arch = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT * FROM applicants_archive WHERE archive_id=$archive_id"));
        if (!$arch) return 'error: Archived applicant record not found.';

        $pos_id     = (int)$arch['position_id'];
        $full_name  = mysqli_real_escape_string($this->conn, $arch['full_name']);
        $email      = mysqli_real_escape_string($this->conn, $arch['email'] ?? '');
        $phone      = mysqli_real_escape_string($this->conn, $arch['phone'] ?? '');
        $address    = mysqli_real_escape_string($this->conn, $arch['address'] ?? '');
        $resume     = mysqli_real_escape_string($this->conn, $arch['resume'] ?? '');
        $stage      = mysqli_real_escape_string($this->conn, $arch['stage'] ?? 'Initial Screening');
        $notes      = mysqli_real_escape_string($this->conn, $arch['notes'] ?? '');
        $applied_at = $arch['applied_at'] ? "'{$arch['applied_at']}'" : 'NOW()';

        if ($stage === 'Approved' || $arch['archive_reason'] === 'Hired') $stage = 'Initial Screening';

        $q = mysqli_query($this->conn, "
            INSERT INTO applicants (position_id, full_name, email, phone, address, resume, stage, notes, applied_at)
            VALUES ($pos_id, '$full_name', '$email', '$phone', '$address', '$resume', '$stage', '$notes', $applied_at)
        ");

        if ($q) {
            $new_id = mysqli_insert_id($this->conn);
            mysqli_query($this->conn, "DELETE FROM applicants_archive WHERE archive_id=$archive_id");
            logAction($this->conn, $admin_id, 'Restore', 'applicants', $new_id, "Restored applicant: $full_name from archive");
            return 'success';
        }
        return 'error: ' . mysqli_error($this->conn);
    }

    /**
     * Convert approved applicant to employee record
     */
    public function convertApplicantToEmployee($postData, $admin_id) {
        $applicant_id   = (int)$postData['applicant_id'];
        $position_id    = (int)$postData['position_id'];
        $full_name      = mysqli_real_escape_string($this->conn, trim($postData['full_name']));
        $email          = mysqli_real_escape_string($this->conn, trim($postData['email']));
        $phone          = mysqli_real_escape_string($this->conn, trim($postData['phone']));
        $address        = mysqli_real_escape_string($this->conn, trim($postData['address']));
        $birthdate      = $postData['birthdate'];
        $gender         = $postData['gender'];
        $civil_status   = $postData['civil_status'];
        $date_hired     = $postData['date_hired'];
        $schedule       = mysqli_real_escape_string($this->conn, trim($postData['schedule'] ?? ''));
        $rest_day       = mysqli_real_escape_string($this->conn, trim($postData['rest_day'] ?? ''));
        $emp_type       = mysqli_real_escape_string($this->conn, trim($postData['employment_type'] ?? 'Full-time'));
        $basic_salary   = (float)$postData['basic_salary'];
        $sal_min        = (float)($postData['sal_min'] ?? 0);
        $sal_max        = (float)($postData['sal_max'] ?? 0);

        if ($sal_min > 0 && $sal_max > 0 && ($basic_salary < $sal_min || $basic_salary > $sal_max))
            return "error: Salary must be between ₱" . number_format($sal_min,2) . " and ₱" . number_format($sal_max,2) . ".";

        $sss_no        = mysqli_real_escape_string($this->conn, trim($postData['sss_no']));
        $philhealth_no = mysqli_real_escape_string($this->conn, trim($postData['philhealth_no']));
        $pagibig_no    = mysqli_real_escape_string($this->conn, trim($postData['pagibig_no']));
        $tin_no        = mysqli_real_escape_string($this->conn, trim($postData['tin_no']));

        $govtPatterns = [
            'SSS No.'        => [$sss_no,        '/^\d{2}-\d{7}-\d{1}$/'],
            'PhilHealth No.' => [$philhealth_no, '/^\d{4}-\d{4}-\d{4}$/'],
            'Pag-IBIG No.'   => [$pagibig_no,    '/^\d{4}-\d{4}-\d{4}$/'],
            'TIN No.'        => [$tin_no,        '/^\d{3}-\d{3}-\d{3}-\d{3}$/'],
        ];
        foreach ($govtPatterns as $label => $check) {
            [$value, $regex] = $check;
            if ($value !== '' && !preg_match($regex, $value))
                return "error: $label is incomplete or invalid.";
        }

        $portal_password = bin2hex(random_bytes(5));
        if (!empty($portal_password) && empty($email)) return 'error: Email is required to generate a portal account.';

        $slotCheck = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT slots FROM positions WHERE position_id=$position_id LIMIT 1"));
        if (!$slotCheck) return 'error: Selected position no longer exists.';
        $filledSlots = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT COUNT(*) AS cnt FROM employees WHERE position_id=$position_id AND status='Active'"))['cnt'];
        if ((int)$filledSlots >= (int)$slotCheck['slots'])
            return "error: This position is already fully filled ($filledSlots/{$slotCheck['slots']} slots taken).";

        $last   = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT employee_no FROM employees ORDER BY employee_id DESC LIMIT 1"));
        $next_num = $last ? (intval(substr($last['employee_no'], 4)) + 1) : 1;
        $emp_no = 'EMP-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);

        $hashed_pw_val = "NULL";
        if (!empty($portal_password))
            $hashed_pw_val = "'" . mysqli_real_escape_string($this->conn, password_hash($portal_password, PASSWORD_BCRYPT)) . "'";

        $contract_start = !empty($postData['contract_start']) ? $postData['contract_start'] : $date_hired;
        $contract_end   = !empty($postData['contract_end']) ? $postData['contract_end'] : date('Y-m-d', strtotime('+6 months', strtotime($contract_start)));

        $q = mysqli_query($this->conn, "
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
                $basic_salary, '$sss_no', '$philhealth_no', '$pagibig_no', '$tin_no', $hashed_pw_val,
                '$schedule', '$rest_day', '$contract_start', '$contract_end', 1, NOW()
            )
        ");

        if (!$q) return 'error: ' . mysqli_error($this->conn);

        $emp_id = mysqli_insert_id($this->conn);

        // Archive applicant as Hired
        $appRow = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT * FROM applicants WHERE applicant_id=$applicant_id"));
        if ($appRow) {
            $aName   = mysqli_real_escape_string($this->conn, $appRow['full_name']);
            $aEmail  = mysqli_real_escape_string($this->conn, $appRow['email'] ?? '');
            $aPhone  = mysqli_real_escape_string($this->conn, $appRow['phone'] ?? '');
            $aAddr   = mysqli_real_escape_string($this->conn, $appRow['address'] ?? '');
            $aResume = mysqli_real_escape_string($this->conn, $appRow['resume'] ?? '');
            $aNotes  = mysqli_real_escape_string($this->conn, $appRow['notes'] ?? '');
            $aApplied= $appRow['applied_at'] ? "'{$appRow['applied_at']}'" : 'NULL';
            mysqli_query($this->conn, "
                INSERT INTO applicants_archive (applicant_id, position_id, full_name, email, phone, address, resume, stage, notes, applied_at, archive_reason, archived_by)
                VALUES ($applicant_id, {$appRow['position_id']}, '$aName', '$aEmail', '$aPhone', '$aAddr', '$aResume', 'Approved', '$aNotes', $aApplied, 'Hired', $admin_id)
            ");
            mysqli_query($this->conn, "DELETE FROM applicants WHERE applicant_id=$applicant_id");
        }

        // Auto-close position if all slots are now filled
        $nowFilled = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT COUNT(*) AS cnt FROM employees WHERE position_id=$position_id AND status='Active'"))['cnt'];
        if ((int)$nowFilled >= (int)$slotCheck['slots'])
            mysqli_query($this->conn, "UPDATE positions SET status='Closed' WHERE position_id=$position_id");

        // Sync with users table
        $mail_status = '';
        if (!empty($email) && !empty($portal_password)) {
            $hashed_password = password_hash($portal_password, PASSWORD_BCRYPT);
            $esc_email       = mysqli_real_escape_string($this->conn, $email);
            $user_exists = mysqli_query($this->conn, "SELECT user_id FROM users WHERE gmail='$esc_email'");
            if (mysqli_num_rows($user_exists) == 0) {
                mysqli_query($this->conn, "INSERT INTO users (gmail, password, full_name, role, status) VALUES ('$esc_email', '" . mysqli_real_escape_string($this->conn, $hashed_password) . "', '$full_name', 'Cashier', 'Active')");
                $sent = $this->sendEmployeeWelcomeEmail($email, $full_name, $portal_password, $contract_start, $contract_end);
                $mail_status = ($sent === true) ? '' : '|Email failed - ' . $sent;
            } else {
                mysqli_query($this->conn, "UPDATE users SET password='" . mysqli_real_escape_string($this->conn, $hashed_password) . "' WHERE gmail='$esc_email'");
                $sent = $this->sendEmployeePasswordResetEmail($email, $full_name, $portal_password);
                $mail_status = ($sent === true) ? '|This Gmail already had a portal account, so its password was reset and emailed.' : '|Gmail already had an account. Password was reset but email failed - ' . $sent;
            }
        }

        return 'success:' . $emp_no . $mail_status;
    }

    /**
     * Get all active applicants with their position
     */
    public function getApplicantsList() {
        return mysqli_query($this->conn, "
            SELECT a.*, p.position_name, p.employment_type
            FROM applicants a
            LEFT JOIN positions p ON a.position_id = p.position_id
            WHERE a.stage != 'Rejected'
            ORDER BY a.applied_at DESC
        ");
    }

    /**
     * Get positions with slot fill data
     */
    public function getPositionsWithSlots() {
        return mysqli_query($this->conn,
            "SELECT p.*,
                    (SELECT COUNT(*) FROM employees e WHERE e.position_id = p.position_id AND e.status = 'Active') AS filled_slots
             FROM positions p ORDER BY p.position_name ASC"
        );
    }

    /**
     * Get applicant stage counts
     */
    public function getApplicantStageCounts() {
        $stageCounts = [];
        $stages = ['Initial Screening','First Interview','Final Interview','Approved','Rejected'];
        foreach ($stages as $stage) {
            $s = mysqli_real_escape_string($this->conn, $stage);
            $stageCounts[$stage] = mysqli_fetch_assoc(mysqli_query($this->conn,
                "SELECT COUNT(*) AS total FROM applicants WHERE stage='$s'"
            ))['total'];
        }
        return $stageCounts;
    }
}
