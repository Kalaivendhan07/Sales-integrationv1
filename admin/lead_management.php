<?php
/**
 * Lead Management Interface with Business Rule Enforcement
 * PHP 5.3 Compatible
 */

require_once __DIR__ . '/../classes/LeadManager.php';

$leadManager = new LeadManager();
$message = '';
$messageType = '';

// Handle lead operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $action = $_POST['action'];
        $leadId = isset($_POST['lead_id']) ? $_POST['lead_id'] : '';
        
        switch ($action) {
            case 'delete_lead':
                $result = $leadManager->deleteLead($leadId, 'Admin');
                $message = $result['message'];
                $messageType = 'success';
                break;
                
            case 'update_status':
                $newStatus = $_POST['new_status'];
                $result = $leadManager->updateLeadStatus($leadId, $newStatus, 'Admin');
                $message = $result['message'];
                if (isset($result['warning'])) {
                    $message .= '<br><strong>Warning:</strong> ' . $result['warning'];
                }
                $messageType = 'success';
                break;
                
            case 'validate_operation':
                $operation = $_POST['operation'];
                $params = array();
                if (isset($_POST['new_status'])) $params['new_status'] = $_POST['new_status'];
                if (isset($_POST['field_name'])) $params['field_name'] = $_POST['field_name'];
                $params['updated_by'] = 'Admin';
                
                $validation = $leadManager->validateOperation($operation, $leadId, $params);
                
                if ($validation['allowed']) {
                    $message = 'Operation allowed: ' . $operation;
                    $messageType = 'success';
                } else {
                    $message = 'Operation blocked: ' . $validation['reason'];
                    $messageType = 'danger';
                }
                break;
        }
        
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get lead management summary
$summary = $leadManager->getLeadManagementSummary();

// Get integration-managed leads
$integrationLeads = $leadManager->getIntegrationManagedLeads(20);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Management - Pipeline Manager Integration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { border-bottom: 3px solid #17a2b8; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { color: #17a2b8; margin: 0; }
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
        .btn-info { background: #17a2b8; }
        .btn-info:hover { background: #138496; }
        .table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        .table th, .table td { border: 1px solid #dee2e6; padding: 8px; text-align: left; }
        .table th { background: #f8f9fa; font-weight: bold; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 4px; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .alert-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .alert-warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .stats { display: flex; gap: 15px; flex-wrap: wrap; margin: 20px 0; }
        .stat-card { flex: 1; min-width: 150px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 15px; text-align: center; }
        .stat-number { font-size: 1.5em; font-weight: bold; color: #17a2b8; margin: 0; }
        .stat-label { color: #666; margin: 5px 0 0 0; font-size: 12px; }
        .card { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 20px; margin: 15px 0; }
        .integration-badge { background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .manual-badge { background: #6c757d; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .protected-badge { background: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .form-inline { display: flex; gap: 10px; align-items: center; margin: 10px 0; }
        .form-control { padding: 5px; border: 1px solid #ced4da; border-radius: 4px; }
    </style>
    <script>
        function confirmDelete(leadId, customerName) {
            return confirm('Are you sure you want to delete the lead for "' + customerName + '"?\n\nNote: Integration-managed leads cannot be deleted and will show an error.');
        }
        
        function testOperation(operation, leadId) {
            document.getElementById('test_operation').value = operation;
            document.getElementById('test_lead_id').value = leadId;
            document.getElementById('test_form').submit();
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Lead Management & Business Rules</h1>
            <p>Manage leads with integration business rule enforcement</p>
        </div>

        <a href="../index.php" class="btn btn-secondary">← Back to Dashboard</a>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Lead Management Summary -->
        <div class="card">
            <h3>Lead Management Summary</h3>
            <div class="stats">
                <div class="stat-card">
                    <p class="stat-number"><?php echo $summary['total_active_leads']; ?></p>
                    <p class="stat-label">Total Active Leads</p>
                </div>
                <div class="stat-card">
                    <p class="stat-number"><?php echo $summary['integration_managed_leads']; ?></p>
                    <p class="stat-label">Integration Managed</p>
                </div>
                <div class="stat-card">
                    <p class="stat-number"><?php echo $summary['manual_leads']; ?></p>
                    <p class="stat-label">Manual Leads</p>
                </div>
                <div class="stat-card">
                    <p class="stat-number"><?php echo $summary['order_status_leads']; ?></p>
                    <p class="stat-label">Order Status</p>
                </div>
                <div class="stat-card">
                    <p class="stat-number"><?php echo $summary['protected_leads']; ?></p>
                    <p class="stat-label">Protected (No Delete)</p>
                </div>
            </div>
        </div>

        <!-- Business Rules Information -->
        <div class="alert alert-info">
            <h4>Business Rule Enforcement:</h4>
            <ul>
                <li><strong>🔒 Deletion Protection:</strong> Integration-managed leads cannot be deleted</li>
                <li><strong>📋 Status Restrictions:</strong> Only integration system can set leads to "Order" status</li>
                <li><strong>🔄 Field Protection:</strong> Critical fields (GSTIN, Volume, etc.) can only be updated by integration</li>
                <li><strong>⚠️ Manual Override Warnings:</strong> Manual changes to certain fields may be overridden</li>
            </ul>
        </div>

        <!-- Test Business Rules -->
        <div class="card">
            <h3>Test Business Rule Validation</h3>
            <p>Test various operations to see business rule enforcement in action:</p>
            
            <form id="test_form" method="POST" style="display: none;">
                <input type="hidden" name="action" value="validate_operation">
                <input type="hidden" name="operation" id="test_operation">
                <input type="hidden" name="lead_id" id="test_lead_id">
                <input type="hidden" name="new_status" value="Order">
                <input type="hidden" name="field_name" value="registration_no">
            </form>
            
            <div class="form-inline">
                <button type="button" class="btn btn-warning" onclick="testOperation('delete', '1')">
                    Test Delete Protection
                </button>
                <button type="button" class="btn btn-warning" onclick="testOperation('update_status', '1')">
                    Test Status Change to Order
                </button>
                <button type="button" class="btn btn-warning" onclick="testOperation('update_field', '1')">
                    Test GSTIN Field Update
                </button>
            </div>
        </div>

        <!-- Integration-Managed Leads -->
        <div class="card">
            <h3>Integration-Managed Leads (Protected)</h3>
            
            <?php if (empty($integrationLeads)): ?>
                <div class="alert alert-info">
                    No integration-managed leads found.
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>GSTIN</th>
                            <th>DSR</th>
                            <th>Status</th>
                            <th>Volume</th>
                            <th>Batch ID</th>
                            <th>Last Integration Update</th>
                            <th>Protection Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($integrationLeads as $lead): ?>
                            <tr>
                                <td><?php echo $lead['id']; ?></td>
                                <td><?php echo htmlspecialchars($lead['cus_name']); ?></td>
                                <td><?php echo htmlspecialchars($lead['registration_no']); ?></td>
                                <td><?php echo htmlspecialchars($lead['dsr_name']); ?></td>
                                <td>
                                    <span class="integration-badge"><?php echo $lead['lead_status']; ?></span>
                                </td>
                                <td><?php echo number_format($lead['volume_converted'], 2); ?></td>
                                <td><?php echo htmlspecialchars($lead['integration_batch_id']); ?></td>
                                <td><?php echo $lead['last_integration_update'] ? date('Y-m-d H:i', strtotime($lead['last_integration_update'])) : 'N/A'; ?></td>
                                <td>
                                    <span class="protected-badge">PROTECTED</span>
                                </td>
                                <td>
                                    <!-- Test delete protection -->
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_lead">
                                        <input type="hidden" name="lead_id" value="<?php echo $lead['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" 
                                                onclick="return confirmDelete(<?php echo $lead['id']; ?>, '<?php echo htmlspecialchars($lead['cus_name']); ?>')">
                                            Try Delete
                                        </button>
                                    </form>
                                    
                                    <!-- Test status change -->
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="lead_id" value="<?php echo $lead['id']; ?>">
                                        <select name="new_status" class="form-control" style="width: auto;">
                                            <option value="Prospecting">Prospecting</option>
                                            <option value="Qualified">Qualified</option>
                                            <option value="Order">Order (Restricted)</option>
                                        </select>
                                        <button type="submit" class="btn btn-info btn-sm">
                                            Update Status
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Lead Status Rules -->
        <div class="alert alert-warning">
            <h4>Lead Status Rules:</h4>
            <ul>
                <li><strong>Order Status:</strong> Reserved for integration system only - represents actual sales transactions</li>
                <li><strong>Manual Status Changes:</strong> Users can change between Prospecting, Qualified, Proposal, Negotiation</li>
                <li><strong>Integration Override:</strong> Integration system can change any status based on sales data</li>
                <li><strong>Business Logic:</strong> SPANCOP/Lost/Sleep → Order when sales detected</li>
            </ul>
        </div>
    </div>
</body>
</html>