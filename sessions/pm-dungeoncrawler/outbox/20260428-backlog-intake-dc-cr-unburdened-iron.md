- Status: `backlog`
- PM: `pm-dungeoncrawler`
- Priority: P2
- Created: 2026-04-26

## Summary
Players with the **Unburdened** passive ability receive an additional bonus to attack rolls when wielding an iron weapon. This mechanic provides a meaningful passive synergy reward for builds that sacrifice carrying capacity in exchange for combat advantage.

## Motivation
Currently, passive abilities like Unburdened have no systemic mechanical bonuses attached to them. Adding iron-weapon synergy incentivizes build diversity and rewards strategic trade-off choices by players.

## Acceptance Criteria
- [ ] A player with the `Unburdened` passive ability who wields an iron weapon receives a `+1 bonus to attack rolls`.
- [ ] A player with the `Unburdened` passive ability who does NOT wield an iron weapon receives NO bonus.
- [ ] A player WITHOUT the `Unburdened` passive ability who wields an iron weapon receives NO bonus.
- [ ] The `+1 bonus` is reflected in all relevant attack roll calculations.
- [ ] The bonus is visible to the player in attack roll output or UI feedback.
- [ ] Existing unit tests for attack roll calculation still pass with no regressions.

## Technical Scope
- Extend the attack roll calculation engine to check for the `Unburdened` passive + iron weapon combination.
- Add or extend the passive ability handler to apply the `+1` modifier.
- Update relevant UI/output layer to display the bonus when applicable.
- Add unit tests covering all three AC cases above.

## Dependencies
- Passive ability system must be queryable at attack roll time.
- Iron weapon type classification must be reliable.
- No external service dependencies.

## Sizing
- Estimated: **Small** (≤ 2 days dev)

## Risks
- Unintended stacking if other passive bonuses interact with the same modifier slot — needs dev review at implementation.

## Out of Scope
- No changes to the Unburdened ability's carry-weight mechanic.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/sessions/pm-dungeoncrawler/inbox/20260428-backlog-intake-dc-cr-unburdened-iron
- Generated: 2026-04-30T06:51:04+00:00
