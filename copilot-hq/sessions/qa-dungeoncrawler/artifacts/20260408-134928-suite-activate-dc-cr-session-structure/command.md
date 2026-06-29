# Suite Activation: dc-cr-session-structure

- Agent: qa-dungeoncrawler
- Status: pending

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-08T13:49:28+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-session-structure"`**  
   This links the test to the living requirements doc at `features/dc-cr-session-structure/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-session-structure-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-session-structure",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-session-structure"`**  
   Example:
   ```json
   {
     "id": "dc-cr-session-structure-<route-slug>",
     "feature_id": "dc-cr-session-structure",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-session-structure",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-session-structure

## Coverage summary
- AC items: ~16 (session data model, campaign model, one-shot vs. campaign, character state persistence, AI GM context integration, security)
- Test cases: 11 (TC-SES-01–11)
- Suites: playwright (downtime, character creation)
- Security: Session/campaign data user-scoped; invite requires registered account validation

---

## TC-SES-01 — Session data model: all required fields stored
- Description: Session stores: session_id, campaign_id (optional), date, GM user, player users, starting narrative state
- Suite: playwright/downtime
- Expected: session record has all required fields; campaign_id nullable for one-shot; date/GM/players populated
- AC: AC-001

## TC-SES-02 — Session end: character state committed (XP, HP, conditions, inventory)
- Description: Session end commits: character XP, HP, conditions, inventory reflecting end-of-session state
- Suite: playwright/downtime
- Expected: session_end event → all character state fields persisted; subsequent load shows committed state
- AC: AC-001

## TC-SES-03 — Session resume: all persistent state from last session restored
- Description: When a session is resumed, all carry-over character state from the last session is restored
- Suite: playwright/downtime
- Expected: session_resume event → character.xp, hp, conditions, inventory all match last session's committed state
- AC: AC-001

## TC-SES-04 — Campaign model: all required fields stored; session history visible
- Description: Campaign stores: campaign_id, title, GM user, enrolled PCs, narrative notes; session history in chronological order
- Suite: playwright/downtime
- Expected: campaign record has all fields; session_history list ordered chronologically
- AC: AC-002

## TC-SES-05 — XP accumulates correctly across campaign sessions
- Description: Replaying session history shows correct cumulative XP totals across all sessions
- Suite: playwright/downtime
- Expected: each session's XP grant adds to character.total_xp; total correct after n sessions
- AC: AC-002

## TC-SES-06 — One-shot: no persistent campaign state required
- Description: One-shot session completes without requiring campaign linkage; self-contained
- Suite: playwright/downtime
- Expected: session without campaign_id is valid; no campaign state required; no carry-over error
- AC: AC-003

## TC-SES-07 — Campaign session: loads character state from last session
- Description: Starting a campaign session loads the character state committed at the end of the last session
- Suite: playwright/downtime
- Expected: campaign session start → character state = last session's end state; not default/initial state
- AC: AC-003

## TC-SES-08 — Character state carry-over: inventory, HP, conditions all present at session start
- Description: Character entering a session with inventory/HP/conditions has all state present at session start
- Suite: playwright/downtime
- Expected: session start → character.inventory, hp, conditions match last session's end commit
- AC: AC-004

## TC-SES-09 — AI GM context: session summary included in system prompt
- Description: When session has narrative summary, AI GM receives it in system prompt
- Suite: playwright/downtime
- Expected: AI GM context includes session_summary text; absent for first session (graceful)
- AC: AC-005

## TC-SES-10 — AI GM context: prior NPC relationship status surfaced
- Description: Important NPCs from prior sessions have relationship status and last-known state in AI GM context
- Suite: playwright/downtime
- Expected: recurring NPC in campaign → npc.last_known_state and relationship_log included in context
- AC: AC-005

## TC-SES-11 — Campaign invite: invited user must be registered account
- Description: Session invite validates invited user is a registered account
- Suite: playwright/downtime
- Expected: invite to unregistered email/user → rejected with feedback; invite to registered user → accepted
- AC: Security AC

### Acceptance criteria (reference)

# Acceptance Criteria: Session and Campaign Structure
# Feature: dc-cr-session-structure

## AC-001: Session Data Model
- Given a new game session is created, when it is saved, then it records session_id, campaign_id (optional), date, GM user, player users, and starting narrative state
- Given a session ends, when session data is committed, then character XP, HP, conditions, and inventory reflect end-of-session state
- Given a session is resumed, when the character state is loaded, then all persistent state from the last session is restored

## AC-002: Campaign Model
- Given a campaign is created, when saved, then it stores campaign_id, title, GM user, a list of enrolled player characters, and narrative notes
- Given a session is linked to a campaign, when the campaign is viewed, then the session history is visible in chronological order
- Given a character advances in a campaign, when sessions are replayed, then XP totals accumulate correctly across sessions

## AC-003: One-Shot vs. Campaign Mode
- Given a player starts a one-shot session, when the session completes, then no persistent campaign state is required (session is self-contained)
- Given a player starts a campaign session, when the session begins, then it loads the character state from the last campaign session

## AC-004: Character State Persistence
- Given a character enters a session with inventory, HP, and conditions, when the session begins, then all carry-over state is present
- Given the AI GM tracks narrative continuity, when a new session in the same campaign begins, then the GM context includes a summary of prior sessions

## AC-005: AI GM Context Integration
- Given a session has a narrative summary, when the AI GM receives its context, then the summary is included in the system prompt
- Given important NPCs appeared in prior sessions, when referenced again, then their relationship status and last-known state are surfaced to the AI GM

## Security acceptance criteria

- Security AC exemption: Session/campaign data is user-scoped and protected by standard auth. No sharing of one user's campaign data with another without explicit invite. Session invites should validate the invited user is a registered account.

## Roadmap section

- Roadmap: Core Rulebook
- Chapter: Campaign infrastructure
