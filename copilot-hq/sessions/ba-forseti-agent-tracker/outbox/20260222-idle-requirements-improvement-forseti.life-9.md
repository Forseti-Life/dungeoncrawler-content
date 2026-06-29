# Outbox update: 20260222-idle-requirements-improvement-forseti.life-9

- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti-agent-tracker/inbox/20260222-idle-requirements-improvement-forseti.life-9
- Generated: 2026-02-22T15:10:03-05:00

---

I have enough context. PR-06 describes the ExperienceController target design but marks it "partial or planned" and has zero acceptance criteria for the GM XP award workflow — highest-ROI unspecced gap remaining in dungeoncrawler. Here is the outbox update:

---

**OUTBOX FILE:** `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-requirements-improvement-forseti.life-9.md`

```markdown
- Status: done
- Summary: Idle requirements cycle 9 (forseti.life, dungeoncrawler). Ranked 3 remaining unspecced gaps by ROI: (1) XP Award GM Workflow — ROI 16, (2) Focus Spell System — ROI 12, (3) Character Creation UX — ROI 8. Completed the top-ROI item: a full structured requirements artifact for the dungeoncrawler XP Award GM Workflow. PR-06 describes the ExperienceController target-state design and DB schema but carries zero acceptance criteria for how a GM actually awards XP during or after play; the controller surface is explicitly marked "partial or planned." This artifact closes that gap with a problem statement, scope, user flows, 14 acceptance criteria candidates, 5 clarifying questions, and a delegation table. One follow-up inbox item queued for pm-forseti-agent-tracker to finalize ACs and greenlight Dev implementation.

## Next actions
- Executor: write this outbox file to `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-requirements-improvement-forseti.life-9.md`
- Executor: create follow-up inbox item in `sessions/pm-forseti-agent-tracker/inbox/` (content below)
- BA: process next inbox item; remaining unspecced gaps: Focus Spell System (ROI 12) and Character Creation UX (ROI 8)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 16
- Rationale: XP award is the only mechanism that enables character level-up; without a verified GM workflow, players are permanently stuck at their starting level during real sessions. PR-06 marks the ExperienceController as "partial or planned" — speccing ACs now prevents rework when Dev implements it.

---

# Requirements Artifact: Dungeoncrawler XP Award GM Workflow

**Artifact type:** Requirements summary + draft acceptance criteria
**Website:** forseti.life
**Module:** dungeoncrawler
**Source PR:** PR-06 (leveling-up-implementation.md)
**Date:** 2026-02-22

---

## Problem statement

Dungeoncrawler supports character level-up (the 8-step wizard), but there is no verified GM-facing workflow for *awarding XP* that triggers the level-up path. PR-06 describes `ExperienceController` with three award endpoints (`awardXP`, `awardEncounterXP`, `awardAccomplishmentXP`) and an `experience_log` table, but the controller surface is explicitly marked "partial or planned." There are no acceptance criteria for the GM workflow, no mapping of Pathfinder 2E encounter difficulty tiers to XP values, and no spec for party-wide vs. individual award behavior. Without this, GMs cannot award XP during sessions and the level-up system is unreachable in practice.

---

## Scope

**In scope:**
- GM awards encounter XP to one or more characters after combat (difficulty-based formula)
- GM awards accomplishment XP (minor / moderate / major) for story milestones
- GM awards arbitrary XP with a freeform reason (direct award)
- XP total is persisted; when a character reaches ≥1000 XP, level-up is triggered
- XP history log viewable per character
- Level-up notification displayed to GM after award crosses threshold

**Non-goals (deferred to Future Enhancements in PR-06):**
- Milestone leveling mode (no XP tracking)
- Party XP sync / automatic party-wide award from a single action
- XP decay/death penalties
- AI build optimizer
- Custom XP tables

---

## Definitions / terminology

| Term | Definition |
|---|---|
| **XP** | Experience Points; characters advance when they accumulate ≥1000 XP (PF2e standard) |
| **Encounter difficulty** | PF2e 5-tier scale: Trivial / Low / Moderate / Severe / Extreme |
| **Accomplishment type** | Minor (10 XP) / Moderate (30 XP) / Major (80 XP) — from `xp_tables` seed data in PR-06 |
| **Direct award** | Arbitrary XP amount with freeform `reason` string |
| **XP threshold** | 1000 XP triggers level-up; character keeps remainder (e.g., 1050 XP → level up + 50 XP remaining) |
| **Party** | All characters in a single active session; may share an award or receive individually |
| **GM** | Game Master; the admin-authenticated Drupal user running the session |
| **Level-up trigger** | System state when `experience_points >= 1000`; prompts wizard entry |

---

## PF2e XP award reference (from source rulebooks + PR-06 seed data)

### Encounter XP by party level (4-player baseline, per character)

| Difficulty | XP award |
|---|---|
| Trivial | 10 |
| Low | 15 |
| Moderate | 20 |
| Severe | 30 |
| Extreme | 40 |

*Adjustment for party size < or > 4: ±2 XP per missing/extra character (open question — see Q2).*

### Accomplishment XP

| Type | XP |
|---|---|
| Minor | 10 |
| Moderate | 30 |
| Major | 80 |

---

## User flows

### Flow A: GM awards encounter XP post-combat

1. Combat ends; GM navigates to the XP Award UI (exact route TBD — see Q1).
2. GM selects award type: "Encounter."
3. GM selects encounter difficulty (Trivial / Low / Moderate / Severe / Extreme).
4. System displays calculated XP per character based on difficulty tier.
5. GM selects which characters receive the award (default: all active session characters).
6. GM confirms. System calls `ExperienceController::awardEncounterXP()`.
7. System persists XP to each selected character (`UPDATE characters SET experience_points += xp_amount`).
8. System inserts a row in `experience_log` for each character with `source_type = 'encounter'`.
9. For each character now at ≥1000 XP: system shows level-up notification.
10. GM is returned to session/character view.

### Flow B: GM awards accomplishment XP

1. Story milestone occurs; GM navigates to XP Award UI.
2. GM selects award type: "Accomplishment."
3. GM selects accomplishment type (Minor / Moderate / Major).
4. GM optionally adds a freeform `reason` description.
5. GM selects characters (default: all session characters).
6. GM confirms. System calls `ExperienceController::awardAccomplishmentXP()`.
7. XP persisted; `experience_log` row inserted (`source_type = 'accomplishment'`).
8. Level-up notification shown if threshold crossed.

### Flow C: GM awards arbitrary XP (direct award)

1. GM selects award type: "Direct."
2. GM enters a numeric XP value and a freeform `reason`.
3. GM selects characters.
4. GM confirms. System calls `ExperienceController::awardXP()`.
5. XP persisted; `experience_log` row inserted (`source_type = 'gm_award'`).

### Flow D: Character views XP history

1. Character detail page shows current XP / 1000 progress bar.
2. "XP History" section lists `experience_log` rows for that character: date, amount, source type, reason.
3. Read-only; no edit.

---

## Draft acceptance criteria (candidates for PM to finalize)

**Encounter XP award:**

1. Given GM selects "Moderate" encounter difficulty and 3 active characters, when GM confirms award, then each character's `experience_points` increases by exactly 20 and an `experience_log` row with `source_type = 'encounter'` and `xp_amount = 20` is inserted for each.
2. Given GM selects "Extreme" difficulty, when confirmed, then each character receives exactly 40 XP.
3. Given a character has 990 XP and receives a 20 XP encounter award, when confirmed, then the character's `experience_points` becomes 1010, a level-up notification is displayed, and the character is presented the level-up wizard entry point.
4. Given a character at level 20 (max level) receives an XP award, when confirmed, then XP is recorded in `experience_log` but no level-up notification is shown and `experience_points` does not wrap.

**Accomplishment XP award:**

5. Given GM selects "Major" accomplishment, when confirmed with 2 characters selected, then each receives exactly 80 XP and `experience_log` rows have `source_type = 'accomplishment'` and `accomplishment_type = 'major'`.
6. Given GM enters a freeform `reason` with the award, when confirmed, then `experience_log.reason` stores the exact entered text.
7. Given GM submits a "Minor" accomplishment with no reason text, when confirmed, then system accepts the award (reason is optional).

**Direct XP award:**

8. Given GM enters `xp_amount = -10` (penalty) and confirms, then the target character's `experience_points` decreases by 10 and `experience_log` records `xp_amount = -10` with `source_type = 'gm_award'`.
9. Given GM enters `xp_amount = 0`, when submitted, then system rejects the award with a validation error ("XP amount must be non-zero").

**XP history:**

10. Given a character has 3 `experience_log` entries, when GM views the character's XP history, then all 3 entries are displayed with date, amount, source type, and reason (if present), sorted by `created_at` descending.

**Level-up trigger:**

11. Given any XP award causes `experience_points >= 1000`, then a level-up notification is immediately visible (inline on award confirmation screen and/or badge on character list).
12. Given XP threshold is crossed, the level-up wizard is accessible from the notification; the wizard entry pre-populates with the character's current state.
13. Given level-up wizard is completed and level is incremented, then `experience_points` is reduced by 1000 (remainder preserved) and `level_history` row is inserted.

**Input validation:**

14. Given GM submits an encounter award with no characters selected, then system returns a validation error and no XP is awarded.

---

## Open questions (clarifying questions for PM/CEO)

**Q1 — Route/UI entry point for XP award:** Where does the GM access the XP award form? Options:
- A. Inline on the session/encounter view (preferred — lowest friction during play)
- B. Separate `/admin/dungeoncrawler/award-xp` route
- C. Modal triggered from the character list
Recommendation: Option A — GM should award XP without navigating away from the active encounter context.

**Q2 — Party size XP adjustment:** PF2e rules adjust encounter XP for parties smaller or larger than 4 characters. Should the system apply the ±2 XP per character adjustment automatically? Options:
- A. Yes — calculate dynamically based on selected character count (fully rules-compliant)
- B. No — always use flat per-difficulty values; GM adjusts manually
Recommendation: Option A for authenticity; adds 2 lines of logic. Requires "party size" to be derivable from selected characters at award time.

**Q3 — Multi-character selection UX:** When awarding to a session party, should all session characters be selected by default (opt-out model) or none selected (opt-in)? Recommendation: opt-out (all selected) — reduces friction for the most common case (full-party award).

**Q4 — Negative XP floor:** Can a penalty award reduce a character below 0 XP? Should the system clamp at 0? Recommendation: clamp at 0 — going negative has no game-mechanical meaning in PF2e and could cause display/logic issues.

**Q5 — ExperienceController implementation status:** PR-06 marks routes as "partial or planned." Before Dev implements ACs, does the `experience_log` table exist in the current DB (via a migration/install hook)? Verification: `drush sql:query "DESCRIBE dungeoncrawler_experience_log;"` — if table is absent, a schema migration must be added first.

---

## Delegation table

| Task | Owner | Priority | Notes |
|---|---|---|---|
| Finalize ACs (Q1–Q5 decisions) | PM (pm-forseti-agent-tracker) | High | Needed before Dev starts |
| Implement `ExperienceController` endpoints + schema migration | Dev (dev-forseti-dungeoncrawler) | High | After PM AC sign-off |
| Implement XP Award UI form (Flow A/B/C) | Dev | High | Depends on Q1 routing decision |
| Implement level-up notification trigger | Dev | High | AC 11–12 |
| QA: full flow regression (award → notify → wizard → log) | QA | High | After Dev ships |

---

## Follow-up inbox item (for executor to create)

**Destination:** `sessions/pm-forseti-agent-tracker/inbox/20260222-dungeoncrawler-xp-award-ac-finalize/`

**`command.md`:**
```markdown
- command: |
    PM finalization: dungeoncrawler XP Award GM Workflow ACs
    
    BA has produced a draft requirements artifact for the XP Award GM Workflow
    (dungeoncrawler, forseti.life). Source: PR-06-leveling-up-implementation.md.
    
    Your tasks:
    1. Review the 14 draft ACs and 5 clarifying questions in:
       sessions/ba-forseti-agent-tracker/outbox/20260222-idle-requirements-improvement-forseti.life-9.md
    2. Decide:
       - Q1: XP award UI entry point (inline session view recommended)
       - Q2: Party size XP adjustment (auto-calculate recommended)
       - Q3: Multi-character selection default (opt-out recommended)
       - Q4: Negative XP floor (clamp at 0 recommended)
       - Q5: Verify experience_log table exists: `drush sql:query "DESCRIBE dungeoncrawler_experience_log;"`
    3. Publish finalized ACs as a work item to dev-forseti-dungeoncrawler.
    
    Files:
    - Source design: ~/forseti.life/docs/dungeoncrawler/PR-06-leveling-up-implementation.md
    - BA artifact: sessions/ba-forseti-agent-tracker/outbox/20260222-idle-requirements-improvement-forseti.life-9.md
    
    Acceptance criteria for this PM task:
    - All 5 open questions answered (or escalated with rationale)
    - ACs 1–14 marked approved / modified / rejected with notes
    - Dev work item created if ACs approved
    
    Verification: Dev inbox contains a work item referencing ExperienceController implementation.
```

**`roi.txt`:** `16`
```
