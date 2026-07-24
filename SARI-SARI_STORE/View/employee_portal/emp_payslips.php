<?php
session_start();
require_once '../../Model/database.php';

if(!isset($_SESSION['emp_id'])){
    exit("Unauthorized");
}

$emp_id = $_SESSION['emp_id'];

// Get all computed payroll records for the logged-in employee
$query = mysqli_query($conn, "
    SELECT p.*, pp.period_name, pp.date_from, pp.date_to, pp.pay_date,
           e.full_name, e.employee_no, pos.position_name, d.department_name
    FROM payroll p
    JOIN payroll_periods pp ON p.period_id = pp.period_id
    JOIN employees e ON p.employee_id = e.employee_id
    LEFT JOIN positions pos ON e.position_id = pos.position_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE p.employee_id = $emp_id
    AND p.status = 'Paid'
    ORDER BY pp.pay_date DESC
");

$payroll_records = [];
while ($row = mysqli_fetch_assoc($query)) {
    $payroll_records[] = $row;
}
?>
<div class="animate__animated animate__fadeIn">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color: #0f5132;">My Payslips</h4>
            <small class="text-muted">View and download your official salary payslip details</small>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="page-card">
        <?php if (empty($payroll_records)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-file-earmark-lock" style="font-size: 48px;"></i>
                <p class="mt-3 mb-0">No released payslips found. Official payslips will appear here once released by HR.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle datatable w-100" id="empPayslipsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Pay Period</th>
                            <th>Release Date</th>
                            <th>Basic Salary</th>
                            <th>Gross Pay</th>
                            <th>Deductions</th>
                            <th>Net Pay</th>
                            <th style="width: 100px; text-align: center;">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($payroll_records as $pay): ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($pay['period_name']); ?></td>
                        <td><?= date('M d, Y', strtotime($pay['pay_date'])); ?></td>
                        <td>₱<?= number_format($pay['basic_salary'], 2); ?></td>
                        <td class="fw-semibold text-success">₱<?= number_format($pay['gross_pay'], 2); ?></td>
                        <td class="text-danger">₱<?= number_format($pay['total_deductions'], 2); ?></td>
                        <td class="fw-bold text-success">₱<?= number_format($pay['net_pay'], 2); ?></td>
                        <td>
                            <div class="d-flex justify-content-center">
                                <button class="btn btn-sm btn-outline-success" 
                                        onclick='viewPayslipModal(<?= json_encode($pay); ?>)'>
                                    <i class="bi bi-eye-fill me-1"></i> View
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- PAYSLIP MODAL -->
<div class="modal fade" id="payslipModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-text-fill me-2"></i>Employee Payslip</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="payslipPrintArea">
                <!-- PRINT WRAPPER -->
                <div class="border p-4 rounded-4" style="background:#fff;">
                    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                        <div>
                            <h4 class="fw-bold text-success mb-1">O-CART!</h4>
                            <small class="text-muted text-uppercase" style="letter-spacing:1px; font-size:10px;">Sari-Sari Store Management System</small>
                        </div>
                        <div class="text-end">
                            <h6 class="fw-bold mb-1">PAYSLIP RECEIPT</h6>
                            <small class="text-muted">Period: <span id="p_period_name"></span></small>
                        </div>
                    </div>

                    <!-- EMPLOYEE INFO -->
                    <div class="row mb-4" style="font-size:13px;">
                        <div class="col-6">
                            <div class="text-muted">Employee ID:</div>
                            <div class="fw-semibold text-dark mb-2" id="p_emp_no"></div>
                            <div class="text-muted">Employee Name:</div>
                            <div class="fw-semibold text-dark" id="p_emp_name"></div>
                        </div>
                        <div class="col-6 text-end">
                            <div class="text-muted">Position:</div>
                            <div class="fw-semibold text-dark mb-2" id="p_position"></div>
                            <div class="text-muted">Payment Date:</div>
                            <div class="fw-semibold text-dark" id="p_pay_date"></div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <!-- EARNINGS -->
                        <div class="col-md-6 border-end">
                            <h6 class="fw-bold text-success border-bottom pb-2 mb-3">EARNINGS</h6>
                            <table class="table table-borderless table-sm mb-0" style="font-size:13.5px;">
                                <tbody>
                                    <tr>
                                        <td>Basic Monthly Rate</td>
                                        <td class="text-end fw-semibold" id="p_basic_salary"></td>
                                    </tr>
                                    <tr>
                                        <td>Days Worked (<span id="p_days_worked"></span> day(s))</td>
                                        <td class="text-end fw-semibold" id="p_computed_basic"></td>
                                    </tr>
                                    <tr>
                                        <td>Overtime Earnings</td>
                                        <td class="text-end fw-semibold" id="p_overtime_pay"></td>
                                    </tr>
                                    <tr class="border-top">
                                        <td class="fw-bold pt-2">GROSS PAY</td>
                                        <td class="text-end fw-bold text-success pt-2" id="p_gross_pay"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- DEDUCTIONS -->
                        <div class="col-md-6">
                            <h6 class="fw-bold text-danger border-bottom pb-2 mb-3">DEDUCTIONS</h6>
                            <table class="table table-borderless table-sm mb-0" style="font-size:13.5px;">
                                <tbody>
                                    <tr>
                                        <td>SSS Contribution</td>
                                        <td class="text-end fw-semibold" id="p_sss"></td>
                                    </tr>
                                    <tr>
                                        <td>PhilHealth Contribution</td>
                                        <td class="text-end fw-semibold" id="p_philhealth"></td>
                                    </tr>
                                    <tr>
                                        <td>Pag-IBIG Contribution</td>
                                        <td class="text-end fw-semibold" id="p_pagibig"></td>
                                    </tr>
                                    <tr>
                                        <td>Withholding Tax</td>
                                        <td class="text-end fw-semibold" id="p_tax"></td>
                                    </tr>
                                    <tr>
                                        <td>Other Deductions</td>
                                        <td class="text-end fw-semibold" id="p_other_ded"></td>
                                    </tr>
                                    <tr class="border-top">
                                        <td class="fw-bold pt-2">TOTAL DEDUCTIONS</td>
                                        <td class="text-end fw-bold text-danger pt-2" id="p_total_ded"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- NET PAY BANNER -->
                    <div class="mt-4 p-3 bg-success bg-opacity-10 text-success rounded-3 d-flex justify-content-between align-items-center">
                        <span class="h6 fw-bold mb-0">NET TAKE HOME PAY</span>
                        <span class="h4 fw-bold mb-0" id="p_net_pay"></span>
                    </div>

                    <!-- DEDUCTION NOTES -->
                    <div class="mt-3" id="p_notes_container" style="display:none;">
                        <small class="text-muted d-block fw-bold mb-1">Deduction Notes:</small>
                        <div class="p-2 bg-light rounded text-muted" style="font-size:12px;" id="p_deduction_notes"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-primary btn-sm" onclick="printPayslip()"><i class="bi bi-printer me-1"></i> Print Payslip</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    if($.fn.DataTable){
        $('#empPayslipsTable').DataTable({
            responsive: true,
            pageLength: 10,
            lengthChange: false,
            ordering: true,
            searching: false,
            order: [[1, 'desc']],
            destroy: true
        });
    }
});

let currentPayslip = null;

function viewPayslipModal(pay) {
    currentPayslip = pay;
    
    // Set text values
    $('#p_period_name').text(pay.period_name);
    $('#p_emp_no').text(pay.employee_no);
    $('#p_emp_name').text(pay.full_name);
    $('#p_position').text(pay.position_name || 'Associate');
    $('#p_pay_date').text(new Date(pay.pay_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }));
    
    // Money Formatting Helper
    const fmt = v => '₱' + parseFloat(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    
    // Calculate values
    const basic = parseFloat(pay.basic_salary);
    const dailyRate = basic / 26; // Assuming standard 26 workdays per month
    const days = parseFloat(pay.days_worked);
    const computedBasic = dailyRate * days;

    // Set earnings
    $('#p_basic_salary').text(fmt(basic));
    $('#p_days_worked').text(days.toFixed(1));
    $('#p_computed_basic').text(fmt(computedBasic));
    $('#p_overtime_pay').text(fmt(pay.overtime_pay));
    $('#p_gross_pay').text(fmt(pay.gross_pay));
    
    // Set deductions
    $('#p_sss').text(fmt(pay.sss));
    $('#p_philhealth').text(fmt(pay.philhealth));
    $('#p_pagibig').text(fmt(pay.pagibig));
    $('#p_tax').text(fmt(pay.withholding_tax));
    $('#p_other_ded').text(fmt(pay.other_deductions));
    $('#p_total_ded').text(fmt(pay.total_deductions));
    
    // Set net pay
    $('#p_net_pay').text(fmt(pay.net_pay));
    
    // Deduction notes
    if (pay.deduction_notes && pay.deduction_notes.trim() !== '') {
        $('#p_deduction_notes').text(pay.deduction_notes);
        $('#p_notes_container').show();
    } else {
        $('#p_notes_container').hide();
    }
    
    new bootstrap.Modal(document.getElementById('payslipModal')).show();
}

function printPayslip() {
    const printContent = document.getElementById('payslipPrintArea').innerHTML;
    const printWindow = window.open('', '_blank', 'width=800,height=700');
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Employee Payslip Receipt</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
            <style>
                body {
                    margin: 0;
                    padding: 40px;
                    background: #ffffff;
                    font-family: 'Segoe UI', system-ui, sans-serif;
                }
            </style>
        </head>
        <body onload="window.print(); window.close();">
            <div class="container">
                ${printContent}
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}
</script>
