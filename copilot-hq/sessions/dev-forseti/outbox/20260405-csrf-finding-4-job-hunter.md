Let me check the Drupal version and test CSRF token behavior:

The Drupal `CsrfAccessCheck` uses the route path as token seed and requires `?token=` in the URL query string. It applies to ALL request methods. Adding `_csrf_token: 'TRUE'` to GET+POST routes would break GET page loads — exactly like the addposting regression. The correct approach is split routes (GET separate from POST) with the Twig `path()` function auto-appending the token via Drupal's RouteProcessorCsrf.

Now I have the complete picture. Let me implement the fix. The approach:
1. **routing.yml**: Split each step3/4/5 (6 routes) + addposting into GET-only + POST-only variants. POST variants get `_csrf_token: 'TRUE'`.
2. **Templates**: Update form actions to use `_post` route names (Drupal URL generator auto-appends `?token=` for CSRF-protected routes).

Good - Drupal's `RouteProcessorCsrf::processOutbound()` auto-appends `?token=` for CSRF-protected routes when using the URL generator. Now let me implement:

Short variants aren't used in templates/controller. Now implementing the full fix:

Now apply all routing.yml changes — split step3/4/5 routes and document addposting:

Now split step3/4/5 routes:

Now update the templates to use the POST route names:

Template updates look correct (step5_screenshot reference is unrelated, it's a GET route). Now run the verification script and clear the cache:

Verification script passes. Now clear Drupal's routing cache:

Now verify GET and POST routing works:

403 for anonymous on authenticated routes is expected and correct — routes resolve, authentication gates apply. Now commit:

Now write the confirmation artifact and outbox:

---

- Status: done
- Summary: CSRF FINDING-4 fully applied. Split all six step3/4/5 routes (plus `_short` variants) into GET-only and POST-only pairs. POST-only variants have `_csrf_token: 'TRUE'` — Drupal's URL generator auto-appends `?token=xxx` to form action URLs. Three templates updated to reference `_post` route names. FINDING-4d (addposting) resolved via controller-level CSRF (already implemented; routing-level CSRF on GET+POST would cause the same GET regression as `60f2a7ab8`). Verification script: **PASS**. Cache rebuilt cleanly.

## Next actions
- QA: run standard suite; form submissions now include `?token=xxx` in action URL (auto-appended by Drupal) — verify step page loads (GET) and form POSTs work end-to-end

## Blockers
- None

## ROI estimate
- ROI: 12
- Rationale: Step 5 is the highest-risk route (forces job application submission); fix is surgical (routing + template only)

**Commits:** `dd2dcc76` (code), `1e595dc6` (artifact + outbox)

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260405-csrf-finding-4-job-hunter
- Generated: 2026-04-05T21:38:48+00:00
