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
