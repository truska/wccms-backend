# Frontend Deploy Test Checklist

Use this checklist on dev backend environment only.

## Preconditions

- `cms_deploy_jobs` migration applied.
- `wccms/deploy_scripts/frontend-release.sh` and `wccms/deploy_scripts/cms-deploy-worker.sh` exist and are executable.
- Cron configured for worker or worker can be run manually.
- Logged in CMS user has role >= `prefFrontendDeployMinRole` (default `4`).

## Test steps

1. Open `/wccms/tools-frontend.php` as authorized user.
2. Confirm page shows:
   - current site root
   - detected frontend repo path
   - release script path
3. Click `Check Status`.
4. Confirm output block includes keys like:
   - `repo_commit=`
   - `repo_css_hash=`
   - `live_css_hash=`
   - `sync_status=`
5. Click `Queue Deploy`.
6. Confirm a new queued row appears in Recent Jobs.
7. Run worker manually:
   - `/var/www/<site>/web/wccms/deploy_scripts/cms-deploy-worker.sh`
8. Refresh page and verify job moved:
   - `queued` -> `running` -> `success` or `failed`
9. Expand job output and verify actionable logs are present.
10. Validate unauthorized access:
    - login as low-role user
    - open `/wccms/tools-frontend.php`
    - confirm 403-style message and no queue action.

## Example worker output (success)

```text
[worker] Running job #15 for /var/www/dev-wc.witecanvas.com
[worker] Job #15 finished with status=success exit=0
[worker] Done. Jobs processed: 1
```

## Example job output (failure)

```text
Frontend repo not found: /var/www/dev-wc.witecanvas.com/frontend
site_root=/var/www/dev-wc.witecanvas.com
frontend_repo=/var/www/dev-wc.witecanvas.com/frontend
live_root=/var/www/dev-wc.witecanvas.com/web
repo_commit=missing
repo_css_hash=missing
live_css_hash=8f1a...
sync_status=out_of_sync
```

## Screenshot set for PR

Capture and attach:
1. Tools page with site context and action buttons.
2. Queued job row in table.
3. Running or completed job row.
4. Expanded successful output.
5. Expanded failed output.
6. Unauthorized role access message.
