<?php
if(session_status() === PHP_SESSION_NONE){
    session_start();
}
require_once __DIR__ . '/../Model/database.php';

$user_id = $_SESSION['user_id'] ?? $_SESSION['emp_id'] ?? null;
if(!$user_id){
    echo 'unauthorized';
    exit();
}

$cashier_id = $user_id;

/*=========================================================
    AJAX: GET RECEIPT FOR A SPECIFIC SALE
==========================================================*/
if(isset($_GET['action']) && $_GET['action'] == 'get_receipt'){
    $sale_id = (int)$_GET['sale_id'];

    $user_role = $_SESSION['role'] ?? 'Cashier';
    $roleFilter = ($user_role === 'Admin') ? "1=1" : "s.cashier_id = $cashier_id";

    $sale = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT s.*, u.full_name AS cashier_name
        FROM sales s
        LEFT JOIN users u ON s.cashier_id = u.user_id
        WHERE s.sale_id = $sale_id AND $roleFilter
    "));

    if(!$sale){
        echo 'error: Sale not found.';
        exit();
    }

    $items = mysqli_query($conn,"
        SELECT si.*, p.product_name
        FROM sale_items si
        LEFT JOIN products p ON si.product_id = p.product_id
        WHERE si.sale_id = $sale_id
    ");

    $itemsHtml = '';
    if(mysqli_num_rows($items) > 0){
        while($item = mysqli_fetch_assoc($items)){
            $prodName = !empty($item['product_name']) ? $item['product_name'] : 'Product #' . $item['product_id'];
            $itemsHtml .= '<div style="display:flex;justify-content:space-between;margin-bottom:2px;">'
                . '<span>' . htmlspecialchars($prodName) . ' x' . $item['quantity'] . '</span>'
                . '<span>₱' . number_format($item['subtotal'], 2) . '</span>'
                . '</div>';
        }
    } else {
        $itemsHtml = '<div style="text-align:center;color:#888;font-style:italic;margin:6px 0;">Item details not recorded</div>';
    }

    $date = date("F j, Y", strtotime($sale['created_at']));
    $time = date("h:i A", strtotime($sale['created_at']));

    echo '
        <div style="text-align:center;margin-bottom:10px;">
            <strong style="font-size:16px;">🛒 O-CART!</strong><br>
            <small>' . $date . '</small><br>
            <small>' . $time . '</small><br>
            <small>Sale #' . $sale['sale_id'] . '</small>
        </div>
        <hr style="border-style:dashed;">
        ' . $itemsHtml . '
        <hr style="border-style:dashed;">
        <div style="display:flex;justify-content:space-between;">
            <strong>TOTAL</strong><strong>₱' . number_format($sale['total_amount'], 2) . '</strong>
        </div>
        <div style="display:flex;justify-content:space-between;">
            <span>Cash</span><span>₱' . number_format($sale['payment'], 2) . '</span>
        </div>
        <div style="display:flex;justify-content:space-between;">
            <span>Change</span><span>₱' . number_format($sale['change_amount'], 2) . '</span>
        </div>
        <hr style="border-style:dashed;">
        <div style="text-align:center;font-size:12px;">Thank you! 😊</div>
    ';
    exit();
}

/*=========================================================
    FETCH SALES
==========================================================*/
$user_role = $_SESSION['role'] ?? 'Cashier';
$whereClause = ($user_role === 'Admin') ? "1=1" : "s.cashier_id = $cashier_id";

$sales = mysqli_query($conn,"
    SELECT s.*,
        (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.sale_id) AS item_count
    FROM sales s
    WHERE $whereClause
    ORDER BY s.created_at DESC
");

$todayTotal = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COALESCE(SUM(total_amount),0) AS total
    FROM sales s
    WHERE $whereClause AND DATE(s.created_at) = CURDATE()
"))['total'];

$todayCount = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) AS total
    FROM sales s
    WHERE $whereClause AND DATE(s.created_at) = CURDATE()
"))['total'];
?>

<style>
.summary-card {
    background: white; border-radius: 12px; padding: 16px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06); height: 100%;
}
.history-card {
    background: white; border-radius: 12px; padding: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}

@media print {
    body * {
        visibility: hidden !important;
    }
    #historyReceiptModal, #historyReceiptModal * {
        visibility: visible !important;
    }
    #historyReceiptModal {
        position: fixed !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        height: 100% !important;
        background: #fff !important;
        display: flex !important;
        justify-content: center !important;
        align-items: flex-start !important;
        padding-top: 20px !important;
    }
    .modal-header, .modal-footer, .btn-close, .modal-backdrop {
        display: none !important;
    }
    .modal-dialog {
        margin: 0 !important;
        width: 300px !important;
        max-width: 300px !important;
    }
    .modal-content {
        border: none !important;
        box-shadow: none !important;
    }
    .modal-body {
        padding: 10px !important;
        font-size: 13px !important;
    }
}
</style>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="summary-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Today's Sales</small>
                    <h3 class="fw-bold mb-0 text-success">₱<?= number_format($todayTotal, 2); ?></h3>
                </div>
                <div style="width:42px;height:42px;border-radius:10px;background:#198754;display:flex;align-items:center;justify-content:center;color:white;font-size:18px;">
                    <i class="bi bi-cash-coin"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="summary-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Today's Transactions</small>
                    <h3 class="fw-bold mb-0"><?= $todayCount; ?></h3>
                </div>
                <div style="width:42px;height:42px;border-radius:10px;background:#6c757d;display:flex;align-items:center;justify-content:center;color:white;font-size:18px;">
                    <i class="bi bi-receipt"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="history-card">
    <h5 class="mb-3">My Transactions</h5>

    <table class="table table-hover datatable">
        <thead>
            <tr>
                <th>Sale #</th>
                <th>Date</th>
                <th>Items</th>
                <th>Total</th>
                <th>Payment</th>
                <th>Change</th>
                <th>Receipt</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if(mysqli_num_rows($sales) == 0){
            ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No transactions yet.</td></tr>
            <?php
            } else {
                while($sale = mysqli_fetch_assoc($sales)){
            ?>
            <tr>
                <td>#<?= $sale['sale_id']; ?></td>
                <td><?= date("M d, Y h:i A", strtotime($sale['created_at'])); ?></td>
                <td><?= $sale['item_count']; ?></td>
                <td>₱<?= number_format($sale['total_amount'], 2); ?></td>
                <td>₱<?= number_format($sale['payment'], 2); ?></td>
                <td>₱<?= number_format($sale['change_amount'], 2); ?></td>
                <td>
                    <button class="btn btn-sm btn-outline-success" onclick="viewReceipt(<?= $sale['sale_id']; ?>)">
                        <i class="bi bi-eye me-1"></i>View
                    </button>
                </td>
            </tr>
            <?php
                }
            }
            ?>
        </tbody>
    </table>
</div>

<!-- RECEIPT MODAL -->
<div class="modal fade" id="historyReceiptModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Receipt</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="historyReceiptBody" style="font-family:monospace;font-size:13px;"></div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary btn-sm" onclick="printThermalReceipt('historyReceiptBody')">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function viewReceipt(saleId){
    $.get('cashier_history.php', { action: 'get_receipt', sale_id: saleId }, function(response){
        if(response.trim().startsWith('error')){
            Swal.fire('Error', response.replace('error:','').trim(), 'error');
            return;
        }
        $("#historyReceiptBody").html(response);
        new bootstrap.Modal(document.getElementById('historyReceiptModal')).show();
    });
}

function printThermalReceipt(containerId) {
    const el = document.getElementById(containerId);
    if (!el) return;

    let iframe = document.getElementById('receiptPrintIframe');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'receiptPrintIframe';
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0px';
        iframe.style.height = '0px';
        iframe.style.border = 'none';
        document.body.appendChild(iframe);
    }

    const doc = iframe.contentWindow.document;
    doc.open();
    doc.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Receipt Print</title>
            <style>
                @page {
                    size: auto;
                    margin: 5mm;
                }
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: 'Courier New', Courier, monospace;
                    font-size: 12px;
                    line-height: 1.4;
                    color: #000;
                    background: #fff;
                    padding: 10px;
                }
                .receipt-box {
                    width: 290px;
                    margin: 10px auto;
                    padding: 16px;
                    border: 2px dashed #222;
                    border-radius: 8px;
                    background: #fff;
                }
                hr { border: none; border-top: 1px dashed #000; margin: 8px 0; }
                @media print {
                    @page { margin: 5mm; }
                    body { padding: 0; }
                    .receipt-box {
                        width: 290px !important;
                        margin: 10px auto !important;
                        border: 2px dashed #000 !important;
                        padding: 14px !important;
                    }
                }
            </style>
        </head>
        <body>
            <div class="receipt-box">
                ${el.innerHTML}
            </div>
        </body>
        </html>
    `);
    doc.close();

    setTimeout(function() {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    }, 200);
}
window.printThermalReceipt = printThermalReceipt;
</script>