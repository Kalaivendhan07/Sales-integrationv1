<?php
/**
 * Sales Return Processor for Pipeline Manager Integration
 * PHP 5.3 Compatible
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/AuditLogger.php';

class SalesReturnProcessor {
    private $db;
    private $auditLogger;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auditLogger = new AuditLogger();
    }
    
    /**
     * Process sales returns - handles both full and partial returns
     */
    public function processSalesReturn($returnData, $batchId) {
        $result = array(
            'status' => 'SUCCESS',
            'opportunity_id' => null,
            'return_amount' => 0,
            'new_volume' => 0,
            'stage_changed' => false,
            'messages' => array()
        );
        
        try {
            // Find opportunity by GSTIN and product
            $opportunity = $this->findOpportunityForReturn($returnData);
            
            if (!$opportunity) {
                throw new Exception('No matching opportunity found for return: ' . $returnData['registration_no'] . ' - ' . $returnData['product_family_name']);
            }
            
            $result['opportunity_id'] = $opportunity['id'];
            
            // Create backup before processing
            $this->auditLogger->createBackup($opportunity['id'], $batchId);
            
            // Process the return
            $this->processReturn($opportunity, $returnData, $batchId, $result);
            
        } catch (Exception $e) {
            $result['status'] = 'FAILED';
            $result['messages'][] = 'Return processing failed: ' . $e->getMessage();
            error_log('SalesReturnProcessor Error: ' . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Find opportunity for return processing
     */
    private function findOpportunityForReturn($returnData) {
        // Look for opportunities with matching GSTIN and product
        $stmt = $this->db->prepare("
            SELECT * FROM isteer_general_lead 
            WHERE registration_no = :gstin 
            AND (product_name = :product OR product_name_2 = :product OR product_name_3 = :product)
            AND lead_status IN ('Order', 'Qualified')
            ORDER BY volume_converted DESC
            LIMIT 1
        ");
        
        $stmt->bindParam(':gstin', $returnData['registration_no']);
        $stmt->bindParam(':product', $returnData['product_family_name']);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Process the actual return
     */
    private function processReturn($opportunity, $returnData, $batchId, &$result) {
        $returnVolume = floatval($returnData['return_volume']);
        $currentVolume = floatval($opportunity['volume_converted']);
        $currentPotential = floatval($opportunity['annual_potential']);
        
        // Validate return amount
        if ($returnVolume > $currentVolume) {
            throw new Exception('Return volume (' . $returnVolume . 'L) cannot exceed sold volume (' . $currentVolume . 'L)');
        }
        
        $newVolume = $currentVolume - $returnVolume;
        $result['return_amount'] = $returnVolume;
        $result['new_volume'] = $newVolume;
        
        // Determine new stage based on business logic
        $newStage = $this->determineStageAfterReturn($opportunity, $newVolume, $currentPotential);
        $stageChanged = ($newStage != $opportunity['lead_status']);
        $result['stage_changed'] = $stageChanged;
        
        // Determine new annual potential (PRESERVE unless full return to zero)
        $newPotential = $this->determineNewPotential($currentPotential, $newVolume, $returnVolume, $currentVolume);
        
        // Update opportunity
        $this->updateOpportunityAfterReturn(
            $opportunity['id'], 
            $newVolume, 
            $newPotential, 
            $newStage, 
            $batchId
        );
        
        // Log all changes
        $this->logReturnChanges($opportunity, $newVolume, $newPotential, $newStage, $returnData, $batchId);
        
        // Add messages
        $result['messages'][] = 'Return processed: ' . $returnVolume . 'L returned';
        $result['messages'][] = 'Volume updated: ' . $currentVolume . 'L → ' . $newVolume . 'L';
        $result['messages'][] = 'Annual potential: ' . $currentPotential . 'L → ' . $newPotential . 'L';
        
        if ($stageChanged) {
            $result['messages'][] = 'Stage changed: ' . $opportunity['lead_status'] . ' → ' . $newStage;
        }
        
        if ($newVolume == 0) {
            $result['messages'][] = 'Full return completed - opportunity moved to Suspect stage (requires investigation)';
        } else {
            $result['messages'][] = 'Partial return - opportunity remains active with reduced volume';
        }
    }
    
    /**
     * Determine stage after return based on business logic
     */
    private function determineStageAfterReturn($opportunity, $newVolume, $currentPotential) {
        if ($newVolume == 0) {
            // Full return - move to Suspect stage (raises questions about customer commitment)
            return 'Suspect';
        } else if ($newVolume > 0) {
            // Partial return - keep Order stage (still has active sales)
            return 'Order';
        }
        
        return $opportunity['lead_status']; // Default - no change
    }
    
    /**
     * Determine new annual potential after return
     */
    private function determineNewPotential($currentPotential, $newVolume, $returnVolume, $originalVolume) {
        // BUSINESS LOGIC: Preserve potential to show customer capacity
        // Exception: If customer returns everything multiple times, may reduce potential
        
        if ($newVolume == 0) {
            // Full return - keep potential (customer proved they can buy this volume)
            return $currentPotential;
        } else {
            // Partial return - keep potential or adjust upward if needed
            return max($currentPotential, $newVolume);
        }
    }
    
    /**
     * Update opportunity after return processing
     */
    private function updateOpportunityAfterReturn($opportunityId, $newVolume, $newPotential, $newStage, $batchId) {
        $stmt = $this->db->prepare("
            UPDATE isteer_general_lead 
            SET volume_converted = :volume,
                annual_potential = :potential,
                lead_status = :stage,
                integration_batch_id = :batch_id,
                last_integration_update = NOW()
            WHERE id = :id
        ");
        
        $stmt->bindParam(':volume', $newVolume);
        $stmt->bindParam(':potential', $newPotential);
        $stmt->bindParam(':stage', $newStage);
        $stmt->bindParam(':batch_id', $batchId);
        $stmt->bindParam(':id', $opportunityId);
        
        $stmt->execute();
    }
    
    /**
     * Log return changes for audit trail
     */
    private function logReturnChanges($opportunity, $newVolume, $newPotential, $newStage, $returnData, $batchId) {
        // Log volume change
        $this->auditLogger->logChange(
            $opportunity['id'],
            'volume_converted',
            $opportunity['volume_converted'],
            $newVolume,
            $batchId,
            'RETURN_SYSTEM'
        );
        
        // Log potential change if different
        if ($newPotential != $opportunity['annual_potential']) {
            $this->auditLogger->logChange(
                $opportunity['id'],
                'annual_potential',
                $opportunity['annual_potential'],
                $newPotential,
                $batchId,
                'RETURN_SYSTEM'
            );
        }
        
        // Log stage change if different
        if ($newStage != $opportunity['lead_status']) {
            $this->auditLogger->logChange(
                $opportunity['id'],
                'lead_status',
                $opportunity['lead_status'],
                $newStage,
                $batchId,
                'RETURN_SYSTEM'
            );
        }
        
        // Log return transaction
        $this->auditLogger->logChange(
            $opportunity['id'],
            'sales_return',
            '0.00',
            $returnData['return_volume'],
            $batchId,
            'RETURN_SYSTEM'
        );
    }
    
    /**
     * Get return history for an opportunity
     */
    public function getReturnHistory($opportunityId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM integration_audit_log 
                WHERE lead_id = :lead_id 
                AND field_name = 'sales_return'
                AND status = 'ACTIVE'
                ORDER BY data_changed_on DESC
            ");
            $stmt->bindParam(':lead_id', $opportunityId);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Get Return History Error: ' . $e->getMessage());
            return array();
        }
    }
}
?></content>
    </file>
</invoke>