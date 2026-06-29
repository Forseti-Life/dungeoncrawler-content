# Acceptance Criteria — dc-cr-gm-tools

- Feature: GM Tools and Adventure Preparation
- Release target: 20260412-dungeoncrawler-release-z
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-29

## Scope

Convert the GM tools backlog into a concrete QA contract for encounter budgeting, environment/terrain references, NPC prep data, and loot generation so the AI GM has a defined rules surface to build against.

## Dependency checkpoints

- Consolidated into: dc-gmg-running-guide (requirements covered in that feature's acceptance criteria)

## Happy Path

- [ ] `[NEW]` GM prep tooling exposes encounter budget guidance by party level/size and threat category (Trivial, Low, Moderate, Severe, Extreme).
- [ ] `[NEW]` GM prep references include environment/terrain guidance, NPC stat-block structure, and loot-by-level lookup data required for session preparation.
- [ ] `[NEW]` AI GM or GM-facing prep flows can retrieve the budgeting and reward data without requiring players to perform manual rules math.
- [ ] `[NEW]` The feature contract identifies which outputs are read-only guidance and which are GM-triggered generation actions.

## Edge Cases

- [ ] `[NEW]` Encounter-budget calculations respond predictably when party size or level is outside the normal table range.
- [ ] `[NEW]` Loot generation handles missing or incomplete tables with an explicit validation/reporting path instead of producing nonsense rewards.
- [ ] `[NEW]` Player-facing surfaces do not expose GM-only prep details that would spoil encounters or hidden NPC data.

## Failure Modes

- [ ] `[NEW]` Invalid party size, level, or threat-category inputs return a validation error rather than generating misleading encounter budgets.
- [ ] `[NEW]` GM-only routes return 403 for non-GM roles.

## Security acceptance criteria

- [ ] GM-only prep or generation routes require authenticated GM/admin access and return 403 to anonymous or standard player roles.
- [ ] All GM prep POST/PATCH actions require `_csrf_request_header_mode: TRUE`.
- [ ] Generated encounter, loot, and NPC prep data is scoped to the current campaign/session context with no cross-campaign leakage.
- [ ] Logs and AI prompt payloads exclude unrelated player secrets/PII and record only minimum traceability identifiers.
