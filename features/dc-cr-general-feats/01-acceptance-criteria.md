# Acceptance Criteria — dc-cr-general-feats

- Feature: General Feats
- Release target: 20260412-dungeoncrawler-release-z
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-29

## Scope

Define the general-feat backlog as a QA-ready contract for the level-based feat schedule, catalog visibility, prerequisite validation, and representative feat effects that apply across classes.

## Dependency checkpoints

- Consolidated into: dc-cr-feats-ch05 (requirements covered in that feature's acceptance criteria)

## Happy Path

- [ ] `[NEW]` General feat slots open at levels 3, 7, 11, 15, and 19 and are distinct from class, ancestry, and skill feat slots.
- [ ] `[NEW]` The general-feat catalog includes the chapter's core cross-class options (for example Armor Proficiency, Shield Block, Toughness, and Incredible Initiative) with the metadata needed for the picker.
- [ ] `[NEW]` The feat picker only offers general feats whose prerequisites are satisfied by the current character build.
- [ ] `[NEW]` Taking a general feat applies its listed modifier, action, or rules flag to the character state in a testable way.

## Edge Cases

- [ ] `[NEW]` A feat available from multiple sources is still tracked in the correct feat pool and not duplicated across slot types.
- [ ] `[NEW]` Leveling without an eligible general feat choice leaves the slot open rather than auto-assigning an invalid feat.
- [ ] `[NEW]` Retraining recalculates downstream prerequisites for other feat selections.

## Failure Modes

- [ ] `[NEW]` General feats cannot be selected in ancestry-feat or class-feat slots.
- [ ] `[NEW]` Submitting a feat without meeting its prerequisites returns a validation error instead of corrupting the build.

## Security acceptance criteria

- Security AC exemption: feat-catalog and character-build scope only; no new routes or input surfaces beyond existing feat assignment handlers.
