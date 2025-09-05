<?php
/**
 * Audit Logger for Integration Changes
 * PHP 5.3 Compatible
 */

require_once __DIR__ . '/../config/database.php';

class AuditLogger {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Log field changes with audit trail
     */
    public function logChange($leadId, $fieldName, $oldValue, $newValue, $batchId, $updatedBy = 'SYSTEM') {
        try {
            // Get old value entry date
            $oldValueDate = $this->getFieldEntryDate($leadId, $fieldName);
            
            $stmt = $this->db->prepare("
                INSERT INTO integration_audit_log (
                    lead_id, field_name, old_value, new_value, 
                    old_value_entered_date, integration_batch_id, updated_by
                ) VALUES (
                    :lead_id, :field_name, :old_value, :new_value,
                    :old_value_entered_date, :batch_id, :updated_by
                )
            ");
            
            $stmt->bindParam(':lead_id', $leadId);
            $stmt->bindParam(':field_name', $fieldName);
            $stmt->bindParam(':old_value', $oldValue);
            $stmt->bindParam(':new_value', $newValue);
            $stmt->bindParam(':old_value_entered_date', $oldValueDate);
            $stmt->bindParam(':batch_id', $batchId);
            $stmt->bindParam(':updated_by', $updatedBy);
            
            $stmt->execute();
            
            return $this->db->lastInsertId();
            
        } catch (Exception $e) {
            error_log('AuditLogger Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create backup snapshot before changes
     */
    public function createBackup($leadId, $batchId) {
        try {
            // Get complete lead data
            $stmt = $this->db->prepare("SELECT * FROM isteer_general_lead WHERE id = :id");
            $stmt->bindParam(':id', $leadId);
            $stmt->execute();
            $leadData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leadData) {
                return false;
            }
            
            // Calculate expiration date (120 days)
            $expiresAt = date('Y-m-d H:i:s', strtotime('+120 days'));
            
            $stmt = $this->db->prepare("
                INSERT INTO integration_backup (
                    lead_id, backup_data, batch_id, expires_at
                ) VALUES (
                    :lead_id, :backup_data, :batch_id, :expires_at
                )
            ");
            
            $stmt->bindParam(':lead_id', $leadId);
            $stmt->bindParam(':backup_data', json_encode($leadData));
            $stmt->bindParam(':batch_id', $batchId);
            $stmt->bindParam(':expires_at', $expiresAt);
            
            $stmt->execute();
            
            return $this->db->lastInsertId();
            
        } catch (Exception $e) {
            error_log('Backup Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get audit history for a lead
     */
    public function getAuditHistory($leadId, $limit = 100) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM integration_audit_log 
                WHERE lead_id = :lead_id AND status = 'ACTIVE'
                ORDER BY data_changed_on DESC 
                LIMIT :limit
            ");
            $stmt->bindParam(':lead_id', $leadId);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Get Audit History Error: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Rollback changes for a specific batch
     */
    public function rollbackBatch($batchId) {
        $this->db->beginTransaction();
        
        try {
            // Get all changes for this batch
            $stmt = $this->db->prepare("
                SELECT * FROM integration_audit_log 
                WHERE integration_batch_id = :batch_id AND status = 'ACTIVE'
                ORDER BY data_changed_on DESC
            ");
            $stmt->bindParam(':batch_id', $batchId);
            $stmt->execute();
            $changes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $rolledBackCount = 0;
            
            foreach ($changes as $change) {
                // Restore old value
                $sql = "UPDATE isteer_general_lead SET " . $change['field_name'] . " = :old_value WHERE id = :lead_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':old_value', $change['old_value']);
                $stmt->bindParam(':lead_id', $change['lead_id']);
                $stmt->execute();
                
                // Mark audit record as reverted
                $stmt = $this->db->prepare("
                    UPDATE integration_audit_log 
                    SET status = 'REVERTED' 
                    WHERE audit_id = :audit_id
                ");
                $stmt->bindParam(':audit_id', $change['audit_id']);
                $stmt->execute();
                
                $rolledBackCount++;
            }
            
            $this->db->commit();
            
            return array(
                'success' => true,
                'rolled_back_changes' => $rolledBackCount,
                'message' => "Successfully rolled back $rolledBackCount changes for batch $batchId"
            );
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Rollback Error: ' . $e->getMessage());
            
            return array(
                'success' => false,
                'message' => 'Rollback failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Clean up expired backup records
     */
    public function cleanupExpiredBackups() {
        try {
            $stmt = $this->db->prepare("DELETE FROM integration_backup WHERE expires_at < NOW()");
            $stmt->execute();
            $deletedCount = $stmt->rowCount();
            
            return array(
                'success' => true,
                'deleted_records' => $deletedCount,
                'message' => "Cleaned up $deletedCount expired backup records"
            );
            
        } catch (Exception $e) {
            error_log('Cleanup Error: ' . $e->getMessage());
            
            return array(
                'success' => false,
                'message' => 'Cleanup failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Get field entry date from last_updated_date_time
     */
    private function getFieldEntryDate($leadId, $fieldName) {
        try {
            $stmt = $this->db->prepare("SELECT last_updated_date_time FROM isteer_general_lead WHERE id = :id");
            $stmt->bindParam(':id', $leadId);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result['last_updated_date_time'] : null;
            
        } catch (Exception $e) {
            error_log('Get Field Entry Date Error: ' . $e->getMessage());
            return null;
        }
    }
}
?>