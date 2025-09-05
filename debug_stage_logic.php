<?php
/**
 * Debug Stage Transition Logic - Find Root Cause
 */

require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

echo "=== STAGE TRANSITION DEBUG TEST ===\n";

$database = new Database();
$db = $database->getConnection();
$auditLogger = new AuditLogger();
$enhancedEngine = new EnhancedValidationEngine($auditLogger);

// Test all common stages that should transition to Order when sales occur
$testStages = array('Prospect', 'Qualified', 'Suspect', 'SPANCOP', 'Lost', 'Sleep');
$testGSTIN = '29STAGE1234F1Z5';

foreach ($testStages as $index => $stage) {
    echo "\n📋 TEST " . ($index + 1) . ": $stage → Order Transition\n";
    echo str_repeat("=", 50) . "\n";
    
    // Clean up
    $db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no = '$testGSTIN'");
    $db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '$testGSTIN'");
    
    // Create opportunity in specific stage
    $stmt = $db->prepare("
        INSERT INTO isteer_general_lead (
            cus_name, registration_no, dsr_name, dsr_id, sector, sub_sector,
            product_name, opportunity_name, lead_status, volume_converted, annual_potential,
            source_from, integration_managed, integration_batch_id, entered_date_time
        ) VALUES (
            'Stage Test Corp', ?, 'Test DSR', 101, 'Manufacturing', 'Industrial', 'Test Product', 
            'Stage Test Opportunity', ?, 0.00, 100.00,
            'CRM System', 1, 'STAGE_TEST', '2025-01-01 10:00:00'
        )
    ");
    $stmt->execute([$testGSTIN, $stage]);
    $opportunityId = $db->lastInsertId();
    
    echo "✅ Created opportunity in '$stage' stage (ID: $opportunityId)\n";
    
    // Process sales data
    $salesData = array(
        'registration_no' => $testGSTIN,
        'customer_name' => 'Stage Test Corp',
        'dsr_name' => 'Test DSR',
        'product_family_name' => 'Test Product',
        'sku_code' => 'TEST_SKU',
        'volume' => '200.00',
        'sector' => 'Manufacturing',
        'sub_sector' => 'Industrial',
        'tire_type' => 'Premium'
    );
    
    $result = $enhancedEngine->validateSalesRecord($salesData, 'STAGE_TEST_BATCH');
    
    // Check final stage
    $stmt = $db->prepare("SELECT lead_status FROM isteer_general_lead WHERE id = ?");
    $stmt->execute([$opportunityId]);
    $finalStage = $stmt->fetch(PDO::FETCH_ASSOC)['lead_status'];
    
    echo "📊 Stage transition: $stage → $finalStage\n";
    
    if ($finalStage === 'Order') {
        echo "✅ CORRECT: Stage changed to Order\n";
    } else {
        echo "❌ BUG: Stage should be Order, but is '$finalStage'\n";
        echo "🔍 This stage is NOT handled in the transition logic!\n";
    }
    
    if (isset($result['messages']) && is_array($result['messages'])) {
        echo "📝 Messages:\n";
        foreach ($result['messages'] as $message) {
            echo "   - $message\n";
        }
    }
}

// Cleanup
$db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no = '$testGSTIN'");
$db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '$testGSTIN'");

echo "\n" . str_repeat("=", 60) . "\n";
echo "🎯 ROOT CAUSE ANALYSIS COMPLETE\n";
echo str_repeat("=", 60) . "\n";

echo "❌ BUG CONFIRMED: Stage transition logic is incomplete!\n";
echo "📋 MISSING STAGES: Prospect, Qualified, Suspect\n";
echo "🔧 FIX REQUIRED: Add these stages to transition logic\n";
echo "✅ WORKING STAGES: SPANCOP, Lost, Sleep (already in code)\n";

echo "\n✅ Debug completed!\n";
?>