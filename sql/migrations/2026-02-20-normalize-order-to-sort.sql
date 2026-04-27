-- Normalize legacy `order` columns to `sort` in CMS tables.
-- Safe to run multiple times.

-- cms_form_field
SET @has_table := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_form_field'
);
SET @sql := IF(@has_table > 0,
  'ALTER TABLE cms_form_field ADD COLUMN IF NOT EXISTS `sort` INT(16) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_order := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_form_field' AND COLUMN_NAME = 'order'
);
SET @has_sort := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_form_field' AND COLUMN_NAME = 'sort'
);
SET @sql := IF(@has_order > 0 AND @has_sort > 0,
  'UPDATE cms_form_field SET `sort` = `order` WHERE (`sort` IS NULL OR `sort` = 0) AND `order` IS NOT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_order > 0,
  'ALTER TABLE cms_form_field DROP COLUMN `order`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- cms_form_field_options
SET @has_table := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_form_field_options'
);
SET @sql := IF(@has_table > 0,
  'ALTER TABLE cms_form_field_options ADD COLUMN IF NOT EXISTS `sort` INT(16) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_order := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_form_field_options' AND COLUMN_NAME = 'order'
);
SET @has_sort := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_form_field_options' AND COLUMN_NAME = 'sort'
);
SET @sql := IF(@has_order > 0 AND @has_sort > 0,
  'UPDATE cms_form_field_options SET `sort` = `order` WHERE (`sort` IS NULL OR `sort` = 0) AND `order` IS NOT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_order > 0,
  'ALTER TABLE cms_form_field_options DROP COLUMN `order`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- gallery
SET @has_table := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery'
);
SET @sql := IF(@has_table > 0,
  'ALTER TABLE gallery ADD COLUMN IF NOT EXISTS `sort` INT(16) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_order := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery' AND COLUMN_NAME = 'order'
);
SET @has_sort := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery' AND COLUMN_NAME = 'sort'
);
SET @sql := IF(@has_order > 0 AND @has_sort > 0,
  'UPDATE gallery SET `sort` = `order` WHERE (`sort` IS NULL OR `sort` = 0) AND `order` IS NOT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_order > 0,
  'ALTER TABLE gallery DROP COLUMN `order`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
