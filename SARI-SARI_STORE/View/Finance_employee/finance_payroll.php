<?php
error_reporting(E_ALL & ~E_NOTICE);
$db_path = __DIR__ . '/../../Model/database.php';
if (!file_exists($db_path)) {
    $db_path = __DIR__ . '/../Model/database.php';
}
require_once($db_path);

$controller_path = __DIR__ . '/../../Controller/HRMSController.php';
if (!file_exists($controller_path)) {
    $controller_path = __DIR__ . '/../Controller/HRMSController.php';
}
require_once($controller_path);

$hrmsController = new HRMSController($conn);

// Handle Actions
$message = '';
$msg_type = '';

$emp_user = intval($_SESSION['user_id'] ?? $_SESSION['emp_id'] ?? 0);
$account_type = isset($_SESSION['user_id']) ? 'User' : 'Employee';

$q_sig = mysqli_query($conn, "SELECT e_signature FROM registered_signatures WHERE account_id = $emp_user AND account_type = '$account_type' LIMIT 1");
$row_sig = mysqli_fetch_assoc($q_sig);
$current_signature = $row_sig ? $row_sig['e_signature'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $pid = intval($_POST['period_id']);
    
    if ($_POST['action'] === 'sign_and_approve_payroll') {
        $notes = $_POST['finance_notes'] ?? 'Budget Approved by Finance';
        
        // Fetch registered signature for snapshot
        $q_sig2 = mysqli_query($conn, "SELECT e_signature FROM registered_signatures WHERE account_id = $emp_user AND account_type = '$account_type' LIMIT 1");
        $row_sig2 = mysqli_fetch_assoc($q_sig2);
        $signature = $row_sig2 ? mysqli_real_escape_string($conn, $row_sig2['e_signature']) : '';
        
        if (empty($signature)) {
            $message = "A registered E-Signature is required.";
            $msg_type = "danger";
        } else {
            $res = $hrmsController->financeApprovePayroll($pid, $notes);
            if ($res === 'success') {
                $emp_name = $_SESSION['emp_name'] ?? $_SESSION['full_name'] ?? 'Finance User';
                $role = 'Finance Officer';
                $ref = 'FIN-PAY-' . date('Ymd') . '-' . rand(1000, 9999);
                
                // 1. Insert into finance_approvals
                mysqli_query($conn, "
                    INSERT INTO finance_approvals (approval_ref, document_type, related_id, approved_by, approver_name, approver_role, decision, e_signature, notes)
                    VALUES ('$ref', 'Payroll', $pid, $emp_user, '$emp_name', '$role', 'Approved', '$signature', '$notes')
                ");

                $message = "Payroll Period formally signed and approved successfully.";
                $msg_type = "success";
            } else {
                $message = "Failed to approve: $res";
                $msg_type = "danger";
            }
        }
    } elseif ($_POST['action'] === 'reject_payroll') {
        $notes = $_POST['finance_notes'] ?? 'Budget Rejected by Finance';
        $res = $hrmsController->financeRejectPayroll($pid, $notes);
        if ($res === 'success') {
            $message = "Payroll Period rejected and sent back to Draft.";
            $msg_type = "warning";
        } else {
            $message = "Failed to reject: $res";
            $msg_type = "danger";
        }
    }
}

// Fetch signed letter data if requested via AJAX
if (isset($_GET['action']) && $_GET['action'] === 'get_signed_payroll') {
    $pid = intval($_GET['period_id']);
    $q = mysqli_query($conn, "
        SELECT fa.*
        FROM finance_approvals fa 
        WHERE fa.document_type = 'Payroll' AND fa.related_id = $pid LIMIT 1
    ");
    $letter = mysqli_fetch_assoc($q);
    header('Content-Type: application/json');
    echo json_encode($letter);
    exit;
}

// Fetch all payroll periods
$periodsResult = $hrmsController->getPayrollPeriodsList();
$requests = [];
$total_pending_cost = 0;

if ($periodsResult) {
    while ($r = mysqli_fetch_assoc($periodsResult)) {
        $requests[] = $r;
        if ($r['status'] === 'For Approval') {
            $total_pending_cost += floatval($r['total_net']);
        }
    }
}
?>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color:#2b1055;">
                <i class="bi bi-cash-coin me-2 text-purple" style="color:#7b2cbf;"></i>Payroll Approvals (Finance)
            </h4>
            <p class="text-muted mb-0" style="font-size:13px;">Review payroll periods forwarded by HRMS. Approve budget to authorize payments or reject back to draft.</p>
        </div>
        <div>
            <div class="badge bg-purple px-3 py-2 text-white shadow-sm" style="background:#7b2cbf; border-radius:8px; font-size:13px;">
                <i class="bi bi-wallet2 me-1"></i> Pending Payroll Cost: ₱<?= number_format($total_pending_cost, 2); ?>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $msg_type; ?> alert-dismissible fade show" role="alert" style="border-radius:10px;">
            <i class="bi bi-info-circle me-2"></i><?= htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="card border-0 shadow-sm" style="border-radius:12px; overflow:hidden;">
        <div class="card-header bg-white py-3 border-0">
            <h6 class="fw-bold mb-0 text-secondary"><i class="bi bi-file-earmark-text me-2"></i>Payroll Applications</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 datatable">
                <thead class="table-light">
                    <tr style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">
                        <th class="ps-4">Period Name</th>
                        <th>Dates</th>
                        <th class="text-center">Employees</th>
                        <th class="text-end">Gross Pay</th>
                        <th class="text-end text-danger">Deductions</th>
                        <th class="text-end text-success">Net Pay (Budget)</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-4">Finance Action</th>
                    </tr>
                </thead>
                <tbody style="font-size:13px;">
                    <?php 
                    $has_items = false;
                    foreach ($requests as $r): 
                        if ($r['status'] === 'Draft' && empty($r['finance_notes'])) {
                            continue; // Hide fresh Drafts from Finance, but show rejected ones
                        }
                        $has_items = true;
                        
                        $st = $r['status'];
                        $badgeClass = 'bg-secondary';
                        
                        // If it's a Draft with notes, it means Finance rejected it
                        if ($st === 'Draft' && !empty($r['finance_notes'])) {
                            $st = 'Rejected (Returned to HR)';
                            $badgeClass = 'bg-danger';
                        } elseif ($st === 'For Approval') {
                            $badgeClass = 'bg-warning text-dark';
                        } elseif ($st === 'Approved') {
                            $badgeClass = 'bg-success';
                        } elseif ($st === 'Paid') {
                            $badgeClass = 'bg-primary';
                        }
                    ?>
                    <tr>
                        <td class="ps-4">
                            <strong class="text-purple d-block" style="color:#7b2cbf;"><?= htmlspecialchars($r['period_name']); ?></strong>
                            <small class="text-muted">Pay Date: <?= date('M d, Y', strtotime($r['pay_date'])); ?></small>
                        </td>
                        <td>
                            <?= date('M d', strtotime($r['date_from'])); ?> – <?= date('M d, Y', strtotime($r['date_to'])); ?>
                        </td>
                        <td class="text-center fw-bold fs-6">
                            <?= number_format($r['employee_count']); ?>
                        </td>
                        <td class="text-end text-dark">
                            ₱<?= number_format($r['total_gross'], 2); ?>
                        </td>
                        <td class="text-end text-danger">
                            - ₱<?= number_format($r['total_deductions'], 2); ?>
                        </td>
                        <td class="text-end fw-bold text-success fs-6">
                            ₱<?= number_format($r['total_net'], 2); ?>
                        </td>
                        <td class="text-center">
                            <span class="badge <?= $badgeClass; ?>"><?= htmlspecialchars($st); ?></span>
                            <?php if(!empty($r['finance_notes'])){ ?>
                                <br><small class="text-muted" title="<?= htmlspecialchars($r['finance_notes']); ?>"><i class="bi bi-chat-text"></i> Notes</small>
                            <?php } ?>
                        </td>
                        <td class="text-end pe-4">
                            <?php if ($st === 'For Approval'): ?>
                                <button class="btn btn-sm btn-success rounded-3 me-1" onclick='openPayrollApproveModal(<?= json_encode($r); ?>)'>
                                    <i class="bi bi-pen me-1"></i> Review & Sign Approval
                                </button>
                                <button class="btn btn-sm btn-outline-danger rounded-3" onclick='openPayrollRejectModal(<?= json_encode($r); ?>)'>
                                    <i class="bi bi-x-lg me-1"></i> Reject
                                </button>
                            <?php elseif ($st === 'Approved' || $st === 'Paid'): ?>
                                <button class="btn btn-sm btn-outline-primary rounded-3 mb-1" onclick='viewSignedPayrollLetter(<?= json_encode($r); ?>)'>
                                    <i class="bi bi-file-earmark-check me-1"></i> View Signed Letter
                                </button>
                                <span class="text-muted d-block" style="font-size:12px;"><i class="bi bi-lock me-1"></i>Processed</span>
                            <?php elseif ($st === 'Rejected (Returned to HR)'): ?>
                                <span class="text-muted fst-italic"><i class="bi bi-arrow-return-left me-1"></i>With HR</span>
                            <?php else: ?>
                                <span class="text-muted"><i class="bi bi-lock me-1"></i>Processed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- FORMAL FINANCE PAYROLL APPROVAL LETTER MODAL -->
<div class="modal fade" id="payrollApproveModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:16px; overflow:hidden;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #198754, #20c997); border:none; padding:20px 24px;">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-text me-2"></i>Formal Payroll Approval Letter</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="approvePayrollForm" method="POST" action="Finance_employee/finance_payroll.php" onsubmit="submitForm(event)">
                <input type="hidden" name="action" value="sign_and_approve_payroll">
                <input type="hidden" name="period_id" id="approve_period_id">
                
                <div class="modal-body p-4" style="background:#fafafa;">
                    <div class="bg-white p-4 border rounded shadow-sm" style="font-family: 'Times New Roman', serif; color:#000;">
                        <div class="text-center mb-4">
                            <h4 class="fw-bold mb-1">O-CART!</h4>
                            <p class="mb-0 text-muted" style="font-size:14px;">Formal Payroll Disbursement Authorization</p>
                            <hr>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <strong>Date:</strong> <span id="pay_app_date"><?= date('F d, Y') ?></span><br>
                                <strong>Requesting Dept:</strong> HRMS<br>
                                <strong>Payroll Period:</strong> <span id="pay_app_period" class="text-primary fw-bold"></span>
                            </div>
                            <div class="col-6 text-end">
                                <strong>Document:</strong> Financial Approval<br>
                                <strong>Status:</strong> <span class="badge bg-warning text-dark">Pending Signature</span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <p>This document serves as the formal financial authorization to release payroll funds for the specified period.</p>
                            <table class="table table-bordered table-sm align-middle" style="font-size:14px;">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th>Employees</th>
                                        <th>Total Gross Pay</th>
                                        <th>Total Deductions</th>
                                        <th>Total Net Pay (Budget)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="text-center">
                                        <td id="pay_app_employees"></td>
                                        <td id="pay_app_gross"></td>
                                        <td id="pay_app_deductions" class="text-danger"></td>
                                        <td id="pay_app_net" class="fw-bold text-success"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary">Finance Approval Notes</label>
                            <textarea class="form-control bg-light border-0" name="finance_notes" rows="2" style="font-family: inherit;">Budget approved for disbursement</textarea>
                        </div>
                        
                        <hr>
                        <div class="mt-4 p-3 bg-light border rounded text-center">
                            <h6 class="fw-bold text-success mb-3"><i class="bi bi-pen me-1"></i>Electronic Signature</h6>
                            
                            <?php if ($current_signature): ?>
                                <p class="text-muted mb-2" style="font-size:13px;">Your registered Finance signature will be attached to this approval.</p>
                                <div class="mx-auto p-2 bg-white border rounded" style="max-width:350px;">
                                    <img src="<?= htmlspecialchars($current_signature) ?>" style="max-height:80px; max-width:300px; border-bottom:1px solid #ccc;" alt="Registered Signature">
                                    <div class="fw-bold mt-2" style="font-size:13px;"><?= htmlspecialchars($_SESSION['emp_name'] ?? $_SESSION['full_name'] ?? 'Finance User') ?></div>
                                </div>
                                <div class="mt-3 text-muted" style="font-size:11px;">Timestamp: <?= date('Y-m-d H:i:s') ?></div>
                            <?php else: ?>
                                <div class="p-4 bg-white border border-warning rounded">
                                    <i class="bi bi-exclamation-triangle-fill text-warning fs-3 mb-2 d-block"></i>
                                    <h6 class="fw-bold">No Registered Signature</h6>
                                    <p class="text-muted" style="font-size:13px;">Please register your Finance signature before approving this document.</p>
                                    <button type="button" class="btn btn-sm btn-primary mt-2" onclick="$('#payrollApproveModal').modal('hide'); loadPage('finance_signature_profile.php', this)">Register Signature Now</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-0 p-4 pt-0 bg-light">
                    <button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <?php if ($current_signature): ?>
                    <button type="submit" class="btn btn-success px-4 rounded-3 fw-bold shadow-sm"><i class="bi bi-check-circle-fill me-1"></i> Sign & Approve Payroll</button>
                    <?php else: ?>
                    <button type="button" class="btn btn-success px-4 rounded-3 fw-bold shadow-sm" disabled><i class="bi bi-check-circle-fill me-1"></i> Sign & Approve Payroll</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- VIEW SIGNED PAYROLL LETTER MODAL -->
<div class="modal fade" id="viewSignedPayrollLetterModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:16px;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0d6efd, #0dcaf0); border:none; padding:20px 24px;">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-file-earmark-check me-2"></i>Signed Payroll Approval Letter
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" style="background:#fafafa;" id="signedPayrollLetterContent">
                <div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>
            </div>
            <div class="modal-footer bg-light border-0 py-2" style="border-radius:0 0 16px 16px;">
                <button type="button" class="btn btn-outline-primary btn-sm rounded-3" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
                <button type="button" class="btn btn-secondary btn-sm rounded-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- FINANCE REJECT MODAL -->
<div class="modal fade" id="payrollRejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:16px; overflow:hidden;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #dc3545, #f87171); border:none; padding:20px 24px;">
                <h5 class="modal-title fw-bold"><i class="bi bi-x-circle me-2"></i>Reject Payroll Budget</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectPayrollForm" method="POST" action="Finance_employee/finance_payroll.php" onsubmit="submitForm(event)">
                <input type="hidden" name="action" value="reject_payroll">
                <input type="hidden" name="period_id" id="reject_period_id">
                
                <div class="modal-body p-4 text-center">
                    <div class="alert alert-danger" role="alert" style="border-radius:10px; font-size:14px; text-align:left;">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> Rejecting will send this back to HR as "Draft".
                    </div>
                    
                    <div class="mb-3 text-start">
                        <label class="form-label fw-bold text-secondary">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea class="form-control bg-light border-0" name="finance_notes" rows="3" placeholder="Explain why this budget is rejected..." required></textarea>
                    </div>
                </div>
                
                <div class="modal-footer border-0 p-4 pt-0 bg-light">
                    <button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4 rounded-3 fw-bold shadow-sm">Reject Payroll</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openPayrollApproveModal(period) {
    document.getElementById('approve_period_id').value = period.period_id;
    document.getElementById('pay_app_period').innerText = period.period_name;
    document.getElementById('pay_app_employees').innerText = period.employee_count;
    document.getElementById('pay_app_gross').innerText = '₱' + parseFloat(period.total_gross).toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('pay_app_deductions').innerText = '₱' + parseFloat(period.total_deductions).toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('pay_app_net').innerText = '₱' + parseFloat(period.total_net).toLocaleString('en-US', {minimumFractionDigits: 2});
    
    new bootstrap.Modal(document.getElementById('payrollApproveModal')).show();
}

// Canvas logic removed (moved to profile page)
// ---------------------------

function getFinancePayrollUrl() {
    return window.location.pathname.toLowerCase().includes('/finance_employee/') ? 'finance_payroll.php' : 'Finance_employee/finance_payroll.php';
}

function viewSignedPayrollLetter(period) {
    new bootstrap.Modal(document.getElementById('viewSignedPayrollLetterModal')).show();
    $('#signedPayrollLetterContent').html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>');
    
    $.get(getFinancePayrollUrl(), { action: 'get_signed_payroll', period_id: period.period_id }, function(data) {
        if(data) {
            const html = `
                <div class="bg-white p-4 border rounded shadow-sm" style="font-family: 'Times New Roman', serif; color:#000;">
                    <div class="text-center mb-4">
                        <h4 class="fw-bold mb-1">O-CART!</h4>
                        <p class="mb-0 text-muted" style="font-size:14px;">Formal Payroll Disbursement Authorization</p>
                        <hr>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Signed Date:</strong> ${new Date(data.signed_at).toLocaleString()}<br>
                            <strong>Requesting Dept:</strong> HRMS<br>
                            <strong>Payroll Period:</strong> <span class="text-primary fw-bold">${period.period_name}</span>
                        </div>
                        <div class="col-6 text-end">
                            <strong>Document:</strong> Financial Approval<br>
                            <strong>Status:</strong> <span class="badge bg-success">Approved & Signed</span><br>
                            <strong>Approval Ref:</strong> ${data.approval_ref}
                        </div>
                    </div>
                    <div class="mb-4">
                        <table class="table table-bordered table-sm align-middle" style="font-size:14px;">
                            <thead class="table-light text-center">
                                <tr><th>Employees</th><th>Total Gross Pay</th><th>Total Deductions</th><th>Total Net Pay (Budget)</th></tr>
                            </thead>
                            <tbody>
                                <tr class="text-center">
                                    <td>${period.employee_count}</td>
                                    <td>₱${parseFloat(period.total_gross).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                    <td class="text-danger">₱${parseFloat(period.total_deductions).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                    <td class="fw-bold text-success">₱${parseFloat(period.total_net).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mb-4">
                        <label class="fw-bold">Approval Notes:</label>
                        <p class="mb-0 bg-light p-2 border rounded" style="font-style:italic;">${data.notes}</p>
                    </div>
                    <hr>
                    <div class="mt-4 row">
                        <div class="col-6">
                            <p class="mb-1"><strong>Authorized By:</strong></p>
                            ${data.e_signature.startsWith('data:image') ? `<img src="${data.e_signature}" style="max-height:80px; max-width:250px;" alt="Signature">` : `<h4 class="text-primary signature-font mb-0" style="font-family: 'Brush Script MT', cursive;">${data.e_signature}</h4>`}
                            <div class="border-top border-dark pt-1 mt-1 d-inline-block" style="min-width: 200px;">
                                <p class="mb-0 fw-bold">${data.approver_name}</p>
                                <p class="mb-0 text-muted" style="font-size:12px;">${data.approver_role}</p>
                            </div>
                        </div>
                        <div class="col-6 text-end">
                            <!-- digital stamp placeholder -->
                            <div class="d-inline-block border border-success text-success p-2 rounded text-center opacity-75" style="border-width: 3px !important; transform: rotate(-5deg);">
                                <h5 class="fw-bold mb-0">APPROVED</h5>
                                <small>${data.signed_at}</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('#signedPayrollLetterContent').html(html);
        } else {
            $('#signedPayrollLetterContent').html(`
                <div class="text-center p-5">
                    <i class="bi bi-exclamation-circle text-warning mb-3" style="font-size:3rem;"></i>
                    <h5 class="fw-bold">No Signature Found</h5>
                    <p class="text-muted">This payroll was approved before the electronic signature system was implemented. There is no formal document on file.</p>
                </div>
            `);
        }
    });
}

function submitForm(e) {
    if (e.target.id === 'approvePayrollForm' && !document.getElementById('payroll_e_signature').value) {
        e.preventDefault();
        Swal.fire('Signature Required', 'Please draw your signature to approve the payroll.', 'warning');
    }
}

function openPayrollRejectModal(period) {
    document.getElementById('reject_period_id').value = period.period_id;
    new bootstrap.Modal(document.getElementById('payrollRejectModal')).show();
}

function submitForm(e) {
    e.preventDefault();
    const form = e.target;
    if (form.id === 'approvePayrollForm') {
        var isUploadActive = document.getElementById('upload-payroll-tab').classList.contains('active');
        if (isUploadActive && !document.getElementById('payrollSigImageUpload').files[0] && !document.getElementById('payroll_e_signature').value) {
            Swal.fire('Signature Required', 'Please upload a signature image to approve the payroll.', 'warning');
            return;
        } else if (!isUploadActive && !document.getElementById('payroll_e_signature').value) {
            Swal.fire('Signature Required', 'Please draw your signature to approve the payroll.', 'warning');
            return;
        }
    }

    const btn = $(form).find('button[type="submit"]');
    const originalText = btn.html();
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');

    $.post(getFinancePayrollUrl(), $(form).serialize(), function(response) {
        $("#content").html(response);
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('padding-right', '');
    }).fail(function(){
        btn.prop('disabled', false).html(originalText);
        Swal.fire('Error', 'Failed to process request. The image might be too large.', 'error');
    });
}
</script>
