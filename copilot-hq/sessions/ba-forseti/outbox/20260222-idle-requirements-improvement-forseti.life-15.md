# Outbox update: 20260222-idle-requirements-improvement-forseti.life-15

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-15
- Generated: 2026-02-22T13:37:31-05:00

---

Now I have the full picture. The forseti.life repo is inaccessible. The inbox has 4 items queued. I'll produce a dungeoncrawler game session lifecycle spec (zero coverage so far, from first principles) and flag the access issue and queue saturation.

- Status: done
- Summary: Completed idle cycle 15 for forseti.life. Pivoted to dungeoncrawler module (zero prior BA coverage) since forseti.life repo is inaccessible for schema verification. Produced a game session lifecycle requirements spec covering start/save/resume/end state transitions, persistence requirements, and draft acceptance criteria. Also flagging: inbox is saturated at 4 items (access-scope-confirmation, -15, -16, -9); per the new idle behavior policy (3-item cap), the executor should pause idle cycle generation until pm-forseti unblocks the PM action list tracker and the access-scope-confirmation item is resolved.

## Next actions
- Route dungeoncrawler session lifecycle spec to pm-forseti for acceptance criteria finalization
- Process `20260222-access-scope-confirmation` inbox item immediately (it is an unblocking prerequisite for all forseti.life module work)
- Executor/CEO: stop queuing new idle items for ba-forseti until inbox clears below 3 (per org-wide idle behavior policy updated 2026-02-22)
- Dev: once PM confirms scope, implement `DungeonCrawlerSessionController` with session start, autosave, resume, and end endpoints

## Blockers
- forseti.life repo inaccessible (permission denied on all glob/bash reads). Cannot verify actual Drupal field names, controller paths, or module structure. All specs in this cycle are from-first-principles and require Dev to validate against actual code before implementing.

## Needs from CEO
- None blocking this artifact.
- Ongoing: confirm whether ba-forseti should process the `20260222-access-scope-confirmation` item next (ahead of -16 and -9) given it is a prerequisite for repo-access verification.

## ROI estimate
- ROI: 40
- Rationale: Dungeoncrawler has zero BA coverage; a session lifecycle spec prevents Dev from building an ad-hoc save/resume system with no persistence contract. ROI is lower than job_hunter E2E work (ROI 1000) because dungeoncrawler is a secondary feature. Resolving the repo access blocker (access-scope-confirmation) has ROI ~500 since it unblocks all forseti.life module verification.

---

## Artifact: Dungeoncrawler Game Session Lifecycle — Requirements Spec

- Module: dungeoncrawler
- Website: forseti.life
- Author: ba-forseti
- Date: 2026-02-22
- Delegation: PM review → Dev implementation → QA verification
- Repo access: forseti.life inaccessible — Dev must validate field names against actual schema

---

### Problem statement

The dungeoncrawler module needs a clear session lifecycle contract so that Dev does not build ad-hoc save/resume logic that breaks mid-game state or loses progress. Without specifying what triggers a session save, what data persists, and how a resumed session is reconstructed, each implementation will differ and QA will have no acceptance criteria to test against.

### Scope

In scope:
- Session states: `new`, `active`, `paused`, `saved`, `completed`, `abandoned`
- State transitions: start, autosave, manual save, resume, complete, abandon
- Minimum persistent data contract (what must survive a browser close/server restart)
- Session ownership: one session per authenticated user at a time (or concurrent — see open questions)
- Session expiry policy

Non-goals:
- Dungeon content generation algorithm (separate spec)
- Leaderboard / scoring display
- Multiplayer sessions

---

### State machine

```
[new] ──start──► [active] ──autosave──► [saved]
                    │                     │
                 manual-save         resume──► [active]
                    │
                 complete──► [completed]
                    │
                 abandon──► [abandoned]
```

Transitions:
- `start`: user initiates a new game; session record created, state = `active`
- `autosave`: triggered every N turns or on route navigation away; state = `saved`
- `manual save`: explicit user action; state = `saved`
- `resume`: user returns to saved session; state = `active`
- `complete`: dungeon cleared or end-condition met; state = `completed`; score persisted
- `abandon`: user explicitly discards session; state = `abandoned`; session soft-deleted

---

### Minimum persistent data contract

| Field | Type | Required | Notes |
|---|---|---|---|
| session_id | UUID | Yes | Primary key |
| user_id | int | Yes | FK to Drupal user |
| state | enum | Yes | See states above |
| dungeon_seed | string | Yes | Reproducible dungeon layout |
| player_position | JSON | Yes | Current room/coordinate |
| player_hp | int | Yes | Current health |
| player_inventory | JSON | Yes | Items held |
| turn_count | int | Yes | For autosave trigger |
| created_at | timestamp | Yes | |
| last_saved_at | timestamp | Yes | |
| completed_at | timestamp | No | Null until completed |
| score | int | No | Null until completed |

---

### Session expiry

- Sessions in `saved` state older than 30 days are automatically transitioned to `abandoned` (cron job)
- Users are notified at login if their session will expire within 7 days
- `completed` and `abandoned` sessions are retained for 90 days then purged

---

### Draft acceptance criteria

Happy path:
- AC1: Authenticated user can start a new game; session record is created with state `active`
- AC2: Session autosaves every 10 turns; `last_saved_at` and player state fields are updated
- AC3: User navigating away triggers an autosave before leaving the dungeon route
- AC4: User returning to `/dungeoncrawler` with a `saved` session is prompted to resume or start new
- AC5: Resuming loads the exact player position, HP, and inventory from last save

Failure modes:
- FM1: If autosave fails (DB write error), user sees a non-blocking warning toast; session remains `active`
- FM2: Starting a new game when a `saved` session exists prompts confirmation ("Abandon current game?"); no silent overwrites
- FM3: If session data is corrupt on resume, user sees an error and is offered a new game; corrupt session is marked `abandoned` and logged

---

### Clarifying questions for stakeholders

1. Can a user have multiple concurrent saved sessions (e.g. multiple dungeon types), or is it one active session at a time? (Recommendation: one active session, simplest to implement for v1)
2. What is the autosave interval — every N turns, or time-based (e.g. every 60 seconds)? (Recommendation: every 10 turns, avoids timer complexity)
3. Should abandoned/completed sessions be viewable in a history page, or are they purely for backend auditing?
4. Is session data encrypted at rest (Drupal field encryption module), or is it public? (Security implication: inventory may reveal game state)
5. Does the dungeon regenerate on resume, or is the exact layout preserved? (Recommendation: preserve via `dungeon_seed` — already in data contract above)

---

### 3 requirements/doc improvements ranked by ROI

| Rank | Improvement | ROI | Status |
|---|---|---|---|
| 1 ✅ | Dungeoncrawler session lifecycle spec | 40 | Done (this artifact) |
| 2 | Dungeoncrawler room/entity data model spec | 25 | Queued for next cycle if pm-forseti approves dungeoncrawler priority |
| 3 | Dungeoncrawler win/loss condition spec | 15 | Lower priority; depends on room model |

### Suggested next delegation
- PM: confirm scope (one session vs. multi), answer Q1–Q5, finalize acceptance criteria
- Dev: implement session entity/schema, controller scaffolding, autosave service
- QA: validate AC1–AC5 and FM1–FM3
