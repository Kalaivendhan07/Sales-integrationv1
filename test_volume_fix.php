<?php
/**
 * Test Volume Fix - Confirm GSTIN 33AAGCA2111H1ZD creates correct volume
 */

require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

echo "=== VOLUME FIX VERIFICATION TEST ===\n";
echo "Testing GSTIN: 33AAGCA2111H1ZD with 450L sales volume\n\n";

$database = new Database();
$db = $database->getConnection();
$auditLogger = new AuditLogger();
$enhancedEngine = new EnhancedValidationEngine($auditLogger);

// Clean up any existing test data first
$db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no = '33AAGCA2111H1ZD'");
$db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '33AAGCA2111H1ZD'");

// Test sales data
$salesData = array(
    'registration_no' => '33AAGCA2111H1ZD',
    'customer_name' => 'Test Volume Fix Corp',
    'dsr_name' => 'Test DSR',
    'product_family_name' => 'Shell Ultra',
    'sku_code' => 'TEST_SKU',
    'volume' => '450.00',
    'sector' => 'Manufacturing',
    'sub_sector' => 'Automotive',
    'tire_type' => 'Premium'
);

echo "📥 Sales Data:\n";
echo "   Volume: " . $salesData['volume'] . "L\n";
echo "   Expected Result: Opportunity with 450L volume (not doubled)\n\n";

// Process through validation engine
$result = $enhancedEngine->validateSalesRecord($salesData, 'VOLUME_FIX_TEST');

echo "📊 Processing Result:\n";
echo "   Status: " . $result['status'] . "\n";
echo "   Opportunity ID: " . $result['opportunity_id'] . "\n";

if (isset($result['messages'])) {
    echo "   Messages:\n";
    foreach ($result['messages'] as $message) {
        echo "     - " . $message . "\n";
    }
}

// Check the actual opportunity in database  
$stmt = $db->prepare("SELECT volume_converted, annual_potential FROM isteer_general_lead WHERE registration_no = '33AAGCA2111H1ZD'");
$stmt->execute();
$opportunity = $stmt->fetch(PDO::FETCH_ASSOC);

echo "\n🔍 Database Verification:\n";
if ($opportunity) {
    echo "   Volume in DB: " . $opportunity['volume_converted'] . "L\n";
    echo "   Annual Potential: " . $opportunity['annual_potential'] . "L\n";
    
    if ($opportunity['volume_converted'] == '450.00') {
        echo "   ✅ VOLUME FIX CONFIRMED - Correct volume (450L)\n";
    } else {
        echo "   ❌ VOLUME ISSUE - Incorrect volume (" . $opportunity['volume_converted'] . "L)\n";
    }
} else {
    echo "   ❌ No opportunity found in database\n";
}

// Cleanup
$db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no = '33AAGCA2111H1ZD'");
$db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '33AAGCA2111H1ZD'");
$db->exec("DELETE FROM integration_audit_log WHERE integration_batch_id = 'VOLUME_FIX_TEST'");

echo "\n✅ Volume fix verification completed!\n";
?>