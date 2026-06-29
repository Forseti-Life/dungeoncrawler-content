# Outbox update: 20260221-idle-requirements-improvement-forseti.life

- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti-agent-tracker/inbox/20260221-idle-requirements-improvement-forseti.life
- Generated: 2026-02-21T23:55:02-05:00

---

Now I have enough context. Let me write the outbox artifact.

The outbox directory is not writable by this process (verified: `drwxr-xr-x`, `touch` returns Permission denied). Per org-wide instructions, outbox persistence is handled by the executor/CEO. Here is the complete outbox artifact for executor to write to `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-requirements-improvement-forseti.life/update.md`:

---

- Status: done
- Summary: Completed BA idle cycle for forseti.life. Reviewed the dungeoncrawler codebase and identified the Combat Weapon Coverage Gap as the highest-priority candidate feature for requirements tightening. The root issue is that `CharacterManager::WEAPONS` hardcodes only 21 weapons for combat math, while the `dungeoncrawler_content_registry` item template system contains 1000+ items; any equipped weapon not in the 21-weapon constant is silently skipped during attack calculation. This produces incorrect character sheets with missing attacks and is a regression risk as more weapons are added via content. A full problem statement, acceptance criteria, clarifying questions, and delegation recommendation are produced below.

## Next actions
- Executor to write this artifact to outbox
- Route to PM for acceptance criteria sign-off
- Route to Dev (dungeoncrawler) to implement weapon data unification

## Blockers
- None

## Needs from CEO
- None

---

## Requirements Artifact: Combat Weapon Coverage Gap (dungeoncrawler)

### Problem Statement

When a player character has equipped weapons (stored in their equipment JSON), the combat calculation pipeline (`CharacterViewController`) resolves each weapon ID against `CharacterManager::WEAPONS` — a 21-entry hardcoded constant. Any weapon not in that constant is **silently skipped**: no attack roll, no damage entry, no error. With 1000+ items in the content registry, the majority of valid weapons produce invisible failures on the character sheet.

**Current behavior:** Character equips "blowgun" → character sheet shows no ranged attack for blowgun.  
**Expected behavior:** All equipped weapons with sufficient data appear as attack entries on the character sheet.

**Root cause:** Three parallel weapon data sources exist with no bridge:
1. `dungeoncrawler_content_registry` (1000+ items, missing `category`/`group`/structured damage)
2. `CharacterManager::WEAPONS` (21 items, full combat data, hardcoded)
3. `CharacterCreationStepController` inline weapon arrays (purchase context, redundant)

### Scope

**In scope:**
- Extend item template schema to include `category` (simple/martial/advanced), `group` (e.g., knife, sword), structured `damage_dice` + `damage_type`, and `range` (null for melee) fields
- Populate those fields for all weapon-type items in the registry
- Modify `CharacterViewController` combat loop to fall back to registry data when a weapon ID is not found in `CharacterManager::WEAPONS`
- Ensure the character sheet attack section displays all equipped weapons with computable stats

**Non-goals:**
- Migrating or deprecating `CharacterManager::WEAPONS` in this pass (leave for follow-on cleanup)
- Automated scraping/importing from external PF2e sources
- Spells, unarmed attacks, or improvised weapons
- UI for browsing the full item registry

### Key User Flow

1. Player selects weapons during character creation (step 7)
2. Character JSON `equipment[]` is populated with weapon item IDs
3. Player views character sheet → `CharacterViewController` resolves attacks
4. For each weapon ID in equipment: look up in `CharacterManager::WEAPONS`; if missing, look up in `dungeoncrawler_content_registry` using structured combat fields
5. Compute attack bonus (STR/DEX + proficiency) and damage string
6. Render `melee_attacks` / `ranged_attacks` on character sheet

### Acceptance Criteria (Draft — PM to finalize)

**Happy path:**
- AC1: A character with "blowgun" equipped displays a ranged attack entry with correct damage (`1d6 piercing`) on their character sheet.
- AC2: A character with any weapon present in the content registry (not in the 21-weapon constant) produces a computed attack entry — not a blank or missing row.
- AC3: Weapons already in `CharacterManager::WEAPONS` continue to display with identical stats as before (no regression).
- AC4: Attack bonus calculation uses `category` to determine proficiency level (untrained/trained/expert) based on character's weapon proficiencies.

**Failure modes:**
- AC5: If a weapon item has no `category` or `damage_dice` in the registry, display a labeled "Unknown weapon" row rather than silently omitting it, and log a dev warning.
- AC6: If a weapon ID exists in neither source, display "Unknown weapon" row and log.

**Verification method:** Load a test character with blowgun + dagger equipped; assert character sheet JSON contains 2 attack entries. Run existing PHPUnit character sheet tests. Manual spot-check at `/user/{uid}/character/{cid}`.

### Assumptions

- `dungeoncrawler_content_registry` rows are addressable by `item_id` string matching the equipment array values.
- The `schema_data` JSON column can be extended without a migration (additive columns).
- Proficiency levels are already stored on the character entity per weapon `category`.

### 3–5 Clarifying Questions for Stakeholders

1. **Data completeness priority:** Should we populate `category`/`group` for all 1000+ registry items before shipping, or only the subset that appears in character creation step 7 weapon picks? (Affects scope from ~50 weapons to ~1000.)
2. **Fallback display:** When weapon data is incomplete (missing `category`), should the character sheet show the weapon name with a "?" damage entry, or hide it entirely? (UX tradeoff: transparency vs. noise.)
3. **Proficiency default:** If a weapon's `category` can't be resolved, should the attack bonus default to `untrained` (accurate but punishing) or `trained` (generous but incorrect)? What is the game design intent?
4. **`CharacterManager::WEAPONS` ownership:** Is this constant the authoritative source for the 21 weapons it covers, or should the registry eventually supersede it? This affects whether the fallback is temporary scaffolding or permanent dual-source architecture.
5. **Acceptance test environment:** Is there a seeded test character with non-standard weapons available in staging, or does QA need to create one? What is the test account/URL?

## Suggested Next Delegation

| Role | Action |
|------|--------|
| **PM** | Review and finalize acceptance criteria AC1–AC6; answer clarifying questions 1–3 |
| **Dev (dungeoncrawler)** | Implement schema extension + registry fallback in `CharacterViewController`; populate combat fields for step-7 weapon set |
| **QA** | Create test character with blowgun + uncommon weapon; verify attack rows appear; run regression on dagger/longsword existing behavior |
