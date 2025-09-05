<?php
/**
 * Debug Validation Engine Step by Step
 */

require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();
$auditLogger = new AuditLogger();
$enhancedEngine = new EnhancedValidationEngine($auditLogger);

// Insert test data
echo "Inserting test data...\n";
$stmt = $db->prepare("
    INSERT INTO isteer_general_lead (
        cus_name, registration_no, dsr_name, dsr_id, sector, sub_sector,
        product_name, opportunity_name, lead_status, volume_converted, annual_potential,
        source_from, integration_managed, entered_date_time
    ) VALUES (
        'Debug Test Corp', '29AATEST1111A1Z', 'DSR Debug', 101,
        'Technology', 'Software', 'Shell Ultra', 'Debug Test Opportunity', 'Qualified', 500, 2000,
        'Debug Test', 0, '2025-07-01 10:00:00'
    )
");
$stmt->execute();
echo "Test data inserted. ID: " . $db->lastInsertId() . "\n\n";

// Test GSTIN validation directly
echo "Testing GSTIN validation directly...\n";
$reflection = new ReflectionClass($enhancedEngine);
$method = $reflection->getMethod('isValidGSTIN');
$method->setAccessible(true);

$gstin = '29AATEST1111A1Z';
$isValid = $method->invoke($enhancedEngine, $gstin);
echo "GSTIN '$gstin' is " . ($isValid ? 'VALID' : 'INVALID') . "\n\n";

// Test Level 1 validation directly
echo "Testing Level 1 validation directly...\n";
$salesData = array(
    'registration_no' => '29AATEST1111A1Z',
    'customer_name' => 'Debug Test Corp',
    'dsr_name' => 'DSR Debug',
    'product_family_name' => 'Shell Ultra',
    'sku_code' => 'SKU001',
    'volume' => '100.00',
    'sector' => 'Technology',
    'sub_sector' => 'Software'
);

try {
    $level1Method = $reflection->getMethod('level1_GSTINValidation');
    $level1Method->setAccessible(true);
    
    $level1Result = $level1Method->invoke($enhancedEngine, $salesData, 'DEBUG_BATCH');
    echo "Level 1 result:\n";
    echo "Status: " . $level1Result['status'] . "\n";
    echo "Opportunity ID: " . (isset($level1Result['opportunity_id']) ? $level1Result['opportunity_id'] : 'NULL') . "\n";
    echo "Message: " . (isset($level1Result['message']) ? $level1Result['message'] : 'No message') . "\n";
    
} catch (Exception $e) {
    echo "Level 1 Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

// Cleanup
echo "\nCleaning up...\n";
$db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '29AATEST1111A1Z'");
echo "Cleanup complete.\n";
?>