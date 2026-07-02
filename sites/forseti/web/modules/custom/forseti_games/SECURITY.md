# Security Policy

## Reporting Security Vulnerabilities

If you discover a security vulnerability, email security@example.com with:
- Vulnerability type
- Code location
- Potential impact
- Reproduction steps

We respond within 48 hours and keep reports confidential.

## Security Practices

### User Input

All input sanitized using Drupal functions:
- `Html::escape()` for XSS prevention
- `Xss::filter()` for safe HTML filtering
- `UrlHelper::filterBadProtocol()` for URL validation

### Database Queries

Parameterized queries prevent SQL injection:

```php
$connection->select('forseti_games_high_scores')
  ->condition('user_id', $user_id)
  ->execute();
```

### CSRF Protection

All state-changing requests require valid CSRF tokens.

### Permissions

All endpoints check user permissions before execution.

### Data Protection

- High scores include user ID and score only
- No sensitive data stored
- Available to authenticated users only

## Dependencies

- Drupal core only
- No external CDNs
- Self-hosted assets

Monitor Drupal security: https://www.drupal.org/security

## Testing

- Code review
- Automated testing
- Dependency scanning

## Known Limitations

- Client-side games (no anti-cheat)
- No rate limiting on score submission
- No encryption at rest

## Response Timeline

1. Report received (24 hours)
2. Validation (48 hours)
3. Fix developed (2 weeks)
4. Testing and release
5. Credit offered (optional)

## Contact

Email: security@example.com
