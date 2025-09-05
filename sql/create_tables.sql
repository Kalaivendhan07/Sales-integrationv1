-- Sales Integration Tables for Pipeline Manager
-- Compatible with MySQL 8.0

-- Sales Integration Staging Table
CREATE TABLE IF NOT EXISTS `sales_integration_staging` (
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
    `processing_status` ENUM('PENDING', 'PROCESSED', 'FAILED') DEFAULT 'PENDING',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `processed_at` TIMESTAMP NULL,
    `error_message` TEXT,
    `batch_id` VARCHAR(50) DEFAULT NULL,
    INDEX `idx_registration_no` (`registration_no`),
    INDEX `idx_processing_status` (`processing_status`),
    INDEX `idx_batch_id` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Integration Audit Log Table
CREATE TABLE IF NOT EXISTS `integration_audit_log` (
    `audit_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `lead_id` BIGINT NOT NULL,
    `field_name` VARCHAR(100) NOT NULL,
    `old_value` TEXT,
    `new_value` TEXT,
    `old_value_entered_date` TIMESTAMP NULL,
    `new_value_entered_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `data_changed_on` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('ACTIVE', 'REVERTED') DEFAULT 'ACTIVE',
    `updated_by` VARCHAR(100) DEFAULT 'SYSTEM',
    `integration_batch_id` VARCHAR(50) NOT NULL,
    `reference_id` VARCHAR(100) DEFAULT NULL,
    INDEX `idx_lead_id` (`lead_id`),
    INDEX `idx_batch_id` (`integration_batch_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DSM Action Queue Table
CREATE TABLE IF NOT EXISTS `dsm_action_queue` (
    `action_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `registration_no` VARCHAR(15) NOT NULL COMMENT 'GSTIN',
    `mismatch_level` INT NOT NULL COMMENT 'Level 1-6',
    `mismatch_type` VARCHAR(50) NOT NULL,
    `sales_data` JSON NOT NULL,
    `opportunity_data` JSON,
    `action_required` VARCHAR(255) NOT NULL,
    `status` ENUM('PENDING', 'COMPLETED', 'EXPIRED') DEFAULT 'PENDING',
    `assigned_to` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `resolved_at` TIMESTAMP NULL,
    `resolution` JSON,
    `priority` ENUM('LOW', 'MEDIUM', 'HIGH') DEFAULT 'MEDIUM',
    INDEX `idx_registration_no` (`registration_no`),
    INDEX `idx_status` (`status`),
    INDEX `idx_assigned_to` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Integration Backup Table
CREATE TABLE IF NOT EXISTS `integration_backup` (
    `backup_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `lead_id` BIGINT NOT NULL,
    `backup_data` JSON NOT NULL COMMENT 'Complete lead snapshot',
    `backup_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `batch_id` VARCHAR(50) NOT NULL,
    `expires_at` TIMESTAMP NOT NULL COMMENT '120-day expiration',
    INDEX `idx_lead_id` (`lead_id`),
    INDEX `idx_batch_id` (`batch_id`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Integration Statistics Table
CREATE TABLE IF NOT EXISTS `integration_statistics` (
    `stat_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `batch_id` VARCHAR(50) NOT NULL,
    `total_records` INT DEFAULT 0,
    `processed_records` INT DEFAULT 0,
    `new_opportunities` INT DEFAULT 0,
    `updated_opportunities` INT DEFAULT 0,
    `failed_records` INT DEFAULT 0,
    `dsm_actions_created` INT DEFAULT 0,
    `processing_start_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `processing_end_time` TIMESTAMP NULL,
    `status` ENUM('PROCESSING', 'COMPLETED', 'FAILED') DEFAULT 'PROCESSING',
    INDEX `idx_batch_id` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;