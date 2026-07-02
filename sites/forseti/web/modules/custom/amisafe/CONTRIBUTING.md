# Contributing to AmISafe

We welcome contributions! This document outlines guidelines for development, testing, and submitting changes.

## Getting Started

### Development Setup

1. Clone repository
2. Install dependencies: `composer install`
3. Set up local Drupal instance: `ddev start`
4. Enable module: `drush en amisafe`
5. Run tests: `composer test`

### Code Standards

**PHP Code Style:**
- Follow Drupal Coding Standards (PSR-12)
- Use PHPStan level 1: `composer phpstan`
- Format with PHPCBF: `composer fix`

**JavaScript:**
- ES6 syntax
- JSHint: `npm run lint:js`
- Prettier format: `npm run format`

**Testing:**
- Write tests for all new features
- Run: `composer test` (PHPUnit)
- Minimum 80% code coverage

## Development Workflow

### 1. Create Feature Branch
```bash
git checkout -b feature/your-feature-name
```

### 2. Make Changes
- Keep commits focused and well-documented
- Reference issues: "Fixes #123" in commit messages
- Follow conventional commits: `feat: description`

### 3. Test Locally
```bash
composer test              # Unit tests
npm run test:js           # JavaScript tests  
drush amisafe:test-api   # API tests
```

### 4. Submit Pull Request
- Write clear PR description
- Link related issues
- Request review from maintainers

## Areas for Contribution

### High Priority
- Performance optimization (database queries)
- Additional H3 resolution levels
- Mobile app improvements
- API endpoint enhancements
- Documentation improvements

### Great for First-Time Contributors
- Bug fixes (labeled `good-first-issue`)
- Documentation
- Tests
- UI improvements

## Testing Guidelines

### Unit Tests
Located in `tests/Unit/`, test individual classes/methods:
```bash
./vendor/bin/phpunit tests/Unit
```

### Functional Tests
Located in `tests/Functional/`, test integration:
```bash
./vendor/bin/phpunit tests/Functional
```

### API Tests
Test endpoints at `/amisafe/testing/api-tests`:
```bash
curl -H "Authorization: Bearer TOKEN" http://example.com/api/amisafe/aggregated?resolution=9
```

## Architecture Notes

**Key Classes:**
- `CrimeDataService` - Data retrieval and filtering
- `H3AggregatorService` - Hexagon aggregations
- `SpatialAnalyzerService` - Geographic analysis
- `ApiController` - API endpoints

**Extension Points:**
- Custom data sources: Implement `AnalyticsDataProviderInterface`
- Custom filters: Extend `DataFilterBase`
- Custom visualizations: Add theme plugins

See [ARCHITECTURE.md](ARCHITECTURE.md) for full API reference.

## Pull Request Process

1. Update documentation if needed
2. Add tests for new features
3. Ensure all tests pass locally
4. Update CHANGELOG.md
5. Submit PR with:
   - Clear title and description
   - Link to related issue
   - Screenshots if UI changes
   - Checklist of testing done

## Code of Conduct

See [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md)

## Questions?

- Check existing issues and discussions
- Ask in PR comments
- Contact maintainers

Thanks for contributing!
