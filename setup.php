<?php
/**
 * Database Setup Script for Pipeline Manager Integration
 * PHP 5.3 Compatible
 */

require_once __DIR__ . '/config/database.php';

echo "<!DOCTYPE html>
<html><head><title>Pipeline Manager Integration Setup</title>
<style>
body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
.container { max-width: 800px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.success { color: #28a745; background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 4px; margin: 10px 0; }
.error { color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 4px; margin: 10px 0; }
.info { color: #0c5460; background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 4px; margin: 10px 0; }
pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
</style>
</head><body>";

echo "<div class='container'>";
echo "<h1>Pipeline Manager India Sales Integration Setup</h1>";

try {
    // Test database connection
    echo "<h2>1. Testing Database Connection...</h2>";
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Could not connect to database');
    }
    
    echo "<div class='success'>✓ Database connection successful</div>";
    
    // Check if required tables exist
    echo "<h2>2. Checking Required Tables...</h2>";
    
    $requiredTables = array(
        'isteer_general_lead' => 'Opportunity table',
        'isteer_sales_upload_master' => 'Sales table'
    );
    
    foreach ($requiredTables as $table => $description) {
        $stmt = $db->prepare("SHOW TABLES LIKE :table");
        $stmt->bindParam(':table', $table);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            echo "<div class='success'>✓ $description ($table) exists</div>";
        } else {
            echo "<div class='error'>✗ $description ($table) missing</div>";
        }
    }
    
    // Create integration tables
    echo "<h2>3. Creating Integration Tables...</h2>";
    
    $sqlFile = __DIR__ . '/sql/create_tables.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception('SQL file not found: ' . $sqlFile);
    }
    
    $sql = file_get_contents($sqlFile);
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        try {
            $db->exec($statement);
        } catch (PDOException $e) {
            // Ignore "table already exists" errors
            if (strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }
    }
    
    echo "<div class='success'>✓ Integration tables created successfully</div>";
    
    // Check table creation
    echo "<h2>4. Verifying Integration Tables...</h2>";
    
    $integrationTables = array(
        'sales_integration_staging' => 'Sales staging table',
        'integration_audit_log' => 'Audit log table',
        'dsm_action_queue' => 'DSM action queue table',
        'integration_backup' => 'Backup table',
        'integration_statistics' => 'Statistics table'
    );
    
    foreach ($integrationTables as $table => $description) {
        $stmt = $db->prepare("SHOW TABLES LIKE :table");
        $stmt->bindParam(':table', $table);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            echo "<div class='success'>✓ $description ($table) created</div>";
        } else {
            echo "<div class='error'>✗ $description ($table) creation failed</div>";
        }
    }
    
    // Create uploads directory
    echo "<h2>5. Creating Upload Directory...</h2>";
    
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) {
        if (mkdir($uploadDir, 0755, true)) {
            echo "<div class='success'>✓ Upload directory created: $uploadDir</div>";
        } else {
            echo "<div class='error'>✗ Failed to create upload directory: $uploadDir</div>";
        }
    } else {
        echo "<div class='success'>✓ Upload directory already exists: $uploadDir</div>";
    }
    
    // System requirements check
    echo "<h2>6. System Requirements Check...</h2>";
    
    echo "<div class='info'>";
    echo "<strong>PHP Version:</strong> " . PHP_VERSION . "<br>";
    echo "<strong>PDO Extension:</strong> " . (extension_loaded('pdo') ? 'Available' : 'Missing') . "<br>";
    echo "<strong>PDO MySQL:</strong> " . (extension_loaded('pdo_mysql') ? 'Available' : 'Missing') . "<br>";
    echo "<strong>JSON Extension:</strong> " . (extension_loaded('json') ? 'Available' : 'Missing') . "<br>";
    echo "<strong>Upload Max Filesize:</strong> " . ini_get('upload_max_filesize') . "<br>";
    echo "<strong>Max Execution Time:</strong> " . ini_get('max_execution_time') . " seconds<br>";
    echo "<strong>Memory Limit:</strong> " . ini_get('memory_limit') . "<br>";
    echo "</div>";
    
    // Configuration summary
    echo "<h2>7. Setup Complete!</h2>";
    
    echo "<div class='success'>";
    echo "<strong>Pipeline Manager India Sales Integration is ready!</strong><br><br>";
    echo "<strong>Features Enabled:</strong><br>";
    echo "• 6-Level Validation Hierarchy<br>";
    echo "• GSTIN-based Opportunity Integration<br>";
    echo "• DSM Action Queue for Manual Resolution<br>";
    echo "• Complete Audit Trail (120-day retention)<br>";
    echo "• Rollback Capabilities<br>";
    echo "• Automated Sales Data Processing<br>";
    echo "• Cross-sell Opportunity Creation<br>";
    echo "</div>";
    
    echo "<div class='info'>";
    echo "<strong>Next Steps:</strong><br>";
    echo "1. <a href='index.php'>Go to Dashboard</a><br>";
    echo "2. <a href='integration/upload.php'>Upload Sales Data</a><br>";
    echo "3. <a href='dsm/action_queue.php'>Check DSM Actions</a><br>";
    echo "4. <a href='reports/audit_log.php'>View Audit Reports</a><br>";
    echo "</div>";
    
    // API endpoints information
    echo "<div class='info'>";
    echo "<strong>API Endpoints for Automated Integration:</strong><br>";
    echo "<code>POST /api/process_sales.php</code> - Process sales data via API<br>";
    echo "<code>GET /api/cleanup_expired.php</code> - Clean up expired backups<br>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>Setup Error:</strong> " . $e->getMessage();
    echo "</div>";
    
    echo "<div class='info'>";
    echo "<strong>Troubleshooting:</strong><br>";
    echo "1. Check database connection settings in config/.env<br>";
    echo "2. Ensure MySQL user has CREATE, INSERT, UPDATE, DELETE privileges<br>";
    echo "3. Verify PHP has PDO and PDO_MySQL extensions<br>";
    echo "4. Check file permissions for uploads directory<br>";
    echo "</div>";
}

echo "</div></body></html>";
?>