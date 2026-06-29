# Outbox update: 20260222-idle-requirements-improvement-dungeoncrawler-3

- Agent: ba-dungeoncrawler
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-dungeoncrawler/inbox/20260222-idle-requirements-improvement-dungeoncrawler-3
- Generated: 2026-02-22T10:48:25-05:00

---

Good. Three idle cycles completed (Campaign Roster Management, Character Notes refinement, Status + Access Matrix). Remaining candidates from prior ranking: QA Smoke Test Matrix skeleton (ROI 5) and Terminology glossary (ROI 3). Also identified: `ba-dungeoncrawler.instructions.md` self-improvement (ROI 4). The queue has items `-4` and `-5` with stale ROI values (both show 0 or 1). I'll produce the QA Smoke Test skeleton now and recommend roi.txt corrections for the queue.

---

- Status: done
- Summary: Completed the fourth BA idle cycle for dungeoncrawler. Three candidates were ranked by ROI; the top item — the QA Smoke Test Matrix skeleton — was completed in full. This is the 60-day roadmap item ("Create QA smoke test matrix artifact for dungeoncrawler") that has never been produced. Its absence means QA currently has no consolidated test reference: scenarios are buried across six PM outbox artifacts, two BA cycle artifacts, and the acceptance criteria file, requiring QA agents to do a full archive search before writing a single test plan. The completed artifact below consolidates every manually-verifiable scenario from all existing dungeoncrawler artifacts into one structured matrix organized by feature area and role, with preconditions, steps, expected results, and test type (automated PHPUnit vs manual smoke). It covers the current P0 sprint (status encoding + non-owner access), Campaign Roster Management, and Character Notes, plus cross-cutting concerns (cache leakage, idempotency, anonymous access). Two queue corrections are also required: executor must write `roi.txt = 4` to inbox item `-4` (ba-dungeoncrawler.instructions.md self-improvement) and `roi.txt = 3` to inbox item `-5` (Terminology glossary) so the executor processes them in the correct order.

## Next actions
- Executor persists this artifact to `sessions/ba-dungeoncrawler/outbox/20260222-idle-requirements-improvement-dungeoncrawler-3.md`.
- Executor updates `sessions/ba-dungeoncrawler/inbox/20260222-idle-requirements-improvement-dungeoncrawler-4/roi.txt` → `4`.
- Executor updates `sessions/ba-dungeoncrawler/inbox/20260222-idle-requirements-improvement-dungeoncrawler-5/roi.txt` → `3`.
- QA uses this matrix as the primary test planning input when the dungeoncrawler repo is accessible; no derivation from scattered artifacts required.
- Dev uses the "automated PHPUnit" rows as the test stub list when writing tests.
- PM/CEO: this matrix references 9 open items (Q1–Q5, OI-1–OI-5); resolving them in the Status + Access Matrix Part 7 table will automatically unblock the [OPEN] rows here.

## Blockers
- None. All source material is in existing HQ artifacts.

## Needs from CEO
- None for this cycle.

## ROI estimate
- ROI: 5
- Rationale: QA currently has no consolidated test reference; every test planning session requires a full archive search across 6+ PM outbox files. This matrix eliminates that rework permanently and provides a ready-to-execute test plan the moment the repo path is provided, reducing QA sprint startup from hours to minutes.

---

## 3 Candidates Ranked by ROI

| Rank | Improvement | ROI | Rationale |
|------|-------------|-----|-----------|
| 1 | **QA Smoke Test Matrix skeleton** | 5 | 60-day roadmap item, never produced. Consolidates all manual and automated test scenarios from all BA/PM artifacts. Zero repo dependency. Eliminates QA ramp-up search overhead permanently. |
| 2 | **ba-dungeoncrawler.instructions.md self-improvement** | 4 | Instructions file is 17 lines with no idle-cycle deduplication guard, no accumulated open-items reference, and no queue discipline rule. Each idle cycle currently re-derives context from scratch. Adding a "prior work" pointer and dedup rule would cut per-cycle startup cost by ~50%. Queued as inbox item `-4`. |
| 3 | **Terminology/Definitions glossary** | 3 | Resolves recurring ambiguity in "owner," "campaign access," "roster access" across artifacts. Lower urgency: most critical terms are now implicitly defined across BA cycles 1–3. Queued as inbox item `-5`. |

---

## Requirements Artifact: QA Smoke Test Matrix — dungeoncrawler

### Purpose
Single consolidated test reference for `dungeoncrawler_content`. QA uses this to write PHPUnit tests and execute manual smoke runs. Dev uses the automated rows as test stub names. Updated when new sprints are scoped.

### Sources synthesized
- `sessions/pm-dungeoncrawler/artifacts/20260220-dcc-standardization-review/pm-review.md`
- `sessions/pm-dungeoncrawler/artifacts/20260220-define-next-sprint/01-acceptance-criteria.md`
- `sessions/pm-dungeoncrawler/outbox/20260220-product-documentation-v4.md`
- `sessions/pm-dungeoncrawler/outbox/20260221-idle-feature-request-dungeoncrawler.md` (Roster Management)
- `sessions/pm-dungeoncrawler/outbox/20260222-idle-feature-request-dungeoncrawler.md` (Character Notes)
- `sessions/ba-dungeoncrawler/outbox/20260221-idle-requirements-improvement-dungeoncrawler.md`
- `sessions/ba-dungeoncrawler/outbox/20260222-idle-requirements-improvement-dungeoncrawler.md`
- `sessions/ba-dungeoncrawler/outbox/20260222-idle-requirements-improvement-dungeoncrawler-2.md` (Status + Access Matrix)

### Legend
- **Type**: `PHPUnit` = automated test required; `Smoke` = manual verification; `Both` = automated + manual
- **Sprint**: which sprint delivers this scenario
- **[OPEN]**: depends on unresolved open item (see Status + Access Matrix Part 7)

---

## Section 1: Status Encoding (Current P0 Sprint)

| # | Scenario | User/Role | Precondition | Steps | Expected result | Type | Sprint |
|---|---------|-----------|--------------|-------|-----------------|------|--------|
| SE-01 | Character status int `0` normalizes to `active` | System | Character exists with DB `status = 0` | Call `StatusNormalizer::normalize(0, 'character')` | Returns `'active'` | PHPUnit | P0 |
| SE-02 | Character status int `1` normalizes to `incomplete` | System | Character exists with DB `status = 1` | Call `StatusNormalizer::normalize(1, 'character')` | Returns `'incomplete'` | PHPUnit | P0 |
| SE-03 | Character status int `2` normalizes to `archived` | System | Character exists with DB `status = 2` | Call `StatusNormalizer::normalize(2, 'character')` | Returns `'archived'` | PHPUnit | P0 |
| SE-04 | Campaign status string `'active'` normalizes to `active` | System | Campaign exists with DB `status = 'active'` | Call `StatusNormalizer::normalize('active', 'campaign')` | Returns `'active'` | PHPUnit | P0 |
| SE-05 | Campaign status string `'archived'` normalizes to `archived` | System | Campaign exists with DB `status = 'archived'` | Call `StatusNormalizer::normalize('archived', 'campaign')` | Returns `'archived'` | PHPUnit | P0 |
| SE-06 | Strict comparison: int `0` does not equal string `''` | System | — | Compare `0 === ''` | `false` — no false positive from loose `==` | PHPUnit | P0 |
| SE-07 | `incomplete` not valid for campaign | System | — | Call `StatusNormalizer::normalize('incomplete', 'campaign')` | Throws `\InvalidArgumentException` | PHPUnit | P0 |
| SE-08 | No raw int literals in query conditions | System | Module source at `dungeoncrawler_content/src/` | `grep -r '[^a-z]0\|[^a-z]1\|[^a-z]2' src/ --include="*.php"` filtered for status conditions | Zero matches (all conditions use `StatusNormalizer`) | Smoke | P0 |
| SE-09 | Character roster excludes `archived` characters | Authenticated user | Characters with status `0`, `1`, `2` in DB | Load roster/selection view | Only `active` (int 0) and `incomplete` (int 1) characters visible; `archived` (int 2) absent | Both | P0 |
| SE-10 | Campaign list excludes `archived` campaigns | Authenticated owner | Campaigns with status `'active'` and `'archived'` in DB | Load campaign list | Only `active` campaigns visible; `archived` absent | Both | P0 |
| SE-11 | CSS class on character card matches canonical status | Authenticated user | Character with int `0`, `1`, `2` status | Render character card for each status value | CSS class = `active`, `incomplete`, `archived` respectively | Smoke | P0 |

---

## Section 2: Non-Owner Access Control (Current P0 Sprint)

| # | Scenario | User/Role | Precondition | Steps | Expected result | Type | Sprint |
|---|---------|-----------|--------------|-------|-----------------|------|--------|
| AC-01 | Owner archives their own campaign | Campaign owner | Campaign exists, status = `active` | POST to campaign archive route | HTTP 200/redirect; campaign status = `archived`; disappears from list | Both | P0 |
| AC-02 | Owner unarchives their own campaign | Campaign owner | Campaign exists, status = `archived` | POST to campaign unarchive route | HTTP 200/redirect; campaign status = `active`; reappears in list | Both | P0 |
| AC-03 | Non-owner cannot archive campaign | Non-owner (auth) | Campaign owned by User A; session = User B | POST to User A's campaign archive route | HTTP 403 before any DB write | PHPUnit | P0 |
| AC-04 | Non-owner cannot unarchive campaign | Non-owner (auth) | Campaign owned by User A, archived; session = User B | POST to User A's campaign unarchive route | HTTP 403 before any DB write | PHPUnit | P0 |
| AC-05 | Anonymous cannot archive campaign | Anonymous | Campaign exists | POST to campaign archive route (no session) | HTTP 302 redirect to login | PHPUnit | P0 |
| AC-06 | Owner archives their own character | Character owner | Character exists, status = `active` (DB `0`) | POST to character archive route | HTTP 200/redirect; DB status = `2`; character absent from roster | Both | P0 |
| AC-07 | Owner unarchives their own character | Character owner | Character exists, DB status = `2` | POST to character unarchive route | HTTP 200/redirect; DB status = `0`; character visible in roster | Both | P0 |
| AC-08 | Non-owner cannot archive character | Non-owner (auth) | Character owned by User A; session = User B | POST to User A's character archive route | HTTP 403 before any DB write | PHPUnit | P0 |
| AC-09 | Non-owner cannot unarchive character | Non-owner (auth) | Character owned by User A, archived; session = User B | POST to User A's character unarchive route | HTTP 403 before any DB write | PHPUnit | P0 |
| AC-10 | Anonymous cannot archive character | Anonymous | Character exists | POST to character archive route (no session) | HTTP 302 redirect to login | PHPUnit | P0 |
| AC-11 | Admin can archive any campaign | Admin | Campaign owned by User A | POST to User A's campaign archive route as Admin | HTTP 200/redirect; campaign archived | PHPUnit | P0 |
| AC-12 | Admin can archive any character | Admin | Character owned by User A | POST to User A's character archive route as Admin | HTTP 200/redirect; character archived | PHPUnit | P0 |

---

## Section 3: Idempotency (Current P0 Sprint)

| # | Scenario | User/Role | Precondition | Steps | Expected result | Type | Sprint |
|---|---------|-----------|--------------|-------|-----------------|------|--------|
| ID-01 | Archive already-archived campaign is no-op | Owner | Campaign status = `archived` | POST to archive route | HTTP 200 or 204; no error; status still `archived` | PHPUnit | P0 |
| ID-02 | Unarchive already-active campaign is no-op | Owner | Campaign status = `active` | POST to unarchive route | HTTP 200 or 204; no error; status still `active` | PHPUnit | P0 |
| ID-03 | Archive already-archived character is no-op | Owner | Character DB status = `2` | POST to character archive route | HTTP 200 or 204; no error; DB status still `2` | PHPUnit | P0 |
| ID-04 | Unarchive already-active character is no-op | Owner | Character DB status = `0` | POST to character unarchive route | HTTP 200 or 204; no error; DB status still `0` | PHPUnit | P0 |

---

## Section 4: Cache Integrity (Current P0 Sprint)

| # | Scenario | User/Role | Precondition | Steps | Expected result | Type | Sprint |
|---|---------|-----------|--------------|-------|-----------------|------|--------|
| CA-01 | User A's campaigns not visible to User B | User A, User B | User A has campaigns; User B has none | Log in as User A, view campaign list. Log out. Log in as User B, view campaign list. | User B sees no campaigns; User A's campaigns not leaked | Smoke | P0 |
| CA-02 | Archived campaign not visible after archive | Owner | Campaign archived | Log out and back in; view campaign list | Archived campaign absent (no stale cache hit) | Smoke | P0 |
| CA-03 | Character roster varies by user (ownership scope) | User A, User B | Each user has own characters | Log in as each user; view roster | Each user sees only their own characters in roster | Smoke | P0 |

---

## Section 5: Campaign Roster Management (Upcoming Sprint — Pending Q1–Q5)

| # | Scenario | User/Role | Precondition | Steps | Expected result | Type | Open item |
|---|---------|-----------|--------------|-------|-----------------|------|-----------|
| RM-01 | DM assigns active character to campaign | DM (owner) | Campaign active; character status `active` | POST `/campaign/{id}/roster/add`, body `{character_id}` | HTTP 200; pivot record created; character in roster view | PHPUnit | — |
| RM-02 | DM assigns incomplete character to campaign | DM (owner) | Campaign active; character status `incomplete` | POST `/campaign/{id}/roster/add`, body `{character_id}` | HTTP 200; pivot record created; character in roster view | PHPUnit | — |
| RM-03 | DM cannot assign archived character | DM (owner) | Campaign active; character DB status `2` | POST `/campaign/{id}/roster/add`, body `{character_id}` | HTTP 400; message: "Character is archived"; no pivot record | PHPUnit | — |
| RM-04 | Non-owner cannot assign character | Non-owner (auth) | Campaign owned by User A; session = User B | POST to User A's campaign roster add route | HTTP 403; no DB write | PHPUnit | — |
| RM-05 | Non-owner cannot remove character | Non-owner (auth) | Campaign owned by User A; session = User B | POST to User A's campaign roster remove route | HTTP 403; no DB write | PHPUnit | — |
| RM-06 | Anonymous cannot assign character | Anonymous | Campaign exists | POST to roster add route (no session) | HTTP 302 redirect to login | PHPUnit | — |
| RM-07 | Assign same character twice is idempotent | DM (owner) | Character already on campaign roster | POST `/campaign/{id}/roster/add` again | HTTP 200/204; no duplicate pivot record; no error | PHPUnit | — |
| RM-08 | Remove character not on roster is no-op | DM (owner) | Character not on campaign roster | POST `/campaign/{id}/roster/remove`, body `{character_id}` | HTTP 200/204; no error | PHPUnit | — |
| RM-09 | Roster view excludes archived characters | Authenticated | Character assigned; then character archived | View campaign roster | Archived character absent from roster display | Both | — |
| RM-10 | Removal does not affect character status | DM (owner) | Character on roster, status `active` | Remove character from roster | Character status still `active`; character exists; pivot record deleted | PHPUnit | — |
| RM-11 | DM can view their own campaign roster | DM (owner) | Characters assigned to campaign | Navigate to campaign page | Assigned non-archived characters listed | Smoke | — |
| RM-12 | Non-owner authenticated reads roster | Non-owner (auth) | Characters assigned to campaign | Navigate to campaign page as non-owner | [OPEN — Q3] roster visible or 403 | Smoke | Q3 |
| RM-13 | Anonymous cannot read roster | Anonymous | Characters assigned to campaign | GET campaign page (no session) | 302 redirect to login | Smoke | Q2 |
| RM-14 | Roster management blocked on archived campaign | DM (owner) | Campaign archived | POST to roster add/remove route | [OPEN — Q4] 4xx or redirect | PHPUnit | Q4 |

---

## Section 6: Character Notes and Session Log (Upcoming Sprint — Pending OI-1–OI-5)

| # | Scenario | User/Role | Precondition | Steps | Expected result | Type | Open item |
|---|---------|-----------|--------------|-------|-----------------|------|-----------|
| CN-01 | Owner creates character note | Character owner | Character exists | POST `/character/{id}/notes`, body `{body, session_tag}` | HTTP 201; note record created with correct `character_id` FK | PHPUnit | OI-1 (schema) |
| CN-02 | Owner edits character note | Character owner | Note exists | PUT `/character/{id}/notes/{note_id}`, updated body | HTTP 200; body updated; `updated_at` changed; `created_at` unchanged | PHPUnit | OI-4 |
| CN-03 | Owner deletes character note | Character owner | Note exists | DELETE `/character/{id}/notes/{note_id}` | HTTP 200/204; note record deleted; character entity unaffected | PHPUnit | — |
| CN-04 | Empty note body rejected | Character owner | Character exists | POST with `body = ""` | HTTP 400; "Note body is required"; no record created | PHPUnit | — |
| CN-05 | Note with no session tag is valid | Character owner | Character exists | POST with `session_tag` omitted | HTTP 201; note created; `session_tag` = null | PHPUnit | — |
| CN-06 | Non-owner cannot create character note | Non-owner (auth) | Character owned by User A; session = User B | POST to User A's character notes route | HTTP 403; no DB write | PHPUnit | — |
| CN-07 | Non-owner cannot edit character note | Non-owner (auth) | Note exists on User A's character; session = User B | PUT to User A's character note route | HTTP 403; no DB write | PHPUnit | OI-4 |
| CN-08 | Non-owner cannot delete character note | Non-owner (auth) | Note exists on User A's character; session = User B | DELETE to User A's character note route | HTTP 403; no DB write | PHPUnit | — |
| CN-09 | Non-owner read access to character notes | Non-owner (auth) | Notes exist on User A's character | GET character detail page as User B | [OPEN — OI-3] notes visible or 403 | Smoke | OI-3 |
| CN-10 | Anonymous cannot read character notes | Anonymous | Notes exist | GET character detail page (no session) | 302 redirect to login | Smoke | — |
| CN-11 | Notes sorted newest first | Character owner | 3+ notes with different `created_at` | GET character detail page | Most recently created note appears first | Both | OI-5 |
| CN-12 | Notes paginated at 20 per page | Character owner | 21+ notes on character | GET character detail page | First page shows 20 notes; pager visible | Smoke | OI-5 |
| CN-13 | FK column references character custom PK, not uid | Dev/System | New character note insert | Inspect note record after create | `character_id` = character entity PK; not user uid | PHPUnit | OI-1 |
| CN-14 | Owner creates campaign session note | Campaign owner | Campaign exists | POST `/campaign/{id}/notes`, body `{body, session_tag}` | HTTP 201; note record created with correct `campaign_id` FK | PHPUnit | OI-1 |
| CN-15 | Non-owner cannot create campaign note | Non-owner (auth) | Campaign owned by User A; session = User B | POST to User A's campaign notes route | HTTP 403; no DB write | PHPUnit | — |
| CN-16 | Campaign session notes visible to non-owner | Non-owner (auth) | Notes exist on User A's campaign | Navigate to campaign page as User B | Campaign notes visible (same access as campaign detail) | Smoke | — |

---

## Section 7: Cross-Cutting Manual Smoke Sequence

This is the end-to-end smoke run that QA executes as a final gate before each sprint is approved. Steps build on each other within each sprint.

### Sprint 1 (Status Encoding + Non-Owner Access) smoke sequence
1. Log in as User A → create campaign → verify in campaign list with no `archived` styling.
2. Archive campaign → verify disappears from list.
3. Unarchive → verify reappears.
4. Log out → log in as User B → POST to User A's archive route → verify 403.
5. Log in as User A → create character → verify in roster with CSS class `active`.
6. Archive character → verify disappears from roster.
7. Unarchive character → verify reappears with CSS class `active`.
8. Log out → log in as User B → POST to User A's character archive route → verify 403.
9. Log out → POST to any archive route (no session) → verify 302 redirect to login.
10. Log in as User A, view campaign list. Log out. Log in as User B → verify User A's campaigns not visible (cache integrity: CA-01).

### Sprint 2 (Campaign Roster Management) smoke sequence
Prerequisites: Sprint 1 approved.
1. Log in as DM (User A) → navigate to campaign → assign active character (own or [OPEN Q1]) → verify character appears in roster.
2. Remove character from roster → verify character disappears; character still exists with unchanged status.
3. Attempt to assign archived character → verify 400 error.
4. Log out → log in as User B → POST to User A's roster add route → verify 403.
5. Log in as User A → assign character → log out → log in as User B → verify roster read behavior per Q3 resolution.
6. [Q4] Archive campaign → attempt roster mutation → verify behavior per Q4 resolution.

### Sprint 3 (Character Notes) smoke sequence
Prerequisites: Sprint 1 approved; Sprint 2 approved or OI-3 resolved independently.
1. Log in as User A → navigate to character → add note with session tag → verify note appears at top of list (newest first).
2. Edit note → verify `updated_at` changes; `created_at` unchanged.
3. Add 21+ notes → verify pagination at 20/page with pager navigation.
4. Delete a note → verify note gone; character entity unchanged.
5. Log out → log in as User B → POST to User A's character note route → verify 403.
6. Log out → GET character detail page (no session) → verify 302 redirect.
7. Log in as campaign owner → add session note to campaign → verify note appears on campaign page.
8. Log in as User B → view campaign page → verify campaign session notes readable.

---

## Maintenance rule

| When | Who | What to update |
|------|-----|----------------|
| Route paths confirmed | dev-dungeoncrawler | Fill in all `[TBD]` route paths referenced in Sections 2–6 step columns |
| Q1–Q5 resolved | pm-dungeoncrawler | Update `[OPEN]` rows in Section 5 with definitive expected results |
| OI-1–OI-5 resolved | pm-dungeoncrawler | Update `[OPEN]` rows in Section 6 with definitive expected results |
| New sprint scoped | ba-dungeoncrawler / pm-dungeoncrawler | Add new section to this matrix before sprint kickoff |
| Test added or renamed | dev-dungeoncrawler | Confirm PHPUnit method names in Section headers match implementation |
