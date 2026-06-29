# PF2E Gamemastery Guide — Chapter 1: Gamemastery Basics
## Systematic Requirements Analysis (Paragraph by Paragraph)

**Source:** `reference documentation/PF2E Gamemastery Guide.txt` (initial pass)
**Status:** Complete

---

## SECTION: Chapter Scope and GM Priorities

### Paragraph — Chapter topic map
> "Running Encounters (page 10)... Running Exploration (page 17)... Running Downtime (page 22)... Adjudicating Rules (page 28)... Resolving Problems (page 30)... Narrative Collaboration (page 32)... Special Circumstances (page 33)... Rarity in Your Game (page 35)... Campaign Structure (page 36)... Adventure Design (page 40)... Encounter Design (page 46)... Drawing Maps (page 52)."

Requirements identified:
- REQ: The GM support layer shall treat encounter, exploration, and downtime as distinct operating modes with separate guidance surfaces.
- REQ: The documentation model shall preserve explicit cross-links between mode-running guidance and encounter/adventure/map design guidance.

---

## SECTION: General Advice

### Paragraph — Session zero
> "A session for building characters is commonly called 'session zero'... lets players share character details... gives veterans the chance to help less experienced players... gives you a better understanding of the characters and help the players integrate them into the adventure."

Requirements identified:
- REQ: Campaign setup flow shall support an explicit pre-play "session zero" phase.
- REQ: Session zero records shall support party links/relationships prior to first adventure session.
- REQ: Session zero shall capture GM-facing character integration notes for later adventure hooks.

---

### Paragraph — Pacing game sessions
> "Most sessions should have lulls in the action punctuated by challenges... break things up into smaller scenes and memorable moments... About 20 minutes before a play session is scheduled to conclude, it can be beneficial to figure out how you'd like to end... cliffhanger..."

Requirements identified:
- REQ: Session planner should support pacing plans with alternating high-intensity and low-intensity scenes.
- REQ: Session planner should support explicit end-of-session targets (cliffhanger, reveal, pre-combat pause, etc.).
- REQ: Between-session task handling should support asynchronous resolution items (treasure division, leveling, downtime).

---

### Paragraph — Stakes and consequences
> "A GM should always convey a clear picture of the stakes and consequences... Consequences should be specific and evocative... It's usually best if the PCs can foresee the consequences, at least in a general sense... keep [subversions] to a minimum..."

Requirements identified:
- REQ: Scenario definitions shall include explicit failure stakes and success outcomes visible to players.
- REQ: Consequence logic should prefer predictable cause-effect chains over frequent opaque reversals.
- REQ: Reward hooks shall support accomplishment XP signaling when major goals are achieved.

---

### Paragraph — Failing forward
> "'Failing forward' means finding a way to progress the story instead of just saying, 'That didn't work.' ... a failure might still impart more information, reveal a way to improve their chances next time, or even cause unforeseen difficulties."

Requirements identified:
- REQ: Check-resolution flow should support non-blocking failure outcomes that still advance state.
- REQ: Failure outcomes should support one or more of: clue reveal, next-attempt advantage setup, or complication insertion.
- REQ: The system shall still allow hard stops when no credible fail-forward branch exists.

---

### Paragraph — Secret checks
> "During play, you roll some checks in secret... It can be handy to keep a list of the PCs' modifiers on hand... check in anytime the PCs level up..."

Requirements identified:
- REQ: GM tools shall support secret-check mode that hides roll outcomes from players.
- REQ: GM dashboard shall maintain a quick-reference cache of key PC modifiers (Perception, Will, common Recall Knowledge skills).
- REQ: Modifier cache should support easy refresh triggers on level-up/stat-change events.

---

## SECTION: Running Encounters

### Paragraph — Encounter GM focus
> "As a GM, you should primarily focus on... Answering questions quickly... Building anticipation... setting a rapid pace... letting players know when they're up and 'on deck'... showing immediate consequences."

Requirements identified:
- REQ: Encounter UI shall provide turn-order visibility including current actor and next-up actor.
- REQ: Encounter log shall surface immediate action-to-consequence feedback.
- REQ: Encounter assist should optimize for fast rulings over exhaustive interruption for minor edge cases.

---

### Paragraph — Looking up rules
> "For something that isn't too impactful, it's better to just make a ruling on the spot and move on... look up something that's both significant and heavily rules-dependent... summarize."

Requirements identified:
- REQ: Adjudication policy shall differentiate minor rulings (resolve now, verify later) from major rules-dependent rulings (pause and verify).
- REQ: Rules references shown in encounter mode should be summarized by default to reduce table-flow disruption.

---

### Paragraph — Rewinding
> "The best policy is usually to let them rewind as needed within their own turn but stop them before they intrude into someone else's... Try to be consistent about what kinds of things you will rewind for and when."

Requirements identified:
- REQ: Turn manager shall permit same-turn rewinds and block cross-turn rewinds by default.
- REQ: Rewind policy shall be configurable but consistent within a session.
- REQ: Lightweight corrections (for example applying omitted static damage) may be allowed outside-turn without full rewind.

---

### Paragraph — Initiative and stealth
> "Anyone who's Avoiding Notice should attempt a Stealth check for their initiative... compare their Stealth check for initiative to the Perception DC of their enemies... You can give them the option to roll Perception instead, but if they do they forsake their Stealth."

Requirements identified:
- REQ: Initiative subsystem shall support skill-based initiative selection (Stealth when Avoiding Notice).
- REQ: Encounter start resolution shall compare Stealth-initiative results against enemy Perception DCs to determine initial undetected state per observer.
- REQ: Choosing Perception initiative in place of Stealth shall remove stealth-preservation at encounter start.

---

### Paragraph — Batch initiative
> "If you have multiple enemies of the same type... you can roll just one initiative check for all of them. They still take individual turns and can still individually change their initiative by Delaying."

Requirements identified:
- REQ: Encounter tooling shall support grouped initiative for identical enemy sets.
- REQ: Grouped initiative shall preserve individual turns and individual Delay behavior.
- REQ: Grouped initiative should be optional and marked as a speed-of-play optimization.

---

### Paragraph — Aid and Ready adjudication
> "AID... preparation should be specific to the task at hand... in a proper position to help... determine how long the preparation takes... READY... trigger must be something that happens in the game world and is observable by the character..."

Requirements identified:
- REQ: Aid validation shall require task-specific preparation, valid helping position, and communication feasibility.
- REQ: Aid timing shall scale with task scope (single-action support for short tasks; sustained support for long tasks).
- REQ: Ready trigger validation shall reject purely meta triggers (for example HP thresholds or unobservable rules tags) and require in-world observables.

---

### Paragraph — Take Cover and map abstraction
> "TAKE COVER... usually just need a large enough object... might require them to Drop Prone... let them combine this with the Take Cover action... A grid and miniatures can make it easier... there’s still room for improvisation... give players minor boosts that fit the story."

Requirements identified:
- REQ: Cover adjudication shall use physical silhouette plausibility to determine cover availability.
- REQ: Take Cover resolution should support optional prone-integration when posture change is required by terrain/object geometry.
- REQ: Tactical map mode shall allow narrative micro-adjustments (minor movement/position boosts) when approved by GM adjudication.

---

## SECTION: Running Exploration

### Paragraph — Exploration goals
> "As you run exploration, keep the following basic goals in mind... evoke the setting with sensory details... shift the passage of time... present small-scale mysteries... look for ways to move the action forward... plan effective transitions to encounters."

Requirements identified:
- REQ: Exploration flow shall support sensory-scene prompts, variable time compression/expansion, and mystery hooks.
- REQ: Exploration checks should support fail-forward outcomes consistent with forward motion and added complications.
- REQ: Exploration engine shall support clean transition hooks into encounter mode.

---

### Paragraph — Evocative environments
> "Convey their surroundings by appealing to the players’ senses... think about what’s familiar versus novel... the more you explain something, the more important it seems."

Requirements identified:
- REQ: Scene-description templates shall include multi-sensory fields (sight, sound, smell, temperature, texture).
- REQ: Environment authoring shall support explicit familiar-vs-novel tagging to guide emphasis.
- REQ: GM aid should warn that repeated/high-detail emphasis implies significance to players.

---

### Paragraph — Flow of time
> "You rarely measure exploration down to the second or minute... nearest 10-minute increment typically does the job... Time will seem to slow down the more detail you give... speed up or slow down... when establishing or progressing the story."

Requirements identified:
- REQ: Exploration time tracking shall default to coarse increments (typically 10-minute units, hour-scale for long travel).
- REQ: Narrative pacing controls shall tie description depth to perceived time dilation.
- REQ: GM controls shall allow explicit slow-time moments for key decisions, emotional beats, and new-area entry.

---

## SECTION: Running Downtime

### Paragraph — Downtime objectives and scope
> "You can use downtime... demonstrate changes to the setting... emphasize planning... keep the number of rolls small... switch to encounter or exploration as needed..."

Requirements identified:
- REQ: Downtime subsystem shall support world-state updates tied to prior PC accomplishments.
- REQ: Downtime resolution shall prefer low-roll-count summaries over granular roll spam by default.
- REQ: Downtime actions shall be allowed to branch into encounter or exploration scenes when triggered by outcomes.

---

### Paragraph — Depth of downtime
> "Determine how involved your group wants downtime to be at the start of the game... Downtime should rarely last a whole session... about a half hour between significant adventures..."

Requirements identified:
- REQ: Campaign configuration shall include a downtime-depth setting (light/medium/deep) adjustable over campaign lifetime.
- REQ: Session planner shall support recommended real-time budgets for downtime blocks.
- REQ: Downtime narration depth shall scale up only when player intent/questions indicate high story value.

---

### Paragraph — Group engagement and no-downtime campaigns
> "One major challenge of downtime is keeping the whole group involved... combine multiple people’s tasks into one... If a player really isn’t interested... one-sentence description... campaigns without downtime... summarize what happens between adventures and skip downtime rules."

Requirements identified:
- REQ: Downtime scheduler shall support scene fusion for multiple PC tasks in shared contexts.
- REQ: Participation controls shall support per-player low-detail summaries when a player opts out of downtime roleplay.
- REQ: Campaign mode shall permit disabling downtime mechanics in favor of between-adventure summary resolution.

---

## SECTION: Adjudicating Rules

### Paragraph — Core adjudication principles
> "Strive to make quick, fair, and consistent rulings... make a call and get on with play... review your decision after the session... explain why you’re ruling a certain way and compare to past rulings."

Requirements identified:
- REQ: Rules-adjudication policy shall prioritize speed, fairness, consistency, and post-session correction loops.
- REQ: Ruling records shall support precedent linkage so future rulings can reference prior analogous decisions.
- REQ: The system shall treat accumulated precedents as candidate house-rule seeds.

---

## SECTION: Resolving Problems

### Paragraph — Table problem handling and TPKs
> "Keep in mind the primary reason... have fun... total party kills... discuss [TPKs] with players... offer opportunities to avoid TPK... game should continue only if players want it to."

Requirements identified:
- REQ: Campaign safety/governance setup shall include explicit table preferences for lethality and TPK handling.
- REQ: Encounter-control guidance shall include non-forced escape/capture/aid branches to preserve player agency under looming TPK risk.
- REQ: Post-TPK workflow shall require explicit player consent before campaign continuation.

---

## SECTION: Narrative Collaboration

### Paragraph — Agency and collaboration models
> "Most players want their contributions to shape the campaign’s story... idea farm... creative collaboration... decentralized storytelling..."

Requirements identified:
- REQ: Campaign framework shall support selectable collaboration modes: GM-led with feedback, shared content ownership, and decentralized narration.
- REQ: Collaboration workflows shall include periodic checkpoints for player input at campaign start and major milestones.
- REQ: Shared-authoring mode shall support ownership logs for player-authored setting/NPC components.

---

## SECTION: Rarity in Your Game

### Paragraph — Four rarities and context/access
> "Common... Uncommon... Rare... Unique... Just because something is common or uncommon in one context doesn’t necessarily mean it’s the same in others... Access entries... Starting elements..."

Requirements identified:
- REQ: Content catalog shall encode rarity tiers (common, uncommon, rare, unique) with distinct default access semantics.
- REQ: Rarity evaluation shall be context-sensitive by locale/culture/campaign framing rather than globally static.
- REQ: Uncommon-option access entries shall grant common-like availability when character criteria are met.
- REQ: Character-creation pipeline shall support uncommon/rare starting elements via GM campaign allowlists.

---

## SECTION: Campaign Structure

### Paragraph — Campaign scopes and progression models
> "One-shot... Brief campaign... Extended campaign... Epic campaign... top level and time frame guidance."

Requirements identified:
- REQ: Campaign planner shall support predefined scope templates (one-shot, brief, extended, epic) with configurable level ceilings and expected session cadence.
- REQ: Campaign templates shall expose adventure-count guidance to structure pacing and progression.
- REQ: Campaign configuration shall allow promotion from shorter templates to longer arcs without data loss.

---

### Paragraph — Linking adventures and theme transitions
> "A smooth transition from one adventure to the next ties the story together... use recurring NPCs, clues, fallout, or travel links... consider how each adventure’s theme plays into the campaign as a whole."

Requirements identified:
- REQ: Adventure graph shall support explicit link artifacts (recurring NPCs, carry-forward clues, consequence fallout, travel bridges).
- REQ: Campaign timeline shall support interstitial adventures for geographic or narrative transitions.
- REQ: Theme management shall support both recurring motifs and intentional theme shifts with world-state continuity.

---

### Paragraph — Player goals in campaign structure
> "Ask what you and the other players enjoy... Find out what each character wants to achieve and look for opportunities you can place in the game world and adventures."

Requirements identified:
- REQ: Campaign intake shall collect player-preference touchstones and character goals at start and refresh points.
- REQ: Goal-tracking model shall map each PC goal to supporting hooks in encounters, exploration, downtime, and rewards.
- REQ: GM planning views should surface uncovered goals to avoid long-term neglect of player priorities.

---

## SECTION: Adventure Design

### Paragraph — Player motivations and engagement
> "Implementing plot hooks that speak to their motivations... ask in advance what they’d like to see... important thing is getting players engaged, not predicting the future."

Requirements identified:
- REQ: Adventure authoring shall include per-player motivation hooks and engagement targets.
- REQ: Hook design shall avoid assuming deterministic player choices while still presenting motivation-aligned opportunities.
- REQ: Session feedback loop shall capture motivation effectiveness for future adjustment.

---

### Paragraph — Keeping adventures varied
> "Give players variety through challenge types, locations, NPCs, monsters, and treasure... avoid repetitive scene structures."

Requirements identified:
- REQ: Adventure balancing tools shall track scene-type diversity (combat/social/problem-solving/stealth/etc.) per session and per arc.
- REQ: Encounter-set builder shall warn on repetitive composition patterns across consecutive sessions.
- REQ: Adventure plans shall support spotlight rotation so different player preferences receive targeted content.

---

### Paragraph — Theme and emotional arc
> "Think about emotional and thematic touchstones... planning for them can give an emotional arc to an adventure."

Requirements identified:
- REQ: Adventure outline format shall include intended emotional beats (for example triumph, dread, optimism) by scene or phase.
- REQ: NPC/location design notes shall reference target thematic tone to maintain coherence.

---

### Paragraph — Adventure recipe framework
> "Styles... Threats... Motivations... Story Arcs... NPCs and Organizations... Mechanics."

Requirements identified:
- REQ: Adventure generator shall support a six-step recipe pipeline linking style and threat to motivations, arcs, factions, and mechanical content.
- REQ: Recipe stages shall remain editable during play to accommodate emergent narrative changes.
- REQ: Opposition modeling shall support multiple non-monolithic adversary groups with internal conflicts.

---

## SECTION: Encounter Design

### Paragraph — Encounter quality dimensions
> "Good encounters have a place in the story, compelling adversaries, interesting locations, and twists and turns..."

Requirements identified:
- REQ: Encounter templates shall require narrative purpose, adversary rationale, and location hooks (not only XP/level math).
- REQ: Encounter metadata shall support dynamic twists/phases to avoid static slugfests.

---

### Paragraph — Encounter variety and composition
> "Variety... theme, difficulty, complexity, composition, setup, information uncertainty."

Requirements identified:
- REQ: Encounter planning shall support explicit variety dimensions: theme, threat band, complexity, composition, setup, and information visibility.
- REQ: Threat scheduling should intentionally mix trivial/low/moderate/severe encounters and gate extreme threats for major set pieces.
- REQ: Setup profiles shall include ambush, negotiation-collapse, duel, chase transition, retreat, and surrender end states.

---

### Paragraph — Encounter locations and terrain logic
> "Choose compelling settings... map features impact maneuverability, line of sight, and attack ranges... inhabitant familiarity and terrain adaptation..."

Requirements identified:
- REQ: Map authoring shall model maneuverability, line-of-sight blockers, range lanes, and cover anchors as first-class encounter features.
- REQ: Encounter placement shall account for inhabitant terrain familiarity and movement-mode advantages (burrow/climb/swim/fly).
- REQ: Defensive scenarios shall support "PC home turf" preparation windows for traps, wards, and ambush setup.

---

## SECTION: Drawing Maps

### Paragraph — Map purpose and production constraints
> "Maps should serve your purposes... tool for tracking action and possibilities... no need for professional art."

Requirements identified:
- REQ: Map workflow shall prioritize legibility and play utility over visual polish.
- REQ: Map tooling should support iterative draft/edit cycles with low-friction correction.

---

### Paragraph — Legend, scale, orientation, symbol key
> "Create a legend... decide scale and orientation... key for symbols..."

Requirements identified:
- REQ: Every adventure map artifact shall include mandatory metadata: scale, orientation, and symbol legend.
- REQ: Grid mapping shall support variable scale modes (5-foot tactical, larger strategic scales).

---

### Paragraph — Sketching and map composition
> "Start sketching... keep maps simple and legible... use real-world references... ensure creatures fit spaces... avoid purposeless rooms and repetitive symmetry."

Requirements identified:
- REQ: Map validation shall enforce traversability constraints for expected creature sizes and movement.
- REQ: Room authoring should require stated functional purpose for each encounter area.
- REQ: Layout guidance shall discourage excessive symmetry/repetition unless intentionally thematic.

---

### Paragraph — Numbering encounter areas and definition pass
> "Number encounter areas... use numbering in encounter notes... add definition with darker lines/ink for reusable maps."

Requirements identified:
- REQ: Map-to-encounter linkage shall require stable area IDs (with optional site prefix namespace).
- REQ: Encounter notes shall reference map area IDs as primary keys for scene indexing.
- REQ: Finalized map artifacts shall support durable export state for reuse across sessions/adventures.

---

## SECTION: Adjudicating Rules (Extended)

### Paragraph — Listen to players and shared recall
> "Sharing the task of remembering the rules makes rules discussions collaborative rather than combative... making the final decision... falls to you."

Requirements identified:
- REQ: Rules discussion flow shall support collaborative lookup with players while preserving GM final authority for live rulings.
- REQ: Session tooling should allow distributed source lookup assignments to speed multi-source rule verification.
- REQ: Rules concerns raised by players shall be captured and not silently dismissed.

---

### Paragraph — Make the call and review later
> "Often the best ruling is the one that keeps the game moving... This is how we’re playing it now... review between sessions."

Requirements identified:
- REQ: Live adjudication shall support provisional rulings with deferred post-session review.
- REQ: Provisional rulings should be announced explicitly as temporary where applicable.
- REQ: Between-session review workflow shall publish clarified/updated rulings before next session start.

---

### Paragraph — Saying 'Yes, but'
> "It’s usually better to say 'yes' than 'no,' within reason... allow the player’s creative idea, but tie it into the world and the game rules via consequences."

Requirements identified:
- REQ: Improvisational adjudication should prefer permissive outcomes bounded by costs, checks, conditions, or constrained one-time effects.
- REQ: Creative-action resolution library shall support minor bonus, minor penalty, minor damage-plus-rider, or object-triggered save templates.
- REQ: GM may mark edge-case rulings as one-time exceptions to prevent disruptive long-term precedent.

---

## SECTION: Resolving Problems (Extended)

### Paragraph — Problematic players and intervention model
> "Handle the problem privately... unacceptable behaviors must be dealt with firmly and decisively..."

Requirements identified:
- REQ: Table-governance policy shall distinguish coachable behaviors from zero-tolerance behaviors.
- REQ: Intervention workflow shall default to private conversation channels before public escalation (except urgent harm cases).
- REQ: Governance logs should support behavior-category tagging and documented outcome decisions.

---

### Paragraph — Safety tools
> "X-Card and Lines and Veils... allow anyone who feels uncomfortable or unsafe to express discomfort, with clear guidance on response."

Requirements identified:
- REQ: Campaign setup shall include optional safety tool configuration (X-Card and Lines/Veils equivalents).
- REQ: Safety interrupts shall provide immediate pause/redirection behavior with no penalty to participating players.

---

### Paragraph — Ejecting player, cheating, and power imbalance
> "Serially disruptive player... modify behavior or leave... cheating steals fun... power imbalance may require retraining, item removal, or encounter/campaign adjustment."

Requirements identified:
- REQ: Persistent severe misconduct workflow shall support removal decisions with explicit rationale and finality.
- REQ: Suspected cheating workflow shall start with correction-first assumptions, then escalate to conduct enforcement when intentional behavior is confirmed.
- REQ: Power-imbalance mitigation shall support consensual retraining, narrative item off-ramping, and encounter recalibration while preserving group fun.

---

## SECTION: Narrative Collaboration (Extended)

### Paragraph — Shared-control challenges and Story Points
> "Largest risk... losing a cohesive story... GM recaps events... Story Points allow players to suggest twists or establish facts, but not rewrite entire scenes."

Requirements identified:
- REQ: Shared-narrative mode shall include recap checkpoints to maintain canonical continuity.
- REQ: Optional Story Point economy shall allow bounded narrative interventions (quick twist/fact/NPC attitude) with explicit scope limits.
- REQ: Story Point actions shall be disallowed from auto-resolving whole scenes or massively rewriting setting reality.

---

## SECTION: Special Circumstances

### Paragraph — Pathfinder Society and constrained option access
> "Organized Play handles some tasks normally in GM purview, such as rules option availability... stay true to scenario while allowing reasonable creative solutions."

Requirements identified:
- REQ: Organized-play mode shall support campaign-level option allowlists/denylists external to local GM preference.
- REQ: Scenario-run mode shall enforce baseline script fidelity while allowing GM-adjudicated alternate solution paths.
- REQ: Alternate challenge bypasses (illusion/social/bribery) shall map to GM-set DC resolution patterns.

---

### Paragraph — Unusual group sizes (small groups)
> "Small groups can have role gaps... add characters/hirelings/support NPCs... avoid GMPC spotlight theft... consider dual-class/free-archetype variants."

Requirements identified:
- REQ: Group-size adaptation layer shall support party-compensation options: extra PCs, support NPCs, or character-flexibility variants.
- REQ: GM-controlled support entities shall have guardrails preventing major-decision dominance or role overshadowing.
- REQ: Encounter tuning for small groups shall flag high-single-target incapacitation threats and recommend compensating adjustments.

---
