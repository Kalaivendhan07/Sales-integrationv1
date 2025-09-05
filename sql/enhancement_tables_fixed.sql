-- Enhancement Tables for Pipeline Manager Integration
-- Volume Discrepancy Tracking Table

CREATE TABLE IF NOT EXISTS `volume_discrepancy_tracking` (
    `discrepancy_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `lead_id` BIGINT NOT NULL,
    `registration_no` VARCHAR(15) NOT NULL COMMENT 'GSTIN',
    `product_family` VARCHAR(200) NOT NULL,
    `sku_code` VARCHAR(50) NOT NULL,
    `opportunity_volume` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Volume in opportunity',
    `sales_volume` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Volume in sales record',
    `discrepancy_volume` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Difference (sales - opportunity)',
    `discrepancy_percentage` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Percentage difference',
    `discrepancy_type` ENUM('OVER_SALE', 'UNDER_SALE', 'EXACT_MATCH') DEFAULT 'EXACT_MATCH',
    `threshold_crossed` TINYINT(1) DEFAULT 0 COMMENT 'Whether it crossed acceptable threshold',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `integration_batch_id` VARCHAR(50) NOT NULL,
    INDEX `idx_lead_id` (`lead_id`),
    INDEX `idx_registration_no` (`registration_no`),
    INDEX `idx_discrepancy_type` (`discrepancy_type`),
    INDEX `idx_batch_id` (`integration_batch_id`),
    FOREIGN KEY (`lead_id`) REFERENCES `isteer_general_lead`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create performance indexes (corrected column names)
CREATE INDEX IF NOT EXISTS idx_sales_tire_type ON isteer_sales_upload_master(tire_type);
CREATE INDEX IF NOT EXISTS idx_sales_invoice_date ON isteer_sales_upload_master(invoice_date);
CREATE INDEX IF NOT EXISTS idx_call_plans_registration ON call_plans(registration_no);
CREATE INDEX IF NOT EXISTS idx_opp_products_opportunity_id ON isteer_opportunity_products(opportunity_id);