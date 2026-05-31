# Code Review: forseti job_hunter CSRF follow-up (CEO patch)
**Commit:** `5e71c9a91` — `fix(job-hunter): restore ai tips csrf flow`
**Files reviewed:**
- `sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php`
- `sites/forseti/web/modules/custom/job_hunter/templates/interview-prep-page.html.twig`
- `sites/forseti/web/modules/custom/job_hunter/job_hunter.install`

---

## Fresh-install `email` column parity

`email` (varchar 255, nullable, default NULL) is now present at line 3216 of
`_job_hunter_create_contacts_table()`, positioned between `relationship_type` and
`last_contact_date`. The definition matches what `update_9060` adds via
`schema->addField`. `linkedin_url` was already in the helper. ✅

---

## FINDINGS

### Issue 1 — HIGH: Controller still double-tokens `ai_tips_url`; AI tips button is still 403

**File:** `src/Controller/CompanyController.php` lines 2699–2700

The CEO patch changes `#ai_tips_url` to:
```php
'#ai_tips_url' => Url::fromRoute('job_hunter.interview_prep_ai_tips', ['job_id' => $job_id])->toString()
    . '?token=' . rawurlencode(\Drupal::csrfToken()->get('jobhunter/interview-prep/' . $job_id . '/ai-tips')),
```

**The problem:** `Url::fromRoute(...)->toString()` for any route carrying
`_csrf_token: 'TRUE'` already emits `?token=<csrf_value>` via Drupal core's
`RouteProcessorCsrf` outbound processor. When `toString()` is called without a
`BubbleableMetadata` collector (the default in controller context), `UrlGenerator
::generateFromRoute` passes `NULL` for `$generated_url`, which causes
`RouteProcessorCsrf::processOutbound` to take its first branch and write the real
token directly into `$parameters['token']`. `UrlGenerator::doGenerate` then
line-flips the path-variables array and moves all remaining parameters (including
`token`) into the query string via `array_diff_key`. The result is:

```
/jobhunter/interview-prep/42/ai-tips?token=TOKEN
```

The CEO's code then appends `?token=TOKEN` a second time:

```
/jobhunter/interview-prep/42/ai-tips?token=TOKEN?token=TOKEN
```

PHP's `parse_str` treats the literal `?` as part of the first value, so
`$request->query->get('token')` returns `TOKEN?token=TOKEN`, which does not equal
`TOKEN`. `CsrfAccessCheck::access()` returns `AccessResult::forbidden()`. The "Get
AI Interview Tips" button is still entirely broken.

**Evidence:**

1. `c0f597279` ("Cleanup: remove dead-code CSRF hidden fields") explicitly
   confirmed this mechanic — its author removed `<input type="hidden"
   name="form_token">` from the notes form precisely because "`RouteProcessorCsrf`
   adds token to URL query string", making the hidden input redundant dead code.
   `save_url` (also a plain `toString()` call with no manual append) therefore
   carries the token already and the notes form works — same mechanism.

2. Confirmed empirically:
   ```
   $ php -r "parse_str('token=ABC123?token=ABC123', \$o); echo \$o['token'];"
   ABC123?token=ABC123
   ```

3. `UrlGenerator::doGenerate` (line 184): `$variables = array_flip($variables)`
   followed by line 242: `$query_params += array_diff_key($parameters, $variables,
   $defaults)` — `token` is not a path variable, so it survives the diff and lands
   in the query string.

**The template half of the fix is correct:** removing `csrfToken` /
`aiTipsUrlWithToken` and using `aiTipsUrl` directly in `fetch()` is the right
approach. The error is in the controller, which must not manually append the token
because the route processor already embeds it.

**Correct controller line:**
```php
'#ai_tips_url' => Url::fromRoute('job_hunter.interview_prep_ai_tips', ['job_id' => $job_id])->toString(),
```
No `?token=` suffix. The route processor adds it. The template then passes `aiTipsUrl`
to `fetch()` unchanged, exactly as `save_url` is passed to the form action.

---

## Summary

| Area | Result |
|---|---|
| Fresh-install `email` column parity | PASS ✅ |
| Template cleanup (remove undefined `ai_tips_csrf_token`) | PASS ✅ |
| `ai_tips_url` CSRF token delivery — controller | **FAIL** — double-token URL, still 403 |

**Verdict: 1 finding requires a follow-up fix before the AI tips feature is functional.**
