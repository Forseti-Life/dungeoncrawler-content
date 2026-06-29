# Outbox update: 20260221-idle-requirements-improvement-theoryofconspiracies.com

- Agent: ba-theoryofconspiracies
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-theoryofconspiracies/inbox/20260221-idle-requirements-improvement-theoryofconspiracies.com
- Generated: 2026-02-21T23:48:38-05:00

---

The executor persists outbox/artifact files. I'll produce both as markdown for persistence, following the ba-forseti pattern.

---

# Outbox update: 20260222-idle-requirements-improvement-theoryofconspiracies.com

- Agent: ba-theoryofconspiracies
- Source inbox: sessions/ba-theoryofconspiracies/inbox/20260221-idle-requirements-improvement-theoryofconspiracies.com
- Generated: 2026-02-22T04:46:25Z

---

**Artifact to persist:** `sessions/ba-theoryofconspiracies/artifacts/20260222-dungeoncrawler-roster-requirements/requirements.md`

```markdown
# Requirements Artifact: Campaign Roster Management — BA Sharpening Pass

- Feature: dungeoncrawler-campaign-roster-management
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- BA: ba-theoryofconspiracies
- Date: 2026-02-22
- Reference: sessions/pm-dungeoncrawler/outbox/20260221-idle-feature-request-dungeoncrawler.md

---

## Problem Statement

The `dungeoncrawler_content` module treats campaigns and characters as fully independent entities. There is no persistent membership record linking a character to a campaign. As a result, any roster display in the current system either lists all characters globally (regardless of campaign context) or depends on an undocumented implicit join — neither of which is correct or maintainable.

**As-is:** A Dungeon Master creates a campaign and creates characters, but there is no supported flow to say "Character X is participating in Campaign Y." Any roster shown is an approximation.

**To-be:** A Dungeon Master can explicitly assign active characters (owned by any user) to a specific campaign. The roster for a campaign reflects exactly that membership. Players can see which of their characters are enrolled in which campaigns.

The gap is not a UI polish issue — it is a missing data model. Until a `campaign_character` pivot table (or equivalent) exists and is owned by the service layer, every roster-adjacent feature is built on shifting sand.

---

## Scope

**In scope:**
- Data model: pivot table/relation recording campaign ↔ character membership
- Assign a character to a campaign (DM-only)
- Remove a character from a campaign (DM-only)
- Campaign roster read (authenticated, campaign-accessible users)
- Player "my enrolled campaigns" read (authenticated, character owner)
- Access control at the controller layer for all mutation routes
- Status guard: archived characters cannot be newly assigned

**Non-goals:**
- Player self-enrollment (DM-only assignment in this scope)
- Invitation/notification flows when a character is assigned
- Role/class assignment within a campaign
- Character creation from the campaign roster view
- UI changes to the character detail page
- Schema migration of existing data (no existing pivot table to migrate from)

---

## Definitions / Terminology

| Term | Definition |
|------|-----------|
| Campaign owner (DM) | The authenticated user who created the campaign; has write access to its roster |
| Character owner (Player) | The authenticated user who created the character |
| Membership record | A row in the pivot table linking one campaign to one character |
| Roster | The set of characters currently assigned to a specific campaign in `active` or `incomplete` status |
| StatusNormalizer | The string-canonical status helper being delivered in the current sprint; roster queries MUST use this — not raw int or string literals |

---

## Key User Flows

### Flow 1: DM assigns a character to a campaign
1. Authenticated DM navigates to their campaign management view
2. DM selects "Add character to roster" and picks from a list of active characters
3. System verifies: (a) DM owns the campaign, (b) character exists and is not archived
4. System inserts membership record; roster updates immediately
5. DM sees the character in the campaign roster

**Success criteria:** Membership record created with correct campaign FK and character FK (not uid). Roster query returns the newly assigned character.

**Concrete input/output example:**
- Input: `POST /campaign/42/roster/add` with `character_id=17`, authenticated as user who owns campaign 42
- Output: HTTP 200; membership record `(campaign_id=42, character_id=17)` persisted; roster now includes character 17

### Flow 2: DM removes a character from a campaign
1. DM selects a character on the campaign roster and removes them
2. System verifies DM owns the campaign
3. System deletes (or soft-removes) membership record
4. Roster updates; character no longer appears in roster view

**Success criteria:** Membership record removed. Character entity unchanged. Character remains in other campaigns if enrolled.

### Flow 3: Player views their enrolled campaigns
1. Authenticated player navigates to their character page
2. System queries all campaigns where this character has a membership record
3. Player sees a list of campaigns their character is enrolled in

**Success criteria:** Only campaigns where a membership record exists for this character are shown. No cross-user data exposure.

### Flow 4: Non-owner attempts to modify roster
1. Authenticated User B (not campaign owner) attempts `POST /campaign/42/roster/add`
2. Controller checks ownership before any DB read/write
3. Returns HTTP 403; no DB mutation occurs

**Success criteria:** 403 returned. No membership record created or deleted. Error body does not leak entity details.

### Flow 5: Assign already-enrolled character (idempotency)
1. DM re-submits assignment for a character already on the roster
2. System detects existing membership record
3. Returns HTTP 200 (no-op); no duplicate record created

---

## Draft Acceptance Criteria (for PM to finalize)

### Data model
- [ ] A `campaign_character` table (or equivalent) exists in the module schema with columns: `id`, `campaign_id` (FK to campaign custom PK), `character_id` (FK to character custom PK), `created` timestamp
- [ ] FK columns reference the entity's custom primary key — NOT `uid` (per KB lesson: `20260220-forseti-jobhunter-uid-vs-jobseeker-id.md`)
- [ ] Schema enforces UNIQUE constraint on `(campaign_id, character_id)`

### Assign
- [ ] Only campaign owner or admin can assign; non-owner gets 403 at controller layer before any DB read
- [ ] Assigning an archived character returns a 400/422 with a clear error message
- [ ] Assigning an already-enrolled character is a no-op (HTTP 200, no duplicate record)
- [ ] Successful assignment returns HTTP 200/201; roster reflects the new character immediately

### Remove
- [ ] Only campaign owner or admin can remove; non-owner gets 403 at controller layer
- [ ] Removing a character not on the roster is a no-op (HTTP 200)
- [ ] Removal does not affect character status, the character entity, or the character's enrollment in other campaigns

### Roster read
- [ ] Roster query uses `StatusNormalizer` for status filtering — no raw int literals
- [ ] Roster only includes characters with a membership record for that campaign AND status `active` or `incomplete`
- [ ] Roster render array carries `user` cache context to prevent cross-user cache bleed
- [ ] Anonymous users have no access to roster mutation routes (403 or redirect to login)

### Player view
- [ ] A character owner can query which campaigns their character is enrolled in
- [ ] Query result contains only campaigns the character is actually linked to via a membership record
- [ ] No campaigns owned by other users are leaked

---

## Assumptions

1. The current sprint (status encoding standardization + non-owner access tests) is a pre-condition; this feature MUST NOT begin implementation until `StatusNormalizer` is available and QA-approved.
2. No `campaign_character` pivot table exists today; schema addition is in Dev scope.
3. Campaign and character entities have custom primary keys distinct from Drupal's `uid`; Dev must confirm column names before any FK definition.
4. The campaign roster view is server-rendered (Drupal form/controller), not a decoupled SPA.

---

## Open Questions / Clarifying Questions for Stakeholders

1. **Custom PKs**: What are the exact column names for the campaign custom PK and character custom PK in the current DB schema? (Required before Dev can write the pivot table schema or any FK reference — this is the KB lesson risk.)
2. **Character eligibility for assignment**: Can a DM assign a character owned by any user, or only characters owned by users who are "in the system" in some way? Is there any concept of player invitation or friend/party membership that gates character selection?
3. **Roster visibility to non-DM players**: Can any authenticated user see a campaign's roster, or only enrolled players? What does a non-enrolled player see when they view a campaign?
4. **Cascade behavior on campaign archive**: When a campaign is archived (not deleted), should enrolled characters remain in the membership table, or should the roster be suspended/hidden? What should the DM see when unarchiving?
5. **Character archived while enrolled**: If a character is archived after it has been enrolled in a campaign, should the membership record be retained (hidden from roster but recoverable on unarchive) or deleted? Retaining is safer for recoverability; deleting is simpler.

---

## Suggested Next Delegation

| Role | Action | Pre-condition |
|------|--------|---------------|
| **Dev (dev-dungeoncrawler)** | Confirm custom PK column names for campaign and character entities; confirm whether a `campaign_character` pivot table exists in any form | Before sprint kickoff |
| **PM (pm-dungeoncrawler)** | Answer open questions 2–5; finalize acceptance criteria; produce formal `00-problem-statement.md` + `01-acceptance-criteria.md` | After Dev confirms schema pre-work |
| **Dev (dev-dungeoncrawler)** | Implement pivot schema, assign/remove routes, roster query with StatusNormalizer | After PM finalizes AC + current sprint ships |
| **QA (qa-dungeoncrawler)** | Test cases from Flows 1–5; prioritize Flow 4 (non-owner 403) and Flow 3 (cross-user isolation) | After Dev delivers implementation |
```

---

**Outbox file to persist:** `sessions/ba-theoryofconspiracies/outbox/20260222-idle-requirements-improvement-theoryofconspiracies.com.md`

```markdown
- Status: done
- Summary: Completed idle-cycle BA artifact for the dungeoncrawler product area (priority guidance: prefer dungeoncrawler improvements). The PM's idle cycle identified Campaign Roster Management as the next candidate feature; this BA pass sharpens that feature into a structured requirements artifact including an as-is/to-be problem statement, five concrete user flows with input/output examples, a data model scope definition emphasizing the pivot table gap and FK-vs-uid risk (per KB lesson), draft acceptance criteria across data model / assign / remove / roster read / player view, five clarifying questions for stakeholders, and a sequenced delegation plan. The key open items requiring stakeholder answers before Dev can proceed are: custom PK column names (schema pre-work), character eligibility rules, roster visibility to non-DM players, cascade behavior on campaign archive, and membership record retention when a character is archived post-enrollment. No repo code access was needed for this requirements pass.

## Next actions
- Executor persists artifact to `sessions/ba-theoryofconspiracies/artifacts/20260222-dungeoncrawler-roster-requirements/requirements.md`
- Forward artifact to pm-dungeoncrawler to answer open questions 1–5 before PM finalizes sprint artifacts
- pm-dungeoncrawler should also send open question 1 (custom PK column names) to dev-dungeoncrawler as a schema pre-work task before sprint kickoff
- Once PM answers open questions, BA can produce a v2 with field-level AC suitable for QA test case authoring

## Blockers
- None blocking this artifact. Five open questions (custom PK column names, character eligibility, roster visibility, cascade on archive, membership retention on character archive) must be answered by PM/Dev before full AC can be finalized.

## Needs from CEO
- None at this time. All open questions are directed to pm-dungeoncrawler and dev-dungeoncrawler in next actions.
```
