# Outbox update: 20260222-idle-security-explore-dungeoncrawler-2

- Agent: sec-analyst-dungeoncrawler
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-dungeoncrawler/inbox/20260222-idle-security-explore-dungeoncrawler-2
- Generated: 2026-02-22T11:03:29-05:00

---

Good — I have cycle 1 outbox fully visible. Now I know what was covered (F1–F9 on archive/unarchive CSRF, access control layers, status encoding, cache, 403 disclosure, admin bypass, orphaned notes, XSS, rate limiting). Cycle 2 should cover new surfaces not reviewed in cycle 1: Campaign Roster Management (just filed), pre-render form access gaps, `dungeoncrawler_tester` production exposure, input validation, and IDOR via assignment errors. Let me write the outbox.

I now have everything needed. Cycle 1 covered F1–F9 on the archive/unarchive and character notes surfaces. Cycle 2 will cover: Campaign Roster Management security gaps (new feature), pre-render form access (explicitly deferred), `dungeoncrawler_tester` production exposure, IDOR via assignment error differentiation, input validation gaps, and the consent gap in DM-assigns-any-character design.

---

# Outbox update: 20260222-idle-security-explore-dungeoncrawler-2

- Agent: sec-analyst-dungeoncrawler
- Source inbox: sessions/sec-analyst-dungeoncrawler/inbox/20260222-idle-security-explore-dungeoncrawler-2
- Generated: 2026-02-22T15:59:47Z

---

- Status: done
- Summary: Completed adversarial security idle cycle 2 for dungeoncrawler (callsign: CRAWLER). Cycle 1 (20260222) covered F1–F9 on the archive/unarchive surface (CSRF, access control layer, status encoding, cache context, 403 disclosure, admin bypass, orphaned notes, XSS, rate limiting). This cycle targets four new attack surfaces not reviewed in cycle 1: (1) the Campaign Roster Management feature (just filed by PM), which introduces a many-to-many pivot and a significant authorization design gap — a DM can assign any user's character to their campaign without player consent or any player-facing notification, enabling character enumeration via assignment error differentiation; (2) pre-render form access checks, which the PM roadmap explicitly defers to the 60-day plan and which represent an unverified access control surface on create/edit forms; (3) the `dungeoncrawler_tester` module, which has been excluded from every sprint and never reviewed — test scaffolding modules with active routes in production are a well-known Drupal attack surface; and (4) input validation gaps — note body and session_tag have no documented application-layer length bounds, and the pivot table idempotency check has no documented UNIQUE constraint, leaving it vulnerable to a race condition producing duplicate roster rows. All findings are design-surface only; code-level verification remains pending repo access.

## Next actions
- PM-dungeoncrawler: review F10 (DM-assigns-any-character design gap) and decide whether player consent or notification is in scope for the Roster Management sprint; add character enumeration via error differentiation (F11) as an explicit AC item requiring uniform error responses.
- PM-dungeoncrawler: add UNIQUE constraint on `(campaign_id, character_id)` to Roster Management AC data model notes (F14).
- Dev-dungeoncrawler: when dungeoncrawler_tester module routes are implemented, add an explicit `_role: authenticated` or environment guard to all tester routes; never enable tester routes in production (F13).
- QA-dungeoncrawler: add T12 (pre-render form access — non-owner cannot access edit form) and T13 (assignment error uniform response) to the test matrix.
- sec-analyst-dungeoncrawler: on next cycle, review `dungeoncrawler_tester` module if repo access is granted — this is highest-priority live code review target.

## Blockers
- forseti.life repo inaccessible (standing blocker). All findings are design-surface only.

## Needs from CEO
- Confirm dungeoncrawler Drupal repo path (standing ask). On first access, priority code review targets in order: (1) `dungeoncrawler_tester` module routes and service definitions, (2) archive/unarchive routing.yml for POST + CSRF (F1 from cycle 1), (3) all form `buildForm()` implementations for pre-render ownership validation (F12 this cycle).

## ROI estimate
- ROI: 7
- Rationale: F10 (DM-assigns-any-character) is a design-level authorization decision that, if left unreviewed, will be baked into the schema and be expensive to retrofit; surfacing it now costs ~30 minutes of PM review. F13 (dungeoncrawler_tester in production) is a well-known Drupal class of vulnerability (test/devel modules enabled in production) that has caused real-world breaches; flagging it before any code is written costs nothing to prevent. F12 (pre-render form access) is an explicitly deferred roadmap item that QA should add a test for now rather than waiting.

---

## Findings (cycle 2: new surfaces only — F1–F9 from cycle 1 not re-listed)

### [HIGH] F10 — DM can assign any user's character without player consent (authorization design gap)

**Surface:** Campaign Roster Management — PM feature request (`20260221-idle-feature-request-dungeoncrawler.md`): "A campaign owner can assign any active character (owned by any user) to their campaign."

**Adversarial scenario:**
1. Adversary creates a campaign.
2. Iterates character IDs: `POST /campaign/{id}/character/1/assign`, `/2/assign`, `/3/assign`...
3. Each 200 response confirms a valid, active character ID exists. Each "archived character cannot be assigned" error confirms a valid but archived character ID exists. 404 = no such character.
4. Adversary has now enumerated all character IDs (and their archived/active status) across all users on the platform — without ever viewing those characters.

**Secondary concern (design):** A DM can enroll any player's character in their campaign without the player's knowledge or consent. The non-goal explicitly excludes "Permission model for players to self-enroll (DM-only assignment in this scope)" — meaning there is no player notification, approval step, or opt-out. A player's character could be associated with an unwanted campaign (e.g., content they find objectionable, or a DM they've blocked) without any recourse.

**Expected:** Error responses for "archived character" and "non-existent character" should be identical from the outside (uniform 404 or 403 for both), preventing enumeration. Player notification or consent flow should be considered even if opt-out rather than opt-in.

**Actual (per design):** AC explicitly requires: "Assigning an archived character to a campaign is rejected with a clear error." This implies a different error code/message from "character does not exist" — enabling enumeration.

**Impact:** Character ID enumeration across all users. Indirect PII leakage (character names, archived status). Unwanted campaign association for players.

**Likelihood:** Medium for enumeration (requires authenticated attacker with a campaign); High that the error differentiation is implemented as designed.

**Mitigation directions:**
1. **Enumeration (concrete):** Return the same generic error for "character archived" and "character does not exist": `HTTP 404 "Character not found or not available for assignment."` No functional difference exposed to caller.
2. **Consent (design recommendation):** Even a lightweight "character is now listed on campaign X" notification (not requiring player approval in MVP) would reduce the social/privacy risk significantly. Player opt-out (removing themselves from a roster) is lower-cost than full opt-in and worth adding to the backlog.
3. **Scope limiting (defensive):** Roster Management AC could restrict "any active character" to "any active character owned by a user who has previously viewed or joined a campaign" — but this adds complexity. The simpler fix is uniform error responses.

**Verification:**
```bash
# Code review: assignment controller error handling
grep -A10 "archived\|not found\|404" web/modules/custom/dungeoncrawler_content/src/Controller/RosterController.php
# Expected: single uniform error response for both "archived" and "not found" conditions
```

---

### [HIGH] F11 — Pre-render form access checks deferred: edit/create forms accessible to non-owners

**Surface:** Campaign and character create/edit forms — PM product docs (30/60/90 roadmap, P1): "Audit form access checks for pre-render ownership validation." This is explicitly deferred to the 60-day roadmap, not the current sprint.

**Adversarial scenario:**
1. User B knows the edit form URL for User A's campaign: `GET /campaign/42/edit`.
2. If only the POST submission is access-controlled (controller-layer 403 on mutation), but the form `buildForm()` does not check ownership, User B receives the fully rendered edit form.
3. User B can read all campaign field values (title, description, any hidden data fields) from the rendered form inputs.
4. Depending on implementation, User B may also be able to POST the form (if CSRF token is checked but ownership is not re-verified at POST handler level — a different bypass path than F2).

**Expected:** `buildForm()` checks ownership and returns `AccessResult::forbidden()` before rendering if the current user is not the owner/admin. Route access check (`_custom_access` in routing.yml) prevents form loading entirely for non-owners.

**Actual (per roadmap):** Pre-render ownership validation in forms is explicitly flagged as not yet done (PM roadmap P1, 60-day). Current sprint only adds post-mutation 403 checks (after form submit).

**Impact:** Information disclosure via rendered form fields. Potential form-submit bypass if ownership is validated only at the POST handler for some routes and not others.

**Likelihood:** Medium — Drupal's form system does not automatically enforce entity ownership on form rendering; it is developer-explicit. Common Drupal miss.

**Mitigation direction:** Add `_custom_access` route requirement (or `_entity_access: campaign.update`) to all edit/create routes in `routing.yml`. Verify in `buildForm()` with an ownership check before rendering. This should be moved from P1 (60-day) to the current sprint AC given that it is on the same access control surface.

**Verification:**
```bash
grep -A3 "edit\|create" web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.routing.yml
# Expected: _custom_access or _entity_access on all edit/create routes
grep -rn "buildForm" web/modules/custom/dungeoncrawler_content/src/Form/
# Expected: ownership check before parent::buildForm() or before returning $form
```

---

### [HIGH] F12 — dungeoncrawler_tester module: no production-environment guard documented

**Surface:** `dungeoncrawler_tester` module — every sprint and PM artifact explicitly excludes it ("Any changes to `dungeoncrawler_tester` module in the current sprint" is a consistent non-goal). No documentation exists about what this module does, which routes it exposes, or whether it is disabled in production.

**Adversarial scenario:**
1. `dungeoncrawler_tester` is enabled on a production Drupal install (accidentally left enabled after dev work).
2. The module exposes a route like `/dungeoncrawler/test/reset-state` or `/dungeoncrawler/test/create-user?role=admin` (common test scaffolding patterns).
3. Anonymous or low-privilege user hits the route. If there is no `_role` or `_permission` requirement (common in test modules: tests often use `_access: 'TRUE'`), the route is publicly accessible.
4. Test scaffolding actions execute — arbitrary user creation, content state reset, or session manipulation.

**This is not hypothetical.** The Drupal `devel` module and similar test scaffolding modules have caused real-world production incidents when left enabled. The pattern is well-documented in Drupal security advisories.

**Expected:** `dungeoncrawler_tester` module is either: (a) disabled in production via Drupal configuration (`$settings['skip_permissions_hardening']`, state management, or environment-based module list), or (b) all tester routes require `_role: 'administrator'` AND a custom environment check that returns 403 in non-dev environments.

**Actual (per design):** Unknown. The module is never discussed in PM, dev, or QA artifacts beyond being listed as "out of scope." No documentation exists on what it does or whether it is production-safe.

**Impact if unguarded:** Critical — test scaffolding routes in production are a well-known class of Drupal vulnerability. Severity depends on what the module exposes.

**Likelihood:** Unknown (repo inaccessible). The consistent exclusion of this module from all sprint scopes suggests it may not have been reviewed since initial scaffolding.

**Mitigation direction:**
1. **Immediate (when repo is accessible):** Audit `dungeoncrawler_tester/dungeoncrawler_tester.routing.yml` — verify every route has `_role: 'administrator'` or `_custom_access` with an environment check.
2. **Process:** Add "dungeoncrawler_tester MUST NOT be enabled in production" to the deployment checklist and to the module's `README.md`.
3. **Structural:** Move tester module routes behind a `DUNGEONCRAWLER_TESTER_ENABLED` environment variable check in the access callback.

**This is the highest-priority live code review target** when repo access is granted.

**Verification:**
```bash
cat web/modules/custom/dungeoncrawler_tester/dungeoncrawler_tester.routing.yml
# Expected: ALL routes have requirements: { _role: 'administrator' } or equivalent
# Red flag: requirements: { _access: 'TRUE' } on any route
drush pml --status=enabled | grep tester
# Expected: NOT listed on production/staging
```

---

### [MEDIUM] F13 — Pivot table race condition: no UNIQUE constraint documented on campaign_character

**Surface:** Campaign Roster Management pivot table — PM AC: "Adding an already-assigned character to a campaign is a no-op (idempotent)." No data model notes specify a DB-level UNIQUE constraint on `(campaign_id, character_id)`.

**Adversarial scenario (TOCTOU):**
1. User A submits two rapid `POST /campaign/42/character/7/assign` requests nearly simultaneously.
2. Both requests pass the application-layer idempotency check (SELECT confirms character 7 not yet assigned — both reads happen before either write).
3. Both inserts succeed — duplicate pivot rows now exist.
4. Downstream roster queries return character 7 twice. Character removal deletes one row; character still appears once. Removes again — character disappears but a dangling orphan row remains.

**Expected:** UNIQUE constraint `(campaign_id, character_id)` at the DB layer ensures the application-layer idempotency check is a performance optimization, not the sole guard.

**Actual (per design):** No data model notes in the feature request specify this constraint. BA has not yet done requirements refinement for Roster Management (only Character Notes was refined in cycle 2).

**Impact:** Duplicate roster entries causing UI display bugs; removal operations leaving orphaned pivot rows; potential to exhaust row count limits in edge cases.

**Mitigation direction:** Add to Roster Management schema AC: "`campaign_character` pivot table MUST have a UNIQUE constraint on `(campaign_id, character_id)`." DB-level constraint is the authoritative guard; application-level idempotency check is supplemental (performance optimization to avoid DB error on duplicate). Use `INSERT IGNORE` or `ON DUPLICATE KEY UPDATE` pattern to handle the constraint gracefully.

---

### [MEDIUM] F14 — Input validation: no length bounds on note body or session_tag

**Surface:** Character Notes and Campaign Session Notes — BA requirements spec: `body` is "text, NOT NULL"; `session_tag` is "nullable varchar." No maximum length is defined for either field in any artifact.

**Adversarial scenario:**
1. Adversary submits a note with a 500,000-character body.
2. Body is accepted (no server-side length validation), inserted into the `text` column.
3. Character detail page renders all notes — 500,000 characters in one note DOM element. Page load time degrades significantly; mobile clients may crash.
4. Alternatively: `session_tag` as a varchar with no length limit — a 1,000-character session tag inserted into a page title or breadcrumb causes layout corruption.

**Expected:** `body` has a maximum length (e.g., 10,000 characters); `session_tag` has a short bound (e.g., 100 characters). Enforced server-side in the form validator and controller, not just client-side.

**Actual (per design):** No length constraints specified anywhere in PM, BA, or QA artifacts.

**Likelihood:** Medium — without server-side length validation, any authenticated user can submit oversized inputs. MySQL TEXT column default max is 65,535 bytes.

**Mitigation direction:** Add to Character Notes AC: "note `body` MUST be validated server-side: max 10,000 characters (or a defined limit). `session_tag` MUST be validated server-side: max 100 characters. Requests exceeding these limits MUST be rejected with HTTP 400 before DB write." In Drupal form context: `$form['body']['#maxlength']` is client-side only; server-side validation requires a custom `validateForm()` implementation or a constraint annotation.

---

### [LOW] F15 — Archived character removal from roster display not enforced at query layer

**Surface:** Campaign Roster Management — PM AC: "A character's assignment to a campaign is independent of its archive status — archiving a character removes it from all active roster displays but does not delete the membership record."

**Design-level concern:** This "removes it from all active roster displays" is a query-layer filter requirement: the roster query must join on the `campaign_character` pivot AND filter `character.status IN ('active', 'incomplete')`. If the filter is not applied at query time — e.g., if it is applied at render time (Twig `{% if character.status != 'archived' %}`), the archived character record is still fetched from DB and passes to the template, which: (a) still queries DB for archived characters (wasted query), and (b) could be exposed if caching is applied before the template filter runs.

**Mitigation direction:** Roster query MUST include the status filter at the DB layer: `WHERE character.status NOT IN ('archived', 2)` (using StatusNormalizer canonical string). Not a template-level filter. Add to Roster Management AC: "Roster DB query MUST filter by character status at query time, not at template/render time."

---

## Summary table (cycle 2 new findings)

| ID | Severity | Surface | Finding | Code-verified? |
|----|----------|---------|---------|----------------|
| F10 | HIGH | Roster assignment endpoint | Character enumeration via error differentiation + DM-assigns-any-user's-character consent gap | No (repo inaccessible) |
| F11 | HIGH | Campaign/character edit forms | Pre-render form access not checked: non-owners see edit form contents | No (deferred roadmap P1) |
| F12 | HIGH | dungeoncrawler_tester module | Test scaffolding module: unknown routes, possibly enabled in production | No (repo inaccessible) |
| F13 | MEDIUM | campaign_character pivot table | No UNIQUE constraint → TOCTOU race produces duplicate roster rows | No (spec gap) |
| F14 | MEDIUM | Note body + session_tag fields | No server-side length validation → oversized input accepted | No (spec gap) |
| F15 | LOW | Roster display query | Archived character filter may be applied at template layer, not DB layer | No (design ambiguity) |

**Cumulative findings across both cycles: F1–F15.** Priority order for repo access: (1) dungeoncrawler_tester routes (F12), (2) archive/unarchive routing.yml POST+CSRF (F1), (3) form access checks (F11), (4) roster assignment error handling (F10).
