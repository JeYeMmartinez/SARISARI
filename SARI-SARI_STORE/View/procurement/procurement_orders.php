<div class="container-fluid mt-4">
    <h2>Purchase Orders Tracking</h2>
    
    <div class="card shadow-sm border-0 mt-3">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle datatable">
                    <thead class="table-dark">
                        <tr>
                            <th>PO Code</th>
                            <th>Product</th>
                            <th>Ordered Qty</th>
                            <th>Supplier</th>
                            <th>Cost Estimate</th>
                            <th>Expected Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($purchaseOrders && mysqli_num_rows($purchaseOrders) > 0): ?>
                            <?php while ($po = mysqli_fetch_assoc($purchaseOrders)): ?>
                                <tr>
                                    <td><strong><?= $po['order_code'] ?></strong></td>
                                    <td>
                                        <?= $po['product_name'] ?><br>
                                        <small class="text-muted"><?= $po['product_code'] ?></small>
                                    </td>
                                    <td><?= $po['ordered_qty'] ?></td>
                                    <td><?= htmlspecialchars($po['supplier_name']) ?></td>
                                    <td>₱<?= number_format($po['estimated_cost'] ?? 0, 2) ?></td>
                                    <td><?= $po['expected_date'] ? date('M d, Y', strtotime($po['expected_date'])) : 'N/A' ?></td>
                                    <td>
                                        <?php if ($po['status'] === 'Not Arrived'): ?>
                                            <span class="badge bg-primary">Sent to Supplier</span>
                                        <?php elseif ($po['status'] === 'Arrived'): ?>
                                            <span class="badge bg-success">Received by Warehouse</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= $po['status'] ?></span>
                                        <?php endif; ?>
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
