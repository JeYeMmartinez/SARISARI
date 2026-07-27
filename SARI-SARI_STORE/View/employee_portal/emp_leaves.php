<?php
session_start();
require_once '../../Model/database.php';
require_once '../../Model/logger.php';

if(!isset($_SESSION['emp_id'])){
    exit("Unauthorized");
}

$emp_id   = $_SESSION['emp_id'];
$emp_name = $_SESSION['emp_name'];

// Handlers
$success_msg = '';
$error_msg = '';

if (isset($_POST['action']) && $_POST['action'] === 'create_leave') {
    $leave_type = mysqli_real_escape_string($conn, $_POST['leave_type']);
    $date_from  = mysqli_real_escape_string($conn, $_POST['date_from']);
    $date_to    = mysqli_real_escape_string($conn, $_POST['date_to']);
    $reason     = mysqli_real_escape_string($conn, trim($_POST['reason']));

    // Calculate days between
    $start = new DateTime($date_from);
    $end   = new DateTime($date_to);
    if ($end < $start) {
        $error_msg = "End date cannot be before start date.";
    } else {
        $interval = $start->diff($end);
        $days = $interval->days + 1; // inclusive

        // Document upload
        $document = '';
        if (isset($_FILES['document']) && $_FILES['document']['error'] === 0) {
            $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
            $fileType = mime_content_type($_FILES['document']['tmp_name']);
            if (!in_array($fileType, $allowed)) {
                $error_msg = "Document must be a PDF or Image (JPG, PNG).";
            } elseif ($_FILES['document']['size'] > 5 * 1024 * 1024) {
                $error_msg = "Document file size must not exceed 5MB.";
            } else {
                $uploadDir = __DIR__ . '/../uploads/leaves/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $ext = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
                $fileName = time() . '_leave_' . $emp_id . '.' . $ext;
                if (move_uploaded_file($_FILES['document']['tmp_name'], $uploadDir . $fileName)) {
                    $document = mysqli_real_escape_string($conn, $fileName);
                } else {
                    $error_msg = "Failed to upload document.";
                }
            }
        }

        if (empty($error_msg)) {
            $q = mysqli_query($conn, "
                INSERT INTO leave_requests (employee_id, leave_type, date_from, date_to, days, reason, document, status)
                VALUES ($emp_id, '$leave_type', '$date_from', '$date_to', $days, '$reason', '$document', 'Pending')
            ");

            if ($q) {
                logAction($conn, 1, 'Create', 'leave_requests', mysqli_insert_id($conn), "Employee $emp_name filed $leave_type for $date_from to $date_to");
                $success_msg = "Leave request submitted successfully! Your supervisor will review it shortly.";
            } else {
                $error_msg = "Failed to submit leave request: " . mysqli_error($conn);
            }
        }
    }
    
    // Output response for AJAX form submission
    ob_clean();
    if (!empty($error_msg)) {
        echo "error: " . $error_msg;
    } else {
        echo "success: " . $success_msg;
    }
    exit();
}

// Fetch leave requests list
$query = mysqli_query($conn, "
    SELECT * FROM leave_requests
    WHERE employee_id = $emp_id
    ORDER BY created_at DESC
");

$leave_records = [];
while ($row = mysqli_fetch_assoc($query)) {
    $leave_records[] = $row;
}
?>
<style>
.badge-pending { background: #fef3c7; color: #92400e; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
.badge-approved { background: #d1fae5; color: #065f46; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
.badge-rejected { background: #fee2e2; color: #991b1b; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
</style>

<div class="animate__animated animate__fadeIn">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color: #0f5132;">Leave Management</h4>
            <small class="text-muted">Request leaves of absence and monitor approval status</small>
        </div>
        <button class="btn btn-success btn-sm" onclick="openFileLeaveModal()">
            <i class="bi bi-calendar-plus me-1"></i> File Leave Request
        </button>
    </div>

    <!-- LEAVE HISTORY -->
    <div class="page-card">
        <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-success"></i>My Leave History</h5>
        
        <?php if (empty($leave_records)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-calendar-x" style="font-size: 48px;"></i>
                <p class="mt-3 mb-0">No leave requests found. Click the button above to file a new leave request.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle datatable w-100" id="empLeavesTable">
                    <thead class="table-light">
                        <tr>
                            <th>Leave Type</th>
                            <th>Date From</th>
                            <th>Date To</th>
                            <th>No. of Days</th>
                            <th>Reason</th>
                            <th>Document</th>
                            <th>Status</th>
                            <th>Submitted On</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($leave_records as $leave):
                        $statusClass = 'badge-pending';
                        if ($leave['status'] === 'Approved') $statusClass = 'badge-approved';
                        elseif ($leave['status'] === 'Rejected') $statusClass = 'badge-rejected';
                    ?>
                    <tr>
                        <td class="fw-semibold text-dark"><?= htmlspecialchars($leave['leave_type']); ?></td>
                        <td><?= date('M d, Y', strtotime($leave['date_from'])); ?></td>
                        <td><?= date('M d, Y', strtotime($leave['date_to'])); ?></td>
                        <td class="fw-bold text-center"><?= $leave['days']; ?></td>
                        <td><small class="text-muted"><?= htmlspecialchars($leave['reason'] ?? '—'); ?></small></td>
                        <td>
                            <?php if(!empty($leave['document'])): ?>
                                <a href="../uploads/leaves/<?= htmlspecialchars($leave['document']); ?>" target="_blank" class="btn btn-xs btn-outline-success">
                                    <i class="bi bi-file-earmark-arrow-down-fill"></i> Download
                                </a>
                            <?php else: ?>
                                <span class="text-muted" style="font-size: 12px;">No file</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="<?= $statusClass; ?>"><?= $leave['status']; ?></span></td>
                        <td><?= date('M d, Y h:i A', strtotime($leave['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- FILE LEAVE MODAL -->
<div class="modal fade" id="leaveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-calendar-plus-fill me-2"></i>File Leave Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="leaveForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_leave">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Leave Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="leave_type" required>
                            <option value="Sick Leave">Sick Leave</option>
                            <option value="Vacation Leave">Vacation Leave</option>
                            <option value="Emergency Leave">Emergency Leave</option>
                            <option value="Maternity">Maternity</option>
                            <option value="Paternity">Paternity</option>
                        </select>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Date From <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_from" id="leave_date_from" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Date To <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_to" id="leave_date_to" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason / Justification <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="reason" rows="3" placeholder="Provide detailed explanation..." required></textarea>
                    </div>
                    
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Attachment / Supporting Document <small class="text-muted">(Optional)</small></label>
                        <input type="file" class="form-control" name="document" accept=".pdf,image/jpeg,image/png">
                        <div class="form-text" style="font-size:11px;">PDF or Image files only. Maximum size 5MB.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-send me-1"></i> Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    if($.fn.DataTable){
        $('#empLeavesTable').DataTable({
            responsive: true,
            pageLength: 10,
            lengthChange: false,
            ordering: true,
            searching: false,
            order: [[7, 'desc']],
            destroy: true
        });
    }

    // Submit leave form via AJAX
    $('#leaveForm').on('submit', function(e){
        e.preventDefault();
        
        // Validation check
        const start = new Date($('#leave_date_from').val());
        const end = new Date($('#leave_date_to').val());
        if (end < start) {
            Swal.fire('Invalid Date Range', 'End date cannot be before start date.', 'warning');
            return;
        }

        // Close modal first
        bootstrap.Modal.getInstance(document.getElementById('leaveModal'))?.hide();
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('padding-right', '');

        const formData = new FormData(this);

        $.ajax({
            url: 'emp_leaves.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res){
                res = res.trim();
                if(res.startsWith('success:')){
                    const msg = res.substring(8);
                    Swal.fire({
                        icon: 'success',
                        title: 'Request Filed!',
                        text: msg,
                        confirmButtonColor: '#198754'
                    }).then(() => {
                        loadPage('emp_leaves.php');
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

function openFileLeaveModal() {
    $('#leaveForm')[0].reset();
    new bootstrap.Modal(document.getElementById('leaveModal')).show();
}
</script>
