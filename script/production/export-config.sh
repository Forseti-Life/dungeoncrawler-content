#!/bin/bash
#
# Export Drupal configuration with environment labeling
# Can be run on production server or local development
#
# Usage: ./export-config.sh [site-name] [environment]
# Example: ./export-config.sh forseti production
# Example: ./export-config.sh forseti development

set -e

SITE_NAME=${1:-forseti}
ENVIRONMENT=${2:-production}
SITE_ROOT="/var/www/html/${SITE_NAME}"
EXPORT_FILE="/tmp/${SITE_NAME}-config-${ENVIRONMENT}-$(date +%Y%m%d-%H%M%S).tar.gz"

echo "🔧 Drupal Config Export Tool"
echo "============================"
echo ""
echo "Site: ${SITE_NAME}"
echo "Environment: ${ENVIRONMENT}"
echo "Root: ${SITE_ROOT}"
echo ""

# Validate site exists
if [ ! -d "${SITE_ROOT}" ]; then
  echo "❌ Error: Site root ${SITE_ROOT} does not exist"
  exit 1
fi

if [ ! -f "${SITE_ROOT}/vendor/bin/drush" ]; then
  echo "❌ Error: Drush not found at ${SITE_ROOT}/vendor/bin/drush"
  exit 1
fi

# Export configuration
echo "📦 Exporting configuration..."
cd "${SITE_ROOT}"
sudo -u www-data ./vendor/bin/drush config:export -y

# Verify export succeeded
if [ ! -d "${SITE_ROOT}/config/sync" ]; then
  echo "❌ Error: Config export failed - config/sync directory not found"
  exit 1
fi

FILE_COUNT=$(ls -1 "${SITE_ROOT}/config/sync" | wc -l)
echo "✅ Exported ${FILE_COUNT} configuration files"

# Create tarball
echo ""
echo "📦 Creating tarball..."
sudo tar -czf "${EXPORT_FILE}" -C "${SITE_ROOT}" config/sync/

TARBALL_SIZE=$(du -h "${EXPORT_FILE}" | cut -f1)
echo "✅ Created: ${EXPORT_FILE} (${TARBALL_SIZE})"

# Set permissions so it can be downloaded
sudo chmod 644 "${EXPORT_FILE}"

# Create metadata file
METADATA_FILE="${EXPORT_FILE}.meta"
cat > "${METADATA_FILE}" <<EOF
site: ${SITE_NAME}
environment: ${ENVIRONMENT}
timestamp: $(date -Iseconds)
file_count: ${FILE_COUNT}
export_file: ${EXPORT_FILE}
EOF
sudo chmod 644 "${METADATA_FILE}"

echo ""
echo "🎉 Export complete!"
echo ""
echo "Export labeled as: ${ENVIRONMENT}"
echo "Metadata: ${METADATA_FILE}"
echo ""
echo "To compare with other environment, use compare-config.sh"
