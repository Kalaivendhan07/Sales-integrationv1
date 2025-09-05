<?php
/**
 * Backend Test Suite for Pipeline Manager Integration
 * Focused testing of individual components to identify root causes
 */

require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/SalesReturnProcessor.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

class BackendTestSuite {
    private $db;
    private $enhancedEngine;
    private $returnProcessor;
    private $auditLogger;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auditLogger = new AuditLogger();
        $this->enhancedEngine = new EnhancedValidationEngine($this->auditLogger);
        $this->returnProcessor = new SalesReturnProcessor();
    }
    
    public function runBackendTests() {
        echo "=== BACKEND COMPONENT TESTING SUITE ===\n\n";
        
        // Setup minimal test data
        $this->setupMinimalTestData();
        
        // Test core components individually
        $this->testDatabaseConnection();
        $this->testGSTINValidationLogic();
        $this->testLevel1ValidationCore();
        $this->testOpportunityCreation();
        $this->testSalesReturnCore();
        $this->testVolumeDiscrepancyCore();
        
        // Cleanup
        $this->cleanupTestData();
        
        echo "\n=== BACKEND TESTING COMPLETE ===\n";
    }
    
    private function setupMinimalTestData() {
        echo "🔧 Setting up minimal test data...\n";
        
        // Insert one test opportunity with correct GSTIN format (15 chars)
        $stmt = $this->db->prepare("
            INSERT INTO isteer_general_lead (
                cus_name, registration_no, dsr_name, dsr_id, sector, sub_sector,
                product_name, product_name_2, product_name_3,
                opportunity_name, lead_status, volume_converted, annual_potential,
                source_from, integration_managed, entered_date_time
            ) VALUES (
                'Backend Test Corp', '29AATEST0001B1X', 'DSR Backend', 999,
                'Technology', 'Software', 'Shell Ultra', 'Shell Premium', '',
                'Backend Test Opportunity', 'Order', 500, 1000,
                'Backend Test', 0, '2025-01-01 10:00:00'
            )
        ");
        $stmt->execute();
        echo "✅ Test opportunity created\n\n";
    }
    
    private function testDatabaseConnection() {
        echo "📋 TEST: Database Connection\n";
        echo "════════════════════════════════════════════════════════════════\n";
        
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM isteer_general_lead");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "✅ Database connection: WORKING\n";
            echo "   Opportunities in database: " . $result['count'] . "\n\n";
        } catch (Exception $e) {
            echo "❌ Database connection: FAILED\n";
            echo "   Error: " . $e->getMessage() . "\n\n";
        }
    }
    
    private function testGSTINValidationLogic() {
        echo "📋 TEST: GSTIN Validation Logic\n";
        echo "════════════════════════════════════════════════════════════════\n";
        
        // Test the private isValidGSTIN method using reflection
        $reflection = new ReflectionClass($this->enhancedEngine);
        $method = $reflection->getMethod('isValidGSTIN');
        $method->setAccessible(true);
        
        $testCases = array(
            '29AATEST0001B1X' => true,   // Valid format (15 chars)
            '29AATEST1111A1Y' => true,   // Valid format (15 chars)
            'INVALID_GSTIN' => false,    // Invalid format
            '29AATEST' => false,         // Too short
            '' => false                  // Empty
        );
        
        foreach ($testCases as $gstin => $expected) {
            $result = $method->invoke($this->enhancedEngine, $gstin);
            $status = ($result === $expected) ? "✅ PASS" : "❌ FAIL";
            echo "   GSTIN: '$gstin' -> Expected: " . ($expected ? 'VALID' : 'INVALID') . 
                 ", Got: " . ($result ? 'VALID' : 'INVALID') . " $status\n";
        }
        echo "\n";
    }
    
    private function testLevel1ValidationCore() {
        echo "📋 TEST: Level 1 Validation Core Logic\n";
        echo "════════════════════════════════════════════════════════════════\n";
        
        // Test with existing customer
        $salesData = array(
            'registration_no' => '29AATEST0001B1ZX',
            'customer_name' => 'Backend Test Corp',
            'dsr_name' => 'DSR Backend',
            'product_family_name' => 'Shell Ultra',
            'sku_code' => 'SKU_BACKEND',
            'volume' => '100.00',
            'sector' => 'Technology',
            'sub_sector' => 'Software'
        );
        
        echo "  🧪 Testing with existing customer (29AATEST0001B1ZX):\n";
        
        try {
            // Use reflection to test the private level1_GSTINValidation method
            $reflection = new ReflectionClass($this->enhancedEngine);
            $method = $reflection->getMethod('level1_GSTINValidation');
            $method->setAccessible(true);
            
            $result = $method->invoke($this->enhancedEngine, $salesData, 'BACKEND_TEST');
            
            echo "     Status: " . $result['status'] . "\n";
            echo "     Opportunity ID: " . (isset($result['opportunity_id']) ? $result['opportunity_id'] : 'NULL') . "\n";
            echo "     Message: " . (isset($result['message']) ? $result['message'] : 'No message') . "\n";
            
            if ($result['status'] === 'SUCCESS' && isset($result['opportunity_id'])) {
                echo "     ✅ Level 1 validation: WORKING\n";
            } else {
                echo "     ❌ Level 1 validation: FAILED\n";
            }
            
        } catch (Exception $e) {
            echo "     ❌ Level 1 validation: ERROR - " . $e->getMessage() . "\n";
        }
        
        // Test with new customer
        echo "\n  🧪 Testing with new customer (29AATEST9999N1ZZ):\n";
        
        $newCustomerData = array(
            'registration_no' => '29AATEST9999N1ZZ',
            'customer_name' => 'New Backend Test Corp',
            'dsr_name' => 'DSR New Backend',
            'product_family_name' => 'Shell New',
            'sku_code' => 'SKU_NEW',
            'volume' => '200.00',
            'sector' => 'Manufacturing',
            'sub_sector' => 'Industrial'
        );
        
        try {
            $result = $method->invoke($this->enhancedEngine, $newCustomerData, 'BACKEND_TEST_NEW');
            
            echo "     Status: " . $result['status'] . "\n";
            echo "     Opportunity ID: " . (isset($result['opportunity_id']) ? $result['opportunity_id'] : 'NULL') . "\n";
            echo "     Message: " . (isset($result['message']) ? $result['message'] : 'No message') . "\n";
            
            if ($result['status'] === 'SUCCESS' && isset($result['opportunity_id'])) {
                echo "     ✅ New customer creation: WORKING\n";
            } else {
                echo "     ❌ New customer creation: FAILED\n";
            }
            
        } catch (Exception $e) {
            echo "     ❌ New customer creation: ERROR - " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    private function testOpportunityCreation() {
        echo "📋 TEST: Opportunity Creation Logic\n";
        echo "════════════════════════════════════════════════════════════════\n";
        
        $salesData = array(
            'registration_no' => '29AATEST8888C1ZW',
            'customer_name' => 'Creation Test Corp',
            'dsr_name' => 'DSR Creation',
            'product_family_name' => 'Shell Creation',
            'sku_code' => 'SKU_CREATE',
            'volume' => '300.00',
            'sector' => 'Retail',
            'sub_sector' => 'Fuel'
        );
        
        try {
            // Use reflection to test the private createNewOpportunity method
            $reflection = new ReflectionClass($this->enhancedEngine);
            $method = $reflection->getMethod('createNewOpportunity');
            $method->setAccessible(true);
            
            $opportunityId = $method->invoke($this->enhancedEngine, $salesData, 'BACKEND_CREATE_TEST');
            
            if ($opportunityId && is_numeric($opportunityId)) {
                echo "     ✅ Opportunity creation: WORKING\n";
                echo "     Created opportunity ID: " . $opportunityId . "\n";
                
                // Verify the opportunity was created correctly
                $stmt = $this->db->prepare("SELECT * FROM isteer_general_lead WHERE id = :id");
                $stmt->bindParam(':id', $opportunityId);
                $stmt->execute();
                $opportunity = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($opportunity) {
                    echo "     Customer Name: " . $opportunity['cus_name'] . "\n";
                    echo "     GSTIN: " . $opportunity['registration_no'] . "\n";
                    echo "     Product: " . $opportunity['product_name'] . "\n";
                    echo "     Volume: " . $opportunity['volume_converted'] . "\n";
                } else {
                    echo "     ❌ Opportunity not found in database\n";
                }
            } else {
                echo "     ❌ Opportunity creation: FAILED\n";
                echo "     Returned ID: " . var_export($opportunityId, true) . "\n";
            }
            
        } catch (Exception $e) {
            echo "     ❌ Opportunity creation: ERROR - " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    private function testSalesReturnCore() {
        echo "📋 TEST: Sales Return Core Logic\n";
        echo "════════════════════════════════════════════════════════════════\n";
        
        // First, ensure we have an opportunity with volume to return
        $stmt = $this->db->prepare("
            UPDATE isteer_general_lead 
            SET volume_converted = 800, lead_status = 'Order' 
            WHERE registration_no = '29AATEST0001B1ZX'
        ");
        $stmt->execute();
        
        $returnData = array(
            'registration_no' => '29AATEST0001B1ZX',
            'customer_name' => 'Backend Test Corp',
            'product_family_name' => 'Shell Ultra',
            'return_volume' => '800.00', // Full return
            'return_reason' => 'Backend Test Return',
            'return_invoice_no' => 'RTN_BACKEND_001'
        );
        
        echo "  🧪 Testing full return (800L -> 0L, Order -> Suspect):\n";
        
        try {
            $result = $this->returnProcessor->processSalesReturn($returnData, 'BACKEND_RETURN_TEST');
            
            echo "     Status: " . $result['status'] . "\n";
            echo "     Return Amount: " . $result['return_amount'] . "L\n";
            echo "     New Volume: " . $result['new_volume'] . "L\n";
            echo "     Stage Changed: " . ($result['stage_changed'] ? 'YES' : 'NO') . "\n";
            
            // Check final stage in database
            $stmt = $this->db->prepare("
                SELECT lead_status, volume_converted FROM isteer_general_lead 
                WHERE registration_no = '29AATEST0001B1ZX'
            ");
            $stmt->execute();
            $finalState = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "     Final Stage: " . $finalState['lead_status'] . "\n";
            echo "     Final Volume: " . $finalState['volume_converted'] . "L\n";
            
            if ($result['status'] === 'SUCCESS' && $finalState['lead_status'] === 'Suspect') {
                echo "     ✅ Sales return processing: WORKING\n";
            } else {
                echo "     ❌ Sales return processing: FAILED\n";
                echo "     Expected stage: Suspect, Got: " . $finalState['lead_status'] . "\n";
            }
            
        } catch (Exception $e) {
            echo "     ❌ Sales return processing: ERROR - " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    private function testVolumeDiscrepancyCore() {
        echo "📋 TEST: Volume Discrepancy Core Logic\n";
        echo "════════════════════════════════════════════════════════════════\n";
        
        // Reset opportunity volume for testing
        $stmt = $this->db->prepare("
            UPDATE isteer_general_lead 
            SET volume_converted = 500 
            WHERE registration_no = '29AATEST0001B1ZX'
        ");
        $stmt->execute();
        
        $salesData = array(
            'registration_no' => '29AATEST0001B1ZX',
            'customer_name' => 'Backend Test Corp',
            'dsr_name' => 'DSR Backend',
            'product_family_name' => 'Shell Ultra',
            'sku_code' => 'SKU_BACKEND',
            'volume' => '2000.00', // Much larger than opportunity volume (500)
            'sector' => 'Technology',
            'sub_sector' => 'Software'
        );
        
        echo "  🧪 Testing over-sale discrepancy (2000L vs 500L opportunity):\n";
        
        try {
            // Use reflection to test the private trackVolumeDiscrepancy method
            $reflection = new ReflectionClass($this->enhancedEngine);
            $method = $reflection->getMethod('trackVolumeDiscrepancy');
            $method->setAccessible(true);
            
            // Get opportunity ID first
            $stmt = $this->db->prepare("SELECT id FROM isteer_general_lead WHERE registration_no = '29AATEST0001B1ZX'");
            $stmt->execute();
            $opp = $stmt->fetch(PDO::FETCH_ASSOC);
            $opportunityId = $opp['id'];
            
            $result = $method->invoke($this->enhancedEngine, $salesData, $opportunityId, 'BACKEND_DISCREPANCY_TEST');
            
            if ($result !== null) {
                echo "     ✅ Volume discrepancy detection: WORKING\n";
                echo "     Discrepancy Type: " . $result['type'] . "\n";
                echo "     Discrepancy Volume: " . $result['volume'] . "L\n";
                echo "     Discrepancy Percentage: " . number_format($result['percentage'], 1) . "%\n";
                
                // Check if record was inserted in database
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as count FROM volume_discrepancy_tracking 
                    WHERE registration_no = '29AATEST0001B1ZX'
                ");
                $stmt->execute();
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                echo "     Records in tracking table: " . $count . "\n";
                
            } else {
                echo "     ❌ Volume discrepancy detection: FAILED\n";
                echo "     No discrepancy detected (should detect over-sale)\n";
            }
            
        } catch (Exception $e) {
            echo "     ❌ Volume discrepancy detection: ERROR - " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    private function cleanupTestData() {
        echo "🧹 Cleaning up backend test data...\n";
        
        // Clean up test opportunities
        $this->db->exec("DELETE FROM isteer_general_lead WHERE registration_no LIKE '29AATEST%'");
        
        // Clean up test discrepancy records
        $this->db->exec("DELETE FROM volume_discrepancy_tracking WHERE registration_no LIKE '29AATEST%'");
        
        // Clean up test audit logs
        $this->db->exec("DELETE FROM integration_audit_log WHERE integration_batch_id LIKE 'BACKEND_%'");
        
        echo "✅ Backend test cleanup completed\n";
    }
}

// Run the backend test suite
$backendTest = new BackendTestSuite();
$backendTest->runBackendTests();
?>