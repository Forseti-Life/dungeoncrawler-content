#!/bin/bash
#
# Sync Drupal configuration from production to local development
# Run this on your LOCAL development machine
#
# Usage: ./sync-config-from-production.sh [site-name] [ssh-host]
# Example: ./sync-config-from-production.sh forseti root@your-server

set -e

SITE_NAME=${1:-forseti}
SSH_HOST=${2:-}
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SITE_PATH="${REPO_ROOT}/sites/${SITE_NAME}"

echo "🔄 Sync Config from Production"
echo "=============================="
echo ""
echo "Site: ${SITE_NAME}"
echo "Local path: ${SITE_PATH}"
echo ""

# Validate local site directory exists
if [ ! -d "${SITE_PATH}" ]; then
  echo "❌ Error: Local site directory ${SITE_PATH} does not exist"
  exit 1
fi

# Prompt for SSH host if not provided
if [ -z "${SSH_HOST}" ]; then
  echo "Enter production SSH host (e.g., root@your-server.com):"
  read -r SSH_HOST
  
  if [ -z "${SSH_HOST}" ]; then
    echo "❌ Error: SSH host is required"
    exit 1
  fi
fi

echo "Production host: ${SSH_HOST}"
echo ""

# Check if production scripts exist, if not provide instructions
echo "📡 Checking if export script exists on production..."
if ! ssh "${SSH_HOST}" "test -f /var/www/html/scripts/export-config.sh"; then
  echo "⚠️  Export script not found on production"
  echo ""
  echo "The production helper scripts need to be deployed first."
  echo "They should be automatically deployed with the next commit."
  echo ""
  echo "For now, running manual export..."
  
  # Manual export fallback
  TEMP_FILE="/tmp/${SITE_NAME}-config-manual-$(date +%Y%m%d-%H%M%S).tar.gz"
  
  echo "📦 Exporting config from production..."
  ssh "${SSH_HOST}" "cd /var/www/html/${SITE_NAME} && sudo -u www-data ./vendor/bin/drush config:export -y && sudo tar -czf /tmp/config-export.tar.gz config/sync/ && sudo chmod 644 /tmp/config-export.tar.gz"
  
  echo "⬇️  Downloading config..."
  scp "${SSH_HOST}:/tmp/config-export.tar.gz" "${TEMP_FILE}"
  
  echo "🧹 Cleaning up production temp file..."
  ssh "${SSH_HOST}" "sudo rm /tmp/config-export.tar.gz"
else
  # Use production script
  echo "✅ Export script found"
  echo ""
  echo "📦 Running export on production..."
  EXPORT_FILE=$(ssh "${SSH_HOST}" "cd /var/www/html/scripts && ./export-config.sh ${SITE_NAME} | grep 'Created:' | awk '{print \$3}'")
  
  echo "⬇️  Downloading config..."
  TEMP_FILE="/tmp/${SITE_NAME}-config-$(date +%Y%m%d-%H%M%S).tar.gz"
  scp "${SSH_HOST}:${EXPORT_FILE}" "${TEMP_FILE}"
  
  echo "🧹 Cleaning up production export file..."
  ssh "${SSH_HOST}" "sudo rm ${EXPORT_FILE}"
fi

# Extract to local site directory
echo ""
echo "📂 Extracting config to local site..."
cd "${SITE_PATH}"
tar -xzf "${TEMP_FILE}"

# Check what changed
echo ""
echo "📊 Config changes:"
cd "${REPO_ROOT}"
git diff --stat sites/${SITE_NAME}/config/sync/ || echo "No changes detected"

echo ""
echo "🎉 Sync complete!"
echo ""
echo "Next steps:"
echo "  cd ${REPO_ROOT}"
echo "  git status"
echo "  git diff sites/${SITE_NAME}/config/sync/"
echo "  git add sites/${SITE_NAME}/config/sync/"
echo "  git commit -m \"Sync config from production $(date +%Y-%m-%d)\""
echo "  git push"
echo ""
echo "⚠️  Review the changes carefully before committing!"

# Clean up temp file
rm "${TEMP_FILE}"
