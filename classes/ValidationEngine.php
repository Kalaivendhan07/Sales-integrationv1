<?php
/**
 * 6-Level Validation Engine for Sales-Opportunity Integration
 * PHP 5.3 Compatible
 */

require_once __DIR__ . '/../config/database.php';

class ValidationEngine {
    private $db;
    private $auditLogger;
    
    public function __construct($auditLogger = null) {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auditLogger = $auditLogger;
    }
    
    /**
     * Process sales record through 6-level validation
     */
    public function validateSalesRecord($salesData, $batchId) {
        $result = array(
            'status' => 'SUCCESS',
            'actions' => array(),
            'messages' => array(),
            'opportunity_id' => null
        );
        
        try {
            // Level 1: GSTIN Validation
            $level1Result = $this->level1_GSTINValidation($salesData, $batchId);
            if ($level1Result['status'] == 'FAILED') {
                return $level1Result;
            }
            
            $opportunityId = $level1Result['opportunity_id'];
            $result['opportunity_id'] = $opportunityId;
            
            // Level 2: DSR Validation
            $level2Result = $this->level2_DSRValidation($salesData, $opportunityId, $batchId);
            if ($level2Result['needs_action']) {
                $result['actions'][] = $level2Result['action'];
            }
            
            // Level 3: Product Family Validation  
            $level3Result = $this->level3_ProductFamilyValidation($salesData, $opportunityId, $batchId);
            if ($level3Result['needs_action']) {
                $result['actions'][] = $level3Result['action'];
            }
            
            // Level 4: Sector Validation
            $level4Result = $this->level4_SectorValidation($salesData, $opportunityId, $batchId);
            $result['messages'] = array_merge($result['messages'], $level4Result['messages']);
            
            // Level 5: Sub-Sector Validation
            $level5Result = $this->level5_SubSectorValidation($salesData, $opportunityId, $batchId);
            $result['messages'] = array_merge($result['messages'], $level5Result['messages']);
            
            // Level 6: Stage Validation
            $level6Result = $this->level6_StageValidation($salesData, $opportunityId, $batchId);
            $result['messages'] = array_merge($result['messages'], $level6Result['messages']);
            
        } catch (Exception $e) {
            $result['status'] = 'FAILED';
            $result['messages'][] = 'Validation error: ' . $e->getMessage();
            error_log('ValidationEngine Error: ' . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Level 1: GSTIN Validation - Find or create opportunity
     */
    private function level1_GSTINValidation($salesData, $batchId) {
        $result = array('status' => 'SUCCESS', 'opportunity_id' => null);
        
        // Validate GSTIN format
        if (!$this->isValidGSTIN($salesData['registration_no'])) {
            $result['status'] = 'FAILED';
            $result['message'] = 'Invalid GSTIN format: ' . $salesData['registration_no'];
            return $result;
        }
        
        // Search for existing opportunity by GSTIN
        $stmt = $this->db->prepare("
            SELECT id, cus_name, dsr_id, dsr_name, sector, product_name, 
                   product_name_2, product_name_3, lead_status, volume_converted 
            FROM isteer_general_lead 
            WHERE registration_no = :gstin AND status = 'A'
            LIMIT 1
        ");
        $stmt->bindParam(':gstin', $salesData['registration_no']);
        $stmt->execute();
        
        $opportunity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$opportunity) {
            // Create new opportunity
            $opportunityId = $this->createNewOpportunity($salesData, $batchId);
            $result['opportunity_id'] = $opportunityId;
            $result['message'] = 'New opportunity created';
        } else {
            $result['opportunity_id'] = $opportunity['id'];
            $result['message'] = 'Existing opportunity found';
        }
        
        return $result;
    }
    
    /**
     * Level 2: DSR Validation
     */
    private function level2_DSRValidation($salesData, $opportunityId, $batchId) {
        $result = array('needs_action' => false, 'action' => null);
        
        // Get current opportunity DSR
        $stmt = $this->db->prepare("SELECT dsr_id, dsr_name FROM isteer_general_lead WHERE id = :id");
        $stmt->bindParam(':id', $opportunityId);
        $stmt->execute();
        $opportunity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Compare DSRs
        if ($opportunity['dsr_name'] != $salesData['dsr_name']) {
            // Create DSM action for DSR mismatch
            $actionData = array(
                'registration_no' => $salesData['registration_no'],
                'mismatch_level' => 2,
                'mismatch_type' => 'DSR_MISMATCH',
                'sales_data' => json_encode($salesData),
                'opportunity_data' => json_encode($opportunity),
                'action_required' => 'Choose DSR: Keep opportunity DSR (' . $opportunity['dsr_name'] . 
                                   ') or assign to sales DSR (' . $salesData['dsr_name'] . ')',
                'priority' => 'MEDIUM'
            );
            
            $this->createDSMAction($actionData);
            $result['needs_action'] = true;
            $result['action'] = 'DSR_MISMATCH_ACTION_CREATED';
        }
        
        return $result;
    }
    
    /**
     * Level 3: Product Family Validation
     */
    private function level3_ProductFamilyValidation($salesData, $opportunityId, $batchId) {
        $result = array('needs_action' => false, 'action' => null);
        
        // Get current opportunity product families
        $stmt = $this->db->prepare("
            SELECT product_name, product_name_2, product_name_3 
            FROM isteer_general_lead WHERE id = :id
        ");
        $stmt->bindParam(':id', $opportunityId);
        $stmt->execute();
        $opportunity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $oppProducts = array($opportunity['product_name'], $opportunity['product_name_2'], $opportunity['product_name_3']);
        $salesProduct = $salesData['product_family_name'];
        
        // Check if sales product family matches any opportunity product family
        if (!in_array($salesProduct, $oppProducts)) {
            // Create new cross-sell opportunity
            $crossSellData = $salesData;
            $crossSellData['opportunity_type'] = 'Cross-Sell';
            $crossSellData['parent_opportunity_id'] = $opportunityId;
            
            $this->createNewOpportunity($crossSellData, $batchId);
            $result['needs_action'] = true;
            $result['action'] = 'CROSS_SELL_OPPORTUNITY_CREATED';
        }
        
        return $result;
    }
    
    /**
     * Level 4: Sector Validation
     */
    private function level4_SectorValidation($salesData, $opportunityId, $batchId) {
        $result = array('messages' => array());
        
        // Get current opportunity sector
        $stmt = $this->db->prepare("SELECT sector FROM isteer_general_lead WHERE id = :id");
        $stmt->bindParam(':id', $opportunityId);
        $stmt->execute();
        $opportunity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($opportunity['sector'] != $salesData['sector']) {
            // Overwrite with sales sector (non-editable)
            $this->updateOpportunityField($opportunityId, 'sector', $salesData['sector'], $batchId);
            $result['messages'][] = 'Sector updated from "' . $opportunity['sector'] . '" to "' . $salesData['sector'] . '"';
        }
        
        return $result;
    }
    
    /**
     * Level 5: Sub-Sector Validation
     */
    private function level5_SubSectorValidation($salesData, $opportunityId, $batchId) {
        $result = array('messages' => array());
        
        // Get current opportunity sub-sector
        $stmt = $this->db->prepare("SELECT sub_sector FROM isteer_general_lead WHERE id = :id");
        $stmt->bindParam(':id', $opportunityId);
        $stmt->execute();
        $opportunity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($opportunity['sub_sector'] != $salesData['sub_sector']) {
            if (empty($salesData['sub_sector'])) {
                // Clear opportunity sub-sector if sales sub-sector is null
                $this->updateOpportunityField($opportunityId, 'sub_sector', '', $batchId);
                $result['messages'][] = 'Sub-sector cleared';
            } else {
                // Overwrite with sales sub-sector
                $this->updateOpportunityField($opportunityId, 'sub_sector', $salesData['sub_sector'], $batchId);
                $result['messages'][] = 'Sub-sector updated to "' . $salesData['sub_sector'] . '"';
            }
        }
        
        return $result;
    }
    
    /**
     * Level 6: Stage Validation and Volume Updates
     */
    private function level6_StageValidation($salesData, $opportunityId, $batchId) {
        $result = array('messages' => array());
        
        // Get current opportunity details
        $stmt = $this->db->prepare("
            SELECT lead_status, volume_converted, annual_potential, py_billed_volume
            FROM isteer_general_lead WHERE id = :id
        ");
        $stmt->bindParam(':id', $opportunityId);
        $stmt->execute();
        $opportunity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $currentStage = $opportunity['lead_status'];
        $currentVolume = floatval($opportunity['volume_converted']);
        $salesVolume = floatval($salesData['volume']);
        
        // Stage validation logic
        if (in_array($currentStage, array('SPANCOP', 'Lost', 'Sleep'))) {
            // Update to "Order" stage
            $this->updateOpportunityField($opportunityId, 'lead_status', 'Order', $batchId);
            $result['messages'][] = 'Stage updated to Order';
        } else if ($currentStage == 'Retention') {
            // Check previous year sales for same SKU
            if ($this->hasPreviousYearSales($salesData['registration_no'], $salesData['sku_code'])) {
                $result['messages'][] = 'Retention stage maintained - previous year sales found';
            } else {
                // Create new "Order" opportunity
                $newOrderData = $salesData;
                $newOrderData['opportunity_type'] = 'New Order';
                $this->createNewOpportunity($newOrderData, $batchId);
                $result['messages'][] = 'New Order opportunity created';
            }
        }
        
        // Volume updates
        $newVolume = $currentVolume + $salesVolume;
        $this->updateOpportunityField($opportunityId, 'volume_converted', $newVolume, $batchId);
        
        // Update annual potential if converted volume exceeds current value
        if ($newVolume > floatval($opportunity['annual_potential'])) {
            $this->updateOpportunityField($opportunityId, 'annual_potential', $newVolume, $batchId);
            $result['messages'][] = 'Annual potential updated';
        }
        
        $result['messages'][] = 'Volume updated: ' . $currentVolume . ' + ' . $salesVolume . ' = ' . $newVolume;
        
        return $result;
    }
    
    /**
     * Helper Functions
     */
    private function isValidGSTIN($gstin) {
        return preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin);
    }
    
    private function createNewOpportunity($salesData, $batchId) {
        $stmt = $this->db->prepare("
            INSERT INTO isteer_general_lead (
                cus_name, registration_no, dsr_name, sector, sub_sector,
                product_name, opportunity_name, opportunity_type, lead_status,
                volume_converted, source_from, integration_managed, 
                integration_batch_id, last_integration_update, entered_date_time
            ) VALUES (
                :cus_name, :registration_no, :dsr_name, :sector, :sub_sector,
                :product_name, :opportunity_name, :opportunity_type, :lead_status,
                :volume_converted, :source_from, 1, :batch_id, NOW(), NOW()
            )
        ");
        
        $opportunityName = $salesData['customer_name'];
        $opportunityType = isset($salesData['opportunity_type']) ? $salesData['opportunity_type'] : 'New Customer';
        $leadStatus = 'Order';
        $sourceFrom = 'Sales Integration';
        
        $stmt->bindParam(':cus_name', $salesData['customer_name']);
        $stmt->bindParam(':registration_no', $salesData['registration_no']);
        $stmt->bindParam(':dsr_name', $salesData['dsr_name']);
        $stmt->bindParam(':sector', $salesData['sector']);
        $stmt->bindParam(':sub_sector', $salesData['sub_sector']);
        $stmt->bindParam(':product_name', $salesData['product_family_name']);
        $stmt->bindParam(':opportunity_name', $opportunityName);
        $stmt->bindParam(':opportunity_type', $opportunityType);
        $stmt->bindParam(':lead_status', $leadStatus);
        $stmt->bindParam(':volume_converted', $salesData['volume']);
        $stmt->bindParam(':source_from', $sourceFrom);
        $stmt->bindParam(':batch_id', $batchId);
        
        $stmt->execute();
        return $this->db->lastInsertId();
    }
    
    private function updateOpportunityField($opportunityId, $fieldName, $newValue, $batchId) {
        // Get old value for audit
        $stmt = $this->db->prepare("SELECT $fieldName FROM isteer_general_lead WHERE id = :id");
        $stmt->bindParam(':id', $opportunityId);
        $stmt->execute();
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
        $oldValue = $oldData[$fieldName];
        
        // Update field and mark as integration-managed with timestamp
        $stmt = $this->db->prepare("
            UPDATE isteer_general_lead 
            SET $fieldName = :value, 
                integration_managed = 1, 
                integration_batch_id = :batch_id,
                last_integration_update = NOW()
            WHERE id = :id
        ");
        $stmt->bindParam(':value', $newValue);
        $stmt->bindParam(':batch_id', $batchId);
        $stmt->bindParam(':id', $opportunityId);
        $stmt->execute();
        
        // Log audit trail
        if ($this->auditLogger) {
            $this->auditLogger->logChange($opportunityId, $fieldName, $oldValue, $newValue, $batchId);
        }
    }
    
    private function createDSMAction($actionData) {
        $stmt = $this->db->prepare("
            INSERT INTO dsm_action_queue (
                registration_no, mismatch_level, mismatch_type, sales_data,
                opportunity_data, action_required, priority
            ) VALUES (
                :registration_no, :mismatch_level, :mismatch_type, :sales_data,
                :opportunity_data, :action_required, :priority
            )
        ");
        
        $stmt->bindParam(':registration_no', $actionData['registration_no']);
        $stmt->bindParam(':mismatch_level', $actionData['mismatch_level']);
        $stmt->bindParam(':mismatch_type', $actionData['mismatch_type']);
        $stmt->bindParam(':sales_data', $actionData['sales_data']);
        $stmt->bindParam(':opportunity_data', $actionData['opportunity_data']);
        $stmt->bindParam(':action_required', $actionData['action_required']);
        $stmt->bindParam(':priority', $actionData['priority']);
        
        $stmt->execute();
    }
    
    private function hasPreviousYearSales($gstin, $skuCode) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM isteer_sales_upload_master 
            WHERE registration_no = :gstin AND sku_code = :sku_code 
            AND YEAR(STR_TO_DATE(date, '%Y%m%d')) = YEAR(CURDATE()) - 1
        ");
        $stmt->bindParam(':gstin', $gstin);
        $stmt->bindParam(':sku_code', $skuCode);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }
}
?>