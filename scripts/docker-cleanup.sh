#!/usr/bin/env bash
set -euo pipefail

THRESHOLD=70
USAGE=$(df / --output=pcent | tail -1 | tr -d ' %')

# Always prune images older than 7 days
docker image prune -a --filter "until=168h" -f > /dev/null 2>&1
docker builder prune --filter "until=168h" -f > /dev/null 2>&1

# If still above threshold, aggressive prune
USAGE_AFTER=$(df / --output=pcent | tail -1 | tr -d ' %')
if [ "$USAGE_AFTER" -ge "$THRESHOLD" ]; then
    docker system prune -a -f > /dev/null 2>&1
    echo "WARNING: Disk usage was ${USAGE_AFTER}% after standard cleanup, ran aggressive prune"
fi
