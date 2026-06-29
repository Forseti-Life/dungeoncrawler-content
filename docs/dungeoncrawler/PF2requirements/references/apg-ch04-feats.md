# APG Chapter 4: Feats — Requirements Extraction
**Source:** PF2E Advanced Players Guide, Ch.4 (lines 27,571–29,137)
**Extracted:** 2026-02-28

---

## SECTION: General Feats Overview

### Paragraph — Chapter Introduction
> Ch.4 introduces new general feats (non-class, non-ancestry) available to any character through dedication or training. Includes non-skill feats and skill feats organized by associated skill.

- REQ: System shall support **general feats** purchasable by any character regardless of class or ancestry, subject only to level and stat prerequisites.
- REQ: General feats shall be categorized as **non-skill** (stat/exploration-based) or **skill** (tied to a trained skill).

---

## SECTION: Non-Skill General Feats

### Paragraph — Hireling Manager (L3)
> Prerequisites: Cha 14. Hirelings you secure gain +2 circumstance bonus to all skill checks. Applies to both trained and untrained hirelings; does not change cost.

- REQ: Feat `Hireling Manager` shall apply a +2 circumstance bonus to all skill checks made by the character's hirelings.

### Paragraph — Improvised Repair (L3)
> No prerequisites. Three-action activity. Quick-fixes a broken non-magical item as a shoddy version of itself. Restores no HP; item is easy to destroy. Once properly Repaired, it is no longer shoddy.

- REQ: Feat `Improvised Repair` (3-action) shall allow a broken non-magical item to be used as a **shoddy** version until it takes damage again.
- REQ: Shoddy items shall have a distinct durability state — functional but easily destroyed; not restored by this feat.

### Paragraph — Keen Follower (L3)
> No prerequisites. When using Follow the Expert exploration activity, gain +3 circumstance bonus if the followed ally is Expert, or +4 if Master (instead of the standard +1/+2).

- REQ: Feat `Keen Follower` shall scale Follow the Expert circumstance bonuses: +3 if ally is Expert rank, +4 if Master rank.

### Paragraph — Pick Up the Pace (L3)
> Prerequisites: Con 14. When Hustling as a group in exploration mode, the group can Hustle for up to 20 additional minutes (capped at the highest-Con member's solo max).

- REQ: Feat `Pick Up the Pace` shall extend group Hustle duration by 20 minutes, capped at the highest Con modifier member's solo Hustle limit.

### Paragraph — Prescient Planner (L3)
> No prerequisites. Cost: price of the item. Requirements: haven't used since last able to purchase goods. Spend 1 minute to retroactively produce a piece of adventuring gear (non-weapon, non-armor, non-magical, common, level ≤ half character level, Bulk light enough to not encumber).

- REQ: Feat `Prescient Planner` shall allow retroactive procurement of an adventuring gear item with these constraints: common rarity, level ≤ half character level, Bulk within encumbrance limits, not weapon/armor/alchemical/magic item.
- REQ: Character must pay the item's price and must not have used the ability since their last shopping opportunity.

### Paragraph — Skitter (L3)
> Prerequisites: Dex 16, Fleet. You can Crawl at up to half your Speed.

- REQ: Feat `Skitter` shall allow Crawl movement at up to half Speed (default Crawl is 5 feet).

### Paragraph — Thorough Search (L3)
> Prerequisites: Expert in Perception. When Searching, can take twice as long (¼ Speed, max 150 ft/min to check everything, 75 ft/min before walking into it) for +2 circumstance bonus to Perception checks to Seek.

- REQ: Feat `Thorough Search` shall allow doubling of Search time to gain +2 circumstance bonus to Seek checks.

### Paragraph — Prescient Consumable (L7)
> Prerequisites: Prescient Planner. Same mechanics as Prescient Planner but can produce a **consumable item** instead of adventuring gear. Same rarity/level/Bulk restrictions apply.

- REQ: Feat `Prescient Consumable` shall extend Prescient Planner to include consumable items within the same rarity/level/Bulk constraints.

### Paragraph — Supertaster (L7)
> Prerequisites: Master in Perception. When eating/drinking, automatically attempt to identify ingredients (GM rolls secret Perception check vs. poison level DC). On success, learn food/drink is poisoned (but not the specific poison). +2 circumstance bonus to Recall Knowledge checks when taste provides relevant info.

- REQ: Feat `Supertaster` shall trigger a secret Perception check when the character eats/drinks near a poison; success reveals poisoning without identifying the specific poison.
- REQ: The bonus (+2) to Recall Knowledge when tasting applies only when taste is mechanically relevant (GM discretion).

### Paragraph — A Home in Every Port (L11)
> Prerequisites: Cha 16. Downtime feat. Spend 8 hours to find a resident willing to provide comfortable lodging (+ meals) for up to 6 allies for 24 hours at no cost.

- REQ: Feat `A Home in Every Port` shall be a downtime feat that secures free comfortable lodging for up to 7 characters (self + 6) for 24 hours in any town/village.

### Paragraph — Caravan Leader (L11)
> Prerequisites: Con 18, Pick Up the Pace. When Hustling as a group, group can Hustle for as long as the member who could Hustle longest (solo), plus an additional 20 minutes.

- REQ: Feat `Caravan Leader` shall extend group Hustle to the longest solo Hustle duration among all members, plus 20 more minutes. Requires Pick Up the Pace as prerequisite.

### Paragraph — Incredible Scout (L11)
> Prerequisites: Master in Perception. When using Scout exploration activity, allies gain +2 circumstance bonus to initiative rolls (vs. standard +1).

- REQ: Feat `Incredible Scout` shall increase Scout exploration activity's initiative bonus to allies from +1 to +2.

### Paragraph — True Perception (L19)
> Prerequisites: Legendary in Perception. Constant true seeing effect (equivalent to 6th-level spell), using own Perception modifier for counteract checks.

- REQ: Feat `True Perception` shall grant a permanent true seeing effect using the character's Perception modifier for any counteract checks against it.

---

## SECTION: Skill Feats — Multi-Skill (Varying)

### Paragraph — Armor Assist (L1)
> Prerequisites: Trained in Athletics or Warfare Lore. Attempt Athletics or Warfare Lore check (DC 15 common/20 uncommon/25 rare) to halve time to don armor, or halve an ally's time by working together.

- REQ: Feat `Armor Assist` shall allow a skill check to halve don-armor time for self or ally, with DC scaling by armor rarity.

### Paragraph — Seasoned (L1)
> Prerequisites: Trained in Alcohol Lore, Cooking Lore, or Crafting. +1 circumstance bonus to Craft food and drink (including potions). Increases to +2 at Master proficiency.

- REQ: Feat `Seasoned` shall grant +1 (+2 at Master) circumstance bonus to Craft checks for food, drink, and potions.

### Paragraph — Assured Identification (L2)
> Prerequisites: Expert in Arcana, Nature, Occultism, or Religion. When Identifying Magic: critical failures become failures. Critical success not needed to avoid misidentifying cursed items — failure to critical-succeed means inability to identify (not false identification).

- REQ: Feat `Assured Identification` shall convert critical failures on Identify Magic checks to failures, and prevent false identification of cursed items on non-critical successes (result: cannot identify, not wrong ID).

### Paragraph — Discreet Inquiry (L2)
> Prerequisites: Expert in Deception or Diplomacy. Gather Information without revealing the true subject; conceal it among other topics at no DC increase. Others must exceed your Deception DC (or normal Gather Info DC, whichever is higher) to learn you were investigating.

- REQ: Feat `Discreet Inquiry` shall allow concealment of the true subject of information gathering; observers must beat the higher of Deception DC or standard Gather Info DC to learn of the inquiry.

### Paragraph — Consult the Spirits (L7)
> Prerequisites: Master in Nature, Occultism, or Religion. Frequency: once/day (once/hour if Legendary). Spend 10 minutes, attempt Recall Knowledge check (GM-set high DC). On critical success: spirits (helpful attitude) answer 3 simple yes/no-style questions about environment within 100 ft. On success: indifferent spirits, 1 question. On critical failure: malevolent spirits that appear helpful but give harmful info. Feat can be taken multiple times (different skills each time).

- REQ: Feat `Consult the Spirits` shall be a once-per-day (once/hour at Legendary) ritual using Nature/Occultism/Religion to interrogate local spirits.
- REQ: Critical success = 3 helpful answers; Success = 1 indifferent answer; Critical failure = up to 3 deceptive harmful answers.
- REQ: Feat can be selected multiple times, each for a different qualifying skill.

---

## SECTION: Skill Feats — Acrobatics

### Paragraph — Acrobatic Performer (L1)
> Prerequisites: Trained in Acrobatics. Roll Acrobatics instead of Performance when using the Perform action.

- REQ: Feat `Acrobatic Performer` shall allow Acrobatics as a substitution skill for the Perform action.

### Paragraph — Aerobatics Mastery (L7)
> Prerequisites: Master in Acrobatics. +2 circumstance bonus to Maneuver in Flight. Can combine two flight maneuvers into one action (DC = hardest + 5). At Legendary: combine three maneuvers (DC = hardest + 10). Movement never exceeds fly Speed regardless.

- REQ: Feat `Aerobatics Mastery` shall grant +2 to Maneuver in Flight and allow combining 2 maneuvers into 1 action (Master) or 3 into 1 action (Legendary), with DC penalties of +5/+10 respectively.

---

## SECTION: Skill Feats — Athletics

### Paragraph — Lead Climber (L2)
> Prerequisites: Expert in Athletics. When allies use Follow the Expert to Climb a route you set, if an ally critically fails their Climb check, you may attempt an Athletics check at the same DC. Success = ally fails instead of critically failing. If you also critically fail, both suffer the critical failure consequence.

- REQ: Feat `Lead Climber` shall allow an Athletics check to catch allies' critical climb failures, converting them to regular failures (or both fall on double crit fail).

### Paragraph — Water Sprint (L7)
> Prerequisites: Master in Athletics. When Striding in a straight line, after covering at least half Speed over ground, can run across level water for the remainder. Must end on solid ground or fall in. At Legendary: can cross water on any path (not just straight), still must end on solid ground.

- REQ: Feat `Water Sprint` shall allow movement across water surfaces during a Stride, requiring solid ground at end of movement; straight line required at Master, any path at Legendary.

---

## SECTION: Skill Feats — Crafting

### Paragraph — Crafter's Appraisal (L1)
> Prerequisites: Trained in Crafting. Use Crafting instead of Arcana/Nature/Occultism/Religion to Identify Magic on magic items only (not other forms of magic).

- REQ: Feat `Crafter's Appraisal` shall allow Crafting as an alternate skill for Identify Magic checks on magic items specifically.

### Paragraph — Improvise Tool (L1)
> Prerequisites: Trained in Crafting. Repair items without a repair kit. With raw materials available, Craft a list of basic mundane items without a crafter's book (caltrops, candle, compass, crowbar, fishing tackle, flint and steel, hammer, ladder, piton, rope, 10-ft pole, replacement thieves' picks, long/short tool, torch).

- REQ: Feat `Improvise Tool` shall allow Repair without a kit and Crafting of a defined list of basic mundane items without a crafter's book (when raw materials present).

### Paragraph — Rapid Affixture (L7)
> Prerequisites: Master in Crafting. Affix a Talisman in 1 minute (vs. normal 10 minutes). At Legendary: Affix Talisman as a 3-action activity.

- REQ: Feat `Rapid Affixture` shall reduce Affix Talisman time to 1 minute at Master, and to 3 actions at Legendary.

---

## SECTION: Skill Feats — Deception

### Paragraph — Doublespeak (L7)
> Prerequisites: Master in Deception. Pass secret messages in normal conversation. Allies who've traveled with you for 1+ full week automatically understand. Others must succeed at Perception vs. your Deception DC to realize a message is being passed; critical success to actually understand the content.

- REQ: Feat `Doublespeak` shall allow hidden messaging in conversation, auto-understood by long-term allies; others need Perception vs. Deception DC to detect and crit to decode.

---

## SECTION: Skill Feats — Diplomacy

### Paragraph — Bon Mot (L1, 1-action)
> Prerequisites: Trained in Diplomacy. Auditory, Concentrate, Emotion, Linguistic, Mental. Target within 30 ft; roll Diplomacy vs. target's Will DC.
> Crit Success: –3 status penalty to target's Perception and Will saves for 1 minute; target can end with a concentrate/linguistic action retort.
> Success: –2 status penalty.
> Crit Failure: You take the same penalty for 1 minute (ends if you succeed on another Bon Mot).

- REQ: Feat `Bon Mot` (1-action, Auditory, Linguistic, Mental) shall impose a status penalty to Perception and Will saves on success, with the target able to remove it via a verbal/concentrate retort.
- REQ: Critical failure shall inflict the same penalty on the caster instead.

### Paragraph — No Cause for Alarm (L1, 3-action)
> Prerequisites: Trained in Diplomacy. Auditory, Emotion, Linguistic, Mental. Attempt Diplomacy check vs. Will DC of all frightened creatures within 10-ft emanation.
> Crit Success: Reduce frightened by 2. Success: Reduce frightened by 1. Creatures are immune for 1 hour after.

- REQ: Feat `No Cause for Alarm` (3-action) shall reduce the frightened condition of creatures in a 10-ft emanation on success/crit success, with 1-hour immunity after.

---

## SECTION: Skill Feats — Intimidation

### Paragraph — Terrifying Resistance (L2)
> Prerequisites: Expert in Intimidation. If you succeed at Demoralizing a creature, you gain +1 circumstance bonus to saving throws against that creature's spells for the next 24 hours.

- REQ: Feat `Terrifying Resistance` shall grant +1 circumstance bonus to saves vs. spells from a creature you successfully Demoralized, lasting 24 hours.

---

## SECTION: Skill Feats — Lore (Warfare)

### Paragraph — Battle Planner (L2)
> Prerequisites: Expert in Warfare Lore. Spend 1 minute after scouting (or receiving a detailed scout report) to create a battle plan. Roll Warfare Lore. As long as information remains accurate when initiative is rolled, may use the pre-rolled Warfare Lore result as your initiative roll (fortune effect).

- REQ: Feat `Battle Planner` shall allow pre-rolling initiative using Warfare Lore, held as a fortune effect until initiative is called (requires accurate scout intelligence).

---

## SECTION: Skill Feats — Medicine

### Paragraph — Forensic Acumen (L1)
> Prerequisites: Trained in Medicine. Perform forensic examination on a body in half normal time (min 5 minutes). On success, immediately make a free follow-up Recall Knowledge check with +2 circumstance bonus (related to cause of death/injury — e.g., Crafting for poison ID, additional Medicine for disease). Bonus scales: +3 at Master, +4 at Legendary.

- REQ: Feat `Forensic Acumen` shall halve body examination time and grant a free Recall Knowledge follow-up on success with a scaling circumstance bonus (+2/+3/+4 at Trained/Master/Legendary).

### Paragraph — Inoculation (L1)
> Prerequisites: Trained in Medicine. When you successfully Treat a Disease and the patient fully recovers, they gain +2 circumstance bonus to saves against the same disease for 1 week.

- REQ: Feat `Inoculation` shall grant a 1-week +2 save bonus against a specific disease after a successful Treat Disease recovery.

### Paragraph — Risky Surgery (L1)
> Prerequisites: Trained in Medicine. When Treating Wounds, optionally deal 1d8 slashing damage to patient before applying healing. If you do: +2 circumstance bonus to the Medicine check, and a success counts as a critical success.

- REQ: Feat `Risky Surgery` shall allow a voluntary 1d8 slashing damage to patient to gain +2 Medicine bonus and upgrade success to critical success on Treat Wounds.

### Paragraph — Advanced First Aid (L7)
> Prerequisites: Master in Medicine. Healing, Manipulate. When using Administer First Aid, instead of Stabilize/Stop Bleeding, reduce frightened or sickened condition by 2 (or remove entirely on crit success). Only one condition at a time. DC = the effect that caused the condition.

- REQ: Feat `Advanced First Aid` shall add frightened/sickened condition reduction as an option for Administer First Aid: –2 on success, remove on crit. Only one condition per use. DC set by originating effect.

---

## SECTION: Skill Feats — Nature

### Paragraph — Express Rider (L1)
> Prerequisites: Trained in Nature. Exploration. Move. When calculating daily travel speed while mounted, attempt a Nature check (DC set by GM based on mount's level or terrain difficulty) to Command Animal for speed increase. On success: increase mount travel speed by half. No encounter-mode effect.

- REQ: Feat `Express Rider` shall allow a Nature check during travel to increase a mount's daily travel speed by 50% on success (exploration mode only).

### Paragraph — Influence Nature (L7)
> Prerequisites: Master in Nature. Downtime. Spend at least a day or two (GM determines) to influence local animal behavior in the region. At Legendary: reduces to 10 minutes. Effects are indirect (animals favor/aid party); cannot control behavior directly.

- REQ: Feat `Influence Nature` shall be a downtime feat allowing indirect manipulation of regional animal behavior (e.g., easier hunts, warning signals); requires 1–2 days at Master, 10 minutes at Legendary.

---

## SECTION: Skill Feats — Occultism

### Paragraph — Deceptive Worship (L1)
> Prerequisites: Trained in Occultism. Use Occultism instead of Deception to Impersonate a typical worshipper of another religion, or to Lie specifically about being a member of that faith. Still uses Deception for impersonating specific individuals or other lies.

- REQ: Feat `Deceptive Worship` shall allow Occultism as a substitution for Deception when impersonating a generic worshipper of a faith or claiming membership in it.

### Paragraph — Root Magic (L1)
> Prerequisites: Trained in Occultism. Daily preparations: assemble a small talisman pouch and give to one ally. That ally gains +1 circumstance bonus to their first saving throw against a spell or haunt that day. Scales to +2 at Expert, +3 at Legendary in Occultism.

- REQ: Feat `Root Magic` shall grant one ally a daily-refreshing +1 (+2 Expert/+3 Legendary) circumstance bonus to their first spell or haunt saving throw.

### Paragraph — Schooled in Secrets (L1)
> Prerequisites: Trained in Occultism. Use Occultism instead of Diplomacy to Gather Information about secret societies and mystery cults. Automatically recognize members of your own organization unless they are actively concealing themselves from you.

- REQ: Feat `Schooled in Secrets` shall substitute Occultism for Diplomacy when gathering info about secret groups, and grant auto-recognition of fellow members.

### Paragraph — Disturbing Knowledge (L7, 2-action)
> Prerequisites: Master in Occultism. Emotion, Fear, Mental. Roll Occultism vs. Will DC of one enemy within 30 ft (or any number of enemies at 30 ft if Legendary). 24-hour immunity after attempt.
> Crit Success: Target confused 1 round + frightened 1. Success: frightened 1. Failure: no effect. Crit Failure: you become frightened 1.

- REQ: Feat `Disturbing Knowledge` (2-action, Mental, Fear) shall impose frightened (and confused on crit) vs. one target (Master) or any number of targets (Legendary) within 30 ft using Occultism vs. Will DC.

---

## SECTION: Skill Feats — Performance

### Paragraph — Distracting Performance (L2)
> Prerequisites: Expert in Performance. When you Aid an ally creating a Diversion, instead of the standard Aid result, you roll Performance and use that result as the outcome of the diversion itself (replacing the ally's Deception check).

- REQ: Feat `Distracting Performance` shall allow a Performance check to substitute for an ally's Deception check when creating a Diversion via Aid.

---

## SECTION: Skill Feats — Religion

### Paragraph — Pilgrim's Token (L1)
> Prerequisites: Trained in Religion. Carry a token attuned at a holy site. When you tie an adversary's initiative roll, you go first. If taken at L1: token is free. Later: must purchase (≥2 sp) and attune (10 min prayer + DC 20 Religion check at a holy site). GM may adjust DC based on site significance and material quality.

- REQ: Feat `Pilgrim's Token` shall grant initiative tiebreaker (character wins ties). Attunement requires 10 min prayer + DC 20 Religion check at a holy site; taken at L1 the token is free.

### Paragraph — Exhort the Faithful (L2)
> Prerequisites: Expert in Religion, follower of a specific religion. Use Religion instead of Diplomacy or Intimidation when Requesting or Coercing members of your own faith. +2 circumstance bonus to the check. On critical failure to Request: target's attitude toward you does not worsen.

- REQ: Feat `Exhort the Faithful` shall substitute Religion for Diplomacy/Intimidation vs. same-faith NPCs with +2 circumstance bonus, and protect against attitude worsening on critical Request failure.

---

## SECTION: Skill Feats — Society

### Paragraph — Eye for Numbers (L1, 1-action)
> Prerequisites: Trained in Society. Immediately estimate the count of visually similar items in a group (rounded to first digit — e.g., 33 → "about 30"). Cannot count continuous quantities (sand, stars). Also grants +2 circumstance bonus to Decipher Writing checks that are primarily numerical/mathematical.

- REQ: Feat `Eye for Numbers` shall provide an instant count estimate (rounded to leading digit) of homogeneous item groups, plus +2 to Decipher Writing for numerical/mathematical documents.

### Paragraph — Glean Contents (L1)
> Prerequisites: Trained in Society. Decipher Writing on partially glimpsed, inverted, reversed, or sealed documents. Crit failure on sealed letters may alert the recipient. Doing so has the Manipulate trait and may require Deception/Stealth to avoid being noticed.

- REQ: Feat `Glean Contents` shall allow Decipher Writing on obstructed or sealed documents, with Manipulate trait and risk of detection on sealed items.

### Paragraph — Criminal Connections (L2, Uncommon)
> Prerequisites: Expert in Society, Streetwise. In areas where you have underworld connections, attempt a Society check to arrange meetings with criminal figures or request favors in exchange for future favors. DC set by GM based on favor difficulty and figure prominence.

- REQ: Feat `Criminal Connections` (Uncommon) shall allow Society checks to broker meetings with criminal organizations and exchange favor arrangements; GM sets DC.

### Paragraph — Quick Contacts (L2)
> Prerequisites: Expert in Society; Connections or Underworld Connections feat. Spend 1 day in a new settlement to build enough connections to use Connections/Underworld Connections feats. At Legendary in Society: 1 hour suffices.

- REQ: Feat `Quick Contacts` shall reduce connection-building time to 1 day (or 1 hour at Legendary) when entering new settlements.

### Paragraph — Underground Network (L2, Uncommon)
> Prerequisites: Expert in Society, Streetwise. Gather Information in areas where you have a network (1 week spent OR 1 day of downtime to build it). Takes ~1 hour, low attention. On successful consultation: +1 circumstance bonus to next Recall Knowledge on same subject (+2 if using Underworld Lore).

- REQ: Feat `Underground Network` (Uncommon) shall allow discreet information gathering via an established network and grant +1 (+2 with Underworld Lore) bonus to follow-up Recall Knowledge.

### Paragraph — Biographical Eye (L7)
> Prerequisites: Master in Society. Secret feat. Spend 1 minute observing/conversing with someone, attempt DC 30 Society check (+1 bonus if engaged in conversation). If target concealing identity: must exceed their Will DC to learn true bio.
> Crit Success: profession, specialty, major accomplishment/controversy, nation and settlement of residence, district (if city), homeland.
> Success: profession, specialty, nation/settlement of residence.
> Failure: profession and region of world they're from.
> Crit Failure: false information.

- REQ: Feat `Biographical Eye` shall be a 1-minute observation yielding character background details on a DC 30 Society check, with results scaling by degree of success. Deception-protected targets require exceeding their Will DC.

---

## SECTION: Skill Feats — Stealth

### Paragraph — Armored Stealth (L2)
> Prerequisites: Expert in Stealth. Reduce Stealth penalty from non-noisy armor by 1 (min 0) when trained in that armor type. Scales to –2 at Master, –3 at Legendary. For noisy armor: instead of reducing the penalty, removes the noisy trait's effects (penalty still removable by sufficient Strength as normal).

- REQ: Feat `Armored Stealth` shall reduce armor Stealth check penalties by 1/2/3 at Expert/Master/Legendary. For noisy armor, negates the noisy trait rather than reducing penalty.

### Paragraph — Shadow Mark (L2)
> Prerequisites: Expert in Stealth. When Avoiding Notice while following a specific target, that target suffers –2 circumstance penalty to their Perception DC against you. Scales to –3 at Master, –4 at Legendary. Target also takes this penalty to initiative and Perception DC at encounter start if shadowing.

- REQ: Feat `Shadow Mark` shall impose a –2 (–3 Master/–4 Legendary) penalty to the tailed target's Perception DC when you're Avoiding Notice while following them.

---

## SECTION: Skill Feats — Survival

### Paragraph — Legendary Guide (L15)
> Prerequisites: Legendary in Survival. When setting the party's path through wilderness, party gains +10-foot circumstance bonus to Speed for travel speed calculation, difficult terrain does not reduce travel speed, and greater difficult terrain reduces speed to half (not one-third). No encounter-mode effect.

- REQ: Feat `Legendary Guide` shall grant the party +10 ft travel Speed, ignore standard difficult terrain speed reduction, and halve (not third) travel speed in greater difficult terrain; exploration mode only.

---

## SECTION: Skill Feats — Thievery

### Paragraph — Concealing Legerdemain (L1)
> Prerequisites: Trained in Thievery. When Concealing an Object (light Bulk or less), you may use Thievery instead of Stealth for your check and for setting the active searcher's Perception DC. Roll once but must continue spending actions to Conceal throughout.

- REQ: Feat `Concealing Legerdemain` shall substitute Thievery for Stealth when Concealing a light-Bulk-or-less object, with ongoing action cost to maintain concealment.
