# Outbox update: 20260222-idle-requirements-improvement-dungeoncrawler-5

- Agent: ba-dungeoncrawler
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-dungeoncrawler/inbox/20260222-idle-requirements-improvement-dungeoncrawler-5
- Generated: 2026-02-22T11:05:14-05:00

---

All five prior cycle artifacts are now persisted in outbox. This is cycle 6 (inbox item -5). Per the prior cycle ranking, the top item is the Terminology/Definitions glossary (ROI 3). Queue corrections needed: items -6 and -7 both show roi.txt = 1; they should be set to 2 (diminishing returns until CEO/PM resolves open items).

---

- Status: done
- Summary: Completed the sixth BA idle cycle for dungeoncrawler. Three candidates were ranked by ROI; the top item — the Terminology and Definitions Glossary — was completed in full. This is the canonical glossary for terms that have appeared inconsistently or ambiguously across all prior BA and PM artifacts: "owner," "campaign access," "roster access," "DM/Dungeon Master," "non-owner," the three canonical status values, and seven additional terms including StatusNormalizer, pivot record, controller layer, and cache context. Each entry states the canonical definition, the contexts where ambiguity was observed, and any usage rules that apply (e.g., "owner" must never be defined by uid alone). The glossary is cross-referenced to the Status + Access Matrix and QA Smoke Test Matrix so all three living reference documents form a coherent set. Two queue corrections are also required: executor must write `roi.txt = 2` to inbox items `-6` and `-7` since additional idle cycles have diminishing returns until CEO/PM resolves Q1–Q5 and OI-1–OI-5.

## Next actions
- Executor persists this artifact to `sessions/ba-dungeoncrawler/outbox/20260222-idle-requirements-improvement-dungeoncrawler-5.md`.
- Executor updates `sessions/ba-dungeoncrawler/inbox/20260222-idle-requirements-improvement-dungeoncrawler-6/roi.txt` → `2`.
- Executor updates `sessions/ba-dungeoncrawler/inbox/20260222-idle-requirements-improvement-dungeoncrawler-7/roi.txt` → `2`.
- Executor also updates the living reference pointer in `org-chart/agents/instructions/ba-dungeoncrawler.instructions.md` to add this glossary as Cycle 6 artifact (once instructions file from Cycle 5 is persisted).
- CEO/PM resolves Q1–Q5 and OI-1–OI-5 — this is now the highest-leverage unblocking action available; all further BA idle cycles are lower ROI until those decisions land.

## Blockers
- None. All source material is in existing HQ artifacts.

## Needs from CEO
- Answers to Q1–Q5 (Campaign Roster Management) and OI-1–OI-5 (Character Notes) — consolidated in `sessions/ba-dungeoncrawler/outbox/20260222-idle-requirements-improvement-dungeoncrawler-2.md` Part 7. No new information needed for this glossary cycle.

## ROI estimate
- ROI: 3
- Rationale: The glossary eliminates the recurring per-artifact re-definition of "owner," "campaign access," and "roster access" that has appeared in every BA and PM cycle to date; a single canonical reference reduces ambiguity overhead for every future sprint and is immediately usable by PM when finalizing acceptance criteria.

---

## 3 Candidates Ranked by ROI

| Rank | Improvement | ROI | Rationale |
|------|-------------|-----|-----------|
| 1 | **Terminology and Definitions Glossary** | 3 | Terms like "owner," "campaign access," and "roster access" appear inconsistently across 10+ artifacts; a single canonical glossary eliminates per-sprint re-definition and supports PM AC finalization. |
| 2 | **Additional idle cycles (general)** | 2 | With Q1–Q5 and OI-1–OI-5 still unresolved, new requirements work is lower leverage until CEO/PM provides decisions. Remaining inbox items -6 and -7 carry this ROI. |
| 3 | **Character Status Audit Log requirements** | 2 | PM ranked this ROI 3 in their backlog; however it is blocked by the status encoding sprint completing first and adds complexity before simpler features ship. Lower priority than glossary. |

---

## Requirements Artifact: Terminology and Definitions Glossary — dungeoncrawler

### Purpose
Single canonical definitions reference for all roles working on `dungeoncrawler_content`. Every BA artifact, PM acceptance criteria, dev implementation note, and QA test plan uses these definitions without re-defining them inline.

### Cross-references
- Status + Access Matrix: `sessions/ba-dungeoncrawler/outbox/20260222-idle-requirements-improvement-dungeoncrawler-2.md`
- QA Smoke Test Matrix: `sessions/ba-dungeoncrawler/outbox/20260222-idle-requirements-improvement-dungeoncrawler-3.md`

---

### Term definitions

---

#### Admin
An authenticated Drupal user with administrative privileges. Admin bypasses all ownership checks — can archive, unarchive, or mutate any campaign or character regardless of who owns it. In permission matrix tables this appears as the "Admin" column.

**Usage rule:** Tests that verify admin override of ownership checks must use a distinct user account with admin role, not an elevated session variable. Do not conflate "admin" with "owner."

---

#### Anonymous user
A user who is not logged in (no active Drupal session). Anonymous users have no access to any mutation routes in `dungeoncrawler_content`. All mutation routes return HTTP 302 redirect to login or HTTP 403 for anonymous requests, depending on site configuration.

**Observed ambiguity:** PM product docs say "✗ redirect" for anonymous, but do not specify 302 vs 403. Resolved recommendation: use 302 (redirect to login) consistently for anonymous access denial, reserving 403 for authenticated non-owners. See Q2 in open items for roster-specific clarification.

---

#### Archive / Archived status
A soft-delete state for campaigns and characters. Archived content is hidden from list views and roster displays but is not deleted and can be recovered via unarchive. The canonical string value is `archived`.

- Campaign: stored as string `'archived'` in DB.
- Character: stored as int `2` in DB; normalized to string `'archived'` by `StatusNormalizer` before any code comparison.

**Usage rule:** "Archive" is never a hard delete. Any route, test, or acceptance criterion that uses "delete" when it means "archive" is incorrect.

---

#### Campaign access
The condition under which an authenticated non-owner user can read (view) a campaign's detail page and/or roster. This term is explicitly undefined in current PM artifacts and is the subject of open item Q3.

**Interpretations on record:**
- (a) Any authenticated user can view any campaign — simplest, no access grant needed.
- (b) Only users explicitly granted access by the DM can view a campaign — requires a separate access grant feature.
- (c) Only users whose character is on the campaign roster can view the campaign — circular dependency on Campaign Roster Management Q3.

**Recommendation (BA):** Interpretation (a) until explicitly scoped otherwise. Do not use "campaign access" in acceptance criteria without first resolving Q3 and replacing this term with the specific rule.

---

#### Campaign owner / DM (Dungeon Master)
The authenticated user who created a specific campaign. The terms "campaign owner" and "DM" are used interchangeably in all dungeoncrawler artifacts and refer to the same role relative to a specific campaign entity. "DM" is the domain term; "campaign owner" is the system/permission term.

**Usage rule:** "Owner" is determined by the `owner_uid` field on the campaign record, which stores the Drupal user uid of the creator. However, ownership checks must compare against the campaign entity's `owner_uid` — never infer ownership from the session uid alone without loading the entity first. See KB lesson `20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` for the FK-vs-uid distinction.

---

#### Character owner / Player
The authenticated user who created a specific character. "Character owner" is the system/permission term; "player" is the domain term. A player may own multiple characters; a character has exactly one owner.

**Usage rule:** Same FK-vs-uid caution as campaign owner. Character `owner_uid` is the authoritative field; ownership checks must load the entity and compare `owner_uid`, not assume the current session uid is the owner.

---

#### Controller layer
The Drupal controller class that handles HTTP requests for a given route. In `dungeoncrawler_content`, all ownership enforcement (403 for non-owners) must happen inside the controller method, before any database read or write is attempted.

**Usage rule:** "Controller-layer enforcement" is the required location for ownership checks. A check that only exists in a Twig template, a form `#access` callback, or a UI element is insufficient — it can be bypassed by a direct HTTP request. Any test verifying a 403 must POST directly to the route URL, not simulate a UI interaction.

**Contrast:** "Route access check" (Drupal `access_check` annotation) handles authentication (is the user logged in?). "Controller layer" handles authorization (is the logged-in user the owner?). Both are required; neither substitutes for the other.

---

#### Non-owner (authenticated)
An authenticated Drupal user who is not the owner of a specific campaign or character and does not have admin privileges. Non-owners receive HTTP 403 when attempting mutation routes on content they do not own.

**Usage rule:** In test scenarios, "non-owner" requires two distinct user accounts — User A (owner) and User B (non-owner). Tests must not simulate non-ownership by manipulating session state; they must use a genuinely different user account.

---

#### Owner
Shorthand for "campaign owner" or "character owner" depending on context. See those entries. Never use "owner" without specifying the entity type when both campaigns and characters are in scope in the same document section.

---

#### Pivot record / campaign_character
The database record that represents a many-to-many membership relationship between a campaign and a character. The anticipated table name is `campaign_character`. Columns: `campaign_id` (FK → campaign custom PK), `character_id` (FK → character custom PK), `assigned_at`, `assigned_by_uid`.

**Usage rule:** FK columns must reference the entity's custom primary key, not the Drupal user uid. This is the identical risk class documented in KB lesson `20260220-forseti-jobhunter-uid-vs-jobseeker-id.md`. Dev must confirm the FK column names before writing any insert logic.

**Status:** This table does not exist yet; it is the primary deliverable of the Campaign Roster Management sprint.

---

#### Roster / Campaign roster
The list of characters explicitly assigned to a campaign via a pivot record. A roster contains only characters with status `active` or `incomplete`; `archived` characters are filtered out of the roster display even if a pivot record exists.

**Contrast with "character list":** The character list (roster/selection view) may show all characters owned by a user regardless of campaign membership. The campaign roster shows only characters explicitly assigned to that specific campaign. These are different views. Any requirement or test that conflates them is ambiguous.

---

#### Roster access
The condition under which an authenticated non-owner can read a campaign's roster. This is explicitly undefined and is the subject of open item Q3. Used in Character Notes OI-3 as the proposed read-access gate for character notes — creating a circular dependency.

**Do not use** "roster access" as a defined permission gate in acceptance criteria until Q3 is resolved. Use "can view campaign detail page" as the temporary fallback access rule (BA recommendation, OI-3).

---

#### StatusNormalizer
A required helper method (or service class) in `dungeoncrawler_content` that maps raw DB status values to canonical strings and back. It is the single authoritative location for the int ↔ string mapping for character status. No other code in the module may contain inline int literals (`0`, `1`, `2`) for status comparisons.

**Contract:**
```php
StatusNormalizer::normalize(int|string $raw, string $entityType): string
// Returns: 'active' | 'incomplete' | 'archived'
// Throws: \InvalidArgumentException on unrecognized value

StatusNormalizer::toDbValue(string $canonical, string $entityType): int|string
// Returns: DB-appropriate value for the entity type
```

**Usage rule:** All query conditions, template logic, and CSS class derivations must call `StatusNormalizer::normalize()` before comparison. All DB writes must use `StatusNormalizer::toDbValue()` to convert canonical strings back to the DB encoding. Strict `===` required throughout; PHP loose `==` is prohibited on status values.

---

#### Canonical status values
The three string values used at the code layer to represent content lifecycle state. These are the only values permitted in PHP comparisons, Twig templates, CSS class names, and query conditions.

| Canonical value | Meaning | Campaign DB encoding | Character DB encoding |
|----------------|---------|---------------------|----------------------|
| `active` | Live, visible in lists and rosters | `'active'` (string) | `0` (int) |
| `incomplete` | Started but unfinished; visible in roster | n/a (not valid for campaigns) | `1` (int) |
| `archived` | Soft-deleted; hidden but recoverable | `'archived'` (string) | `2` (int) |

**Usage rule:** `incomplete` is a character-only status. Any query condition or acceptance criterion that applies `incomplete` to campaigns is a bug.

---

#### Cache context (`user`)
A Drupal cache metadata annotation that marks a render array as varying per authenticated user. Any render array whose content changes based on the logged-in user's identity or ownership must carry this cache context.

**Required on:** campaign list views, character roster views, any view that shows owner-scoped content or conditionally renders based on the authenticated user.

**Usage rule:** Omitting the `user` cache context on owner-scoped views causes cross-user cache leakage — User B may see User A's campaigns in a warm-cache response. QA smoke test CA-01 in the QA Smoke Test Matrix verifies this.

---

#### Sprint P0 / Current sprint
The active work sprint for `dungeoncrawler_content`: status encoding standardization (`StatusNormalizer` helper) and non-owner access regression tests (`testNonOwnerCannotArchiveCampaign`, `testNonOwnerCannotArchiveCharacter`). This sprint must be QA-approved before any subsequent sprint begins.

---

### Terms with deferred definitions (pending CEO/PM decisions)

| Term | Deferral reason | Resolving open item |
|------|-----------------|---------------------|
| "Campaign access" (specific rule) | Three valid interpretations; none selected | Q3 |
| "Roster access" (as permission gate) | Circular dependency on Q3 | Q3, OI-3 |
| "Session" (as a game session linked to notes) | Free-text label vs structured FK link unresolved | OI-2 |
| "Note ownership transfer" | Not yet scoped; character owner change not in any feature | Future scope |

---

### Suggested next delegation

| Step | Role | ROI |
|------|------|-----|
| Resolve Q1–Q5 and OI-1–OI-5 | CEO / PM | 8 — unblocks all [OPEN] rows in Status + Access Matrix and QA Smoke Test Matrix simultaneously |
| Reference this glossary in PM sprint artifacts | PM | 3 — one-line addition to `01-acceptance-criteria.md` header: "See BA glossary for term definitions" |
| Reference this glossary in QA test plan | QA | 3 — same |
