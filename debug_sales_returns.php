<?php
/**
 * Debug script for sales returns processing
 */

require_once __DIR__ . '/classes/SalesReturnProcessor.php';
require_once __DIR__ . '/config/database.php';

class SalesReturnDebugger {
    private $db;
    private $returnProcessor;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->returnProcessor = new SalesReturnProcessor();
    }
    
    public function debugSalesReturns() {
        echo "=== SALES RETURNS DEBUG ===\n\n";
        
        // Setup test data
        $this->setupReturnTestData();
        
        // Test full return
        $this->testFullReturn();
        
        // Test partial return
        $this->testPartialReturn();
        
        $this->cleanupReturnTestData();
    }
    
    private function setupReturnTestData() {
        echo "🔧 Setting up return test data...\n";
        
        // Insert test opportunity for returns
        $stmt = $this->db->prepare("
            INSERT INTO isteer_general_lead (
                cus_name, registration_no, dsr_name, dsr_id, sector, sub_sector,
                product_name, opportunity_name, lead_status, volume_converted, annual_potential,
                source_from, integration_managed, entered_date_time
            ) VALUES (
                'Return Test Corp', '29AATEST2222B1Z', 'DSR Return', 102,
                'Technology', 'Software', 'Shell Pro', 'Return Test Opportunity', 
                'Order', 800, 1000, 'Return Test', 0, '2025-01-01 10:00:00'
            )
        ");
        $stmt->execute();
        echo "✅ Return test opportunity created\n\n";
    }
    
    private function testFullReturn() {
        echo "🔍 DEBUGGING Full Return (800L -> 0L, Order -> Suspect):\n";
        
        // Reset opportunity state
        $stmt = $this->db->prepare("
            UPDATE isteer_general_lead 
            SET volume_converted = 800, lead_status = 'Order', integration_managed = 1 
            WHERE registration_no = '29AATEST2222B1Z'
        ");
        $stmt->execute();
        
        $returnData = array(
            'registration_no' => '29AATEST2222B1Z',
            'customer_name' => 'Return Test Corp',
            'product_family_name' => 'Shell Pro',
            'return_volume' => '800.00',
            'return_reason' => 'Debug Full Return',
            'return_invoice_no' => 'RTN_DEBUG_FULL'
        );
        
        echo "Before Return:\n";
        $this->showOpportunityState('29AATEST2222B1Z');
        
        try {
            $result = $this->returnProcessor->processSalesReturn($returnData, 'DEBUG_FULL_RETURN');
            
            echo "\nReturn Result:\n";
            echo "  Status: " . $result['status'] . "\n";
            echo "  Return Amount: " . $result['return_amount'] . "L\n";
            echo "  New Volume: " . $result['new_volume'] . "L\n";
            echo "  Stage Changed: " . ($result['stage_changed'] ? 'YES' : 'NO') . "\n";
            
            if (isset($result['messages'])) {
                echo "  Messages:\n";
                foreach ($result['messages'] as $msg) {
                    echo "    - " . $msg . "\n";
                }
            }
            
            echo "\nAfter Return:\n";
            $this->showOpportunityState('29AATEST2222B1Z');
            
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
    
    private function testPartialReturn() {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "🔍 DEBUGGING Partial Return (800L -> 500L, Order -> Order):\n";
        
        // Reset opportunity state
        $stmt = $this->db->prepare("
            UPDATE isteer_general_lead 
            SET volume_converted = 800, lead_status = 'Order', integration_managed = 1 
            WHERE registration_no = '29AATEST2222B1Z'
        ");
        $stmt->execute();
        
        $returnData = array(
            'registration_no' => '29AATEST2222B1Z',
            'customer_name' => 'Return Test Corp',
            'product_family_name' => 'Shell Pro',
            'return_volume' => '300.00',
            'return_reason' => 'Debug Partial Return',
            'return_invoice_no' => 'RTN_DEBUG_PARTIAL'
        );
        
        echo "Before Return:\n";
        $this->showOpportunityState('29AATEST2222B1Z');
        
        try {
            $result = $this->returnProcessor->processSalesReturn($returnData, 'DEBUG_PARTIAL_RETURN');
            
            echo "\nReturn Result:\n";
            echo "  Status: " . $result['status'] . "\n";
            echo "  Return Amount: " . $result['return_amount'] . "L\n";
            echo "  New Volume: " . $result['new_volume'] . "L\n";
            echo "  Stage Changed: " . ($result['stage_changed'] ? 'YES' : 'NO') . "\n";
            
            if (isset($result['messages'])) {
                echo "  Messages:\n";
                foreach ($result['messages'] as $msg) {
                    echo "    - " . $msg . "\n";
                }
            }
            
            echo "\nAfter Return:\n";
            $this->showOpportunityState('29AATEST2222B1Z');
            
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
    
    private function showOpportunityState($gstin) {
        $stmt = $this->db->prepare("
            SELECT cus_name, lead_status, volume_converted, annual_potential 
            FROM isteer_general_lead 
            WHERE registration_no = :gstin
        ");
        $stmt->bindParam(':gstin', $gstin);
        $stmt->execute();
        $opp = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($opp) {
            echo "  Customer: " . $opp['cus_name'] . "\n";
            echo "  Stage: " . $opp['lead_status'] . "\n";
            echo "  Volume: " . $opp['volume_converted'] . "L\n";
            echo "  Potential: " . $opp['annual_potential'] . "L\n";
        } else {
            echo "  Opportunity not found!\n";
        }
    }
    
    private function cleanupReturnTestData() {
        echo "\n🧹 Cleaning up return test data...\n";
        
        // Set integration_managed to 0 before deleting
        $this->db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no = '29AATEST2222B1Z'");
        
        // Clean up test opportunity
        $this->db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '29AATEST2222B1Z'");
        
        echo "✅ Return test cleanup completed\n";
    }
}

// Run the debug
$debugger = new SalesReturnDebugger();
$debugger->debugSalesReturns();
?>