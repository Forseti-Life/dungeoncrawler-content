# Outbox update: 20260221-idle-requirements-improvement-dungeoncrawler

- Agent: ba-dungeoncrawler
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-dungeoncrawler/inbox/20260221-idle-requirements-improvement-dungeoncrawler
- Generated: 2026-02-21T23:49:34-05:00

---

Now I have enough context. Let me create the outbox artifact.

The executor handles outbox persistence (as is org-wide practice). The complete outbox artifact is below for the executor to write to `sessions/ba-dungeoncrawler/outbox/20260222-idle-requirements-improvement-dungeoncrawler.md`:

---

- Status: done
- Summary: Completed the BA idle cycle for dungeoncrawler. The highest-value requirements improvement is a tightened problem statement and sharpened acceptance criteria for the Campaign Roster Management feature (the next planned sprint after status-encoding/access-tests ships). The PM's feature request is well-structured but carries five concrete ambiguities that would cause rework if handed to dev as-is: (1) "access to a campaign" in the roster-read permission is undefined; (2) whether a DM can assign characters owned by other users without consent is unstated; (3) roster management behavior when a campaign is archived is absent; (4) removal semantics (hard-delete vs soft-removal for audit) are unspecified; and (5) character browsability scope for the assignment UI is unclear. All five are itemized as stakeholder questions with rationale below, alongside a tightened problem statement, sharpened acceptance criteria with concrete input/output examples, and delegation table.

## Next actions
- Executor persists this artifact to `sessions/ba-dungeoncrawler/outbox/20260222-idle-requirements-improvement-dungeoncrawler.md`.
- CEO/PM reviews the five clarifying questions and provides decisions so PM can finalize `01-acceptance-criteria.md` before the Roster Management sprint begins.
- Pre-condition remains: status-encoding + non-owner access tests sprint must be QA-approved before Roster Management implementation begins (roster query correctness depends on `StatusNormalizer`).

## Blockers
- None blocking this artifact.

## Needs from CEO
- Answers to Q1–Q5 (below) before PM finalizes roster management acceptance criteria.

---

## Requirements Artifact: Campaign Roster Management

### Candidate feature
Campaign Roster Management — Assign and Remove Characters from a Campaign

### Source
PM feature request: `sessions/pm-dungeoncrawler/outbox/20260221-idle-feature-request-dungeoncrawler.md`

---

### Tightened problem statement

Campaigns and characters in `dungeoncrawler_content` are independent entities with no explicit membership relationship. As a result:

1. There is no defined schema, route, or permission model for a Dungeon Master (DM) to assign specific characters to a campaign.
2. Any roster display currently either shows all characters site-wide or relies on an undocumented implicit join — a correctness risk compounded by the unresolved status-encoding mismatch (current sprint).
3. There is no data contract governing what "being on a campaign roster" means: no FK-validated pivot record, no defined lifecycle (is roster membership preserved when a character is archived?), and no stated ownership rule for the pivot record itself.

The gap blocks: meaningful multi-campaign play, accurate roster display once `StatusNormalizer` lands, and a defined permission surface for player-facing roster visibility.

**Definition of done (feature-level):** A DM can assign and remove characters from their campaign roster via controller-backed routes; the roster view displays only explicitly assigned, non-archived characters; all mutation routes enforce DM ownership at the controller layer (403 pre-DB-write); pivot table schema is documented and FK-validated.

---

### Sharpened acceptance criteria

Items marked **[OPEN]** depend on stakeholder answers (Q1–Q5 below).

#### Data model
- [ ] A `campaign_character` pivot table (or equivalent) exists with columns: `campaign_id` (FK → campaign PK), `character_id` (FK → character PK), `assigned_at` (timestamp), `assigned_by_uid`.
- [ ] FK columns reference the **custom entity PK** (not `uid`) — per KB lesson `20260220-forseti-jobhunter-uid-vs-jobseeker-id.md`.
- [ ] Removing a character from a roster hard-deletes the pivot record. The character entity and status are unaffected.

#### Assignment (DM action)
- [ ] DM assigns a character via `POST /campaign/{id}/roster/add`, body `character_id`.
- [ ] Only `active` or `incomplete` characters can be assigned; assigning an `archived` character returns HTTP 400 with a user-safe error message.
- [ ] **[OPEN — Q1]** DM character visibility scope for assignment to be confirmed.
- [ ] Assigning an already-assigned character is a no-op (200/204, no duplicate record).
- [ ] A character may be on multiple campaigns simultaneously (many-to-many).

#### Removal (DM action)
- [ ] DM removes a character via `POST /campaign/{id}/roster/remove`, body `character_id`.
- [ ] Removing a character not on the roster is a no-op (200/204, no error).
- [ ] Removal does not change the character's status or any other character field.

#### Roster display (read)
- [ ] Roster view shows only characters with an explicit pivot record AND status `active` or `incomplete` (requires `StatusNormalizer` from current sprint).
- [ ] **[OPEN — Q2]** Anonymous access to roster read — behavior to be confirmed.
- [ ] **[OPEN — Q3]** Non-owner authenticated user read access — behavior to be confirmed.
- [ ] Roster render array carries `user` cache context.

#### Campaign archived state
- [ ] **[OPEN — Q4]** Roster mutation behavior when campaign is archived — to be confirmed.
- [ ] Regardless: archiving a campaign does NOT delete pivot records (preserved for unarchive recovery).

#### Permissions (controller-enforced)
- [ ] DM (owner): can add/remove characters from their campaign.
- [ ] Admin: can add/remove from any campaign.
- [ ] Non-owner authenticated: 403 on add/remove before any DB write.
- [ ] Anonymous: 403 or redirect to login on mutation routes.

#### Data integrity
- [ ] All FK values validated against entity schema before insert — no raw uid used as character_id or campaign_id.
- [ ] Document campaign-delete cascade dependency in dev implementation notes (even if delete is out of scope now).

#### Verification
```bash
vendor/bin/phpunit web/modules/custom/dungeoncrawler_content --filter Roster --testdox
# Required:
# testDmCanAssignActiveCharacterToCampaign
# testDmCannotAssignArchivedCharacter
# testNonOwnerCannotAddCharacterToCampaign
# testNonOwnerCannotRemoveCharacterFromCampaign
# testAssignmentIsIdempotent
# testRemovalOfUnassignedCharacterIsNoOp
# testRosterDisplayExcludesArchivedCharacters
```

---

### Concrete input/output examples

**Example 1 — happy path assign:**
- `POST /campaign/42/roster/add` `{"character_id": 7}`, session = DM owner, character 7 = `active`, not yet on roster.
- Expected: HTTP 200, pivot record created, character 7 appears in roster.

**Example 2 — archived character rejected:**
- `POST /campaign/42/roster/add` `{"character_id": 9}`, character 9 = `archived`.
- Expected: HTTP 400, message: "Character is archived and cannot be assigned." No pivot record created.

**Example 3 — non-owner rejected:**
- `POST /campaign/42/roster/add` `{"character_id": 7}`, session = User B (not campaign owner).
- Expected: HTTP 403 before any DB read/write.

**Example 4 — idempotent assign:**
- `POST /campaign/42/roster/add` `{"character_id": 7}`, character 7 already on campaign 42.
- Expected: HTTP 200 or 204. No duplicate record. No error.

---

### Non-goals (unchanged from PM artifact)
- Invitation/notification flows on assignment.
- Character creation from within roster view.
- Role/class assignment within a campaign.
- UI changes to character detail page.
- Player self-enrollment.
- Campaign delete implementation (cascade dependency documentation only).

---

### 5 clarifying questions for stakeholders

**Q1 — Character visibility scope for assignment:**
When a DM opens the assign-character UI, which characters are browsable — only characters the DM owns, or characters owned by any authenticated user on the site? The PM draft says "any active character (owned by any user)" but if a DM can assign another user's character without consent, that needs to be an intentional product decision.
*Why it matters:* Drives the assignment query scope, permission model, and whether players need a "my character's campaign memberships" view.

**Q2 — Anonymous access to roster read:**
Should anonymous users be able to view campaign rosters (read-only)? Product docs say "✗ redirect" for character roster but the exact behavior (redirect-to-login vs 403) and whether any campaigns are intended to be publicly visible in the future should be confirmed.
*Why it matters:* Determines `access_check` annotation and cache context strategy (`user.roles` vs `user`).

**Q3 — Non-owner authenticated read access:**
For an authenticated user who is not the campaign DM: can they view the roster of (a) any campaign on the site, (b) only campaigns they've been explicitly granted access to, or (c) only campaigns where one of their characters is assigned? "Campaigns they have access to" (PM language) is undefined.
*Why it matters:* If the answer is "any authenticated user can read any roster," implementation is simple. If access grants are required, that is a separate feature that needs its own scope definition before Roster Management can be fully spec'd.

**Q4 — Roster management on an archived campaign:**
If a campaign is archived, should the DM be able to add/remove characters from its roster? Should the roster still be readable (e.g., for historical reference)? This case is absent from the PM acceptance criteria.
*Why it matters:* Without a rule, dev will make an implicit choice. An archived campaign with unguarded mutation routes is a subtle permission surface.

**Q5 — Removal semantics (hard-delete vs soft-removal):**
Is roster removal a hard-delete of the pivot record (no history preserved), or should there be a soft-removal model (`removed_at`, `removed_by_uid`) for audit purposes? The PM draft implies hard-delete but doesn't state it explicitly.
*Why it matters:* Affects schema design before dev starts. A soft-removal model is significantly more complex and should be explicitly scoped in or out.

---

### Suggested next delegation

| Step | Role | Artifact |
|------|------|---------|
| Answer Q1–Q5 | CEO / PM | Inline decisions → update this BA artifact |
| Finalize acceptance criteria | PM | `01-acceptance-criteria.md` for Roster Management sprint |
| Schema pre-work (pivot table existence) | Dev | Implementation notes pre-work |
| Sprint kickoff (after current sprint ships) | PM | `00-problem-statement.md`, `01-acceptance-criteria.md`, `06-risk-assessment.md` |
| Implementation | Dev | `02-implementation-notes.md`, code, tests |
| Verification | QA | `03-test-plan.md`, `04-verification-report.md` |
