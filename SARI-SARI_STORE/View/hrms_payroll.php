<?php
require_once '../Model/database.php';
require_once '../Controller/HRMSController.php';

if(session_status() === PHP_SESSION_NONE){
    session_start();
}
$admin_id = $_SESSION['user_id'] ?? 1;

$hrmsController = new HRMSController($conn);

/*=========================================================
    ACTIONS
==========================================================*/

// ARCHIVE PAYROLL RECORD
if(isset($_POST['action']) && $_POST['action'] === 'archive_payroll'){
    $result = $hrmsController->archivePayroll($_POST['payroll_id'], $_POST['reason'] ?? '', $admin_id);
    ob_clean(); echo $result; exit;
}

// RESTORE PAYROLL RECORD
if(isset($_POST['action']) && $_POST['action'] === 'restore_payroll'){
    $result = $hrmsController->restorePayroll($_POST['archive_id']);
    ob_clean(); echo $result; exit;
}

// REJECT PAYROLL RECORD
if(isset($_POST['action']) && $_POST['action'] === 'reject_payroll'){
    $result = $hrmsController->rejectPayroll($_POST['payroll_id'], $_POST['reason'] ?? '');
    ob_clean(); echo $result; exit;
}

// COMPUTE DEDUCTIONS (AJAX)
if(isset($_POST['action']) && $_POST['action'] == 'compute'){
    $result = $hrmsController->computePayrollDeductions(
        $_POST['employee_id'],
        (float)$_POST['days_worked'],
        (float)($_POST['hours_per_day'] ?? 8),
        (float)($_POST['overtime_hours'] ?? 0)
    );
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}

// GET ATTENDANCE SUMMARY (auto-fill Days Worked / Hours per Day / Overtime)
if(isset($_POST['action']) && $_POST['action'] == 'get_attendance_summary'){
    $result = $hrmsController->getAttendanceSummaryForPeriod($_POST['employee_id'], $_POST['period_id']);
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}

// SAVE PAYROLL RECORD
if(isset($_POST['action']) && $_POST['action'] == 'save_payroll'){
    $result = $hrmsController->savePayroll($_POST, $admin_id);
    ob_clean(); echo $result; exit();
}

// GET NEXT AUTO PERIOD DATES
if(isset($_POST['action']) && $_POST['action'] == 'get_next_period'){
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($hrmsController->getNextPeriodDates());
    exit();
}

// CREATE PAYROLL PERIOD
if(isset($_POST['action']) && $_POST['action'] == 'create_period'){
    $result = $hrmsController->createPayrollPeriod($_POST, $admin_id);
    ob_clean(); echo $result; exit();
}

// APPROVE PAYROLL PERIOD
if(isset($_POST['action']) && $_POST['action'] == 'approve_period'){
    $result = $hrmsController->approvePeriod($_POST['period_id']);
    ob_clean(); echo $result; exit();
}

// MARK AS PAID
if(isset($_POST['action']) && $_POST['action'] == 'mark_paid'){
    $result = $hrmsController->markPeriodPaid($_POST['period_id']);
    ob_clean(); echo $result; exit();
}

// DELETE PERIOD
if(isset($_POST['action']) && $_POST['action'] == 'delete_period'){
    $result = $hrmsController->deletePeriod($_POST['period_id']);
    ob_clean(); echo $result; exit();
}

// GET PERIOD RECORDS (AJAX - GET)
if(isset($_GET['action']) && $_GET['action'] == 'get_records'){
    $res = $hrmsController->getPeriodRecords($_GET['period_id']);
    $records = [];
    while($r = mysqli_fetch_assoc($res)) $records[] = $r;
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($records);
    exit();
}

/*=========================================================
    FETCH DATA
==========================================================*/
$periodsResult = $hrmsController->getPayrollPeriodsList();

$periodsList    = [];
$totalPeriods   = 0;
$draftCount     = 0;
$approvedCount  = 0;
$paidCount      = 0;
$totalNetAll    = 0.0;
while($p = mysqli_fetch_assoc($periodsResult)){
    $periodsList[] = $p;
    $totalPeriods++;
    $totalNetAll += (float)$p['total_net'];
    if($p['status'] === 'Paid')           $paidCount++;
    elseif($p['status'] === 'Approved')   $approvedCount++;
    else                                  $draftCount++;
}

$employees = $hrmsController->getActiveEmployeesWithPosition();
$employeeList = [];
while($e = mysqli_fetch_assoc($employees)) $employeeList[] = $e;
?>


<style>
.page-card {
    background: white; border-radius: 14px;
    padding: 22px 24px; box-shadow: 0 2px 10px rgba(0,0,0,.06); margin-bottom: 22px;
}
.stat-card {
    background: white; border-radius: 14px; padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06); height: 100%;
    display: flex; justify-content: space-between; align-items: flex-start;
}
.stat-icon {
    width: 44px; height: 44px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; color: white; flex-shrink: 0;
}
.stat-label { font-size: 11px; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
.stat-value { font-size: 24px; font-weight: 800; line-height: 1.2; margin-top: 4px; }
.deduction-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px;
}
.deduction-row:last-child { border-bottom: none; }
.deduction-label { color: #6c757d; }
.deduction-value { font-weight: 600; }
.net-pay-box {
    background: linear-gradient(135deg, #1a3c5e, #2563eb);
    border-radius: 12px; padding: 20px; color: white; text-align: center;
}
.period-badge { font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; display: inline-block; }
.badge-draft    { background: #f3f4f6; color: #374151; }
.badge-approved { background: #dbeafe; color: #1e40af; }
.badge-paid     { background: #d1fae5; color: #065f46; }
.modal-header-primary { background: linear-gradient(135deg, #1a3c5e, #2563eb); color: white; }
.modal-header-primary .btn-close { filter: invert(1); }
.rec-row { border-bottom: 1px solid #f0f0f0; padding: 10px 0; font-size: 13px; }
.rec-row:last-child { border-bottom: none; }
</style>

<!-- ===== PAGE HEADER ===== -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color:#1a3c5e;">
            <i class="bi bi-cash-coin me-2" style="color:#2563eb;"></i>Payroll Management
        </h4>
        <small class="text-muted">Philippine Government Deductions — SSS, PhilHealth, Pag-IBIG, TRAIN Law</small>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" onclick="openArchivePayrollModal()">
            <i class="bi bi-archive-fill me-1"></i>Archive
            <?php
            $archPayCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payroll_archive"))['c'];
            if ($archPayCount > 0)
                echo '<span class="badge bg-danger ms-1">' . $archPayCount . '</span>';
            ?>
        </button>
        <button class="btn btn-outline-danger" onclick="openRejectedPayrollModal()">
            <i class="bi bi-x-circle-fill me-1"></i>Rejected / Disputed
            <?php
            $rejPayCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM payroll WHERE rejection_notes IS NOT NULL AND rejection_notes != ''"))['c'];
            if ($rejPayCount > 0)
                echo '<span class="badge bg-danger ms-1">' . $rejPayCount . '</span>';
            ?>
        </button>
        <button class="btn btn-primary" onclick="openCreatePeriodModal()">
            <i class="bi bi-plus-lg me-1"></i> New Payroll Period
        </button>
    </div>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="row g-3 mb-4 animate__animated animate__fadeIn">
    <div class="col-md-3">
        <div class="stat-card border-start border-primary border-4">
            <div>
                <div class="stat-label">Total Periods</div>
                <div class="stat-value"><?= $totalPeriods; ?></div>
            </div>
            <div class="stat-icon bg-primary"><i class="bi bi-calendar-range-fill"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card border-start border-secondary border-4">
            <div>
                <div class="stat-label">Draft / Pending</div>
                <div class="stat-value"><?= $draftCount; ?></div>
            </div>
            <div class="stat-icon bg-secondary"><i class="bi bi-pencil-square"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card border-start border-success border-4">
            <div>
                <div class="stat-label">Paid Periods</div>
                <div class="stat-value text-success"><?= $paidCount; ?></div>
            </div>
            <div class="stat-icon bg-success"><i class="bi bi-check-circle-fill"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card border-start border-info border-4">
            <div>
                <div class="stat-label">Total Net Payroll</div>
                <div class="stat-value" style="font-size:18px; color:#0d6efd;">&#8369;<?= number_format($totalNetAll, 0); ?></div>
            </div>
            <div class="stat-icon bg-info"><i class="bi bi-cash-stack"></i></div>
        </div>
    </div>
</div>

<!-- ===== PAYROLL PERIODS TABLE ===== -->
<div class="page-card animate__animated animate__fadeIn">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0" style="color:#1a3c5e;">
            <i class="bi bi-table me-2"></i>Payroll Periods
        </h5>
        <span class="text-muted" style="font-size:12px;">Click <strong>Records</strong> to view employee payslips per period</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle w-100" id="payrollTable">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Period Name</th>
                    <th>Date Range</th>
                    <th>Pay Date</th>
                    <th style="text-align:center;">Employees</th>
                    <th>Total Net Pay</th>
                    <th>Status</th>
                    <th style="text-align:center; width:200px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($periodsList as $i => $period) {
                    $badgeClass = match($period['status']){
                        'Paid'     => 'badge-paid',
                        'Approved' => 'badge-approved',
                        default    => 'badge-draft',
                    };
                ?>
                <tr>
                    <td class="text-muted fw-semibold"><?= $i + 1; ?></td>
                    <td>
                        <div class="fw-semibold text-dark"><?= htmlspecialchars($period['period_name']); ?></div>
                        <div class="text-muted" style="font-size:11px;">By: <?= htmlspecialchars($period['created_by_name'] ?? 'System'); ?></div>
                    </td>
                    <td style="font-size:13px;">
                        <?= date('M d', strtotime($period['date_from'])); ?> &ndash;
                        <?= date('M d, Y', strtotime($period['date_to'])); ?>
                    </td>
                    <td style="font-size:13px;"><?= date('M d, Y', strtotime($period['pay_date'])); ?></td>
                    <td style="text-align:center;">
                        <span class="badge bg-light text-dark border"><?= $period['employee_count']; ?></span>
                    </td>
                    <td>
                        <span class="fw-bold text-success">&#8369;<?= number_format($period['total_net'], 2); ?></span>
                    </td>
                    <td>
                        <span class="period-badge <?= $badgeClass; ?>"><?= $period['status']; ?></span>
                    </td>
                    <td>
                        <div class="d-flex justify-content-center gap-1 flex-wrap">
                            <!-- VIEW RECORDS -->
                            <button class="btn btn-sm btn-outline-primary"
                                    onclick="viewRecords(<?= $period['period_id']; ?>, '<?= addslashes($period['period_name']); ?>')"
                                    title="View Payroll Records">
                                <i class="bi bi-list-ul"></i> Records
                            </button>
                            <!-- COMPUTE -->
                            <button class="btn btn-sm btn-outline-secondary"
                                    onclick="openPayrollModal(<?= $period['period_id']; ?>, '<?= addslashes($period['period_name']); ?>')"
                                    title="Compute Payroll">
                                <i class="bi bi-calculator"></i>
                            </button>
                            <?php if(in_array($period['status'], ['Draft','For Approval']) && $period['employee_count'] > 0){ ?>
                            <!-- APPROVE -->
                            <button class="btn btn-sm btn-warning"
                                    onclick="approvePeriod(<?= $period['period_id']; ?>)"
                                    title="Approve Period">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <?php } ?>
                            <?php if($period['status'] === 'Approved'){ ?>
                            <!-- MARK PAID -->
                            <button class="btn btn-sm btn-success"
                                    onclick="markPaid(<?= $period['period_id']; ?>)"
                                    title="Mark as Paid">
                                <i class="bi bi-cash"></i>
                            </button>
                            <?php } ?>
                            <!-- DELETE -->
                            <button class="btn btn-sm btn-outline-danger"
                                    onclick="deletePeriod(<?= $period['period_id']; ?>, '<?= addslashes($period['period_name']); ?>')"
                                    title="Delete Period">
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
    CREATE PERIOD MODAL
==========================================================-->
<div class="modal fade" id="createPeriodModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header modal-header-primary">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-calendar2-week me-2"></i>New Payroll Period
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Loading spinner while fetching auto-dates -->
                <div id="periodLoadingSpinner" class="text-center py-3">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2 text-muted" style="font-size:13px;">Calculating next payroll cycle&hellip;</div>
                </div>
                <div id="periodFormFields" style="display:none;">
                    <!-- Info banner -->
                    <div class="alert alert-info d-flex align-items-start gap-2 mb-3 py-2" style="font-size:12.5px;">
                        <i class="bi bi-info-circle-fill mt-1"></i>
                        <div>
                            Payroll periods follow the <strong>semi-monthly 15-day cycle</strong> (1st–15th and 16th–end of month).
                            Dates are automatically calculated based on the last existing period.
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Period Name</label>
                            <input type="text" class="form-control bg-light" id="period_name" readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Date From</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                                <input type="date" class="form-control bg-light" id="period_from" readonly>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Date To</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                                <input type="date" class="form-control bg-light" id="period_to" readonly>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Pay Date <span class="text-muted fw-normal" style="font-size:11px;">(auto-set)</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-cash-coin"></i></span>
                                <input type="date" class="form-control bg-light" id="period_pay_date" readonly>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="periodErrorMsg" class="alert alert-danger mt-2" style="display:none; font-size:13px;"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="btnCreatePeriod" onclick="submitCreatePeriod()" disabled>
                    <i class="bi bi-check-lg me-1"></i>Create Period
                </button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    VIEW RECORDS MODAL
==========================================================-->
<div class="modal fade" id="viewRecordsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header modal-header-primary">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-list-ul me-2"></i>Payroll Records &mdash; <span id="recPeriodName"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="recLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2 text-muted">Loading records&hellip;</div>
                </div>
                <div id="recContent" style="display:none;">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="recordsTable" style="font-size:13px;">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Employee</th>
                                    <th style="text-align:right;">Basic Pay</th>
                                    <th style="text-align:right;">OT Pay</th>
                                    <th style="text-align:right;">Gross Pay</th>
                                    <th style="text-align:right;">SSS</th>
                                    <th style="text-align:right;">PhilHealth</th>
                                    <th style="text-align:right;">Pag-IBIG</th>
                                    <th style="text-align:right;">W/Tax</th>
                                    <th style="text-align:right;">Other Ded.</th>
                                    <th style="text-align:right;font-weight:700;">Net Pay</th>
                                </tr>
                            </thead>
                            <tbody id="recBody"></tbody>
                            <tfoot>
                                <tr class="table-light fw-bold">
                                    <td colspan="4">TOTAL</td>
                                    <td style="text-align:right;" id="recTotalGross">&#8369;0.00</td>
                                    <td colspan="4"></td>
                                    <td></td>
                                    <td style="text-align:right; color:#16a34a;" id="recTotalNet">&#8369;0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div id="recEmpty" class="text-center text-muted py-4" style="display:none;">
                        <i class="bi bi-inbox fs-1"></i>
                        <div class="mt-2">No payroll records yet. Use the <strong>Compute</strong> button to add employees.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    COMPUTE PAYROLL MODAL
==========================================================-->
<div class="modal fade" id="payrollModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background:#1a3c5e; color:white;">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-calculator me-2"></i>
                    Compute Payroll &mdash; <span id="modalPeriodName"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="current_period_id">
                <div class="row g-3">
                    <!-- LEFT: INPUTS -->
                    <div class="col-lg-5">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Select Employee</label>
                            <select class="form-select" id="sel_employee" onchange="onEmployeeChange()">
                                <option value="">-- Select Employee --</option>
                                <?php foreach($employeeList as $e){ ?>
                                <option value="<?= $e['employee_id']; ?>"
                                        data-salary="<?= $e['basic_salary']; ?>"
                                        data-name="<?= htmlspecialchars($e['full_name']); ?>">
                                    <?= htmlspecialchars($e['full_name']); ?> &mdash;
                                    &#8369;<?= number_format($e['basic_salary'],2); ?>/mo
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Basic Salary</label>
                                <div class="input-group">
                                    <span class="input-group-text">&#8369;</span>
                                    <input type="number" class="form-control" id="inp_basic" step="0.01" min="0" readonly>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Daily Rate</label>
                                <div class="input-group">
                                    <span class="input-group-text">&#8369;</span>
                                    <input type="number" class="form-control" id="inp_daily" step="0.01" min="0" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <label class="form-label fw-semibold">Days Worked</label>
                                <input type="number" class="form-control" id="inp_days"
                                       step="0.5" min="0" max="26" placeholder="e.g. 13"
                                       oninput="computePayroll()">
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-semibold">Hours / Day</label>
                                <input type="number" class="form-control" id="inp_hours_per_day"
                                       step="0.5" min="0" max="24" value="8" placeholder="e.g. 8"
                                       oninput="computePayroll()">
                            </div>
                            <div class="col-4">
                                <label class="form-label fw-semibold">Overtime Hours</label>
                                <input type="number" class="form-control" id="inp_overtime"
                                       step="0.5" min="0" placeholder="e.g. 2"
                                       oninput="computePayroll()">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Other Deductions (&#8369;)</label>
                            <input type="number" class="form-control" id="inp_other_ded"
                                   step="0.01" min="0" placeholder="e.g. 500 for cash advance"
                                   oninput="computePayroll()">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Deduction Notes</label>
                            <textarea class="form-control" id="inp_ded_notes" rows="2"
                                      placeholder="e.g. Cash advance deduction..."></textarea>
                        </div>
                    </div>
                    <!-- RIGHT: PAYSLIP PREVIEW -->
                    <div class="col-lg-7">
                        <div style="background:#f8f9fa; border-radius:12px; padding:20px;" id="payslipPreview">
                            <div class="text-center mb-3">
                                <strong style="font-size:16px;">&#127978; O-CART!</strong><br>
                                <small class="text-muted">Payslip Preview</small><br>
                                <small id="prev_empName" class="text-muted">&#8212;</small>
                            </div>
                            <hr>
                            <div class="fw-bold text-success mb-2" style="font-size:13px;">EARNINGS</div>
                            <div class="deduction-row">
                                <span class="deduction-label">Basic Pay</span>
                                <span class="deduction-value" id="prev_basic">&#8369;0.00</span>
                            </div>
                            <div class="deduction-row">
                                <span class="deduction-label">Overtime Pay (125%)</span>
                                <span class="deduction-value" id="prev_ot">&#8369;0.00</span>
                            </div>
                            <div class="deduction-row" style="border-top:2px solid #dee2e6; padding-top:10px;">
                                <span class="fw-bold">Gross Pay</span>
                                <span class="fw-bold text-success" id="prev_gross">&#8369;0.00</span>
                            </div>
                            <hr>
                            <div class="fw-bold text-danger mb-2" style="font-size:13px;">GOVERNMENT DEDUCTIONS</div>
                            <div class="deduction-row">
                                <span class="deduction-label">SSS</span>
                                <span class="deduction-value text-danger" id="prev_sss">&#8369;0.00</span>
                            </div>
                            <div class="deduction-row">
                                <span class="deduction-label">PhilHealth (2.5%)</span>
                                <span class="deduction-value text-danger" id="prev_ph">&#8369;0.00</span>
                            </div>
                            <div class="deduction-row">
                                <span class="deduction-label">Pag-IBIG</span>
                                <span class="deduction-value text-danger" id="prev_pi">&#8369;0.00</span>
                            </div>
                            <div class="deduction-row">
                                <span class="deduction-label">Withholding Tax (TRAIN)</span>
                                <span class="deduction-value text-danger" id="prev_wtax">&#8369;0.00</span>
                            </div>
                            <div class="deduction-row">
                                <span class="deduction-label">Other Deductions</span>
                                <span class="deduction-value text-danger" id="prev_other">&#8369;0.00</span>
                            </div>
                            <div class="deduction-row" style="border-top:2px solid #dee2e6; padding-top:10px;">
                                <span class="fw-bold">Total Deductions</span>
                                <span class="fw-bold text-danger" id="prev_total_ded">&#8369;0.00</span>
                            </div>
                            <hr>
                            <div class="net-pay-box mt-3">
                                <div style="font-size:13px; opacity:.8; margin-bottom:4px;">NET PAY</div>
                                <div style="font-size:32px; font-weight:900;" id="prev_net">&#8369;0.00</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-success" onclick="savePayroll()">
                    <i class="bi bi-floppy-disk me-1"></i>Save Payroll Record
                </button>
            </div>
        </div>
    </div>
</div>

<script>

let currentComputed = {};

function fmt(n){ return '&#8369;' + parseFloat(n || 0).toLocaleString('en-PH', {minimumFractionDigits:2}); }

/*====================================================
    DATATABLE INIT
====================================================*/
$(document).ready(function(){
    if($.fn.DataTable){
        $('#payrollTable').DataTable({
            destroy: true,
            pageLength: 15,
            order: [[0, 'asc']],
            columnDefs: [{ orderable: false, targets: [7] }],
            language: {
                emptyTable: 'No payroll periods yet. Click "New Payroll Period" to get started.',
                search: 'Search periods:',
                lengthMenu: 'Show _MENU_ periods'
            }
        });
    }
});

/*====================================================
    CREATE PERIOD (Auto 15-day semi-monthly cycle)
====================================================*/
function openCreatePeriodModal(){
    // Reset state
    $('#periodLoadingSpinner').show();
    $('#periodFormFields').hide();
    $('#periodErrorMsg').hide();
    $('#btnCreatePeriod').prop('disabled', true);
    $("#period_name, #period_from, #period_to, #period_pay_date").val('');

    new bootstrap.Modal(document.getElementById('createPeriodModal')).show();

    // Fetch the next auto-calculated period dates from the server
    $.post('hrms_payroll.php', { action: 'get_next_period' }, function(response){
        try {
            const d = (typeof response === 'string') ? JSON.parse(response) : response;
            if(d.error){
                $('#periodLoadingSpinner').hide();
                $('#periodErrorMsg').text(d.error).show();
                return;
            }
            $('#period_name').val(d.period_name);
            $('#period_from').val(d.date_from);
            $('#period_to').val(d.date_to);
            $('#period_pay_date').val(d.pay_date);

            $('#periodLoadingSpinner').hide();
            $('#periodFormFields').show();
            $('#btnCreatePeriod').prop('disabled', false);
        } catch(e) {
            $('#periodLoadingSpinner').hide();
            $('#periodErrorMsg').text('Failed to load period dates. Please try again.').show();
        }
    });
}

function submitCreatePeriod(){
    const name     = $("#period_name").val().trim();
    const from     = $("#period_from").val();
    const to       = $("#period_to").val();
    const pay_date = $("#period_pay_date").val();

    if(!name || !from || !to || !pay_date){
        Swal.fire('Error', 'Period dates could not be loaded. Please close and try again.', 'warning');
        return;
    }

    Swal.fire({
        title: 'Create Payroll Period?',
        html: `<strong>${name}</strong><br><span class="text-muted" style="font-size:13px;">${from} &ndash; ${to} &nbsp;|&nbsp; Pay Date: ${pay_date}</span>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        confirmButtonText: 'Yes, Create'
    }).then(result => {
        if(!result.isConfirmed) return;
        $('#btnCreatePeriod').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Creating...');

        $.post('hrms_payroll.php', {
            action: 'create_period',
            period_name: name, date_from: from, date_to: to, pay_date: pay_date
        }, function(response){
            if(response.startsWith('success:')){
                Swal.fire({ icon:'success', title:'Period Created!', text: name, showConfirmButton:false, timer:1800 })
                .then(() => { clearBackdrop(); loadPage('hrms_payroll.php'); });
            } else {
                const errMsg = response.replace(/^error:\s*/i, '');
                Swal.fire('Cannot Create Period', errMsg, 'error');
                $('#btnCreatePeriod').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Create Period');
            }
        });
    });
}

/*====================================================
    VIEW RECORDS
====================================================*/
function viewRecords(periodId, periodName){
    document.getElementById('recPeriodName').textContent = periodName;
    document.getElementById('recLoading').style.display = '';
    document.getElementById('recContent').style.display = 'none';
    new bootstrap.Modal(document.getElementById('viewRecordsModal')).show();

    $.get('hrms_payroll.php', { action: 'get_records', period_id: periodId }, function(data){
        document.getElementById('recLoading').style.display = 'none';
        document.getElementById('recContent').style.display = '';

        const tbody = document.getElementById('recBody');
        tbody.innerHTML = '';

        if(!data || data.length === 0){
            document.getElementById('recordsTable').style.display = 'none';
            document.getElementById('recEmpty').style.display = '';
            return;
        }
        document.getElementById('recordsTable').style.display = '';
        document.getElementById('recEmpty').style.display = 'none';

        let totalGross = 0, totalNet = 0;
        data.forEach((r, i) => {
            totalGross += parseFloat(r.gross_pay || 0);
            totalNet   += parseFloat(r.net_pay   || 0);
            tbody.innerHTML += `
                <tr>
                    <td class="text-muted">${i+1}</td>
                    <td>
                        <div class="fw-semibold">${r.full_name}</div>
                        <div class="text-muted" style="font-size:11px;">${r.employee_no}</div>
                    </td>
                    <td style="text-align:right;">&#8369;${fmtN(r.gross_pay - r.overtime_pay)}</td>
                    <td style="text-align:right;">&#8369;${fmtN(r.overtime_pay)}</td>
                    <td style="text-align:right; font-weight:600;">&#8369;${fmtN(r.gross_pay)}</td>
                    <td style="text-align:right; color:#dc2626;">&#8369;${fmtN(r.sss)}</td>
                    <td style="text-align:right; color:#dc2626;">&#8369;${fmtN(r.philhealth)}</td>
                    <td style="text-align:right; color:#dc2626;">&#8369;${fmtN(r.pagibig)}</td>
                    <td style="text-align:right; color:#dc2626;">&#8369;${fmtN(r.withholding_tax)}</td>
                    <td style="text-align:right; color:#dc2626;">&#8369;${fmtN(r.other_deductions)}</td>
                    <td style="text-align:right; font-weight:700; color:#16a34a;">&#8369;${fmtN(r.net_pay)}</td>
                </tr>`;
        });
        document.getElementById('recTotalGross').innerHTML = '&#8369;' + fmtN(totalGross);
        document.getElementById('recTotalNet').innerHTML   = '&#8369;' + fmtN(totalNet);
    }, 'json').fail(function(){
        document.getElementById('recLoading').style.display = 'none';
        document.getElementById('recContent').style.display = '';
        document.getElementById('recEmpty').style.display = '';
        document.getElementById('recordsTable').style.display = 'none';
    });
}

function fmtN(n){ return parseFloat(n||0).toLocaleString('en-PH',{minimumFractionDigits:2}); }

/*====================================================
    COMPUTE PAYROLL
====================================================*/
function openPayrollModal(periodId, periodName){
    $("#current_period_id").val(periodId);
    $("#modalPeriodName").text(periodName);
    $("#sel_employee").val('');
    $("#inp_basic, #inp_daily, #inp_days, #inp_overtime, #inp_other_ded, #inp_ded_notes").val('');
    $("#inp_hours_per_day").val('8');
    resetPreview();
    new bootstrap.Modal(document.getElementById('payrollModal')).show();
}

function onEmployeeChange(){
    const opt      = $("#sel_employee option:selected");
    const salary   = parseFloat(opt.data('salary')) || 0;
    const name     = opt.data('name') || '&#8212;';
    const emp_id   = $("#sel_employee").val();
    const periodId = $("#current_period_id").val();

    $("#inp_basic").val(salary.toFixed(2));
    $("#inp_daily").val((salary / 26).toFixed(2));
    $("#prev_empName").html(name);
    resetPreview();

    if(!emp_id) return;

    // Auto-fill Days Worked / Hours per Day / Overtime from attendance records
    $.post('hrms_payroll.php', {
        action: 'get_attendance_summary',
        employee_id: emp_id,
        period_id: periodId
    }, function(response){
        try {
            const d = JSON.parse(response);
            if(d.error){ return; }
            $("#inp_days").val(d.days_worked);
            $("#inp_hours_per_day").val(d.hours_per_day || 8);
            $("#inp_overtime").val(d.overtime_hours);
            computePayroll();
        } catch(e){ console.error('Attendance summary parse error:', response); }
    });
}

function resetPreview(){
    ["prev_basic","prev_ot","prev_gross","prev_sss","prev_ph",
     "prev_pi","prev_wtax","prev_other","prev_total_ded","prev_net"]
    .forEach(id => $("#"+id).html('&#8369;0.00'));
}

function computePayroll(){
    const emp_id      = $("#sel_employee").val();
    const period      = $("#current_period_id").val();
    const days        = parseFloat($("#inp_days").val()) || 0;
    const hoursPerDay = parseFloat($("#inp_hours_per_day").val()) || 8;
    const overtime    = parseFloat($("#inp_overtime").val()) || 0;
    if(!emp_id || days <= 0) return;

    $.post('hrms_payroll.php', {
        action: 'compute', employee_id: emp_id,
        period_id: period, days_worked: days, hours_per_day: hoursPerDay, overtime_hours: overtime
    }, function(response){
        try {
            const d = JSON.parse(response);
            if(d.error){ Swal.fire('Error', d.error, 'error'); return; }
            const other     = parseFloat($("#inp_other_ded").val()) || 0;
            const total_ded = d.sss + d.philhealth + d.pagibig + d.withholding_tax + other;
            const net       = d.gross_pay - total_ded;
            currentComputed = { ...d, other_deductions: other, total_deductions: total_ded, net_pay: net };
            $("#inp_daily").val(d.daily_rate);
            $("#prev_basic").html(fmt(d.basic_pay));
            $("#prev_ot").html(fmt(d.overtime_pay));
            $("#prev_gross").html(fmt(d.gross_pay));
            $("#prev_sss").html(fmt(d.sss));
            $("#prev_ph").html(fmt(d.philhealth));
            $("#prev_pi").html(fmt(d.pagibig));
            $("#prev_wtax").html(fmt(d.withholding_tax));
            $("#prev_other").html(fmt(other));
            $("#prev_total_ded").html(fmt(total_ded));
            $("#prev_net").html(fmt(net));
        } catch(e){ console.error('Parse error:', response); }
    });
}

function savePayroll(){
    if(!currentComputed.gross_pay){
        Swal.fire('Not Computed', 'Please compute payroll first.', 'warning'); return;
    }
    const emp_id = $("#sel_employee").val();
    if(!emp_id){
        Swal.fire('No Employee', 'Please select an employee.', 'warning'); return;
    }

    Swal.fire({
        title: 'Save this payroll record?',
        html: `Net pay: <strong>₱${parseFloat(currentComputed.net_pay).toLocaleString('en-PH',{minimumFractionDigits:2})}</strong>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Yes, Save'
    }).then(result => {
        if(!result.isConfirmed) return;

        $.post('hrms_payroll.php', {
            action:           'save_payroll',
            period_id:        $("#current_period_id").val(),
            employee_id:      emp_id,
            basic_salary:     $("#inp_basic").val(),
            days_worked:      $("#inp_days").val(),
            hours_per_day:    $("#inp_hours_per_day").val(),
            overtime_pay:     currentComputed.overtime_pay,
            gross_pay:        currentComputed.gross_pay,
            sss:              currentComputed.sss,
            philhealth:       currentComputed.philhealth,
            pagibig:          currentComputed.pagibig,
            withholding_tax:  currentComputed.withholding_tax,
            other_deductions: currentComputed.other_deductions,
            deduction_notes:  $("#inp_ded_notes").val()
        }, function(response){
            if(response == 'success'){
                Swal.fire({ icon:'success', title:'Payroll Saved!', showConfirmButton:false, timer:1500 });
                loadPage('hrms_payroll.php');
            } else {
                Swal.fire('Error', response, 'error');
            }
        });
    });
}

/*====================================================
    APPROVE / MARK PAID
====================================================*/
function approvePeriod(periodId){
    Swal.fire({
        title: 'Approve this Payroll Period?',
        text: 'Once approved, it will be ready for payment processing.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        confirmButtonText: 'Yes, Approve'
    }).then(result => {
        if(!result.isConfirmed) return;
        $.post('hrms_payroll.php', { action:'approve_period', period_id:periodId }, function(response){
            if(response == 'success'){
                Swal.fire({ icon:'success', title:'Period Approved!', showConfirmButton:false, timer:1500 })
                .then(() => loadPage('hrms_payroll.php'));
            } else {
                Swal.fire('Error', response, 'error');
            }
        });
    });
}

function markPaid(periodId){
    Swal.fire({
        title: 'Mark Payroll as PAID?',
        text: 'This confirms that all employees in this period have been paid.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Yes, Mark as Paid'
    }).then(result => {
        if(!result.isConfirmed) return;
        $.post('hrms_payroll.php', { action:'mark_paid', period_id:periodId }, function(response){
            if(response == 'success'){
                Swal.fire({ icon:'success', title:'Payroll Marked as Paid!', showConfirmButton:false, timer:1500 })
                .then(() => loadPage('hrms_payroll.php'));
            }
        });
    });
}

/*====================================================
    DELETE PERIOD
====================================================*/
function deletePeriod(periodId, periodName){
    Swal.fire({
        title: 'Delete Payroll Period?',
        html: `This will permanently delete <strong>${periodName}</strong> and all its payroll records. This cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Delete Period'
    }).then(result => {
        if(!result.isConfirmed) return;
        $.post('hrms_payroll.php', { action:'delete_period', period_id:periodId }, function(response){
            if(response.trim() === 'success'){
                Swal.fire({ icon:'success', title:'Period Deleted!', showConfirmButton:false, timer:1500 })
                .then(() => loadPage('hrms_payroll.php'));
            } else {
                Swal.fire('Error', response, 'error');
            }
        });
    });
}

function openArchivePayrollModal() {
    const modalEl = document.getElementById('archivePayrollModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }
}
window.openArchivePayrollModal = openArchivePayrollModal;

function openRejectedPayrollModal() {
    const modalEl = document.getElementById('rejectedPayrollModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }
}
window.openRejectedPayrollModal = openRejectedPayrollModal;

function archivePayrollRecord(id, name) {
    Swal.fire({
        title: 'Archive Payroll Record?',
        html: `<p class="text-muted mb-2" style="font-size:13px;">Archive payroll record for <strong>${name}</strong>.</p>
               <input id="archReasonPay" class="swal2-input" placeholder="Reason e.g. Recalculated entry, Audit adjustment...">`,
        showCancelButton: true,
        confirmButtonColor: '#6c757d',
        confirmButtonText: 'Archive Record',
        preConfirm: () => {
            const r = document.getElementById('archReasonPay').value.trim();
            if (!r) { Swal.showValidationMessage('Please provide a reason.'); return false; }
            return r;
        }
    }).then(result => {
        if (!result.isConfirmed) return;
        $.post('hrms_payroll.php', { action: 'archive_payroll', payroll_id: id, reason: result.value }, function (res) {
            if (res.trim() === 'success') {
                Swal.fire({ icon: 'success', title: 'Payroll Archived!', timer: 1500, showConfirmButton: false })
                    .then(() => loadPage('hrms_payroll.php'));
            } else {
                Swal.fire('Error', res, 'error');
            }
        });
    });
}
window.archivePayrollRecord = archivePayrollRecord;

function restorePayrollRecord(archiveId, name) {
    Swal.fire({
        title: 'Restore Payroll Record?',
        html: `Restore payroll record for <strong>${name}</strong> back to active list?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Yes, Restore'
    }).then(result => {
        if (!result.isConfirmed) return;
        $.post('hrms_payroll.php', { action: 'restore_payroll', archive_id: archiveId }, function (res) {
            if (res.trim() === 'success') {
                Swal.fire({ icon: 'success', title: 'Restored!', timer: 1500, showConfirmButton: false })
                    .then(() => { clearBackdropHrms(); loadPage('hrms_payroll.php'); });
            } else {
                Swal.fire('Error', res, 'error');
            }
        });
    });
}
window.restorePayrollRecord = restorePayrollRecord;

function rejectPayrollRecord(id, name) {
    Swal.fire({
        title: 'Reject / Dispute Payroll Record?',
        html: `<p class="text-muted mb-2" style="font-size:13px;">Record dispute / rejection notes for <strong>${name}</strong>.</p>
               <input id="rejReasonPay" class="swal2-input" placeholder="Reason e.g. Disputed overtime hours, Incorrect rate...">`,
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Reject Record',
        preConfirm: () => {
            const r = document.getElementById('rejReasonPay').value.trim();
            if (!r) { Swal.showValidationMessage('Please provide a reason.'); return false; }
            return r;
        }
    }).then(result => {
        if (!result.isConfirmed) return;
        $.post('hrms_payroll.php', { action: 'reject_payroll', payroll_id: id, reason: result.value }, function (res) {
            if (res.trim() === 'success') {
                Swal.fire({ icon: 'success', title: 'Payroll Disputed / Rejected!', timer: 1500, showConfirmButton: false })
                    .then(() => loadPage('hrms_payroll.php'));
            } else {
                Swal.fire('Error', res, 'error');
            }
        });
    });
}
window.rejectPayrollRecord = rejectPayrollRecord;

window.openCreatePeriodModal = openCreatePeriodModal;
window.openRunPayrollModal   = openRunPayrollModal;
window.computePayroll        = computePayroll;
window.savePayroll           = savePayroll;
window.approvePeriod         = approvePeriod;
window.markPaid              = markPaid;
window.deletePeriod          = deletePeriod;
</script>

<!-- ARCHIVE PAYROLL MODAL -->
<div class="modal fade" id="archivePayrollModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-archive-fill me-2"></i>Archived Payroll Records</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100 fs-7">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Employee</th>
                                <th>Gross Pay</th>
                                <th>Total Deductions</th>
                                <th>Net Pay</th>
                                <th>Archival Reason</th>
                                <th>Archived At</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $archPayQuery = mysqli_query($conn, "
                                SELECT a.*, e.full_name, e.employee_no 
                                FROM payroll_archive a 
                                LEFT JOIN employees e ON a.employee_id = e.employee_id 
                                ORDER BY a.archived_at DESC
                            ");
                            if (mysqli_num_rows($archPayQuery) > 0) {
                                while ($archRow = mysqli_fetch_assoc($archPayQuery)) {
                                    echo '<tr>';
                                    echo '<td><span class="badge bg-secondary font-monospace">#' . $archRow['archive_id'] . '</span></td>';
                                    echo '<td><div class="fw-bold">' . htmlspecialchars($archRow['full_name'] ?? 'N/A') . '</div><small class="text-muted">' . htmlspecialchars($archRow['employee_no'] ?? '') . '</small></td>';
                                    echo '<td>₱' . number_format($archRow['gross_pay'], 2) . '</td>';
                                    echo '<td>₱' . number_format($archRow['total_deductions'], 2) . '</td>';
                                    echo '<td class="fw-bold text-success">₱' . number_format($archRow['net_pay'], 2) . '</td>';
                                    echo '<td><span class="text-danger fw-semibold"><i class="bi bi-chat-left-quote me-1"></i>' . htmlspecialchars($archRow['archive_reason'] ?: 'No reason provided') . '</span></td>';
                                    echo '<td><small class="text-muted">' . date('M d, Y h:i A', strtotime($archRow['archived_at'])) . '</small></td>';
                                    echo '<td class="text-center"><button class="btn btn-sm btn-success" onclick="restorePayrollRecord(' . $archRow['archive_id'] . ', \'' . addslashes($archRow['full_name']) . '\')"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</button></td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="8" class="text-center text-muted py-4"><i class="bi bi-inbox me-1"></i>No archived payroll records found.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- REJECTED PAYROLL MODAL -->
<div class="modal fade" id="rejectedPayrollModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-x-circle-fill me-2"></i>Rejected / Disputed Payroll Records</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100 fs-7">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Employee</th>
                                <th>Gross Pay</th>
                                <th>Net Pay</th>
                                <th>Rejection Reasoning / Disputed Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rejPayQuery = mysqli_query($conn, "
                                SELECT p.*, e.full_name, e.employee_no 
                                FROM payroll p 
                                JOIN employees e ON p.employee_id = e.employee_id 
                                WHERE p.rejection_notes IS NOT NULL AND p.rejection_notes != '' 
                                ORDER BY p.created_at DESC
                            ");
                            if (mysqli_num_rows($rejPayQuery) > 0) {
                                while ($rejRow = mysqli_fetch_assoc($rejPayQuery)) {
                                    echo '<tr>';
                                    echo '<td><span class="badge bg-danger font-monospace">#' . $rejRow['payroll_id'] . '</span></td>';
                                    echo '<td><div class="fw-bold">' . htmlspecialchars($rejRow['full_name']) . '</div><small class="text-muted">' . htmlspecialchars($rejRow['employee_no']) . '</small></td>';
                                    echo '<td>₱' . number_format($rejRow['gross_pay'], 2) . '</td>';
                                    echo '<td class="fw-bold text-dark">₱' . number_format($rejRow['net_pay'], 2) . '</td>';
                                    echo '<td><span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i>' . htmlspecialchars($rejRow['rejection_notes']) . '</span></td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="5" class="text-center text-muted py-4"><i class="bi bi-check-circle me-1"></i>No rejected or disputed payroll records found.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
