# BA Analysis: dc-cr-action-economy

**Date:** 2026-02-28  
**Release:** 20260228-dungeoncrawler-release-next  
**Scope:** dc-cr-action-economy — PF2E three-action economy (3 actions + 1 reaction per turn)

---

## Correction to prior gap analysis

The gap analysis artifact `20260228-gap-analysis-dc-cr-current-release.md` stated that `Calculator::determineDegreeOfSuccess()` (always returns `'success'`) blocked encounter resolution. **This was partially wrong.** There are TWO separate services:

- `Calculator::determineDegreeOfSuccess()` — stub, always returns `'success'` — **not used by ActionProcessor**
- `CombatCalculator::calculateDegreeOfSuccess()` — **fully implemented**: applies ≥DC+10 = crit_success, ≥DC = success, ≤DC-10 = crit_failure, natural 20 bumps up, natural 1 bumps down — **used by ActionProcessor::executeStrike()**

Encounter-mode attack resolution is therefore functional for strikes. The TODO comment in `CombatCalculator` is misleading but the logic is implemented. Prior recommendation to "fix `determineDegreeOfSuccess()` first" is moot for combat — that stub in `Calculator.php` is on a separate code path.

---

## Current implementation state (verified in codebase)

### What is implemented ✅

| Component | Location | State |
|---|---|---|
| `actions_remaining` (0–3) on participant | `CombatEncounterStore`, `CombatEngine` | ✅ Tracked; reset to 3 at `startRound()` and `startTurn()` |
| Action cost deduction | `ActionProcessor::executeStrike()`, `executeStride()` | ✅ Decrements `actions_remaining` by 1 per action |
| Action cost validation | `EncounterAiIntegrationService::validateRecommendation()` | ✅ Rejects if `action_cost > actions_remaining` |
| `attacks_this_turn` counter | `CombatEncounterStore`, `ActionProcessor` | ✅ Tracked; reset at `startRound()` / `startTurn()` |
| MAP calculation | `ActionProcessor::executeStrike()` via `CombatCalculator::calculateMultipleAttackPenalty()` | ✅ Implemented |
| `reactionAvailable` in character sheet | `CharacterStateService::startNewTurn()`, `useReaction()` | ✅ Implemented — but only on character-sheet side |
| Degree of success (combat) | `CombatCalculator::calculateDegreeOfSuccess()` | ✅ Fully implemented (crit/success/failure/crit-fail + nat-20/nat-1 shifts) |

### What is missing / stub ❌

| Component | Gap | Location of gap |
|---|---|---|
| `reaction_available` in encounter store | Not a field in CombatEncounterStore participant schema; not reset in `CombatEngine::startRound()` or `startTurn()` | `CombatEngine.php` lines 84–152, `CombatEncounterStore.php` |
| Reaction execution pipeline | `ReactionHandler::checkForReactions()` = TODO stub; `ReactionHandler::executeReaction()` = TODO stub | `ReactionHandler.php` lines 23–53; `CombatActionController.php` line 239 comment only |
| Free actions | `EncounterAiIntegrationService::validateRecommendation()` rejects `action_cost <= 0`; no `free` type in `ActionProcessor::executeAction()` switch | `EncounterAiIntegrationService.php` line 168; `ActionProcessor.php` lines 41–52 |
| Action content type (Drupal entity) | Actions are hardcoded enum strings (`strike`, `stride`, `interact`, etc.); no `action` content type with cost/trigger/effect fields | No content type found; `allowed_actions` is a PHP array constant |

---

## Dependency and sequencing risks

**dc-cr-action-economy → dc-cr-encounter-rules (already unblocked for basic attacks)**  
Strike and Stride work end-to-end via ActionProcessor. The encounter-rules feature is partially functional today.

**dc-cr-action-economy → dc-cr-conditions (reaction timing dependency)**  
Several conditions modify what reactions are available (grabbed = no reactions, stunned = no actions or reactions). The `ConditionManager` is partially implemented. If reactions are scoped in, condition modifiers must gate reaction availability.

**dc-cr-action-economy → dc-cr-skill-system (free action risk)**  
Several skill actions are free actions (e.g., Recall Knowledge in some contexts) or have variable cost. If free action support is deferred, skill actions with 0-action cost cannot be executed through the action pipeline.

**Two-system split (CharacterStateService vs. CombatEncounterStore):**  
`CharacterStateService` tracks `reactionAvailable` on the character-sheet object (camelCase). `CombatEncounterStore` tracks `actions_remaining` on the encounter participant (snake_case). These are currently **separate data paths** that do not share state. This is a design decision that Dev needs clarity on before implementing reaction tracking in the encounter engine.

---

## Unresolved PM decisions (before coding)

### Decision 1 (required): Reaction implementation scope for this release

Three options:

| Option | Scope | Effort | Unblocks |
|---|---|---|---|
| A — State only | Track `reaction_available` bool in encounter store; reset each turn; set to false when used; no trigger detection | ~2h | Future reaction features; state is correct |
| B — Targeted reaction | Implement one specific reaction (Fighter Attack of Opportunity or Shield Block) end-to-end: trigger detection + execution | ~1 day | Proves the reaction pipeline; ships one playable reaction |
| C — Full pipeline | Implement `checkForReactions()` generically for all trigger types | ~3–5 days | All reactions in future, but high complexity |

**BA recommendation: Option A for this release.** Track `reaction_available` state correctly (small, well-scoped), defer reaction execution to next release when specific class reactions are scoped in. This avoids building a trigger pipeline before the class features (dc-cr-character-class) that define which reactions exist are implemented.

### Decision 2 (required): Free action scope for this release

Free actions are needed by several mechanics (some recall knowledge uses, some feat abilities). The current action validation rejects `action_cost = 0`.

| Option | Scope |
|---|---|
| A — Defer | Skip free actions entirely this release; flag any feature that requires them as blocked |
| B — Passthrough | Allow `action_cost = 0` in validation; add `'free'` to allowed_actions; no deduction from `actions_remaining`; no content type |

**BA recommendation: Option B (passthrough) — minimal change.** Change the validation condition in `EncounterAiIntegrationService` from `$action_cost <= 0` to `$action_cost < 0`. Add `'free'` to `allowed_actions`. This is a 2-line change that unblocks free action dispatch without building a full content type.

### Decision 3 (informational, not blocking): Architecture — hardcoded action types vs. content entities

Actions are currently hardcoded enum strings in PHP (`strike`, `stride`, `interact`, etc.). The feature stub implies an `action` content type. This is the same architecture decision as ancestry/background/class.

**BA recommendation:** Accept hardcoded action types for this release (consistent with ancestry/class pattern). Log a future feature for migrating to a Drupal `action` content type when the action library grows large enough to need CMS management.

---

## Implementation slice order (recommended for Dev)

**Slice 1 — Add `reaction_available` to encounter store** (2–4 hours, no PM decision required)
- File: `CombatEncounterStore.php` — add `reaction_available` field to participant schema (default: `true`)
- File: `CombatEngine::startRound()` — add `'reaction_available' => true` to the `updateParticipant()` call
- File: `CombatEngine::startTurn()` — add `'reaction_available' => true` to the `updateParticipant()` call
- File: `CombatActionController::executeReaction()` — add `'reaction_available' => false` to the `updateParticipant()` call (currently returns a stub array)
- Verify: start turn → participant has `reaction_available = true`; call executeReaction (stub) → `reaction_available = false`; start new turn → `reaction_available = true`

**Slice 2 — Allow free actions in action pipeline** (1–2 hours, requires PM Decision 2)
- File: `EncounterAiIntegrationService::validateRecommendation()` line 168: change `$action_cost <= 0` to `$action_cost < 0`
- File: `EncounterAiIntegrationService::buildEncounterContext()`: add `'free'` to `allowed_actions[]`
- File: `ActionProcessor::executeAction()` switch: add a `case 'free'` that dispatches to a stub (returns ok, no action cost deducted)
- Verify: dispatch `action_type = 'free'` with `action_cost = 0` → validation passes; `actions_remaining` unchanged

**Slice 3 — Reaction execution stub-to-working** (requires PM Decision 1; only if Option B or C chosen)
- Out of scope for Slice 1. Design spec for reaction trigger pipeline to be written when PM decides scope.

---

## Acceptance criteria (updated from gap analysis)

### dc-cr-action-economy — full definition of done

1. Turn state has `actions_remaining` (0-3) reset to 3 at start of each turn ✅ already done
2. Turn state has `reaction_available` (bool) reset to `true` at start of each turn ❌ needs Slice 1
3. Executing a reaction sets `reaction_available = false` ❌ needs Slice 1
4. Attempting a second reaction in the same turn returns an error ❌ needs Slice 1
5. Free actions can be dispatched without consuming `actions_remaining` ❌ needs Slice 2 (pending PM Decision 2)
6. Multi-action activities (2-action, 3-action) deduct correct action cost ❌ needs verification — currently executeStrike always deducts 1

**Verification commands:**
```
# Slice 1 — verify reaction_available in turn state
POST /encounters/{id}/participants/{pid}/turn/start
→ response.participant.reaction_available === true

# Slice 1 — verify reaction marks used
POST /encounters/{id}/reactions
→ response.reaction_available === false

# Slice 2 — verify free action passthrough
POST /encounters/{id}/actions with action_type=free, action_cost=0
→ response.status === 'ok', actions_remaining unchanged
```

---

## Open question for QA

The `EncounterAiIntegrationService::validateRecommendation()` checks `action_cost` but `ActionProcessor::executeStrike()` always deducts 1 regardless of the action's actual cost. 2-action Strikes (Power Attack) and 3-action activities are not yet enforced. This is out of scope for dc-cr-action-economy but should be logged as a QA edge case for dc-cr-encounter-rules.
