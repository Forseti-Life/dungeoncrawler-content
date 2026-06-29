# Outbox update: 20260222-idle-requirements-improvement-forseti.life-5

- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti-agent-tracker/inbox/20260222-idle-requirements-improvement-forseti.life-5
- Generated: 2026-02-22T12:09:07-05:00

---

I have enough context. The condition stacking/exclusivity rules are referenced but never actually specified — no canonical document defines which conditions are mutually exclusive, which stack, or how immunity works. This is the strongest dungeoncrawler requirements gap. Here is the complete outbox artifact for executor to write to `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-requirements-improvement-forseti.life-5/update.md`:

---

- Status: done
- Summary: Completed BA idle cycle for forseti.life (fifth pass). Selected Combat Condition Lifecycle Requirements (dungeoncrawler) as the top-ROI item this cycle, pivoting to dungeoncrawler to balance coverage across the two priority modules. The DB schema (`combat_conditions` table) and action validation rules both reference condition stacking, exclusivity, and immunity but contain no canonical spec defining those rules. This creates an underdetermined system: Dev's `checkStacking rules` call in the engine service has no source of truth to implement against. A full requirements artifact follows.

## Next actions
- Executor: write this artifact to outbox
- Route to PM (dungeoncrawler) for acceptance criteria sign-off
- Route dungeoncrawler Dev task after PM sign-off
- Note: this spec is implementable now — it has no upstream dependency on other open gaps

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 60
- Rationale: Combat is the dungeoncrawler's core feature loop and is partially shipped; without a condition stacking/exclusivity spec, `CombatEngine::applyCondition()` and `::removeCondition()` are ambiguous — Dev either leaves stacking unimplemented (silent bugs during combat) or makes ad-hoc decisions that will need to be refactored. This spec costs 0 implementation effort and prevents rework on a shipped module.

---

## ROI-Ranked Requirements Improvements (3 candidates)

| Rank | Item | Module | ROI | Rationale |
|------|------|--------|-----|-----------|
| 1 ✅ | Combat Condition Lifecycle (stacking, exclusivity, immunity) | dungeoncrawler | 60 | Engine service references stacking rules with no spec; shipped code has silent gap |
| 2 | Job Search & Discovery Dashboard (Flows 3/16) | job_hunter | 55 | Depends on Diffbot (Flow 11) but spec can be prepared in parallel for Frontend |
| 3 | Application Tracking Dashboard (Flow 9) | job_hunter | 45 | Content type exists, no UI spec; moderate-effort standalone track |

---

## Requirements Artifact: dungeoncrawler — Combat Condition Lifecycle (Stacking, Exclusivity, Immunity)

### Problem Statement

The dungeoncrawler combat system has a `combat_conditions` table with 40+ condition types, a `CombatEngine` service with `applyCondition()`, `removeCondition()`, and `updateConditionDuration()` methods, and action validation rules that check `has_condition()` — but no requirements document specifies the rules governing:

1. **Stacking:** Can the same condition apply multiple times to one participant? Do values add, take the max, or replace?
2. **Exclusivity:** Which conditions are mutually exclusive (applying one removes another)?
3. **Immunity:** When does a condition application silently fail because the target is immune?
4. **Removal triggers:** What events (end of turn, start of turn, death, save) remove each condition type?

`CombatEngineService.php` pseudocode (line 356) lists "Check stacking rules (same type conditions)" and line 398 references "proper stacking rules" — both are TODOs with no backing specification. Without this spec, implementation will diverge from PF2e rules, producing incorrect combat outcomes.

**Current behavior:** `applyCondition()` is called with a condition type — behavior for valued conditions (frightened 2 + frightened 1), exclusivity pairs (grabbed / restrained), and immunity is undefined.  
**Expected behavior:** `applyCondition()` follows the canonical rules below; any conflict or immunity causes a predictable, specified outcome.

### Scope

**In scope:**
- Stacking rules for all valued conditions (frightened, clumsy, enfeebled, drained, doomed, sickened, stupefied, slowed, stunned, wounded)
- Exclusivity rules for condition pairs/groups (grabbed → restrained, dying → unconscious, etc.)
- Immunity rules: which conditions grant immunity to other conditions
- Removal triggers per condition: what game events remove each condition
- UI display rules: how valued conditions are shown (e.g., "Frightened 2")

**Non-goals:**
- Persistent (non-combat) condition tracking on character sheets (separate feature)
- Condition immunity from class features, resistances, or item properties (Phase 2)
- Custom/house-rule conditions
- Condition interactions with spells or special abilities (covered per-ability)

### Definitions

| Term | Definition |
|------|------------|
| Valued condition | A condition with a numeric severity (e.g., Frightened 2, Clumsy 1). The value affects magnitude of the mechanical penalty. |
| Simple condition | A condition with no numeric value; it either applies or doesn't (e.g., Paralyzed, Prone). |
| Exclusive conditions | Two conditions that cannot coexist; applying one automatically removes the other. |
| Stacking | The behavior when the same condition is applied to a participant who already has it. |
| Immunity | A state where a condition application on a target silently fails or is downgraded. |
| End-of-turn removal | Condition removed at the end of the affected participant's turn. |
| Recovery check | A DC 15 flat check at start of turn to reduce the `dying` value by 1 (or 2 on critical success). |

### Canonical Condition Rules (PF2e Core)

#### A. Stacking Rules for Valued Conditions

| Condition | Stacking Behavior | Example |
|---|---|---|
| `frightened` | Take the higher value; do not add | frightened 2 + frightened 1 = frightened 2 |
| `clumsy` | Take the higher value | clumsy 2 + clumsy 1 = clumsy 2 |
| `enfeebled` | Take the higher value | enfeebled 3 + enfeebled 1 = enfeebled 3 |
| `drained` | Take the higher value | drained 2 + drained 1 = drained 2 |
| `doomed` | Take the higher value | doomed 1 + doomed 1 = doomed 1 (no change) |
| `sickened` | Take the higher value | sickened 2 + sickened 1 = sickened 2 |
| `stupefied` | Take the higher value | stupefied 3 + stupefied 2 = stupefied 3 |
| `slowed` | Take the higher value | slowed 2 + slowed 1 = slowed 2 |
| `stunned` | **Add values** | stunned 2 + stunned 1 = stunned 3 |
| `wounded` | **Add values** (max = doomed) | wounded 1 + wounded 1 = wounded 2 |
| `persistent_damage` | **Different sources stack** (same damage type from same source: take higher) | fire persistent from two sources = both apply |

**Implementation rule for valued conditions:** `new_value = Math.max(current_value, incoming_value)` except `stunned` and `wounded` which use `current_value + incoming_value` (cap at implementation max).

#### B. Exclusivity / Superseding Rules

| When condition is applied | Automatically remove |
|---|---|
| `restrained` applied | Remove `grabbed` (restrained supersedes) |
| `unconscious` applied | Remove `paralyzed`, `dying` (at dying 4), `prone` is added automatically |
| `dying` reduced to 0 | Apply `wounded` (value = wounded + 1), remove `dying`, remove `unconscious` |
| `dying 4` reached | Apply `dead` state; remove all conditions |
| `petrified` applied | Remove `grabbed`, `restrained`, `prone` (petrified supersedes physical restraint) |
| `dead` state | Remove all conditions |

#### C. Removal Triggers Per Condition

| Condition | When removed |
|---|---|
| `frightened` | Reduced by 1 at **end of each of the affected's turns** until 0 |
| `slowed` | Reduced by 1 at **start of each of the affected's turns** until 0 |
| `stunned` | Reduced by number of actions lost at **start of turn** |
| `grabbed` | When grabber is incapacitated, moves away, or target succeeds on Escape (action) |
| `restrained` | Target succeeds on Escape (action) or condition source removed |
| `prone` | Target uses **Stand** action (1 action) |
| `dying` | Recovery check at start of turn (DC 15 flat): success = dying -1; critical = dying -2; fail = dying +1; critical fail = dead |
| `drained` | Reduced by 1 per **full night of rest** |
| `wounded` | Removed after full rest with Treat Wounds or magical healing; persists through short rests |
| `doomed` | Removed only by specific magic (not rest); reduces max dying value |
| `fatigued` | Full night of rest |
| `sickened` | Reduced by 1 when target uses **Retch** action (1 action, once per round) or via Treat Poison / medicine |
| `persistent_damage` | DC 15 flat check at **end of turn**; success = condition removed; automatically removed by 1 action of assistance from ally |
| `fascinated` | Any hostile action targeting the creature removes it; ends at end of source's turn if duration-based |
| `fleeing` | Duration expires (usually end of source's turn or fixed rounds) |
| `quickened` | End of the creature's turn (lasts 1 round) |
| `flat_footed` | Per trigger (varies; often until start of next turn) |
| `concealed`, `hidden`, `invisible`, `undetected` | Per situation/movement; removed by Seek action revealing the target |

#### D. Immunity Rules

| Condition | Immunity source | Behavior |
|---|---|---|
| `dying` | `doomed X` where X ≥ max_dying threshold | Adjust max dying value before applying |
| `unconscious` | Creatures with the `construct` or `undead` trait | `applyCondition` silently skips; log: "Immune to unconscious" |
| `paralyzed` | Creatures with `construct` or `undead` trait | Same — skip silently |
| `frightened` | Creatures with `mindless` trait | Skip silently |
| `fascinated` | Creatures with `mindless` trait | Skip silently |
| All conditions | `dead` state | Return early; no condition can be applied to a dead participant |

### Key User Flows

**Flow A: Applying a valued condition (Frightened)**
1. Spell hits character → `applyCondition(participant_id, 'frightened', value: 2)`
2. System checks: does participant already have `frightened`?
3. If yes: `new_value = max(current, 2)` — update if higher, otherwise no-op
4. If no: insert `combat_conditions` row with `condition_type='frightened'`, `condition_value=2`, `duration_type='rounds'`, `duration_remaining=NULL` (frightened reduces per turn, not by timer)
5. UI updates participant panel: shows "Frightened 2" badge
6. At end of participant's turn: system calls `decrementValuedCondition(participant_id, 'frightened', 1)` → frightened 1; next end of turn → removed

**Flow B: Applying a superseding condition (Restrained)**
1. Enemy grapple succeeds at Restrained → `applyCondition(participant_id, 'restrained')`
2. System checks exclusivity: participant has `grabbed` → call `removeCondition(participant_id, 'grabbed')`
3. Insert `restrained` row
4. UI removes "Grabbed" badge, adds "Restrained" badge
5. Log entry: "Grabbed removed (superseded by Restrained)"

**Flow C: Dying → recovery → wounded**
1. Character HP reaches 0 → `applyCondition(participant_id, 'dying', value: 1)` + `applyCondition(participant_id, 'unconscious')`
2. `prone` auto-applied
3. Start of participant's next turn: system prompts recovery check (DC 15 flat)
4. Player rolls: result 16 (success) → `dying` reduced to 0 → remove `dying`, remove `unconscious`, apply `wounded` (value: current_wounded + 1)
5. Character stands (uses action) → `prone` removed

### Acceptance Criteria (Draft — PM to finalize)

**Happy path:**
- AC1: Applying `frightened 2` to a participant already at `frightened 3` leaves them at `frightened 3` (higher value wins; no change).
- AC2: Applying `frightened 2` to a participant already at `frightened 1` updates them to `frightened 2`.
- AC3: Applying `stunned 2` to a participant already at `stunned 1` results in `stunned 3` (values add).
- AC4: Applying `restrained` to a participant who has `grabbed` removes `grabbed` and applies `restrained`; combat log shows "Grabbed removed (superseded by Restrained)".
- AC5: At end of participant's turn, `frightened X` decrements by 1; at 0, condition is removed from the UI and DB.
- AC6: A participant with the `mindless` trait targeted by `frightened` receives no condition; system logs "Immune: mindless cannot be frightened."
- AC7: When `dying` reaches 0 via recovery check, `dying` is removed, `wounded` value increments by 1, `unconscious` is removed, and character can act on their next turn.

**Failure modes:**
- AC8: Calling `applyCondition` on a `dead` participant returns immediately with no DB write and logs "Skipped: participant is dead."
- AC9: Applying an unknown `condition_type` not in the ENUM list returns a validation error rather than silently creating an invalid row.

**Verification method:**
- PHPUnit: `CombatConditionTest` — unit test each stacking rule, each exclusivity pair, each immunity case with mock participant data.
- PHPUnit: `CombatConditionLifecycleTest` — simulate dying → recovery → wounded flow end-to-end.
- Manual: Start a combat encounter; apply Frightened 3 then Frightened 2 — confirm participant shows "Frightened 3". End participant's turn three times — confirm condition clears.

### Assumptions

1. `CombatEngineService::applyCondition()` is the single choke point for all condition application; exclusivity and stacking checks must be implemented there, not in callers.
2. The `combat_conditions` table `removed_at` timestamp column (observed in DB schema) is used for soft-deletes when conditions are removed; hard-delete is not used.
3. Creature traits (`mindless`, `undead`, `construct`) are accessible from the `combat_participants` record or the linked character/monster entity at condition-apply time.
4. "Dead" state is represented as a participant flag (`is_defeated = TRUE`, `defeat_reason = 'dead'`), not as a condition in `combat_conditions`.

### 3–5 Clarifying Questions for Stakeholders

1. **Persistent damage source tracking:** PF2e rules say the same persistent damage type from different sources stacks, but the same type from the same source takes the higher value. Is source tracking in scope for MVP, or should we simplify to "all persistent damage of the same type stacks" for now?
2. **`doomed` interaction with `dying`:** `Doomed X` reduces the dying threshold at which a character dies (e.g., doomed 1 = die at dying 3 instead of 4). Is this interaction in scope for the MVP combat system, or is doomed tracked but its death-threshold effect deferred?
3. **Condition immunity via items/feats:** Some class features and items grant immunity to specific conditions (e.g., certain Champion feats grant immunity to Frightened). Is per-character condition immunity (beyond trait-based) in scope for this implementation pass?
4. **UI badge display:** For valued conditions, should the combat UI show "Frightened 2" as a single badge or as two separate "Frightened" badges? Recommendation: single badge with numeric label — confirm with PM/designer.
5. **Sickened removal:** PF2e uses a "Retch" action (1 action, once per round, Fort save) to reduce Sickened. Is this a full action with a save throw UI in scope, or should Sickened be GM-removable only at MVP?

### Delegation Table

| Role | Action |
|------|--------|
| **PM** | Finalize AC1–AC9; answer clarifying questions 1–5; confirm doomed/dying interaction scope |
| **Dev (dungeoncrawler)** | Implement stacking/exclusivity/immunity rules in `CombatEngineService::applyCondition()`; add `decrementValuedCondition()` turn-end hook; add immunity check via participant traits |
| **QA** | Execute each stacking scenario in AC1–AC3; test exclusivity pair (grabbed → restrained); test dying flow AC7; test immunity AC6 via mindless monster |
