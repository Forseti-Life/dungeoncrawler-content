# Outbox update: 20260222-idle-requirements-improvement-forseti.life-7

- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti-agent-tracker/inbox/20260222-idle-requirements-improvement-forseti.life-7
- Generated: 2026-02-22T12:48:24-05:00

---

I have enough. The design doc exists but PR-06 is explicitly flagged as "target-state architecture" with a partial implementation note. The key gap is that there are no acceptance criteria anywhere specifying what constitutes a shippable level-up wizard, what the session atomicity contract is, or what the feat prerequisite check surface must cover. Here is the complete outbox artifact:

---

- Status: done
- Summary: Completed BA idle cycle for forseti.life (ninth pass, inbox item -7 re-run). Selected the Dungeoncrawler Level-Up Wizard Requirements as the top-ROI item, pivoting to dungeoncrawler to clear this long-standing design-only gap. PR-06 exists as a target-state architecture document with a verification note explicitly flagging it as "partial or planned" — there are no acceptance criteria, no atomicity contract, and no definition of done covering the multi-step wizard session, feat prerequisite validation, proficiency rank enforcement, or spell slot progression. Without this spec, Dev cannot determine what "done" looks like for the level-up wizard and the feature will remain in perpetual partial-ship. Full requirements artifact follows.

## Next actions
- Executor: write this artifact to outbox
- Route to PM (dungeoncrawler) for acceptance criteria sign-off (especially Q1 on partial-success rollback and Q4 on spell repertoire selection scope)
- Route dev task to dungeoncrawler Dev after PM sign-off — can be split into two sub-tasks: (a) automated steps (HP, proficiency, spell slots) and (b) player-choice steps (feats, ability boosts, skill increases)
- Note: `SchemaLoader::getClassData()` must be implemented before feat prerequisite checks can work — Dev should resolve this TODO as part of this task or as a prerequisite task

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 42
- Rationale: Level-up is the core character progression mechanic in dungeoncrawler — without a shippable wizard, characters are effectively frozen at their starting level. PR-06 is design-complete but has no acceptance criteria; this spec closes that gap and gives Dev a clear definition of done to ship against.

---

## ROI-Ranked Requirements Improvements (3 candidates)

| Rank | Item | Module | ROI | Rationale |
|------|------|--------|-----|-----------|
| 1 ✅ | Dungeoncrawler Level-Up Wizard Requirements | dungeoncrawler | 42 | Design-complete but no AC or definition of done; SchemaLoader::getClassData() is a confirmed TODO blocking schema-driven HP/feat loading |
| 2 | Automated Application Submission Spec (Flow 17) | job_hunter | 35 | Most complex flow (8-10 days, browser automation); speccing now unblocks Dev planning for Playwright vs. Puppeteer and MFA/CAPTCHA strategy |
| 3 | User Support Contact Form Spec (Flow 5) | job_hunter | 20 | Simplest remaining gap (1-2 day Webform/Contact task); low ROI but quick win when sprint capacity is available |

---

## Requirements Artifact: dungeoncrawler — Level-Up Wizard (Shippable Definition of Done)

### Problem Statement

Characters in dungeoncrawler earn XP through combat but there is no shipped level-up workflow. `PR-06-leveling-up-implementation.md` describes a multi-step wizard with a `level_up_sessions` table, `LevelingController`, and `processLevelUp()` atomic commit — but it is explicitly flagged as "target-state design; dedicated route/controller surface described below may be partial or planned." No acceptance criteria exist for any step.

Two specific implementation gaps are confirmed in documentation:
1. **`SchemaLoader::getClassData()` is a TODO/throws** (verified in `issue-1-character-class-hp-design.md`) — HP calculation during level-up and feat list loading both depend on this schema lookup.
2. **Feat prerequisite checking** references `feat_prerequisites` table but no spec defines which prerequisite types must be enforced at MVP vs. deferred.

**Current behavior:** Characters gain XP (combat awards XP); no level-up notification triggers; no wizard route is accessible to players; characters cannot gain new levels, feats, ability boosts, or spell slots.  
**Expected behavior:** When a character's XP ≥ 1,000, the character sheet shows a "Level Up Available" notification; player clicks → enters guided wizard → completes all required choices → wizard commits atomically → character sheet reflects the new level.

### Scope

**In scope:**
- Level-up eligibility detection: when `character.experience_points ≥ 1000`, show "Level Up Available" banner on character sheet at `/character/{id}`
- Wizard entry at `GET /character/{id}/level-up` (or `/character/leveling/start`): creates a `level_up_sessions` row; wizard cannot be started if character is in active combat
- Wizard steps (in order):
  1. **HP Increase** (automatic, no user choice): `class_hp_per_level + CON_modifier`; if CON modifier increased this level, retroactive HP added per prior level; displayed for confirmation
  2. **Ability Boosts** (levels 5, 10, 15, 20 only): player selects 4 ability scores to boost (+2 each, same score cannot be boosted twice in one round); all other levels: this step is skipped automatically
  3. **Skill Increases** (class-determined frequency): player selects which skill(s) to advance by one proficiency rank; only skills currently below `legendary` are offered; class table determines how many increases are available this level
  4. **Feat Selection** (class-determined, varies by level): player selects one or more feats from the appropriate list(s) (ancestry feat, class feat, general feat, skill feat) per class advancement table; feat list filtered by type, level cap, and met prerequisites
  5. **Spell Slots** (spellcasting classes only): new spell slots and slot count increases applied automatically; spontaneous casters additionally select new spells to add to repertoire
- Wizard summary/preview screen: shows all changes before committing
- Atomic commit at `POST /character/leveling/process`: all changes applied in a single DB transaction; `level += 1`, `XP -= 1000`, HP/proficiency/feats/spells updated; `level_up_sessions.is_complete = TRUE`
- If wizard is abandoned mid-way: `level_up_sessions` row is soft-deleted; no partial changes applied to character
- Post-commit: redirect to character sheet with "Level up complete! You are now level N" banner

**Non-goals:**
- Multiclassing feat selection (Phase 2)
- Archetype dedication prerequisites (Phase 2 — treat archetypes as standard feats for MVP)
- Free archetype variant rule support (shelved)
- XP award system (separate — combat already awards XP; this spec only covers the level-up wizard triggered by XP threshold)
- Retroactive stat recalculation beyond HP (Phase 2 — e.g., ability score changes cascading to derived stats beyond HP)

### Definitions

| Term | Definition |
|------|------------|
| `level_up_sessions` | DB table storing in-progress wizard state: `character_id`, `new_level`, `hp_increase`, `ability_boosts JSON`, `proficiency_increases JSON`, `selected_feats JSON`, `selected_spells JSON`, `is_complete`, `created_at`, `completed_at` |
| Atomic commit | All level-up changes applied in a single DB transaction; if any part fails, the entire transaction rolls back and the character remains at the previous level |
| Class advancement table | Per-class data in `game_classes` DB table (or schema JSON) defining which feats, skill increases, and abilities are granted at each level |
| `SchemaLoader::getClassData()` | PHP service method that reads class definition JSON to retrieve `hp_per_level`, feat grant schedule, and spell slot progression — currently a TODO; must be implemented for this task |
| Prerequisite enforcement | Feat prerequisite types that are checked at MVP: `level` (character must be ≥ N) and `feat` (must already have prerequisite feat). Deferred to Phase 2: `proficiency`, `ability_score`, `class_feature`, `other` |

### Key User Flows

**Flow A: Automatic steps (HP + proficiency) — no player choices required**
1. Character sheet shows "Level Up Available — You have 1,200 XP"
2. Player clicks "Level Up" → wizard opens at step 1 (HP Increase)
3. System computes: Fighter class HP = 10, CON modifier = +2 → display "You gain +12 HP (10 class + 2 CON)"
4. Player confirms → step auto-advances
5. No ability boosts this level (not level 5/10/15/20) → step skipped
6. No skill increases this level per Fighter class table → step skipped
7. Feat step: Fighter gains Class Feat at level 2 → player presented with filtered class feat list
8. Player selects "Power Attack" (prerequisites: level 1 Fighter — met) → feat added to `selected_feats`
9. No spellcasting → spell step skipped
10. Summary screen: "+12 HP, Power Attack feat selected"
11. Player confirms → atomic commit → character is now level 2

**Flow B: Ability boost level (level 5)**
1. Wizard reaches ability boost step
2. Player sees 6 ability score labels each with a dropdown
3. Player selects STR, DEX, CON, WIS (4 different scores)
4. Attempts to select STR twice → UI prevents ("Cannot boost the same ability twice")
5. Saves boosts → `ability_boosts = ["STR", "DEX", "CON", "WIS"]`

**Flow C: Wizard abandonment**
1. Player starts wizard → completes HP step → closes browser tab
2. `level_up_sessions` row exists with `is_complete = FALSE`
3. Player returns to character sheet → sees "Level Up Available" banner again
4. Clicking "Level Up" checks for existing incomplete session: resume from last completed step (HP confirmed) → continue at feat selection step
5. OR: player clicks "Start Over" → existing session soft-deleted, new session created

### Acceptance Criteria (Draft — PM to finalize)

**Happy path:**
- AC1: A character with `experience_points ≥ 1000` displays a "Level Up Available" banner on their character sheet; a character with `experience_points < 1000` does not.
- AC2: Navigating to `/character/{id}/level-up` creates a `level_up_sessions` row with the character's new target level and `is_complete = FALSE`.
- AC3: HP increase for a level-2 Fighter with CON modifier +2 is computed as 12 (10 class HP + 2 CON) and stored in `level_up_sessions.hp_increase` before the commit step.
- AC4: Feat selection step presents only feats of the correct type(s) for the character's class and level; feats with unmet `level` or `feat` prerequisites are excluded from the list.
- AC5: Selecting a feat and reaching the summary step shows the feat name and HP increase as a confirmation before committing.
- AC6: Committing (`processLevelUp`) applies all changes atomically: `character.level += 1`, `character.experience_points -= 1000`, `character.max_hp += hp_increase`, feat added to character feats, `level_up_sessions.is_complete = TRUE` — all in a single transaction.
- AC7: After commit, character sheet at `/character/{id}` shows the new level, updated max HP, and the newly selected feat.
- AC8: Ability boost step is presented only at levels 5, 10, 15, 20; at all other levels it is silently skipped.
- AC9: Spell slot progression for a spellcasting class (e.g., Wizard) is applied automatically (no user choice required for slot counts); spontaneous casters (e.g., Sorcerer) additionally see a spell repertoire selection step.

**Failure modes:**
- AC10: If `processLevelUp` transaction fails (DB error), the character's level, HP, XP, and feats are unchanged; `level_up_sessions.is_complete` remains FALSE; user sees an error message: "Level up failed — please try again."
- AC11: A character in `ACTIVE` combat state (active `combat_encounters` row) cannot start the level-up wizard; attempting returns: "Cannot level up during active combat."
- AC12: Attempting to boost the same ability score twice in one level-up (ability boost step) returns a validation error; the submission is rejected.
- AC13: If `SchemaLoader::getClassData()` fails to load class data, the wizard returns an error rather than proceeding with incorrect defaults: "Class data unavailable — contact support."

**Verification method:**
- PHPUnit: `LevelingWizardTest` — test AC3 HP calculation for Fighter (10 HP + CON) and Wizard (6 HP + CON); test AC4 feat filtering by level prerequisite; test AC6 atomic commit with mock DB transaction.
- PHPUnit: `LevelingAtomicityTest` — simulate DB failure mid-commit; assert character state is unchanged.
- Manual: Level a Fighter from 1 to 2 in staging; assert character sheet shows level 2, +12 HP, and Power Attack feat.
- Manual: Level a Sorcerer to 3 in staging; assert new spell slots applied and spell selection step appears.
- Manual: Start wizard, abandon, return to character sheet; assert "Level Up Available" still shown; assert resuming wizard resumes from last completed step.

### Assumptions

1. `SchemaLoader::getClassData()` must be implemented as part of this task or as a hard prerequisite; the schema JSON at `character_options_step2.json` already contains `hit_points` per class (verified in Issue #1 design doc).
2. The `feat_prerequisites` table (schema defined in PR-06) exists or will be created as part of this task; MVP only enforces `level` and `feat` prerequisite types.
3. The `game_classes` table has `hp_per_level`, `feat_schedule JSON` (which feats are granted at which level by type), and `skill_increases JSON` (how many skill increases per level) columns — or equivalent data is in schema JSON.
4. Combat state check uses existing `active_encounters` table — if `combat_participants` has an active row for this character in an `ACTIVE` encounter, level-up is blocked.
5. Spell slot auto-progression uses the existing `character_spell_slots` table (referenced in PR-06 pseudocode); no user interaction needed for slot counts.

### 3–5 Clarifying Questions for Stakeholders

1. **Partial-success rollback vs. resume:** If a player completes 3 of 5 wizard steps and the DB fails on commit, should the wizard offer to resume from step 3 (preserve session choices) or require starting over (simpler, less frustrating only if failures are rare)? Recommendation: resume from last step saved to `level_up_sessions` — preserves player choices without requiring re-selection.
2. **`SchemaLoader::getClassData()` priority:** This TODO is a prerequisite for schema-driven HP calculation and feat list loading. Should it be a separate pre-task (Dev task A) before the wizard (Dev task B), or bundled into the same ticket? Recommendation: separate task to allow parallel progress and avoid blocking the whole wizard on a single service method.
3. **Feat prerequisite depth for MVP:** PR-06's `feat_prerequisites` table supports 6 prerequisite types. The spec above limits MVP enforcement to `level` and `feat`. Is this acceptable, or must `proficiency` prerequisites (e.g., "must be Expert in Athletics") also be enforced at MVP? Proficiency prerequisites are common in class feat trees.
4. **Spell repertoire selection (spontaneous casters):** Sorcerer/Bard level-ups include choosing new spells to add to their known spell list. Is this step in scope for MVP, or should spontaneous casters automatically gain the maximum allowed new spells with a warning that manual adjustment is needed? (Tradeoff: auto-assign is simpler but gives no player choice on a significant character decision.)
5. **Level-up during downtime vs. combat:** PR-06 blocks level-up during active combat. Should it also be blocked during active quests (travel/exploration mode), or is downtime the only safe window? MVP recommendation: block only during active combat; allow during exploration/downtime.

### Delegation Table

| Role | Action |
|------|--------|
| **PM** | Finalize AC1–AC13; answer clarifying questions 1–5; confirm feat prerequisite depth for MVP; confirm spell repertoire selection scope |
| **Dev (dungeoncrawler)** | Implement `SchemaLoader::getClassData()` (pre-task); build multi-step wizard controller + `level_up_sessions` table; implement atomic `processLevelUp`; enforce level+feat prerequisites; auto-apply HP, proficiency, spell slots |
| **QA** | Test Fighter level 1→2 (HP + feat); test level 5 (ability boosts); test Sorcerer spell slot progression; test abandon + resume; test atomic rollback simulation (AC10); test combat-block (AC11) |
