<?php
error_reporting(E_ALL & ~E_NOTICE);
$db_path = __DIR__ . '/../../Model/database.php';
if (!file_exists($db_path)) {
    $db_path = __DIR__ . '/../Model/database.php';
}
require_once($db_path);

$emp_user = intval($_SESSION['user_id'] ?? $_SESSION['emp_id'] ?? 0);
$account_type = isset($_SESSION['user_id']) ? 'User' : 'Employee';

if ($emp_user === 0) {
    die("Unauthorized access.");
}

$message = '';
$msg_type = '';

// Handle Signature Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_signature') {
    $signature = $_POST['e_signature'] ?? '';
    
    if (empty($signature)) {
        $message = "Please provide a signature.";
        $msg_type = "danger";
    } else {
        // Validate if it is a valid data URI image
        if (preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $signature)) {
            $signature_escaped = mysqli_real_escape_string($conn, $signature);
            
            // Insert or Update
            $q = "INSERT INTO registered_signatures (account_id, account_type, e_signature) 
                  VALUES ($emp_user, '$account_type', '$signature_escaped')
                  ON DUPLICATE KEY UPDATE e_signature = VALUES(e_signature), updated_at = NOW()";
            
            if (mysqli_query($conn, $q)) {
                $message = "Signature successfully registered.";
                $msg_type = "success";
            } else {
                $message = "Database error: " . mysqli_error($conn);
                $msg_type = "danger";
            }
        } else {
            $message = "Invalid signature image format. Only clear PNG/JPEG drawings or uploads are permitted.";
            $msg_type = "danger";
        }
    }
}

// Fetch current signature
$q_sig = mysqli_query($conn, "SELECT e_signature FROM registered_signatures WHERE account_id = $emp_user AND account_type = '$account_type' LIMIT 1");
$row_sig = mysqli_fetch_assoc($q_sig);
$current_signature = $row_sig ? $row_sig['e_signature'] : null;

$emp_name = $_SESSION['emp_name'] ?? $_SESSION['full_name'] ?? 'Finance Staff';
$emp_role = $_SESSION['emp_role'] ?? 'Finance Specialist';

?>
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="mb-0 fw-bold" style="color: #2b1055;"><i class="bi bi-person-badge me-2"></i>Finance Profile & Signature</h4>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Profile Info & Current Signature -->
        <div class="col-md-5 mb-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 text-center">
                    <div class="mb-4">
                        <div class="rounded-circle bg-light mx-auto d-flex align-items-center justify-content-center" style="width:100px; height:100px; font-size:40px; color:#2b1055;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($emp_name) ?></h5>
                    <p class="text-muted mb-4"><?= htmlspecialchars($emp_role) ?></p>
                    <hr>
                    
                    <h6 class="fw-bold mb-3 mt-4">Registered Signature</h6>
                    <?php if ($current_signature): ?>
                        <div class="p-3 bg-light border rounded mb-3 text-center">
                            <img src="<?= htmlspecialchars($current_signature) ?>" style="max-height:100px; max-width:300px; border-bottom:1px solid #000;" alt="Registered Signature">
                        </div>
                        <div class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Signature Registered</div>
                    <?php else: ?>
                        <div class="p-4 bg-light border rounded mb-3 text-center text-muted border-danger">
                            <i class="bi bi-x-circle-fill text-danger fs-3 mb-2 d-block"></i>
                            No Signature Registered
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Signature Registration / Update -->
        <div class="col-md-7 mb-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><?= $current_signature ? 'Change Registered Signature' : 'Register E-Signature' ?></h5>
                    <p class="text-muted" style="font-size:14px;">Use the options below to legally register your electronic signature for formal document authorization. You can either draw it using your mouse/touchscreen or upload a clear image.</p>
                    
                    <div class="mt-4 p-3 border rounded text-center" style="background:#fcfcfc;">
                        <ul class="nav nav-pills justify-content-center mb-3 fs-6" id="regSignatureTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active py-1 px-3" id="draw-reg-tab" data-bs-toggle="tab" data-bs-target="#draw-reg-sig" type="button" role="tab">Draw Signature</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link py-1 px-3" id="upload-reg-tab" data-bs-toggle="tab" data-bs-target="#upload-reg-sig" type="button" role="tab">Upload Image</button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="regSignatureTabContent">
                            <div class="tab-pane fade show active" id="draw-reg-sig" role="tabpanel">
                                <div class="mx-auto" style="max-width:350px;">
                                    <canvas id="regSignaturePad" width="320" height="120" style="border: 2px dashed #0d6efd; border-radius: 8px; background: #fff; cursor: crosshair; touch-action: none;"></canvas>
                                    <div class="mt-2 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearRegSignature()">Clear Canvas</button>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="upload-reg-sig" role="tabpanel">
                                <div class="mx-auto" style="max-width:350px;">
                                    <input class="form-control form-control-sm" type="file" id="regSigImageUpload" accept="image/png, image/jpeg" onchange="handleRegSignatureUpload(event)">
                                    <div class="mt-2 border rounded" style="height:120px; display:flex; align-items:center; justify-content:center; background:#fff;">
                                        <img id="regSigImagePreview" src="" style="max-height:100px; max-width:300px; display:none;" alt="Signature Preview">
                                        <span id="regSigUploadPlaceholder" class="text-muted" style="font-size:12px;">Image preview will appear here</span>
                                    </div>
                                    <p class="text-muted mt-2 mb-0" style="font-size:11px;">* Please upload a clear signature on a plain background (PNG/JPG only).</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form id="saveRegSignatureForm" class="mt-4 text-end">
                        <input type="hidden" name="action" value="save_signature">
                        <input type="hidden" name="e_signature" id="reg_e_signature">
                        <button type="submit" class="btn btn-primary fw-bold px-4 rounded-3 shadow-sm">
                            <i class="bi bi-save me-2"></i>Save Signature
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Prevent drawing from scrolling on touch devices
function preventBehavior(e) { e.preventDefault(); };
document.getElementById('regSignaturePad').addEventListener('touchmove', preventBehavior, {passive: false});

// Canvas Setup
let regCanvas = document.getElementById('regSignaturePad');
let regCtx = regCanvas.getContext('2d');
regCtx.lineWidth = 2;
regCtx.strokeStyle = '#000';
let isDrawingReg = false;

regCanvas.addEventListener('mousedown', startDrawingReg);
regCanvas.addEventListener('mousemove', drawReg);
regCanvas.addEventListener('mouseup', stopDrawingReg);
regCanvas.addEventListener('mouseout', stopDrawingReg);

regCanvas.addEventListener('touchstart', function(e) { e.preventDefault(); startDrawingReg(e.touches[0]); }, {passive: false});
regCanvas.addEventListener('touchmove', function(e) { e.preventDefault(); drawReg(e.touches[0]); }, {passive: false});
regCanvas.addEventListener('touchend', stopDrawingReg);

function getPosReg(evt) {
    const rect = regCanvas.getBoundingClientRect();
    return {
        x: evt.clientX - rect.left,
        y: evt.clientY - rect.top
    };
}

function startDrawingReg(e) {
    isDrawingReg = true;
    const pos = getPosReg(e);
    regCtx.beginPath();
    regCtx.moveTo(pos.x, pos.y);
}

function drawReg(e) {
    if (!isDrawingReg) return;
    const pos = getPosReg(e);
    regCtx.lineTo(pos.x, pos.y);
    regCtx.stroke();
}

function stopDrawingReg() {
    if (isDrawingReg) {
        isDrawingReg = false;
        document.getElementById('reg_e_signature').value = regCanvas.toDataURL();
    }
}

function clearRegSignature() {
    regCtx.clearRect(0, 0, regCanvas.width, regCanvas.height);
    document.getElementById('reg_e_signature').value = '';
}

function handleRegSignatureUpload(event) {
    const file = event.target.files[0];
    if (file) {
        // Validate size (max 2MB)
        if (file.size > 2 * 1024 * 1024) {
            Swal.fire('File Too Large', 'Signature image must be less than 2MB.', 'error');
            event.target.value = '';
            clearRegUploadPreview();
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('regSigImagePreview').src = e.target.result;
            document.getElementById('regSigImagePreview').style.display = 'block';
            document.getElementById('regSigUploadPlaceholder').style.display = 'none';
            document.getElementById('reg_e_signature').value = e.target.result;
        }
        reader.readAsDataURL(file);
    } else {
        clearRegUploadPreview();
    }
}

function clearRegUploadPreview() {
    document.getElementById('regSigImagePreview').style.display = 'none';
    document.getElementById('regSigUploadPlaceholder').style.display = 'block';
    document.getElementById('reg_e_signature').value = '';
}

function getFinanceProfileUrl() {
    return window.location.pathname.toLowerCase().includes('/finance_employee/') ? 'finance_signature_profile.php' : 'Finance_employee/finance_signature_profile.php';
}

$('#saveRegSignatureForm').on('submit', function(e){
    e.preventDefault();
    
    var isUploadActive = document.getElementById('upload-reg-tab').classList.contains('active');
    if (isUploadActive && !document.getElementById('regSigImageUpload').files[0] && !document.getElementById('reg_e_signature').value) {
        Swal.fire('Signature Required', 'Please upload a signature image.', 'warning');
        return;
    } else if (!isUploadActive && !document.getElementById('reg_e_signature').value) {
        Swal.fire('Signature Required', 'Please draw your signature on the canvas.', 'warning');
        return;
    }

    <?php if ($current_signature): ?>
    Swal.fire({
        title: 'Replace Signature?',
        text: "Are you sure you want to replace your registered signature? (Previously signed documents will not be affected.)",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, proceed'
    }).then((result) => {
        if (result.isConfirmed) {
            promptPasswordForSignature();
        }
    });
    <?php else: ?>
    promptPasswordForSignature();
    <?php endif; ?>
});

function promptPasswordForSignature() {
    Swal.fire({
        title: 'Authentication Required',
        text: 'Please enter your password to save your signature:',
        input: 'password',
        inputAttributes: {
            autocapitalize: 'off',
            autocorrect: 'off'
        },
        showCancelButton: true,
        confirmButtonText: 'Verify & Save',
        showLoaderOnConfirm: true,
        preConfirm: (password) => {
            if (!password) {
                Swal.showValidationMessage('Password is required');
                return false;
            }
            
            const verifyUrl = window.location.pathname.toLowerCase().includes('/finance_employee/') ? '../verify_password.php' : 'verify_password.php';
            
            return $.post(verifyUrl, { password: password })
                .then(response => {
                    if (response.trim() !== 'success') {
                        throw new Error(response.replace('error:', '').trim() || 'Authentication failed');
                    }
                    return true;
                })
                .catch(error => {
                    Swal.showValidationMessage(error.message || 'Invalid password');
                });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            processSaveSignature();
        }
    });
}

function processSaveSignature() {
    const btn = $('#saveRegSignatureForm').find('button[type="submit"]');
    const originalText = btn.html();
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');

    $.post(getFinanceProfileUrl(), $('#saveRegSignatureForm').serialize(), function(response){
        $("#content").html(response);
    }).fail(function(){
        btn.prop('disabled', false).html(originalText);
        Swal.fire('Error', 'Failed to save signature. Please try again.', 'error');
    });
}
</script>
