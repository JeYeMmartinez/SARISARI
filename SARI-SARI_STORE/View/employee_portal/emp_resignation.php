<?php
session_start();
require_once '../../Model/database.php';
require_once '../../Model/logger.php';

if(!isset($_SESSION['emp_id'])){
    exit("Unauthorized");
}

$emp_id   = $_SESSION['emp_id'];
$emp_name = $_SESSION['emp_name'];

$success_msg = '';
$error_msg = '';

// Check if there is an active resignation for this employee
$res_check = mysqli_query($conn, "
    SELECT r.*, u.full_name AS processor_name 
    FROM resignations r
    LEFT JOIN users u ON r.processed_by = u.user_id
    WHERE r.employee_id = $emp_id
    ORDER BY r.created_at DESC
    LIMIT 1
");
$resignation = mysqli_fetch_assoc($res_check);

if (isset($_POST['action']) && $_POST['action'] === 'file_resignation') {
    $resignation_type = mysqli_real_escape_string($conn, $_POST['resignation_type']);
    $date_filed       = date('Y-m-d');
    $last_day         = mysqli_real_escape_string($conn, $_POST['last_day']);
    $reason           = mysqli_real_escape_string($conn, trim($_POST['reason']));

    // Prevent duplicates
    if ($resignation && in_array($resignation['status'], ['Pending', 'Acknowledged'])) {
        $error_msg = "You already have an active resignation request on file.";
    } else {
        $q = mysqli_query($conn, "
            INSERT INTO resignations (employee_id, resignation_type, date_filed, last_day, reason, status, created_by)
            VALUES ($emp_id, '$resignation_type', '$date_filed', '$last_day', '$reason', 'Pending', 1)
        ");

        if ($q) {
            logAction($conn, 1, 'Create', 'resignations', mysqli_insert_id($conn), "Employee $emp_name filed a resignation letter. Last Day: $last_day");
            $success_msg = "Resignation letter submitted successfully. HR Operations will schedule an exit interview shortly.";
        } else {
            $error_msg = "Failed to submit resignation: " . mysqli_error($conn);
        }
    }
    
    ob_clean();
    if (!empty($error_msg)) {
        echo "error: " . $error_msg;
    } else {
        echo "success: " . $success_msg;
    }
    exit();
}
?>
<style>
.badge-pending { background: #fef3c7; color: #92400e; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
.badge-acknowledged { background: #e0e7ff; color: #3730a3; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
.badge-approved { background: #d1fae5; color: #065f46; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
.badge-rejected { background: #fee2e2; color: #991b1b; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
</style>

<div class="animate__animated animate__fadeIn">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color: #0f5132;">Resignation Filing</h4>
            <small class="text-muted">File a voluntary resignation letter or review exit clearance status</small>
        </div>
    </div>

    <div class="row g-4">
        
        <?php if ($resignation && in_array($resignation['status'], ['Pending', 'Acknowledged', 'Approved'])): 
            $statusClass = 'badge-pending';
            if ($resignation['status'] === 'Acknowledged') $statusClass = 'badge-acknowledged';
            elseif ($resignation['status'] === 'Approved') $statusClass = 'badge-approved';
        ?>
            <!-- RESIGNATION STATUS CARD -->
            <div class="col-lg-8 mx-auto">
                <div class="page-card border-top border-success border-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-file-earmark-person me-2 text-success"></i>Active Resignation Letter</h5>
                        <span class="<?= $statusClass; ?>"><?= $resignation['status']; ?></span>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle mb-4">
                            <tbody>
                                <tr>
                                    <td class="text-muted ps-0" style="width: 160px;">Resignation Type:</td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($resignation['resignation_type']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Date Filed:</td>
                                    <td class="fw-semibold text-dark"><?= date('F j, Y', strtotime($resignation['date_filed'])); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Proposed Last Day:</td>
                                    <td class="fw-bold text-success"><?= date('F j, Y', strtotime($resignation['last_day'])); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Reason for Leaving:</td>
                                    <td class="text-muted"><p class="mb-0 bg-light p-3 rounded" style="font-size: 13.5px;"><?= nl2br(htmlspecialchars($resignation['reason'])); ?></p></td>
                                </tr>
                                <?php if (!empty($resignation['remarks'])): ?>
                                <tr>
                                    <td class="text-muted ps-0">HR Remarks:</td>
                                    <td class="text-muted"><p class="mb-0 bg-success bg-opacity-10 p-3 text-success rounded" style="font-size: 13.5px;"><strong>Processed by <?= htmlspecialchars($resignation['processor_name'] ?? 'HR Admin'); ?>:</strong><br><?= nl2br(htmlspecialchars($resignation['remarks'])); ?></p></td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-secondary py-3 px-4 border-0 rounded-4" style="background:#e9ecef; font-size:13px; color:#495057;">
                        <i class="bi bi-info-circle-fill text-secondary me-2"></i>
                        Once submitted, your resignation request cannot be canceled or updated via the employee portal. Please reach out to your HR operations supervisor for exit clearance inquiries.
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- FILE RESIGNATION FORM -->
            <div class="col-lg-7">
                <div class="page-card">
                    <h5 class="fw-bold text-success mb-3 pb-2 border-bottom"><i class="bi bi-pencil-square me-2"></i>File New Resignation</h5>
                    
                    <form id="resignationForm">
                        <input type="hidden" name="action" value="file_resignation">
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Resignation Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="resignation_type" required>
                                <option value="Voluntary">Voluntary Resignation</option>
                                <option value="Constructive">Constructive Resignation</option>
                                <option value="Mutual Agreement">Mutual Agreement Separation</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Proposed Last Day <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="last_day" id="res_last_day" required>
                            <div class="form-text" style="font-size:11px;">Standard resignation notice period is usually 30 days.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Reason for Leaving <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="reason" rows="4" placeholder="Provide detailed explanation for your separation..." required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-danger btn-sm w-100"><i class="bi bi-send me-1"></i> Submit Resignation Letter</button>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-5">
                <div class="page-card border-start border-danger border-4">
                    <h6 class="fw-bold text-danger mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>Exit Notice Guidelines</h6>
                    <p style="font-size: 13.5px; line-height: 1.6;" class="text-muted mb-0">
                        1. <strong>Filing Notice Period</strong>: Under normal operation, a 30-day exit notice period is highly recommended to transfer account knowledge and store assets smoothly.<br><br>
                        2. <strong>Clearance</strong>: Filing resignation begins the exit clearance workflow. You must return all keys, POS registers, and inventory records assigned to you before your final paycheck can be computed and released.<br><br>
                        3. <strong>Clear Information</strong>: Please input an honest reason for leaving to help the admin analyze operations and improve the workplace experience.
                    </p>
                </div>
            </div>
        <?php endif; ?>

    </div>

</div>

<script>
$(document).ready(function(){
    $('#resignationForm').on('submit', function(e){
        e.preventDefault();
        
        const lastDayVal = $('#res_last_day').val();
        if(!lastDayVal){
            Swal.fire('Missing Last Day', 'Please specify your proposed final work date.', 'warning');
            return;
        }

        const last = new Date(lastDayVal);
        const today = new Date();
        if (last < today) {
            Swal.fire('Invalid Date', 'Proposed final work date cannot be in the past.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Submit Resignation Letter?',
            html: 'This is a formal filing of separation. <br><strong class="text-danger">This request cannot be undone.</strong>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, Submit Resignation'
        }).then(result => {
            if(!result.isConfirmed) return;
            
            const formData = $(this).serialize();
            $.ajax({
                url: 'emp_resignation.php',
                type: 'POST',
                data: formData,
                success: function(res){
                    res = res.trim();
                    if(res.startsWith('success:')){
                        const msg = res.substring(8);
                        Swal.fire({
                            icon: 'success',
                            title: 'Submitted!',
                            text: msg,
                            confirmButtonColor: '#dc3545'
                        }).then(() => {
                            loadPage('emp_resignation.php');
                        });
                    } else {
                        const errorMsg = res.startsWith('error:') ? res.substring(6) : res;
                        Swal.fire('Error', errorMsg, 'error');
                    }
                },
                error: function(){
                    Swal.fire('Error', 'Communication failure with the server.', 'error');
                }
            });
        });
    });
});
</script>
