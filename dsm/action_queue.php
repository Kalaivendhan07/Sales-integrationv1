<?php
/**
 * DSM Action Queue Management Interface
 * PHP 5.3 Compatible
 */

require_once __DIR__ . '/../config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

$message = '';
$messageType = '';

// Handle action resolution
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_id'])) {
    try {
        $actionId = $_POST['action_id'];
        $resolution = $_POST['resolution'];
        $resolutionData = array();
        
        // Get action details
        $stmt = $db->prepare("SELECT * FROM dsm_action_queue WHERE action_id = :action_id");
        $stmt->bindParam(':action_id', $actionId);
        $stmt->execute();
        $action = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$action) {
            throw new Exception('Action not found');
        }
        
        // Process resolution based on mismatch type
        if ($action['mismatch_type'] == 'DSR_MISMATCH') {
            $salesData = json_decode($action['sales_data'], true);
            $opportunityData = json_decode($action['opportunity_data'], true);
            
            if ($resolution == 'keep_opportunity_dsr') {
                $resolutionData['action'] = 'Keep Opportunity DSR';
                $resolutionData['dsr_name'] = $opportunityData['dsr_name'];
            } else if ($resolution == 'assign_sales_dsr') {
                $resolutionData['action'] = 'Assign Sales DSR';
                $resolutionData['dsr_name'] = $salesData['dsr_name'];
                
                // Update opportunity with sales DSR
                $stmt = $db->prepare("UPDATE isteer_general_lead SET dsr_name = :dsr_name WHERE registration_no = :gstin");
                $stmt->bindParam(':dsr_name', $salesData['dsr_name']);
                $stmt->bindParam(':gstin', $action['registration_no']);
                $stmt->execute();
            }
        }
        
        // Mark action as completed
        $stmt = $db->prepare("
            UPDATE dsm_action_queue 
            SET status = 'COMPLETED', resolved_at = NOW(), resolution = :resolution 
            WHERE action_id = :action_id
        ");
        $stmt->bindParam(':resolution', json_encode($resolutionData));
        $stmt->bindParam(':action_id', $actionId);
        $stmt->execute();
        
        $message = 'Action resolved successfully!';
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = 'Error resolving action: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get pending actions
$stmt = $db->prepare("
    SELECT aq.*, 
           gl.cus_name as opportunity_customer_name,
           gl.lead_status as opportunity_stage
    FROM dsm_action_queue aq
    LEFT JOIN isteer_general_lead gl ON aq.registration_no = gl.registration_no AND gl.status = 'A'
    WHERE aq.status = 'PENDING'
    ORDER BY aq.priority DESC, aq.created_at ASC
");
$stmt->execute();
$pendingActions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get completed actions (last 50)
$stmt = $db->prepare("
    SELECT aq.*, 
           gl.cus_name as opportunity_customer_name
    FROM dsm_action_queue aq
    LEFT JOIN isteer_general_lead gl ON aq.registration_no = gl.registration_no AND gl.status = 'A'
    WHERE aq.status = 'COMPLETED'
    ORDER BY aq.resolved_at DESC
    LIMIT 50
");
$stmt->execute();
$completedActions = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DSM Action Queue - Pipeline Manager Integration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { border-bottom: 3px solid #ffc107; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { color: #ffc107; margin: 0; }
        .btn { background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 2px; border: none; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #0056b3; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #545b62; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #1e7e34; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
        .table th, .table td { border: 1px solid #dee2e6; padding: 10px; text-align: left; vertical-align: top; }
        .table th { background: #f8f9fa; font-weight: bold; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 4px; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .alert-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .priority-high { background: #ffebee; }
        .priority-medium { background: #fff3e0; }
        .priority-low { background: #f3e5f5; }
        .action-form { background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 10px 0; }
        .form-group { margin: 10px 0; }
        .form-control { padding: 8px; border: 1px solid #ced4da; border-radius: 4px; width: 200px; }
        .json-data { background: #e9ecef; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 150px; overflow-y: auto; }
        .tabs { border-bottom: 1px solid #dee2e6; margin-bottom: 20px; }
        .tab { background: none; border: none; padding: 10px 20px; cursor: pointer; border-bottom: 3px solid transparent; }
        .tab.active { border-bottom-color: #ffc107; font-weight: bold; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
    <script>
        function showTab(tabName) {
            // Hide all tab contents
            var contents = document.getElementsByClassName('tab-content');
            for (var i = 0; i < contents.length; i++) {
                contents[i].classList.remove('active');
            }
            
            // Remove active class from all tabs
            var tabs = document.getElementsByClassName('tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            
            // Show selected tab content and mark tab as active
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function confirmAction(actionId, actionType) {
            return confirm('Are you sure you want to resolve this ' + actionType + ' action?');
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>DSM Action Queue</h1>
            <p>Manual resolution required for sales-opportunity integration mismatches</p>
        </div>

        <a href="../index.php" class="btn btn-secondary">← Back to Dashboard</a>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('pending-tab')">
                Pending Actions (<?php echo count($pendingActions); ?>)
            </button>
            <button class="tab" onclick="showTab('completed-tab')">
                Completed Actions (<?php echo count($completedActions); ?>)
            </button>
        </div>

        <!-- Pending Actions Tab -->
        <div id="pending-tab" class="tab-content active">
            <?php if (empty($pendingActions)): ?>
                <div class="alert alert-info">
                    <strong>No pending actions!</strong> All integration mismatches have been resolved.
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Priority</th>
                            <th>GSTIN</th>
                            <th>Mismatch Type</th>
                            <th>Customer</th>
                            <th>Action Required</th>
                            <th>Created</th>
                            <th>Sales Data</th>
                            <th>Opportunity Data</th>
                            <th>Resolution</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingActions as $action): ?>
                            <tr class="priority-<?php echo strtolower($action['priority']); ?>">
                                <td>
                                    <span class="btn btn-<?php echo $action['priority'] == 'HIGH' ? 'danger' : ($action['priority'] == 'MEDIUM' ? 'warning' : 'secondary'); ?>">
                                        <?php echo $action['priority']; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($action['registration_no']); ?></td>
                                <td><?php echo htmlspecialchars($action['mismatch_type']); ?></td>
                                <td><?php echo htmlspecialchars($action['opportunity_customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($action['action_required']); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($action['created_at'])); ?></td>
                                <td>
                                    <div class="json-data">
                                        <?php 
                                        $salesData = json_decode($action['sales_data'], true);
                                        echo "Customer: " . htmlspecialchars($salesData['customer_name']) . "<br>";
                                        echo "DSR: " . htmlspecialchars($salesData['dsr_name']) . "<br>";
                                        echo "Volume: " . htmlspecialchars($salesData['volume']) . "<br>";
                                        echo "Invoice: " . htmlspecialchars($salesData['invoice_no']);
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="json-data">
                                        <?php 
                                        if ($action['opportunity_data']) {
                                            $opportunityData = json_decode($action['opportunity_data'], true);
                                            echo "Customer: " . htmlspecialchars($opportunityData['cus_name']) . "<br>";
                                            echo "DSR: " . htmlspecialchars($opportunityData['dsr_name']) . "<br>";
                                            echo "Stage: " . htmlspecialchars($action['opportunity_stage']) . "<br>";
                                            echo "Volume: " . htmlspecialchars($opportunityData['volume_converted']);
                                        } else {
                                            echo "New opportunity";
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($action['mismatch_type'] == 'DSR_MISMATCH'): ?>
                                        <div class="action-form">
                                            <form method="POST" onsubmit="return confirmAction(<?php echo $action['action_id']; ?>, 'DSR mismatch')">
                                                <input type="hidden" name="action_id" value="<?php echo $action['action_id']; ?>">
                                                <div class="form-group">
                                                    <label>Choose DSR:</label><br>
                                                    <label>
                                                        <input type="radio" name="resolution" value="keep_opportunity_dsr" required>
                                                        Keep Opportunity DSR
                                                    </label><br>
                                                    <label>
                                                        <input type="radio" name="resolution" value="assign_sales_dsr" required>
                                                        Assign Sales DSR
                                                    </label>
                                                </div>
                                                <button type="submit" class="btn btn-success">Resolve</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <div class="action-form">
                                            <form method="POST" onsubmit="return confirmAction(<?php echo $action['action_id']; ?>, '<?php echo $action['mismatch_type']; ?>')">
                                                <input type="hidden" name="action_id" value="<?php echo $action['action_id']; ?>">
                                                <input type="hidden" name="resolution" value="manual_resolved">
                                                <button type="submit" class="btn btn-success">Mark Resolved</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Completed Actions Tab -->
        <div id="completed-tab" class="tab-content">
            <?php if (empty($completedActions)): ?>
                <div class="alert alert-info">
                    No completed actions found.
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>GSTIN</th>
                            <th>Mismatch Type</th>
                            <th>Customer</th>
                            <th>Action Required</th>
                            <th>Resolved</th>
                            <th>Resolution</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($completedActions as $action): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($action['registration_no']); ?></td>
                                <td><?php echo htmlspecialchars($action['mismatch_type']); ?></td>
                                <td><?php echo htmlspecialchars($action['opportunity_customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($action['action_required']); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($action['resolved_at'])); ?></td>
                                <td>
                                    <div class="json-data">
                                        <?php 
                                        if ($action['resolution']) {
                                            $resolution = json_decode($action['resolution'], true);
                                            foreach ($resolution as $key => $value) {
                                                echo htmlspecialchars($key) . ": " . htmlspecialchars($value) . "<br>";
                                            }
                                        } else {
                                            echo "Manual resolution";
                                        }
                                        ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="alert alert-info">
            <strong>DSM Action Queue Help:</strong><br>
            • <strong>High Priority:</strong> Critical mismatches requiring immediate attention<br>
            • <strong>Medium Priority:</strong> Standard validation conflicts<br>
            • <strong>Low Priority:</strong> Minor data inconsistencies<br>
            • <strong>DSR Mismatch:</strong> Choose whether to keep existing opportunity DSR or assign sales DSR<br>
            • Actions expire automatically after 30 days if not resolved
        </div>
    </div>
</body>
</html>