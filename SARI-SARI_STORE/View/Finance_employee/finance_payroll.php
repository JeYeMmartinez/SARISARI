<?php
error_reporting(E_ALL & ~E_NOTICE);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $pid = intval($_POST['period_id']);
    
    if ($_POST['action'] === 'approve_payroll') {
        $notes = $_POST['finance_notes'] ?? 'Budget Approved by Finance';
        $res = $hrmsController->financeApprovePayroll($pid, $notes);
        if ($res === 'success') {
            $message = "Payroll Period approved successfully.";
            $msg_type = "success";
        } else {
            $message = "Failed to approve: $res";
            $msg_type = "danger";
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
                                    <i class="bi bi-check-lg me-1"></i> Approve
                                </button>
                                <button class="btn btn-sm btn-outline-danger rounded-3" onclick='openPayrollRejectModal(<?= json_encode($r); ?>)'>
                                    <i class="bi bi-x-lg me-1"></i> Reject
                                </button>
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

<!-- FINANCE APPROVE MODAL -->
<div class="modal fade" id="payrollApproveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:16px; overflow:hidden;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #198754, #20c997); border:none; padding:20px 24px;">
                <h5 class="modal-title fw-bold"><i class="bi bi-check-circle me-2"></i>Approve Payroll Budget</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="approvePayrollForm" method="POST" action="Finance_employee/finance_payroll.php" onsubmit="submitForm(event)">
                <input type="hidden" name="action" value="approve_payroll">
                <input type="hidden" name="period_id" id="approve_period_id">
                
                <div class="modal-body p-4 text-center">
                    <p class="text-muted mb-4">You are authorizing the release of funds for this payroll period.</p>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary">Finance Notes (Optional)</label>
                        <textarea class="form-control bg-light border-0" name="finance_notes" rows="2" placeholder="e.g. Budget approved for disbursement"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer border-0 p-4 pt-0 bg-light">
                    <button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4 rounded-3 fw-bold shadow-sm">Confirm Approval</button>
                </div>
            </form>
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
    new bootstrap.Modal(document.getElementById('payrollApproveModal')).show();
}

function openPayrollRejectModal(period) {
    document.getElementById('reject_period_id').value = period.period_id;
    new bootstrap.Modal(document.getElementById('payrollRejectModal')).show();
}

function submitForm(e) {
    e.preventDefault();
    const form = e.target;
    $.post('Finance_employee/finance_payroll.php', $(form).serialize(), function(response) {
        $("#content").html(response);
        // hide backdrop
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('padding-right', '');
    });
}
</script>
