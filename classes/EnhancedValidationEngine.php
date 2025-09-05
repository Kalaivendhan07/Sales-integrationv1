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
    
    // ... (Include all other helper methods from original ValidationEngine)
    
    /**
     * Helper function to get DSR ID from name
     */
    private function getDSRId($dsrName) {
        // This should be implemented based on your DSR table structure
        // For now, returning a default value
        return 1;
    }
    
    // ... (Include other helper methods like isValidGSTIN, createNewOpportunity, etc.)
}
?>