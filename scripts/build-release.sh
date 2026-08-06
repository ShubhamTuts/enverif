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
trap 'rm -rf "$WORK"' EXIT
rm -rf "$DIST"
mkdir -p "$DIST" "$WORK/source" "$WORK/shared/enverif" "$WORK/websites"

php -d zend.assertions=1 -d assert.exception=1 tests/standalone/run.php
php scripts/verify.php
php scripts/build-site.php
php scripts/check-site.php

# Exact source tree from the checked-out Git commit.
git archive --format=tar --prefix="enverif-v${VERSION}/" HEAD | tar -xf - -C "$WORK/source"
(
  cd "$WORK/source"
  zip -q -r "$DIST/enverif-v${VERSION}-source.zip" "enverif-v${VERSION}"
)

# Shared-hosting package: production source + locked Composer dependencies.
git archive --format=tar HEAD | tar -xf - -C "$WORK/shared/enverif"
composer install \
  --working-dir="$WORK/shared/enverif" \
  --no-dev \
  --no-interaction \
  --prefer-dist \
  --classmap-authoritative \
  --no-progress

rm -rf \
  "$WORK/shared/enverif/.github" \
  "$WORK/shared/enverif/.worktrees" \
  "$WORK/shared/enverif/node_modules" \
  "$WORK/shared/enverif/site" \
  "$WORK/shared/enverif/tests" \
  "$WORK/shared/enverif/websites"
rm -f "$WORK/shared/enverif/.env" "$WORK/shared/enverif/.env."* "$WORK/shared/enverif/phpunit.xml"
mkdir -p "$WORK/shared/enverif/vendor"
cat > "$WORK/shared/enverif/vendor/.htaccess" <<'EOF'
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Deny from all
</IfModule>
EOF
cp README-INSTALL.txt "$WORK/shared/enverif/README-INSTALL.txt"

# Release package hygiene: no secrets/development state, required runtime files present.
for required in artisan .htaccess public/.htaccess storage/.htaccess database/.htaccess vendor/autoload.php README-INSTALL.txt VERSION; do
  [[ -e "$WORK/shared/enverif/$required" ]] || { echo "Shared package missing $required" >&2; exit 1; }
done
for forbidden in .env .git node_modules tests websites; do
  [[ ! -e "$WORK/shared/enverif/$forbidden" ]] || { echo "Shared package contains forbidden $forbidden" >&2; exit 1; }
done
(
  cd "$WORK/shared"
  zip -q -r "$DIST/enverif-v${VERSION}-shared-hosting.zip" enverif
)

# Standalone enverif.com + docs.enverif.com package.
cp -a websites "$WORK/websites/enverif-websites"
(
  cd "$WORK/websites"
  zip -q -r "$DIST/enverif-v${VERSION}-websites.zip" enverif-websites
)

(
  cd "$DIST"
  sha256sum *.zip > SHA256SUMS.txt
)

printf 'Release artifacts built in %s\n' "$DIST"
ls -lh "$DIST"
