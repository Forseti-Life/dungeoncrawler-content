# Contributing Guide

Welcome! We appreciate contributions to the Resume Tailoring module.

## How to Contribute

### 1. Report Issues

Found a bug? Please report it:
- Visit: https://www.drupal.org/project/resume_tailoring/issues
- Include: Description, steps to reproduce, expected vs actual behavior
- Add: PHP/Drupal version, module version

### 2. Suggest Features

Have an idea? We'd love to hear it:
- Open an issue with "feature request" tag
- Describe the use case and benefits
- Include relevant examples

### 3. Contribute Code

- Fork the repository
- Create a feature branch: `git checkout -b feature/my-feature`
- Follow PSR-12 code standards
- Add tests for new functionality
- Submit a pull request

### Code Standards

```bash
# Run linting
./vendor/bin/phpcs modules/custom/resume_tailoring

# Run tests
./vendor/bin/phpunit modules/custom/resume_tailoring/tests/

# Run code analysis
./vendor/bin/phpstan analyse modules/custom/resume_tailoring
```

## Development Setup

```bash
# Clone and install dependencies
composer install

# Enable development modules
./vendor/bin/drush module:install devel twig_xdebug

# Run local server
./vendor/bin/drush runserver localhost:8000
```

## Pull Request Process

1. Update documentation
2. Add/update tests
3. Ensure all tests pass
4. Submit PR with clear description
5. Respond to review feedback

## Coding Guidelines

- Follow PSR-12 PHP standards
- Use type hints for all parameters
- Document public methods
- Keep functions focused and testable
- Write meaningful commit messages

## License

By contributing, you agree your code is licensed under GNU GPL v2.0 or later.
