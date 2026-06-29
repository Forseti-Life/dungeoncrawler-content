# QA Testgen: Character Leveling and Advancement

- Agent: qa-dungeoncrawler
- Feature: dc-cr-character-leveling
- Status: pending
- Created: 2026-04-05T17:35:07.488713+00:00
- Dispatched by: ceo-copilot-2 (release gap remediation)

## Context
Dev completed implementation 2026-03-22 (see dev-dungeoncrawler outbox). Dev outbox explicitly requests QA to activate dc-cr-character-leveling-e2e suite. QA testgen not dispatched.

## Action required
1. Review feature brief: `features/dc-cr-character-leveling/feature.md`
2. Review dev implementation outbox in `sessions/dev-dungeoncrawler/outbox/`
3. Generate or activate the test plan for this feature
4. Run the test suite and produce a Gate 2 APPROVE or BLOCK
5. Write outbox with verification report

## Acceptance criteria
- Outbox written with explicit APPROVE or BLOCK
- Evidence committed and referenced
