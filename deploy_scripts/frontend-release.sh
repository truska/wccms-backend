#!/usr/bin/env bash
set -euo pipefail

MODE="${1:-}"
if [[ "${MODE}" != "check" && "${MODE}" != "deploy" ]]; then
  echo "Usage: $0 {check|deploy}" >&2
  exit 2
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WCCMS_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
WEB_ROOT="$(cd "${WCCMS_ROOT}/.." && pwd)"
SITE_ROOT="$(cd "${WEB_ROOT}/.." && pwd)"

FRONTEND_REPO_NAME="${FRONTEND_REPO_NAME:-frontend}"
FRONTEND_REPO="${FRONTEND_REPO:-${SITE_ROOT}/${FRONTEND_REPO_NAME}}"
LIVE_ROOT="${LIVE_ROOT:-${WEB_ROOT}}"
REPO_CSS_FILE="${REPO_CSS_FILE:-${FRONTEND_REPO}/css/site.css}"
LIVE_CSS_FILE="${LIVE_CSS_FILE:-${LIVE_ROOT}/css/site.css}"

RSYNC_EXCLUDES=(
  --exclude ".git"
  --exclude "wccms"
  --exclude "deploy_scripts"
  --exclude "tools"
  --exclude "sql"
  --exclude "private"
  --exclude "log"
  --exclude "tmp"
  --exclude "ssl"
)

hash_file() {
  local file_path="$1"
  if [[ -f "${file_path}" ]]; then
    sha256sum "${file_path}" | awk '{print $1}'
  else
    echo "missing"
  fi
}

print_status() {
  local repo_commit="missing"
  if [[ -d "${FRONTEND_REPO}/.git" ]]; then
    repo_commit="$(git -C "${FRONTEND_REPO}" rev-parse --short HEAD 2>/dev/null || echo unknown)"
  fi

  local repo_hash live_hash sync_state
  repo_hash="$(hash_file "${REPO_CSS_FILE}")"
  live_hash="$(hash_file "${LIVE_CSS_FILE}")"

  if [[ "${repo_hash}" != "missing" && "${repo_hash}" == "${live_hash}" ]]; then
    sync_state="in_sync"
  else
    sync_state="out_of_sync"
  fi

  echo "site_root=${SITE_ROOT}"
  echo "frontend_repo_name=${FRONTEND_REPO_NAME}"
  echo "frontend_repo=${FRONTEND_REPO}"
  echo "live_root=${LIVE_ROOT}"
  echo "repo_commit=${repo_commit}"
  echo "repo_css_hash=${repo_hash}"
  echo "live_css_hash=${live_hash}"
  echo "sync_status=${sync_state}"
}

run_deploy() {
  if [[ ! -d "${FRONTEND_REPO}" ]]; then
    echo "Frontend repo not found: ${FRONTEND_REPO}" >&2
    return 3
  fi

  if [[ ! -d "${FRONTEND_REPO}/.git" ]]; then
    echo "Frontend repo is not a git checkout: ${FRONTEND_REPO}" >&2
    return 3
  fi

  if [[ ! -d "${LIVE_ROOT}" ]]; then
    echo "Live root not found: ${LIVE_ROOT}" >&2
    return 3
  fi

  git -C "${FRONTEND_REPO}" fetch --all --prune
  git -C "${FRONTEND_REPO}" pull --ff-only
  rsync -a --delete "${RSYNC_EXCLUDES[@]}" "${FRONTEND_REPO}/" "${LIVE_ROOT}/"
}

if [[ "${MODE}" == "deploy" ]]; then
  run_deploy
fi

print_status
