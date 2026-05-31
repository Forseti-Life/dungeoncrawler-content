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
