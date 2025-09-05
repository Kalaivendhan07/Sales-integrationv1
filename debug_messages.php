<?php
require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

echo "=== DETAILED MESSAGE DEBUG ===\n";

// Test simple validation
$salesData = array(
    'registration_no' => '11NEWCUSTOMER1ZD',  
    'customer_name' => 'New Test Customer',
    'dsr_name' => 'Test DSR', 
    'product_family_name' => 'Test Product',
    'sku_code' => 'TEST002',
    'volume' => '200.00',
    'sector' => 'Manufacturing',
    'sub_sector' => 'Industrial',
    'tire_type' => 'Premium'
);

try {
    $auditLogger = new AuditLogger();
    $engine = new EnhancedValidationEngine($auditLogger);
    
    $result = $engine->validateSalesRecord($salesData, 'MESSAGE_TEST');
    
    echo "Status: " . $result['status'] . "\n";
    
    if (isset($result['messages']) && is_array($result['messages'])) {
        echo "Messages (" . count($result['messages']) . "):\n";
        foreach ($result['messages'] as $i => $message) {
            echo "  " . ($i+1) . ". " . $message . "\n";
        }
    } else {
        echo "No messages array found\n";
        echo "Result keys: " . implode(', ', array_keys($result)) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n=== MESSAGE DEBUG COMPLETED ===\n";
?>