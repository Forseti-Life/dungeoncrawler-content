# Suite Activation: dc-cr-dwarf-heritage-ancient-blooded

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T14:54:12+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-dwarf-heritage-ancient-blooded"`**  
   This links the test to the living requirements doc at `features/dc-cr-dwarf-heritage-ancient-blooded/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-dwarf-heritage-ancient-blooded-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-dwarf-heritage-ancient-blooded",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-dwarf-heritage-ancient-blooded"`**  
   Example:
   ```json
   {
     "id": "dc-cr-dwarf-heritage-ancient-blooded-<route-slug>",
     "feature_id": "dc-cr-dwarf-heritage-ancient-blooded",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-dwarf-heritage-ancient-blooded",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan — dc-cr-dwarf-heritage-ancient-blooded

- Feature: Dwarf Heritage — Ancient-Blooded
- Work item id: dc-cr-dwarf-heritage-ancient-blooded
- Release target: 20260308-dungeoncrawler-release-b (NEXT RELEASE — grooming only)
- QA owner: qa-dungeoncrawler
- AC source: features/dc-cr-dwarf-heritage-ancient-blooded/01-acceptance-criteria.md
- Date: 2026-03-09
- Status: groomed (NOT activated — do not add to suite.json until feature enters Stage 0)

## Knowledgebase check
- No prior lesson found for heritage implementations or circumstance bonus stacking specifically.
- This is the **first concrete heritage implementation** — it validates the heritage data model for all future heritages. Higher ambiguity risk on first-of-kind patterns; PM clarification items flagged below.
- Related feature context:
  - `dc-cr-heritage-system` (`features/dc-cr-heritage-system/feature.md`): heritage selection at level 1, locked after creation; `parent_ancestry` field enforces ancestry-gating.
  - `dc-cr-conditions` (`features/dc-cr-conditions/feature.md`): in release-a; `ConditionManager` handles circumstance bonuses on saving throws.
  - `dc-cr-encounter-rules`: in release-a; saving throw resolution pipeline exists.
- No prior KB lesson for reaction mechanics or magical-vs-mundane source classification.

---

## Suite mapping

| Suite ID | Type | Purpose |
|---|---|---|
| `dc-cr-dwarf-heritage-ancient-blooded-e2e` | playwright | End-to-end: heritage assignment, reaction trigger, bonus application, stacking, expiry |
| `role-url-audit` | audit | ACL: heritage selection route accessible only to character owner + admin |

> `dc-cr-dwarf-heritage-ancient-blooded-e2e` is a NEW suite to be added to `qa-suites/products/dungeoncrawler/suite.json` at Stage 0.

---

## Test cases

### Happy Path

#### TC-001: Ancient-Blooded heritage is available to Dwarf characters
- Suite: `dc-cr-dwarf-heritage-ancient-blooded-e2e` (playwright)
- Description: Verify that when a Dwarf character reaches the heritage selection step in character creation, `ancient-blooded-dwarf` appears in the available options list.
- Steps:
  1. Begin character creation with ancestry = Dwarf
  2. Proceed to heritage selection step
  3. Assert: `ancient-blooded-dwarf` is in the list of selectable heritages
- Expected: Heritage appears as a selectable option for Dwarf characters.
- Roles covered: authenticated
- AC: Happy Path — heritage available to Dwarf only

#### TC-002: Selecting Ancient-Blooded grants call-on-ancient-blood reaction
- Suite: `dc-cr-dwarf-heritage-ancient-blooded-e2e` (playwright)
- Description: Complete character creation with Ancient-Blooded heritage selected; verify the `call-on-ancient-blood` reaction appears in the character's abilities.
- Steps:
  1. Create Dwarf character; select `ancient-blooded-dwarf` heritage
  2. Complete character creation
  3. GET character abilities (or abilities endpoint for that character)
  4. Assert: `call-on-ancient-blood` is present in the abilities list
- Expected: Character has the `call-on-ancient-blood` reaction as a granted ability.
- Roles covered: authenticated, administrator
- AC: Happy Path — reaction granted on heritage selection

#### TC-003: Reaction prompt appears before rolling a magical saving throw
- Suite: `dc-cr-dwarf-heritage-ancient-blooded-e2e` (playwright)
- Description: When a character with this heritage is targeted by a magical effect that triggers a saving throw, and `reaction_available = true`, the system prompts the player to use the reaction before the roll.
- Steps:
  1. Create dwarf character with Ancient-Blooded heritage; start encounter
  2. Apply a magical effect that requires a saving throw (e.g., a spell that forces a Will save)
  3. Assert: reaction prompt is issued (reaction_available = true → prompt fires)
  4. Assert: prompt appears BEFORE the save roll is resolved
- Expected: Reaction prompt fires with magical-save trigger; no premature roll.
- **PM clarification needed (see CQ-001)**: how is the reaction prompt surfaced in the API response — as a field in the saving-throw event payload, a separate prompt action, or a state machine transition?
- Roles covered: authenticated
- AC: Happy Path — reaction prompt on magical save

#### TC-004: Using the reaction applies +1 circumstance bonus to the triggering save
- Suite: `dc-cr-dwarf-heritage-ancient-blooded-e2e` (playwright)
- Description: Player accepts the reaction prompt; verify the triggering save roll has a +1 circumstance bonus applied.
- Steps:
  1. Trigger magical saving throw prompt (as in TC-003)
  2. Player accepts reaction use
  3. Assert: save result includes `circumstance_bonus: 1` on the triggering roll
  4. Assert: `reaction_available` is now `false`
- Expected: Roll value = base_roll + ability_modifier + 1 (circumstance). The +1 is labeled as circumstance type.
- Roles covered: authenticated
- AC: Happy Path — bonus applied to triggering save

#### TC-005: +1 circumstance bonus persists for the remainder of the turn
- Suite: `dc-cr-dwarf-heritage-ancient-blooded-e2e` (playwright)
- Description: After using the reaction, verify any subsequent saving throws in the same turn also receive the +1 circumstance bonus.
- Steps:
  1. Use reaction (as in TC-004)
  2. Trigger a second saving throw in the same turn (e.g., second magical effect or AoE)
  3. Assert: second save also has `circumstance_bonus: 1` from this reaction
- Expected: Bonus applies to all saves until end of turn (not just the triggering one).
- **PM clarification needed (see CQ-002)**: does the system track "same turn" as a turn counter/phase tag on the character state? How is "end of turn" defined in the API?
- Roles covered: authenticated
- AC: Happy Path — bonus applies until end of turn

#### TC-006: Circumstance bonus expires at end of turn
- Suite: `dc-cr-dwarf-heritage-ancient-blooded-e2e` (playwright)
- Description: After the character's turn ends, verify the +1 circumstance bonus from this reaction is removed.
- Steps:
  1. Use reaction; verify bonus is active
  2. Advance to next turn (end-of-turn event fires)
  3. Assert: `circumstance_bonus` from `call-on-ancient-blood` is no longer active on the character
  4. Assert: `reaction_available` is reset to `true` at start of next turn
- Expected: Bonus removed at end of turn; reaction becomes available again next turn.
- Roles covered: authenticated
- AC: Happy Path — bonus expiry; reaction reset

#### TC-007: Reaction_available = false after use (can't use twice in one turn)
- Suite: `dc-cr-dwarf-heritage-ancient-blooded-e2e` (playwright)
- Description: After using the reaction once, verify `reaction_available` is set to false for the remainder of the turn.
- Steps:
  1. Use reaction on first magical save
  2. Assert: `reaction_available = false`
  3. Trigger a second magical save prompt in the same turn
  4. Assert: no reaction prompt fires (reaction already spent)
- Expected: Reaction is a once-per-turn resource; once spent, it does not prompt again.
- Roles covered: authenticated
- AC: Happy Path — reaction consumption

---

### Edge Cases

#### TC-008: Ancient-Blooded is not available to non-dwarf characters
- Suite: `dc-cr-dwarf-heritage-ancient-blooded-e2e` (playwright)
- Description: During character creation with a non-Dwarf ancestry, verify `ancient-blooded-dwarf` does not appear in the heritage options list.
- Steps:
  1. Begin character creation with ancestry = Elf (or Human, Halfling, etc.)
  2. Proceed to heritage selection step
  3. Assert: `ancient-blooded-dwarf` is NOT in the available options list
- Expected: Heritage is filtered out for non-dwarf ancestries.
- Roles covered: authenticated
- AC: Edge Case — non-dwarf exclusion

#### TC-009: Spent reaction cannot be used again (reaction_available = false)
- Suite: `dc-cr-dwarf-heritage-ancient-blooded-e2e` (playwright)
- Description: Directly attempt to trigger the reaction when `reaction_available = false`; verify appropriate rejection.
- Steps:
  1. Use reaction in current turn (reaction_available → false)
  2. Attempt to use reaction again via API directly
  3. Assert: error response — "Reaction already used this turn" (or equivalent)
- Expected: HTTP 422 (or similar) with a clear message indicating the reaction is spent.
- Roles covered: authenticated
- AC: Edge Case — reaction spent rejection; Failure Mode

#### TC-010: Circumstance bonus does not stack with another circumstance bonus on saves
- Suite: `dc-cr-dwarf-heritage-ancient-blooded-e2e` (playwright)
- Description: Apply a second +1 (or higher) circumstance bonus from a different source while the Ancient Blood reaction is active; verify only the highest applies (no stacking).
- Steps:
  1. Use Call on Ancient Blood reaction (active +1 circumstance)
  2. Apply a second circumstance bonus to saving throws from a different source (e.g., a spell granting +2 circumstance to saves)
  3. Make a saving throw
  4. Assert: total circumstance bonus = max(1, 2) = 2, NOT 3 (no additive stacking)
- Expected: PF2e circumstance bonus rule: same type does not stack; highest applies.
- **PM clarification needed (see CQ-003)**: is the circumstance-bonus non-stacking rule already implemented in the conditions system (dc-cr-conditions), or does this heritage feature need to implement it independently?
- Roles covered: authenticated
- AC: Edge Case — circumstance stacking rule

#### TC-011: Reaction does NOT trigger for non-magical saving throws
- Suite: `dc-cr-dwarf-heritage-ancient-blooded-e2e` (playwright)
- Description: Apply a non-magical effect (e.g., a physical attack that forces a Reflex save, or an environmental hazard with no magical source) that requires a saving throw; verify no reaction prompt fires for this heritage.
- Steps:
  1. Create dwarf character with Ancient-Blooded heritage; start encounter
  2. Apply a non-magical saving throw trigger (e.g., trap, physical hazard)
  3. Assert: no `call-on-ancient-blood` reaction prompt fires
  4. Save rolls proceed normally without the reaction being offered
- Expected: Reaction is explicitly limited to magical-effect triggers; does not fire for mundane/physical saves.
- **PM clarification needed (see CQ-004)**: how is "magical effect" classified in the combat engine — is there a `source_type: magical` flag on effect events? Who sets it (AI GM, spell entity, item)? This is critical for the trigger-detection logic.
- Roles covered: authenticated
- AC: Edge Case — magical-only trigger

---

### Failure Modes

#### TC-012: Assigning Ancient-Blooded to non-dwarf character is rejected
- Suite: `dc-cr-dwarf-heritage-ancient-blooded-e2e` (playwright)
- Description: Attempt to assign `ancient-blooded-dwarf` heritage to a non-dwarf character directly via API (bypassing UI filtering); verify server-side rejection.
- Steps:
  1. Create an elf character (no heritage selected)
  2. POST heritage assignment: `heritage_id: ancient-blooded-dwarf` for the elf character
  3. Assert: server returns error — heritage not valid for this ancestry
- Expected: HTTP 422 (or 400) with a clear error indicating ancestry mismatch. Heritage is NOT applied.
- Roles covered: authenticated, administrator
- AC: Failure Mode — non-dwarf assignment rejection

#### TC-013: Using a spent reaction returns clear error message
- Suite: `dc-cr-dwarf-heritage-ancient-blooded-e2e` (playwright)
- Description: (Cross-reference with TC-009) Directly call the reaction endpoint when `reaction_available = false`; verify error message text matches AC specification.
- Steps:
  1. Spend the reaction in current turn
  2. POST call-on-ancient-blood reaction use again
  3. Assert: response body contains message equivalent to "Reaction already used this turn"
  4. Assert: no bonus is applied; character state unchanged
- Expected: Error message is informative; no double-application.
- Roles covered: authenticated
- AC: Failure Mode — spent reaction message

---

### Permissions / Access Control

#### TC-014: Heritage selection only allowed at character creation (locked after)
- Suite: `dc-cr-dwarf-heritage-ancient-blooded-e2e` (playwright) + `role-url-audit`
- Description: Verify that heritage cannot be changed after character creation is complete.
- Steps:
  1. Create Dwarf character with Ancient-Blooded heritage; complete creation
  2. Attempt to POST a heritage change for the completed character (e.g., switch to a different dwarf heritage)
  3. Assert: server rejects the change (heritage is locked after creation)
- Expected: HTTP 422 (or 403) — heritage locked; cannot be changed post-creation.
- Roles covered: authenticated
- AC: Permissions — heritage locked at creation

#### TC-015: Only character owner or GM can use the reaction
- Suite: `dc-cr-dwarf-heritage-ancient-blooded-e2e` (playwright) + `role-url-audit`
- Description: A different authenticated player (not the character owner) cannot trigger the reaction on behalf of the character. The owning player and GM (admin) can.
- Steps:
  1. Create Dwarf character (owned by User A) with Ancient-Blooded heritage
  2. As User B (different authenticated user, non-GM): attempt to trigger the reaction
  3. Assert: HTTP 403 — not authorized
  4. As administrator/GM: trigger reaction
  5. Assert: reaction fires successfully
- Expected: Reaction use is scoped to character owner + GM role.
- **PM clarification needed (see CQ-005)**: is there a dedicated GM role separate from `administrator`? Same clarification as dc-cr-clan-dagger CQ-005 — needs consistent answer across features.
- Roles covered: authenticated (owner), authenticated (non-owner), administrator (GM)
- AC: Permissions — reaction usage authorization

---

## PM clarification items (required before Stage 0 activation)

| ID | AC item | Question |
|---|---|---|
| CQ-001 | Happy Path — reaction prompt | How is the reaction prompt surfaced in the API? Is it a field in the saving-throw event payload, a separate `prompt_reaction` response, or a state machine transition? What does the player's "accept" action look like (endpoint + payload)? |
| CQ-002 | Happy Path — bonus persists until end of turn | How does the system track "same turn" for bonus persistence? What API event or payload field signals "end of turn" for bonus expiry? Is `reaction_available` reset automatically at start of next turn, or does it require an explicit reset call? |
| CQ-003 | Edge Case — circumstance stacking | Is the circumstance-bonus non-stacking rule already enforced by the dc-cr-conditions `ConditionManager`? If yes, this test only verifies the heritage feeds the bonus correctly. If no, dev must implement stacking logic here. |
| CQ-004 | Edge Case — magical vs. non-magical trigger | How are "magical effects" classified in the combat engine? Is there a `source_type` field on effect/spell events? Who is responsible for setting it — the AI GM, the spell entity, or the combat engine? This is the key integration point for the reaction trigger. |
| CQ-005 | Permissions — GM role | Which Drupal role(s) constitute "GM" for reaction override? Same question as dc-cr-clan-dagger CQ-005 — needs a consistent definition across the product. |

---

## Notes to Dev

- TC-011 (magical-vs-non-magical trigger) is the highest-risk test case — it depends on the combat engine having a reliable, queryable `source_type: magical` classification on effect events. Dev should document this in implementation notes before QA can write a deterministic assertion.
- TC-010 (circumstance stacking) depends on whether dc-cr-conditions already enforces PF2e bonus-type stacking rules. Dev should confirm in implementation notes whether `ConditionManager` handles this or if Ancient-Blooded must implement it independently.
- TC-005/TC-006 (turn-duration tracking) require the encounter system to emit an observable "end of turn" event. Dev should expose this in the API or state payload so QA can assert timing-dependent bonus expiry.
- This is the first heritage implementation — once the data model and API contract are confirmed, test cases TC-001/TC-002 become a template for all future heritage features. Dev should document the heritage entity schema in implementation notes.

### Acceptance criteria (reference)

# Acceptance Criteria — dc-cr-dwarf-heritage-ancient-blooded

- Feature: Dwarf Heritage — Ancient-Blooded
- Release target: 20260308-dungeoncrawler-release-b
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-03-08

## Scope

Implement the Ancient-Blooded dwarf heritage, which grants the **Call on Ancient Blood** reaction: +1 circumstance bonus to saving throws until end of turn when triggered before rolling a save against a magical effect.

## Prerequisites satisfied

- dc-cr-dwarf-ancestry: complete
- dc-cr-heritage-system: complete (heritage selection at level 1, locked after creation)
- dc-cr-conditions: in release-a (circumstance bonuses apply to saving throws)
- dc-cr-encounter-rules: in release-a (saving throw resolution exists)

## Knowledgebase check

None found for specific heritage implementations. This is the first concrete heritage implementation — the spec validates the heritage data model for future heritages.

## Happy Path

- [ ] `[NEW]` `ancient-blooded-dwarf` heritage is available only to characters with the Dwarf ancestry.
- [ ] `[NEW]` Selecting Ancient-Blooded heritage grants the character the `call-on-ancient-blood` reaction in their abilities list.
- [ ] `[NEW]` When a character with this heritage is about to make a saving throw against a magical effect (trigger: before rolling), the system prompts the player to use the reaction (if `reaction_available = true`).
- [ ] `[NEW]` When the reaction is used, the character gains +1 circumstance bonus to all saving throws until end of their current turn. This includes the triggering save.
- [ ] `[NEW]` Using the reaction sets `reaction_available = false` for the turn (standard reaction usage rule).
- [ ] `[NEW]` The +1 circumstance bonus is correctly applied to the triggering save roll and any subsequent saves in the same turn.
- [ ] `[NEW]` At end of turn, the circumstance bonus from this reaction expires.

## Edge Cases

- [ ] `[NEW]` Non-dwarf character cannot select Ancient-Blooded heritage; the heritage is filtered out of the available options.
- [ ] `[NEW]` If `reaction_available = false`, the reaction cannot be used (already spent this turn).
- [ ] `[NEW]` The +1 is a circumstance bonus — it does not stack with another circumstance bonus on saving throws (PF2e rule: same bonus type, highest applies).
- [ ] `[NEW]` The reaction prompt is only triggered for magical effects (not mundane attacks or physical saves); the AI combat engine must distinguish magical vs. non-magical sources.

## Failure Modes

- [ ] `[NEW]` Attempting to assign Ancient-Blooded to a non-dwarf character is rejected with a clear error.
- [ ] `[NEW]` Attempting to use a spent reaction returns an appropriate message ("Reaction already used this turn").

## Permissions / Access Control

- [ ] Heritage selection: only at character creation (or re-leveling if re-selection is allowed per rules — not applicable here; locked at creation per dc-cr-heritage-system).
- [ ] Reaction usage: only the character's controlling player may use the reaction; the GM may also trigger it on behalf of the player.

## Gameplay-rule alignment

- PF2e Source: "CALL ON ANCIENT BLOOD [reaction] — Trigger: You attempt a saving throw against a magical effect, but you haven't rolled yet. Your ancestors' innate resistance to magic surges. You gain a +1 circumstance bonus until the end of this turn. This bonus also applies to the triggering save."
- Circumstance bonus stacking: PF2e rules — only the highest circumstance bonus applies if multiple are active.

## Test path guidance (for QA)

| Requirement | Test path |
|---|---|
| Heritage assignment | Create dwarf; select Ancient-Blooded; verify call-on-ancient-blood in abilities |
| Non-dwarf rejection | Attempt Ancient-Blooded on elf; verify error/filtered-out |
| Reaction trigger on magical save | Simulate magic save against dwarf with this heritage; verify reaction prompt |
| Bonus application | Use reaction; roll save; verify +1 circumstance bonus applied to roll |
| Reaction consumption | Use reaction; verify reaction_available = false |
| Reaction spent rejection | Attempt reaction again when spent; verify rejection message |
| Non-magical save — no trigger | Simulate physical attack save; verify no reaction prompt for this heritage |
