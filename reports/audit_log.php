<?php
/**
 * Audit Log Reports Interface
 * PHP 5.3 Compatible
 */

require_once __DIR__ . '/../config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Handle search filters
$leadId = isset($_GET['lead_id']) ? $_GET['lead_id'] : '';
$batchId = isset($_GET['batch_id']) ? $_GET['batch_id'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$whereClause = "WHERE 1=1";
$params = array();

if ($leadId) {
    $whereClause .= " AND ial.lead_id = :lead_id";
    $params[':lead_id'] = $leadId;
}

if ($batchId) {
    $whereClause .= " AND ial.integration_batch_id LIKE :batch_id";
    $params[':batch_id'] = '%' . $batchId . '%';
}

if ($dateFrom) {
    $whereClause .= " AND DATE(ial.data_changed_on) >= :date_from";
    $params[':date_from'] = $dateFrom;
}

if ($dateTo) {
    $whereClause .= " AND DATE(ial.data_changed_on) <= :date_to";
    $params[':date_to'] = $dateTo;
}

// Get audit logs
$stmt = $db->prepare("
    SELECT ial.*, 
           gl.cus_name, gl.registration_no
    FROM integration_audit_log ial
    LEFT JOIN isteer_general_lead gl ON ial.lead_id = gl.id
    $whereClause
    ORDER BY ial.data_changed_on DESC
    LIMIT 1000
");

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_changes,
        COUNT(DISTINCT lead_id) as affected_opportunities,
        COUNT(DISTINCT integration_batch_id) as total_batches,
        COUNT(CASE WHEN status = 'ACTIVE' THEN 1 END) as active_changes,
        COUNT(CASE WHEN status = 'REVERTED' THEN 1 END) as reverted_changes
    FROM integration_audit_log ial
    $whereClause
");

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log Reports - Pipeline Manager Integration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { border-bottom: 3px solid #28a745; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { color: #28a745; margin: 0; }
        .btn { background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 2px; border: none; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #545b62; }
        .table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        .table th, .table td { border: 1px solid #dee2e6; padding: 8px; text-align: left; }
        .table th { background: #f8f9fa; font-weight: bold; }
        .stats { display: flex; gap: 15px; flex-wrap: wrap; margin: 20px 0; }
        .stat-card { flex: 1; min-width: 150px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 15px; text-align: center; }
        .stat-number { font-size: 1.5em; font-weight: bold; color: #28a745; margin: 0; }
        .stat-label { color: #666; margin: 5px 0 0 0; font-size: 12px; }
        .filter-form { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 20px; margin: 20px 0; }
        .form-row { display: flex; gap: 15px; flex-wrap: wrap; align-items: end; }
        .form-group { flex: 1; min-width: 150px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #495057; }
        .form-control { width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; }
        .status-active { color: #28a745; font-weight: bold; }
        .status-reverted { color: #dc3545; font-weight: bold; }
        .field-change { background: #e7f3ff; border-left: 4px solid #007bff; padding: 5px; margin: 2px 0; border-radius: 3px; }
        .change-value { font-family: monospace; background: #f8f9fa; padding: 2px 4px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Audit Log Reports</h1>
            <p>Complete audit trail for all integration changes</p>
        </div>

        <a href="../index.php" class="btn btn-secondary">← Back to Dashboard</a>

        <!-- Summary Statistics -->
        <div class="stats">
            <div class="stat-card">
                <p class="stat-number"><?php echo $summary['total_changes']; ?></p>
                <p class="stat-label">Total Changes</p>
            </div>
            <div class="stat-card">
                <p class="stat-number"><?php echo $summary['affected_opportunities']; ?></p>
                <p class="stat-label">Affected Opportunities</p>
            </div>
            <div class="stat-card">
                <p class="stat-number"><?php echo $summary['total_batches']; ?></p>
                <p class="stat-label">Integration Batches</p>
            </div>
            <div class="stat-card">
                <p class="stat-number"><?php echo $summary['active_changes']; ?></p>
                <p class="stat-label">Active Changes</p>
            </div>
            <div class="stat-card">
                <p class="stat-number"><?php echo $summary['reverted_changes']; ?></p>
                <p class="stat-label">Reverted Changes</p>
            </div>
        </div>

        <!-- Search Filters -->
        <div class="filter-form">
            <h3>Search Filters</h3>
            <form method="GET">
                <div class="form-row">
                    <div class="form-group">
                        <label for="lead_id">Lead ID:</label>
                        <input type="number" name="lead_id" id="lead_id" class="form-control" 
                               value="<?php echo htmlspecialchars($leadId); ?>" placeholder="Enter Lead ID">
                    </div>
                    <div class="form-group">
                        <label for="batch_id">Batch ID:</label>
                        <input type="text" name="batch_id" id="batch_id" class="form-control" 
                               value="<?php echo htmlspecialchars($batchId); ?>" placeholder="Enter Batch ID">
                    </div>
                    <div class="form-group">
                        <label for="date_from">Date From:</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" 
                               value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_to">Date To:</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" 
                               value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn">Search</button>
                        <a href="audit_log.php" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Audit Logs Table -->
        <table class="table">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>Lead ID</th>
                    <th>Customer</th>
                    <th>GSTIN</th>
                    <th>Field</th>
                    <th>Old Value</th>
                    <th>New Value</th>
                    <th>Batch ID</th>
                    <th>Updated By</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($auditLogs)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 20px; color: #666;">
                            No audit logs found for the selected criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($auditLogs as $log): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($log['data_changed_on'])); ?></td>
                            <td><?php echo $log['lead_id']; ?></td>
                            <td><?php echo htmlspecialchars($log['cus_name']); ?></td>
                            <td><?php echo htmlspecialchars($log['registration_no']); ?></td>
                            <td><?php echo htmlspecialchars($log['field_name']); ?></td>
                            <td>
                                <span class="change-value">
                                    <?php echo htmlspecialchars(substr($log['old_value'], 0, 50)); ?>
                                    <?php if (strlen($log['old_value']) > 50) echo '...'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="change-value">
                                    <?php echo htmlspecialchars(substr($log['new_value'], 0, 50)); ?>
                                    <?php if (strlen($log['new_value']) > 50) echo '...'; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($log['integration_batch_id']); ?></td>
                            <td><?php echo htmlspecialchars($log['updated_by']); ?></td>
                            <td>
                                <span class="status-<?php echo strtolower($log['status']); ?>">
                                    <?php echo $log['status']; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if (count($auditLogs) >= 1000): ?>
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 4px; margin: 20px 0;">
                <strong>Note:</strong> Results limited to 1000 records. Use filters to narrow down the search.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>