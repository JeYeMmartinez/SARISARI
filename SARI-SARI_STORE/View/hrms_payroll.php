<?php
require_once '../Model/database.php';

if(session_status() === PHP_SESSION_NONE){
    session_start();
}
$admin_id = $_SESSION['user_id'] ?? 1;

/*=========================================================
    PHILIPPINE DEDUCTION CALCULATORS
==========================================================*/

function computeSSS($monthly_salary){
    // SSS Table 2024 — Employee Share
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
    return 900.00; // Max at 20000+
}

function computePhilHealth($monthly_salary){
    // PhilHealth 2024 — 5% of basic salary, split 50/50, employee pays 2.5%
    // Minimum: 500/month (employee: 250)
    // Maximum: salary ceiling 100,000 → max employee share 1,250
    $rate      = 0.05;
    $premium   = $monthly_salary * $rate;
    $employee  = $premium / 2;
    $min       = 250.00;  // employee minimum
    $max       = 1250.00; // employee maximum
    return max($min, min($max, $employee));
}

function computePagIbig($monthly_salary){
    // Pag-IBIG 2024 — employee share
    // Salary <= 1,500: 1% | Salary > 1,500: 2%
    // Max monthly compensation for computation: 5,000
    $comp = min($monthly_salary, 5000);
    if($monthly_salary <= 1500){
        return $comp * 0.01;
    }
    return $comp * 0.02; // Max = 100
}

function computeWithholdingTax($monthly_salary, $sss, $philhealth, $pagibig){
    // TRAIN Law 2023 — Annual tax computation
    $taxable_monthly = $monthly_salary - $sss - $philhealth - $pagibig;
    $annual_taxable  = $taxable_monthly * 12;

    if($annual_taxable <= 250000)          $annual_tax = 0;
    elseif($annual_taxable <= 400000)      $annual_tax = ($annual_taxable - 250000) * 0.15;
    elseif($annual_taxable <= 800000)      $annual_tax = 22500  + ($annual_taxable - 400000) * 0.20;
    elseif($annual_taxable <= 2000000)     $annual_tax = 102500 + ($annual_taxable - 800000) * 0.25;
    elseif($annual_taxable <= 8000000)     $annual_tax = 402500 + ($annual_taxable - 2000000) * 0.30;
    else                                   $annual_tax = 2202500 + ($annual_taxable - 8000000) * 0.35;

    return round($annual_tax / 12, 2);
}

/*=========================================================
    ACTIONS
==========================================================*/

// COMPUTE DEDUCTIONS (AJAX)
if(isset($_POST['action']) && $_POST['action'] == 'compute'){
    $employee_id   = (int)$_POST['employee_id'];
    $period_id     = (int)$_POST['period_id'];
    $days_worked   = (float)$_POST['days_worked'];
    $hours_per_day = (float)($_POST['hours_per_day'] ?? 8);
    $overtime      = (float)($_POST['overtime_hours'] ?? 0);

    $emp = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM employees WHERE employee_id = $employee_id"
    ));

    if(!$emp){ echo json_encode(['error'=>'Employee not found']); exit(); }

    $monthly     = (float)$emp['basic_salary'];
    $daily_rate  = $monthly / 26; // 26 working days
    $hourly_rate = $daily_rate / 8; // rate basis: a standard 8-hour day

    $basic_pay    = $hourly_rate * $hours_per_day * $days_worked; // actual hours rendered per day
    $overtime_pay = $hourly_rate * 1.25 * $overtime; // OT = 125%
    $gross_pay    = $basic_pay + $overtime_pay;

    $sss          = computeSSS($monthly);
    $philhealth   = computePhilHealth($monthly);
    $pagibig      = computePagIbig($monthly);
    $wtax         = computeWithholdingTax($monthly, $sss, $philhealth, $pagibig);

    $total_deductions = $sss + $philhealth + $pagibig + $wtax;
    $net_pay          = $gross_pay - $total_deductions;

    ob_clean();
    echo json_encode([
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
    ]);
    exit();
}

// SAVE PAYROLL RECORD
if(isset($_POST['action']) && $_POST['action'] == 'save_payroll'){
    $period_id        = (int)$_POST['period_id'];
    $employee_id      = (int)$_POST['employee_id'];
    $basic_salary     = (float)$_POST['basic_salary'];
    $days_worked      = (float)$_POST['days_worked'];
    $hours_per_day    = (float)($_POST['hours_per_day'] ?? 8);
    $overtime_pay     = (float)$_POST['overtime_pay'];
    $gross_pay        = (float)$_POST['gross_pay'];
    $sss              = (float)$_POST['sss'];
    $philhealth       = (float)$_POST['philhealth'];
    $pagibig          = (float)$_POST['pagibig'];
    $wtax             = (float)$_POST['withholding_tax'];
    $other_ded        = (float)$_POST['other_deductions'];
    $ded_notes        = mysqli_real_escape_string($conn, $_POST['deduction_notes'] ?? '');
    $total_deductions = $sss + $philhealth + $pagibig + $wtax + $other_ded;
    $net_pay          = $gross_pay - $total_deductions;

    // Check if already exists
    $exists = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT payroll_id FROM payroll WHERE period_id=$period_id AND employee_id=$employee_id"
    ));

    if($exists){
        $pid = $exists['payroll_id'];
        $q = mysqli_query($conn,"
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
        $q = mysqli_query($conn,"
            INSERT INTO payroll (period_id, employee_id, basic_salary, days_worked, hours_per_day,
                overtime_pay, gross_pay, sss, philhealth, pagibig, withholding_tax,
                other_deductions, deduction_notes, total_deductions, net_pay)
            VALUES ($period_id, $employee_id, $basic_salary, $days_worked, $hours_per_day,
                $overtime_pay, $gross_pay, $sss, $philhealth, $pagibig, $wtax,
                $other_ded, '$ded_notes', $total_deductions, $net_pay)
        ");
    }

    ob_clean();
    echo $q ? 'success' : 'error: ' . mysqli_error($conn);
    exit();
}

// CREATE PAYROLL PERIOD
if(isset($_POST['action']) && $_POST['action'] == 'create_period'){
    $name     = mysqli_real_escape_string($conn, $_POST['period_name']);
    $from     = $_POST['date_from'];
    $to       = $_POST['date_to'];
    $pay_date = $_POST['pay_date'];

    $q = mysqli_query($conn,"
        INSERT INTO payroll_periods (period_name, date_from, date_to, pay_date, created_by)
        VALUES ('$name', '$from', '$to', '$pay_date', $admin_id)
    ");

    ob_clean();
    echo $q ? 'success:'.mysqli_insert_id($conn) : 'error: '.mysqli_error($conn);
    exit();
}

// APPROVE PAYROLL PERIOD
if(isset($_POST['action']) && $_POST['action'] == 'approve_period'){
    $period_id = (int)$_POST['period_id'];
    $q = mysqli_query($conn,
        "UPDATE payroll_periods SET status='Approved' WHERE period_id=$period_id"
    );
    ob_clean();
    echo $q ? 'success' : 'error: '.mysqli_error($conn);
    exit();
}

// MARK AS PAID
if(isset($_POST['action']) && $_POST['action'] == 'mark_paid'){
    $period_id = (int)$_POST['period_id'];
    mysqli_query($conn,
        "UPDATE payroll_periods SET status='Paid' WHERE period_id=$period_id"
    );
    mysqli_query($conn,
        "UPDATE payroll SET status='Paid' WHERE period_id=$period_id"
    );
    ob_clean();
    echo 'success';
    exit();
}

// DELETE PERIOD
if(isset($_POST['action']) && $_POST['action'] == 'delete_period'){
    $period_id = (int)$_POST['period_id'];
    mysqli_query($conn, "DELETE FROM payroll WHERE period_id=$period_id");
    $q = mysqli_query($conn, "DELETE FROM payroll_periods WHERE period_id=$period_id");
    ob_clean();
    echo $q ? 'success' : 'error: '.mysqli_error($conn);
    exit();
}

// GET PERIOD RECORDS (AJAX - GET)
if(isset($_GET['action']) && $_GET['action'] == 'get_records'){
    $period_id = (int)$_GET['period_id'];
    $res = mysqli_query($conn,
        "SELECT p.*, e.full_name, e.employee_no
         FROM payroll p
         JOIN employees e ON p.employee_id = e.employee_id
         WHERE p.period_id = $period_id
         ORDER BY e.full_name ASC"
    );
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
$periodsResult = mysqli_query($conn,"
    SELECT pp.*, u.full_name AS created_by_name,
           COUNT(p.payroll_id) AS employee_count,
           IFNULL(SUM(p.net_pay),0) AS total_net
    FROM payroll_periods pp
    LEFT JOIN users u ON pp.created_by = u.user_id
    LEFT JOIN payroll p ON pp.period_id = p.period_id
    GROUP BY pp.period_id
    ORDER BY pp.created_at DESC
");

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

$employees = mysqli_query($conn,"
    SELECT e.*, pos.position_name, d.department_name
    FROM employees e
    LEFT JOIN positions pos ON e.position_id = pos.position_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE e.status = 'Active'
    ORDER BY e.full_name ASC
");
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
    <button class="btn btn-primary" onclick="openCreatePeriodModal()">
        <i class="bi bi-plus-lg me-1"></i> New Payroll Period
    </button>
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
                    <i class="bi bi-plus-circle me-2"></i>New Payroll Period
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Period Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="period_name"
                               placeholder="e.g. July 1-15, 2026 Payroll">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Date From <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="period_from">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Date To <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="period_to">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Pay Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="period_pay_date">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="submitCreatePeriod()">
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
                                <strong style="font-size:16px;">&#127978; Sari-Sari Store</strong><br>
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
    CREATE PERIOD
====================================================*/
function openCreatePeriodModal(){
    $("#period_name, #period_from, #period_to, #period_pay_date").val('');
    new bootstrap.Modal(document.getElementById('createPeriodModal')).show();
}

function submitCreatePeriod(){
    const name     = $("#period_name").val().trim();
    const from     = $("#period_from").val();
    const to       = $("#period_to").val();
    const pay_date = $("#period_pay_date").val();

    if(!name || !from || !to || !pay_date){
        Swal.fire('Missing Fields', 'Please fill in all fields.', 'warning');
        return;
    }
    if(to < from){
        Swal.fire('Invalid Dates', '"Date To" cannot be before "Date From".', 'warning');
        return;
    }

    $.post('hrms_payroll.php', {
        action: 'create_period',
        period_name: name, date_from: from, date_to: to, pay_date: pay_date
    }, function(response){
        if(response.startsWith('success:')){
            Swal.fire({ icon:'success', title:'Period Created!', showConfirmButton:false, timer:1500 })
            .then(() => { clearBackdrop(); loadPage('hrms_payroll.php'); });
        } else {
            Swal.fire('Error', response, 'error');
        }
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
    const opt    = $("#sel_employee option:selected");
    const salary = parseFloat(opt.data('salary')) || 0;
    const name   = opt.data('name') || '&#8212;';
    $("#inp_basic").val(salary.toFixed(2));
    $("#inp_daily").val((salary / 26).toFixed(2));
    $("#prev_empName").html(name);
    resetPreview();
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

</script>
