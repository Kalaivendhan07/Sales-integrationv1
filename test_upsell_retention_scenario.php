<?php
/**
 * Test Up-Sell Detection for Retention Stage Opportunities
 * Scenario: Existing Retention opportunity with Mainstream tier + New Premium tier sales
 */

require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

echo "=== UP-SELL RETENTION SCENARIO TEST ===\n";
echo "Scenario: Retention stage + Mainstream tier -> New Premium tier sales\n";
echo "Expected: Up-Sell opportunity created\n\n";

$database = new Database();
$db = $database->getConnection();
$auditLogger = new AuditLogger();
$enhancedEngine = new EnhancedValidationEngine($auditLogger);

$testGSTIN = '11UPSELL11H1ZD5';

// Clean up any existing data first
$db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no = '$testGSTIN'");
$db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '$testGSTIN'");

// STEP 1: Setup existing Retention opportunity with Mainstream tier product
echo "🔧 STEP 1: Setting up existing Retention opportunity with Mainstream tier...\n";
echo "════════════════════════════════════════════════════════════════\n";

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
$originalOpportunityId = $db->lastInsertId();

// Add product to opportunity_products with Mainstream tier
$stmt = $db->prepare("
    INSERT INTO isteer_opportunity_products (
        lead_id, product_id, product_name, volume, tier, status, added_by
    ) VALUES (
        ?, 'SHELL_PRO_MAIN', 'Shell Pro', 300.00, 'Mainstream', 'A', 'INTEGRATION_SYSTEM'
    )
");
$stmt->execute([$originalOpportunityId]);

echo "✅ Created existing Retention opportunity:\n";
echo "   Opportunity ID: $originalOpportunityId\n";
echo "   Customer: Up-Sell Test Customer\n";
echo "   GSTIN: $testGSTIN\n";
echo "   Product: Shell Pro (Mainstream tier)\n";
echo "   Stage: Retention\n";
echo "   Volume: 300L\n\n";

// STEP 2: Process incoming Premium tier sales for same product family
echo "📋 STEP 2: Processing Premium tier sales for same product family...\n";
echo "════════════════════════════════════════════════════════════════\n";

$salesData = array(
    'registration_no' => $testGSTIN,
    'customer_name' => 'Up-Sell Test Customer',
    'dsr_name' => 'DSR Alpha',
    'product_family_name' => 'Shell Pro',  // SAME product family
    'sku_code' => 'SHELL_PRO_PREMIUM',
    'volume' => '200.00',  // Premium tier sales
    'sector' => 'Manufacturing',
    'sub_sector' => 'Industrial',
    'tire_type' => 'Premium'  // PREMIUM tier (upgrade from Mainstream)
);

echo "📥 Incoming sales data:\n";
foreach ($salesData as $key => $value) {
    echo "   " . ucfirst(str_replace('_', ' ', $key)) . ": " . $value . "\n";
}
echo "\n";
echo "🔍 EXPECTED LOGIC:\n";
echo "   • Existing: Retention stage + Shell Pro (Mainstream tier)\n";
echo "   • New Sales: Shell Pro (Premium tier)\n";
echo "   • Expected Result: Up-Sell opportunity created\n\n";

// STEP 3: Run through validation engine
echo "🔄 STEP 3: Running through validation engine...\n";
echo "════════════════════════════════════════════════════════════════\n";

$startTime = microtime(true);
$result = $enhancedEngine->validateSalesRecord($salesData, 'UPSELL_TEST_' . time());
$endTime = microtime(true);

echo "📊 VALIDATION ENGINE RESULTS:\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "🎯 Processing Status: " . $result['status'] . "\n";
echo "🆔 Original Opportunity ID: " . $originalOpportunityId . "\n";
echo "⏱️ Processing Time: " . sprintf("%.2f ms", ($endTime - $startTime) * 1000) . "\n";

// Check specific Up-Sell result
if (isset($result['up_sell_created']) && $result['up_sell_created']) {
    echo "✅ Up-Sell Created: YES (SUCCESS!)\n";
} else {
    echo "❌ Up-Sell Created: NO (Logic needs review)\n";
}

if (isset($result['cross_sell_created']) && $result['cross_sell_created']) {
    echo "⚠️ Cross-Sell Created: YES (Should be Up-Sell instead)\n";
} else {
    echo "✅ Cross-Sell Created: NO (Correct - should be Up-Sell)\n";
}

if (isset($result['messages']) && is_array($result['messages'])) {
    echo "📝 System Actions Taken:\n";
    foreach ($result['messages'] as $message) {
        echo "   ✓ " . $message . "\n";
    }
}

echo "\n";

// STEP 4: Show opportunity breakdown
echo "📈 STEP 4: Opportunity breakdown after processing...\n";
echo "════════════════════════════════════════════════════════════════\n";

$stmt = $db->prepare("
    SELECT id, cus_name, registration_no, product_name, 
           lead_status, volume_converted, annual_potential, opp_type
    FROM isteer_general_lead 
    WHERE registration_no = ?
    ORDER BY id
");
$stmt->execute([$testGSTIN]);
$allOpportunities = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalOpportunities = count($allOpportunities);
echo "📊 Total Opportunities: $totalOpportunities\n\n";

foreach ($allOpportunities as $index => $opp) {
    echo "🎯 OPPORTUNITY " . ($index + 1) . ":\n";
    echo "   ID: " . $opp['id'] . "\n";
    echo "   Customer: " . $opp['cus_name'] . "\n";
    echo "   Product: " . $opp['product_name'] . "\n";
    echo "   Type: " . $opp['opp_type'] . "\n";
    echo "   Stage: " . $opp['lead_status'] . "\n";
    echo "   Volume: " . $opp['volume_converted'] . "L\n";
    
    if ($opp['id'] == $originalOpportunityId) {
        echo "   📋 ORIGINAL RETENTION OPPORTUNITY\n";
    } else {
        if ($opp['opp_type'] === 'Up-Sell') {
            echo "   📋 NEW UP-SELL OPPORTUNITY ✅\n";
        } else {
            echo "   📋 NEW " . strtoupper($opp['opp_type']) . " OPPORTUNITY\n";
        }
    }
    echo "\n";
}

// STEP 5: Test result analysis
echo "🔍 STEP 5: Test result analysis...\n";
echo "════════════════════════════════════════════════════════════════\n";

$upSellFound = false;
foreach ($allOpportunities as $opp) {
    if ($opp['opp_type'] === 'Up-Sell') {
        $upSellFound = true;
        break;
    }
}

if ($upSellFound) {
    echo "✅ UP-SELL TEST RESULT: PASSED\n";
    echo "   📋 Up-Sell opportunity correctly created for tier upgrade\n";
    echo "   🎯 Mainstream -> Premium upgrade detected successfully\n";
} else {
    echo "❌ UP-SELL TEST RESULT: FAILED\n";
    echo "   📋 Up-Sell opportunity not created\n";
    echo "   🔍 May have been created as Cross-Sell instead\n";
}

// STEP 6: Cleanup
echo "\n🧹 STEP 6: Cleaning up test data...\n";

$db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no = '$testGSTIN'");
$db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '$testGSTIN'");
$db->exec("DELETE FROM integration_audit_log WHERE integration_batch_id LIKE 'UPSELL_TEST_%'");
$db->exec("DELETE FROM isteer_opportunity_products WHERE product_id IN ('SHELL_PRO_MAIN', 'SHELL_PRO_PREMIUM')");
$db->exec("DELETE FROM volume_discrepancy_tracking WHERE registration_no = '$testGSTIN'");

echo "✅ Cleanup completed\n";

echo "\n" . str_repeat("=", 80) . "\n";
echo "🎯 UP-SELL RETENTION SCENARIO TEST SUMMARY\n";
echo str_repeat("=", 80) . "\n";
echo "SCENARIO: Retention + Mainstream tier -> Premium tier sales\n";
echo "EXPECTED: Up-Sell opportunity creation\n";
echo "RESULT:   " . ($upSellFound ? "PASSED ✅" : "FAILED ❌") . "\n";
echo str_repeat("=", 80) . "\n";

echo "\n✅ Up-Sell retention scenario test completed!\n";
?>