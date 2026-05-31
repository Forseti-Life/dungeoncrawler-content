# GitHub Copilot Instructions for forseti.life

## Project Overview

This is a multi-site Drupal workspace featuring:
- **Primary Site**: forseti.life - Drupal 11+ site for safety and community services
- **Mobile App**: React Native 0.72.6 Android application
- **Additional Sites**: Multiple independent Drupal installations (stlouisintegration.com, theoryofconspiracies.com, thetruthperspective.org)
- **Infrastructure**: LAMP stack (Ubuntu 24.04, Apache 2.4.58+, MySQL 8.0+, PHP 8.3+) on AWS EC2

## Technology Stack

### Backend
- **Drupal 11+**: Content management system using Drupal-native patterns
- **PHP 8.3+**: Server-side scripting with mod_php
- **MySQL 8.0+**: Database with utf8mb4 character set
- **Composer**: PHP dependency management
- **Drush**: Drupal CLI tool (site-specific installations)

### Mobile Development
- **React Native 0.72.6**: Stable LTS version
- **Android Build Tools**: AGP 8.0.2, Gradle 8.0.1, Kotlin 1.8.22
- **Java 17**: Build environment

### Server & Deployment
- **Apache 2.4.58+**: Multi-site virtual host configuration
- **Git**: Version control
- **GitHub Actions**: Automated deployment workflows

## Development Guidelines

### Core Principles

1. **NO QUICK FIXES**: Always diagnose properly, understand root causes, and implement sustainable solutions
2. **NO BACKWARD COMPATIBILITY CONCERNS**: Break existing implementations when necessary to achieve proper architecture. Refactor aggressively, consolidate redundant systems, and prioritize clean design over preserving legacy patterns.
3. **Drupal-Native First**: Use Drupal's built-in features (nodes, fields, Views) before creating custom code
4. **Documentation Required**: Update README files after all changes
5. **Process Over Speed**: Understand systems thoroughly before implementing solutions

### AI Agent Execution Default (MANDATORY)

**Default mode for this repository is AGGRESSIVE AUTONOMOUS CONTINUATION.**

- Implement requested changes immediately without asking for permission.
- Minimize exploratory reads/searches to only what is needed to make safe edits.
- After completing a requested task, immediately start the next obvious, high-value step without waiting for additional user input.
- Continue advancing work end-to-end until blocked, complete, or explicitly stopped by the user.
- Run validation based on impact and risk; do not let routine checks block obvious forward progress.
- Report only changed files and a concise summary after implementation.
- If blocked, make one reasonable assumption and proceed; only ask a question when absolutely required to continue.

**Validation behavior:** if the user explicitly requests checks (for example, "run checks", "run tests", "lint", "build", "validate"), run them and report outcomes.

### Work Tracking and Status Policy

- Do not create new `Summary.md` files for work tracking.
- Do not create new `status.md` files for work tracking.
- Keep implementation progress, status updates, and completion notes in the corresponding GitHub Issue.
- Continue updating README/ARCHITECTURE documentation when implementation behavior or policy changes.

### Issues Tracker Mutation Policy (`Issues.md`)

- **Allowed writer paths**: Local Drupal/PHP automation in `dungeoncrawler_tester` (including import/reconcile flows) may read and mutate repository-root `Issues.md` when synchronizing confirmed GitHub issue state.
- **Required conversion behavior**: For open-row conversion, PHP automation must: identify Open rows in `Issues.md` → create/find matching open GitHub issue → confirm open state via GitHub API → remove matching local Open row(s).
- **Operational exception**: Manual human cleanup edits are allowed when explicitly requested.

### Drupal Development Standards

**Before Developing Modules**:
1. Read `ARCHITECTURE.md` in the relevant directory
2. Verify the implementation uses Drupal-native patterns
3. Check if existing Drupal functionality can be extended

### File Editing Workflow

**Before Editing**:
1. Read `README.md` in the target directory
2. Review existing file structure and dependencies
3. Understand the current state from documentation

**After Editing**:
1. Update `README.md` to reflect changes
2. Run `drush cr` after modifying:
   - CSS/SCSS files
   - Twig templates
   - Theme assets
   - Menu links
   - Routing definitions

### Mobile Development Standards

**React Native Configuration**:
- Use documented version combinations: AGP 8.0.2 + Gradle 8.0.1 + Kotlin 1.8.22
- Apply patches via patch-package for node_modules modifications
- Use automated setup script: `./script/setup-forseti-mobile-dev.sh`

**Build Commands**:
- Debug APK: `./gradlew assembleDebug`
- Release APK: `./gradlew assembleRelease`

## Project Structure

### Production Environment
```
/var/www/html/
├── forseti/              # forseti.life production
├── stlouisintegration/   # stlouisintegration.com
├── drupal/               # thetruthperspective.org
├── theoryofconspiracies/ # theoryofconspiracies.com
└── [other-sites]/        # Additional sites
```

### Development Environment
```
/home/keithaumiller/forseti.life/
├── sites/forseti/        # Drupal development site
├── forseti-mobile/       # React Native mobile app
├── script/               # Setup and utility scripts
└── docs/                 # Documentation hub
```

## Common Tasks

### Drupal Site Management

**Service Management**:
```bash
# Start/stop Apache
sudo systemctl start apache2
sudo systemctl stop apache2

# Check service status
sudo systemctl status apache2
```

**Database Operations**:
```bash
# Access MySQL
mysql -u drupal_user -p

# Development database: forseti_dev
# Production database: forseti_prod
```

**Drupal Commands** (from site root):
```bash
# Clear cache (REQUIRED after template/CSS/routing changes)
./vendor/bin/drush cr

# View recent logs
./vendor/bin/drush watchdog:show --count=10

# Check configuration
./vendor/bin/drush config:get system.logging

# Module management
./vendor/bin/drush pm:list
```

### Mobile Development

**Environment Setup**:
```bash
# Complete setup (first time)
./script/setup-forseti-mobile-dev.sh

# From forseti-mobile directory
cd forseti-mobile
npm install
```

**Building APKs**:
```bash
cd forseti-mobile/android
./gradlew clean assembleDebug
```

### Quick Start Scripts

```bash
# After workspace restart (fastest)
./script/quick-start.sh

# Complete multi-site setup (first time)
./script/setup.sh

# Verify setup
./script/verify-setup.sh
```

## Testing & Quality

### Testing Approach
- Test changes immediately after implementation
- Run site-specific tests before deployment
- Validate in development before production
- Check Apache and Drupal logs for errors

### Logging Locations

**Apache Logs** (site-specific):
- `/var/log/apache2/forseti_error.log`
- `/var/log/apache2/forseti_access.log`
- Pattern: `/var/log/apache2/{sitename}_error.log`

**Drupal Logs**:
- Database watchdog table (dblog module enabled)
- View via Drush: `./vendor/bin/drush watchdog:show`

## Deployment

**Production Sites**:
- Deployed via GitHub Actions workflows
- Each site has independent Drupal installation
- Site-specific configurations in virtual hosts

**Deployment Process**:
1. Commit changes to Git
2. Push to GitHub
3. GitHub Actions handles deployment
4. Verify via site-specific logs

## Key Documentation References

### Comprehensive Guidelines
- `.github/instructions/instructions.md` - Complete development policies and technical specifications (798 lines)
- `.github/instructions/README.md` - Instructions directory overview and usage guide

### Project Documentation
- `/docs/` - Documentation hub with comprehensive guides
- `sites/forseti/ARCHITECTURE.md` - Drupal architecture details
- `forseti-mobile/README.md` - React Native mobile app documentation

### Setup & Scripts
- `./script/README.md` - Setup scripts documentation
- `./script/setup-forseti-mobile-dev.sh` - Automated mobile dev environment

## Important Notes

1. **Multi-Site Architecture**: Each site (forseti, stlouisintegration, etc.) is independent with its own:
   - Drupal installation
   - Database
   - Apache virtual host
   - Error logs

2. **Context Requirements**: For comprehensive AI development sessions, read `.github/instructions/instructions.md` for detailed policies and technical specifications

3. **Cache Management**: Always run `drush cr` after changes to templates, CSS, or routing to make changes visible

4. **Version Control**: Working configurations are documented; refer to instructions.md for compatibility matrices

5. **Security**: Never commit secrets, credentials, or sensitive configuration to Git

## Getting Help

- Check README files in relevant directories
- Review ARCHITECTURE.md for module development
- Consult `.github/instructions/instructions.md` for detailed technical specifications
- Check Apache and Drupal logs for error diagnosis
