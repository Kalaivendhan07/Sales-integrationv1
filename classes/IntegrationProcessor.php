<?php
/**
 * Main Integration Processor for Sales-Opportunity Integration
 * PHP 5.3 Compatible
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ValidationEngine.php';
require_once __DIR__ . '/AuditLogger.php';

class IntegrationProcessor {
    private $db;
    private $validationEngine;
    private $auditLogger;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auditLogger = new AuditLogger();
        $this->validationEngine = new ValidationEngine($this->auditLogger);
    }
    
    /**
     * Process sales data file (CSV/Excel)
     */
    public function processSalesFile($filePath, $fileType = 'csv') {
        $batchId = $this->generateBatchId();
        
        $result = array(
            'batch_id' => $batchId,
            'status' => 'SUCCESS',
            'total_records' => 0,
            'processed_records' => 0,
            'failed_records' => 0,
            'new_opportunities' => 0,
            'updated_opportunities' => 0,
            'dsm_actions_created' => 0,
            'errors' => array(),
            'messages' => array()
        );
        
        try {
            // Initialize statistics
            $this->initializeStatistics($batchId);
            
            // Read and stage sales data
            $salesRecords = $this->readSalesFile($filePath, $fileType);
            $result['total_records'] = count($salesRecords);
            
            if (empty($salesRecords)) {
                throw new Exception('No valid sales records found in file');
            }
            
            // Stage records for processing
            $this->stageSalesRecords($salesRecords, $batchId);
            
            // Process each staged record
            foreach ($salesRecords as $index => $salesData) {
                try {
                    $this->processSalesRecord($salesData, $batchId, $result);
                    $result['processed_records']++;
                } catch (Exception $e) {
                    $result['failed_records']++;
                    $result['errors'][] = "Row " . ($index + 1) . ": " . $e->getMessage();
                    
                    // Update staging record with error
                    $this->updateStagingRecord($salesData, 'FAILED', $e->getMessage(), $batchId);
                }
            }
            
            // Update final statistics
            $this->updateStatistics($batchId, $result);
            
            $result['messages'][] = "Processing completed. {$result['processed_records']} of {$result['total_records']} records processed successfully.";
            
        } catch (Exception $e) {
            $result['status'] = 'FAILED';
            $result['errors'][] = 'Processing failed: ' . $e->getMessage();
            error_log('IntegrationProcessor Error: ' . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Process individual sales record
     */
    private function processSalesRecord($salesData, $batchId, &$result) {
        // Create backup before processing
        $existingOpportunity = $this->findExistingOpportunity($salesData['registration_no']);
        if ($existingOpportunity) {
            $this->auditLogger->createBackup($existingOpportunity['id'], $batchId);
        }
        
        // Run through validation engine
        $validationResult = $this->validationEngine->validateSalesRecord($salesData, $batchId);
        
        if ($validationResult['status'] == 'FAILED') {
            throw new Exception('Validation failed: ' . implode(', ', $validationResult['messages']));
        }
        
        // Update counters based on validation results
        if ($existingOpportunity) {
            $result['updated_opportunities']++;
        } else {
            $result['new_opportunities']++;
        }
        
        $result['dsm_actions_created'] += count($validationResult['actions']);
        $result['messages'] = array_merge($result['messages'], $validationResult['messages']);
        
        // Update staging record as processed
        $this->updateStagingRecord($salesData, 'PROCESSED', null, $batchId);
    }
    
    /**
     * Read sales file and convert to array
     */
    private function readSalesFile($filePath, $fileType) {
        $salesRecords = array();
        
        if ($fileType == 'csv') {
            $salesRecords = $this->readCSVFile($filePath);
        } else if ($fileType == 'excel') {
            // For PHP 5.3, we'll use a simple CSV export from Excel
            // In production, you might want to use PHPExcel for PHP 5.3
            $salesRecords = $this->readCSVFile($filePath);
        }
        
        return $salesRecords;
    }
    
    /**
     * Read CSV file
     */
    private function readCSVFile($filePath) {
        $salesRecords = array();
        
        if (!file_exists($filePath)) {
            throw new Exception('Sales file not found: ' . $filePath);
        }
        
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception('Could not open sales file');
        }
        
        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            throw new Exception('Invalid CSV file - no headers found');
        }
        
        // Map CSV headers to our expected fields
        $headerMap = $this->createHeaderMap($headers);
        
        // Read data rows
        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== FALSE) {
            $rowNumber++;
            
            if (count($row) < count($headers)) {
                continue; // Skip incomplete rows
            }
            
            $salesData = array();
            foreach ($headerMap as $csvIndex => $fieldName) {
                $salesData[$fieldName] = isset($row[$csvIndex]) ? trim($row[$csvIndex]) : '';
            }
            
            // Validate required fields
            if ($this->validateRequiredFields($salesData)) {
                $salesRecords[] = $salesData;
            }
        }
        
        fclose($handle);
        return $salesRecords;
    }
    
    /**
     * Create header mapping from CSV to our field names
     */
    private function createHeaderMap($headers) {
        $headerMap = array();
        
        // Map based on your sample data structure
        $fieldMappings = array(
            'Invoice Date.' => 'invoice_date',
            'DSR Name' => 'dsr_name', 
            'Customer Name' => 'customer_name',
            'Sector' => 'sector',
            'Sub Sector' => 'sub_sector',
            'SKU Code' => 'sku_code',
            'Volume (L)' => 'volume',
            'Invoice No.' => 'invoice_no',
            'Registration No' => 'registration_no',
            'Product Family' => 'product_family_name'
        );
        
        foreach ($headers as $index => $header) {
            $header = trim($header);
            if (isset($fieldMappings[$header])) {
                $headerMap[$index] = $fieldMappings[$header];
            }
        }
        
        return $headerMap;
    }
    
    /**
     * Validate required fields
     */
    private function validateRequiredFields($salesData) {
        $requiredFields = array('registration_no', 'customer_name', 'dsr_name', 'volume', 'invoice_no');
        
        foreach ($requiredFields as $field) {
            if (empty($salesData[$field])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Stage sales records in database
     */
    private function stageSalesRecords($salesRecords, $batchId) {
        foreach ($salesRecords as $salesData) {
            $stmt = $this->db->prepare("
                INSERT INTO sales_integration_staging (
                    invoice_date, dsr_name, customer_name, sector, sub_sector,
                    sku_code, volume, invoice_no, registration_no, product_family_name,
                    batch_id, processing_status
                ) VALUES (
                    :invoice_date, :dsr_name, :customer_name, :sector, :sub_sector,
                    :sku_code, :volume, :invoice_no, :registration_no, :product_family_name,
                    :batch_id, 'PENDING'
                )
            ");
            
            // Convert invoice date format (YYYYMMDD to YYYY-MM-DD)
            $invoiceDate = $this->convertDateFormat($salesData['invoice_date']);
            
            $stmt->bindParam(':invoice_date', $invoiceDate);
            $stmt->bindParam(':dsr_name', $salesData['dsr_name']);
            $stmt->bindParam(':customer_name', $salesData['customer_name']);
            $stmt->bindParam(':sector', $salesData['sector']);
            $stmt->bindParam(':sub_sector', $salesData['sub_sector']);
            $stmt->bindParam(':sku_code', $salesData['sku_code']);
            $stmt->bindParam(':volume', $salesData['volume']);
            $stmt->bindParam(':invoice_no', $salesData['invoice_no']);
            $stmt->bindParam(':registration_no', $salesData['registration_no']);
            $stmt->bindParam(':product_family_name', $salesData['product_family_name']);
            $stmt->bindParam(':batch_id', $batchId);
            
            $stmt->execute();
        }
    }
    
    /**
     * Helper functions
     */
    private function generateBatchId() {
        return 'BATCH_' . date('Ymd_His') . '_' . rand(1000, 9999);
    }
    
    private function convertDateFormat($dateStr) {
        if (strlen($dateStr) == 8) {
            return substr($dateStr, 0, 4) . '-' . substr($dateStr, 4, 2) . '-' . substr($dateStr, 6, 2);
        }
        return $dateStr;
    }
    
    private function findExistingOpportunity($gstin) {
        $stmt = $this->db->prepare("
            SELECT id, cus_name FROM isteer_general_lead 
            WHERE registration_no = :gstin AND status = 'A' 
            LIMIT 1
        ");
        $stmt->bindParam(':gstin', $gstin);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function updateStagingRecord($salesData, $status, $errorMessage, $batchId) {
        $stmt = $this->db->prepare("
            UPDATE sales_integration_staging 
            SET processing_status = :status, error_message = :error_message, processed_at = NOW()
            WHERE registration_no = :registration_no AND batch_id = :batch_id
        ");
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':error_message', $errorMessage);
        $stmt->bindParam(':registration_no', $salesData['registration_no']);
        $stmt->bindParam(':batch_id', $batchId);
        $stmt->execute();
    }
    
    private function initializeStatistics($batchId) {
        $stmt = $this->db->prepare("
            INSERT INTO integration_statistics (batch_id, status) 
            VALUES (:batch_id, 'PROCESSING')
        ");
        $stmt->bindParam(':batch_id', $batchId);
        $stmt->execute();
    }
    
    private function updateStatistics($batchId, $result) {
        $stmt = $this->db->prepare("
            UPDATE integration_statistics SET 
                total_records = :total_records,
                processed_records = :processed_records,
                new_opportunities = :new_opportunities,
                updated_opportunities = :updated_opportunities,
                failed_records = :failed_records,
                dsm_actions_created = :dsm_actions_created,
                processing_end_time = NOW(),
                status = :status
            WHERE batch_id = :batch_id
        ");
        
        $status = $result['status'] == 'SUCCESS' ? 'COMPLETED' : 'FAILED';
        
        $stmt->bindParam(':total_records', $result['total_records']);
        $stmt->bindParam(':processed_records', $result['processed_records']);
        $stmt->bindParam(':new_opportunities', $result['new_opportunities']);
        $stmt->bindParam(':updated_opportunities', $result['updated_opportunities']);
        $stmt->bindParam(':failed_records', $result['failed_records']);
        $stmt->bindParam(':dsm_actions_created', $result['dsm_actions_created']);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':batch_id', $batchId);
        
        $stmt->execute();
    }
}
?>