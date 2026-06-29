Now I have all the info I need. Let me write the test:

Lines 1586 and 1619 pick up the array key as a literal. Let me inspect those lines:

The array key `'apply_csrf_token'` is being captured as a literal. I need to trim the line to only the part after `csrfToken()->get(`. Let me fix the test:

All 6 route-path seeds pass. Now add the suite entry and commit:

Done. Here's the outbox update:

---

- Status: done
- Summary: Implemented the CSRF seed consistency test for job_hunter controllers as specified in the inbox. Created `tests/src/Unit/Controller/CsrfSeedConsistencyTest.php` — a static analysis PHPUnit test that scans all `csrfToken()->get()` calls in `src/Controller/*.php`, extracts seed string literals, and asserts any seed containing a `/` (route-path-like) matches a route path in `job_hunter.routing.yml` (leading slash stripped, `{param}` placeholders normalized). Seeds without `/` are treated as intentional custom/form tokens and skipped. All 6 live route-path seeds pass: 5 in `JobApplicationController` and 1 in `CompanyController`. Added `forseti-csrf-seed-consistency` to `qa-suites/products/forseti/suite.json`. Committed as `ae6acda72`.

## Next actions
- QA can verify by running: `cd /var/www/html/forseti && vendor/bin/phpunit web/modules/custom/job_hunter/tests/src/Unit/Controller/CsrfSeedConsistencyTest.php --no-coverage --testdox` (requires `composer install --dev`)
- Alternatively the static grep check passes: `grep -rn "csrfToken.*get(" sites/forseti/web/modules/custom/job_hunter/src/Controller/` shows all seeds are route-path seeds

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 40
- Rationale: Directly prevents a repeat of FR-RB-01 which caused 7 commits of rework and a separate fix release. Test runs on every release cycle as a fast static check with zero external dependencies. High leverage per line of code.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260409-csrf-seed-validation-20260408-forseti-release-b
- Generated: 2026-04-09T01:45:13+00:00
