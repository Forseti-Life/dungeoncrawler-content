# Feature Brief: Tactical Grid System

- Work item id: dc-cr-tactical-grid
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release:
20260409-dungeoncrawler-release-e
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 301–600
- Category: rule-system
- Created: 2026-02-27

## Goal

Implement the 5-foot grid system used for tactical combat. The grid defines positions for all creatures, determines reach, areas of effect, flanking, and movement costs. Each grid square = 5 feet. This spatial model is required for accurate adjudication of movement (Stride action), melee reach (typically 5 or 10 feet), burst/cone/line area spells, and flanking bonuses. Without the grid, combat spatial rules cannot be resolved correctly.

## Source reference

> "The chaos of combat can be difficult to imagine, so many groups use maps to represent the battlefield. These maps are marked with a 1-inch grid, and each square represents 5 feet in the game. Miniatures and illustrated tokens called pawns are used to represent the characters and the adversaries they face."

## Implementation hint

Grid model: 2D coordinate system (x, y) where each unit = 1 square = 5 feet. Creature entity gains `position: {x, y}` and `size` (Small/Medium = 1×1, Large = 2×2, etc.). Distance calculation uses Pathfinder's "every other diagonal costs extra" rule (or simplified Euclidean for digital). API functions: `get_distance(a, b)`, `get_area_targets(origin, shape, size)`, `is_flanking(attacker1, attacker2, target)`. Renders to a map component in the dungeoncrawler frontend.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: grid state is session-scoped; only session participants may read/write; `_session_participant_access: TRUE` enforced
- CSRF expectations: all POST/PATCH grid position routes require `_csrf_request_header_mode: TRUE`
- Input validation: coordinates validated as integers within grid bounds server-side; not client-asserted
- PII/logging constraints: no PII logged; session id + character id + position only

## Roadmap section

- Roadmap: Core Rulebook
