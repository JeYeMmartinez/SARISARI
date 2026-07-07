<?php
require_once '../Model/database.php';

$admin_id = $_SESSION['user_id'];

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
    $employee_id = (int)$_POST['employee_id'];
    $period_id   = (int)$_POST['period_id'];
    $days_worked = (float)$_POST['days_worked'];
    $overtime    = (float)($_POST['overtime_hours'] ?? 0);

    $emp = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM employees WHERE employee_id = $employee_id"
    ));

    if(!$emp){ echo json_encode(['error'=>'Employee not found']); exit(); }

    $monthly     = (float)$emp['basic_salary'];
    $daily_rate  = $monthly / 26; // 26 working days
    $hourly_rate = $daily_rate / 8;

    $basic_pay    = $daily_rate * $days_worked;
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
    ]);
    exit();
}

// SAVE PAYROLL RECORD
if(isset($_POST['action']) && $_POST['action'] == 'save_payroll'){
    $period_id        = (int)$_POST['period_id'];
    $employee_id      = (int)$_POST['employee_id'];
    $basic_salary     = (float)$_POST['basic_salary'];
    $days_worked      = (float)$_POST['days_worked'];
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
                basic_salary=$basic_salary, days_worked=$days_worked,
                overtime_pay=$overtime_pay, gross_pay=$gross_pay,
                sss=$sss, philhealth=$philhealth, pagibig=$pagibig,
                withholding_tax=$wtax, other_deductions=$other_ded,
                deduction_notes='$ded_notes', total_deductions=$total_deductions,
                net_pay=$net_pay
            WHERE payroll_id=$pid
        ");
    } else {
        $q = mysqli_query($conn,"
            INSERT INTO payroll (period_id, employee_id, basic_salary, days_worked,
                overtime_pay, gross_pay, sss, philhealth, pagibig, withholding_tax,
                other_deductions, deduction_notes, total_deductions, net_pay)
            VALUES ($period_id, $employee_id, $basic_salary, $days_worked,
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

/*=========================================================
    FETCH DATA
==========================================================*/
$periods = mysqli_query($conn,"
    SELECT pp.*, u.full_name AS created_by_name,
           COUNT(p.payroll_id) AS employee_count,
           IFNULL(SUM(p.net_pay),0) AS total_net
    FROM payroll_periods pp
    LEFT JOIN users u ON pp.created_by = u.user_id
    LEFT JOIN payroll p ON pp.period_id = p.period_id
    GROUP BY pp.period_id
    ORDER BY pp.created_at DESC
");

$employees = mysqli_query($conn,"
    SELECT e.*, p.position_name, d.department_name
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.position_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE e.status = 'Active'
    ORDER BY e.full_name ASC
");

$employeeList = [];
while($e = mysqli_fetch_assoc($employees)){
    $employeeList[] = $e;
}
?>

<style>
.page-card { background:white; border-radius:14px; padding:22px 24px;
             box-shadow:0 2px 10px rgba(0,0,0,.06); margin-bottom:22px; }
.deduction-row { display:flex; justify-content:space-between; align-items:center;
                 padding:8px 0; border-bottom:1px solid #f0f0f0; font-size:14px; }
.deduction-row:last-child { border-bottom:none; }
.deduction-label { color:#6c757d; }
.deduction-value { font-weight:600; }
.net-pay-box { background:linear-gradient(135deg,#1a3c5e,#2563eb);
               border-radius:12px; padding:20px; color:white; text-align:center; }
.period-status { font-size:11px; }
</style>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Payroll Management</h4>
        <small class="text-muted">Philippine Government Deductions (SSS, PhilHealth, Pag-IBIG, TRAIN Law)</small>
    </div>
    <button class="btn btn-primary" onclick="openCreatePeriodModal()">
        <i class="bi bi-plus-lg me-1"></i> New Payroll Period
    </button>
</div>

<!-- PAYROLL PERIODS TABLE -->
<div class="page-card">
    <h5 class="mb-3">Payroll Periods</h5>
    <table class="table table-bordered table-hover datatable">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Period</th>
                <th>Date Range</th>
                <th>Pay Date</th>
                <th>Employees</th>
                <th>Total Net Pay</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php $i=1; while($period = mysqli_fetch_assoc($periods)){ ?>
            <tr>
                <td><?= $i++; ?></td>
                <td><strong><?= htmlspecialchars($period['period_name']); ?></strong></td>
                <td>
                    <?= date("M d", strtotime($period['date_from'])); ?> –
                    <?= date("M d, Y", strtotime($period['date_to'])); ?>
                </td>
                <td><?= date("M d, Y", strtotime($period['pay_date'])); ?></td>
                <td><?= $period['employee_count']; ?></td>
                <td><strong class="text-success">₱<?= number_format($period['total_net'],2); ?></strong></td>
                <td>
                    <?php
                    $badges = [
                        'Draft'       => 'bg-secondary',
                        'For Approval'=> 'bg-warning text-dark',
                        'Approved'    => 'bg-primary',
                        'Paid'        => 'bg-success'
                    ];
                    $badge = $badges[$period['status']] ?? 'bg-secondary';
                    echo '<span class="badge '.$badge.' period-status">'.$period['status'].'</span>';
                    ?>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1"
                            onclick="openPayrollModal(<?= $period['period_id']; ?>, '<?= addslashes($period['period_name']); ?>')">
                        <i class="bi bi-calculator"></i> Compute
                    </button>
                    <?php if($period['status'] == 'Draft' && $period['employee_count'] > 0){ ?>
                    <button class="btn btn-sm btn-warning me-1"
                            onclick="approvePeriod(<?= $period['period_id']; ?>)">
                        <i class="bi bi-check-lg"></i> Approve
                    </button>
                    <?php } ?>
                    <?php if($period['status'] == 'Approved'){ ?>
                    <button class="btn btn-sm btn-success"
                            onclick="markPaid(<?= $period['period_id']; ?>)">
                        <i class="bi bi-cash"></i> Mark Paid
                    </button>
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<!--=========================================================
    CREATE PERIOD MODAL
==========================================================-->
<div class="modal fade" id="createPeriodModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a3c5e;color:white;">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>New Payroll Period
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Period Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="period_name"
                               placeholder="e.g. June 1-15, 2026 Payroll">
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
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="submitCreatePeriod()">
                    <i class="bi bi-check-lg me-1"></i>Create Period
                </button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    COMPUTE PAYROLL MODAL
==========================================================-->
<div class="modal fade" id="payrollModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a3c5e;color:white;">
                <h5 class="modal-title">
                    <i class="bi bi-calculator me-2"></i>
                    Compute Payroll — <span id="modalPeriodName"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="current_period_id">

                <div class="row g-3">

                    <!-- LEFT: EMPLOYEE SELECTOR + INPUTS -->
                    <div class="col-lg-5">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Select Employee</label>
                            <select class="form-select" id="sel_employee"
                                    onchange="onEmployeeChange()">
                                <option value="">-- Select Employee --</option>
                                <?php foreach($employeeList as $e){ ?>
                                <option value="<?= $e['employee_id']; ?>"
                                        data-salary="<?= $e['basic_salary']; ?>"
                                        data-name="<?= htmlspecialchars($e['full_name']); ?>">
                                    <?= htmlspecialchars($e['full_name']); ?> —
                                    ₱<?= number_format($e['basic_salary'],2); ?>/mo
                                </option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Basic Salary</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" class="form-control" id="inp_basic"
                                           step="0.01" min="0" readonly>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Daily Rate</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" class="form-control" id="inp_daily"
                                           step="0.01" min="0" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Days Worked</label>
                                <input type="number" class="form-control" id="inp_days"
                                       step="0.5" min="0" max="26" placeholder="e.g. 13"
                                       oninput="computePayroll()">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Overtime Hours</label>
                                <input type="number" class="form-control" id="inp_overtime"
                                       step="0.5" min="0" placeholder="e.g. 2"
                                       oninput="computePayroll()">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Other Deductions (₱)</label>
                            <input type="number" class="form-control" id="inp_other_ded"
                                   step="0.01" min="0" placeholder="e.g. 500 for cash advance"
                                   oninput="computePayroll()">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Deduction Notes</label>
                            <textarea class="form-control" id="inp_ded_notes" rows="2"
                                      placeholder="e.g. Cash advance deduction..."></textarea>
                        </div>

                        <button class="btn btn-primary w-100" onclick="computePayroll()">
                            <i class="bi bi-calculator me-1"></i>Compute
                        </button>
                    </div>

                    <!-- RIGHT: PAYSLIP PREVIEW -->
                    <div class="col-lg-7">
                        <div style="background:#f8f9fa;border-radius:12px;padding:20px;"
                             id="payslipPreview">

                            <div class="text-center mb-3">
                                <strong style="font-size:16px;">🏪 Sari-Sari Store</strong><br>
                                <small class="text-muted">Payslip Preview</small><br>
                                <small id="prev_empName" class="text-muted">—</small>
                            </div>

                            <hr>

                            <!-- EARNINGS -->
                            <div class="fw-bold text-success mb-2" style="font-size:13px;">
                                EARNINGS
                            </div>
                            <div class="deduction-row">
                                <span class="deduction-label">Basic Pay</span>
                                <span class="deduction-value" id="prev_basic">₱0.00</span>
                            </div>
                            <div class="deduction-row">
                                <span class="deduction-label">Overtime Pay (125%)</span>
                                <span class="deduction-value" id="prev_ot">₱0.00</span>
                            </div>
                            <div class="deduction-row" style="border-top:2px solid #dee2e6;padding-top:10px;">
                                <span class="fw-bold">Gross Pay</span>
                                <span class="fw-bold text-success" id="prev_gross">₱0.00</span>
                            </div>

                            <hr>

                            <!-- GOVERNMENT DEDUCTIONS -->
                            <div class="fw-bold text-danger mb-2" style="font-size:13px;">
                                GOVERNMENT DEDUCTIONS
                            </div>
                            <div class="deduction-row">
                                <span class="deduction-label">SSS</span>
                                <span class="deduction-value text-danger" id="prev_sss">₱0.00</span>
                            </div>
                            <div class="deduction-row">
                                <span class="deduction-label">PhilHealth (2.5%)</span>
                                <span class="deduction-value text-danger" id="prev_ph">₱0.00</span>
                            </div>
                            <div class="deduction-row">
                                <span class="deduction-label">Pag-IBIG</span>
                                <span class="deduction-value text-danger" id="prev_pi">₱0.00</span>
                            </div>
                            <div class="deduction-row">
                                <span class="deduction-label">Withholding Tax (TRAIN)</span>
                                <span class="deduction-value text-danger" id="prev_wtax">₱0.00</span>
                            </div>
                            <div class="deduction-row">
                                <span class="deduction-label">Other Deductions</span>
                                <span class="deduction-value text-danger" id="prev_other">₱0.00</span>
                            </div>
                            <div class="deduction-row" style="border-top:2px solid #dee2e6;padding-top:10px;">
                                <span class="fw-bold">Total Deductions</span>
                                <span class="fw-bold text-danger" id="prev_total_ded">₱0.00</span>
                            </div>

                            <hr>

                            <!-- NET PAY -->
                            <div class="net-pay-box mt-3">
                                <div style="font-size:13px;opacity:.8;margin-bottom:4px;">NET PAY</div>
                                <div style="font-size:32px;font-weight:900;" id="prev_net">₱0.00</div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>

            <div class="modal-footer">
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

function fmt(n){ return '₱' + parseFloat(n || 0).toLocaleString('en-PH', {minimumFractionDigits:2}); }

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
        Swal.fire('Missing Fields','Please fill in all fields.','warning');
        return;
    }

    $.post('hrms_payroll.php', {
        action: 'create_period',
        period_name: name,
        date_from: from,
        date_to: to,
        pay_date: pay_date
    }, function(response){
        if(response.startsWith('success:')){
            Swal.fire({ icon:'success', title:'Period Created!',
                showConfirmButton:false, timer:1500 })
            .then(() => { clearBackdrop(); loadPage('hrms_payroll.php'); });
        } else {
            Swal.fire('Error', response, 'error');
        }
    });
}

function openPayrollModal(periodId, periodName){
    $("#current_period_id").val(periodId);
    $("#modalPeriodName").text(periodName);
    $("#sel_employee").val('');
    $("#inp_basic, #inp_daily, #inp_days, #inp_overtime, #inp_other_ded, #inp_ded_notes").val('');
    resetPreview();
    new bootstrap.Modal(document.getElementById('payrollModal')).show();
}

function onEmployeeChange(){
    const opt = $("#sel_employee option:selected");
    const salary = parseFloat(opt.data('salary')) || 0;
    const name   = opt.data('name') || '—';
    $("#inp_basic").val(salary.toFixed(2));
    $("#inp_daily").val((salary / 26).toFixed(2));
    $("#prev_empName").text(name);
    resetPreview();
}

function resetPreview(){
    ["prev_basic","prev_ot","prev_gross","prev_sss","prev_ph",
     "prev_pi","prev_wtax","prev_other","prev_total_ded","prev_net"]
    .forEach(id => $("#"+id).text('₱0.00'));
}

function computePayroll(){
    const emp_id   = $("#sel_employee").val();
    const period   = $("#current_period_id").val();
    const days     = parseFloat($("#inp_days").val()) || 0;
    const overtime = parseFloat($("#inp_overtime").val()) || 0;

    if(!emp_id || days <= 0) return;

    $.post('hrms_payroll.php', {
        action:         'compute',
        employee_id:    emp_id,
        period_id:      period,
        days_worked:    days,
        overtime_hours: overtime
    }, function(response){
        try {
            const d = JSON.parse(response);
            if(d.error){ Swal.fire('Error', d.error, 'error'); return; }

            const other = parseFloat($("#inp_other_ded").val()) || 0;
            const total_ded = d.sss + d.philhealth + d.pagibig + d.withholding_tax + other;
            const net = d.gross_pay - total_ded;

            currentComputed = {
                ...d,
                other_deductions: other,
                total_deductions: total_ded,
                net_pay: net
            };

            $("#inp_daily").val(d.daily_rate);
            $("#prev_basic").text(fmt(d.basic_pay));
            $("#prev_ot").text(fmt(d.overtime_pay));
            $("#prev_gross").text(fmt(d.gross_pay));
            $("#prev_sss").text(fmt(d.sss));
            $("#prev_ph").text(fmt(d.philhealth));
            $("#prev_pi").text(fmt(d.pagibig));
            $("#prev_wtax").text(fmt(d.withholding_tax));
            $("#prev_other").text(fmt(other));
            $("#prev_total_ded").text(fmt(total_ded));
            $("#prev_net").text(fmt(net));

        } catch(e){ console.error('Parse error:', response); }
    });
}

function savePayroll(){
    if(!currentComputed.gross_pay){
        Swal.fire('Not Computed','Please compute payroll first.','warning');
        return;
    }

    const emp_id = $("#sel_employee").val();
    if(!emp_id){
        Swal.fire('No Employee','Please select an employee.','warning');
        return;
    }

    $.post('hrms_payroll.php', {
        action:           'save_payroll',
        period_id:        $("#current_period_id").val(),
        employee_id:      emp_id,
        basic_salary:     $("#inp_basic").val(),
        days_worked:      $("#inp_days").val(),
        overtime_pay:     currentComputed.overtime_pay,
        gross_pay:        currentComputed.gross_pay,
        sss:              currentComputed.sss,
        philhealth:       currentComputed.philhealth,
        pagibig:          currentComputed.pagibig,
        withholding_tax:  currentComputed.withholding_tax,
        other_deductions: currentComputed.other_deductions,
        deduction_notes:  $("#inp_ded_notes").val(),
    }, function(response){
        if(response == 'success'){
            Swal.fire({ icon:'success', title:'Payroll Saved!',
                showConfirmButton:false, timer:1500 });
            loadPage('hrms_payroll.php');
        } else {
            Swal.fire('Error', response, 'error');
        }
    });
}

function approvePeriod(periodId){
    Swal.fire({
        title: 'Approve this payroll period?',
        text: 'Once approved, it will be ready for payment processing.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        confirmButtonText: 'Yes, Approve'
    }).then(result => {
        if(!result.isConfirmed) return;
        $.post('hrms_payroll.php', { action:'approve_period', period_id:periodId },
        function(response){
            if(response == 'success'){
                Swal.fire({ icon:'success', title:'Period Approved!',
                    showConfirmButton:false, timer:1500 })
                .then(() => loadPage('hrms_payroll.php'));
            } else {
                Swal.fire('Error', response, 'error');
            }
        });
    });
}

function markPaid(periodId){
    Swal.fire({
        title: 'Mark payroll as PAID?',
        text: 'This confirms that all employees have been paid.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Yes, Mark as Paid'
    }).then(result => {
        if(!result.isConfirmed) return;
        $.post('hrms_payroll.php', { action:'mark_paid', period_id:periodId },
        function(response){
            if(response == 'success'){
                Swal.fire({ icon:'success', title:'Payroll Marked as Paid!',
                    showConfirmButton:false, timer:1500 })
                .then(() => loadPage('hrms_payroll.php'));
            }
        });
    });
}

</script>