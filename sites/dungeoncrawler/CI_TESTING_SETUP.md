# CI Testing Setup Supplement

> This file contains CI/CD-specific guidance.
>
> Canonical run/test commands are maintained in `TESTING.md`.

## Scope

Use this guide to wire PHPUnit execution in CI pipelines for `sites/dungeoncrawler`.

## Baseline CI Steps

```bash
cd sites/dungeoncrawler
composer install --no-interaction --prefer-dist
./setup-tests.sh
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml
```

## CI Requirements

- PHP 8.3+
- MySQL/MariaDB service available to runner
- Writable filesystem for:
  - `web/sites/simpletest`
  - `/tmp/dungeoncrawler-simpletest` (or configured equivalent)

## Recommended Environment Variables

- `SIMPLETEST_DB`
- `SIMPLETEST_BASE_URL`
- `SIMPLETEST_FILES_DIRECTORY` (optional)

## Minimal GitHub Actions Example

```yaml
name: Run Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, ctype, json, mysql, gd
      - name: Install dependencies
        run: |
          cd sites/dungeoncrawler
          composer install --no-interaction --prefer-dist
      - name: Prepare test environment
        run: |
          cd sites/dungeoncrawler
          ./setup-tests.sh
      - name: Run PHPUnit
        run: |
          cd sites/dungeoncrawler
          ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml
```

## CI Troubleshooting

- `settings.php` permission failures: ensure runner can write `web/sites/simpletest`.
- DB connection failures: verify service hostname/port and `SIMPLETEST_DB` credentials.
- Autoload/bootstrap failures: ensure `composer install` succeeded in the same workspace path.

## Related

- `TESTING.md` (canonical)
- `TEST_SETUP.md` (local)
