# Quest goals, approaches and hidden information — plan

Status: partly built 2026-11-15 (revision 4). Section 10 says what exists
and where it differs from the design above.

## 1. The problem

A quest's aim ("end the goblin threat") and one way of achieving it ("kill
four goblins") are stored as the same thing. A party that talks the goblins
into leaving cannot finish the quest, the journal cannot show a goal with
several known ways to reach it, and there is nowhere to keep what only the GM
should know.

## 2. What exists today

There are two separate mechanisms, and they are not connected.

### 2.1 Quest objectives (the mechanical layer)

- Seven types: collect, kill, investigate, explore, escort, interact,
  composite (`config/objective_type_options.json`).
- Three completion kinds: `count`, `flag`, `all_children`. No "any of".
- One `description`, which is the player-facing text. No GM-only field.
- `hidden` objectives are revealed in list order when earlier ones complete
  (`QuestTrackerService::normalizeObjectiveCollectionVisibility`). Nothing
  reveals on a discovery. Hidden objectives are still sent to the browser and
  filtered there.
- Progress comes from typed game events ("touchpoints") matched to objectives
  of the same type (`QuestTouchpointService`). Uncertain matches become
  confirmations.

### 2.2 Storyline decisions (the narrative layer)

The Storyline Builder already has "Player decisions and consequences":

- a **decision** hangs off a source quest and has two or more **choices**;
- each choice has a typed **trigger** (player choice, action, objective
  result, skill result, actor/environment/item state, timer, GM decision);
- each choice has **effects** on actors, the environment and the storyline,
  and a destination (next quest or scene, or terminal);
- during play the GM agent sees pending decisions in its prompt and resolves
  one with the tool `resolve_storyline_branch`.

This is most of an "approach → outcome" system. What it lacks:

- **It does not complete the quest.** I found no code that completes the
  source quest's objective when a choice is resolved. Negotiating moves the
  story on and leaves "kill four goblins" open.
- **No visibility.** A choice has a "player-facing" label but nothing says
  whether players know about it, and the journal shows no choices at all.
- **Storyline-only.** A standalone quest has no decisions.

## 3. Design

One rule: **an approach is a choice.** The existing decision/choice record is
extended rather than adding a third model beside it.

### 3.1 Goal

Each quest objective that players pursue is a **goal**:

```
goal
  objective_id
  goal_text        player-facing aim: "End the goblin threat to the mill"
  gm_notes         never sent to a player
  resolution       any_of | all_of
  open_resolution  true when an unplanned way may still count
  decision_id      the decision whose choices are this goal's approaches
```

### 3.2 Approach (an extended choice)

```
choice
  decision_id, choice_id, label          (exist today)
  trigger                                (exists today)
  effects, target quest/scene, terminal  (exist today)
  visibility      known | discoverable | secret        NEW
  reveal_when     trigger-shaped condition             NEW
  criteria        typed objective tree, or none        NEW
  outcome_label   shown afterwards: "The goblins left" NEW
  rewards         optional change to the quest reward  NEW
```

- `criteria` present: touchpoints advance it; when met, the choice resolves
  itself. "Kill the goblins" is a choice whose criteria are `kill ×4`.
- `criteria` absent: only the GM agent resolves it. "Negotiate" is this kind.
- Resolving any choice of an `any_of` goal completes the goal, records which
  choice, applies the effects and moves the storyline — in one operation.

### 3.3 Visibility, enforced on the server

- **known**: in the journal from the start.
- **discoverable**: withheld until `reveal_when` is met (an NPC conversation,
  an item found, a room entered, a skill result, another goal completed).
- **secret**: never shown before it is used; appears only in "how it was
  resolved".

The quest summary is built per audience. The player version omits unrevealed
approaches and all GM notes. Game Master mode and the GM agent get everything
with visibility marked. Nothing is filtered in the browser.

### 3.4 Standalone quests

The goal/decision/choice structure moves into the quest definition itself, so
a quest outside any storyline can have approaches. The storyline keeps only
what is storyline-specific: destinations and storyline effects.

## 4. Storyline editor review

Step 2 of the builder ("Journal, Tasks, Objectives, Completion Criteria").

### 4.1 Tasks and scene placement

- **Today:** quest id, chapter id, scene id, status. No name, no aim.
- **Problems:** the heading says "Tasks" while every field says "Quest"; the
  author never writes what the players are trying to do.
- **Change:** rename to Quests. Add goal text and GM notes. Chapter and scene
  become pickers from the journal entries.

### 4.2 Objective completion criteria

- **Today:** one row per quest, keyed by the quest id: kind
  (count/flag/all_children), a free-text metric, description, required value.
- **Problems:** no objective type or target, so a row cannot say "kill four
  goblins"; exactly one criterion per quest; no alternatives; not visibly tied
  to its quest.
- **Change:** this collection goes away. The goal lives on the quest (4.1) and
  criteria live on each approach (4.3).

### 4.3 Choices and triggers

- **Today:** decision id, source task id, choice id, label, trigger type,
  actor interaction, trigger condition, source id, and a free-text
  "key | operation | value" box.
- **Problems:** ten unlabelled inputs in one row; ids typed by hand; at least
  two choices are required, so a goal with one way cannot be expressed here;
  no visibility; no link to completing the quest.
- **Change:** becomes "Approaches", shown under its quest. Adds visibility,
  reveal condition, completion criteria (objective type, target, count),
  outcome label and reward change. One approach is allowed. Ids are generated;
  the source quest is implied by where the approach sits.

### 4.4 Actor, environment and storyline effects

- **Today:** three separate collections keyed by choice id.
- **Change:** keep the data; show each under its approach as "Consequences"
  so an author sees aim → ways → results in one place.

### 4.5 Layout

Replace the four flat collections with one card per quest:

```
Quest: Clear the mill            chapter / scene
  Goal:   End the goblin threat to the mill
  GM notes: Grubnak will leave if offered the old mine
  Approaches (any one)
    [known]        Drive them out by force   — kill ×4 goblins
    [discoverable] Persuade Chief Grubnak    — GM judged
                   revealed when: talks to the captured goblin
    [secret]       Collapse the mine tunnel  — GM judged
      Consequences: actors … environment … storyline …
  [ ] An unplanned way may also count
```

### 4.6 Step 7 review graph

The timeline and conflict graph already draws decision and choice nodes. Add
a visibility badge to each choice and mark which choices complete a goal.

## 5. Tools review

### 5.1 Storyline editor GM tools (`EditorGm/Tool/Storyline`)

- **describe_storyline_authoring_contract** — add goal, approach, visibility,
  reveal and criteria fields; drop the objective collection.
- **plan / apply_storyline_draft_operations** — the collection contract in
  `StorylineAuthoringDraftService` gains the new fields and loses
  `/process_flow/objective_completion_criteria`.
- **get_storyline_branching** — report visibility, reveal condition, criteria
  and whether each choice completes its goal.
- **check_storyline_creation_readiness** — new refusals (section 6).
- **plan / apply_storyline_remediation** — able to propose approaches for a
  goal that has one, and to flag goals whose only approach is "kill".
- **preview_storyline_definition** — show the player view and the GM view.
- **preview_storyline_narrative_analysis**, **publish_storyline_draft**,
  status, synopsis, snapshot — carry the new fields; no new behaviour.

### 5.2 Gameplay GM tools (`GmToolExecutionService`)

- **resolve_storyline_branch** — becomes the one way a goal is resolved. It
  also completes the goal, records the approach and evidence, and accepts
  `choice_id: "other"` with a reason when the goal allows an unplanned way.
  Renamed to match (for example `resolve_quest_goal`); the old name is removed.
- **modify_quest_state / modify_campaign_quest_progress** — today's manual
  overrides. They stop being a way to complete a goal; goal completion goes
  through the tool above so the approach is always recorded.
- **New: reveal_quest_approach** — the GM agent reveals a discoverable
  approach when play establishes it and no automatic condition fired.
- **GM prompt context** — pending decisions are already included. Add each
  choice's visibility and what the players currently know, so the GM does not
  leak a secret approach.

### 5.3 Quest runtime

- **QuestTouchpointService** — match events to the criteria of approaches;
  when an approach's criteria are met, resolve its choice. Evaluate
  `reveal_when` on each event.
- **QuestTrackerService** — any-of completion, approach recorded on the quest,
  per-audience projection replacing the hidden/revealed sequence rule.
  Progress moves from per character to the party: one quest state per
  campaign, with the acting character recorded on each event.
- **Quest summary schema** — goal, known approaches, resolved approach.
- **Canonical quest definition editor** — same goal/approach fields for
  standalone quests.

### 5.4 Journal

- Goal on top; "Ways you know of" with progress; "there may be other ways"
  whenever unrevealed approaches exist; after completion "Resolved: …". Game Master mode shows every approach
  with its visibility and the GM notes.

## 6. Validation rules (all refusals, no silent repair)

- A goal has at least one approach, or allows an unplanned way.
- A `known` approach exists for every goal, unless the goal is meant to be a
  mystery and says so.
- A `discoverable` approach has a reveal condition naming a real actor, item,
  room or goal.
- Criteria use a real objective type and target.
- Every approach of an `any_of` goal leads somewhere: a next quest, a scene,
  or a terminal outcome.

## 7. Migration

- Each existing objective becomes a goal with one `known` approach holding its
  current criteria. Play is unchanged until an author adds another approach.
- Existing decisions become the approaches of their source quest's goal, with
  visibility `known` (authors then adjust).
- One schema version bump for quest templates, storyline drafts, published
  storylines and live quest rows. No parallel old path.

## 8. Work order

1. **Data contract** — schemas, validation, migration of stored templates,
   drafts and live quests.
2. **Runtime** — any-of goals, approach criteria, resolution through one tool,
   resolution log, storyline advance on resolution.
3. **Visibility** — reveal conditions, per-audience quest summary.
4. **Gameplay GM** — tool rename and extension, reveal tool, prompt context.
5. **Journal** — goal / ways / resolved; Game Master view.
6. **Storyline editor** — per-quest cards (section 4), editor GM contract and
   tools (5.1).
7. **Quest definition editor** — standalone quests.
8. **Content** — rework published storylines where the only way is "kill".

Steps 1–2 change stored data and ship together. Each later step ships alone.
Each is checked by the quest, storyline and schema unit tests plus one quest
played three ways (force, negotiation, unplanned) in a throwaway campaign.

## 9. Board decisions (Keith, 2026-11-15)

1. **Other ways hint:** yes. The journal says "there may be other ways" when
   unrevealed approaches exist.
2. **Judged and unplanned routes:** the GM agent resolves them alone. Every
   resolution is logged with the approach and the evidence.
3. **Rewards:** the approach taken changes the reward. Story consequences
   stay on the approach as they are on choices today.
4. **Multiplayer progress:** shared by the party, not per character.
5. **Game Master mode:** stays open to every member. Secret approaches are
   kept from Player mode only; the table is on the honour system.

## 10. What is built (2026-11-15)

Contract: `src/Service/QuestGoalContract.php`. Checks:
`drush php:script tests/quest_goal_integration.php` (36 checks; uses archived
campaign 1152 and removes what it creates).

### Built

- **Goal and approaches**, with known / discoverable / secret visibility,
  reveal on an objective or a game event, approach rewards, and an unplanned
  way when the goal allows it.
- **Runtime** (`QuestTrackerService`): a goal quest completes when one
  approach is achieved — by its objectives, or by the GM. Direct completion
  of a goal quest is refused (`quest_goal_unresolved`).
- **Party-shared progress**: any party member's actions find the one quest
  state. On completion every other member earns the XP; gold and items go
  once, to the character who completed the quest.
- **Server-side visibility**: the quest summary gives players only revealed
  ways and their objectives; Game Master mode gets everything.
- **Gameplay GM tools**: `resolve_quest_goal`, `reveal_quest_approach`, and a
  "QUEST GOALS" block in the GM prompt listing hidden ways as hidden.
- **Journal**: goal, ways you know of, "there may be other ways", resolved
  outcome; Game Master view with hidden ways and GM notes.
- **Storyline Builder**: goal text, GM notes and "unplanned way" on each
  quest; visibility, outcome, reveal condition, objective ids and reward on
  each choice. The storyline GM's authoring contract picks these up from the
  draft schema.

### Where it differs from the design

- **A goal belongs to the quest, not to each objective.** One quest, one
  goal. Objectives not named by any approach are shared steps.
- **Where a goal is authored.** A storyline quest: on the storyline (quest
  goal + choices marked as approaches). A standalone quest: at
  `story_impact.goal` on its template. Declaring both is refused.
- **`resolve_storyline_branch` stays** for decisions that are not ways of
  achieving a goal. It refuses a choice that is, pointing to
  `resolve_quest_goal`.
- **Objective ids on an approach are checked when the quest is created in a
  campaign**, not at publication, because that is when the quest's objectives
  exist.

### Added in the second pass

- **Quest outline** in the builder: one card per quest showing goal, GM
  notes, ways (with who can see each, how it is achieved, outcome, reward,
  where it leads, consequences), other story decisions, and warnings. It is
  read from the rows, updates as you type, and each line has an Edit jump.
- **Review graph**: a choice that is a way of achieving a goal is labelled
  with its visibility.
- **`modify_quest_state`** is now guarded like the table override.
- **Verified**: resolving a goal by a way bound to a storyline choice records
  the choice, moves the storyline to the next scene, starts the next quest and
  completes the source quest (checked on campaign 1152, then restored).

### Added in the third pass

- **Quest cards are editable.** Goal, GM notes, unplanned ways, and each way
  (text, who knows, discovered when, objectives, outcome, reward, what comes
  next) are edited on the card. "Add a way" and "Add a quest" create the
  rows. The full rows sit under "All … fields" disclosures.
- **The live GM can act.** The room-chat GM does not call GM tools; it emits
  actions. `resolve_quest_goal` and `reveal_quest_approach` are now actions in
  its contract, executed by `GmOrchestrationBrokerService`. The GM tools of
  the same names remain for the agent harness (`drush dc:gm-actor-tool`).
- **Game Master mode buttons** in the journal: "Mark achieved", "Reveal to
  players", "Achieved another way…". Refused outside Game Master mode.
- **First content:** "Clear the Goblin Den" has a goal with two ways (by
  force, known; by parley, discovered on speaking with the chieftain) and
  allows an unplanned way.

### Not built yet

- Removing the "Objective completion criteria" collection (4.2). It is now
  under a disclosure, but the storyline manager's objective validation stage
  still reads it, so removing it means reworking that stage.
- A form for standalone quest goals. The product has no quest template editor
  at all; standalone quests are authored in `content/quest_templates.json`.
- The other three quests with kill objectives (rescue_merchant,
  tal-win-the-opening-fight, tal-clear-the-goblin-camp) have no goal yet.

### Not yet exercised

- The GM model choosing to emit the new actions. The broker executes them
  correctly when given one; one live turn was tried in campaign 1152, which
  was mid-encounter and produced no GM reply at all.
- A storyline published from the builder with goals, attached to a campaign
  and played through from the start.
