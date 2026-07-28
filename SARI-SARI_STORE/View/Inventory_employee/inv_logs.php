<?php
session_start();
require_once '../../Model/database.php';

// Query audit logs relevant to inventory and products
$logs = mysqli_query($conn, "
    SELECT log_id, user_id, action, table_name, record_id, description, created_at
    FROM audit_logs
    WHERE table_name IN ('inventory', 'products', 'stock_requisitions')
    ORDER BY created_at DESC
");
?>

<div class="animate__animated animate__fadeIn">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1 text-dark"><i class="bi bi-journal-text me-2 text-info"></i>Inventory Movement & Audit Logs</h3>
            <p class="text-muted mb-0" style="font-size:13px;">Historical log of stock additions, deductions, location changes, and requisitions.</p>
        </div>
    </div>

    <!-- LOGS TABLE CARD -->
    <div class="page-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle datatable w-100" id="logsTable" style="font-size: 13px;">
                <thead class="table-light">
                    <tr>
                        <th style="width:70px;">Log ID</th>
                        <th>Action</th>
                        <th>Module / Table</th>
                        <th>Record ID</th>
                        <th>Description & Notes</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($logs)){ 
                        $badge_class = 'bg-secondary';
                        if($row['action'] == 'Create') $badge_class = 'bg-success';
                        elseif($row['action'] == 'Update') $badge_class = 'bg-primary';
                        elseif($row['action'] == 'Delete' || $row['action'] == 'Void') $badge_class = 'bg-danger';
                    ?>
                    <tr>
                        <td class="fw-bold text-muted">#<?= $row['log_id']; ?></td>
                        <td><span class="badge <?= $badge_class; ?>"><?= htmlspecialchars($row['action']); ?></span></td>
                        <td><code><?= htmlspecialchars($row['table_name']); ?></code></td>
                        <td><span class="badge bg-light text-dark border">#<?= $row['record_id']; ?></span></td>
                        <td class="fw-semibold text-dark"><?= htmlspecialchars($row['description']); ?></td>
                        <td><small class="text-muted"><?= date('M d, Y g:i:s A', strtotime($row['created_at'])); ?></small></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
