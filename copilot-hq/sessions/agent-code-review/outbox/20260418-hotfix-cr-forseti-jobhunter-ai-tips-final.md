# Code Review: forseti job_hunter AI tips final correction
**Commit:** `dd128da98` — `fix(job-hunter): remove duplicate ai tips csrf token`
**File reviewed:**
- `sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php`

---

## Change verified

The one-line change is exactly what the prior review prescribed:

```php
// Before (commit 5e71c9a91 — still double-tokens):
'#ai_tips_url' => Url::fromRoute('job_hunter.interview_prep_ai_tips', ['job_id' => $job_id])->toString()
    . '?token=' . rawurlencode(\Drupal::csrfToken()->get('jobhunter/interview-prep/' . $job_id . '/ai-tips')),

// After (this commit — correct):
'#ai_tips_url' => Url::fromRoute('job_hunter.interview_prep_ai_tips', ['job_id' => $job_id])->toString(),
```

## `#ai_tips_url` generation — PASS ✅

`Url::fromRoute('job_hunter.interview_prep_ai_tips', ...)->toString()` calls
`UrlGenerator::generateFromRoute`, which passes the route through all registered
outbound processors including `RouteProcessorCsrf`. Because
`job_hunter.interview_prep_ai_tips` declares `_csrf_token: 'TRUE'` (routing.yml
line 1329), `RouteProcessorCsrf::processOutbound` injects `token` into the
parameter array before path generation, producing:

```
/jobhunter/interview-prep/{job_id}/ai-tips?token=<valid_csrf_token>
```

No manual suffix. The resulting `#ai_tips_url` is now identical in structure to
`#save_url` (line 2698), which uses the same `toString()` call on a route that
also carries `_csrf_token: 'TRUE'` and is confirmed working.

## Token delivery end-to-end — PASS ✅

Template (commit `5e71c9a91`, unchanged here):

```twig
var aiTipsUrl = {{ ai_tips_url|json_encode|raw }};
fetch(aiTipsUrl, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
```

`json_encode` on the URL string produces a properly quoted JS string literal
containing the full path with the embedded `?token=`. The `fetch` call POSTs to
that URL. Drupal's `CsrfAccessCheck::access()` reads
`$request->query->get('token')` and validates it against the request path
`jobhunter/interview-prep/{job_id}/ai-tips` — the same seed `RouteProcessorCsrf`
used to generate the token. Match is guaranteed. ✅

## `interviewPrepAiTips` controller method — PASS ✅

The handler (lines 2877–2945) performs no manual CSRF validation; it relies
entirely on the routing requirement, which is correct. Access/ownership check
(`$job->uid !== $uid`) is present. No regression to this method from the change. ✅

## Notes save / interview prep page rendering — PASS ✅

The single-line change touches only the `#ai_tips_url` array key. `#save_url`,
`#job`, `#job_id`, and `#notes_text` are untouched. `interviewPrepSave` is
untouched. No regression to notes save or page rendering. ✅

---

## FINDINGS

No issues found. The fix is correct and complete.

---

## Collateral regressions from the full hotfix chain

All issues surfaced across the three-review chain are resolved:

| Finding | Surfaced in | Status |
|---|---|---|
| CSRF route-path seed mismatches (5 routes) | `csrf-fresh-install` review | Fixed `848edf2c1` ✅ |
| `resume_source_save` token in header instead of `?token=` | `csrf-fresh-install` review | Fixed `848edf2c1` ✅ |
| Fresh-install `email` column missing from contacts helper | `csrf-fresh-install` review | Fixed `848edf2c1` ✅ |
| `ai_tips_csrf_token` removed from build; template consumed it → 403 | `csrf-fresh-install` review | Fixed `5e71c9a91` + `dd128da98` ✅ |
| `ai_tips_url` double-token (`toString()` + manual append) → 403 | `csrf-followup` review | Fixed `dd128da98` ✅ |

**Verdict: hotfix chain is closed. No open findings.**
