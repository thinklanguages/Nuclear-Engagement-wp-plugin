#!/usr/bin/env bash
set -euo pipefail

ROOT=$(cd "$(dirname "$0")/.." && pwd)
PLUGIN_DIR="$ROOT/nuclear-engagement"
BUILD_DIR="$ROOT/build"

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"

# Install PHP dependencies without dev packages
composer install --no-dev --optimize-autoloader --no-interaction

# Build front-end assets
npm ci
npm run build

# Copy plugin files excluding development artifacts
rsync -a "$PLUGIN_DIR/" "$BUILD_DIR/nuclear-engagement/" --exclude-from="$ROOT/.distignore"

# Create zip archive
cd "$BUILD_DIR"
zip -r nuclear-engagement.zip nuclear-engagement > /dev/null

printf '\nRelease archive created at %s/nuclear-engagement.zip\n' "$BUILD_DIR"
