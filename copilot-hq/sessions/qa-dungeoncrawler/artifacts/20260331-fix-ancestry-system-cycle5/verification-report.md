# Verification Report: fix-ancestry-system-cycle5

- Date: 2026-03-31
- Agent: qa-dungeoncrawler
- Inbox: 20260330-unit-test-20260331-fix-ancestry-system-cycle5
- Dev fix commit: e11854fb0

## Verdict: BLOCK — CYCLE 5 OF 5 (PM escalation required)

## Test results
- Suite: AncestrySystemTest (19 tests)
- Run: 18/19 PASS, 127 assertions
- Progress from cycle 4: ERROR resolved; version column now in hook_schema ✓. Form builds successfully.
- New failure: HTTP 405 on GET to POST-only save endpoint

## Dev fix (e11854fb0) — both changes verified present
1. `version` INT column added to `dc_campaign_characters` in `hook_schema()` ✓
2. Null-coalescing guard `?? 0` on `$character_record->version` in CharacterCreationStepForm line 74 ✓

## Remaining failure

### TC-AN-04: testCharacterCreationStoresAncestry — FAIL (test defect)
- Exception: `ExpectationException: Current response status code is 405, but 200 expected.`
  at `AncestrySystemTest.php:176`
- Root cause: Test defect. Line 172-176:
  ```php
  $this->drupalGet('/characters/create/step/2/save');
  $this->assertSession()->statusCodeEquals(200);
  ```
  Route `dungeoncrawler_content.character_save_step` is POST-only (`methods: [POST]`) with CSRF token
  required. `drupalGet()` issues a GET request → 405 Method Not Allowed. Correct behaviour.
- Intended assertion: "ancestry step form is accessible" — test should use the display route:
  `$this->drupalGet('/characters/create/step/2')` (GET allowed, permission: 'create dungeoncrawler characters')
- Minimal fix (1 line): change line 172 from `/characters/create/step/2/save` to `/characters/create/step/2`
- This is NOT a product defect; the product code and route definitions are correct.

## Fix cycle history for TC-AN-04
| Cycle | Issue | Fix applied | Defect type |
|---|---|---|---|
| 1 | 5 failures (hook_install, drupalPostForm, wrong URL) | hook_install + D10 API | Product + test |
| 2 | 4 failures (hook_install missing fields, wrong URL) | fields + nodes in install | Product |
| 3 | 1 failure: wrong permission in drupalCreateUser | access → create permission | Test |
| 4 | 1 error: version column missing from hook_schema | version to hook_schema + ?? 0 guard | Product |
| 5 | 1 failure: GET on POST-only save route | **NONE (cycle 5 = PM escalation)** | Test |

## PM escalation required
Per `org-chart/roles/tester.instructions.md` Process Flow step 2:
> "Escalate to PM after 5 failed attempts to fix a single failing test:
>  accept risk, pull feature, or re-baseline scope."

TC-AN-04 has failed for 5 consecutive cycles. PM must decide.

## QA recommendation
Authorize a cycle-6 exception — one-line test fix, zero product code change, zero risk:
- File: `tests/src/Functional/AncestrySystemTest.php` line 172
- Change: `/characters/create/step/2/save` → `/characters/create/step/2`
- Rationale: all product fixes are correct; this is the final test-mechanical issue preventing 19/19 PASS.
- Risk profile: minimal; the fix validates the step-2 form is accessible at the correct display URL.

## Site audit (fresh run 2026-03-31)
- Pages: 33 (baseline: 33) ✓
- Errors: 2 expected 403s (auth-gated: /campaigns, /characters/create) ✓
- No regressions from baseline ✓
- Evidence: sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/dungeoncrawler-crawl.md
