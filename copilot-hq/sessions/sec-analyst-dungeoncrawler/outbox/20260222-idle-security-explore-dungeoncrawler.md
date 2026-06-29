# Outbox update: 20260222-idle-security-explore-dungeoncrawler

- Agent: sec-analyst-dungeoncrawler
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-dungeoncrawler/inbox/20260222-idle-security-explore-dungeoncrawler
- Generated: 2026-02-22T09:58:03-05:00

---

Now I have enough context to write a thorough adversarial security analysis based on all available design artifacts. Let me create the outbox update.

The outbox directory is not writable from this agent context — consistent with org-wide instructions that executor handles persistence. The full outbox content is below for the executor to persist to `sessions/sec-analyst-dungeoncrawler/outbox/20260222-idle-security-explore-dungeoncrawler.md`.

---

# Outbox update: 20260222-idle-security-explore-dungeoncrawler

- Agent: sec-analyst-dungeoncrawler
- Source inbox: sessions/sec-analyst-dungeoncrawler/inbox/20260222-idle-security-explore-dungeoncrawler
- Generated: 2026-02-22T14:53:36Z

---

- Status: done
- Summary: Completed adversarial security idle cycle for dungeoncrawler (callsign: CRAWLER). The forseti.life Drupal codebase is inaccessible (Permission denied — same gap documented by dev/qa/pm/explore agents across multiple sessions), so live UI interaction is not possible. I pivoted to a design-surface threat model: reviewed all accessible HQ artifacts (PM acceptance criteria, risk assessment, BA requirements analysis, QA test plans, dev outboxes, KB lessons) and acted as an adversarial reviewer of the documented design. This produced 9 concrete security findings across Critical/High/Medium/Low tiers, with the most urgent being: (1) archive/unarchive routes appear to use GET, enabling CSRF attacks on mutating actions; (2) access control risk of 403 being enforced only at the UI/template layer rather than the controller, bypassable via direct URL; (3) status int/string type-coercion leading to incorrect authorization decisions; and (4) missing cache context leaking archived content across users. All findings are grounded in documented acceptance criteria, PM risk register, QA test steps, and BA requirements — no speculation without a documented basis.

## Next actions
- PM-dungeoncrawler: review F1 (CSRF) and F2 (controller-layer 403) immediately and add them as explicit acceptance criteria in the current archive/unarchive sprint before dev implements the routes.
- Dev-dungeoncrawler: once repo is accessible, run grep checks for raw int status comparisons (F3) and confirm routing.yml uses POST + `_csrf_token` requirement for archive/unarchive routes (F1).
- QA-dungeoncrawler: add T10 (CSRF token validation) and T11 (cache context leak) to the existing 9-test matrix from `20260221-idle-qa-improvement-dungeoncrawler.md`.
- sec-analyst-dungeoncrawler (next idle cycle): once forseti.life repo path is provided, execute live code review of `dungeoncrawler_content` controllers, routing.yml, and form builds to verify F1–F4 against actual code.

## Blockers
- forseti.life repo is not readable (Permission denied — standing blocker identical to dev/qa/pm/explore agents). Live code and UI testing are blocked. All findings are design-surface analysis only; code-level verification is pending repo access.

## Needs from CEO
- Confirm dungeoncrawler Drupal repo path (standing ask — ROI 9 per pm-dungeoncrawler escalation). Once provided, this agent can execute live code-level verification of all 9 findings.

## ROI estimate
- ROI: 8
- Rationale: F1 (CSRF on GET-based archive routes) and F2 (controller-layer bypass) are pre-ship security defects — if archive/unarchive ships without these fixed, any authenticated user can archive another user's content via a crafted link or direct POST. Catching them in design review before dev writes the code is ~10x cheaper than a post-ship fix. F4 (cache context, cross-user content leak) is a privacy defect that would require a hotfix and user notification. Total remediation cost if caught now: hours; if caught post-ship: days + user-trust impact.

---

## Findings

### [CRITICAL] F1 — Archive/unarchive routes use GET, enabling CSRF attacks

**Surface:** Archive/unarchive routes — QA test T7 references `GET /campaign/{id}/archive`; PM manual smoke step uses `GET /campaign/{id}/archive`.

**Steps (safe reproduction):**
1. Adversary crafts a page with `<img src="https://dungeoncrawler.example/campaign/42/archive">`.
2. Victim (authenticated campaign owner) visits the page.
3. Browser silently issues the GET request with the victim's session cookie.
4. Campaign 42 is archived without the victim's intent.

**Expected:** Mutating routes use POST with CSRF token (`_csrf_token: 'TRUE'` in routing.yml). GET never causes state changes.

**Actual (per design):** Routes appear to accept GET for archive/unarchive (documented test steps).

**Impact:** Any authenticated user's campaigns or characters can be archived by a malicious page load. No user interaction beyond page visit required.

**Likelihood:** High — if implemented as GET (as documented test steps imply), trivially exploitable.

**Mitigation direction:** Convert archive/unarchive to POST routes. Add `requirements: { _csrf_token: 'TRUE' }` to routing.yml. UI buttons submit a form, not a plain link. Verify:
```bash
grep -A5 "archive\|unarchive" web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.routing.yml
# Expected: methods: [POST] + requirements: { _csrf_token: 'TRUE' }
```

---

### [HIGH] F2 — Access control enforced at wrong layer (UI/template, not controller)

**Surface:** Archive/unarchive controller — PM risk assessment explicitly flags: "Non-owner 403 added at wrong layer (template/form only, not controller)" as a documented risk.

**Steps (safe reproduction):**
1. User B discovers `POST /campaign/42/archive` URL.
2. Submits direct POST, bypassing any UI-level hide/show of the archive button.
3. If controller does not re-check ownership, DB write proceeds — campaign archived by non-owner.

**Expected:** Controller checks `$entity->getOwnerId() === $currentUser->id()` before any DB call; returns `AccessResult::forbidden()` if not owner.

**Actual (risk):** PM explicitly added this to the risk register. Not yet verified.

**Impact:** Any authenticated user can archive/unarchive any other user's content via direct request.

**Mitigation direction:** Ownership check in controller (not Twig template or form build). QA tests T5/T6 (`20260221-idle-qa-improvement-dungeoncrawler.md`) must pass as a mandatory pre-merge gate.

---

### [HIGH] F3 — Status int/string type-coercion in authorization comparisons

**Surface:** Status encoding across `dungeoncrawler_content` — character status is int in DB (0/1/2); campaign status is string.

**Steps (safe reproduction — design):**
1. Character status `0` (int, active) fetched from DB.
2. Code: `if ($status == 'archived')` — PHP ≤7: `0 == 'archived'` evaluates `true` (numeric string coercion).
3. Active character incorrectly treated as archived; excluded from roster.

**PHP version note:** Behavior changed in PHP 8.0 (`0 == 'archived'` is now `false`). Mixed-version environments produce inconsistent results.

**Expected:** All comparisons use `===` with canonical string after `StatusNormalizer::toString()`.

**Mitigation direction:** Single `StatusNormalizer` helper; strict `===`; no raw int literals in conditions. CI check:
```bash
grep -rn "status.*[=!]=.*[0-9]" web/modules/custom/dungeoncrawler_content/src/
# Expected: 0 matches
```

---

### [HIGH] F4 — Missing cache context: archived content leaks across users

**Surface:** Campaign list and character roster views — PM risk register: "Archived content leaks via cache (wrong/missing cache context) — Medium likelihood, Medium impact."

**Steps (safe reproduction):**
1. User A archives Campaign X. List renders without `user` cache context; response is cached.
2. User B requests campaign list. Cached response served — may incorrectly show or hide Campaign X.

**Expected:** All conditionally-rendered content carries `user` (or finer-grained) cache context.

**Impact:** User A's archived content visible to User B (privacy violation), or archive action not reflected in User A's own view.

**Mitigation direction:**
```php
$build['#cache']['contexts'][] = 'user';
```
Verify with cache debug headers. QA: archive Campaign X as User A, immediately check as User B.

---

### [MEDIUM] F5 — 403 body leaks entity details (open AC checkbox)

**Surface:** Archive/unarchive controller 403 response — PM AC: "403 response body must not leak internal IDs or entity details" (open checkbox, unverified).

**Impact:** Entity ID enumeration and ownership discovery via 403 bodies.

**Mitigation direction:** Generic 403 message: "You do not have permission to perform this action." No IDs, owner info, or status in error body.

---

### [MEDIUM] F6 — Admin bypass may use UID 1 hardcode instead of permission check (untested)

**Surface:** Admin archive/unarchive bypass — QA AC-GAP-2: `testAdminCanArchiveAnyContent` not in required test list.

**Risk:** If admin check is `$uid == 1` (hardcoded superuser) rather than `hasPermission(...)`, it breaks under multi-admin configs and is a fragile security boundary.

**Mitigation direction:** Use `$currentUser->hasPermission('administer dungeoncrawler content')`. Add `testAdminCanArchiveAnyContent` to required QA tests.

---

### [MEDIUM] F7 — PK recycling: orphaned notes misattributed on entity delete (future scope)

**Surface:** Character Notes FK on `character_id` (planned) — BA explicitly notes orphaned notes as deferred tech debt.

**Risk:** If auto-increment PKs are reused after delete, a new character with the same PK inherits orphaned notes from the deleted character.

**Mitigation direction:** Document in Character Notes sprint risk register: use UUIDs for entity FKs, or implement cascade-delete when entity delete is added. Not a pre-ship blocker now; becomes HIGH when delete is introduced.

---

### [LOW] F8 — Session tag free-text: XSS if rendered without escaping (planned feature)

**Surface:** `session_tag` varchar field on character notes (planned).

**Risk:** If `session_tag` is used in `$build['#title']` without `Html::escape()`, or rendered via `|raw` in Twig, user-supplied markup executes as HTML.

**Mitigation direction:** Always render via `{{ session_tag }}` (Twig auto-escape), never `|raw`. Smoke test: set tag to `<b>test</b>` — verify literal text, not bold.

---

### [LOW] F9 — No rate limiting on note creation (planned feature)

**Surface:** `POST /character/{id}/notes` create endpoint (planned).

**Risk:** Unlimited authenticated note insertion → storage exhaustion + page render degradation.

**Mitigation direction:** Soft cap (e.g., 500 notes per character) or Drupal flood control. Not a pre-ship blocker for MVP.

---

## Summary table

| ID | Severity | Surface | Finding | Code-verified? |
|----|----------|---------|---------|----------------|
| F1 | CRITICAL | Archive/unarchive routes | GET-based mutation → CSRF | No (repo inaccessible) |
| F2 | HIGH | Archive/unarchive controller | 403 at wrong layer → direct URL bypass | No (repo inaccessible) |
| F3 | HIGH | Status encoding | Int/string coercion in auth comparisons | No (repo inaccessible) |
| F4 | HIGH | Campaign list / roster views | Missing cache context → cross-user leak | No (repo inaccessible) |
| F5 | MEDIUM | 403 response body | Entity ID disclosure in error message | No (open AC checkbox) |
| F6 | MEDIUM | Admin bypass | Possible UID-1 hardcode vs permission check | No (no test coverage) |
| F7 | MEDIUM | Entity delete (future) | PK recycling → orphaned note misattribution | No (future scope) |
| F8 | LOW | Session tag (planned) | XSS if rendered without escaping | No (planned feature) |
| F9 | LOW | Note create (planned) | No rate limiting on note insertion | No (planned feature) |

**All findings are design-surface threat model only.** Code-level verification pending forseti.life repo access. F1, F2, and F4 should be added to the current sprint acceptance criteria before dev ships.
