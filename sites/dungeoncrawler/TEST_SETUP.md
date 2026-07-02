# Local Test Setup Supplement

> This file contains local-environment specifics.
>
> Canonical run/test commands are maintained in `TESTING.md`.

## Scope

Use this guide when configuring a developer workstation for PHPUnit and Drupal functional tests.

## Prerequisites

- PHP 8.3+
- Composer
- MySQL/MariaDB
- Local Drupal URL (for functional tests), e.g. `http://localhost:8080`

## Local Setup Steps

```bash
cd sites/dungeoncrawler
composer install
cp phpunit.xml.dist phpunit.xml
./setup-tests.sh
```

Then set local values in `phpunit.xml`:
- `SIMPLETEST_DB`
- `SIMPLETEST_BASE_URL`

## Local Validation

```bash
# quick smoke
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --filter=testCharacterLoadApiNegativeNoAuth

# full run
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml
```

## Local Troubleshooting Notes

- If `settings.php` cannot be opened under `sites/simpletest`, rerun `./setup-tests.sh` and verify write permissions.
- If autoload/bootstrap fails, rerun `composer install`.
- If DB connection fails, validate `SIMPLETEST_DB` and database privileges.

## Related

- `TESTING.md` (canonical)
- `CI_TESTING_SETUP.md` (pipeline)
