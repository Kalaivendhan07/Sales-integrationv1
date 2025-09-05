<?php
/**
 * Example Scenario Test: GSTIN found, DSR matches, same product (Tellus)
 * This demonstrates the exact actions the system will take
 */

require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

class ExampleScenarioTest {
    private $db;
    private $enhancedEngine;
    private $auditLogger;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auditLogger = new AuditLogger();
        $this->enhancedEngine = new EnhancedValidationEngine($this->auditLogger);
    }
    
    public function runScenario() {
        echo "=== PIPELINE MANAGER SCENARIO TEST ===\n";
        echo "Scenario: GSTIN found, DSR matches, same product (Tellus)\n\n";
        
        // Setup example opportunity
        $this->setupExampleOpportunity();
        
        // Process incoming sales data
        $this->processIncomingSalesData();
        
        // Show results
        $this->showResults();
        
        // Cleanup
        $this->cleanup();
    }
    
    private function setupExampleOpportunity() {
        echo "🔧 STEP 1: Setting up existing opportunity...\n";
        echo "════════════════════════════════════════════════════════════════\n";
        
        // Create existing opportunity with Tellus product
        $stmt = $this->db->prepare("
            INSERT INTO isteer_general_lead (
                cus_name, registration_no, dsr_name, dsr_id, sector, sub_sector,
                product_name, opportunity_name, lead_status, volume_converted, annual_potential,
                source_from, integration_managed, integration_batch_id, entered_date_time
            ) VALUES (
                'ABC Manufacturing Ltd', '29ABCDE1234F1Z5', 'John Smith', 201,
                'Manufacturing', 'Heavy Machinery', 'Tellus', 
                'ABC Manufacturing Opportunity', 'Qualified', 500.00, 2000.00,
                'CRM System', 1, 'BATCH_001', '2025-01-01 10:00:00'
            )
        ");
        $stmt->execute();
        
        $opportunityId = $this->db->lastInsertId();
        
        echo "✅ Created existing opportunity:\n";
        echo "   Customer: ABC Manufacturing Ltd\n";
        echo "   GSTIN: 29ABCDE1234F1Z5\n";
        echo "   DSR: John Smith (ID: 201)\n";
        echo "   Product: Tellus\n";
        echo "   Current Stage: Qualified\n";
        echo "   Current Volume: 500L\n";
        echo "   Annual Potential: 2000L\n";
        echo "   Opportunity ID: " . $opportunityId . "\n\n";
        
        return $opportunityId;
    }
    
    private function processIncomingSalesData() {
        echo "📋 STEP 2: Processing incoming sales data...\n";
        echo "════════════════════════════════════════════════════════════════\n";
        
        // Incoming sales data - same GSTIN, same DSR, same product
        $salesData = array(
            'registration_no' => '29ABCDE1234F1Z5',  // Same GSTIN
            'customer_name' => 'ABC Manufacturing Ltd',
            'dsr_name' => 'John Smith',  // Same DSR
            'product_family_name' => 'Tellus',  // Same product
            'sku_code' => 'TELLUS_50L',
            'volume' => '300.00',  // New sales volume
            'sector' => 'Manufacturing',
            'sub_sector' => 'Heavy Machinery',
            'tire_type' => 'Premium'
        );
        
        echo "📥 Incoming sales data:\n";
        foreach ($salesData as $key => $value) {
            echo "   " . ucfirst(str_replace('_', ' ', $key)) . ": " . $value . "\n";
        }
        echo "\n";
        
        echo "🔄 Running through 6-level validation engine...\n";
        
        // Process through validation engine
        $result = $this->enhancedEngine->validateSalesRecord($salesData, 'EXAMPLE_BATCH_' . time());
        
        echo "\n📊 VALIDATION RESULTS:\n";
        echo "════════════════════════════════════════════════════════════════\n";
        echo "Status: " . $result['status'] . "\n";
        echo "Opportunity ID: " . (isset($result['opportunity_id']) ? $result['opportunity_id'] : 'N/A') . "\n";
        
        if (isset($result['messages']) && is_array($result['messages'])) {
            echo "Actions Taken:\n";
            foreach ($result['messages'] as $message) {
                echo "   ✓ " . $message . "\n";
            }
        }
        
        if (isset($result['actions']) && is_array($result['actions'])) {
            echo "DSM Actions Created:\n";
            foreach ($result['actions'] as $action) {
                echo "   📋 " . $action . "\n";
            }
        }
        
        if (isset($result['volume_discrepancy'])) {
            echo "Volume Discrepancy Detected:\n";
            echo "   Type: " . $result['volume_discrepancy']['type'] . "\n";
            echo "   Volume: " . $result['volume_discrepancy']['volume'] . "L\n";
        }
        
        echo "\n";
        return $result;
    }
    
    private function showResults() {
        echo "📈 STEP 3: Final opportunity state...\n";
        echo "════════════════════════════════════════════════════════════════\n";
        
        // Get updated opportunity details
        $stmt = $this->db->prepare("
            SELECT cus_name, registration_no, dsr_name, product_name, lead_status, 
                   volume_converted, annual_potential, last_integration_update,
                   integration_batch_id
            FROM isteer_general_lead 
            WHERE registration_no = '29ABCDE1234F1Z5'
        ");
        $stmt->execute();
        $opportunity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($opportunity) {
            echo "📋 Updated Opportunity Details:\n";
            echo "   Customer: " . $opportunity['cus_name'] . "\n";
            echo "   GSTIN: " . $opportunity['registration_no'] . "\n";
            echo "   DSR: " . $opportunity['dsr_name'] . "\n";
            echo "   Product: " . $opportunity['product_name'] . "\n";
            echo "   Stage: " . $opportunity['lead_status'] . "\n";
            echo "   Volume: " . $opportunity['volume_converted'] . "L\n";
            echo "   Annual Potential: " . $opportunity['annual_potential'] . "L\n";
            echo "   Last Updated: " . $opportunity['last_integration_update'] . "\n";
            echo "   Batch ID: " . $opportunity['integration_batch_id'] . "\n";
        }
        
        // Check for audit trail
        echo "\n📝 Audit Trail:\n";
        $stmt = $this->db->prepare("
            SELECT field_name, old_value, new_value, data_changed_on 
            FROM integration_audit_log 
            WHERE lead_id = (SELECT id FROM isteer_general_lead WHERE registration_no = '29ABCDE1234F1Z5')
            ORDER BY data_changed_on DESC
            LIMIT 5
        ");
        $stmt->execute();
        $auditRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($auditRecords as $record) {
            echo "   📊 " . $record['field_name'] . ": " . $record['old_value'] . " → " . $record['new_value'] . 
                 " (" . $record['data_changed_on'] . ")\n";
        }
        
        // Check for SKU tracking
        echo "\n🏷️ SKU Tracking:\n";
        $stmt = $this->db->prepare("
            SELECT product_name, volume, added_date 
            FROM isteer_opportunity_products 
            WHERE lead_id = (SELECT id FROM isteer_general_lead WHERE registration_no = '29ABCDE1234F1Z5')
        ");
        $stmt->execute();
        $skuRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($skuRecords as $sku) {
            echo "   🏷️ SKU: " . $sku['product_name'] . " | Volume: " . $sku['volume'] . "L | Added: " . $sku['added_date'] . "\n";
        }
        
        echo "\n";
    }
    
    private function cleanup() {
        echo "🧹 Cleaning up example data...\n";
        
        // Set integration_managed to 0 to bypass trigger
        $this->db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no = '29ABCDE1234F1Z5'");
        
        // Clean up data
        $this->db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '29ABCDE1234F1Z5'");
        $this->db->exec("DELETE FROM integration_audit_log WHERE integration_batch_id LIKE 'EXAMPLE_BATCH_%'");
        $this->db->exec("DELETE FROM isteer_opportunity_products WHERE added_by = 'INTEGRATION_SYSTEM'");
        $this->db->exec("DELETE FROM volume_discrepancy_tracking WHERE registration_no = '29ABCDE1234F1Z5'");
        
        echo "✅ Cleanup completed\n";
    }
}

// Run the example scenario
$scenarioTest = new ExampleScenarioTest();
$scenarioTest->runScenario();
?>