# PF2E Core Rulebook — Chapter 7: Spells
## Requirements Extraction

---

## SECTION: Traditions and Schools

### Paragraph — Four Magical Traditions

> Arcane, Divine, Occult, Primal. A spell's tradition can vary; a spell's school is intrinsic.
> Arcane: broadest list, weak on spirit/soul. Divine: faith-based, unseen. Occult: unexplainable/mental. Primal: nature/instinct.

- REQ: Four distinct magical traditions: Arcane, Divine, Occult, Primal
- REQ: Each spell belongs to one or more traditions (via spell list membership) and exactly one school
- REQ: A caster's tradition is determined by class (and sometimes bloodline/deity); the tradition trait is added to all spells cast

### Paragraph — Eight Magical Schools

> Abjuration (ward/protect), Conjuration (teleport/summon/create), Divination (reveal/perceive), Enchantment (mind/emotion), Evocation (energy/damage), Illusion (sense-fooling), Necromancy (life/death), Transmutation (alter form).
> Every spell has the trait of its school.

- REQ: Eight spell schools, each as a distinct spell trait
- REQ: Schools define what the spell does; some spellcasters specialize in specific schools

### Paragraph — Four Essences

> Matter (arcane/primal; conjuration/evocation/transmutation), Spirit (divine/occult; divination/necromancy), Mind (arcane/occult; divination/enchantment/illusion), Life (divine/primal; necromancy).

- REQ: Essence classification can inform system lore and resistances/immunities to magic

---

## SECTION: Spell Slots and Spellcasting Types

### Paragraph — Spell Slots

> Spell slots represent how many spells you can cast per day, at which levels. Gained per class level table.
> Spell level = power of the spell (1–10). Spell slots are per-level pools refreshed at daily preparation.

- REQ: Each spellcaster character tracks spell slots by spell level (not a shared pool)
- REQ: Spell slots refresh on daily preparation
- REQ: Spells have a spell level (1–10) independent of character level

### Paragraph — Prepared Spellcasters

> Choose spells each day during daily preparations. Each slot may hold one spell; casting expends it.
> Cantrips can be prepared unlimited times. Multiple preparations of same spell needed to cast it multiple times.

- REQ: Prepared spellcasters (Cleric, Druid, Wizard): select spells during daily prep; each spell occupies a slot
- REQ: Casting expends the spell slot; same spell must be prepared multiple times to cast multiple times
- REQ: Cantrips do not expend slots; prepared once, castable indefinitely until next preparation

### Paragraph — Spontaneous Spellcasters

> Choose which spell to cast at cast time (from repertoire). More flexible, smaller total repertoire.
> Spell repertoire doesn't change at daily prep; only slots refresh.

- REQ: Spontaneous spellcasters (Bard, Sorcerer): spell repertoire fixed; any known spell can be cast using any available slot of appropriate level
- REQ: Daily prep refreshes spell slots, not the repertoire

### Paragraph — Heightening Spells

> Any spell can be prepared/cast in a higher-level slot than its base level. Spell level = slot level used.
> Many spells have specific heightened benefits at listed levels or cumulative "+N level" entries.
> Spontaneous: can only heighten to levels they know (not intermediate levels unless granted by class feature).
> Cantrips: automatically heightened to half character level rounded up.
> Focus spells: automatically heightened to half character level rounded up.

- REQ: Heightening mechanism: casting in a higher slot raises spell's effective level
- REQ: Heightened entries apply at their specific level only; cumulative "+(N)" entries stack above base level
- REQ: Spontaneous spellcasters can only heighten to levels where they have the spell in their repertoire
- REQ: Signature spells (class feature): can be heightened spontaneously even if only known at one level
- REQ: Cantrips: effective level = ceil(character level / 2); no slot cost
- REQ: Focus spells: effective level = ceil(character level / 2)

---

## SECTION: Special Spell Types

### Paragraph — Cantrips

> Weak spells castable at will, unlimited times per day. Prepared casters prepare a fixed number; spontaneous casters know a number from their class.

- REQ: Cantrips have no slot cost; cannot be placed in spell slots
- REQ: Automatically heightened to caster's maximum effective spell level

### Paragraph — Focus Spells

> Gained through class features or feats, not spell lists. Require Focus Points (not spell slots).
> Focus Pool: starts at 1 (from first focus ability), max 3. Each focus spell ability adds 1 to pool capacity.
> Casting any focus spell costs 1 Focus Point.
> Refocus activity (10 minutes): regain 1 Focus Point (requires performing tradition-specific deeds).
> Daily prep restores entire pool.

- REQ: Focus Pool is a separate resource from spell slots
- REQ: Focus pool max capacity = 3 (hard cap); each focus spell ability adds 1 to pool (not beyond 3)
- REQ: Refocus: 10 minutes activity → restore 1 Focus Point; requires specific deeds per class
- REQ: Daily preparation restores all Focus Points
- REQ: Focus spells can't be prepared in spell slots; spell slots can't activate focus spells
- REQ: Non-spellcasters with focus spells gain proficiency for those spells from the granting ability; don't qualify as "spellcasters"
- REQ: Multiple focus sources share one pool; points are fungible across all focus spells

### Paragraph — Innate Spells

> Natural to character (usually from ancestry or magic item). Cast using spellcasting actions; can replace material component with somatic.
> Always at least trained in innate spell attack/DC even if not otherwise a spellcaster.
> Use Charisma modifier unless specified otherwise.
> Usually cast once per day (as specified by granting ability); innate cantrips at will, auto-heightened.
> Can't be heightened or placed in spell slots.

- REQ: Innate spells: granted by ancestry/item; refresh daily; separate from class spell slots
- REQ: Innate spells use Charisma modifier for attack/DC by default
- REQ: Caster always at least trained in innate spell attack rolls and DCs
- REQ: Innate cantrips: auto-heightened; innate non-cantrips: usually once/day; can't be heightened

---

## SECTION: Casting Spells

### Paragraph — Cast a Spell Activity

> Variable action cost per spell (listed in stat block). Effect occurs at end of action. Some spells: reaction or free action.
> Long casting times (minutes/hours): exploration trait; can't be cast in combat; disrupted if combat starts.

- REQ: Casting a spell is an activity costing listed number of actions (1/2/3/reaction/free)
- REQ: Effect applies at end of all casting actions; spell can be disrupted mid-cast
- REQ: Spells with casting times > 1 round have exploration trait; can't be used in encounters

### Paragraph — Spell Components

> **Material** (manipulate): consume bit of matter; requires free hand; component expended even if spell disrupted. Assumed available via material component pouch except extreme circumstances.
> **Somatic** (manipulate): specific hand gesture; requires ability to gesture; can be done while holding item in hand; used for touch spells.
> **Verbal** (concentrate): speak words of power in strong voice; must be able to speak.
> **Focus** (manipulate): must hold/retrieve specific object listed; requires free hand; can put away after casting.

- REQ: Four component types: Material, Somatic, Verbal, Focus; each adds corresponding trait
- REQ: Material: free hand required; expended on cast (even if disrupted); covered by material component pouch
- REQ: Somatic: must be able to gesture; can hold item in hand; must be unrestrained
- REQ: Verbal: must be able to speak; generates noise; reveals spellcasting
- REQ: Focus: specific item required; free hand or already held; can be returned after cast
- REQ: Disrupted spell: slot/Focus Point/action expended; no effect; if disrupted during Sustain → spell ends immediately

### Paragraph — Component Substitutions

> Bard: can use instrument instead of somatic/material/verbal components (while playing requires ≥1 hand).
> Cleric: divine focus (religious symbol/text) held → replace any material component with focus component.
> Druid: primal focus (holly+mistletoe) held → replace any material component with focus component.
> Sorcerer: can replace any material component with somatic (drawing on blood magic).
> Innate spells: any character can replace material components with somatic components.

- REQ: Class-specific component substitutions must be implemented per class
- REQ: Innate spells always allow material→somatic substitution

### Paragraph — Metamagic

> Actions with metamagic trait modify an immediately following Cast a Spell action. Must use metamagic action directly before the spell; any other intervening action wastes it (including reactions/free actions/other metamagic).

- REQ: Metamagic: declared before casting; applies its effects to the subsequent Cast a Spell activity
- REQ: Metamagic is wasted if anything other than Cast a Spell immediately follows

### Paragraph — Spell Attack Roll and Spell DC

> Spell attack roll = spellcasting ability modifier + proficiency bonus + other bonuses + penalties.
> Spell DC = 10 + spellcasting ability modifier + proficiency bonus + other bonuses + penalties.
> MAP applies to spell attack rolls. Weapon/unarmed attack bonuses don't apply to spell attacks.

- REQ: Spell attack roll formula: spellcasting mod + proficiency + bonuses + penalties
- REQ: Spell DC formula: 10 + spellcasting mod + proficiency + bonuses + penalties
- REQ: Multiple attack penalty applies to spell attacks
- REQ: Weapon-specific bonuses (weapon specialization etc.) do NOT apply to spell attacks
- REQ: Spell attacks target AC; basic saving throw spells use spell DC

### Paragraph — Basic Saving Throws

> "Basic" save spells: crit success = no damage; success = half damage; failure = full damage; crit failure = double damage.

- REQ: Basic saving throw results: crit success=0; success=half; fail=full; crit fail=double damage

### Paragraph — Ranges, Areas, Targets

> Touch range: physically touch target; uses unarmed reach; auto-hit unless spell specifies attack roll or save.
> Area types: burst (radiates from point), cone (from caster), emanation (from caster), line.
> Targets: must be in range + perceivable by precise sense; valid target required.
> Line of effect required to target/place spell effects.

- REQ: Area spell measurement follows burst/cone/emanation/line geometry rules (see Ch9)
- REQ: Touch spells use unarmed reach; additional range starts from 0 ft for touch spells
- REQ: Willing targets can be declared by player at any time (regardless of condition)
- REQ: Invalid targets cause spell to fail on that target (doesn't invalidate other targets)
- REQ: Line of effect required from caster to spell target/origin point

### Paragraph — Durations

> Instantaneous: immediate; no duration. Timed: rounds/minutes/hours (decrements at start of caster's turn in encounters). Sustained: ends at end of next turn unless Sustain a Spell used.
> Long durations: can be extended through next daily prep by keeping spell slot reserved. Unlimited: until counteracted or dismissed.

- REQ: Duration types: instantaneous, round-based, timed, sustained, until next daily prep, unlimited
- REQ: Round-based durations: decrement by 1 at start of caster's turn; ends at 0
- REQ: Sustained spells: last until end of next turn; require 1 action to sustain; sustaining > 100 rounds causes fatigue + ends spell (unless "sustained up to N" specified)
- REQ: If caster dies/incapacitated: timed spells continue until expiry; sustained spells end if not sustained
- REQ: Dismiss action (1 action, concentrate): end a dismissible spell

### Paragraph — Disbelieving Illusions

> Some illusions allow Seek or engage-based disbelief attempt: Perception vs spell DC (or Will save).
> Disbelieved illusion: appears hazy/indistinct; may still provide concealment at GM discretion.

- REQ: Illusions with disbelief: crit success reveals nothing real; disbelief success makes it hazy but may not fully remove it
- REQ: Physical proof of illusion (e.g., being pushed through a door) reveals illusion but doesn't automatically disbelieve it

### Paragraph — Counteracting

> Use dispel magic or similar spell: attempt counteract check = spellcasting ability mod + proficiency in spell attack rolls vs counteract DC. Target: at least one manifestation within range. See counteract rules (Ch9 p.458).

- REQ: Counteract mechanism: special check separate from normal spell attack
- REQ: Light and darkness (trait): can always counteract each other by targeting directly; non-magical light cannot overcome magical darkness

### Paragraph — Key Spell Traits

> **Auditory**: only affects creatures that can hear it.
> **Incapacitation**: creatures > 2× spell level treat saves as one step better / attacker's checks one step worse.
> **Minion**: only 2 actions/turn; no reactions; can't act off-turn; requires command action from controller.
> **Morph**: partial form alteration; can have multiple morph spells (different body parts); second morph to same part attempts to counteract first; polymorphed form may dismiss morph effects.
> **Polymorph**: full transformation; only one at a time; second attempts to counteract first; battle form blocks casting/speaking/most manipulate actions; gear absorbed (constant abilities still function, can't activate items).
> **Summoned**: minion trait; can't summon others or create value; can't cast spells ≥ summoning spell level; auto-banished at 0 HP or spell ends.
> **Visual**: only affects creatures that can see it.

- REQ: Incapacitation: hard counter for high-level creatures; applies to any incapacitation-trait effect
- REQ: Polymorph (battle form): gear absorbed; no casting; no speech; no manipulate actions (GM adjudicates edge cases)
- REQ: Summoned creatures: use 2 actions on turn they appear; minion rules apply
- REQ: Trigger-set spells (e.g., Magic Mouth): react when sensor observes matching trigger; can be fooled by appropriate disguise/illusion

### Paragraph — Identifying Spells

> Automatically identify spell if prepared/in repertoire. Otherwise: Recall Knowledge action (1 action on your turn). Long-lasting spell in effect: use Identify Magic (not Recall Knowledge).

- REQ: Spell identification: auto if caster has spell prepared/known; else Recall Knowledge action
- REQ: Pre-existing spell effects require Identify Magic action

---

## SECTION: Arcane Spell List (Summary)

### Paragraph — Arcane Cantrips

- REQ: Arcane cantrips: Acid Splash, Chill Touch, Dancing Lights, Daze, Detect Magic, Electric Arc, Ghost Sound, Light, Mage Hand, Message, Prestidigitation, Produce Flame, Ray of Frost, Read Aura, Shield, Sigil, Tanglefoot, Telekinetic Projectile

### Paragraph — Arcane 1st–10th Level Spells (Key spells per level)

- REQ: Arcane 1st: Air Bubble, Alarm, Ant Haul, Burning Hands, Charm, Color Spray, Command, Create Water, Fear, Feather Fall, Fleet Step, Floating Disk, Goblin Pox, Grease, Grim Tendrils, Gust of Wind, Hydraulic Push, Illusory Disguise, Illusory Object, Item Facade, Jump, Lock, Longstrider, Mage Armor, Magic Aura, Magic Missile, Magic Weapon, Mending, Negate Aroma, Pest Form, Ray of Enfeeblement, Shocking Grasp, Sleep, Spider Sting, Summon Animal, Summon Construct, True Strike, Unseen Servant, Ventriloquism
- REQ: Arcane 2nd: Acid Arrow, Blur, Comprehend Language, Continual Flame, Create Food, Darkness, Darkvision, Deafness, Dispel Magic, Endure Elements, Enlarge, False Life, Flaming Sphere, Gentle Repose, Glitterdust, Hideous Laughter, Humanoid Form, Illusory Creature, Invisibility, Knock, Magic Mouth, Mirror Image, Misdirection, Obscuring Mist, Phantom Steed, Resist Energy, See Invisibility, Shrink, Spectral Hand, Spider Climb, Summon Elemental, Telekinetic Maneuver, Touch of Idiocy, Water Breathing, Water Walk, Web
- REQ: Arcane 3rd: Bind Undead, Blindness, Clairaudience, Dream Message, Earthbind, Enthrall, Feet to Fins, Fireball, Ghostly Weapon, Glyph of Warding, Haste, Hypnotic Pattern, Invisibility Sphere, Levitate, Lightning Bolt, Locate, Meld into Stone, Mind Reading, Nondetection, Paralyze, Secret Page, Shrink Item, Slow, Stinking Cloud, Vampiric Touch, Wall of Wind
- REQ: Arcane 4th: Aerial Form, Blink, Clairvoyance, Confusion, Creation, Detect Scrying, Dimension Door, Dimensional Anchor, Discern Lies, Fly, Gaseous Form, Globe of Invulnerability, Hallucinatory Terrain, Modify Memory, Nightmare, Outcast's Curse, Phantasmal Killer, Private Sanctum, Read Omens, Remove Curse, Resilient Sphere, Rope Trick, Spell Immunity, Stoneskin, Suggestion, Talking Corpse, Telepathy, Veil
- REQ: Arcane 5th: Chromatic Wall, Cloudkill, Cone of Cold, Control Water, Death Ward, Dominate, Hallucination, Illusory Scene, Mantle of Death, Mind Probe, Passwall, Prying Eye, Sending, Shadow Siphon, Shadow Walk, Subconscious Suggestion, Summon Dragon, Telekinetic Haul, Telepathic Bond, Tongues, Wall of Ice, Wall of Stone
- REQ: Arcane 6th: Chain Lightning, Collective Transposition, Disintegrate, Dominate, Dragon Form, Feeblemind, Flesh to Stone, Phantasmal Calamity, Repulsion, Scrying, Spellwrack, Stone to Flesh, Teleport, True Seeing, Vampiric Exsanguination, Vibrant Pattern, Wall of Force
- REQ: Arcane 7th: Dimensional Lock, Duplicate Foe, Energy Aegis, Ethereal Jaunt, Magnificent Mansion, Maze, Plane Shift, Prismatic Spray, Project Image, Reverse Gravity, Retrocognition, True Target, Visions of Danger
- REQ: Arcane 8th: Antimagic Field, Disappearance, Discern Location, Dream Council, Mind Blank, Prismatic Wall, Scintillating Pattern, Spirit Song, Trap the Soul
- REQ: Arcane 9th: Foresight, Imprisonment, Massacre, Meteor Swarm, Shapechange, Storm of Vengeance, Wail of the Banshee, Weird
- REQ: Arcane 10th: Alter Reality, Gate, Remake, Time Stop

---

## SECTION: Divine Spell List (Summary)

### Paragraph — Divine Cantrips

- REQ: Divine cantrips: Chill Touch, Daze, Detect Magic, Disrupt Undead, Divine Lance, Forbidding Ward, Guidance, Know Direction, Light, Message, Prestidigitation, Read Aura, Shield, Sigil, Stabilize

### Paragraph — Divine 1st–10th Level Spells

- REQ: Divine 1st: Air Bubble, Alarm, Bane, Bless, Command, Create Water, Detect Alignment, Detect Poison, Disrupting Weapons, Fear, Harm, Heal, Lock, Magic Weapon, Mending, Protection, Purify Food and Drink, Ray of Enfeeblement, Sanctuary, Spirit Link, Ventriloquism
- REQ: Divine 2nd: Augury, Calm Emotions, Comprehend Language, Continual Flame, Create Food, Darkness, Darkvision, Deafness, Death Knell, Dispel Magic, Endure Elements, Enhance Victuals, Faerie Fire, Gentle Repose, Ghoulish Cravings, Remove Fear, Remove Paralysis, Resist Energy, Restoration, Restore Senses, See Invisibility, Silence, Sound Burst, Spiritual Weapon, Status, Undetectable Alignment
- REQ: Divine 3rd: Crisis of Faith, Dream Message, Glyph of Warding, Heroism, Locate, Neutralize Poison, Remove Disease, Sanctified Ground, Searing Light, Vampiric Touch, Wanderer's Guide, Zone of Truth
- REQ: Divine 4th: Air Walk, Anathematic Reprisal, Dimensional Anchor, Discern Lies, Divine Wrath, Freedom of Movement, Globe of Invulnerability, Holy Cascade, Outcast's Curse, Read Omens, Remove Curse, Spell Immunity, Talking Corpse, Vital Beacon
- REQ: Divine 5th: Abyssal Plague, Banishment, Breath of Life, Death Ward, Drop Dead, Flame Strike, Prying Eye, Sending, Shadow Blast, Spiritual Guardian, Summon Celestial, Summon Fiend, Tongues
- REQ: Divine 6th: Blade Barrier, Field of Life, Raise Dead, Repulsion, Righteous Might, Spellwrack, Spirit Blast, Stone Tell, Stone to Flesh, True Seeing, Vampiric Exsanguination, Zealous Conviction
- REQ: Divine 7th: Dimensional Lock, Divine Decree, Divine Vessel, Eclipse Burst, Energy Aegis, Ethereal Jaunt, Finger of Death, Plane Shift, Regenerate, Sunburst
- REQ: Divine 8th: Antimagic Field, Discern Location, Divine Aura, Divine Inspiration, Moment of Renewal, Spiritual Epidemic
- REQ: Divine 9th: Bind Soul, Crusade, Foresight, Massacre, Overwhelming Presence, Telepathic Demand, Wail of the Banshee, Weapon of Judgment
- REQ: Divine 10th: Avatar, Gate, Miracle, Remake, Revival

---

## SECTION: Occult Spell List (Summary)

### Paragraph — Occult Cantrips

- REQ: Occult cantrips: Chill Touch, Dancing Lights, Daze, Detect Magic, Forbidding Ward, Ghost Sound, Guidance, Know Direction, Light, Mage Hand, Message, Prestidigitation, Read Aura, Shield, Sigil, Telekinetic Projectile

### Paragraph — Occult 1st–10th Level Spells

- REQ: Occult 1st: Alarm, Bane, Bless, Charm, Color Spray, Command, Detect Alignment, Fear, Floating Disk, Grim Tendrils, Illusory Disguise, Illusory Object, Item Facade, Lock, Mage Armor, Magic Aura, Magic Missile, Magic Weapon, Mending, Mindlink, Phantom Pain, Protection, Ray of Enfeeblement, Sanctuary, Sleep, Soothe, Spirit Link, Summon Fey, True Strike, Unseen Servant, Ventriloquism
- REQ: Occult 2nd: Augury, Blur, Calm Emotions, Comprehend Language, Continual Flame, Darkness, Darkvision, Deafness, Death Knell, Dispel Magic, Faerie Fire, False Life, Gentle Repose, Ghoulish Cravings, Hideous Laughter, Humanoid Form, Illusory Creature, Invisibility, Knock, Magic Mouth, Mirror Image, Misdirection, Paranoia, Phantom Steed, Remove Fear, Remove Paralysis, Resist Energy, Restoration, Restore Senses, See Invisibility, Shatter, Silence, Sound Burst, Spectral Hand, Spiritual Weapon, Status, Telekinetic Maneuver, Touch of Idiocy, Undetectable Alignment
- REQ: Occult 3rd: Bind Undead, Blindness, Circle of Protection, Clairaudience, Dream Message, Enthrall, Ghostly Weapon, Glyph of Warding, Haste, Heroism, Hypercognition, Hypnotic Pattern, Invisibility Sphere, Levitate, Locate, Mind Reading, Nondetection, Paralyze, Secret Page, Slow, Vampiric Touch, Wanderer's Guide, Zone of Truth
- REQ: Occult 4th: Blink, Clairvoyance, Confusion, Detect Scrying, Dimension Door, Dimensional Anchor, Discern Lies, Fly, Gaseous Form, Glibness, Globe of Invulnerability, Hallucinatory Terrain, Modify Memory, Nightmare, Outcast's Curse, Phantasmal Killer, Private Sanctum, Read Omens, Remove Curse, Resilient Sphere, Rope Trick, Spell Immunity, Suggestion, Talking Corpse, Telepathy, Veil
- REQ: Occult 5th: Abyssal Plague, Banishment, Black Tentacles, Chromatic Wall, Cloak of Colors, Crushing Despair, Death Ward, Dreaming Potential, False Vision, Hallucination, Illusory Scene, Mariner's Curse, Mind Probe, Prying Eye, Sending, Shadow Blast, Shadow Siphon, Shadow Walk, Subconscious Suggestion, Summon Entity, Synaptic Pulse, Synesthesia, Telekinetic Haul, Telepathic Bond, Tongues
- REQ: Occult 6th: Collective Transposition, Dominate, Feeblemind, Mislead, Phantasmal Calamity, Repulsion, Scrying, Spellwrack, Spirit Blast, Teleport, True Seeing, Vampiric Exsanguination, Vibrant Pattern, Wall of Force, Zealous Conviction
- REQ: Occult 7th: Dimensional Lock, Duplicate Foe, Energy Aegis, Ethereal Jaunt, Magnificent Mansion, Mask of Terror, Plane Shift, Possession, Prismatic Spray, Project Image, Retrocognition, Reverse Gravity, True Target, Visions of Danger, Warp Mind
- REQ: Occult 8th: Antimagic Field, Disappearance, Discern Location, Dream Council, Maze, Mind Blank, Prismatic Wall, Scintillating Pattern, Spirit Song, Spiritual Epidemic, Uncontrollable Dance, Unrelenting Observation
- REQ: Occult 9th: Bind Soul, Foresight, Overwhelming Presence, Prismatic Sphere, Resplendent Mansion, Telepathic Demand, Unfathomable Song, Wail of the Banshee, Weird
- REQ: Occult 10th: Alter Reality, Fabricated Truth, Gate, Remake, Time Stop

---

## SECTION: Primal Spell List (Summary)

### Paragraph — Primal Cantrips

- REQ: Primal cantrips: Acid Splash, Dancing Lights, Detect Magic, Disrupt Undead, Electric Arc, Guidance, Know Direction, Light, Prestidigitation, Produce Flame, Ray of Frost, Read Aura, Sigil, Stabilize, Tanglefoot

### Paragraph — Primal 1st–10th Level Spells

- REQ: Primal 1st: Air Bubble, Alarm, Ant Haul, Burning Hands, Charm, Create Water, Detect Poison, Fear, Feather Fall, Fleet Step, Goblin Pox, Grease, Gust of Wind, Heal, Hydraulic Push, Jump, Longstrider, Magic Fang, Mending, Negate Aroma, Pass without Trace, Pest Form, Purify Food and Drink, Shillelagh, Shocking Grasp, Spider Sting, Summon Animal, Summon Fey, Summon Plant or Fungus, Ventriloquism
- REQ: Primal 2nd: Acid Arrow, Animal Form, Animal Messenger, Barkskin, Continual Flame, Create Food, Darkness, Darkvision, Deafness, Dispel Magic, Endure Elements, Enhance Victuals, Enlarge, Entangle, Faerie Fire, Flaming Sphere, Gentle Repose, Glitterdust, Humanoid Form, Obscuring Mist, Phantom Steed, Remove Fear, Remove Paralysis, Resist Energy, Restoration, Restore Senses, Shape Wood, Shatter, Shrink, Speak with Animals, Spider Climb, Status, Summon Elemental, Tree Shape, Water Breathing, Water Walk, Web
- REQ: Primal 3rd: Animal Vision, Blindness, Earthbind, Feet to Fins, Fireball, Glyph of Warding, Haste, Insect Form, Lightning Bolt, Meld into Stone, Neutralize Poison, Nondetection, Remove Disease, Searing Light, Slow, Stinking Cloud, Wall of Thorns, Wall of Wind
- REQ: Primal 4th: Aerial Form, Air Walk, Creation, Dinosaur Form, Fire Shield, Fly, Freedom of Movement, Gaseous Form, Hallucinatory Terrain, Hydraulic Torrent, Shape Stone, Solid Fog, Speak with Plants, Stoneskin, Vital Beacon, Wall of Fire, Weapon Storm
- REQ: Primal 5th: Banishment, Cloudkill, Cone of Cold, Control Water, Death Ward, Elemental Form, Mariner's Curse, Moon Frenzy, Passwall, Plant Form, Summon Giant, Tree Stride, Wall of Ice, Wall of Stone
- REQ: Primal 6th: Baleful Polymorph, Chain Lightning, Dragon Form, Field of Life, Fire Seeds, Flesh to Stone, Purple Worm Sting, Stone Tell, Stone to Flesh, Tangling Creepers, True Seeing
- REQ: Primal 7th: Eclipse Burst, Energy Aegis, Fiery Body, Finger of Death, Mask of Terror, Plane Shift, Regenerate, Sunburst, Unfettered Pack, Volcanic Eruption
- REQ: Primal 8th: Earthquake, Horrid Wilting, Moment of Renewal, Monstrosity Form, Polar Ray, Punishing Winds, Wind Walk
- REQ: Primal 9th: Disjunction, Implosion, Massacre, Meteor Swarm, Nature's Enmity, Shapechange, Storm of Vengeance
- REQ: Primal 10th: Cataclysm, Nature Incarnate, Primal Herd, Primal Phenomenon, Remake, Revival

---

## SECTION: Focus Spells by Class

### Paragraph — Bard Focus Spells (Composition Spells)

> Bards use composition spells as focus spells. All bard focus spells are uncommon.

- REQ: Bard focus (composition) spells include: Allegro (cantrip 7), Counter Performance (focus 1), Dirge of Doom (cantrip 3), Fatal Aria (focus 10), House of Imaginary Walls (cantrip 5), Inspire Competence (cantrip 2), Inspire Courage (cantrip 1), Inspire Defense (cantrip 4), Lingering Composition (focus 1), Loremaster's Etude (focus 1), Ode to Ouroboros (focus 10), Pling the Pillywiggin (focus 4), Soothing Ballad (focus 4), Symphony of the Muse (focus 10), Triple Time (cantrip 1), Unusual Composition (focus 1)

### Paragraph — Champion Devotion Spells

- REQ: Champion devotion spells include: Champion's Sacrifice (focus 6), Hero's Defiance (focus 10), Lay on Hands (focus 1), Litany Against Sloth (focus 5), Litany Against Wrath (focus 3), Litany of Righteousness (focus 5)

### Paragraph — Cleric Domain Spells

> Clerics gain domain spells from their deity's domains (see Ch.8). Each domain has 1 domain spell and 1 advanced domain spell.

- REQ: Cleric domain spells organized by domain (full list in system implementation)
- REQ: Domain spells are focus 1 (standard) or focus 4–6 (advanced); each domain spell selection adds 1 Focus Point to pool

### Paragraph — Druid Order Spells

- REQ: Druid order spells include: Animal (wild shape series), Leaf, Stone, Storm, Wild — each order grants 1–2 focus spells
- REQ: Wild Shape cantrip is foundation of druid shapechanging; heightened versions at 2nd, 4th, 6th levels

### Paragraph — Monk Ki Spells

- REQ: Monk ki focus spells include: Ki Blast (focus 3), Ki Rush (focus 1), Ki Strike (focus 1), Quivering Palm (focus 8), Wholeness of Body (focus 2), Wild Winds Stance/Stance ki spells, Stunning Fist (focus 1)

### Paragraph — Sorcerer Bloodline Spells

> Each sorcerer bloodline grants one focus spell.

- REQ: Sorcerer bloodline spells include: Angelic Halo (Angelic), Ancestral Memories (Imperial), Dragon Claws (Draconic), Entrancing Eyes (Fey), Glutton's Jaw (Demonic), Grasping Grave (Undead), Jealous Hex (Hag), Marvelous Mount (Genie), Phantasmal Minion (Shadow), Tentacular Limbs (Aberrant)

### Paragraph — Wizard School Spells

> Each specialist wizard school grants a school spell.

- REQ: Wizard school spells include: Augment Summoning (Conjuration), Charming Words (Enchantment), Dimensional Steps (Conjuration advanced), Diviner's Sight (Divination), Dread Aura (Necromancy), Elemental Tempest (Evocation), Extend Spell (Universalist), Hand of the Apprentice (Universalist), Invisibility Cloak (Illusion), Physical Boost (Transmutation), Protective Ward (Abjuration), Shifting Form (Transmutation)

---

## SECTION: Spell Stat Block Format

### Paragraph — Standard Spell Entry Format

> Each spell contains: Name, Type (Spell/Cantrip/Focus), Level, Traits, Traditions, Cast (actions + components + trigger/cost), Range/Area/Targets, Saving Throw, Duration, Effect description, Heightened entries.

- REQ: Spell data model must capture: name, type, level, school, traditions, rarity, cast actions, components, trigger, cost, range, area, targets, save type, duration, description, heightened effects
- REQ: Heightened entries: either specific levels ("Heightened (4th)") or cumulative increments ("Heightened (+2)")
- REQ: Horizontal line separates metadata from effect description in display

### Paragraph — Notes on Spell Implementation

> The Chapter 7 spell catalog contains ~450+ individual spell entries. Full stat blocks for each spell must be implemented separately.

- REQ: All spells in all four tradition spell lists must be implemented with full stat blocks
- REQ: Focus spells from all classes must be implemented per class
- REQ: Rare/uncommon spells require access restriction
- REQ: Spell variants (e.g., Harm vs Heal) must be implemented as distinct spells despite similar structure
- REQ: "H" superscript in spell list = spell has heightened benefits (must implement all heightened entries)
- REQ: "U" superscript = uncommon rarity (access required)
- REQ: "R" superscript = rare rarity (special access required)

---
