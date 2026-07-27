<?php
session_start();
require_once '../../Model/database.php';

if(!isset($_SESSION['emp_id'])){
    exit("Unauthorized");
}

$emp_id = $_SESSION['emp_id'];

// Get employee info
$emp_q = mysqli_query($conn, "
    SELECT e.*, p.position_name, d.department_name
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.position_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE e.employee_id = $emp_id
    LIMIT 1
");
$emp = mysqli_fetch_assoc($emp_q);

// Statistics
$days_worked = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM attendance 
    WHERE employee_id = $emp_id 
    AND MONTH(date) = MONTH(CURDATE()) 
    AND YEAR(date) = YEAR(CURDATE())
    AND status IN ('Present', 'Late', 'Half Day')
"))['total'];

$pending_leaves = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM leave_requests 
    WHERE employee_id = $emp_id 
    AND status = 'Pending'
"))['total'];

$total_leaves_approved = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM leave_requests 
    WHERE employee_id = $emp_id 
    AND status = 'Approved'
"))['total'];

$next_payday = date('M 30, Y'); // Quick placeholder static or dynamically calculated pay_date
$pay_q = mysqli_query($conn, "
    SELECT pp.pay_date 
    FROM payroll_periods pp
    JOIN payroll p ON pp.period_id = p.period_id
    WHERE p.employee_id = $emp_id
    ORDER BY pp.pay_date DESC
    LIMIT 1
");
if ($pay_row = mysqli_fetch_assoc($pay_q)) {
    if ($pay_row['pay_date']) {
        $next_payday = date('M d, Y', strtotime($pay_row['pay_date']));
    }
}
?>
<style>
.welcome-banner {
    background: linear-gradient(135deg, #198754 0%, #0f5132 100%);
    color: white;
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 24px;
    box-shadow: 0 4px 15px rgba(25, 135, 84, 0.15);
}
.portal-card {
    background: white;
    border-radius: 14px;
    padding: 24px;
    box-shadow: 0 2px 10px rgba(0,0,0,.03);
    height: 100%;
}
.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s;
    border: 1px solid #e9ecef;
}
.quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,.05);
}
</style>

<div class="animate__animated animate__fadeIn">

    <!-- WELCOME BANNER -->
    <div class="welcome-banner">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="fw-bold mb-2">Hello, <?= htmlspecialchars($emp['full_name']); ?>!</h2>
                <p class="mb-0 opacity-90">Welcome to your O-Cart! employee self-service portal. Keep track of your work schedule, check your earnings, and request time off easily.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="badge bg-success py-2 px-3 fs-6">
                    <i class="bi bi-shield-check me-1"></i> Status: <?= htmlspecialchars($emp['status']); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- STAT CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card border-start border-success border-4">
                <div>
                    <div class="stat-label">Days Worked This Month</div>
                    <div class="stat-value text-success"><?= $days_worked; ?></div>
                </div>
                <div class="stat-icon bg-success"><i class="bi bi-calendar2-check-fill"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card border-start border-warning border-4">
                <div>
                    <div class="stat-label">Pending Leave Requests</div>
                    <div class="stat-value text-warning"><?= $pending_leaves; ?></div>
                </div>
                <div class="stat-icon bg-warning"><i class="bi bi-hourglass-split"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card border-start border-primary border-4">
                <div>
                    <div class="stat-label">Last Generated Pay Date</div>
                    <div class="stat-value text-primary" style="font-size: 20px; margin-top: 8px;"><?= $next_payday; ?></div>
                </div>
                <div class="stat-icon bg-primary"><i class="bi bi-cash-coin"></i></div>
            </div>
        </div>
    </div>

    <!-- ROW -->
    <div class="row g-4">
        
        <!-- ASSIGNMENT DETAILS -->
        <div class="col-lg-6">
            <div class="portal-card">
                <h5 class="fw-bold text-success mb-3 pb-2 border-bottom"><i class="bi bi-briefcase me-2"></i>Job Information</h5>
                <div class="table-responsive">
                    <table class="table table-borderless align-middle mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted ps-0" style="width: 140px;">Position:</td>
                                <td class="fw-semibold text-dark"><?= htmlspecialchars($emp['position_name'] ?? 'Unassigned'); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Department:</td>
                                <td class="fw-semibold text-dark"><?= htmlspecialchars($emp['department_name'] ?? 'Operations'); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Employment Type:</td>
                                <td class="fw-semibold text-dark"><span class="badge bg-light text-success border border-success"><?= htmlspecialchars($emp['employment_type'] ?? 'Full-time'); ?></span></td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Hired Date:</td>
                                <td class="fw-semibold text-dark"><?= $emp['date_hired'] ? date('F j, Y', strtotime($emp['date_hired'])) : 'Not recorded'; ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Employee Code:</td>
                                <td class="fw-semibold text-dark"><code><?= htmlspecialchars($emp['employee_no']); ?></code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="col-lg-6">
            <div class="portal-card">
                <h5 class="fw-bold text-success mb-3 pb-2 border-bottom"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</h5>
                <div class="d-flex flex-column gap-2">
                    <a href="#" onclick="loadPage('emp_profile.php')" class="quick-action-btn bg-light text-dark">
                        <i class="bi bi-person-circle text-success fs-4"></i>
                        <div>
                            <div class="text-dark">View My Profile</div>
                            <small class="text-muted fw-normal" style="font-size:11px;">Check your government benefit IDs & basic info</small>
                        </div>
                    </a>
                    <a href="#" onclick="loadPage('emp_leaves.php')" class="quick-action-btn bg-light text-dark">
                        <i class="bi bi-calendar-plus text-success fs-4"></i>
                        <div>
                            <div class="text-dark">Request Leave of Absence</div>
                            <small class="text-muted fw-normal" style="font-size:11px;">Submit new Sick, Vacation, or Emergency Leave</small>
                        </div>
                    </a>
                    <a href="#" onclick="loadPage('emp_payslips.php')" class="quick-action-btn bg-light text-dark">
                        <i class="bi bi-receipt-cutoff text-success fs-4"></i>
                        <div>
                            <div class="text-dark">Check My Payslips</div>
                            <small class="text-muted fw-normal" style="font-size:11px;">View and print details of your earnings & deductions</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>

    </div>

</div>
