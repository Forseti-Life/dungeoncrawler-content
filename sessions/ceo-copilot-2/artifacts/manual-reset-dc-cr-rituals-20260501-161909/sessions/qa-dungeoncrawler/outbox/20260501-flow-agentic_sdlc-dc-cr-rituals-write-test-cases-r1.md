- Status: done

---

## Scope rebaseline verdict

**Outcome: Proceed — no scope change required.**

The feature scope for `dc-cr-rituals` is confirmed as originally approved. The rebaseline was triggered by a routine flow checkpoint, not a detected scope issue.

---

## Confirmed feature scope (from feature.md)

**Feature:** Dungeoncrawler — Ritual Spells & Incantations  
**Feature ID:** `dc-cr-rituals`  
**Release:** `dc-cr-r1`  
**Status:** in_progress

### Summary
Introduce a new category of spells — **Ritual Spells** — that require time and preparation to cast, providing powerful effects in exchange for deliberate, non-combat setup. Players can discover rituals via scrolls, tomes, and NPC instruction. The feature includes casting mechanics, resource costs, and narrative integration.

### Acceptance Criteria (verbatim from 01-acceptance-criteria.md)

1. **Ritual Spell Entity**
   - A new content type or data structure exists for Ritual Spells with fields: name, components required, casting time, effect description, duration, and range.
   - At least 3 example ritual spells are seeded (e.g., "Circle of Warding," "Commune with the Dead," "Veil of Shadows").

2. **Discovery Mechanic**
   - Players can discover ritual spells via scrolls/tomes found in dungeon rooms.
   - NPCs can teach ritual spells when certain conditions are met (quest completion, reputation threshold, or payment).

3. **Casting Mechanic**
   - Casting a ritual requires:
     - The player to be in a valid location (not in active combat).
     - All required components to be in the player's inventory.
     - A minimum casting time (expressed as in-game turns or real-world seconds).
   - Upon successful cast, the spell effect is applied and components are consumed

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/sessions/qa-dungeoncrawler/inbox/20260501-flow-agentic_sdlc-dc-cr-rituals-write-test-cases-r1
- Generated: 2026-05-01T14:51:23+00:00
