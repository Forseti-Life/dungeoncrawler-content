# Job Application Automation Module

Automated job application workflow and tracking system for Drupal.

## Overview

The Job Application Automation module streamlines the job application process by:

- Automating form filling for common job board platforms
- Tracking application status across multiple sources
- Recording application history and outcomes
- Generating follow-up reminders
- Analyzing application success rates
- Exporting application records

## Installation

### Requirements

- Drupal 10.3+
- PHP 8.1+

### Steps

1. **Place the module** in your Drupal modules directory:
   ```bash
   cp -r job_application_automation {YOUR_DRUPAL_ROOT}/modules/custom/
   ```

2. **Enable the module**:
   ```bash
   cd {YOUR_DRUPAL_ROOT}
   ./vendor/bin/drush module:install job_application_automation
   ```

3. **Clear caches**:
   ```bash
   ./vendor/bin/drush cache:rebuild
   ```

## Quick Start

1. Navigate to **Manage > Content > Job Applications**
2. Create a new job application record
3. Link it to the resume you want to use
4. Track the status through the workflow

## Features

- **Application Tracking**: Monitor all applications in one place
- **Status Workflow**: Custom workflow states (applied, interview, rejected, offer)
- **Automated Reminders**: Get notified for follow-ups
- **Success Analytics**: Track conversion rates and application metrics
- **Bulk Operations**: Process multiple applications efficiently
- **Export Reports**: Download application history and analytics
- **Integration**: Connect with job boards and email services

## Configuration

Edit configuration at **Manage > Configuration > Job Application Settings**:

- Default workflow states
- Notification preferences
- Export formats
- Integration credentials

## Usage Examples

### Tracking a Single Application

```
1. Go to Job Applications
2. Create new application
3. Enter company name, position, date applied
4. Select resume used
5. Track status through workflow
```

### Bulk Import from CSV

```
1. Go to Applications > Import
2. Upload CSV file
3. Map columns to fields
4. Review and confirm
5. Import applications
```

### Generate Reports

```
1. Go to Analytics
2. Select date range
3. Choose report type
4. View metrics and charts
5. Export as PDF or CSV
```

## Troubleshooting

**Issue**: Reminders not sending
- Verify cron is configured and running
- Check email settings
- Review notification preferences

**Issue**: Import fails
- Verify CSV format matches template
- Check file permissions
- Review validation errors

## Support

For issues, questions, or contributions:
- Visit: https://www.drupal.org/project/job_application_automation/issues
- Read: INSTALL.md
- Check: ARCHITECTURE.md

## License

This module is licensed under the GNU General Public License v2.0 or later.
