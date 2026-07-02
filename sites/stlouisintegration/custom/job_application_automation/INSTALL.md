# Installation Guide: Job Application Automation Module

## Prerequisites

- Drupal 10.3 or 11
- PHP 8.1 or higher
- Composer

## System Requirements

### Minimum
- RAM: 256 MB
- Disk: 50 MB for module files
- Database: MySQL 5.7+ or PostgreSQL 10+

### Recommended
- RAM: 1 GB
- Disk: 500 MB (includes logs/exports)
- Database: MySQL 8.0+ or PostgreSQL 12+

## Installation Steps

### 1. Download and Place Module

```bash
cd {YOUR_DRUPAL_ROOT}
cp -r path/to/job_application_automation modules/custom/
```

### 2. Install via Drush

```bash
cd {YOUR_DRUPAL_ROOT}
./vendor/bin/drush module:install job_application_automation
```

### 3. Verify Installation

```bash
./vendor/bin/drush pm:list --filter=job_application
```

### 4. Post-Installation

```bash
# Clear caches
./vendor/bin/drush cache:rebuild

# Run database updates
./vendor/bin/drush updatedb
```

## Configuration

### Permissions

Navigate to **Admin > People > Permissions** and set:
- Create Job Application content
- Edit own Job Application content
- Delete own Job Application content
- Access Job Application reports

### Module Settings

Navigate to **Admin > Configuration > Job Application Automation**

Configure:
- Default workflow states
- Notification email settings
- Reminder schedule
- Export preferences

### Environment Variables

Create or update `.env` file:

```bash
JOB_APP_ENABLE_REMINDERS=true
JOB_APP_REMINDER_DAYS=7
JOB_APP_EXPORT_FORMAT=csv
```

## Troubleshooting

### Module Won't Enable

```bash
./vendor/bin/drush cache:clear
./vendor/bin/drush module:install job_application_automation
```

### Database Errors

```bash
./vendor/bin/drush updatedb -y
./vendor/bin/drush entity:updates -y
```

## Next Steps

1. Review ARCHITECTURE.md for system design
2. Check CONTRIBUTING.md for development guidelines
3. Read SECURITY.md for security considerations

## Support

Visit: https://www.drupal.org/project/job_application_automation/issues
