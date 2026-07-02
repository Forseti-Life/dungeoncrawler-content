# Resume Tailoring Module

Professional resume customization and optimization for job applications.

## Overview

The Resume Tailoring module provides a comprehensive system for managing and customizing resumes for specific job applications. It enables users to:

- Create and manage multiple resume versions
- Tailor resumes to specific job descriptions
- Track resume usage across applications
- Maintain version history and backups
- Generate formatted outputs (PDF, DOCX, etc.)

## Installation

### Requirements

- Drupal 10.3+
- PHP 8.1+

### Steps

1. **Place the module** in your Drupal modules directory:
   ```bash
   cp -r resume_tailoring {YOUR_DRUPAL_ROOT}/modules/custom/
   ```

2. **Enable the module**:
   ```bash
   cd {YOUR_DRUPAL_ROOT}
   ./vendor/bin/drush module:install resume_tailoring
   ```

3. **Import configuration** (if any):
   ```bash
   ./vendor/bin/drush config:import
   ```

4. **Clear caches**:
   ```bash
   ./vendor/bin/drush cache:rebuild
   ```

## Quick Start

1. Navigate to **Manage > Content > Resume Tailoring**
2. Create a new resume version
3. Upload or paste your base resume content
4. Use the tailoring interface to customize for specific roles

## Features

- **Multi-version Management**: Keep different resume versions for various industries/roles
- **AI-Assisted Tailoring**: Get suggestions for optimizing content
- **Job Description Matching**: Align resume keywords with job postings
- **Export Options**: Download in multiple formats
- **Usage Tracking**: Monitor which resumes were used for which applications
- **Audit Trail**: View all changes and modifications

## Configuration

Edit configuration at **Manage > Configuration > Resume Tailoring Settings**:

- Default export format (PDF/DOCX)
- Maximum resume versions to retain
- Custom sections and fields
- Notification preferences

## Usage Examples

### Creating a Tailored Resume

```
1. Go to Resume Tailoring dashboard
2. Select your base resume
3. Copy to create a new version
4. Use the tailoring wizard to customize
5. Export the tailored version
```

### Tracking Applications

```
1. Link tailored resumes to applications
2. View success rates by resume version
3. Identify most effective keywords
```

## Development

### File Structure

```
resume_tailoring/
├── src/
│   ├── Controller/        # Route controllers
│   ├── Form/              # Configuration forms
│   ├── Service/           # Business logic
│   └── Entity/            # Custom entities
├── templates/             # Twig templates
├── css/                   # Stylesheets
├── js/                    # JavaScript
├── config/                # Configuration templates
└── tests/                 # PHPUnit tests
```

### Running Tests

```bash
cd {YOUR_DRUPAL_ROOT}
./vendor/bin/phpunit modules/custom/resume_tailoring/tests/
```

## Troubleshooting

**Issue**: Resume export fails
- Check PHP memory limit (2GB minimum)
- Verify file permissions in export directory
- Clear Drupal cache

**Issue**: Tailoring suggestions not appearing
- Verify API credentials are set
- Check network connectivity
- Review module logs

## Support

For issues, questions, or contributions:
- Visit: https://www.drupal.org/project/resume_tailoring/issues
- Read: MODULE_INSTALL_GUIDE.md
- Check: ARCHITECTURE.md

## License

This module is licensed under the GNU General Public License v2.0 or later.
