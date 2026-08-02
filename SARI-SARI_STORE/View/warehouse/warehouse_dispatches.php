<?php
session_start();
require_once '../../Model/database.php';
require_once '../../Model/logger.php';

$current_user = $_SESSION['user_id'] ?? $_SESSION['emp_id'] ?? 1;
$user_name    = $_SESSION['full_name'] ?? $_SESSION['emp_name'] ?? 'Warehouse Clerk';

/*=========================================================
    DATABASE SCHEMA INITIALIZATION (AUTO-MIGRATE)
==========================================================*/
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS warehouse_dispatches (
        dispatch_id INT AUTO_INCREMENT PRIMARY KEY,
        dispatch_code VARCHAR(50) UNIQUE NOT NULL,
        source_warehouse VARCHAR(100) DEFAULT 'Central Warehouse',
        destination_branch VARCHAR(100) NOT NULL,
        expected_delivery_date DATE NULL,
        transport_method VARCHAR(50) DEFAULT 'Company Vehicle',
        courier_name VARCHAR(100) NULL,
        driver_info VARCHAR(150) NULL,
        total_products INT DEFAULT 0,
        status ENUM('Pending','Packed','In Transit','Delivered','Partially Received','Rejected') DEFAULT 'Pending',
        notes TEXT NULL,
        dispatched_by INT NOT NULL,
        dispatched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        received_at DATETIME NULL,
        received_by INT NULL,
        discrepancy_reason TEXT NULL,
        proof_image VARCHAR(255) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
$chkCol1 = mysqli_query($conn, "SHOW COLUMNS FROM warehouse_dispatches LIKE 'expected_delivery_date'");
if ($chkCol1 && mysqli_num_rows($chkCol1) == 0) {
    @mysqli_query($conn, "ALTER TABLE warehouse_dispatches ADD COLUMN expected_delivery_date DATE NULL");
}

$chkCol2 = mysqli_query($conn, "SHOW COLUMNS FROM warehouse_dispatches LIKE 'transport_method'");
if ($chkCol2 && mysqli_num_rows($chkCol2) == 0) {
    @mysqli_query($conn, "ALTER TABLE warehouse_dispatches ADD COLUMN transport_method VARCHAR(50) DEFAULT 'Company Vehicle'");
}

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS warehouse_dispatch_items (
        item_id INT AUTO_INCREMENT PRIMARY KEY,
        dispatch_id INT NOT NULL,
        product_id INT NOT NULL,
        expected_qty INT NOT NULL,
        received_qty INT DEFAULT 0,
        item_status ENUM('Pending','Received','Discrepancy','Missing') DEFAULT 'Pending'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/*=========================================================
    HELPER: VERIFY ADMIN / USER PASSWORD
==========================================================*/
function verifyDispatchPassword($conn, $user_id, $password) {
    if (empty($password)) return false;
    $user_id = (int)$user_id;
    if (isset($_SESSION['user_id'])) {
        $res = mysqli_query($conn, "SELECT password FROM users WHERE user_id = $user_id LIMIT 1");
        $row = mysqli_fetch_assoc($res);
        return ($row && !empty($row['password']) && password_verify($password, $row['password']));
    }
    if (isset($_SESSION['emp_id'])) {
        $res = mysqli_query($conn, "SELECT password, employee_no FROM employees WHERE employee_id = $user_id LIMIT 1");
        $row = mysqli_fetch_assoc($res);
        if (!$row) return false;
        return ((!empty($row['password']) && password_verify($password, $row['password'])) || ($password === $row['employee_no']));
    }
    return false;
}

/*=========================================================
    ACTION: CREATE NEW DISPATCH (MULTIPLE PRODUCTS)
==========================================================*/
if (isset($_POST['action']) && $_POST['action'] === 'create_dispatch') {
    $password = $_POST['password'] ?? '';
    if (!verifyDispatchPassword($conn, $current_user, $password)) {
        ob_clean(); echo 'error: Incorrect password authorization.'; exit();
    }

    $dest_branch      = mysqli_real_escape_string($conn, trim($_POST['destination_branch'] ?? 'Main Storefront'));
    $expected_date    = mysqli_real_escape_string($conn, trim($_POST['expected_delivery_date'] ?? date('Y-m-d')));
    $transport_method = mysqli_real_escape_string($conn, trim($_POST['transport_method'] ?? 'Company Vehicle'));

    if ($transport_method === 'Company Vehicle') {
        $veh  = trim($_POST['vehicle_info'] ?? 'In-House Vehicle');
        $drv  = trim($_POST['driver_name'] ?? 'In-House Driver');
        $courier_name = 'Company Transport';
        $driver_info  = mysqli_real_escape_string($conn, $veh . ' | Driver: ' . $drv);
    } else {
        $courier_name = mysqli_real_escape_string($conn, trim($_POST['courier_company'] ?? 'Third-Party Courier'));
        $track        = trim($_POST['tracking_number'] ?? 'N/A');
        $driver_info  = mysqli_real_escape_string($conn, 'Waybill/Tracking #: ' . $track);
    }

    $notes        = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? ''));
    $status       = 'In Transit'; // System automated shipment status
    
    $product_ids  = $_POST['product_ids'] ?? [];
    $quantities   = $_POST['quantities'] ?? [];

    if (empty($product_ids) || count($product_ids) === 0) {
        ob_clean(); echo 'error: Please add at least one product to dispatch.'; exit();
    }

    $dispatch_code = 'DSP-' . date('Ymd') . '-' . rand(1000, 9999);
    $total_items = 0;

    for ($i = 0; $i < count($product_ids); $i++) {
        $pid = (int)$product_ids[$i];
        $qty = (int)($quantities[$i] ?? 0);
        if ($pid > 0 && $qty > 0) {
            $total_items++;
        }
    }

    if ($total_items === 0) {
        ob_clean(); echo 'error: Product quantities must be greater than 0.'; exit();
    }

    // Insert Dispatch Header
    $query = mysqli_query($conn, "
        INSERT INTO warehouse_dispatches 
        (dispatch_code, source_warehouse, destination_branch, expected_delivery_date, transport_method, courier_name, driver_info, total_products, status, notes, dispatched_by, dispatched_at)
        VALUES ('$dispatch_code', 'Central Warehouse', '$dest_branch', '$expected_date', '$transport_method', '$courier_name', '$driver_info', $total_items, '$status', '$notes', $current_user, NOW())
    ");

    if (!$query) {
        ob_clean(); echo 'error: Failed to create dispatch record. ' . mysqli_error($conn); exit();
    }

    $dispatch_id = mysqli_insert_id($conn);

    // Insert Line Items & Deduct Warehouse Stock if In Transit / Packed
    for ($i = 0; $i < count($product_ids); $i++) {
        $pid = (int)$product_ids[$i];
        $qty = (int)($quantities[$i] ?? 0);

        if ($pid > 0 && $qty > 0) {
            mysqli_query($conn, "
                INSERT INTO warehouse_dispatch_items (dispatch_id, product_id, expected_qty, received_qty, item_status)
                VALUES ($dispatch_id, $pid, $qty, 0, 'Pending')
            ");

            // If confirmed as In Transit / Packed, deduct from Central Warehouse Inventory
            if (in_array($status, ['In Transit', 'Packed'])) {
                $invRes = mysqli_query($conn, "SELECT inventory_id, quantity FROM inventory WHERE product_id = $pid LIMIT 1");
                $inv = mysqli_fetch_assoc($invRes);
                if ($inv) {
                    $newQty = max(0, (int)$inv['quantity'] - $qty);
                    $invId  = (int)$inv['inventory_id'];
                    mysqli_query($conn, "UPDATE inventory SET quantity = $newQty WHERE inventory_id = $invId");
                    mysqli_query($conn, "INSERT INTO stock_movements (inventory_id, type, quantity, reference_no, notes, moved_by, moved_at) VALUES ($invId, 'Transfer Out', $qty, '$dispatch_code', 'Warehouse Dispatch to $dest_branch', $current_user, NOW())");
                }
            }
        }
    }

    logAction($conn, 1, 'Create Warehouse Dispatch', 'warehouse_dispatches', $dispatch_id, 
        "Created dispatch {$dispatch_code} to {$dest_branch} with {$total_items} items by {$user_name}");
    
    mysqli_query($conn, "
        INSERT INTO notifications (title, message, type, is_read)
        VALUES ('Warehouse Dispatch Sent', 'Shipment {$dispatch_code} ({$total_items} items) created for {$dest_branch}.', 'System', 0)
    ");

    ob_clean(); echo 'success'; exit();
}

/*=========================================================
    ACTION: UPDATE SHIPMENT STATUS
==========================================================*/
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $dispatch_id = (int)$_POST['dispatch_id'];
    $new_status  = mysqli_real_escape_string($conn, trim($_POST['status']));

    $disp = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM warehouse_dispatches WHERE dispatch_id = $dispatch_id LIMIT 1"));
    if (!$disp) { ob_clean(); echo 'error: Dispatch record not found.'; exit(); }

    $old_status = $disp['status'];
    mysqli_query($conn, "UPDATE warehouse_dispatches SET status = '$new_status' WHERE dispatch_id = $dispatch_id");

    // Deduct stock if transitioning into 'In Transit' from 'Pending'
    if ($new_status === 'In Transit' && $old_status === 'Pending') {
        $itemsRes = mysqli_query($conn, "SELECT * FROM warehouse_dispatch_items WHERE dispatch_id = $dispatch_id");
        while ($item = mysqli_fetch_assoc($itemsRes)) {
            $pid = (int)$item['product_id'];
            $qty = (int)$item['expected_qty'];
            $invRes = mysqli_query($conn, "SELECT inventory_id, quantity FROM inventory WHERE product_id = $pid LIMIT 1");
            $inv = mysqli_fetch_assoc($invRes);
            if ($inv) {
                $newQty = max(0, (int)$inv['quantity'] - $qty);
                $invId  = (int)$inv['inventory_id'];
                mysqli_query($conn, "UPDATE inventory SET quantity = $newQty WHERE inventory_id = $invId");
                mysqli_query($conn, "INSERT INTO stock_movements (inventory_id, type, quantity, reference_no, notes, moved_by, moved_at) VALUES ($invId, 'Transfer Out', $qty, '{$disp['dispatch_code']}', 'Warehouse Dispatch to {$disp['destination_branch']}', $current_user, NOW())");
            }
        }
    }

    logAction($conn, 1, 'Update Dispatch Status', 'warehouse_dispatches', $dispatch_id, 
        "Updated dispatch {$disp['dispatch_code']} status to {$new_status} by {$user_name}");
    
    ob_clean(); echo 'success'; exit();
}

/*=========================================================
    FETCH DISPATCHES & PRODUCTS
==========================================================*/
$dispatches = mysqli_query($conn, "
    SELECT d.*, e.full_name AS clerk_name
    FROM warehouse_dispatches d
    LEFT JOIN employees e ON d.dispatched_by = e.employee_id
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

$driverEmployees = mysqli_query($conn, "
    SELECT e.employee_id, e.full_name, p.position_name, d.department_name
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.position_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE e.status = 'Active' OR e.status IS NULL
    ORDER BY e.full_name ASC
");
$driverList = [];
if ($driverEmployees) {
    while ($e = mysqli_fetch_assoc($driverEmployees)) $driverList[] = $e;
}

// Summary Metrics
$totalDispatches = count($dispatchList);
$pendingCount    = 0;
$inTransitCount  = 0;
$deliveredCount  = 0;

foreach ($dispatchList as $d) {
    if (in_array($d['status'], ['Pending', 'Packed'])) $pendingCount++;
    if ($d['status'] === 'In Transit')                 $inTransitCount++;
    if (in_array($d['status'], ['Delivered', 'Received'])) $deliveredCount++;
}
?>

<style>
.wh-card {
    background: white; border-radius: 14px; padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.05); height: 100%;
}
.badge-pending { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; font-weight: 600; }
.badge-transit { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 600; }
.badge-deliv   { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; font-weight: 600; }
.badge-reject  { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; font-weight: 600; }
.badge-partial { background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; font-weight: 600; }

.item-row { background: #f8fafc; border-radius: 8px; padding: 10px; margin-bottom: 8px; }
</style>

<?php
$isEmployeeSession = isset($_SESSION['is_work_session']) || (isset($_SESSION['role']) && $_SESSION['role'] === 'Inventory Employee');
$isWarehousePortal = !$isEmployeeSession;
?>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color:#0f4c81;">
            <i class="bi bi-box-seam-fill me-2 text-primary"></i>Warehouse Dispatches (Shipping Side)
        </h4>
        <p class="text-muted mb-0" style="font-size:13px;">Prepare, package, and send stock dispatches from central warehouse to store branches.</p>
    </div>
    <?php if ($isWarehousePortal): ?>
    <button class="btn btn-primary" onclick="openNewDispatchModal()" style="border-radius:10px;font-weight:600;">
        <i class="bi bi-plus-lg me-1"></i> Create New Dispatch
    </button>
    <?php endif; ?>
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
            <small class="text-muted fw-semibold">Pending / Packing</small>
            <h3 class="fw-bold mb-0 text-warning"><?= $pendingCount; ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="wh-card">
            <small class="text-muted fw-semibold">In Transit</small>
            <h3 class="fw-bold mb-0 text-info"><?= $inTransitCount; ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="wh-card">
            <small class="text-muted fw-semibold">Delivered / Received</small>
            <h3 class="fw-bold mb-0 text-success"><?= $deliveredCount; ?></h3>
        </div>
    </div>
</div>

<!-- DISPATCHES TABLE -->
<div class="wh-card">
    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-table me-2 text-primary"></i>Warehouse Dispatch Log</h6>
    <div class="table-responsive">
        <table class="table table-hover align-middle datatable w-100" id="dispatchesTable">
            <thead class="table-light" style="font-size:12px;text-transform:uppercase;">
                <tr>
                    <th>Dispatch ID</th>
                    <th>Destination Branch</th>
                    <th>Total Products</th>
                    <th>Dispatch Date</th>
                    <th>Shipment Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dispatchList as $d): ?>
                <tr>
                    <td class="fw-bold text-primary" style="font-size:13px;"><?= htmlspecialchars($d['dispatch_code']); ?></td>
                    <td><span class="badge bg-secondary-subtle text-dark border fw-semibold"><?= htmlspecialchars($d['destination_branch']); ?></span></td>
                    <td class="fw-bold"><?= $d['total_products']; ?> Items</td>
                    <td style="font-size:12px;"><?= date('M d, Y h:i A', strtotime($d['dispatched_at'])); ?></td>
                    <td>
                        <?php 
                        $st = $d['status'];
                        $bClass = 'badge-pending';
                        if ($st === 'In Transit')        $bClass = 'badge-transit';
                        if (in_array($st, ['Delivered', 'Received'])) $bClass = 'badge-deliv';
                        if ($st === 'Partially Received') $bClass = 'badge-partial';
                        if ($st === 'Rejected')          $bClass = 'badge-reject';
                        ?>
                        <span class="badge <?= $bClass; ?>"><?= $st; ?></span>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="viewDispatchDetails(<?= $d['dispatch_id']; ?>)">
                            <i class="bi bi-eye-fill me-1"></i>View
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="printDeliveryManifest(<?= $d['dispatch_id']; ?>)">
                            <i class="bi bi-printer-fill me-1"></i>Manifest
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- CREATE DISPATCH MODAL -->
<div class="modal fade" id="dispatchModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px; border:none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); overflow:hidden;">
            
            <!-- MODAL HEADER -->
            <div class="modal-header text-white py-3 px-4" style="background: linear-gradient(135deg, #0f4c81 0%, #1e5894 100%);">
                <div>
                    <h5 class="modal-title fw-bold mb-0 d-flex align-items-center">
                        <i class="bi bi-truck-front-fill me-2 fs-4"></i>Create Warehouse Dispatch
                    </h5>
                    <small style="opacity:0.85; font-size:12px;">WMS Shipping & Transport Dispatch Form</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <!-- MODAL BODY -->
            <div class="modal-body p-4" style="background:#f8fafc;">
                <form id="dispatchForm">
                    
                    <!-- SYSTEM AUTOMATED INFO BADGE -->
                    <div class="alert alert-info d-flex align-items-center mb-4 py-2 px-3" style="border-radius:10px; font-size:12px; border:1px solid #bae6fd; background:#f0f9ff;">
                        <i class="bi bi-robot fs-5 text-primary me-2"></i>
                        <div>
                            <strong>System Automated Fields:</strong> Dispatch ID (Auto-generated), Origin Warehouse (<strong>Central Warehouse</strong>), Dispatched By, and Timestamp will be logged automatically upon authorization.
                        </div>
                    </div>

                    <!-- SECTION 1: DESTINATION & SCHEDULE -->
                    <div class="card border-0 shadow-sm mb-3" style="border-radius:12px;">
                        <div class="card-header bg-white border-0 pt-3 px-3 pb-0">
                            <h6 class="fw-bold mb-0 d-flex align-items-center" style="font-size:13px; color:#0f4c81;">
                                <i class="bi bi-geo-alt-fill text-primary me-2"></i>1. Destination & Delivery Schedule
                            </h6>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-secondary" style="font-size:11px; text-transform:uppercase;">Destination Branch <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="destination_branch" name="destination_branch" required style="border-radius:8px;">
                                        <option value="">-- Select Destination Branch --</option>
                                        <option value="Main Storefront">Main Storefront</option>
                                        <option value="Branch #1 - North">Branch #1 - North</option>
                                        <option value="Branch #2 - South">Branch #2 - South</option>
                                        <option value="Retail Outlet A">Retail Outlet A</option>
                                        <option value="Retail Outlet B">Retail Outlet B</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-secondary" style="font-size:11px; text-transform:uppercase;">Expected Delivery Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-sm" id="expected_delivery_date" name="expected_delivery_date" value="<?= date('Y-m-d'); ?>" min="<?= date('Y-m-d'); ?>" required style="border-radius:8px;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: TRANSPORT METHOD -->
                    <div class="card border-0 shadow-sm mb-3" style="border-radius:12px;">
                        <div class="card-header bg-white border-0 pt-3 px-3 pb-0">
                            <h6 class="fw-bold mb-0 d-flex align-items-center" style="font-size:13px; color:#0f4c81;">
                                <i class="bi bi-box-seam text-primary me-2"></i>2. Transport Method & Logistics
                            </h6>
                        </div>
                        <div class="card-body p-3">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary d-block" style="font-size:11px; text-transform:uppercase;">Select Transport Type <span class="text-danger">*</span></label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="transport_method" id="transport_company" value="Company Vehicle" checked onclick="toggleTransportFields('company')">
                                    <label class="btn btn-outline-primary btn-sm py-2 fw-semibold" for="transport_company" style="border-radius:8px 0 0 8px;">
                                        <i class="bi bi-truck me-1"></i> Company Vehicle
                                    </label>

                                    <input type="radio" class="btn-check" name="transport_method" id="transport_courier" value="Third-Party Courier" onclick="toggleTransportFields('courier')">
                                    <label class="btn btn-outline-primary btn-sm py-2 fw-semibold" for="transport_courier" style="border-radius:0 8px 8px 0;">
                                        <i class="bi bi-box2-fill me-1"></i> Third-Party Courier
                                    </label>
                                </div>
                            </div>

                            <!-- CONDITIONAL: COMPANY VEHICLE -->
                            <div id="companyVehicleFields" class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-secondary" style="font-size:11px; text-transform:uppercase;">Vehicle Details <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="vehicle_info" name="vehicle_info" placeholder="e.g. L300 Van (Plate: NBO-8829)" style="border-radius:8px;">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-secondary" style="font-size:11px; text-transform:uppercase;">Assigned Driver (HRMS Staff) <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="driver_name" name="driver_name" style="border-radius:8px;">
                                        <option value="">-- Select Driver from HRMS --</option>
                                        <?php foreach ($driverList as $emp): ?>
                                            <option value="<?= htmlspecialchars($emp['full_name']); ?>">
                                                <?= htmlspecialchars($emp['full_name']); ?> <?= !empty($emp['position_name']) ? '('.htmlspecialchars($emp['position_name']).')' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- CONDITIONAL: THIRD PARTY COURIER -->
                            <div id="courierFields" class="row g-3" style="display:none;">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-secondary" style="font-size:11px; text-transform:uppercase;">Courier Company Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="courier_company" name="courier_company" placeholder="e.g. Lalamove / Transportify / Grab" style="border-radius:8px;">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-secondary" style="font-size:11px; text-transform:uppercase;">Waybill / Tracking Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="tracking_number" name="tracking_number" placeholder="e.g. LLM-892301928" style="border-radius:8px;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: DISPATCH NOTES -->
                    <div class="card border-0 shadow-sm mb-3" style="border-radius:12px;">
                        <div class="card-body p-3">
                            <label class="form-label fw-bold text-secondary" style="font-size:11px; text-transform:uppercase;">3. Dispatch Notes & Instructions <span class="text-muted">(Optional)</span></label>
                            <textarea class="form-control form-control-sm" name="notes" rows="2" placeholder="Enter fragile handling instructions, gate pass notes, or delivery remarks..." style="border-radius:8px;"></textarea>
                        </div>
                    </div>

                    <!-- SECTION 4: DISPATCH LINE ITEMS -->
                    <div class="card border-0 shadow-sm mb-3" style="border-radius:12px;">
                        <div class="card-header bg-white border-0 pt-3 px-3 pb-0 d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0 d-flex align-items-center" style="font-size:13px; color:#0f4c81;">
                                <i class="bi bi-list-check text-primary me-2"></i>4. Dispatch Line Items
                            </h6>
                            <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" onclick="addProductRow()" style="border-radius:8px;">
                                <i class="bi bi-plus-circle me-1"></i> Add Product
                            </button>
                        </div>
                        <div class="card-body p-3">
                            <div id="productRowsContainer">
                                <!-- Dynamic Rows -->
                            </div>
                        </div>
                    </div>

                    <!-- AUTO-GENERATED SUMMARY CARD -->
                    <div class="card border-0 text-white p-3 mb-2" style="border-radius:12px; background: linear-gradient(135deg, #0f4c81 0%, #1e5894 100%);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-white-50 text-uppercase fw-bold" style="font-size:10px;">Dispatch Manifest Summary</small>
                                <div class="d-flex align-items-center gap-3 mt-1" style="font-size:13px;">
                                    <span><i class="bi bi-boxes me-1"></i> <strong id="summaryProductsCount">0</strong> Products</span>
                                    <span>•</span>
                                    <span><i class="bi bi-stack me-1"></i> <strong id="summaryItemsCount">0</strong> Total Units / Items</span>
                                </div>
                            </div>
                            <div>
                                <span class="badge bg-white text-primary fw-bold px-3 py-2" style="border-radius:8px; font-size:11px;">Status: In Transit</span>
                            </div>
                        </div>
                    </div>

                </form>
            </div>

            <!-- MODAL FOOTER -->
            <div class="modal-footer bg-white border-0 px-4 py-3">
                <button type="button" class="btn btn-light border px-4 fw-semibold" data-bs-dismiss="modal" style="border-radius:8px;">Cancel</button>
                <button type="button" class="btn btn-primary px-4 fw-semibold" onclick="submitDispatchForm()" style="border-radius:8px; background:#0f4c81; border:none;">
                    <i class="bi bi-send-check-fill me-1"></i> Dispatch Shipment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- VIEW DETAILS MODAL -->
<div class="modal fade" id="viewDispatchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:12px;">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="viewModalTitle"><i class="bi bi-file-text-fill me-2"></i>Dispatch Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewDispatchBody" style="padding:24px;">
                <!-- Loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
const availableProducts = <?= json_encode($productList); ?>;

function openNewDispatchModal() {
    $('#dispatchForm')[0].reset();
    $('#productRowsContainer').empty();
    toggleTransportFields('company');
    addProductRow();
    updateDispatchSummary();
    new bootstrap.Modal(document.getElementById('dispatchModal')).show();
}

function toggleTransportFields(type) {
    if (type === 'company') {
        $('#companyVehicleFields').show();
        $('#courierFields').hide();
        $('#vehicle_info').prop('required', true);
        $('#driver_name').prop('required', true);
        $('#courier_company').prop('required', false);
        $('#tracking_number').prop('required', false);
    } else {
        $('#companyVehicleFields').hide();
        $('#courierFields').show();
        $('#vehicle_info').prop('required', false);
        $('#driver_name').prop('required', false);
        $('#courier_company').prop('required', true);
        $('#tracking_number').prop('required', true);
    }
}

function addProductRow() {
    const rowId = 'prow_' + Date.now() + '_' + Math.floor(Math.random()*100);
    let optionsHtml = '<option value="">-- Select Product --</option>';
    availableProducts.forEach(p => {
        optionsHtml += `<option value="${p.product_id}" data-stock="${p.stock_qty}">${p.product_name} (Warehouse Available: ${p.stock_qty})</option>`;
    });

    const html = `
        <div class="item-row d-flex align-items-center gap-2 p-2 mb-2 bg-light border rounded" id="${rowId}">
            <div class="flex-grow-1">
                <select class="form-select form-select-sm product-select" name="product_ids[]" onchange="updateDispatchSummary()" required style="border-radius:6px;">
                    ${optionsHtml}
                </select>
            </div>
            <div style="width: 130px;">
                <input type="number" class="form-control form-control-sm qty-input" name="quantities[]" min="1" value="1" placeholder="Qty" oninput="updateDispatchSummary()" required style="border-radius:6px;">
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="$('#${rowId}').remove(); updateDispatchSummary();" style="border-radius:6px;">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    $('#productRowsContainer').append(html);
    updateDispatchSummary();
}

function updateDispatchSummary() {
    let productsCount = 0;
    let itemsCount = 0;

    $('.item-row').each(function() {
        const pId = $(this).find('.product-select').val();
        const qty = parseInt($(this).find('.qty-input').val()) || 0;
        if (pId) {
            productsCount++;
            itemsCount += qty;
        }
    });

    $('#summaryProductsCount').text(productsCount);
    $('#summaryItemsCount').text(itemsCount);
}

function submitDispatchForm() {
    const branch = $('#destination_branch').val();
    if (!branch) {
        Swal.fire('Missing Information', 'Please select a destination branch.', 'warning');
        return;
    }

    const expDate = $('#expected_delivery_date').val();
    if (!expDate) {
        Swal.fire('Missing Information', 'Please select expected delivery date.', 'warning');
        return;
    }

    const transportType = $('input[name="transport_method"]:checked').val();
    if (transportType === 'Company Vehicle') {
        if (!$('#vehicle_info').val().trim() || !$('#driver_name').val().trim()) {
            Swal.fire('Missing Information', 'Please enter vehicle details and driver name.', 'warning');
            return;
        }
    } else {
        if (!$('#courier_company').val().trim() || !$('#tracking_number').val().trim()) {
            Swal.fire('Missing Information', 'Please enter courier company name and tracking number.', 'warning');
            return;
        }
    }

    let hasProduct = false;
    $('.item-row').each(function() {
        if ($(this).find('.product-select').val() && parseInt($(this).find('.qty-input').val()) > 0) {
            hasProduct = true;
        }
    });

    if (!hasProduct) {
        Swal.fire('Missing Line Items', 'Please add at least one product with quantity > 0.', 'warning');
        return;
    }

    Swal.fire({
        target: document.getElementById('dispatchModal'),
        title: 'Authorize Dispatch Password',
        html: 'Enter your password to authorize warehouse inventory deduction and dispatch.',
        input: 'password',
        inputPlaceholder: 'Password',
        inputAttributes: { autocapitalize: 'off', autocorrect: 'off', autocomplete: 'new-password' },
        didOpen: () => {
            const input = Swal.getInput();
            if (input) { input.value = ''; input.setAttribute('autocomplete', 'new-password'); }
        },
        showCancelButton: true,
        confirmButtonColor: '#0f4c81',
        confirmButtonText: 'Authorize & Dispatch'
    }).then(result => {
        if (!result.isConfirmed) return;

        const formData = $('#dispatchForm').serialize() + '&action=create_dispatch&password=' + encodeURIComponent(result.value);
        $.post('warehouse/warehouse_dispatches.php', formData, function(res) {
            res = res.trim();
            if (res === 'success') {
                Swal.fire({ icon:'success', title:'Dispatch Created!', text:'Warehouse dispatch has been authorized and stock deducted.', timer:1800, showConfirmButton:false })
                .then(() => {
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open');
                    loadPage('warehouse/warehouse_dispatches.php');
                });
            } else {
                Swal.fire('Error', res.replace(/^error:\s*/i, ''), 'error');
            }
        });
    });
}

function viewDispatchDetails(dispatchId) {
    $.get('warehouse/get_dispatch_details.php?dispatch_id=' + dispatchId, function(html) {
        $('#viewDispatchBody').html(html);
        new bootstrap.Modal(document.getElementById('viewDispatchModal')).show();
    });
}

function printDeliveryManifest(dispatchId) {
    window.open('warehouse/print_manifest.php?dispatch_id=' + dispatchId, '_blank', 'width=850,height=900');
}
</script>
