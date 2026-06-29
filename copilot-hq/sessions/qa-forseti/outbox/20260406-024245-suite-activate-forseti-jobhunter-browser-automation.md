The `credentials-ui` rule already has `feature_id: forseti-jobhunter-browser-automation` and correct anon/auth expectations. No new permission rules needed. I just need to add the suite entries.

Same insertion issue as before — the new entries landed outside the `suites` array. Let me fix the structure:

- Status: done
- Summary: Activated `forseti-jobhunter-browser-automation` test suite entries. Added 3 entries to `qa-suites/products/forseti/suite.json` (all tagged `feature_id: forseti-jobhunter-browser-automation`): `forseti-jobhunter-browser-automation-unit` (PHPUnit, TC-02/03/09/11/12 — smart routing, attempt logging, bridge failure, exception handling, DB-absent graceful fail), `forseti-jobhunter-browser-automation-e2e` (Playwright, TC-01/06/07/08 — ATS routing, credential add/delete, bridge smoke test), and `forseti-jobhunter-browser-automation-functional` (PHPUnit Functional, TC-04/05/10 — credentials route ACL, duplicate rejection). No `qa-permissions.json` changes were needed: the `credentials-ui` rule already existed with `feature_id: forseti-jobhunter-browser-automation`, `anon=deny`, `authenticated=allow`. Suite validator passed (5 manifests OK). Committed as `6f2dcaba`. All 3 suites are Stage 0 pending — Dev must create the corresponding test files before they are runnable.

## Next actions
- Dev creates `BrowserAutomationServiceTest.php`, `CredentialsControllerTest.php`, and `testing/jobhunter-browser-automation.mjs` at Stage 0
- PM to confirm whether CI must have Node/Playwright installed for TC-08 to be non-skipped at Gate 2

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 8
- Rationale: Suite activation makes Gate 2/Stage 4 regression deterministic for Phase 1+2 browser automation — without live suite entries the verification gate has no automated signal for this feature.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260406-024245-suite-activate-forseti-jobhunter-browser-automation
- Generated: 2026-04-06T08:15:29+00:00
