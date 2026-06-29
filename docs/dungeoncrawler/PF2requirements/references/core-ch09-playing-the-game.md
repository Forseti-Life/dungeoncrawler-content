# PF2E Core Rulebook — Chapter 9: Playing the Game
## Requirements Extraction

---

## SECTION: Three Modes of Play

### Paragraph — Encounter, Exploration, Downtime

> Time is divided into encounter mode (rounds/actions), exploration mode (minutes/hours), and downtime mode (days/weeks). Each mode governs pacing and available activities.

- REQ: System must support three distinct play modes: **Encounter** (round-by-round), **Exploration** (free-form travel and activity), and **Downtime** (day-by-day).
- REQ: Transitioning between modes must be supported; e.g., an encounter ending drops back into exploration mode.

---

## SECTION: Core Check Mechanics

### Paragraph — d20 Checks

> Roll a d20 and add the relevant modifier. Compare result to a DC. DC = 10 + the total modifier for that statistic (when the GM sets it from a character stat).

- REQ: All checks are d20 + modifier vs. a DC.
- REQ: DC derived from an ability = 10 + that ability's total modifier.

### Paragraph — Degrees of Success

> Critical Success: result ≥ DC + 10. Success: result ≥ DC. Failure: result < DC. Critical Failure: result ≤ DC – 10.

- REQ: Four degrees of success must be implemented: critical success, success, failure, critical failure.
- REQ: Natural 20 on the die shifts the degree one step better (failure → success, success → critical success, etc.).
- REQ: Natural 1 on the die shifts the degree one step worse.
- REQ: Natural 20 can't exceed critical success; natural 1 can't go below critical failure.

### Paragraph — Proficiency Ranks

> Untrained = +0. Trained = level + 2. Expert = level + 4. Master = level + 6. Legendary = level + 8.

- REQ: Proficiency ranks: **Untrained** (+0), **Trained** (level+2), **Expert** (level+4), **Master** (level+6), **Legendary** (level+8).
- REQ: Proficiency bonus is added to all checks and DCs that use the proficiency.

### Paragraph — Bonus and Penalty Types

> Circumstance, item, and status bonuses each stack with each other but not with themselves (only the highest applies). Untyped bonuses always stack. Same rules for penalties except untyped penalties ALL stack.

- REQ: Bonus types: **circumstance**, **item**, **status**. Only the highest bonus of the same type applies.
- REQ: Different bonus types stack with each other.
- REQ: Untyped bonuses always stack.
- REQ: Penalty types follow same rule: same type → take the worst; different types → all apply; untyped penalties all stack.

---

## SECTION: Attack Rolls

### Paragraph — Attack Roll Formula

> Attack roll = d20 + ability modifier + proficiency bonus + item bonus + other bonuses/penalties vs. target's AC.

- REQ: Melee attack roll = d20 + Str mod + proficiency + bonuses.
- REQ: Ranged attack roll = d20 + Dex mod + proficiency + bonuses (by default).
- REQ: Spell attack roll = d20 + spellcasting ability mod + proficiency.
- REQ: Attack roll is compared against target's AC. ≥ AC = hit; ≥ AC+10 = critical hit.

### Paragraph — Multiple Attack Penalty (MAP)

> First attack: no penalty. Second attack: –5 (agile: –4). Third and beyond: –10 (agile: –8). MAP resets at start of each turn.

- REQ: Each attack after the first in a turn applies MAP.
- REQ: Standard MAP: –5 / –10.
- REQ: Agile weapon/unarmed MAP: –4 / –8.
- REQ: MAP resets at start of each turn.
- REQ: Attacks made outside own turn (e.g., Attack of Opportunity) do NOT incur MAP and do not increase it.

### Paragraph — Range Penalty

> –2 penalty per range increment beyond the first. Maximum range = 6× increment (–10 at 6th). Beyond that, attack is impossible.

- REQ: Ranged weapons have a range increment. Attacks beyond 1× increment take –2 per additional increment.
- REQ: Maximum effective range = 6× the range increment.
- REQ: Attacks beyond maximum range cannot be attempted.

---

## SECTION: Armor Class and Defenses

### Paragraph — AC Formula

> AC = 10 + Dex modifier + proficiency bonus + armor item bonus + other bonuses.

- REQ: AC formula: 10 + Dex mod (capped by armor's Dex Cap) + proficiency + armor item bonus.

### Paragraph — Saving Throws

> Fortitude (Constitution), Reflex (Dexterity), Will (Wisdom). Each = ability mod + proficiency + bonuses.

- REQ: Three saving throw types: **Fortitude** (Con), **Reflex** (Dex), **Will** (Wis).
- REQ: Basic saving throw results: Critical Success = 0 damage; Success = half damage; Failure = full damage; Critical Failure = double damage.

### Paragraph — Perception

> Perception = Wisdom modifier + proficiency + item bonuses. Used for initiative (most common), Seek action, and noticing threats.

- REQ: Perception = Wis mod + proficiency + bonuses.
- REQ: Perception is the default initiative check.
- REQ: GM may call for a different check for initiative in some situations (e.g., Stealth when Avoiding Notice).

---

## SECTION: Flat Checks

### Paragraph — Flat Check Rules

> Flat checks have no modifiers. Roll d20 ≥ DC = success. If DC ≤ 1 = auto-success. If DC ≥ 21 = auto-fail.

- REQ: Flat checks use d20 with no added modifiers.
- REQ: DC ≤ 1 = automatic success. DC ≥ 21 = automatic failure.
- REQ: Common use: targeting hidden creatures (DC 11), concealed creatures (DC 5), ending persistent damage (DC 15, or DC 10 with assistance).

### Paragraph — Secret Checks

> GM rolls certain checks secretly on behalf of the player (e.g., Seek, Sense Motive). The player declares the action but doesn't learn the exact result.

- REQ: Some checks (marked Secret) are rolled secretly by the GM. Player knows only what their character would perceive.

### Paragraph — Fortune and Misfortune

> Fortune effects: roll twice, take higher. Misfortune effects: roll twice, take lower. If both apply, they cancel and roll once normally.

- REQ: Fortune: roll twice, use higher result.
- REQ: Misfortune: roll twice, use lower result.
- REQ: Fortune and misfortune cancel each other; only one of each can apply to a single check.

---

## SECTION: Damage

### Paragraph — Damage Types

> Physical: bludgeoning (B), piercing (P), slashing (S). Energy: acid, cold, electricity, fire, sonic. Positive, negative, force, alignment (chaotic/evil/good/lawful), mental, poison, bleed, precision.

- REQ: Implement all damage types: B/P/S (physical), acid/cold/electricity/fire/sonic (energy), positive, negative, force, chaotic/evil/good/lawful (alignment), mental, poison, bleed, precision.

### Paragraph — Damage Formulas

> Melee: damage die + Str mod. Ranged: damage die only (no ability mod). Thrown weapons: add Str mod. Propulsive: add half Str mod.

- REQ: Melee damage = weapon die + Str modifier.
- REQ: Ranged damage = weapon die only (no ability modifier by default).
- REQ: Thrown weapons add full Str modifier to damage.
- REQ: Propulsive ranged weapons add half Str modifier (positive only) to damage.
- REQ: Spells: damage die only (ability mod not added unless a specific rule says otherwise).

### Paragraph — Minimum Damage

> Minimum damage from a roll is always 1, even if penalties reduce it below 1.

- REQ: Damage dealt is always minimum 1 after all reductions (before immunities/resistances).

### Paragraph — Critical Hit Damage

> Critical hit = double all damage dice results (or double the die count). Add flat bonuses after doubling.

- REQ: Critical hit: double all damage dice rolled. Flat bonuses (e.g., Str modifier) are NOT doubled — add them once after doubling dice.
- REQ: Additionally apply weapon/spell critical specialization effects on a critical hit.

### Paragraph — Immunity, Weakness, Resistance

> Immunity: completely negates that damage type. Weakness X: +X damage taken from that type. Resistance X: –X damage taken (minimum 0).

- REQ: Creatures may have immunities (negate all damage of that type), weaknesses (add +X to damage taken), and resistances (reduce damage taken by X, minimum 0).
- REQ: Immunity to critical hits changes double-damage to normal damage; does not remove other critical effects.
- REQ: Precision immunity ignores precision damage only; other parts of the attack still apply.

### Paragraph — Nonlethal Attacks

> Attacking nonlethally with a lethal weapon: –2 penalty. Attacking lethally with a nonlethal weapon: –2 penalty. Reaching 0 HP from nonlethal → unconscious, not dying.

- REQ: Nonlethal attacks with lethal weapons take –2 to attack roll (and vice versa).
- REQ: If nonlethal damage reduces a creature to 0 HP, it is knocked unconscious (gains no dying condition).

---

## SECTION: Conditions

### Paragraph — Conditions Overview

> ~40 named conditions exist (detailed in Appendix). Each has specific rules for duration, stacking, and removal.

- REQ: Full conditions system required. Each condition must track its value (if valued) and duration.
- REQ: Conditions with values (dying, wounded, frightened, etc.) track a numeric severity.
- REQ: Conditions end via specified removal methods (healing, saves, time, etc.).

---

## SECTION: Areas of Effect

### Paragraph — Burst

> A burst issues from a corner of a square. Extends in all directions to the listed radius. Affects any creature whose space (even one square) is within the burst.

- REQ: **Burst** area: originates at a corner of a square; affects all creatures within the radius. Partial occupancy (one square) counts.

### Paragraph — Cone

> A cone shoots from you in a quarter circle. First square shares an edge (orthogonal) or corner (diagonal) with caster's space. Width increases as it extends.

- REQ: **Cone** area: quarter-circle from caster's space. Must start at an edge (orthogonal aim) or corner (diagonal aim). Cannot overlap caster's own space.

### Paragraph — Emanation

> An emanation extends outward from every side of the creator's space. Larger creatures produce larger emanations. Creator may choose whether they are affected.

- REQ: **Emanation** area: extends outward from all sides of caster's space. Creator chooses whether to be included unless the ability states otherwise.

### Paragraph — Line

> A line shoots from the caster in a straight path. 5 feet wide unless stated otherwise. Affects all creatures whose space it overlaps.

- REQ: **Line** area: straight path from caster, 5 feet wide by default. Affects all creatures whose space is overlapped.

### Paragraph — Area Movement and Difficult Terrain

> Distances for area effects are never reduced by difficult terrain. Areas are measured identically to movement on a grid.

- REQ: Area effect range and shape are never reduced by difficult terrain.

---

## SECTION: Line of Effect and Line of Sight

### Paragraph — Line of Effect

> An unblocked path to the target is required. Visibility doesn't matter. Portcullises and non-solid barriers don't block. Typically a 1-foot gap maintains line of effect.

- REQ: Most effects require **line of effect** (unblocked physical path). Solid barriers block it; semi-solid obstacles (portcullises) do not.
- REQ: 1-foot gap is typically sufficient to maintain line of effect (GM adjudicates).
- REQ: Area effects require line of effect from origin to each target.

### Paragraph — Line of Sight

> Some effects require line of sight: must be able to precisely sense the area and path must not be blocked by a solid barrier. Darkness blocks if no darkvision.

- REQ: Line of sight: requires clear path and ability to sense the area (darkness blocks sight without darkvision).
- REQ: Solid barriers block line of sight; portcullises and non-solid obstacles do not.

---

## SECTION: Afflictions

### Paragraph — Affliction Format

> Name + traits + level (if applicable). Saving throw type and DC. Onset (if any). Maximum duration (if any). Stages (effect + interval per stage).

- REQ: Afflictions (poison, disease, curse, radiation) have: name, traits, level, save type/DC, optional onset, optional max duration, and numbered stages.

### Paragraph — Initial Save

> On first exposure, attempt an initial saving throw. Success = unaffected for this exposure. Failure = advance to stage 1 (or stage 2 on critical failure) after onset.

- REQ: Initial save on first exposure. Success = unaffected. Critical failure = jump to stage 2 instead of stage 1.
- REQ: If onset is listed, effects of the first stage don't activate until onset time elapses.

### Paragraph — Stage Progression

> At end of each stage's interval, attempt a new save. Critical success = –2 stages. Success = –1 stage. Failure = +1 stage. Critical failure = +2 stages. Below stage 1 = affliction ends.

- REQ: Periodic saves at end of each stage interval.
- REQ: Critical success on periodic save: reduce stage by 2. Success: –1. Failure: +1. Critical failure: +2.
- REQ: Reducing below stage 1 ends the affliction. Exceeding max stage repeats max stage effects.
- REQ: Conditions from an affliction may persist beyond the affliction's end per normal condition rules.

### Paragraph — Multiple Exposures

> Multiple exposures to same curse/disease while affected = no change. Poison: each new exposure failure = +1 stage (or +2 on crit fail); doesn't reset max duration.

- REQ: Curses and diseases: re-exposure while already afflicted has no additional effect.
- REQ: Poisons: re-exposure failure during active poison advances stage by 1 (crit fail: +2); max duration unchanged.

### Paragraph — Virulent Afflictions

> Virulent trait: must succeed twice consecutively to reduce stage by 1. Critical success reduces stage by only 1 (not 2).

- REQ: **Virulent** afflictions require two consecutive successes to reduce stage by 1. Critical success = only –1 stage (not –2).

---

## SECTION: Counteract Rules

### Paragraph — Counteract Check

> Add relevant skill modifier or spellcasting ability mod + proficiency to check vs. target's DC (or DC from target's level). What you can counteract depends on check result and counteract level comparison.

- REQ: Counteract check = appropriate modifier + proficiency vs. target DC.
- REQ: Spell counteract level = spell's level. Other effects: halve level (round up). Creature-based: halve creature level (round up).

### Paragraph — Counteract Outcomes

> Critical Success: counteract if target's level is ≤ 3 higher. Success: counteract if target's level is ≤ 1 higher. Failure: counteract only if target's level is lower. Critical Failure: cannot counteract.

- REQ: Critical success counteract: target level ≤ caster level +3.
- REQ: Success counteract: target level ≤ caster level +1.
- REQ: Failure counteract: only if target level is lower than caster's counteract level.
- REQ: Critical failure: counteract fails entirely.

---

## SECTION: Hit Points, Healing, and Dying

### Paragraph — Hit Points

> Max HP = ancestry HP + class HP (per level × level) + Constitution modifier per level + other sources. Current HP cannot drop below 0. Taking damage reduces current HP.

- REQ: Track current HP vs. max HP. HP cannot go below 0.
- REQ: Max HP includes: ancestry base + class (con + class HP per level × levels).

### Paragraph — Knocked Out

> PCs and significant NPCs at 0 HP are knocked out rather than dying. Initiative moves to just before the turn when knocked out.

- REQ: At 0 HP, PC is knocked out. Initiative position shifts to just before the damaging turn.
- REQ: Gain **dying 1** condition. If attacker critically succeeded or PC critically failed the triggering save: dying 2.
- REQ: If already **wounded**, add wounded value to the dying value gained.
- REQ: Nonlethal: knocked out → unconscious; no dying condition gained.

### Paragraph — Dying Condition

> Dying (1–4). At dying 4, the character dies. At start of each turn while dying, roll a recovery check (flat check DC = 10 + dying value). Crit success: –2 dying; success: –1; failure: +1; crit fail: +2.

- REQ: Dying is a valued condition (1–3, death at 4; modified by doomed).
- REQ: Recovery check: flat check at DC = 10 + current dying value, at start of each dying creature's turn.
- REQ: Critical success on recovery: –2 dying. Success: –1. Failure: +1. Critical failure: +2.
- REQ: Losing the dying condition (recovery): creature remains unconscious at 0 HP until healed to 1+ HP.
- REQ: Losing the dying condition: gain **wounded 1** (or +1 to existing wounded value).

### Paragraph — Wounded Condition

> Wounded X: gained when losing dying condition. If you gain dying while wounded, add wounded value to dying. Ends when Treat Wounds succeeds or you are restored to full HP and rest 10 minutes.

- REQ: Wounded is a valued condition. Each time dying condition is lost, wounded increases by 1 (or is gained at 1).
- REQ: When gaining dying while wounded: add wounded value to dying value gained.
- REQ: Wounded ends: successful Treat Wounds, or restored to full HP AND resting 10 minutes.

### Paragraph — Doomed Condition

> Doomed X: reduces maximum dying threshold before death. Doomed 1 = die at dying 3. If max dying ever ≤ 0, die instantly. Decreases by 1 with full night's rest.

- REQ: **Doomed X** reduces the dying death threshold by X (normally dying 4; doomed 1 → die at dying 3).
- REQ: If doomed equals or exceeds the dying threshold, die instantly.
- REQ: Doomed decreases by 1 per full night's rest. Cleared on death.

### Paragraph — Unconscious Condition

> –4 status penalty to AC, Perception, and Reflex saves. Blinded and flat-footed. Falls prone on gaining condition. Can't act.

- REQ: Unconscious: –4 status penalty to AC, Perception, Reflex saves; blinded; flat-footed; fall prone; cannot act.
- REQ: Wake from unconscious at 0 HP (not dying): natural recovery after 10 min to hours.
- REQ: Wake from unconscious with HP > 0: on taking damage (not to 0), receiving healing, being shaken awake (Interact), loud noise (Perception check vs. DC 5 for battle), or per GM decision.

### Paragraph — Heroic Recovery (Hero Points)

> Spend all Hero Points at start of turn or when dying value would increase. Lose dying condition; stabilize at 0 HP. Do not gain wounded from this.

- REQ: Hero Point heroic recovery: spend all Hero Points → lose dying, stabilize at 0 HP, do NOT gain wounded from this use.

### Paragraph — Death Effects and Instant Death

> Death trait effects: can kill at 0 HP without needing dying 4. Some effects kill outright.

- REQ: Death effects bypass dying track: reduce to 0 HP = immediate death, no recovery rolls.
- REQ: Massive damage (damage ≥ double max HP in one blow) = instant death.

### Paragraph — Temporary Hit Points

> Track separately from current HP. Damage reduces temp HP first. Can't be restored by healing. Only one source at a time (choose which to keep when gaining new ones).

- REQ: Temporary HP tracked separately; damage reduces temp HP first.
- REQ: Only one source of temp HP at a time. Player chooses to keep old or accept new when both are active.
- REQ: Temp HP cannot be restored by healing spells/abilities.

### Paragraph — Fast Healing and Regeneration

> Fast healing X: regain X HP at start of each turn. Regeneration X: same + dying condition can't increase to killing value while active. Specific damage type disables regeneration until end of next turn.

- REQ: **Fast healing X**: regain X HP at start of each turn automatically.
- REQ: **Regeneration X**: same as fast healing + prevent dying from reaching death threshold while active. Specific damage types disable it temporarily.

---

## SECTION: Actions

### Paragraph — Action Types

> Single actions (1 action), activities (multiple actions in sequence), reactions (triggered, 1 per round), free actions (no action cost; triggered or triggered-free).

- REQ: Action types: **single action** (1 action cost), **activity** (2–3 actions in sequence), **reaction** (1 per round, triggered), **free action** (no cost).
- REQ: Activities must be completed in sequence; cannot interrupt with other actions mid-activity.
- REQ: Reactions have a specific trigger; can be used on any turn when trigger occurs.
- REQ: Free actions with triggers follow reaction rules; without triggers follow single action rules.

### Paragraph — Action Economy Per Turn

> Each turn: gain 3 actions + 1 reaction. Reaction resets each round. Actions cannot be saved between turns.

- REQ: Each turn: 3 actions + 1 reaction granted.
- REQ: Unused actions are lost at end of turn. Reaction is lost at start of next turn if unused.
- REQ: Conditions (quickened, slowed, stunned) can modify action count.

### Paragraph — Turn Structure

> Three steps: (1) Start of Turn — reduce durations, use triggers, recovery check if dying, regain actions. (2) Act — spend actions. (3) End of Turn — end effects, persistent damage, conditions decrease (frightened –1, etc.).

- REQ: Start of turn: reduce ongoing effect durations by 1, process "start of turn" triggers, roll recovery check if dying, regain 3 actions + 1 reaction.
- REQ: End of turn: end effects with "until end of turn" duration, take persistent damage and attempt flat check to remove it, reduce valued conditions (e.g., frightened –1), process "end of turn" triggers.

### Paragraph — Disrupting Actions

> If an action is disrupted (e.g., by Attack of Opportunity), action costs are still spent but effects don't occur. For activities: lose all committed actions for that activity.

- REQ: Disrupted action: costs still spent; effects negated.
- REQ: Disrupted multi-action activity: all actions spent on the activity are lost.

---

## SECTION: Basic Actions

### Paragraph — Aid (Reaction)

> Trigger: ally about to use skill check or attack roll. Prerequisite: prepared to help (used an action to set up). Roll skill check or attack roll vs. DC 20 (or GM-set DC).
> Critical success: +2 circumstance (master: +3; legendary: +4). Success: +1. Critical failure: –1 penalty to ally.

- REQ: **Aid** reaction: requires setup action on prior turn. Attempt relevant check vs. DC 20.
- REQ: Critical success Aid bonus: +2 (master: +3, legendary: +4). Success: +1. Crit fail: –1 to ally.

### Paragraph — Crawl

> Move 5 feet while prone. Requires Speed ≥ 10 feet. Move trait.

- REQ: **Crawl** (1 action, move): move 5 feet while prone; requires Speed ≥ 10 ft.

### Paragraph — Delay (Free Action)

> Trigger: start of your turn. Remove yourself from initiative order. Return as a free action at end of any other creature's turn; new position is permanent. Negative turn effects happen immediately when Delay is used.

- REQ: **Delay** (free action, trigger: your turn begins): exit initiative order; re-enter at end of any other creature's turn.
- REQ: Delaying triggers immediate application of any negative start/end-of-turn effects. Beneficial effects that would have ended during the turn end when Delay is taken.
- REQ: If an entire round passes without returning, lose the delayed turn's actions; initiative stays unchanged.

### Paragraph — Drop Prone

> Fall prone as 1 action.

- REQ: **Drop Prone** (1 action, move): gain the prone condition.

### Paragraph — Escape

> Attack trait. Use unarmed attack modifier (or Acrobatics/Athletics) vs. DC of grabbing creature (Athletics DC), spell DC, or hazard Escape DC. Success: free of grabbed/immobilized/restrained. Crit success: also Stride 5 feet.

- REQ: **Escape** (1 action, attack): roll unarmed modifier, Acrobatics, or Athletics vs. appropriate DC to remove grabbed/immobilized/restrained.
- REQ: Critical success: freed + may Stride 5 feet. Critical failure: cannot attempt again until next turn.
- REQ: Escape has the attack trait (counts for MAP).

### Paragraph — Interact

> Manipulate trait. Use hand(s) to grab, open a door, or perform similar manipulation. May require skill check.

- REQ: **Interact** (1 action, manipulate): grab unattended object, open door, or similar manipulation. May trigger reactions (manipulate trait).

### Paragraph — Leap

> Move trait. Jump up to 10 ft horizontally (Speed ≥ 15 ft) or 15 ft (Speed ≥ 30 ft). Or 3 ft vertical + 5 ft horizontal. Greater distances require Athletics.

- REQ: **Leap** (1 action, move): horizontal jump up to 10 ft (Speed 15+) or 15 ft (Speed 30+). Vertical: up to 3 ft up + 5 ft horizontal.
- REQ: Greater leaps require the High Jump or Long Jump Athletics actions.

### Paragraph — Ready

> 2 actions. Choose single action or free action + trigger. Turn ends. If trigger occurs before next turn, use chosen action as a reaction. If attack, MAP from time of Ready applies.

- REQ: **Ready** (2 actions, concentrate): designate one action and a trigger. The action becomes a reaction usable until start of next turn.
- REQ: Readied attack uses MAP from when Ready was used.
- REQ: Cannot Ready a free action that already has a trigger.

### Paragraph — Release

> Free action. Release held item(s). Does NOT trigger manipulate reactions.

- REQ: **Release** (free action, manipulate): drop a held item. Does not trigger Attack of Opportunity or other manipulate-triggered reactions.

### Paragraph — Seek

> 1 action, concentrate, secret. Search a 30-ft cone or 15-ft burst (for creatures) or 10-ft square (for objects). GM rolls secret Perception check vs. Stealth DCs.
> Crit success: undetected/hidden creatures become observed; objects found. Success: undetected → hidden; hidden → observed. Objects: location or clue.

- REQ: **Seek** (1 action, concentrate, secret): choose area; GM rolls secret Perception check vs. Stealth DCs.
- REQ: Seek creatures — crit success: observed. Success: undetected → hidden, hidden → observed.
- REQ: Seek objects — crit success: exact location. Success: location or clue.
- REQ: Imprecise sense: detected creature can be at best hidden, not observed.

### Paragraph — Sense Motive

> 1 action, concentrate, secret. Choose one creature. GM rolls secret Perception vs. creature's Deception DC.
> Crit success: true intentions + any mental magic. Success: normal/abnormal behavior. Failure: told what deceiver wants you to think. Crit failure: false impression.

- REQ: **Sense Motive** (1 action, concentrate, secret): assess creature for deception.
- REQ: Results as above; typically can't retry until situation changes.

### Paragraph — Stand

> 1 action, move. Remove prone condition.

- REQ: **Stand** (1 action, move): remove the prone condition.

### Paragraph — Step

> 1 action, move. Move exactly 5 feet. Does NOT trigger reactions based on movement. Cannot Step into difficult terrain or using non-land Speed. Requires Speed ≥ 10 ft.

- REQ: **Step** (1 action, move): move exactly 5 feet without triggering move-triggered reactions.
- REQ: Cannot Step into difficult terrain. Cannot Step with non-land Speed.

### Paragraph — Stride

> 1 action, move. Move up to Speed.

- REQ: **Stride** (1 action, move): move up to full land Speed.

### Paragraph — Strike

> 1 action, attack. Make an attack roll with a weapon or unarmed attack vs. target's AC. Success: deal weapon damage. Crit success: double damage.

- REQ: **Strike** (1 action, attack): attack roll vs. target AC. Success: weapon damage. Critical success: double dice damage.

### Paragraph — Take Cover

> 1 action. Requires current cover, nearby cover feature, or prone. Improves cover by one tier (lesser → standard, standard → greater). Lasts until movement, attack action, unconscious, or voluntary end.

- REQ: **Take Cover** (1 action): upgrade cover tier: standard → greater (+4 to AC/Reflex/Stealth). If lesser: gain standard. Lasts until move, attack, unconscious, or free-action end.

---

## SECTION: Specialty Basic Actions

### Paragraph — Arrest a Fall

> Reaction. Trigger: fall. Requires fly Speed. Acrobatics check (DC 15+). Success: no fall damage. Crit fail: damage for 20+ feet fallen.

- REQ: **Arrest a Fall** (reaction): requires fly Speed; Acrobatics DC 15. Success: land safely. Crit fail: take 10 bludgeoning per 20 ft fallen so far.

### Paragraph — Avert Gaze

> 1 action. +2 circumstance bonus to saves against visual gaze abilities. Lasts until start of next turn.

- REQ: **Avert Gaze** (1 action): +2 circumstance to saves vs. gaze-based visual abilities until next turn start.

### Paragraph — Burrow

> 1 action, move. Requires burrow Speed. Dig through loose material at burrow Speed. Doesn't create tunnel unless stated.

- REQ: **Burrow** (1 action, move): requires burrow Speed. No tunnel created unless ability specifies.

### Paragraph — Fly

> 1 action, move. Requires fly Speed. Moving upward = difficult terrain. Moving straight down: 10 ft per 5 ft spent. Land safely (no fall damage). At end of turn without Fly action: fall.

- REQ: **Fly** (1 action, move): move up to fly Speed. Upward = difficult terrain cost. Fall at end of turn if airborne without using Fly.
- REQ: Fly 0 feet = hover.

### Paragraph — Grab an Edge

> Reaction. Trigger: fall from edge. Hands not restrained. Reflex save vs. Climb DC. Crit success: stop fall (reduces effective fall by 30 ft, no free hand needed). Success: stop fall if hand free (reduces fall 20 ft). Crit fail: continue falling, +10 damage per 20 ft fallen so far.

- REQ: **Grab an Edge** (reaction): Reflex save when falling past a handhold.

### Paragraph — Mount / Dismount

> 1 action. Mount must be adjacent, at least 1 size larger, willing. Can also use to dismount.

- REQ: **Mount** (1 action): requires adjacent, willing, size-larger creature. Also used to dismount.

### Paragraph — Point Out

> 1 action, auditory/manipulate/visual. Requires: creature is undetected by ally but not by you. Make it hidden to allies instead of undetected. Allies must be able to see you. Allies that can't hear/understand attempt Perception vs. Stealth DC.

- REQ: **Point Out** (1 action): reveal undetected creature's location to allies; creature becomes hidden (not undetected) to those allies.

### Paragraph — Raise a Shield

> 1 action. Requires wielding a shield. Gain shield's listed circumstance bonus to AC until start of next turn.

- REQ: **Raise a Shield** (1 action): gain shield's AC bonus (circumstance) until start of next turn.

---

## SECTION: Reactions in Encounters

### Paragraph — Attack of Opportunity

> Reaction. Trigger: creature within reach uses manipulate action, move action, makes a ranged attack, or leaves a square during a move action. Make a melee Strike. Critical hit on manipulate trigger: disrupt that action. Does not count toward MAP.

- REQ: **Attack of Opportunity** (reaction, fighter class feature): melee Strike on trigger.
- REQ: Critical hit + trigger was manipulate action: disrupt the action.
- REQ: Attack of Opportunity does not count toward or apply MAP.

### Paragraph — Shield Block

> Reaction. Trigger: while Shield Raised, take physical damage. Both creature and shield take the damage reduced by the shield's Hardness. Shield takes remaining damage (may break or be destroyed).

- REQ: **Shield Block** (reaction): reduce damage taken by shield's Hardness; remaining damage splits between creature and shield.
- REQ: Shield must have been Raised to use this reaction.

---

## SECTION: Movement in Encounters

### Paragraph — Speed and Movement Types

> Land Speed, Burrow Speed, Climb Speed (auto-succeed Athletics climb, move at climb Speed; +4 circumstance to Athletics Climb), Fly Speed, Swim Speed (auto-succeed Athletics swim, move at swim Speed; +4 circumstance to Athletics Swim).

- REQ: Movement types: land, burrow, climb, fly, swim. Each has its own Speed value.
- REQ: Climb Speed: auto-succeed Athletics checks to climb, move at climb Speed. +4 circumstance to Athletics (Climb). Not flat-footed while climbing.
- REQ: Swim Speed: auto-succeed Athletics (Swim), move at swim Speed. +4 circumstance to Athletics (Swim). Still flat-footed underwater.
- REQ: Speed bonuses/penalties (circumstance, item, status) apply. Minimum 5 feet unless otherwise specified.

### Paragraph — Diagonal Movement

> On a grid: first diagonal counts as 5 ft, second counts as 10 ft, alternating. Total diagonal count tracked across whole turn; resets at turn end.

- REQ: Diagonal grid movement alternates between 5 ft and 10 ft per square (1st diagonal = 5, 2nd = 10, 3rd = 5, etc.). Total tracked across the turn.

### Paragraph — Size, Space, and Reach

> TABLE 9–1: Tiny (<5 ft space, 0 reach), Small/Medium (5 ft space, 5 ft reach), Large (10 ft, 10 ft tall / 5 ft long), Huge (15 ft, 15/10), Gargantuan (20+ ft, 20/15).

- REQ: Implement size categories: Tiny, Small, Medium, Large, Huge, Gargantuan.
- REQ: Each size has a defined space (in feet) and reach (tall vs. long creature variant).
- REQ: Moving through a creature's space: allowed if willing, or via Tumble Through (Acrobatics). Cannot end turn in another creature's space.
- REQ: Creatures ≥3 sizes larger/smaller: can move through each other's space.
- REQ: Tiny creatures: can occupy same space as larger creatures; can end movement there.

### Paragraph — Falling

> Fall damage = half the distance fallen (bludgeoning). Treat falls over 1,500 ft as 1,500 ft (750 damage). Land prone if taking fall damage. Fall ~500 ft per first round, ~1,500 ft each subsequent round.
> Soft surfaces: treat fall as 20 ft shorter (30 ft if intentional dive). Reduction can't exceed depth.
> Land on creature: Reflex DC 15. Crit success: no damage. Success: 1/4. Failure: 1/2. Crit fail: same as falling creature took.

- REQ: Fall damage = ½ distance in bludgeoning. Max 1,500 ft = 750 damage.
- REQ: Fall into soft surface (water, snow): treat fall as 20 ft (30 ft dive) shorter; reduction ≤ depth of substance.
- REQ: Land on creature: Reflex save DC 15 for that creature.
- REQ: Land prone on taking any fall damage.

### Paragraph — Forced Movement

> Forced movement distance is defined by the effect, not Speed. Doesn't trigger move reactions. Stops at first impassable square.

- REQ: Forced movement (push/pull effects) does not trigger move-triggered reactions.
- REQ: Forced movement stops at impassable terrain/squares.

### Paragraph — Difficult Terrain

> Difficult terrain: extra 5 ft per square (or per 5 ft moved). Greater difficult terrain: extra 10 ft per square. Diagonal extra cost is not doubled. Can't Step into difficult terrain.

- REQ: Difficult terrain: +5 ft movement cost per square.
- REQ: Greater difficult terrain: +10 ft movement cost per square (not doubled diagonally).
- REQ: Cannot Step into difficult terrain.
- REQ: Area effects not affected by difficult terrain.

### Paragraph — Flanking

> Two allies flank an enemy when a line between their centers passes through opposite sides/corners of the enemy's space, both can act, both can reach the target with melee.

- REQ: Flanking: two allies on opposite sides of a target; both must have melee reach to the target and be able to act.
- REQ: Flanked target is flat-footed (–2 circumstance to AC) to flanking creatures' melee attacks.

### Paragraph — Cover

> Lesser cover: +1 AC (can't hide). Standard cover: +2 AC, +2 Reflex vs. areas, +2 Stealth. Greater cover: +4 AC, +4 Reflex, +4 Stealth.
> Provided by: terrain for standard/greater, creature for lesser. Line from center to center of attacker/target determines cover.

- REQ: Three cover tiers: **lesser** (+1 AC, no hiding), **standard** (+2 AC/Reflex/Stealth, can hide), **greater** (+4 all, can hide).
- REQ: Cover determined by line from attacker center to target center. Passes through terrain = standard cover. Passes through creature = lesser cover. Extremely obscured = greater cover (GM discretion).
- REQ: Cover is relative per attacker/defender pair.

### Paragraph — Mounted Combat

> Rider and mount share MAP. Rider occupies all squares of mount's space for attack purposes. Mount acts on rider's initiative. Rider must use Command an Animal to get mount to use actions.

- REQ: Mounted rider shares MAP with mount.
- REQ: Mount acts on rider's initiative; requires Command an Animal to spend actions.
- REQ: Ride general feat: auto-success on Command an Animal for own mount.
- REQ: Rider takes –2 circumstance to Reflex saves while mounted. Only move action available is Mount (dismount).

### Paragraph — Aquatic Combat

> Flat-footed unless swim Speed. Resistance 5 acid/fire. –2 circumstance to slashing/bludgeoning melee through water. Bludgeoning/slashing ranged auto-miss if either party is underwater; piercing ranged: halved range increments.

- REQ: Aquatic combat modifiers: flat-footed (no swim Speed), resistance 5 acid/fire, –2 circumstance slashing/bludgeoning melee.
- REQ: Ranged bludgeoning/slashing auto-misses if attacker or target is underwater. Piercing ranged: half range increments.
- REQ: Cannot cast fire spells or use fire trait actions underwater.

### Paragraph — Drowning and Suffocating

> Hold breath: 5 + Con modifier rounds. Reduce by 1 per turn; by 2 if attacked or cast spells. Lose all air if speaking. At 0 air: fall unconscious, start suffocating. Fort save DC 20 at end of each turn: fail = 1d10 damage, crit fail = die. DC increases +5 per check; damage +1d10 each. Stops when air restored.

- REQ: Track held breath: 5 + Con mod rounds. –1 per turn; –2 if attacked or cast spells; –all if spoke.
- REQ: At 0 air: unconscious, begin suffocating. Fort DC 20 at turn end; fail = 1d10, crit fail = death. DC and damage increase cumulatively each check.

---

## SECTION: Senses and Detection

### Paragraph — Sense Types

> Precise senses (vision): can directly observe. Imprecise senses (hearing): detected creature is hidden, not observed. Vague senses (smell): at best, creature goes from unnoticed to undetected.

- REQ: Sense precision: **precise** (can observe), **imprecise** (creature hidden, not observed), **vague** (creature unnoticed → undetected only).
- REQ: Primary sense defaults: vision (precise), hearing (imprecise), smell (vague).

### Paragraph — Special Senses

> Darkvision: see in darkness (black & white). Greater darkvision: see through magical darkness. Low-light vision: treat dim light as bright light. Tremorsense (imprecise): detect movement via vibration on same surface.

- REQ: **Darkvision**: see normally in darkness/dim light (black & white). Some magical darkness blocks normal darkvision.
- REQ: **Greater darkvision**: see through all magical darkness.
- REQ: **Low-light vision**: treat dim light as bright light (no concealment from dim light).
- REQ: **Tremorsense** (imprecise): detect movement through vibrations on same surface; requires creature to be moving along/burrowing through that surface.
- REQ: **Scent** (vague typically): detect creatures/objects by smell; range varies; can be doubled/halved by wind.

### Paragraph — Light Levels

> Bright light: normal observation. Dim light: creatures/objects are concealed (DC 5 flat check to target). Darkness: creatures hidden/undetected without darkvision; blinded condition.

- REQ: Three light levels: bright (normal), dim (concealed — DC 5 flat check to target), darkness (blinded; creatures hidden or undetected).
- REQ: Light sources specify bright light radius; dim light extends to double that radius.

### Paragraph — Detection States

> Observed: precisely detected, can target normally. Hidden: know space, but flat-footed and DC 11 flat check to target. Undetected: don't know space; flat-footed; GM rolls secret attack + flat check. Unnoticed: totally unaware.

- REQ: Detection states: **observed** (normal targeting), **hidden** (know space, flat-footed, DC 11 flat check to attack), **undetected** (unknown space, flat-footed, GM-secret flat check + attack roll), **unnoticed** (no awareness).
- REQ: Concealed condition: DC 5 flat check before attack (regardless of observe/hidden status).
- REQ: Invisible condition: automatically undetected vs. sight-only perceivers. Can be found via Seek (hidden), then Sneak again to become undetected.

---

## SECTION: Hero Points

### Paragraph — Hero Point Rules

> Max 3 per session. Start with 1. Spend 1: reroll check (must use new result, fortune effect). Spend all: avoid death (stabilize at 0 HP, no wounded gained).

- REQ: Hero Points: max 3; gained per session; lost at session end.
- REQ: Spend 1 Hero Point: reroll any check, must use second result (fortune effect — can't stack another fortune).
- REQ: Spend all Hero Points: stabilize at 0 HP when dying value would increase; no wounded condition gained from this.
- REQ: Can be spent for a familiar or animal companion.

---

## SECTION: Encounter Mode Structure

### Paragraph — Initiative and Round Structure

> Roll initiative (usually Perception) to determine order. Ties: foe goes first; PC ties resolved between players. Rounds cycle until encounter ends.

- REQ: Roll initiative at start of encounter; usually Perception check.
- REQ: Initiative ranked highest to lowest. Enemy ties: foe goes first. PC ties: players decide.
- REQ: Order remains fixed unless Delay or knockout changes it.
- REQ: Each round: 6 seconds of in-world time.

### Paragraph — Changing Initiative Order

> Delay: remove from order, re-enter later (permanent new position). Knocked out: initiative shifted to just before being reduced to 0 HP. Ready: doesn't change order.

- REQ: Delay action: exit initiative, re-enter at chosen later position (permanent).
- REQ: Knockout at 0 HP: initiative position shifts.
- REQ: Ready action: position unchanged.

---

## SECTION: Exploration Mode

### Paragraph — Travel Speed

> TABLE 9–2: Speed 30 ft = 300 ft/min, 3 mph, 24 miles/day (flat terrain). Difficult terrain halves rate. Greater difficult = 1/3 rate.

- REQ: Travel speeds scale with land Speed (Table 9–2). Reference: Speed 30 = 24 miles/day.
- REQ: Difficult terrain halves travel speed. Greater difficult terrain = 1/3 travel speed.

### Paragraph — Exploration Activities

> Avoid Notice (half speed, Stealth check for initiative). Defend (half speed, Shield Raised). Detect Magic (half speed, continuous detect magic). Follow the Expert. Hustle (double speed, limited by Con mod). Investigate. Repeat a Spell. Scout (+1 circumstance initiative for party next encounter). Search (half speed, Seek for hazards/secrets).

- REQ: **Avoid Notice**: Stealth check; half speed; Stealth used for initiative instead of Perception at encounter start.
- REQ: **Defend**: half speed; shield Raised before first turn of any encounter entered.
- REQ: **Detect Magic**: half speed (or slower); automatic detection of magic auras.
- REQ: **Follow the Expert**: match ally's exploration activity; gain proficiency bonus as if trained + circumstance by ally rank.
- REQ: **Hustle**: double travel speed; limit = Con mod × 10 minutes (min 10 min).
- REQ: **Scout**: +1 circumstance bonus to all party initiative rolls in next encounter.
- REQ: **Search**: half speed; automatically Seek for hazards, secret doors, hidden objects.
- REQ: **Investigate**: half speed; secret Recall Knowledge checks for environmental clues.
- REQ: **Repeat a Spell**: half speed; sustain or re-cast a spell.

### Paragraph — Rest and Daily Preparations

> 8-hour rest: regain HP = Con mod (min 1) × level. Sleeping in armor = fatigued next day. >16 hours without rest = fatigued. Daily prep (1 hour after rest): regain spell slots, reset daily abilities, invest 10 magic items.

- REQ: 8-hour rest: regain HP = Con mod (min 1) × level.
- REQ: Sleeping in armor: fatigued on waking.
- REQ: >16 hours without rest: fatigued until resting ≥6 continuous hours.
- REQ: Daily preparations (1 hour, requires rest): regain spell slots, reset daily-use abilities, invest up to 10 worn magic items.
- REQ: Can only prepare once per 24 hours.

---

## SECTION: Downtime Mode

### Paragraph — Long-Term Rest

> Full day of rest: regain HP = Con mod (min 1) × 2 × level.

- REQ: Downtime long-term rest: regain HP = Con mod (min 1) × (2 × level).

### Paragraph — Retraining

> Spend 1 week to swap a feat (same type), a skill increase (same or lower rank), or a class feature choice (GM-determined time). Cannot retrain ancestry, heritage, background, class, or ability scores.

- REQ: **Retraining** (downtime): 1 week to swap one feat (same type/level restrictions), skill increase (same or lower rank), or class feature choice.
- REQ: Cannot retrain: ancestry, heritage, background, class, ability scores.
- REQ: Larger changes (e.g., druid order, wizard school): at least 1 month per GM.
- REQ: Cannot perform other downtime activities while retraining.

---
*Source: PF2E Core Rulebook Ch.9, lines 68673–73974 | Extracted for DungeonCrawler system requirements*
