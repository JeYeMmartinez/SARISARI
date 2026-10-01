<div class="container-fluid mt-4">
    <h2>Procurement Requests</h2>
    
    <?php if (isset($_SESSION['proc_msg'])): ?>
        <div class="alert alert-<?= $_SESSION['proc_msg_type'] ?> alert-dismissible fade show">
            <?= $_SESSION['proc_msg'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['proc_msg'], $_SESSION['proc_msg_type']); ?>
    <?php endif; ?>

    <ul class="nav nav-tabs" id="procurementTab" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="pending-tab" data-bs-toggle="tab" href="#pending">Pending Sourcing</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="approved-tab" data-bs-toggle="tab" href="#approved">Product Order</a>
        </li>
    </ul>

    <div class="tab-content mt-3">
        <!-- PENDING SOURCING TAB -->
        <div class="tab-pane fade show active" id="pending">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle datatable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Request Code</th>
                                    <th>Product</th>
                                    <th>Requested Qty</th>
                                    <th>Current Cost Estimate</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $pendingArr = [];
                                if ($pendingRequests && mysqli_num_rows($pendingRequests) > 0): 
                                    while ($req = mysqli_fetch_assoc($pendingRequests)): 
                                        $pendingArr[] = $req;
                                ?>
                                        <tr>
                                            <td><?= $req['purchase_code'] ?></td>
                                            <td>
                                                <?= $req['product_name'] ?><br>
                                                <small class="text-muted"><?= $req['product_code'] ?></small>
                                            </td>
                                            <td><?= $req['requested_qty'] ?></td>
                                            <td>₱<?= number_format($req['estimated_cost'], 2) ?></td>
                                            <td><span class="badge bg-warning text-dark"><?= $req['status'] ?></span></td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#quoteModal<?= $req['purchase_id'] ?>">
                                                    <i class="fas fa-edit"></i> Add Quotation
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Quote Modals (Placed outside table to prevent DataTables DOM breaking) -->
                    <?php foreach ($pendingArr as $req): ?>
                        <div class="modal fade" id="quoteModal<?= $req['purchase_id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form action="../router?route=procurement_action" method="POST">
                                        <div class="modal-header bg-primary text-white">
                                            <h5 class="modal-title">Supplier Quotation: <?= $req['purchase_code'] ?></h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="update_quote">
                                            <input type="hidden" name="purchase_id" value="<?= $req['purchase_id'] ?>">
                                            
                                            <div class="mb-3">
                                                <label>Supplier Name</label>
                                                <select name="supplier_name" class="form-select" required>
                                                    <option value="">-- Select Official Supplier --</option>
                                                    <?php
                                                    if ($suppliers) {
                                                        mysqli_data_seek($suppliers, 0);
                                                        while ($sup = mysqli_fetch_assoc($suppliers)) {
                                                            echo '<option value="' . htmlspecialchars($sup['supplier_name']) . '">' . htmlspecialchars($sup['supplier_name']) . '</option>';
                                                        }
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label>Estimated Unit Cost (for 1 pc)</label>
                                                <input type="hidden" name="requested_qty" value="<?= $req['requested_qty'] ?>">
                                                <input type="number" step="0.01" name="unit_cost" class="form-control" required placeholder="0.00">
                                            </div>
                                            <div class="alert alert-info">
                                                Submitting this will forward the request to Finance for Budget Approval.
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Submit to Finance</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- APPROVED BY FINANCE TAB -->
        <div class="tab-pane fade" id="approved">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle datatable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Request Code</th>
                                    <th>Product</th>
                                    <th>Requested Qty</th>
                                    <th>Supplier</th>
                                    <th>Approved Cost</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($approvedRequests && mysqli_num_rows($approvedRequests) > 0): ?>
                                    <?php while ($req = mysqli_fetch_assoc($approvedRequests)): ?>
                                        <tr>
                                            <td><?= $req['purchase_code'] ?></td>
                                            <td>
                                                <?= $req['product_name'] ?><br>
                                                <small class="text-muted"><?= $req['product_code'] ?></small>
                                            </td>
                                            <td><?= $req['requested_qty'] ?></td>
                                            <td><?= htmlspecialchars($req['supplier_name']) ?></td>
                                            <td>₱<?= number_format($req['estimated_cost'], 2) ?></td>
                                            <td><span class="badge bg-success"><?= $req['status'] ?></span></td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick='viewSignedLetter(<?= json_encode($req); ?>)'>
                                                        <i class="bi bi-file-earmark-check"></i> View Approval
                                                    </button>
                                                    <form action="../router?route=procurement_action" method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="generate_po">
                                                        <input type="hidden" name="purchase_id" value="<?= $req['purchase_id'] ?>">
                                                        <button type="button" class="btn btn-sm btn-success" onclick="confirmGeneratePO(this)">
                                                            <i class="fas fa-file-invoice"></i> Generate
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmGeneratePO(button) {
    let form = $(button).closest('form');
    Swal.fire({
        title: 'Generate Purchase Order?',
        text: 'Are you sure you want to generate a Purchase Order and send it to the supplier?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-file-earmark-check me-1"></i> Yes, Generate PO',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
}
</script>

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
                <button type="button" class="btn btn-outline-primary btn-sm rounded-3" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
                <button type="button" class="btn btn-secondary btn-sm rounded-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewSignedLetter(request) {
    new bootstrap.Modal(document.getElementById('viewSignedLetterModal')).show();
    $('#signedLetterContent').html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>');
    
    $.get('../router?route=finance_action', { action: 'get_signed_letter', purchase_id: request.purchase_id }, function(data) {
        if(data && !data.error) {
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
                            ${data.e_signature && data.e_signature.startsWith('data:image') ? `<img src="${data.e_signature}" style="max-height:80px; max-width:250px;" alt="Signature">` : `<h4 class="text-primary signature-font mb-0" style="font-family: 'Brush Script MT', cursive;">${data.e_signature || data.approver_name}</h4>`}
                            <div class="border-top border-dark pt-1 mt-1 d-inline-block" style="min-width: 200px;">
                                <p class="mb-0 fw-bold">${data.approver_name}</p>
                                <p class="mb-0 text-muted" style="font-size:12px;">${data.approver_role}</p>
                            </div>
                        </div>
                        <div class="col-6 text-end">
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
                    <p class="text-muted">This request was approved before the electronic signature system was implemented.</p>
                </div>
            `);
        }
    }, 'json');
}
</script>
</div>
