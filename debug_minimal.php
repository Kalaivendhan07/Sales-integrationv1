<?php
require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

echo "=== MINIMAL VALIDATION TEST ===\n";

$database = new Database();
$db = $database->getConnection();

// Insert some test sales data first
$db->exec("INSERT IGNORE INTO isteer_sales_upload_master (
    date, invoice_date, dsr_name, customer_name, sector, cus_sector, 
    product_family_name, sku_code, volume, registration_no, tire_type, invoice_no
) VALUES (
    '2024-07-01', '2024-07-01', 'Test DSR', 'Test Customer', 'Manufacturing', 'Manufacturing',
    'Test Product', 'TEST001', 100.00, '11TESTGSTIN1ZD', 'Premium', 'INV001'
)");

// Test simple validation
$salesData = array(
    'registration_no' => '11NEWCUSTOMER1ZD',  // New customer
    'customer_name' => 'New Test Customer',
    'dsr_name' => 'Test DSR', 
    'product_family_name' => 'Test Product',
    'sku_code' => 'TEST002',
    'volume' => '200.00',
    'sector' => 'Manufacturing',
    'sub_sector' => 'Industrial',
    'tire_type' => 'Premium'
);

echo "Testing with new customer (should trigger new opportunity creation)...\n";

try {
    $auditLogger = new AuditLogger();
    $engine = new EnhancedValidationEngine($auditLogger);
    
    $result = $engine->validateSalesRecord($salesData, 'MINIMAL_TEST');
    
    echo "✅ Validation completed successfully\n";
    echo "Status: " . $result['status'] . "\n";
    if (isset($result['messages']) && is_array($result['messages'])) {
        echo "Messages: " . implode(', ', $result['messages']) . "\n";
    } else {
        echo "Messages: " . (isset($result['messages']) ? $result['messages'] : 'None') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error caught: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

// Cleanup
$db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no = '11NEWCUSTOMER1ZD'");
$db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '11NEWCUSTOMER1ZD'");

echo "\n=== MINIMAL TEST COMPLETED ===\n";
?>