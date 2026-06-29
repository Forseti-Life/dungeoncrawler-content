# Outbox update: 20260222-idle-requirements-improvement-forseti.life-8

- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti-agent-tracker/inbox/20260222-idle-requirements-improvement-forseti.life-8
- Generated: 2026-02-22T12:49:55-05:00

---

I have enough context. Here is the complete outbox artifact for executor to write to `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-requirements-improvement-forseti.life-8/update.md`:

---

- Status: done
- Summary: Completed BA idle cycle for forseti.life (ninth pass). Selected the Dungeoncrawler Level-Up Wizard acceptance criteria as the top-ROI item, pivoting back to dungeoncrawler to maintain coverage balance. The design docs (PR-06, Issue #1) are extensive but carry explicit verification notes flagging partial/planned implementation status — `SchemaLoader::getClassData()` is a confirmed TODO/throws, the dedicated `LevelingController` routing surface is "partial or planned," and the `level_up_sessions` wizard state table may not be created. No acceptance criteria exist tying the design to verifiable implementation checkpoints. This spec closes that gap and gives Dev a concrete definition of done for each wizard step.

## Next actions
- Executor: write this artifact to outbox
- Route to PM (dungeoncrawler) for acceptance criteria sign-off (especially Q2 on incomplete session recovery and Q4 on spellcaster spell selection scope)
- Route Level-Up Wizard dev task to Dev (dungeoncrawler) after PM sign-off
- Remaining unspecced items: Automated Application Submission (Flow 17, complex), User Support Contact Form (Flow 5, simple 1-2 day task)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 40
- Rationale: The level-up wizard is a core character progression feature for dungeoncrawler — without it, characters are stuck at their creation stats regardless of XP earned. The design is fully documented but has no acceptance criteria, meaning Dev has no pass/fail targets; this spec provides that contract and prevents unbounded scope creep on a multi-step wizard.

---

## ROI-Ranked Requirements Improvements (3 candidates)

| Rank | Item | Module | ROI | Rationale |
|------|------|--------|-----|-----------|
| 1 ✅ | Level-Up Wizard Acceptance Criteria (dungeoncrawler) | dungeoncrawler | 40 | Design complete, implementation partial/planned; no AC exists; blocks character progression |
| 2 | Automated Application Submission Spec (Flow 17) | job_hunter | 35 | Most complex remaining flow (8-10 days, browser automation); CAPTCHA/MFA handling strategy needs decision before Dev can scope |
| 3 | User Support Contact Form (Flow 5) | job_hunter | 20 | Simple 1-2 day Drupal contact form; lowest complexity, lowest risk; good quick win |

---

## Requirements Artifact: dungeoncrawler — Level-Up Wizard Acceptance Criteria

### Problem Statement

The dungeoncrawler has a comprehensive target-state design for a multi-step level-up wizard (PR-06) and a `level_up_sessions` DB schema, but:

1. PR-06 verification notes explicitly state: "dedicated route/controller surface described below may be partial or planned."
2. Issue #1 verification notes confirm: "`SchemaLoader::getClassData()` is still a TODO/throws" — the schema-driven class data lookup used by HP calculation is not working.
3. No acceptance criteria exist for any of the 8+ wizard steps.
4. There is no definition of "done" for the wizard as a whole — Dev has no pass/fail targets.

**Current behavior:** Characters earn XP. When XP ≥ 1000, no automatic notification triggers. There is no confirmed `/character/leveling/start` route. If `LevelingController` exists, it may silently fail on class data lookups via `SchemaLoader::getClassData()`.  
**Expected behavior:** Character with XP ≥ 1000 sees a "Level Up Available" notification → clicks to start wizard → completes 6 steps (HP, class features, ability boosts if milestone, feat selection, skill increases, spell selection if spellcaster) → confirms → character data updated atomically → `character_level_history` row inserted → `level_up_sessions` marked complete.

### Scope

**In scope:**
- XP threshold detection and level-up notification (badge/banner on character sheet)
- Wizard start: `POST /leveling/start` creates `level_up_sessions` row
- Step 1 — HP Increase: compute `class_hp_per_level + con_modifier`; handle retroactive HP if CON increased this level
- Step 2 — Class Features Display: read `game_classes.class_features` JSON, filter by new level; display new features (read-only, no choices)
- Step 3 — Ability Boosts (milestone levels 5, 10, 15, 20 only): present 4 boost slots; enforce "cannot boost same ability twice this milestone"; update `level_up_sessions.ability_boosts`
- Step 4 — Feat Selection: determine feat types granted at the new level per PF2e class table; for each: filter `dungeoncrawler_content_registry` feats by type, level requirement, and `feat_prerequisites`; user selects; save to `level_up_sessions.selected_feats`
- Step 5 — Skill Increases (class-dependent): if class grants proficiency increase at this level, present eligible skills and rank upgrade options; save to `level_up_sessions.proficiency_increases`
- Step 6 — Spell Selection (spellcaster classes only): if class gains new spell slots or spells in repertoire at this level, present eligible spells; save to `level_up_sessions.new_spells`
- Confirmation step: preview summary of all changes; "Confirm Level Up" button
- Atomic commit: `processLevelUp()` applies all `level_up_sessions` choices to `characters` table in a DB transaction; inserts `character_level_history` row; marks session `is_complete = TRUE`
- Post-commit: redirect to character sheet; display "Leveled up to Level X!" success banner

**Non-goals:**
- Multi-class leveling (Phase 2)
- Undoing a completed level-up (not supported by PF2e rules; not in scope)
- Bulk XP award / GM XP interface (separate admin feature)
- Feat search/browse UI beyond the filtered list (Phase 2)
- Mobile-optimized wizard layout (Phase 2 — basic responsive acceptable)

### Definitions

| Term | Definition |
|------|------------|
| Level-up session | A `level_up_sessions` row tracking one character's in-progress level-up wizard; one active session per character at a time |
| Milestone level | Levels 5, 10, 15, 20 — the only levels granting 4 ability boosts |
| Feat type | One of: ancestry, class, general, skill — determines which feat pool is shown and at which levels |
| Retroactive HP | When CON modifier increases during a level-up, HP is recalculated for all previous levels: `(new_con_mod - old_con_mod) × new_level` added to max HP |
| Atomic commit | All level-up changes applied in a single DB transaction; if any step fails, all changes roll back and the session remains incomplete |
| `is_complete` | Boolean on `level_up_sessions`; FALSE = in-progress (resumable); TRUE = committed to character |

### Key User Flows

**Flow A: Standard level-up (non-milestone, non-spellcaster)**
1. Fighter character reaches 1000 XP → "Level Up Available" banner appears on character sheet
2. User clicks "Level Up" → `POST /leveling/start` creates session; wizard opens at Step 1
3. Step 1 (HP): shows "+12 HP (10 class + 2 CON)"; user clicks Next
4. Step 2 (Class Features): shows "Weapon Specialization unlocked at level 7"; user clicks Next
5. Step 3 (Ability Boosts): not shown (level 7 is not a milestone)
6. Step 4 (Feats): shows ancestry feat list (level 7 is an ancestry feat level) and general feat list; user selects one of each; clicks Next
7. Step 5 (Skill Increases): Fighter gains 1 skill increase at level 7; user selects Athletics → Expert; clicks Next
8. Step 6 (Spells): skipped (non-spellcaster)
9. Confirmation: summary shown; user clicks "Confirm Level Up"
10. `processLevelUp()` commits changes; character sheet refreshes at Level 7

**Flow B: Milestone level-up (level 5, with spellcaster)**
1. Wizard character reaches level 5 → wizard opened
2. Steps 1–2 as above
3. Step 3 (Ability Boosts): 4 boost slots presented; user boosts INT, DEX, CON, WIS; system enforces no double-boost
4. Step 4 (Feats): wizard gains class feat; ancestry feat; user selects
5. Step 5 (Skill Increases): if applicable
6. Step 6 (Spells): wizard gains new 3rd-level spell slots automatically; user selects 2 new spells for repertoire (if spontaneous caster); prepared caster shown info-only
7. Confirmation and commit

**Flow C: Resuming an incomplete session**
1. User starts wizard, completes Steps 1–3, closes browser
2. User returns to character sheet → "Level Up in Progress" banner
3. Clicks "Resume" → wizard reopens at Step 4 (current_step from `level_up_sessions`)
4. Previously saved choices (HP, ability boosts) are pre-populated
5. User completes remaining steps and confirms

### Acceptance Criteria (Draft — PM to finalize)

**Happy path:**
- AC1: A character with XP ≥ 1000 displays a "Level Up Available" notification on their character sheet; a character with XP < 1000 does not.
- AC2: `POST /leveling/start` creates a `level_up_sessions` row with `from_level = current_level`, `to_level = current_level + 1`, `is_complete = FALSE`; calling it when a session already exists for the character returns the existing session (not a duplicate).
- AC3: Step 1 (HP Increase) correctly computes `class_hp_per_level + con_modifier` using the character's class from `game_classes`; the computed value is stored in `level_up_sessions.hp_increase`.
- AC4: For a Fighter leveling from 3 to 4 (class HP = 10, CON modifier = +2), Step 1 shows "+12 HP" and the character's `max_hp` increases by 12 after commit.
- AC5: Step 3 (Ability Boosts) only appears at levels 5, 10, 15, and 20; it does not appear at other levels.
- AC6: Ability Boost step enforces that the same ability cannot be boosted twice in the same level-up; selecting the same ability for two slots shows a validation error.
- AC7: Step 4 (Feat Selection) presents only feats where level requirement ≤ new level AND all `feat_prerequisites` are met by the character's current feats/proficiencies.
- AC8: `processLevelUp()` applies all choices atomically: if any DB write fails, the transaction rolls back and `level_up_sessions.is_complete` remains FALSE; the character's stats are unchanged.
- AC9: After a successful commit, `character_level_history` contains a new row for the new level with a snapshot of feats gained, proficiency increases, and final max HP.
- AC10: The character sheet displays the new level immediately after commit; XP is reduced by 1000 (remainder kept).

**Failure modes:**
- AC11: If a character has an existing incomplete `level_up_sessions` row, the character sheet shows "Level Up in Progress — Resume" rather than "Level Up Available"; `POST /leveling/start` returns the existing session.
- AC12: Attempting to start a level-up for a character at level 20 (max) returns HTTP 400 with message "Character is already at maximum level."
- AC13: If `game_classes` does not contain a `hp_per_level` value for the character's class, the HP step shows an error: "Class HP data unavailable — contact support" and does not allow proceeding; an error is logged to Drupal watchdog.
- AC14: A user attempting to access another player's level-up wizard at `/leveling/{session_id}` receives HTTP 403.
- AC15: Navigating away mid-wizard and returning within the same session shows the wizard at the last completed step with prior choices intact.

**Verification method:**
- PHPUnit: `LevelingControllerTest` — assert session creation, HP calculation (Fighter +12, Wizard +8), ability boost validation, feat prerequisite filtering, atomic commit, level history row.
- PHPUnit: `SchemaLoaderClassDataTest` — assert `getClassData('fighter')` returns `{hp_per_level: 10, ...}` without throwing; assert fallback behavior when class not found.
- Manual: Level a Fighter from 3→4 on staging; confirm max HP increased by correct amount; confirm `character_level_history` row exists; confirm XP decremented.
- Manual: Level a Wizard at level 5→6 (milestone); confirm ability boost step appears with 4 slots; confirm double-boost validation fires.

### Known Implementation Gap: `SchemaLoader::getClassData()`

Issue #1 verification notes (2026-02-18) confirm: "`SchemaLoader::getClassData()` is still a TODO/throws." This is a **hard blocker** for AC3, AC4, and AC13 — the HP calculation at Step 1 depends on it. Dev must resolve this before the level-up wizard can function correctly. Current workaround (`CharacterManager::getClassHP()` fallback) exists but is not schema-driven.

Dev task for this issue:
- Implement `SchemaLoader::getClassData($class_id)` to read from `character_options_step2.json` and return class data including `hit_points`
- Remove the TODO/throw; replace with proper JSON parse + cache
- AC: `SchemaLoader::getClassData('fighter')` returns `{id: 'fighter', hit_points: 10, key_ability: ['STR', 'DEX']}` in PHPUnit test

### Assumptions

1. `game_classes` DB table or equivalent JSON source contains `hp_per_level` per class; if this data is only in `character_options_step2.json`, `SchemaLoader` is the bridge.
2. `feat_prerequisites` table is created as designed in PR-06; if not, feat prerequisite filtering must fall back to level-only filtering at MVP.
3. The wizard is a server-rendered multi-page form (not a single-page AJAX wizard) for MVP; each "Next" button is a form POST updating `level_up_sessions.current_step`.
4. Spell selection for prepared casters (Cleric, Wizard) is informational only at level-up (they choose prepared spells at rest, not at level-up); only spontaneous casters (Bard, Sorcerer) make an active repertoire choice during level-up.
5. The character sheet route already exists and is the redirect target post-commit.

### 3–5 Clarifying Questions for Stakeholders

1. **`SchemaLoader::getClassData()` priority:** This is a confirmed TODO that blocks the HP calculation step. Should fixing it be scoped as part of this level-up wizard task, or is it a prerequisite that must ship first as a separate ticket? Recommendation: bundle it into this task since it is short (1 day) and the wizard is unshippable without it.
2. **Incomplete session recovery timeout:** Should incomplete `level_up_sessions` rows expire after N days (e.g., 7 days) and be auto-abandoned, or do they persist indefinitely? An abandoned session blocks the character from starting a new level-up if "one session at a time" is enforced. Recommendation: 7-day expiry with a cleanup cron job.
3. **Feat pool source:** Feats are currently in `dungeoncrawler_content_registry`. Does the registry have all PF2e feats with `type` (ancestry/class/general/skill), `level_requirement`, and `class_id` (for class feats)? Or does feat filtering need to be implemented first before the wizard can present valid feat choices?
4. **Spellcaster spell selection scope:** For spontaneous casters at level-up, they gain new spells in their repertoire AND can swap one existing spell. Is the swap mechanic in scope for this wizard pass, or is it deferred? Recommendation: defer to Phase 2; just handle new spell additions at MVP.
5. **Wizard UI approach:** Should the wizard be a traditional multi-page form (each step is a page load) or an AJAX single-page wizard? Multi-page is simpler but clunkier UX; AJAX requires more frontend work. What is the target experience for MVP?

### Delegation Table

| Role | Action |
|------|--------|
| **PM** | Finalize AC1–AC15; answer clarifying questions 1–5; confirm feat pool completeness in content registry; confirm spell swap scope |
| **Dev (dungeoncrawler)** | Fix `SchemaLoader::getClassData()`; create `level_up_sessions` and `character_level_history` tables if not present; implement wizard steps 1–6 + commit; implement XP notification; enforce AC8 atomic transaction |
| **QA** | Level Fighter 3→4 (verify HP); level Wizard 5→6 (verify boosts + spells); test feat prerequisite filtering; test incomplete session resume (AC11/AC15); test cross-user 403 (AC14) |
