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
