<?php
/**
 * Rollback Management Interface
 * PHP 5.3 Compatible
 */

require_once __DIR__ . '/../classes/AuditLogger.php';
require_once __DIR__ . '/../config/database.php';

// Initialize components
$database = new Database();
$db = $database->getConnection();
$auditLogger = new AuditLogger();

$message = '';
$messageType = '';

// Handle rollback request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['batch_id'])) {
    try {
        $batchId = $_POST['batch_id'];
        
        if (empty($batchId)) {
            throw new Exception('Batch ID is required');
        }
        
        // Confirm rollback with user
        if (!isset($_POST['confirm_rollback'])) {
            throw new Exception('Rollback confirmation is required');
        }
        
        $result = $auditLogger->rollbackBatch($batchId);
        
        if ($result['success']) {
            $message = $result['message'];
            $messageType = 'success';
        } else {
            $message = $result['message'];
            $messageType = 'danger';
        }
        
    } catch (Exception $e) {
        $message = 'Rollback error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle cleanup request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cleanup_expired'])) {
    try {
        $result = $auditLogger->cleanupExpiredBackups();
        
        if ($result['success']) {
            $message = $result['message'];
            $messageType = 'success';
        } else {
            $message = $result['message'];
            $messageType = 'danger';
        }
        
    } catch (Exception $e) {
        $message = 'Cleanup error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get available batches for rollback
$stmt = $db->prepare("
    SELECT DISTINCT ial.integration_batch_id as batch_id,
           COUNT(*) as change_count,
           MIN(ial.data_changed_on) as first_change,
           MAX(ial.data_changed_on) as last_change,
           iss.total_records,
           iss.processed_records,
           iss.new_opportunities,
           iss.updated_opportunities
    FROM integration_audit_log ial
    LEFT JOIN integration_statistics iss ON ial.integration_batch_id = iss.batch_id
    WHERE ial.status = 'ACTIVE'
    GROUP BY ial.integration_batch_id
    ORDER BY last_change DESC
    LIMIT 50
");
$stmt->execute();
$availableBatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get backup statistics
$stmt = $db->prepare("
    SELECT COUNT(*) as total_backups,
           COUNT(CASE WHEN expires_at > NOW() THEN 1 END) as active_backups,
           COUNT(CASE WHEN expires_at <= NOW() THEN 1 END) as expired_backups,
           MIN(backup_date) as oldest_backup,
           MAX(backup_date) as newest_backup
    FROM integration_backup
");
$stmt->execute();
$backupStats = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rollback Management - Pipeline Manager Integration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { border-bottom: 3px solid #dc3545; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { color: #dc3545; margin: 0; }
        .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px; border: none; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #545b62; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #1e7e34; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .table th, .table td { border: 1px solid #dee2e6; padding: 12px; text-align: left; }
        .table th { background: #f8f9fa; font-weight: bold; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 4px; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .alert-warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .alert-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .card { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 20px; margin: 15px 0; }
        .stats { display: flex; gap: 20px; flex-wrap: wrap; }
        .stat-card { flex: 1; min-width: 150px; background: white; border: 1px solid #dee2e6; border-radius: 6px; padding: 15px; text-align: center; }
        .stat-number { font-size: 1.5em; font-weight: bold; color: #dc3545; margin: 0; }
        .stat-label { color: #666; margin: 5px 0 0 0; font-size: 14px; }
        .form-group { margin: 20px 0; }
        .form-control { padding: 10px; border: 1px solid #ced4da; border-radius: 4px; width: 300px; }
        .rollback-form { background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .checkbox-group { margin: 15px 0; }
    </style>
    <script>
        function confirmRollback(batchId) {
            return confirm('WARNING: This will rollback ALL changes made by batch ' + batchId + '. This action cannot be undone. Are you sure you want to continue?');
        }
        
        function confirmCleanup() {
            return confirm('This will permanently delete all expired backup records. Continue?');
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Rollback Management</h1>
            <p>Rollback integration changes and manage backup data</p>
        </div>

        <a href="../index.php" class="btn btn-secondary">← Back to Dashboard</a>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Backup Statistics -->
        <div class="card">
            <h3>Backup Statistics</h3>
            <div class="stats">
                <div class="stat-card">
                    <p class="stat-number"><?php echo $backupStats['total_backups']; ?></p>
                    <p class="stat-label">Total Backups</p>
                </div>
                <div class="stat-card">
                    <p class="stat-number"><?php echo $backupStats['active_backups']; ?></p>
                    <p class="stat-label">Active Backups</p>
                </div>
                <div class="stat-card">
                    <p class="stat-number"><?php echo $backupStats['expired_backups']; ?></p>
                    <p class="stat-label">Expired Backups</p>
                </div>
                <div class="stat-card">
                    <p class="stat-number">120</p>
                    <p class="stat-label">Days Retention</p>
                </div>
            </div>
            
            <?php if ($backupStats['expired_backups'] > 0): ?>
                <div class="alert alert-info">
                    There are <?php echo $backupStats['expired_backups']; ?> expired backup records that can be cleaned up.
                    <form method="POST" style="display: inline; margin-left: 15px;">
                        <input type="hidden" name="cleanup_expired" value="1">
                        <button type="submit" class="btn btn-success" onclick="return confirmCleanup()">
                            Clean Up Expired Backups
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Available Batches for Rollback -->
        <div class="card">
            <h3>Available Batches for Rollback</h3>
            
            <?php if (empty($availableBatches)): ?>
                <div class="alert alert-info">
                    No integration batches available for rollback.
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <strong>Warning:</strong> Rollback operations are irreversible. They will restore opportunities to their state before the selected batch was processed.
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Batch ID</th>
                            <th>Changes Made</th>
                            <th>Records Processed</th>
                            <th>New Opportunities</th>
                            <th>Updated Opportunities</th>
                            <th>First Change</th>
                            <th>Last Change</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($availableBatches as $batch): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($batch['batch_id']); ?></td>
                                <td><?php echo $batch['change_count']; ?></td>
                                <td><?php echo $batch['processed_records'] ?: 'N/A'; ?></td>
                                <td><?php echo $batch['new_opportunities'] ?: 'N/A'; ?></td>
                                <td><?php echo $batch['updated_opportunities'] ?: 'N/A'; ?></td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($batch['first_change'])); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($batch['last_change'])); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="batch_id" value="<?php echo htmlspecialchars($batch['batch_id']); ?>">
                                        <div class="checkbox-group">
                                            <label>
                                                <input type="checkbox" name="confirm_rollback" value="1" required>
                                                I understand this action is irreversible
                                            </label>
                                        </div>
                                        <button type="submit" class="btn btn-danger" 
                                                onclick="return confirmRollback('<?php echo htmlspecialchars($batch['batch_id']); ?>')">
                                            Rollback
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Manual Rollback -->
        <div class="rollback-form">
            <h3>Manual Rollback by Batch ID</h3>
            <p>Enter a specific batch ID to rollback if it's not listed above:</p>
            
            <form method="POST">
                <div class="form-group">
                    <label for="batch_id">Batch ID:</label>
                    <input type="text" name="batch_id" id="batch_id" class="form-control" 
                           placeholder="BATCH_20250901_143022_1234" required>
                </div>
                
                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="confirm_rollback" value="1" required>
                        I understand this rollback action is irreversible and will restore all opportunities to their state before this batch was processed
                    </label>
                </div>
                
                <button type="submit" class="btn btn-danger">Execute Rollback</button>
            </form>
        </div>

        <!-- Information Panel -->
        <div class="alert alert-info">
            <strong>Rollback Information:</strong><br>
            • Rollback operations restore opportunities to their exact state before integration processing<br>
            • All field changes made during the selected batch are reversed<br>
            • Rollback actions are logged in the audit trail<br>
            • Backup data is automatically maintained for 120 days<br>
            • Only batches with active audit records can be rolled back<br>
            • New opportunities created during the batch will retain their data but changes will be reverted
        </div>
    </div>
</body>
</html>