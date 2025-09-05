<?php
/**
 * Debug Up-Sell Detection Issue
 */

require_once 'config/database.php';
require_once 'classes/EnhancedValidationEngine.php';

echo "=== UP-SELL DETECTION DEBUG ===\n\n";

$database = new Database();
$db = $database->getConnection();

// Check the test opportunity setup
$stmt = $db->prepare("
    SELECT id, registration_no, product_name, product_name_2, product_name_3, lead_status
    FROM isteer_general_lead 
    WHERE registration_no = '29AAATE1111A1Z5'
    LIMIT 1
");
$stmt->execute();
$opportunity = $stmt->fetch(PDO::FETCH_ASSOC);

if ($opportunity) {
    echo "✅ Found test opportunity:\n";
    echo "   ID: " . $opportunity['id'] . "\n";
    echo "   GSTIN: " . $opportunity['registration_no'] . "\n";
    echo "   Products: " . $opportunity['product_name'] . "\n";
    echo "   Stage: " . $opportunity['stage'] . "\n\n";
    
    // Check existing sales records for this GSTIN
    $stmt = $db->prepare("
        SELECT tire_type, sku_code, product_family_name, created_at
        FROM isteer_sales_upload_master 
        WHERE registration_no = :gstin
        ORDER BY created_at DESC
    ");
    $stmt->bindParam(':gstin', $opportunity['registration_no']);
    $stmt->execute();
    $salesRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 Existing sales records for this GSTIN:\n";
    if (empty($salesRecords)) {
        echo "   ❌ NO EXISTING SALES RECORDS FOUND\n";
        echo "   🔍 This is likely why Up-Sell detection fails!\n";
        echo "   💡 Up-Sell logic requires existing 'Mainstream' tier to upgrade to 'Premium'\n\n";
    } else {
        foreach ($salesRecords as $record) {
            echo "   - Product: " . $record['product_family_name'] . "\n";
            echo "     Tier: " . $record['tire_type'] . "\n";
            echo "     SKU: " . $record['sku_code'] . "\n";
            echo "     Date: " . $record['created_at'] . "\n\n";
        }
    }
    
    // Check opportunity products table
    $stmt = $db->prepare("
        SELECT product_name, volume, status
        FROM isteer_opportunity_products 
        WHERE lead_id = :lead_id
    ");
    $stmt->bindParam(':lead_id', $opportunity['id']);
    $stmt->execute();
    $oppProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📦 Opportunity products:\n";
    if (empty($oppProducts)) {
        echo "   ❌ NO PRODUCTS IN OPPORTUNITY_PRODUCTS TABLE\n\n";
    } else {
        foreach ($oppProducts as $product) {
            echo "   - SKU: " . $product['product_name'] . "\n";
            echo "     Volume: " . $product['volume'] . "\n";
            echo "     Status: " . $product['status'] . "\n\n";
        }
    }
    
    // Now simulate the Up-Sell test scenario
    echo "🧪 SIMULATING UP-SELL TEST SCENARIO:\n";
    echo "   Current setup: Mainstream tier (from opportunity_products)\n";
    echo "   New sales: Premium tier (Shell Ultra)\n";
    echo "   Expected: Up-Sell detection should work\n\n";
    
    // Test the tier upgrade logic directly
    $engine = new EnhancedValidationEngine();
    
    $testSalesData = array(
        'registration_no' => '29AAATE1111A1Z5',
        'customer_name' => 'Test Corp Alpha',
        'dsr_name' => 'DSR Alpha',
        'product_family_name' => 'Shell Ultra',
        'sku_code' => 'SKU001',
        'volume' => '200.00',
        'sector' => 'Technology',
        'sub_sector' => 'Software',
        'tire_type' => 'Premium'
    );
    
    echo "🔍 RUNNING UP-SELL DETECTION TEST:\n";
    $result = $engine->validateSalesRecord($testSalesData, 'DEBUG_UPSELL');
    
    echo "   Status: " . $result['status'] . "\n";
    echo "   Up-Sell Created: " . ($result['up_sell_created'] ? "YES" : "NO") . "\n";
    echo "   Messages:\n";
    if (isset($result['messages'])) {
        foreach ($result['messages'] as $msg) {
            echo "     - " . $msg . "\n";
        }
    }
    
} else {
    echo "❌ Test opportunity not found!\n";
}

echo "\n=== DEBUG COMPLETE ===\n";
?>