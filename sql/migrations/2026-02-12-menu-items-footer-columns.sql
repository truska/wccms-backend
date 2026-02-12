-- Footer menu controls for site-level menu_items.
-- Allows selecting any menu item for a given footer column.

ALTER TABLE `menu_items`
  ADD COLUMN IF NOT EXISTS `showonfooter` ENUM('Yes','No') NOT NULL DEFAULT 'No' AFTER `showonweb`,
  ADD COLUMN IF NOT EXISTS `footer_column` INT(4) NOT NULL DEFAULT 0 AFTER `showonfooter`,
  ADD COLUMN IF NOT EXISTS `footer_sort` INT(11) NOT NULL DEFAULT 0 AFTER `footer_column`;
