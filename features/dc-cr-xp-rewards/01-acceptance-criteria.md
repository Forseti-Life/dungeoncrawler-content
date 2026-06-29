# Acceptance Criteria — dc-cr-xp-rewards

- Feature: XP and Rewards System
- Release target: 20260412-dungeoncrawler-release-z
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-29

## Scope

Capture the XP-and-rewards backlog as a QA contract for encounter, hazard, and story-milestone XP accrual that advances character levels at the documented threshold and aligns with the newer xp-award-system dependency.

## Dependency checkpoints

- Depends on: dc-cr-character-leveling
- Consolidated into: dc-cr-xp-award-system (requirements covered in that feature's acceptance criteria)

## Happy Path

- [ ] `[NEW]` Characters can earn XP from encounters, hazards, and story milestones using the same progression ledger.
- [ ] `[NEW]` Reaching the configured level-up threshold (default 1,000 XP) triggers the character-leveling workflow instead of leaving XP in an unresolved state.
- [ ] `[NEW]` XP tracking remains aligned with the consolidated `dc-cr-xp-award-system` rules for award sources and threshold handling.
- [ ] `[NEW]` Party or campaign reward flows can identify which characters received XP and what source generated the reward.

## Edge Cases

- [ ] `[NEW]` XP progress across multiple rewards accumulates correctly until the threshold is crossed.
- [ ] `[NEW]` Rewards of 0 XP (for example trivial events under the broader system) are handled explicitly rather than silently ignored.
- [ ] `[NEW]` Characters already behind or ahead in XP state still level through the same validated threshold logic.

## Failure Modes

- [ ] `[NEW]` Invalid XP amounts or unknown reward-source types return validation errors.
- [ ] `[NEW]` Awarding XP to a character outside the active party/campaign context is blocked.

## Security acceptance criteria

- [ ] XP award writes require authenticated GM/system access; players can read progress but cannot mint XP for themselves.
- [ ] POST/PATCH XP award routes require `_csrf_request_header_mode: TRUE`.
- [ ] Server-side validation confirms the target characters belong to the active campaign/session before XP is applied.
- [ ] XP award logging records only the minimum campaign/session and character IDs required for traceability, with no unrelated PII.
