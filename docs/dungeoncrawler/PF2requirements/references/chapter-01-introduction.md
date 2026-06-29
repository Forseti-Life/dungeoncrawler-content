# PF2E Core Rulebook — Chapter 1: Introduction
## Systematic Requirements Analysis (Paragraph by Paragraph)

---

## SECTION: Format of Rules Elements

### Paragraph 1
> "Throughout this rulebook, you will see formatting standards that might look a bit unusual at first. Specifically, the game's rules are set apart in this text using specialized capitalization and italicization. These standards are in place to make the rules elements in this book easier to recognize."

Requirements identified: None. (Describes documentation conventions, not game mechanics.)

---

### Paragraph 2
> "The names of specific statistics, skills, feats, actions, and some other mechanical elements in Pathfinder are capitalized. This way, when you see the statement 'a Strike targets Armor Class,' you know that both Strike and Armor Class are referring to rules."

Requirements identified:
- The system must maintain a defined vocabulary of named mechanical entities (statistics, skills, feats, actions, conditions) that are distinct from narrative text.
- Any UI that surfaces rules text must be able to distinguish and link named game terms from plain prose.

---

### Paragraph 3
> "If a word or a phrase is italicized, it is describing a spell or a magic item. This way, when you see the statement 'the door is sealed by *lock*,' you know that in this case the word denotes the lock spell, rather than a physical item."

Requirements identified:
- Spells and magic items are a distinct entity type from plain-text descriptions and physical mundane objects.
- The data model must differentiate between a spell reference, an item reference, and free narrative text.

---

### Paragraph 4
> "Pathfinder also uses many terms that are typically expressed as abbreviations, like AC for Armor Class, DC for Difficulty Class, and HP for Hit Points. If you're ever confused about a game term or an abbreviation, you can always turn to the Glossary and Index, beginning on page 628, and look it up."

Requirements identified:
- Core abbreviations that must be first-class fields: AC (Armor Class), DC (Difficulty Class), HP (Hit Points).
- System must include a glossary/lookup mechanism for game terms and abbreviations.

---

---

## SECTION: Understanding Actions

### Paragraph 1
> "Characters and their adversaries affect the world of Pathfinder by using actions and producing effects. This is especially the case during encounters, when every action counts. When you use an action, you generate an effect. This effect might be automatic, but sometimes actions necessitate that you roll a die, and the effect is based on what you rolled."

Requirements identified:
- Actions must produce effects; effects are either automatic or dice-roll-dependent.
- The action system must be available to both player characters and adversaries (NPCs/monsters).
- The system must support encounter mode (turn-based) as a distinct play context where actions are counted.

---

### Paragraph 2
> "Throughout this book, you will see special icons to denote actions."

Requirements identified:
- Action type must be a visual/symbolic field on every rules element: [one-action], [two-actions], [three-actions], [reaction], [free-action].

---

### Subsection: Single Actions
> "Single actions use this symbol: [one-action]. They're the simplest, most common type of action. You can use three single actions on your turn in an encounter, in any order you see fit."

Requirements identified:
- Each combatant has a per-turn action budget of exactly 3 single actions.
- Actions within a turn may be spent in any order chosen by the acting player/GM.

---

### Subsection: Reactions
> "Reactions use this symbol: [reaction]. These actions can be used even when it's not your turn. You get only one reaction per encounter round, and you can use it only when its specific trigger is fulfilled. Often, the trigger is another creature's action."

Requirements identified:
- Each combatant has exactly 1 reaction per round (not per turn; resets at start of each round).
- Reactions may fire on any combatant's turn, not only the owner's.
- Every reaction must define a trigger condition; it may only be used when that trigger fires.
- Trigger is most commonly defined as another creature taking a specific action.

---

### Subsection: Free Actions
> "Free actions use this symbol: [free-action]. Free actions don't require you to spend any of your three single actions or your reaction. A free action might have a trigger like a reaction does. If so, you can use it just like a reaction—even if it's not your turn. However, you can use only one free action per trigger, so if you have multiple free actions with the same trigger, you have to decide which to use. If a free action doesn't have a trigger, you use it like a single action, just without spending any of your actions for the turn."

Requirements identified:
- Free actions do not consume the 3-action budget or the 1 reaction slot.
- Free actions with a trigger: may fire off-turn like reactions; only 1 free action per trigger instance allowed even if multiple qualify.
- Free actions without a trigger: used on-turn, no action cost.
- System must enforce the "only one free action per trigger" constraint when multiple free actions share a trigger.

---

### Subsection: Activities — Paragraph 1
> "Activities are special tasks that you complete by spending one or more of your actions together. Usually, an activity uses two or more actions and lets you do more than a single action would allow. You have to spend all the actions an activity requires for its effects to happen. Spellcasting is one of the most common activities, as most spells take more than a single action to cast."

Requirements identified:
- Activities are multi-action composite tasks; all required actions must be spent for the effect to resolve.
- If all required actions cannot be spent, the activity does not trigger/resolve.
- Spellcasting is an Activity type consuming 1–3 actions depending on the spell.

---

### Subsection: Activities — Paragraph 2
> "Activities that use two actions use this symbol: [two-actions]. Activities that use three actions use this symbol: [three-actions]. A few special activities, such as spells you can cast in an instant, can be performed by spending a free action or a reaction."

Requirements identified:
- Activity action-cost types: 2 actions, 3 actions, free action, or reaction.
- Some spells/activities can resolve as a free action or reaction (instantaneous cast).

---

### Subsection: Activities — Paragraph 3
> "All tasks that take longer than a turn are activities. If an activity is meant to be done during exploration, it has the exploration trait. An activity that takes a day or more of commitment and that can be done only during downtime has the downtime trait."

Requirements identified:
- Multi-turn tasks are still classified as activities.
- Activities must carry a `trait` field. Required trait values: `exploration` (multi-turn, out-of-combat) and `downtime` (≥1 day, downtime mode only).
- System must model three distinct play modes: encounter, exploration, and downtime.


---

## SECTION: Reading Rules

### Paragraph 1
> "This book contains hundreds of rules elements that give characters new and interesting ways to respond to situations in the game. All characters can use the basic actions found in Chapter 9, but an individual character often has special rules that allow them to do things most other characters can't. Most of these options are feats, which are gained by making certain choices at character creation or when a character advances in level."

Requirements identified:
- There is a universal set of basic actions available to all characters regardless of class/ancestry.
- Characters have individual special rules layered on top of universal actions; primary vehicle is feats.
- Feats are gained at character creation and on level advancement.

---

### Paragraph 2
> "Regardless of the game mechanic they convey, rules elements are always presented in the form of a stat block, a summary of the rules necessary to bring the monster, character, item, or other rules element to life during play."

Requirements identified:
- All rules elements (feats, actions, monsters, characters, items) must have a structured stat block data model.

---

### Paragraph 3
> "Where appropriate, stat blocks are introduced with an explanation of their format. For example, the Ancestry section of Chapter 2 contains rules for each of the game's six core ancestries, and an explanation of these rules appears at the beginning of that chapter."

Requirements identified: None. (Describes book organization, not game mechanics.)

---

### Paragraph 4
> "The general format for stat blocks is shown below. Entries are omitted from a stat block when they don't apply, so not all rule elements have all of the entries given below. Actions, reactions, and free actions each have the corresponding icon next to their name to indicate their type. An activity that can be completed in a single turn has a symbol indicating how many actions are needed to complete it; activities that take longer to perform omit these icons. If a character must attain a certain level before accessing an ability, that level is indicated to the right of the stat block's name. Rules also often have traits associated with them (traits appear in the Glossary and Index)."

Requirements identified:
- Stat block fields are optional (omit when not applicable); no field is universally mandatory except Name.
- Every stat block has an action-type icon field indicating its type (single, two-action, three-action, reaction, free-action, or none for multi-turn activities).
- Level prerequisite is a stat block field; character must meet it to access the ability (enforced at runtime).
- Traits are a tag system on rules elements (many-to-many).

---

### Paragraph 5
> "Spells, alchemical items, and magic items use a similar format, but their stat blocks contain a number of unique elements (see Chapter 7 for more on reading spells, and Chapter 11 for more on alchemical and magic items)."

Requirements identified:
- Spells, alchemical items, and magic items share the base stat block structure but extend it with additional fields specific to their type.

---

### Stat Block Field: Prerequisites
> "Any minimum ability scores, feats, proficiency ranks, or other prerequisites you must have before you can access this rule element are listed here. Feats also have a level prerequisite, which appears above."

Requirements identified:
- Prerequisites field must support: minimum ability score values, feat ownership checks, proficiency rank minimums, level minimums, and arbitrary other conditions.
- Prerequisites must be evaluated at runtime before granting access to a rules element.

---

### Stat Block Field: Frequency
> "This is the limit on how many times you can use the ability within a given time."

Requirements identified:
- Frequency field must support a uses-per-time-period model (e.g., once per round, once per day, once per encounter).
- System must track usage count and reset on the appropriate time boundary.

---

### Stat Block Field: Trigger
> "Reactions and some free actions have triggers that must be met before they can be used."

Requirements identified:
- Trigger is a defined event or condition; the engine must detect it and evaluate whether the reaction/free-action may fire.
- Trigger is required on all reactions and optional on free actions.

---

### Stat Block Field: Requirements
> "Sometimes you must have a certain item or be in a certain circumstance to use an ability. If so, it's listed in this section."

Requirements identified:
- Requirements field checks item possession state or active circumstance flags at the time of use.
- If requirements are not met, the ability cannot be used.

---

### Stat Block Field: Effect (body text)
> "This section describes the effects or benefits of a rule element. If the rule is an action, it explains what the effect is or what you must roll to determine the effect. If it's a feat that modifies an existing action or grants a constant effect, the benefit is explained here."

Requirements identified:
- Every stat block must have an effect/benefit description body.
- Effect may be: deterministic (automatic), roll-dependent (requires a die roll), or passive/constant (modifies another rule element).

---

### Stat Block Field: Special
> "Any special qualities of the rule are explained in this section. Usually this section appears in feats you can select more than once, explaining what happens when you do."

Requirements identified:
- Special field is optional; used primarily on feats that can be taken multiple times.
- System must support stacking/escalating behavior on feats taken multiple times, using the Special field to define the stacking rules.


---

## SECTION: Character Creation

### Paragraph 1
> "Unless you're the GM, the first thing you need to do when playing Pathfinder is create your character. It's up to you to imagine your character's past experiences, personality, and worldview, and this will set the stage for your roleplaying during the game. You'll use the game's mechanics to determine your character's ability to perform various tasks and use special abilities during the game."

Requirements identified: None. (Flavor/orientation text describing purpose of character creation.)

---

### Paragraph 2
> "This section provides a step-by-step guide for creating a character using the Pathfinder rules, preceded by a guide to help you understand ability scores. These scores are a critical part of your character, and you will be asked to make choices about them during many of the following steps. The steps of character creation are presented in a suggested order, but you can complete them in whatever order you prefer."

Requirements identified:
- Character creation is a multi-step process with a suggested (but not mandatory) ordering.
- The system must allow non-linear step completion; no step is strictly gated by completing a prior step.

---

### Paragraph 3
> "Many of the steps on pages 21–28 instruct you to fill out fields on your character sheet. The character sheet is shown on pages 24–25; you can find a copy in the back of this book or online as a free pdf. The character sheet is designed to be easy to use when you're actually playing the game—but creating a character happens in a different order, so you'll move back and forth through the character sheet as you go through the character creation process. Additionally, the character sheet includes every field you might need, even though not all characters will have something to put in each field. If a field on your character sheet is not applicable to your character, just leave that field blank."

Requirements identified:
- Character data model must accommodate all character types without requiring every field to be populated.
- Inapplicable fields must be allowed to remain empty/null without error.

---

### Paragraph 4
> "All the steps of character creation are detailed on the following pages; each is marked with a number that corresponds to the sample character sheet on pages 24–25, showing you where the information goes. If the field you need to fill out is on the third or fourth page of the character sheet, which aren't shown, the text will tell you."

Requirements identified: None. (Describes book layout cross-referencing.)

---

### Paragraph 5
> "If you're creating a higher-level character, it's a good idea to begin with the instructions here, then turn to page 29 for instructions on leveling up characters."

Requirements identified:
- Character creation must support starting at levels above 1st (e.g., joining a higher-level campaign).
- Higher-level creation follows the standard creation process then applies level-up steps iteratively.


---

## SECTION: The Six Ability Scores

### Paragraph 1
> "One of the most important aspects of your character is their ability scores. These scores represent your character's raw potential and influence nearly every other statistic on your character sheet. Determining your ability scores is not done all at once, but instead happens over several steps during character creation."

Requirements identified:
- Ability scores are assembled incrementally across multiple character-creation steps, not entered as a single input.
- Ability scores influence nearly every other statistic; they are a foundational data dependency.

---

### Paragraph 2
> "Ability scores are split into two main groups: physical and mental. Strength, Dexterity, and Constitution are physical ability scores, measuring your character's physical power, agility, and stamina. In contrast, Intelligence, Wisdom, and Charisma are mental ability scores and measure your character's learned prowess, awareness, and force of personality."

Requirements identified:
- Six ability scores must be modeled as first-class fields: STR (Strength), DEX (Dexterity), CON (Constitution), INT (Intelligence), WIS (Wisdom), CHA (Charisma).
- Scores are grouped: Physical (STR, DEX, CON) and Mental (INT, WIS, CHA). Grouping should be representable in the data model.

---

### Paragraph 3
> "Excellence in an ability score improves the checks and statistics related to that ability, as described below. When imagining your character, you should also decide what ability scores you want to focus on to give you the best chance at success."

Requirements identified: None. (Guidance/flavor directing player to read the individual score entries below.)

---

### Subsection: Strength
> "Strength measures your character's physical power. Strength is important if your character plans to engage in hand-to-hand combat. Your Strength modifier gets added to melee damage rolls and determines how much your character can carry."

Requirements identified:
- STR modifier is added to melee damage rolls.
- STR score/modifier determines carry capacity (Bulk limits).

---

### Subsection: Dexterity
> "Dexterity measures your character's agility, balance, and reflexes. Dexterity is important if your character plans to make attacks with ranged weapons or use stealth to surprise foes. Your Dexterity modifier is also added to your character's AC and Reflex saving throws."

Requirements identified:
- DEX modifier is added to AC (subject to armor's Dexterity modifier cap).
- DEX modifier is added to Reflex saving throws.
- DEX modifier is used for ranged attack rolls and Stealth checks.

---

### Subsection: Constitution
> "Constitution measures your character's overall health and stamina. Constitution is an important statistic for all characters, especially those who fight in close combat. Your Constitution modifier is added to your Hit Points and Fortitude saving throws."

Requirements identified:
- CON modifier is added to maximum Hit Points (once per level, applied to every level the character has).
- CON modifier is added to Fortitude saving throws.

---

### Subsection: Intelligence
> "Intelligence measures how well your character can learn and reason. A high Intelligence allows your character to analyze situations and understand patterns, and it means they can become trained in additional skills and might be able to master additional languages."

Requirements identified:
- INT modifier determines the number of additional trained skills granted (above the class's base skill count).
- INT modifier ≥ +1 grants additional language proficiencies.

---

### Subsection: Wisdom
> "Wisdom measures your character's common sense, awareness, and intuition. Your Wisdom modifier is added to your Perception and Will saving throws."

Requirements identified:
- WIS modifier is added to Perception checks.
- WIS modifier is added to Will saving throws.

---

### Subsection: Charisma
> "Charisma measures your character's personal magnetism and strength of personality. A high Charisma score helps you influence the thoughts and moods of others."

Requirements identified:
- CHA modifier is used for social influence skills (Diplomacy, Deception, Intimidation, Performance at minimum).


---

## SECTION: Ability Score Overview (Sidebar)

### Paragraph 1
> "Each ability score starts at 10, representing human average, but as you make character choices, you'll adjust these scores by applying ability boosts, which increase a score, and ability flaws, which decrease a score. As you build your character, remember to apply ability score adjustments when making the following decisions."

Requirements identified:
- All six ability scores initialize to 10 before any boosts or flaws are applied.
- Ability boosts increase a score; ability flaws decrease a score.

---

### Entry: Ancestry
> "Each ancestry provides ability boosts, and sometimes an ability flaw. If you are taking any voluntary flaws, apply them in this step."

Requirements identified:
- Ancestry entity must define: one or more ability boosts (fixed and/or free) and optionally one ability flaw.
- Voluntary flaws are player-elected at character creation and applied alongside ancestry flaws.

---

### Entry: Background
> "Your character's background provides two ability boosts."

Requirements identified:
- Background entity provides exactly 2 ability boosts (one typed choice-of-two, one free — per Step 4 detail below).

---

### Entry: Class
> "Your character's class provides an ability boost to the ability score most important to your class, called your key ability score."

Requirements identified:
- Class entity must define a key ability score.
- Character receives one ability boost to the class's key ability score at creation.

---

### Entry: Determine Scores
> "After the other steps, you apply four more ability boosts of your choice. Then, determine your ability modifiers based on those scores."

Requirements identified:
- At the finalization step, the character gains 4 additional free ability boosts (each applied to a different score).
- After all boosts are applied, ability modifiers are derived from final scores using Table 1–1.

---

## SECTION: Ability Boosts

### Paragraph 1
> "An ability boost normally increases an ability score's value by 2. However, if the ability score to which you're applying an ability boost is already 18 or higher, its value increases by only 1. At 1st level, a character can never have any ability score that's higher than 18."

Requirements identified:
- Ability boost adds +2 to a score, or +1 if the score is already ≥18.
- Hard cap: no ability score may exceed 18 at 1st level during character creation.

---

### Paragraph 2
> "When your character receives an ability boost, the rules indicate whether it must be applied to a specific ability score or to one of two specific ability scores, or whether it is a 'free' ability boost that can be applied to any ability score of your choice. However, when you gain multiple ability boosts at the same time, you must apply each one to a different score."

Requirements identified:
- Ability boosts are typed: fixed (one specific score), choice-of-two (one of two named scores), or free (any score).
- Multiple simultaneous boosts must each target a distinct ability score (no two boosts in the same batch may go to the same score).

---

### Paragraph 3 (continued from wrap)
> "Dwarves, for example, receive an ability boost to their Constitution score and their Wisdom score, as well as one free ability boost, which can be applied to any score other than Constitution or Wisdom."

Requirements identified:
- When a free boost is granted alongside fixed boosts in the same batch, the free boost cannot be applied to a score already boosted in that batch.

---

## SECTION: Ability Flaws

### Paragraph 1
> "Ability flaws are not nearly as common in Pathfinder as ability boosts. If your character has an ability flaw—likely from their ancestry—you decrease that ability score by 2."

Requirements identified:
- Ability flaw subtracts 2 from the specified score.
- Flaws come primarily from ancestry; may also be taken voluntarily.

---

## SECTION: Ability Modifiers

### Paragraph 1
> "Once you've finalized your ability scores, you can use them to determine your ability modifiers, which are used in most other statistics in the game. Find the score in Table 1–1: Ability Modifiers to determine its ability modifier."

Requirements identified:
- Ability modifiers are derived values computed from final ability scores.
- Modifier formula: floor((score − 10) / 2). Must be recomputed whenever the underlying score changes.

---

### Table 1–1: Ability Modifiers

| Score Range | Modifier |
|---|---|
| 1 | — (theoretical floor) |
| 2–3 | −5 |
| 4–5 | −4 |
| 6–7 | −3 |
| 8–9 | −2 |
| 10–11 | +0 |
| 12–13 | +1 |
| 14–15 | +2 |
| 16–17 | +3 |
| 18–19 | +4 |
| 20–21 | +5 |
| 22–23 | +6 |
| 24–25 | +7 |
| (continues +1 per 2 points) | … |

Requirements identified:
- Table 1–1 must be implemented as a formula lookup. System must handle scores beyond 25 for high-level play.


---

## SECTION: Alternative Method — Rolling Ability Scores (Sidebar)

### Paragraph 1
> "The standard method of generating ability scores that's described above works great if you want to create a perfectly customized, balanced character. But your GM may decide to add a little randomness to character creation and let the dice decide what kind of character the players are going to play. In that case, you can use this alternative method to generate your ability scores. Be warned—the same randomness that makes this system fun also allows it to sometimes create characters that are significantly more (or less) powerful than the standard ability score system and other Pathfinder rules assume."

Requirements identified:
- An optional dice-roll character creation mode must be supported as an alternative to the standard point-buy/boost method.
- This mode is GM-enabled; the system should support a GM toggle for this option.

---

### Paragraph 2
> "If your GM opts for rolling ability scores, follow these alternative steps, ignoring all other instructions and guidelines about applying ability boosts and ability flaws throughout the character generation process."

Requirements identified:
- When rolling mode is active, the standard boost/flaw pipeline is bypassed and replaced entirely by the rolling steps below.

---

### Rolling Step 1: Roll and Assign Scores
> "Roll four 6-sided dice (4d6) and discard the lowest die result. Add the three remaining results together and record the sum. Repeat this process until you've generated six such values. Decide which value you want for each of your ability scores."

Requirements identified:
- Dice mechanic: roll 4d6, discard lowest, sum remaining 3. Repeat ×6.
- Player assigns the six generated values to the six ability scores freely.

---

### Rolling Step 2: Assign Ability Boosts and Flaws
> "Apply the ability boosts your character gains from their ancestry, but your character gets one fewer free ability boost than normal. If your character's ancestry has any ability flaws, apply those next. Finally, apply one ability boost to one of the ability scores specified in the character's background (you do not get the other free ability boost). These ability boosts cannot raise a score above 18."

Requirements identified:
- In rolling mode: ancestry provides fixed boosts and flaws normally, but one fewer free boost.
- Background provides only 1 of its 2 ability boosts (the typed choice, not the free one).
- Boosts still capped at 18; excess boost may redirect to another score or be applied to a 17 (capping at 18, losing the excess).

---

### Rolling Step 3: Record Scores and Modifiers
> "Record the final scores and assign the ability modifiers according to Table 1–1. When your character receives additional ability boosts at higher levels, you assign them as any character would."

Requirements identified:
- After rolling-mode generation, modifiers are derived identically to standard mode (Table 1–1).
- Level-up ability boosts in rolling mode work identically to standard mode.

---

## SECTION: Ancestries and Classes (Sidebar)

### Paragraph 1
> "Each player takes a different approach to creating a character. Some want a character who will fit well into the story, while others look for a combination of abilities that complement each other mechanically. You might combine these two approaches. There is no wrong way!"

Requirements identified: None. (Player guidance, not game mechanics.)

---

### Paragraph 2
> "When you turn the page, you'll see a graphical representation of ancestries and classes that provide at-a-glance information for players looking to make the most of their starting ability scores. In the ancestries overview on page 22, each entry lists which ability scores it boosts, and also indicates any ability flaws the ancestry might have."

Requirements identified:
- Ancestry entries must expose ability boosts and ability flaws in a machine-readable format (for display and computation).

---

### Paragraph 3
> "The summaries of the classes on pages 22–23 list each class's key ability score—the ability score used to calculate the potency of many of their class abilities. Characters receive an ability boost in that ability score when you choose their class. This summary also lists one or more secondary ability scores important to members of that class."

Requirements identified:
- Class data must include: key ability score (primary), and a list of secondary ability scores (advisory, not mechanically enforced but recommended for optimization).

---

### Paragraph 4
> "Keep in mind a character's background also affects their ability scores, though there's more flexibility in the ability boosts from backgrounds than in those from classes. For descriptions of the available backgrounds, see pages 60–64."

Requirements identified: None. (Cross-reference to Chapter 2; no new mechanics introduced.)


---

## SECTION: Step 1 — Create a Concept

### Paragraph 1
> "What sort of hero do you want to play? The answer to this question might be as simple as 'a brave warrior,' or as complicated as 'the child of elven wanderers, but raised in a city dominated by humans and devoted to Sarenrae, goddess of the sun.' Consider your character's personality, sketch out a few details about their past, and think about how and why they adventure."

Requirements identified: None. (Roleplay/narrative guidance.)

---

### Paragraph 2
> "You'll want to peruse Pathfinder's available ancestries, backgrounds, and classes. The summaries on pages 22–23 might help you match your concept with some of these basic rule elements. Before a game begins, it's also a good idea for the players to discuss how their characters might know each other and how they'll work together throughout the course of their adventures."

Requirements identified:
- Character creation UI should present ancestries, backgrounds, and classes as browsable/filterable options early in the process.

---

### Paragraph 3
> "There are many ways to approach your character concept. Once you have a good idea of the character you'd like to play, move on to Step 2 to start building your character."

Requirements identified: None. (Navigation guidance.)

---

### Subsection: Ancestry, Background, Class, or Details — Paragraph 1
> "If one of Pathfinder's character ancestries, backgrounds, or classes particularly intrigues you, it's easy to build a character concept around these options. The summaries of ancestries and classes on pages 22–23 give a brief overview of these options (full details appear in Chapters 2 and 3, respectively). Each ancestry also has several heritages that might refine your concept further, such as a human with an elf or orc parent, or an arctic or woodland elf. Additionally, the game has many backgrounds to choose from, representing your character's upbringing, their family's livelihood, or their earliest profession."

Requirements identified:
- Each ancestry must have sub-selections called heritages that further modify it.

---

### Subsection: Ancestry, Background, Class, or Details — Paragraph 2
> "Building a character around a specific ancestry, background, or class can be a fun way to interact with the world's lore. Would you like to build a typical member of your character's ancestry or class, as described in the relevant entry, or would you prefer to play a character who defies commonly held notions about their people?"

Requirements identified: None. (Roleplay guidance.)

---

### Subsection: Ancestry, Background, Class, or Details — Paragraph 3
> "You can draw your concept from any aspect of a character's details. You can use roleplaying to challenge not only the norms of Pathfinder's fictional world, but even real-life societal norms. Your character might challenge gender notions, explore cultural identity, have a disability, or any combination of these suggestions. Your character can live any life you see fit."

Requirements identified:
- Character data model must support freeform personal details (gender, pronouns, background notes) without mechanical constraints.

---

### Subsection: Faith — Paragraph 1
> "Perhaps you'd like to play a character who is a devout follower of a specific deity. Pathfinder is a rich world with myriad faiths and philosophies spanning a wide range... Your character might be so drawn to a particular faith that you decide they should be a champion or cleric of that deity; they might instead be a lay worshipper who applies their faith's teachings to daily life, or simply the child of devout parents."

Requirements identified:
- Deity is a character attribute. Some classes (Champion, Cleric) have mandatory deity selection; others may worship optionally.
- Deity selection must be enforced as required for Champion and Cleric classes.

---

### Subsection: Your Allies — Paragraph 1
> "You might want to coordinate with other players when forming your character concept. Your characters could have something in common already; perhaps they are relatives, or travelers from the same village. You might discuss mechanical aspects with the other players, creating characters whose combat abilities complement each other. In the latter case, it can be helpful for a party to include characters who deal damage, characters who can absorb damage, and characters who can provide healing."

Requirements identified:
- Party/group model must support multiple characters playing together with complementary roles.
- Recognized archetypes: damage dealer, damage absorber (tank), healer. (Advisory categorization; not mechanically enforced.)

---

### Subsection: Your Allies — Paragraph 2
> "However, Pathfinder's classes include a lot of choices, and there are many options for building each type of character, so don't let these broad categories restrict your decisions."

Requirements identified: None. (Advisory guidance.)

---

### Character Sheet Instructions (Step 1)
> "Once you've developed your character's concept, jot down a few sentences summarizing your ideas under the Notes section on the third page of your character sheet. Record any of the details you've already decided, such as your character's name, on the appropriate lines on the first page."

Requirements identified:
- Character sheet must include a free-text Notes field.
- Character name is a required field on the character sheet.


---

## SECTION: Step 2 — Start Building Ability Scores

### Paragraph 1
> "At this point, you need to start building your character's ability scores. See the overview of ability scores on pages 19–20 for more information about these important aspects of your character and an overview of the process."

Requirements identified: None. (Cross-reference.)

---

### Paragraph 2
> "Your character's ability scores each start at 10, and as you select your ancestry, background, and class, you'll apply ability boosts, which increase a score by 2, and ability flaws, which decrease a score by 2. At this point, just note a 10 in each ability score and familiarize yourself with the rules for ability boosts and flaws on page 20. This is also a good time to identify which ability scores will be most important to your character."

Requirements identified:
- All six ability scores initialize to 10 at the start of character creation.
- Ability boosts: +2 per boost (or +1 if score ≥18). Ability flaws: −2 per flaw.
- At this step no boosts or flaws are applied yet; scores are simply initialized.

---

## SECTION: Step 3 — Select an Ancestry

### Paragraph 1
> "Select an ancestry for your character. The ancestry summaries on page 22 provide an overview of Pathfinder's core ancestry options, and each is fully detailed in Chapter 2."

Requirements identified: None. (Navigation guidance.)

---

### Paragraph 2
> "Ancestry determines your character's size, Speed, and languages, and contributes to their Hit Points. Each also grants ability boosts and ability flaws to represent the ancestry's basic capabilities."

Requirements identified:
- Ancestry entity must provide: Size, Speed (movement rate), starting Languages (list), Hit Points (flat bonus at 1st level), Ability Boosts (fixed and/or free), Ability Flaw (0 or 1).

---

### Paragraph 3 — Four Decisions
> "You'll make four decisions when you select your character's ancestry:
> • Pick the ancestry itself.
> • Assign any free ability boosts and decide if you are taking any voluntary flaws.
> • Select a heritage from those available within that ancestry, further defining the traits your character was born with.
> • Choose an ancestry feat, representing an ability your hero learned at an early age."

Requirements identified:
- Ancestry selection is itself a four-part sub-process: (1) ancestry choice, (2) free boost assignment + optional voluntary flaws, (3) heritage selection, (4) ancestry feat selection.
- Each ancestry must have a list of available heritages (at least one).
- Each ancestry must have a list of available ancestry feats; player selects one at 1st level.
- Voluntary flaws: player may elect to take additional ability flaws beyond the ancestry's default (purely optional, roleplaying choice). Maximum one voluntary flaw per ability score.

---

### Character Sheet Instructions (Step 3)
> "Write your character's ancestry and heritage in the appropriate space at the top of your character sheet's first page. Adjust your ability scores, adding 2 to an ability score if you gained an ability boost from your ancestry, and subtracting 2 from an ability score if you gained an ability flaw from your ancestry. Note the number of Hit Points your character gains from their ancestry—you'll add more to this number later. Finally, in the appropriate spaces, record your character's size, Speed, and languages. If your character's ancestry provides them with special abilities, write them in the appropriate spaces, such as darkvision in the Senses section on the first page and innate spells on the fourth page. Write the ancestry feat you selected in the Ancestry Feat section on your character sheet's second page."

Requirements identified:
- Character sheet fields populated in Step 3: Ancestry name, Heritage name, Ability Score adjustments from ancestry, Ancestry HP, Size, Speed, Languages, Special senses (e.g., darkvision), Innate spells (if any), Ancestry Feat.

---

### Sidebar: Alternate Ancestry Boosts
> "The ability boosts and flaws listed in each ancestry represent general trends or help guide players to create the kinds of characters from that ancestry most likely to pursue the life of an adventurer. However, ancestries aren't a monolith. You always have the option to replace your ancestry's listed ability boosts and ability flaws entirely and instead select two free ability boosts when creating your character."

Requirements identified:
- Alternate ancestry boost rule: player may replace all fixed ancestry boosts and flaws with 2 free ability boosts (no flaw applied in this case).
- This is a player-elected option at character creation; must be supported as an alternative to the default ancestry boost/flaw array.

---

### Sidebar: Optional Voluntary Flaws
> "Sometimes, it's fun to play a character with a major flaw regardless of your ancestry. You can elect to take additional ability flaws when applying the ability boosts and ability flaws from your ancestry. This is purely for roleplaying a highly flawed character, and you should consult with the rest of your group if you plan to do this! You can't apply more than one flaw to any single ability score."

Requirements identified:
- Voluntary flaws are player-elected additional −2 penalties to chosen ability scores at creation.
- Constraint: no more than one flaw (including any ancestry flaw) per individual ability score.


---

## SECTION: Step 4 — Pick a Background

### Paragraph 1
> "Your character's background might represent their upbringing, an aptitude they've been honing since their youth, or another aspect of their life before they became an adventurer. Character backgrounds appear in Chapter 2, starting on page 60. They typically provide two ability boosts (one that can be applied to either of two specific ability scores, and one that is free), training in a specific skill, training in a Lore skill, and a specific skill feat."

Requirements identified:
- Background entity must provide:
  - Ability Boost #1: choice-of-two (player picks one of two named scores)
  - Ability Boost #2: free (any score)
  - Skill Training: training in one specific named skill
  - Lore Skill Training: training in a Lore skill (a sub-type of skill with a topic modifier)
  - Skill Feat: one specific skill feat granted at 1st level

---

### Character Sheet Instructions (Step 4)
> "Record your character's background in the space at the top of the first page of your character sheet. Adjust your ability scores, adding 2 to an ability score if you gained an ability boost from your background. Record the skill feat the background provides in the Skill Feat section of your character sheet's second page. On the first page, check the 'T' box next to the name of the specific skill and for one Lore skill to indicate your character is trained, then write the name of the Lore skill granted by your background."

Requirements identified:
- Character sheet fields populated in Step 4: Background name, Ability Score adjustments from background, Skill Feat from background, Skill proficiency marks (Trained) for the background skill and Lore skill, Lore skill name.

---

## SECTION: Step 5 — Choose a Class

### Paragraph 1
> "At this point, you need to decide your character's class. A class gives your character access to a suite of heroic abilities, determines how effectively they fight, and governs how easily they can shake off or avoid certain harmful effects. Each class is fully detailed in Chapter 3, but the summaries on pages 22–23 provide an overview of each and tells you which ability scores are important when playing that class."

Requirements identified:
- Class entity must define: heroic abilities suite, combat effectiveness (weapon/armor proficiencies), and saving throw proficiency ranks.

---

### Paragraph 2
> "You don't need to write down all of your character's class features yet. You simply need to know which class you want to play, which determines the ability scores that will be most important for your character."

Requirements identified: None. (Step sequencing guidance; class details recorded in Step 7.)

---

### Character Sheet Instructions (Step 5)
> "Write your character's class in the space at the top of the first page of your character sheet, then write '1' in the Level box to indicate that your character is 1st level. Next to the ability scores, note the class's key ability score, and add 2 to that ability score from the ability boost the class provides. Don't worry about recording the rest of your character's class features and abilities yet—you'll handle that in Step 7."

Requirements identified:
- Character sheet fields populated in Step 5: Class name, Level (set to 1), Key Ability Score boost applied (+2 to key score).

---

## SECTION: Step 6 — Determine Ability Scores

### Paragraph 1
> "Now that you've made the main mechanical choices about your character, it's time to finalize their ability scores. Do these three things:
> • First, make sure you've applied all the ability boosts and ability flaws you've noted in previous steps (from your ancestry, background, and class).
> • Then, apply four more ability boosts to your character's ability scores, choosing a different ability score for each and increasing that ability score by 2.
> • Finally, record your starting ability scores and ability modifiers, as determined using Table 1–1: Ability Modifiers."

Requirements identified:
- Step 6 is a finalization step: verify all prior boosts/flaws have been applied, then add 4 additional free boosts (each to a different score), then compute modifiers.
- 4 additional free ability boosts at 1st level, each targeting a distinct score (no two may go to the same score in this batch).

---

### Paragraph 2
> "Remember that each ability boost adds 2 to the base score of 10, and each ability flaw subtracts 2. You should have no ability score lower than 8 or higher than 18."

Requirements identified:
- Post-finalization validation: all six scores must be in the range [8, 18].
- If any score falls outside this range, the character build is invalid.

---

### Character Sheet Instructions (Step 6)
> "Write your character's starting ability scores in the box provided for each. Record the ability modifier for each ability score in the box to the left of the ability's name."

Requirements identified:
- Character sheet fields populated in Step 6: all six ability score values and their derived modifiers.


---

## SECTION: Step 7 — Record Class Details

### Paragraph 1
> "Now, record all the benefits and class features that your character receives from the class you've chosen. While you've already noted your key ability score, you'll want to be sure to record the following class features."

Requirements identified: None. (Transition/orientation text.)

---

### Bullet: Hit Points
> "To determine your character's total starting Hit Points, add together the number of Hit Points your character gains from their ancestry (chosen in Step 2) and the number of Hit Points they gain from their class."

Requirements identified:
- Total starting HP = Ancestry HP + Class HP per level (Class HP per level typically already includes CON modifier × level).
- Specifically: Class HP per level = class base HP value + CON modifier. Multiply by character level (= 1 at creation).

---

### Bullet: Initial Proficiencies
> "The Initial Proficiencies section of your class entry indicates your character's starting proficiency ranks in a number of areas. Choose which skills your character is trained in and record those, along with the ones set by your class. If your class would make you trained in a skill you're already trained in (typically due to your background), you can select another skill to become trained in."

Requirements identified:
- Class defines initial proficiency ranks for: Perception, saving throws (Fort/Ref/Will), weapons, armor, skills (some fixed, some chosen).
- If class skill training overlaps with background skill training, player substitutes a different skill.

---

### Bullet: Class Features at 1st Level
> "See the class advancement table in your class entry to learn the class features your character gains at 1st level—but remember, you already chose an ancestry and background. Some class features require you to make additional choices, such as selecting spells."

Requirements identified:
- Each class must have an advancement table mapping level → class features gained.
- Some class features require sub-selections at time of gain (e.g., spell selection for spellcasters, order selection for druids).

---

### Character Sheet Instructions (Step 7)
> "Write your character's total Hit Points on the first page of your character sheet. Use the proficiency fields (the boxes marked 'T,' 'E,' 'M,' and 'L') on your character sheet to record your character's initial proficiencies in Perception, saving throws, and the skills granted by their class; mark 'T' if your character is trained, or 'E' if your character is expert. Indicate which additional skills you chose for your character to be trained in by marking the 'T' proficiency box for each skill you selected. Likewise, record your character's armor proficiencies in the Armor Class section at the top of the first page and their weapon proficiencies at the bottom of the first page. Record all other class feats and abilities on the second page. Don't worry yet about finalizing any values for your character's statistics—you'll handle that in Step 9."

Requirements identified:
- Proficiency rank field values on character sheet: T (Trained), E (Expert), M (Master), L (Legendary).
- Character sheet fields populated in Step 7: Total HP, Perception proficiency, Saving throw proficiencies (Fort/Ref/Will), Skill proficiency marks, Armor proficiency marks, Weapon proficiency marks, Class feats and abilities list.

---

### Sidebar: Spells and Spellcasting
> "Most classes can learn to cast a few focus spells, but the bard, cleric, druid, sorcerer, and wizard all gain spellcasting—the ability to cast a wide variety of spells. If your character's class grants spells, you should take time during Step 7 to learn about the spells they know and how to cast them. The fourth page of the character sheet provides space to note your character's magic tradition and their proficiency rank for spell attack rolls and spell DCs. It also gives ample space to record the spells in your character's repertoire or spellbook, or that you prepare frequently. Each class determines which spells a character can cast, how they are cast, and how many they can cast in a day, but the spells themselves and detailed rules for spellcasting are located in Chapter 7."

Requirements identified:
- Full spellcasting classes (at minimum): Bard, Cleric, Druid, Sorcerer, Wizard.
- Spellcasting attributes per class: magic tradition, spell attack roll proficiency, spell DC proficiency, spell slots per day (by spell level), spells known/prepared.
- Distinction between focus spells (limited pool available to most classes) and full spellcasting (broad repertoire/prepared list).
- Character sheet fields: magic tradition, spell attack roll, spell DC, spell list/spellbook/repertoire.


---

## SECTION: Step 8 — Buy Equipment

### Paragraph 1
> "At 1st level, your character has 15 gold pieces (150 silver pieces) to spend on armor, weapons, and other basic equipment. Your character's class lists the types of weapons and armor with which they are trained (or better!). Their weapons determine how much damage they deal in combat, and their armor influences their Armor Class; these calculations are covered in more detail in Step 10. Don't forget essentials such as food and traveling gear! For more on the available equipment and how much it costs, see Chapter 6."

Requirements identified:
- Starting wealth at 1st level: 15 gp (= 150 sp = 1,500 cp).
- Currency system: cp (copper piece), sp (silver piece), gp (gold piece), pp (platinum piece).
- Equipment selection is constrained by class weapon/armor proficiency ranks.
- Equipment purchased affects: damage output (weapons) and Armor Class (armor).
- Equipment categories include at minimum: weapons, armor, food/rations, and traveling gear.

---

### Character Sheet Instructions (Step 8)
> "Once you've spent your character's starting wealth, calculate any remaining gp, sp, and cp they might still have and write those amounts in Inventory on the second page. Record your character's weapons in the Melee Strikes and Ranged Strikes sections of the first page, depending on the weapon, and the rest of their equipment in the Inventory section on your character sheet's second page. You'll calculate specific numbers for melee Strikes and ranged Strikes with the weapons in Step 9 and for AC when wearing that armor in Step 10."

Requirements identified:
- Character sheet fields populated in Step 8: Remaining currency (gp/sp/cp), Melee Strikes weapon entries, Ranged Strikes weapon entries, Inventory list.
- Strike numbers (attack modifier, damage) are not computed until Step 9; armor AC not computed until Step 10.

---

## SECTION: Step 9 — Calculate Modifiers

### Paragraph 1
> "With most of the big decisions for your character made, it's time to calculate the modifiers for each of the following statistics. If your proficiency rank for a statistic is trained, expert, master, and legendary, your bonus equals your character's level plus another number based on the rank (2, 4, 6, and 8, respectively). If your character is untrained, your proficiency bonus is +0."

Requirements identified:
- Proficiency bonus formula:
  - Untrained: +0
  - Trained: Level + 2
  - Expert: Level + 4
  - Master: Level + 6
  - Legendary: Level + 8
- All derived statistics recalculate from this formula on every level gain.

---

### Subsection: Perception
> "Your character's Perception modifier measures how alert they are. This modifier is equal to their proficiency bonus in Perception plus their Wisdom modifier."

Requirements identified:
- Perception modifier = Perception proficiency bonus + WIS modifier.

---

### Subsection: Saving Throws
> "For each kind of saving throw, add your character's Fortitude, Reflex, or Will proficiency bonus (as appropriate) plus the ability modifier associated with that kind of saving throw. For Fortitude saving throws, use your character's Constitution modifier. For Reflex saving throws, use your character's Dexterity modifier. For Will saving throws, use your character's Wisdom modifier. Then add in any bonuses or penalties from abilities, feats, or items that always apply."

Requirements identified:
- Fortitude save modifier = Fort proficiency bonus + CON modifier + permanent bonuses/penalties.
- Reflex save modifier = Reflex proficiency bonus + DEX modifier + permanent bonuses/penalties.
- Will save modifier = Will proficiency bonus + WIS modifier + permanent bonuses/penalties.
- Situational modifiers (apply only in certain situations) must be noted separately, not baked into the base total.

---

### Subsection: Melee Strikes and Ranged Strikes
> "The modifier for a Strike is equal to your character's proficiency bonus with the weapon plus an ability modifier (usually Strength for melee Strikes and Dexterity for ranged Strikes). You also add any item bonus from the weapon and any other permanent bonuses or penalties. Melee weapons usually add your character's Strength modifier to damage rolls, while ranged weapons might add some or all of your character's Strength modifier, depending on the weapon's traits."

Requirements identified:
- Melee attack modifier = weapon proficiency bonus + STR modifier + item bonus + other permanent bonuses/penalties.
- Ranged attack modifier = weapon proficiency bonus + DEX modifier + item bonus + other permanent bonuses/penalties.
- Melee damage = weapon damage dice + STR modifier.
- Ranged damage = weapon damage dice + (STR modifier as modified by weapon traits; may be 0, half, or full).
- Item bonus is a distinct bonus type sourced from the weapon's magical properties.

---

### Subsection: Skills
> "In the second box to the right of each skill name on your character sheet, there's an abbreviation that reminds you of the ability score tied to that skill. For each skill in which your character is trained, add your proficiency bonus for that skill (typically +3 for a 1st-level character) to the indicated ability's modifier, as well as any other applicable bonuses and penalties, to determine the total modifier for that skill. For skills your character is untrained in, use the same method, but your proficiency bonus is +0."

Requirements identified:
- Each skill is associated with a governing ability score.
- Skill modifier = skill proficiency bonus + governing ability modifier + bonuses/penalties.
- Untrained skill modifier = +0 (proficiency) + governing ability modifier.
- At 1st level, Trained proficiency bonus = 1 (level) + 2 = +3.

---

### Character Sheet Instructions (Step 9)
> "For Perception and saving throws, write your proficiency bonus and the appropriate ability modifier in the boxes provided, then record the total modifier in the large space. Record the proficiency bonuses, ability modifiers, and total modifiers for your melee Strikes and ranged Strikes in the box after the name of each weapon, and put the damage for each in the space below, along with the traits for that attack. For skills, record the relevant ability modifier and proficiency bonus in the appropriate box for each skill, and then write the total skill modifiers in the spaces to the left. If your character has any modifiers, bonuses, or penalties from feats or abilities that always apply, add them into the total modifiers. For ones that apply only in certain situations, note them next to the total modifiers."

Requirements identified:
- Character sheet fields populated in Step 9: Perception total, Saving throw totals (Fort/Ref/Will), Strike attack modifiers and damage expressions per weapon (plus traits), Skill totals for all skills.
- Always-on modifiers are baked into totals; situational modifiers are annotated separately.


---

## SECTION: Step 10 — Finishing Details

### Subsection: Alignment — Paragraph 1
> "Your character's alignment is an indicator of their morality and personality. There are nine possible alignments in Pathfinder, as shown on Table 1–2: The Nine Alignments. If your alignment has any components other than neutral, your character gains the traits of those alignment components. This might affect the way various spells, items, and creatures interact with your character."

Requirements identified:
- Character must have an alignment field with one of nine valid values (see Table 1–2 below).
- Non-neutral alignment components (Lawful, Chaotic, Good, Evil) are also character traits affecting spell/item/creature interactions.

---

### Table 1–2: The Nine Alignments

| | Good | Neutral | Evil |
|---|---|---|---|
| **Lawful** | Lawful Good (LG) | Lawful Neutral (LN) | Lawful Evil (LE) |
| **Neutral** | Neutral Good (NG) | True Neutral (N) | Neutral Evil (NE) |
| **Chaotic** | Chaotic Good (CG) | Chaotic Neutral (CN) | Chaotic Evil (CE) |

Requirements identified:
- Alignment is a two-axis value: Law/Chaos axis (Lawful, Neutral, Chaotic) × Good/Evil axis (Good, Neutral, Evil) = 9 combinations.
- Alignment is stored as a first-class field, not just a free-text note.

---

### Subsection: Alignment — Paragraph 2
> "Your character's alignment is measured by two pairs of opposed values: the axis of good and evil and the axis of law and chaos. A character who isn't committed strongly to either side is neutral on that axis."

Requirements identified: None. (Restates the two-axis structure already captured above.)

---

### Subsection: Alignment — Paragraph 3 (class restrictions)
> "Keep in mind that alignment is a complicated subject, and even acts that might be considered good can be used for nefarious purposes, and vice versa. The GM is the arbiter of questions about how specific actions might affect your character's alignment. If you play a champion, your character's alignment must be one allowed for their deity and cause (pages 437–440 and 106–107), and if you play a cleric, your character's alignment must be one allowed for their deity (pages 437–440)."

Requirements identified:
- Champion: alignment must be within the allowed set for their deity AND cause. (Constraint enforced at class selection and if alignment changes.)
- Cleric: alignment must be within the allowed set for their deity. (Constraint enforced at class selection and if alignment changes.)

---

### Subsection: Good and Evil
> "Your character has a good alignment if they consider the happiness of others above their own and work selflessly to assist others, even those who aren't friends and family. They are also good if they value protecting others from harm, even if doing so puts the character in danger. Your character has an evil alignment if they're willing to victimize others for their own selfish gain, and even more so if they enjoy inflicting harm. If your character falls somewhere in the middle, they're likely neutral on this axis."

Requirements identified: None. (Narrative description of alignment meanings; no new mechanical rules.)

---

### Subsection: Changing Alignment
> "Alignment can change during play as a character's beliefs change, or as you realize that your character's actions reflect a different alignment than the one on your character sheet. In most cases, you can just change their alignment and continue playing. However, if you play a cleric or champion and your character's alignment changes to one not allowed for their deity (or cause, for champions), your character loses some of their class abilities until they atone (as described in the class)."

Requirements identified:
- Alignment must be mutable during play.
- When a Cleric or Champion's alignment changes to one incompatible with their deity/cause, they lose class abilities until an atonement condition is satisfied.
- Atonement is a class-defined condition; system must support a "class abilities suppressed" state pending atonement.

---

### Subsection: Law and Chaos — Paragraph 1
> "Your character has a lawful alignment if they value consistency, stability, and predictability over flexibility. Lawful characters have a set system in life, whether it's meticulously planning day-to-day activities, carefully following a set of official or unofficial laws, or strictly adhering to a code of honor."

Requirements identified: None. (Narrative description.)

---

### Subsection: Law and Chaos — Paragraph 2
> "On the other hand, if your character values flexibility, creativity, and spontaneity over consistency, they have a chaotic alignment—though this doesn't mean they make decisions by choosing randomly. Chaotic characters believe that lawful characters are too inflexible to judge each situation by its own merits or take advantage of opportunities, while lawful characters believe that chaotic characters are irresponsible and flighty."

Requirements identified: None. (Narrative description.)

---

### Subsection: Law and Chaos — Paragraph 3
> "Many characters are in the middle, obeying the law or following a code of conduct in many situations, but bending the rules when the situation requires it. If your character is in the middle, they are neutral on this axis."

Requirements identified: None. (Narrative description.)

---

### Subsection: Age
> "Decide your character's age and note it on the third page of the character sheet. The description for your character's ancestry in Chapter 2 gives some guidance on the age ranges of members of that ancestry. Beyond that, you can play a character of whatever age you like. There aren't any mechanical adjustments to your character for being particularly old, but you might want to take it into account when considering your starting ability scores and future advancement. Particularly young characters can change the tone of some of the game's threats, so it's recommended that characters are at least young adults."

Requirements identified:
- Character age is a free-form field; no mechanical stat adjustments are derived from it.
- Ancestry entries should include guidance on typical age ranges for reference.

---

### Subsection: Gender and Pronouns
> "Characters of all genders are equally likely to become adventurers. Record your character's gender, if applicable, and their pronouns on the third page of the character sheet."

Requirements identified:
- Character sheet must include free-text gender and pronoun fields. These fields have no mechanical effect.

---

### Subsection: Class DC
> "A class DC sets the difficulty for certain abilities granted by your character's class. This DC equals 10 plus their proficiency bonus for their class DC (+3 for most 1st-level characters) plus the modifier for the class's key ability score."

Requirements identified:
- Class DC = 10 + class DC proficiency bonus + key ability score modifier.
- Class DC proficiency bonus follows the standard formula (Level + rank bonus).
- At 1st level with Trained class DC: Class DC = 10 + 3 + key ability modifier.

---

### Subsection: Hero Points
> "Your character usually begins each game session with 1 Hero Point, and you can gain additional Hero Points during sessions by performing heroic deeds or devising clever strategies. Your character can use Hero Points to gain certain benefits, such as staving off death or rerolling a d20."

Requirements identified:
- Hero Points are a per-session resource; each session begins with 1 Hero Point.
- Additional Hero Points may be awarded by the GM during a session.
- Hero Points may be spent to: avoid death (stave off dying), or reroll a d20.
- Hero Point pool must be tracked as a session-level (not persistent) resource; resets each session.

---

### Subsection: Armor Class (AC)
> "Your character's Armor Class represents how difficult they are to hit in combat. To calculate your AC, add 10 plus your character's Dexterity modifier (up to their armor's Dexterity modifier cap; page 274), plus their proficiency bonus with their armor, plus their armor's item bonus to AC and any other permanent bonuses and penalties."

Requirements identified:
- AC = 10 + DEX modifier (capped by armor's Dex cap) + armor proficiency bonus + armor's item bonus to AC + other permanent bonuses/penalties.
- Armor must carry a Dexterity modifier cap field that clamps the DEX contribution to AC.

---

### Subsection: Bulk
> "Your character's maximum Bulk determines how much weight they can comfortably carry. If they're carrying a total amount of Bulk that exceeds 5 plus their Strength modifier, they are encumbered. A character can't carry a total amount of Bulk that exceeds 10 plus their Strength modifier. The Bulk your character is carrying equals the sum of all of their items; keep in mind that 10 light items make up 1 Bulk."

Requirements identified:
- Bulk is the weight system; each item has a Bulk value.
- 10 Light items = 1 Bulk.
- Encumbered threshold: total carried Bulk > STR modifier + 5.
- Maximum carry limit: total carried Bulk ≤ STR modifier + 10; cannot exceed this.
- Encumbered state must be a trackable condition affecting movement and possibly other stats.


---

## SECTION: Sample Character (Worked Example — Gar the Dwarf Druid)

### Steps 1 and 2 (Example)
> "Adam is making his first Pathfinder character. After talking about it with the rest of the group, he's decided to make a dwarven druid. After jotting down a few ideas, he begins by writing down a 10 for each ability score."

Requirements identified: None. (Confirms initialization of all scores to 10 — already captured in Step 2.)

---

### Step 3 (Example)
> "Adam looks up the dwarf entry in Chapter 2. He records the ability boosts to his Constitution and Wisdom scores (bringing both up to 12). He also applies the ability flaw to his Charisma, dropping it to 8. For his free ability boost, he chooses Dexterity to boost his defenses, raising it to 12 as well. He also records the 10 Hit Points the ancestry gives him. Next, he returns to his character sheet to record the size, Speed, language, and darkvision ability he gets from being a dwarf. Finally, he decides on a heritage, writing 'rock dwarf' next to dwarf, and he picks an ancestry feat, deciding on Rock Runner."

Requirements identified:
- Dwarf ancestry data (illustrative): +CON, +WIS, one free boost, flaw to CHA; 10 Ancestry HP; grants darkvision; heritage choices include Rock Dwarf.
- Darkvision is a special sense type that must be represented in the character senses model.

---

### Step 4 (Example)
> "Adam likes the idea of a solitary dwarven druid, and the nomad background makes for a good choice. For the first ability boost granted by the background, Adam chooses Wisdom, and for the free ability boost, he chooses Constitution, taking both up to 14. On the second page, he writes 'Assurance (Survival)' in the Skill Feats area. Finally, he writes 'cave' next to the first Lore skill entry and checks the box under the 'T' for that skill and Survival."

Requirements identified:
- Backgrounds grant a typed-choice boost and a free boost (as established); this example shows both applied to different scores.
- Lore skills are named sub-skills (e.g., "Cave Lore"); the topic (here "cave") must be a configurable field.
- Skill feats from background are recorded in a dedicated Skill Feats section.

---

### Step 5 (Example)
> "Adam writes 'druid' on the class line of his character sheet and fills in the number 1 in the level box. The druid class grants an ability boost to its key ability score, which is Wisdom, so Adam's character has his Wisdom raised to 16."

Requirements identified:
- Druid key ability score: WIS. (Class-specific data, confirmed by example.)

---

### Step 6 (Example)
> "Adam applies four more ability boosts to his ability scores to determine his starting scores. After giving it some thought, he applies them to Wisdom (raising it to 18), and to Strength, Dexterity, and Constitution (raising them to 12, 14, and 16, respectively)."

Requirements identified: None. (Confirms 4 additional free boosts applied to different scores — already captured in Step 6.)

---

### Step 7 (Example)
> "Adam records all of his initial proficiencies... He marks Nature as trained and notes that once he picks his druid order, he'll become trained in another skill determined by that order. He then gets to choose two more skills (if he had a higher Intelligence, he would have gotten more). He decides on Athletics and Medicine, marking both of them as trained. Next, he adds the 8 Hit Points from the druid class and his Constitution modifier of +3 to the 10 Hit Points from his dwarf ancestry for an impressive 21 total Hit Points."

Requirements identified:
- Druid class: grants Nature as a fixed trained skill; additional trained skill determined by druid Order sub-selection.
- Druid class base HP per level: 8 (before adding CON modifier).
- Total HP at 1st level: Ancestry HP (10) + Class HP (8) + CON modifier (+3) = 21.
- Additional skill choices granted by class (beyond fixed skills) scale with INT modifier (more INT = more skills); this example: 2 bonus skill choices with INT modifier of +0.

> "Moving on to class features, Adam marks down wild empathy in the class feats and abilities area, as well as the Shield Block feat in the bonus feats area... He makes note of the anathema for being a druid and records Druidic in his language section."

Requirements identified:
- Class features may include: bonus feats (e.g., Shield Block), class-specific features (e.g., wild empathy), special languages (e.g., Druidic), and behavioral restrictions (anathema).
- Anathema is a class-specific behavioral constraint field (not a mechanical stat, but a rules element to be recorded).
- Class-granted special languages must be addable to the character's language list.

> "Next, he looks through the druid orders and decides upon the wild order, which gives him his final trained skill (Intimidation), the ability to cast wild morph, as well as the Wild Shape feat."

Requirements identified:
- Druid class has a sub-selection called an Order; orders grant additional trained skills, spells, and feats.
- Druid Order is a required sub-selection within the Druid class entry.

> "Finally, a druid can cast a limited number of primal spells... Adam is curious, and he turns to Chapter 7: Spells to decide what spells he might cast. He jots down five cantrips and two 1st-level spells and marks them as prepared."

Requirements identified:
- Druid uses the primal magic tradition.
- At 1st level, Druid can prepare: 5 cantrips + 2 1st-level spell slots.
- Spells are prepared (not spontaneous) for the Druid class; prepared spells are selected each day from the spell list.

---

### Step 8 (Example)
> "Adam turns to Chapter 6: Equipment. He's trained in medium armor, but since wearing metal armor is anathema to druids, he chooses hide armor. For weapons, he decides on a spear, but he buys two just in case he wants to throw the first one."

Requirements identified:
- Hide armor is a valid medium armor; must exist in the equipment database.
- Spear is a weapon usable for both melee and ranged (thrown); it must appear in both Melee Strikes and Ranged Strikes lists on the character sheet.
- Druid anathema prohibits wearing metal armor — demonstrates that anathema rules can restrict equipment choices.

---

### Step 9 (Example)
> "Adam records all of the ability modifiers for Perception, saving throws, Strikes, and skills. He then puts a '+3' in the box marked Prof to indicate his proficiency bonus for each statistic he's trained in (1 for his level, plus 2 for being trained) and '+5' in any that he is an expert."

Requirements identified: None. (Confirms proficiency bonus formula: Level 1 + Trained 2 = +3; Level 1 + Expert 4 = +5.)

---

### Step 10 (Example)
> "Finally, Adam fills out the final details of his character, noting his neutral alignment and calculating his AC and Bulk limits. Last but not least, he fills in some last-minute information about his character and decides on a name. Gar the dwarf druid is ready for his first adventure!"

Requirements identified: None. (Confirms Step 10 completes creation; no new mechanics introduced.)


---

## SECTION: Leveling Up

### Paragraph 1
> "The world of Pathfinder is a dangerous place, and your character will face terrifying beasts and deadly traps on their journey into legend. With each challenge resolved, a character earns Experience Points (XP) that allow them to increase in level. Each level grants greater skill, increased resiliency, and new capabilities, allowing your character to face even greater challenges and go on to earn even more impressive rewards."

Requirements identified:
- XP is a persistent character resource accumulated from overcoming challenges.
- XP is the mechanism for level advancement.

---

### Paragraph 2
> "Each time your character reaches 1,000 Experience Points, their level increases by 1. On your character sheet, indicate your character's new level beside the name of their class, and deduct 1,000 XP from their XP total. If you have any Experience Points left after this, record them—they count toward your next level, so your character is already on their way to advancing yet again!"

Requirements identified:
- Level advancement threshold: 1,000 XP per level.
- On level-up: deduct 1,000 XP from total; carry remaining XP forward (no floor reset to 0).
- Character level is an integer field that increments on each level-up event.

---

### Paragraph 3
> "Next, return to your character's class entry. Increase your character's total Hit Points by the number indicated for your class. Then, take a look at the class advancement table and find the row for your character's new level. Your character gains all the abilities listed for that level, including new abilities specific to your class and additional benefits all characters gain as they level up. For example, all characters gain four ability boosts at 5th level and every 5 levels thereafter."

Requirements identified:
- On level-up: max HP increases by class HP per level (class base + CON modifier).
- On level-up: all class features listed in the advancement table for the new level are granted.
- Universal level-up grants: 4 ability boosts at levels 5, 10, 15, and 20 (every 5 levels).

---

### Paragraph 4
> "You can find all the new abilities specific to your class, including class feats, right in your class entry, though you can also use class feats to take an archetype (page 219). Your character's class entry also explains how to apply any ability boosts and skill increases your character gains. If they gain an ancestry feat, head back to the entry for your character's ancestry in Chapter 2 and select another ancestry feat from the list of options. If they gain a skill increase, refer to Chapter 4 when deciding which skill to apply it to. If they gain a general feat or a skill feat, you can choose from the feats listed in Chapter 5. If they can cast spells, see the class entry for details on adding spell slots and spells."

Requirements identified:
- Class feats can also be used to take an archetype (multiclass/archetype system exists).
- Skill increases allow a character to raise a skill's proficiency rank (e.g., Trained → Expert).
- On level-up, different feat categories may be granted: class feats, ancestry feats, general feats, skill feats (each sourced from different pools).
- Spellcasters gain additional spell slots and spells on level-up per class advancement table.

---

### Paragraph 5
> "Once you've made all your choices for your character's new level, be sure to go over your character sheet and adjust any values that have changed. At a bare minimum, your proficiency bonuses all increase by 1 because you've gained a level, so your AC, attack rolls, Perception, saving throws, skill modifiers, spell attack rolls, and class DC all increase by at least 1. You might need to change other values because of skill increases, ability boosts, or class features that either increase your proficiency rank or increase other statistics at certain levels."

Requirements identified:
- Every proficiency-based statistic increases by +1 automatically on every level gain (because Level is part of the proficiency bonus formula).
- Stats affected by level-up (minimum): AC, attack rolls, Perception, saving throws (Fort/Ref/Will), skill modifiers, spell attack rolls, class DC.

---

### Paragraph 6
> "If an ability boost increases your character's Constitution modifier, recalculate their maximum Hit Points using their new Constitution modifier (typically this adds 1 Hit Point per level). If an ability boost increases your character's Intelligence modifier, they become trained in an additional skill and language."

Requirements identified:
- CON modifier increase from ability boost: recalculate max HP = (new CON modifier − old CON modifier) × character level additional HP.
- INT modifier increase from ability boost: grants 1 additional trained skill + 1 additional language per modifier point gained.

---

### Paragraph 7
> "Some feats grant a benefit based on your level, such as Toughness, and these benefits are adjusted whenever you gain a level as well."

Requirements identified:
- Some feats have level-scaling benefits; system must recompute these feats' contributions on every level gain.

---

### Paragraph 8
> "You can perform the steps in the leveling-up process in whichever order you want. For example, if you wanted to take the skill feat Intimidating Prowess as your skill feat at 10th level, but your character's Strength score was only 14, you could first increase their Strength score to 16 using the ability boosts gained at 10th level, and then take Intimidating Prowess as a skill feat at the same level."

Requirements identified:
- Level-up steps may be completed in any order (non-linear); prerequisite checks for feats taken at the same level apply after all level-up choices are finalized, not mid-process.

---

### Leveling-Up Checklist
> • Increase your level by 1 and subtract 1,000 XP from your XP total.
> • Increase your maximum Hit Points by the amount listed in your class entry in Chapter 3.
> • Add class features from your class advancement table, including ability boosts and skill increases.
> • Select feats as indicated on your class advancement table. For ancestry feats, see Chapter 2. For class feats, see your class entry in Chapter 3. For general feats and skill feats, see Chapter 5.
> • Add spells and spell slots if your class grants spellcasting. See Chapter 7 for spells.
> • Increase all of your proficiency bonuses by 1 from your new level, and make other increases to your proficiency bonuses as necessary from skill increases or other class features. Increase any other statistics that changed as a result of ability boosts or other abilities.
> • Adjust bonuses from feats and other abilities that are based on your level.

Requirements identified:
- Level-up must be a structured transaction covering all of the following in any order:
  1. Level increment + XP deduction
  2. Max HP increase
  3. Class features from advancement table (may include sub-selections)
  4. Feat selections by type (ancestry / class / general / skill)
  5. Spell slot and spell additions (if spellcasting class)
  6. Proficiency bonus recalculation across all affected stats (automatic from level formula)
  7. Skill increase applications (if granted)
  8. Ability boost applications (if at level 5/10/15/20 or granted by class)
  9. Recalculate all level-scaling feat benefits
- All prerequisite checks for new feats selected during this level-up should validate after all choices are made, not before.

