<?php
/**
 * API endpoint for cleaning up expired backup data
 * PHP 5.3 Compatible
 */

require_once __DIR__ . '/../classes/AuditLogger.php';

header('Content-Type: application/json');

try {
    $auditLogger = new AuditLogger();
    $result = $auditLogger->cleanupExpiredBackups();
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode(array(
            'status' => 'success',
            'message' => $result['message'],
            'deleted_records' => $result['deleted_records']
        ));
    } else {
        http_response_code(500);
        echo json_encode(array(
            'status' => 'error',
            'message' => $result['message']
        ));
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'status' => 'error',
        'message' => 'Cleanup failed: ' . $e->getMessage()
    ));
}
?>