<?php
require_once '../../Model/database.php';
require_once '../../Model/logger.php';

$current_user = $_SESSION['user_id'] ?? $_SESSION['emp_id'] ?? 1;
$user_name    = $_SESSION['full_name'] ?? $_SESSION['emp_name'] ?? 'Warehouse Clerk';

$portalParam = $_GET['portal'] ?? '';
$referer     = $_SERVER['HTTP_REFERER'] ?? '';
$requestUri  = $_SERVER['REQUEST_URI'] ?? '';

// Check if explicitly coming from Warehouse portal vs Inventory portal
if ($portalParam === 'warehouse' || strpos($referer, 'admin_warehouse.php') !== false || strpos($requestUri, 'admin_warehouse.php') !== false) {
    $isWarehousePortal = true;
} else {
    $isWarehousePortal = false;
}

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

// Ensure all needed columns exist (migration safety)
$disp_cols = [
    'dispatch_code'          => "VARCHAR(50) NULL",
    'reference_no'           => "VARCHAR(50) NULL",
    'source_warehouse'       => "VARCHAR(100) DEFAULT 'Central Warehouse'",
    'destination_branch'     => "VARCHAR(100) NULL",
    'expected_delivery_date' => "DATE NULL",
    'transport_method'       => "VARCHAR(50) DEFAULT 'Company Vehicle'",
    'courier_name'           => "VARCHAR(100) NULL",
    'driver_info'            => "VARCHAR(150) NULL",
    'total_products'         => "INT DEFAULT 0",
    'status'                 => "VARCHAR(50) DEFAULT 'Pending'",
    'notes'                  => "TEXT NULL",
    'dispatched_by'          => "INT DEFAULT 1",
    'dispatched_at'          => "DATETIME DEFAULT CURRENT_TIMESTAMP",
    'received_at'            => "DATETIME NULL",
    'received_by'            => "INT NULL",
    'discrepancy_reason'     => "TEXT NULL",
    'proof_image'            => "VARCHAR(255) NULL",
    'request_id'             => "INT NULL"
];

foreach ($disp_cols as $col => $def) {
    $chk = mysqli_query($conn, "SHOW COLUMNS FROM warehouse_dispatches LIKE '$col'");
    if ($chk && mysqli_num_rows($chk) == 0) {
        @mysqli_query($conn, "ALTER TABLE warehouse_dispatches ADD COLUMN $col $def");
    }
}

@mysqli_query($conn, "ALTER TABLE warehouse_dispatches MODIFY COLUMN status VARCHAR(50) DEFAULT 'Pending'");
@mysqli_query($conn, "UPDATE warehouse_dispatches SET dispatch_code = reference_no WHERE (dispatch_code IS NULL OR dispatch_code = '') AND reference_no IS NOT NULL");
@mysqli_query($conn, "UPDATE warehouse_dispatches SET reference_no = dispatch_code WHERE (reference_no IS NULL OR reference_no = '') AND dispatch_code IS NOT NULL");
@mysqli_query($conn, "UPDATE warehouse_dispatches SET status = 'Pending' WHERE status IS NULL OR status = '' OR status = 'Scheduled'");

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
    $status       = 'Pending'; // Start as Pending — warehouse clerk must pack then dispatch

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

    // Insert Dispatch Header (starts as Pending)
    $query = mysqli_query($conn, "
        INSERT INTO warehouse_dispatches 
        (dispatch_code, source_warehouse, destination_branch, expected_delivery_date, transport_method, courier_name, driver_info, total_products, status, notes, dispatched_by, dispatched_at)
        VALUES ('$dispatch_code', 'Central Warehouse', '$dest_branch', '$expected_date', '$transport_method', '$courier_name', '$driver_info', $total_items, '$status', '$notes', $current_user, NOW())
    ");

    if (!$query) {
        ob_clean(); echo 'error: Failed to create dispatch record. ' . mysqli_error($conn); exit();
    }

    $dispatch_id = mysqli_insert_id($conn);

    // Insert Line Items (no stock deduction yet — deduction happens when dispatched/in-transit)
    for ($i = 0; $i < count($product_ids); $i++) {
        $pid = (int)$product_ids[$i];
        $qty = (int)($quantities[$i] ?? 0);

        if ($pid > 0 && $qty > 0) {
            mysqli_query($conn, "
                INSERT INTO warehouse_dispatch_items (dispatch_id, product_id, expected_qty, received_qty, item_status)
                VALUES ($dispatch_id, $pid, $qty, 0, 'Pending')
            ");
        }
    }

    logAction($conn, 1, 'Create Warehouse Dispatch', 'warehouse_dispatches', $dispatch_id, 
        "Created dispatch {$dispatch_code} to {$dest_branch} with {$total_items} items by {$user_name}");
    
    mysqli_query($conn, "
        INSERT INTO notifications (title, message, type, is_read)
        VALUES ('Warehouse Dispatch Created', 'Dispatch {$dispatch_code} ({$total_items} items) created for {$dest_branch}. Awaiting packing.', 'System', 0)
    ");

    ob_clean(); echo 'success'; exit();
}

/*=========================================================
    ACTION: MARK AS PACKED
==========================================================*/
if (isset($_POST['action']) && $_POST['action'] === 'mark_packed') {
    $dispatch_id = (int)$_POST['dispatch_id'];

    $disp = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM warehouse_dispatches WHERE dispatch_id = $dispatch_id LIMIT 1"));
    if (!$disp) { ob_clean(); echo 'error: Dispatch record not found.'; exit(); }

    $old_status = trim($disp['status'] ?? '');
    if (empty($old_status) || $old_status === 'Scheduled') {
        $old_status = 'Pending';
    }

    if ($old_status !== 'Pending') {
        ob_clean(); echo 'error: Only Pending dispatches can be marked as Packed.'; exit();
    }

    mysqli_query($conn, "UPDATE warehouse_dispatches SET status = 'Packed' WHERE dispatch_id = $dispatch_id");

    logAction($conn, 1, 'Pack Dispatch', 'warehouse_dispatches', $dispatch_id, 
        "Dispatch {$disp['dispatch_code']} marked as Packed by {$user_name}");

    ob_clean(); echo 'success'; exit();
}

/*=========================================================
    ACTION: DISPATCH (MARK AS IN TRANSIT + DEDUCT STOCK)
==========================================================*/
if (isset($_POST['action']) && $_POST['action'] === 'dispatch_shipment') {
    $dispatch_id = (int)$_POST['dispatch_id'];

    $disp = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM warehouse_dispatches WHERE dispatch_id = $dispatch_id LIMIT 1"));
    if (!$disp) { ob_clean(); echo 'error: Dispatch record not found.'; exit(); }

    $old_status = trim($disp['status'] ?? '');
    if (empty($old_status) || $old_status === 'Scheduled') {
        $old_status = 'Pending';
    }
    if (!in_array($old_status, ['Packed', 'Pending'])) {
        ob_clean(); echo 'error: Only Pending or Packed dispatches can be dispatched.'; exit();
    }

    // Update status to In Transit
    mysqli_query($conn, "UPDATE warehouse_dispatches SET status = 'In Transit' WHERE dispatch_id = $dispatch_id");

    // Deduct stock from Central Warehouse Inventory
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

    logAction($conn, 1, 'Dispatch Shipment', 'warehouse_dispatches', $dispatch_id, 
        "Dispatch {$disp['dispatch_code']} sent In Transit to {$disp['destination_branch']} by {$user_name}");

    mysqli_query($conn, "
        INSERT INTO notifications (title, message, type, is_read)
        VALUES ('Shipment Dispatched', 'Dispatch {$disp['dispatch_code']} is now In Transit to {$disp['destination_branch']}.', 'System', 0)
    ");

    ob_clean(); echo 'success'; exit();
}

/*=========================================================
    AJAX: GET DISPATCH DETAILS FOR VIEW MODAL
==========================================================*/
if (isset($_GET['action']) && $_GET['action'] === 'get_details') {
    $dispatch_id = (int)$_GET['dispatch_id'];

    $disp = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT d.*, e.full_name AS prepared_by_name,
               tr.request_code AS transfer_request_code
        FROM warehouse_dispatches d
        LEFT JOIN employees e ON d.dispatched_by = e.employee_id
        LEFT JOIN transfer_requests tr ON d.request_id = tr.request_id
        WHERE d.dispatch_id = $dispatch_id LIMIT 1
    "));

    if (!$disp) {
        // Fallback: try with users table if not employee
        $disp = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT d.*, u.full_name AS prepared_by_name,
                   tr.request_code AS transfer_request_code
            FROM warehouse_dispatches d
            LEFT JOIN users u ON d.dispatched_by = u.user_id
            LEFT JOIN transfer_requests tr ON d.request_id = tr.request_id
            WHERE d.dispatch_id = $dispatch_id LIMIT 1
        "));
    }

    if (!$disp) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Dispatch not found']);
        exit();
    }

    $items = mysqli_query($conn, "
        SELECT i.*, p.product_name, c.category_name
        FROM warehouse_dispatch_items i
        JOIN products p ON i.product_id = p.product_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE i.dispatch_id = $dispatch_id
    ");
    $itemList = [];
    if ($items) {
        while ($r = mysqli_fetch_assoc($items)) $itemList[] = $r;
    }

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['dispatch' => $disp, 'items' => $itemList]);
    exit();
}

/*=========================================================
    FETCH DISPATCHES & PRODUCTS
==========================================================*/
$dispatches = mysqli_query($conn, "
    SELECT d.*, e.full_name AS clerk_name,
           tr.request_code AS transfer_request_code
    FROM warehouse_dispatches d
    LEFT JOIN employees e ON d.dispatched_by = e.employee_id
    LEFT JOIN transfer_requests tr ON d.request_id = tr.request_id
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

// Summary Metrics — focused on preparation stages only
$totalDispatches = count($dispatchList);
$pendingCount    = 0;
$packedCount     = 0;
$dispatchedCount = 0;

foreach ($dispatchList as $d) {
    $st = trim($d['status'] ?? '');
    if (empty($st)) $st = 'Pending';
    if ($st === 'Pending')    $pendingCount++;
    if ($st === 'Packed')     $packedCount++;
    if ($st === 'In Transit') $dispatchedCount++;
}
?>

<style>
.wh-card {
    background: white; border-radius: 14px; padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.05); height: 100%;
}
.badge-pending { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; font-weight: 600; }
.badge-packed  { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 600; }
.badge-sent    { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; font-weight: 600; }
.badge-other   { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; font-weight: 600; }
.item-row { background: #f8fafc; border-radius: 8px; padding: 10px; margin-bottom: 8px; }

/* View Details Panel */
.detail-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 700; margin-bottom: 2px; }
.detail-value { font-size: 14px; font-weight: 600; color: #0f172a; }
</style>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color:#0f4c81;">
            <i class="bi bi-box-seam-fill me-2 text-primary"></i>Warehouse Dispatches
        </h4>
        <p class="text-muted mb-0" style="font-size:13px;">Prepare and release approved stock transfers for delivery to branches.</p>
    </div>
    <?php if ($isWarehousePortal): ?>
    <button class="btn btn-primary fw-semibold px-3" onclick="openNewDispatchModal()" style="border-radius:10px;">
        <i class="bi bi-plus-circle-fill me-1"></i> New Dispatch
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
            <small class="text-muted fw-semibold">Pending Preparation</small>
            <h3 class="fw-bold mb-0" style="color:#b45309;"><?= $pendingCount; ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="wh-card">
            <small class="text-muted fw-semibold">Packed & Ready</small>
            <h3 class="fw-bold mb-0 text-info"><?= $packedCount; ?></h3>
        </div>
    </div>
    <div class="col-md-3">
        <div class="wh-card">
            <small class="text-muted fw-semibold">Dispatched (Sent Out)</small>
            <h3 class="fw-bold mb-0 text-success"><?= $dispatchedCount; ?></h3>
        </div>
    </div>
</div>

<!-- DISPATCHES TABLE -->
<div class="wh-card">
    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-table me-2 text-primary"></i>Dispatch Preparation Log</h6>
    <div class="table-responsive">
        <table class="table table-hover align-middle datatable w-100" id="dispatchesTable">
            <thead class="table-light" style="font-size:12px;text-transform:uppercase;">
                <tr>
                    <th>Dispatch ID</th>
                    <th>Destination Branch</th>
                    <th>Transfer Req.</th>
                    <th>Total Products</th>
                    <th>Date Created</th>
                    <th>Preparation Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dispatchList as $d): ?>
                <?php
                    $st = trim($d['status'] ?? '');
                    if (empty($st)) $st = 'Pending';
                    $bClass = 'badge-other';
                    if ($st === 'Pending')    $bClass = 'badge-pending';
                    if ($st === 'Packed')     $bClass = 'badge-packed';
                    if ($st === 'In Transit') $bClass = 'badge-sent';
                ?>
                <tr>
                    <td class="fw-bold text-primary" style="font-size:13px;"><?= htmlspecialchars($d['dispatch_code']); ?></td>
                    <td><span class="badge bg-secondary-subtle text-dark border fw-semibold"><?= htmlspecialchars($d['destination_branch']); ?></span></td>
                    <td style="font-size:12px;">
                        <?php if (!empty($d['transfer_request_code'])): ?>
                            <span class="text-info fw-semibold"><?= htmlspecialchars($d['transfer_request_code']); ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="fw-bold"><?= $d['total_products']; ?> Items</td>
                    <td style="font-size:12px;"><?= date('M d, Y h:i A', strtotime($d['dispatched_at'])); ?></td>
                    <td><span class="badge <?= $bClass; ?>"><?= htmlspecialchars($st); ?></span></td>
                    <td class="text-center">
                        <?php if ($isWarehousePortal): ?>
                            <?php if ($st === 'Pending'): ?>
                            <button class="btn btn-sm btn-info text-white me-1" onclick="markAsPacked(<?= $d['dispatch_id']; ?>, '<?= htmlspecialchars($d['dispatch_code']); ?>')" title="Mark as Packed">
                                <i class="bi bi-box2-fill me-1"></i>Pack
                            </button>
                            <?php endif; ?>
                            <?php if ($st === 'Packed'): ?>
                            <button class="btn btn-sm btn-success me-1" onclick="dispatchShipment(<?= $d['dispatch_id']; ?>, '<?= htmlspecialchars($d['dispatch_code']); ?>')" title="Send out / Dispatch">
                                <i class="bi bi-truck me-1"></i>Dispatch
                            </button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-secondary" onclick="viewDispatchDetails(<?= $d['dispatch_id']; ?>)">
                            <i class="bi bi-eye-fill me-1"></i>View
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- VIEW DISPATCH DETAILS MODAL -->
<div class="modal fade" id="viewDispatchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:14px; border:none; overflow:hidden;">
            <div class="modal-header text-white py-3 px-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
                <div>
                    <h5 class="modal-title fw-bold mb-0"><i class="bi bi-file-earmark-text-fill me-2"></i>Dispatch Details</h5>
                    <small style="opacity:0.7; font-size:11px;" id="viewModalSubtitle"></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="viewDispatchBody">
                <!-- Loaded dynamically -->
            </div>
            <div class="modal-footer border-0 bg-light px-4 py-3" id="viewDispatchFooter" style="display:none;">
                <!-- Action buttons injected dynamically -->
            </div>
        </div>
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
                    <small style="opacity:0.85; font-size:12px;">Prepare a new stock transfer dispatch for branch delivery</small>
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
                            <strong>System Automated Fields:</strong> Dispatch ID (Auto-generated), Origin Warehouse (<strong>Central Warehouse</strong>), Prepared By, and Timestamp will be logged automatically upon authorization.
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
                                <span class="badge bg-white text-primary fw-bold px-3 py-2" style="border-radius:8px; font-size:11px;">Status: Pending</span>
                            </div>
                        </div>
                    </div>

                </form>
            </div>

            <!-- MODAL FOOTER -->
            <div class="modal-footer bg-white border-0 px-4 py-3">
                <button type="button" class="btn btn-light border px-4 fw-semibold" data-bs-dismiss="modal" style="border-radius:8px;">Cancel</button>
                <button type="button" class="btn btn-primary px-4 fw-semibold" onclick="submitDispatchForm()" style="border-radius:8px; background:#0f4c81; border:none;">
                    <i class="bi bi-check-circle-fill me-1"></i> Create Dispatch
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const availableProducts = <?= json_encode($productList); ?>;

/* ============================================
    CREATE DISPATCH MODAL
============================================ */
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
        optionsHtml += `<option value="${p.product_id}" data-stock="${p.stock_qty}">${p.product_name} (Available: ${p.stock_qty})</option>`;
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
        title: 'Authorize Dispatch Creation',
        html: 'Enter your password to authorize this warehouse dispatch.',
        input: 'password',
        inputPlaceholder: 'Password',
        inputAttributes: { autocapitalize: 'off', autocorrect: 'off', autocomplete: 'new-password' },
        didOpen: () => {
            const input = Swal.getInput();
            if (input) { input.value = ''; input.setAttribute('autocomplete', 'new-password'); }
        },
        showCancelButton: true,
        confirmButtonColor: '#0f4c81',
        confirmButtonText: 'Authorize & Create'
    }).then(result => {
        if (!result.isConfirmed) return;

        const formData = $('#dispatchForm').serialize() + '&action=create_dispatch&password=' + encodeURIComponent(result.value);
        $.post('warehouse/warehouse_dispatches.php', formData, function(res) {
            res = res.trim();
            if (res === 'success') {
                Swal.fire({ icon:'success', title:'Dispatch Created!', text:'Dispatch has been created and is awaiting packing.', timer:1800, showConfirmButton:false })
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

/* ============================================
    MARK AS PACKED
============================================ */
function markAsPacked(dispatchId, dispatchCode) {
    Swal.fire({
        title: 'Mark as Packed?',
        html: `Confirm that dispatch <strong>${dispatchCode}</strong> has been fully packed and is ready for shipment.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0ea5e9',
        confirmButtonText: '<i class="bi bi-box2-fill me-1"></i> Yes, Mark as Packed'
    }).then(result => {
        if (!result.isConfirmed) return;
        $.post('warehouse/warehouse_dispatches.php', { action: 'mark_packed', dispatch_id: dispatchId }, function(res) {
            if (res.trim() === 'success') {
                Swal.fire({ icon:'success', title:'Packed!', text:'Dispatch is now ready for shipment.', timer:1500, showConfirmButton:false })
                .then(() => loadPage('warehouse/warehouse_dispatches.php'));
            } else {
                Swal.fire('Error', res.replace(/^error:\s*/i,''), 'error');
            }
        });
    });
}

/* ============================================
    DISPATCH (SEND OUT / IN TRANSIT)
============================================ */
function dispatchShipment(dispatchId, dispatchCode) {
    Swal.fire({
        title: 'Dispatch Shipment?',
        html: `This will send <strong>${dispatchCode}</strong> out for delivery and <strong>deduct stock</strong> from warehouse inventory.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        confirmButtonText: '<i class="bi bi-truck me-1"></i> Yes, Dispatch Now'
    }).then(result => {
        if (!result.isConfirmed) return;
        $.post('warehouse/warehouse_dispatches.php', { action: 'dispatch_shipment', dispatch_id: dispatchId }, function(res) {
            if (res.trim() === 'success') {
                Swal.fire({ icon:'success', title:'Dispatched!', text:'Shipment is now In Transit. Stock has been deducted.', timer:1800, showConfirmButton:false })
                .then(() => loadPage('warehouse/warehouse_dispatches.php'));
            } else {
                Swal.fire('Error', res.replace(/^error:\s*/i,''), 'error');
            }
        });
    });
}

/* ============================================
    VIEW DISPATCH DETAILS
============================================ */
function viewDispatchDetails(dispatchId) {
    $.getJSON('warehouse/warehouse_dispatches.php?action=get_details&dispatch_id=' + dispatchId, function(data) {
        if (data.error) {
            Swal.fire('Error', data.error, 'error');
            return;
        }

        const d = data.dispatch;
        const items = data.items;
        const st = (d.status || 'Pending').trim();

        // Status badge
        let stClass = 'bg-warning text-dark';
        if (st === 'Packed')     stClass = 'bg-info text-white';
        if (st === 'In Transit') stClass = 'bg-success text-white';
        if (st === 'Delivered' || st === 'Received') stClass = 'bg-success text-white';

        // Build products table
        let productsHtml = '';
        items.forEach(it => {
            productsHtml += `
                <tr>
                    <td class="fw-semibold">${it.product_name}</td>
                    <td><span class="badge bg-light text-dark border">${it.category_name || 'General'}</span></td>
                    <td class="text-center fw-bold text-primary">${it.expected_qty} units</td>
                </tr>
            `;
        });

        // Transfer Request ID display
        const trCode = d.transfer_request_code
            ? `<span class="fw-bold text-info">${d.transfer_request_code}</span>`
            : `<span class="text-muted">— Manual Dispatch —</span>`;

        // Transport details
        let transportHtml = '';
        if (d.transport_method === 'Company Vehicle' || d.transport_method === null) {
            transportHtml = `<span class="fw-semibold">${d.driver_info || 'Company Vehicle'}</span>`;
        } else {
            transportHtml = `<span class="fw-semibold">${d.courier_name || 'Third-Party'}</span> · <span class="text-muted">${d.driver_info || ''}</span>`;
        }

        const bodyHtml = `
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="detail-label">DISPATCH ID</div>
                    <div class="detail-value text-primary">${d.dispatch_code}</div>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="detail-label">DISPATCH STATUS</div>
                    <span class="badge ${stClass} fs-6 px-3 py-2">${st}</span>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="detail-label">DESTINATION BRANCH</div>
                    <div class="detail-value">${d.destination_branch}</div>
                </div>
                <div class="col-md-6">
                    <div class="detail-label">TRANSFER REQUEST ID</div>
                    <div>${trCode}</div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="detail-label">PREPARED BY</div>
                    <div class="detail-value">${d.prepared_by_name || 'Warehouse Staff'}</div>
                </div>
                <div class="col-md-4">
                    <div class="detail-label">DATE PREPARED</div>
                    <div class="detail-value" style="font-size:13px;">${new Date(d.dispatched_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true })}</div>
                </div>
                <div class="col-md-4">
                    <div class="detail-label">EXPECTED DELIVERY</div>
                    <div class="detail-value" style="font-size:13px;">${d.expected_delivery_date ? new Date(d.expected_delivery_date + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'}</div>
                </div>
            </div>

            <div class="mb-4">
                <div class="detail-label">TRANSPORT / LOGISTICS</div>
                <div class="p-2 bg-light rounded border" style="font-size:13px;">
                    <i class="bi bi-truck me-1 text-primary"></i> ${transportHtml}
                </div>
            </div>

            ${d.notes ? `
            <div class="mb-4">
                <div class="detail-label">DISPATCH NOTES</div>
                <div class="p-2 bg-light rounded border" style="font-size:13px;">${d.notes}</div>
            </div>
            ` : ''}

            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-list-check me-2 text-primary"></i>Products Manifest</h6>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light" style="font-size:11px; text-transform:uppercase;">
                        <tr>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th class="text-center">Dispatched Qty</th>
                        </tr>
                    </thead>
                    <tbody style="font-size:13px;">
                        ${productsHtml}
                    </tbody>
                </table>
            </div>
        `;

        $('#viewModalSubtitle').text(d.dispatch_code);
        $('#viewDispatchBody').html(bodyHtml);

        // Action buttons in footer (only for actionable statuses in Warehouse Portal)
        let footerHtml = '';
        const isWarehousePortal = <?= json_encode($isWarehousePortal); ?>;
        
        if (isWarehousePortal && st === 'Pending') {
            footerHtml = `
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info text-white fw-semibold" onclick="$('#viewDispatchModal').modal('hide'); markAsPacked(${d.dispatch_id}, '${d.dispatch_code}')">
                    <i class="bi bi-box2-fill me-1"></i> Mark as Packed
                </button>
            `;
        } else if (isWarehousePortal && st === 'Packed') {
            footerHtml = `
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success fw-semibold" onclick="$('#viewDispatchModal').modal('hide'); dispatchShipment(${d.dispatch_id}, '${d.dispatch_code}')">
                    <i class="bi bi-truck me-1"></i> Dispatch Shipment
                </button>
            `;
        } else {
            footerHtml = `<button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>`;
        }

        const $footer = $('#viewDispatchFooter');
        $footer.html(footerHtml).show();

        new bootstrap.Modal(document.getElementById('viewDispatchModal')).show();
    }).fail(function() {
        Swal.fire('Error', 'Failed to load dispatch details.', 'error');
    });
}
</script>
