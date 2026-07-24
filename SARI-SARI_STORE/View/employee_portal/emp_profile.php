<?php
session_start();
require_once '../../Model/database.php';

if(!isset($_SESSION['emp_id'])){
    exit("Unauthorized");
}

$emp_id = $_SESSION['emp_id'];

// Fetch employee details
$emp_q = mysqli_query($conn, "
    SELECT e.*, p.position_name, d.department_name
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.position_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE e.employee_id = $emp_id
    LIMIT 1
");
$emp = mysqli_fetch_assoc($emp_q);

function getInitials($name) {
    $words = explode(" ", preg_replace('/[^a-zA-Z0-9\s]/', '', $name));
    $initials = "";
    foreach ($words as $w) {
        $initials .= strtoupper(substr($w, 0, 1));
        if(strlen($initials) >= 2) break;
    }
    return $initials ?: "?";
}

$initials = getInitials($emp['full_name']);
$hasPhoto = !empty($emp['photo']) && file_exists(__DIR__ . '/../uploads/employees/' . $emp['photo']);
?>
<style>
.profile-avatar-large {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: #198754;
    color: white;
    font-size: 38px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 4px solid #e8f5e9;
    box-shadow: 0 4px 10px rgba(0,0,0,.08);
}
.profile-img-large {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #e8f5e9;
    box-shadow: 0 4px 10px rgba(0,0,0,.08);
}
.section-title {
    font-size: 15px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #198754;
    border-bottom: 2px solid #e8f5e9;
    padding-bottom: 8px;
    margin-bottom: 16px;
}
.info-label {
    font-size: 12px;
    color: #6c757d;
    font-weight: 600;
    text-transform: uppercase;
}
.info-value {
    font-size: 14px;
    color: #212529;
    font-weight: 600;
    margin-bottom: 12px;
}
</style>

<div class="animate__animated animate__fadeIn">
    
    <div class="row g-4">
        
        <!-- LEFT COLUMN: Profile Header Card -->
        <div class="col-lg-4">
            <div class="page-card text-center py-4">
                <div class="d-flex justify-content-center mb-3">
                    <?php if($hasPhoto): ?>
                        <img src="../uploads/employees/<?= htmlspecialchars($emp['photo']); ?>" class="profile-img-large" alt="Profile Image">
                    <?php else: ?>
                        <div class="profile-avatar-large"><?= $initials; ?></div>
                    <?php endif; ?>
                </div>
                <h4 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($emp['full_name']); ?></h4>
                <div class="text-success fw-semibold mb-2"><?= htmlspecialchars($emp['position_name'] ?? 'Associate'); ?></div>
                <div class="text-muted small mb-3"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($emp['email'] ?? 'No email'); ?></div>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">
                    <i class="bi bi-check-circle-fill me-1"></i>Active Associate
                </span>
            </div>
            
            <div class="page-card mt-3">
                <h6 class="section-title"><i class="bi bi-shield-check me-2"></i>Employment Details</h6>
                
                <div class="info-label">Employee Number</div>
                <div class="info-value"><code><?= htmlspecialchars($emp['employee_no']); ?></code></div>
                
                <div class="info-label">Department</div>
                <div class="info-value"><?= htmlspecialchars($emp['department_name'] ?? 'Operations'); ?></div>
                
                <div class="info-label">Employment Type</div>
                <div class="info-value"><?= htmlspecialchars($emp['employment_type'] ?? 'Full-time'); ?></div>
                
                <div class="info-label">Date Hired</div>
                <div class="info-value"><?= $emp['date_hired'] ? date('F j, Y', strtotime($emp['date_hired'])) : 'Not recorded'; ?></div>
                
                <div class="info-label">Basic Salary</div>
                <div class="info-value">₱<?= number_format($emp['basic_salary'], 2); ?>/month</div>
            </div>
        </div>
        
        <!-- RIGHT COLUMN: Personal Info & Govt IDs -->
        <div class="col-lg-8">
            <div class="page-card">
                <h6 class="section-title"><i class="bi bi-person me-2"></i>Personal Information</h6>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-label">Birthdate</div>
                        <div class="info-value"><?= $emp['birthdate'] ? date('F j, Y', strtotime($emp['birthdate'])) : '—'; ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-label">Gender</div>
                        <div class="info-value"><?= htmlspecialchars($emp['gender'] ?? '—'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-label">Civil Status</div>
                        <div class="info-value"><?= htmlspecialchars($emp['civil_status'] ?? '—'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-label">Phone Number</div>
                        <div class="info-value"><?= htmlspecialchars($emp['phone'] ?? '—'); ?></div>
                    </div>
                    <div class="col-12">
                        <div class="info-label">Residential Address</div>
                        <div class="info-value"><?= htmlspecialchars($emp['address'] ?? '—'); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="page-card mt-4">
                <h6 class="section-title"><i class="bi bi-card-checklist me-2"></i>Government Benefits & Tax IDs</h6>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-label">SSS Number</div>
                        <div class="info-value"><?= htmlspecialchars($emp['sss_no'] ?? '—'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-label">PhilHealth Number</div>
                        <div class="info-value"><?= htmlspecialchars($emp['philhealth_no'] ?? '—'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-label">Pag-IBIG MID</div>
                        <div class="info-value"><?= htmlspecialchars($emp['pagibig_no'] ?? '—'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-label">TIN Number</div>
                        <div class="info-value"><?= htmlspecialchars($emp['tin_no'] ?? '—'); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-secondary py-3 px-4 border-0 rounded-4" style="background: #e9ecef; font-size:13px; color:#495057;">
                <i class="bi bi-info-circle-fill text-secondary me-2"></i>
                If any of the information above is incorrect or needs updating, please contact your Human Resources department to submit official verification documents.
            </div>
        </div>
        
    </div>

</div>
