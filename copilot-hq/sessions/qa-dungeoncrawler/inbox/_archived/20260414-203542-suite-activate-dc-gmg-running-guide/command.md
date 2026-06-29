# Suite Activation: dc-gmg-running-guide

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-14T20:35:42+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-gmg-running-guide"`**  
   This links the test to the living requirements doc at `features/dc-gmg-running-guide/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-gmg-running-guide-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-gmg-running-guide",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-gmg-running-guide"`**  
   Example:
   ```json
   {
     "id": "dc-gmg-running-guide-<route-slug>",
     "feature_id": "dc-gmg-running-guide",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-gmg-running-guide",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-gmg-running-guide

## Coverage summary
- AC items: ~35 (session zero, GM dashboard, secret checks, ruling records, adventure/campaign design, encounter design tools, running encounter UI)
- Test cases: 12 (TC-RUN-01–12)
- Suites: playwright (downtime, encounter, character creation)
- Security: AC exemption granted (no new routes)

---

## TC-RUN-01 — Session zero workflow: party links, character integration notes
- Description: Pre-play phase supports records for party relationships and character integration notes for adventure hooks
- Suite: playwright/character-creation
- Expected: session-zero phase accessible before first encounter; party_links and adventure_hook fields saved per character
- AC: SessionZero-1

## TC-RUN-02 — GM dashboard: key PC modifier cache (Perception, Will, Recall Knowledge)
- Description: GM sees quick-reference cache of PC modifiers (Perception, Will, common RK skills); refreshes on level-up or stat change
- Suite: playwright/encounter
- Expected: GM dashboard panel shows PC modifier list; values update automatically after level-up or stat edit
- AC: GMDashboard-1

## TC-RUN-03 — Secret check mode: GM-only roll outcome view
- Description: Secret-check mode hides roll outcomes from players; GM-only view
- Suite: playwright/encounter
- Expected: secret mode toggle hides roll results in player view; GM sees all results; player sees only "roll was made"
- AC: GMDashboard-2

## TC-RUN-04 — Ruling records: precedent linkage and provisional flags
- Description: Rulings can reference prior analogous decisions; accumulated precedents tracked; GM can mark ruling provisional with deferred review flag
- Suite: playwright/downtime
- Expected: ruling record UI allows linking to prior ruling; provisional flag + deferred-review flag available; review workflow before next session
- AC: Rulings-1–3

## TC-RUN-05 — Creative-action resolution templates
- Description: GM has templates for: minor bonus, minor penalty, minor damage-plus-rider, object-triggered save; one-time exception flag prevents disruptive precedent
- Suite: playwright/encounter
- Expected: resolution template picker available; one-time-exception flag marks ruling as non-precedent
- AC: Rulings-4–5

## TC-RUN-06 — Adventure authoring: motivation hooks, scene diversity tracking
- Description: Per-player motivation hooks and engagement targets; scene-type diversity tracking (combat/social/problem-solving/stealth) per session and arc
- Suite: playwright/downtime
- Expected: adventure record has per-player hook fields; scene-type log per session; arc-level diversity summary
- AC: AdventureDesign-1–2

## TC-RUN-07 — Encounter set builder: repetition warning; encounter metadata
- Description: Encounter builder warns on repetitive composition across consecutive sessions; metadata captures narrative purpose, adversary rationale, location hooks
- Suite: playwright/encounter
- Expected: consecutive same-composition sessions → warning; encounter metadata fields present (narrative purpose, adversary rationale)
- AC: EncounterDesign-1–2

## TC-RUN-08 — Threat scheduling: trivial/low/moderate/severe; extreme as set-piece gate
- Description: Encounter builder mixes threat levels; extreme encounters gated as designated set-pieces
- Suite: playwright/encounter
- Expected: threat-level distribution visible in campaign planner; extreme encounters require explicit set-piece flag
- AC: EncounterDesign-3

## TC-RUN-09 — Turn-order UI: current + next actor shown
- Description: Encounter turn-order panel shows current actor highlighted and next actor in queue
- Suite: playwright/encounter
- Expected: current turn actor visually distinct; next-up actor shown in initiative order
- AC: RunningEncounters-1

## TC-RUN-10 — Turn manager: same-turn rewinds permitted; cross-turn blocked by default
- Description: Same-turn rewinds allowed; cross-turn rewinds blocked by default (configurable setting)
- Suite: playwright/encounter
- Expected: undo within current turn succeeds; undo crossing a turn boundary blocked unless config enabled; lightweight corrections (omitted static damage) always allowed
- AC: RunningEncounters-3–4

## TC-RUN-11 — Stealth initiative: compares Stealth vs. each observer's Perception DC
- Description: When "Avoiding Notice" initiative used: compare Stealth result against each enemy's Perception DC; determines undetected state per observer
- Suite: playwright/encounter
- Expected: stealth initiative roll compared against each observer individually; result: undetected to some observers, detected to others (per result)
- AC: RunningEncounters-5–6

## TC-RUN-12 — Campaign planner: scope templates and player preference intake
- Description: Campaign scope templates (one-shot, brief, extended, epic) with level ceilings and session cadence; templates promotable to longer arcs; player-preference intake collected at campaign creation
- Suite: playwright/downtime
- Expected: 4 scope templates available; promotion preserves data; player goal coverage tracked per session
- AC: CampaignDesign-1–3

### Acceptance criteria (reference)

# Acceptance Criteria: GMG Running Guide (GM Tools)

## Feature: dc-gmg-running-guide
## Source: PF2E Game Master's Guide, Chapter 1

---

## General GM Advice Tools

- [ ] Session zero workflow: system supports a pre-play "session zero" phase with records for party links/relationships and character integration notes for adventure hooks
- [ ] Between-session task handling: supports asynchronous resolution (treasure division, leveling, downtime)
- [ ] GM dashboard: quick-reference cache of key PC modifiers (Perception, Will, common Recall Knowledge skills); refreshes on level-up/stat change
- [ ] Secret-check mode: hides roll outcomes from players (GM-only view)

---

## Rules Adjudication Tools

- [ ] Ruling records: support precedent linkage so future rulings can reference prior analogous decisions
- [ ] Accumulated precedents tracked as candidate house-rule seeds
- [ ] Collaborative rules lookup: GM marks ruling as provisional with deferred post-session review flag
- [ ] Between-session review workflow: publishes clarified/updated rulings before next session start
- [ ] Creative-action resolution templates: minor bonus, minor penalty, minor damage-plus-rider, object-triggered save
- [ ] GM can mark edge-case rulings as one-time exceptions to prevent disruptive precedent

---

## Adventure and Campaign Design

- [ ] Adventure authoring: per-player motivation hooks and engagement targets; captures spotlight rotation
- [ ] Scene-type diversity tracking: combat/social/problem-solving/stealth per session and arc
- [ ] Encounter set builder: warns on repetitive composition across consecutive sessions
- [ ] Adventure outline format: supports intended emotional beats (triumph, dread, optimism) by scene or phase
- [ ] Adventure generator: six-step pipeline linking style and threat to motivations, arcs, factions, and mechanical content
- [ ] Campaign planner: scope templates (one-shot, brief, extended, epic) with level ceilings and session cadence guidance
- [ ] Campaign templates: promotable from shorter to longer arcs without data loss
- [ ] Campaign intake: collects player-preference touchstones and character goals; tracks goal coverage across sessions

---

## Encounter Design

- [ ] Encounter metadata: narrative purpose, adversary rationale, location hooks (not only XP math)
- [ ] Dynamic twists/phases tracked per encounter
- [ ] Threat scheduling: mixes trivial/low/moderate/severe; gates extreme threats as set pieces
- [ ] Setup profiles: ambush, negotiation-collapse, duel, chase transition, retreat, surrender end states
- [ ] Map authoring: models maneuverability, line-of-sight, range lanes, and cover anchors as first-class features
- [ ] Encounter placement: accounts for inhabitant terrain familiarity and movement modes (burrow/climb/swim/fly)

---

## Running Encounters (GM Tooling)

- [ ] Turn-order UI: shows current and next actor
- [ ] Encounter log: surfaces action-to-consequence feedback immediately
- [ ] Turn manager: same-turn rewinds permitted; cross-turn rewinds blocked by default (configurable)
- [ ] Lightweight corrections outside turn: allowed (e.g., applying omitted static damage)
- [ ] Initiative: skill-based selection (Stealth when Avoiding Notice)
- [ ] Stealth-initiative: compares Stealth result against enemy Perception DCs; determines undetected state per observer
- [ ] Grouped initiative: optional, for identical enemy sets; preserves individual turns and Delay behavior
- [ ] Aid validation: requires preparation, valid position, communication feasibility; scales aid timing with task scope
- [ ] Ready trigger validation: rejects purely meta triggers (HP thresholds, unobservable tags); requires in-world observables
- [ ] Cover adjudication: physical silhouette plausibility determines cover; supports prone-integration when terrain requires

---

## Running Exploration

- [ ] Exploration flow: sensory-scene prompts, variable time compression, mystery hooks
- [ ] Scene description templates: multi-sensory fields (sight, sound, smell, temperature, texture)
- [ ] Exploration time tracking: coarse increments (10-minute default, hour-scale for travel)
- [ ] GM slow-time controls: explicit moments for key decisions, emotional beats, new-area entry
- [ ] Environment authoring: familiar-vs-novel tagging to guide description emphasis

---

## Running Downtime

- [ ] Downtime subsystem: world-state updates tied to PC accomplishments
- [ ] Downtime resolution: low-roll-count summaries by default; branches into encounter/exploration when triggered
- [ ] Campaign downtime-depth setting: light/medium/deep; adjustable over campaign lifetime
- [ ] Scene fusion: supports multiple PC tasks in shared contexts
- [ ] Per-player opt-out: low-detail summary option for players who skip downtime roleplay

---

## Rarity in Your Game

- [ ] Content catalog: encodes rarity tiers (common/uncommon/rare/unique) with distinct default access semantics
- [ ] Rarity evaluation: context-sensitive by locale/culture/campaign framing (not globally static)
- [ ] Character-creation pipeline: supports uncommon/rare starting elements via GM campaign allowlists

---

## Narrative Collaboration

- [ ] Campaign framework: selectable collaboration modes (GM-led, shared content, decentralized narration)
- [ ] Shared-authoring mode: ownership logs for player-authored setting/NPC components
- [ ] Optional Story Point economy: bounded narrative interventions (twist/fact/NPC attitude); cannot auto-resolve whole scenes

---

## Resolving Problems and Safety

- [ ] Safety/governance setup: explicit table preferences for lethality and TPK handling
- [ ] Post-TPK workflow: requires player consent before campaign continuation
- [ ] Safety tool configuration: X-Card and Lines/Veils equivalents; safety interrupts with immediate pause/redirection behavior
- [ ] Table-governance policy: distinguishes coachable from zero-tolerance behaviors
- [ ] Power-imbalance mitigation: consensual retraining, narrative off-ramping, encounter recalibration

---

## Special Circumstances

- [ ] Organized-play mode: campaign-level option allowlists/denylists external to GM preference
- [ ] Group-size adaptation: party-compensation options (extra PCs, support NPCs, character flexibility variants)
- [ ] GM-controlled support entities: guardrails preventing major-decision dominance or role overshadowing

---

## Integration Checks

- [ ] GM dashboard modifier cache correctly refreshes after character level-up events
- [ ] Provisional rulings clearly flagged in session log with deferred-review status
- [ ] Story Point economy enforces scope limits (cannot rewrite reality or auto-resolve scenes)
- [ ] Safety interrupts are accessible from main session UI with no more than 1-click access

## Edge Cases

- [ ] Grouped initiative: if one creature in a group delays, they are removed from the group and placed individually
- [ ] Secret check mode: GM can override the hidden result and reveal it at any time
- [ ] Rarity allowlist: GM approval grants full access; revocation of access mid-campaign flags dependent character features
- Agent: qa-dungeoncrawler
- Status: pending
