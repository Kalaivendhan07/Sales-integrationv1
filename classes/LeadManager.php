<?php
/**
 * Lead Management with Business Rule Enforcement
 * PHP 5.3 Compatible
 */

require_once __DIR__ . '/../config/database.php';

class LeadManager {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Check if a lead is integration-managed
     */
    public function isIntegrationManaged($leadId) {
        $stmt = $this->db->prepare("SELECT integration_managed FROM isteer_general_lead WHERE id = :id");
        $stmt->bindParam(':id', $leadId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['integration_managed'] == 1;
    }
    
    /**
     * Prevent deletion of integration-managed leads
     */
    public function canDeleteLead($leadId) {
        if ($this->isIntegrationManaged($leadId)) {
            return array(
                'allowed' => false,
                'reason' => 'Integration-managed leads cannot be deleted. They are protected by business rules.'
            );
        }
        
        return array('allowed' => true, 'reason' => '');
    }
    
    /**
     * Prevent users from manually setting lead status to "Order"
     */
    public function canUpdateLeadStatus($leadId, $newStatus, $updatedBy = 'Manual') {
        // Only integration system can set status to "Order"
        if ($newStatus == 'Order' && $updatedBy != 'SYSTEM') {
            return array(
                'allowed' => false,
                'reason' => 'Only the integration system can set lead status to "Order". This stage is reserved for actual sales transactions.'
            );
        }
        
        // Integration-managed leads have additional restrictions
        if ($this->isIntegrationManaged($leadId)) {
            $restrictedStatuses = array('Order', 'Converted');
            if (in_array($newStatus, $restrictedStatuses) && $updatedBy != 'SYSTEM') {
                return array(
                    'allowed' => false,
                    'reason' => 'Integration-managed leads cannot be manually moved to ' . $newStatus . ' status.'
                );
            }
        }
        
        return array('allowed' => true, 'reason' => '');
    }
    
    /**
     * Check if specific fields can be updated for integration-managed leads
     */
    public function canUpdateField($leadId, $fieldName, $updatedBy = 'Manual') {
        if (!$this->isIntegrationManaged($leadId)) {
            return array('allowed' => true, 'reason' => '');
        }
        
        // Fields that only integration system can update
        $systemOnlyFields = array(
            'registration_no',      // GSTIN
            'volume_converted',     // Sales volume
            'annual_potential',     // Calculated by system
            'py_billed_volume',     // Previous year volume
            'source_from',          // Source identifier
            'integration_managed',  // Management flag
            'integration_batch_id', // Batch tracking
            'last_integration_update'
        );
        
        if (in_array($fieldName, $systemOnlyFields) && $updatedBy != 'SYSTEM') {
            return array(
                'allowed' => false,
                'reason' => "Field '$fieldName' can only be updated by the integration system for integration-managed leads."
            );
        }
        
        // Fields with restrictions but allowed with warnings
        $restrictedFields = array(
            'sector',           // Integration overrides
            'sub_sector',       // Integration overrides
            'dsr_name',         // May conflict with sales data
            'lead_status'       // Special status rules apply
        );
        
        if (in_array($fieldName, $restrictedFields) && $updatedBy != 'SYSTEM') {
            return array(
                'allowed' => true,
                'reason' => '',
                'warning' => "Manual changes to '$fieldName' may be overridden by future integration updates."
            );
        }
        
        return array('allowed' => true, 'reason' => '');
    }
    
    /**
     * Safe lead deletion with protection
     */
    public function deleteLead($leadId, $updatedBy = 'Manual') {
        $canDelete = $this->canDeleteLead($leadId);
        
        if (!$canDelete['allowed']) {
            throw new Exception($canDelete['reason']);
        }
        
        // Soft delete instead of hard delete for audit trail
        $stmt = $this->db->prepare("
            UPDATE isteer_general_lead 
            SET status = 'D', last_updated_date_time = NOW() 
            WHERE id = :id
        ");
        $stmt->bindParam(':id', $leadId);
        $stmt->execute();
        
        return array(
            'success' => true,
            'message' => 'Lead deleted successfully'
        );
    }
    
    /**
     * Safe lead status update with business rules
     */
    public function updateLeadStatus($leadId, $newStatus, $updatedBy = 'Manual') {
        $canUpdate = $this->canUpdateLeadStatus($leadId, $newStatus, $updatedBy);
        
        if (!$canUpdate['allowed']) {
            throw new Exception($canUpdate['reason']);
        }
        
        $stmt = $this->db->prepare("
            UPDATE isteer_general_lead 
            SET lead_status = :status, last_updated_date_time = NOW() 
            WHERE id = :id
        ");
        $stmt->bindParam(':status', $newStatus);
        $stmt->bindParam(':id', $leadId);
        $stmt->execute();
        
        $result = array(
            'success' => true,
            'message' => 'Lead status updated successfully'
        );
        
        if (isset($canUpdate['warning'])) {
            $result['warning'] = $canUpdate['warning'];
        }
        
        return $result;
    }
    
    /**
     * Get integration-managed leads
     */
    public function getIntegrationManagedLeads($limit = 100) {
        $stmt = $this->db->prepare("
            SELECT id, cus_name, registration_no, dsr_name, lead_status, 
                   volume_converted, integration_batch_id, 
                   last_integration_update, entered_date_time
            FROM isteer_general_lead 
            WHERE integration_managed = 1 AND status = 'A'
            ORDER BY last_integration_update DESC, id DESC
            LIMIT :limit
        ");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get lead management summary
     */
    public function getLeadManagementSummary() {
        $summary = array();
        
        // Total leads
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM isteer_general_lead WHERE status = 'A'");
        $stmt->execute();
        $summary['total_active_leads'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Integration-managed leads
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM isteer_general_lead WHERE integration_managed = 1 AND status = 'A'");
        $stmt->execute();
        $summary['integration_managed_leads'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Manual leads
        $summary['manual_leads'] = $summary['total_active_leads'] - $summary['integration_managed_leads'];
        
        // Leads in Order status
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM isteer_general_lead WHERE lead_status = 'Order' AND status = 'A'");
        $stmt->execute();
        $summary['order_status_leads'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Protected leads (cannot be deleted)
        $summary['protected_leads'] = $summary['integration_managed_leads'];
        
        return $summary;
    }
    
    /**
     * Validate lead operation before execution
     */
    public function validateOperation($operation, $leadId, $params = array()) {
        $updatedBy = isset($params['updated_by']) ? $params['updated_by'] : 'Manual';
        
        switch ($operation) {
            case 'delete':
                return $this->canDeleteLead($leadId);
                
            case 'update_status':
                $newStatus = isset($params['new_status']) ? $params['new_status'] : '';
                return $this->canUpdateLeadStatus($leadId, $newStatus, $updatedBy);
                
            case 'update_field':
                $fieldName = isset($params['field_name']) ? $params['field_name'] : '';
                return $this->canUpdateField($leadId, $fieldName, $updatedBy);
                
            default:
                return array('allowed' => true, 'reason' => '');
        }
    }
}
?>