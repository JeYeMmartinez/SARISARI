<?php
require_once '../Model/database.php';
require_once '../Controller/HRMSController.php';

if(session_status() === PHP_SESSION_NONE){
    session_start();
}
$admin_id = $_SESSION['user_id'] ?? 1;

$hrmsController = new HRMSController($conn);

/*=========================================================
    AJAX ACTIONS (POST)
==========================================================*/

// CREATE POSITION
if(isset($_POST['action']) && $_POST['action'] == 'create'){
    $result = $hrmsController->createPosition($_POST, $admin_id);
    ob_clean(); echo $result; exit();
}

// UPDATE POSITION
if(isset($_POST['action']) && $_POST['action'] == 'update'){
    $result = $hrmsController->updatePosition($_POST, $admin_id);
    ob_clean(); echo $result; exit();
}

// DELETE POSITION
if(isset($_POST['action']) && $_POST['action'] == 'delete'){
    $result = $hrmsController->deletePosition($_POST['position_id'], $admin_id);
    ob_clean(); echo $result; exit();
}

// FETCH INDIVIDUAL POSITION DETAILS (AJAX - GET)
if(isset($_GET['action']) && $_GET['action'] == 'get_position'){
    $pos = $hrmsController->getPositionDetails($_GET['position_id']);
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($pos);
    exit();
}

/*=========================================================
    FETCH LISTS & STATS
==========================================================*/

// Fetch departments list
$departments_list = [];
$dept_query_res = $hrmsController->getDepartmentsList();
while($d = mysqli_fetch_assoc($dept_query_res)){
    $departments_list[] = $d;
}

// Get positions list
$pos_query = $hrmsController->getPositionsDetailedList();
$positionsList = [];
$total_positions = 0;
$open_positions = 0;
$total_slots = 0;
$avg_salary = 0.00;
$salary_sum = 0;

while($row = mysqli_fetch_assoc($pos_query)){
    $positionsList[] = $row;
    $total_positions++;
    // "Open" = flag says Open AND may available slot pa — hindi na open kung puno na
    if($row['status'] === 'Open' && (int)$row['filled_slots'] < (int)$row['slots']) $open_positions++;
    $total_slots += (int)$row['slots'];
    $salary_sum += (($row['salary_min'] + $row['salary_max']) / 2);
}
if($total_positions > 0){
    $avg_salary = $salary_sum / $total_positions;
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
.stat-value { font-size: 24px; font-weight: 800; line-height: 1.2; margin-top: 4px; }
.status-pill { font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; display: inline-block; }
.status-open { background: #d1fae5; color: #065f46; }
.status-closed { background: #f3f4f6; color: #374151; }
.status-onhold { background: #fef3c7; color: #92400e; }
.modal-header-primary { background: linear-gradient(135deg, #1a3c5e, #2563eb); color: white; }
.modal-header-primary .btn-close { filter: invert(1); }
</style>

<!-- ===== PAGE HEADER ===== -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color:#1a3c5e;">
            <i class="bi bi-tag-fill me-2" style="color:#2563eb;"></i>Position Management
        </h4>
        <small class="text-muted">Manage job roles, employment models, and pay ranges.</small>
    </div>
    <button class="btn btn-primary" onclick="openAddModal()">
        <i class="bi bi-plus-lg me-1"></i> Add New Position
    </button>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="row g-3 mb-4 animate__animated animate__fadeIn">
    <div class="col-md-3">
        <div class="stat-card border-start border-primary border-4">
            <div>
                <div class="stat-label">Total Job Roles</div>
                <div class="stat-value"><?= $total_positions; ?></div>
            </div>
            <div class="stat-icon bg-primary"><i class="bi bi-briefcase-fill"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card border-start border-success border-4">
            <div>
                <div class="stat-label">Open Positions</div>
                <div class="stat-value text-success"><?= $open_positions; ?></div>
            </div>
            <div class="stat-icon bg-success"><i class="bi bi-unlock-fill"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card border-start border-warning border-4">
            <div>
                <div class="stat-label">Total Target Slots</div>
                <div class="stat-value text-warning"><?= $total_slots; ?></div>
            </div>
            <div class="stat-icon bg-warning text-dark"><i class="bi bi-person-lines-fill"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card border-start border-info border-4">
            <div>
                <div class="stat-label">Avg Salary Range</div>
                <div class="stat-value text-primary" style="font-size: 18px;">&#8369;<?= number_format($avg_salary, 0); ?></div>
            </div>
            <div class="stat-icon bg-info"><i class="bi bi-currency-dollar"></i></div>
        </div>
    </div>
</div>

<!-- ===== POSITIONS TABLE ===== -->
<div class="page-card animate__animated animate__fadeIn">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0" style="color:#1a3c5e;">
            <i class="bi bi-table me-2"></i>Positions Directory
        </h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle w-100" id="positionsTable">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Role / Title</th>
                    <th>Department</th>
                    <th>Employment Type</th>
                    <th>Target Slots</th>
                    <th>Monthly Salary Range</th>
                    <th>Status</th>
                    <th style="text-align:center; width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($positionsList as $i => $row) { 
                    $badgeClass = match($row['status']){
                        'Open' => 'status-open',
                        'Closed' => 'status-closed',
                        default => 'status-onhold'
                    };
                ?>
                <tr>
                    <td><?= $i + 1; ?></td>
                    <td>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['position_name']); ?></div>
                    </td>
                    <td>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold">
                            <?= htmlspecialchars($row['department_name'] ?? 'General'); ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($row['employment_type']); ?></td>
                    <td><?= $row['slots']; ?></td>
                    <td class="fw-semibold text-secondary">
                        &#8369;<?= number_format($row['salary_min'], 2); ?> - &#8369;<?= number_format($row['salary_max'], 2); ?>
                    </td>
                    <td>
                        <span class="status-pill <?= $badgeClass; ?>"><?= htmlspecialchars($row['status']); ?></span>
                    </td>
                    <td>
                        <div class="d-flex justify-content-center gap-1">
                            <button class="btn btn-sm btn-outline-info" onclick="viewDetails(<?= $row['position_id']; ?>)" title="View Requirements">
                                <i class="bi bi-eye-fill"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="openEditModal(<?= $row['position_id']; ?>)" title="Edit Position">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deletePosition(<?= $row['position_id']; ?>, '<?= addslashes($row['position_name']); ?>')" title="Delete Position">
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
    ADD/EDIT MODAL
==========================================================-->
<div class="modal fade" id="positionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header modal-header-primary">
                <h5 class="modal-title fw-bold" id="modalTitle">
                    <i class="bi bi-plus-circle me-2"></i>Add Position
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="positionForm">
                    <input type="hidden" id="pos_id" name="position_id">
                    <input type="hidden" id="form_action" name="action" value="create">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Position/Role Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="pos_name" name="position_name" required placeholder="e.g. Store Supervisor">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Department</label>
                            <select class="form-select" id="pos_dept" name="department_id">
                                <option value="">-- Unassigned / General --</option>
                                <?php foreach($departments_list as $dept){ ?>
                                <option value="<?= $dept['department_id']; ?>"><?= htmlspecialchars($dept['department_name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">Employment Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="pos_type" name="employment_type" required>
                                <option value="Full-time">Full-time</option>
                                <option value="Part-time">Part-time</option>
                                <option value="Contractual">Contractual</option>
                                <option value="Probationary">Probationary</option>
                            </select>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">Target Slots <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="pos_slots" name="slots" min="1" value="1" required>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">Min Monthly Salary (&#8369;) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="pos_sal_min" name="salary_min" min="0" step="0.01" value="0.00" required>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">Max Monthly Salary (&#8369;) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="pos_sal_max" name="salary_max" min="0" step="0.01" value="0.00" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="pos_status" name="status" required>
                                <option value="Open">Open</option>
                                <option value="Closed">Closed</option>
                                <option value="On Hold">On Hold</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Requirements & Description</label>
                            <textarea class="form-control" id="pos_req" name="requirements" rows="4" placeholder="Mention qualifications, responsibilities, or tools required..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="submitForm()">
                    <i class="bi bi-check-lg me-1"></i>Save Position
                </button>
            </div>
        </div>
    </div>
</div>

<!--=========================================================
    VIEW DETAILS MODAL
==========================================================-->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header modal-header-primary">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-info-circle me-2"></i>Position Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <small class="text-muted text-uppercase fw-semibold">Role Name</small>
                    <div class="fs-5 fw-bold text-dark" id="view_name"></div>
                </div>
                <div class="row mb-3">
                    <div class="col-12">
                        <small class="text-muted text-uppercase fw-semibold">Employment Type</small>
                        <div class="fw-semibold text-dark" id="view_type"></div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <small class="text-muted text-uppercase fw-semibold">Salary Range</small>
                        <div class="fw-semibold text-dark" id="view_salary"></div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted text-uppercase fw-semibold">Available Slots</small>
                        <div class="fw-semibold text-dark" id="view_slots"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <small class="text-muted text-uppercase fw-semibold">Status</small>
                    <div><span class="status-pill" id="view_status"></span></div>
                </div>
                <hr>
                <div>
                    <small class="text-muted text-uppercase fw-semibold">Requirements & Description</small>
                    <div class="p-3 bg-light rounded mt-1 border text-secondary" id="view_req" style="white-space: pre-wrap; font-size: 13px;"></div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    if($.fn.DataTable){
        $('#positionsTable').DataTable({
            destroy: true,
            pageLength: 15,
            order: [[0, 'asc']],
            columnDefs: [{ orderable: false, targets: [6] }],
            language: {
                emptyTable: 'No positions recorded. Click "Add New Position" to add one.',
                search: 'Search:',
                lengthMenu: 'Show _MENU_ records'
            }
        });
    }
});

function openAddModal() {
    document.getElementById('positionForm').reset();
    document.getElementById('pos_id').value = '';
    document.getElementById('form_action').value = 'create';
    document.getElementById('pos_dept').value = '';
    document.getElementById('modalTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Add Position';
    new bootstrap.Modal(document.getElementById('positionModal')).show();
}

function openEditModal(id) {
    document.getElementById('positionForm').reset();
    $.get('hrms_positions.php', { action: 'get_position', position_id: id }, function(pos){
        document.getElementById('pos_id').value = pos.position_id;
        document.getElementById('form_action').value = 'update';
        document.getElementById('pos_name').value = pos.position_name;
        document.getElementById('pos_dept').value = pos.department_id || '';
        document.getElementById('pos_type').value = pos.employment_type;
        document.getElementById('pos_slots').value = pos.slots;
        document.getElementById('pos_sal_min').value = pos.salary_min;
        document.getElementById('pos_sal_max').value = pos.salary_max;
        document.getElementById('pos_status').value = pos.status;
        document.getElementById('pos_req').value = pos.requirements;

        document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Edit Position';
        new bootstrap.Modal(document.getElementById('positionModal')).show();
    }, 'json');
}

function viewDetails(id) {
    $.get('hrms_positions.php', { action: 'get_position', position_id: id }, function(pos){
        document.getElementById('view_name').innerText = pos.position_name + (pos.department_name ? ' (' + pos.department_name + ')' : '');
        document.getElementById('view_type').innerText = pos.employment_type;
        document.getElementById('view_slots').innerText = pos.slots;
        document.getElementById('view_salary').innerHTML = '&#8369;' + parseFloat(pos.salary_min).toLocaleString('en-US', {minimumFractionDigits:2}) + ' - &#8369;' + parseFloat(pos.salary_max).toLocaleString('en-US', {minimumFractionDigits:2});
        
        const statusEl = document.getElementById('view_status');
        statusEl.innerText = pos.status;
        statusEl.className = 'status-pill ' + (pos.status === 'Open' ? 'status-open' : (pos.status === 'Closed' ? 'status-closed' : 'status-onhold'));

        document.getElementById('view_req').innerText = pos.requirements || 'No specific requirements listed.';
        new bootstrap.Modal(document.getElementById('detailsModal')).show();
    }, 'json');
}

function submitForm() {
    const form = document.getElementById('positionForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const minSal = parseFloat(document.getElementById('pos_sal_min').value) || 0;
    const maxSal = parseFloat(document.getElementById('pos_sal_max').value) || 0;
    if (maxSal < minSal) {
        Swal.fire('Invalid Salary Range', 'Maximum salary cannot be less than minimum salary.', 'warning');
        return;
    }

    $.post('hrms_positions.php', $(form).serialize(), function(res){
        if(res.trim() === 'success'){
            Swal.fire({ icon:'success', title:'Saved Successfully!', timer:1500, showConfirmButton:false })
            .then(() => { clearBackdrop(); loadPage('hrms_positions.php'); });
        } else {
            Swal.fire('Error', res, 'error');
        }
    });
}

function deletePosition(id, name) {
    Swal.fire({
        title: 'Delete Position?',
        html: `Are you sure you want to delete the role: <strong>${name}</strong>?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Delete'
    }).then(result => {
        if(!result.isConfirmed) return;
        $.post('hrms_positions.php', { action: 'delete', position_id: id }, function(res){
            if(res.trim() === 'success'){
                Swal.fire({ icon:'success', title:'Deleted!', timer:1500, showConfirmButton:false })
                .then(() => loadPage('hrms_positions.php'));
            } else {
                Swal.fire('Error', res, 'error');
            }
        });
    });
}
</script>