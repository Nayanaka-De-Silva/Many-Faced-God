#!/bin/sh
# Post-deploy smoke gate. Runs INSIDE the app container and talks to the
# deployed stack over HTTP through nginx, because that is the only place a
# runtime dependency failure shows up — the unit suite runs on sqlite and the
# migrate step proved only that one connection worked, once (issue #74).
#
# Every route below touches MySQL. Requests are repeated because a name that
# resolves to more than one container fails intermittently: a single lucky
# request is not evidence that the stack is healthy.
set -eu

BASE_URL="${BASE_URL:-http://webserver}"
ATTEMPTS="${ATTEMPTS:-10}"

request_status() {
    curl -sS -o /dev/null -w '%{http_code}' --max-time 10 "$1"
}

assert_ok_repeatedly() {
    path="$1"
    attempt=1
    while [ "$attempt" -le "$ATTEMPTS" ]; do
        status=$(request_status "${BASE_URL}${path}")
        if [ "$status" != "200" ]; then
            echo "SMOKE FAIL: GET ${path} returned ${status} on attempt ${attempt}/${ATTEMPTS}" >&2
            curl -sS --max-time 10 "${BASE_URL}${path}" | head -c 2000 >&2
            echo >&2
            return 1
        fi
        attempt=$((attempt + 1))
    done
    echo "ok: GET ${path} (${ATTEMPTS}/${ATTEMPTS} => 200)"
}

first_npc_id() {
    curl -sS --max-time 10 "${BASE_URL}/api/v1/npcs" \
        | tr ',' '\n' \
        | sed -n 's/.*"id":[[:space:]]*\([0-9][0-9]*\).*/\1/p' \
        | head -n 1
}

echo "Smoke-testing ${BASE_URL} (${ATTEMPTS} requests per route)..."

assert_ok_repeatedly "/up"
assert_ok_repeatedly "/npcs"
assert_ok_repeatedly "/api/v1/npcs"

npc_id=$(first_npc_id)
if [ -n "$npc_id" ]; then
    assert_ok_repeatedly "/npcs/${npc_id}"
    assert_ok_repeatedly "/api/v1/npcs/${npc_id}"
else
    echo "note: no NPCs returned by /api/v1/npcs, skipping the detail-page checks"
fi

echo "Smoke tests passed."
