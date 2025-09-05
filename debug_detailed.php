<?php
require_once __DIR__ . '/config/database.php';

echo "=== DETAILED DEBUG TEST ===\n";

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo "❌ Database connection failed\n";
    exit(1);
}

echo "✅ Database connected successfully\n";

// Test integration_statistics insert
echo "📊 Testing integration_statistics insert...\n";
try {
    $stmt = $db->prepare("
        INSERT INTO integration_statistics (batch_id, status) 
        VALUES (:batch_id, 'PROCESSING')
    ");
    $batchId = 'DEBUG_BATCH_' . time();
    $stmt->bindParam(':batch_id', $batchId);
    $stmt->execute();
    echo "✅ integration_statistics insert successful\n";
} catch (Exception $e) {
    echo "❌ integration_statistics insert error: " . $e->getMessage() . "\n";
}

// Test basic opportunity insert
echo "📊 Testing isteer_general_lead insert...\n";
try {
    $stmt = $db->prepare("
        INSERT INTO isteer_general_lead (
            cus_name, registration_no, dsr_name, dsr_id, sector, sub_sector,
            product_name, opportunity_name, lead_status, volume_converted, 
            annual_potential, source_from, integration_managed, integration_batch_id
        ) VALUES (
            'Debug Test Customer', '11DEBUG111H1ZD', 'Debug DSR', 1, 'Test Sector', 'Test Sub',
            'Debug Product', 'Debug Opportunity', 'Suspect', 100.00,
            500.00, 'Debug System', 1, 'DEBUG_BATCH'
        )
    ");
    $stmt->execute();
    $leadId = $db->lastInsertId();
    echo "✅ isteer_general_lead insert successful, ID: $leadId\n";
    
    // Clean up
    $db->exec("DELETE FROM isteer_general_lead WHERE id = $leadId");
    
} catch (Exception $e) {
    echo "❌ isteer_general_lead insert error: " . $e->getMessage() . "\n";
}

echo "\n=== DETAILED DEBUG COMPLETED ===\n";
?>