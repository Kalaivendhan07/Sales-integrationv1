<?php
require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

echo "=== UP-SELL DEBUG - DETAILED ANALYSIS ===\n";

$database = new Database();
$db = $database->getConnection();

$testGSTIN = '11AAGCA2111H1ZD';

// Setup test data
$db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no = '$testGSTIN'");
$db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '$testGSTIN'");

$stmt = $db->prepare("
    INSERT INTO isteer_general_lead (
        cus_name, registration_no, dsr_name, dsr_id, sector, sub_sector,
        product_name, opportunity_name, lead_status, volume_converted, annual_potential,
        source_from, integration_managed, integration_batch_id, entered_date_time
    ) VALUES (
        'Up-Sell Test Customer', ?, 'DSR Alpha', 1, 'Manufacturing', 'Industrial', 
        'Shell Pro', 'Up-Sell Test Opportunity', 
        'Retention', 300.00, 1000.00,
        'CRM System', 1, 'UPSELL_TEST_BATCH', '2025-01-01 10:00:00'
    )
");
$stmt->execute([$testGSTIN]);
$opportunityId = $db->lastInsertId();

$stmt = $db->prepare("
    INSERT INTO isteer_opportunity_products (
        lead_id, product_id, product_name, volume, tier, status, added_by
    ) VALUES (
        ?, 'SHELL_PRO_MAIN', 'Shell Pro', 300.00, 'Mainstream', 'A', 'INTEGRATION_SYSTEM'
    )
");
$stmt->execute([$opportunityId]);

echo "✅ Test setup completed - Opportunity ID: $opportunityId\n\n";

// Test validation with debug
$salesData = array(
    'registration_no' => $testGSTIN,
    'customer_name' => 'Up-Sell Test Customer',
    'dsr_name' => 'DSR Alpha',
    'product_family_name' => 'Shell Pro',
    'sku_code' => 'SHELL_PRO_PREMIUM',
    'volume' => '200.00',
    'sector' => 'Manufacturing',
    'sub_sector' => 'Industrial',
    'tire_type' => 'Premium'
);

echo "🔍 TESTING VALIDATION WITH DETAILED MESSAGES:\n";

try {
    $auditLogger = new AuditLogger();
    $engine = new EnhancedValidationEngine($auditLogger);
    $result = $engine->validateSalesRecord($salesData, 'DEBUG_UPSELL');
    
    echo "Status: " . $result['status'] . "\n";
    echo "Opportunity ID: " . (isset($result['opportunity_id']) ? $result['opportunity_id'] : 'None') . "\n";
    
    echo "Result keys: " . implode(', ', array_keys($result)) . "\n";
    
    if (isset($result['messages']) && is_array($result['messages'])) {
        echo "Messages (" . count($result['messages']) . "):\n";
        foreach ($result['messages'] as $i => $message) {
            echo "  " . ($i+1) . ". " . $message . "\n";
        }
    } else if (isset($result['message'])) {
        echo "Single message: " . $result['message'] . "\n";
    }
    
    if (isset($result['up_sell_created'])) {
        echo "Up-Sell Created: " . ($result['up_sell_created'] ? 'YES' : 'NO') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

// Cleanup
$db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no = '$testGSTIN'");
$db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '$testGSTIN'");

echo "\n=== DEBUG COMPLETED ===\n";
?>