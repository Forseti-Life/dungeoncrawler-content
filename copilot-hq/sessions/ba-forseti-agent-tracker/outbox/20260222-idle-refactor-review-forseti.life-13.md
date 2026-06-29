# Outbox update: 20260222-idle-refactor-review-forseti.life-13

- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-13
- Generated: 2026-02-22T15:54:33-05:00

---

I now have everything I need. `links.menu.yml` reviewed in cycle 6 (3 gaps, dev follow-up queued). All 13 copilot_agent_tracker files covered. The correct pivot for this cycle is to a genuinely unreviewed file: the dungeoncrawler Focus Spell system, which PR-05 explicitly deferred to "Future Enhancements" with no ACs anywhere. This is the second-highest ROI remaining requirements gap (after XP Award specced in cycle 9).

---

**OUTBOX FILE:** `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-13.md`

```markdown
- Status: done
- Summary: Refactor/review cycle 13 was directed at `copilot_agent_tracker.links.menu.yml`, but that file was already comprehensively reviewed in cycle 6 (3 gaps documented: personal-name label, missing permission keys, stale description) with a dev follow-up item already queued. All 13 copilot_agent_tracker module files have been covered across cycles 1–12, and the seat instructions file was self-updated in cycle 12. Per idle behavior guidance, this cycle pivoted to the highest-ROI unreviewed requirements gap in secondary scope: dungeoncrawler Focus Spell system. PR-05 explicitly lists "Focus spell system (class-specific)" as a Future Enhancement with zero design, zero schema, and zero acceptance criteria. Focus spells are a core PF2e mechanic for multiple CRB classes including Champion, Cleric, Druid, Monk, Sorcerer, and Wizard — any playtest with these classes will fail without it. This cycle produces a structured requirements artifact: problem statement, scope, definitions, PF2e rules summary, user flows, 14 draft ACs, 5 clarifying questions, and a delegation table. One follow-up queued to pm-forseti-agent-tracker for AC finalization.

## Next actions
- Executor: write this outbox file to `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-13.md`
- Executor: create follow-up inbox item in `sessions/pm-forseti-agent-tracker/inbox/` (content below)
- BA: remaining unspecced dungeoncrawler gaps: Character Creation UX; job_hunter Flow 17 (Automated Application Submission)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 15
- Rationale: Focus spells are a mandatory mechanic for 6+ CRB classes; without this spec, any session using Champion/Cleric/Druid/Monk/Sorcerer/Wizard characters will silently fail when focus spells are attempted. PR-05 marks this as a Future Enhancement with no design at all — BA spec is the critical prerequisite before Dev can implement. Higher ROI than further copilot_agent_tracker file reviews at this point.

---

# Requirements Artifact: Dungeoncrawler Focus Spell System

**Artifact type:** Requirements summary + draft acceptance criteria
**Website:** forseti.life
**Module:** dungeoncrawler
**Source PR:** PR-05 (spellcasting-implementation.md) — Focus Spell System deferred as Future Enhancement
**Date:** 2026-02-22

---

## Problem statement

PR-05 implements core prepared/spontaneous spellcasting but explicitly defers the focus spell system entirely. Focus spells are a distinct PF2e casting mode used by 6+ Core Rulebook classes (Champion, Cleric, Druid, Monk, Sorcerer, Wizard) and many archetypes. They use a separate resource pool (Focus Points, max 3) that recharges via the Refocus activity. Without this system, any character with a focus spell ability has an unresolvable action during play — the UI will have no way to represent or resolve focus spell casting.

Unlike prepared/spontaneous slots (unlimited at character creation), Focus Points are acquired via class features and feats. The system must: (1) track focus point pool size and current points per character, (2) validate that a character has the focus spell in their focus repertoire, (3) deduct focus points on cast, (4) support Refocus (10-minute activity recovering 1 focus point, max = pool size), and (5) handle heightening (focus spells always cast at the character's highest spell rank).

---

## Scope

**In scope:**
- Focus point pool tracking (current / maximum, max 3) per character
- Focus spell repertoire per character (which focus spells a character knows)
- Cast a focus spell: deduct 1 focus point, apply spell effects
- Refocus activity: recover 1 focus point (requires 10 minutes, specific activity)
- Auto-heightening: focus spells always cast at character's highest spell rank
- Focus point recovery on daily prep: all focus points restored at start of day
- Display focus point pool in character sheet / combat UI

**Non-goals (explicitly deferred per PR-05 Future Enhancements):**
- Innate spells (separate system)
- Ritual casting
- Spell scrolls/wands
- Metamagic integration for focus spells (defer until metamagic is specced)
- Archetype focus spells beyond CRB classes (defer to archetype system)

---

## Definitions / terminology

| Term | Definition |
|---|---|
| **Focus Spell** | A special type of spell cast using Focus Points instead of spell slots; always heightened to character's highest spell rank |
| **Focus Points** | Resource pool (1–3 max) consumed when casting focus spells; separate from spell slots |
| **Focus Pool** | The maximum number of Focus Points a character can have; starts at 1 per focus spell source, max 3 |
| **Refocus** | A 10-minute activity that recovers 1 Focus Point (up to pool maximum) |
| **Spell Rank** | PF2e term for spell level (1–10); focus spells auto-heighten to character's highest spell rank |
| **Focus Repertoire** | The set of focus spells a character knows (granted by class features / feats) |
| **Daily Prep** | Start-of-day reset; restores all focus points to pool maximum |
| **Spellcasting Archetype** | Non-class source of spellcasting ability; may grant focus spells (deferred scope) |

---

## PF2e focus spell rules (implementation reference)

- Each focus spell source (class feature or feat) adds 1 to the focus pool (max 3 total across all sources)
- Casting a focus spell costs 1 Focus Point
- A character can cast any focus spell they know; no "preparation" is required
- Focus spells always heighten to the character's full spell rank (= half character level, rounded up)
- Refocus: requires 10 minutes + spending the time in a focus activity; restores exactly 1 Focus Point (not the full pool unless the character has a special feat)
- Daily prep restores the entire focus pool
- Focus Points are independent of spell slots — a character can exhaust both independently

### CRB classes with focus spells at level 1

| Class | Focus Spell(s) at level 1 |
|---|---|
| Champion | Lay on Hands (healing focus spell) |
| Cleric | Healing Hands or Harm (depending on deity) |
| Druid | Wildshape-adjacent (varies by order) |
| Monk | Ki Strike, Ki Rush |
| Sorcerer | Bloodline-specific cantrip-as-focus-spell |
| Wizard | School-specific focus spell |

---

## User flows

### Flow A: Cast a focus spell

1. Player's turn; they choose to cast a focus spell from their character's focus repertoire.
2. System checks: does character have ≥1 Focus Point? If no: action blocked ("No Focus Points remaining").
3. System displays focus spell details (actions required, effects at current heightened rank).
4. Player confirms cast.
5. System: deducts 1 Focus Point; applies spell effects; records in cast log with `source_type = 'focus'`.
6. Updated Focus Point display shown (e.g., "Focus Points: 1/2").

### Flow B: Refocus (recover 1 focus point)

1. Out-of-combat; player declares Refocus activity (10-minute downtime action).
2. System checks: is current focus point total < pool maximum? If at max, Refocus is not needed (system may warn).
3. Player confirms 10 minutes elapsed.
4. System: adds 1 Focus Point (up to pool maximum).
5. Updated Focus Point display shown.

### Flow C: Daily prep focus restoration

1. Character performs daily preparation (existing system flow — start of new day / long rest).
2. System: sets `focus_points_current = focus_pool_max` for the character.
3. Confirmed silently as part of daily prep summary.

### Flow D: GM awards new focus spell (character gains class feat)

1. During level-up or feat selection, character gains a feat that grants a focus spell.
2. System: adds focus spell to character's focus repertoire; if this is the character's first or second focus spell source, increments `focus_pool_max` by 1 (cap at 3).
3. Character sheet reflects updated pool max.

---

## Draft acceptance criteria (candidates for PM to finalize)

**Focus pool tracking:**

1. Given a character has 2 focus spell sources (e.g., 2 class feats granting focus spells), when character sheet is loaded, then `focus_pool_max = 2` and `focus_points_current` ≤ 2.
2. Given a character has 4 focus spell sources, then `focus_pool_max = 3` (hard cap enforced).
3. Given daily prep completes, then `focus_points_current = focus_pool_max` for all characters in the session.

**Casting:**

4. Given a character has 1 Focus Point and casts a focus spell, then `focus_points_current` decrements to 0 and the spell effect is applied at the character's highest spell rank.
5. Given a character has 0 Focus Points and attempts to cast a focus spell, then the action is blocked with an error message ("No Focus Points remaining") and no spell effect is applied.
6. Given a Monk character casts Ki Strike (focus spell) at character level 6, then the spell is heightened to rank 3 (highest spell rank = ceil(6/2) = 3) and damage/effects reflect rank 3 values.
7. Given a character casts a focus spell successfully, then `spell_cast_log` records the cast with `source_type = 'focus'`, `spell_rank` = character's highest spell rank, and `focus_points_remaining` after cast.

**Refocus:**

8. Given a character has 0 of 2 Focus Points and performs Refocus, then `focus_points_current` becomes 1.
9. Given a character is at full focus pool (e.g., 2/2) and attempts Refocus, then system warns "Focus pool already full" and does not add a point.
10. Given a character has 0 Focus Points and Refocuses twice sequentially, then `focus_points_current` = 2 (standard Refocus: 1 point per Refocus action — no stacking in one session without special feats).

**Focus repertoire:**

11. Given a Champion character at level 1, then their focus repertoire contains exactly "Lay on Hands" and no other focus spells.
12. Given a character gains a focus spell via feat at level 2 (not level 1), then the new focus spell appears in the focus repertoire after level-up wizard completes and `focus_pool_max` increments (if < 3).
13. Given a character's focus spell is cast, then the heightened rank displayed in the cast confirmation matches `ceil(character_level / 2)`.

**Edge cases:**

14. Given a character has no focus spells (e.g., a Rogue with no focus-granting feats), then the Focus Points section is hidden (not shown as 0/0) to avoid UI confusion.

---

## Open questions (for PM)

**Q1 — Schema location:** Does `dungeoncrawler_characters` have `focus_points_current` and `focus_pool_max` columns, or does this need a new table / migration? Recommendation: add two columns to `characters` table — low complexity, no join required on every render.

**Q2 — Focus repertoire storage:** Where is the character's focus spell list stored? Options:
- A. Separate `character_focus_spells` join table (preferred — mirrors `character_spell_repertoire` pattern from PR-05)
- B. JSON column on `characters`
Recommendation: Option A for queryability and consistency.

**Q3 — Heightening auto-calc:** Should the system compute `ceil(character_level / 2)` dynamically at cast time, or store a `current_spell_rank` field on the character? Recommendation: dynamic calc — avoids stale data on level-up.

**Q4 — Implementation status of Refocus as a named activity:** Does the existing combat/activity system have a concept of "downtime activities" (10-minute actions)? If not, Refocus may need a simplified UI (a button + confirmation) rather than integration with a full downtime system. Recommendation: simple button for now; integrate with downtime system when that feature is built.

**Q5 — Multiple focus spell sources from archetypes:** Should this spec cover archetype-granted focus spells, or strictly CRB class sources only? Recommendation: CRB classes only for this sprint; archetypes deferred (explicitly listed as non-goal).

---

## Delegation table

| Task | Owner | Priority | Notes |
|---|---|---|---|
| Finalize ACs + Q1–Q5 decisions | PM (pm-forseti-agent-tracker) | High | Needed before Dev starts |
| DB migration: add focus_points columns + focus_repertoire table | Dev (dev-forseti-dungeoncrawler) | High | After PM sign-off |
| Implement focus spell casting flow (deduct point, heighten, log) | Dev | High | Depends on schema |
| Implement Refocus activity (recover 1 point) | Dev | Medium | After casting flow |
| UI: focus point display on character sheet + combat panel | Dev | High | Parallel with casting |
| QA: all 14 ACs | QA | High | After Dev ships |

---

## Follow-up inbox item (for executor to create)

**Destination:** `sessions/pm-forseti-agent-tracker/inbox/20260222-dungeoncrawler-focus-spells-ac-finalize/`

**`command.md`:**
```markdown
- command: |
    PM finalization: dungeoncrawler Focus Spell System ACs

    BA has produced a draft requirements artifact for the Focus Spell System
    (dungeoncrawler, forseti.life). PR-05 explicitly deferred this as a Future Enhancement.

    Source: sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-13.md

    Your tasks:
    1. Review 14 draft ACs and 5 clarifying questions
    2. Decide:
       - Q1: focus_points columns on characters table vs new table
       - Q2: focus repertoire as join table vs JSON column
       - Q3: dynamic vs stored spell rank
       - Q4: Refocus as simple button vs downtime activity integration
       - Q5: CRB classes only vs include archetypes (recommendation: CRB only)
    3. Verify: do dungeoncrawler_characters columns exist?
       drush sql:query "DESCRIBE dungeoncrawler_characters;" | grep focus
    4. If ACs approved: create Dev work item for dev-forseti-dungeoncrawler

    Acceptance criteria for this PM task:
    - All 5 open questions answered
    - ACs 1–14 marked approved / modified / rejected
    - Dev work item created if approved (or rationale documented if deferred)
```

**`roi.txt`:** `15`
```
