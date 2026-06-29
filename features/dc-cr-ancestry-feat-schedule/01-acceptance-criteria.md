# Acceptance Criteria — dc-cr-ancestry-feat-schedule

- Feature: Ancestry Feat Schedule
- Release target: 20260412-dungeoncrawler-release-z
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-29

## Scope

Define the ancestry-feat progression contract so character leveling grants ancestry feat slots at levels 1, 5, 9, 13, and 17, and the feat picker only offers ancestry feats the character is eligible to take at each milestone.

## Dependency checkpoints

- Dependencies: none explicitly listed in feature.md; QA should validate against the surrounding Core Rulebook chapter implementation.

## Happy Path

- [ ] `[NEW]` Characters receive ancestry feat selection opportunities at levels 1, 5, 9, 13, and 17 and not at unrelated levels.
- [ ] `[NEW]` At each ancestry-feat milestone, the picker allows any ancestry feat whose level is less than or equal to the character level and whose prerequisites are satisfied.
- [ ] `[NEW]` Previously selected ancestry feats remain attached to the character after later level-ups and do not get replaced when a new slot opens.
- [ ] `[NEW]` Level-up output clearly indicates when an ancestry feat is pending so QA can verify the milestone is visible in the character progression flow.

## Edge Cases

- [ ] `[NEW]` A character leveling through multiple milestones in one rebuild or import can fill each missing ancestry-feat slot in order.
- [ ] `[NEW]` An ancestry with no currently legal feat options reports a blocked selection state instead of offering invalid choices.
- [ ] `[NEW]` Retraining or rebuild flows recalculate ancestry feat eligibility from the current level and ancestry rather than leaving stale choices in place.

## Failure Modes

- [ ] `[NEW]` Submitting an ancestry feat above the character level or without prerequisites returns a validation error instead of being silently accepted.
- [ ] `[NEW]` A character cannot mutate ancestry-feat slots belonging to another character or campaign context.

## Security acceptance criteria

- [ ] Ancestry-feat selection endpoints require authenticated character-owner or GM access.
- [ ] POST/PATCH ancestry-feat mutation routes require `_csrf_request_header_mode: TRUE`.
- [ ] Server-side validation enforces ancestry, level, and prerequisite checks before persisting a feat choice.
- [ ] QA verifies a user cannot mutate ancestry-feat slots belonging to another character.
