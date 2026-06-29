# Test Plan: dc-gmg-subsystems

## Coverage summary
- AC items: ~45 (subsystem architecture, Influence, Research, Infiltration, Chases, Vehicles, Hexploration, Duels, variant rules: Free Archetype, Ancestry Paragon, ABP, Proficiency Without Level)
- Test cases: 18 (TC-SUB-01–18)
- Suites: playwright (encounter, downtime, character creation)
- Security: AC exemption granted (no new routes)

---

## TC-SUB-01 — Subsystem architecture: setup/round/resolution states tracked independently
- Description: Active subsystems tracked independently from main encounter; can be triggered during exploration/social/encounter mode; results feed back to narrative state (XP, items, story beats)
- Suite: playwright/encounter
- Expected: subsystem panel distinct from encounter tracker; multiple subsystems simultaneously tracked; subsystem results update session state
- AC: Architecture-1–4

## TC-SUB-02 — Influence Subsystem: NPC disposition, influence points, thresholds
- Description: Each NPC has preferred/opposed skills; success +influence; crit success +double; failure +none; crit failure –influence; thresholds unlock outcomes; exceeding opposition triggers hostility
- Suite: playwright/encounter
- Expected: influence point changes per outcome tier; threshold detection fires outcomes; hostility triggered at opposition limit
- AC: Influence-1–5

## TC-SUB-03 — Research Subsystem: library entries, Research Points, tiers, time limit
- Description: Research checks vs. library DC generate Research Points; cumulative points unlock information tiers; time limit in rounds/blocks; per-entry cap prevents grinding
- Suite: playwright/downtime
- Expected: each check generates RP; tier unlock at threshold; time-based termination; cap prevents overflow RP
- AC: Research-1–5

## TC-SUB-04 — Infiltration Subsystem: Awareness score, complications, preparation points
- Description: Failed checks raise Awareness; thresholds trigger complications; critical failures trigger immediate complications; preparation points reduce initial Awareness
- Suite: playwright/encounter
- Expected: failed check → awareness += defined amount; threshold crossing triggers complication; crit fail bypasses threshold; prep points deducted from starting awareness
- AC: Infiltration-1–5

## TC-SUB-05 — Chases Subsystem: stages, skill option pools, simultaneous advancement
- Description: Chase = series of stages; 2–3 skill options per stage; each side chooses and attempts independently; obstacle distance tracked; chase ends when one side wins/escapes
- Suite: playwright/encounter
- Expected: stage skill pools selectable; both sides resolve independently (not alternating); distance tracked numerically; end condition fires correctly
- AC: Chases-1–5, Integration-4

## TC-SUB-06 — Vehicles Subsystem: stat blocks, piloting, passengers, collision
- Description: Vehicles have stat blocks (Piloting DC, Speed, Maneuverability, HP, Hardness, AC, Saves); pilot maneuvers via Piloting check; passengers act independently; collision deals damage by size/speed; HP loss degrades Speed/Maneuverability at thresholds
- Suite: playwright/encounter
- Expected: vehicle stat block fields all present; failed piloting → damage/control loss; passenger actions work; collision damage formula applied; threshold degradation
- AC: Vehicles-1–5

## TC-SUB-07 — Hexploration Subsystem: hex discovery status, terrain effects, Reconnoiter
- Description: Hexes = unknown/revealed/explored; enter hex → revealed; full exploration requires Reconnoiter; terrain types affect travel time and actions; party exploration actions per day determined by terrain + speed
- Suite: playwright/downtime
- Expected: hex status transitions correctly; Reconnoiter changes status to explored; terrain modifier on daily exploration actions
- AC: Hexploration-1–5

## TC-SUB-08 — Duels Subsystem: formal/informal, win conditions, honor tracking
- Description: Duel structured as formal/informal; win conditions per type; may use combat or opposed skill checks; deviating from terms applies reputation/honor penalty
- Suite: playwright/encounter
- Expected: duel type selection; win condition fires on correct trigger; honor penalty applied on rule deviation
- AC: Duels-1–3

## TC-SUB-09 — Variant rules: feature-flagged per campaign, compatibility check
- Description: Variant rules enabled/disabled at campaign setup; compatibility check alerts on conflict with active assumptions; each rule has documented precedence level
- Suite: playwright/character-creation
- Expected: variant toggles in campaign settings; conflict alert shows when incompatible rules combined; precedence level displayed
- AC: VariantRules-1–3

## TC-SUB-10 — Free Archetype Variant: bonus class feat at every even level for archetypes only
- Description: Extra class feat at each even level exclusively for archetype feats; does not stack with normal class feat; dedicated/regular feats remain separate
- Suite: playwright/character-creation
- Expected: even levels show archetype-only bonus slot; normal class feat unchanged; archetype slot cannot accept non-archetype feats
- AC: FreeArchetype-1–3

## TC-SUB-11 — Ancestry Paragon Variant: ancestry feats at every even level
- Description: Ancestry feats granted at every even level (double normal rate); ancestry-feats only (not general or class feats)
- Suite: playwright/character-creation
- Expected: even levels show extra ancestry feat slot; slot filters ancestry feats only; total ancestry feat count doubles
- AC: AncestryParagon-1–2

## TC-SUB-12 — Automatic Bonus Progression (ABP): removes rune item bonus dependency
- Description: ABP removes item bonus from fundamental runes; grants attack/damage/AC/saves/perception bonuses by level via ABP table; magic items still exist but don't stack with ABP values
- Suite: playwright/character-creation
- Expected: when ABP active, rune item bonuses suppressed; ABP bonuses applied from table; no stacking
- AC: ABP-1–3

## TC-SUB-13 — ABP migration: prior rune bonuses replaced not stacked
- Description: Characters migrating to ABP get migration warning; prior rune effects replaced (not additive)
- Suite: playwright/character-creation
- Expected: ABP enable on existing character → migration warning shown; rune bonuses removed; ABP table bonuses applied
- AC: ABP-4, Edge-3

## TC-SUB-14 — ABP table is lookup-based and configurable per campaign
- Description: ABP bonus values loaded from table (not hardcoded); configurable per campaign
- Suite: playwright/character-creation
- Expected: ABP values match published table; GM can modify per campaign without code change
- AC: Integration-3

## TC-SUB-15 — Proficiency Without Level: fixed rank-based proficiency bonus
- Description: Proficiency bonus = fixed (Untrained 0, Trained 2, Expert 4, Master 6, Legendary 8); character level not added to checks/DCs/saves
- Suite: playwright/character-creation
- Expected: check modifiers recalculated without level component; each rank has correct fixed bonus
- AC: ProfWithoutLevel-1–2

## TC-SUB-16 — Proficiency Without Level: NPC DCs also updated
- Description: Creature DCs and ACs also strip level component (critical for challenge parity)
- Suite: playwright/encounter
- Expected: when ProfWithoutLevel active, NPC DCs recalculated without level; GM warned if NPC data not migrated
- AC: Edge-4

## TC-SUB-17 — Two simultaneous subsystems tracked independently
- Description: Two active subsystems (e.g., Influence + Research) tracked without interaction unless explicitly defined
- Suite: playwright/encounter
- Expected: both subsystem panels visible; each tracks state independently; no bleed-over between subsystem states
- AC: Edge-1

## TC-SUB-18 — Free Archetype: pre-existing feat in slot unaffected by variant activation
- Description: If a character already has an archetype feat from a class feature, the free archetype slot is not removed or overridden
- Suite: playwright/character-creation
- Expected: activating Free Archetype variant on existing character preserves existing feat; free slot appears for future even levels
- AC: Edge-2
