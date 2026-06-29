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
