# Acceptance Criteria — dc-cr-rituals

- Feature: Ritual Magic System
- Release target: 20260412-dungeoncrawler-release-z
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-29

## Scope

Define ritual magic as a separate QA-ready subsystem contract covering long casting times, caster roles, skill checks, and non-slot failure consequences for narrative-scale magic.

## Dependency checkpoints

- Related parent: dc-cr-spells-ch07 shipped the spell catalog, but ritual execution remains standalone scope.
- Existing seed asset: `CharacterManager::RITUALS` already contains CRB/APG ritual definitions and participant metadata for integration reuse.

## Happy Path

- [ ] `[NEW]` Rituals are represented separately from standard spellcasting and do not consume prepared spell slots or spontaneous spell slots.
- [ ] `[NEW]` A ritual definition captures casting time, primary caster requirements, optional/required secondary casters, and the relevant skill checks.
- [ ] `[NEW]` Ritual execution supports success, failure, and critical-failure outcomes with explicit consequences.
- [ ] `[NEW]` Rituals can be surfaced as campaign-scale actions without being mixed into everyday encounter spellcasting UI.

## Edge Cases

- [ ] `[NEW]` Rituals with long casting times (minutes to days) preserve progress and requirements across the full casting window.
- [ ] `[NEW]` Insufficient or invalid secondary casters block ritual completion with a clear validation path.
- [ ] `[NEW]` Narrative-only or partially manual ritual consequences are identified so QA can separate automation from manual verification.

## Failure Modes

- [ ] `[NEW]` Attempting to cast a ritual through the normal spellcasting action flow is rejected.
- [ ] `[NEW]` Missing required skill-check metadata or ritual participants fails validation rather than creating a partially resolved ritual.

## Security acceptance criteria

- Security AC exemption: spellcasting/rules-engine scope only; no new public routes expected beyond existing spellcasting, downtime, or session-action handlers.
