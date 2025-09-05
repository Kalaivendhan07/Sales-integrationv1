<?php
/**
 * Pipeline Manager India Sales Integration - Main Dashboard
 * PHP 5.3 Compatible
 */

require_once __DIR__ . '/config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get recent statistics
$stmt = $db->prepare("
    SELECT * FROM integration_statistics 
    ORDER BY processing_start_time DESC 
    LIMIT 10
");
$stmt->execute();
$recentBatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending DSM actions count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM dsm_action_queue WHERE status = 'PENDING'");
$stmt->execute();
$pendingActions = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pipeline Manager India Sales Integration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { border-bottom: 3px solid #007bff; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { color: #007bff; margin: 0; }
        .header p { color: #666; margin: 5px 0 0 0; }
        .card { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 20px; margin: 15px 0; }
        .card h3 { margin-top: 0; color: #495057; }
        .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #1e7e34; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .stats { display: flex; gap: 20px; flex-wrap: wrap; }
        .stat-card { flex: 1; min-width: 200px; background: white; border: 1px solid #dee2e6; border-radius: 6px; padding: 15px; text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; color: #007bff; margin: 0; }
        .stat-label { color: #666; margin: 5px 0 0 0; }
        .table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .table th, .table td { border: 1px solid #dee2e6; padding: 12px; text-align: left; }
        .table th { background: #f8f9fa; font-weight: bold; }
        .status-completed { color: #28a745; font-weight: bold; }
        .status-processing { color: #ffc107; font-weight: bold; }
        .status-failed { color: #dc3545; font-weight: bold; }
        .alert { padding: 15px; margin: 15px 0; border-radius: 4px; }
        .alert-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .alert-warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Pipeline Manager India Sales Integration</h1>
            <p>Sales-Opportunity Integration System with 6-Level Validation Hierarchy</p>
        </div>

        <!-- Key Statistics -->
        <div class="card">
            <h3>System Overview</h3>
            <div class="stats">
                <div class="stat-card">
                    <p class="stat-number"><?php echo $pendingActions; ?></p>
                    <p class="stat-label">Pending DSM Actions</p>
                </div>
                <div class="stat-card">
                    <p class="stat-number"><?php echo count($recentBatches); ?></p>
                    <p class="stat-label">Recent Batches</p>
                </div>
                <div class="stat-card">
                    <p class="stat-number">6</p>
                    <p class="stat-label">Validation Levels</p>
                </div>
                <div class="stat-card">
                    <p class="stat-number">120</p>
                    <p class="stat-label">Days Audit Retention</p>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="card">
            <h3>Integration Actions</h3>
            <a href="integration/upload.php" class="btn">
                📁 Upload Sales Data
            </a>
            <a href="dsm/action_queue.php" class="btn btn-warning">
                ⚠️ DSM Action Queue (<?php echo $pendingActions; ?>)
            </a>
            <a href="reports/audit_log.php" class="btn btn-success">
                📊 Audit Reports
            </a>
            <a href="admin/rollback.php" class="btn btn-danger">
                🔄 Rollback Management
            </a>
            <a href="api/cleanup_expired.php" class="btn">
                🧹 Cleanup Expired Data
            </a>
            <a href="admin/lead_management.php" class="btn btn-info">
                🔒 Lead Management & Rules
            </a>
        </div>

        <!-- Validation Hierarchy Info -->
        <div class="card">
            <h3>6-Level Validation Hierarchy</h3>
            <div class="alert alert-info">
                <strong>Integration Process:</strong>
                <ol>
                    <li><strong>Level 1:</strong> GSTIN Validation & New Customer Creation</li>
                    <li><strong>Level 2:</strong> DSR Validation & Action Queue</li>
                    <li><strong>Level 3:</strong> Product Family Validation & Cross-Sell</li>
                    <li><strong>Level 4:</strong> Sector Validation & Override</li>
                    <li><strong>Level 5:</strong> Sub-Sector Validation & Override</li>
                    <li><strong>Level 6:</strong> Stage Validation & Volume Updates</li>
                </ol>
            </div>
        </div>

        <!-- Recent Processing History -->
        <div class="card">
            <h3>Recent Processing History</h3>
            <?php if (empty($recentBatches)): ?>
                <div class="alert alert-info">
                    No processing history found. Upload your first sales data file to get started.
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Batch ID</th>
                            <th>Start Time</th>
                            <th>Total Records</th>
                            <th>Processed</th>
                            <th>New Opportunities</th>
                            <th>Updated</th>
                            <th>Failed</th>
                            <th>DSM Actions</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentBatches as $batch): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($batch['batch_id']); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($batch['processing_start_time'])); ?></td>
                                <td><?php echo $batch['total_records']; ?></td>
                                <td><?php echo $batch['processed_records']; ?></td>
                                <td><?php echo $batch['new_opportunities']; ?></td>
                                <td><?php echo $batch['updated_opportunities']; ?></td>
                                <td><?php echo $batch['failed_records']; ?></td>
                                <td><?php echo $batch['dsm_actions_created']; ?></td>
                                <td>
                                    <span class="status-<?php echo strtolower($batch['status']); ?>">
                                        <?php echo $batch['status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- System Requirements -->
        <div class="card">
            <h3>System Information</h3>
            <div class="alert alert-info">
                <strong>Technology Stack:</strong> PHP <?php echo PHP_VERSION; ?> | MySQL 8.0 | Apache<br>
                <strong>Processing Capacity:</strong> 500+ daily records | 120-day audit retention<br>
                <strong>Last Updated:</strong> <?php echo date('Y-m-d H:i:s'); ?>
            </div>
        </div>
    </div>
</body>
</html>