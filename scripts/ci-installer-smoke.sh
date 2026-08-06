#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

BASE_URL="${ENVERIF_INSTALLER_SMOKE_URL:-http://127.0.0.1:8013}"
PORT="${ENVERIF_INSTALLER_SMOKE_PORT:-8013}"
DB_HOST_SMOKE="${DB_HOST:-127.0.0.1}"
DB_PORT_SMOKE="${DB_PORT:-3306}"
DB_DATABASE_SMOKE="${DB_DATABASE:-enverif_installer_test}"
DB_USERNAME_SMOKE="${DB_USERNAME:-root}"
DB_PASSWORD_SMOKE="${DB_PASSWORD:-root}"
ADMIN_EMAIL="installer@example.test"
ADMIN_PASSWORD="InstallerPass123!"
COOKIE_JAR="$(mktemp)"
WORK="$(mktemp -d)"
SERVER_LOG="$WORK/server.log"
SERVER_PID=""

cleanup() {
  if [[ -n "$SERVER_PID" ]] && kill -0 "$SERVER_PID" 2>/dev/null; then
    kill "$SERVER_PID" 2>/dev/null || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
  rm -f "$COOKIE_JAR"
  rm -rf "$WORK"
}
trap cleanup EXIT

start_server() {
  : > "$SERVER_LOG"
  php artisan serve --host=127.0.0.1 --port="$PORT" >"$SERVER_LOG" 2>&1 &
  SERVER_PID=$!
  for _ in $(seq 1 40); do
    if curl -fsS "$BASE_URL/install" >/dev/null 2>&1; then
      return 0
    fi
    sleep 0.5
  done
  cat "$SERVER_LOG" >&2
  echo "Laravel test server did not become ready." >&2
  exit 1
}

stop_server() {
  if [[ -n "$SERVER_PID" ]] && kill -0 "$SERVER_PID" 2>/dev/null; then
    kill "$SERVER_PID" 2>/dev/null || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
  SERVER_PID=""
}

csrf_from() {
  local url="$1"
  local out="$2"
  curl -fsS -c "$COOKIE_JAR" -b "$COOKIE_JAR" "$url" -o "$out"
  sed -n 's/.*name="_token" value="\([^"]*\)".*/\1/p' "$out" | head -n1
}

assert_http_200() {
  local path="$1"
  local body="$WORK/body-$(echo "$path" | tr '/?' '__').html"
  local code
  code="$(curl -sS -c "$COOKIE_JAR" -b "$COOKIE_JAR" -o "$body" -w '%{http_code}' "$BASE_URL$path")"
  if [[ "$code" != "200" ]]; then
    echo "Expected 200 for $path, received $code" >&2
    cat "$body" >&2 || true
    cat "$SERVER_LOG" >&2 || true
    exit 1
  fi
  if grep -Eqi 'Internal Server Error|Server Error|ParseError|FatalError' "$body"; then
    echo "Framework error page detected at $path" >&2
    cat "$body" >&2
    exit 1
  fi
}

rm -f storage/app/installed storage/app/bootstrap.key bootstrap/cache/config.php
find storage/framework/views -type f ! -name '.gitignore' -delete 2>/dev/null || true
cp .env.example .env
# Keep the pre-install request independent from database-backed session/cache/queue.
php -r '$p=".env";$s=file_get_contents($p);$s=preg_replace("/^APP_KEY=.*$/m","APP_KEY=",$s);$s=preg_replace("/^APP_URL=.*$/m","APP_URL=http://127.0.0.1:8013",$s);$s=preg_replace("/^SESSION_DRIVER=.*$/m","SESSION_DRIVER=file",$s);$s=preg_replace("/^CACHE_STORE=.*$/m","CACHE_STORE=file",$s);$s=preg_replace("/^QUEUE_CONNECTION=.*$/m","QUEUE_CONNECTION=sync",$s);file_put_contents($p,$s);'

start_server
INSTALL_HTML="$WORK/install.html"
TOKEN="$(csrf_from "$BASE_URL/install" "$INSTALL_HTML")"
[[ -n "$TOKEN" ]] || { cat "$INSTALL_HTML" >&2; echo "Installer CSRF token missing." >&2; exit 1; }

curl -sS -D "$WORK/install.headers" -o "$WORK/install.response" \
  -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
  --data-urlencode "_token=$TOKEN" \
  --data-urlencode "app_url=$BASE_URL" \
  --data-urlencode "timezone=UTC" \
  --data-urlencode "locale=en" \
  --data-urlencode "db_host=$DB_HOST_SMOKE" \
  --data-urlencode "db_port=$DB_PORT_SMOKE" \
  --data-urlencode "db_database=$DB_DATABASE_SMOKE" \
  --data-urlencode "db_username=$DB_USERNAME_SMOKE" \
  --data-urlencode "db_password=$DB_PASSWORD_SMOKE" \
  --data-urlencode "runtime_mode=shared" \
  --data-urlencode "tick_budget=45" \
  --data-urlencode "admin_name=Installer Operator" \
  --data-urlencode "admin_email=$ADMIN_EMAIL" \
  --data-urlencode "admin_password=$ADMIN_PASSWORD" \
  --data-urlencode "admin_password_confirmation=$ADMIN_PASSWORD" \
  --data-urlencode "workspace_name=Installer Workspace" \
  "$BASE_URL/install"

if ! grep -Eiq '^location: .*\/login\r?$' "$WORK/install.headers"; then
  echo "Installer did not redirect to login." >&2
  cat "$WORK/install.headers" >&2
  cat "$WORK/install.response" >&2
  cat "$SERVER_LOG" >&2 || true
  exit 1
fi

stop_server
# Reboot the application from the .env that the installer actually wrote.
rm -f "$COOKIE_JAR" && COOKIE_JAR="$(mktemp)"
start_server

LOGIN_HTML="$WORK/login.html"
LOGIN_TOKEN="$(csrf_from "$BASE_URL/login" "$LOGIN_HTML")"
[[ -n "$LOGIN_TOKEN" ]] || { cat "$LOGIN_HTML" >&2; echo "Login CSRF token missing." >&2; exit 1; }

curl -sS -D "$WORK/login.headers" -o "$WORK/login.response" \
  -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
  --data-urlencode "_token=$LOGIN_TOKEN" \
  --data-urlencode "email=$ADMIN_EMAIL" \
  --data-urlencode "password=$ADMIN_PASSWORD" \
  "$BASE_URL/login"

if ! grep -Eiq '^location: ' "$WORK/login.headers" || grep -Eiq '^location: .*/login\r?$' "$WORK/login.headers"; then
  echo "Login did not redirect to the chat root." >&2
  cat "$WORK/login.headers" >&2
  cat "$WORK/login.response" >&2
  exit 1
fi

for path in / /agents /agents/create /workflows /workflows/create /connectors /skills /models /mcp /settings; do
  assert_http_200 "$path"
done

ROOT_HTML="$WORK/body-_.html"
grep -q 'data-chat-shell' "$ROOT_HTML" || { echo "Authenticated root did not render the chat shell." >&2; exit 1; }
if grep -q 'BY CODEFREEX' "$ROOT_HTML"; then
  echo "Product lockup still contains BY CODEFREEX." >&2
  exit 1
fi

echo "Fresh HTTP installer/login/core-screen smoke passed."
