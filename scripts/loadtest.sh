#!/usr/bin/env bash
set -euo pipefail

TARGET="${1:-http://127.0.0.1:8080/}"
HOST_HEADER="${2:-localhost}"

if command -v hey >/dev/null 2>&1; then
  hey -n 2000 -c 50 -t 5 -host "$HOST_HEADER" "$TARGET"
  hey -n 200 -c 20 -t 5 -host "$HOST_HEADER" "${TARGET}?q=%3Cscript%3Ealert(1)%3C/script%3E"
  exit 0
fi

if command -v wrk >/dev/null 2>&1; then
  wrk -t4 -c50 -d20s -H "Host: $HOST_HEADER" "$TARGET"
  exit 0
fi

echo "Install hey (https://github.com/rakyll/hey) or wrk, then re-run."
echo "Example: hey -n 2000 -c 50 -host $HOST_HEADER $TARGET"
exit 1
