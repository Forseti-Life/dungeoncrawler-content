# Security Policy

## Reporting Vulnerabilities

**Do not** open public issues for security issues.

Email: security@example.com

Include:
- Description of vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if available)

## Security Practices

### Input Validation
- All user inputs validated via Drupal Forms API
- Game commands sanitized
- API responses validated

### API Integration
- API credentials stored in environment variables
- Rate limiting implemented
- API responses cached to minimize requests

### Database Security
- Parameterized queries
- User content escaped in templates
- Permissions enforced at entity level

### Configuration
```php
// Secure defaults
$settings['dungeoncrawler_ai_timeout'] = 30;
$settings['dungeoncrawler_max_creatures'] = 1000;
$settings['dungeoncrawler_enable_sandbox'] = TRUE;
```

## Dependency Management

- Drupal security updates checked monthly
- Composer dependencies scanned for vulnerabilities
- GitHub Dependabot alerts enabled

## Known Vulnerabilities

None currently. See: https://www.drupal.org/project/dungeoncrawler_content/issues
