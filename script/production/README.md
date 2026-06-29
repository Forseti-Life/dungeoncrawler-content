# Production Helper Scripts

This directory contains scripts for managing Drupal configuration between development and production environments.

## Scripts

### export-config.sh

Exports Drupal configuration with environment labeling (dev or prod).

**Usage (on production OR development):**
```bash
# On production server:
cd /var/www/html/scripts
./export-config.sh forseti production

# On development machine:
cd ~/forseti.life/script/production
./export-config.sh forseti development
```

**Output:**
- Creates `/tmp/forseti-config-{environment}-YYYYMMDD-HHMMSS.tar.gz`
- Creates metadata file with export details
- Contains `config/sync/` directory from the site

### compare-config.sh

Compares configuration between development and production to identify differences. **Intelligently filters expected differences** using `.config-differences.yml`.

**Usage (on local dev machine):**
```bash
cd ~/forseti.life/script/production
./compare-config.sh forseti root@your-server

# For verbose output showing expected differences:
VERBOSE=1 ./compare-config.sh forseti root@your-server
```

**What it does:**
1. Exports config from both dev and prod
2. Loads expected differences from `sites/forseti/.config-differences.yml`
3. Compares files to find:
   - Files only in dev (expected vs unexpected)
   - Files only in prod (expected vs unexpected)
   - Files that exist in both but are different (expected vs unexpected)
4. Generates detailed diff report
5. Shows summary focusing on **unexpected differences only**
6. Preserves comparison files for manual review

**Output shows:**
- Clear distinction between expected and unexpected differences
- Only flags what needs attention
- Count of differences by category
- List of affected files
- Guidance on next steps

**Expected Differences System:**

The `.config-differences.yml` file documents intentional config differences between environments:

```yaml
expected_differences:
  # Example: Backup module only in production
  - pattern: "backup_migrate.*"
    reason: "Backup module only needed in production for scheduled backups"
    category: "module"
    environments:
      production: "installed"
      development: "not installed"
```

**Categories:**
- `module` - Different modules installed in each environment
- `performance` - Cache/performance settings differ
- `security` - Security-related configs (tracking, SEO)
- `debug` - Debugging/development tools
- `feature` - Environment-specific features

**When to update .config-differences.yml:**
1. Installing a production-only module (backup_migrate, monitoring)
2. Installing a dev-only module (devel, kint, webprofiler)
3. Intentionally setting different performance configs
4. Configuring environment-specific integrations (analytics, tracking)

### reconcile-config.sh

Reconciles configuration differences using various strategies.

**Usage (on local dev machine):**
```bash
cd ~/forseti.life/script/production
./reconcile-config.sh forseti root@your-server [strategy]
```

**Strategies:**

1. **use-prod** (Recommended) - Replace dev with production config
   - Safest option for syncing environments
   - Production is source of truth
   - Dev config is backed up first

2. **use-dev** (Dangerous) - Replace production with dev config
   - Requires explicit confirmation
   - Use only when intentionally deploying config changes
   - Must manually import after deployment

3. **selective** (Coming soon) - Interactive review
   - Select individual files to keep/replace
   - Not yet implemented

### sync-config-from-production.sh

Simple script to sync production config to dev (uses reconcile under the hood).

**Usage (on local dev machine):**
```bash
cd ~/forseti.life/script/production
./sync-config-from-production.sh forseti root@your-server
```

## Complete Workflows

### 1. Initial Setup - Sync Dev with Production

When starting fresh or dev config is out of sync:

```bash
cd ~/forseti.life/script/production

# Option A: Simple sync (recommended for first time)
./sync-config-from-production.sh forseti root@your-server

# Option B: Compare first, then reconcile
./compare-config.sh forseti root@your-server
# Review the differences
./reconcile-config.sh forseti root@your-server use-prod

# Commit the synced config
cd ~/forseti.life
git add sites/forseti/config/sync/
git commit -m "Sync config from production baseline"
git push
```

### 2. Regular Workflow - Making Config Changes

When you need to modify configuration:

```bash
# 1. Always start by syncing from production
./compare-config.sh forseti root@your-server
# If there are differences, reconcile first:
./reconcile-config.sh forseti root@your-server use-prod

# 2. Make your changes in development
cd ~/forseti.life/sites/forseti
drush config:set system.site name "New Site Name"
# ... make other changes via UI or drush ...

# 3. Export development changes
drush config:export -y

# 4. Review what changed
git diff config/sync/

# 5. Commit your changes
git add config/sync/
git commit -m "Update site configuration: describe changes"
git push

# 6. Manually deploy to production (when ready)
ssh root@your-server
cd /var/www/html/forseti
git pull origin main
sudo -u www-data ./vendor/bin/drush config:import -y
sudo -u www-data ./vendor/bin/drush cache:rebuild
```

### 3. Emergency - Production Config Drift

If production has changes that dev doesn't:

```bash
# 1. Compare to see what drifted
./compare-config.sh forseti root@your-server

# 2. Decide:
#    - If prod changes are wanted: use-prod strategy
#    - If prod changes are wrong: fix prod manually first

# 3. Sync production to dev
./reconcile-config.sh forseti root@your-server use-prod

# 4. Commit the updates
cd ~/forseti.life
git add sites/forseti/config/sync/
git commit -m "Sync production config drift $(date +%Y-%m-%d)"
git push
```

### 4. Comparing Without Syncing

To just see what's different without making changes:

```bash
# Run comparison
./compare-config.sh forseti root@your-server

# Review the detailed report
less /tmp/config-compare-forseti-*/diff-report.txt

# Manually inspect specific files
cd /tmp/config-compare-forseti-*/
diff -u prod/system.site.yml dev/system.site.yml

# Clean up when done
rm -rf /tmp/config-compare-forseti-*
```

### 5. Managing Expected Differences

When you install environment-specific modules or intentionally configure things differently:

**Add to .config-differences.yml:**

```bash
cd ~/forseti.life/sites/forseti
# Edit .config-differences.yml
vim .config-differences.yml
```

**Example additions:**

```yaml
expected_differences:
  # New production-only module
  - pattern: "stage_file_proxy.*"
    reason: "File proxy to use prod files in dev, only configured in dev"
    category: "module"
    environments:
      production: "not installed"
      development: "installed for convenience"
  
  # Different cache settings
  - pattern: "system.performance.yml"
    reason: "Aggressive caching in prod, disabled in dev for debugging"
    category: "performance"
    environments:
      production: "CSS/JS aggregation ON, page cache ON"
      development: "Aggregation OFF for easier debugging"
```

**After updating:**

```bash
# Commit the updated expectations
git add sites/forseti/.config-differences.yml
git commit -m "Document new expected config difference: [describe]"
git push

# Re-run comparison to verify
./compare-config.sh forseti root@your-server
# Should now show 0 unexpected differences
```

## Deployment

These scripts are automatically deployed to `/var/www/html/scripts/` on production by the GitHub Actions workflow.

## Important Rules

### ✅ DO:
- Always sync FROM production TO development before making changes
- Use `compare-config.sh` to understand differences
- Document expected differences in `.config-differences.yml`
- Commit config changes with descriptive messages
- Test config changes in development first
- Manually deploy config to production (never automatic)
- Update `.config-differences.yml` when installing environment-specific modules
- Review unexpected differences carefully before reconciling

### ❌ DON'T:
- Never blindly overwrite production config with dev config
- Don't skip the comparison step
- Don't commit config without reviewing changes
- Don't auto-deploy config in CI/CD (disabled for safety)
- Don't make config changes directly in production
- Don't ignore unexpected differences without investigation
- Don't forget to document new expected differences
Expected differences tracking**: `.config-differences.yml` documents normal variations
4. **Smart comparison**: Only flags unexpected differences
5. **Comparison required**: See differences before syncing
6. **Confirmation prompts**: Dangerous operations require explicit YES
7. **Auto-deploy disabled**: Config deployment removed from CI/CD

## Config Differences Tracking

The `.config-differences.yml` file is the **single source of truth** for what config differences are expected between environments.

### Structure:

```yaml
expected_differences:
  - pattern: "glob_pattern_or_filename"
    reason: "Human-readable explanation"
    category: "module|performance|security|debug|feature"
    environments:
      production: "description of prod state"
      development: "description of dev state"
    note: "Optional additional context"
```

### Common Patterns:

**Production-only modules:**
```yaml
- pattern: "backup_migrate.*"
  reason: "Scheduled backups only needed in production"
  category: "module"
```

**Development-only modules:**
```yaml
- pattern: "devel.*"
  reason: "Development and debugging tools"
  category: "debug"
```

**Performance differences:**
```yaml
- pattern: "system.performance.yml"
  reason: "Caching optimized for each environment"
  category: "performance"
```

**Tracking/Analytics:**
```yaml
- pattern: "google_tag.*"
  reason: "Analytics only track production traffic"
  category: "feature"
```

### Maintenance:

**When to add patterns:**
1. Installing/uninstalling environment-specific modules
2. Intentionally configuring different performance settings
3. Setting up environment-specific integrations
4. Any deliberate configuration divergence

**Review schedule:**
- Review during major module installations
- Review when compare shows many unexpected differences
- Review quarterly to ensure accuracy
- Remove patterns for deprecated differences
1. **Automatic backups**: Scripts backup config before replacing
2. **Environment labeling**: Exports tagged with dev/prod
3. **Comparison required**: See differences before syncing
4. **Confirmation prompts**: Dangerous operations require explicit YES
5. **Auto-deploy disabled**: Config deployment removed from CI/CD

## Troubleshooting

### "Config import failed" in production

```bash
# Check what would be imported
ssh root@your-server
cd /var/www/html/forseti
sudo -u www-data ./vendor/bin/drush config:import -y --preview=list

# Look at specific difference
sudo -u www-data ./vendor/bin/drush config:diff config_name

# Force import (if needed)
sudo -u www-data ./vendor/bin/drush config:import -y
```

### UUID mismatch errors

Already fixed! Dev and prod now share the same UUID. If this happens again:

```bash
# Get production UUID
ssh root@your-server
cd /var/www/html/forseti
sudo -u www-data ./vendor/bin/drush config:get system.site uuid

# Update dev config
cd ~/forseti.life/sites/forseti
# Edit config/sync/system.site.yml with production UUID
git commit -m "Fix UUID mismatch"
```

### Too many differences

If compare shows hundreds of differences, environments are too far apart:

```bash
# Nuclear option: completely replace dev with prod
./reconcile-config.sh forseti root@your-server use-prod
git add sites/forseti/config/sync/
git commit -m "Complete resync with production"
git push
```
