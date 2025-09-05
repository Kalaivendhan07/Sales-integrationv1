<?php
require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

echo "=== UP-SELL vs CROSS-SELL FIXED LOGIC TEST ===\n";

$database = new Database();
$db = $database->getConnection();
$auditLogger = new AuditLogger();
$enhancedEngine = new EnhancedValidationEngine($auditLogger);

$testGSTIN = '29ABCDE1234F1Z5';  // Valid 15-character GSTIN

// Clean up
$db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no = '$testGSTIN'");
$db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '$testGSTIN'");

// Create opportunity with Shell Ultra (Mainstream)
$stmt = $db->prepare("
    INSERT INTO isteer_general_lead (
        cus_name, registration_no, dsr_name, dsr_id, sector, sub_sector,
        product_name, opportunity_name, lead_status, volume_converted, annual_potential,
        source_from, integration_managed, integration_batch_id, entered_date_time
    ) VALUES (
        'Test Corp', ?, 'Test DSR', 101, 'Manufacturing', 'Industrial', 'Shell Ultra', 
        'Test Opportunity', 'Qualified', 200.00, 1000.00,
        'CRM System', 1, 'TEST_BATCH', '2025-01-01 10:00:00'
    )
");
$stmt->execute([$testGSTIN]);
$opportunityId = $db->lastInsertId();

// Add historical sales data for tier tracking
$stmt = $db->prepare("
    INSERT INTO isteer_sales_upload_master (
        date, dsr_name, customer_name, cus_sector, product_family_name,
        sku_code, volume, registration_no, tire_type
    ) VALUES (
        '2025-01-01', 'Test DSR', 'Test Corp', 'Manufacturing', 'Shell Ultra',
        'SHELL_ULTRA_MAIN', 200.00, ?, 'Mainstream'
    )
");
$stmt->execute([$testGSTIN]);

echo "✅ Setup completed with opportunity ID: $opportunityId\n\n";

// TEST 1: Same Product Family + Tier Upgrade (Should be UP-SELL)
echo "📋 TEST 1: Same Product Family + Tier Upgrade\n";
$salesData1 = array(
    'registration_no' => $testGSTIN,
    'customer_name' => 'Test Corp',
    'dsr_name' => 'Test DSR',
    'product_family_name' => 'Shell Ultra',  // SAME PRODUCT FAMILY
    'sku_code' => 'SHELL_ULTRA_PREM',
    'volume' => '300.00',
    'sector' => 'Manufacturing',
    'sub_sector' => 'Industrial',
    'tire_type' => 'Premium'  // TIER UPGRADE
);

$result1 = $enhancedEngine->validateSalesRecord($salesData1, 'TEST_UPSELL');
echo "Result: " . $result1['status'] . "\n";
echo "Up-sell created: " . (isset($result1['up_sell_created']) && $result1['up_sell_created'] ? 'YES ✅' : 'NO ❌') . "\n";

// Count opportunities
$stmt = $db->prepare("SELECT COUNT(*) as count FROM isteer_general_lead WHERE registration_no = ?");
$stmt->execute([$testGSTIN]);
$count = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Opportunities: " . $count['count'] . " (Expected: 2)\n\n";

// TEST 2: Different Product Family (Should be CROSS-SELL)  
echo "📋 TEST 2: Different Product Family\n";
$salesData2 = array(
    'registration_no' => $testGSTIN,
    'customer_name' => 'Test Corp',
    'dsr_name' => 'Test DSR',
    'product_family_name' => 'Shell Premium',  // DIFFERENT PRODUCT FAMILY
    'sku_code' => 'SHELL_PREM_001',
    'volume' => '250.00',
    'sector' => 'Manufacturing',
    'sub_sector' => 'Industrial',
    'tire_type' => 'Mainstream'
);

$result2 = $enhancedEngine->validateSalesRecord($salesData2, 'TEST_CROSSSELL');
echo "Result: " . $result2['status'] . "\n";
echo "Cross-sell created: " . (isset($result2['cross_sell_created']) && $result2['cross_sell_created'] ? 'YES ✅' : 'NO ❌') . "\n";

// Final count
$stmt->execute([$testGSTIN]);
$finalCount = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Final opportunities: " . $finalCount['count'] . " (Expected: 3)\n\n";

// Show results
echo "📊 RESULTS:\n";
$stmt = $db->prepare("SELECT product_name, opp_type, volume_converted FROM isteer_general_lead WHERE registration_no = ? ORDER BY id");
$stmt->execute([$testGSTIN]);
$opportunities = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($opportunities as $opp) {
    echo "  - Product: " . $opp['product_name'] . " | Type: " . $opp['opp_type'] . " | Volume: " . $opp['volume_converted'] . "L\n";
}

// Cleanup
$db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no = '$testGSTIN'");
$db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '$testGSTIN'");
$db->exec("DELETE FROM isteer_sales_upload_master WHERE registration_no = '$testGSTIN'");

echo "\n✅ Test completed!\n";
?>
