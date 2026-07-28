<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../Model/database.php';
require_once __DIR__ . '/../Model/logger.php';

/*=========================================================
    HELPER: Send Email Notification
==========================================================*/
if (!function_exists('sendApplicantStageEmail')) {
    function sendApplicantStageEmail($gmail, $name, $stage, $interviewDate = '')
    {
        $exceptionPath = __DIR__ . '/../Assets/PHPMailer/Exception.php';
        $phpmailerPath = __DIR__ . '/../Assets/PHPMailer/PHPMailer.php';
        $smtpPath = __DIR__ . '/../Assets/PHPMailer/SMTP.php';

        if (!file_exists($exceptionPath) || !file_exists($phpmailerPath) || !file_exists($smtpPath)) {
            return false;
        }

        require_once $exceptionPath;
        require_once $phpmailerPath;
        require_once $smtpPath;

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

            $mail->Subject = 'Application Received - O-Cart!';
            $body = "Hi " . htmlspecialchars($name) . ",<br><br>";
            $body .= "Thank you for applying with us! We've received your job application and resume. It is now under initial screening by our HR team. We will contact you regarding next steps.<br><br>";
            $body .= "Best regards,<br><strong>O-Cart! HR Team</strong>";

            $mail->Body = $body;
            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

/*=========================================================
    HANDLING APPLICANT SUBMISSION VIA AJAX
==========================================================*/
if (isset($_POST['action']) && $_POST['action'] === 'submit_application') {
    if (ob_get_length())
        ob_clean();
    header('Content-Type: application/json');

    $position_id = isset($_POST['position_id']) ? (int) $_POST['position_id'] : 0;
    $full_name = isset($_POST['full_name']) ? mysqli_real_escape_string($conn, trim($_POST['full_name'])) : '';
    $email = isset($_POST['email']) ? mysqli_real_escape_string($conn, trim($_POST['email'])) : '';
    $phone = isset($_POST['phone']) ? mysqli_real_escape_string($conn, trim($_POST['phone'])) : '';
    $address = isset($_POST['address']) ? mysqli_real_escape_string($conn, trim($_POST['address'])) : '';
    $notes = isset($_POST['notes']) ? mysqli_real_escape_string($conn, trim($_POST['notes'])) : '';

    if ($position_id <= 0) {
        if (ob_get_length())
            ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Please select a valid position.']);
        exit();
    }

    if (empty($full_name)) {
        if (ob_get_length())
            ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Full name is required.']);
        exit();
    }

    // Email validation
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || substr_count($email, '@') !== 1) {
        if (ob_get_length())
            ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address with exactly one @ symbol.']);
        exit();
    }

    // Phone validation (Philippine 11-digit starting with 09)
    if (empty($phone) || !preg_match('/^09\d{9}$/', $phone)) {
        if (ob_get_length())
            ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Phone number must start with 09 and contain exactly 11 digits (e.g. 09123456789).']);
        exit();
    }

    if (empty($address)) {
        if (ob_get_length())
            ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Complete home address is required.']);
        exit();
    }

    // Check position status and slots
    $posQuery = mysqli_query($conn, "
        SELECT p.position_name, p.slots, p.status,
               (SELECT COUNT(*) FROM employees e WHERE e.position_id = p.position_id AND e.status = 'Active') AS filled_slots
        FROM positions p
        WHERE p.position_id = $position_id
        LIMIT 1
    ");
    $posData = mysqli_fetch_assoc($posQuery);

    if (!$posData || $posData['status'] !== 'Open') {
        if (ob_get_length())
            ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'This position is currently not open for applications.']);
        exit();
    }

    $remaining_slots = (int) $posData['slots'] - (int) $posData['filled_slots'];
    if ($remaining_slots <= 0) {
        if (ob_get_length())
            ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'All slots for this position have already been filled.']);
        exit();
    }

    // Resume Upload Validation
    if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
        if (ob_get_length())
            ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Please upload your resume in PDF format.']);
        exit();
    }

    $fileTmpPath = $_FILES['resume']['tmp_name'];
    $fileName = $_FILES['resume']['name'];
    $fileSize = $_FILES['resume']['size'];
    $fileType = mime_content_type($fileTmpPath);

    if ($fileType !== 'application/pdf' && strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'pdf') {
        if (ob_get_length())
            ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Only PDF documents (.pdf) are allowed for resume upload.']);
        exit();
    }

    if ($fileSize > 5 * 1024 * 1024) {
        if (ob_get_length())
            ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Resume file size must not exceed 5MB.']);
        exit();
    }

    // Save Resume to View/uploads/resumes/
    $uploadDir = __DIR__ . '/uploads/resumes/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $safeFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
    $destPath = $uploadDir . $safeFileName;

    if (!move_uploaded_file($fileTmpPath, $destPath)) {
        if (ob_get_length())
            ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Failed to save the uploaded resume. Please try again.']);
        exit();
    }

    $resumeDbName = mysqli_real_escape_string($conn, $safeFileName);
    $positionName = mysqli_real_escape_string($conn, $posData['position_name']);

    // Insert into applicants table
    $insertApp = mysqli_query($conn, "
        INSERT INTO applicants (position_id, full_name, email, phone, address, notes, resume, stage, applied_at)
        VALUES ($position_id, '$full_name', '$email', '$phone', '$address', '$notes', '$resumeDbName', 'Initial Screening', NOW())
    ");

    if ($insertApp) {
        $appId = mysqli_insert_id($conn);

        // Notify HRMS via notifications table
        $notifTitle = mysqli_real_escape_string($conn, "New Applicant: " . $posData['position_name']);
        $notifMsg = mysqli_real_escape_string($conn, "$full_name submitted an application for {$posData['position_name']}.");
        mysqli_query($conn, "
            INSERT INTO notifications (title, message, type, is_read, created_at)
            VALUES ('$notifTitle', '$notifMsg', 'HRMS', 0, NOW())
        ");

        // Log into audit_logs
        logAction($conn, 1, 'Create', 'applicants', $appId, "New public job application from $full_name for position #$position_id ({$posData['position_name']})");

        // Try sending confirmation email
        @sendApplicantStageEmail($email, $full_name, 'Application Received');

        if (ob_get_length())
            ob_clean();
        echo json_encode([
            'status' => 'success',
            'message' => 'Application submitted successfully!',
            'app_id' => $appId,
            'position_name' => $posData['position_name']
        ]);
    } else {
        if (ob_get_length())
            ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
    exit();
}

/*=========================================================
    FETCH DATA FOR PUBLIC VIEW
==========================================================*/
// Fetch Open Positions joined with Departments & Active Employee counts
$positionsRes = mysqli_query($conn, "
    SELECT p.*,
           COALESCE(d.department_name, 'General Operations') AS department_name,
           (SELECT COUNT(*) FROM employees e WHERE e.position_id = p.position_id AND e.status = 'Active') AS filled_slots,
           (SELECT COUNT(*) FROM applicants a WHERE a.position_id = p.position_id AND a.stage NOT IN ('Approved','Rejected')) AS active_applicants
    FROM positions p
    LEFT JOIN departments d ON p.department_id = d.department_id
    WHERE p.status = 'Open'
    ORDER BY p.position_id DESC
");

$positionsList = [];
$departmentsMap = [];

if ($positionsRes) {
    while ($row = mysqli_fetch_assoc($positionsRes)) {
        $remaining = (int) $row['slots'] - (int) $row['filled_slots'];
        // Show position if remaining slots > 0
        if ($remaining > 0) {
            $row['remaining_slots'] = $remaining;
            $positionsList[] = $row;
            $departmentsMap[$row['department_name']] = true;
        }
    }
}

$totalOpenPositions = count($positionsList);
$totalDepartments = count($departmentsMap);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Careers & Job Openings | Sari-Sari Store HRMS</title>

    <!-- FontAwesome & Google Fonts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            --accent-gradient: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            --card-hover-transform: translateY(-4px);
            --navy-dark: #0f172a;
            --blue-accent: #2563eb;
            --emerald-accent: #10b981;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #334155;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top Header Navigation */
        .site-navbar {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.25rem;
            color: #ffffff !important;
            letter-spacing: -0.5px;
        }

        /* Hero Banner */
        .hero-banner {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #1e293b 100%);
            color: #ffffff;
            padding: 4.5rem 0 4rem;
            position: relative;
            overflow: hidden;
        }

        .hero-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.15) 0%, rgba(0, 0, 0, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-title {
            font-weight: 800;
            font-size: 2.75rem;
            line-height: 1.2;
            letter-spacing: -1px;
        }

        .hero-subtitle {
            font-size: 1.15rem;
            color: #94a3b8;
            max-width: 650px;
            margin: 1rem auto 2rem;
        }

        /* Stat Badge Bar */
        .stat-pill {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            border-radius: 50px;
            padding: 0.6rem 1.4rem;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            color: #e2e8f0;
            font-size: 0.92rem;
            font-weight: 500;
        }

        .stat-pill i {
            color: #38bdf8;
        }

        /* Filter Controls */
        .filter-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            margin-top: -2.5rem;
            position: relative;
            z-index: 10;
            border: 1px solid #e2e8f0;
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        /* Job Posting Cards */
        .job-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .job-card:hover {
            transform: var(--card-hover-transform);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            border-color: #cbd5e1;
        }

        .job-card-header {
            padding: 1.5rem 1.5rem 1rem;
        }

        .dept-tag {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #2563eb;
            background: #eff6ff;
            padding: 0.35rem 0.75rem;
            border-radius: 30px;
            display: inline-block;
            margin-bottom: 0.75rem;
        }

        .job-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .type-badge {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.25rem 0.65rem;
            border-radius: 6px;
            background: #f1f5f9;
            color: #475569;
        }

        .slot-badge {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.25rem 0.65rem;
            border-radius: 6px;
            background: #ecfdf5;
            color: #059669;
        }

        .job-card-body {
            padding: 0 1.5rem 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .salary-range {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1rem 0;
        }

        .salary-range span {
            font-size: 0.8rem;
            font-weight: 500;
            color: #64748b;
        }

        .req-preview {
            font-size: 0.88rem;
            color: #64748b;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .job-card-footer {
            padding: 1.25rem 1.5rem;
            background: #f8fafc;
            border-top: 1px solid #f1f5f9;
            margin-top: auto;
            display: flex;
            gap: 0.75rem;
        }

        .btn-custom-primary {
            background: var(--accent-gradient);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            padding: 0.65rem 1.25rem;
            transition: all 0.2s ease;
        }

        .btn-custom-primary:hover {
            opacity: 0.95;
            color: #ffffff;
            transform: translateY(-1px);
        }

        .btn-custom-outline {
            background: transparent;
            color: #475569;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-weight: 600;
            padding: 0.65rem 1rem;
            transition: all 0.2s ease;
        }

        .btn-custom-outline:hover {
            background: #ffffff;
            color: #0f172a;
            border-color: #94a3b8;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }

        .modal-header {
            background: #0f172a;
            color: #ffffff;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.5rem 2rem;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        .modal-body {
            padding: 2rem;
        }

        /* File Upload Container */
        .file-upload-box {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 2rem 1.5rem;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .file-upload-box:hover,
        .file-upload-box.dragover {
            border-color: #2563eb;
            background: #eff6ff;
        }

        .file-upload-box i {
            font-size: 2.5rem;
            color: #3b82f6;
            margin-bottom: 0.75rem;
        }

        .file-upload-box input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        /* Footer */
        footer {
            margin-top: auto;
            background: #0f172a;
            color: #94a3b8;
            padding: 3rem 0 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>

<body>

    <!-- Header Navigation -->
    <nav class="navbar navbar-expand-lg site-navbar py-3 sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <i class="fa-solid fa-store text-primary"></i>
                <span>O-Cart! Careers</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <a href="index.php" class="btn btn-sm btn-outline-light rounded-pill px-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Return to Storefront
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero-banner text-center">
        <div class="container">
            <span
                class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-semibold mb-3">
                <i class="fa-solid fa-briefcase me-1"></i> We Are Hiring!
            </span>
            <h1 class="hero-title">Join Our Dedicated Team</h1>
            <p class="hero-subtitle">
                Explore rewarding job opportunities at O-Cart!. Submit your application and resume directly to
                our HR Recruitment System today!
            </p>

            <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
                <div class="stat-pill">
                    <i class="fa-solid fa-bullhorn fs-5"></i>
                    <span><strong><?= $totalOpenPositions ?></strong> Open Positions</span>
                </div>
                <div class="stat-pill">
                    <i class="fa-solid fa-sitemap fs-5"></i>
                    <span><strong><?= $totalDepartments ?></strong> Active Departments</span>
                </div>
                <div class="stat-pill">
                    <i class="fa-solid fa-bolt fs-5"></i>
                    <span>Fast HR Screening Process</span>
                </div>
            </div>
        </div>
    </header>

    <!-- Filter & Content Container -->
    <main class="container mb-5">

        <!-- Filter Card -->
        <div class="filter-card mb-5">
            <div class="row g-3">
                <div class="col-md-5">
                    <label for="searchKeyword" class="form-label fw-semibold small text-secondary">Search
                        Positions</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i
                                class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="searchKeyword" class="form-control border-start-0"
                            placeholder="Position title, requirements...">
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="filterDept" class="form-label fw-semibold small text-secondary">Department</label>
                    <select id="filterDept" class="form-select">
                        <option value="ALL">All Departments</option>
                        <?php foreach (array_keys($departmentsMap) as $deptName): ?>
                            <option value="<?= htmlspecialchars($deptName) ?>"><?= htmlspecialchars($deptName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filterType" class="form-label fw-semibold small text-secondary">Employment Type</label>
                    <select id="filterType" class="form-select">
                        <option value="ALL">All Employment Types</option>
                        <option value="Full-time">Full-time</option>
                        <option value="Part-time">Part-time</option>
                        <option value="Contractual">Contractual</option>
                        <option value="Probationary">Probationary</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Job Cards Grid -->
        <div class="row g-4" id="jobGrid">
            <?php if (empty($positionsList)): ?>
                <div class="col-12 text-center py-5">
                    <div class="p-5 bg-white rounded-4 border shadow-sm">
                        <i class="fa-solid fa-folder-open text-muted display-4 mb-3"></i>
                        <h4 class="fw-bold">No Open Positions Available</h4>
                        <p class="text-muted">There are currently no active job vacancies. Please check back later!</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($positionsList as $job): ?>
                    <div class="col-md-6 col-lg-4 job-item-col"
                        data-title="<?= strtolower(htmlspecialchars($job['position_name'])) ?>"
                        data-dept="<?= htmlspecialchars($job['department_name']) ?>"
                        data-type="<?= htmlspecialchars($job['employment_type']) ?>"
                        data-req="<?= strtolower(htmlspecialchars($job['requirements'] ?? '')) ?>">

                        <div class="job-card">
                            <div class="job-card-header">
                                <div class="dept-tag">
                                    <i class="fa-solid fa-building-user me-1"></i>
                                    <?= htmlspecialchars($job['department_name']) ?>
                                </div>
                                <h3 class="job-title"><?= htmlspecialchars($job['position_name']) ?></h3>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <span class="type-badge"><i class="fa-regular fa-clock me-1"></i>
                                        <?= htmlspecialchars($job['employment_type']) ?></span>
                                    <span class="slot-badge"><i class="fa-solid fa-user-check me-1"></i>
                                        <?= $job['remaining_slots'] ?>
                                        <?= $job['remaining_slots'] == 1 ? 'Slot Available' : 'Slots Available' ?></span>
                                </div>
                            </div>

                            <div class="job-card-body">
                                <div class="salary-range">
                                    ₱<?= number_format($job['salary_min'], 2) ?> – ₱<?= number_format($job['salary_max'], 2) ?>
                                    <span>/ month</span>
                                </div>

                                <div class="req-preview">
                                    <strong>Requirements & Overview:</strong><br>
                                    <?= !empty($job['requirements']) ? nl2br(htmlspecialchars($job['requirements'])) : 'Standard position requirements apply.' ?>
                                </div>
                            </div>

                            <div class="job-card-footer">
                                <button type="button" class="btn btn-custom-outline flex-grow-1"
                                    onclick="openDetailsModal(<?= htmlspecialchars(json_encode($job)) ?>)">
                                    <i class="fa-solid fa-circle-info me-1"></i> Details
                                </button>
                                <button type="button" class="btn btn-custom-primary flex-grow-1"
                                    onclick="openApplyModal(<?= htmlspecialchars(json_encode($job)) ?>)">
                                    <i class="fa-solid fa-paper-plane me-1"></i> Apply Now
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- No Results Search Message -->
        <div id="noResultsMsg" class="text-center py-5 d-none">
            <div class="p-5 bg-white rounded-4 border shadow-sm">
                <i class="fa-solid fa-magnifying-glass text-muted display-4 mb-3"></i>
                <h4 class="fw-bold">No Matching Positions Found</h4>
                <p class="text-muted">Try adjusting your search keywords or filter options.</p>
                <button class="btn btn-outline-primary rounded-pill px-4" onclick="resetFilters()">Reset Search
                    Filters</button>
            </div>
        </div>

    </main>

    <!-- JOB DETAILS MODAL -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <span class="badge bg-primary text-white mb-2" id="modalDept">Department</span>
                        <h4 class="modal-title fw-bold" id="modalTitle">Position Title</h4>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-4">
                        <div class="col-sm-4">
                            <div class="p-3 bg-light rounded-3 text-center border">
                                <small class="text-muted d-block fw-semibold">Employment Type</small>
                                <strong class="fs-6 text-dark" id="modalType">-</strong>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-3 bg-light rounded-3 text-center border">
                                <small class="text-muted d-block fw-semibold">Monthly Salary Range</small>
                                <strong class="fs-6 text-primary" id="modalSalary">-</strong>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-3 bg-light rounded-3 text-center border">
                                <small class="text-muted d-block fw-semibold">Open Slots</small>
                                <strong class="fs-6 text-success" id="modalSlots">-</strong>
                            </div>
                        </div>
                    </div>

                    <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-list-check me-2 text-primary"></i>
                        Requirements & Duties</h5>
                    <div class="p-3 bg-white border rounded-3 text-secondary mb-4" id="modalReqs"
                        style="white-space: pre-line; line-height: 1.6;">
                    </div>

                    <div
                        class="p-3 bg-primary-subtle rounded-3 border border-primary-subtle d-flex align-items-center justify-content-between">
                        <div>
                            <strong class="text-primary d-block">Ready to Join Our Team?</strong>
                            <small class="text-muted">Prepare your updated PDF resume and submit your details.</small>
                        </div>
                        <button type="button" class="btn btn-primary rounded-pill px-4" id="modalApplyBtn">
                            Apply Now <i class="fa-solid fa-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- APPLICATION FORM MODAL -->
    <div class="modal fade" id="applyModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h4 class="modal-title fw-bold mb-0"><i class="fa-solid fa-user-plus me-2 text-primary"></i>
                            Submit Job Application</h4>
                        <small class="text-slate-400">Position: <strong id="applyPositionTitle"
                                class="text-white"></strong></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form id="applicantForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="submit_application">
                    <input type="hidden" name="position_id" id="applyPositionId">

                    <div class="modal-body">

                        <!-- Alert Box -->
                        <div id="formAlert" class="alert d-none rounded-3" role="alert"></div>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="applyFullName" class="form-label fw-semibold text-dark">Full Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="applyFullName" name="full_name"
                                    placeholder="e.g. Juan De La Cruz" required>
                            </div>

                            <div class="col-md-6">
                                <label for="applyEmail" class="form-label fw-semibold text-dark">Email Address <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i
                                            class="fa-solid fa-envelope text-muted"></i></span>
                                    <input type="email" class="form-control" id="applyEmail" name="email"
                                        placeholder="applicant@gmail.com" required>
                                </div>
                                <small class="text-muted">Must contain exactly one valid '@'</small>
                            </div>

                            <div class="col-md-6">
                                <label for="applyPhone" class="form-label fw-semibold text-dark">Contact Number <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i
                                            class="fa-solid fa-phone text-muted"></i></span>
                                    <input type="tel" class="form-control" id="applyPhone" name="phone"
                                        placeholder="09123456789" maxlength="11" required>
                                </div>
                                <small class="text-muted">11 digits starting with 09 (e.g., 09123456789)</small>
                            </div>

                            <div class="col-md-12">
                                <label for="applyAddress" class="form-label fw-semibold text-dark">Home Address <span
                                        class="text-danger">*</span></label>
                                <textarea class="form-control" id="applyAddress" name="address" rows="2"
                                    placeholder="House No., Street, Barangay, City, Province" required></textarea>
                            </div>

                            <div class="col-md-12">
                                <label for="applyNotes" class="form-label fw-semibold text-dark">Cover Letter /
                                    Additional Information <span class="text-muted fw-normal">(Optional)</span></label>
                                <textarea class="form-control" id="applyNotes" name="notes" rows="2"
                                    placeholder="Tell us briefly about your experience or availability..."></textarea>
                            </div>

                            <!-- Resume PDF Upload -->
                            <div class="col-md-12">
                                <label class="form-label fw-semibold text-dark">Upload Resume (PDF File) <span
                                        class="text-danger">*</span></label>
                                <div class="file-upload-box" id="dropZone">
                                    <i class="fa-solid fa-file-pdf"></i>
                                    <h6 class="fw-bold mb-1 text-dark" id="fileNameDisplay">Click or drag & drop your
                                        PDF resume here</h6>
                                    <p class="text-muted small mb-0">Supported format: <strong>PDF (.pdf)</strong> | Max
                                        size: <strong>5MB</strong></p>
                                    <input type="file" id="resumeInput" name="resume" accept="application/pdf" required
                                        onchange="handleFileSelect(this)">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer bg-light px-4 py-3">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold" id="submitBtn">
                            <i class="fa-solid fa-paper-plane me-1"></i> Submit Resume & Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SUCCESS CONFIRMATION MODAL -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4">
                <div class="mb-3">
                    <div class="d-inline-flex align-items-center justify-content-center bg-success-subtle text-success rounded-circle"
                        style="width: 80px; height: 80px;">
                        <i class="fa-solid fa-circle-check display-4"></i>
                    </div>
                </div>
                <h4 class="fw-bold text-dark mb-2">Application Submitted!</h4>
                <p class="text-muted fs-6 mb-3">
                    Thank you, <strong id="succApplicantName">Applicant</strong>! Your application for
                    <strong id="succPositionName">Position</strong> has been successfully received.
                </p>
                <div class="p-3 bg-light rounded-3 text-start mb-4 border">
                    <small class="text-muted d-block mb-1"><i class="fa-solid fa-circle-info text-primary me-1"></i>
                        What happens next?</small>
                    <ul class="small text-secondary mb-0 ps-3">
                        <li>Your application is now under <strong>Initial Screening</strong> in our HRMS module.</li>
                        <li>Our HR team will review your qualifications and contact you via email or phone for the next
                            steps.</li>
                    </ul>
                </div>
                <button type="button" class="btn btn-success rounded-pill px-5 py-2 fw-semibold w-100"
                    data-bs-dismiss="modal">
                    Done
                </button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container text-center">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                <i class="fa-solid fa-store text-primary fs-5"></i>
                <span class="text-white fw-bold">O-CART!</span>
            </div>
            <p class="small text-slate-400 mb-0">&copy; <?= date('Y') ?> O-CART! HR Recruitment System. All
                rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap 5 & jQuery JS -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let currentJobObject = null;
        const detailsModalInstance = new bootstrap.Modal(document.getElementById('detailsModal'));
        const applyModalInstance = new bootstrap.Modal(document.getElementById('applyModal'));
        const successModalInstance = new bootstrap.Modal(document.getElementById('successModal'));

        // Filter Logic
        function filterJobs() {
            const keyword = $('#searchKeyword').val().toLowerCase().trim();
            const dept = $('#filterDept').val();
            const type = $('#filterType').val();

            let visibleCount = 0;

            $('.job-item-col').each(function () {
                const itemTitle = $(this).data('title');
                const itemDept = $(this).data('dept');
                const itemType = $(this).data('type');
                const itemReq = $(this).data('req');

                const matchesSearch = !keyword || itemTitle.includes(keyword) || itemReq.includes(keyword);
                const matchesDept = dept === 'ALL' || itemDept === dept;
                const matchesType = type === 'ALL' || itemType === type;

                if (matchesSearch && matchesDept && matchesType) {
                    $(this).removeClass('d-none');
                    visibleCount++;
                } else {
                    $(this).addClass('d-none');
                }
            });

            if (visibleCount === 0) {
                $('#noResultsMsg').removeClass('d-none');
            } else {
                $('#noResultsMsg').addClass('d-none');
            }
        }

        $('#searchKeyword, #filterDept, #filterType').on('input change', filterJobs);

        function resetFilters() {
            $('#searchKeyword').val('');
            $('#filterDept').val('ALL');
            $('#filterType').val('ALL');
            filterJobs();
        }

        // Open Job Details Modal
        function openDetailsModal(job) {
            currentJobObject = job;
            $('#modalTitle').text(job.position_name);
            $('#modalDept').text(job.department_name);
            $('#modalType').text(job.employment_type);
            $('#modalSalary').text('₱' + parseFloat(job.salary_min).toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' - ₱' + parseFloat(job.salary_max).toLocaleString(undefined, { minimumFractionDigits: 2 }));
            $('#modalSlots').text(job.remaining_slots + (job.remaining_slots === 1 ? ' Slot' : ' Slots'));
            $('#modalReqs').text(job.requirements || 'No specific requirements listed.');

            $('#modalApplyBtn').off('click').on('click', function () {
                detailsModalInstance.hide();
                setTimeout(() => openApplyModal(job), 300);
            });

            detailsModalInstance.show();
        }

        // Open Application Form Modal
        function openApplyModal(job) {
            currentJobObject = job;
            $('#applyPositionId').val(job.position_id);
            $('#applyPositionTitle').text(job.position_name + ' (' + job.department_name + ')');

            // Reset form
            $('#applicantForm')[0].reset();
            $('#fileNameDisplay').html('Click or drag & drop your PDF resume here');
            $('#formAlert').addClass('d-none').removeClass('alert-danger alert-success').html('');

            applyModalInstance.show();
        }

        // Handle File Drag & Display
        function handleFileSelect(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
                    alert('Please select a valid PDF file.');
                    input.value = '';
                    $('#fileNameDisplay').html('Click or drag & drop your PDF resume here');
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size exceeds 5MB limit.');
                    input.value = '';
                    $('#fileNameDisplay').html('Click or drag & drop your PDF resume here');
                    return;
                }
                $('#fileNameDisplay').html('<i class="fa-solid fa-file-circle-check text-success me-2"></i><strong>' + file.name + '</strong> (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB)');
            }
        }

        // Form Submission AJAX
        $('#applicantForm').on('submit', function (e) {
            e.preventDefault();

            const phone = $('#applyPhone').val().trim();
            const email = $('#applyEmail').val().trim();

            if (!/^09\d{9}$/.test(phone)) {
                showAlert('Phone number must start with 09 and contain exactly 11 digits.', 'danger');
                return;
            }

            if (!email.includes('@') || email.split('@').length !== 2) {
                showAlert('Please provide a valid email address with exactly one @.', 'danger');
                return;
            }

            const formData = new FormData(this);
            const submitBtn = $('#submitBtn');
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></i>Submitting...');

            $.ajax({
                url: 'jobs.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function (response) {
                    submitBtn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane me-1"></i> Submit Resume & Application');

                    if (response.status === 'success') {
                        applyModalInstance.hide();
                        $('#succApplicantName').text($('#applyFullName').val().trim());
                        $('#succPositionName').text(response.position_name);
                        successModalInstance.show();
                        $('#applicantForm')[0].reset();
                    } else {
                        showAlert(response.message || 'An error occurred while submitting.', 'danger');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Submission Error:', xhr.responseText || error);
                    submitBtn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane me-1"></i> Submit Resume & Application');
                    let errMsg = 'Server connection error. Please try again.';
                    if (xhr.responseText) {
                        try {
                            const parsed = JSON.parse(xhr.responseText);
                            if (parsed && parsed.message) errMsg = parsed.message;
                        } catch (e) { }
                    }
                    showAlert(errMsg, 'danger');
                }
            });
        });

        function showAlert(msg, type) {
            $('#formAlert')
                .removeClass('d-none alert-danger alert-success')
                .addClass('alert-' + type)
                .html('<i class="fa-solid fa-circle-exclamation me-2"></i>' + msg);
        }
    </script>
</body>

</html>