<?php
session_start();
require_once '../../Model/database.php';

if(!isset($_SESSION['emp_id'])){
    exit("Unauthorized");
}

$emp_id = $_SESSION['emp_id'];

// Get attendance logs for current employee
$logs = mysqli_query($conn, "
    SELECT * FROM attendance
    WHERE employee_id = $emp_id
    ORDER BY date DESC, time_in DESC
");

$logList = [];
while($row = mysqli_fetch_assoc($logs)){
    $logList[] = $row;
}
?>
<style>
.badge-present    { background: #d1fae5; color: #065f46; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
.badge-late       { background: #fef3c7; color: #92400e; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
.badge-absent     { background: #fee2e2; color: #991b1b; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
.badge-halfday    { background: #e0e7ff; color: #3730a3; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
.badge-onleave    { background: #f3e8ff; color: #6b21a8; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
</style>

<div class="animate__animated animate__fadeIn">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color: #0f5132;">My Attendance logs</h4>
            <small class="text-muted">Review your verified daily clock-in/out records</small>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="page-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle datatable w-100" id="empAttendanceTable">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Hours Worked</th>
                        <th>Overtime Hours</th>
                        <th>Status</th>
                        <th>Remarks / Notes</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($logList as $log):
                    $statusClass = 'badge-present';
                    if($log['status'] === 'Late')     $statusClass = 'badge-late';
                    elseif($log['status'] === 'Absent')   $statusClass = 'badge-absent';
                    elseif($log['status'] === 'Half Day') $statusClass = 'badge-halfday';
                    elseif($log['status'] === 'On Leave') $statusClass = 'badge-onleave';
                ?>
                <tr>
                    <td class="fw-bold"><?= date('M d, Y', strtotime($log['date'])); ?></td>
                    <td><?= $log['time_in'] ? date('h:i A', strtotime($log['time_in'])) : '<span class="text-muted">—</span>'; ?></td>
                    <td><?= $log['time_out'] ? date('h:i A', strtotime($log['time_out'])) : '<span class="text-muted">—</span>'; ?></td>
                    <td><?= $log['hours_worked'] > 0 ? '<span class="fw-bold text-success">' . number_format($log['hours_worked'], 2) . 'h</span>' : '<span class="text-muted">—</span>'; ?></td>
                    <td><?= $log['overtime_hours'] > 0 ? '<span class="fw-bold text-warning">' . number_format($log['overtime_hours'], 2) . 'h</span>' : '<span class="text-muted">0h</span>'; ?></td>
                    <td><span class="<?= $statusClass; ?>"><?= htmlspecialchars($log['status']); ?></span></td>
                    <td><small class="text-muted"><?= htmlspecialchars($log['notes'] ?? '—'); ?></small></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
$(document).ready(function(){
    if($.fn.DataTable){
        $('#empAttendanceTable').DataTable({
            responsive: true,
            pageLength: 15,
            lengthChange: false,
            ordering: true,
            searching: true,
            order: [[0, 'desc']],
            destroy: true
        });
    }
});
</script>
