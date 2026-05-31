# Test Plan: dc-cr-exploration-mode

## Coverage summary
- AC items: ~14 (time scale, activities, initiative from activity, light/darkness, encounter transition, stealth surprise)
- Test cases: 10 (TC-EXP-01–10)
- Suites: playwright (encounter, character creation)
- Security: AC exemption granted

---

## TC-EXP-01 — Exploration time scale: minutes and hours
- Description: Exploration mode tracks time in minutes/hours (not rounds)
- Suite: playwright/encounter
- Expected: time_unit = minutes when exploration_mode = active; round tracker absent
- AC: AC-001

## TC-EXP-02 — Exploration activities: correct set available
- Description: Available activities: Avoid Notice, Detect Magic, Hustle, Investigate, Repeat a Spell, Scout, Search, Sense Direction
- Suite: playwright/encounter
- Expected: all 8 activities in exploration activity picker; no encounter-only actions listed
- AC: AC-002

## TC-EXP-03 — Search: detects secrets/hazards/items per 10-ft square moved
- Description: While Searching, each 10-ft square moved checks for secret doors, hazards, hidden items
- Suite: playwright/encounter
- Expected: movement while Searching → Perception check per 10-ft segment; detection fires on success
- AC: AC-002

## TC-EXP-04 — Hustle: double speed, fatigue after 10 minutes
- Description: Hustle doubles movement speed; accrues fatigue after 10 minutes of Hustling
- Suite: playwright/encounter
- Expected: speed_bonus = ×2; after 10 min → fatigue condition applied
- AC: AC-002

## TC-EXP-05 — Initiative from exploration activity: skill per current activity
- Description: When encounter begins, initiative skill = associated with current exploration activity (e.g., Stealth for Avoid Notice, Perception for Scout)
- Suite: playwright/encounter
- Expected: initiative_skill determined by active exploration activity; correct skill mapping per activity
- AC: AC-003

## TC-EXP-06 — Light/darkness: vision type determines sight
- Description: Normal/low-light/darkvision determines what character sees per area lighting; bright radius and dim radius tracked for carried light sources
- Suite: playwright/encounter
- Expected: light_source has bright_radius and dim_radius; area beyond bright = dim; beyond dim = dark; vision type applied
- AC: AC-004

## TC-EXP-07 — Encounter transition: exploration → combat round scale
- Description: Encounter trigger automatically transitions from exploration time scale to combat rounds
- Suite: playwright/encounter
- Expected: encounter_trigger event → time_unit changes to rounds; initiative rolls fire; exploration activities deactivated
- AC: AC-005

## TC-EXP-08 — Stealth approach: enemies that failed Perception are surprised
- Description: Characters using Avoid Notice: when encounter begins, enemies failing Perception vs. Stealth are surprised (cannot act first round)
- Suite: playwright/encounter
- Expected: each enemy makes Perception vs. stealth_roll; failure → enemy.state = surprised; surprised enemies skip first turn
- AC: AC-005

## TC-EXP-09 — Exploration-to-encounter bonus: accumulated activity affects first round
- Description: Prior exploration activity affects first encounter round (initiative bonuses, detection state)
- Suite: playwright/encounter
- Expected: initiative roll uses exploration activity skill; detection states from pre-combat carry into first round
- AC: AC-001

## TC-EXP-10 — Grid position server-validated: no client-side spoofing
- Description: Grid/map positions in exploration mode are server-validated
- Suite: playwright/encounter
- Expected: position updates rejected if not consistent with server state; client cannot directly set coordinates
- AC: Security AC
