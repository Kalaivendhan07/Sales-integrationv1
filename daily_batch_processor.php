<?php
/**
 * Daily Batch Processor for Pipeline Manager India Sales Integration
 * Production-ready script for processing daily sales data uploads
 * Optimized for handling 500+ sales records efficiently
 */

require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

class DailyBatchProcessor {
    private $db;
    private $enhancedEngine;
    private $auditLogger;
    private $batchId;
    private $logFile;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auditLogger = new AuditLogger();
        $this->enhancedEngine = new EnhancedValidationEngine($this->auditLogger);
        $this->batchId = 'DAILY_' . date('Y-m-d_H-i-s');
        $this->logFile = __DIR__ . '/logs/batch_' . date('Y-m-d') . '.log';
        
        // Ensure logs directory exists
        if (!is_dir(__DIR__ . '/logs')) {
            mkdir(__DIR__ . '/logs', 0755, true);
        }
    }
    
    /**
     * Process daily sales batch from CSV file
     */
    public function processDailyBatch($csvFilePath) {
        $this->log("=== DAILY BATCH PROCESSING STARTED ===");
        $this->log("Batch ID: {$this->batchId}");
        $this->log("Input File: {$csvFilePath}");
        $this->log("Start Time: " . date('Y-m-d H:i:s'));
        
        try {
            // Validate input file
            if (!file_exists($csvFilePath)) {
                throw new Exception("Input file not found: {$csvFilePath}");
            }
            
            // Parse CSV file
            $salesRecords = $this->parseCsvFile($csvFilePath);
            $this->log("Parsed " . count($salesRecords) . " records from CSV");
            
            // Process records in batches
            $results = $this->processRecordsInBatches($salesRecords);
            
            // Generate summary report
            $this->generateBatchReport($results);
            
            // Update batch statistics
            $this->updateBatchStatistics($results);
            
            $this->log("=== DAILY BATCH PROCESSING COMPLETED ===");
            
            return $results;
            
        } catch (Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Parse CSV file and validate format
     */
    private function parseCsvFile($csvFilePath) {
        $salesRecords = array();
        $lineNumber = 0;
        
        if (($handle = fopen($csvFilePath, "r")) !== FALSE) {
            // Read header
            $header = fgetcsv($handle, 1000, ",");
            $lineNumber++;
            
            // Expected columns
            $expectedColumns = array(
                'registration_no', 'customer_name', 'dsr_name', 'product_family_name',
                'sku_code', 'volume', 'sector', 'sub_sector', 'tire_type'
            );
            
            // Validate header
            $missingColumns = array_diff($expectedColumns, $header);
            if (!empty($missingColumns)) {
                throw new Exception("Missing required columns in CSV: " . implode(', ', $missingColumns));
            }
            
            // Read data rows
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $lineNumber++;
                
                if (count($data) != count($header)) {
                    $this->log("WARNING: Line {$lineNumber} has incorrect column count, skipping");
                    continue;
                }
                
                $record = array_combine($header, $data);
                
                // Basic validation
                if (empty($record['registration_no']) || empty($record['customer_name'])) {
                    $this->log("WARNING: Line {$lineNumber} missing required fields, skipping");
                    continue;
                }
                
                $salesRecords[] = $record;
            }
            
            fclose($handle);
        } else {
            throw new Exception("Unable to open CSV file for reading");
        }
        
        return $salesRecords;
    }
    
    /**
     * Process records in optimized batches
     */
    private function processRecordsInBatches($salesRecords) {
        $batchSize = 50; // Optimal batch size based on performance testing
        $batches = array_chunk($salesRecords, $batchSize);
        
        $results = array(
            'total_processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'new_opportunities' => 0,
            'updated_opportunities' => 0,
            'dsm_actions' => 0,
            'cross_sells' => 0,
            'up_sells' => 0,
            'opportunity_splits' => 0,
            'volume_discrepancies' => 0,
            'failed_records' => array(),
            'start_time' => microtime(true)
        );
        
        $this->log("Processing " . count($salesRecords) . " records in " . count($batches) . " batches");
        
        foreach ($batches as $batchIndex => $batch) {
            $batchStartTime = microtime(true);
            
            // Start transaction for batch
            $this->db->beginTransaction();
            
            try {
                foreach ($batch as $recordIndex => $record) {
                    $result = $this->enhancedEngine->validateSalesRecord($record, $this->batchId);
                    
                    $results['total_processed']++;
                    
                    if ($result['status'] === 'SUCCESS') {
                        $results['successful']++;
                        
                        // Count different types of actions
                        if (isset($result['opportunity_created']) && $result['opportunity_created']) {
                            $results['new_opportunities']++;
                        } else {
                            $results['updated_opportunities']++;
                        }
                        
                        if (isset($result['actions']) && is_array($result['actions'])) {
                            $results['dsm_actions'] += count($result['actions']);
                        }
                        
                        if (isset($result['cross_sell_created']) && $result['cross_sell_created']) {
                            $results['cross_sells']++;
                        }
                        
                        if (isset($result['up_sell_created']) && $result['up_sell_created']) {
                            $results['up_sells']++;
                        }
                        
                        if (isset($result['opportunity_split']) && $result['opportunity_split']) {
                            $results['opportunity_splits']++;
                        }
                        
                        if (isset($result['volume_discrepancy'])) {
                            $results['volume_discrepancies']++;
                        }
                    } else {
                        $results['failed']++;
                        $results['failed_records'][] = array(
                            'record' => $record,
                            'error' => isset($result['message']) ? $result['message'] : 'Unknown error'
                        );
                    }
                }
                
                // Commit transaction
                $this->db->commit();
                
                $batchEndTime = microtime(true);
                $batchTime = ($batchEndTime - $batchStartTime) * 1000;
                
                $this->log(sprintf("Batch %d/%d: %d records processed in %.2f ms", 
                    $batchIndex + 1, count($batches), count($batch), $batchTime));
                
            } catch (Exception $e) {
                $this->db->rollback();
                $this->log("ERROR: Batch {$batchIndex} failed: " . $e->getMessage());
                
                // Mark all records in failed batch as failed
                foreach ($batch as $record) {
                    $results['failed']++;
                    $results['total_processed']++;
                    $results['failed_records'][] = array(
                        'record' => $record,
                        'error' => 'Batch processing failed: ' . $e->getMessage()
                    );
                }
            }
        }
        
        $results['end_time'] = microtime(true);
        $results['total_time'] = $results['end_time'] - $results['start_time'];
        
        return $results;
    }
    
    /**
     * Generate comprehensive batch report
     */
    private function generateBatchReport($results) {
        $successRate = ($results['successful'] / $results['total_processed']) * 100;
        $recordsPerSecond = $results['total_processed'] / $results['total_time'];
        
        $this->log("=== BATCH PROCESSING REPORT ===");
        $this->log("Total Records: " . $results['total_processed']);
        $this->log("Successful: " . $results['successful']);
        $this->log("Failed: " . $results['failed']);
        $this->log("Success Rate: " . sprintf("%.1f%%", $successRate));
        $this->log("Processing Time: " . sprintf("%.2f seconds", $results['total_time']));
        $this->log("Processing Speed: " . sprintf("%.2f records/second", $recordsPerSecond));
        
        $this->log("=== BUSINESS ACTIONS ===");
        $this->log("New Opportunities: " . $results['new_opportunities']);
        $this->log("Updated Opportunities: " . $results['updated_opportunities']);
        $this->log("DSM Actions: " . $results['dsm_actions']);
        $this->log("Cross-Sell Opportunities: " . $results['cross_sells']);
        $this->log("Up-Sell Opportunities: " . $results['up_sells']);
        $this->log("Opportunity Splits: " . $results['opportunity_splits']);
        $this->log("Volume Discrepancies: " . $results['volume_discrepancies']);
        
        // Log failed records for review
        if (!empty($results['failed_records'])) {
            $this->log("=== FAILED RECORDS ===");
            foreach ($results['failed_records'] as $index => $failedRecord) {
                $this->log("Failed Record " . ($index + 1) . ":");
                $this->log("  GSTIN: " . $failedRecord['record']['registration_no']);
                $this->log("  Customer: " . $failedRecord['record']['customer_name']);
                $this->log("  Error: " . $failedRecord['error']);
            }
        }
    }
    
    /**
     * Update batch statistics in database
     */
    private function updateBatchStatistics($results) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO integration_statistics (
                    batch_id, total_records, processed_records, new_opportunities,
                    updated_opportunities, failed_records, dsm_actions_created,
                    processing_start_time, processing_end_time, status
                ) VALUES (
                    :batch_id, :total_records, :processed_records, :new_opportunities,
                    :updated_opportunities, :failed_records, :dsm_actions_created,
                    FROM_UNIXTIME(:start_time), FROM_UNIXTIME(:end_time), 'COMPLETED'
                )
            ");
            
            $stmt->bindParam(':batch_id', $this->batchId);
            $stmt->bindParam(':total_records', $results['total_processed']);
            $stmt->bindParam(':processed_records', $results['successful']);
            $stmt->bindParam(':new_opportunities', $results['new_opportunities']);
            $stmt->bindParam(':updated_opportunities', $results['updated_opportunities']);
            $stmt->bindParam(':failed_records', $results['failed']);
            $stmt->bindParam(':dsm_actions_created', $results['dsm_actions']);
            $stmt->bindParam(':start_time', $results['start_time']);
            $stmt->bindParam(':end_time', $results['end_time']);
            
            $stmt->execute();
            
            $this->log("Batch statistics recorded in database");
            
        } catch (Exception $e) {
            $this->log("WARNING: Failed to record batch statistics: " . $e->getMessage());
        }
    }
    
    /**
     * Log message to file and console
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$message}\n";
        
        // Write to log file
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Output to console
        echo $logEntry;
    }
    
    /**
     * Get batch processing statistics
     */
    public function getBatchStatistics($days = 7) {
        $stmt = $this->db->prepare("
            SELECT batch_id, total_records, processed_records, failed_records,
                   new_opportunities, updated_opportunities, dsm_actions_created,
                   processing_start_time, processing_end_time,
                   TIMESTAMPDIFF(SECOND, processing_start_time, processing_end_time) as duration_seconds
            FROM integration_statistics 
            WHERE processing_start_time >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ORDER BY processing_start_time DESC
        ");
        
        $stmt->bindParam(':days', $days);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Command line usage
if ($argc > 1) {
    $csvFile = $argv[1];
    
    echo "Daily Batch Processor for Pipeline Manager Integration\n";
    echo "====================================================\n\n";
    
    try {
        $processor = new DailyBatchProcessor();
        $results = $processor->processDailyBatch($csvFile);
        
        echo "\n✅ Batch processing completed successfully!\n";
        echo "📊 Processed " . $results['total_processed'] . " records\n";
        echo "✅ Success rate: " . sprintf("%.1f%%", ($results['successful'] / $results['total_processed']) * 100) . "\n";
        
        exit(0);
        
    } catch (Exception $e) {
        echo "\n❌ Batch processing failed: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "Usage: php daily_batch_processor.php <csv_file_path>\n";
    echo "Example: php daily_batch_processor.php /path/to/sales_data.csv\n";
}
?>