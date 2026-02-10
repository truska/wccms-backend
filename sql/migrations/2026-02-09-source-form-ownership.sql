-- Explicit source-form ownership for content/gallery image resolution.
-- Run on MySQL 8+ (or adapt IF NOT EXISTS syntax if your version differs).

ALTER TABLE `content`
  ADD COLUMN IF NOT EXISTS `source_form_id` INT NULL AFTER `page`,
  ADD COLUMN IF NOT EXISTS `source_form_name` VARCHAR(64) NULL AFTER `source_form_id`;

-- Optional but recommended for gallery lookup performance.
ALTER TABLE `gallery`
  ADD INDEX `idx_gallery_form_record_web` (`form_id`, `record_id`, `showonweb`, `archived`, `sort`);

-- Backfill existing content rows where possible (by current table/form mapping).
-- This assumes cms_form.table points to cms_table.id and cms_table.name = 'content'.
UPDATE `content` c
JOIN `cms_table` t ON LOWER(t.`name`) = 'content'
JOIN `cms_form` f ON f.`table` = t.`id`
SET
  c.`source_form_id` = COALESCE(c.`source_form_id`, f.`id`),
  c.`source_form_name` = COALESCE(c.`source_form_name`, f.`name`)
WHERE c.`source_form_id` IS NULL OR c.`source_form_name` IS NULL;
