#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Usage:
  wccms/sql/tools/export_schema.sh --out FILE.sql

Exports schema only (no data) using DB credentials loaded from private/dbcon.php.
EOF
}

OUT_FILE=""
while [ "$#" -gt 0 ]; do
  case "$1" in
    --out)
      OUT_FILE="$2"; shift 2 ;;
    *)
      echo "Unknown argument: $1" >&2
      usage
      exit 1 ;;
  esac
done

if [ -z "$OUT_FILE" ]; then
  usage
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEB_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
DBCON_FILE="$WEB_ROOT/../private/dbcon.php"

if [ ! -f "$DBCON_FILE" ]; then
  echo "Cannot find db config: $DBCON_FILE" >&2
  exit 1
fi

if ! command -v mysqldump >/dev/null 2>&1; then
  echo 'mysqldump is required but not found in PATH.' >&2
  exit 1
fi

DB_LINE="$(php -r '
require $argv[1];
if (!isset($DB_HOST, $DB_NAME, $DB_USER, $DB_PASS)) {
  fwrite(STDERR, "DB vars missing in dbcon.php\n");
  exit(1);
}
echo $DB_HOST, "\t", $DB_NAME, "\t", $DB_USER, "\t", $DB_PASS;
' "$DBCON_FILE")"

IFS=$'\t' read -r DB_HOST DB_NAME DB_USER DB_PASS <<<"$DB_LINE"

mkdir -p "$(dirname "$OUT_FILE")"
mysqldump \
  --host="$DB_HOST" \
  --user="$DB_USER" \
  "--password=$DB_PASS" \
  --no-data \
  --skip-comments \
  --skip-triggers \
  "$DB_NAME" > "$OUT_FILE"

echo "Schema exported to: $OUT_FILE"
