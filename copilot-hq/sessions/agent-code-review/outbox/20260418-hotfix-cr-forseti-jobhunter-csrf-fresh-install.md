# Code Review: forseti job_hunter CSRF + install hotfix
**Commit:** `848edf2c1` — `fix(job-hunter): repair CSRF route seeds`
**Files reviewed:**
- `sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php`
- `sites/forseti/web/modules/custom/job_hunter/job_hunter.install`

---

## CSRF route-path seeds (5 in-scope routes)

All five seeds now match the rendered route paths in `job_hunter.routing.yml`:

| Route | Seed used | Actual path | Result |
|---|---|---|---|
| `company_interest_save` | `jobhunter/companies/{id}/interest/save` | `/jobhunter/companies/{company_id}/interest/save` | ✅ |
| `company_research_save` | `jobhunter/companies/{id}/research/save` | `/jobhunter/companies/{company_id}/research/save` | ✅ |
| `contacts_save` | `jobhunter/contacts/save` | `/jobhunter/contacts/save` | ✅ |
| `contacts_delete` | `jobhunter/contacts/{id}/delete` | `/jobhunter/contacts/{contact_id}/delete` | ✅ |
| `contact_job_link_save` | `jobhunter/contacts/{id}/link-job` | `/jobhunter/contacts/{contact_id}/link-job` | ✅ |

## `resume_source_save` delivery channel

Token moved from `X-CSRF-Token` header to `?token=` query param. Route has `_csrf_token: TRUE` which uses Drupal's `CsrfAccessCheck` — that checker reads `?token=` only. Delivery channel is now correct. ✅

## Install helper extraction (contacts + offers)

`_job_hunter_create_contacts_table()` and `_job_hunter_create_offers_table()` correctly extracted; existing `update_9050` and `update_9058` delegate to them; idempotent `tableExists` guard preserved. ✅

---

## FINDINGS

### Issue 1 — HIGH: `ai_tips_csrf_token` removed from build; template still consumes it → all AI tips requests fail 403

**File:** `src/Controller/CompanyController.php` (interviewPrep build, ~line 2693) + `templates/interview-prep-page.html.twig` line 77

The hotfix removed `#ai_tips_csrf_token` from the `$build` array and eliminated the `$ai_tips_csrf_token` variable entirely:

```php
// REMOVED by hotfix:
-    $ai_tips_csrf_token = \Drupal::csrfToken()->get('jobhunter/interview-prep/' . $job_id . '/ai-tips');
-      '#ai_tips_csrf_token' => $ai_tips_csrf_token,
```

But `templates/interview-prep-page.html.twig` line 77 still uses it to construct the request URL:

```twig
var csrfToken = {{ ai_tips_csrf_token|json_encode|raw }};
var aiTipsUrlWithToken = aiTipsUrl + '?token=' + encodeURIComponent(csrfToken);
```

The route `job_hunter.interview_prep_ai_tips` declares `_csrf_token: TRUE`, so Drupal's `CsrfAccessCheck` gates every POST on a valid `?token=`. With `ai_tips_csrf_token` undefined in template context, Twig renders it as `null`, the URL becomes `?token=null`, and every request to that endpoint returns 403. The "Get AI Interview Tips" button is entirely broken for all users.

**Fix:** Re-add the token to the build:
```php
'#ai_tips_url' => Url::fromRoute('job_hunter.interview_prep_ai_tips', ['job_id' => $job_id])->toString()
    . '?token=' . rawurlencode(\Drupal::csrfToken()->get('jobhunter/interview-prep/' . $job_id . '/ai-tips')),
```
and update the template to use `ai_tips_url` directly without the JS token concatenation, mirroring the pattern used for `resume_source_save` in this same hotfix.

---

### Issue 2 — MEDIUM: Fresh-install `_job_hunter_create_contacts_table()` is missing the `email` column

**File:** `job_hunter.install`, `_job_hunter_create_contacts_table()` (~line 3201)

`update_9060` adds `email` (varchar 255, nullable) to `jobhunter_contacts`, but that column is absent from the fresh-install helper. Upgraded installs have `email`; fresh installs do not.

The save handler has a `fieldExists` guard (line ~4359), so fresh installs won't crash on write — but `email` is silently discarded for any user on a fresh install. Any future code that reads `email` without a guard will fail on fresh-install databases.

**Fix:** Add `'email' => ['type' => 'varchar', 'length' => 255, 'not null' => FALSE, 'default' => NULL]` to the `fields` array in `_job_hunter_create_contacts_table()`.

---

## Summary

| Area | Result |
|---|---|
| CSRF route-path seeds (5 routes) | PASS |
| `resume_source_save` delivery channel | PASS |
| Install helper extraction regression | PASS |
| Fresh-install schema parity (`company_interest`, `company_research`, `offers`) | PASS |
| Fresh-install schema parity (`contacts` — `email` column) | **FAIL** |
| Collateral regression (`ai_tips_csrf_token` removal) | **FAIL** |

**Verdict: 2 findings require follow-up fixes before this hotfix can be considered closed.**
