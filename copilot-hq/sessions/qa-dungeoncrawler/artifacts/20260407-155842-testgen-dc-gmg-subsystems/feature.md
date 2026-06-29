# Feature Brief: GMG Chapter 4 — Subsystems

- Work item id: dc-gmg-subsystems
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Gamemastery Guide, gmg/ch03-ch04
- Category: gm-tools
- Created: 2026-04-07
- DB sections: gmg/ch03/Baseline Requirements, gmg/ch03/Integration Notes, gmg/ch04/Baseline Requirements, gmg/ch04/Integration Notes
- Depends on: dc-cr-encounter-rules, dc-cr-exploration-mode, dc-cr-downtime-mode

## Description
Implement GMG chapter 4 subsystem framework and variant rules (REQs 2732–2737).
GMG ch04 covers pluggable subsystem mechanics: chases, influence, research,
infiltration, hexploration, vehicle rules, and extended downtime activities.
These are GM-configurable overlays with explicit setup, turn flow, and resolution
states. Baseline integration requirements: variant rules feature-flagged with
compatibility checks; subsystem framework supports pluggable mechanics.

## Security acceptance criteria

- Security AC exemption: game-mechanic GM tooling logic; no new routes or user-facing input beyond existing GM campaign management forms

## Roadmap section
- Book: gmg, Chapter: ch04
- REQs: 2732, 2733, 2734, 2735, 2736, 2737
- See `runbooks/roadmap-audit.md` for audit process.
