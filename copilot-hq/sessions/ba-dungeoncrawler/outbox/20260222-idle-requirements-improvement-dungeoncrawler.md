# Outbox update: 20260222-idle-requirements-improvement-dungeoncrawler

- Agent: ba-dungeoncrawler
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-dungeoncrawler/inbox/20260222-idle-requirements-improvement-dungeoncrawler
- Generated: 2026-02-22T09:45:03-05:00

---

I now have full context. The previous BA cycle covered Campaign Roster Management. The PM's latest filed Character Notes and Session Log with an explicit delegation to BA for requirements refinement (ROI 5 in their table). That's the highest-ROI work item — it unblocks PM from finalizing acceptance criteria and dev from starting schema design, and it has concrete ambiguities that would cause schema rework if left to dev to decide.

---

# Outbox update: 20260222-idle-requirements-improvement-dungeoncrawler

- Agent: ba-dungeoncrawler
- Source inbox: sessions/ba-dungeoncrawler/inbox/20260222-idle-requirements-improvement-dungeoncrawler
- Generated: 2026-02-22T14:42:00Z

---

- Status: done
- Summary: Completed the BA idle cycle for dungeoncrawler. Three requirements improvements were ranked by ROI; the top item — Character Notes and Session Log requirements refinement — was completed in full. The PM explicitly delegated this to BA (ROI 5 in their delegation table, `20260222-idle-feature-request-dungeoncrawler.md`) and the feature carries five concrete ambiguities that would cause schema rework if handed to dev unresolved: (1) single polymorphic table vs two separate entities is an architectural fork point the PM left implicit; (2) session tag linking between character notes and campaign session summaries is undefined; (3) non-owner read access via "roster access" is a circular dependency on the five unresolved Campaign Roster Management questions; (4) note edit semantics (mutable vs append-only) are unstated and affect whether any audit trail is needed; (5) pagination/ordering has a "newest first" specification but no page-size or fallback rule. All five are addressed below with recommended defaults, a tightened data model, sharpened acceptance criteria, and a delegation table with ROI estimates.

## Next actions
- Executor persists this artifact to `sessions/ba-dungeoncrawler/outbox/20260222-idle-requirements-improvement-dungeoncrawler.md`.
- PM reviews five open items and either adopts the recommended defaults or overrides them, then finalizes `01-acceptance-criteria.md` for Character Notes sprint.
- Pre-condition chain: Status Encoding sprint → QA-approved → Campaign Roster Management sprint → QA-approved → Character Notes sprint begins. Notes read-access depends on Roster Management Q3 being resolved first.

## Blockers
- None blocking this artifact.

## Needs from CEO
- None for this cycle. Ongoing standing blocker (dungeoncrawler repo path) affects dev/QA but not BA documentation work.

## ROI estimate
- ROI: 7
- Rationale: The PM explicitly delegated this BA refinement with ROI 5; surfacing the schema architecture fork (polymorphic table vs two entities) alone is worth preventing a mid-sprint rewrite. Completing this now keeps the requirements pipeline ahead of dev and avoids a scoping lag when the repo path is provided.

---

## 3 Candidates Ranked by ROI

| Rank | Improvement | ROI | Rationale |
|------|-------------|-----|-----------|
| 1 | **Character Notes and Session Log — BA requirements refinement** | 7 | PM explicitly delegated; schema architecture fork must be resolved before dev touches the DB; circular read-access dependency on Campaign Roster Management must be surfaced now, not mid-sprint. |
| 2 | **QA Smoke Test Matrix skeleton — dungeoncrawler living doc** | 5 | 60-day roadmap item; all test scenarios currently buried across PM outbox artifacts; a single reference doc reduces QA ramp-up when repo becomes accessible; no repo dependency. |
| 3 | **Dev environment setup skeleton** | 3 | 90-day roadmap item; dungeoncrawler repo path unknown so a template/placeholder is the maximum deliverable; lower leverage until the repo path is confirmed. |

---

## Requirements Artifact: Character Notes and Session Log

### Candidate feature
Character Notes and Session Log — per-character and per-campaign free-text notes with optional session tagging

### Source
PM feature request: `sessions/pm-dungeoncrawler/outbox/20260222-idle-feature-request-dungeoncrawler.md`

---

### Tightened problem statement

After lifecycle management (archive/unarchive) and roster assignment are in place, dungeoncrawler has no persistent narrative layer. Players cannot record what happened to their character per session. DMs cannot attach a summary to a campaign session. Without this, the app is a roster manager with no return engagement beyond setup.

The PM's feature request is well-framed but contains five implementation-blocking ambiguities (below). The most critical is the data model decision: the PM proposes a single polymorphic `note` table with an `entity_type` column dispatching to character or campaign. This is a fork point — two separate entity tables produce safer FK constraints and simpler queries, while a single table is more DRY but requires type-dispatch logic and makes FK enforcement harder in SQL. **This must be decided before dev touches the schema.**

**Definition of done (feature-level):** Authenticated character owners can write, edit, and delete notes on their own characters; campaign owners can write, edit, and delete session summary notes on their own campaigns; notes are displayed on the relevant detail page (newest first, paginated); all mutation routes enforce ownership at the controller layer (403 pre-DB-write); FK columns reference entity custom PKs (not uid); data model decision is documented in implementation notes before any insert logic is written.

---

### Open items with recommended defaults

The following five items must be resolved by PM before dev begins. Recommended defaults are provided; PM should explicitly accept or override each.

#### OI-1 — Data model: polymorphic single table vs two separate entities

**PM proposal:** single `note` table, `entity_type` column = `character` | `campaign`.

**Ambiguity:** A polymorphic table means `entity_id` FK cannot be enforced at the DB layer against both character PK and campaign PK simultaneously (SQL FK can only reference one table per column). Enforcing FK integrity requires application-layer checks, which introduces the same class of bug already documented in KB lesson `20260220-forseti-jobhunter-uid-vs-jobseeker-id.md`.

**Recommendation:** Use **two separate tables**: `character_note` and `campaign_session_note`. Each has a hard FK to the respective entity PK. This is more verbose but eliminates the FK ambiguity risk, produces simpler queries, and makes permissions straightforward. Both tables share the same columns (`id`, `entity_id`, `author_uid`, `body`, `session_tag`, `created_at`, `updated_at`).

**Decision needed from PM:** Accept two-table approach, or accept polymorphic with explicit acknowledgment that FK enforcement is application-layer only?

---

#### OI-2 — Session tag: independent annotation vs structured link to campaign session

**PM proposal:** `session_tag` is a free-text nullable varchar on character notes (e.g., "Session 3"). Campaign session summaries also have a free-text title/tag.

**Ambiguity:** If a character note is tagged "Session 3" and the campaign has a session summary also labeled "Session 3," should these be linked navigably (character note → campaign session summary, and vice versa)? The PM does not state this. If not linked, the session tag is purely a cosmetic label. If linked, it implies a `session_id` FK from `character_note` to `campaign_session_note`, which is a more complex schema and introduces a dependency ordering problem (session summary must exist before character notes can reference it).

**Recommendation:** Session tag is **independent free-text only** in this scope — no FK link between character notes and campaign session summaries. A player types "Session 3" as a label for their own reference; it is not validated against or linked to any campaign session summary record. Cross-referencing is future scope. This keeps the schema simple and the two note types fully independent.

**Decision needed from PM:** Accept free-text-only session tag (no FK link), or is navigable linking a requirement in this sprint?

---

#### OI-3 — Non-owner read access: circular dependency on Campaign Roster Management Q3

**PM proposal:** "Authenticated non-owners can read character notes if they have campaign roster access to that character."

**Ambiguity:** "Campaign roster access" is undefined. Campaign Roster Management (filed 2026-02-21) has five open questions, including Q3: "For an authenticated user who is not the campaign DM: can they view the roster of any campaign, only campaigns they've been explicitly granted access to, or only campaigns where one of their characters is assigned?" Until Q3 is resolved, the character note read access rule is also undefined.

**Recommendation:** Use a simple fallback rule independent of Campaign Roster Management Q3: **any authenticated user who can view a character's detail page can read that character's notes**. Character detail page visibility is already governed by the access control model from the lifecycle sprint. This decouples the Character Notes sprint from the Campaign Roster Management open questions entirely. Access can be tightened to roster-membership-scoped once Q3 is resolved.

**Decision needed from PM:** Accept "can view character detail page → can read notes" as the access rule, or is roster-membership-scoped read access a hard requirement for this sprint?

---

#### OI-4 — Note edit semantics: mutable vs append-only

**PM proposal:** "Create, edit, and delete" notes — edit is included.

**Ambiguity:** For a session log (historical record), mutable edits allow retroactive revision of what happened, which can cause disputes in a multi-player context. The PM does not state whether edit history is needed (e.g., `updated_at` timestamp as lightweight audit vs no audit at all). Without explicit guidance, dev may either add no audit trail (undesirable for a log) or over-engineer a full revision history (out of scope).

**Recommendation:** Notes are **mutable** (edits allowed), `updated_at` timestamp is written on every edit as the only audit trail. No full edit history in this scope. The `updated_at` vs `created_at` difference is visible in the UI as a "(edited)" indicator if desired. Append-only enforcement is future scope if disputes arise.

**Decision needed from PM:** Accept mutable-with-`updated_at` model, or is append-only required?

---

#### OI-5 — Pagination / default sort / page size

**PM proposal:** "Newest first" — no page size or pagination behavior specified.

**Ambiguity:** A character in a long-running campaign accumulates notes over many sessions. Without a page size, the roster detail page could render dozens of notes in a single query, degrading performance.

**Recommendation:** Default sort `created_at DESC`, page size **20 notes per page**, Drupal pager for navigation. This is the standard Drupal list pattern. No infinite scroll in this scope.

**Decision needed from PM:** Accept 20-per-page paginated list, or is a different page size / no pagination required?

---

### Sharpened acceptance criteria

Items marked **[OPEN — OI-n]** depend on PM resolving the corresponding open item above. All other items are unambiguous.

#### Data model (assumes OI-1 resolved as two-table)
- [ ] Two separate tables exist: `character_note` and `campaign_session_note`.
- [ ] `character_note`: columns `id` (PK), `character_id` (FK → character custom PK — not uid), `author_uid` (FK → Drupal user uid), `body` (text, NOT NULL), `session_tag` (varchar, nullable), `created_at` (timestamp), `updated_at` (timestamp).
- [ ] `campaign_session_note`: columns `id` (PK), `campaign_id` (FK → campaign custom PK — not uid), `author_uid`, `body` (NOT NULL), `session_tag` (nullable), `created_at`, `updated_at`.
- [ ] Dev documents FK column choices in implementation notes before any insert logic is written — mandatory per KB lesson `20260220-forseti-jobhunter-uid-vs-jobseeker-id.md`.
- [ ] **[OPEN — OI-1]** If PM accepts polymorphic table instead: document that `entity_id` FK is application-layer-only; dev must include explicit validation before every insert.

#### Character notes (player-facing)
- [ ] Authenticated character owner can create a note on their own character: `POST /character/{id}/notes`, body `{body: text, session_tag: nullable}`.
- [ ] Character owner can edit their own note: `PUT /character/{id}/notes/{note_id}`, updates `body` and/or `session_tag`, sets `updated_at`.
- [ ] Character owner can delete their own note: `DELETE /character/{id}/notes/{note_id}`. Hard-delete of the note record. Character entity unaffected.
- [ ] Empty `body` is rejected with HTTP 400: "Note body is required."
- [ ] `session_tag` is optional; omitting it is valid.
- [ ] Notes are displayed on the character detail page, sorted `created_at DESC`, paginated at 20 per page.
- [ ] **[OPEN — OI-4]** If PM requires append-only: remove edit endpoint from scope.

#### Campaign session notes (DM-facing)
- [ ] Campaign owner can create a session summary note: `POST /campaign/{id}/notes`, body `{body: text, session_tag: nullable}`.
- [ ] Campaign owner can edit and delete their own campaign session notes (same rules as character notes above).
- [ ] Session notes displayed on campaign detail page, sorted `created_at DESC`, paginated at 20 per page.

#### Read access
- [ ] **[OPEN — OI-3]** If PM accepts "can view character detail page → can read notes": character notes are visible to any user who can access the character detail route.
- [ ] **[OPEN — OI-3]** If PM requires roster-scoped read access: note read route must check roster membership — defer this until Campaign Roster Management Q3 is resolved.
- [ ] Any authenticated user who can view a campaign can read that campaign's session notes (consistent with campaign list access rules).
- [ ] Anonymous users: 403 or redirect to login on all note routes (read and write).
- [ ] Note render arrays carry `user` cache context.

#### Permissions (controller-enforced, not UI-only)
- [ ] Character owner and admin: create/edit/delete character notes.
- [ ] Non-owner authenticated user: 403 on character note create/edit/delete before any DB write.
- [ ] Campaign owner and admin: create/edit/delete campaign session notes.
- [ ] Non-owner authenticated user: 403 on campaign note create/edit/delete before any DB write.
- [ ] Anonymous: 403 or redirect to login on all mutation routes.

#### Data integrity
- [ ] Deleting a character does **not** cascade-delete `character_note` records in this scope (orphaned notes are known tech debt — document in implementation notes).
- [ ] Deleting a campaign does **not** cascade-delete `campaign_session_note` records in this scope (same).
- [ ] Note `updated_at` is set on every edit. `created_at` is immutable after insert.

#### Verification
```bash
vendor/bin/phpunit web/modules/custom/dungeoncrawler_content --filter Notes --testdox
# Required passing:
# testNonOwnerCannotCreateCharacterNote
# testOwnerCanCreateCharacterNote
# testOwnerCanEditCharacterNote
# testOwnerCanDeleteCharacterNote
# testEmptyNoteBodyRejected
# testNonOwnerCannotCreateCampaignNote
# testOwnerCanCreateCampaignNote
# testCharacterNotesSortedNewestFirst
```

Manual smoke:
1. Log in as character owner → navigate to character detail → add note with session tag → verify note appears, sorted newest first.
2. Edit the note → verify `updated_at` changes, `created_at` unchanged.
3. Log in as different user → POST to character note create route → expect 403.
4. Log in as campaign owner → navigate to campaign detail → add session note → verify it appears.
5. Anonymous GET on character detail → redirect to login (no notes visible).

---

### Concrete input/output examples

**Example 1 — happy path character note create:**
- `POST /character/7/notes`, body `{"body": "Found the Sword of Ages. Session was intense.", "session_tag": "Session 3"}`, session = character owner.
- Expected: HTTP 201, note record created with `character_id = 7`, `author_uid = current user uid`. Note appears at top of character detail page.

**Example 2 — empty body rejected:**
- `POST /character/7/notes`, body `{"body": "", "session_tag": "Session 3"}`, session = character owner.
- Expected: HTTP 400, message: "Note body is required." No record created.

**Example 3 — non-owner rejected:**
- `POST /character/7/notes`, body `{"body": "I was there too"}`, session = User B (not character owner).
- Expected: HTTP 403 before any DB read/write.

**Example 4 — edit with audit:**
- `PUT /character/7/notes/12`, body `{"body": "Found the Sword of Ages. Nearly died."}`, session = character owner.
- Expected: HTTP 200, `body` updated, `updated_at` = now, `created_at` unchanged. Edited note still sorted by `created_at`.

**Example 5 — campaign session note:**
- `POST /campaign/42/notes`, body `{"body": "The party cleared the dungeon floor. Two players absent.", "session_tag": "Session 3"}`, session = campaign owner (DM).
- Expected: HTTP 201, note appears on campaign detail page.

---

### Non-goals (this sprint)
- Rich-text / markdown rendering (plain text only).
- Note attachments (images, files).
- Navigable link between character notes and campaign session summaries by session tag (free-text label only).
- Full edit revision history (only `updated_at` timestamp as lightweight audit).
- Note export or printing.
- Real-time collaboration or comments on notes.
- Cascade-delete on character or campaign delete (tech debt, document only).
- Player self-assignment to notes or shared note visibility controls.

---

### 5 clarifying questions for stakeholders

**Q1 — Data model architecture (OI-1):**
Should character notes and campaign session notes be stored in two separate tables (`character_note`, `campaign_session_note`) with hard FKs per entity type, or a single polymorphic `note` table with an `entity_type` column and application-layer FK validation? The polymorphic approach introduces the same FK ambiguity risk already documented in KB lesson `20260220-forseti-jobhunter-uid-vs-jobseeker-id.md`. Recommendation: two tables.

**Q2 — Session tag linking (OI-2):**
Is the `session_tag` on a character note purely a cosmetic free-text label (e.g., "Session 3" for the player's own reference), or is it intended to be navigably linked to a campaign session summary with the same tag? If the latter, a `session_id` FK is required and campaign session summaries must exist before character notes can reference them. Recommendation: free-text label only in this sprint; linking is future scope.

**Q3 — Note read access (OI-3):**
Should non-owner read access to character notes be scoped to "anyone who can view the character detail page" (simple, decoupled from Campaign Roster Management), or to "users who are on the same campaign roster as the character" (complex, blocked on Campaign Roster Management Q3)? Recommendation: use character detail page access as the gate; tighten to roster-scoped later once Campaign Roster Management Q3 is resolved.

**Q4 — Edit semantics (OI-4):**
Should character and campaign notes be mutable (editable after creation, with `updated_at` as the only audit trail), or append-only (no edits, delete-and-recreate to correct)? A session log use-case argues for append-only to prevent retroactive revision disputes. Recommendation: mutable with `updated_at` timestamp as lightweight audit; append-only is future scope if disputes arise.

**Q5 — Orphaned notes on entity delete:**
When a character or campaign is deleted (if/when delete is introduced), should their notes be cascade-deleted, soft-deleted, or left as orphans? This is explicitly out of scope for the current sprint, but dev must document the FK dependency in implementation notes so it is not silently introduced as a data hazard later. Confirm: is "orphaned notes = known tech debt, document and defer" the accepted decision, or should cascade delete be added to the acceptance criteria now?

---

### Suggested next delegation

| Step | Role | Artifact | ROI |
|------|------|---------|-----|
| Resolve OI-1 through OI-5 | PM / CEO | Inline decisions → update BA artifact | 8 — unblocks schema design |
| Finalize acceptance criteria (post-decision) | PM | `01-acceptance-criteria.md` for Character Notes sprint | 7 |
| Schema pre-work (confirm FK columns, two-table vs polymorphic) | Dev (exploratory) | Implementation notes pre-work | 6 |
| Sprint kickoff (after Roster Management ships) | PM | `00-problem-statement.md`, `01-acceptance-criteria.md`, `06-risk-assessment.md` | 7 |
| Implementation | Dev | `02-implementation-notes.md`, note CRUD, controller-layer checks, tests | 6 |
| Verification | QA | `03-test-plan.md`, `04-verification-report.md` | 5 |

**Pre-condition chain:** Status Encoding sprint → QA-approved → Campaign Roster Management sprint → QA-approved (Q3 resolved) → Character Notes sprint begins. If PM accepts OI-3 recommended default ("character detail page access" rule), Character Notes sprint can begin independently of Campaign Roster Management Q3.
