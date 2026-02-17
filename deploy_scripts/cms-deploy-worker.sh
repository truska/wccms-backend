#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WCCMS_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
WEB_ROOT="$(cd "${WCCMS_ROOT}/.." && pwd)"
SITE_ROOT="$(cd "${WEB_ROOT}/.." && pwd)"
WORKER_PHP="${WCCMS_ROOT}/bin/cms-deploy-worker.php"
PHP_BIN="${PHP_BIN:-php}"
MAX_JOBS="${MAX_JOBS:-5}"
STALE_MINUTES="${STALE_MINUTES:-120}"

if [[ ! -f "${WORKER_PHP}" ]]; then
  echo "[worker-sh] Missing worker PHP script: ${WORKER_PHP}" >&2
  exit 2
fi

exec "${PHP_BIN}" "${WORKER_PHP}" --max-jobs="${MAX_JOBS}" --stale-minutes="${STALE_MINUTES}" --site-root="${SITE_ROOT}"
