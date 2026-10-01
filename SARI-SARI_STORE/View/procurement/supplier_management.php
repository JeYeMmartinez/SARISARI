<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Supplier Information Management</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
            <i class="bi bi-plus-circle"></i> Add Supplier
        </button>
    </div>
    
    <?php if (isset($_SESSION['proc_msg'])): ?>
        <div class="alert alert-<?= $_SESSION['proc_msg_type'] ?> alert-dismissible fade show">
            <?= $_SESSION['proc_msg'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['proc_msg'], $_SESSION['proc_msg_type']); ?>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle datatable">
                    <thead class="table-dark">
                        <tr>
                            <th>Supplier Name</th>
                            <th>Contact Person</th>
                            <th>Contact Number</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($suppliers && mysqli_num_rows($suppliers) > 0): ?>
                            <?php while ($sup = mysqli_fetch_assoc($suppliers)): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($sup['supplier_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($sup['contact_person'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($sup['contact_number'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($sup['email'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if ($sup['status'] === 'Active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editSupplierModal<?= $sup['supplier_id'] ?>">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                    </td>
                                </tr>

                                <!-- Edit Supplier Modal -->
                                <div class="modal fade" id="editSupplierModal<?= $sup['supplier_id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="../router?route=procurement_action" method="POST">
                                                <div class="modal-header bg-primary text-white">
                                                    <h5 class="modal-title">Edit Supplier</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="update_supplier">
                                                    <input type="hidden" name="supplier_id" value="<?= $sup['supplier_id'] ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label>Company Name <span class="text-danger">*</span></label>
                                                        <input type="text" name="supplier_name" class="form-control" value="<?= htmlspecialchars($sup['supplier_name']) ?>" required>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label>Contact Person</label>
                                                            <input type="text" name="contact_person" class="form-control" value="<?= htmlspecialchars($sup['contact_person']) ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label>Contact Number</label>
                                                            <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($sup['contact_number']) ?>">
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Email Address</label>
                                                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($sup['email']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Physical Address</label>
                                                        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($sup['address']) ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Status</label>
                                                        <select name="status" class="form-select">
                                                            <option value="Active" <?= $sup['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                                                            <option value="Inactive" <?= $sup['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../router?route=procurement_action" method="POST">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Add New Supplier</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_supplier">
                    
                    <div class="mb-3">
                        <label>Company Name <span class="text-danger">*</span></label>
                        <input type="text" name="supplier_name" class="form-control" required placeholder="e.g. Acme Corp">
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label>Contact Person</label>
                            <input type="text" name="contact_person" class="form-control" placeholder="John Doe">
                        </div>
                        <div class="col-md-6">
                            <label>Contact Number</label>
                            <input type="text" name="contact_number" class="form-control" placeholder="09123456789">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="sales@acmecorp.com">
                    </div>
                    <div class="mb-3">
                        <label>Physical Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="123 Industrial Ave, Manila"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>
