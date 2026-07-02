# Contributing Guide

## How to Contribute

### Report Issues

- Visit: https://www.drupal.org/project/job_application_automation/issues
- Include reproduction steps
- Add environment details

### Contribute Code

- Follow PSR-12 standards
- Add tests for new features
- Update documentation
- Submit pull request

### Testing

```bash
./vendor/bin/phpunit modules/custom/job_application_automation/tests/
./vendor/bin/phpcs modules/custom/job_application_automation/src/
```

## Development Setup

```bash
composer install
./vendor/bin/drush module:install job_application_automation devel
./vendor/bin/drush runserver localhost:8000
```

## License

Code contributed is licensed under GNU GPL v2.0 or later.
