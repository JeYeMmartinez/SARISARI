<?php
// View/Finance_employee/expenses.php
?>
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color:#2b1055;">
                <i class="bi bi-receipt me-2 text-purple" style="color:#7b2cbf;"></i>Expense Management
            </h4>
            <p class="text-muted mb-0" style="font-size:13px;">Record an operational expense that does not originate from another O-CART module, such as utilities, rent, maintenance, repairs, or office expenses.</p>
        </div>
        <div>
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#createExpenseModal" style="background:#7b2cbf; border:none; border-radius:8px;">
                <i class="bi bi-plus-circle me-1"></i> Record External Expense
            </button>
        </div>
    </div>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius:10px;">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius:10px;">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($_SESSION['error_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <!-- Table -->
    <div class="card border-0 shadow-sm" style="border-radius:12px; overflow:hidden;">
        <div class="card-header bg-white py-3 border-0">
            <h6 class="fw-bold mb-0 text-secondary"><i class="bi bi-journal-text me-2"></i>Expenses</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 datatable">
                <thead class="table-light">
                    <tr style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">
                        <th class="ps-4">Reference No</th>
                        <th>Budget</th>
                        <th>Category</th>
                        <th>Payee</th>
                        <th class="text-end">Amount (₱)</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody style="font-size:13px;">
                    <?php if (!empty($expenses)): ?>
                        <?php foreach ($expenses as $e): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-secondary"><?= htmlspecialchars($e['reference_no']); ?></td>
                                <td><?= htmlspecialchars($e['budget_ref']); ?></td>
                                <td><?= htmlspecialchars($e['category_name']); ?></td>
                                <td><?= htmlspecialchars($e['payee']); ?></td>
                                <td class="text-end fw-bold text-dark"><?= number_format($e['amount'], 2); ?></td>
                                <td>
                                    <?php
                                    $badge = 'bg-secondary';
                                    if($e['status'] == 'Approved') $badge = 'bg-primary';
                                    elseif($e['status'] == 'Paid') $badge = 'bg-success';
                                    elseif($e['status'] == 'For Approval') $badge = 'bg-warning text-dark';
                                    elseif(in_array($e['status'], ['Cancelled', 'Rejected'])) $badge = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $badge; ?> rounded-pill px-3 py-2"><?= htmlspecialchars($e['status']); ?></span>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($e['status'] === 'Draft'): ?>
                                        <form method="POST" action="../router?route=finance_expense_action" style="display:inline-block;" onsubmit="handleFormSubmit(event, this)">
                                            <input type="hidden" name="action" value="submit">
                                            <input type="hidden" name="expense_id" value="<?= $e['expense_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Submit for Approval"><i class="bi bi-send"></i></button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($e['status'] === 'For Approval'): ?>
                                        <button class="btn btn-sm btn-outline-success" title="Approve" onclick="approveExpense(<?= $e['expense_id'] ?>)">
                                            <i class="bi bi-check-circle"></i>
                                        </button>
                                        <form method="POST" action="../router?route=finance_expense_action" style="display:inline-block;" onsubmit="return confirm('Reject this expense?'); handleFormSubmit(event, this)">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="expense_id" value="<?= $e['expense_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Reject"><i class="bi bi-x-circle"></i></button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($e['status'] === 'Approved'): ?>
                                        <button class="btn btn-sm btn-outline-success" title="Mark Paid" onclick="markPaid(<?= $e['expense_id'] ?>)">
                                            <i class="bi bi-cash"></i> Paid
                                        </button>
                                    <?php endif; ?>

                                    <?php if (in_array($e['status'], ['Draft', 'For Approval', 'Approved'])): ?>
                                        <form method="POST" action="../router?route=finance_expense_action" style="display:inline-block;" onsubmit="return confirm('Cancel this expense?'); handleFormSubmit(event, this)">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="expense_id" value="<?= $e['expense_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Cancel"><i class="bi bi-slash-circle"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No expenses found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Expense Modal -->
<div class="modal fade" id="createExpenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:12px; border:none;">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold" style="color:#2b1055;">Record External Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="../router?route=finance_expense_action" onsubmit="handleFormSubmit(event, this)">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold" style="font-size:12px; text-transform:uppercase;">Budget</label>
                        <select class="form-select" name="budget_id" required>
                            <option value="">Select Approved Budget</option>
                            <?php foreach($budgets as $b): ?>
                                <?php $remaining = $budget_model->getRemainingBudget($b['budget_id']); ?>
                                <option value="<?= $b['budget_id'] ?>"><?= htmlspecialchars($b['reference_no'] . ' - ' . $b['title']) ?> (Rem: ₱<?= number_format($remaining, 2) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold" style="font-size:12px; text-transform:uppercase;">Category</label>
                        <select class="form-select" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach($categories as $c): ?>
                                <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3 row">
                        <div class="col-6">
                            <label class="form-label text-muted fw-bold" style="font-size:12px; text-transform:uppercase;">Amount (₱)</label>
                            <input type="number" step="0.01" class="form-control" name="amount" required min="0.01">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted fw-bold" style="font-size:12px; text-transform:uppercase;">Date</label>
                            <input type="date" class="form-control" name="expense_date" required value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold" style="font-size:12px; text-transform:uppercase;">Payee</label>
                        <input type="text" class="form-control" name="payee" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold" style="font-size:12px; text-transform:uppercase;">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background:#7b2cbf; border:none;">Create Draft</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Approve Expense Modal -->
<div class="modal fade" id="approveExpenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:12px; border:none;">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold" style="color:#2b1055;">Approve Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="../router?route=finance_expense_action" onsubmit="handleFormSubmit(event, this)">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="expense_id" id="approve_expense_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold" style="font-size:12px; text-transform:uppercase;">Notes (Optional)</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold" style="font-size:12px; text-transform:uppercase;">E-Signature Data</label>
                        <textarea class="form-control" name="signature_data" rows="3" required placeholder="Paste signature data here..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mark Paid Modal -->
<div class="modal fade" id="markPaidModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:12px; border:none;">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold" style="color:#2b1055;">Mark as Paid</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="../router?route=finance_expense_action" onsubmit="handleFormSubmit(event, this)">
                <input type="hidden" name="action" value="mark_paid">
                <input type="hidden" name="expense_id" id="paid_expense_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold" style="font-size:12px; text-transform:uppercase;">Payment Date</label>
                        <input type="date" class="form-control" name="payment_date" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold" style="font-size:12px; text-transform:uppercase;">Payment Method</label>
                        <select class="form-select" name="payment_method" required>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Check">Check</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold" style="font-size:12px; text-transform:uppercase;">Reference / Receipt # (Optional)</label>
                        <input type="text" class="form-control" name="payment_reference">
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveExpense(id) {
    document.getElementById('approve_expense_id').value = id;
    new bootstrap.Modal(document.getElementById('approveExpenseModal')).show();
}

function markPaid(id) {
    document.getElementById('paid_expense_id').value = id;
    new bootstrap.Modal(document.getElementById('markPaidModal')).show();
}

function handleFormSubmit(e, form) {
    e.preventDefault();
    let btn = form.querySelector('button[type="submit"]');
    if(btn) {
        let orig = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        btn.disabled = true;
        
        let formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(() => {
            if(typeof window.parent.loadPage === 'function') {
                window.parent.loadPage('../router?route=finance_expenses');
            } else {
                window.location.reload();
            }
        })
        .catch(() => {
            window.location.reload();
        });
    }
}
</script>
