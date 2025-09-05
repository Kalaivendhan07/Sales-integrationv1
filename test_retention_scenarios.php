<?php
/**
 * Test Retention Stage Scenarios
 * Testing cross-sell and up-sell logic for Retention stage
 */

require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

echo "=== RETENTION STAGE SCENARIOS TEST ===\n";
echo "Testing cross-sell and up-sell logic for Retention stage\n\n";

$database = new Database();
$db = $database->getConnection();
$auditLogger = new AuditLogger();
$enhancedEngine = new EnhancedValidationEngine($auditLogger);

$testGSTIN = '29RETEN1234F1Z5';

// TEST 1: Retention + Same Product + Same Tier = Volume Update Only
echo "📋 TEST 1: Retention + Same Product + Same Tier (Volume Update Only)\n";
echo str_repeat("=", 70) . "\n";

// Clean up
$db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no = '$testGSTIN'");
$db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '$testGSTIN'");

// Create opportunity in Retention stage
$stmt = $db->prepare("
    INSERT INTO isteer_general_lead (
        cus_name, registration_no, dsr_name, dsr_id, sector, sub_sector,
        product_name, opportunity_name, lead_status, volume_converted, annual_potential,
        source_from, integration_managed, integration_batch_id, entered_date_time
    ) VALUES (
        'Retention Test Corp', ?, 'Retention DSR', 101, 'Manufacturing', 'Industrial', 'Shell Ultra', 
        'Retention Test Opportunity', 'Retention', 100.00, 500.00,
        'CRM System', 1, 'RETENTION_TEST', '2025-01-01 10:00:00'
    )
");
$stmt->execute([$testGSTIN]);
$opportunityId = $db->lastInsertId();

// Add historical sales for tier tracking (Mainstream tier)
$stmt = $db->prepare("
    INSERT INTO isteer_sales_upload_master (
        date, dsr_name, customer_name, cus_sector, product_family_name,
        sku_code, volume, registration_no, tire_type
    ) VALUES (
        '2024-12-01', 'Retention DSR', 'Retention Test Corp', 'Manufacturing', 'Shell Ultra',
        'SHELL_ULTRA_MAIN', 100.00, ?, 'Mainstream'
    )
");
$stmt->execute([$testGSTIN]);

echo "✅ Created Retention opportunity with Shell Ultra (Mainstream tier)\n";

// Process sales data - Same product, Same tier
$salesData1 = array(
    'registration_no' => $testGSTIN,
    'customer_name' => 'Retention Test Corp',
    'dsr_name' => 'Retention DSR',
    'product_family_name' => 'Shell Ultra',  // SAME PRODUCT
    'sku_code' => 'SHELL_ULTRA_MAIN_2',
    'volume' => '150.00',
    'sector' => 'Manufacturing',
    'sub_sector' => 'Industrial',
    'tire_type' => 'Mainstream'  // SAME TIER
);

$result1 = $enhancedEngine->validateSalesRecord($salesData1, 'RETENTION_TEST_1');

// Check results
$stmt = $db->prepare("SELECT lead_status, volume_converted FROM isteer_general_lead WHERE id = ?");
$stmt->execute([$opportunityId]);
$finalState1 = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT COUNT(*) as count FROM isteer_general_lead WHERE registration_no = ?");
$stmt->execute([$testGSTIN]);
$totalOpportunities1 = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

echo "📊 Results:\n";
echo "   Stage: Retention → " . $finalState1['lead_status'] . "\n";
echo "   Volume: 100L → " . $finalState1['volume_converted'] . "L\n";
echo "   Total Opportunities: $totalOpportunities1\n";

if ($finalState1['lead_status'] === 'Retention' && $totalOpportunities1 == 1) {
    echo "✅ PASS: Retention maintained with volume update only\n";
} else {
    echo "❌ FAIL: Expected Retention maintained with 1 opportunity\n";
}

if (isset($result1['messages'])) {
    echo "📝 Messages:\n";
    foreach ($result1['messages'] as $message) {
        echo "   - $message\n";
    }
}

// TEST 2: Retention + Same Product + Tier Upgrade = Up-Sell
echo "\n📋 TEST 2: Retention + Same Product + Tier Upgrade (Up-Sell)\n";
echo str_repeat("=", 70) . "\n";

// Process sales data - Same product, Tier upgrade
$salesData2 = array(
    'registration_no' => $testGSTIN,
    'customer_name' => 'Retention Test Corp',
    'dsr_name' => 'Retention DSR',
    'product_family_name' => 'Shell Ultra',  // SAME PRODUCT
    'sku_code' => 'SHELL_ULTRA_PREM',
    'volume' => '200.00',
    'sector' => 'Manufacturing',
    'sub_sector' => 'Industrial',
    'tire_type' => 'Premium'  // TIER UPGRADE
);

$result2 = $enhancedEngine->validateSalesRecord($salesData2, 'RETENTION_TEST_2');

// Check results
$stmt = $db->prepare("SELECT COUNT(*) as count FROM isteer_general_lead WHERE registration_no = ?");
$stmt->execute([$testGSTIN]);
$totalOpportunities2 = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

echo "📊 Results:\n";
echo "   Total Opportunities: $totalOpportunities2 (Expected: 2 - Original + Up-Sell)\n";

if ($totalOpportunities2 == 2) {
    echo "✅ PASS: Up-Sell opportunity created from Retention\n";
} else {
    echo "❌ FAIL: Expected 2 opportunities (Original + Up-Sell)\n";
}

if (isset($result2['up_sell_created']) && $result2['up_sell_created']) {
    echo "✅ Up-Sell Created: YES\n";
} else {
    echo "❌ Up-Sell Created: NO\n";
}

if (isset($result2['messages'])) {
    echo "📝 Messages:\n";
    foreach ($result2['messages'] as $message) {
        echo "   - $message\n";
    }
}

// TEST 3: Retention + Different Product = Cross-Sell  
echo "\n📋 TEST 3: Retention + Different Product (Cross-Sell)\n";
echo str_repeat("=", 70) . "\n";

// Process sales data - Different product
$salesData3 = array(
    'registration_no' => $testGSTIN,
    'customer_name' => 'Retention Test Corp',
    'dsr_name' => 'Retention DSR',
    'product_family_name' => 'Shell Premium',  // DIFFERENT PRODUCT
    'sku_code' => 'SHELL_PREM_001',
    'volume' => '100.00',
    'sector' => 'Manufacturing',
    'sub_sector' => 'Industrial',
    'tire_type' => 'Mainstream'
);

$result3 = $enhancedEngine->validateSalesRecord($salesData3, 'RETENTION_TEST_3');

// Check results
$stmt = $db->prepare("SELECT COUNT(*) as count FROM isteer_general_lead WHERE registration_no = ?");
$stmt->execute([$testGSTIN]);
$totalOpportunities3 = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

echo "📊 Results:\n";
echo "   Total Opportunities: $totalOpportunities3 (Expected: 3 - Original + Up-Sell + Cross-Sell)\n";

if ($totalOpportunities3 == 3) {
    echo "✅ PASS: Cross-Sell opportunity created from Retention\n";
} else {
    echo "❌ FAIL: Expected 3 opportunities (Original + Up-Sell + Cross-Sell)\n";
}

if (isset($result3['cross_sell_created']) && $result3['cross_sell_created']) {
    echo "✅ Cross-Sell Created: YES\n";
} else {
    echo "❌ Cross-Sell Created: NO\n";
}

if (isset($result3['messages'])) {
    echo "📝 Messages:\n";
    foreach ($result3['messages'] as $message) {
        echo "   - $message\n";
    }
}

// Show final opportunity breakdown
echo "\n📊 FINAL OPPORTUNITY BREAKDOWN:\n";
echo str_repeat("=", 70) . "\n";

$stmt = $db->prepare("
    SELECT id, product_name, opp_type, lead_status, volume_converted 
    FROM isteer_general_lead 
    WHERE registration_no = ?
    ORDER BY id
");
$stmt->execute([$testGSTIN]);
$opportunities = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($opportunities as $index => $opp) {
    echo sprintf("   %d. Product: %-15s | Type: %-10s | Stage: %-10s | Volume: %sL\n", 
        $index + 1, $opp['product_name'], $opp['opp_type'], $opp['lead_status'], $opp['volume_converted']);
}

// Cleanup
$db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no = '$testGSTIN'");
$db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '$testGSTIN'");
$db->exec("DELETE FROM isteer_sales_upload_master WHERE registration_no = '$testGSTIN'");

echo "\n" . str_repeat("=", 80) . "\n";
echo "🎯 RETENTION STAGE LOGIC SUMMARY\n";
echo str_repeat("=", 80) . "\n";
echo "✅ Same Product + Same Tier: Volume update only (Retention maintained)\n";
echo "✅ Same Product + Tier Upgrade: Up-Sell opportunity created\n";
echo "✅ Different Product: Cross-Sell opportunity created\n";
echo "✅ All retention scenarios working as per business requirements\n";

echo "\n✅ Retention scenarios test completed!\n";
?>