#!/bin/bash

# ClassFlow Pro Build Script

# Exit on error
set -e

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo "Building ClassFlow Pro..."

# Change to the project directory
cd /Users/brandon/Documents/GitHub/classflow-pro

# Clean previous build
echo "Cleaning previous build..."
rm -f ../classflow-pro.zip
rm -rf vendor

# Install production dependencies
echo "Installing production dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Build assets if needed (uncomment if you have npm build process)
# if [ -f "package.json" ]; then
#     echo "Building assets..."
#     npm install
#     npm run build
# fi

# Create zip file
echo "Creating zip file..."
cd ..
zip -r classflow-pro.zip classflow-pro \
    -x "classflow-pro/.git/*" \
    -x "classflow-pro/.gitignore" \
    -x "classflow-pro/.github/*" \
    -x "classflow-pro/.DS_Store" \
    -x "classflow-pro/**/.DS_Store" \
    -x "classflow-pro/node_modules/*" \
    -x "classflow-pro/tests/*" \
    -x "classflow-pro/phpunit.xml" \
    -x "classflow-pro/phpstan.neon" \
    -x "classflow-pro/.phpcs.xml" \
    -x "classflow-pro/composer.lock" \
    -x "classflow-pro/package-lock.json" \
    -x "classflow-pro/build.sh" \
    -x "classflow-pro/*.md" \
    -x "classflow-pro/.env" \
    -x "classflow-pro/.env.example" \
    -x "classflow-pro/src/**/*.test.php" \
    -x "classflow-pro/src/**/*.spec.php"

echo -e "${GREEN}Build complete!${NC}"
echo "Output: classflow-pro.zip"

# Get file size
if [ -f "classflow-pro.zip" ]; then
    SIZE=$(du -h classflow-pro.zip | cut -f1)
    echo "File size: $SIZE"
fi

# Return to original directory
cd classflow-pro