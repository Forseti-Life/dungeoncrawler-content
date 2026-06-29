# Outbox update: 20260222-idle-refactor-review-forseti.life-14

- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-14
- Generated: 2026-02-22T16:08:39-05:00

---

I have enough context. No character creation PR exists in the docs. `DashboardController.php` has been reviewed in cycles 3–5 and 8. All copilot_agent_tracker files are covered. The correct pivot is the dungeoncrawler Character Creation UX — the last major unspecced gap — which has no PR doc at all, making it a clean requirements-from-scratch artifact.

---

**OUTBOX FILE:** `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-14.md`

```markdown
- Status: done
- Summary: Refactor/review cycle 14 was directed at `DashboardController.php`, which has been reviewed in cycles 3, 4, 5, and 8 with 14+ gaps documented and multiple dev follow-up items already queued. Per seat instructions, when the copilot_agent_tracker module sweep is complete, idle cycles pivot to dungeoncrawler/job_hunter requirements gaps. This cycle produces the last major unspecced dungeoncrawler requirements artifact: Character Creation UX. No character creation PR doc exists in `~/forseti.life/docs/dungeoncrawler/` — PR-05 covers spellcasting and PR-06 covers leveling, but character creation (selecting ancestry, background, class, distributing ability boosts, choosing initial feats) has zero design documentation. This is a critical gap: without a character creation flow, no player can start a new campaign session. The artifact below provides problem statement, scope, definitions, 8-step user flow, 15 draft ACs, 5 clarifying questions, and a delegation table. One follow-up queued to pm-forseti-agent-tracker for AC finalization.

## Next actions
- Executor: write this outbox file to `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-14.md`
- Executor: create follow-up inbox item in `sessions/pm-forseti-agent-tracker/inbox/` (content below)
- BA: all major dungeoncrawler requirements gaps now specced (Combat, Conditions, Level-Up, Spellcasting, XP Award, Focus Spells, Character Creation); remaining unspecced item is job_hunter Flow 17 (Automated Application Submission)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 18
- Rationale: Character creation is the mandatory first step for any new player or new campaign; without a verified implementation, no session can begin with a new character. No PR doc exists at all for this feature — this is the highest-impact undocumented gap remaining in the dungeoncrawler module. Every other specced feature (combat, spellcasting, leveling, XP) is unreachable without a character to play.

---

# Requirements Artifact: Dungeoncrawler Character Creation UX

**Artifact type:** Requirements summary + draft acceptance criteria
**Website:** forseti.life
**Module:** dungeoncrawler
**Source:** No existing PR doc — greenfield requirements
**Date:** 2026-02-22

---

## Problem statement

Dungeoncrawler has extensive specs for combat (PR-02/03), spellcasting (PR-05), and leveling (PR-06), but no design documentation for the character creation flow that produces the initial character. In PF2e, character creation is a structured multi-step process: choose ancestry, choose background, choose class, distribute 4 free ability boosts, choose initial feats (ancestry feat + class feat at level 1 + skill feat at level 1 for many classes), set initial HP, and derive all secondary statistics. Without this flow, a new player cannot create a character, and no existing feature is reachable.

The goal is a wizard-style creation flow that walks a player through each required decision in order, validates prerequisites, and produces a complete, playable level-1 character stored in the DB.

---

## Scope

**In scope:**
- Step 1: Choose ancestry (human, elf, dwarf, gnome, halfling, goblin — CRB only for MVP)
- Step 2: Choose heritage (ancestry sub-type, e.g., Half-Elf for Human)
- Step 3: Choose background (grants 2 ability boosts + trained skill + skill feat)
- Step 4: Choose class (fighter, wizard, cleric, rogue, ranger, druid, monk, champion, sorcerer, bard — CRB)
- Step 5: Distribute 4 free ability boosts (from ancestry + background + class + free)
- Step 6: Choose initial feats (ancestry feat, class feat at level 1, skill feat from background)
- Step 7: Set character name and optionally portrait/description
- Step 8: Confirm and commit — write complete character row to DB with all derived stats

**Non-goals:**
- Multi-class / archetype dedication at creation (level 2+ feature)
- Custom ancestries / homebrew classes
- Character import from Pathbuilder or PDF
- Character portrait image upload (name only for MVP)
- Ability score allocation method variants (no point-buy or dice-roll — PF2e uses fixed boosts only)

---

## Definitions / terminology

| Term | Definition |
|---|---|
| **Ancestry** | Character's species/lineage (Human, Elf, Dwarf, etc.); grants HP, speed, size, traits, and 2 ability boosts + 1 ability flaw (varies by ancestry) |
| **Heritage** | Ancestry sub-type selected at creation (e.g., Half-Elf for Human, Unbreakable Goblin for Goblin) |
| **Background** | Character's pre-adventuring life; grants 2 ability boosts (one fixed, one free), trained skill, and a skill feat |
| **Class** | Character's adventuring archetype; determines Hit Points per level, proficiencies, class DC, and class features |
| **Ability Boost** | +2 to an ability score (or +1 if already 18+); boosts stack — a character may receive multiple boosts to the same score via different sources |
| **Ability Flaw** | -2 to an ability score; some ancestries impose 1 flaw |
| **Free Ability Boosts** | At creation, each character distributes 4 "free" boosts (to any ability score, no repeats among the 4 free boosts) |
| **Ancestry Feat** | A feat granted at level 1 from the character's ancestry feat list |
| **Class Feat** | A feat granted at level 1 from the character's class feat list |
| **Skill Feat** | A feat that enhances skill use; level 1 skill feat comes from background selection |
| **Key Ability Score** | The class's primary ability score (determines class DC and spell attack rolls for casters) |
| **Initial HP** | Ancestry base HP + (Class HP/level × 1) + Constitution modifier |

---

## PF2e ability boost sources at level 1 (4 sources total)

| Source | Boosts granted |
|---|---|
| Ancestry | 2 fixed boosts (ancestry-specific) + possibly 2 free boosts (Human) |
| Ancestry flaw | -2 to 1 ability (ancestry-specific; Human has none) |
| Background | 1 fixed boost + 1 free boost |
| Class | 1 fixed boost to Key Ability Score |
| Free boosts | 4 free boosts (any ability, no repeats among the 4) |

Total at level 1: 4 fixed + 4 free ability boosts (minus any flaws).

---

## User flows

### Flow A: Full 8-step character creation wizard

**Step 1 — Choose Ancestry**
- Player sees ancestry cards (Human, Elf, Dwarf, Gnome, Halfling, Goblin) with HP, speed, size, traits, ability boosts/flaws summary
- Player selects one ancestry
- System records selection; pre-populates ancestry ability boosts and flaw

**Step 2 — Choose Heritage**
- Player sees heritage options for their chosen ancestry (e.g., for Elf: Ancient Elf, Cavern Elf, Desert Elf, Seer Elf, Whisper Elf, Woodland Elf)
- Player selects one heritage
- System records selection

**Step 3 — Choose Background**
- Player sees background list with ability boost summary, trained skill, and skill feat description for each
- Player selects one background
- System records fixed ability boost and notes the free ability boost from background (resolved in Step 5)
- System notes the trained skill (character is Trained in that skill from background)
- System auto-assigns the background skill feat

**Step 4 — Choose Class**
- Player sees class cards with HP/level, Key Ability Score, proficiency summary, and 1-sentence description
- Player selects one class
- System records class and applies class Key Ability Score boost (resolved in Step 5)
- System assigns class features (class DC, initial proficiencies, class-specific features)

**Step 5 — Distribute Ability Boosts**
- System displays ability score grid showing:
  - Fixed boosts from ancestry (locked)
  - Ancestry flaw if any (locked)
  - Fixed boost from background (locked, user already chose background)
  - Key Ability Score boost from class (locked)
  - 4 free boosts to distribute (ancestry free, background free, 2 remaining free boosts)
- Player assigns the free boosts to ability scores (no repeats among the 4 free boosts)
- System shows derived modifier (score → modifier) in real time
- System validates: no free boost assigned twice to same score

**Step 6 — Choose Feats**
- Player sees 3 feat selection panels:
  - Ancestry feat (filtered list of level 1 ancestry feats for chosen ancestry)
  - Class feat (filtered list of level 1 class feats for chosen class)
  - Skill feat from background (auto-assigned from background selection in Step 3 — may display for confirmation, not selectable)
- Player selects ancestry feat and class feat
- System validates prerequisites (most level 1 feats have no prerequisites; system checks anyway)

**Step 7 — Character Identity**
- Player enters character name (required, max 80 chars)
- Player optionally enters character description/backstory (free text, optional)
- Player selects character pronouns (optional dropdown: he/him, she/her, they/them, other)

**Step 8 — Review & Confirm**
- System displays summary: name, ancestry, heritage, background, class, all ability scores + modifiers, HP, speed, proficiencies, feats
- Player confirms
- System commits character to DB: `INSERT` into `dungeoncrawler_characters` with all derived stats calculated
- System redirects to character sheet

---

## Derived stats calculated on commit

| Stat | Formula |
|---|---|
| Initial HP | Ancestry base HP + class HP/level × 1 + CON modifier |
| AC (unarmored) | 10 + DEX modifier + proficiency bonus (trained = level + 2) |
| Speed | Ancestry base speed (modified by encumbrance — none at creation) |
| Perception | level + WIS modifier + proficiency bonus |
| Class DC | 10 + level + Key Ability modifier + proficiency bonus |
| Saving throws | CON/DEX/WIS modifier + proficiency bonus (varies by class) |
| Skill bonuses | Ability modifier + proficiency bonus for trained skills |
| Attack bonus (melee) | STR modifier + proficiency bonus |
| Attack bonus (ranged) | DEX modifier + proficiency bonus |
| Spell attack bonus | Key Ability modifier + proficiency bonus (casters only) |

---

## Draft acceptance criteria (candidates for PM to finalize)

**Wizard flow:**

1. Given a player starts character creation, then they are presented with Step 1 (Ancestry) and cannot skip to a later step without completing the current one.
2. Given a player selects an ancestry, then their ancestry ability boosts and flaw are locked in the ability score UI in Step 5 (non-removable).
3. Given a player selects Human ancestry, then they receive 2 additional free ability boosts (for a total of 6 free boosts at character creation, not 4).
4. Given a player selects a background, then the background's skill feat is auto-assigned (no player selection required) and the trained skill is added to the character's skill proficiency list.
5. Given a player chooses a class with a Key Ability Score (e.g., Fighter → Strength), then the Key Ability Score boost is locked in the ability score UI.

**Ability boosts:**

6. Given a player attempts to assign 2 of their 4 free boosts to the same ability score, then the system rejects the second assignment with an error ("Cannot boost the same ability score twice with free boosts").
7. Given a player has applied an ability flaw (e.g., Goblin's -2 to Wisdom), then the flaw is reflected in the final Wisdom modifier calculation even if the player also boosted Wisdom.
8. Given all ability boosts are assigned, then each ability score's modifier is displayed as `floor((score - 10) / 2)` and is correct for all 6 ability scores.

**Feat selection:**

9. Given a player is on the feat selection step, then only level 1 feats for their chosen ancestry/class are displayed (not feats requiring level 2+).
10. Given a player selects an ancestry feat, then selecting a different ancestry feat replaces the previous selection (not stacks).

**Derived stats on commit:**

11. Given a level-1 Dwarf Fighter is confirmed, then initial HP = 10 (Dwarf ancestry HP) + 10 (Fighter class HP/level × 1) + CON modifier.
12. Given a level-1 character commits, then a row is inserted in `dungeoncrawler_characters` with `level = 1`, `experience_points = 0`, and all derived stats populated (no NULL values in required stat columns).
13. Given commit succeeds, then the player is redirected to the character sheet which displays all stats correctly.

**Validation and error handling:**

14. Given a player submits Step 7 with an empty character name, then form submission is blocked with "Character name is required."
15. Given a player navigates back to a previous step and changes a selection (e.g., changes ancestry), then all downstream selections that depended on the old choice are reset (e.g., ancestry feat is cleared, ancestry ability boosts update).

---

## Open questions (for PM)

**Q1 — Character creation entry point:** Where does a player initiate character creation? Options:
- A. From a campaign/session dashboard ("Create Character" button linked to a session)
- B. From a standalone character library ("/characters/new" route)
- C. Both (character is created standalone and then linked to a session)
Recommendation: Option C — characters should be portable across sessions.

**Q2 — DB schema status:** Does `dungeoncrawler_characters` table already exist with the required columns (ancestry, background, class, all ability score columns)? Verification: `drush sql:query "DESCRIBE dungeoncrawler_characters;"`. If the table exists but is missing columns, a migration is needed before implementation.

**Q3 — Ancestry/class/background data source:** Are CRB ancestries, classes, and backgrounds already seeded as static data in the DB (like the `xp_tables` seed in PR-06)? If not, seed data must be added as a migration before the wizard UI can be built.

**Q4 — Back-navigation behavior:** If a player navigates back from Step 6 (Feats) to Step 4 (Class) and changes their class, should all feat selections be cleared? Recommendation: Yes — feat eligibility depends on class; stale feats would be invalid.

**Q5 — Character name uniqueness:** Should character names be unique per player account, globally, or not enforced? Recommendation: unique per player (no two characters with the same name per user) — prevents confusion in character selection UI.

---

## Delegation table

| Task | Owner | Priority | Notes |
|---|---|---|---|
| Finalize ACs + Q1–Q5 decisions | PM (pm-forseti-agent-tracker) | High | Prerequisite for Dev |
| Verify/create DB schema (characters table + seed data) | Dev (dev-forseti-dungeoncrawler) | High | Q2/Q3 must be answered first |
| Implement 8-step wizard controller + form | Dev | High | Core deliverable |
| Implement derived stat calculation on commit | Dev | High | AC 11–13 |
| QA: all 15 ACs | QA | High | After Dev ships |

---

## Follow-up inbox item (for executor to create)

**Destination:** `sessions/pm-forseti-agent-tracker/inbox/20260222-dungeoncrawler-character-creation-ac-finalize/`

**`command.md`:**
```markdown
- command: |
    PM finalization: dungeoncrawler Character Creation UX ACs

    BA has produced a draft requirements artifact for the Character Creation UX
    (dungeoncrawler, forseti.life). No PR doc currently exists for this feature.

    Source: sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-14.md

    Your tasks:
    1. Review 15 draft ACs and 5 clarifying questions
    2. Decide:
       - Q1: Character creation entry point (recommendation: standalone + linkable to session)
       - Q2: Verify dungeoncrawler_characters table exists:
             drush sql:query "DESCRIBE dungeoncrawler_characters;"
       - Q3: Verify ancestry/class/background seed data exists:
             drush sql:query "SELECT COUNT(*) FROM dungeoncrawler_ancestries;" (or equivalent)
       - Q4: Back-navigation behavior (recommendation: reset downstream selections)
       - Q5: Character name uniqueness scope (recommendation: per-player)
    3. If schema/seed data missing: create a schema migration dev task first
    4. If ACs approved: create Dev work item for dev-forseti-dungeoncrawler covering wizard implementation

    Acceptance criteria for this PM task:
    - All 5 open questions answered
    - ACs 1–15 approved / modified / rejected
    - Dev work item(s) created (schema migration if needed, then wizard implementation)
```

**`roi.txt`:** `18`
```
