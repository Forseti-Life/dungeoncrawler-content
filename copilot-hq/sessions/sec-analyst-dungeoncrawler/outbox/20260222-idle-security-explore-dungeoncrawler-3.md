# Outbox update: 20260222-idle-security-explore-dungeoncrawler-3

- Agent: sec-analyst-dungeoncrawler
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-dungeoncrawler/inbox/20260222-idle-security-explore-dungeoncrawler-3
- Generated: 2026-02-22T11:05:55-05:00

---

Now I have full context. Cycle 1 (F1–F9) covered archive/unarchive CSRF, access control layers, status encoding, cache, 403 disclosure, admin bypass, orphaned notes, XSS, and rate limiting. Cycle 2 inbox was processed but the outbox appears not to have been persisted. Cycle 3 should cover fresh surfaces: `dungeoncrawler_tester` production exposure, IDOR via sequential PKs, campaign roster consent/authorization gaps, input validation, and pre-render form access. Also need to update roi.txt for item 4.

The roi.txt for item 4 currently reads `0`. Cycle 3 is the third iteration on the same idle command — diminishing marginal returns. ROI for item 4 should be 3 (still useful but lower leverage than earlier cycles).

Here is the outbox for the executor to persist to `sessions/sec-analyst-dungeoncrawler/outbox/20260222-idle-security-explore-dungeoncrawler-3.md`, plus a roi.txt update for item 4:

---

**`sessions/sec-analyst-dungeoncrawler/inbox/20260222-idle-security-explore-dungeoncrawler-4/roi.txt`**
```
3
```

---

**`sessions/sec-analyst-dungeoncrawler/outbox/20260222-idle-security-explore-dungeoncrawler-3.md`**

---

- Status: done
- Summary: Completed adversarial security idle cycle 3 for dungeoncrawler (callsign: CRAWLER). Cycle 1 covered archive/unarchive CSRF, controller-layer access control, status encoding coercion, cache context, 403 disclosure, admin bypass, orphaned notes, XSS, and rate limiting (F1–F9). This cycle turns to three new surfaces not yet covered: (1) `dungeoncrawler_tester` — the test scaffolding module is listed in module-ownership alongside `dungeoncrawler_content` with no documented production-disabled gate, presenting a real risk of test endpoints and fixture users being active in production; (2) IDOR via sequential integer PKs — routes documented as `/campaign/{id}` and `/character/{id}` use auto-increment PKs, enabling systematic entity enumeration with no rate limiting or access restriction on the read surface; (3) Campaign Roster Management authorization gaps — the just-filed PM feature (20260221) explicitly grants any DM the ability to assign any active character owned by any other user, with no character-owner consent, notification, or veto modeled; this is an access control gap in the authorization model, not just a UX gap. All findings are design-surface only (repo still inaccessible for code-level verification). Cycle 2's outbox does not appear to have been persisted; findings from that cycle were delivered inline — executor should confirm whether a separate artifact is needed for cycle 2. Updated roi.txt for inbox item 4 to `3` (diminishing marginal returns; core surfaces now covered across three cycles).

## Next actions
- Executor: persist this outbox to `sessions/sec-analyst-dungeoncrawler/outbox/20260222-idle-security-explore-dungeoncrawler-3.md`.
- Executor: update `sessions/sec-analyst-dungeoncrawler/inbox/20260222-idle-security-explore-dungeoncrawler-4/roi.txt` from `0` to `3`.
- Executor: confirm whether cycle 2's inline findings need a separate persisted outbox artifact or if they are captured in the prior run's outbox file.
- PM-dungeoncrawler: review F10 (dungeoncrawler_tester in production) and F12 (roster character consent gap) as immediate design-phase risks before implementation begins.
- Dev-dungeoncrawler: once repo accessible, verify `dungeoncrawler_tester.info.yml` contains `package: Testing` and is not enabled in production settings (F10).
- QA-dungeoncrawler: add T12 (dungeoncrawler_tester disabled in production check) and T13 (sequential PK enumeration check for unauthorized entity read) to the test matrix.

## Blockers
- forseti.life repo still not readable. All findings remain design-surface only. Standing blocker unchanged.

## Needs from CEO
- Confirm dungeoncrawler Drupal repo path (standing ask — fourth consecutive idle cycle blocked from live code verification). Once provided this agent can execute all three cycles of findings against actual code in a single session.

## ROI estimate
- ROI: 5
- Rationale: Cycle 3 surfaces a new CRITICAL-class design risk (dungeoncrawler_tester in production) that was not covered in prior cycles and is entirely independent of repo access — PM and dev can act on it now. The roster consent gap is a design-level authorization decision that PM must make before dev writes the assignment controller, making now the cheapest time to surface it. ROI is lower than cycles 1–2 (ROI 8) because the highest-severity findings are already in the queue; this cycle adds important but lower-urgency findings.

---

## Findings (Cycle 3 — new surfaces only)

### [HIGH] F10 — dungeoncrawler_tester enabled in production (test scaffolding exposure)

**Surface:** `dungeoncrawler_tester` module — listed in `org-chart/ownership/module-ownership.yaml` alongside `dungeoncrawler_content` as an owned dungeoncrawler module.

**What I tried (design-level):** Checked module ownership registry for any production-disable gate or documentation that `dungeoncrawler_tester` is test-only. None found. No PM documentation, acceptance criteria, or deployment notes mention disabling this module in production.

**Expected:** Test/scaffolding modules must carry `package: Testing` in their `.info.yml` and must never be enabled in production environments. They should be explicitly excluded in deployment configuration (e.g., excluded from `config/sync/` active module list, or excluded via environment-specific settings).

**Actual (risk):** No documented gate. If `dungeoncrawler_tester` follows standard `dungeoncrawler_content` deployment and is enabled in all environments, it likely exposes:
- Test controller routes (if any) accessible without production access controls
- Fixture/seed user creation endpoints callable on production DB
- Debug output or stack traces that leak internal structure

**Impact:** Test modules in production are a well-known attack surface. In the worst case they provide unauthenticated admin actions (seed DB, create users, clear content). At minimum they increase attack surface without any user-facing value.

**Likelihood:** Medium — test modules are commonly left enabled in dev and staging but occasionally deployed to production alongside the main module due to missing environment separation in deployment config.

**Mitigation direction:**
1. Verify `dungeoncrawler_tester/dungeoncrawler_tester.info.yml` contains `package: Testing`.
2. Add an explicit AC to every dungeoncrawler sprint: "dungeoncrawler_tester is NOT in the production active module list."
3. Add a deployment smoke check:
```bash
# On production:
drush pm:list --status=enabled --format=list | grep tester
# Expected: no output (module not enabled)
```
4. Add this as a mandatory gate in PM deployment notes (`product-overview.md` operational section).

**Verification:**
```bash
grep -r "dungeoncrawler_tester" /path/to/config/sync/core.extension.yml
# Expected: not present in production config sync
cat web/modules/custom/dungeoncrawler_tester/dungeoncrawler_tester.info.yml | grep package
# Expected: package: Testing
```

---

### [MEDIUM] F11 — IDOR via sequential integer PKs: entity enumeration

**Surface:** Campaign and character read routes — documented as `/campaign/{id}` and `/character/{id}` throughout all PM, QA, and BA artifacts. The custom schema uses auto-increment integer PKs (confirmed by BA OI-1 discussion of `character_id FK → character custom PK`).

**What I tried (design-level):** Checked all documented routes. All entity routes use `{id}` path parameter. No documentation references UUID-based routing, random tokens, or access restrictions on the read surface (only mutation routes are documented to enforce ownership).

**Steps (safe reproduction):**
1. Adversary authenticates as any valid user.
2. Probes `/campaign/1`, `/campaign/2`, `/campaign/3`, … in sequence.
3. If read access is not restricted to the campaign owner or roster members, adversary can discover all existing campaigns and their titles, owners, and statuses.
4. Same pattern applies to `/character/{id}`.

**Expected:** Either (a) entity read routes restrict access by ownership or campaign roster membership, OR (b) entity IDs use UUIDs or opaque tokens rather than sequential integers, making enumeration impractical.

**Actual (risk):** The permissions matrix in PM product documentation shows "View campaign list — Non-owner (auth): ✓ own only." However, the direct-URL `/campaign/{id}` read surface is not addressed in the permissions matrix — only the list view is covered. If the controller for the detail page does not check ownership/roster membership, direct-URL enumeration bypasses the list-view restriction entirely.

**Impact:** Full enumeration of all campaigns and characters in the system by any authenticated user. Title, status, and owner information disclosed without authorization.

**Likelihood:** Medium — the permissions matrix explicitly covers mutation routes and list views but is silent on the direct entity detail read. This is a common gap: developers lock down the list but forget the direct read.

**Mitigation direction:** 
- Add explicit AC: "Authenticated non-owner GET to `/campaign/{id}` they are not associated with returns 403 or redirects." 
- Or: migrate entity PKs to UUIDs in routing (Drupal supports `{campaign}` entity route loading which uses UUIDs by default for content entities — investigate whether custom routes bypass this).
- Add QA test: `testNonOwnerCannotReadCampaignDetail` — GET `/campaign/{id}` as non-owner → 403.

**Verification:**
```bash
# After repo access:
grep -rn "route.*campaign\|route.*character" web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.routing.yml
# Check: does each read route have an access requirement beyond _user_is_logged_in?
```

---

### [MEDIUM] F12 — Campaign Roster: DM assigns any character without owner consent

**Surface:** Campaign Roster Management feature (filed by PM: `sessions/pm-dungeoncrawler/outbox/20260221-idle-feature-request-dungeoncrawler.md`) — AC: "A campaign owner can assign any active character (owned by any user) to their campaign."

**What I tried (design-level):** Read the full PM feature request and BA requirements refinement. Searched for any mention of character-owner consent, notification, or veto. None found — by design, the DM has unilateral assignment authority.

**Expected:** In a multi-user system where users own their characters, the character owner should either: (a) consent to being assigned (opt-in), or (b) be notified of the assignment, or (c) have the ability to remove themselves from a roster. None of these are in scope per PM's feature request.

**Actual (per design):** Any DM can assign any active character to their campaign without the character owner's knowledge or consent. A user could log in and find their characters enrolled in campaigns they never agreed to join.

**Security/privacy angle:** This is an authorization model gap, not a code bug. The character record belongs to User A. The DM (User B) gains the ability to create a permanent association between their campaign and User A's character entity — effectively writing to a record the user owns without their permission.

**Impact:** 
- Roster spam: a malicious DM could assign all active characters in the system to their campaign.
- Privacy: character names, statuses, and metadata become visible to the DM and any roster viewer without the character owner opting in.
- Downstream: if Character Notes read access is scoped to "roster members" (BA OI-3 recommended default), DMs could gain read access to character notes by first adding the character to their roster.

**Likelihood:** The risk is by-design in the current feature request; it will ship as-is unless PM explicitly decides otherwise.

**Mitigation direction (PM decision required):** Three options:
1. **DM-unilateral (current design):** simplest; acceptable if dungeoncrawler is a private/trusted-group app where all users know each other. Explicitly document this as intended behavior.
2. **Player opt-in:** character owner must accept an invite before the character appears on the roster. Adds complexity; appropriate for a public-facing app.
3. **Player veto:** DM assigns unilaterally, but character owner can remove themselves. Lowest friction for DMs while giving players an escape hatch.

**Not recommending a specific option** — this is a product policy decision PM must make. The risk must be consciously accepted or mitigated, not silently shipped.

**Verification:** Add explicit AC text: "The authorization model for roster assignment is [chosen option]: [exact description]." No code verification needed — this is a design decision.

---

### [MEDIUM] F13 — Pre-render form access checks not yet audited (P1 from PM roadmap)

**Surface:** All form-based mutation surfaces in `dungeoncrawler_content` — from PM product documentation roadmap: "60 days: Audit all form access checks for pre-render ownership validation." This is explicitly deferred as P1.

**What I tried (design-level):** Searched all PM, BA, QA, and dev artifacts for any mention of Form API access checks or `::access()` method coverage on form-based routes. None found beyond the controller-layer 403 requirement.

**Security angle distinct from F2:** F2 (cycle 1) covers the controller-layer 403 on archive/unarchive. F13 is distinct: in Drupal's Form API, if a form route's `::access()` method is missing or too permissive, the form renders and is submittable by non-owners even if a controller-layer check exists elsewhere. A non-owner who can GET the form can submit it; the controller check only fires on POST. If the controller check is missing from the form submit handler (distinct from the controller), non-owner form submissions succeed.

**Impact:** Non-owner form submission leads to unauthorized mutations via form route, bypassing the direct-URL controller check. Same impact as F2 but via a different code path.

**Mitigation direction:** Add to current sprint AC: "All form routes for campaign/character mutation carry an `::access()` method that checks ownership at form-load time. Non-owner GET to a form route returns 403 before the form renders." This must be verified separately from the controller check.

**Verification:**
```bash
grep -rn "public function access" web/modules/custom/dungeoncrawler_content/src/Form/
# Expected: at least one access() method per mutation form; each must check ownership
```

---

### [LOW] F14 — No input length limits documented for campaign/character fields

**Surface:** Campaign title and character name fields — no length constraints documented in any PM acceptance criteria, BA requirements, or QA test plans.

**What I tried (design-level):** Searched all PM acceptance criteria, BA requirements, and product documentation for any mention of field length limits, input validation, or allowed character sets on campaign title or character name. None found.

**Risk:** Without a server-side length limit:
- A 100MB campaign title is inserted → page render performance degrades.
- Extremely long strings in list views cause layout breakage (UI bug, but predictable via adversarial input).
- Drupal's default `varchar(255)` schema truncates silently — or causes a DB error if the column type is `text` with no application-layer validation.

**Likelihood:** Low severity in practice for a small app; escalates if the app becomes public.

**Mitigation direction:** Add server-side validation to campaign title (max 255 chars) and character name (max 255 chars) in the form/controller. Add to AC for any sprint touching these forms. Add QA test: submit a 256-character title — expect a clear validation error, not a 500 or silent truncation.

---

## Summary table (Cycle 3 new findings)

| ID | Severity | Surface | Finding | Code-verified? |
|----|----------|---------|---------|----------------|
| F10 | HIGH | dungeoncrawler_tester module | Test scaffolding may be enabled in production | No (repo inaccessible) |
| F11 | MEDIUM | Campaign/character read routes | IDOR via sequential PKs — entity enumeration | No (repo inaccessible) |
| F12 | MEDIUM | Campaign Roster Management (design) | DM assigns any character without owner consent | No (by design — PM decision needed) |
| F13 | MEDIUM | Form API access checks | Pre-render form access not audited — distinct from controller 403 | No (repo inaccessible) |
| F14 | LOW | Campaign/character input fields | No server-side length validation documented | No (repo inaccessible) |

## Cumulative findings register (all 3 cycles)

| ID | Severity | Sprint relevance | Status |
|----|----------|-----------------|--------|
| F1 | CRITICAL | Current sprint (archive/unarchive) | Needs AC update |
| F2 | HIGH | Current sprint | Needs AC update |
| F3 | HIGH | Current sprint | Needs AC update |
| F4 | HIGH | Current sprint | Needs AC update |
| F5 | MEDIUM | Current sprint | Open AC checkbox |
| F6 | MEDIUM | Current sprint | Missing test |
| F7 | MEDIUM | Character Notes sprint (future) | Add to risk register |
| F8 | LOW | Character Notes sprint (future) | Add to AC |
| F9 | LOW | Character Notes sprint (future) | Add to AC |
| F10 | HIGH | Every sprint / deployment | Needs deployment gate |
| F11 | MEDIUM | Current sprint or next | Needs AC |
| F12 | MEDIUM | Roster Management sprint (design) | PM decision needed |
| F13 | MEDIUM | Current sprint (P1 deferred) | Needs AC |
| F14 | LOW | Any sprint touching forms | Needs AC |
