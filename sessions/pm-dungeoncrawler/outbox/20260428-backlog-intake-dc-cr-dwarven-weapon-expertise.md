- Status: backlog
- PM owner: `pm-dungeoncrawler`
- Priority: P2
- Source: player suggestion (in-game feedback)
- ROI: 6

## Summary
Add a passive racial ability — **Dwarven Weapon Expertise** — to the Dwarf race in DungeonCrawler (Criminal title/world). When a Dwarf character wields a **War Pick** or **Warhammer**, they gain a +1 bonus to attack rolls. This mirrors the D&D 5e racial proficiency design pattern and provides meaningful mechanical identity for the Dwarf race.

## Acceptance Criteria
1. Dwarf characters receive a passive +1 to attack rolls when wielding a War Pick or Warhammer.
2. The bonus applies correctly in combat resolution (both player-facing display and server-side calculation).
3. The bonus does NOT apply when the Dwarf wields any other weapon type.
4. Character sheet or stat display reflects the active bonus when the correct weapon is equipped.
5. No regression on non-Dwarf races or other weapon types.
6. Feature is behind a content/config flag if runtime toggling is needed for staged rollout.

## Dependencies
- Race system (Dwarf race definition)
- Weapon type registry (War Pick, Warhammer entries must exist)
- Combat resolution module (attack roll calculation)
- Character sheet / stat display layer

## Open Questions
1. Does the War Pick and Warhammer currently exist in the weapon type registry, or do they need to be added?
2. Is the combat resolution module accessible for this type of passive modifier hook?
3. Should the bonus stack with other modifiers (e.g., enchantments), or is it capped?
4. Is there a feature-flag infrastructure already in place for staged rollout?

## Out of Scope

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/sessions/pm-dungeoncrawler/inbox/20260428-backlog-intake-dc-cr-dwarven-weapon-expertise
- Generated: 2026-04-30T04:55:31+00:00
