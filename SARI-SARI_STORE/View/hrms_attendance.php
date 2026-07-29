<?php
require_once '../Model/database.php';
require_once '../Controller/HRMSController.php';

if(session_status() === PHP_SESSION_NONE){
    session_start();
}
$admin_id = $_SESSION['user_id'] ?? 1;

$hrmsController = new HRMSController($conn);

/*=========================================================
    ACTIONS (POST)
==========================================================*/

// ARCHIVE ATTENDANCE
if(isset($_POST['action']) && $_POST['action'] === 'archive_attendance'){
    $result = $hrmsController->archiveAttendance($_POST['attendance_id'], $_POST['reason'] ?? '', $admin_id);
    ob_clean(); echo $result; exit;
}

// RESTORE ATTENDANCE
if(isset($_POST['action']) && $_POST['action'] === 'restore_attendance'){
    $result = $hrmsController->restoreAttendance($_POST['archive_id'], $admin_id);
    ob_clean(); echo $result; exit;
}

// REJECT ATTENDANCE
if(isset($_POST['action']) && $_POST['action'] === 'reject_attendance'){
    $result = $hrmsController->rejectAttendance($_POST['attendance_id'], $_POST['reason'] ?? '', $admin_id);
    ob_clean(); echo $result; exit;
}

// CREATE - Manual Entry
if(isset($_POST['action']) && $_POST['action'] === 'create'){
    $result = $hrmsController->createAttendanceManual($_POST, $admin_id);
    ob_clean(); echo $result; exit;
}

// UPDATE
if(isset($_POST['action']) && $_POST['action'] === 'update'){
    $result = $hrmsController->updateAttendanceManual($_POST, $admin_id);
    ob_clean(); echo $result; exit;
}

// DELETE
if(isset($_POST['action']) && $_POST['action'] === 'delete'){
    $result = $hrmsController->deleteAttendance($_POST['attendance_id'], $admin_id, $_POST['password'] ?? '');
    ob_clean(); echo $result; exit;
}

/*=========================================================
    FETCH DATA
==========================================================*/
$today = date('Y-m-d');

$logs = $hrmsController->getAttendanceLogsList();
$logList = [];
while($row = mysqli_fetch_assoc($logs)){
    $logList[] = $row;
}

// Employees for dropdown
$empRes = mysqli_query($conn, "SELECT employee_id, employee_no, full_name FROM employees WHERE status='Active' ORDER BY full_name ASC");
$empList = [];
while($row = mysqli_fetch_assoc($empRes)){ $empList[] = $row; }

// Today stats
$presentCount = 0; $lateCount = 0; $absentCount = 0;
$totalHours   = 0; $doneCount = 0;
foreach($logList as $l){
    if($l['date'] !== $today) continue;
    if(in_array($l['status'], ['Present','Late','Half Day'])) $presentCount++;
    if($l['status'] === 'Late')   $lateCount++;
    if($l['status'] === 'Absent') $absentCount++;
    if($l['hours_worked'] > 0){ $totalHours += (float)$l['hours_worked']; $doneCount++; }
}
$avgHours = $doneCount > 0 ? round($totalHours / $doneCount, 2) : 0;
?>

<style>

.swal2-container { z-index: 99999 !important; }

.att-stat-card {
    background: white;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
    height: 100%;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.att-stat-icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; color: white; flex-shrink: 0;
}
.att-stat-label { font-size: 11px; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.att-stat-value { font-size: 26px; font-weight: 800; line-height: 1.2; margin-top: 4px; }
.badge-present    { background: #d1fae5; color: #065f46; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
.badge-late       { background: #fef3c7; color: #92400e; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
.badge-absent     { background: #fee2e2; color: #991b1b; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
.badge-halfday    { background: #e0e7ff; color: #3730a3; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
.badge-onleave    { background: #f3e8ff; color: #6b21a8; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
.snapshot-thumb {
    width: 44px; height: 44px;
    border-radius: 8px;
    object-fit: cover;
    cursor: pointer;
    border: 2px solid #e5e7eb;
    transition: transform 0.15s;
}
.snapshot-thumb:hover { transform: scale(1.1); border-color: #2563eb; }
.snapshot-placeholder {
    width: 44px; height: 44px;
    border-radius: 8px;
    background: #f3f4f6;
    display: flex; align-items: center; justify-content: center;
    color: #9ca3af; font-size: 18px;
    border: 2px dashed #d1d5db;
}
</style>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color: #1a3c5e;">Attendance</h4>
        <small class="text-muted">Track employee time-in/out logs and manage attendance records</small>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="openArchiveAttendanceModal()">
            <i class="bi bi-archive-fill me-1"></i> Archive
            <?php
            $archAttCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM attendance_archive"))['c'];
            if ($archAttCount > 0)
                echo '<span class="badge bg-danger ms-1">' . $archAttCount . '</span>';
            ?>
        </button>
        <a href="qr_attendance.php" target="_blank" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-qr-code-scan me-1"></i> Open Scanner Terminal
        </a>
        <button class="btn btn-primary btn-sm" onclick="openManualEntry()">
            <i class="bi bi-plus-lg me-1"></i> Manual Entry
        </button>
    </div>
</div>

<!-- STATS -->
<div class="row g-3 mb-4 animate__animated animate__fadeIn">
    <div class="col-md-3">
        <div class="att-stat-card border-start border-success border-4">
            <div>
                <div class="att-stat-label">Present Today</div>
                <div class="att-stat-value text-success"><?= $presentCount; ?></div>
            </div>
            <div class="att-stat-icon bg-success"><i class="bi bi-person-check-fill"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="att-stat-card border-start border-warning border-4">
            <div>
                <div class="att-stat-label">Late Today</div>
                <div class="att-stat-value text-warning"><?= $lateCount; ?></div>
            </div>
            <div class="att-stat-icon bg-warning"><i class="bi bi-clock-fill"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="att-stat-card border-start border-danger border-4">
            <div>
                <div class="att-stat-label">Absent Today</div>
                <div class="att-stat-value text-danger"><?= $absentCount; ?></div>
            </div>
            <div class="att-stat-icon bg-danger"><i class="bi bi-person-x-fill"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="att-stat-card border-start border-info border-4">
            <div>
                <div class="att-stat-label">Avg Hrs Worked</div>
                <div class="att-stat-value text-info"><?= $avgHours; ?>h</div>
            </div>
            <div class="att-stat-icon bg-info"><i class="bi bi-hourglass-split"></i></div>
        </div>
    </div>
</div>

<!-- TABLE -->
<div class="page-card animate__animated animate__fadeIn">
    <div class="table-responsive">
        <table class="table table-hover align-middle datatable w-100" id="attendanceTable">
            <thead class="table-light">
                <tr>
                    <th style="width:50px;">Snap</th>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Hours</th>
                    <th>OT Hrs</th>
                    <th>Status</th>
                    <th style="width:100px; text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($logList as $log):
                $statusClass = 'badge-present';
                if($log['status'] === 'Late')     $statusClass = 'badge-late';
                elseif($log['status'] === 'Absent')   $statusClass = 'badge-absent';
                elseif($log['status'] === 'Half Day') $statusClass = 'badge-halfday';
                elseif($log['status'] === 'On Leave') $statusClass = 'badge-onleave';
                $hasPhoto = !empty($log['photo']) && file_exists(__DIR__ . '/uploads/attendance/' . $log['photo']);
            ?>
            <tr>
                <td>
                    <?php if($hasPhoto): ?>
                        <img src="uploads/attendance/<?= htmlspecialchars($log['photo']); ?>"
                             class="snapshot-thumb"
                             onclick="viewSnapshot('uploads/attendance/<?= htmlspecialchars($log['photo']); ?>', '<?= htmlspecialchars(addslashes($log['full_name'])); ?>', '<?= $log['date']; ?>')"
                             title="View Snapshot">
                    <?php else: ?>
                        <div class="snapshot-placeholder" title="No snapshot"><i class="bi bi-camera-slash"></i></div>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="fw-semibold text-dark"><?= htmlspecialchars($log['full_name']); ?></div>
                    <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($log['employee_no']); ?></div>
                </td>
                <td class="fw-semibold"><?= date('M d, Y', strtotime($log['date'])); ?></td>
                <td><?= $log['time_in'] ? date('h:i A', strtotime($log['time_in'])) : '<span class="text-muted">—</span>'; ?></td>
                <td><?= $log['time_out'] ? date('h:i A', strtotime($log['time_out'])) : '<span class="text-muted">—</span>'; ?></td>
                <td><?= $log['hours_worked'] > 0 ? '<span class="fw-bold text-success">' . number_format($log['hours_worked'], 2) . 'h</span>' : '<span class="text-muted">—</span>'; ?></td>
                <td><?= $log['overtime_hours'] > 0 ? '<span class="fw-bold text-warning">' . number_format($log['overtime_hours'], 2) . 'h</span>' : '<span class="text-muted">0h</span>'; ?></td>
                <td><span class="<?= $statusClass; ?>"><?= htmlspecialchars($log['status']); ?></span></td>
                <td>
                    <div class="d-flex justify-content-center gap-1">
                        <button class="btn btn-sm btn-outline-warning"
                                onclick="openEditModal(<?= htmlspecialchars(json_encode($log)); ?>)"
                                title="Edit Log">
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-dark"
                                onclick="deleteLog(<?= $log['attendance_id']; ?>, '<?= addslashes($log['full_name']); ?>', '<?= $log['date']; ?>')"
                                title="Remove Log">
                            <i class="bi bi-trash-fill"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!--=========================================================
    SNAPSHOT VIEW MODAL
==========================================================-->
<div class="modal fade" id="snapshotModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header" style="background:#1a3c5e;color:white;">
                <h5 class="modal-title fw-bold"><i class="bi bi-camera-fill me-2"></i>Verification Snapshot</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="snapshotImg" src="" class="img-fluid w-100" style="max-height:480px;object-fit:contain;">
            </div>
            <div class="modal-footer bg-light border-0">
                <div class="text-start flex-grow-1">
                    <div id="snapshotEmpName" class="fw-bold text-dark"></div>
                    <small id="snapshotDate" class="text-muted"></small>
                </div>
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    MANUAL ENTRY MODAL
==========================================================-->
<div class="modal fade" id="manualEntryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header" style="background:#1a3c5e;color:white;">
                <h5 class="modal-title fw-bold"><i class="bi bi-calendar-plus-fill me-2"></i>Manual Attendance Entry</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="manualEntryForm">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Employee <span class="text-danger">*</span></label>
                            <select class="form-select" name="employee_id" required>
                                <option value="">-- Select Employee --</option>
                                <?php foreach($empList as $e): ?>
                                <option value="<?= $e['employee_id']; ?>"><?= htmlspecialchars($e['full_name']); ?> (<?= htmlspecialchars($e['employee_no']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date" required value="<?= date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Time In</label>
                            <input type="time" class="form-control" name="time_in">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Time Out</label>
                            <input type="time" class="form-control" name="time_out">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select class="form-select" name="status" required>
                                <option value="Present">Present</option>
                                <option value="Late">Late</option>
                                <option value="Absent">Absent</option>
                                <option value="Half Day">Half Day</option>
                                <option value="On Leave">On Leave</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="Optional notes..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!--=========================================================
    EDIT LOG MODAL
==========================================================-->
<div class="modal fade" id="editLogModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Attendance Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editLogForm">
                <input type="hidden" name="attendance_id" id="edit_att_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="alert alert-secondary py-2 mb-0">
                                <strong>Employee:</strong> <span id="edit_att_emp_name"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date" id="edit_att_date" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Time In</label>
                            <input type="time" class="form-control" name="time_in" id="edit_att_time_in">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Time Out</label>
                            <input type="time" class="form-control" name="time_out" id="edit_att_time_out">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select class="form-select" name="status" id="edit_att_status" required>
                                <option value="Present">Present</option>
                                <option value="Late">Late</option>
                                <option value="Absent">Absent</option>
                                <option value="Half Day">Half Day</option>
                                <option value="On Leave">On Leave</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea class="form-control" name="notes" id="edit_att_notes" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-check-lg me-1"></i>Update Log</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ARCHIVE ATTENDANCE MODAL -->
<div class="modal fade" id="archiveAttendanceModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-archive-fill me-2"></i>Archived Attendance Records</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100 fs-7">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Employee</th>
                                <th>Date</th>
                                <th>Original Status</th>
                                <th>Archival Reason</th>
                                <th>Archived At</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $archAttQuery = mysqli_query($conn, "
                                SELECT a.*, e.full_name, e.employee_no 
                                FROM attendance_archive a 
                                LEFT JOIN employees e ON a.employee_id = e.employee_id 
                                ORDER BY a.archived_at DESC
                            ");
                            if (mysqli_num_rows($archAttQuery) > 0) {
                                while ($archRow = mysqli_fetch_assoc($archAttQuery)) {
                                    echo '<tr>';
                                    echo '<td><span class="badge bg-secondary font-monospace">#' . $archRow['archive_id'] . '</span></td>';
                                    echo '<td><div class="fw-bold">' . htmlspecialchars($archRow['full_name'] ?? 'N/A') . '</div><small class="text-muted">' . htmlspecialchars($archRow['employee_no'] ?? '') . '</small></td>';
                                    echo '<td>' . date('M d, Y', strtotime($archRow['date'])) . '</td>';
                                    echo '<td><span class="badge bg-light text-dark border">' . htmlspecialchars($archRow['status']) . '</span></td>';
                                    echo '<td><span class="text-danger fw-semibold"><i class="bi bi-chat-left-quote me-1"></i>' . htmlspecialchars($archRow['archive_reason'] ?: 'No reason provided') . '</span></td>';
                                    echo '<td><small class="text-muted">' . date('M d, Y h:i A', strtotime($archRow['archived_at'])) . '</small></td>';
                                    echo '<td class="text-center"><button class="btn btn-sm btn-success" onclick="restoreAttendanceRecord(' . $archRow['archive_id'] . ', \'' . addslashes($archRow['full_name']) . '\')"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</button></td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox me-1"></i>No archived attendance records found.</td></tr>';
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

<!-- REJECTED ATTENDANCE MODAL -->
<div class="modal fade" id="rejectedAttendanceModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-x-circle-fill me-2"></i>Rejected Attendance Records</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100 fs-7">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Employee</th>
                                <th>Date</th>
                                <th>Time In / Out</th>
                                <th>Hours</th>
                                <th>Rejection Reasoning / Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rejAttQuery = mysqli_query($conn, "
                                SELECT a.*, e.full_name, e.employee_no 
                                FROM attendance a 
                                JOIN employees e ON a.employee_id = e.employee_id 
                                WHERE a.status = 'Rejected' 
                                ORDER BY a.date DESC
                            ");
                            if (mysqli_num_rows($rejAttQuery) > 0) {
                                while ($rejRow = mysqli_fetch_assoc($rejAttQuery)) {
                                    $inFmt = $rejRow['time_in'] ? date('h:i A', strtotime($rejRow['time_in'])) : '—';
                                    $outFmt = $rejRow['time_out'] ? date('h:i A', strtotime($rejRow['time_out'])) : '—';
                                    echo '<tr>';
                                    echo '<td><span class="badge bg-danger font-monospace">#' . $rejRow['attendance_id'] . '</span></td>';
                                    echo '<td><div class="fw-bold">' . htmlspecialchars($rejRow['full_name']) . '</div><small class="text-muted">' . htmlspecialchars($rejRow['employee_no']) . '</small></td>';
                                    echo '<td>' . date('M d, Y', strtotime($rejRow['date'])) . '</td>';
                                    echo '<td>' . $inFmt . ' - ' . $outFmt . '</td>';
                                    echo '<td>' . number_format($rejRow['hours_worked'], 2) . 'h</td>';
                                    echo '<td><span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i>' . htmlspecialchars($rejRow['notes'] ?: 'Unverified / Disputed Attendance') . '</span></td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-check-circle me-1"></i>No rejected attendance records found.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
                            <input type="time" class="form-control" name="time_in">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Time Out</label>
                            <input type="time" class="form-control" name="time_out">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select class="form-select" name="status" required>
                                <option value="Present">Present</option>
                                <option value="Late">Late</option>
                                <option value="Absent">Absent</option>
                                <option value="Half Day">Half Day</option>
                                <option value="On Leave">On Leave</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="Optional notes..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!--=========================================================
    EDIT LOG MODAL
==========================================================-->
<div class="modal fade" id="editLogModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Attendance Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editLogForm">
                <input type="hidden" name="attendance_id" id="edit_att_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="alert alert-secondary py-2 mb-0">
                                <strong>Employee:</strong> <span id="edit_att_emp_name"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date" id="edit_att_date" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Time In</label>
                            <input type="time" class="form-control" name="time_in" id="edit_att_time_in">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Time Out</label>
                            <input type="time" class="form-control" name="time_out" id="edit_att_time_out">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select class="form-select" name="status" id="edit_att_status" required>
                                <option value="Present">Present</option>
                                <option value="Late">Late</option>
                                <option value="Absent">Absent</option>
                                <option value="Half Day">Half Day</option>
                                <option value="On Leave">On Leave</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea class="form-control" name="notes" id="edit_att_notes" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-check-lg me-1"></i>Update Log</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ARCHIVE ATTENDANCE MODAL -->
<div class="modal fade" id="archiveAttendanceModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-archive-fill me-2"></i>Archived Attendance Records</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100 fs-7">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Employee</th>
                                <th>Date</th>
                                <th>Original Status</th>
                                <th>Archival Reason</th>
                                <th>Archived At</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $archAttQuery = mysqli_query($conn, "
                                SELECT a.*, e.full_name, e.employee_no 
                                FROM attendance_archive a 
                                LEFT JOIN employees e ON a.employee_id = e.employee_id 
                                ORDER BY a.archived_at DESC
                            ");
                            if (mysqli_num_rows($archAttQuery) > 0) {
                                while ($archRow = mysqli_fetch_assoc($archAttQuery)) {
                                    echo '<tr>';
                                    echo '<td><span class="badge bg-secondary font-monospace">#' . $archRow['archive_id'] . '</span></td>';
                                    echo '<td><div class="fw-bold">' . htmlspecialchars($archRow['full_name'] ?? 'N/A') . '</div><small class="text-muted">' . htmlspecialchars($archRow['employee_no'] ?? '') . '</small></td>';
                                    echo '<td>' . date('M d, Y', strtotime($archRow['date'])) . '</td>';
                                    echo '<td><span class="badge bg-light text-dark border">' . htmlspecialchars($archRow['status']) . '</span></td>';
                                    echo '<td><span class="text-danger fw-semibold"><i class="bi bi-chat-left-quote me-1"></i>' . htmlspecialchars($archRow['archive_reason'] ?: 'No reason provided') . '</span></td>';
                                    echo '<td><small class="text-muted">' . date('M d, Y h:i A', strtotime($archRow['archived_at'])) . '</small></td>';
                                    echo '<td class="text-center"><button class="btn btn-sm btn-success" onclick="restoreAttendanceRecord(' . $archRow['archive_id'] . ', \'' . addslashes($archRow['full_name']) . '\')"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</button></td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox me-1"></i>No archived attendance records found.</td></tr>';
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

<script>
(function(){
$(document).ready(function(){
    if($.fn.DataTable){
        $('#attendanceTable').DataTable({
            responsive: true, pageLength: 15, lengthChange: false, ordering: true, searching: true, order: [[2, 'desc']], destroy: true
        });
    }

    // Manual Entry submit
    $('#manualEntryForm').off('submit').on('submit', function(e){
        e.preventDefault();
        const formEl = this;
        const modalEl = document.getElementById('manualEntryModal');
        if (modalEl) (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('padding-right', '');

        Swal.fire({
            title: 'Confirm Your Password',
            html: 'Enter your account password to save this entry.',
            input: 'password',
            inputPlaceholder: 'Password',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            confirmButtonText: 'Confirm & Save',
            inputValidator: v => { if(!v) return 'Password is required.'; }
        }).then(result => {
            if(!result.isConfirmed) return;
            const formData = new FormData(formEl);
            formData.append('action', 'create');
            formData.append('password', result.value);
            $.ajax({
                url: 'hrms_attendance.php', type: 'POST', data: formData, processData: false, contentType: false,
                success: function(res){
                    if(res.trim() === 'success'){
                        Swal.fire({ icon:'success', title:'Entry Saved!', showConfirmButton: false, timer: 1500 }).then(() => loadPage('hrms_attendance.php'));
                    } else { Swal.fire('Error', res, 'error'); }
                }
            });
        });
    });

    // Edit Log submit
    $('#editLogForm').off('submit').on('submit', function(e){
        e.preventDefault();
        const formEl = this;
        const modalEl = document.getElementById('editLogModal');
        if (modalEl) (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('padding-right', '');

        Swal.fire({
            title: 'Confirm Your Password',
            html: 'Enter your account password to save these changes.',
            input: 'password',
            inputPlaceholder: 'Password',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            confirmButtonText: 'Confirm & Update',
            inputValidator: v => { if(!v) return 'Password is required.'; }
        }).then(result => {
            if(!result.isConfirmed) return;
            const formData = new FormData(formEl);
            formData.append('action', 'update');
            formData.append('password', result.value);
            $.ajax({
                url: 'hrms_attendance.php', type: 'POST', data: formData, processData: false, contentType: false,
                success: function(res){
                    if(res.trim() === 'success'){
                        Swal.fire({ icon:'success', title:'Log Updated!', showConfirmButton: false, timer: 1500 }).then(() => loadPage('hrms_attendance.php'));
                    } else { Swal.fire('Error', res, 'error'); }
                }
            });
        });
    });
});

function clearBackdropHrms(){
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open').css('padding-right','');
}

function openArchiveAttendanceModal() {
    const modalEl = document.getElementById('archiveAttendanceModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }
}

function openRejectedAttendanceModal() {
    const modalEl = document.getElementById('rejectedAttendanceModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }
}

function archiveAttendanceRecord(id, name, date) {
    Swal.fire({
        title: 'Archive Attendance Record?',
        html: `<p class="text-muted mb-2" style="font-size:13px;">Archive log for <strong>${name}</strong> on <strong>${date}</strong>.</p>
               <input id="archReasonAtt" class="swal2-input" placeholder="Reason e.g. Duplicate entry, Audit cleanup...">`,
        showCancelButton: true,
        confirmButtonColor: '#6c757d',
        confirmButtonText: 'Archive Record',
        preConfirm: () => {
            const r = document.getElementById('archReasonAtt').value.trim();
            if (!r) { Swal.showValidationMessage('Please provide a reason.'); return false; }
            return r;
        }
    }).then(result => {
        if (!result.isConfirmed) return;
        $.post('hrms_attendance.php', { action: 'archive_attendance', attendance_id: id, reason: result.value }, function (res) {
            if (res.trim() === 'success') {
                Swal.fire({ icon: 'success', title: 'Archived!', timer: 1500, showConfirmButton: false }).then(() => loadPage('hrms_attendance.php'));
            } else { Swal.fire('Error', res, 'error'); }
        });
    });
}

function restoreAttendanceRecord(archiveId, name) {
    Swal.fire({
        title: 'Restore Attendance Record?',
        html: `Restore record for <strong>${name}</strong>?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Yes, Restore'
    }).then(result => {
        if (!result.isConfirmed) return;
        $.post('hrms_attendance.php', { action: 'restore_attendance', archive_id: archiveId }, function (res) {
            if (res.trim() === 'success') {
                Swal.fire({ icon: 'success', title: 'Restored!', timer: 1500, showConfirmButton: false }).then(() => loadPage('hrms_attendance.php'));
            } else { Swal.fire('Error', res, 'error'); }
        });
    });
}

function openManualEntry(){
    $('#manualEntryForm')[0].reset();
    $('[name="date"]', '#manualEntryForm').val('<?= date('Y-m-d'); ?>');
    const modalEl = document.getElementById('manualEntryModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }
}

function openEditModal(log){
    $('#edit_att_id').val(log.attendance_id);
    $('#edit_att_emp_name').text(log.full_name + ' (' + log.employee_no + ')');
    $('#edit_att_date').val(log.date);
    $('#edit_att_time_in').val(log.time_in ? log.time_in.substring(0, 5) : '');
    $('#edit_att_time_out').val(log.time_out ? log.time_out.substring(0, 5) : '');
    $('#edit_att_status').val(log.status || 'Present');
    $('#edit_att_notes').val(log.notes || '');
    const modalEl = document.getElementById('editLogModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }
}

function viewSnapshot(src, empName, date){
    $('#snapshotImg').attr('src', src + '?t=' + Date.now());
    $('#snapshotEmpName').text(empName);
    $('#snapshotDate').text(date);
    const modalEl = document.getElementById('snapshotModal');
    if (modalEl) {
        document.body.appendChild(modalEl);
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    }
}

function deleteLog(id, name, date){
    Swal.fire({
        title: 'Remove Attendance Log?',
        html: `
            <p class="text-muted mb-2" style="font-size:13px;">Removing log for <strong>${name}</strong> on <strong>${date}</strong>.</p>
            <div class="text-start mb-2">
                <label class="form-label fw-bold text-dark" style="font-size:12px;">Reason for Removal <span class="text-danger">*</span></label>
                <select id="removeAttReasonSelect" class="form-select" style="font-size:13px;">
                    <option value="Duplicate Attendance Log">Duplicate Attendance Log</option>
                    <option value="System / Device Timekeeper Error">System / Device Timekeeper Error</option>
                    <option value="Unauthorized Time-In Entry">Unauthorized Time-In Entry</option>
                    <option value="Log Corrected via Manual Overtime Form">Log Corrected via Manual Overtime Form</option>
                    <option value="Test / Incorrect Scan Entry">Test / Incorrect Scan Entry</option>
                </select>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Next: Confirm Password',
        preConfirm: () => {
            const r = document.getElementById('removeAttReasonSelect').value;
            if (!r) { Swal.showValidationMessage('Please select a reason.'); return false; }
            return r;
        }
    }).then(reasonResult => {
        if (!reasonResult.isConfirmed) return;
        const reason = reasonResult.value;

        Swal.fire({
            title: 'Confirm Admin Password',
            html: `Enter your password to confirm removing <strong>${name}'s</strong> log on <strong>${date}</strong>.`,
            input: 'password',
            inputPlaceholder: 'Enter your password',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, Remove Log',
            inputValidator: v => { if(!v) return 'Password is required.'; }
        }).then(result => {
            if(!result.isConfirmed) return;

            $.post('hrms_attendance.php', {
                action: 'delete',
                attendance_id: id,
                password: result.value,
                reason: reason
            }, function(res){
                res = res.trim();
                if(res === 'success'){
                    Swal.fire({ icon:'success', title:'Log Removed!', showConfirmButton: false, timer: 1500 })
                        .then(() => loadPage('hrms_attendance.php'));
                } else {
                    Swal.fire('Error', res, 'error');
                }
            });
        });
    });
}

window.openManualEntry             = openManualEntry;
window.openEditModal               = openEditModal;
window.viewSnapshot                = viewSnapshot;
window.deleteLog                   = deleteLog;
window.openArchiveAttendanceModal  = openArchiveAttendanceModal;
window.openRejectedAttendanceModal = openRejectedAttendanceModal;
window.archiveAttendanceRecord     = archiveAttendanceRecord;
window.restoreAttendanceRecord     = restoreAttendanceRecord;
window.clearBackdropHrms           = clearBackdropHrms;
})();
</script>
