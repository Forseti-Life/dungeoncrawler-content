#!/bin/bash
#
# Pre-Sync Module Setup for Dev Environment
# Based on decisions in QUICK_DECISIONS_NEEDED.txt
#
# This prepares dev to match production module installations
# BEFORE we sync the config files.

set -e  # Exit on any error

echo "================================================"
echo "Pre-Sync Module Setup for Dev Environment"
echo "================================================"
echo ""

cd /home/keithaumiller/forseti.life/sites/forseti

# Step 1: Remove modules that don't exist in production
echo "Step 1: Removing modules not in production..."
echo "---------------------------------------------"

# Uninstall Group module (not used in prod)
echo "  - Uninstalling Group module (families/institutions)..."
drush pm:uninstall group flexible_permissions institutional_management -y 2>/dev/null || echo "    (already uninstalled or doesn't exist)"

# Uninstall Contact module (not used in prod, using webform instead)
echo "  - Uninstalling Contact module..."
drush pm:uninstall contact -y 2>/dev/null || echo "    (already uninstalled)"

# Uninstall forseti_safety_content (renamed to forseti_content)
echo "  - Uninstalling forseti_safety_content (old name)..."
drush pm:uninstall forseti_safety_content -y 2>/dev/null || echo "    (already uninstalled)"

echo "  ✓ Cleanup complete"
echo ""

# Step 2: Install critical production modules
echo "Step 2: Installing critical production modules..."
echo "-------------------------------------------------"

# Check if composer packages need to be installed
echo "  - Installing Composer packages (this may take a few minutes)..."

composer require \
  drupal/webform \
  drupal/social_api \
  drupal/social_auth \
  drupal/social_auth_google \
  drupal/metatag \
  drupal/google_tag \
  drupal/pathauto \
  drupal/token \
  drupal/admin_toolbar \
  drupal/twig_tweak \
  --no-update

echo "  - Running composer update..."
composer update --with-all-dependencies

echo "  ✓ Composer packages installed"
echo ""

# Step 3: Enable the modules
echo "Step 3: Enabling modules with Drush..."
echo "--------------------------------------"

drush pm:enable \
  webform \
  webform_ui \
  social_api \
  social_auth \
  social_auth_google \
  metatag \
  google_tag \
  pathauto \
  token \
  admin_toolbar \
  admin_toolbar_tools \
  twig_tweak \
  -y

echo "  ✓ Modules enabled"
echo ""

# Step 4: Clear cache
echo "Step 4: Clearing cache..."
echo "-------------------------"
drush cr
echo "  ✓ Cache cleared"
echo ""

# Step 5: Export current config (before sync)
echo "Step 5: Exporting current dev config (pre-sync baseline)..."
echo "----------------------------------------------------------"
drush config:export -y
echo "  ✓ Config exported"
echo ""

echo "================================================"
echo "✓ Pre-Sync Setup Complete!"
echo "================================================"
echo ""
echo "MODULES REMOVED:"
echo "  - group (not in production)"
echo "  - flexible_permissions (not in production)"
echo "  - institutional_management (not in production)"
echo "  - contact (not in production, using webform)"
echo "  - forseti_safety_content (renamed to forseti_content)"
echo ""
echo "MODULES INSTALLED:"
echo "  - webform + webform_ui (forms system)"
echo "  - social_auth + social_auth_google (Google login)"
echo "  - metatag (SEO)"
echo "  - google_tag (Analytics)"
echo "  - pathauto (URL aliases)"
echo "  - token (token system)"
echo "  - admin_toolbar + admin_toolbar_tools (better admin UI)"
echo "  - twig_tweak (theme helper)"
echo ""
echo "NEXT STEPS:"
echo "1. Review any error messages above"
echo "2. Test that dev site still works:"
echo "   cd /home/keithaumiller/forseti.life/sites/forseti"
echo "   drush status"
echo "   Visit dev site in browser"
echo ""
echo "3. When ready, run config sync:"
echo "   cd /home/keithaumiller/forseti.life/script/production"
echo "   ./reconcile-config.sh ../../prod-config ../../sites/forseti/config/sync"
echo "   Choose: use-prod"
echo ""
echo "4. After sync, test dev site again"
echo "5. Commit synced config to git"
echo ""
