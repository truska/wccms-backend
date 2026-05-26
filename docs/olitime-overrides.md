OLITIME temporary CMS overrides
===============================

This repo currently carries OLITIME-specific copies of the v5 record files:

- `recordViewOliv5.php`
- `recordEditOliv5.php`
- `recordNewOliv5.php`

The CMS menu URL whitelist in `includes/menu.php` has also been updated so menu
items keep the `frm` parameter when using these OLITIME override files.

When the upstream WC/dev master files are migrated back in, re-check this menu
whitelist behaviour. Either preserve the OLITIME file entries or replace them
with a more general workaround so `frm=...` is not dropped from menu links.
