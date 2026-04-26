#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TESTS_DIR="${SCRIPT_DIR}/tests"
ENV_TEST="${CSDT_ENV_TEST:-${SCRIPT_DIR}/.env.test}"

# ── Load CSDT session credentials ────────────────────────────────────────────
if [[ ! -f "$ENV_TEST" ]]; then
    echo "ERROR: $ENV_TEST not found."
    echo "  Run: bash setup-playwright-test-account.sh"
    exit 1
fi

CSDT_SECRET=$(grep '^CSDT_TEST_SECRET='        "$ENV_TEST" | cut -d'=' -f2- | tr -d '\r')
CSDT_ROLE=$(grep '^CSDT_TEST_ROLE='            "$ENV_TEST" | cut -d'=' -f2- | tr -d '\r')
CSDT_SESSION_URL=$(grep '^CSDT_TEST_SESSION_URL=' "$ENV_TEST" | cut -d'=' -f2- | tr -d '\r')
CSDT_LOGOUT_URL=$(grep '^CSDT_TEST_LOGOUT_URL='   "$ENV_TEST" | cut -d'=' -f2- | tr -d '\r')

for var in CSDT_SECRET CSDT_ROLE CSDT_SESSION_URL CSDT_LOGOUT_URL; do
    [[ -z "${!var}" ]] && { echo "ERROR: $var not set in $ENV_TEST"; exit 1; }
done

# ── Load WP_BASE_URL ──────────────────────────────────────────────────────────
if [[ -f "$TESTS_DIR/.env" ]]; then
    WP_BASE_URL=$(grep '^WP_BASE_URL=' "$TESTS_DIR/.env" | cut -d'=' -f2- | tr -d '\r')
fi
[[ -z "${WP_BASE_URL:-}" ]] && WP_BASE_URL=$(grep '^WP_SITE=' "$ENV_TEST" | cut -d'=' -f2- | tr -d '\r')
[[ -z "${WP_BASE_URL:-}" ]] && { echo "ERROR: WP_BASE_URL not set in $TESTS_DIR/.env or $ENV_TEST"; exit 1; }

# ── Obtain test session via CSDT API ─────────────────────────────────────────
echo "--- Obtaining test session via CSDT API (role: ${CSDT_ROLE})..."
SESSION_JSON=$(curl -s -X POST "$CSDT_SESSION_URL" \
    --data-urlencode "secret=${CSDT_SECRET}" \
    --data-urlencode "role=${CSDT_ROLE}" \
    -d "ttl=7200")

if ! echo "$SESSION_JSON" | python3 -c "import sys,json; d=json.load(sys.stdin); exit(0 if 'session_token' in d else 1)" 2>/dev/null; then
    echo "ERROR: Test session API failed. Response: $SESSION_JSON"
    exit 1
fi

SESSION_TOKEN=$(echo "$SESSION_JSON" | python3 -c "import sys,json; print(json.load(sys.stdin)['session_token'])")
echo "    Session obtained."

# Map CSDT response to WP_COOKIES JSON for Playwright cookie injection.
WP_COOKIES=$(echo "$SESSION_JSON" | python3 -c "
import sys, json
d = json.load(sys.stdin)
print(json.dumps({
    'auth_name':   d['secure_auth_cookie_name'],
    'auth_value':  d['secure_auth_cookie'],
    'sec_name':    d['secure_auth_cookie_name'],
    'sec_value':   d['secure_auth_cookie'],
    'login_name':  d['logged_in_cookie_name'],
    'login_value': d['logged_in_cookie'],
    'domain':      d['cookie_domain'],
    'expiration':  d['expires_at'],
}))
")

# ── Cleanup: invalidate session on exit ──────────────────────────────────────
_cleanup() {
    echo ""
    echo "--- Closing test session..."
    curl -s -X POST "$CSDT_LOGOUT_URL" \
        --data-urlencode "secret=${CSDT_SECRET}" \
        --data-urlencode "role=${CSDT_ROLE}" \
        --data-urlencode "session_token=${SESSION_TOKEN}" > /dev/null
    echo "--- Test session closed."
}
trap _cleanup EXIT

# ── Install Node deps + Playwright browser on first run ──────────────────────
cd "$TESTS_DIR"
if [[ ! -d node_modules ]]; then
    echo "--- Installing Playwright dependencies..."
    npm install
    npx playwright install chromium
fi

# ── Run tests ─────────────────────────────────────────────────────────────────
echo ""
echo "--- Running UI tests..."
WP_BASE_URL="${WP_BASE_URL}" WP_COOKIES="${WP_COOKIES}" \
    npx playwright test "$@"
