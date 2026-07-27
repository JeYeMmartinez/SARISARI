<?php
require_once '../Model/database.php';

// Clear all logs
if(isset($_POST['action']) && $_POST['action'] == 'clear_all'){
    mysqli_query($conn, "DELETE FROM audit_logs");
    echo 'success';
    exit();
}

/*=========================================================
    FETCH LOGS
==========================================================*/

$logs = mysqli_query($conn,"
    SELECT al.*, u.full_name, u.role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    ORDER BY al.created_at DESC
");

$totalLogs = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM audit_logs"
))['total'];

?>

<style>
.log-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    margin-bottom: 20px;
}
.summary-card {
    background: white;
    border-radius: 12px;
    padding: 16px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
</style>

<!-- SUMMARY CARDS -->
<div class="row g-3 mb-4">

    <?php
    $actions = ['Create','Update','Delete','Login','Logout','Void'];
    $colors  = ['success','warning','danger','primary','secondary','dark'];
    $icons   = ['plus-circle','pencil-square','trash','box-arrow-in-right','box-arrow-right','slash-circle'];

    foreach($actions as $i => $action){
        $count = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) AS total FROM audit_logs WHERE action='$action'"
        ))['total'];
    ?>
    <div class="col-lg-2 col-md-4 col-6">
        <div class="summary-card text-center">
            <div class="mb-1">
                <i class="bi bi-<?= $icons[$i]; ?> text-<?= $colors[$i]; ?>"
                   style="font-size:22px;"></i>
            </div>
            <h4 class="fw-bold mb-0"><?= $count; ?></h4>
            <small class="text-muted"><?= $action; ?></small>
        </div>
    </div>
    <?php } ?>

</div>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        Activity Logs
        <span class="badge bg-secondary ms-1"><?= $totalLogs; ?> records</span>
    </h5>
    <button class="btn btn-sm btn-outline-danger" onclick="clearLogs()">
        <i class="bi bi-trash me-1"></i>Clear All Logs
    </button>
</div>

<!-- LOGS TABLE -->
<div class="log-card">
    <table class="table table-bordered table-striped datatable">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>User</th>
                <th>Role</th>
                <th>Action</th>
                <th>Table</th>
                <th>Description</th>
                <th>Date & Time</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 1;
            while($log = mysqli_fetch_assoc($logs)){
                switch($log['action']){
                    case 'Create':  $badge = 'bg-success'; break;
                    case 'Update':  $badge = 'bg-warning text-dark'; break;
                    case 'Delete':  $badge = 'bg-danger'; break;
                    case 'Login':   $badge = 'bg-primary'; break;
                    case 'Logout':  $badge = 'bg-secondary'; break;
                    default:        $badge = 'bg-dark';
                }
            ?>
            <tr>
                <td><?= $i++; ?></td>
                <td><?= htmlspecialchars($log['full_name'] ?? 'Unknown'); ?></td>
                <td>
                    <span class="badge <?= $log['role']=='Admin' ? 'bg-success' : 'bg-primary'; ?>">
                        <?= $log['role'] ?? '—'; ?>
                    </span>
                </td>
                <td><span class="badge <?= $badge; ?>"><?= $log['action']; ?></span></td>
                <td><code><?= htmlspecialchars($log['table_name']); ?></code></td>
                <td><?= htmlspecialchars($log['description']); ?></td>
                <td><?= date("M d, Y h:i A", strtotime($log['created_at'])); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<script>
function clearLogs(){
    Swal.fire({
        title: 'Clear all logs?',
        text: 'This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Clear All'
    }).then(result => {
        if(result.isConfirmed){
            $.post('audit_logs.php', { action: 'clear_all' }, function(response){
                if(response == 'success'){
                    Swal.fire({ icon:'success', title:'Logs Cleared!',
                        showConfirmButton:false, timer:1200 })
                    .then(() => { loadPage('audit_logs.php'); });
                }
            });
        }
    });
}
</script>