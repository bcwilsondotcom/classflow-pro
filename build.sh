#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD_DIR="$ROOT_DIR/build"
PKG_DIR="$BUILD_DIR/classflow-pro"
ZIP_FILE="$BUILD_DIR/classflow-pro.zip"

command -v zip >/dev/null 2>&1 || { echo "Error: 'zip' is required but not installed." >&2; exit 1; }

echo "Cleaning build directory..."
rm -rf "$PKG_DIR" "$ZIP_FILE"
mkdir -p "$PKG_DIR"

echo "Staging plugin files..."
cp "$ROOT_DIR/classflow-pro.php" "$PKG_DIR/"

if [ -d "$ROOT_DIR/includes" ]; then
  cp -R "$ROOT_DIR/includes" "$PKG_DIR/"
fi
if [ -d "$ROOT_DIR/assets" ]; then
  cp -R "$ROOT_DIR/assets" "$PKG_DIR/"
fi
if [ -d "$ROOT_DIR/languages" ]; then
  cp -R "$ROOT_DIR/languages" "$PKG_DIR/"
fi
# Copy readme (support both cases) and uninstall script
if [ -f "$ROOT_DIR/readme.txt" ]; then
  cp "$ROOT_DIR/readme.txt" "$PKG_DIR/readme.txt"
elif [ -f "$ROOT_DIR/README.txt" ]; then
  cp "$ROOT_DIR/README.txt" "$PKG_DIR/readme.txt"
fi
if [ -f "$ROOT_DIR/uninstall.php" ]; then
  cp "$ROOT_DIR/uninstall.php" "$PKG_DIR/"
fi

echo "Creating zip..."
(cd "$BUILD_DIR" && zip -rq "$(basename "$ZIP_FILE")" "$(basename "$PKG_DIR")")

echo "Build complete: $ZIP_FILE"
