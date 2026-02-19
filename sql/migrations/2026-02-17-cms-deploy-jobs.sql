-- Queue table for CMS-triggered frontend deploy jobs processed by worker/cron.
CREATE TABLE IF NOT EXISTS `cms_deploy_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_root` VARCHAR(255) NOT NULL,
  `job_type` VARCHAR(50) NOT NULL,
  `status` VARCHAR(20) NOT NULL,
  `requested_by` INT NULL,
  `requested_at` DATETIME NOT NULL,
  `started_at` DATETIME NULL,
  `finished_at` DATETIME NULL,
  `exit_code` INT NULL,
  `output_text` MEDIUMTEXT NULL,
  `showonweb` ENUM('Yes','No') NOT NULL DEFAULT 'Yes',
  `archived` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_cms_deploy_jobs_status_requested` (`status`, `requested_at`),
  KEY `idx_cms_deploy_jobs_site_job_requested` (`site_root`, `job_type`, `requested_at`),
  KEY `idx_cms_deploy_jobs_requested_by` (`requested_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
