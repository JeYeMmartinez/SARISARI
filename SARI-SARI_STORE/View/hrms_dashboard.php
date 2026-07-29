<?php
require_once '../Model/database.php';
require_once '../Controller/HRMSController.php';

$hrmsController = new HRMSController($conn);
$stats = $hrmsController->getDashboardStats();

$totalEmployees   = $stats['totalEmployees'];
$totalApplicants  = $stats['totalApplicants'];
$openJobs         = $stats['openJobs'];
$pendingLeaves    = $stats['pendingLeaves'];
$todayPresent     = $stats['todayPresent'];
$todayAbsent      = $stats['todayAbsent'];
$draftPayroll     = $stats['draftPayroll'];
$totalDepartments = $stats['totalDepartments'];

$deptBreakdown = $hrmsController->getDepartmentBreakdown();
$recentEmployees = $hrmsController->getRecentEmployees(5);
$stageData = $hrmsController->getApplicantStageData();
?>

<style>
.hrms-stat { background:white; border-radius:14px; padding:20px;
             box-shadow:0 2px 10px rgba(0,0,0,.06); height:100%; }
.hrms-stat .icon { width:48px; height:48px; border-radius:12px;
                   display:flex; align-items:center; justify-content:center;
                   font-size:22px; color:white; flex-shrink:0; }
.page-card { background:white; border-radius:14px; padding:22px 24px;
             box-shadow:0 2px 10px rgba(0,0,0,.06); margin-bottom:22px; }
.emp-row { display:flex; align-items:center; gap:12px; padding:10px 0;
           border-bottom:1px solid #f0f0f0; }
.emp-row:last-child { border-bottom:none; }
.emp-avatar { width:38px; height:38px; border-radius:50%; background:#2563eb;
              display:flex; align-items:center; justify-content:center;
              color:white; font-weight:700; font-size:14px; flex-shrink:0; }
</style>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">

    <div class="col-xl-2 col-md-4">
        <div class="hrms-stat">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div style="font-size:12px;color:#6c757d;">Departments</div>
                    <div style="font-size:26px;font-weight:800;line-height:1.2;margin:6px 0;">
                        <?= $totalDepartments; ?>
                    </div>
                    <span class="badge bg-secondary">Org Units</span>
                </div>
                <div class="icon bg-secondary"><i class="bi bi-building"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-4">
        <div class="hrms-stat">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div style="font-size:12px;color:#6c757d;">Active Employees</div>
                    <div style="font-size:26px;font-weight:800;line-height:1.2;margin:6px 0;">
                        <?= $totalEmployees; ?>
                    </div>
                    <span class="badge bg-success">Workforce</span>
                </div>
                <div class="icon bg-success"><i class="bi bi-people-fill"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4">
        <div class="hrms-stat">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div style="font-size:12px;color:#6c757d;">Applicants</div>
                    <div style="font-size:26px;font-weight:800;line-height:1.2;margin:6px 0;">
                        <?= $totalApplicants; ?>
                    </div>
                    <span class="badge bg-primary">Pipeline</span>
                </div>
                <div class="icon bg-primary"><i class="bi bi-person-lines-fill"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-6">
        <div class="hrms-stat">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div style="font-size:12px;color:#6c757d;">Open Positions</div>
                    <div style="font-size:26px;font-weight:800;line-height:1.2;margin:6px 0;">
                        <?= $openJobs; ?>
                    </div>
                    <span class="badge bg-warning text-dark">Hiring</span>
                </div>
                <div class="icon bg-warning"><i class="bi bi-briefcase-fill"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="hrms-stat">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div style="font-size:12px;color:#6c757d;">Pending Leaves</div>
                    <div style="font-size:26px;font-weight:800;line-height:1.2;margin:6px 0;">
                        <?= $pendingLeaves; ?>
                    </div>
                    <span class="badge bg-danger">Needs Review</span>
                </div>
                <div class="icon bg-danger"><i class="bi bi-calendar-x-fill"></i></div>
            </div>
        </div>
    </div>

</div>

<!-- SECOND ROW -->
<div class="row g-3 mb-4">

    <div class="col-md-4">
        <div class="hrms-stat">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div style="font-size:12px;color:#6c757d;">Present Today</div>
                    <div style="font-size:28px;font-weight:800;color:#198754;line-height:1.2;margin:6px 0;">
                        <?= $todayPresent; ?>
                    </div>
                    <span class="badge bg-success">On Time</span>
                </div>
                <div class="icon bg-success"><i class="bi bi-calendar-check-fill"></i></div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="hrms-stat">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div style="font-size:12px;color:#6c757d;">Absent Today</div>
                    <div style="font-size:28px;font-weight:800;color:#dc3545;line-height:1.2;margin:6px 0;">
                        <?= $todayAbsent; ?>
                    </div>
                    <span class="badge bg-danger">Absent</span>
                </div>
                <div class="icon bg-danger"><i class="bi bi-person-dash-fill"></i></div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="hrms-stat">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div style="font-size:12px;color:#6c757d;">Payroll Drafts</div>
                    <div style="font-size:28px;font-weight:800;color:#2563eb;line-height:1.2;margin:6px 0;">
                        <?= $draftPayroll; ?>
                    </div>
                    <span class="badge bg-primary">Pending Approval</span>
                </div>
                <div class="icon bg-primary"><i class="bi bi-cash-coin"></i></div>
            </div>
        </div>
    </div>

</div>

<div class="row g-3">

    <!-- RECENT EMPLOYEES -->
    <div class="col-lg-6">
        <div class="page-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Recent Employees</h5>
                <a href="#" onclick="loadPage('hrms_employees.php')"
                   style="font-size:13px;color:#2563eb;text-decoration:none;">
                    View all →
                </a>
            </div>

            <?php if(mysqli_num_rows($recentEmployees) == 0){ ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-people" style="font-size:36px;"></i>
                <p class="mt-2 mb-0" style="font-size:13px;">No employees yet</p>
            </div>
            <?php } else { while($emp = mysqli_fetch_assoc($recentEmployees)){ ?>
            <div class="emp-row">
                <div class="emp-avatar">
                    <?= strtoupper(substr($emp['full_name'], 0, 1)); ?>
                </div>
                <div style="flex:1;">
                    <div style="font-size:13px;font-weight:600;">
                        <?= htmlspecialchars($emp['full_name']); ?>
                    </div>
                    <div style="font-size:11px;color:#6c757d;">
                        <?= htmlspecialchars($emp['position_name'] ?? 'No Position'); ?> •
                        <?= htmlspecialchars($emp['department_name'] ?? 'No Dept'); ?>
                    </div>
                </div>
                <span class="badge bg-success" style="font-size:10px;">Active</span>
            </div>
            <?php } } ?>
        </div>
    </div>

    <!-- APPLICANT PIPELINE CHART -->
    <div class="col-lg-6">
        <div class="page-card">
            <h5 class="mb-3">Applicant Pipeline</h5>
            <canvas id="pipelineChart" height="200"></canvas>
        </div>
    </div>

</div>

<script>
function buildPipelineChart(){
    new Chart(document.getElementById('pipelineChart'), {
        type: 'bar',
        data: {
            labels: ['Initial Screening','First Interview','Final Interview'],
            datasets:[{
                label: 'Applicants',
                data: <?= json_encode($stageData); ?>,
                backgroundColor: ['#2563eb','#ffc107','#198754'],
                borderRadius: 8,
                borderWidth: 0
            }]
        },
        options:{
            responsive:true,
            plugins:{ legend:{ display:false } },
            scales:{
                y:{ beginAtZero:true, ticks:{ stepSize:1 } },
                x:{ grid:{ display:false } }
            }
        }
    });
}

if(typeof Chart !== 'undefined'){
    buildPipelineChart();
} else {
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
    s.onload = buildPipelineChart;
    document.head.appendChild(s);
}
</script>