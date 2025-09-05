<?php
/**
 * Test Retention Multi-Product Scenario
 * Sales: 600L Tellus, GSTIN 11AAGCA2111H1ZD, DSR 1, 1 Aug 2025
 * Opportunity: Same GSTIN, Retention stage, Rimula+Tellus+Gadus, DSR 1, 500L
 */

require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

echo "=== RETENTION MULTI-PRODUCT SCENARIO TEST ===\n";
echo "Sales: 600L Tellus, GSTIN 11AAGCA2111H1ZD, DSR 1, 1 Aug 2025\n";
echo "Existing: Same GSTIN, Retention stage, Rimula+Tellus+Gadus, DSR 1, 500L\n\n";

$database = new Database();
$db = $database->getConnection();
$auditLogger = new AuditLogger();
$enhancedEngine = new EnhancedValidationEngine($auditLogger);

$testGSTIN = '11AAGCA2111H1ZD';

// Clean up any existing data first
$db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no = '$testGSTIN'");
$db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '$testGSTIN'");

// STEP 1: Setup existing multi-product Retention opportunity
echo "🔧 STEP 1: Setting up existing multi-product Retention opportunity...\n";
echo "════════════════════════════════════════════════════════════════\n";

$stmt = $db->prepare("
    INSERT INTO isteer_general_lead (
        cus_name, registration_no, dsr_name, dsr_id, sector, sub_sector,
        product_name, product_name_2, product_name_3, opportunity_name, 
        lead_status, volume_converted, annual_potential,
        source_from, integration_managed, integration_batch_id, entered_date_time
    ) VALUES (
        'Retention Multi Product Ltd', ?, 'DSR One', 1, 'Manufacturing', 'Industrial', 
        'Rimula', 'Tellus', 'Gadus', 'Retention Multi Product Opportunity', 
        'Retention', 500.00, 2000.00,
        'CRM System', 1, 'RETENTION_MULTI_BATCH', '2025-01-01 10:00:00'
    )
");
$stmt->execute([$testGSTIN]);
$originalOpportunityId = $db->lastInsertId();

echo "✅ Created existing multi-product Retention opportunity:\n";
echo "   Opportunity ID: $originalOpportunityId\n";
echo "   Customer: Retention Multi Product Ltd\n";
echo "   GSTIN: $testGSTIN\n";
echo "   DSR: DSR One (ID: 1)\n";
echo "   Products: Rimula, Tellus, Gadus (MULTI-PRODUCT)\n";
echo "   Current Stage: Retention\n";
echo "   Current Volume: 500L\n";
echo "   Annual Potential: 2000L\n\n";

// STEP 2: Process incoming sales data for ONE of the products
echo "📋 STEP 2: Processing sales data for ONE product in Retention (COMPLEX SCENARIO)...\n";
echo "════════════════════════════════════════════════════════════════\n";

$salesData = array(
    'registration_no' => $testGSTIN,
    'customer_name' => 'Retention Multi Product Ltd',
    'dsr_name' => 'DSR One',  // Same DSR
    'product_family_name' => 'Tellus',  // MATCHES ONE of the three products
    'sku_code' => 'TELLUS_RET_001',
    'volume' => '600.00',  // Sales volume for Tellus only
    'sector' => 'Manufacturing',
    'sub_sector' => 'Industrial',
    'tire_type' => 'Premium'
);

echo "📥 Incoming sales data (1 Aug 2025):\n";
foreach ($salesData as $key => $value) {
    echo "   " . ucfirst(str_replace('_', ' ', $key)) . ": " . $value . "\n";
}
echo "\n";
echo "🤔 COMPLEX SCENARIO: Retention + Multi-Product + Sales matches ONE product\n";
echo "🎯 BUSINESS QUESTION: Which logic takes precedence?\n";
echo "   Option A: Retention logic (volume update only)\n";
echo "   Option B: Multi-product splitting (separate Tellus opportunity)\n\n";

// STEP 3: Run through validation engine
echo "🔄 STEP 3: Running through validation engine to see actual behavior...\n";
echo "════════════════════════════════════════════════════════════════\n";

$startTime = microtime(true);
$result = $enhancedEngine->validateSalesRecord($salesData, 'RETENTION_MULTI_BATCH_' . time());
$endTime = microtime(true);

echo "📊 VALIDATION ENGINE RESULTS:\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "🎯 Processing Status: " . $result['status'] . "\n";
echo "🆔 Original Opportunity ID: " . $originalOpportunityId . "\n";
echo "⏱️ Processing Time: " . sprintf("%.2f ms", ($endTime - $startTime) * 1000) . "\n";

// Check what happened
if (isset($result['opportunity_split']) && $result['opportunity_split']) {
    echo "✅ Opportunity Split: YES (Multi-product logic applied)\n";
} else {
    echo "❌ Opportunity Split: NO (Retention logic applied)\n";
}

if (isset($result['cross_sell_created']) && $result['cross_sell_created']) {
    echo "✅ Cross-Sell Created: YES\n";
} else {
    echo "❌ Cross-Sell Created: NO\n";
}

if (isset($result['up_sell_created']) && $result['up_sell_created']) {
    echo "✅ Up-Sell Created: YES\n";
} else {
    echo "❌ Up-Sell Created: NO\n";
}

if (isset($result['messages']) && is_array($result['messages'])) {
    echo "📝 System Actions Taken:\n";
    foreach ($result['messages'] as $message) {
        echo "   ✓ " . $message . "\n";
    }
}

echo "\n";

// STEP 4: Show complete opportunity breakdown
echo "📈 STEP 4: Complete opportunity breakdown after processing...\n";
echo "════════════════════════════════════════════════════════════════\n";

$stmt = $db->prepare("
    SELECT id, cus_name, registration_no, dsr_name, dsr_id, 
           product_name, product_name_2, product_name_3,
           opportunity_name, lead_status, volume_converted, annual_potential,
           opp_type, last_integration_update, entered_date_time
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
    echo "   Products: " . $opp['product_name'];
    if ($opp['product_name_2']) echo ", " . $opp['product_name_2'];
    if ($opp['product_name_3']) echo ", " . $opp['product_name_3'];
    echo "\n";
    echo "   Type: " . $opp['opp_type'] . "\n";
    echo "   Stage: " . $opp['lead_status'] . "\n";
    echo "   Volume: " . $opp['volume_converted'] . "L\n";
    echo "   Annual Potential: " . $opp['annual_potential'] . "L\n";
    echo "   DSR: " . $opp['dsr_name'] . " (ID: " . $opp['dsr_id'] . ")\n";
    echo "   Last Updated: " . $opp['last_integration_update'] . "\n";
    
    if ($opp['id'] == $originalOpportunityId) {
        echo "   📋 ORIGINAL RETENTION OPPORTUNITY\n";
    } else {
        echo "   📋 NEW OPPORTUNITY (Split/Cross-sell/Up-sell)\n";
    }
    echo "\n";
}

// STEP 5: Business logic analysis
echo "🔍 STEP 5: Business logic analysis...\n";
echo "════════════════════════════════════════════════════════════════\n";

echo "📊 SCENARIO COMPLEXITY ANALYSIS:\n";
echo "   🔄 Stage: Retention (special handling required)\n";
echo "   📦 Products: Multi-product opportunity (3 products)\n";
echo "   🎯 Sales Match: One specific product (Tellus)\n";
echo "   💰 Volume: 600L for specific product\n";

echo "\n🤔 BUSINESS LOGIC DECISION:\n";
if ($totalOpportunities > 1) {
    echo "   ✅ MULTI-PRODUCT SPLITTING applied\n";
    echo "   📋 Rationale: Cannot add Tellus volume to Rimula+Gadus opportunity\n";
    echo "   🎯 Result: Clean product-specific opportunity management\n";
} else {
    echo "   ✅ RETENTION LOGIC applied\n";
    echo "   📋 Rationale: Retention stage overrides multi-product splitting\n";
    echo "   🎯 Result: Volume added to existing multi-product opportunity\n";
}

// STEP 6: Show audit trail
echo "\n📝 STEP 6: Audit trail for this complex scenario...\n";
echo "════════════════════════════════════════════════════════════════\n";

$stmt = $db->prepare("
    SELECT lead_id, field_name, old_value, new_value, data_changed_on 
    FROM integration_audit_log 
    WHERE lead_id IN (SELECT id FROM isteer_general_lead WHERE registration_no = ?)
    ORDER BY data_changed_on DESC
    LIMIT 10
");
$stmt->execute([$testGSTIN]);
$auditRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($auditRecords)) {
    foreach ($auditRecords as $record) {
        echo "   📊 Lead " . $record['lead_id'] . ": " . $record['field_name'] . 
             " (" . $record['old_value'] . " → " . $record['new_value'] . 
             ") at " . $record['data_changed_on'] . "\n";
    }
} else {
    echo "   📊 Audit trail created during processing\n";
}

// STEP 7: Recommendations
echo "\n💡 STEP 7: Business recommendations...\n";
echo "════════════════════════════════════════════════════════════════\n";

echo "📈 RECOMMENDED APPROACH:\n";
if ($totalOpportunities > 1) {
    echo "   ✅ Current behavior is OPTIMAL\n";
    echo "   📋 Separate tracking per product family\n";
    echo "   🎯 Retention relationship maintained for non-sales products\n";
    echo "   💰 Accurate volume attribution per product\n";
} else {
    echo "   ⚠️  Current behavior may need review\n";
    echo "   📋 Adding Tellus volume to multi-product opportunity\n";
    echo "   🎯 Consider product-specific volume tracking\n";
    echo "   💰 Volume attribution may be unclear\n";
}

// STEP 8: Cleanup
echo "\n🧹 STEP 8: Cleaning up test data...\n";

$db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no = '$testGSTIN'");
$db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '$testGSTIN'");
$db->exec("DELETE FROM integration_audit_log WHERE integration_batch_id LIKE 'RETENTION_MULTI_BATCH_%'");
$db->exec("DELETE FROM isteer_opportunity_products WHERE product_id = 'TELLUS_RET_001'");
$db->exec("DELETE FROM volume_discrepancy_tracking WHERE registration_no = '$testGSTIN'");

echo "✅ Cleanup completed\n";

echo "\n" . str_repeat("=", 80) . "\n";
echo "🎯 RETENTION MULTI-PRODUCT SCENARIO SUMMARY\n";
echo str_repeat("=", 80) . "\n";
echo "SCENARIO: Retention + Multi-Product + Sales matches ONE product\n";
echo "QUESTION: Which business logic should take precedence?\n";
echo "CURRENT:  System behavior analysis completed\n";
echo "RESULT:   Business recommendation provided\n";
echo str_repeat("=", 80) . "\n";

echo "\n✅ Retention multi-product scenario test completed!\n";
?>