<?php
session_start();
require_once '../../Model/database.php';
require_once '../../Model/logger.php';

$current_user = $_SESSION['user_id'] ?? $_SESSION['emp_id'] ?? 1;
$user_name    = $_SESSION['full_name'] ?? $_SESSION['emp_name'] ?? 'Warehouse Manager';

/*=========================================================
    DATABASE TABLE INITIALIZATION (AUTO-MIGRATE)
==========================================================*/
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS warehouse_dispatches (
        dispatch_id INT AUTO_INCREMENT PRIMARY KEY,
        reference_no VARCHAR(50) NOT NULL,
        destination_branch VARCHAR(100) NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        scheduled_date DATETIME NOT NULL,
        driver_info VARCHAR(150) NULL,
        notes TEXT NULL,
        status ENUM('Scheduled','In-Transit','Delivered','Discrepancy','Cancelled') DEFAULT 'Scheduled',
        dispatched_by INT NOT NULL,
        dispatched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        received_at DATETIME NULL,
        received_by INT NULL,
        discrepancy_notes TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/*=========================================================
    HELPER: VERIFY ADMIN / USER PASSWORD
==========================================================*/
function verifyDispatchPassword($conn, $user_id, $password) {
    if (empty($password)) return false;

    if (!isset($_SESSION['user_id']) && !isset($_SESSION['emp_id'])) {
        $alt_name = (session_name() === 'OCART_ADMIN_SESS') ? 'OCART_EMP_SESS' : 'OCART_ADMIN_SESS';
        session_write_close();
        session_name($alt_name);
        session_start();
    }

    if (isset($_SESSION['user_id'])) {
        $uid = (int)$_SESSION['user_id'];
        $res = mysqli_query($conn, "SELECT password FROM users WHERE user_id = $uid LIMIT 1");
        $row = mysqli_fetch_assoc($res);
        return ($row && !empty($row['password']) && password_verify($password, $row['password']));
    }
    if (isset($_SESSION['emp_id'])) {
        $eid = (int)$_SESSION['emp_id'];
        $res = mysqli_query($conn, "SELECT password, employee_no FROM employees WHERE employee_id = $eid LIMIT 1");
        $row = mysqli_fetch_assoc($res);
        if (!$row) return false;
        return ((!empty($row['password']) && password_verify($password, $row['password'])) || ($password === $row['employee_no']));
    }
    return false;
}

/*=========================================================
    ACTION: CREATE SCHEDULED TRANSPORT DISPATCH
==========================================================*/
if (isset($_POST['action']) && $_POST['action'] === 'create_dispatch') {
    $password     = $_POST['password'] ?? '';
    if (!verifyDispatchPassword($conn, $current_user, $password)) {
        ob_clean(); echo 'error: Incorrect password. Dispatch authorization failed.'; exit();
    }

    $dest_branch  = mysqli_real_escape_string($conn, trim($_POST['destination_branch'] ?? 'Main Storefront'));
    $product_id   = (int)$_POST['product_id'];
    $quantity     = (int)$_POST['quantity'];
    $sched_date   = mysqli_real_escape_string($conn, trim($_POST['scheduled_date'] ?? date('Y-m-d H:i:s')));
    $driver_info  = mysqli_real_escape_string($conn, trim($_POST['driver_info'] ?? ''));
    $notes        = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? ''));
    $ref_no       = 'TRF-WH-' . date('Ymd') . '-' . rand(1000, 9999);

    if ($product_id <= 0 || $quantity <= 0) {
        ob_clean(); echo 'error: Please select a valid product and quantity.'; exit();
    }

    // Verify product stock exists
    $prodRes = mysqli_query($conn, "SELECT p.product_name, i.inventory_id, i.quantity AS current_qty FROM products p LEFT JOIN inventory i ON p.product_id = i.product_id WHERE p.product_id = $product_id LIMIT 1");
    $prod = mysqli_fetch_assoc($prodRes);
    if (!$prod) {
        ob_clean(); echo 'error: Product not found.'; exit();
    }

    $query = mysqli_query($conn, "
        INSERT INTO warehouse_dispatches 
        (reference_no, destination_branch, product_id, quantity, scheduled_date, driver_info, notes, status, dispatched_by, dispatched_at)
        VALUES ('$ref_no', '$dest_branch', $product_id, $quantity, '$sched_date', '$driver_info', '$notes', 'Scheduled', $current_user, NOW())
    ");

    if ($query) {
        $dispatch_id = mysqli_insert_id($conn);
        logAction($conn, 1, 'Create Transport Dispatch', 'warehouse_dispatches', $dispatch_id, 
            "Scheduled stock transport {$ref_no} ({$quantity}x {$prod['product_name']}) to {$dest_branch} by {$user_name}");
        mysqli_query($conn, "
            INSERT INTO notifications (title, message, type, is_read)
            VALUES ('Warehouse Dispatch Scheduled', 'New shipment {$ref_no} ({$quantity} units of {$prod['product_name']}) scheduled for {$dest_branch}.', 'System', 0)
        ");
        ob_clean(); echo 'success'; exit();
    } else {
        ob_clean(); echo 'error: ' . mysqli_error($conn); exit();
    }
}

/*=========================================================
    ACTION: UPDATE DISPATCH STATUS (e.g. Mark In-Transit / Dispatched)
==========================================================*/
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $dispatch_id = (int)$_POST['dispatch_id'];
    $new_status  = mysqli_real_escape_string($conn, trim($_POST['status']));

    $disp = mysqli_fetch_assoc(mysqli_query($conn, "SELECT d.*, p.product_name FROM warehouse_dispatches d JOIN products p ON d.product_id=p.product_id WHERE d.dispatch_id=$dispatch_id LIMIT 1"));
    if (!$disp) { ob_clean(); echo 'error: Dispatch record not found.'; exit(); }

    $query = mysqli_query($conn, "UPDATE warehouse_dispatches SET status='$new_status' WHERE dispatch_id=$dispatch_id");
    if ($query) {
        logAction($conn, 1, 'Dispatch Status Update', 'warehouse_dispatches', $dispatch_id, 
            "Dispatch {$disp['reference_no']} status updated to {$new_status} by {$user_name}");
        ob_clean(); echo 'success'; exit();
    } else {
        ob_clean(); echo 'error: ' . mysqli_error($conn); exit();
    }
}

/*=========================================================
    FETCH WAREHOUSE DATA & DISPATCHES
==========================================================*/
$dispatches = mysqli_query($conn, "
    SELECT d.*, p.product_name, c.category_name, i.quantity AS warehouse_stock
    FROM warehouse_dispatches d
    JOIN products p ON d.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN inventory i ON p.product_id = i.product_id
    ORDER BY d.dispatched_at DESC
");
$dispatchList = [];
if ($dispatches) {
    while ($r = mysqli_fetch_assoc($dispatches)) $dispatchList[] = $r;
}

$products = mysqli_query($conn, "
    SELECT p.product_id, p.product_name, c.category_name, COALESCE(i.quantity, 0) AS stock_qty
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN inventory i ON p.product_id = i.product_id
    WHERE p.deleted_at IS NULL
    ORDER BY p.product_name ASC
");
$productList = [];
if ($products) {
    while ($p = mysqli_fetch_assoc($products)) $productList[] = $p;
}

// Stat metrics
$totalDispatches  = count($dispatchList);
$scheduledCount   = 0;
$inTransitCount   = 0;
$deliveredCount   = 0;

foreach ($dispatchList as $d) {
    if ($d['status'] === 'Scheduled')  $scheduledCount++;
    if ($d['status'] === 'In-Transit') $inTransitCount++;
    if ($d['status'] === 'Delivered')  $deliveredCount++;
}
?>

<style>
.wh-card {
    background: white; border-radius: 14px; padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.05); height: 100%;
}
.badge-sched { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 600; }
.badge-transit { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; font-weight: 600; }
.badge-deliv { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; font-weight: 600; }
.badge-disc { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; font-weight: 600; }
</style>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color:#0f4c81;">
            <i class="bi bi-building-fill me-2 text-primary"></i>Central Warehouse Management
        </h4>
        <p class="text-muted mb-0" style="font-size:13px;">Schedule and manage stock transports from central warehouse to store branches.</p>
    </div>
    <button class="btn btn-primary" onclick="openScheduleModal()" style="border-radius:10px;font-weight:600;">
        <i class="bi bi-truck me-1"></i> Schedule Transport
    </button>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="wh-card">
            <small class="text-muted fw-semibold">Total Dispatches</small>
            <h3 class="fw-bold mb-0 text-dark"><?= $totalDispatches; ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="wh-card">
            <small class="text-muted fw-semibold">Scheduled Transports</small>
            <h3 class="fw-bold mb-0 text-info"><?= $scheduledCount; ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="wh-card">
            <small class="text-muted fw-semibold">In-Transit / Dispatched</small>
            <h3 class="fw-bold mb-0 text-warning"><?= $inTransitCount; ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="wh-card">
            <small class="text-muted fw-semibold">Completed Deliveries</small>
            <h3 class="fw-bold mb-0 text-success"><?= $deliveredCount; ?></h3>
        </div>
    </div>
</div>

<!-- SCHEDULE TABLE -->
<div class="wh-card">
    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-table me-2 text-primary"></i>Warehouse Transport Schedule & History</h6>
    <div class="table-responsive">
        <table class="table table-hover align-middle datatable w-100" id="warehouseTable">
            <thead class="table-light" style="font-size:12px;text-transform:uppercase;">
                <tr>
                    <th>Ref #</th>
                    <th>Destination Branch</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Scheduled Date</th>
                    <th>Driver / Vehicle</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dispatchList as $d): ?>
                <tr>
                    <td class="fw-bold text-primary" style="font-size:13px;"><?= htmlspecialchars($d['reference_no']); ?></td>
                    <td><span class="badge bg-secondary-subtle text-dark border fw-semibold"><?= htmlspecialchars($d['destination_branch']); ?></span></td>
                    <td class="fw-semibold"><?= htmlspecialchars($d['product_name']); ?></td>
                    <td class="fw-bold"><?= $d['quantity']; ?> units</td>
                    <td style="font-size:12px;"><?= date('M d, Y h:i A', strtotime($d['scheduled_date'])); ?></td>
                    <td style="font-size:12px;"><?= htmlspecialchars($d['driver_info'] ?: '—'); ?></td>
                    <td>
                        <?php 
                        $st = $d['status'];
                        $bClass = 'badge-sched';
                        if ($st === 'In-Transit') $bClass = 'badge-transit';
                        if ($st === 'Delivered')  $bClass = 'badge-deliv';
                        if ($st === 'Discrepancy') $bClass = 'badge-disc';
                        ?>
                        <span class="badge <?= $bClass; ?>"><?= $st; ?></span>
                    </td>
                    <td class="text-center">
                        <?php if ($d['status'] === 'Scheduled'): ?>
                        <button class="btn btn-sm btn-warning me-1" onclick="updateDispatchStatus(<?= $d['dispatch_id']; ?>, 'In-Transit')">
                            <i class="bi bi-box-arrow-right me-1"></i>Dispatch
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-secondary" onclick="viewDispatchDetails(<?= htmlspecialchars(json_encode($d), ENT_QUOTES); ?>)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SCHEDULE MODAL -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:12px;">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-truck me-2"></i>Schedule Stock Transport</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:24px;">
                <form id="scheduleForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold" style="font-size:12px;">Destination Branch <span class="text-danger">*</span></label>
                            <select class="form-select" id="destination_branch" name="destination_branch" required>
                                <option value="Main Storefront">Main Storefront</option>
                                <option value="Branch #1 - North">Branch #1 - North</option>
                                <option value="Branch #2 - South">Branch #2 - South</option>
                                <option value="Retail Outlet A">Retail Outlet A</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" style="font-size:12px;">Scheduled Dispatch Date & Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="scheduled_date" name="scheduled_date" required value="<?= date('Y-m-d\TH:i'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" style="font-size:12px;">Select Product <span class="text-danger">*</span></label>
                            <select class="form-select" id="product_id" name="product_id" required>
                                <option value="">-- Select Product --</option>
                                <?php foreach ($productList as $p): ?>
                                <option value="<?= $p['product_id']; ?>">
                                    <?= htmlspecialchars($p['product_name']); ?> (Available: <?= $p['stock_qty']; ?> units)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" style="font-size:12px;">Transport Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" required placeholder="e.g. 50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" style="font-size:12px;">Driver / Vehicle Info</label>
                            <input type="text" class="form-control" id="driver_info" name="driver_info" placeholder="e.g. Van-02 / Driver: Carlos">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" style="font-size:12px;">Transport Notes / Instructions</label>
                            <input type="text" class="form-control" id="notes" name="notes" placeholder="e.g. Handle with care, Frozen items...">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitScheduleTransport()">
                    <i class="bi bi-check-circle-fill me-1"></i> Confirm & Schedule Dispatch
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function openScheduleModal() {
    $('#scheduleForm')[0].reset();
    new bootstrap.Modal(document.getElementById('scheduleModal')).show();
}

function submitScheduleTransport() {
    const branch = $('#destination_branch').val();
    const prodId = $('#product_id').val();
    const qty    = $('#quantity').val();

    if (!branch || !prodId || !qty || parseInt(qty) <= 0) {
        Swal.fire('Missing Information', 'Please fill in all required transport fields.', 'warning');
        return;
    }

    Swal.fire({
        target: document.getElementById('scheduleModal'),
        title: 'Confirm Authorization Password',
        html: 'Enter your administrator password to authorize this warehouse stock transport dispatch.',
        input: 'password',
        inputPlaceholder: 'Password',
        inputAttributes: { autocapitalize: 'off', autocorrect: 'off', autocomplete: 'new-password' },
        didOpen: () => {
            const input = Swal.getInput();
            if (input) { input.value = ''; input.setAttribute('autocomplete', 'new-password'); }
        },
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        confirmButtonText: 'Authorize & Schedule',
        cancelButtonText: 'Cancel',
        inputValidator: (val) => { if (!val) return 'Password is required to confirm.'; }
    }).then(result => {
        if (!result.isConfirmed) return;

        const formData = $('#scheduleForm').serialize() + '&action=create_dispatch&password=' + encodeURIComponent(result.value);
        $.post('warehouse/warehouse.php', formData, function(res) {
            res = res.trim();
            if (res === 'success') {
                Swal.fire({ icon:'success', title:'Transport Scheduled!', text:'Warehouse stock transport dispatch has been recorded.', timer:1800, showConfirmButton:false })
                .then(() => {
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open');
                    loadPage('warehouse/warehouse.php');
                });
            } else {
                Swal.fire('Error', res.replace(/^error:\s*/i, ''), 'error');
            }
        });
    });
}

function updateDispatchStatus(dispatchId, status) {
    Swal.fire({
        title: 'Mark as Dispatched / In-Transit?',
        text: 'This will notify the target branch of incoming stock.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        confirmButtonText: 'Yes, Dispatch Transport'
    }).then(result => {
        if (!result.isConfirmed) return;
        $.post('warehouse/warehouse.php', { action: 'update_status', dispatch_id: dispatchId, status: status }, function(res) {
            if (res.trim() === 'success') {
                Swal.fire({ icon:'success', title:'Status Updated!', timer:1500, showConfirmButton:false })
                .then(() => loadPage('warehouse/warehouse.php'));
            } else {
                Swal.fire('Error', res.replace(/^error:\s*/i,''), 'error');
            }
        });
    });
}

function viewDispatchDetails(d) {
    Swal.fire({
        title: `Dispatch Ref #${d.reference_no}`,
        html: `
            <div class="text-start p-2">
                <p><strong>Destination Branch:</strong> ${d.destination_branch}</p>
                <p><strong>Product:</strong> ${d.product_name}</p>
                <p><strong>Quantity:</strong> ${d.quantity} units</p>
                <p><strong>Scheduled:</strong> ${d.scheduled_date}</p>
                <p><strong>Vehicle/Driver:</strong> ${d.driver_info || 'N/A'}</p>
                <p><strong>Notes:</strong> ${d.notes || 'None'}</p>
                <p><strong>Status:</strong> ${d.status}</p>
            </div>
        `,
        icon: 'info'
    });
}
</script>
