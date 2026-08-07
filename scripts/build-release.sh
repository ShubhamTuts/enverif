#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
VERSION="$(tr -d '[:space:]' < VERSION)"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.-]+)?$ ]] || { echo "Invalid VERSION: $VERSION" >&2; exit 1; }
command -v git >/dev/null
command -v zip >/dev/null
command -v composer >/dev/null

DIST="${1:-$ROOT/dist}"
WORK="$(mktemp -d)"
PACKAGE_ROOT="enverif-${VERSION}"
trap 'rm -rf "$WORK"' EXIT
rm -rf "$DIST"
mkdir -p "$DIST" "$WORK/source" "$WORK/shared/$PACKAGE_ROOT"

php -d zend.assertions=1 -d assert.exception=1 tests/standalone/run.php
php scripts/verify.php

# Exact application source tree from the checked-out Git commit.
git archive --format=tar --prefix="${PACKAGE_ROOT}/" HEAD | tar -xf - -C "$WORK/source"
(
  cd "$WORK/source"
  zip -q -r "$DIST/enverif-${VERSION}-source.zip" "$PACKAGE_ROOT"
)

# Shared-hosting package: production source + locked Composer dependencies.
git archive --format=tar HEAD | tar -xf - -C "$WORK/shared/$PACKAGE_ROOT"
composer install \
  --working-dir="$WORK/shared/$PACKAGE_ROOT" \
  --no-dev \
  --no-interaction \
  --prefer-dist \
  --classmap-authoritative \
  --no-progress

rm -rf \
  "$WORK/shared/$PACKAGE_ROOT/.github" \
  "$WORK/shared/$PACKAGE_ROOT/.worktrees" \
  "$WORK/shared/$PACKAGE_ROOT/node_modules" \
  "$WORK/shared/$PACKAGE_ROOT/site" \
  "$WORK/shared/$PACKAGE_ROOT/tests" \
  "$WORK/shared/$PACKAGE_ROOT/websites"
rm -f "$WORK/shared/$PACKAGE_ROOT/.env" "$WORK/shared/$PACKAGE_ROOT/.env."* "$WORK/shared/$PACKAGE_ROOT/phpunit.xml"
mkdir -p "$WORK/shared/$PACKAGE_ROOT/vendor"
cat > "$WORK/shared/$PACKAGE_ROOT/vendor/.htaccess" <<'EOF'
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Deny from all
</IfModule>
EOF
cp README-INSTALL.txt "$WORK/shared/$PACKAGE_ROOT/README-INSTALL.txt"

# Release package hygiene: no secrets/development state, required runtime files present.
for required in artisan .htaccess public/.htaccess storage/.htaccess database/.htaccess vendor/autoload.php README-INSTALL.txt VERSION; do
  [[ -e "$WORK/shared/$PACKAGE_ROOT/$required" ]] || { echo "Shared package missing $required" >&2; exit 1; }
done
for forbidden in .env .git node_modules tests websites; do
  [[ ! -e "$WORK/shared/$PACKAGE_ROOT/$forbidden" ]] || { echo "Shared package contains forbidden $forbidden" >&2; exit 1; }
done
(
  cd "$WORK/shared"
  zip -q -r "$DIST/enverif-${VERSION}-shared-hosting.zip" "$PACKAGE_ROOT"
)

(
  cd "$DIST"
  sha256sum *.zip > SHA256SUMS.txt
)

printf 'Release artifacts built in %s\n' "$DIST"
ls -lh "$DIST"
