# Contributing Guide

## Development Setup

```bash
git clone <repo>
cd dungeoncrawler
composer install

# Enable development modules
./vendor/bin/drush module:install devel twig_xdebug

# Copy environment template
cp .env.example .env

# Set up test AI credentials (optional)
# export GEMINI_API_KEY=...

# Start dev server
./vendor/bin/drush runserver localhost:8000
```

## Code Standards

```bash
# Lint
./vendor/bin/phpcs src/ tests/ --standard=PSR12

# Static analysis
./vendor/bin/phpstan analyse src/

# Tests
./vendor/bin/phpunit tests/
```

## Testing

### Unit Tests

```bash
./vendor/bin/phpunit tests/Unit/
```

### Functional Tests (requires Drupal installation)

```bash
./vendor/bin/phpunit tests/Functional/
```

### Manual Testing

```
1. Create a campaign at /game
2. Generate a dungeon
3. Start playing
4. Test various actions
5. Verify creature generation
6. Check combat mechanics
```

## Contributing Process

1. Fork repository
2. Create feature branch: `git checkout -b feature/amazing-feature`
3. Write tests
4. Ensure all tests pass
5. Submit pull request

## Code Style

- PSR-12 for PHP
- 2-space indentation
- Type hints for all parameters
- PHPDoc for public methods
- Self-documenting code preferred over comments

## Commit Messages

```
Short summary (50 chars or less)

Longer description if needed, explaining:
- What changed
- Why it changed
- Any related issues
```

## Pull Request Process

1. Update documentation
2. Add/update tests
3. Ensure linters pass
4. Add changelog entry
5. Respond to review feedback

## Reporting Issues

- Title: Clear and concise
- Description: Steps to reproduce
- Environment: PHP version, Drupal version, OS
- Expected vs actual behavior
- Screenshots if UI-related

## Questions?

Open an issue on Drupal.org: https://www.drupal.org/project/dungeoncrawler_content/issues
