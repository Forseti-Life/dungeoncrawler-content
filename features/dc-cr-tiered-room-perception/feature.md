# Feature Brief: Tiered Room Perception

- Work item id: dc-cr-tiered-room-perception
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release:
- Feature type: enhancement
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-cr-exploration-mode
- Source: community_suggestion NID 46 (Talk to Forseti intake)
- Category: exploration-system
- Created: 2026-05-16

## Summary

Add tiered Perception-driven sensory detail to room exploration so every room always provides baseline sight and sound, while deeper details such as smell, touch/texture, and atmosphere/mood are unlocked through progressively harder Perception checks. This makes perceptive characters feel meaningfully better at scouting without making low-Perception characters miss the core room description.

## Goal

- Preserve a clear baseline room narrative for all players.
- Reward Perception investment with richer environmental detail and scouting value.
- Keep the mechanic grounded in the existing exploration loop rather than introducing a separate minigame.

## Operating decisions

- **Always free:** room descriptions always include sight and sound with no check required.
- **Tiered reveals:** additional sensory layers are authored separately and gated behind escalating Perception DCs.
- **Probe order:** the first optional layer should be the easiest and each subsequent layer should be harder to unlock.
- **No baseline regression:** failure on an optional sense never removes or rewrites the baseline sight/sound description.
- **Room-scoped discovery:** once an optional sensory detail is successfully revealed in a room, it remains visible for that room state instead of requiring repeated re-rolls.

## Non-goals

- No replacement of the existing room narration system with a fully procedural sensory simulator.
- No requirement to author every optional sensory layer for every room.
- No hidden critical path content that is only available through optional sensory checks.

## Gap analysis

| Requirement | Existing code path | Coverage status |
|---|---|---|
| Baseline room description rendering | room exploration / room description pipeline | Partial |
| Perception-driven optional room detail layers | room description authoring + exploration interaction flow | None |
| Persisting discovered sensory details per room state | exploration/session state + room payloads | None |
| UI affordance for probing deeper senses | hexmap / room exploration shell | None |

## Acceptance Criteria (link)

See `features/dc-cr-tiered-room-perception/01-acceptance-criteria.md`.

## Delivery shape

Ship this as one exploration enhancement with three linked surfaces:

1. authored room sensory layers + DC metadata
2. Perception-check resolution and persistence rules
3. client UX for requesting and displaying deeper sensory details

## Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Authors leave optional sensory layers blank, creating inconsistent UX | Medium | Low | Treat optional layers as additive; baseline sight/sound remains sufficient |
| Repeated probing becomes spammy | Medium | Medium | Persist revealed details per room state and define explicit retry rules |
| Hidden details leak without a successful check | Medium | High | Keep optional layers separate from the baseline payload and cover with regression tests |
| Perception resolution duplicates existing skill logic inconsistently | Medium | Medium | Reuse the existing exploration/skill-check path where possible instead of inventing a second roller |

## Security acceptance criteria

- Authentication/permission surface: only authenticated campaign players can request additional sensory probes in active play.
- CSRF expectations: any new POST/PATCH perception probe route must keep existing secured request-header protections.
- Input validation: requests must validate room/session identity, allowed sensory tier keys, and active character ownership before resolving a check.
- PII/logging constraints: logs should record operational IDs, sense tier requested, and outcome only; no player-entered freeform text is required.

## Roadmap section

- Roadmap: Dungeoncrawler exploration

## Latest updates

- 2026-05-16: Created and groomed from community suggestion NID 46 as a ready exploration enhancement covering authored sensory tiers, Perception-gated reveals, and room-state persistence.
