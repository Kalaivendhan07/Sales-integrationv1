<?php
/**
 * API endpoint for processing sales data (for automated integration)
 * PHP 5.3 Compatible
 */

require_once __DIR__ . '/../classes/IntegrationProcessor.php';

header('Content-Type: application/json');

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('status' => 'error', 'message' => 'Method not allowed'));
    exit;
}

try {
    // Check if data is sent as JSON
    $input = file_get_contents('php://input');
    $salesData = json_decode($input, true);
    
    if (!$salesData) {
        throw new Exception('Invalid JSON data');
    }
    
    // Validate required fields
    $requiredFields = array('registration_no', 'customer_name', 'dsr_name', 'volume', 'invoice_no');
    foreach ($requiredFields as $field) {
        if (!isset($salesData[$field]) || empty($salesData[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Create temporary file for processing
    $tempFile = tempnam(sys_get_temp_dir(), 'sales_api_');
    $handle = fopen($tempFile, 'w');
    
    // Write CSV header
    $headers = array('Invoice Date.', 'DSR Name', 'Customer Name', 'Sector', 'Sub Sector', 
                    'SKU Code', 'Volume (L)', 'Invoice No.', 'Registration No', 'Product Family');
    fputcsv($handle, $headers);
    
    // Write data (support single record or array of records)
    $records = isset($salesData[0]) ? $salesData : array($salesData);
    
    foreach ($records as $record) {
        $row = array(
            isset($record['invoice_date']) ? $record['invoice_date'] : date('Ymd'),
            $record['dsr_name'],
            $record['customer_name'],
            isset($record['sector']) ? $record['sector'] : '',
            isset($record['sub_sector']) ? $record['sub_sector'] : '',
            isset($record['sku_code']) ? $record['sku_code'] : '',
            $record['volume'],
            $record['invoice_no'],
            $record['registration_no'],
            isset($record['product_family_name']) ? $record['product_family_name'] : ''
        );
        fputcsv($handle, $row);
    }
    
    fclose($handle);
    
    // Process the file
    $processor = new IntegrationProcessor();
    $result = $processor->processSalesFile($tempFile, 'csv');
    
    // Clean up
    unlink($tempFile);
    
    if ($result['status'] == 'SUCCESS') {
        http_response_code(200);
        echo json_encode(array(
            'status' => 'success',
            'batch_id' => $result['batch_id'],
            'total_records' => $result['total_records'],
            'processed_records' => $result['processed_records'],
            'new_opportunities' => $result['new_opportunities'],
            'updated_opportunities' => $result['updated_opportunities'],
            'failed_records' => $result['failed_records'],
            'dsm_actions_created' => $result['dsm_actions_created'],
            'messages' => array_slice($result['messages'], 0, 5) // Limit messages for API response
        ));
    } else {
        http_response_code(400);
        echo json_encode(array(
            'status' => 'error',
            'message' => 'Processing failed',
            'errors' => $result['errors']
        ));
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'status' => 'error',
        'message' => $e->getMessage()
    ));
}
?>