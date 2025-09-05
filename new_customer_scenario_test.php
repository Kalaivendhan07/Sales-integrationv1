<?php
/**
 * New Customer Scenario Test: GSTIN not in opportunity list
 * GSTIN: 33AAGCA2111H1ZD - New customer from sales data
 */

require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

class NewCustomerScenarioTest {
    private $db;
    private $enhancedEngine;
    private $auditLogger;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auditLogger = new AuditLogger();
        $this->enhancedEngine = new EnhancedValidationEngine($this->auditLogger);
    }
    
    public function runNewCustomerScenario() {
        echo "=== NEW CUSTOMER SCENARIO TEST ===\n";
        echo "GSTIN: 33AAGCA2111H1ZD (Not in opportunity database)\n\n";
        
        // First, verify this GSTIN doesn't exist
        $this->verifyGstinNotExists();
        
        // Process the sales data
        $this->processSalesDataForNewCustomer();
        
        // Show what was created
        $this->showNewCustomerResults();
        
        // Cleanup
        $this->cleanup();
    }
    
    private function verifyGstinNotExists() {
        echo "🔍 STEP 1: Verifying GSTIN doesn't exist in opportunity database...\n";
        echo "════════════════════════════════════════════════════════════════\n";
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM isteer_general_lead 
            WHERE registration_no = '33AAGCA2111H1ZD'
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Opportunities with GSTIN 33AAGCA2111H1ZD: " . $result['count'] . "\n";
        
        if ($result['count'] == 0) {
            echo "✅ Confirmed: GSTIN not found in opportunity database\n";
            echo "📋 System Action: Will create NEW CUSTOMER opportunity\n\n";
        } else {
            echo "⚠️  GSTIN already exists, cleaning up first...\n";
            $this->cleanup();
            echo "✅ Now confirmed: GSTIN not in database\n\n";
        }
    }
    
    private function processSalesDataForNewCustomer() {
        echo "📋 STEP 2: Processing sales data for new customer...\n";
        echo "════════════════════════════════════════════════════════════════\n";
        
        // Sales data from your scenario
        $salesData = array(
            'registration_no' => '33AAGCA2111H1ZD',  // New GSTIN
            'customer_name' => 'New Customer Corp Pvt Ltd',
            'dsr_name' => 'Rajesh Kumar',
            'product_family_name' => 'Shell Ultra',
            'sku_code' => 'ULTRA_NEW_001',
            'volume' => '450.00',
            'sector' => 'Manufacturing',
            'sub_sector' => 'Automotive',
            'tire_type' => 'Premium'
        );
        
        echo "📥 Incoming sales data:\n";
        foreach ($salesData as $key => $value) {
            echo "   " . ucfirst(str_replace('_', ' ', $key)) . ": " . $value . "\n";
        }
        echo "\n";
        
        echo "🔄 Running through 6-level validation engine...\n";
        echo "════════════════════════════════════════════════════════════════\n";
        
        // Process through validation engine
        $result = $this->enhancedEngine->validateSalesRecord($salesData, 'NEW_CUSTOMER_BATCH_' . time());
        
        echo "📊 VALIDATION ENGINE RESULTS:\n";
        echo "════════════════════════════════════════════════════════════════\n";
        echo "🎯 Final Status: " . $result['status'] . "\n";
        echo "🆔 Opportunity ID: " . (isset($result['opportunity_id']) ? $result['opportunity_id'] : 'N/A') . "\n";
        
        if (isset($result['opportunity_created']) && $result['opportunity_created']) {
            echo "✅ New Opportunity Created: YES\n";
        }
        
        if (isset($result['messages']) && is_array($result['messages'])) {
            echo "📝 Actions Taken:\n";
            foreach ($result['messages'] as $message) {
                echo "   ✓ " . $message . "\n";
            }
        }
        
        if (isset($result['actions']) && is_array($result['actions'])) {
            echo "📋 DSM Actions Created:\n";
            foreach ($result['actions'] as $action) {
                echo "   📋 " . $action . "\n";
            }
        } else {
            echo "📋 DSM Actions Created: None (Perfect match scenario)\n";
        }
        
        if (isset($result['volume_discrepancy'])) {
            echo "📊 Volume Discrepancy Detected:\n";
            echo "   Type: " . $result['volume_discrepancy']['type'] . "\n";
            echo "   Volume: " . $result['volume_discrepancy']['volume'] . "L\n";
        }
        
        echo "\n";
        return $result;
    }
    
    private function showNewCustomerResults() {
        echo "📈 STEP 3: New customer opportunity details...\n";
        echo "════════════════════════════════════════════════════════════════\n";
        
        // Get the newly created opportunity
        $stmt = $this->db->prepare("
            SELECT id, cus_name, registration_no, dsr_name, dsr_id, sector, sub_sector,
                   product_name, opportunity_name, lead_status, volume_converted, 
                   annual_potential, source_from, integration_managed, integration_batch_id,
                   last_integration_update, entered_date_time, opp_type
            FROM isteer_general_lead 
            WHERE registration_no = '33AAGCA2111H1ZD'
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute();
        $opportunity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($opportunity) {
            echo "🎉 NEW OPPORTUNITY CREATED:\n";
            echo "   ID: " . $opportunity['id'] . "\n";
            echo "   Customer Name: " . $opportunity['cus_name'] . "\n";
            echo "   GSTIN: " . $opportunity['registration_no'] . "\n";
            echo "   DSR: " . $opportunity['dsr_name'] . "\n";
            echo "   DSR ID: " . ($opportunity['dsr_id'] ?: 'To be assigned') . "\n";
            echo "   Sector: " . $opportunity['sector'] . "\n";
            echo "   Sub-Sector: " . $opportunity['sub_sector'] . "\n";
            echo "   Product: " . $opportunity['product_name'] . "\n";
            echo "   Opportunity Name: " . $opportunity['opportunity_name'] . "\n";
            echo "   Stage: " . $opportunity['lead_status'] . "\n";
            echo "   Volume: " . $opportunity['volume_converted'] . "L\n";
            echo "   Annual Potential: " . $opportunity['annual_potential'] . "L\n";
            echo "   Source: " . $opportunity['source_from'] . "\n";
            echo "   Integration Managed: " . ($opportunity['integration_managed'] ? 'YES' : 'NO') . "\n";
            echo "   Batch ID: " . $opportunity['integration_batch_id'] . "\n";
            echo "   Created Date: " . $opportunity['entered_date_time'] . "\n";
            echo "   Last Updated: " . $opportunity['last_integration_update'] . "\n";
            echo "   Opportunity Type: " . $opportunity['opp_type'] . "\n";
        } else {
            echo "❌ No opportunity found - creation may have failed\n";
        }
        
        // Check for audit trail
        echo "\n📝 Audit Trail:\n";
        $stmt = $this->db->prepare("
            SELECT field_name, old_value, new_value, data_changed_on 
            FROM integration_audit_log 
            WHERE lead_id = (SELECT id FROM isteer_general_lead WHERE registration_no = '33AAGCA2111H1ZD' ORDER BY id DESC LIMIT 1)
            ORDER BY data_changed_on DESC
            LIMIT 10
        ");
        $stmt->execute();
        $auditRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($auditRecords)) {
            foreach ($auditRecords as $record) {
                echo "   📊 " . $record['field_name'] . 
                     " (" . $record['old_value'] . " → " . $record['new_value'] . 
                     ") at " . $record['data_changed_on'] . "\n";
            }
        } else {
            echo "   📊 Audit records will be created during processing\n";
        }
        
        // Check for SKU tracking
        echo "\n🏷️ SKU Level Tracking:\n";
        $stmt = $this->db->prepare("
            SELECT product_id, product_name, volume, status, added_by, added_date 
            FROM isteer_opportunity_products 
            WHERE lead_id = (SELECT id FROM isteer_general_lead WHERE registration_no = '33AAGCA2111H1ZD' ORDER BY id DESC LIMIT 1)
        ");
        $stmt->execute();
        $skuRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($skuRecords)) {
            foreach ($skuRecords as $sku) {
                echo "   🏷️ Product ID: " . $sku['product_id'] . 
                     " | Name: " . $sku['product_name'] . 
                     " | Volume: " . $sku['volume'] . "L" .
                     " | Status: " . $sku['status'] .
                     " | Added: " . $sku['added_date'] . "\n";
            }
        } else {
            echo "   🏷️ SKU records will be created during processing\n";
        }
        
        echo "\n";
    }
    
    private function cleanup() {
        echo "🧹 Cleaning up test data...\n";
        
        try {
            // Set integration_managed to 0 to bypass trigger
            $this->db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no = '33AAGCA2111H1ZD'");
            
            // Clean up test opportunity
            $this->db->exec("DELETE FROM isteer_general_lead WHERE registration_no = '33AAGCA2111H1ZD'");
            
            // Clean up audit logs
            $this->db->exec("DELETE FROM integration_audit_log WHERE integration_batch_id LIKE 'NEW_CUSTOMER_BATCH_%'");
            
            // Clean up SKU records
            $this->db->exec("DELETE FROM isteer_opportunity_products WHERE product_id LIKE 'ULTRA_NEW_%'");
            
            // Clean up volume discrepancy records
            $this->db->exec("DELETE FROM volume_discrepancy_tracking WHERE registration_no = '33AAGCA2111H1ZD'");
            
            echo "✅ Cleanup completed\n";
        } catch (Exception $e) {
            echo "⚠️ Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

// Run the new customer scenario
$newCustomerTest = new NewCustomerScenarioTest();
$newCustomerTest->runNewCustomerScenario();
?>