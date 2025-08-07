#!/bin/bash

# WordPress.org Plugin Setup Script
# This script helps set up the plugin for WordPress.org deployment

echo "🚀 elnk.pro Link Shortener - WordPress.org Setup"
echo "================================================"

# Check if we're in the right directory
if [ ! -f "elnk-pro-shortener.php" ]; then
    echo "❌ Error: Please run this script from the plugin root directory"
    exit 1
fi

echo "✅ Plugin directory confirmed"

# Check required files
echo "🔍 Checking required files..."

REQUIRED_FILES=("elnk-pro-shortener.php" "README.txt" "includes/class-elnk-pro-admin.php")
MISSING_FILES=()

for file in "${REQUIRED_FILES[@]}"; do
    if [ ! -f "$file" ]; then
        MISSING_FILES+=("$file")
    fi
done

if [ ${#MISSING_FILES[@]} -gt 0 ]; then
    echo "❌ Missing required files:"
    printf ' - %s\n' "${MISSING_FILES[@]}"
    exit 1
fi

echo "✅ All required files found"

# Check version consistency
echo "🔍 Checking version consistency..."

PLUGIN_VERSION=$(grep "Version:" elnk-pro-shortener.php | head -1 | sed 's/.*Version: *//' | sed 's/ *$//' | tr -d '\r')
README_VERSION=$(grep "Stable tag:" README.txt | sed 's/.*Stable tag: *//' | sed 's/ *$//' | tr -d '\r')

echo "Plugin file version: $PLUGIN_VERSION"
echo "README.txt version: $README_VERSION"

if [ "$PLUGIN_VERSION" != "$README_VERSION" ]; then
    echo "⚠️  Version mismatch detected!"
    echo "Would you like to sync versions? (y/n)"
    read -r response
    if [[ "$response" =~ ^[Yy]$ ]]; then
        sed -i "s/Stable tag: .*/Stable tag: $PLUGIN_VERSION/" README.txt
        echo "✅ Versions synced to: $PLUGIN_VERSION"
    else
        echo "❌ Please manually fix version mismatch before deployment"
        exit 1
    fi
else
    echo "✅ Versions match: $PLUGIN_VERSION"
fi

# Check Git setup
echo "🔍 Checking Git setup..."

if ! git rev-parse --git-dir > /dev/null 2>&1; then
    echo "❌ This is not a Git repository"
    echo "Initialize Git repository? (y/n)"
    read -r response
    if [[ "$response" =~ ^[Yy]$ ]]; then
        git init
        echo "✅ Git repository initialized"
    else
        echo "⚠️  Git is required for automated deployment"
    fi
else
    echo "✅ Git repository found"
fi

# Check GitHub setup
if [ -f ".git/config" ] && grep -q "github.com" .git/config; then
    echo "✅ GitHub remote configured"
else
    echo "⚠️  No GitHub remote found"
    echo "Please add your GitHub repository as remote:"
    echo "git remote add origin https://github.com/yourusername/elnk-pro-shortener.git"
fi

# Check GitHub Actions
if [ -d ".github/workflows" ]; then
    echo "✅ GitHub Actions workflows found"
    echo "Available workflows:"
    ls -1 .github/workflows/*.yml | sed 's/.*\//  - /'
else
    echo "❌ GitHub Actions workflows not found"
fi

# Security recommendations
echo "🔒 Security checklist:"

# Check for ABSPATH protection
if grep -r "ABSPATH" --include="*.php" . > /dev/null; then
    echo "✅ Direct access protection found"
else
    echo "⚠️  Consider adding ABSPATH protection to PHP files"
fi

# Check for nonce usage
if grep -r "wp_nonce" --include="*.php" . > /dev/null; then
    echo "✅ Nonce security found"
else
    echo "⚠️  Consider adding nonce verification for forms"
fi

# WordPress.org readiness
echo "📦 WordPress.org readiness checklist:"

# Check README.txt format
if grep -q "=== .* ===" README.txt && grep -q "== Description ==" README.txt; then
    echo "✅ README.txt format looks good"
else
    echo "❌ README.txt needs proper WordPress.org formatting"
fi

# Check GPL license
if grep -qi "gpl" README.txt || grep -qi "gpl" elnk-pro-shortener.php; then
    echo "✅ GPL license referenced"
else
    echo "⚠️  Ensure GPL-compatible license is specified"
fi

echo ""
echo "🎯 Next Steps:"
echo "1. Set up GitHub repository secrets:"
echo "   - SVN_USERNAME: Your WordPress.org username"
echo "   - SVN_PASSWORD: Your WordPress.org password"
echo ""
echo "2. Submit plugin to WordPress.org for initial review"
echo "   https://wordpress.org/plugins/developers/add/"
echo ""
echo "3. After approval, create a release:"
echo "   git tag 1.0.0"
echo "   git push origin 1.0.0"
echo ""
echo "4. The GitHub Action will automatically deploy to WordPress.org"
echo ""
echo "📚 Read DEPLOYMENT.md for detailed instructions"
echo ""
echo "✅ Setup complete! Your plugin is ready for WordPress.org submission."
