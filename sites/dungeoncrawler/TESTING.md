# Running Tests (Canonical)

> **Primary test runbook for `sites/dungeoncrawler`.**
>
> Use this file as the single source of truth for local and CI test execution.
>
> Environment-specific supplements:
> - Local environment details: `TEST_SETUP.md`
> - CI pipeline details: `CI_TESTING_SETUP.md`

## Quick Start

### Local
```bash
cd sites/dungeoncrawler
cp phpunit.xml.dist phpunit.xml
composer install
./setup-tests.sh
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml
```

### CI
```bash
cd sites/dungeoncrawler
composer install --no-interaction --prefer-dist
./setup-tests.sh
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml
```

## Required Configuration

Set these values in `phpunit.xml` (or provide via environment variables):
- `SIMPLETEST_DB` (required)
- `SIMPLETEST_BASE_URL` (required for functional/browser flows)
- `SIMPLETEST_FILES_DIRECTORY` (optional; defaults to `/tmp/dungeoncrawler-simpletest`)

Example:
```xml
<env name="SIMPLETEST_DB" value="mysql://user:pass@127.0.0.1:3306/dungeoncrawler_dev"/>
<env name="SIMPLETEST_BASE_URL" value="http://localhost:8080"/>
<env name="SIMPLETEST_FILES_DIRECTORY" value="/tmp/dungeoncrawler-simpletest"/>
```

## Common Commands

```bash
# all tests
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml

# single testsuite
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --testsuite=functional

# single test
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --filter=testCharacterLoadApiNegativeNoAuth
```

## Permissions and Directories

Test runs need writable directories:
- `web/sites/simpletest`
- `/tmp/dungeoncrawler-simpletest` (or your configured files directory)

Prepare with:
```bash
./setup-tests.sh
```

## Troubleshooting

- Missing `web/autoload.php` or autoload errors: run `composer install`.
- `settings.php` write/open failures: verify `web/sites/simpletest` permissions and rerun `./setup-tests.sh`.
- DB errors: confirm database exists and credentials in `SIMPLETEST_DB` are valid.

## Related Docs

- `TEST_SETUP.md` (local machine specifics)
- `CI_TESTING_SETUP.md` (pipeline specifics)
- `web/modules/custom/dungeoncrawler_tester/README.md`
