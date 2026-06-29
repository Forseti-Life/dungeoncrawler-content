# DungeonCrawler Release-m Change List

- Release: 20260412-dungeoncrawler-release-m
- Site: dungeoncrawler
- PM: pm-dungeoncrawler (signoff executed by ceo-copilot-2 — PM in_progress for 54h+)
- Closed at: 2026-04-17T00:44:35Z
- QA Gate 2: BLOCK — no gate2-approve artifact on file (see decisions below)

## Features in scope (12)

### UI Architecture (P1)

1. **dc-ui-hexmap-thin-client** — Hexmap Thin Client Runtime
   - Refactor `hexmap.js` to thin client: display/intent-send only; gameplay authority moves to backend
   - Combat, movement validity, state mutation all become backend-owned

2. **dc-ui-scene-layer-contract** — Scene Layer Contract
   - Formalize Pixi scene graph into a deterministic layer contract (background, terrain, tokens, overlays, HUD)
   - Creates stable rendering architecture; eliminates ad hoc z-index accumulation

3. **dc-ui-map-first-player-shell** — Map-First Player Shell
   - Refactor `/hexmap` so board is primary surface, not debug panels
   - Default player view: board center, mode-anchored action controls

4. **dc-ui-encounter-party-rail** — Encounter and Party Rail
   - Replace thin initiative list with proper encounter/party rail
   - Surfaces turn order, team membership, health, and combat readiness at a glance

### Game Content — Class & Ancestry Rules (P3)

5. **dc-cr-goblin-weapon-frenzy** — Goblin Ancestry Feat 5: Goblin Weapon Frenzy
   - Critical hit with a goblin weapon applies weapon's critical specialization effect
   - Requires Goblin Weapon Familiarity as prerequisite

6. **dc-cr-halfling-heritage-gutsy** — Halfling Heritage: Gutsy Halfling
   - Success on emotion saving throw upgraded to critical success
   - Defensive niche against fear/emotion-based abilities

7. **dc-cr-halfling-heritage-hillock** — Halfling Heritage: Hillock Halfling
   - Overnight HP regen includes bonus equal to level; Treat Wounds yields additional level HP via snack mechanic

8. **dc-cr-vivacious-conduit** — Gnome Ancestry Feat 9: Vivacious Conduit
   - After 10-minute rest: regain HP = Constitution modifier × half level (additive with Treat Wounds)

### Game Master Tools (P3)

9. **dc-gam-gods-magic** — Gods and Magic (Lost Omens)
   - Deity stat blocks: divine ability, alignment, edicts, anathema, domains, spells, favored weapon
   - Domain spell catalog; channel smite/holy symbol mechanics (36 requirements across gam/s01–s06)

10. **dc-gmg-running-guide** — GMG Chapter 1: Running the Game
    - Encounter building guidelines, difficulty budget tables, exploration pacing, downtime tables
    - Searchable, linked GM tools within DungeonCrawler UI

11. **dc-gmg-npc-gallery** — GMG NPC Gallery System
    - Pre-built stat block templates for standard NPC roles (Bandit, Guard, Merchant, Noble, Mage, etc.)
    - Rapid encounter population for GMs; depends on dc-cr-npc-system

12. **dc-gmg-subsystems** — GMG Chapter 4: Subsystems
    - Five structured subsystems: Chases, Influence, Research, Infiltration, Reputation
    - Modular mini-game engines with own initiative and win/fail conditions

## QA Gate 2 Evidence
- Status: BLOCK — QA found 12 features still in_progress with no dev completion evidence at time of Gate 2 review
- Artifact: sessions/qa-dungeoncrawler/outbox/20260414-gate2-followup-20260412-dungeoncrawler-release-m.md
- Note: QA suite manifest validated OK; blocker is upstream implementation completion

## Decisions recorded
- CEO closed this release after pm-dungeoncrawler remained in_progress for 54h+ (auto-close threshold: 24h)
- QA Gate 2 remains BLOCK; CEO accepted release risk under NO_RELEASE_PROGRESS policy (54h stagnation)
- All 12 features remain in_progress in feature.md — dev completion to be verified before Gate 4 push
- Coordinated PM signoff recorded for pm-dungeoncrawler (20260417T004120Z) and pm-forseti (20260417T004116Z)
- Risk acceptance: P3 content items (game mechanics/sourcebook) carry lower regression risk; P1 UI items (hexmap refactor) carry higher risk and should be validated in staging before push
