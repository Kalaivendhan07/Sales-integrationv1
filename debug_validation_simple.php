<?php
require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

echo "=== SIMPLE VALIDATION ENGINE DEBUG TEST ===\n";

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo "❌ Database connection failed\n";
    exit(1);
}

echo "✅ Database connected successfully\n";

// Test simple sales data
$salesData = array(
    'registration_no' => '11AAGCA2111H1ZD',
    'customer_name' => 'Test Customer',
    'dsr_name' => 'Test DSR',
    'product_family_name' => 'Tellus',
    'sku_code' => 'TEST001',
    'volume' => '100.00',
    'sector' => 'Manufacturing',
    'sub_sector' => 'Industrial',
    'tire_type' => 'Premium'
);

echo "📊 Testing validation engine with simple data...\n";

try {
    $auditLogger = new AuditLogger();
    $enhancedEngine = new EnhancedValidationEngine($auditLogger);
    
    echo "✅ Validation engine initialized\n";
    
    $result = $enhancedEngine->validateSalesRecord($salesData, 'DEBUG_BATCH');
    
    echo "✅ Validation completed\n";
    echo "Status: " . $result['status'] . "\n";
    
    if (isset($result['messages'])) {
        echo "Messages:\n";
        foreach ($result['messages'] as $message) {
            echo "  - " . $message . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== DEBUG TEST COMPLETED ===\n";
?>