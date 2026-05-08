#!/usr/bin/env bash
#
# Build the wordpress.org-ready ZIP for the CaptchaLa WP plugin.
# Delegates to the shared PHP build script which:
#   1. composer install --no-dev --optimize-autoloader
#   2. zips into release/wordpress-<version>.zip with the plugin folder
#      flattened to `captchala/` (no version-suffixed wrapper directory).
#
# Usage:
#   ./build.sh           # uses version from captchala.php
#   VERSION=1.0.1 ./build.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

cd "${SCRIPT_DIR}"
rm -rf release
mkdir -p release

# Fresh vendor — path-repo'd captchala-php SDK won't auto-refresh on src
# change unless we force-reinstall.
rm -rf vendor/captchala
if [[ -f composer.json ]] && command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader --quiet
fi

VERSION="${VERSION:-$(grep -oE "Version:[[:space:]]+[0-9]+\.[0-9]+\.[0-9]+" captchala.php | awk '{print $2}')}"
STAGE="$(mktemp -d)"
mkdir -p "${STAGE}/captchala"

# wordpress.org's plugin scanner rejects unknown files in the root, and we
# don't want to leak dev tooling into the public download. Keep only what
# the plugin actually needs at runtime.
#
# composer.json is intentionally KEPT: the plugin ships a populated vendor/
# directory and the wp.org checker flags `vendor without composer.json`.
# composer.lock is also kept so the dependency tree is auditable.
tar \
    --exclude='./release' \
    --exclude='./.git' \
    --exclude='./tests' \
    --exclude='./node_modules' \
    --exclude='./*.zip' \
    --exclude='./build.sh' \
    --exclude='./SUBMIT.md' \
    --exclude='./INTEGRATIONS.md' \
    -cf - . | ( cd "${STAGE}/captchala" && tar -xf - )

( cd "${STAGE}" && zip -rq "${SCRIPT_DIR}/release/wordpress-${VERSION}.zip" captchala )
rm -rf "${STAGE}"
echo "[captchala-wp] Built release/wordpress-${VERSION}.zip"
