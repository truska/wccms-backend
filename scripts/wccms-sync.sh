#!/usr/bin/env bash
set -euo pipefail

if [ "${1:-}" = "" ]; then
  echo "Usage: $0 <site_web_root> [branch]"
  echo "Example: $0 /var/www/dev.witecanvas.com/web staging"
  exit 1
fi

SITE_WEB_ROOT="$1"
BRANCH="${2:-main}"
REPO_URL="${WCCMS_REPO_URL:-https://github.com/truska/wccms.git}"
TARGET_DIR="${SITE_WEB_ROOT%/}/wccms"

if [ ! -d "$SITE_WEB_ROOT" ]; then
  echo "Site web root does not exist: $SITE_WEB_ROOT"
  exit 1
fi

if [ -d "$TARGET_DIR/.git" ]; then
  echo "Updating existing WCCMS checkout in $TARGET_DIR"
  if [ -n "$(git -C "$TARGET_DIR" status --porcelain)" ]; then
    echo "Aborting: local changes found in $TARGET_DIR"
    echo "Commit/stash/discard local changes first."
    exit 1
  fi

  git -C "$TARGET_DIR" fetch origin
  git -C "$TARGET_DIR" checkout "$BRANCH"
  git -C "$TARGET_DIR" pull --ff-only origin "$BRANCH"
else
  echo "Cloning WCCMS into $TARGET_DIR"
  git clone --branch "$BRANCH" --single-branch "$REPO_URL" "$TARGET_DIR"
fi

echo "Done. WCCMS is on branch '$BRANCH' at:"
git -C "$TARGET_DIR" rev-parse --short HEAD
