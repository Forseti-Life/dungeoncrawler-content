# Dungeon Crawler Testing Module (tester package)

## Overview

This testing module exercises the Dungeon Crawler module with route/controller functional tests, selected unit tests for core calculators, and a testing dashboard.

## Testing Dashboard

**For complete run instructions, test suites, groups, and examples, see [README.md](README.md) - the canonical testing guide.**

The testing dashboard provides stagegates for release readiness and surfaces GitHub issues tagged with ci-failure or testing-defect labels.

**URL:** `/dungeoncrawler/testing`
**Access:** Requires `administer site configuration` permission
**Controller:** `TestingDashboardController`
**Purpose:** Release stagegates and issue surfacing for testing workflow

## Test Structure

### Route Tests (`tests/src/Functional/Routes/`)

Route tests validate that all defined routes work correctly with proper access control:

1. **PublicRoutesTest** - Tests public-facing routes (home, world, about, credits, how-to-play)
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
18. **TestingPageControllerTest** - Tests the testing dashboard controller

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

See [README.md](README.md) for complete run instructions including:
- All tests / specific test suites
- Test groups (routes, controllers, API, PF2e rules)
- Single test files
- Coverage reports

## Test Coverage

The testing module covers:

### Routes
- Public (home, world, about, credits, how-to-play)
- Admin (dashboard, settings, testing dashboard)
- Character (list, CRUD, creation flow)
- Campaign (list, create, selection flow)
- API endpoints (character + combat)
- Demo routes (hex map)

### Controllers
- Public page controllers (home, about, world, credits, how-to-play)
- Testing dashboard controller
- Character management controllers
- Campaign controllers
- Combat controllers
- Admin controller

For detailed coverage targets, suite/group execution examples, and current status details, see [README.md](README.md).

## Notes

- Tests use Drupal's `BrowserTestBase` for functional testing
- Each test extends the base test case with proper module dependencies
- Tests create users with specific permissions as needed
- Some tests validate route existence even without actual entities (character, campaign, etc.)
- Negative tests ensure proper access control and error handling

## Future Enhancements

- Optional enhancements:
	- Add deeper kernel coverage for service interactions
	- Expand integration scenarios for multi-step workflows
	- Add performance-oriented API checks where needed
