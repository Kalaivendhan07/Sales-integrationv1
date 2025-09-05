<?php
/**
 * Debug Batch Performance Test - Investigate 0% Success Rate
 */

require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

class DebugBatchPerformance {
    private $db;
    private $enhancedEngine;
    private $auditLogger;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auditLogger = new AuditLogger();
        $this->enhancedEngine = new EnhancedValidationEngine($this->auditLogger);
    }
    
    public function debugFailures() {
        echo "=== DEBUG BATCH PERFORMANCE FAILURES ===\n\n";
        
        // Setup one baseline opportunity
        $this->setupOneOpportunity();
        
        // Test a few sample records to see exact failure reasons
        $this->testSampleRecords();
        
        // Cleanup
        $this->cleanup();
    }
    
    private function setupOneOpportunity() {
        echo "🔧 Setting up one test opportunity...\n";
        
        $stmt = $this->db->prepare("
            INSERT INTO isteer_general_lead (
                cus_name, registration_no, dsr_name, dsr_id, sector, sub_sector,
                product_name, product_name_2, product_name_3,
                opportunity_name, lead_status, volume_converted, annual_potential,
                source_from, integration_managed, integration_batch_id, entered_date_time
            ) VALUES (
                'Debug Test Customer 1', '29PERF0001A1Z1', 'DSR_A', 1001,
                'Manufacturing', 'Industrial', 'Shell Ultra', 'Shell Premium', 'Shell Standard',
                'Debug Test Customer 1 Opportunity', 'Qualified', 400, 2000,
                'Debug Test', 1, 'DEBUG_BATCH', '2025-01-01 10:00:00'
            )
        ");
        $stmt->execute();
        echo "✅ Test opportunity created\n\n";
    }
    
    private function testSampleRecords() {
        echo "🧪 Testing sample records to identify failure causes...\n";
        echo "════════════════════════════════════════════════════════════════\n";
        
        // Test 1: Existing customer record
        $existingCustomer = array(
            'registration_no' => '29PERF0001A1Z1',
            'customer_name' => 'Debug Test Customer 1',
            'dsr_name' => 'DSR_A',
            'product_family_name' => 'Shell Ultra',
            'sku_code' => 'SKU_0001',
            'volume' => '100.50',
            'sector' => 'Manufacturing',
            'sub_sector' => 'Industrial',
            'tire_type' => 'Mainstream'
        );
        
        echo "📋 TEST 1: Existing Customer Record\n";
        echo "   GSTIN: " . $existingCustomer['registration_no'] . "\n";
        echo "   Customer: " . $existingCustomer['customer_name'] . "\n";
        echo "   Product: " . $existingCustomer['product_family_name'] . "\n";
        echo "   Volume: " . $existingCustomer['volume'] . "\n\n";
        
        try {
            $result = $this->enhancedEngine->validateSalesRecord($existingCustomer, 'DEBUG_BATCH_TEST');
            
            echo "   Result Status: " . $result['status'] . "\n";
            if (isset($result['messages']) && is_array($result['messages'])) {
                echo "   Messages: " . implode(', ', $result['messages']) . "\n";
            }
            if (isset($result['opportunity_id'])) {
                echo "   Opportunity ID: " . $result['opportunity_id'] . "\n";
            }
            
            if ($result['status'] !== 'SUCCESS') {
                echo "   ❌ FAILURE DETECTED!\n";
                echo "   Debug Info: " . print_r($result, true) . "\n";
            } else {
                echo "   ✅ SUCCESS\n";
            }
            
        } catch (Exception $e) {
            echo "   ❌ EXCEPTION: " . $e->getMessage() . "\n";
            echo "   Stack Trace: " . $e->getTraceAsString() . "\n";
        }
        
        echo "\n" . str_repeat("─", 64) . "\n\n";
        
        // Test 2: New customer record
        $newCustomer = array(
            'registration_no' => '29NEW00001B2Y1',
            'customer_name' => 'New Debug Customer 1',
            'dsr_name' => 'DSR_B',
            'product_family_name' => 'Shell Premium',
            'sku_code' => 'SKU_NEW_001',
            'volume' => '200.75',
            'sector' => 'Retail',
            'sub_sector' => 'Industrial',
            'tire_type' => 'Premium'
        );
        
        echo "📋 TEST 2: New Customer Record\n";
        echo "   GSTIN: " . $newCustomer['registration_no'] . "\n";
        echo "   Customer: " . $newCustomer['customer_name'] . "\n";
        echo "   Product: " . $newCustomer['product_family_name'] . "\n";
        echo "   Volume: " . $newCustomer['volume'] . "\n\n";
        
        try {
            $result = $this->enhancedEngine->validateSalesRecord($newCustomer, 'DEBUG_BATCH_TEST');
            
            echo "   Result Status: " . $result['status'] . "\n";
            if (isset($result['messages']) && is_array($result['messages'])) {
                echo "   Messages: " . implode(', ', $result['messages']) . "\n";
            }
            if (isset($result['opportunity_id'])) {
                echo "   Opportunity ID: " . $result['opportunity_id'] . "\n";
            }
            
            if ($result['status'] !== 'SUCCESS') {
                echo "   ❌ FAILURE DETECTED!\n";
                echo "   Debug Info: " . print_r($result, true) . "\n";
            } else {
                echo "   ✅ SUCCESS\n";
            }
            
        } catch (Exception $e) {
            echo "   ❌ EXCEPTION: " . $e->getMessage() . "\n";
            echo "   Stack Trace: " . $e->getTraceAsString() . "\n";
        }
        
        echo "\n" . str_repeat("─", 64) . "\n\n";
        
        // Test 3: Invalid GSTIN format
        $invalidGSTIN = array(
            'registration_no' => 'INVALID_GSTIN',
            'customer_name' => 'Invalid Customer',
            'dsr_name' => 'DSR_C',
            'product_family_name' => 'Shell Standard',
            'sku_code' => 'SKU_INVALID',
            'volume' => '50.00',
            'sector' => 'Agriculture',
            'sub_sector' => 'Industrial'
        );
        
        echo "📋 TEST 3: Invalid GSTIN Format\n";
        echo "   GSTIN: " . $invalidGSTIN['registration_no'] . "\n";
        echo "   Customer: " . $invalidGSTIN['customer_name'] . "\n\n";
        
        try {
            $result = $this->enhancedEngine->validateSalesRecord($invalidGSTIN, 'DEBUG_BATCH_TEST');
            
            echo "   Result Status: " . $result['status'] . "\n";
            if (isset($result['messages']) && is_array($result['messages'])) {
                echo "   Messages: " . implode(', ', $result['messages']) . "\n";
            }
            
            if ($result['status'] === 'FAILED') {
                echo "   ✅ CORRECTLY FAILED (as expected for invalid GSTIN)\n";
            } else {
                echo "   ❌ UNEXPECTED SUCCESS\n";
            }
            
        } catch (Exception $e) {
            echo "   ❌ EXCEPTION: " . $e->getMessage() . "\n";
        }
    }
    
    private function cleanup() {
        echo "\n🧹 Cleaning up debug test data...\n";
        
        try {
            $this->db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no LIKE '29PERF%' OR registration_no LIKE '29NEW%'");
            $this->db->exec("DELETE FROM isteer_general_lead WHERE registration_no LIKE '29PERF%' OR registration_no LIKE '29NEW%'");
            $this->db->exec("DELETE FROM integration_audit_log WHERE integration_batch_id LIKE 'DEBUG_%'");
            echo "✅ Debug cleanup completed\n";
        } catch (Exception $e) {
            echo "⚠️ Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

// Run the debug test
$debugTest = new DebugBatchPerformance();
$debugTest->debugFailures();
?>