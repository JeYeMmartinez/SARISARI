<!-- ===== PRINT-ONLY RECEIPT OVERLAY ===== -->
<style>
@media print {
    /* Make everything invisible */
    body * { visibility: hidden !important; }
    /* But show our receipt overlay and all its children */
    #receiptPrintOverlay, #receiptPrintOverlay * { visibility: visible !important; }
    /* Position it to fill the page */
    #receiptPrintOverlay {
        position: fixed !important;
        top: 0 !important; left: 0 !important;
        width: 100% !important; height: auto !important;
        background: #fff !important;
        padding: 30px !important;
        z-index: 999999 !important;
        font-family: 'Times New Roman', serif !important;
        color: #000 !important;
    }
    @page { margin: 1.5cm; }
}
/* Hidden from normal view */
#receiptPrintOverlay { display: none; }
</style>
<div id="receiptPrintOverlay"></div>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color:#2b1055;">
                <i class="bi bi-bank me-2 text-purple" style="color:#7b2cbf;"></i>Stock Purchase Requests (Finance)
            </h4>
            <p class="text-muted mb-0" style="font-size:13px;">Review supplier procurement requests forwarded from Warehouse when central storage is out of stock.</p>
        </div>
        <div>
            <div class="badge bg-purple px-3 py-2 text-white shadow-sm" style="background:#7b2cbf; border-radius:8px; font-size:13px;">
                <i class="bi bi-wallet2 me-1"></i> Pending Purchase Cost: ₱<?= number_format($total_pending_cost, 2); ?>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $msg_type; ?> alert-dismissible fade show" role="alert" style="border-radius:10px;">
            <i class="bi bi-info-circle me-2"></i><?= htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="card border-0 shadow-sm" style="border-radius:12px; overflow:hidden;">
        <div class="card-header bg-white py-3 border-0">
            <h6 class="fw-bold mb-0 text-secondary"><i class="bi bi-file-earmark-text me-2"></i>Supplier Procurement Applications</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">
                        <th class="ps-4">PO Code</th>
                        <th>Product Details</th>
                        <th class="text-center">Qty to Purchase</th>
                        <th>Supplier</th>
                        <th class="text-end">Estimated Cost</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-4">Finance Action</th>
                    </tr>
                </thead>
                <tbody style="font-size:13px;">
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No stock purchase requests pending finance approval.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $r): 
                            $st = $r['status'];
                            $badgeClass = 'bg-secondary';
                            if ($st === 'Pending Finance Approval') $badgeClass = 'bg-warning text-dark';
                            elseif ($st === 'Approved by Finance') $badgeClass = 'bg-success';
                            elseif ($st === 'Rejected by Finance') $badgeClass = 'bg-danger';
                            
                            $imgSrc = !empty($r['image']) ? '../uploads/' . htmlspecialchars($r['image']) : 'https://via.placeholder.com/40?text=Product';
                        ?>
                        <tr>
                            <td class="ps-4">
                                <strong class="text-purple d-block" style="color:#7b2cbf;"><?= htmlspecialchars($r['purchase_code']); ?></strong>
                                <small class="text-muted"><?= date('M d, Y h:i A', strtotime($r['created_at'])); ?></small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?= $imgSrc; ?>" class="rounded border" style="width:36px; height:36px; object-fit:cover;">
                                    <div>
                                        <strong class="d-block text-dark"><?= htmlspecialchars($r['product_name']); ?></strong>
                                        <small class="text-muted">Unit Price: ₱<?= number_format($r['price'], 2); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center fw-bold fs-6">
                                <?= number_format($r['requested_qty']); ?> units
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($r['supplier_name']); ?></span>
                            </td>
                            <td class="text-end fw-bold text-dark fs-6">
                                ₱<?= number_format($r['estimated_cost'], 2); ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= $badgeClass; ?>"><?= htmlspecialchars($st); ?></span>
                            </td>
                            <td class="text-end pe-4">
                                <?php if ($st === 'Pending Finance Approval'): ?>
                                    <button class="btn btn-sm btn-success rounded-3 me-1" onclick='openFinanceApproveModal(<?= json_encode($r); ?>)'>
                                        <i class="bi bi-pen me-1"></i> Review & Sign Approval
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger rounded-3" onclick='openFinanceRejectModal(<?= json_encode($r); ?>)'>
                                        <i class="bi bi-x-lg me-1"></i> Reject
                                    </button>
                                <?php elseif ($st === 'Approved by Finance'): ?>
                                    <button class="btn btn-sm btn-outline-primary rounded-3" onclick='viewSignedLetter(<?= json_encode($r); ?>)'>
                                        <i class="bi bi-file-earmark-check me-1"></i> View Signed Letter
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:12px;"><i class="bi bi-lock me-1"></i>Processed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- FORMAL FINANCE APPROVAL LETTER MODAL -->
<div class="modal fade" id="financeApproveModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:14px;">
            <div class="modal-header bg-dark text-white border-0 py-3" style="border-radius:14px 14px 0 0;">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-file-earmark-text me-2"></i>Formal Purchase Approval Letter
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="approveFinanceForm">
                <input type="hidden" name="action" value="sign_and_approve_finance">
                <input type="hidden" name="purchase_id" id="app_purchase_id">
                
                <div class="modal-body p-4" style="background:#fafafa;">
                    <div class="bg-white p-4 border rounded shadow-sm" style="font-family: 'Times New Roman', serif; color:#000;">
                        <div class="text-center mb-4">
                            <h4 class="fw-bold mb-1">O-CART!</h4>
                            <p class="mb-0 text-muted" style="font-size:14px;">Formal Stock Purchase Authorization</p>
                            <hr>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <strong>Date:</strong> <span id="app_date"><?= date('F d, Y') ?></span><br>
                                <strong>Requesting Dept:</strong> Central Warehouse<br>
                                <strong>Ref No:</strong> <span id="app_code" class="text-primary fw-bold"></span>
                            </div>
                            <div class="col-6 text-end">
                                <strong>Document:</strong> Financial Approval<br>
                                <strong>Status:</strong> <span class="badge bg-warning text-dark">Pending Signature</span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <p>This document serves as the formal financial authorization to procure the following stock items to replenish the central warehouse.</p>
                            <table class="table table-bordered table-sm align-middle" style="font-size:14px;">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th>Product</th>
                                        <th>Supplier</th>
                                        <th>Requested Qty</th>
                                        <th>Estimated Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="text-center">
                                        <td id="app_product"></td>
                                        <td id="app_supplier"></td>
                                        <td id="app_qty"></td>
                                        <td id="app_cost" class="fw-bold"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold style-label">Finance Approval Notes</label>
                            <textarea name="finance_notes" class="form-control form-control-sm" rows="2" style="font-family: inherit;">Budget verified and approved for supplier procurement.</textarea>
                        </div>
                        
                        <hr>
                        <div class="mt-4 p-3 bg-light border rounded text-center">
                            <h6 class="fw-bold text-success mb-3"><i class="bi bi-pen me-1"></i>Electronic Signature</h6>
                            
                            <?php if ($current_signature): ?>
                                <p class="text-muted mb-2" style="font-size:13px;">Your registered Finance signature will be attached to this approval.</p>
                                <div class="mx-auto p-2 bg-white border rounded" style="max-width:350px;">
                                    <img src="<?= htmlspecialchars($current_signature) ?>" style="max-height:80px; max-width:300px; border-bottom:1px solid #ccc;" alt="Registered Signature">
                                    <div class="fw-bold mt-2" style="font-size:13px;"><?= htmlspecialchars($_SESSION['emp_name'] ?? $_SESSION['full_name'] ?? 'Finance User') ?></div>
                                </div>
                                <div class="mt-3 text-muted" style="font-size:11px;">Timestamp: <?= date('Y-m-d H:i:s') ?></div>
                            <?php else: ?>
                                <div class="p-4 bg-white border border-warning rounded">
                                    <i class="bi bi-exclamation-triangle-fill text-warning fs-3 mb-2 d-block"></i>
                                    <h6 class="fw-bold">No Registered Signature</h6>
                                    <p class="text-muted" style="font-size:13px;">Please register your Finance signature before approving this document.</p>
                                    <button type="button" class="btn btn-sm btn-primary mt-2" onclick="$('#financeApproveModal').modal('hide'); loadPage('finance_signature_profile.php', this)">Register Signature Now</button>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
                
                <div class="modal-footer bg-light border-0 py-2" style="border-radius:0 0 14px 14px;">
                    <button type="button" class="btn btn-secondary btn-sm rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <?php if ($current_signature): ?>
                    <button type="submit" class="btn btn-success btn-sm rounded-3 px-3 fw-bold">
                        <i class="bi bi-check-circle-fill me-1"></i> Sign & Approve Purchase
                    </button>
                    <?php else: ?>
                    <button type="button" class="btn btn-success btn-sm rounded-3 px-3 fw-bold" disabled>
                        <i class="bi bi-check-circle-fill me-1"></i> Sign & Approve Purchase
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- VIEW SIGNED LETTER MODAL -->
<div class="modal fade" id="viewSignedLetterModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:14px;">
            <div class="modal-header bg-primary text-white border-0 py-3" style="border-radius:14px 14px 0 0;">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-file-earmark-check me-2"></i>Signed Formal Approval Letter
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" style="background:#fafafa;" id="signedLetterContent">
                <div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>
            </div>
            <div class="modal-footer bg-light border-0 py-2" style="border-radius:0 0 14px 14px;">
                <button type="button" class="btn btn-outline-primary btn-sm rounded-3" onclick="printSignedLetter()"><i class="bi bi-printer me-1"></i> Print</button>
                <button type="button" class="btn btn-secondary btn-sm rounded-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- FINANCE REJECT MODAL -->
<div class="modal fade" id="financeRejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius:14px;">
            <div class="modal-header bg-danger text-white border-0 py-3" style="border-radius:14px 14px 0 0;">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-x-circle me-2"></i>Reject Stock Purchase Request
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectFinanceForm">
                <input type="hidden" name="action" value="reject_finance">
                <input type="hidden" name="purchase_id" id="rej_purchase_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary style-label">Reason for Rejection</label>
                        <textarea name="finance_notes" class="form-control form-control-sm" rows="3" required>Budget allocation exceeded or request denied by Finance.</textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-2" style="border-radius:0 0 14px 14px;">
                    <button type="button" class="btn btn-secondary btn-sm rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm rounded-3 px-3">
                        <i class="bi bi-x-lg me-1"></i> Reject Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openFinanceApproveModal(request) {
    document.getElementById('app_purchase_id').value = request.purchase_id;
    document.getElementById('app_code').innerText = request.purchase_code;
    document.getElementById('app_product').innerText = request.product_name;
    document.getElementById('app_supplier').innerText = request.supplier_name;
    document.getElementById('app_qty').innerText = request.requested_qty + ' units';
    document.getElementById('app_cost').innerText = '₱' + parseFloat(request.estimated_cost).toLocaleString('en-US', {minimumFractionDigits: 2});
    
    new bootstrap.Modal(document.getElementById('financeApproveModal')).show();
}

// Canvas logic removed (moved to profile page)
// ---------------------------

function getFinanceTargetUrl() {
    return '../router.php?route=finance_action';
}

function viewSignedLetter(request) {
    new bootstrap.Modal(document.getElementById('viewSignedLetterModal')).show();
    $('#signedLetterContent').html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>');
    
    $.get(getFinanceTargetUrl(), { action: 'get_signed_letter', purchase_id: request.purchase_id }, function(data) {
        if(data) {
            const html = `
                <div class="bg-white p-4 border rounded shadow-sm" style="font-family: 'Times New Roman', serif; color:#000;">
                    <div class="text-center mb-4">
                        <h4 class="fw-bold mb-1">O-CART!</h4>
                        <p class="mb-0 text-muted" style="font-size:14px;">Formal Stock Purchase Authorization</p>
                        <hr>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <strong>Signed Date:</strong> ${new Date(data.signed_at).toLocaleString()}<br>
                            <strong>Requesting Dept:</strong> Central Warehouse<br>
                            <strong>Requested By:</strong> ${data.requested_by || 'Warehouse Manager'}<br>
                            <strong>Ref No:</strong> <span class="text-primary fw-bold">${data.purchase_code}</span>
                        </div>
                        <div class="col-6 text-end">
                            <strong>Document:</strong> Financial Approval<br>
                            <strong>Status:</strong> <span class="badge bg-success">Approved & Signed</span><br>
                            <strong>Approval Ref:</strong> ${data.approval_ref}
                        </div>
                    </div>
                    <div class="mb-4">
                        <table class="table table-bordered table-sm align-middle" style="font-size:14px;">
                            <thead class="table-light text-center">
                                <tr><th>Product</th><th>Supplier</th><th>Requested Qty</th><th>Estimated Cost</th></tr>
                            </thead>
                            <tbody>
                                <tr class="text-center">
                                    <td>${data.product_name}</td>
                                    <td>${data.supplier_name}</td>
                                    <td>${data.requested_qty} units</td>
                                    <td class="fw-bold">₱${parseFloat(data.estimated_cost).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mb-4">
                        <label class="fw-bold">Approval Notes:</label>
                        <p class="mb-0 bg-light p-2 border rounded" style="font-style:italic;">${data.notes}</p>
                    </div>
                    <hr>
                    <div class="mt-4 row">
                        <div class="col-6">
                            <p class="mb-1"><strong>Authorized By:</strong></p>
                            ${data.e_signature.startsWith('data:image') ? `<img src="${data.e_signature}" style="max-height:80px; max-width:250px;" alt="Signature">` : `<h4 class="text-primary signature-font mb-0" style="font-family: 'Brush Script MT', cursive;">${data.e_signature}</h4>`}
                            <div class="border-top border-dark pt-1 mt-1 d-inline-block" style="min-width: 200px;">
                                <p class="mb-0 fw-bold">${data.approver_name}</p>
                                <p class="mb-0 text-muted" style="font-size:12px;">${data.approver_role}</p>
                            </div>
                        </div>
                        <div class="col-6 text-end">
                            <!-- digital stamp placeholder -->
                            <div class="d-inline-block border border-success text-success p-2 rounded text-center opacity-75" style="border-width: 3px !important; transform: rotate(-5deg);">
                                <h5 class="fw-bold mb-0">APPROVED</h5>
                                <small>${data.signed_at}</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('#signedLetterContent').html(html);
        } else {
            $('#signedLetterContent').html(`
                <div class="text-center p-5">
                    <i class="bi bi-exclamation-circle text-warning mb-3" style="font-size:3rem;"></i>
                    <h5 class="fw-bold">No Signature Found</h5>
                    <p class="text-muted">This request was approved before the electronic signature system was implemented. There is no formal document on file.</p>
                </div>
            `);
        }
    }, 'json');
}

function submitApproveForm(e) {
    var isUploadActive = document.getElementById('upload-tab').classList.contains('active');
    if (isUploadActive && !document.getElementById('sigImageUpload').files[0] && !document.getElementById('stock_e_signature').value) {
        e.preventDefault();
        Swal.fire('Signature Required', 'Please upload a signature image to approve the request.', 'warning');
    } else if (!isUploadActive && !document.getElementById('stock_e_signature').value) {
        e.preventDefault();
        Swal.fire('Signature Required', 'Please draw your signature to approve the request.', 'warning');
    }
}
document.getElementById('approveFinanceForm').addEventListener('submit', submitApproveForm);

function openFinanceRejectModal(request) {
    $('#rej_purchase_id').val(request.purchase_id);
    new bootstrap.Modal(document.getElementById('financeRejectModal')).show();
}

function clearBackdropFinance(){
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open').css('padding-right','');
}

$('#approveFinanceForm, #rejectFinanceForm').on('submit', function(e){
    e.preventDefault();
    const formData = $(this).serialize();
    const isApprove = $(this).attr('id') === 'approveFinanceForm';
    const modalId = isApprove ? '#financeApproveModal' : '#financeRejectModal';

    // Disable button to prevent double click
    const btn = $(this).find('button[type="submit"]');
    const originalText = btn.html();
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');

    $.post(getFinanceTargetUrl(), formData, function(res){
        $(modalId).modal('hide');
        clearBackdropFinance();
        if (typeof loadPage === 'function') {
            loadPage('../router.php?route=finance_stock_requests');
        } else {
            location.reload();
        }
    }).fail(function(){
        btn.prop('disabled', false).html(originalText);
        Swal.fire('Error', 'Failed to process request. The image might be too large.', 'error');
    });
});
</script>

<script>
/**
 * Copies the receipt into the print overlay and calls window.print().
 * CSS @media print hides everything except the overlay — no popup needed.
 */
function printSignedLetter() {
    var content = document.getElementById('signedLetterContent');
    var overlay = document.getElementById('receiptPrintOverlay');
    if (!content || !overlay) { window.print(); return; }

    // Copy the receipt HTML into the overlay
    overlay.innerHTML = content.innerHTML;
    overlay.style.display = 'block';

    // Wait a tick then print
    setTimeout(function() {
        window.print();
        // After printing, hide and clear the overlay
        setTimeout(function() {
            overlay.style.display = 'none';
            overlay.innerHTML = '';
        }, 500);
    }, 150);
}
</script>
