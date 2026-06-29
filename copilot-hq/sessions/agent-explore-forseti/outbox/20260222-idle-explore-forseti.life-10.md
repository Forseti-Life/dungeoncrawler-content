# Outbox update: 20260222-idle-explore-forseti.life-10

- Agent: agent-explore-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-forseti/inbox/20260222-idle-explore-forseti.life-10
- Generated: 2026-02-22T12:14:04-05:00

---

Now I have enough findings. Let me compile the outbox:

Now I have all the findings needed. Here is the complete cycle 10 outbox:

---

- Status: done
- Summary: Explored the NFR (National Firefighter Registry) module and the forseti_games module via static code review, simulating a user navigating the enrollment flow, documentation hub, and games leaderboard. Found 7 issues: the most critical is that the entire NFR internal documentation suite — including architecture, installation, business requirements, and a `test-credentials` page — is accessible to any authenticated user (`_permission: 'access content'`) rather than requiring an admin role. Additionally, the NFR questionnaire sections have no enforcement of completion order or prior-section prerequisites, `forseti_games` score submission accepts an arbitrary `game_id` without whitelist validation allowing leaderboard pollution, an old 29KB dead-code form file remains in the module, and the NFR consent form supports an admin-only `?uid=` override that could be misused.

## What I tried
1. Read `nfr.routing.yml` — mapped all 72 routes; identified all with `_permission: 'access content'` vs `administer nfr` vs `access nfr dashboard`.
2. Read `NFRDocumentationController.php` — traced `testCredentials()`, `architecture()`, `installation()`, `businessRequirements()` to confirm they are behind `access content` permission only.
3. Read `NFREnrollmentController.php` — checked `confirmation()` and `welcome()` for anonymous access handling.
4. Read `NFRConsentForm.php` — found `?uid=` admin parameter in `buildForm()`.
5. Read `NFRQuestionnaireSection1Form.php` and `Section3Form.php` — confirmed no prior-section ordering check in either.
6. Read `forseti_games.routing.yml` and `HighScoreController.php` — confirmed `game_id` is accepted from POST body and stored without whitelist validation.
7. Read `copilot_agent_tracker.routing.yml` and `ApiController::event()` — confirmed `_access: TRUE` with token-gating in controller (implementation looks correct).

## Findings

### Finding 1 — SECURITY: NFR internal documentation accessible to any authenticated user
- **File**: `sites/forseti/web/modules/custom/nfr/nfr.routing.yml` lines 50–178
- **Routes**: `/nfr/documentation`, `/nfr/documentation/architecture`, `/nfr/documentation/installation`, `/nfr/documentation/business-requirements`, `/nfr/documentation/protocol`, `/nfr/documentation/user-roles`, `/nfr/documentation/page-specifications`, `/nfr/documentation/questionnaire`, `/nfr/documentation/drupal11-compliance`, `/nfr/documentation/module-completion`, `/nfr/documentation/process-flow-relationships`, `/nfr/documentation/reporting`
- **What happens**: All 12+ documentation pages require only `_permission: 'access content'` (the default authenticated-user permission). Any registered forseti.life user can read NFR system architecture, installation instructions, protocol documents, and completion summaries.
- **Expected**: Internal system documentation should require `administer nfr` or at minimum `view nfr reports`.

### Finding 2 — SECURITY: `/nfr/documentation/test-credentials` exposes test logins to all authenticated users
- **File**: `sites/forseti/web/modules/custom/nfr/nfr.routing.yml` line 146; `NFRDocumentationController::testCredentials()` line 347
- **What happens**: Route `_permission: 'access content'` means any logged-in user can visit `/nfr/documentation/test-credentials` to see `TEST_USER_CREDENTIALS.md`. Even though the file does not currently exist in the `documents/` folder (page would show empty), the route exists and is live. If a developer ever populates the credentials file, they would be immediately visible to all users.
- **Expected**: This route should require `administer nfr` permission and the file should never contain real credentials.

### Finding 3 — UX / DATA INTEGRITY: NFR questionnaire sections can be submitted out of order
- **File**: `sites/forseti/web/modules/custom/nfr/nfr.routing.yml` lines 428–505; `NFRQuestionnaireSection3Form.php` `buildForm()` line ~36
- **What happens**: All 9 section routes have `_permission: 'access content'`. Neither the routing system nor the form `buildForm()` checks whether prior sections are complete before rendering or accepting a section submission. A user can navigate directly to `/nfr/questionnaire/section/9` and submit it, bypassing sections 1–8. The result is a partially-filled questionnaire where later sections appear complete but earlier ones have no data — skewing any research results derived from the registry.
- **Expected**: Each section's `buildForm()` should verify that the previous section (or at minimum, consent) is complete; if not, redirect to the appropriate prior step.

### Finding 4 — UX: NFR questionnaire has no navigation guard between consent and section 1
- **File**: `sites/forseti/web/modules/custom/nfr/src/Form/NFRQuestionnaireSection1Form.php` `buildForm()`; `sites/forseti/web/modules/custom/nfr/nfr.routing.yml` line 428
- **What happens**: `/nfr/questionnaire/section/1` renders without checking whether the user has a consent record in `nfr_consent`. A user who skips the consent step and goes directly to section 1 can complete and submit the questionnaire, creating a data record with no consent audit trail. This is a regulatory risk for a health research registry (NFR collects cancer history and AFFF exposure data).
- **Expected**: Section 1 (and all subsequent sections) should verify `nfr_consent` exists for the current user and redirect to `/nfr/consent` if not.

### Finding 5 — BUG: `forseti_games` score submission accepts arbitrary `game_id`
- **File**: `sites/forseti/web/modules/custom/forseti_games/src/Controller/HighScoreController.php` `submitScore()` lines ~8-12
- **What happens**: `$game_id = $data['game_id']` is accepted from the POST body and inserted directly into `forseti_games_high_scores` without checking against a whitelist of known games (e.g., `block-matcher`). Any authenticated user can submit scores for fake game IDs like `"hack"`, `"test123"`, `"block-matcher-2"`, polluting the leaderboard table and potentially causing the high scores page to display unexpected entries.
- **Expected**: `game_id` should be validated against an allowlist of known games before the score is accepted.

### Finding 6 — DEAD CODE: `NFRReviewSubmitForm_OLD.php` (29KB) still present in module
- **File**: `sites/forseti/web/modules/custom/nfr/src/Form/NFRReviewSubmitForm_OLD.php` (29,139 bytes)
- **What happens**: The file is not referenced in any route, service, or other PHP file in the NFR module. It is dead code from a previous implementation. Its presence creates confusion about which form is authoritative and adds maintenance risk (e.g., security scan tools may flag it as an unregistered form class, or a developer may accidentally wire it up).
- **Expected**: Dead code files should be removed from the module.

### Finding 7 — SECURITY: NFR consent form `?uid=` override parameter has no CSRF/confirmation guard
- **File**: `sites/forseti/web/modules/custom/nfr/src/Form/NFRConsentForm.php` `buildForm()` lines 66-74
- **What happens**: An admin user who visits `/nfr/consent?uid=<any_uid>` will see the consent form pre-filled with that user's data. The form `buildForm()` stores the overridden `$uid` in form state (line 85) and the submit handler uses it to write the consent record (line 188-193). An admin navigating a crafted URL and accidentally submitting the form would record consent on behalf of another user without that user's knowledge. For a health research registry, recording consent without a user's direct action is a regulatory concern.
- **Expected**: The `?uid=` parameter should only be used for read-only admin review, with the submit handler always forcing `$uid = $this->currentUser->id()` (or showing a confirmation modal warning that the admin is about to record consent for another user).

## Next actions
- Escalate Finding 1 and 2 (internal docs and test-credentials exposed to all users) to PM/dev as security/privacy items.
- Escalate Findings 3 and 4 (questionnaire ordering and consent bypass) as data integrity / regulatory compliance items — these affect research data quality.
- File Finding 5 (game_id no whitelist) as a low-priority bug.
- Remove `NFRReviewSubmitForm_OLD.php` (Finding 6) as a cleanup task.
- Review Finding 7 (consent uid override) with legal/privacy to determine if recording on behalf of another user is permissible.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 8
- Rationale: Findings 1/2 are straightforward access-control fixes with high visibility risk — any registered user seeing NFR architecture docs or test credentials would be a compliance issue for a federally-oriented health research registry. Findings 3/4 directly threaten data quality for the research dataset, which is the core value proposition of the NFR product.
