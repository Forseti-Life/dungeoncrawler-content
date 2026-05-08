# Dungeon Crawler Content Module - Tests

Comprehensive test suite for the Dungeon Crawler content module.

## 📚 Documentation

**Complete testing strategy and design:**
- [Testing Strategy Design Document](../../../../docs/dungeoncrawler/issues/issue-testing-strategy-design.md)
- [Testing Quick Start Guide](../../../../docs/dungeoncrawler/testing/README.md)
- [Documentation Index](../../../../docs/dungeoncrawler/issues/testing-strategy-index.md)
- [Prioritized Test Case Matrix](TEST_CASE_MATRIX.md)

## 🏗️ Structure

```
tests/
├── src/
│   ├── Unit/                    # Unit tests (80% of suite, 90% coverage target)
│   │   ├── Service/             # Service layer tests
│   │   ├── PF2eRules/           # PF2e rules validation
│   │   └── Traits/              # Reusable test traits
│   ├── Kernel/                  # Integration tests (15% of suite)
│   │   ├── Storage/             # Database integration
│   │   └── Api/                 # API integration
│   ├── Functional/              # Browser tests (5% of suite)
│   │   ├── CharacterCreation/   # Character creation flows
│   │   └── Dashboard/           # Dashboard functionality
│   └── FunctionalJavascript/    # JavaScript interaction tests
├── fixtures/                    # Test data files
│   ├── characters/              # Character test data
│   ├── schemas/                 # Schema test data
│   └── pf2e_reference/          # PF2e reference data
├── phpunit.xml                  # PHPUnit configuration
└── README.md                    # This file
```

## 🚀 Running Tests

### All Tests
```bash
cd sites/dungeoncrawler
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_content/phpunit.xml
```

### Specific Test Suite
```bash
# Unit tests only (fast)
./vendor/bin/phpunit --testsuite=unit

# Functional tests only
./vendor/bin/phpunit --testsuite=functional
```

### Specific Group
```bash
# PF2e rules validation tests
./vendor/bin/phpunit --group=pf2e-rules

# Character creation tests
./vendor/bin/phpunit --group=character-creation
```

### Single Test File
```bash
./vendor/bin/phpunit web/modules/custom/dungeoncrawler_content/tests/src/Unit/Service/CharacterCalculatorTest.php
```

### With Coverage Report
```bash
./vendor/bin/phpunit --coverage-html tests/coverage
# Open tests/coverage/index.html in browser
```

## 📊 Test Status

**Current State:** 🚧 Stub files created based on design document

All test files are currently stubs with `markTestIncomplete()` placeholders. Each stub includes:
- Pseudocode showing what needs to be implemented
- References to design documentation
- Links to test fixtures and PF2e reference data
- PHPDoc with coverage annotations

**Next Steps:**
1. Implement service layer (CharacterCalculator, SchemaLoader, CombatCalculator)
2. Implement unit tests for services
3. Implement integration tests for storage/API
4. Implement functional tests for user workflows
5. Run tests and achieve coverage targets (80-90%)

## 🎯 Coverage Targets

| Layer | Target | Priority |
|-------|--------|----------|
| Service Layer | 90% | Critical |
| Controllers | 70% | High |
| Overall | 80% | High |

See [Testing Strategy Design](../../../../docs/dungeoncrawler/issues/issue-testing-strategy-design.md) for complete coverage strategy.

## 📦 Test Fixtures

Test fixtures are located in `tests/fixtures/` and provide realistic test data:

### Characters
- `level_1_fighter.json` - Fighter with 18 STR, 16 CON (13 HP expected)
- `level_1_wizard.json` - Wizard with 18 INT, 12 CON (7 HP expected)
- `level_5_rogue.json` - Mid-level rogue with stealth focus

### Schemas
- `classes_test.json` - 6 core classes with HP and proficiencies
- `ancestries_test.json` - 6 ancestries with HP bonuses
- `backgrounds_test.json` - 6 backgrounds with ability boosts

### PF2e Reference
- `core_mechanics.json` - Official PF2e rules and calculations

All fixtures include expected results for validation.

## 🧪 Test Types

### Unit Tests (80%)
- Fast, isolated tests
- No database operations
- Use mock services
- Test individual methods

### Integration Tests (15%)
- Test service interactions
- Use test database
- Verify database operations
- Test configuration loading

### Functional Tests (5%)
- Full Drupal environment
- Simulated browser
- Test user workflows
- End-to-end validation

## 🎮 PF2e Rules Testing

Special test group for validating Pathfinder 2e game rules:

```bash
./vendor/bin/phpunit --group=pf2e-rules
```

Tests in this group validate:
- Ability score modifiers (PF2e Core Rulebook pp. 20-21)
- HP calculations by class
- Multiple Attack Penalty (p. 446)
- Degrees of Success (p. 445)
- Proficiency progression
- Combat calculations

All PF2e tests reference official rulebook page numbers and test fixture data.

## 🛠️ Implementation Status

### ✅ Complete (Stubs)
- [x] Test directory structure
- [x] PHPUnit configuration
- [x] Test fixtures (characters, schemas, PF2e reference)
- [x] Service stubs (CharacterCalculator, CombatCalculator, SchemaLoader)
- [x] Exception hierarchy
- [x] Unit test stubs
- [x] Functional test stubs
- [x] Test trait stubs

### 🚧 TODO (Implementation)
- [ ] Implement service layer methods
- [ ] Implement unit tests
- [ ] Implement integration tests
- [ ] Implement functional tests
- [ ] Achieve 80-90% coverage
- [ ] Set up CI/CD (see design doc)
- [ ] Add performance tests (see design doc)

## 📖 Resources

- **Design Document**: [issue-testing-strategy-design.md](../../../../docs/dungeoncrawler/issues/issue-testing-strategy-design.md)
- **Quick Start Guide**: [testing/README.md](../../../../docs/dungeoncrawler/testing/README.md)
- **Example Tests**: [testing/examples/](../../../../docs/dungeoncrawler/testing/examples/)
- **Drupal Testing Docs**: https://www.drupal.org/docs/testing
- **PHPUnit Docs**: https://phpunit.de/documentation.html
- **PF2e Rules**: [reference documentation/](../../../../docs/dungeoncrawler/reference%20documentation/)

## 💡 Tips

- Use `markTestIncomplete()` for tests that are defined but not yet implemented
- Reference fixture files in test docblocks for clarity
- Group related tests with `@group` annotations
- Use data providers for testing multiple scenarios
- Keep test methods focused on single assertions when possible
- Add `@covers` annotations to track coverage

---

**Status**: 🚧 Design complete, implementation pending  
**Created**: 2026-02-13  
**Design Reference**: [Testing Strategy & Bug Prevention](../../../../docs/dungeoncrawler/issues/issue-testing-strategy-design.md)
