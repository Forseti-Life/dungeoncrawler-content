#!/bin/bash
#
# Reconcile configuration differences between dev and prod
# Run this on your LOCAL development machine after running compare-config.sh
#
# Usage: ./reconcile-config.sh [site-name] [ssh-host] [strategy]
# Strategies: use-prod, use-dev, selective
#
# Example: ./reconcile-config.sh forseti root@your-server use-prod

set -e

SITE_NAME=${1:-forseti}
SSH_HOST=${2:-}
STRATEGY=${3:-}
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SITE_PATH="${REPO_ROOT}/sites/${SITE_NAME}"
BACKUP_DIR="/tmp/config-backup-${SITE_NAME}-$(date +%Y%m%d-%H%M%S)"

echo "⚙️  Config Reconciliation Tool"
echo "=============================="
echo ""

# Validate inputs
if [ -z "${SSH_HOST}" ]; then
  echo "❌ Error: SSH host required"
  echo "Usage: $0 [site-name] [ssh-host] [strategy]"
  exit 1
fi

if [ -z "${STRATEGY}" ]; then
  echo "Select reconciliation strategy:"
  echo "  1) use-prod    - Replace dev config with production (recommended)"
  echo "  2) use-dev     - Replace prod config with development (dangerous!)"
  echo "  3) selective   - Review each difference interactively"
  echo ""
  echo -n "Choice (1-3): "
  read -r choice
  
  case $choice in
    1) STRATEGY="use-prod" ;;
    2) STRATEGY="use-dev" ;;
    3) STRATEGY="selective" ;;
    *) echo "❌ Invalid choice"; exit 1 ;;
  esac
fi

echo "Site: ${SITE_NAME}"
echo "Strategy: ${STRATEGY}"
echo ""

# Backup current dev config
echo "💾 Backing up current development config..."
mkdir -p "${BACKUP_DIR}"
cp -r "${SITE_PATH}/config/sync" "${BACKUP_DIR}/"
echo "✅ Backup saved: ${BACKUP_DIR}"
echo ""

case "${STRATEGY}" in
  use-prod)
    echo "📥 Fetching production config..."
    TEMP_FILE="/tmp/${SITE_NAME}-prod-config.tar.gz"
    
    ssh "${SSH_HOST}" "cd /var/www/html/${SITE_NAME} && sudo -u www-data ./vendor/bin/drush config:export -y && sudo tar -czf /tmp/prod-config-sync.tar.gz config/sync/ && sudo chmod 644 /tmp/prod-config-sync.tar.gz"
    scp "${SSH_HOST}:/tmp/prod-config-sync.tar.gz" "${TEMP_FILE}"
    ssh "${SSH_HOST}" "sudo rm /tmp/prod-config-sync.tar.gz"
    
    echo "📂 Replacing dev config with production..."
    rm -rf "${SITE_PATH}/config/sync"
    mkdir -p "${SITE_PATH}/config/sync"
    tar -xzf "${TEMP_FILE}" -C "${SITE_PATH}" --strip-components=1
    rm "${TEMP_FILE}"
    
    echo ""
    echo "✅ Development config replaced with production config"
    echo ""
    echo "📊 Changes:"
    cd "${REPO_ROOT}"
    git diff --stat sites/${SITE_NAME}/config/sync/ || echo "No differences"
    echo ""
    echo "Next steps:"
    echo "  cd ${REPO_ROOT}"
    echo "  git diff sites/${SITE_NAME}/config/sync/"
    echo "  git add sites/${SITE_NAME}/config/sync/"
    echo "  git commit -m \"Sync config from production $(date +%Y-%m-%d)\""
    ;;
    
  use-dev)
    echo "⚠️  WARNING: This will replace PRODUCTION config with DEVELOPMENT config!"
    echo "This is dangerous and not recommended unless you know what you're doing."
    echo ""
    echo -n "Type 'YES' to confirm: "
    read -r confirm
    
    if [ "$confirm" != "YES" ]; then
      echo "❌ Cancelled"
      exit 1
    fi
    
    echo ""
    echo "📤 Exporting development config..."
    cd "${SITE_PATH}"
    ./vendor/bin/drush config:export -y
    
    echo "📦 Creating tarball..."
    TEMP_FILE="/tmp/${SITE_NAME}-dev-config.tar.gz"
    tar -czf "${TEMP_FILE}" -C "${SITE_PATH}" config/sync/
    
    echo "📤 Uploading to production..."
    scp "${TEMP_FILE}" "${SSH_HOST}:/tmp/dev-config-upload.tar.gz"
    
    echo "📂 Deploying to production..."
    ssh "${SSH_HOST}" "cd /var/www/html/${SITE_NAME} && sudo tar -xzf /tmp/dev-config-upload.tar.gz --strip-components=1 && sudo rm /tmp/dev-config-upload.tar.gz"
    
    rm "${TEMP_FILE}"
    
    echo ""
    echo "⚠️  Development config deployed to production"
    echo ""
    echo "You MUST now import it on production:"
    echo "  ssh ${SSH_HOST}"
    echo "  cd /var/www/html/${SITE_NAME}"
    echo "  sudo -u www-data ./vendor/bin/drush config:import -y"
    echo "  sudo -u www-data ./vendor/bin/drush cache:rebuild"
    ;;
    
  selective)
    echo "🔍 Interactive reconciliation not yet implemented"
    echo ""
    echo "For now, use:"
    echo "  1. Run compare-config.sh to see differences"
    echo "  2. Manually review: ${BACKUP_DIR}/../config-compare-*/"
    echo "  3. Manually copy files you want to keep"
    echo "  4. Use 'use-prod' or 'use-dev' strategy"
    ;;
    
  *)
    echo "❌ Invalid strategy: ${STRATEGY}"
    echo "Valid strategies: use-prod, use-dev, selective"
    exit 1
    ;;
esac

echo ""
echo "💾 Original dev config backed up at:"
echo "   ${BACKUP_DIR}"
echo ""
echo "To restore backup if needed:"
echo "   cp -r ${BACKUP_DIR}/sync/* ${SITE_PATH}/config/sync/"
