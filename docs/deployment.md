# WCCMS Deploy/Update

Use one command for first install and future updates.

## Script

`scripts/wccms-sync.sh <site_web_root> [branch]`

Examples:

- First install to staging site:
  `./scripts/wccms-sync.sh /var/www/dev.witecanvas.com/web staging`
- Update staging site later:
  `./scripts/wccms-sync.sh /var/www/dev.witecanvas.com/web staging`
- Update production site:
  `./scripts/wccms-sync.sh /var/www/example.com/web main`

## Notes

- If `wccms/` does not exist, script clones it.
- If `wccms/` exists, script fetches and fast-forward pulls.
- Script stops if there are local uncommitted changes in target `wccms/`.
- To use a different repo URL temporarily:
  `WCCMS_REPO_URL=git@github.com:truska/wccms.git ./scripts/wccms-sync.sh <site_web_root> <branch>`

## Data Tools (site conversion / first rollout)

Use direct page (no CMS menu required):

`/wccms/tools-data.php`

Purpose:
- Check target DB connection/runtime config
- Compare a target site DB against a master DB on the same server
- Scope compare by table prefix (default: `cms_`)
- Add missing tables and missing columns only
- No drop/delete/alter-type operations

### Local-only master DB config

Create on each server (not in git):

`/var/www/clients/<client>/web<id>/private/dbcon-master.php`

Example:

```php
<?php
$MASTER_DB_HOST = 'localhost';
$MASTER_DB_NAME = 'master_wccms_db';
$MASTER_DB_USER = 'master_user';
$MASTER_DB_PASS = 'master_password';
```

### Recommended workflow

1. Open `/wccms/tools-data.php`
2. Run `Check Target DB`
3. Run `Preview Missing Schema`
4. Review planned SQL
5. Backup target DB
6. Tick `Apply changes now` and run `Apply Missing Schema`
7. Re-test `/wccms/login.php`
