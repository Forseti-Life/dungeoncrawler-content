# Security Policy

## Reporting Security Issues

**Please DO NOT open public GitHub issues for security vulnerabilities.**

Instead, email security concerns to: security@example.com

Include:
- Description of vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

We aim to acknowledge reports within 48 hours and provide updates on remediation progress.

## Security Best Practices

### Access Control
- Always verify user permissions before data access
- Use Drupal's permission system for all features
- Implement row-level security for sensitive data

### Data Protection
- Never log sensitive user data
- Encrypt location tracking data at rest
- Use HTTPS for all API communications
- Implement rate limiting on API endpoints

### Code Security
- Sanitize all user input: Use Drupal's form API
- Validate all API parameters
- Use parameterized queries (don't build SQL strings)
- Keep dependencies updated: `composer update`

### Logging
- Log access attempts to protected resources
- Log configuration changes
- Review logs regularly for suspicious activity
- Implement log retention policies (e.g., delete after 90 days)

## Known Limitations

- API endpoints do not support client certificates
- Location tracking data retention limited to 30 days
- High-resolution queries (13) may timeout with 100M+ records

## Supported Versions

Security fixes provided for:
- Latest minor version: Full support
- Previous minor version: Security fixes only
- Older versions: No support

Example: If 1.2.0 is latest:
- 1.2.x - Full support
- 1.1.x - Security fixes only
- 1.0.x - No support

## Drupal Core Security

This module follows Drupal's security update process. Check:
- drupal.org/security for Drupal core updates
- Composer dependencies for known vulnerabilities

## Regular Security Tasks

- **Weekly:** Review access logs
- **Monthly:** Update dependencies (`composer update`)
- **Quarterly:** Run OWASP ZAP security scan
- **Annually:** Conduct penetration testing

## Compliance

This module supports compliance with:
- GDPR (data protection)
- HIPAA (if used with health data)
- SOC 2 (with proper configuration)

Refer to [DATABASE_ARCHITECTURE.md](DATABASE_ARCHITECTURE.md) for data retention policies.
