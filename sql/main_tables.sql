-- Main Tables for Pipeline Manager Sales Integration System
-- Based on PHP code analysis

-- Main Opportunity/Lead Table  
CREATE TABLE IF NOT EXISTS `isteer_general_lead` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `cus_name` VARCHAR(500) NOT NULL,
    `registration_no` VARCHAR(15) NOT NULL COMMENT 'GSTIN',
    `dsr_name` VARCHAR(255) NOT NULL,
    `dsr_id` INT DEFAULT NULL,
    `sector` VARCHAR(100) DEFAULT '',
    `sub_sector` VARCHAR(100) DEFAULT '',
    `product_name` VARCHAR(200) DEFAULT '',
    `product_name_2` VARCHAR(200) DEFAULT '',
    `product_name_3` VARCHAR(200) DEFAULT '',
    `opportunity_name` VARCHAR(500) DEFAULT '',
    `lead_status` VARCHAR(50) DEFAULT 'Suspect',
    `volume_converted` DECIMAL(15,2) DEFAULT 0.00,
    `annual_potential` DECIMAL(15,2) DEFAULT 0.00,
    `source_from` VARCHAR(100) DEFAULT '',
    `integration_managed` TINYINT(1) DEFAULT 0,
    `integration_batch_id` VARCHAR(50) DEFAULT NULL,
    `last_integration_update` TIMESTAMP NULL,
    `entered_date_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `opp_type` VARCHAR(50) DEFAULT 'New',
    `py_billed_volume` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Previous year billed volume',
    `product_pack` VARCHAR(100) DEFAULT '',
    `updated_by` VARCHAR(100) DEFAULT 'SYSTEM',
    INDEX `idx_registration_no` (`registration_no`),
    INDEX `idx_dsr_id` (`dsr_id`),
    INDEX `idx_lead_status` (`lead_status`),
    INDEX `idx_integration_managed` (`integration_managed`),
    INDEX `idx_integration_batch_id` (`integration_batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sales Upload Master Table
CREATE TABLE IF NOT EXISTS `isteer_sales_upload_master` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `invoice_date` DATE NOT NULL,
    `dsr_name` VARCHAR(255) NOT NULL,
    `dsr_id` INT DEFAULT NULL,
    `customer_name` VARCHAR(500) NOT NULL,
    `sector` VARCHAR(100) DEFAULT '',
    `sub_sector` VARCHAR(100) DEFAULT '',
    `sku_code` VARCHAR(50) NOT NULL,
    `volume` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `invoice_no` VARCHAR(100) NOT NULL,
    `registration_no` VARCHAR(15) NOT NULL COMMENT 'GSTIN',
    `product_family_name` VARCHAR(200) DEFAULT '',
    `tire_type` VARCHAR(50) DEFAULT '',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_registration_no` (`registration_no`),
    INDEX `idx_dsr_id` (`dsr_id`),
    INDEX `idx_product_family` (`product_family_name`),
    INDEX `idx_tire_type` (`tire_type`),
    INDEX `idx_invoice_date` (`invoice_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Opportunity Products Table (for multi-product tracking)
CREATE TABLE IF NOT EXISTS `isteer_opportunity_products` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `opportunity_id` BIGINT NOT NULL,
    `product_id` VARCHAR(50) NOT NULL,
    `product_name` VARCHAR(200) NOT NULL,
    `volume` DECIMAL(15,2) DEFAULT 0.00,
    `tier` VARCHAR(50) DEFAULT '',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_opportunity_id` (`opportunity_id`),
    INDEX `idx_product_id` (`product_id`),
    FOREIGN KEY (`opportunity_id`) REFERENCES `isteer_general_lead`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Call Plans Table
CREATE TABLE IF NOT EXISTS `call_plans` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `registration_no` VARCHAR(15) NOT NULL,
    `dsr_id` INT NOT NULL,
    `dsr_name` VARCHAR(255) NOT NULL,
    `status` VARCHAR(50) DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_registration_no` (`registration_no`),
    INDEX `idx_dsr_id` (`dsr_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Database Triggers for Business Rules
DELIMITER ;;

-- Trigger to prevent deletion of integration-managed leads
CREATE TRIGGER IF NOT EXISTS `prevent_integration_lead_deletion`
BEFORE DELETE ON `isteer_general_lead`
FOR EACH ROW
BEGIN
    IF OLD.integration_managed = 1 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Cannot delete integration-managed lead. Use system rollback instead.';
    END IF;
END;;

DELIMITER ;