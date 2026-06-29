# Testing Strategy & Bug Prevention - Design Document

## Overview
Comprehensive testing strategy for the Dungeon Crawler module to ensure code quality, PF2e rules accuracy, and system reliability.

## Current State
- **Drupal Site**: Multi-site Drupal 11 installation
- **Custom Modules**: `dungeoncrawler_content`, `ai_conversation`
- **Testing Infrastructure**: Standard Drupal testing framework available (PHPUnit, Functional, FunctionalJavascript)
- **CI/CD**: GitHub Actions deployment workflow (`.github/workflows/deploy.yml`)
- **Problem**: No comprehensive testing strategy or test suite implemented

## Testing Philosophy

### Goals
1. **Prevent bugs** before they reach production
2. **Validate PF2e rules** accuracy in all game mechanics
3. **Ensure API reliability** for frontend interactions
4. **Maintain performance** standards under load
5. **Enable confident refactoring** through comprehensive coverage

### Testing Pyramid Strategy
```
         /\
        /  \  E2E Tests (5%)
       /────\
      /      \  Integration Tests (15%)
     /────────\
    /          \  Unit Tests (80%)
   /────────────\
```

## Testing Layers

### 1. Unit Tests (80% of test suite)

**Purpose**: Test individual methods, functions, and classes in isolation

**Scope**:
- Service methods (character calculators, validators)
- Helper functions (PF2e rules calculators)
- Data transformers and formatters
- Individual class methods

**Example Test Cases**:
```
dungeoncrawler_content module:
├── CharacterCalculator Service Tests
│   ├── testCalculateHP()
│   ├── testCalculateAbilityModifier()
│   ├── testCalculateProficiencyBonus()
│   └── testCalculateArmorClass()
├── SchemaLoader Service Tests
│   ├── testGetClassData()
│   ├── testGetAncestryData()
│   ├── testGetBackgroundData()
│   └── testValidateSchemaStructure()
├── Character Validator Tests
│   ├── testValidateAbilityScores()
│   ├── testValidateClassSelection()
│   └── testValidateLevelRequirements()
└── PF2e Rules Calculator Tests
    ├── testMultipleAttackPenalty()
    ├── testDegreeOfSuccess()
    └── testSpellSaveCalculation()
```

**Framework**: PHPUnit (Drupal standard)

**Location**: `sites/dungeoncrawler/web/modules/custom/{module}/tests/src/Unit/`

**Mock Strategy**:
- Mock external services (database, API calls)
- Use test fixtures for schema data
- No actual database operations

**Coverage Target**: 90% for service layer, 80% overall

### 2. Kernel/Integration Tests (15% of test suite)

**Purpose**: Test integration between services and Drupal systems

**Scope**:
- Service interactions with entity API
- Database queries and storage
- Configuration management
- Module integration points

**Example Test Cases**:
```
Integration Tests:
├── Character Storage Tests
│   ├── testCreateCharacter()
│   ├── testUpdateCharacter()
│   ├── testLoadCharacterData()
│   └── testDeleteCharacter()
├── Schema Integration Tests
│   ├── testSchemaLoadingFromFile()
│   └── testSchemaValidationWithDatabase()
├── Session Management Tests
│   ├── testCharacterCreationSession()
│   └── testMultiStepFormPersistence()
└── API Integration Tests
    ├── testCharacterAPIEndpoints()
    └── testDataFormatTransformation()
```

**Framework**: PHPUnit Kernel Tests (Drupal)

**Location**: `sites/dungeoncrawler/web/modules/custom/{module}/tests/src/Kernel/`

**Database**: Uses test database with minimal schema

**Coverage Target**: 70% of integration points

### 3. Functional Tests (15% of test suite)

**Purpose**: Test complete user workflows through the browser

**Scope**:
- User authentication flows
- Character creation wizard (all steps)
- Dashboard functionality
- Form submissions and validations

**Example Test Cases**:
```
Functional Tests:
├── Character Creation Workflow Tests
│   ├── testCompleteCharacterCreationWizard()
│   ├── testStepNavigation()
│   ├── testFormValidation()
│   └── testDataPersistenceAcrossSteps()
├── Dashboard Tests
│   ├── testCharacterListDisplay()
│   ├── testCharacterEditAccess()
│   └── testCharacterDeletion()
├── API Endpoint Tests
│   ├── testCharacterDataAPI()
│   ├── testSchemaDataAPI()
│   └── testErrorHandling()
└── Access Control Tests
    ├── testAnonymousUserAccess()
    ├── testAuthenticatedUserPermissions()
    └── testAdminPermissions()
```

**Framework**: BrowserTestBase (Drupal Functional)

**Location**: `sites/dungeoncrawler/web/modules/custom/{module}/tests/src/Functional/`

**Environment**: Simulated browser with full Drupal installation

**Coverage Target**: All critical user paths

### 4. Functional JavaScript Tests (5% of test suite)

**Purpose**: Test JavaScript interactions and dynamic UI

**Scope**:
- AJAX form interactions
- Dynamic UI updates
- Real-time validations
- Interactive map rendering (future)

**Example Test Cases**:
```
JavaScript Tests:
├── AJAX Character Creation Tests
│   ├── testDynamicAbilityScoreCalculation()
│   ├── testClassSelectionUpdate()
│   └── testLivePreviewUpdates()
├── Form Interaction Tests
│   ├── testDynamicFieldVisibility()
│   └── testAutoSaveFeature()
└── Future: Map Interaction Tests
    ├── testHexMapRendering()
    └── testCharacterMovement()
```

**Framework**: FunctionalJavascript (Drupal with Webdriver)

**Location**: `sites/dungeoncrawler/web/modules/custom/{module}/tests/src/FunctionalJavascript/`

**Coverage Target**: All JavaScript-dependent features

### 5. PF2e Rules Validation Tests (Critical)

**Purpose**: Ensure accurate implementation of Pathfinder 2e rules

**Scope**:
- All character creation calculations
- Combat mechanics (when implemented)
- Skill check calculations
- Spell mechanics
- Leveling up calculations

**Example Test Cases**:
```
PF2e Rules Tests:
├── Character Creation Rules
│   ├── testAbilityBoostRules()
│   ├── testClassHPByClass()
│   ├── testProficiencyProgression()
│   └── testAncestryFeatureApplication()
├── Combat Calculations
│   ├── testMultipleAttackPenaltyProgression()
│   ├── testCriticalSuccessRules()
│   └── testArmorClassCalculation()
├── Skill System
│   ├── testDegreeOfSuccessCalculation()
│   ├── testProficiencyBonuses()
│   └── testSkillDCsByLevel()
└── Leveling System
    ├── testXPRequirementsByLevel()
    ├── testAbilityBoostSchedule()
    └── testFeatProgression()
```

**Data Source**: Reference PF2e documentation in `/docs/dungeoncrawler/`

**Location**: `sites/dungeoncrawler/web/modules/custom/{module}/tests/src/Unit/PF2eRules/`

**Validation Method**:
- Test cases derived from Core Rulebook examples
- Compare calculations against official PF2e character sheets
- Use Archives of Nethys as reference

**Coverage Target**: 100% of implemented PF2e rules

### 6. Performance/Load Tests (As Needed)

**Purpose**: Ensure system performs under realistic load

**Scope**:
- API endpoint response times
- Database query performance
- Concurrent user handling
- Large dataset operations

**Performance Benchmarks**:
```
Target Metrics:
├── API Response Times
│   ├── Character creation: < 200ms
│   ├── Character data fetch: < 100ms
│   ├── Schema data fetch: < 50ms (cached)
│   └── Complex calculations: < 150ms
├── Page Load Times
│   ├── Character creation wizard: < 1.5s
│   ├── Dashboard: < 1s
│   └── Character list (50 items): < 1.2s
├── Database Performance
│   ├── Character query: < 50ms
│   ├── Batch character load: < 200ms (100 characters)
│   └── Complex join queries: < 100ms
└── Concurrent Users
    ├── 10 simultaneous users: No degradation
    ├── 50 simultaneous users: < 10% degradation
    └── 100 simultaneous users: < 25% degradation
```

**Tools**:
- Apache JMeter for load testing
- Drupal Performance module for profiling
- New Relic or similar APM (if available)

**Location**: `testing/performance/` (repository root)

**Test Frequency**: Before major releases, after significant changes

## Test Data Fixtures

### Fixture Organization
```
sites/dungeoncrawler/web/modules/custom/{module}/tests/fixtures/
├── characters/
│   ├── level_1_fighter.json
│   ├── level_1_wizard.json
│   ├── level_5_rogue.json
│   └── invalid_character_data.json
├── schemas/
│   ├── ancestries_test.json
│   ├── classes_test.json
│   ├── backgrounds_test.json
│   └── schema_validation_cases.json
├── pf2e_reference/
│   ├── ability_score_examples.json
│   ├── hp_calculation_examples.json
│   ├── proficiency_examples.json
│   └── official_character_sheets.json
└── api_responses/
    ├── successful_character_create.json
    ├── validation_error_responses.json
    └── edge_case_data.json
```

### Fixture Characteristics
- **Minimal**: Only include data needed for test
- **Realistic**: Based on actual PF2e characters
- **Varied**: Cover edge cases and error conditions
- **Versioned**: Track changes to fixture data
- **Documented**: Include comments explaining purpose

### Example Fixture: level_1_fighter.json
```json
{
  "name": "Test Fighter",
  "level": 1,
  "ancestry": "human",
  "background": "warrior",
  "class": "fighter",
  "ability_scores": {
    "strength": 18,
    "dexterity": 14,
    "constitution": 16,
    "intelligence": 10,
    "wisdom": 12,
    "charisma": 8
  },
  "expected_hp": 19,
  "expected_ac": 18,
  "hp_breakdown": {
    "class_base": 10,
    "con_modifier": 3,
    "ancestry_bonus": 0,
    "other": 0
  }
}
```

## Mock Service Designs

### Mock Strategy Principles
1. **Interface-based mocking**: Mock interfaces, not concrete classes
2. **Predictable behavior**: Mocks return consistent, testable data
3. **Edge case support**: Mocks can simulate error conditions
4. **Performance**: Mocks respond instantly (no I/O)

### Service Mocking Patterns

#### 1. Schema Loader Mock
```php
/**
 * Mock implementation of SchemaLoader for testing
 */
class MockSchemaLoader implements SchemaLoaderInterface {
  
  private array $testData;
  
  public function __construct(array $testData = []) {
    $this->testData = $testData;
  }
  
  public function getClassData(string $class_id): array {
    return $this->testData['classes'][$class_id] ?? [];
  }
  
  public function setTestData(array $data): void {
    $this->testData = $data;
  }
  
  // Simulate loading errors
  public function simulateLoadError(): void {
    throw new SchemaLoadException('Test error');
  }
}
```

#### 2. Character Storage Mock
```php
/**
 * Mock character storage for testing without database
 */
class MockCharacterStorage {
  
  private array $characters = [];
  private int $nextId = 1;
  
  public function save(array $characterData): int {
    $id = $this->nextId++;
    $this->characters[$id] = $characterData;
    return $id;
  }
  
  public function load(int $id): ?array {
    return $this->characters[$id] ?? null;
  }
  
  public function delete(int $id): bool {
    if (isset($this->characters[$id])) {
      unset($this->characters[$id]);
      return true;
    }
    return false;
  }
  
  public function clear(): void {
    $this->characters = [];
    $this->nextId = 1;
  }
}
```

#### 3. API Client Mock
```php
/**
 * Mock API client for testing external service calls
 */
class MockApiClient {
  
  private array $responses = [];
  private array $requestLog = [];
  
  public function setResponse(string $endpoint, array $response): void {
    $this->responses[$endpoint] = $response;
  }
  
  public function get(string $endpoint): array {
    $this->requestLog[] = ['method' => 'GET', 'endpoint' => $endpoint];
    return $this->responses[$endpoint] ?? ['error' => 'Not mocked'];
  }
  
  public function getRequestLog(): array {
    return $this->requestLog;
  }
  
  public function clearLog(): void {
    $this->requestLog = [];
  }
}
```

### Mock Trait for Common Mocks
```php
trait CharacterTestMocks {
  
  protected function getMockSchemaLoader(array $testData = []): MockSchemaLoader {
    return new MockSchemaLoader($testData);
  }
  
  protected function getMockCharacterStorage(): MockCharacterStorage {
    return new MockCharacterStorage();
  }
  
  protected function getTestCharacterData(string $type = 'fighter'): array {
    $fixtures = $this->loadFixture("characters/level_1_{$type}.json");
    return $fixtures;
  }
  
  protected function loadFixture(string $path): array {
    $fullPath = __DIR__ . '/../fixtures/' . $path;
    return json_decode(file_get_contents($fullPath), true);
  }
}
```

## CI/CD Integration Plan

### Current CI/CD State
- **Platform**: GitHub Actions
- **Workflow**: `.github/workflows/deploy.yml`
- **Trigger**: Push to main branch (specific paths)
- **Deployment**: SSH to production server
- **Testing**: None currently integrated

### Proposed CI/CD Testing Integration

#### 1. New Testing Workflow
**File**: `.github/workflows/test.yml`

```yaml
name: Dungeon Crawler Test Suite

on:
  pull_request:
    paths:
      - 'sites/dungeoncrawler/**'
      - 'docs/dungeoncrawler/**'
  push:
    branches:
      - main
      - develop
    paths:
      - 'sites/dungeoncrawler/**'

jobs:
  phpunit:
    name: PHPUnit Tests
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: ${{ secrets.MYSQL_ROOT_PASSWORD }}
          MYSQL_DATABASE: drupal_test
        ports:
          - 3306:3306
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: gd, mysql, pdo_mysql
          coverage: xdebug
      
      - name: Install Composer Dependencies
        run: |
          cd sites/dungeoncrawler
          composer install --no-interaction --prefer-dist
      
      - name: Run PHPUnit Tests
        run: |
          cd sites/dungeoncrawler
          ./vendor/bin/phpunit \
            --configuration web/core/phpunit.xml.dist \
            --testsuite=unit \
            --coverage-text \
            --coverage-html coverage
      
      - name: Upload Coverage Report
        uses: actions/upload-artifact@v3
        with:
          name: coverage-report
          path: sites/dungeoncrawler/coverage
  
  functional:
    name: Functional Tests
    runs-on: ubuntu-latest
    needs: phpunit
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: ${{ secrets.MYSQL_ROOT_PASSWORD }}
          MYSQL_DATABASE: drupal_test
        ports:
          - 3306:3306
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: gd, mysql, pdo_mysql
      
      - name: Install Dependencies
        run: |
          cd sites/dungeoncrawler
          composer install --no-interaction
      
      - name: Setup Drupal
        run: |
          cd sites/dungeoncrawler/web
          ../vendor/bin/drush site:install standard \
            --db-url=mysql://root:${{ secrets.MYSQL_ROOT_PASSWORD }}@127.0.0.1:3306/drupal_test \
            --yes
          ../vendor/bin/drush en dungeoncrawler_content -y
      
      - name: Run Functional Tests
        run: |
          cd sites/dungeoncrawler
          ./vendor/bin/phpunit \
            --configuration web/core/phpunit.xml.dist \
            --testsuite=functional
  
  code-quality:
    name: Code Quality Checks
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      
      - name: Install Dependencies
        run: |
          cd sites/dungeoncrawler
          composer install --no-interaction
      
      - name: PHP CodeSniffer
        run: |
          cd sites/dungeoncrawler
          ./vendor/bin/phpcs \
            --standard=Drupal,DrupalPractice \
            web/modules/custom/
      
      - name: PHPStan Analysis
        run: |
          cd sites/dungeoncrawler
          ./vendor/bin/phpstan analyse \
            web/modules/custom/ \
            --level=5
  
  pf2e-validation:
    name: PF2e Rules Validation
    runs-on: ubuntu-latest
    needs: phpunit
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      
      - name: Install Dependencies
        run: |
          cd sites/dungeoncrawler
          composer install --no-interaction
      
      - name: Run PF2e Rules Tests
        run: |
          cd sites/dungeoncrawler
          ./vendor/bin/phpunit \
            --configuration web/core/phpunit.xml.dist \
            --testsuite=unit \
            --group=pf2e-rules
```

#### 2. Test Configuration
**File**: `sites/dungeoncrawler/phpunit.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.0/phpunit.xsd"
         bootstrap="web/core/tests/bootstrap.php"
         colors="true">
  
  <testsuites>
    <testsuite name="unit">
      <directory>web/modules/custom/*/tests/src/Unit</directory>
    </testsuite>
    <testsuite name="kernel">
      <directory>web/modules/custom/*/tests/src/Kernel</directory>
    </testsuite>
    <testsuite name="functional">
      <directory>web/modules/custom/*/tests/src/Functional</directory>
    </testsuite>
    <testsuite name="functional-javascript">
      <directory>web/modules/custom/*/tests/src/FunctionalJavascript</directory>
    </testsuite>
  </testsuites>
  
  <coverage>
    <include>
      <directory>web/modules/custom/*/src</directory>
    </include>
    <exclude>
      <directory>web/modules/custom/*/tests</directory>
    </exclude>
  </coverage>
  
  <groups>
    <group>pf2e-rules</group>
    <group>character-creation</group>
    <group>combat</group>
    <group>api</group>
  </groups>
  
  <php>
    <env name="SIMPLETEST_BASE_URL" value="http://localhost:8080"/>
    <env name="SIMPLETEST_DB" value="mysql://root:drupal@127.0.0.1:3306/drupal_test"/>
    <env name="BROWSERTEST_OUTPUT_DIRECTORY" value="web/sites/simpletest/browser_output"/>
  </php>
</phpunit>
```

#### 3. Pre-commit Hooks (Optional)
**File**: `.git/hooks/pre-commit` (developer setup)

```bash
#!/bin/bash
# Run quick tests before commit

echo "Running PHPUnit tests..."
cd sites/dungeoncrawler
./vendor/bin/phpunit --configuration web/core/phpunit.xml.dist --testsuite=unit --stop-on-failure

if [ $? -ne 0 ]; then
  echo "❌ Tests failed. Commit aborted."
  exit 1
fi

echo "Running code style checks..."
./vendor/bin/phpcs --standard=Drupal web/modules/custom/ --report=summary

if [ $? -ne 0 ]; then
  echo "⚠️  Code style issues found. Commit aborted."
  exit 1
fi

echo "✅ All checks passed!"
exit 0
```

#### 4. Deployment Gate
Update `.github/workflows/deploy.yml` to require tests:

```yaml
jobs:
  test:
    uses: ./.github/workflows/test.yml
  
  deploy:
    needs: test  # Only deploy if tests pass
    runs-on: ubuntu-latest
    # ... existing deployment steps
```

### CI/CD Testing Schedule

| Event | Tests Run | Duration | Blocking |
|-------|-----------|----------|----------|
| Pull Request | Unit + Functional + Code Quality | ~5-10 min | Yes |
| Push to develop | Full Suite + PF2e Validation | ~10-15 min | No |
| Push to main | Full Suite + Performance | ~15-20 min | Yes |
| Nightly | Full Suite + Extended Performance | ~30 min | No |

## Error Handling Patterns

### Error Handling Strategy

#### 1. Exception Hierarchy
```php
namespace Drupal\dungeoncrawler_content\Exception;

/**
 * Base exception for dungeon crawler module
 */
class DungeonCrawlerException extends \Exception {}

/**
 * Character-related exceptions
 */
class CharacterException extends DungeonCrawlerException {}
class InvalidCharacterDataException extends CharacterException {}
class CharacterNotFoundException extends CharacterException {}

/**
 * PF2e rules validation exceptions
 */
class RulesValidationException extends DungeonCrawlerException {}
class InvalidAbilityScoreException extends RulesValidationException {}
class InvalidLevelException extends RulesValidationException {}

/**
 * Schema/data exceptions
 */
class SchemaException extends DungeonCrawlerException {}
class SchemaLoadException extends SchemaException {}
class SchemaValidationException extends SchemaException {}

/**
 * API exceptions
 */
class ApiException extends DungeonCrawlerException {}
class ApiValidationException extends ApiException {}
class ApiAuthenticationException extends ApiException {}
```

#### 2. Error Response Format
```php
/**
 * Standard error response structure
 */
class ErrorResponse {
  
  public static function create(
    string $message,
    string $code,
    int $statusCode = 400,
    array $details = []
  ): array {
    return [
      'error' => [
        'message' => $message,
        'code' => $code,
        'status' => $statusCode,
        'details' => $details,
        'timestamp' => time(),
      ],
    ];
  }
  
  // Predefined error responses
  public static function invalidCharacterData(array $errors): array {
    return self::create(
      'Invalid character data provided',
      'INVALID_CHARACTER_DATA',
      400,
      ['validation_errors' => $errors]
    );
  }
  
  public static function characterNotFound(int $id): array {
    return self::create(
      "Character with ID {$id} not found",
      'CHARACTER_NOT_FOUND',
      404
    );
  }
  
  public static function rulesViolation(string $rule, $value): array {
    return self::create(
      "PF2e rules violation: {$rule}",
      'RULES_VIOLATION',
      422,
      ['rule' => $rule, 'value' => $value]
    );
  }
}
```

#### 3. Error Logging Strategy
```php
/**
 * Centralized error logging
 */
class ErrorLogger {
  
  private LoggerChannelInterface $logger;
  
  public function __construct(LoggerChannelInterface $logger) {
    $this->logger = $logger;
  }
  
  public function logCharacterError(\Exception $e, array $context = []): void {
    $this->logger->error('Character error: @message', [
      '@message' => $e->getMessage(),
      'exception' => $e,
      'context' => $context,
      'trace' => $e->getTraceAsString(),
    ]);
  }
  
  public function logRulesViolation(string $rule, $value, array $context = []): void {
    $this->logger->warning('PF2e rules violation: @rule', [
      '@rule' => $rule,
      'value' => $value,
      'context' => $context,
    ]);
  }
  
  public function logApiError(string $endpoint, \Exception $e): void {
    $this->logger->error('API error at @endpoint: @message', [
      '@endpoint' => $endpoint,
      '@message' => $e->getMessage(),
      'exception' => $e,
    ]);
  }
}
```

#### 4. Error Recovery Patterns
```php
/**
 * Error recovery service
 */
class ErrorRecoveryService {
  
  /**
   * Attempt to recover from schema loading error
   */
  public function recoverSchemaLoad(string $schemaType): array {
    try {
      // Try primary source
      return $this->schemaLoader->load($schemaType);
    }
    catch (SchemaLoadException $e) {
      // Try cache
      if ($cached = $this->cache->get("schema_{$schemaType}")) {
        $this->logger->warning('Using cached schema due to load error');
        return $cached;
      }
      
      // Fall back to defaults
      $this->logger->error('Using default schema data');
      return $this->getDefaultSchema($schemaType);
    }
  }
  
  /**
   * Graceful degradation for character data
   */
  public function recoverCharacterData(int $id): ?array {
    try {
      return $this->characterStorage->load($id);
    }
    catch (\Exception $e) {
      $this->logger->error('Character load failed: @error', [
        '@error' => $e->getMessage(),
      ]);
      
      // Try backup storage
      if ($backup = $this->backupStorage->load($id)) {
        return $backup;
      }
      
      return null;
    }
  }
}
```

#### 5. Testing Error Scenarios
```php
/**
 * Error scenario test examples
 */
class ErrorHandlingTest extends UnitTestCase {
  
  public function testInvalidCharacterDataThrowsException(): void {
    $calculator = new CharacterCalculator();
    
    $this->expectException(InvalidCharacterDataException::class);
    $this->expectExceptionCode('INVALID_CHARACTER_DATA');
    
    $calculator->calculate(['invalid' => 'data']);
  }
  
  public function testSchemaLoadErrorRecovery(): void {
    $schemaLoader = $this->createMock(SchemaLoaderInterface::class);
    $schemaLoader->method('load')
      ->willThrowException(new SchemaLoadException('File not found'));
    
    $recovery = new ErrorRecoveryService($schemaLoader);
    $result = $recovery->recoverSchemaLoad('classes');
    
    // Should return default schema
    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
  }
  
  public function testApiErrorResponseFormat(): void {
    $response = ErrorResponse::characterNotFound(123);
    
    $this->assertEquals(404, $response['error']['status']);
    $this->assertEquals('CHARACTER_NOT_FOUND', $response['error']['code']);
    $this->assertArrayHasKey('timestamp', $response['error']);
  }
}
```

### Error Monitoring in Production

#### Metrics to Track
- Exception frequency by type
- API error rates
- Failed validation patterns
- Performance degradation triggers
- User-impacting errors vs system errors

#### Alerting Thresholds
- **Critical**: Character data loss, authentication failures
- **Warning**: High validation error rates, slow performance
- **Info**: Expected errors (e.g., invalid user input)

## Test Coverage Strategy

### Coverage Targets by Layer

| Layer | Coverage Target | Priority | Rationale |
|-------|----------------|----------|-----------|
| Service Layer | 90% | Critical | Core business logic |
| Controllers | 70% | High | User-facing functionality |
| Forms | 60% | High | User input handling |
| Helpers/Utils | 85% | High | Reusable logic |
| Templates | 40% | Medium | Visual testing preferred |
| Configuration | 50% | Medium | Stable, rarely changes |

### Coverage Exclusions
- Third-party libraries
- Drupal core overrides (unless custom logic)
- Generated code
- Deprecated code (marked for removal)
- Debug/development-only code

### Coverage Enforcement
- PR must not decrease overall coverage
- New code must meet 80% coverage minimum
- Critical paths require 100% coverage

### Coverage Reporting
```bash
# Generate coverage report
cd sites/dungeoncrawler
./vendor/bin/phpunit --coverage-html coverage --coverage-text

# Upload to codecov (optional)
bash <(curl -s https://codecov.io/bash)
```

## Implementation Roadmap

### Phase 1: Foundation (Week 1-2)
- [ ] Set up PHPUnit configuration
- [ ] Create test directory structure
- [ ] Implement base test classes and traits
- [ ] Create initial fixture data
- [ ] Set up basic CI workflow

### Phase 2: Unit Tests (Week 3-4)
- [ ] Write unit tests for CharacterCalculator
- [ ] Write unit tests for SchemaLoader
- [ ] Write unit tests for validators
- [ ] Write PF2e rules tests
- [ ] Achieve 80% unit test coverage

### Phase 3: Integration Tests (Week 5-6)
- [ ] Write kernel tests for character storage
- [ ] Write integration tests for API endpoints
- [ ] Write session management tests
- [ ] Test database operations

### Phase 4: Functional Tests (Week 7-8)
- [ ] Write character creation workflow tests
- [ ] Write dashboard functionality tests
- [ ] Write access control tests
- [ ] Add JavaScript interaction tests

### Phase 5: CI/CD Integration (Week 9)
- [ ] Complete GitHub Actions workflow
- [ ] Configure automated test runs
- [ ] Set up coverage reporting
- [ ] Integrate with deployment pipeline

### Phase 6: Performance & Monitoring (Week 10)
- [ ] Set up performance benchmarks
- [ ] Create load testing scripts
- [ ] Configure error monitoring
- [ ] Document testing procedures

## Testing Best Practices

### Test Writing Guidelines
1. **AAA Pattern**: Arrange, Act, Assert
2. **One assertion per test** (when possible)
3. **Descriptive test names**: `testCalculateHPForFighterWithHighConstitution`
4. **Test both happy path and edge cases**
5. **Use data providers** for testing multiple scenarios
6. **Mock external dependencies**
7. **Keep tests independent** (no shared state)

### Code Review Checklist
- [ ] All new code has corresponding tests
- [ ] Tests cover edge cases and error conditions
- [ ] Tests are readable and maintainable
- [ ] No skipped or commented-out tests
- [ ] Coverage meets target thresholds
- [ ] Tests run quickly (< 1 second per test)
- [ ] No flaky tests (intermittent failures)

### Maintenance Guidelines
- Review and update tests quarterly
- Remove tests for deprecated features
- Update fixtures when data structures change
- Refactor tests when refactoring code
- Keep test documentation current

## Tools & Dependencies

### Required Packages
```json
{
  "require-dev": {
    "phpunit/phpunit": "^10.0",
    "drupal/core-dev": "^11.0",
    "phpstan/phpstan": "^1.10",
    "drupal/coder": "^8.3",
    "behat/behat": "^3.13",
    "behat/mink": "^1.11",
    "behat/mink-goutte-driver": "^2.0",
    "mockery/mockery": "^1.6"
  }
}
```

### Optional Tools
- **Codecov**: Coverage tracking and reporting
- **SonarQube**: Code quality analysis
- **Behat**: BDD-style acceptance testing
- **PHPMetrics**: Code metrics visualization

## Documentation Requirements

### Test Documentation
Each test file should include:
- Purpose of the test suite
- Setup requirements
- Known limitations
- Related documentation links

### README Files
- `sites/dungeoncrawler/web/modules/custom/{module}/tests/README.md`
  - How to run tests locally
  - How to write new tests
  - Troubleshooting common issues
  - Test data fixture documentation

## Success Metrics

### Quantitative Metrics
- **Test Coverage**: 80%+ overall, 90%+ for services
- **Test Execution Time**: < 5 minutes for full suite
- **Build Success Rate**: > 95% for main branch
- **Bug Detection**: 90%+ of bugs caught before production
- **Regression Rate**: < 5% of closed bugs reappear

### Qualitative Metrics
- Developers feel confident making changes
- Tests serve as documentation
- Tests catch real bugs (not just false positives)
- Onboarding time reduced for new developers
- Code review time reduced due to automated testing

## Related Documentation

- [Character Creation Process](../01-character-creation-process.md)
- [Database Schema Design](../database-schema-design.md)
- [PF2e Core Rulebook Reference](../reference%20documentation/)
- [Issue #1: Character Class HP Design](./issue-1-character-class-hp-design.md)
- [Issue #2: Hexmap Rendering Design](./issue-2-hexmap-rendering-design.md)
- [Issue #3: Game Content System Design](./issue-3-game-content-system-design.md)

## Appendices

### Appendix A: Example Test Class Structure
```php
<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\dungeoncrawler_content\Service\CharacterCalculator;

/**
 * Tests for CharacterCalculator service.
 *
 * @group dungeoncrawler_content
 * @group character-creation
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CharacterCalculator
 */
class CharacterCalculatorTest extends UnitTestCase {

  /**
   * The character calculator service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\CharacterCalculator
   */
  protected $calculator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->calculator = new CharacterCalculator();
  }

  /**
   * Tests HP calculation for a Fighter with 16 Constitution.
   *
   * @covers ::calculateHP
   */
  public function testCalculateHPForFighterWithSixteenConstitution(): void {
    $characterData = [
      'class' => 'fighter',
      'class_hp' => 10,
      'abilities' => ['constitution' => 16],
      'ancestry_hp_bonus' => 0,
    ];

    $result = $this->calculator->calculateHP($characterData);

    $this->assertEquals(13, $result['total']);
    $this->assertEquals(10, $result['breakdown']['class_base']);
    $this->assertEquals(3, $result['breakdown']['con_modifier']);
  }

  /**
   * Data provider for HP calculation tests.
   *
   * @return array
   *   Test data with class, con, ancestry bonus, and expected HP.
   */
  public function hpCalculationProvider(): array {
    return [
      'Fighter high CON' => [10, 18, 0, 14],
      'Wizard low CON' => [6, 10, 0, 6],
      'Dwarf Fighter' => [10, 16, 2, 15],
      'Half-Elf Rogue' => [8, 14, 0, 10],
    ];
  }

  /**
   * Tests HP calculation with various combinations.
   *
   * @covers ::calculateHP
   * @dataProvider hpCalculationProvider
   */
  public function testCalculateHPWithVariousClasses(
    int $classHp,
    int $con,
    int $ancestryBonus,
    int $expectedTotal
  ): void {
    $characterData = [
      'class_hp' => $classHp,
      'abilities' => ['constitution' => $con],
      'ancestry_hp_bonus' => $ancestryBonus,
    ];

    $result = $this->calculator->calculateHP($characterData);

    $this->assertEquals($expectedTotal, $result['total']);
  }
}
```

### Appendix B: PF2e Rules Reference Tests
Example of a test validating against official PF2e rules:

```php
/**
 * Tests PF2e ability score boost rules.
 *
 * Reference: PF2e Core Rulebook, page 20
 * 
 * @group pf2e-rules
 */
class AbilityBoostRulesTest extends UnitTestCase {
  
  /**
   * Tests that ability boosts follow the +2 rule for scores under 18.
   *
   * Per PF2e rules: Boosts add 2 to scores under 18, or 1 to scores at 18+
   */
  public function testAbilityBoostRuleUnderEighteen(): void {
    $calculator = new AbilityScoreCalculator();
    
    // Score 10 + boost = 12
    $this->assertEquals(12, $calculator->applyBoost(10));
    
    // Score 16 + boost = 18
    $this->assertEquals(18, $calculator->applyBoost(16));
  }
  
  /**
   * Tests that ability boosts only add 1 to scores of 18 or higher.
   */
  public function testAbilityBoostRuleAtEighteenOrHigher(): void {
    $calculator = new AbilityScoreCalculator();
    
    // Score 18 + boost = 19 (not 20)
    $this->assertEquals(19, $calculator->applyBoost(18));
    
    // Score 19 + boost = 20 (not 21)
    $this->assertEquals(20, $calculator->applyBoost(19));
  }
}
```

---

## Document Metadata

**Status**: Design Complete  
**Created**: 2026-02-12  
**Type**: Design-Only (No Implementation)  
**Related Issues**: Testing Strategy & Bug Prevention  
**Next Steps**: Review and approval before implementation phase

---

*This is a DESIGN document. No implementation is included. Implementation will be tracked in separate implementation issues/PRs.*
