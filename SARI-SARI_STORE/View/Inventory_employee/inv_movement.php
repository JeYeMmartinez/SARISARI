<?php
session_start();
require_once '../../Model/database.php';

// Filter params
$filterType    = isset($_GET['type'])    ? mysqli_real_escape_string($conn, $_GET['type']) : '';
$filterProduct = isset($_GET['product']) ? (int)$_GET['product'] : 0;
$dateFrom      = isset($_GET['date_from']) && $_GET['date_from'] ? mysqli_real_escape_string($conn, $_GET['date_from']) : '';
$dateTo        = isset($_GET['date_to'])   && $_GET['date_to']   ? mysqli_real_escape_string($conn, $_GET['date_to'])   : '';

$where = ["1=1"];
if ($filterType)    $where[] = "sm.type = '$filterType'";
if ($filterProduct) $where[] = "i.inventory_id = $filterProduct";
if ($dateFrom)      $where[] = "DATE(sm.moved_at) >= '$dateFrom'";
if ($dateTo)        $where[] = "DATE(sm.moved_at) <= '$dateTo'";
$whereStr = implode(' AND ', $where);

$movements = mysqli_query($conn, "
    SELECT sm.*, p.product_name, c.category_name, e.full_name AS emp_name
    FROM stock_movements sm
    JOIN inventory i ON sm.inventory_id = i.inventory_id
    JOIN products p ON i.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN employees e ON sm.moved_by = e.employee_id
    WHERE $whereStr
    ORDER BY sm.moved_at DESC
    LIMIT 500
");
$rows = [];
if ($movements) while ($r = mysqli_fetch_assoc($movements)) $rows[] = $r;

$items = mysqli_query($conn, "SELECT i.inventory_id, p.product_name FROM inventory i JOIN products p ON i.product_id=p.product_id WHERE p.deleted_at IS NULL ORDER BY p.product_name ASC");
$itemList = [];
while ($r = mysqli_fetch_assoc($items)) $itemList[] = $r;

$inCount   = count(array_filter($rows, fn($r) => in_array($r['type'], ['Stock In', 'Transfer In'])));
$outCount  = count(array_filter($rows, fn($r) => in_array($r['type'], ['Stock Out', 'Transfer Out'])));
$adjCount  = count(array_filter($rows, fn($r) => str_starts_with($r['type'], 'Adjustment')));
$trCount   = count(array_filter($rows, fn($r) => str_starts_with($r['type'], 'Transfer')));
?>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 style="font-size:20px;font-weight:700;color:#0f4c81;"><i class="bi bi-clock-history me-2 text-primary"></i>Stock Movement History</h2>
        <p class="text-muted" style="font-size:13px;margin:0;">Display stock movement history. Click a record to view its full details.</p>
    </div>
</div>

<div class="row g-3">
    <!-- FILTERS + TABLE -->
    <div class="col-lg-8">
        <!-- STATS ROW -->
        <div class="row g-2 mb-3">
            <?php
            $stats = [
                ['label'=>'Total Records', 'val'=>count($rows), 'color'=>'#0d6efd', 'bg'=>'#e7f0ff'],
                ['label'=>'Stock In',      'val'=>$inCount,     'color'=>'#198754', 'bg'=>'#d1fae5'],
                ['label'=>'Stock Out',     'val'=>$outCount,    'color'=>'#dc3545', 'bg'=>'#fee2e2'],
                ['label'=>'Adjustments',   'val'=>$adjCount,    'color'=>'#f59e0b', 'bg'=>'#fef3c7'],
            ];
            foreach ($stats as $s): ?>
            <div class="col-3">
                <div class="page-card text-center p-2" style="background:<?= $s['bg']; ?>;">
                    <div style="font-size:22px;font-weight:800;color:<?= $s['color']; ?>;"><?= $s['val']; ?></div>
                    <div style="font-size:10px;font-weight:700;color:<?= $s['color']; ?>;text-transform:uppercase;"><?= $s['label']; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- FILTERS -->
        <div class="page-card mb-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold" style="font-size:11px;">TYPE</label>
                    <select class="form-select form-select-sm" name="type">
                        <option value="">All Types</option>
                        <?php foreach (['Stock In','Stock Out','Transfer In','Transfer Out','Adjustment (+)','Adjustment (-)','Transfer Discrepancy'] as $t): ?>
                        <option <?= $filterType === $t ? 'selected' : ''; ?>><?= $t; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold" style="font-size:11px;">PRODUCT</label>
                    <select class="form-select form-select-sm" name="product">
                        <option value="">All Products</option>
                        <?php foreach ($itemList as $it): ?>
                        <option value="<?= $it['inventory_id']; ?>" <?= $filterProduct == $it['inventory_id'] ? 'selected' : ''; ?>><?= htmlspecialchars($it['product_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold" style="font-size:11px;">FROM DATE</label>
                    <input type="date" class="form-control form-control-sm" name="date_from" value="<?= htmlspecialchars($dateFrom); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold" style="font-size:11px;">TO DATE</label>
                    <input type="date" class="form-control form-control-sm" name="date_to" value="<?= htmlspecialchars($dateTo); ?>">
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                    <a href="inv_movement.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
                </div>
            </form>
        </div>

        <!-- TABLE -->
        <div class="page-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle datatable w-100" id="movementTable">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>Reference</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rows) > 0): $si = 1;
                        foreach ($rows as $m):
                            $isIn   = in_array($m['type'], ['Stock In', 'Transfer In']) || strpos($m['type'], '(+)') !== false;
                            $isOut  = in_array($m['type'], ['Stock Out', 'Transfer Out']) || strpos($m['type'], '(-)') !== false;
                            $isDisc = strpos($m['type'], 'Discrepancy') !== false;
                            if ($isDisc)    { $tb = '<span class="badge bg-dark">' . htmlspecialchars($m['type']) . '</span>'; $qs = 'text-muted'; $sign = ''; }
                            elseif ($isIn)  { $tb = '<span class="badge bg-success">' . htmlspecialchars($m['type']) . '</span>'; $qs = 'text-success'; $sign = '+'; }
                            elseif ($isOut) { $tb = '<span class="badge bg-danger">' . htmlspecialchars($m['type']) . '</span>';  $qs = 'text-danger'; $sign = '-'; }
                            else            { $tb = '<span class="badge bg-warning text-dark">' . htmlspecialchars($m['type']) . '</span>'; $qs = 'text-dark'; $sign = '~'; }
                        ?>
                        <tr style="cursor:pointer;" onclick="showMvDetail(<?= $m['movement_id']; ?>)" data-id="<?= $m['movement_id']; ?>">
                            <td class="text-muted"><?= $si++; ?></td>
                            <td style="font-size:12px;"><?= date('M d, Y', strtotime($m['moved_at'])); ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($m['product_name']); ?></td>
                            <td><?= $tb; ?></td>
                            <td class="fw-bold <?= $qs; ?>"><?= $sign . $m['quantity']; ?></td>
                            <td class="font-monospace text-muted" style="font-size:11px;"><?= htmlspecialchars($m['reference_no'] ?? '—'); ?></td>
                            <td style="font-size:12px;"><?= htmlspecialchars($m['emp_name'] ?? 'Staff'); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- DETAIL PANEL -->
    <div class="col-lg-4">
        <div id="mvPlaceholder" class="page-card text-center py-5 text-muted">
            <i class="bi bi-hand-index-thumb fs-2 d-block mb-2 text-secondary"></i>
            <div class="fw-semibold">Select a Movement Record</div>
            <div style="font-size:12px;">Click any row to view movement details</div>
        </div>
        <div id="mvDetailPanel" class="page-card" style="display:none;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-info-circle-fill me-2 text-primary"></i>Movement Details</h6>
                <button class="btn btn-sm btn-outline-secondary" onclick="closeMvDetail()"><i class="bi bi-x"></i> Close</button>
            </div>
            <div id="mvDetailBody"></div>
        </div>
    </div>
</div>

<script>
const mvRecords = <?= json_encode($rows); ?>;

function showMvDetail(id) {
    const rec = mvRecords.find(r => r.movement_id == id);
    if (!rec) return;
    document.getElementById('mvPlaceholder').style.display = 'none';
    document.getElementById('mvDetailPanel').style.display = 'block';
    document.querySelectorAll('#movementTable tbody tr').forEach(r => r.classList.remove('table-primary'));
    document.querySelector(`tr[data-id="${id}"]`)?.classList.add('table-primary');

    const isIn   = ['Stock In','Transfer In'].includes(rec.type) || rec.type.includes('(+)');
    const isOut  = ['Stock Out','Transfer Out'].includes(rec.type) || rec.type.includes('(-)');
    const isDisc = rec.type.includes('Discrepancy');
    let qtyHtml = `<span class="fw-bold fs-4 ${isIn ? 'text-success' : (isOut ? 'text-danger' : 'text-dark')}">${isIn?'+':isOut?'-':'~'}${rec.quantity}</span>`;

    document.getElementById('mvDetailBody').innerHTML = `
        <div class="row g-2 mb-2">
            <div class="col-6"><div class="text-muted" style="font-size:10px;">DATE &amp; TIME</div><div class="fw-semibold" style="font-size:12px;">${rec.moved_at}</div></div>
            <div class="col-6"><div class="text-muted" style="font-size:10px;">RECORDED BY</div><div class="fw-semibold" style="font-size:12px;">${rec.emp_name || 'Staff'}</div></div>
        </div>
        <div class="mb-2"><div class="text-muted" style="font-size:10px;">PRODUCT</div><div class="fw-bold">${rec.product_name}</div></div>
        <div class="mb-2"><div class="text-muted" style="font-size:10px;">CATEGORY</div><div>${rec.category_name || '—'}</div></div>
        <div class="row g-2 mb-2">
            <div class="col-6"><div class="text-muted" style="font-size:10px;">MOVEMENT TYPE</div><div>${rec.type}</div></div>
            <div class="col-6"><div class="text-muted" style="font-size:10px;">QUANTITY</div>${qtyHtml}</div>
        </div>
        <div class="mb-2"><div class="text-muted" style="font-size:10px;">REFERENCE NO.</div><div class="font-monospace" style="font-size:12px;">${rec.reference_no || '—'}</div></div>
        <div class="mb-2"><div class="text-muted" style="font-size:10px;">SUPPLIER / REASON</div><div style="font-size:12px;">${rec.supplier || rec.reason || '—'}</div></div>
        <div><div class="text-muted" style="font-size:10px;">NOTES</div><div style="font-size:12px;">${rec.notes || '—'}</div></div>
        ${isDisc ? `<div class="alert alert-danger mt-3 mb-0 py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>Discrepancy reported. Stock NOT adjusted.</div>` : ''}
        ${rec.evidence_file ? `<hr><div class="text-muted" style="font-size:10px;">EVIDENCE FILE</div><a href="../../uploads/stock_evidence/${rec.evidence_file}" target="_blank" class="btn btn-sm btn-outline-secondary mt-1 w-100"><i class="bi bi-paperclip me-1"></i>View Evidence</a>` : ''}
    `;
}

function closeMvDetail() {
    document.getElementById('mvDetailPanel').style.display = 'none';
    document.getElementById('mvPlaceholder').style.display = 'block';
    document.querySelectorAll('#movementTable tbody tr').forEach(r => r.classList.remove('table-primary'));
}
window.closeMvDetail = closeMvDetail;
</script>
