<?php
require_once '../Model/database.php';
require_once '../Model/logger.php';

if(session_status() === PHP_SESSION_NONE){
    session_start();
}
$admin_id = $_SESSION['user_id'] ?? 1;

// Fetch periods that have at least one payroll computed
$periods_query = mysqli_query($conn, "
    SELECT DISTINCT pp.* 
    FROM payroll_periods pp
    JOIN payroll p ON pp.period_id = p.period_id
    ORDER BY pp.created_at DESC
");

$periods = [];
while($row = mysqli_fetch_assoc($periods_query)) {
    $periods[] = $row;
}

// Check if a specific period is requested or default to the latest one
$selected_period_id = isset($_GET['period_id']) ? (int)$_GET['period_id'] : 0;
if ($selected_period_id === 0 && !empty($periods)) {
    $selected_period_id = (int)$periods[0]['period_id'];
}

// Fetch payroll data for the selected period
$payroll_records = [];
$stats = [
    'total_gross' => 0.00,
    'total_deductions' => 0.00,
    'total_net' => 0.00,
    'count' => 0
];

if ($selected_period_id > 0) {
    $records_query = mysqli_query($conn, "
        SELECT p.*, e.full_name, e.employee_no, e.email,
               pos.position_name, d.department_name,
               pp.period_name, pp.date_from, pp.date_to, pp.pay_date
        FROM payroll p
        JOIN employees e ON p.employee_id = e.employee_id
        LEFT JOIN positions pos ON e.position_id = pos.position_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        JOIN payroll_periods pp ON p.period_id = pp.period_id
        WHERE p.period_id = $selected_period_id
        ORDER BY e.full_name ASC
    ");

    while($row = mysqli_fetch_assoc($records_query)) {
        $payroll_records[] = $row;
        $stats['total_gross'] += (float)$row['gross_pay'];
        $stats['total_deductions'] += (float)$row['total_deductions'];
        $stats['total_net'] += (float)$row['net_pay'];
        $stats['count']++;
    }
}
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
.stat-value { font-size: 22px; font-weight: 800; line-height: 1.2; margin-top: 4px; }

/* Payslip styling */
.payslip-container {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 30px;
    font-family: 'Courier New', Courier, monospace;
}
.payslip-header {
    text-align: center;
    margin-bottom: 20px;
    border-bottom: 2px dashed #000;
    padding-bottom: 15px;
}
.payslip-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 20px;
    font-size: 14px;
}
.payslip-table {
    width: 100%;
    margin-bottom: 20px;
    border-collapse: collapse;
}
.payslip-table th {
    border-bottom: 2px dashed #000;
    border-top: 2px dashed #000;
    padding: 8px 0;
    text-align: left;
    font-weight: bold;
}
.payslip-table td {
    padding: 6px 0;
}
.payslip-total-row {
    border-top: 1px dashed #000;
    font-weight: bold;
}
.payslip-net-box {
    border: 2px solid #000;
    padding: 15px;
    text-align: center;
    font-size: 18px;
    font-weight: bold;
    margin-top: 15px;
}

@media print {
    body * {
        visibility: hidden;
    }
    #printArea, #printArea * {
        visibility: visible;
    }
    #printArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        border: none;
        padding: 0;
    }
}
</style>

<!-- ===== PAGE HEADER ===== -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color:#1a3c5e;">
            <i class="bi bi-file-earmark-pdf me-2" style="color:#2563eb;"></i>Employee Payslips
        </h4>
        <small class="text-muted">Generate, view, and print payslips for employees.</small>
    </div>
    <div style="min-width: 250px;">
        <select class="form-select" id="payslipPeriodFilter" onchange="filterPeriod(this.value)">
            <option value="">-- Select Payroll Period --</option>
            <?php foreach($periods as $p) { ?>
                <option value="<?= $p['period_id']; ?>" <?= $selected_period_id == $p['period_id'] ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($p['period_name']); ?>
                </option>
            <?php } ?>
        </select>
    </div>
</div>

<?php if ($selected_period_id > 0) { ?>
<!-- ===== STAT CARDS ===== -->
<div class="row g-3 mb-4 animate__animated animate__fadeIn">
    <div class="col-md-3">
        <div class="stat-card border-start border-primary border-4">
            <div>
                <div class="stat-label">Total Payslips</div>
                <div class="stat-value"><?= $stats['count']; ?></div>
            </div>
            <div class="stat-icon bg-primary"><i class="bi bi-file-earmark-text-fill"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card border-start border-success border-4">
            <div>
                <div class="stat-label">Total Gross Pay</div>
                <div class="stat-value text-success">&#8369;<?= number_format($stats['total_gross'], 2); ?></div>
            </div>
            <div class="stat-icon bg-success"><i class="bi bi-cash"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card border-start border-danger border-4">
            <div>
                <div class="stat-label">Total Deductions</div>
                <div class="stat-value text-danger">&#8369;<?= number_format($stats['total_deductions'], 2); ?></div>
            </div>
            <div class="stat-icon bg-danger"><i class="bi bi-percent"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card border-start border-info border-4">
            <div>
                <div class="stat-label">Total Net Pay</div>
                <div class="stat-value text-primary">&#8369;<?= number_format($stats['total_net'], 2); ?></div>
            </div>
            <div class="stat-icon bg-info"><i class="bi bi-wallet2"></i></div>
        </div>
    </div>
</div>

<!-- ===== PAYSLIPS TABLE ===== -->
<div class="page-card animate__animated animate__fadeIn">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0" style="color:#1a3c5e;">
            <i class="bi bi-table me-2"></i>Employee Payslip Records
        </h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle w-100" id="payslipTable">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Employee No.</th>
                    <th>Employee Name</th>
                    <th>Gross Pay</th>
                    <th>Deductions</th>
                    <th>Net Pay</th>
                    <th>Status</th>
                    <th style="text-align:center; width: 150px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($payroll_records as $i => $r) { ?>
                <tr>
                    <td><?= $i + 1; ?></td>
                    <td class="fw-semibold text-muted"><?= htmlspecialchars($r['employee_no']); ?></td>
                    <td>
                        <div class="fw-bold"><?= htmlspecialchars($r['full_name']); ?></div>
                        <small class="text-muted"><?= htmlspecialchars($r['position_name'] ?? 'General Staff'); ?> | <?= htmlspecialchars($r['department_name'] ?? 'Operation'); ?></small>
                    </td>
                    <td>&#8369;<?= number_format($r['gross_pay'], 2); ?></td>
                    <td class="text-danger">&#8369;<?= number_format($r['total_deductions'], 2); ?></td>
                    <td class="text-success fw-bold">&#8369;<?= number_format($r['net_pay'], 2); ?></td>
                    <td>
                        <span class="badge bg-<?= $r['status'] === 'Paid' ? 'success' : ($r['status'] === 'Approved' ? 'primary' : 'secondary'); ?>">
                            <?= $r['status']; ?>
                        </span>
                    </td>
                    <td style="text-align:center;">
                        <button class="btn btn-sm btn-outline-primary" onclick='viewPayslip(<?= json_encode($r); ?>)'>
                            <i class="bi bi-eye-fill"></i> View Payslip
                        </button>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<?php } else { ?>
<!-- ===== NO DATA STATE ===== -->
<div class="page-card text-center py-5">
    <i class="bi bi-file-earmark-excel fs-1 text-muted"></i>
    <h5 class="fw-bold mt-3" style="color:#1a3c5e;">No Payroll Periods Available</h5>
    <p class="text-muted">You must compute and save payroll records first in the **Payroll** page before payslips can be viewed.</p>
</div>
<?php } ?>

<!--=========================================================
    VIEW PAYSLIP MODAL
==========================================================-->
<div class="modal fade" id="payslipModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header modal-header-primary">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-file-earmark-text me-2"></i>Employee Payslip Preview
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- PRINT CONTAINER -->
                <div id="printArea" class="payslip-container">
                    <div class="payslip-header">
                        <h4 class="fw-bold m-0">O-CART!</h4>
                        <small class="text-muted">HR & PAYROLL SYSTEM</small>
                    </div>

                    <div class="payslip-details-grid">
                        <div>
                            <strong>Employee No:</strong> <span id="ps_empNo"></span><br>
                            <strong>Employee Name:</strong> <span id="ps_empName"></span><br>
                            <strong>Position:</strong> <span id="ps_position"></span><br>
                            <strong>Department:</strong> <span id="ps_department"></span>
                        </div>
                        <div style="text-align: right;">
                            <strong>Payroll Period:</strong> <span id="ps_periodName"></span><br>
                            <strong>Date Range:</strong> <span id="ps_dateRange"></span><br>
                            <strong>Pay Date:</strong> <span id="ps_payDate"></span><br>
                            <strong>Status:</strong> <span id="ps_status" class="fw-bold text-success"></span>
                        </div>
                    </div>

                    <table class="payslip-table">
                        <thead>
                            <tr>
                                <th>EARNINGS / DEDUCTIONS</th>
                                <th style="text-align: right;">AMOUNT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Earnings -->
                            <tr>
                                <td>Basic Pay (<span id="ps_daysWorked"></span> days)</td>
                                <td style="text-align: right;" id="ps_basicPay"></td>
                            </tr>
                            <tr>
                                <td>Overtime Pay</td>
                                <td style="text-align: right;" id="ps_otPay"></td>
                            </tr>
                            <tr class="payslip-total-row">
                                <td>GROSS PAY</td>
                                <td style="text-align: right;" id="ps_grossPay"></td>
                            </tr>

                            <!-- Deductions -->
                            <tr>
                                <td>SSS Contribution</td>
                                <td style="text-align: right; color:#dc2626;" id="ps_sss"></td>
                            </tr>
                            <tr>
                                <td>PhilHealth Contribution</td>
                                <td style="text-align: right; color:#dc2626;" id="ps_philhealth"></td>
                            </tr>
                            <tr>
                                <td>Pag-IBIG Contribution</td>
                                <td style="text-align: right; color:#dc2626;" id="ps_pagibig"></td>
                            </tr>
                            <tr>
                                <td>Withholding Tax</td>
                                <td style="text-align: right; color:#dc2626;" id="ps_wtax"></td>
                            </tr>
                            <tr>
                                <td>Other Deductions (<span id="ps_dedNotes" class="text-muted" style="font-size:11px;"></span>)</td>
                                <td style="text-align: right; color:#dc2626;" id="ps_otherDed"></td>
                            </tr>
                            <tr class="payslip-total-row">
                                <td>TOTAL DEDUCTIONS</td>
                                <td style="text-align: right; color:#dc2626;" id="ps_totalDeductions"></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="payslip-net-box">
                        NET PAY: <span id="ps_netPay"></span>
                    </div>
                </div>
            </div>
           <div class="modal-footer border-0">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-outline-primary" onclick="downloadPayslip()">
                    <i class="bi bi-download me-1"></i> Download PDF
                </button>
                <button class="btn btn-success" onclick="printPayslip()">
                    <i class="bi bi-printer-fill me-1"></i> Print Payslip
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    if($.fn.DataTable){
        $('#payslipTable').DataTable({
            destroy: true,
            pageLength: 10,
            language: {
                emptyTable: 'No payslip records found for this period.',
                search: 'Search:',
                lengthMenu: 'Show _MENU_ records'
            }
        });
    }
});

function filterPeriod(periodId) {
    if (periodId !== "") {
        loadPage('hrms_payslip.php?period_id=' + periodId);
    }
}

function viewPayslip(r) {
    document.getElementById('ps_empNo').innerText = r.employee_no;
    document.getElementById('ps_empName').innerText = r.full_name;
    document.getElementById('ps_position').innerText = r.position_name || 'General Staff';
    document.getElementById('ps_department').innerText = r.department_name || 'Operation';
    document.getElementById('ps_periodName').innerText = r.period_name;
    
    // Date formats
    const dateFrom = new Date(r.date_from).toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
    const dateTo = new Date(r.date_to).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'});
    document.getElementById('ps_dateRange').innerText = `${dateFrom} - ${dateTo}`;
    
    document.getElementById('ps_payDate').innerText = new Date(r.pay_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'});
    document.getElementById('ps_status').innerText = r.status;

    // Numbers
    const hoursPerDay = r.hours_per_day ? parseFloat(r.hours_per_day) : 8;
    document.getElementById('ps_daysWorked').innerText = parseFloat(r.days_worked) + ' day(s) × ' + hoursPerDay + ' hr(s)/day';
    document.getElementById('ps_basicPay').innerText = '₱' + parseFloat(r.gross_pay - r.overtime_pay).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('ps_otPay').innerText = '₱' + parseFloat(r.overtime_pay).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('ps_grossPay').innerText = '₱' + parseFloat(r.gross_pay).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    document.getElementById('ps_sss').innerText = '₱' + parseFloat(r.sss).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('ps_philhealth').innerText = '₱' + parseFloat(r.philhealth).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('ps_pagibig').innerText = '₱' + parseFloat(r.pagibig).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('ps_wtax').innerText = '₱' + parseFloat(r.withholding_tax).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    document.getElementById('ps_dedNotes').innerText = r.deduction_notes || 'None';
    document.getElementById('ps_otherDed').innerText = '₱' + parseFloat(r.other_deductions).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('ps_totalDeductions').innerText = '₱' + parseFloat(r.total_deductions).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    document.getElementById('ps_netPay').innerText = '₱' + parseFloat(r.net_pay).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    new bootstrap.Modal(document.getElementById('payslipModal')).show();
}

function printPayslip() {
    window.print();
}

function downloadPayslip() {
    const empNo  = document.getElementById('ps_empNo').innerText || 'employee';
    const period = document.getElementById('ps_periodName').innerText || 'payslip';
    const filename = ('Payslip_' + empNo + '_' + period).replace(/\s+/g, '_') + '.pdf';

    function generate() {
        Swal.fire({
            title: 'Generating PDF...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
        html2pdf().set({
            margin: 10,
            filename: filename,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'mm', format: 'a5', orientation: 'portrait' }
        }).from(document.getElementById('printArea')).save().then(() => Swal.close());
    }

    if (typeof html2pdf !== 'undefined') {
        generate();
    } else {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
        script.onload = generate;
        document.head.appendChild(script);
    }
}
</script>
