<?php
/**
 * Debug script to understand why validateSalesRecord is failing
 */

require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

class ValidationDebugger {
    private $db;
    private $enhancedEngine;
    private $auditLogger;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auditLogger = new AuditLogger();
        $this->enhancedEngine = new EnhancedValidationEngine($this->auditLogger);
    }
    
    public function debugValidation() {
        echo "=== VALIDATION ENGINE DEBUG ===\n\n";
        
        // Setup test data first
        $this->setupDebugData();
        
        // Test with existing customer
        $salesData = array(
            'registration_no' => '29AAATE1111A1Z5',
            'customer_name' => 'Test Corp Alpha',
            'dsr_name' => 'DSR Alpha',
            'product_family_name' => 'Shell Ultra',
            'sku_code' => 'SKU001',
            'volume' => '100.00',
            'sector' => 'Technology',
            'sub_sector' => 'Software'
        );
        
        echo "🔍 DEBUGGING validateSalesRecord() with existing customer:\n";
        echo "GSTIN: " . $salesData['registration_no'] . "\n";
        echo "Customer: " . $salesData['customer_name'] . "\n\n";
        
        try {
            $result = $this->enhancedEngine->validateSalesRecord($salesData, 'DEBUG_TEST');
            
            echo "RESULT STATUS: " . $result['status'] . "\n";
            echo "OPPORTUNITY ID: " . (isset($result['opportunity_id']) ? $result['opportunity_id'] : 'NULL') . "\n";
            echo "MESSAGES:\n";
            if (isset($result['messages']) && is_array($result['messages'])) {
                foreach ($result['messages'] as $msg) {
                    echo "  - " . $msg . "\n";
                }
            } else {
                echo "  No messages\n";
            }
            
            // Now let's debug the Level 1 validation specifically
            echo "\n🔍 DEBUGGING Level 1 validation directly:\n";
            $this->debugLevel1Validation($salesData);
            
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            echo "STACK TRACE:\n" . $e->getTraceAsString() . "\n";
        }
        
        // Test with new customer
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "🔍 DEBUGGING validateSalesRecord() with NEW customer:\n";
        
        $newCustomerData = array(
            'registration_no' => '29XYZAB9999G2Z8',
            'customer_name' => 'New Debug Test Corp',
            'dsr_name' => 'DSR New Debug',
            'product_family_name' => 'Shell New Debug',
            'sku_code' => 'SKU_NEW_DEBUG',
            'volume' => '200.00',
            'sector' => 'Manufacturing',
            'sub_sector' => 'Industrial'
        );
        
        echo "GSTIN: " . $newCustomerData['registration_no'] . "\n";
        echo "Customer: " . $newCustomerData['customer_name'] . "\n\n";
        
        try {
            $result = $this->enhancedEngine->validateSalesRecord($newCustomerData, 'DEBUG_TEST_NEW');
            
            echo "RESULT STATUS: " . $result['status'] . "\n";
            echo "OPPORTUNITY ID: " . (isset($result['opportunity_id']) ? $result['opportunity_id'] : 'NULL') . "\n";
            echo "MESSAGES:\n";
            if (isset($result['messages']) && is_array($result['messages'])) {
                foreach ($result['messages'] as $msg) {
                    echo "  - " . $msg . "\n";
                }
            } else {
                echo "  No messages\n";
            }
            
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            echo "STACK TRACE:\n" . $e->getTraceAsString() . "\n";
        }
        
        $this->cleanupDebugData();
    }
    
    private function debugLevel1Validation($salesData) {
        try {
            // Use reflection to access private method
            $reflection = new ReflectionClass($this->enhancedEngine);
            $method = $reflection->getMethod('level1_GSTINValidation');
            $method->setAccessible(true);
            
            $result = $method->invoke($this->enhancedEngine, $salesData, 'DEBUG_L1');
            
            echo "Level 1 Status: " . $result['status'] . "\n";
            echo "Level 1 Opportunity ID: " . (isset($result['opportunity_id']) ? $result['opportunity_id'] : 'NULL') . "\n";
            echo "Level 1 Message: " . (isset($result['message']) ? $result['message'] : 'No message') . "\n";
            
            // Check if opportunity exists in database
            if (isset($result['opportunity_id'])) {
                $stmt = $this->db->prepare("SELECT * FROM isteer_general_lead WHERE id = :id");
                $stmt->bindParam(':id', $result['opportunity_id']);
                $stmt->execute();
                $opp = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($opp) {
                    echo "Opportunity found in DB: YES\n";
                    echo "  Customer: " . $opp['cus_name'] . "\n";
                    echo "  GSTIN: " . $opp['registration_no'] . "\n";
                    echo "  Stage: " . $opp['lead_status'] . "\n";
                } else {
                    echo "Opportunity found in DB: NO\n";
                }
            }
            
        } catch (Exception $e) {
            echo "Level 1 Debug Error: " . $e->getMessage() . "\n";
        }
    }
    
    private function setupDebugData() {
        echo "🔧 Setting up debug test data...\n";
        
        // Insert test opportunity
        $stmt = $this->db->prepare("
            INSERT INTO isteer_general_lead (
                cus_name, registration_no, dsr_name, dsr_id, sector, sub_sector,
                product_name, product_name_2, product_name_3,
                opportunity_name, lead_status, volume_converted, annual_potential,
                source_from, integration_managed, entered_date_time
            ) VALUES (
                'Test Corp Alpha', '29AAATE1111A1Z5', 'DSR Alpha', 101,
                'Technology', 'Software', 'Shell Ultra', 'Shell Premium', 'Shell Basic',
                'Test Opportunity Alpha', 'Qualified', 500, 2000,
                'Debug Test', 0, '2025-01-01 10:00:00'
            )
        ");
        $stmt->execute();
        echo "✅ Debug test opportunity created\n\n";
    }
    
    private function cleanupDebugData() {
        echo "\n🧹 Cleaning up debug data...\n";
        
        // Set integration_managed to 0 before deleting
        $this->db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no LIKE '29AAATE%' OR registration_no LIKE '29XYZAB%' OR cus_name LIKE '%Debug%'");
        
        // Clean up debug opportunities
        $this->db->exec("DELETE FROM isteer_general_lead WHERE registration_no LIKE '29AAATE%' OR registration_no LIKE '29XYZAB%' OR cus_name LIKE '%Debug%'");
        
        echo "✅ Debug cleanup completed\n";
    }
}

// Run the debug
$debugger = new ValidationDebugger();
$debugger->debugValidation();
?>