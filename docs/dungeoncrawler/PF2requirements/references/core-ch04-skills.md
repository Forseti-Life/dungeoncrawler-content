# PF2E Core Rulebook — Chapter 4: Skills
## Requirements Extraction

---

## SECTION: Chapter Overview

### Paragraph — Skill System Introduction

> Skills represent training and experience at performing certain tasks. Each skill is keyed to one of your character's ability scores and used for an array of related actions. Expertise comes from background, class, and choices made during advancement.

- REQ: Each skill must be associated with one primary ability score (Str/Dex/Con/Int/Wis/Cha)
- REQ: Characters gain initial skill training from: background (typically 2), class (fixed list + choice pool)
- REQ: If trained in the same skill from multiple sources, redirect the redundant training to any other skill; Lore redirects must stay within Lore subtypes
- REQ: Skills have two tiers of actions: untrained (any character) and trained (proficiency rank ≥ trained required)

### Paragraph — Key Ability & Skill Modifier

> Each skill is tied to a key ability. You add that ability modifier to checks and DCs when using that skill.
> Skill modifier = key ability modifier + proficiency bonus + other bonuses + penalties

- REQ: Skill check = d20 + skill modifier (key ability mod + proficiency bonus + bonuses/penalties)
- REQ: Skill DC = 10 + skill modifier (opponents roll against this)
- REQ: GM may substitute a different ability modifier for a skill check when situationally appropriate

### Paragraph — Skill DCs (Simple)

> Simple skill DCs by proficiency tier:
> Untrained: 10 | Trained: 15 | Expert: 20 | Master: 30 | Legendary: 40

- REQ: System must expose a Simple DC table by proficiency rank for quick GM/system adjudication
- REQ: Five proficiency tiers map to fixed simple DCs: untrained=10, trained=15, expert=20, master=30, legendary=40

### Paragraph — Skill Increases

> Skill increases allow characters to improve proficiency in skills of their choice: become trained in a new skill, or increase existing rank (any→expert at any level; expert→master at 7th+; master→legendary at 15th+). If two abilities would both raise a skill to the same rank, the redundant benefit is lost.

- REQ: Skill increases are granted by class at specific levels
- REQ: Skill increases can raise untrained→trained at any level
- REQ: Skill increases can raise trained→expert at any level
- REQ: Expert→master upgrade requires character level ≥ 7
- REQ: Master→legendary upgrade requires character level ≥ 15
- REQ: Redundant rank upgrades (two sources granting same rank) provide no benefit; no replacement pick

### Paragraph — Armor and Skills

> Armor imposes a check penalty on Strength- and Dexterity-based skill checks and skill DCs, unless the action has the attack trait.

- REQ: Worn armor may apply a check penalty to Str-based and Dex-based skill checks and DCs
- REQ: Armor check penalty does NOT apply to actions with the attack trait

### Paragraph — Secret Checks

> If an action has the secret trait, the GM rolls the check and informs the player of the effect without revealing the die result or degree of success. Used when player knowledge about the outcome should be imperfect (searching for hidden creatures, deception, ancient text translation, lore recall).

- REQ: Actions flagged with the secret trait should have the roll resolved by the system/GM without exposing the numerical result to the player
- REQ: GM/system may opt to make secret rolls public

### Paragraph — Exploration and Downtime Activities

> Some skill activities have the exploration trait (usually ≥ 1 minute) or the downtime trait (usually ≥ 1 day). These normally cannot be used during an encounter.

- REQ: Skills must support activity traits: exploration (≥1 min), downtime (≥1 day), encounter (action-based)
- REQ: Exploration and downtime activities are blocked during encounters by default (GM exception possible)

---

## SECTION: General Skill Actions

### Paragraph — Overview

> General skill actions can be used with multiple different skills. The applicable skill depends on the situation.
> General actions: Decipher Writing (T), Earn Income (T), Identify Magic (T), Learn a Spell (T), Recall Knowledge (U), Subsist (U)

- REQ: A set of general skill actions must exist, each usable with multiple relevant skills
- REQ: Recall Knowledge and Subsist are untrained; all others require trained proficiency

### Paragraph — Decipher Writing (Trained, Exploration, Secret)

> Attempt to decipher complicated writing on an obscure topic. 1 minute per page (hours for ciphers). Text must be in a language you can read. DC set by GM based on complexity. Arcana=magic/science, Occultism=esoteric/philosophy, Religion=scripture, Society=codes/archaic docs.
> Critical Success: Understand true meaning. Success: Understand true meaning (coded docs: general meaning, not word-for-word). Failure: Can't understand; –2 circumstance penalty to further attempts. Critical Failure: Believe you understand but have misconstrued the message.

- REQ: Decipher Writing action requires trained proficiency, is exploration, has secret trait
- REQ: Time cost: 1 minute per page (longer for ciphers, ~1 hour per page)
- REQ: Character must be able to read the language; Society may allow attempt in unfamiliar language at GM discretion
- REQ: Degrees of success: Crit Success=full meaning; Success=true meaning (coded=general); Fail=blocked + –2 penalty to retry; Crit Fail=false understanding (player believes they succeeded)
- REQ: Applicable skills: Arcana, Occultism, Religion, Society

### Paragraph — Earn Income (Trained, Downtime)

> Spend time during downtime to earn money. Use the skill to find work at a task level up to your level (or half level for performing art). Roll the skill check to determine your income per day over the downtime period.
> Critical Success: Earn full income + access to higher-level work. Success: Earn standard income for task level. Failure: Earn lower income. Critical Failure: No income for that day; bad reputation possible.

- REQ: Earn Income is a downtime action requiring trained proficiency
- REQ: Characters can find work at a task level up to their character level
- REQ: Income rate is determined by task level and degree of success (critical success earns more, failure earns less, critical failure earns nothing)
- REQ: Applicable skills: Arcana, Crafting, Lore, Occultism, Performance, Religion, Society

### Paragraph — Identify Magic (Trained, Exploration)

> Spend 10 minutes examining a magic item, location, or ongoing effect to determine its properties. The DC is usually the standard DC for the item or effect's level. Using the wrong skill for the tradition is allowed but at +5 DC.
> Crit Success: Identify fully + learn one random additional fact. Success: Identify fully. Failure: Can't identify; try again after 1 day. Crit Fail: Misidentify the magic (false info, appears to succeed).

- REQ: Identify Magic action requires trained, is exploration, takes 10 minutes
- REQ: DC = standard DC for item/effect level; +5 DC penalty if using wrong tradition skill
- REQ: Crit Success=full ID + bonus fact; Success=full ID; Fail=blocked for 1 day; Crit Fail=false identification (secret trait consequence)
- REQ: Applicable skills: Arcana (arcane), Nature (primal), Occultism (occult), Religion (divine)

### Paragraph — Learn a Spell (Trained, Exploration)

> Spend 1 hour with a magical source (teacher, book, scroll) to attempt to learn a spell and add it to your spellbook or repertoire. Cost: spell level × 10 gp in materials (consumed on attempt). DC = standard spell DC for the spell's level.
> Crit Success: Learn the spell; materials cost reduced by half. Success: Learn the spell. Failure: Don't learn; materials are not expended. Crit Fail: Don't learn; materials are expended.

- REQ: Learn a Spell requires trained, is exploration, takes 1 hour
- REQ: Material cost = spell level × 10 gp (consumed on attempt depending on result)
- REQ: DC = standard DC for the spell's level
- REQ: Crit Success=learn + refund half cost; Success=learn, full cost; Fail=don't learn, no cost; Crit Fail=don't learn, lose materials
- REQ: Applicable skills: Arcana (arcane), Nature (primal), Occultism (occult), Religion (divine)

### Paragraph — Recall Knowledge (Untrained, One Action)

> Attempt a skill check to remember a fact about the topic associated with the skill. Secret check; DC set by GM.
> Crit Success: Recall the knowledge + learn one extra piece of info. Success: Recall the knowledge. Failure: Can't recall. Crit Fail: Recall incorrectly (false info, appears to succeed).

- REQ: Recall Knowledge is a 1-action untrained action available on many skills (secret check)
- REQ: DC set by GM based on obscurity of information
- REQ: Crit Success=info + bonus fact; Success=accurate info; Fail=nothing; Crit Fail=false info (player does not know it's false)
- REQ: Applicable skills: Arcana, Crafting, Lore, Medicine, Nature, Occultism, Religion, Society (and any other skill per GM)

### Paragraph — Subsist (Untrained, Downtime)

> Attempt to find food and shelter in the wild or a city. DC 15 usually (may be higher in harsh environments). You can Subsist for yourself or for a group (party of 4 = +2 DC per additional person past first).
> Crit Success: Subsist without issue + provide for additional creatures. Success: Subsist without issue. Failure: Subsist but only barely. Crit Fail: Fail to subsist; take a –1 penalty to Fortitude saves until you eat a proper meal.

- REQ: Subsist is untrained, downtime action
- REQ: Base DC 15; higher in harsh environments; +2 DC per additional creature after first
- REQ: Crit Success=full provision for group; Success=self fed; Fail=meager subsistence; Crit Fail=starvation (–1 Fort save until fed)
- REQ: Applicable skills: Nature (wilderness), Society (urban)

---

---

## SECTION: Skill Table (4-1 Summary)

### Paragraph — Skills, Key Abilities, and Actions

> All 17 skills with key ability, untrained actions, and trained actions.

| Skill | Key Ability | Untrained Actions | Trained Actions |
|-------|-------------|-------------------|-----------------|
| Acrobatics | Dexterity | Balance, Tumble Through | Maneuver in Flight, Squeeze |
| Arcana | Intelligence | Recall Knowledge | Borrow Arcane Spell, Decipher Writing, Identify Magic, Learn a Spell |
| Athletics | Strength | Climb, Force Open, Grapple, High Jump, Long Jump, Shove, Swim, Trip | Disarm |
| Crafting | Intelligence | Recall Knowledge, Repair | Craft, Earn Income, Identify Alchemy |
| Deception | Charisma | Create a Diversion, Impersonate, Lie | Feint |
| Diplomacy | Charisma | Gather Information, Make an Impression, Request | — |
| Intimidation | Charisma | Coerce, Demoralize | — |
| Lore | Intelligence | Recall Knowledge | Earn Income |
| Medicine | Wisdom | Administer First Aid, Recall Knowledge | Treat Disease, Treat Poison, Treat Wounds |
| Nature | Wisdom | Command an Animal, Recall Knowledge | Identify Magic, Learn a Spell |
| Occultism | Intelligence | Recall Knowledge | Decipher Writing, Identify Magic, Learn a Spell |
| Performance | Charisma | Perform | Earn Income |
| Religion | Wisdom | Recall Knowledge | Decipher Writing, Identify Magic, Learn a Spell |
| Society | Intelligence | Recall Knowledge, Subsist | Create Forgery, Decipher Writing |
| Stealth | Dexterity | Conceal an Object, Hide, Sneak | — |
| Survival | Wisdom | Sense Direction, Subsist | Cover Tracks, Track |
| Thievery | Dexterity | Palm an Object, Steal | Disable a Device, Pick a Lock |

- REQ: System must implement all 17 skills with their respective key abilities
- REQ: Each skill must gate trained-only actions behind proficiency rank ≥ trained
- REQ: Escape basic action may use Acrobatics or Athletics modifier in place of unarmed attack modifier

---

## SECTION: Acrobatics (Dex)

### Paragraph — Identity

> Measures ability to perform tasks requiring coordination and grace. When using Escape, may use Acrobatics modifier instead of unarmed attack modifier.

- REQ: Acrobatics is the key skill for coordinated movement tasks
- REQ: Escape action accepts Acrobatics modifier as alternative to unarmed attack modifier

### Paragraph — Balance [1 action, Move]

> Move across narrow surfaces or uneven ground. Flat-footed while doing so. Attempt check vs Balance DC.
> Crit Success: Move up to Speed. Success: Move up to Speed, treated as difficult terrain. Fail: Remain stationary or fall (turn ends). Crit Fail: Fall, turn ends.

- REQ: Balance is a 1-action move; character is flat-footed during it
- REQ: Degrees: Crit=full speed; Success=full speed + difficult terrain; Fail=stop or fall; Crit Fail=fall + end turn
- REQ: Sample DCs: Untrained=roots/cobblestones; Trained=wooden beam; Expert=gravel; Master=tightrope; Legendary=razor edge

### Paragraph — Tumble Through [1 action, Move]

> Stride up to Speed, attempting to move through one enemy's space. Roll Acrobatics vs enemy's Reflex DC upon entering.
> Success: Move through (difficult terrain). Fail: Movement ends, triggers reactions.

- REQ: Tumble Through allows movement through enemy space; must enter their space to trigger check
- REQ: Can substitute Climb, Fly, Swim etc. for Stride in appropriate environment
- REQ: Success=pass through (difficult terrain); Fail=movement stops + triggers reactions

### Paragraph — Maneuver in Flight [1 action, Move, Trained]

> Requires fly Speed. Attempt difficult aerial maneuver. GM determines what's possible (can't exceed fly Speed).
> Success: Maneuver succeeds. Fail: Maneuver fails (can't move or other consequence). Crit Fail: More dire consequence.

- REQ: Maneuver in Flight requires fly Speed and trained proficiency
- REQ: Sample DCs: Trained=steep ascent/descent; Expert=against wind/hover; Master=reverse direction; Legendary=gale force winds

### Paragraph — Squeeze [Exploration, Trained]

> Contort to fit through a space too small to normally fit. 1 minute per 5 feet (crit success: 1 minute per 10 feet). Crit Fail: Become stuck (1 minute + check to escape; any non-crit-fail frees you).

- REQ: Squeeze is exploration; costs 1 min/5 ft (crit success: 1 min/10 ft)
- REQ: Critical failure sticks the character; they can escape with a follow-up check (any non-critical-fail result frees them)
- REQ: Sample DCs: Trained=barely fits shoulders; Master=barely fits head

---

## SECTION: Arcana (Int)

### Paragraph — Identity

> Measures knowledge about arcane magic and creatures. Untrained can still Recall Knowledge.
> Recall Knowledge topics: arcane theories, magic traditions, arcane creatures (dragons, beasts), Elemental/Astral/Shadow Planes.

- REQ: Arcana covers arcane magic knowledge, arcane creature identification, planar lore (Elemental, Astral, Shadow)
- REQ: Untrained characters may use Arcana to Recall Knowledge

### Paragraph — Borrow an Arcane Spell [Exploration, Trained]

> Arcane prepared spellcasters only. Attempt to prepare a spell from another's spellbook. DC set by spell level and rarity (slightly easier than Learn a Spell).
> Success: Prepare borrowed spell in normal spell preparation. Fail: Slot remains available, can't try again until next spell prep.

- REQ: Borrow Arcane Spell requires trained Arcana; only usable by arcane prepared spellcasters (spellbook users)
- REQ: Success=prepare borrowed spell normally; Fail=slot stays open, retry blocked until next prep cycle

---

## SECTION: Athletics (Str)

### Paragraph — Identity

> Perform deeds of physical prowess. Escape action may use Athletics modifier instead of unarmed attack modifier.

- REQ: Athletics covers physical prowess; Escape accepts Athletics modifier alternative

### Paragraph — Climb [1 action, Move]

> Move up/across/down an incline. Flat-footed unless you have a climb Speed.
> Crit Success: Move 5 ft + 5 ft per 20 ft Speed. Success: 5 ft per 20 ft Speed. Fail: No progress. Crit Fail: Fall, land prone.

- REQ: Climb is 1-action move; character is flat-footed without a climb Speed
- REQ: Movement distance scales with land Speed (standard character = ~5 ft success, ~10 ft crit success)
- REQ: Crit fail results in fall and prone

### Paragraph — Force Open [1 action, Attack]

> Forcefully open a door, window, container, gate, or wall. Without a crowbar: –2 item penalty.
> Crit Success: Open without damage. Success: Open but gains broken condition. Crit Fail: Jammed shut (–2 circumstance to future attempts).

- REQ: Force Open has attack trait; –2 item penalty without crowbar
- REQ: Success makes the object broken; Crit Success opens without damage; Crit Fail jams it + –2 to future attempts

### Paragraph — Grapple [1 action, Attack]

> Grab a creature. Requires free hand or already grappling/restraining; target no more than 1 size larger. Roll vs target's Fortitude DC.
> Crit Success: Target restrained until end of next turn (unless you move or target Escapes). Success: Grabbed until end of next turn. Fail: No grab (releases if already had it). Crit Fail: Target breaks free; may grab you or knock you prone.

- REQ: Grapple requires 1 free hand (or existing grapple/restrain); size limit = 1 size larger
- REQ: Degrees: Crit=restrained; Success=grabbed; Fail=release; Crit Fail=target may grab you or knock you prone
- REQ: Grabbed and restrained conditions last until end of next turn; broken by moving or target Escaping

### Paragraph — High Jump [2 actions]

> Stride then vertical Leap. DC 30 Athletics to increase jump height. Must Stride ≥ 10 ft or auto-fail.
> Crit Success: 8 ft vertical (or 5 ft vertical + 10 ft horizontal). Success: 5 ft vertical. Fail: Normal Leap. Crit Fail: Don't leap; fall prone.

- REQ: High Jump costs 2 actions; requires ≥10 ft Stride or auto-fail
- REQ: Base DC 30; Crit Success=8 ft vertical (or 5+10); Success=5 ft; Fail=normal Leap; Crit Fail=prone

### Paragraph — Long Jump [2 actions]

> Stride then horizontal Leap. DC = desired distance in feet. Must Stride ≥ 10 ft and in same direction. Can't exceed Speed.
> Success: Leap desired distance. Fail: Normal Leap. Crit Fail: Normal Leap then fall prone.

- REQ: Long Jump costs 2 actions; DC = distance in feet; must Stride ≥10 ft in same direction
- REQ: Maximum distance = character Speed; Crit Fail = normal leap + prone

### Paragraph — Shove [1 action, Attack]

> Push target away. Requires 1 free hand; target no more than 1 size larger. Roll vs target's Fortitude DC.
> Crit Success: Push 10 ft away (may Stride after it). Success: Push 5 ft (may Stride after). Crit Fail: Fall prone.

- REQ: Shove has attack trait; forced movement doesn't trigger movement reactions
- REQ: Crit Success=10 ft push; Success=5 ft push; Crit Fail=fall prone; may follow target with Stride

### Paragraph — Swim [1 action, Move]

> Propel through water. In calm water, succeed without a check. Must hold breath if air-breathing while submerged; fail to hold breath = begin drowning.
> If not a Swim action at end of turn: sink 10 ft or move with current.
> Crit Success: 10 ft + 5 ft per 20 ft Speed. Success: 5 ft + 5 ft per 20 ft Speed. Crit Fail: No progress; lose 1 round of air if holding breath.

- REQ: Swim is 1-action move; no check needed in calm water
- REQ: Air-breathing characters must hold breath each round while submerged
- REQ: If no Swim action at turn end: sink 10 ft or drift with current (not on turn entering water)
- REQ: Crit Fail costs 1 round of held breath

### Paragraph — Trip [1 action, Attack]

> Knock target prone. Requires free hand; target no more than 1 size larger. Roll vs target's Reflex DC.
> Crit Success: Target falls prone + 1d6 bludgeoning damage. Success: Target falls prone. Crit Fail: You fall prone.

- REQ: Trip has attack trait; Crit Success deals 1d6 bludgeoning + prone; Success=prone only; Crit Fail=attacker prone

### Paragraph — Disarm [1 action, Attack, Trained]

> Remove object from creature's grasp. Requires 1 free hand; target no more than 1 size larger. Roll vs target's Reflex DC.
> Crit Success: Item falls to ground in target's space. Success: Item grip weakened (+2 to further Disarm attempts; target –2 to attacks/checks requiring firm grip until start of their turn). Crit Fail: You become flat-footed until start of your next turn.

- REQ: Disarm requires trained Athletics; has attack trait
- REQ: Crit Success=item dropped; Success=grip weakened (bonuses/penalties until start of their turn); Crit Fail=attacker flat-footed

### Paragraph — Falling Damage

> Taking falling damage: bludgeoning = half distance fallen. Knocked prone. Falling into soft substance (water/snow): treat fall as 20 ft shorter (capped at depth of substance).

- REQ: Falling damage = half distance in bludgeoning; character lands prone
- REQ: Soft landing surfaces (water, snow) reduce effective fall distance by up to 20 ft (capped at surface depth)
- REQ: Grab an Edge reaction can reduce/eliminate fall damage

---

## SECTION: Crafting (Int)

### Paragraph — Identity

> Create and repair items. Untrained may Recall Knowledge (alchemical reactions, item values, engineering, unusual materials, constructs).

- REQ: Crafting covers item creation/repair; Recall Knowledge topics = alchemy, engineering, item values, construct creatures

### Paragraph — Repair [Exploration, Trained]

> Spend 10 min to restore HP to a damaged item. Requires repair kit. DC ≈ craft DC for that item. Cannot repair destroyed items.
> Crit Success: Restore 10 HP + 10 per proficiency rank (trained=20, expert=30, master=40, legendary=50). Success: 5 HP + 5 per rank (trained=10, expert=15, master=20, legendary=25). Crit Fail: Deal 2d6 damage to item (reduced by Hardness).

- REQ: Repair requires repair kit, trained Crafting, 10 minutes
- REQ: HP restoration scales with proficiency rank (crit: 10+10/rank; success: 5+5/rank)
- REQ: Crit Fail deals 2d6 to the item (after Hardness)
- REQ: Destroyed items cannot be Repaired

### Paragraph — Craft [Downtime, Trained]

> Create an item from raw materials. Prerequisites: item ≤ character level; have formula; proper tools + workshop; supply ≥50% of item Price in raw materials. Spend 4 days then roll.
> Items level 9+: must be master in Crafting. Items level 16+: must be legendary.
> Crit Success: Item complete; additional days reduce remaining cost by level+1 + proficiency rate. Success: Item complete; additional days reduce at level + proficiency rate. Fail: No item; salvage all materials. Crit Fail: No item; 10% materials ruined, rest salvageable.
> Consumables: Craft up to 4 identical items in one batch. Non-magical ammunition crafted in listed batch quantities (typically 10).

- REQ: Craft is downtime; requires trained, formula, tools/workshop, ≥50% raw material cost upfront
- REQ: Item level cap = character level; level 9+ items require master; level 16+ require legendary
- REQ: Minimum 4 days; additional days reduce remaining cost; can pause and resume later
- REQ: Crit Success=faster cost reduction (level+1 rate); Success=normal reduction; Fail=salvage all; Crit Fail=10% material loss
- REQ: Consumables: batch up to 4 identical items per check; ammunition in standard batch quantities
- REQ: Special feats required for: Alchemical Crafting (alchemical items), Magical Crafting (magic items), Snare Crafting (snares)

### Paragraph — Identify Alchemy [Exploration, Trained]

> 10 minutes using alchemist's tools to identify an alchemical item. Must not be interrupted.
> Success: Identify item and activation method. Fail: No ID; can retry. Crit Fail: Misidentify as another item.

- REQ: Identify Alchemy requires trained Crafting, alchemist's tools, 10 min uninterrupted
- REQ: Crit Fail produces false identification

---

## SECTION: Deception (Cha)

### Paragraph — Identity

> Trick and mislead others using disguises, lies, and subterfuge.

### Paragraph — Create a Diversion [1 action]

> Draw creatures' attention elsewhere with gesture/trick (manipulate trait) or words (auditory + linguistic traits). Roll vs each target's Perception DC. +4 circumstance bonus to targets' Perception DCs vs future Diversion attempts for 1 minute.
> Success: Become hidden to creatures whose Perception DC ≤ result. Lasts until end of turn or until non-Hide/Sneak/Step action. Striking makes you observed after the attack. Fail: No effect on targets that beat the result; those targets aware you were trying to deceive.

- REQ: Create a Diversion is 1 action with manipulate or auditory+linguistic+mental traits (based on method)
- REQ: +4 circumstance bonus to all attempted targets' Perception DCs for 1 minute after attempting
- REQ: On success, character becomes hidden (not undetected); reverts to observed on most actions except Hide, Sneak, Step
- REQ: Striking while hidden: target is flat-footed for that attack, then character becomes observed

### Paragraph — Impersonate [Exploration, Secret]

> Create a disguise to pass as someone/something else. Takes 10 minutes + disguise kit. Creatures detect via Seek (Perception vs Deception DC). Direct interaction = secret Deception check vs Perception DC.
> Success: Target believes disguise. Fail: Target sees through it. Crit Fail: Target sees through it AND recognizes you if they'd know you undisguised.

- REQ: Impersonate is exploration; requires 10 minutes + disguise kit
- REQ: Passive observers check Perception vs Deception DC; active searchers use Seek
- REQ: Crit Fail reveals character's true identity to observers who would recognize them

### Paragraph — Lie [Auditory, Linguistic, Secret]

> Fool someone with an untruth (≥1 round, longer if elaborate). Roll once vs all targets' Perception DCs.
> Success: Target believes lie. Fail: Doesn't believe; +4 circumstance bonus to future Lie attempts against this target for the conversation.

- REQ: Lie is secret check; roll once compared to multiple targets' Perception DCs
- REQ: Failure grants target +4 circumstance bonus to resist future lies for the conversation
- REQ: GM may allow delayed recheck if creature later encounters contradicting evidence

### Paragraph — Feint [1 action, Mental, Trained]

> Within melee reach, mislead opponent with a gesture. Roll Deception vs target's Perception DC.
> Crit Success: Target flat-footed vs all your melee attacks until end of your next turn. Success: Target flat-footed vs next melee attack this turn only. Crit Fail: You are flat-footed vs target's melee attacks until end of your next turn.

- REQ: Feint is 1 action, mental trait, requires trained Deception, requires melee reach
- REQ: Crit Success=flat-footed for full turn of attacks; Success=flat-footed for one attack; Crit Fail=backfire (attacker flat-footed)

---

## SECTION: Diplomacy (Cha)

### Paragraph — Identity

> Influence others through negotiation and flattery.

### Paragraph — Gather Information [Exploration, Secret]

> Canvass markets, taverns, gathering places for info about an individual or topic. Typically 2 hours (may vary). Bribes/gifts may help.
> Success: Collect information (specifics per GM). Crit Fail: Collect incorrect information.

- REQ: Gather Information is exploration, secret; typical time cost ~2 hours
- REQ: Crit Fail produces false information
- REQ: Sample DCs: Untrained=town gossip; Trained=common rumor; Expert=obscure/guarded secret; Master=well-guarded/esoteric; Legendary=known only to select/extraordinary beings

### Paragraph — Make an Impression [Exploration, Auditory+Linguistic+Mental]

> 1+ minute conversation to improve attitude. Roll vs target's Will DC.
> Crit Success: Attitude improves 2 steps. Success: Improves 1 step. Crit Fail: Decreases 1 step. Impressions last for current social interaction (GM may extend).

- REQ: Make an Impression is exploration, takes ≥1 minute, rolls vs Will DC
- REQ: Five NPC attitudes: Helpful → Friendly → Indifferent → Unfriendly → Hostile
- REQ: Crit Success=+2 steps; Success=+1 step; Crit Fail=–1 step
- REQ: PC attitudes cannot be changed by these skill actions (player controls their own character's reactions)

### Paragraph — Request [1 action, Auditory+Linguistic+Mental]

> Make a request of a Friendly or Helpful creature. GM sets DC based on request difficulty.
> Crit Success: Agreement without qualifications. Success: Agreement with possible conditions. Fail: Refusal (may propose alternative). Crit Fail: Refusal + attitude decreases 1 step.

- REQ: Request requires target to be Friendly or Helpful; cannot be used on Indifferent or lower
- REQ: Crit Fail decreases target's attitude by 1 step

---

## SECTION: Intimidation (Cha)

### Paragraph — Identity

> Bend others to will using threats.

### Paragraph — Coerce [Exploration, Auditory+Linguistic+Mental+Emotion]

> 1+ minute threatening conversation. Roll vs target's Will DC.
> Crit Success: Target complies ≤1 day, then becomes Unfriendly (too scared to retaliate short-term). Success: Same but target may act against you after becoming Unfriendly. Fail: Refuses; becomes Unfriendly if wasn't already. Crit Fail: Refuses; becomes Hostile; immune to your Coerce for ≥1 week.

- REQ: Coerce is exploration; takes ≥1 minute; rolls vs Will DC
- REQ: Compliance window ≤1 day; then attitude drops to Unfriendly regardless of result
- REQ: Crit Fail creates 1-week immunity to Coerce from that character

### Paragraph — Demoralize [1 action, Auditory+Emotion+Fear+Mental]

> Shake resolve of one creature within 30 feet. Roll Intimidation vs target's Will DC. –4 penalty if target doesn't understand your language. Target immune to your Demoralize for 10 minutes regardless of result.
> Crit Success: Target frightened 2. Success: Target frightened 1.

- REQ: Demoralize is 1 action, range 30 ft, requires shared language (–4 penalty otherwise)
- REQ: Target automatically becomes immune to Demoralize from this character for 10 minutes after attempt
- REQ: Crit Success=frightened 2; Success=frightened 1; Fail=no effect

---

## SECTION: Lore (Int)

### Paragraph — Identity

> Specialized knowledge on a narrow topic. Each subcategory is its own skill. Backgrounds grant a specific Lore subcategory. Lore subcategories must be narrower than other Recall Knowledge skills and cannot replace them.
> Common Lore subcategories: Academia, Accounting, Architecture, Art, Circus, Engineering, Farming, Fishing, Fortune-Telling, Games, Genealogy, Gladiatorial, Guild, Heraldry, Herbalism, Hunting, Labor, Legal, Library, deity Lore, creature/type Lore, plane Lore, settlement Lore, terrain Lore, food/drink Lore, Mercantile, Midwifery, Milling, Mining, Sailing, Scouting, Scribing, Stabling, Tanning, Theater, Underworld, Warfare.

- REQ: Lore is implemented as a family of narrow-topic skills; each subcategory tracked separately
- REQ: Lore subcategories cannot be broader than other knowledge skills; no "Magic Lore" or "Adventuring Lore"
- REQ: When multiple Lore subcategories could apply, character may use the better modifier
- REQ: Earn Income is the only trained action for Lore (using knowledge to practice a trade)

---

## SECTION: Medicine (Wis)

### Paragraph — Identity

> Patch up wounds; recover from disease and poison. Untrained can Recall Knowledge (diseases, injuries, poisons, ailments; forensic examination with 10+ min).

### Paragraph — Administer First Aid [2 actions, Manipulate]

> Requires healer's tools. On adjacent creature that is dying or bleeding. Choose one ailment to address per use.
> Stabilize (dying creature at 0 HP): DC = 5 + recovery roll DC (usually 15 + dying value). Success: creature loses dying condition (stays unconscious). Crit Fail: dying value increases by 1.
> Stop Bleeding (persistent bleed): DC = DC of effect causing bleed. Success: creature attempts flat check to end bleeding. Crit Fail: creature immediately takes its persistent bleed damage.

- REQ: Administer First Aid is 2 actions; requires healer's tools; target must be adjacent
- REQ: One ailment addressed per use (dying or bleeding, not both at once)
- REQ: Stabilize DC = 5 + (10 + dying value) = 15 + dying value typically
- REQ: Stop Bleeding grants target a flat check to remove bleed; Crit Fail immediately triggers bleed damage

### Paragraph — Treat Disease [Downtime, Trained]

> 8 hours caring for diseased creature. Requires healer's tools. Roll vs disease DC. Can only attempt once per creature per save cycle.
> Crit Success: +4 circumstance to next save vs disease. Success: +2. Crit Fail: –2 penalty.

- REQ: Treat Disease is downtime; requires ≥8 hours + healer's tools; one attempt per save cycle
- REQ: Bonuses/penalties apply to next save only

### Paragraph — Treat Poison [1 action, Trained]

> Requires healer's tools. Roll vs poison DC. Once per creature per save attempt.
> Crit Success: +4 circumstance to next save. Success: +2. Crit Fail: –2 penalty.

- REQ: Treat Poison is 1 action; requires healer's tools; one attempt per creature per poison save

### Paragraph — Treat Wounds [Exploration, Trained]

> 10 minutes on one injured living creature (can self-treat). Target immune to further Treat Wounds for 1 hour (overlapping with treatment time = once per hour).
> Base DC 15. Expert: may attempt DC 20 (+10 HP). Master: DC 30 (+30 HP). Legendary: DC 40 (+50 HP).
> Crit Success: 4d8 HP + remove wounded condition. Success: 2d8 HP + remove wounded condition. Crit Fail: Target takes 1d8 damage.
> Optional: treat for 1 full hour → double HP regained.

- REQ: Treat Wounds is exploration; requires healer's tools; 10 min; once per hour per patient (1-hr immunity window)
- REQ: Base: DC 15 / 2d8 HP. Expert option: DC 20 / +10 HP. Master option: DC 30 / +30 HP. Legendary option: DC 40 / +50 HP
- REQ: Crit Success removes wounded condition + heals; Success heals + removes wounded; Crit Fail deals 1d8 damage
- REQ: Extended 1-hour treatment doubles HP healed

---

## SECTION: Nature (Wis)

### Paragraph — Identity

> Knowledge of natural world; command and train animals/magical beasts. Untrained can Recall Knowledge (fauna, flora, geography, weather, environment, animals, beasts, fey, plants, First World, Material Plane, Elemental Planes).

### Paragraph — Command an Animal [1 action, Auditory+Concentrate]

> Issue order to an animal. Roll Nature vs animal's Will DC. GM may adjust DC based on attitude. Auto-fail if animal is Hostile or Unfriendly; Helpful = degree of success improves one step.
> Animals know: Drop Prone, Leap, Seek, Stand, Stride, Strike. May know activities (e.g., Gallop).
> Success: Animal acts as commanded on next turn. Fail: Animal does nothing. Crit Fail: Animal takes other action (GM determines).
> Multiple commands: executed in order on next turn; only what fits in that turn; forgets remainder.

- REQ: Command an Animal is 1 action (or more for multi-action activities); Auditory + Concentrate traits
- REQ: Auto-fail if animal is Hostile/Unfriendly; Helpful improves degree of success by 1 step
- REQ: Animals know Drop Prone, Leap, Seek, Stand, Stride, Strike by default; may know additional activities
- REQ: Commands executed in order on animal's next turn; commands beyond turn capacity are forgotten
- REQ: If multiple people command same animal, GM resolves conflict

---

## SECTION: Occultism (Int)

### Paragraph — Identity

> Ancient philosophies, esoteric lore, obscure mysticism, supernatural creatures. Untrained can Recall Knowledge (ancient mysteries, obscure philosophies, aberrations, spirits, oozes; Positive Energy/Negative Energy/Shadow/Astral/Ethereal Planes).

- REQ: Occultism trained actions: Decipher Writing (occult topics: metaphysics, syncretic principles, weird philosophies), Identify Magic (occult), Learn a Spell (occult tradition)

---

## SECTION: Performance (Cha)

### Paragraph — Identity

> Skilled at a form of performance, impressing crowds or earning a living. Actions gain additional traits based on performance type: Act/comedy=auditory+linguistic+visual; Dance=move+visual; Play instrument=auditory+manipulate; Orate/sing=auditory+linguistic.
> Basic Competence: if relevant ability score is negative, GM may apply penalty (e.g., dancing with negative Dex).

- REQ: Performance actions must carry appropriate traits based on type (auditory, visual, manipulate, linguistic, move)
- REQ: Negative ability scores relevant to performance type may impose GM-set penalties

### Paragraph — Perform [1 action, Concentrate]

> Brief performance (one song, dance, jokes). Proves capability, may influence Diplomacy DCs or attitudes.
> Crit Success: Impresses observers; word of your ability spreads. Success: Appreciated. Fail: Falls flat. Crit Fail: Demonstrates incompetence.

- REQ: Perform is 1-action; outcome may affect subsequent Diplomacy DCs or attitudes per GM
- REQ: Sample audience DCs: Untrained=commoners; Trained=artisans; Expert=merchants/minor nobles; Master=high nobility/minor royalty; Legendary=major royalty/otherworldly beings

---

## SECTION: Religion (Wis)

### Paragraph — Identity

> Knowledge of deities, dogma, faith, divine creatures. Untrained can Recall Knowledge (divine agents, theology, myths, celestials, fiends, undead, Outer Sphere, Positive/Negative Energy Planes).

- REQ: Religion trained actions: Decipher Writing (religious: allegories, homilies, proverbs), Identify Magic (divine), Learn a Spell (divine tradition)

---

## SECTION: Society (Int)

### Paragraph — Identity

> Understanding of people, systems, historical events. Untrained: Recall Knowledge (local history, personalities, legal institutions, societal structure, humanoid cultures; GM may expand); Subsist (urban).

- REQ: Society trained actions: Decipher Writing (codes, archaic/incomplete texts, unfamiliar languages); Create Forgery

### Paragraph — Create Forgery [Downtime, Secret]

> Create forged document (usually 1 day to 1 week). Requires proper writing materials. GM secretly rolls DC 20 Society check. If ≥20: forgery passes passive observers. Only active scrutiny (Perception or Society vs your Society DC) can detect it.
> Handwriting that needn't be specific: +up to +4 circumstance bonus. Specific person's handwriting: requires sample.
> If check < 20: GM compares result to each passive observer's Perception/Society DC (whichever higher).
> Success: Observer doesn't detect forgery. Fail: Observer knows it's fake. Active scrutiny (even after initial pass) can still detect with Perception/Society vs your Society DC.

- REQ: Create Forgery is downtime, secret; GM rolls DC 20 check secretly
- REQ: Non-specific handwriting: up to +4 circumstance bonus; specific handwriting: requires sample
- REQ: Results below 20 expose forgery to passive observers who beat the result
- REQ: Active scrutiny always allows a Perception or Society check vs forger's Society DC regardless of initial result

---

## SECTION: Stealth (Dex)

### Paragraph — Identity

> Avoid detection: slip past foes, hide, conceal items. Three detection states: Observed (clear view), Hidden (location known but unseen), Undetected (location unknown).

- REQ: Stealth system must model three detection states: Observed, Hidden, Undetected (relative to each creature independently)
- REQ: Becoming hidden: use Hide. Becoming undetected: use Sneak while hidden.

### Paragraph — Conceal an Object [1 action, Manipulate, Secret]

> Hide small item on person (light Bulk). GM rolls Stealth vs passive observers' Perception DC. Active searchers roll Perception vs Stealth DC.
> May also conceal objects in locations (undergrowth, secret compartments); characters Seeking roll Perception vs Stealth DC.
> Success: Object undetected. Fail: Found.

- REQ: Conceal an Object is 1-action manipulate; small items only (light Bulk or less)
- REQ: Passive observers: system rolls; active searchers use Seek action vs Stealth DC
- REQ: Can conceal in environment (not just on person); discovered via Seeking area

### Paragraph — Hide [1 action, Secret]

> Requires cover or greater cover, or concealment. GM rolls Stealth vs Perception DC of each observing creature (with cover bonus). Remain hidden as long as cover/concealment maintained; become observed if cover lost.
> Success: Observed→Hidden. Already hidden/undetected: retain condition.
> Become observed if anything except Hide, Sneak, or Step performed.
> Striking while hidden: target flat-footed for that attack; then become observed.

- REQ: Hide requires cover, greater cover, or concealment condition
- REQ: Cover bonus applies to Stealth check
- REQ: Observed→Hidden on success; retained if already hidden/undetected
- REQ: Actions other than Hide, Sneak, Step cause character to become Observed (before acting)
- REQ: Strike while hidden: target is flat-footed; character becomes Observed after strike

### Paragraph — Sneak [1 action, Move, Secret]

> Move at half Speed while maintaining stealth. Roll Stealth at end of movement vs Perception DCs of creatures you were hidden from or undetected by. Cover bonus applies throughout movement.
> If invisible + undetected: crit fails become fails.
> Success: Remain undetected. Fail: Become hidden (location revealed, still unseen). Crit Fail: Become Observed.
> After Sneaking successfully: become Observed on any action except Hide, Sneak, Step. Speaking = become Hidden instead of Undetected.

- REQ: Sneak is 1-action move at half Speed; secret check at end of movement
- REQ: Cover bonus applies if maintained throughout Stride
- REQ: Invisible + undetected characters: crit fail upgraded to fail
- REQ: Crit Fail = Observed; Fail = Hidden; Success = Undetected maintained
- REQ: Speaking degrades Undetected to Hidden (not Observed)

---

## SECTION: Survival (Wis)

### Paragraph — Identity

> Live in the wilderness, forage for food, build shelter; tracking and covering tracks with training. Untrained can Subsist (wilderness).
> Recall Knowledge: not listed as a standard Survival action.

### Paragraph — Sense Direction [Exploration, Secret]

> Use stars/sun/geography to stay oriented. Typically 1 check per day. Without compass: –2 item penalty.
> Crit Success: Excellent sense of position; exact cardinal directions. Success: Avoid being hopelessly lost; general sense of direction.

- REQ: Sense Direction is exploration, secret; –2 item penalty without compass
- REQ: Sample DCs: Untrained=sun direction; Trained=overgrown path; Expert=hedge maze; Master=byzantine labyrinth; Legendary=ever-changing dream realm

### Paragraph — Cover Tracks [Exploration, Move, Trained]

> Move at half travel Speed while covering tracks. No check required to cover; but trackers must beat your Survival DC (if higher than standard Track DC).
> In encounter: single action, no exploration trait; more frequent rolls may be needed.

- REQ: Cover Tracks is exploration; move at half Speed; no active check — just sets harder DC for trackers
- REQ: Can be used in encounter as 1 action (loses exploration trait)

### Paragraph — Track [Exploration, Move, Trained]

> Follow tracks at up to half travel Speed. Check at start, once per hour, and on significant trail changes.
> Success: Follow trail; continue without checks for up to 1 hour. Fail: Lose trail; retry after 1-hour delay. Crit Fail: Lose trail; can't retry for 24 hours.

- REQ: Track is exploration; move at half Speed; ongoing checks every hour or on trail change
- REQ: Crit Fail creates 24-hour lockout on retrying
- REQ: In encounter: 1-action version (no exploration trait); GM determines check frequency
- REQ: Sample DCs: Untrained=large army on road; Trained=bear tracks in plains; Expert=panther in jungle/rain-obscured; Master=snow-obscured/mouse/bare rock; Legendary=windy desert/blizzard-obscured

---

## SECTION: Thievery (Dex)

### Paragraph — Identity

> Thieves' skills: pick pockets, steal, disarm traps, pick locks.

### Paragraph — Palm an Object [1 action, Manipulate]

> Take a small unattended object without being noticed. Roll Thievery vs Perception DCs of all observing creatures. Object is taken regardless of success or failure.
> Success: Observers don't notice. Fail: Observer notices.

- REQ: Palm an Object is 1 action; typically negligible Bulk items only
- REQ: Object is taken regardless of success/failure; only detectability varies

### Paragraph — Steal [1 action, Manipulate]

> Take small object from another creature without being noticed. Auto-fail if target is in combat or on guard. Roll vs Perception DC (worn object); –5 penalty if in pocket. May also need to beat observers' Perception DCs.
> Success: Steal without bearer noticing. Fail: Bearer/observer notices; GM determines response.

- REQ: Steal auto-fails if target is in combat or on guard
- REQ: Standard DC = target's Perception DC; –5 penalty for pocketed items
- REQ: Observers may also need to be beaten; object of negligible Bulk typically

### Paragraph — Disable a Device [2 actions, Manipulate, Trained]

> Disarm a trap or complex device. May require thieves' tools. Complex devices may require multiple successes. GM sets DC; higher proficiency ranks may be required for certain devices.
> Crit Success: Disabled + 2 successes toward complex device; no trace; can rearm if applicable. Success: 1 success toward disabling. Crit Fail: Device triggers.

- REQ: Disable a Device is 2 actions; may require thieves' tools; trained Thievery required
- REQ: Complex devices require multiple successes to fully disable
- REQ: Crit Success leaves no trace and allows future rearming; Crit Fail triggers device

### Paragraph — Pick a Lock [2 actions, Manipulate, Trained]

> Open a lock without a key. DC set by lock complexity. Complex locks require multiple successes. Without proper tools: improvised picks treated as shoddy tools.
> Crit Success: Unlock (or 2 successes toward complex lock); no trace. Success: Open lock (or 1 success). Crit Fail: Break tools.

- REQ: Pick a Lock is 2 actions; requires thieves' tools (improvised = shoddy penalty)
- REQ: Complex locks require multiple successes; DC based on lock quality/complexity
- REQ: Crit Fail breaks tools (requires Repair or replacement)

---

