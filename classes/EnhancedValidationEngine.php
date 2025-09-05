<?php
/**
 * Enhanced 6-Level Validation Engine with SKU, Tier, Call Plans, and Volume Discrepancy
 * PHP 5.3 Compatible
 */

require_once __DIR__ . '/../config/database.php';

class EnhancedValidationEngine {
    private $db;
    private $auditLogger;
    
    public function __construct($auditLogger = null) {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auditLogger = $auditLogger;
    }
    
    /**
     * Enhanced process sales record through 6-level validation with SKU/Tier/Call Plans
     */
    public function validateSalesRecord($salesData, $batchId) {
        $result = array(
            'status' => 'SUCCESS',
            'actions' => array(),
            'messages' => array(),
            'opportunity_id' => null,
            'up_sell_created' => false,
            'call_plans_updated' => false,
            'volume_discrepancy' => null,
            'sku_updated' => false
        );
        
        try {
            // Level 1: GSTIN Validation
            $level1Result = $this->level1_GSTINValidation($salesData, $batchId);
            if ($level1Result['status'] == 'FAILED') {
                return $level1Result;
            }
            
            $opportunityId = $level1Result['opportunity_id'];
            $result['opportunity_id'] = $opportunityId;
            
            // Level 2: DSR Validation with Call Plans
            $level2Result = $this->level2_DSRValidationWithCallPlans($salesData, $opportunityId, $batchId);
            if ($level2Result['needs_action']) {
                $result['actions'][] = $level2Result['action'];
            }
            if ($level2Result['call_plans_updated']) {
                $result['call_plans_updated'] = true;
                $result['messages'][] = 'Call plans reassigned due to DSR change';
            }
            
            // Level 3: Product Family Validation with Enhanced Splitting
            $level3Result = $this->level3_ProductFamilyValidationEnhanced($salesData, $opportunityId, $batchId);
            if ($level3Result['needs_action']) {
                $result['actions'][] = $level3Result['action'];
            }
            
            // Level 4: Sector Validation
            $level4Result = $this->level4_SectorValidation($salesData, $opportunityId, $batchId);
            $result['messages'] = array_merge($result['messages'], $level4Result['messages']);
            
            // Level 5: Sub-Sector Validation
            $level5Result = $this->level5_SubSectorValidation($salesData, $opportunityId, $batchId);
            $result['messages'] = array_merge($result['messages'], $level5Result['messages']);
            
            // Level 6: Enhanced Stage, Volume, SKU & Tier Validation
            $level6Result = $this->level6_EnhancedStageVolumeSkuValidation($salesData, $opportunityId, $batchId);
            $result['messages'] = array_merge($result['messages'], $level6Result['messages']);
            if ($level6Result['up_sell_created']) {
                $result['up_sell_created'] = true;
                $result['messages'][] = 'Up-Sell opportunity created due to tier upgrade';
            }
            if ($level6Result['sku_updated']) {
                $result['sku_updated'] = true;
                $result['messages'][] = 'SKU details updated in opportunity';
            }
            
            // Volume Discrepancy Tracking
            $discrepancyResult = $this->trackVolumeDiscrepancy($salesData, $opportunityId, $batchId);
            if ($discrepancyResult) {
                $result['volume_discrepancy'] = $discrepancyResult;
                $result['messages'][] = 'Volume discrepancy detected and tracked';
            }
            
        } catch (Exception $e) {
            $result['status'] = 'FAILED';
            $result['messages'][] = 'Enhanced validation error: ' . $e->getMessage();
            error_log('EnhancedValidationEngine Error: ' . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Level 1: GSTIN Validation (Enhanced)
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
            WHERE registration_no = :gstin
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
     * Level 2: DSR Validation with Call Plans Management
     */
    private function level2_DSRValidationWithCallPlans($salesData, $opportunityId, $batchId) {
        $result = array('needs_action' => false, 'action' => null, 'call_plans_updated' => false);
        
        // Get current opportunity DSR
        $stmt = $this->db->prepare("SELECT dsr_id, dsr_name FROM isteer_general_lead WHERE id = :id");
        $stmt->bindParam(':id', $opportunityId);
        $stmt->execute();
        $opportunity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Compare DSRs
        if ($opportunity['dsr_name'] != $salesData['dsr_name']) {
            // Update call plans when DSR changes
            $this->updateCallPlansForDSRChange($opportunityId, $salesData['dsr_name'], $batchId);
            $result['call_plans_updated'] = true;
            
            // Create DSM action for DSR mismatch
            $actionData = array(
                'registration_no' => $salesData['registration_no'],
                'mismatch_level' => 2,
                'mismatch_type' => 'DSR_MISMATCH',
                'sales_data' => json_encode($salesData),
                'opportunity_data' => json_encode($opportunity),
                'action_required' => 'DSR Change: Call plans updated. Choose DSR: Keep opportunity DSR (' . $opportunity['dsr_name'] . 
                                   ') or assign to sales DSR (' . $salesData['dsr_name'] . ')',
                'priority' => 'MEDIUM'
            );
            
            $this->createDSMAction($actionData);
            $result['needs_action'] = true;
            $result['action'] = 'DSR_MISMATCH_WITH_CALL_PLANS_UPDATE';
        }
        
        return $result;
    }
    
    /**
     * Level 3: Enhanced Product Family Validation
     */
    private function level3_ProductFamilyValidationEnhanced($salesData, $opportunityId, $batchId) {
        $result = array('needs_action' => false, 'action' => null);
        
        // Get current opportunity product families and details
        $stmt = $this->db->prepare("
            SELECT product_name, product_name_2, product_name_3, annual_potential, 
                   cus_name, registration_no, dsr_id, dsr_name, sector, sub_sector,
                   opportunity_name, opportunity_type, entered_date_time
            FROM isteer_general_lead WHERE id = :id
        ");
        $stmt->bindParam(':id', $opportunityId);
        $stmt->execute();
        $opportunity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $oppProducts = array_filter(array($opportunity['product_name'], $opportunity['product_name_2'], $opportunity['product_name_3']));
        $salesProduct = $salesData['product_family_name'];
        
        // Check if sales product family matches any opportunity product family
        if (!in_array($salesProduct, $oppProducts)) {
            // Check if it's Cross-Sell or Retention based on previous year sales
            $isRetention = $this->checkPreviousYearSales($salesData['registration_no'], $salesData['product_family_name']);
            
            if (!$isRetention) {
                // Create Cross-Sell opportunity
                $crossSellData = $salesData;
                $crossSellData['opportunity_type'] = 'Cross-Sell';
                $crossSellData['parent_opportunity_id'] = $opportunityId;
                $crossSellData['original_entered_date'] = $opportunity['entered_date_time'];
                
                $this->createNewOpportunity($crossSellData, $batchId);
                $result['needs_action'] = true;
                $result['action'] = 'CROSS_SELL_OPPORTUNITY_CREATED';
            } else {
                // It's retention - log but no action
                if ($this->auditLogger) {
                    $this->auditLogger->logChange(
                        $opportunityId,
                        'retention_validation',
                        'No Cross-Sell needed',
                        'Previous year sales found for ' . $salesProduct,
                        $batchId
                    );
                }
            }
        } else {
            // Product matches - check if opportunity has multiple products for splitting
            if (count($oppProducts) > 1) {
                // Split opportunity for matched product
                $this->splitOpportunityForProductEnhanced($opportunity, $salesData, $batchId, $opportunityId);
                $result['needs_action'] = true;
                $result['action'] = 'OPPORTUNITY_SPLIT_FOR_PRODUCT';
            }
        }
        
        return $result;
    }
    
    /**
     * Level 6: Enhanced Stage, Volume, SKU & Tier Validation
     */
    private function level6_EnhancedStageVolumeSkuValidation($salesData, $opportunityId, $batchId) {
        $result = array('messages' => array(), 'up_sell_created' => false, 'sku_updated' => false);
        
        // Get current opportunity details
        $stmt = $this->db->prepare("
            SELECT lead_status, volume_converted, annual_potential, py_billed_volume,
                   product_name, registration_no, cus_name, dsr_name, sector, sub_sector,
                   entered_date_time
            FROM isteer_general_lead WHERE id = :id
        ");
        $stmt->bindParam(':id', $opportunityId);
        $stmt->execute();
        $opportunity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $currentStage = $opportunity['lead_status'];
        $currentVolume = floatval($opportunity['volume_converted']);
        $salesVolume = floatval($salesData['volume']);
        
        // Check for tier upgrade (Up-Sell detection)
        $tierUpgrade = $this->checkTierUpgrade($opportunityId, $salesData);
        if ($tierUpgrade['is_upgrade']) {
            // Create Up-Sell opportunity
            $upSellData = $salesData;
            $upSellData['opportunity_type'] = 'Up-Sell';
            $upSellData['parent_opportunity_id'] = $opportunityId;
            $upSellData['original_entered_date'] = $opportunity['entered_date_time'];
            $upSellData['tier_upgrade_info'] = $tierUpgrade;
            
            $this->createNewOpportunity($upSellData, $batchId);
            $result['up_sell_created'] = true;
            $result['messages'][] = 'Up-Sell created: ' . $tierUpgrade['from_tier'] . ' → ' . $tierUpgrade['to_tier'];
        }
        
        // Update SKU details in opportunity
        $this->updateOpportunitySKUDetails($opportunityId, $salesData, $batchId);
        $result['sku_updated'] = true;
        
        // Stage validation logic
        if (in_array($currentStage, array('SPANCOP', 'Lost', 'Sleep'))) {
            $this->updateOpportunityField($opportunityId, 'lead_status', 'Order', $batchId);
            $result['messages'][] = 'Stage updated to Order';
        } else if ($currentStage == 'Retention') {
            // Special handling for Retention stage
            if ($this->hasPreviousYearSales($salesData['registration_no'], $salesData['sku_code'])) {
                $result['messages'][] = 'Retention stage maintained - previous year sales found';
            } else {
                // Create new "Order" opportunity for new product
                $newOrderData = $salesData;
                $newOrderData['opportunity_type'] = 'New Product';
                $newOrderData['original_entered_date'] = $opportunity['entered_date_time'];
                $this->createNewOpportunity($newOrderData, $batchId);
                $result['messages'][] = 'New Product opportunity created from Retention';
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
     * Check for tier upgrade (Mainstream to Premium)
     */
    private function checkTierUpgrade($opportunityId, $salesData) {
        $result = array('is_upgrade' => false, 'from_tier' => '', 'to_tier' => '');
        
        // Get current SKU tier from opportunity products
        $stmt = $this->db->prepare("
            SELECT op.product_name, s.tire_type as current_tier
            FROM isteer_opportunity_products op
            LEFT JOIN isteer_sales_upload_master s ON op.product_name = s.sku_code
            WHERE op.lead_id = :lead_id AND s.registration_no = (
                SELECT registration_no FROM isteer_general_lead WHERE id = :lead_id2
            )
            ORDER BY s.created_at DESC
            LIMIT 1
        ");
        $stmt->bindParam(':lead_id', $opportunityId);
        $stmt->bindParam(':lead_id2', $opportunityId);
        $stmt->execute();
        $currentTier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get new tier from sales data
        $salesTier = isset($salesData['tire_type']) ? $salesData['tire_type'] : 'Mainstream';
        
        if ($currentTier && $currentTier['current_tier'] == 'Mainstream' && $salesTier == 'Premium') {
            $result['is_upgrade'] = true;
            $result['from_tier'] = 'Mainstream';
            $result['to_tier'] = 'Premium';
        }
        
        return $result;
    }
    
    /**
     * Update opportunity SKU details
     */
    private function updateOpportunitySKUDetails($opportunityId, $salesData, $batchId) {
        // Check if SKU already exists for this opportunity
        $stmt = $this->db->prepare("
            SELECT id FROM isteer_opportunity_products 
            WHERE lead_id = :lead_id AND product_name = :sku_code
        ");
        $stmt->bindParam(':lead_id', $opportunityId);
        $stmt->bindParam(':sku_code', $salesData['sku_code']);
        $stmt->execute();
        $existingSKU = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingSKU) {
            // Update existing SKU
            $stmt = $this->db->prepare("
                UPDATE isteer_opportunity_products 
                SET volume = volume + :volume,
                    updated_by = 'INTEGRATION_SYSTEM',
                    updated_date = NOW()
                WHERE id = :id
            ");
            $stmt->bindParam(':volume', $salesData['volume']);
            $stmt->bindParam(':id', $existingSKU['id']);
            $stmt->execute();
        } else {
            // Insert new SKU
            $stmt = $this->db->prepare("
                INSERT INTO isteer_opportunity_products (
                    lead_id, product_id, product_name, volume, product_pack,
                    dsr_id, status, added_by, added_date
                ) VALUES (
                    :lead_id, :product_id, :product_name, :volume, :product_pack,
                    :dsr_id, 'A', 'INTEGRATION_SYSTEM', NOW()
                )
            ");
            
            $stmt->bindParam(':lead_id', $opportunityId);
            $stmt->bindParam(':product_id', $salesData['sku_code']); // Using SKU as product_id
            $stmt->bindParam(':product_name', $salesData['sku_code']);
            $stmt->bindParam(':volume', $salesData['volume']);
            $stmt->bindParam(':product_pack', $salesData['product_family_name']);
            $dsr_id = $this->getDSRId($salesData['dsr_name']);
            $stmt->bindParam(':dsr_id', $dsr_id);
            $stmt->execute();
        }
    }
    
    /**
     * Update call plans when DSR changes
     */
    private function updateCallPlansForDSRChange($opportunityId, $newDSRName, $batchId) {
        // Get opportunity's customer key (cmkey)
        $stmt = $this->db->prepare("SELECT registration_no FROM isteer_general_lead WHERE id = :id");
        $stmt->bindParam(':id', $opportunityId);
        $stmt->execute();
        $opportunity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($opportunity) {
            // Update active call plans to new DSR
            $stmt = $this->db->prepare("
                UPDATE isteer_call_plan 
                SET event_user_key = :new_dsr,
                    last_modified_by = 'INTEGRATION_SYSTEM',
                    last_modified_on = NOW(),
                    remarks = CONCAT(IFNULL(remarks, ''), ' [DSR Changed by Integration: ', :new_dsr2, ']')
                WHERE cmkey = :cmkey 
                AND active_status = 'A'
                AND (dsr_status IS NULL OR dsr_status != 'COMPLETED')
            ");
            
            $stmt->bindParam(':new_dsr', $newDSRName);
            $stmt->bindParam(':new_dsr2', $newDSRName);
            $stmt->bindParam(':cmkey', $opportunity['registration_no']);
            $stmt->execute();
        }
    }
    
    /**
     * Track volume discrepancy between opportunity and sales data
     */
    private function trackVolumeDiscrepancy($salesData, $opportunityId, $batchId) {
        // Get opportunity volume for this product
        $stmt = $this->db->prepare("
            SELECT volume_converted FROM isteer_general_lead WHERE id = :id
        ");
        $stmt->bindParam(':id', $opportunityId);
        $stmt->execute();
        $opp = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $opportunityVolume = floatval($opp['volume_converted']);
        $selloutVolume = floatval($salesData['volume']);
        $discrepancy = $selloutVolume - $opportunityVolume;
        $discrepancyPercentage = $opportunityVolume > 0 ? ($discrepancy / $opportunityVolume) * 100 : 0;
        
        $discrepancyType = 'MATCH';
        if ($discrepancy > 0) {
            $discrepancyType = 'OVER_SALE';
        } else if ($discrepancy < 0) {
            $discrepancyType = 'UNDER_SALE';
        }
        
        // Only track significant discrepancies (>5% or >100L)
        if (abs($discrepancyPercentage) > 5 || abs($discrepancy) > 100) {
            $stmt = $this->db->prepare("
                INSERT INTO volume_discrepancy_tracking (
                    lead_id, registration_no, product_family, sku_code,
                    opportunity_volume, sellout_volume, discrepancy_volume,
                    discrepancy_percentage, discrepancy_type, integration_batch_id
                ) VALUES (
                    :lead_id, :registration_no, :product_family, :sku_code,
                    :opp_volume, :sellout_volume, :discrepancy_volume,
                    :discrepancy_percentage, :discrepancy_type, :batch_id
                )
            ");
            
            $stmt->bindParam(':lead_id', $opportunityId);
            $stmt->bindParam(':registration_no', $salesData['registration_no']);
            $stmt->bindParam(':product_family', $salesData['product_family_name']);
            $stmt->bindParam(':sku_code', $salesData['sku_code']);
            $stmt->bindParam(':opp_volume', $opportunityVolume);
            $stmt->bindParam(':sellout_volume', $selloutVolume);
            $stmt->bindParam(':discrepancy_volume', $discrepancy);
            $stmt->bindParam(':discrepancy_percentage', $discrepancyPercentage);
            $stmt->bindParam(':discrepancy_type', $discrepancyType);
            $stmt->bindParam(':batch_id', $batchId);
            $stmt->execute();
            
            return array(
                'type' => $discrepancyType,
                'volume' => $discrepancy,
                'percentage' => $discrepancyPercentage
            );
        }
        
        return null;
    }
    
    /**
     * Check previous year sales for Cross-Sell vs Retention determination
     */
    private function checkPreviousYearSales($gstin, $productFamily) {
        $currentYear = date('Y');
        $previousYear = $currentYear - 1;
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM isteer_sales_upload_master 
            WHERE registration_no = :gstin 
            AND product_family_name = :product_family
            AND YEAR(STR_TO_DATE(date, '%Y%m%d')) = :previous_year
        ");
        $stmt->bindParam(':gstin', $gstin);
        $stmt->bindParam(':product_family', $productFamily);
        $stmt->bindParam(':previous_year', $previousYear);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
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
                product_name, opportunity_name, opp_type, lead_status,
                volume_converted, source_from, integration_managed, 
                integration_batch_id, last_integration_update, entered_date_time
            ) VALUES (
                :cus_name, :registration_no, :dsr_name, :sector, :sub_sector,
                :product_name, :opportunity_name, :opp_type, :lead_status,
                :volume_converted, :source_from, 1, :batch_id, NOW(), :entered_date_time
            )
        ");
        
        $opportunityName = $salesData['customer_name'];
        $oppType = isset($salesData['opportunity_type']) ? $salesData['opportunity_type'] : 'New Customer';
        $leadStatus = 'Order';
        $sourceFrom = 'Sales Integration';
        
        // Use original entered date for split opportunities, current date for new customers
        $enteredDateTime = isset($salesData['original_entered_date']) ? $salesData['original_entered_date'] : date('Y-m-d H:i:s');
        
        $stmt->bindParam(':cus_name', $salesData['customer_name']);
        $stmt->bindParam(':registration_no', $salesData['registration_no']);
        $stmt->bindParam(':dsr_name', $salesData['dsr_name']);
        $stmt->bindParam(':sector', $salesData['sector']);
        $stmt->bindParam(':sub_sector', $salesData['sub_sector']);
        $stmt->bindParam(':product_name', $salesData['product_family_name']);
        $stmt->bindParam(':opportunity_name', $opportunityName);
        $stmt->bindParam(':opp_type', $oppType);
        $stmt->bindParam(':lead_status', $leadStatus);
        $stmt->bindParam(':volume_converted', $salesData['volume']);
        $stmt->bindParam(':source_from', $sourceFrom);
        $stmt->bindParam(':batch_id', $batchId);
        $stmt->bindParam(':entered_date_time', $enteredDateTime);
        
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
    
    private function splitOpportunityForProductEnhanced($opportunity, $salesData, $batchId, $originalOpportunityId) {
        $salesProduct = $salesData['product_family_name'];
        $salesVolume = floatval($salesData['volume']);
        $currentAnnualPotential = floatval($opportunity['annual_potential']);
        
        // Create new opportunity for the matched product with SAME lead generation date
        $newOpportunityData = $salesData;
        $newOpportunityData['opportunity_type'] = 'Product Split';
        $newOpportunityData['annual_potential'] = $salesVolume; // Targeted volume = Invoiced volume
        $newOpportunityData['parent_opportunity_id'] = $originalOpportunityId;
        $newOpportunityData['original_entered_date'] = $opportunity['entered_date_time']; // Preserve original date
        
        $newOpportunityId = $this->createNewOpportunity($newOpportunityData, $batchId);
        
        // Update original opportunity: Remove matched product and adjust volume
        $this->removeProductFromOpportunity($originalOpportunityId, $salesProduct, $salesVolume, $batchId);
        
        // Log the split action
        if ($this->auditLogger) {
            $this->auditLogger->logChange(
                $originalOpportunityId, 
                'opportunity_split', 
                'Single opportunity with multiple products', 
                'Split into separate opportunities for product: ' . $salesProduct,
                $batchId
            );
        }
        
        return $newOpportunityId;
    }
    
    private function removeProductFromOpportunity($opportunityId, $productToRemove, $volumeToSubtract, $batchId) {
        // Get current opportunity details
        $stmt = $this->db->prepare("
            SELECT product_name, product_name_2, product_name_3, annual_potential
            FROM isteer_general_lead WHERE id = :id
        ");
        $stmt->bindParam(':id', $opportunityId);
        $stmt->execute();
        $opportunity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Determine which product field to clear and reorganize remaining products
        $remainingProducts = array();
        if ($opportunity['product_name'] != $productToRemove && !empty($opportunity['product_name'])) {
            $remainingProducts[] = $opportunity['product_name'];
        }
        if ($opportunity['product_name_2'] != $productToRemove && !empty($opportunity['product_name_2'])) {
            $remainingProducts[] = $opportunity['product_name_2'];
        }
        if ($opportunity['product_name_3'] != $productToRemove && !empty($opportunity['product_name_3'])) {
            $remainingProducts[] = $opportunity['product_name_3'];
        }
        
        // Reassign remaining products
        $newProduct1 = isset($remainingProducts[0]) ? $remainingProducts[0] : '';
        $newProduct2 = isset($remainingProducts[1]) ? $remainingProducts[1] : '';
        $newProduct3 = '';
        
        // Calculate new annual potential
        $newAnnualPotential = max(0, floatval($opportunity['annual_potential']) - $volumeToSubtract);
        
        // Update the original opportunity
        $stmt = $this->db->prepare("
            UPDATE isteer_general_lead 
            SET product_name = :product1,
                product_name_2 = :product2, 
                product_name_3 = :product3,
                annual_potential = :new_potential,
                integration_managed = 1,
                integration_batch_id = :batch_id,
                last_integration_update = NOW()
            WHERE id = :id
        ");
        
        $stmt->bindParam(':product1', $newProduct1);
        $stmt->bindParam(':product2', $newProduct2);
        $stmt->bindParam(':product3', $newProduct3);
        $stmt->bindParam(':new_potential', $newAnnualPotential);
        $stmt->bindParam(':batch_id', $batchId);
        $stmt->bindParam(':id', $opportunityId);
        $stmt->execute();
        
        // Log the changes
        if ($this->auditLogger) {
            $this->auditLogger->logChange($opportunityId, 'product_removed', $productToRemove, 'Product split to new opportunity', $batchId);
            $this->auditLogger->logChange($opportunityId, 'annual_potential', $opportunity['annual_potential'], $newAnnualPotential, $batchId);
        }
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
     * Helper function to get DSR ID from name
     */
    private function getDSRId($dsrName) {
        // Simple implementation - you may need to adjust based on your DSR table
        $stmt = $this->db->prepare("SELECT dsr_id FROM isteer_general_lead WHERE dsr_name = :dsr_name LIMIT 1");
        $stmt->bindParam(':dsr_name', $dsrName);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['dsr_id'] : 1; // Default to 1 if not found
    }
}
?>