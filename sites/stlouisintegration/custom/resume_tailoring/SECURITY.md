# Security Policy

## Reporting Security Issues

**Do not** open public issues for security vulnerabilities.

Email security concerns to: security@example.com

Include:
- Description of vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if available)

## Security Best Practices

### Input Validation

All user inputs are validated through Drupal Forms API:
- Text fields: sanitized via `Html::escape()`
- File uploads: MIME type checked and renamed
- AJAX calls: CSRF token required

### Output Sanitization

- Twig templates: autoescaped by default
- User content: filtered through text formats
- API responses: JSON escaped

### File Security

- Uploads stored outside web root
- Sensitive files not committed to repository
- .env files ignored from version control

### Configuration

```php
// Secure settings
$settings['hash_salt'] = 'unique random value';
$settings['trusted_host_patterns'] = ['^example\.com$'];
```

## Dependency Updates

Module dependencies checked monthly for vulnerabilities via:
- Drupal security advisories
- Composer/Packagist vulnerability scanner
- GitHub Dependabot

## Known Vulnerabilities

None currently. See: https://www.drupal.org/project/resume_tailoring/issues
