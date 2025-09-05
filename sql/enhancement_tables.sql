-- Enhancement Tables for Pipeline Manager Integration
-- Volume Discrepancy Tracking Table

CREATE TABLE IF NOT EXISTS `volume_discrepancy_tracking` (
    `discrepancy_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `lead_id` BIGINT NOT NULL,
    `registration_no` VARCHAR(15) NOT NULL COMMENT 'GSTIN',
    `product_family` VARCHAR(200) NOT NULL,
    `sku_code` VARCHAR(50) NOT NULL,
    `opportunity_volume` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Volume in opportunity',
    `sellout_volume` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Volume in sales data',
    `discrepancy_volume` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Difference',
    `discrepancy_percentage` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Percentage difference',
    `discrepancy_type` ENUM('OVER_SALE', 'UNDER_SALE', 'MATCH') DEFAULT 'MATCH',
    `integration_batch_id` VARCHAR(50) NOT NULL,
    `detected_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `resolution_status` ENUM('PENDING', 'RESOLVED', 'IGNORED') DEFAULT 'PENDING',
    `resolution_notes` TEXT,
    `resolved_by` VARCHAR(100),
    `resolved_date` TIMESTAMP NULL,
    INDEX `idx_lead_id` (`lead_id`),
    INDEX `idx_registration_no` (`registration_no`),
    INDEX `idx_discrepancy_type` (`discrepancy_type`),
    INDEX `idx_resolution_status` (`resolution_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add tire_type to sales table if not exists (for tier tracking)
-- ALTER TABLE isteer_sales_upload_master ADD COLUMN tire_type VARCHAR(50) DEFAULT 'Mainstream' COMMENT 'SKU Tier: Mainstream/Premium';

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_sales_tire_type ON isteer_sales_upload_master(tire_type);
CREATE INDEX IF NOT EXISTS idx_sales_date_year ON isteer_sales_upload_master(date);
CREATE INDEX IF NOT EXISTS idx_call_plan_cmkey ON isteer_call_plan(cmkey);
CREATE INDEX IF NOT EXISTS idx_opp_products_lead_id ON isteer_opportunity_products(lead_id);