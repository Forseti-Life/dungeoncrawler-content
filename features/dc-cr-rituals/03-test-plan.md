# Test Plan: dc-cr-rituals

## Coverage summary
- AC items: 9 (4 happy path, 3 edge cases, 2 failure modes)
- Test cases: 5 (TC-RTL-01-05)
- Suites: playwright (ritual casting, participant validation, campaign actions)
- Security: Security AC exemption: spellcasting/rules-engine scope only; no new public routes expected beyond existing spellcasting, downtime, or session-action handlers.
- Existing implementation seed: `CharacterManager::RITUALS` provides ritual catalog fixtures; tests should focus on execution flow and validation gaps rather than re-proving catalog ingestion.

---

## TC-RTL-01 — Feature availability and subsystem entry points
- Description: Rituals are represented separately from standard spellcasting and do not consume prepared spell slots or spontaneous spell slots.
- Suite: playwright/rituals
- Expected: Rituals are represented separately from standard spellcasting and do not consume prepared spell slots or spontaneous spell slots.
- AC: Happy Path-1

## TC-RTL-02 — Primary subsystem rule resolution
- Description: A ritual definition captures casting time, primary caster requirements, optional/required secondary casters, and the relevant skill checks.
- Suite: playwright/rituals
- Expected: A ritual definition captures casting time, primary caster requirements, optional/required secondary casters, and the relevant skill checks.; Ritual execution supports success, failure, and critical-failure outcomes with explicit consequences.
- AC: Happy Path-2, Happy Path-3

## TC-RTL-03 — State recovery, caps, or long-running flow handling
- Description: Ritual execution supports success, failure, and critical-failure outcomes with explicit consequences.
- Suite: playwright/rituals
- Expected: Ritual execution supports success, failure, and critical-failure outcomes with explicit consequences.; Rituals can be surfaced as campaign-scale actions without being mixed into everyday encounter spellcasting UI.
- AC: Happy Path-3, Happy Path-4

## TC-RTL-04 — Edge-case subsystem coverage
- Description: Rituals with long casting times (minutes to days) preserve progress and requirements across the full casting window.
- Suite: playwright/rituals
- Expected: Rituals with long casting times (minutes to days) preserve progress and requirements across the full casting window.; Insufficient or invalid secondary casters block ritual completion with a clear validation path.; Narrative-only or partially manual ritual consequences are identified so QA can separate automation from manual verification.
- AC: Edge Cases-1, Edge Cases-2, Edge Cases-3

## TC-RTL-05 — Validation errors and wrong-surface rejection handling
- Description: Attempting to cast a ritual through the normal spellcasting action flow is rejected.
- Suite: playwright/rituals
- Expected: Attempting to cast a ritual through the normal spellcasting action flow is rejected.; Missing required skill-check metadata or ritual participants fails validation rather than creating a partially resolved ritual.
- AC: Failure Modes-1, Failure Modes-2
