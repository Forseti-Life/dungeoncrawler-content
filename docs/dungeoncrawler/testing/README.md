# Dungeon Crawler Testing Guide

Quick start guide for developers working with the Dungeon Crawler testing suite.

## Quick Links
- [Full Testing Strategy](../issues/issue-testing-strategy-design.md) - Complete design document
- [Test Fixtures](./fixtures/) - Example test data
- [Example Tests](./examples/) - Sample test implementations

## Getting Started

### Prerequisites
```bash
# Navigate to dungeon crawler site
cd sites/dungeoncrawler

# Install dependencies (includes testing tools)
composer install
```

### Running Tests

#### Run All Tests
```bash
cd sites/dungeoncrawler
./vendor/bin/phpunit --configuration web/core/phpunit.xml.dist
```

#### Run Specific Test Suites
```bash
# Unit tests only
./vendor/bin/phpunit --testsuite=unit

# Functional tests only
./vendor/bin/phpunit --testsuite=functional

# PF2e rules validation tests
./vendor/bin/phpunit --group=pf2e-rules

# Character creation tests
./vendor/bin/phpunit --group=character-creation
```

#### Run Single Test File
```bash
./vendor/bin/phpunit web/modules/custom/dungeoncrawler_content/tests/src/Unit/CharacterCalculatorTest.php
```

#### Run with Coverage Report
```bash
./vendor/bin/phpunit --coverage-html coverage --coverage-text
# Open coverage/index.html in browser
```

## Test Structure

### Directory Layout
```
sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/
└── tests/
    ├── src/
    │   ├── Unit/                    # Unit tests (no Drupal bootstrap)
    │   │   ├── Service/             # Service layer tests
    │   │   ├── PF2eRules/           # PF2e rules validation
    │   │   └── Traits/              # Reusable test traits
    │   ├── Kernel/                  # Integration tests (minimal Drupal)
    │   │   ├── Storage/             # Database integration
    │   │   └── Api/                 # API integration
    │   ├── Functional/              # Browser tests (full Drupal)
    │   │   ├── CharacterCreation/   # Character creation flows
    │   │   └── Dashboard/           # Dashboard functionality
    │   └── FunctionalJavascript/    # JavaScript interaction tests
    └── fixtures/                    # Test data files
        ├── characters/              # Character test data
        ├── schemas/                 # Schema test data
        └── pf2e_reference/          # PF2e reference data
```

## Writing Your First Test

### 1. Unit Test Example
```php
<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\dungeoncrawler_content\Service\CharacterCalculator;

/**
 * Tests for CharacterCalculator service.
 *
 * @group dungeoncrawler_content
 * @group character-creation
 */
class CharacterCalculatorTest extends UnitTestCase {

  /**
   * Tests HP calculation.
   */
  public function testCalculateHP(): void {
    $calculator = new CharacterCalculator();
    
    $characterData = [
      'class_hp' => 10,
      'abilities' => ['constitution' => 16],
      'ancestry_hp_bonus' => 0,
    ];
    
    $result = $calculator->calculateHP($characterData);
    
    $this->assertEquals(13, $result['total']);
  }
}
```

### 2. Functional Test Example
```php
<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests character creation workflow.
 *
 * @group dungeoncrawler_content
 */
class CharacterCreationTest extends BrowserTestBase {

  protected static $modules = ['dungeoncrawler_content'];
  
  protected $defaultTheme = 'stark';

  /**
   * Tests creating a new character.
   */
  public function testCreateCharacter(): void {
    // Create and login user
    $user = $this->drupalCreateUser(['create characters']);
    $this->drupalLogin($user);
    
    // Navigate to character creation
    $this->drupalGet('/character/create');
    $this->assertSession()->statusCodeEquals(200);
    
    // Fill form and submit
    $this->submitForm([
      'name' => 'Test Fighter',
      'ancestry' => 'human',
      'class' => 'fighter',
    ], 'Create Character');
    
    // Verify success
    $this->assertSession()->pageTextContains('Character created successfully');
  }
}
```

## Testing Best Practices

### DO
✅ Write tests for all new features  
✅ Test both success and failure cases  
✅ Use descriptive test names  
✅ Keep tests independent  
✅ Use test fixtures for data  
✅ Mock external dependencies  
✅ Run tests before committing  

### DON'T
❌ Skip writing tests  
❌ Write tests that depend on each other  
❌ Test implementation details  
❌ Use production data in tests  
❌ Commit commented-out tests  
❌ Ignore failing tests  
❌ Test third-party code  

## Test Data Fixtures

### Loading Fixtures
```php
use Drupal\dungeoncrawler_content\Tests\Traits\FixtureLoaderTrait;

class MyTest extends UnitTestCase {
  use FixtureLoaderTrait;
  
  public function testSomething(): void {
    // Load fixture data
    $fighter = $this->loadFixture('characters/level_1_fighter.json');
    
    // Use in test
    $hp = $calculator->calculateHP($fighter);
    $this->assertEquals($fighter['expected_hp'], $hp['total']);
  }
}
```

### Creating Fixtures
```json
{
  "name": "Test Fighter",
  "level": 1,
  "ancestry": "human",
  "class": "fighter",
  "ability_scores": {
    "strength": 18,
    "dexterity": 14,
    "constitution": 16,
    "intelligence": 10,
    "wisdom": 12,
    "charisma": 8
  },
  "expected_hp": 13,
  "expected_ac": 18
}
```

## CI/CD Integration

Tests run automatically on:
- Pull requests to any branch
- Pushes to `main` and `develop` branches

### Local Pre-commit Testing
```bash
# Run quick tests before commit
cd sites/dungeoncrawler
./vendor/bin/phpunit --testsuite=unit --stop-on-failure

# Run code style checks
./vendor/bin/phpcs --standard=Drupal web/modules/custom/
```

### CI Test Status
View test results on GitHub:
- Go to your pull request
- Check "Actions" tab
- Review test results

## Troubleshooting

### Tests Won't Run
```bash
# Clear cache
./vendor/bin/drush cache:rebuild

# Reinstall dependencies
composer install

# Check PHPUnit version
./vendor/bin/phpunit --version
```

### Database Errors
```bash
# Set test database in phpunit.xml
<env name="SIMPLETEST_DB" value="mysql://user:pass@localhost/test_db"/>
```

### Permission Errors
```bash
# Fix permissions
chmod -R 755 web/modules/custom/*/tests
```

### Slow Tests
```bash
# Run only fast tests
./vendor/bin/phpunit --testsuite=unit --exclude-group=slow

# Run tests in parallel (requires extension)
./vendor/bin/paratest --processes=4
```

## Common Test Patterns

### Testing Exceptions
```php
public function testInvalidDataThrowsException(): void {
  $this->expectException(InvalidCharacterDataException::class);
  $calculator->calculate(['invalid' => 'data']);
}
```

### Using Data Providers
```php
/**
 * @dataProvider abilityScoreProvider
 */
public function testAbilityModifier(int $score, int $expected): void {
  $this->assertEquals($expected, calculateModifier($score));
}

public function abilityScoreProvider(): array {
  return [
    'score 10' => [10, 0],
    'score 18' => [18, 4],
    'score 8' => [8, -1],
  ];
}
```

### Mocking Services
```php
public function testWithMockedService(): void {
  $mockService = $this->createMock(SchemaLoaderInterface::class);
  $mockService->method('load')->willReturn(['data' => 'test']);
  
  $calculator = new CharacterCalculator($mockService);
  // ... test with mock
}
```

## Performance Testing

### Benchmark Tests
```bash
# Run performance tests
./vendor/bin/phpunit --group=performance

# Generate performance report
php scripts/performance-benchmark.php
```

### Expected Performance
- Unit tests: < 1 second each
- Functional tests: < 10 seconds each
- Full test suite: < 5 minutes

## PF2e Rules Testing

### Reference Documentation
When writing PF2e rules tests, reference official documentation:
- [PF2e Core Rulebook](../reference%20documentation/)
- [Character Creation Process](../01-character-creation-process.md)
- [Combat Mechanics](../02-combat-encounter-mechanics.md)

### Rules Test Example
```php
/**
 * Tests ability boost rules per PF2e Core Rulebook p.20.
 *
 * @group pf2e-rules
 */
public function testAbilityBoostRules(): void {
  // Boost adds +2 under 18
  $this->assertEquals(12, $calculator->applyBoost(10));
  
  // Boost adds +1 at 18 or higher
  $this->assertEquals(19, $calculator->applyBoost(18));
}
```

## Getting Help

### Documentation
- [Full Testing Strategy](../issues/issue-testing-strategy-design.md)
- [Drupal PHPUnit Documentation](https://www.drupal.org/docs/testing)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)

### Asking Questions
1. Check existing test examples
2. Review documentation
3. Ask in team chat
4. Open a GitHub issue

## Contributing

### Adding New Tests
1. Create test file in appropriate directory
2. Follow naming conventions: `{Class}Test.php`
3. Add appropriate @group annotations
4. Include docblocks
5. Run tests locally
6. Submit pull request

### Test Review Checklist
- [ ] Tests cover new functionality
- [ ] Tests follow AAA pattern (Arrange, Act, Assert)
- [ ] Tests have descriptive names
- [ ] Tests are independent
- [ ] Code coverage meets thresholds
- [ ] Tests pass locally
- [ ] Tests pass in CI

---

**Happy Testing!** Remember: Good tests make confident developers. 🚀
