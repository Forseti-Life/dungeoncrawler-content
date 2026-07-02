# Installation Guide: Resume Tailoring Module

## Prerequisites

- Drupal 10.3 or 11 (core_version_requirement: ^10.3 || ^11)
- PHP 8.1 or higher
- Composer (for dependency management)

## System Requirements

### Minimum
- RAM: 512 MB
- Disk: 100 MB for module files
- Database: MySQL 5.7+ or PostgreSQL 10+

### Recommended
- RAM: 2 GB
- Disk: 1 GB (includes export storage)
- Database: MySQL 8.0+ or PostgreSQL 12+

## Installation Steps

### 1. Download and Place Module

```bash
cd {YOUR_DRUPAL_ROOT}

# Copy module to custom modules directory
cp -r path/to/resume_tailoring modules/custom/
```

### 2. Install via Drush

```bash
cd {YOUR_DRUPAL_ROOT}

# Install the module
./vendor/bin/drush module:install resume_tailoring

# Import default configuration
./vendor/bin/drush config:import
```

Or via UI:
1. Admin > Manage > Modules
2. Search for "Resume Tailoring"
3. Check the box and click Install

### 3. Verify Installation

```bash
# Check module status
./vendor/bin/drush pm:list --filter=resume

# Should show: resume_tailoring - Resume Tailoring (Enabled)
```

### 4. Post-Installation

```bash
# Clear all caches
./vendor/bin/drush cache:rebuild

# Run database updates (if any)
./vendor/bin/drush updatedb
```

## Configuration

### Permissions

Navigate to **Admin > People > Permissions** and set:
- Create Resume content
- Edit own Resume content
- Delete own Resume content
- Access Resume Tailoring reports

### Module Settings

Navigate to **Admin > Configuration > Resume Tailoring**

Set the following (optional):
- Default export format (PDF/DOCX)
- Storage location for exports
- Maximum resume versions
- API credentials (if using AI features)

### Environment Variables

Create or update `.env` file:

```bash
# Resume Tailoring Configuration
RESUME_TAILORING_EXPORT_FORMAT=pdf
RESUME_TAILORING_MAX_VERSIONS=5
RESUME_TAILORING_ENABLE_AI=false
```

## Dependency Management

The module has the following Drupal dependencies:
- drupal:field
- drupal:node
- drupal:taxonomy
- drupal:text
- drupal:menu_ui
- drupal:image
- drupal:link

All dependencies are included in core. No additional modules required.

## Troubleshooting

### Module Won't Enable

```bash
# Clear cache and try again
./vendor/bin/drush cache:clear
./vendor/bin/drush module:install resume_tailoring

# Check for errors
./vendor/bin/drush ws
```

### Permission Denied on Exports

```bash
# Fix file permissions
sudo chown -R www-data:www-data {YOUR_DRUPAL_ROOT}/sites/default/files

# Set proper permissions
chmod -R 755 {YOUR_DRUPAL_ROOT}/sites/default/files
```

### Database Errors

```bash
# Run database updates
./vendor/bin/drush updatedb -y

# Verify entity schema
./vendor/bin/drush entity:updates -y
```

## Uninstallation

If you need to remove the module:

```bash
cd {YOUR_DRUPAL_ROOT}

# Uninstall the module
./vendor/bin/drush module:uninstall resume_tailoring

# Clear cache
./vendor/bin/drush cache:rebuild
```

**Note**: This will remove all Resume Tailoring content and configuration from your site.

## Next Steps

1. Review ARCHITECTURE.md for system design
2. Check CONTRIBUTING.md for development guidelines
3. Read SECURITY.md for security considerations
4. Run the test suite: `./vendor/bin/phpunit modules/custom/resume_tailoring/tests/`

## Support

Visit: https://www.drupal.org/project/resume_tailoring/issues
