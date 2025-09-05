<?php
/**
 * Debug Level 1 validation in comprehensive test context
 */

require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

class Level1ComprehensiveDebugger {
    private $db;
    private $enhancedEngine;
    private $auditLogger;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auditLogger = new AuditLogger();
        $this->enhancedEngine = new EnhancedValidationEngine($this->auditLogger);
    }
    
    public function debugLevel1Comprehensive() {
        echo "=== LEVEL 1 COMPREHENSIVE DEBUG ===\n\n";
        
        // Setup the same test data as comprehensive test
        $this->setupComprehensiveTestData();
        
        // Test the exact same scenarios as comprehensive test
        $this->testExistingCustomer();
        $this->testNewCustomer();
        $this->testInvalidGSTIN();
        
        $this->cleanupTestData();
    }
    
    private function setupComprehensiveTestData() {
        echo "🔧 Setting up comprehensive test data...\n";
        
        // Create the same test data as comprehensive test
        $testData = array(
            array(
                'cus_name' => 'Test Corp Alpha',
                'registration_no' => '29AAATE1111A1Z5',
                'dsr_name' => 'DSR Alpha',
                'dsr_id' => 101,
                'products' => array('Shell Ultra', 'Shell Premium', 'Shell Basic'),
                'stage' => 'Qualified',
                'volume' => 500,
                'potential' => 2000
            ),
            array(
                'cus_name' => 'Test Corp Beta',
                'registration_no' => '29AAATE2222B1Z5',
                'dsr_name' => 'DSR Beta',
                'dsr_id' => 102,
                'products' => array('Shell Pro'),
                'stage' => 'Order',
                'volume' => 800,
                'potential' => 1000
            )
        );
        
        foreach ($testData as $customer) {
            $stmt = $this->db->prepare("
                INSERT INTO isteer_general_lead (
                    cus_name, registration_no, dsr_name, dsr_id, sector, sub_sector,
                    product_name, product_name_2, product_name_3,
                    opportunity_name, lead_status, volume_converted, annual_potential,
                    source_from, integration_managed, integration_batch_id, entered_date_time
                ) VALUES (
                    :cus_name, :registration_no, :dsr_name, :dsr_id, 'Technology', 'Software',
                    :product1, :product2, :product3,
                    :opportunity_name, :stage, :volume, :potential,
                    'Test Setup', 1, 'TEST_BATCH', '2025-07-01 10:00:00'
                )
            ");
            
            $product1 = $customer['products'][0];
            $product2 = isset($customer['products'][1]) ? $customer['products'][1] : '';
            $product3 = isset($customer['products'][2]) ? $customer['products'][2] : '';
            
            $stmt->bindParam(':cus_name', $customer['cus_name']);
            $stmt->bindParam(':registration_no', $customer['registration_no']);
            $stmt->bindParam(':dsr_name', $customer['dsr_name']);
            $stmt->bindParam(':dsr_id', $customer['dsr_id']);
            $stmt->bindParam(':product1', $product1);
            $stmt->bindParam(':product2', $product2);
            $stmt->bindParam(':product3', $product3);
            $stmt->bindParam(':opportunity_name', $customer['cus_name']);
            $stmt->bindParam(':stage', $customer['stage']);
            $stmt->bindParam(':volume', $customer['volume']);
            $stmt->bindParam(':potential', $customer['potential']);
            
            $stmt->execute();
        }
        
        echo "✅ Comprehensive test data created\n\n";
    }
    
    private function testExistingCustomer() {
        echo "🔍 DEBUGGING: Valid GSTIN - Existing Customer\n";
        echo "════════════════════════════════════════════════════════════════\n";
        
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
        
        echo "Testing with: " . $salesData['registration_no'] . "\n";
        
        // Check if opportunity exists
        $stmt = $this->db->prepare("SELECT * FROM isteer_general_lead WHERE registration_no = :gstin");
        $stmt->bindParam(':gstin', $salesData['registration_no']);
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            echo "✅ Opportunity exists in DB:\n";
            echo "  ID: " . $existing['id'] . "\n";
            echo "  Customer: " . $existing['cus_name'] . "\n";
            echo "  Stage: " . $existing['lead_status'] . "\n";
        } else {
            echo "❌ Opportunity NOT found in DB\n";
        }
        
        try {
            $result = $this->enhancedEngine->validateSalesRecord($salesData, 'COMP_TEST_EXISTING');
            
            echo "\nValidation Result:\n";
            echo "  Status: " . $result['status'] . "\n";
            echo "  Opportunity ID: " . (isset($result['opportunity_id']) ? $result['opportunity_id'] : 'NULL') . "\n";
            
            if (isset($result['messages']) && is_array($result['messages'])) {
                echo "  Messages:\n";
                foreach ($result['messages'] as $msg) {
                    echo "    - " . $msg . "\n";
                }
            }
            
            $expectedResult = ($result['status'] == 'SUCCESS') ? 'PASS' : 'FAIL';
            echo "  Expected: SUCCESS, Got: " . $result['status'] . " -> " . $expectedResult . "\n";
            
        } catch (Exception $e) {
            echo "❌ ERROR: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    private function testNewCustomer() {
        echo "🔍 DEBUGGING: Valid GSTIN - New Customer\n";
        echo "════════════════════════════════════════════════════════════════\n";
        
        $salesData = array(
            'registration_no' => '29XYZAB9999G2Z8',
            'customer_name' => 'New Test Corp',
            'dsr_name' => 'DSR New',
            'product_family_name' => 'Shell New',
            'sku_code' => 'SKU_NEW',
            'volume' => '200.00',
            'sector' => 'Manufacturing',
            'sub_sector' => 'Industrial'
        );
        
        echo "Testing with: " . $salesData['registration_no'] . "\n";
        
        // Check if opportunity exists (should not)
        $stmt = $this->db->prepare("SELECT * FROM isteer_general_lead WHERE registration_no = :gstin");
        $stmt->bindParam(':gstin', $salesData['registration_no']);
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            echo "⚠️ Opportunity already exists in DB (unexpected)\n";
        } else {
            echo "✅ Opportunity does not exist (as expected for new customer)\n";
        }
        
        try {
            $result = $this->enhancedEngine->validateSalesRecord($salesData, 'COMP_TEST_NEW');
            
            echo "\nValidation Result:\n";
            echo "  Status: " . $result['status'] . "\n";
            echo "  Opportunity ID: " . (isset($result['opportunity_id']) ? $result['opportunity_id'] : 'NULL') . "\n";
            
            if (isset($result['messages']) && is_array($result['messages'])) {
                echo "  Messages:\n";
                foreach ($result['messages'] as $msg) {
                    echo "    - " . $msg . "\n";
                }
            }
            
            $expectedResult = ($result['status'] == 'SUCCESS') ? 'PASS' : 'FAIL';
            echo "  Expected: SUCCESS, Got: " . $result['status'] . " -> " . $expectedResult . "\n";
            
        } catch (Exception $e) {
            echo "❌ ERROR: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    private function testInvalidGSTIN() {
        echo "🔍 DEBUGGING: Invalid GSTIN Format\n";
        echo "════════════════════════════════════════════════════════════════\n";
        
        $salesData = array(
            'registration_no' => 'INVALID_GSTIN',
            'customer_name' => 'Invalid Test Corp',
            'dsr_name' => 'DSR Invalid',
            'product_family_name' => 'Shell Invalid',
            'sku_code' => 'SKU_INVALID',
            'volume' => '100.00',
            'sector' => 'Technology',
            'sub_sector' => 'Software'
        );
        
        echo "Testing with: " . $salesData['registration_no'] . "\n";
        
        try {
            $result = $this->enhancedEngine->validateSalesRecord($salesData, 'COMP_TEST_INVALID');
            
            echo "\nValidation Result:\n";
            echo "  Status: " . $result['status'] . "\n";
            echo "  Opportunity ID: " . (isset($result['opportunity_id']) ? $result['opportunity_id'] : 'NULL') . "\n";
            
            if (isset($result['messages']) && is_array($result['messages'])) {
                echo "  Messages:\n";
                foreach ($result['messages'] as $msg) {
                    echo "    - " . $msg . "\n";
                }
            }
            
            $expectedResult = ($result['status'] == 'FAILED') ? 'PASS' : 'FAIL';
            echo "  Expected: FAILED, Got: " . $result['status'] . " -> " . $expectedResult . "\n";
            
        } catch (Exception $e) {
            echo "❌ ERROR: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    private function cleanupTestData() {
        echo "🧹 Cleaning up test data...\n";
        
        // Set integration_managed to 0 before deleting
        $this->db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no LIKE '29AAATE%' OR registration_no LIKE '29XYZAB%'");
        
        // Clean up test opportunities
        $this->db->exec("DELETE FROM isteer_general_lead WHERE registration_no LIKE '29AAATE%' OR registration_no LIKE '29XYZAB%'");
        
        echo "✅ Cleanup completed\n";
    }
}

// Run the debug
$debugger = new Level1ComprehensiveDebugger();
$debugger->debugLevel1Comprehensive();
?>