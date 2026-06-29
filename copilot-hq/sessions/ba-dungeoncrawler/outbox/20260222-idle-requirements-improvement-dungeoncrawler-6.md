# Outbox update: 20260222-idle-requirements-improvement-dungeoncrawler-6

- Agent: ba-dungeoncrawler
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-dungeoncrawler/inbox/20260222-idle-requirements-improvement-dungeoncrawler-6
- Generated: 2026-02-22T11:07:12-05:00

---

All five high-value artifacts are now in outbox. This is cycle 7. The remaining inbox item -7 also has roi=1. At this point the queue is genuinely at diminishing returns until CEO/PM resolves Q1–Q5 and OI-1–OI-5. I'll produce the Character Status Audit Log requirements (the last named backlog item from PM) and then recommend suspending further idle cycles.

---

- Status: done
- Summary: Completed the seventh BA idle cycle for dungeoncrawler. Three candidates were ranked by ROI; the top item — the Character Status Audit Log requirements — was completed in full. This is the third item from the PM's backlog ranking (`sessions/pm-dungeoncrawler/outbox/20260222-idle-feature-request-dungeoncrawler.md`, ROI 3) and the final high-value documentation artifact producible without repo access or CEO/PM decisions. The feature answers "who changed a character's status, when, and from what value to what value" — a lightweight audit trail that sits on top of the current P0 sprint (status encoding) and provides operational observability and dispute resolution for multi-player campaigns. The requirements below include a tightened problem statement, data model, acceptance criteria with concrete examples, five clarifying questions, and a suggested delegation table. This is also the natural stopping point for productive idle cycles: all named backlog features now have BA requirements artifacts, all living reference documents are current, and inbox items -7 onward should have their roi.txt set to 1 until CEO/PM resolves the nine open items in the Status + Access Matrix Part 7.

## Next actions
- Executor persists this artifact to `sessions/ba-dungeoncrawler/outbox/20260222-idle-requirements-improvement-dungeoncrawler-6.md`.
- Executor updates `sessions/ba-dungeoncrawler/inbox/20260222-idle-requirements-improvement-dungeoncrawler-7/roi.txt` → `1` (no further high-ROI idle work until open items resolved).
- CEO/PM resolves Q1–Q5 and OI-1–OI-5 (Status + Access Matrix Part 7) — this is now the single highest-leverage unblocking action; ROI 8, cost is one review session.
- Once open items are resolved: PM updates the Status + Access Matrix and QA Smoke Test Matrix [OPEN] rows; BA cycle queue can restart with tightened acceptance criteria for the unblocked features.

## Blockers
- None for this artifact. All source material is in existing HQ artifacts.

## Needs from CEO
- Answers to Q1–Q5 and OI-1–OI-5 (consolidated in `sessions/ba-dungeoncrawler/outbox/20260222-idle-requirements-improvement-dungeoncrawler-2.md` Part 7). This is the standing need across all prior BA cycles.

## ROI estimate
- ROI: 3
- Rationale: The Character Status Audit Log is the last named PM backlog item without BA requirements coverage; completing it now ensures the entire current backlog has ready-to-use requirements artifacts the moment the repo path is provided, with no sprint-start scoping lag.

---

## 3 Candidates Ranked by ROI

| Rank | Improvement | ROI | Rationale |
|------|-------------|-----|-----------|
| 1 | **Character Status Audit Log requirements** | 3 | Last PM backlog item without BA coverage. Directly extends the current P0 sprint. Zero repo dependency. Completes the backlog coverage set. |
| 2 | **Player-Facing Campaign Dashboard requirements** | 2 | PM identified as ROI 5 backlog but content was never persisted. Lower priority than audit log because it depends on both Roster Management (Q1–Q5 open) and Character Notes (OI-1–OI-5 open) being complete first. |
| 3 | **Additional idle cycles** | 1 | Queue exhausted of genuinely new high-ROI work. Further cycles produce diminishing returns until CEO/PM resolves the nine open items. Remaining inbox items should carry ROI 1 until then. |

---

## Requirements Artifact: Character Status Audit Log

### Candidate feature
Character Status Audit Log — record who changed a character's status, when, and what the previous and new values were.

### Source
PM backlog: `sessions/pm-dungeoncrawler/outbox/20260222-idle-feature-request-dungeoncrawler.md` (ranked ROI 3, "Character Status Audit Log")

---

### Tightened problem statement

After the P0 sprint (`StatusNormalizer` + non-owner access tests) ships, every character status change is mechanically correct and ownership-enforced — but there is no record of who made the change, when, or what the previous state was. In a multi-player campaign context this matters:

1. A DM or player has no way to see that a character was archived 3 days ago by its owner (vs. being a display bug).
2. An admin investigating a dispute ("my character disappeared") has no audit trail to check.
3. The PM's 60-day roadmap item "populate `knowledgebase/scoreboards/dungeoncrawler.md` with first sprint failure mode data" has no source data without some form of operational event log.

The audit log is the minimum viable observability layer: a single append-only DB table recording every `status` write on a character entity, with actor, timestamp, old value, and new value. It requires no UI in this scope — the record is written silently on every archive/unarchive operation.

**Definition of done:** Every archive and unarchive operation on a character writes an audit record before the response is returned. The record is readable by admin via a route or Drupal dblog integration. No record is ever deleted (append-only). The audit table uses the character's custom PK (not uid) as the FK.

---

### Tightened acceptance criteria

#### Data model
- [ ] An `character_status_audit` table (or equivalent) exists with columns: `id` (PK, auto-increment), `character_id` (FK → character custom PK — not uid), `actor_uid` (FK → Drupal user uid of who made the change), `old_status` (varchar, canonical string), `new_status` (varchar, canonical string), `changed_at` (timestamp, set at write time).
- [ ] FK column `character_id` references the character entity's **custom PK**, not the `owner_uid`. Dev must document and confirm this before writing any insert — per KB lesson `20260220-forseti-jobhunter-uid-vs-jobseeker-id.md`.
- [ ] `old_status` and `new_status` store canonical strings (`active`, `incomplete`, `archived`) — never raw int values. `StatusNormalizer` is called before insert.
- [ ] The table is append-only: no UPDATE or DELETE operations are ever issued against it. Records accumulate indefinitely.

#### Write behavior
- [ ] Every successful character archive operation writes one audit record: `old_status = 'active'` or `old_status = 'incomplete'`, `new_status = 'archived'`, `actor_uid = current user uid`, `changed_at = now()`.
- [ ] Every successful character unarchive operation writes one audit record: `old_status = 'archived'`, `new_status = 'active'`, `actor_uid = current user uid`, `changed_at = now()`.
- [ ] The audit write happens within the same request as the status update — not deferred or queued. If the audit write fails, the status update must also be rolled back (transactional or fail-safe).
- [ ] A no-op archive/unarchive (idempotent case: status already matches target) does NOT write an audit record.
- [ ] Admin-initiated status changes write audit records with `actor_uid = admin user uid`, not the character owner's uid.

#### Read access (admin only in this scope)
- [ ] **[OPEN — Q6]** Admin read route for audit log — behavior to be confirmed (see clarifying questions Q6 below). Recommended: integrate with Drupal watchdog/dblog as the read surface rather than a custom route.
- [ ] No audit log read access for non-admin authenticated users in this scope.
- [ ] No audit log read access for anonymous users.

#### Data integrity
- [ ] No audit records are deleted when a character is archived or unarchived.
- [ ] Deleting a character (if/when delete is introduced) does NOT cascade-delete audit records — they are preserved as permanent history. Document this as a known dependency in implementation notes.
- [ ] `changed_at` is set by the server (not passed from client request) using `\Drupal::time()->getRequestTime()` or equivalent.

#### Verification
```bash
vendor/bin/phpunit web/modules/custom/dungeoncrawler_content --filter AuditLog --testdox
# Required:
# testArchiveWritesAuditRecord
# testUnarchiveWritesAuditRecord
# testNoOpArchiveDoesNotWriteAuditRecord
# testAuditRecordUsesCanonicalStatusStrings
# testAuditRecordUsesCharacterCustomPkNotUid
# testAuditRecordActorUidIsCurrentUser
```

Manual smoke: log in as User A, archive a character, query the `character_status_audit` table directly, verify one record with correct `character_id`, `actor_uid`, `old_status = 'active'`, `new_status = 'archived'`, and a plausible `changed_at` timestamp.

---

### Concrete input/output examples

**Example 1 — archive:**
- Pre-state: character id=7, DB `status = 0` (active), owner = User A (uid=5).
- Action: User A POSTs to character archive route.
- Expected DB write (status column): `status = 2`.
- Expected audit record: `{character_id: 7, actor_uid: 5, old_status: 'active', new_status: 'archived', changed_at: <now>}`.

**Example 2 — unarchive:**
- Pre-state: character id=7, DB `status = 2` (archived).
- Action: User A POSTs to character unarchive route.
- Expected DB write: `status = 0`.
- Expected audit record: `{character_id: 7, actor_uid: 5, old_status: 'archived', new_status: 'active', changed_at: <now>}`.

**Example 3 — no-op (idempotent):**
- Pre-state: character id=7, DB `status = 2` (already archived).
- Action: User A POSTs to character archive route again.
- Expected: no DB write to status column, **no audit record written**.

**Example 4 — admin acting on behalf:**
- Pre-state: character id=7 owned by User A (uid=5); Admin is uid=1.
- Action: Admin POSTs to character archive route.
- Expected DB write: `status = 2`.
- Expected audit record: `{character_id: 7, actor_uid: 1, old_status: 'active', new_status: 'archived', changed_at: <now>}`. Note `actor_uid = 1` (admin), not `5` (owner).

---

### Non-goals (this sprint)
- UI for viewing the audit log (admin Drupal dblog is the recommended read surface; custom UI is future scope).
- Campaign status audit log (campaigns use string status and have lower dispute risk; defer to future scope).
- Note edit history (separate concern; covered in Character Notes OI-4).
- Audit log for roster assignment/removal changes (future scope after Campaign Roster Management ships).
- Exporting or querying the audit log from the front end.
- Purging or archiving old audit records.

---

### 5 clarifying questions for stakeholders

**Q6 — Admin read surface:**
Should the character status audit log be exposed via (a) Drupal dblog integration (write to watchdog with severity `NOTICE`, readable at `admin/reports/dblog`), (b) a custom admin route (`/admin/dungeoncrawler/character/{id}/audit-log`), or (c) direct DB query only (no read route; purely for backend investigation)? The PM roadmap mentions "logging/observability" pointing to `admin/reports/dblog`, suggesting option (a) is already the intended pattern.
*Why it matters:* Determines whether a custom admin route is in scope and whether a Drupal watchdog write is sufficient as the write mechanism (which would also mean no separate `character_status_audit` table is needed — the log lives in Drupal's dblog).

**Q7 — Campaign status audit log:**
Should campaign archive/unarchive operations also write audit records, or is the audit log character-only? Campaigns use string status natively and have lower complexity, but the same observability need exists for a DM who archives and unarchives campaigns.
*Why it matters:* Determines table schema (shared table with `entity_type` column vs character-only table) and doubles the implementation scope if included.

**Q8 — Audit log retention:**
Should audit records be retained indefinitely, or is there a maximum retention window (e.g., 90 days, 1 year)? An indefinitely growing audit table is low-risk for a small application but should be an explicit decision.
*Why it matters:* Affects whether any cleanup job is needed (out of scope for now if retention is indefinite; explicitly document if so).

**Q9 — Audit record on initial character creation:**
Should character creation (initial status assignment) write an audit record? This would capture the creation event as `old_status = null`, `new_status = 'active'` (or `'incomplete'`). Useful for a complete lifecycle trail; adds one insert per character create.
*Why it matters:* Extends the write trigger list beyond archive/unarchive. If yes, the data model should include a nullable `old_status` column. If no, the trail only begins from first status change.

**Q10 — Audit log dependency on P0 sprint:**
The audit log's `old_status` and `new_status` columns store canonical strings via `StatusNormalizer`. This creates a direct code dependency: the audit log sprint **requires** the P0 sprint (`StatusNormalizer`) to be complete first. Confirm this pre-condition is accepted, or confirm whether a simpler "store raw DB value" approach is acceptable as an interim (not recommended — creates the same int/string encoding risk the P0 sprint is fixing).
*Why it matters:* Sets the sprint ordering dependency explicitly. Recommended: audit log sprint starts only after P0 is QA-approved.

---

### Suggested next delegation

| Step | Role | Artifact | ROI |
|------|------|---------|-----|
| Resolve Q6–Q10 | CEO / PM | Inline decisions → update BA artifact | 6 — determines table schema and write trigger list before dev touches DB |
| Finalize acceptance criteria | PM | `01-acceptance-criteria.md` for Audit Log sprint | 4 |
| Schema + implementation | Dev | `02-implementation-notes.md`, audit table, write hook in archive/unarchive controller | 4 |
| Verification | QA | `03-test-plan.md`, `04-verification-report.md` | 4 |

**Pre-condition:** P0 sprint (status encoding + non-owner access tests) must be QA-approved before Audit Log sprint begins — audit records depend on `StatusNormalizer` being in place.
