<?php
require_once '../Model/database.php';
require_once '../Model/logger.php';

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

// Auto-create uploads directories
define('ATTENDANCE_UPLOAD_DIR', __DIR__ . '/uploads/attendance/');
define('ATTENDANCE_UPLOAD_URL', 'uploads/attendance/');
if(!is_dir(ATTENDANCE_UPLOAD_DIR)){
    mkdir(ATTENDANCE_UPLOAD_DIR, 0755, true);
}

// Ensure the photo column exists in the attendance table
$checkCol = mysqli_query($conn, "SHOW COLUMNS FROM attendance LIKE 'photo'");
if (mysqli_num_rows($checkCol) == 0) {
    mysqli_query($conn, "ALTER TABLE attendance ADD COLUMN photo VARCHAR(255) DEFAULT NULL");
}

// Handle AJAX POST Request (Attendance Log)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_attendance') {
    $emp_no = mysqli_real_escape_string($conn, trim($_POST['employee_no']));
    $type = mysqli_real_escape_string($conn, trim($_POST['type'])); // 'in' or 'out'
    $image_data = $_POST['image'] ?? ''; // Base64 image
    
    // Find employee
    $emp_q = mysqli_query($conn, "SELECT employee_id, full_name, photo FROM employees WHERE employee_no = '$emp_no' LIMIT 1");
    $emp = mysqli_fetch_assoc($emp_q);
    
    if (!$emp) {
        echo json_encode(['status' => 'error', 'message' => "Invalid QR Code: Employee code '$emp_no' not found."]);
        exit;
    }
    
    $employee_id = $emp['employee_id'];
    $full_name = $emp['full_name'];
    $emp_photo = $emp['photo'];
    $today = date('Y-m-d');
    $current_time = date('H:i:s');
    
    // Save base64 snapshot image
    $photo_filename = '';
    if (!empty($image_data)) {
        $image_parts = explode(";base64,", $image_data);
        if (count($image_parts) === 2) {
            $image_type_aux = explode("image/", $image_parts[0]);
            $image_type = $image_type_aux[1] ?? 'jpg';
            $image_base64 = base64_decode($image_parts[1]);
            
            $photo_filename = 'att_' . $emp_no . '_' . date('Ymd_His') . '.' . $image_type;
            file_put_contents(ATTENDANCE_UPLOAD_DIR . $photo_filename, $image_base64);
        }
    }
    
    // Check if record exists for today
    $att_q = mysqli_query($conn, "SELECT * FROM attendance WHERE employee_id = $employee_id AND date = '$today' LIMIT 1");
    $att = mysqli_fetch_assoc($att_q);
    
    if ($type === 'in') {
        if ($att) {
            echo json_encode([
                'status' => 'error', 
                'message' => "You have already Timed In today at " . date('h:i A', strtotime($att['time_in'])),
                'emp_name' => $full_name,
                'emp_photo' => $emp_photo
            ]);
            exit;
        }
        
        // Insert new record
        $status = 'Present';
        $grace_time = '09:01:00'; // Standard start grace limit
        if ($current_time >= $grace_time) {
            $status = 'Late';
        }
        
        $insert = mysqli_query($conn, "
            INSERT INTO attendance (employee_id, date, time_in, status, photo)
            VALUES ($employee_id, '$today', '$current_time', '$status', '$photo_filename')
        ");
        
        if ($insert) {
            echo json_encode([
                'status' => 'success', 
                'message' => "Timed In Successfully!", 
                'emp_name' => $full_name,
                'emp_photo' => $emp_photo,
                'time' => date('h:i A', strtotime($current_time)),
                'type' => 'Time In'
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($conn)]);
        }
    } else { // type === 'out'
        if (!$att) {
            echo json_encode([
                'status' => 'error', 
                'message' => "No Time In found for today. You must Time In first.",
                'emp_name' => $full_name,
                'emp_photo' => $emp_photo
            ]);
            exit;
        }
        
        if ($att['time_out'] !== NULL) {
            echo json_encode([
                'status' => 'error', 
                'message' => "You have already Timed Out today at " . date('h:i A', strtotime($att['time_out'])),
                'emp_name' => $full_name,
                'emp_photo' => $emp_photo
            ]);
            exit;
        }
        
        // Calculate hours worked
        $time_in = new DateTime($att['time_in']);
        $time_out = new DateTime($current_time);
        $interval = $time_in->diff($time_out);
        $hours_worked = $interval->h + ($interval->i / 60);
        $hours_worked = round($hours_worked, 2);
        
        // Cap overtime hours
        $overtime_hours = 0.00;
        if ($hours_worked > 8.00) {
            $overtime_hours = round($hours_worked - 8.00, 2);
        }
        
        $photo_sql = "";
        if (!empty($photo_filename)) {
            $photo_sql = ", photo = '$photo_filename'";
        }
        
        $update = mysqli_query($conn, "
            UPDATE attendance 
            SET time_out = '$current_time', hours_worked = $hours_worked, overtime_hours = $overtime_hours $photo_sql
            WHERE attendance_id = {$att['attendance_id']}
        ");
        
        if ($update) {
            echo json_encode([
                'status' => 'success', 
                'message' => "Timed Out Successfully!", 
                'emp_name' => $full_name,
                'emp_photo' => $emp_photo,
                'time' => date('h:i A', strtotime($current_time)),
                'hours' => $hours_worked,
                'type' => 'Time Out'
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($conn)]);
        }
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Terminal — Sari-Sari Store</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: #f1f5f9;
            font-family: 'Segoe UI', system-ui, sans-serif;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .header-section {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(10px);
        }
        .terminal-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .mode-selector {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            justify-content: center;
        }
        .mode-btn {
            flex: 1;
            max-width: 250px;
            padding: 18px;
            border-radius: 16px;
            border: 2px solid rgba(255, 255, 255, 0.05);
            background: rgba(30, 41, 59, 0.7);
            color: #94a3b8;
            font-weight: 700;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }
        .mode-btn i {
            font-size: 32px;
        }
        .mode-btn:hover {
            background: rgba(30, 41, 59, 0.9);
            color: #f1f5f9;
        }
        .mode-btn.active-in {
            border-color: #22c55e;
            color: #22c55e;
            background: rgba(34, 197, 94, 0.15);
            box-shadow: 0 0 20px rgba(34, 197, 94, 0.3);
            transform: translateY(-2px);
        }
        .mode-btn.active-out {
            border-color: #f97316;
            color: #f97316;
            background: rgba(249, 115, 22, 0.15);
            box-shadow: 0 0 20px rgba(249, 115, 22, 0.3);
            transform: translateY(-2px);
        }
        .scanner-card {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .scanner-viewport-wrapper {
            position: relative;
            width: 100%;
            max-width: 480px;
            border-radius: 20px;
            overflow: hidden;
            border: 4px solid #475569;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
            transition: border-color 0.3s ease;
            background: #000;
        }
        .scanner-viewport-wrapper.in-active {
            border-color: #22c55e;
            box-shadow: 0 0 30px rgba(34, 197, 94, 0.2);
        }
        .scanner-viewport-wrapper.out-active {
            border-color: #f97316;
            box-shadow: 0 0 30px rgba(249, 115, 22, 0.2);
        }
        #reader {
            width: 100%;
            background: #000;
        }
        .scan-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        .scan-target-box {
            width: 250px;
            height: 250px;
            border: 2px dashed rgba(255, 255, 255, 0.4);
            border-radius: 16px;
            position: relative;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);
            transition: border-color 0.3s ease;
        }
        .in-active .scan-target-box {
            border-color: #22c55e;
        }
        .out-active .scan-target-box {
            border-color: #f97316;
        }
        .scan-line {
            position: absolute;
            width: 100%;
            height: 4px;
            top: 0;
            left: 0;
            opacity: 0.8;
            animation: scanning 2.5s infinite linear;
        }
        .in-active .scan-line {
            background: linear-gradient(to right, transparent, #22c55e, transparent);
            box-shadow: 0 0 8px #22c55e;
        }
        .out-active .scan-line {
            background: linear-gradient(to right, transparent, #f97316, transparent);
            box-shadow: 0 0 8px #f97316;
        }
        @keyframes scanning {
            0% { top: 0%; }
            50% { top: 100%; }
            100% { top: 0%; }
        }
        .clock-section {
            text-align: center;
            margin-top: 25px;
        }
        .digital-clock {
            font-size: 42px;
            font-weight: 800;
            font-family: monospace;
            letter-spacing: 2px;
            color: #f8fafc;
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.2);
            line-height: 1.1;
        }
        .digital-date {
            font-size: 16px;
            color: #94a3b8;
            font-weight: 600;
            margin-top: 4px;
        }
        .footer-section {
            padding: 15px;
            text-align: center;
            font-size: 12px;
            color: #475569;
            border-top: 1px solid rgba(255, 255, 255, 0.02);
            background: rgba(15, 23, 42, 0.8);
        }
        .swal2-att-modal {
            border-radius: 20px !important;
            background: #1e293b !important;
            color: #f1f5f9 !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
        }
        .swal2-att-title {
            color: #f8fafc !important;
            font-weight: 700 !important;
        }
        .swal2-att-html {
            color: #cbd5e1 !important;
            width: 100% !important;
        }
        .att-preview-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin: 15px 0;
            width: 100%;
        }
        .att-preview-circle {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #2563eb;
        }
        .att-preview-initials {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: #2563eb;
            color: white;
            font-size: 32px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <div class="header-section">
        <h4 class="fw-bold m-0 text-white" style="letter-spacing: 0.5px;">
            <i class="bi bi-shop me-2 text-primary"></i>SARI-SARI STORE
        </h4>
        <small class="text-muted text-uppercase tracking-wider fs-7">Attendance Scanner Terminal</small>
    </div>

    <!-- MAIN BODY -->
    <div class="terminal-container">
        
        <!-- Mode Selectors -->
        <div class="mode-selector">
            <button class="mode-btn active-in" id="btnModeIn" onclick="setMode('in')">
                <i class="bi bi-box-arrow-in-right"></i>
                TIME IN
            </button>
            <button class="mode-btn" id="btnModeOut" onclick="setMode('out')">
                <i class="bi bi-box-arrow-left"></i>
                TIME OUT
            </button>
        </div>

        <!-- Scanner Card -->
        <div class="scanner-card">
            <div class="scanner-viewport-wrapper in-active" id="viewportWrapper">
                <!-- Camera viewfinder -->
                <div id="reader"></div>
                <!-- Dynamic scan lines overlay -->
                <div class="scan-overlay">
                    <div class="scan-target-box">
                        <div class="scan-line"></div>
                    </div>
                </div>
            </div>

            <!-- Digital Clock -->
            <div class="clock-section">
                <div class="digital-clock" id="digitalClock">00:00:00 AM</div>
                <div class="digital-date" id="digitalDate">Friday, July 10, 2026</div>
            </div>
        </div>

    </div>

    <!-- FOOTER -->
    <div class="footer-section">
        &copy; 2026 Sari-Sari Store HRMS. Secure authentication terminal.
    </div>

    <script>
        let currentMode = 'in'; // 'in' or 'out'
        let html5QrcodeScanner = null;
        let isProcessing = false;

        // Sound feedback system using Web Audio API
        function playSound(type) {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioCtx.createOscillator();
                const gainNode = audioCtx.createGain();
                oscillator.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                
                if (type === 'success') {
                    oscillator.type = 'sine';
                    oscillator.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5 note
                    gainNode.gain.setValueAtTime(0.08, audioCtx.currentTime);
                    oscillator.start();
                    oscillator.stop(audioCtx.currentTime + 0.12);
                    
                    setTimeout(() => {
                        const osc2 = audioCtx.createOscillator();
                        const gain2 = audioCtx.createGain();
                        osc2.connect(gain2);
                        gain2.connect(audioCtx.destination);
                        osc2.type = 'sine';
                        osc2.frequency.setValueAtTime(880, audioCtx.currentTime); // A5 note
                        gain2.gain.setValueAtTime(0.08, audioCtx.currentTime);
                        osc2.start();
                        osc2.stop(audioCtx.currentTime + 0.18);
                    }, 140);
                } else {
                    oscillator.type = 'sawtooth';
                    oscillator.frequency.setValueAtTime(140, audioCtx.currentTime); // Low buzz
                    gainNode.gain.setValueAtTime(0.15, audioCtx.currentTime);
                    oscillator.start();
                    oscillator.stop(audioCtx.currentTime + 0.35);
                }
            } catch (e) {
                console.error("Audio API blocked or not supported:", e);
            }
        }

        // Live Clock
        function updateTerminalTime() {
            const now = new Date();
            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            $('#digitalClock').text(`${hours}:${minutes}:${seconds} ${ampm}`);

            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            $('#digitalDate').text(now.toLocaleDateString('en-US', options));
        }
        setInterval(updateTerminalTime, 1000);
        updateTerminalTime();

        // Switch modes (Time-In / Time-Out)
        function setMode(mode) {
            if (isProcessing) return;
            currentMode = mode;
            if (mode === 'in') {
                $('#btnModeIn').addClass('active-in');
                $('#btnModeOut').removeClass('active-out');
                $('#viewportWrapper').addClass('in-active').removeClass('out-active');
            } else {
                $('#btnModeOut').addClass('active-out');
                $('#btnModeIn').removeClass('active-in');
                $('#viewportWrapper').addClass('out-active').removeClass('in-active');
            }
        }

        // Start Camera scanner
        function startScanner() {
            html5QrcodeScanner = new Html5Qrcode("reader");
            const config = { 
                fps: 15, 
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0
            };
            
            html5QrcodeScanner.start(
                { facingMode: "user" }, // Facing Mode user
                config,
                onScanSuccess
            ).catch(err => {
                console.error("Camera access error:", err);
                Swal.fire({
                    icon: 'error',
                    title: 'Camera Access Error',
                    text: 'Unable to access your webcam. Please verify permissions.',
                    confirmButtonColor: '#2563eb'
                });
            });
        }

        // Capture snapshot frame from video feed
        function captureSnapshot() {
            const video = document.querySelector('#reader video');
            if (!video) return null;
            
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;
            const ctx = canvas.getContext('2d');
            
            // Mirror flip snapshot horizontally
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            return canvas.toDataURL('image/jpeg', 0.85);
        }

        // Initials helper
        function getInitials(name) {
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

        // Scan Success handler
        function onScanSuccess(decodedText, decodedResult) {
            if (isProcessing) return;
            isProcessing = true;

            // Immediately capture attendance verification snapshot
            const snapshot = captureSnapshot();
            
            // Play initial trigger chime
            playSound('success');

            // Send via AJAX to same page
            $.ajax({
                url: 'qr_attendance.php',
                type: 'POST',
                data: {
                    action: 'log_attendance',
                    employee_no: decodedText,
                    type: currentMode,
                    image: snapshot
                },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        playSound('success');
                        
                        let photoHtml = '';
                        if(res.emp_photo && res.emp_photo !== '') {
                            photoHtml = `<img src="uploads/employees/${res.emp_photo}" class="att-preview-circle">`;
                        } else {
                            photoHtml = `<div class="att-preview-initials">${getInitials(res.emp_name)}</div>`;
                        }

                        let textHtml = `
                            <div class="att-preview-container">
                                ${photoHtml}
                                <div class="text-start">
                                    <h5 class="fw-bold mb-1 text-white">${res.emp_name}</h5>
                                    <div class="badge bg-secondary mb-1">${decodedText}</div>
                                    <div class="text-success fw-bold"><i class="bi bi-clock me-1"></i>${res.type} at ${res.time}</div>
                                    ${res.hours ? `<div class="text-muted mt-1 fs-7">Hours worked today: <strong>${res.hours}h</strong></div>` : ''}
                                </div>
                            </div>
                        `;

                        Swal.fire({
                            title: 'Log Confirmed',
                            html: textHtml,
                            icon: 'success',
                            customClass: {
                                popup: 'swal2-att-modal',
                                title: 'swal2-att-title',
                                htmlContainer: 'swal2-att-html'
                            },
                            showConfirmButton: false,
                            timer: 3500,
                            timerProgressBar: true
                        }).then(() => {
                            isProcessing = false;
                        });
                    } else {
                        playSound('error');
                        
                        let photoHtml = '';
                        if(res.emp_photo && res.emp_photo !== '') {
                            photoHtml = `<img src="uploads/employees/${res.emp_photo}" class="att-preview-circle" style="border-color:#dc3545;">`;
                        } else {
                            photoHtml = `<div class="att-preview-initials" style="background:#dc3545;">${getInitials(res.emp_name || "?")}</div>`;
                        }

                        let errorHtml = `
                            <div class="att-preview-container">
                                ${res.emp_name ? photoHtml : ''}
                                <div class="text-start">
                                    <h5 class="fw-bold mb-1 text-white">${res.emp_name || 'Invalid employee'}</h5>
                                    <div class="text-danger fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>Error</div>
                                    <div class="text-muted mt-1 fs-7">${res.message}</div>
                                </div>
                            </div>
                        `;

                        Swal.fire({
                            title: 'Scan Failed',
                            html: errorHtml,
                            icon: 'error',
                            customClass: {
                                popup: 'swal2-att-modal',
                                title: 'swal2-att-title',
                                htmlContainer: 'swal2-att-html'
                            },
                            showConfirmButton: false,
                            timer: 4000,
                            timerProgressBar: true
                        }).then(() => {
                            isProcessing = false;
                        });
                    }
                },
                error: function() {
                    playSound('error');
                    Swal.fire({
                        title: 'Server Error',
                        text: 'Failed to record attendance. Please try again.',
                        icon: 'error',
                        confirmButtonColor: '#2563eb'
                    }).then(() => {
                        isProcessing = false;
                    });
                }
            });
        }

        $(document).ready(function() {
            startScanner();
        });
    </script>
</body>
</html>
