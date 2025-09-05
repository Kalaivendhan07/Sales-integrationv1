<?php
/**
 * Batch Performance Test - Daily 500 Sales Records Processing
 * Tests system performance with production-scale data load
 */

require_once __DIR__ . '/classes/EnhancedValidationEngine.php';
require_once __DIR__ . '/classes/AuditLogger.php';
require_once __DIR__ . '/config/database.php';

class BatchPerformanceTest {
    private $db;
    private $enhancedEngine;
    private $auditLogger;
    private $batchId;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auditLogger = new AuditLogger();
        $this->enhancedEngine = new EnhancedValidationEngine($this->auditLogger);
        $this->batchId = 'BATCH_PERF_' . date('Y-m-d_H-i-s');
    }
    
    public function runPerformanceTest() {
        echo "=== DAILY BATCH PERFORMANCE TEST (500 RECORDS) ===\n";
        echo "Batch ID: {$this->batchId}\n";
        echo "Test Date: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Setup baseline data
        $this->setupBaselineOpportunities();
        
        // Generate 500 test sales records
        $salesRecords = $this->generateTestSalesRecords(500);
        
        // Process batch and measure performance
        $this->processBatchWithMetrics($salesRecords);
        
        // Generate performance report
        $this->generatePerformanceReport();
        
        // Cleanup test data
        $this->cleanupTestData();
    }
    
    private function setupBaselineOpportunities() {
        echo "🔧 Setting up baseline opportunities...\n";
        
        // Create 50 existing opportunities to simulate real scenario
        $baseOpportunities = array();
        for ($i = 1; $i <= 50; $i++) {
            $gstin = sprintf('29PERFA%04d1Z%d', $i, $i % 10);
            $baseOpportunities[] = array(
                'cus_name' => "Performance Test Customer {$i}",
                'registration_no' => $gstin,
                'dsr_name' => "DSR_" . chr(65 + ($i % 26)),
                'dsr_id' => 1000 + $i,
                'products' => array('Shell Ultra', 'Shell Premium', 'Shell Standard'),
                'stage' => ($i % 4 == 0) ? 'SPANCOP' : 'Qualified',
                'volume' => rand(200, 800),
                'potential' => rand(1000, 3000)
            );
        }
        
        foreach ($baseOpportunities as $opp) {
            $stmt = $this->db->prepare("
                INSERT INTO isteer_general_lead (
                    cus_name, registration_no, dsr_name, dsr_id, sector, sub_sector,
                    product_name, product_name_2, product_name_3,
                    opportunity_name, lead_status, volume_converted, annual_potential,
                    source_from, integration_managed, integration_batch_id, entered_date_time
                ) VALUES (
                    :cus_name, :registration_no, :dsr_name, :dsr_id, 'Manufacturing', 'Industrial',
                    :product1, :product2, :product3,
                    :opportunity_name, :stage, :volume, :potential,
                    'Performance Test', 1, :batch_id, '2025-01-01 10:00:00'
                )
            ");
            
            $cus_name = $opp['cus_name'];
            $registration_no = $opp['registration_no'];
            $dsr_name = $opp['dsr_name'];
            $dsr_id = $opp['dsr_id'];
            $product1 = $opp['products'][0];
            $product2 = $opp['products'][1];
            $product3 = $opp['products'][2];
            $opportunity_name = $opp['cus_name'] . ' Opportunity';
            $stage = $opp['stage'];
            $volume = $opp['volume'];
            $potential = $opp['potential'];
            $batch_id = $this->batchId;
            
            $stmt->bindParam(':cus_name', $cus_name);
            $stmt->bindParam(':registration_no', $registration_no);
            $stmt->bindParam(':dsr_name', $dsr_name);
            $stmt->bindParam(':dsr_id', $dsr_id);
            $stmt->bindParam(':product1', $product1);
            $stmt->bindParam(':product2', $product2);
            $stmt->bindParam(':product3', $product3);
            $stmt->bindParam(':opportunity_name', $opportunity_name);
            $stmt->bindParam(':stage', $stage);
            $stmt->bindParam(':volume', $volume);
            $stmt->bindParam(':potential', $potential);
            $stmt->bindParam(':batch_id', $batch_id);
            
            $stmt->execute();
        }
        
        echo "✅ Created 50 baseline opportunities\n\n";
    }
    
    private function generateTestSalesRecords($count) {
        echo "📊 Generating {$count} test sales records...\n";
        
        $salesRecords = array();
        $products = array('Shell Ultra', 'Shell Premium', 'Shell Standard', 'Shell Pro', 'Shell Advanced');
        $tireTypes = array('Mainstream', 'Premium');
        $sectors = array('Manufacturing', 'Retail', 'Agriculture', 'Construction');
        
        for ($i = 1; $i <= $count; $i++) {
            $isExisting = ($i <= 200); // 40% existing customers, 60% new
            
            if ($isExisting) {
                // Existing customer
                $customerNum = ($i % 50) + 1;
                $gstin = sprintf('29PERFA%03d1Z%d', $customerNum, $customerNum % 10);
                $customerName = "Performance Test Customer {$customerNum}";
                $dsrName = "DSR_" . chr(65 + ($customerNum % 26));
            } else {
                // New customer
                $gstin = sprintf('29NEWAB%04d2Z%d', $i, $i % 10);
                $customerName = "New Customer {$i}";
                $dsrName = "DSR_" . chr(65 + ($i % 26));
            }
            
            $salesRecords[] = array(
                'registration_no' => $gstin,
                'customer_name' => $customerName,
                'dsr_name' => $dsrName,
                'product_family_name' => $products[array_rand($products)],
                'sku_code' => 'SKU_' . sprintf('%04d', $i),
                'volume' => sprintf('%.2f', rand(50, 500)),
                'sector' => $sectors[array_rand($sectors)],
                'sub_sector' => 'Industrial',
                'tire_type' => $tireTypes[array_rand($tireTypes)]
            );
        }
        
        echo "✅ Generated {$count} sales records\n";
        echo "   - Existing customers: 200 (40%)\n";
        echo "   - New customers: 300 (60%)\n\n";
        
        return $salesRecords;
    }
    
    private function processBatchWithMetrics($salesRecords) {
        echo "🚀 Processing batch with performance metrics...\n";
        echo "════════════════════════════════════════════════════════════════\n";
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $peakMemory = $startMemory;
        
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
            'processing_times' => array()
        );
        
        $batchSize = 50; // Process in batches of 50
        $batches = array_chunk($salesRecords, $batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            $batchStartTime = microtime(true);
            
            // Start transaction for batch
            $this->db->beginTransaction();
            
            try {
                foreach ($batch as $record) {
                    $recordStartTime = microtime(true);
                    
                    $result = $this->enhancedEngine->validateSalesRecord($record, $this->batchId);
                    
                    $recordEndTime = microtime(true);
                    $results['processing_times'][] = ($recordEndTime - $recordStartTime) * 1000; // ms
                    
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
                    }
                    
                    // Track peak memory
                    $currentMemory = memory_get_usage(true);
                    if ($currentMemory > $peakMemory) {
                        $peakMemory = $currentMemory;
                    }
                }
                
                // Commit transaction
                $this->db->commit();
                
                $batchEndTime = microtime(true);
                $batchTime = ($batchEndTime - $batchStartTime) * 1000;
                
                echo sprintf("Batch %d/%d: %d records processed in %.2f ms (%.2f ms/record)\n", 
                    $batchIndex + 1, count($batches), count($batch), $batchTime, $batchTime / count($batch));
                
            } catch (Exception $e) {
                $this->db->rollback();
                echo "❌ Batch {$batchIndex} failed: " . $e->getMessage() . "\n";
                $results['failed'] += count($batch);
                $results['total_processed'] += count($batch);
            }
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $totalTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $avgTime = array_sum($results['processing_times']) / count($results['processing_times']);
        $memoryUsed = ($peakMemory - $startMemory) / 1024 / 1024; // Convert to MB
        
        echo "\n📊 BATCH PROCESSING METRICS:\n";
        echo "════════════════════════════════════════════════════════════════\n";
        echo sprintf("Total Time: %.2f ms (%.2f seconds)\n", $totalTime, $totalTime / 1000);
        echo sprintf("Average Time per Record: %.2f ms\n", $avgTime);
        echo sprintf("Records per Second: %.2f\n", 1000 / $avgTime);
        echo sprintf("Memory Used: %.2f MB\n", $memoryUsed);
        echo sprintf("Peak Memory: %.2f MB\n", $peakMemory / 1024 / 1024);
        echo sprintf("Success Rate: %.1f%%\n", ($results['successful'] / $results['total_processed']) * 100);
        echo "\n";
        
        $this->results = $results;
        $this->metrics = array(
            'total_time_ms' => $totalTime,
            'avg_time_per_record_ms' => $avgTime,
            'records_per_second' => 1000 / $avgTime,
            'memory_used_mb' => $memoryUsed,
            'peak_memory_mb' => $peakMemory / 1024 / 1024,
            'success_rate' => ($results['successful'] / $results['total_processed']) * 100
        );
    }
    
    private function generatePerformanceReport() {
        echo "📋 DETAILED PERFORMANCE REPORT:\n";
        echo "════════════════════════════════════════════════════════════════\n";
        
        echo "🎯 PROCESSING RESULTS:\n";
        echo "   Total Processed: " . $this->results['total_processed'] . "\n";
        echo "   Successful: " . $this->results['successful'] . " ✅\n";
        echo "   Failed: " . $this->results['failed'] . " ❌\n";
        echo "   Success Rate: " . sprintf("%.1f%%", $this->metrics['success_rate']) . "\n\n";
        
        echo "📈 BUSINESS ACTIONS:\n";
        echo "   New Opportunities Created: " . $this->results['new_opportunities'] . "\n";
        echo "   Existing Opportunities Updated: " . $this->results['updated_opportunities'] . "\n";
        echo "   DSM Actions Generated: " . $this->results['dsm_actions'] . "\n";
        echo "   Cross-Sell Opportunities: " . $this->results['cross_sells'] . "\n";
        echo "   Up-Sell Opportunities: " . $this->results['up_sells'] . "\n";
        echo "   Opportunity Splits: " . $this->results['opportunity_splits'] . "\n";
        echo "   Volume Discrepancies: " . $this->results['volume_discrepancies'] . "\n\n";
        
        echo "⚡ PERFORMANCE METRICS:\n";
        echo "   Total Processing Time: " . sprintf("%.2f seconds", $this->metrics['total_time_ms'] / 1000) . "\n";
        echo "   Average Time per Record: " . sprintf("%.2f ms", $this->metrics['avg_time_per_record_ms']) . "\n";
        echo "   Processing Speed: " . sprintf("%.2f records/second", $this->metrics['records_per_second']) . "\n";
        echo "   Memory Usage: " . sprintf("%.2f MB", $this->metrics['memory_used_mb']) . "\n";
        echo "   Peak Memory: " . sprintf("%.2f MB", $this->metrics['peak_memory_mb']) . "\n\n";
        
        // Performance benchmarks
        echo "🎯 PERFORMANCE BENCHMARKS:\n";
        if ($this->metrics['records_per_second'] >= 10) {
            echo "   ✅ Processing Speed: EXCELLENT (>10 records/sec)\n";
        } elseif ($this->metrics['records_per_second'] >= 5) {
            echo "   ✅ Processing Speed: GOOD (5-10 records/sec)\n";
        } else {
            echo "   ⚠️  Processing Speed: ACCEPTABLE (<5 records/sec)\n";
        }
        
        if ($this->metrics['peak_memory_mb'] <= 256) {
            echo "   ✅ Memory Usage: EXCELLENT (<256 MB)\n";
        } elseif ($this->metrics['peak_memory_mb'] <= 512) {
            echo "   ✅ Memory Usage: GOOD (256-512 MB)\n";
        } else {
            echo "   ⚠️  Memory Usage: HIGH (>512 MB)\n";
        }
        
        if ($this->metrics['success_rate'] >= 95) {
            echo "   ✅ Success Rate: EXCELLENT (>95%)\n";
        } elseif ($this->metrics['success_rate'] >= 90) {
            echo "   ✅ Success Rate: GOOD (90-95%)\n";
        } else {
            echo "   ⚠️  Success Rate: NEEDS ATTENTION (<90%)\n";
        }
        
        echo "\n🚀 PRODUCTION READINESS:\n";
        if ($this->metrics['records_per_second'] >= 5 && $this->metrics['success_rate'] >= 95) {
            echo "   ✅ READY FOR PRODUCTION - System can handle 500 daily records efficiently\n";
            $estimatedTime = 500 / $this->metrics['records_per_second'];
            echo "   📊 Estimated daily batch time: " . sprintf("%.1f minutes", $estimatedTime / 60) . "\n";
        } else {
            echo "   ⚠️  OPTIMIZATION RECOMMENDED\n";
        }
        
        echo "\n";
    }
    
    private function cleanupTestData() {
        echo "🧹 Cleaning up performance test data...\n";
        
        try {
            // Set integration_managed to 0 to bypass trigger
            $this->db->exec("UPDATE isteer_general_lead SET integration_managed = 0 WHERE registration_no LIKE '29PERF%' OR registration_no LIKE '29NEW%'");
            
            // Clean up test opportunities
            $this->db->exec("DELETE FROM isteer_general_lead WHERE registration_no LIKE '29PERF%' OR registration_no LIKE '29NEW%'");
            
            // Clean up test audit logs
            $this->db->exec("DELETE FROM integration_audit_log WHERE integration_batch_id = '{$this->batchId}'");
            
            // Clean up test DSM actions
            $this->db->exec("DELETE FROM dsm_action_queue WHERE registration_no LIKE '29PERF%' OR registration_no LIKE '29NEW%'");
            
            // Clean up test discrepancy records
            $this->db->exec("DELETE FROM volume_discrepancy_tracking WHERE registration_no LIKE '29PERF%' OR registration_no LIKE '29NEW%'");
            
            // Clean up test opportunity products
            $this->db->exec("DELETE FROM isteer_opportunity_products WHERE added_by = 'INTEGRATION_SYSTEM' AND added_date >= CURDATE()");
            
            echo "✅ Performance test cleanup completed\n";
        } catch (Exception $e) {
            echo "⚠️ Cleanup warning: " . $e->getMessage() . "\n";
        }
    }
}

// Run the performance test
echo "Starting Daily Batch Performance Test...\n";
echo "Testing system capacity for 500 sales records\n";
echo "============================================\n\n";

$performanceTest = new BatchPerformanceTest();
$performanceTest->runPerformanceTest();

echo "\n🎉 Performance test completed successfully!\n";
?>