#!/usr/bin/env bash
set -euo pipefail

ROOT=$(cd "$(dirname "$0")/.." && pwd)
PLUGIN_DIR="$ROOT/nuclear-engagement"
BUILD_DIR="$ROOT/build"
ASSETS_DIR="$PLUGIN_DIR/assets"

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"

# Install PHP dependencies without dev packages
composer install --no-dev --optimize-autoloader --no-interaction --working-dir="$PLUGIN_DIR"

# Build front-end assets
npm ci
npm run build

# Clean up old JS chunks that may have accumulated
# Keeps only the most recent chunk files to prevent bloat
printf 'Cleaning up old JavaScript chunks...\n'
if [ -d "$ASSETS_DIR/js" ]; then
    # Remove old .js and .js.map files with hash patterns (chunk-XXXX.js)
    # but preserve main entry points
    find "$ASSETS_DIR/js" -name "chunk-*.js" -type f -mtime +1 -delete 2>/dev/null || true
    find "$ASSETS_DIR/js" -name "chunk-*.js.map" -type f -mtime +1 -delete 2>/dev/null || true

    # Also clean any orphaned .LICENSE.txt files
    find "$ASSETS_DIR/js" -name "*.LICENSE.txt" -type f -mtime +7 -delete 2>/dev/null || true

    printf 'Old chunks cleaned.\n'
fi

# Copy plugin files excluding development artifacts
rsync -a "$PLUGIN_DIR/" "$BUILD_DIR/nuclear-engagement/" --exclude-from="$ROOT/.distignore"

# Create zip archive
cd "$BUILD_DIR"
zip -r nuclear-engagement.zip nuclear-engagement > /dev/null

printf '\nRelease archive created at %s/nuclear-engagement.zip\n' "$BUILD_DIR"
