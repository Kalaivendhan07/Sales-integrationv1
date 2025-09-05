<?php
/**
 * Test Up-Sell vs Cross-Sell Logic Verification
 * Verify the correct business logic for product family distinction
 */

require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

echo "=== UP-SELL vs CROSS-SELL LOGIC TEST ===\n";
echo "Testing correct business logic implementation\n\n";

$database = new Database();
$db = $database->getConnection();
$auditLogger = new AuditLogger();
$enhancedEngine = new EnhancedValidationEngine($auditLogger);

// Clean up test data first
$db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no = '29TESTUP1234A1Z'");
$db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '29TESTUP1234A1Z'");

// Setup: Create existing opportunity with Shell Ultra (Mainstream tier)
$stmt = $db->prepare("
    INSERT INTO isteer_general_lead (
        cus_name, registration_no, dsr_name, dsr_id, sector, sub_sector,
        product_name, opportunity_name, lead_status, volume_converted, annual_potential,
        source_from, integration_managed, integration_batch_id, entered_date_time
    ) VALUES (
        'Test Corp Ltd', '29TESTUP1234A1Z', 'Test DSR', 101,
        'Manufacturing', 'Industrial', 'Shell Ultra', 
        'Test Corp Opportunity', 'Qualified', 200.00, 1000.00,
        'CRM System', 1, 'TEST_BATCH', '2025-01-01 10:00:00'
    )
");
$stmt->execute();
$opportunityId = $db->lastInsertId();

// Add SKU record for current tier (Mainstream)
$stmt = $db->prepare("
    INSERT INTO isteer_opportunity_products (
        lead_id, product_id, product_name, volume, status, added_by
    ) VALUES (
        :lead_id, 'SHELL_ULTRA_MAIN', 'Shell Ultra', 200.00, 'A', 'SYSTEM'
    )
");
$stmt->bindParam(':lead_id', $opportunityId);
$stmt->execute();

// Add sales record with Mainstream tier for current tier tracking
$stmt = $db->prepare("
    INSERT INTO isteer_sales_upload_master (
        date, dsr_name, customer_name, cus_sector, product_family_name,
        sku_code, volume, registration_no, tire_type
    ) VALUES (
        '2025-01-01', 'Test DSR', 'Test Corp Ltd', 'Manufacturing', 'Shell Ultra',
        'SHELL_ULTRA_MAIN', 200.00, '29TESTUP1234A1Z5', 'Mainstream'
    )
");
$stmt->execute();

echo "✅ Setup completed: Existing opportunity with Shell Ultra (Mainstream tier)\n\n";

// TEST 1: Same Product Family + Tier Upgrade (Should be UP-SELL)
echo "📋 TEST 1: Same Product Family + Tier Upgrade (Should be UP-SELL)\n";
echo "══════════════════════════════════════════════════════════════════\n";

$salesData1 = array(
    'registration_no' => '29TESTUP1234A1Z',
    'customer_name' => 'Test Corp Ltd',
    'dsr_name' => 'Test DSR',
    'product_family_name' => 'Shell Ultra',  // SAME PRODUCT FAMILY
    'sku_code' => 'SHELL_ULTRA_PREM',
    'volume' => '300.00',
    'sector' => 'Manufacturing',
    'sub_sector' => 'Industrial',
    'tire_type' => 'Premium'  // TIER UPGRADE: Mainstream → Premium
);

echo "Sales Data: Same Product Family (Shell Ultra), Tier: Mainstream → Premium\n";
echo "Expected Result: UP-SELL opportunity created\n\n";

$result1 = $enhancedEngine->validateSalesRecord($salesData1, 'TEST_UPSELL');

echo "Result: " . $result1['status'] . "\n";
if (isset($result1['up_sell_created']) && $result1['up_sell_created']) {
    echo "✅ UP-SELL CREATED: Correct\n";
} else {
    echo "❌ UP-SELL NOT CREATED: Incorrect logic\n";
}

// Count opportunities after Test 1
$stmt = $db->prepare("SELECT COUNT(*) as count FROM isteer_general_lead WHERE registration_no = '29TESTUP1234A1Z5'");
$stmt->execute();
$count1 = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Opportunities after Test 1: " . $count1['count'] . " (Expected: 2)\n\n";

// TEST 2: Different Product Family (Should be CROSS-SELL)
echo "📋 TEST 2: Different Product Family (Should be CROSS-SELL)\n";
echo "══════════════════════════════════════════════════════════════════\n";

$salesData2 = array(
    'registration_no' => '29TESTUP1234A1Z',
    'customer_name' => 'Test Corp Ltd',
    'dsr_name' => 'Test DSR',
    'product_family_name' => 'Shell Premium',  // DIFFERENT PRODUCT FAMILY
    'sku_code' => 'SHELL_PREM_001',
    'volume' => '250.00',
    'sector' => 'Manufacturing',
    'sub_sector' => 'Industrial',
    'tire_type' => 'Mainstream'
);

echo "Sales Data: Different Product Family (Shell Premium vs Shell Ultra)\n";
echo "Expected Result: CROSS-SELL opportunity created\n\n";

$result2 = $enhancedEngine->validateSalesRecord($salesData2, 'TEST_CROSSSELL');

echo "Result: " . $result2['status'] . "\n";
if (isset($result2['cross_sell_created']) && $result2['cross_sell_created']) {
    echo "✅ CROSS-SELL CREATED: Correct\n";
} else {
    echo "❌ CROSS-SELL NOT CREATED: Checking messages...\n";
    if (isset($result2['messages'])) {
        foreach ($result2['messages'] as $message) {
            echo "   - " . $message . "\n";
        }
    }
}

// Count opportunities after Test 2
$stmt = $db->prepare("SELECT COUNT(*) as count FROM isteer_general_lead WHERE registration_no = '29TESTUP1234A1Z5'");
$stmt->execute();
$count2 = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Opportunities after Test 2: " . $count2['count'] . " (Expected: 3)\n\n";

// Show all opportunities created
echo "📊 FINAL OPPORTUNITY BREAKDOWN:\n";
echo "══════════════════════════════════════════════════════════════════\n";
$stmt = $db->prepare("
    SELECT id, cus_name, product_name, opp_type, volume_converted, lead_status 
    FROM isteer_general_lead 
    WHERE registration_no = '29TESTUP1234A1Z5'
    ORDER BY id
");
$stmt->execute();
$opportunities = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($opportunities as $opp) {
    echo "ID: " . $opp['id'] . 
         " | Product: " . $opp['product_name'] . 
         " | Type: " . $opp['opp_type'] . 
         " | Volume: " . $opp['volume_converted'] . "L" .
         " | Stage: " . $opp['lead_status'] . "\n";
}

// Cleanup
echo "\n🧹 Cleaning up test data...\n";
$db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no = '29TESTUP1234A1Z5'");
$db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '29TESTUP1234A1Z5'");
$db->exec("DELETE FROM isteer_opportunity_products WHERE product_id LIKE 'SHELL_%'");
$db->exec("DELETE FROM isteer_sales_upload_master WHERE registration_no = '29TESTUP1234A1Z5'");
$db->exec("DELETE FROM integration_audit_log WHERE integration_batch_id LIKE 'TEST_%'");
echo "✅ Cleanup completed\n";

echo "\n=== TEST SUMMARY ===\n";
echo "Current logic needs to be fixed to match business requirements:\n";
echo "✓ Same Product Family + Tier Upgrade = UP-SELL\n";
echo "✓ Different Product Family = CROSS-SELL\n";
echo "✓ Same Product Family + Same Tier = Volume Addition Only\n";
?>