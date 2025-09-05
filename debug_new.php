<?php
require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

echo "=== QUICK DEBUG ===\n";

$database = new Database();
$db = $database->getConnection();

// Check if the GSTIN validation is working first
$salesData = array('registration_no' => '29DEBUG1234A1Z');

echo "Testing GSTIN format validation...\n";
$auditLogger = new AuditLogger();
$engine = new EnhancedValidationEngine($auditLogger);

// Test if isValidGSTIN method exists and works
$reflection = new ReflectionClass($engine);
$method = $reflection->getMethod('isValidGSTIN');
$method->setAccessible(true);

$isValid = $method->invoke($engine, '29DEBUG1234A1Z');
echo "GSTIN '29DEBUG1234A1Z' is " . ($isValid ? "VALID" : "INVALID") . "\n";

echo "Debug completed.\n";
?>
