<?php
require_once '../Model/database.php';
require_once '../Model/logger.php';

if(session_status() === PHP_SESSION_NONE){
    session_start();
}
$admin_id = $_SESSION['user_id'] ?? 1;

/*=========================================================
    AJAX ACTIONS (POST)
==========================================================*/

// CREATE DEPARTMENT
if(isset($_POST['action']) && $_POST['action'] == 'create'){
    $department_name = mysqli_real_escape_string($conn, trim($_POST['department_name']));
    $description     = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));

    if(empty($department_name)){
        ob_clean();
        echo 'error: Department Name is required.';
        exit();
    }

    // Duplicate check
    $dup = mysqli_query($conn, "SELECT department_id FROM departments WHERE department_name='$department_name'");
    if(mysqli_num_rows($dup) > 0){
        ob_clean();
        echo 'error: A department with this name already exists.';
        exit();
    }

    $q = mysqli_query($conn, "
        INSERT INTO departments (department_name, description)
        VALUES ('$department_name', '$description')
    ");

    ob_clean();
    if($q){
        $new_id = mysqli_insert_id($conn);
        logAction($conn, $admin_id, 'Create', 'departments', $new_id, "Created new department: $department_name");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// UPDATE DEPARTMENT
if(isset($_POST['action']) && $_POST['action'] == 'update'){
    $department_id   = (int)$_POST['department_id'];
    $department_name = mysqli_real_escape_string($conn, trim($_POST['department_name']));
    $description     = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));

    if($department_id <= 0 || empty($department_name)){
        ob_clean();
        echo 'error: Department Name is required.';
        exit();
    }

    // Duplicate check (exclude self)
    $dup = mysqli_query($conn, "SELECT department_id FROM departments WHERE department_name='$department_name' AND department_id != $department_id");
    if(mysqli_num_rows($dup) > 0){
        ob_clean();
        echo 'error: Another department with this name already exists.';
        exit();
    }

    $q = mysqli_query($conn, "
        UPDATE departments SET
            department_name='$department_name',
            description='$description'
        WHERE department_id=$department_id
    ");

    ob_clean();
    if($q){
        logAction($conn, $admin_id, 'Update', 'departments', $department_id, "Updated department: $department_name");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// DELETE DEPARTMENT
if(isset($_POST['action']) && $_POST['action'] == 'delete'){
    $department_id = (int)$_POST['department_id'];

    if($department_id <= 0){
        ob_clean();
        echo 'error: Invalid Department ID.';
        exit();
    }

    // Check for employee dependencies
    $check_emp = mysqli_query($conn, "SELECT employee_id FROM employees WHERE department_id=$department_id LIMIT 1");
    if(mysqli_num_rows($check_emp) > 0){
        ob_clean();
        echo 'error: Cannot delete this department because it is assigned to one or more active employees.';
        exit();
    }

    // Check for position dependencies
    $check_pos = mysqli_query($conn, "SELECT position_id FROM positions WHERE department_id=$department_id LIMIT 1");
    if(mysqli_num_rows($check_pos) > 0){
        ob_clean();
        echo 'error: Cannot delete this department because it has registered positions linked to it.';
        exit();
    }

    // Fetch details for logging
    $dept_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT department_name FROM departments WHERE department_id=$department_id"));
    $department_name = $dept_info ? $dept_info['department_name'] : 'Unknown';

    $q = mysqli_query($conn, "DELETE FROM departments WHERE department_id=$department_id");

    ob_clean();
    if($q){
        logAction($conn, $admin_id, 'Delete', 'departments', $department_id, "Deleted department: $department_name");
        echo 'success';
    } else {
        echo 'error: ' . mysqli_error($conn);
    }
    exit();
}

// FETCH INDIVIDUAL DEPARTMENT DETAILS (AJAX - GET)
if(isset($_GET['action']) && $_GET['action'] == 'get_department'){
    $department_id = (int)$_GET['department_id'];
    $q = mysqli_query($conn, "
        SELECT * FROM departments WHERE department_id = $department_id
    ");
    $dept = mysqli_fetch_assoc($q);
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($dept);
    exit();
}

/*=========================================================
    FETCH LISTS & STATS
==========================================================*/

// Get departments list with counts
$dept_query = mysqli_query($conn, "
    SELECT d.*, 
           COUNT(DISTINCT e.employee_id) as employee_count,
           COUNT(DISTINCT p.position_id) as position_count
    FROM departments d
    LEFT JOIN employees e ON d.department_id = e.department_id AND e.status = 'Active'
    LEFT JOIN positions p ON d.department_id = p.department_id
    GROUP BY d.department_id
    ORDER BY d.department_name ASC
");

$departmentsList = [];
$total_departments = 0;
$total_employees_count = 0;
$total_positions_count = 0;

while($row = mysqli_fetch_assoc($dept_query)){
    $departmentsList[] = $row;
    $total_departments++;
    $total_employees_count += (int)$row['employee_count'];
    $total_positions_count += (int)$row['position_count'];
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
.modal-header-primary { background: linear-gradient(135deg, #1a3c5e, #2563eb); color: white; }
.modal-header-primary .btn-close { filter: invert(1); }
</style>

<!-- ===== PAGE HEADER ===== -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color:#1a3c5e;">
            <i class="bi bi-tag-fill me-2" style="color:#2563eb;"></i>Department Management
        </h4>
        <small class="text-muted">Manage company organizational structures, details, and classifications.</small>
    </div>
    <button class="btn btn-primary" onclick="openAddModal()">
        <i class="bi bi-plus-lg me-1"></i> Add New Department
    </button>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="row g-3 mb-4 animate__animated animate__fadeIn">
    <div class="col-md-4">
        <div class="stat-card border-start border-primary border-4">
            <div>
                <div class="stat-label">Total Departments</div>
                <div class="stat-value"><?= $total_departments; ?></div>
            </div>
            <div class="stat-icon bg-primary"><i class="bi bi-building"></i></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card border-start border-success border-4">
            <div>
                <div class="stat-label">Active Employees Assigned</div>
                <div class="stat-value text-success"><?= $total_employees_count; ?></div>
            </div>
            <div class="stat-icon bg-success"><i class="bi bi-people-fill"></i></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card border-start border-warning border-4">
            <div>
                <div class="stat-label">Total Linked Positions</div>
                <div class="stat-value text-warning"><?= $total_positions_count; ?></div>
            </div>
            <div class="stat-icon bg-warning text-dark"><i class="bi bi-tag-fill"></i></div>
        </div>
    </div>
</div>

<!-- ===== DEPARTMENTS TABLE ===== -->
<div class="page-card animate__animated animate__fadeIn">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0" style="color:#1a3c5e;">
            <i class="bi bi-table me-2"></i>Departments List
        </h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle w-100" id="departmentsTable">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Department Name</th>
                    <th>Description</th>
                    <th style="text-align:center;">Positions Linked</th>
                    <th style="text-align:center;">Active Employees</th>
                    <th style="text-align:center; width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($departmentsList as $i => $row) { ?>
                <tr>
                    <td><?= $i + 1; ?></td>
                    <td>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['department_name']); ?></div>
                    </td>
                    <td>
                        <span class="text-muted" style="font-size:13px;"><?= htmlspecialchars($row['description'] ?: 'No description provided'); ?></span>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge bg-light text-dark border"><?= $row['position_count']; ?></span>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge bg-light text-dark border"><?= $row['employee_count']; ?></span>
                    </td>
                    <td>
                        <div class="d-flex justify-content-center gap-1">
                            <button class="btn btn-sm btn-outline-primary" onclick="openEditModal(<?= $row['department_id']; ?>)" title="Edit Department">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteDepartment(<?= $row['department_id']; ?>, '<?= addslashes($row['department_name']); ?>')" title="Delete Department">
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
<div class="modal fade" id="departmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header modal-header-primary">
                <h5 class="modal-title fw-bold" id="modalTitle">
                    <i class="bi bi-plus-circle me-2"></i>Add Department
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="departmentForm">
                    <input type="hidden" id="dept_id" name="department_id">
                    <input type="hidden" id="form_action" name="action" value="create">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Department Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="dept_name" name="department_name" required placeholder="e.g. Sales & Operations">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea class="form-control" id="dept_desc" name="description" rows="4" placeholder="Briefly describe the functions of this department..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="submitForm()">
                    <i class="bi bi-check-lg me-1"></i>Save Department
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    if($.fn.DataTable){
        $('#departmentsTable').DataTable({
            destroy: true,
            pageLength: 10,
            order: [[0, 'asc']],
            columnDefs: [{ orderable: false, targets: [5] }],
            language: {
                emptyTable: 'No departments recorded. Click "Add New Department" to add one.',
                search: 'Search:',
                lengthMenu: 'Show _MENU_ records'
            }
        });
    }
});

function openAddModal() {
    document.getElementById('departmentForm').reset();
    document.getElementById('dept_id').value = '';
    document.getElementById('form_action').value = 'create';
    document.getElementById('modalTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Add Department';
    new bootstrap.Modal(document.getElementById('departmentModal')).show();
}

function openEditModal(id) {
    document.getElementById('departmentForm').reset();
    $.get('hrms_departments.php', { action: 'get_department', department_id: id }, function(dept){
        document.getElementById('dept_id').value = dept.department_id;
        document.getElementById('form_action').value = 'update';
        document.getElementById('dept_name').value = dept.department_name;
        document.getElementById('dept_desc').value = dept.description;

        document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Edit Department';
        new bootstrap.Modal(document.getElementById('departmentModal')).show();
    }, 'json');
}

function submitForm() {
    const form = document.getElementById('departmentForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    $.post('hrms_departments.php', $(form).serialize(), function(res){
        if(res.trim() === 'success'){
            Swal.fire({ icon:'success', title:'Saved Successfully!', timer:1500, showConfirmButton:false })
            .then(() => { clearBackdrop(); loadPage('hrms_departments.php'); });
        } else {
            Swal.fire('Error', res, 'error');
        }
    });
}

function deleteDepartment(id, name) {
    Swal.fire({
        title: 'Delete Department?',
        html: `Are you sure you want to delete the department: <strong>${name}</strong>?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Delete'
    }).then(result => {
        if(!result.isConfirmed) return;
        $.post('hrms_departments.php', { action: 'delete', department_id: id }, function(res){
            if(res.trim() === 'success'){
                Swal.fire({ icon:'success', title:'Deleted!', timer:1500, showConfirmButton:false })
                .then(() => loadPage('hrms_departments.php'));
            } else {
                Swal.fire('Error', res, 'error');
            }
        });
    });
}
</script>
