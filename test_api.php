<?php
// Test API integration
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(); // Simulate POST request

// Simulate JSON input
$testData = array(
    array(
        'invoice_date' => '20250907',
        'dsr_name' => 'Test DSR API',
        'customer_name' => 'Test Customer API',
        'sector' => 'Testing',
        'sub_sector' => 'API Testing',
        'sku_code' => 'TEST001',
        'volume' => '100.50',
        'invoice_no' => 'TEST/2025/001',
        'registration_no' => '27AATEST9999T1ZX',
        'product_family_name' => 'Test Product'
    )
);

// Simulate php://input
function test_file_get_contents($filename) {
    global $testData;
    if ($filename === 'php://input') {
        return json_encode($testData);
    }
    return file_get_contents($filename);
}

// Override file_get_contents temporarily
$backup = 'file_get_contents';
if (!function_exists('original_file_get_contents')) {
    function original_file_get_contents($filename) {
        return file_get_contents($filename);
    }
}

// Include the API file
ob_start();
include 'api/process_sales.php';
$output = ob_get_clean();

echo $output;
?>