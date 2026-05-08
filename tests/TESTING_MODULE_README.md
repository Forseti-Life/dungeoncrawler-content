# Dungeon Crawler Testing Module

## Overview

This testing module provides comprehensive test coverage for the Dungeon Crawler module, including:
- A testing page stub accessible at `/testing`
- Positive and negative test cases for all routes
- Positive and negative test cases for all controllers

## Testing Page

The testing page is a simple stub page that can be used for manual testing and validation.

**URL:** `/testing`
**Access:** Public (no authentication required)
**Controller:** `TestingPageController`

## Test Structure

### Route Tests (`tests/src/Functional/Routes/`)

Route tests validate that all defined routes work correctly with proper access control:

1. **PublicRoutesTest** - Tests public-facing routes (home, world, about, credits, how-to-play, testing)
2. **AdminRoutesTest** - Tests admin routes (settings, dashboard)
3. **CharacterRoutesTest** - Tests character management routes (list, create, view, edit, delete)
4. **CampaignRoutesTest** - Tests campaign routes (list, create, tavern entrance, select character)
5. **ApiRoutesTest** - Tests API endpoints (character and combat APIs)
6. **DemoRoutesTest** - Tests demo routes (hexmap demo)

### Controller Tests (`tests/src/Functional/Controller/`)

Controller tests validate the behavior of individual controllers:

1. **HomeControllerTest** - Tests homepage functionality
2. **AboutControllerTest** - Tests about page
3. **WorldControllerTest** - Tests world page
4. **CreditsControllerTest** - Tests credits page
5. **HowToPlayControllerTest** - Tests how-to-play page
6. **DashboardControllerTest** - Tests admin dashboard
7. **CampaignControllerTest** - Tests campaign management
8. **CharacterListControllerTest** - Tests character list
9. **CharacterCreationStepControllerTest** - Tests character creation wizard
10. **CharacterViewControllerTest** - Tests character viewing
11. **CharacterApiControllerTest** - Tests character API
12. **CharacterStateControllerTest** - Tests character state management API
13. **CombatControllerTest** - Tests combat functionality
14. **CombatActionControllerTest** - Tests combat actions
15. **CombatEncounterApiControllerTest** - Tests combat encounter API
16. **DungeonControllerTest** - Tests dungeon functionality
17. **HexMapControllerTest** - Tests hex map demo
18. **TestingPageControllerTest** - Tests the testing page itself

## Test Cases

Each test file includes:

### Positive Test Cases
- Valid user with proper permissions can access routes
- Routes return expected status codes (200, etc.)
- Routes display expected content
- API endpoints accept valid requests

### Negative Test Cases
- Users without permissions receive 403 Forbidden
- Invalid route parameters return 404 Not Found
- Wrong HTTP methods return 405 Method Not Allowed
- Anonymous users are blocked from protected routes
- Invalid data returns appropriate error codes

## Running Tests

### Run All Tests

```bash
cd /home/runner/work/forseti.life/forseti.life/sites/dungeoncrawler
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_content/phpunit.xml
```

### Run Route Tests Only

```bash
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_content/phpunit.xml tests/src/Functional/Routes/
```

### Run Controller Tests Only

```bash
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_content/phpunit.xml tests/src/Functional/Controller/
```

### Run Specific Test File

```bash
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_content/phpunit.xml tests/src/Functional/Routes/PublicRoutesTest.php
```

### Run Tests by Group

```bash
# Run all route tests
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_content/phpunit.xml --group routes

# Run all controller tests
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_content/phpunit.xml --group controller

# Run all API tests
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_content/phpunit.xml --group api
```

## Test Coverage

The testing module covers:

### Routes (24 routes tested)
- 6 Public routes
- 2 Admin routes
- 8 Character management routes
- 5 Campaign routes
- 11 API endpoints
- 2 Demo routes

### Controllers (20 controllers tested)
- 7 Public page controllers
- 4 Character management controllers
- 2 Campaign controllers
- 4 Combat controllers
- 1 Admin controller
- 1 Testing controller

## Notes

- Tests use Drupal's `BrowserTestBase` for functional testing
- Each test extends the base test case with proper module dependencies
- Tests create users with specific permissions as needed
- Some tests validate route existence even without actual entities (character, campaign, etc.)
- Negative tests ensure proper access control and error handling

## Future Enhancements

- Add kernel tests for service layer testing
- Add unit tests for specific business logic
- Add integration tests for complex workflows
- Add tests with actual entity creation for more complete coverage
- Add performance tests for API endpoints
