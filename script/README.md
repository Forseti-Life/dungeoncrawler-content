# Multi-Site Development Environment Setup Scripts

**Last Updated:** February 6, 2026

This directory contains scripts and documentation for setting up the multi-site Drupal development environment supporting both St. Louis Integration and Theory of Conspiracies websites, including the **Complete H3 Geolocation Pipeline** with **100% Silver Layer Processing Achievement** and **Production-Ready Database Exports**.

## 🎯 Processing Achievement Status

**MAJOR MILESTONE ACHIEVED**: 100% H3 Silver Layer Processing Completion

### Current Status
- **H3 Coverage**: 100% of crime incident data processed through Bronze → Silver → Gold layers
- **Database Size**: 3,406,194 total records across all processing layers
- **Export System**: Production-ready 457MB compressed database exports available
- **Processing Pipeline**: Fully validated ObjectID-based transformation complete
- **Data Quality**: All records successfully geocoded and H3-indexed at optimal granularity levels

### Available Exports
- **Location**: `/h3-geolocation/database-exports/dumps/`
- **Files**: Structure + Data exports with compression and validation
- **Size**: 457MB compressed, ready for cloud storage backup
- **Documentation**: Complete restoration and deployment guides included

## Quick Start

### 🚀 After Workspace Restart (Fastest)

For existing setups after workspace restart:

```bash
./quick-start.sh
```

### 🔧 Complete Multi-Site Setup (First Time)

Run the complete setup script for full multi-site environment:

```bash
./complete-setup.sh
```

This comprehensive script will:
1. Install system dependencies (PHP 8.3, MySQL, Apache, etc.)
2. Configure multi-site directory structure (`/sites/stlouisintegration/` and `/sites/theoryofconspiracies/`)
3. Set up Apache virtual hosts on ports 80 and 8080
4. Create separate databases for each site
5. Install both Drupal 11 sites with development modules
6. Enable custom modules and theme on primary site
7. Configure development tools and coding standards

### ✅ Verification

Check that everything is working:

```bash
./verify-setup.sh
```

## Multi-Site Architecture

This workspace supports two independent Drupal websites:

### 🏢 St. Louis Integration (Primary Site)
- **URL**: http://localhost (port 80)
- **Directory**: `/workspaces/stlouisintegration.com/sites/stlouisintegration/`
- **Database**: `stlouisintegration_dev`
- **Features**: 5 custom modules + custom theme
- **Status**: Production-ready with all custom components

### 🕳️ Theory of Conspiracies (Secondary Site)
- **URL**: http://localhost:8080 (port 8080)
- **Directory**: `/workspaces/stlouisintegration.com/sites/theoryofconspiracies/`
- **Database**: `theoryofconspiracies_dev`
- **Features**: Fresh Drupal 11 with development modules + **H3:13 Granular Crime Analytics**
- **Status**: Production-ready with ultra-precision geospatial capabilities

## Available Scripts

### Core Setup Scripts
- **complete-setup.sh** - 🔧 **Complete multi-site environment setup** (use for first-time setup)
- **quick-start.sh** - 🚀 **Rapid startup** after workspace restarts (starts services, tests sites)
- **verify-setup.sh** - ✅ **Comprehensive verification** of entire multi-site setup, including OpenClaw runtime check when `verify-openclaw.sh` is present
- **setup.sh** - 🦞 **Primary full-environment installer** for Forseti + Dungeoncrawler; includes AWS CLI + OpenClaw CLI integration (installs `awscli` via apt and upgrades Node.js to 22.x when needed before installing `openclaw@2026.2.17`; if OpenClaw requirements still fail, logs guidance and continues)
- **verify-openclaw.sh** - 🧪 **OpenClaw runtime verification** (checks Node.js requirement, PATH, CLI execution, and global npm package state)
- **openclaw-chat.sh** - 💬 **One-command OpenClaw chat wrapper** for quick local agent prompts (`--agent`, `--session-id`, and `--json` supported)
- **openclaw-agentic-loop.sh** - 🔁 **Bounded agentic loop runner** for iterative goal execution with interval/max-iterations controls and log output
- **copilot/chat-loop.sh** - 💬 **Command-line Copilot chat loop** (wraps the `copilot` CLI, persists a session id under `~/.copilot/wrappers/`, supports `:new` / `:exit`)

### Issue Tracker Automation
- **import-open-issues-to-github.sh** - 🧾 Imports **Open** rows from `Issues.md` into GitHub using existing Drupal GitHub client services (`dungeoncrawler_tester.github_issue_pr_client`), attempts Copilot assignment (`@copilot`), and processes in creation batches (default: stop after 50 new issues per run).
  - Usage: `./import-open-issues-to-github.sh --repo keithaumiller/forseti.life`
  - Dry run: `./import-open-issues-to-github.sh --dry-run`
  - Batch size: `./import-open-issues-to-github.sh --batch-size 50`
  - Options: `--issues-file`, `--site-dir`, `--drush-bin`, `--repo`, `--sleep`, `--batch-size`

### Forseti Mobile App Scripts
- **setup-forseti-mobile-dev.sh** - 📱 **Complete Forseti Mobile development environment** (NEW - Recommended)
  - Installs all dependencies (React Native, ESLint, Prettier, Jest, TypeScript)
  - Sets up Android SDK (optional with --skip-android)
  - Configures web preview (optional with --skip-web)
  - Verifies all configuration files
  - Includes VS Code debugging, testing, and code quality tools
  - Usage: `./setup-forseti-mobile-dev.sh [--skip-android] [--skip-web] [--quick]`

### Legacy Mobile Scripts (Deprecated - Use setup-forseti-mobile-dev.sh instead)
- **setup-mobile.sh** - Original mobile app environment setup (points to old directory)
- **setup-mobile-web.sh** - Web preview setup only (functionality now in consolidated script)
- **setup-android-build.sh** - Android SDK setup only (functionality now in consolidated script)

### Legacy Scripts (archived/)
- **setup-environment.sh** - Install system dependencies only
- **install-drupal.sh** - Single-site Drupal installation
- **configure-development.sh** - Development tools setup
- **database-setup.sql** - Manual database commands

### Database Scripts
- **database/setup_consolidated.sh** - 🗄️ **Unified database setup** (ObjectID-based processing)
- **database/DATABASE_CONSOLIDATION.md** - Database consolidation documentation
- **database/legacy/** - Archived legacy setup scripts

### Documentation
- **requirements.md** - System requirements and dependencies
- **troubleshooting.md** - Common issues and solutions

## Working with Individual Sites

### St. Louis Integration Site
```bash
cd /workspaces/stlouisintegration.com/sites/stlouisintegration
./vendor/bin/drush status
./vendor/bin/drush cr  # Clear cache
./vendor/bin/drush uli  # One-time login link
```

### Theory of Conspiracies Site  
```bash
cd /workspaces/stlouisintegration.com/sites/theoryofconspiracies
./vendor/bin/drush status
./vendor/bin/drush cr  # Clear cache
./vendor/bin/drush uli  # One-time login link
```

### AmISafe Mobile Application

**Setup and Testing:**
```bash
# Complete mobile environment setup
./setup-mobile.sh

# After setup, test the application
cd /workspaces/stlouisintegration.com/amisafe-mobile

# Test H3 geospatial functions
node test-h3.js

# Test authentication
npm test

# Open web previews
# - web-test.html: Interactive authentication testing
# - crime-map-demo.html: Crime map visualization
# - demo-preview.html: Feature preview
```

**Current Status:**
- ✅ React Native 0.72.6 with TypeScript support
- ✅ 651 packages installed (H3-js, Axios, Geolocation, Maps)
- ✅ Web-based testing environment ready
- ⚠️ Native platforms (android/ios) not initialized yet
- ✅ H3 geospatial integration working (43m² precision)
- ✅ API integration configured for stlouisintegration.com

## Configuration

### Default Configuration
The setup scripts use these default values:

**Databases:**
- `stlouisintegration_dev` - Primary site database
- `theoryofconspiracies_dev` - Secondary site database

**Database User:**
- Username: `drupal_user`
- Password: `your_db_password`
- Host: `127.0.0.1`

**Admin Accounts (Both Sites):**
- Username: `admin`
- Password: `your_admin_password`
- Email: `support@forseti.life`

### Custom Configuration (.env file)
Create a `.env` file in the project root to override defaults:

```bash
# Database Configuration
DB_USER=your_db_user
DB_PASSWORD=your_secure_password
DB_HOST=127.0.0.1

# Admin Configuration  
ADMIN_USER=your_admin_user
ADMIN_PASSWORD=your_admin_password

# Site Configuration
SITE_NAME="Your Site Name"
```

## Troubleshooting

### Common Issues After Workspace Restart

**Sites not accessible:**
```bash
./quick-start.sh  # Restart services and verify
```

**Apache configuration errors:**
```bash
sudo apache2ctl configtest  # Check configuration
sudo service apache2 reload  # Reload config
```

**Database connection issues:**
```bash
sudo service mysql start  # Start MySQL
mysql -u drupal_user -p  # Test connection
```

**Drush not working:**
```bash
cd /path/to/site
composer install  # Reinstall dependencies
```

### Getting Help

1. **Run verification**: `./verify-setup.sh` - Comprehensive system check
2. **Check logs**: Apache logs separated by site in `/var/log/apache2/`
3. **Review documentation**: See `../MULTI_SITE_SETUP.md` for detailed guidance
4. **Fresh setup**: Run `./complete-setup.sh` if issues persist

## Development Workflow

### Adding Custom Modules/Themes

**For St. Louis Integration:**
- Modules: `/sites/stlouisintegration/web/modules/custom/`
- Themes: `/sites/stlouisintegration/web/themes/custom/`

**For Theory of Conspiracies:**
- Modules: `/sites/theoryofconspiracies/web/modules/custom/`
- Themes: `/sites/theoryofconspiracies/web/themes/custom/`

### Database Management

Each site maintains separate databases and can be backed up independently:

```bash
# Backup primary site
cd /workspaces/stlouisintegration.com/sites/stlouisintegration
./vendor/bin/drush sql:dump > backup_$(date +%Y%m%d).sql

# Backup secondary site
cd /workspaces/stlouisintegration.com/sites/theoryofconspiracies  
./vendor/bin/drush sql:dump > backup_$(date +%Y%m%d).sql
```

## Production Deployment

The deployment workflow is handled by `.github/workflows/deploy.yml` which automatically deploys to the production server when changes are pushed to the main branch.

**Note:** Each site can be deployed independently to different production environments if needed.