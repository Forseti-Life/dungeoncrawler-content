# Dungeon Crawler Testing Module

Focused view of the **functional/controller** testing surface for `dungeoncrawler_content`.

## What this document covers

1. Functional route/controller testing patterns.
2. How to run those tests.
3. Immediate functional gaps still pending.

For full-system testing inventory (unit + functional + drush + node), use `tests/README.md`.

## Functional structure

- `tests/src/Functional/Routes/` for route access/method/permission behavior.
- `tests/src/Functional/Controller/` for controller/page/API behavior assertions.

Functional tests rely on Drupal `BrowserTestBase` and should assert both:

1. **Positive path** (authorized/valid request).
2. **Negative path** (unauthorized/invalid request, deterministic failure response).

## Running functional tests

From Drupal root (`/var/www/html/dungeoncrawler`):

```bash
# Functional suite only.
./vendor/bin/phpunit -c web/modules/custom/dungeoncrawler_content/phpunit.xml --testsuite functional

# Only route-focused tests.
./vendor/bin/phpunit -c web/modules/custom/dungeoncrawler_content/phpunit.xml web/modules/custom/dungeoncrawler_content/tests/src/Functional/Routes

# Only controller-focused tests.
./vendor/bin/phpunit -c web/modules/custom/dungeoncrawler_content/phpunit.xml web/modules/custom/dungeoncrawler_content/tests/src/Functional/Controller
```

## Pending functional/controller work

High-priority additions still needed (detailed backlog in `tests/TEST_CASE_MATRIX.md`):

1. `GameObjectsController` functional coverage.
2. `GeneratedImageApiController` functional coverage.
3. `DungeonStateController` and `RoomStateController` functional/API contract coverage.
4. Campaign archive/unarchive lifecycle behavior assertions (including ownership and status restoration).
5. Admin architecture/testing pages access + content assertions.

## Contract posture

Functional/API tests should enforce strict contract behavior:

1. Validate required payload shape.
2. Assert deterministic error codes/messages for invalid input.
3. Fail on contract violations (no fallback/recovery masking defects).
