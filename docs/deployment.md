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

## Frontend Deploy Integration

This CMS-integrated deploy flow is queue based. CMS requests insert jobs only. Worker/cron executes deploy jobs out-of-band.

### Required files per site

Expected structure:
- `<site_root>/web/wccms/` (CMS)
- `<site_root>/web/wccms/deploy_scripts/frontend-release.sh`
- `<site_root>/web/wccms/deploy_scripts/cms-deploy-worker.sh`

Command contract:
- Check command: `<site_root>/web/wccms/deploy_scripts/frontend-release.sh check`
- Deploy command: `<site_root>/web/wccms/deploy_scripts/frontend-release.sh deploy`

Behavior contract for `frontend-release.sh`:
- `check` prints:
  - frontend repo path
  - repo commit
  - repo CSS hash
  - live CSS hash
  - sync status
- `deploy` runs pull + rsync, then prints the same status block
- Exit code is non-zero on failure
- Output is clear text on stdout/stderr for job history visibility

### Required DB migration

Apply:
- `wccms/sql/migrations/2026-02-17-cms-deploy-jobs.sql`

### Required cron entry

Run every minute (adjust user/path per host):

```cron
* * * * * /var/www/<site>/web/wccms/deploy_scripts/cms-deploy-worker.sh >> /var/log/cms-deploy-worker.log 2>&1
```

### Ownership and permissions

- `wccms/deploy_scripts/frontend-release.sh` and `wccms/deploy_scripts/cms-deploy-worker.sh` must be executable (`chmod 755`).
- Worker user must have:
  - read/write access to `<site_root>/web`
  - read/write access to frontend repo checkout
  - git remote access (SSH key or credential helper)
- PHP CLI user for worker must be able to read DB config at `<site_root>/private/dbcon.php`.

### Troubleshooting

- DNS or git access failures:
  - Test `git fetch` manually in frontend repo.
  - Validate SSH keys/known_hosts for worker user.
- Rsync permission denied:
  - Verify target ownership and group write settings.
  - Confirm deploy user can write destination files/directories.
- Jobs stuck in `queued`:
  - Confirm cron is running and worker path is correct.
  - Run worker manually and inspect output.
- Jobs stuck in `running`:
  - Worker auto-fails stale jobs (default 120 minutes).
  - Manual reset option:
    `UPDATE cms_deploy_jobs SET status='failed', finished_at=NOW(), exit_code=124 WHERE status='running';`

### New Site Onboarding Checklist

1. Ensure site path resolves to canonical `<site_root>/web` under `/var/www`.
2. Deploy `wccms/` to `<site_root>/web/wccms`.
3. Ensure `<site_root>/web/wccms/deploy_scripts/` contains both scripts.
4. Set executable permissions (`chmod 755`).
5. Set CMS prefs:
   - `prefFrontendDeployRepoName` (default `frontend`)
   - optional `prefFrontendDeployRepoPath` (absolute or site-root-relative override)
6. Apply migration `2026-02-17-cms-deploy-jobs.sql`.
7. Add worker cron entry.
8. In CMS, verify `prefFrontendDeployMinRole` (defaults to `4` if missing).
9. Log in with high-role user and open `/wccms/tools-frontend.php`.
10. Run `Check Status`, queue deploy, verify job transitions and output.

## Local-only master DB config

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

## Recommended workflow

1. Open `/wccms/tools-data.php`
2. Run `Check Target DB`
3. Run `Preview Missing Schema`
4. Review planned SQL
5. Backup target DB
6. Tick `Apply changes now` and run `Apply Missing Schema`
7. Re-test `/wccms/login.php`
