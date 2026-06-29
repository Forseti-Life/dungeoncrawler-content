# QA Task: Basic Actions (Reqs 2190–2218)

**Type:** qa  
**Section:** Ch9 — Basic Actions  
**Rulebook Reference:** `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md`  
**Primary Source:** Pathfinder 2e Core Rulebook Chapter 9 — "Basic Actions"

---

## Scope

Write positive and negative test cases for all 29 requirements (2190–2218).

Known implemented: Stride (2216), Strike (2217), Interact (2200).  
Known partial: Delay (2193 — sets flag, no re-entry logic).  
All others are EXPECTED to FAIL — document and confirm.

Key files:
- `EncounterPhaseHandler.php` — action dispatch switch, `getLegalIntents()`
- `ActionProcessor.php` — `executeActivity()`, `executeAttack()`
- `CombatEngine.php` — `resolveAttack()`

---

## Test Cases

### REQ 2190 — Aid: reaction, setup action required on prior turn
- **Positive:** Player declares Aid setup on turn N; on turn N+1 or later, Aid reaction fires with relevant skill check vs DC 20
- **Negative:** Aid attempted without prior setup action → rejected

### REQ 2191 — Aid degree outcomes
- **Positive:** Crit success Aid → ally gets +2 circumstance bonus; success → +1; crit fail → –1 penalty
- **Negative:** Aid with master proficiency on crit success → +3, legendary → +4

### REQ 2192 — Crawl: 1 action, move 5 ft while prone
- **Positive:** Prone character Crawls → moves 5 ft, still prone
- **Negative:** Character with Speed 5 ft → cannot Crawl (requires Speed ≥ 10 ft)

### REQ 2193 — Delay: free action, exit initiative, re-enter after another creature's turn
- **Positive:** Character Delays → `delayed=TRUE` in game state; re-enters at end of next creature's turn
- **Negative:** Delay does NOT grant extra actions on re-entry (same 3 actions remaining when re-entering)

### REQ 2194 — Delaying triggers negative start/end-of-turn effects; beneficial ones end immediately
- **Positive:** Dying character Delays → recovery check fires immediately; persistent fire damage triggers immediately
- **Negative:** Buff "until end of turn" on delaying character → ends when Delay taken (not deferred)

### REQ 2195 — Full round passes without returning: lose delayed actions, initiative unchanged
- **Positive:** Character Delays and never re-enters → on next round, their initiative stays; they get 0 actions for the missed round
- **Negative:** Delayed character does NOT gain extra actions from the missed turn

### REQ 2196 — Drop Prone: 1 action, gains prone condition
- **Positive:** `drop_prone` action → character has `prone` condition; costs 1 action
- **Negative:** Standing character uses Drop Prone → prone condition applied (not removed)

### REQ 2197 — Escape: 1 action, roll vs appropriate DC to remove grabbed/immobilized/restrained
- **Positive:** Grabbed character escapes with Acrobatics vs. grapple DC → grabbed removed
- **Negative:** Escape attempted when not grabbed/immobilized/restrained → rejected

### REQ 2198 — Escape crit success: freed + may Stride 5 ft; crit fail: cannot retry until next turn
- **Positive:** Crit success Escape → freed, gains 5-ft Stride
- **Negative:** Crit fail → `escape_blocked_until_next_turn` flag set; retry attempt → rejected

### REQ 2199 — Escape has attack trait (counts for MAP)
- **Positive:** After Strike, Escape costs attacks_this_turn +1 and suffers MAP
- **Negative:** First Escape on turn → no MAP penalty; second action → MAP −5 applies

### REQ 2200 — Interact: grab unattended object, open door, manipulation
- **Positive:** `interact` action on door → door state changes; costs 1 action
- **Negative:** Interact with an attended (carried) object without permission → rejected

### REQ 2201 — Leap: 1 action, horizontal jump up to 10 ft (Speed 15+) or 15 ft (Speed 30+)
- **Positive:** Speed 30 character leaps → moves up to 15 ft horizontally
- **Negative:** Speed 10 character leaps → cannot leap (min Speed 15 ft for horizontal leap)

### REQ 2202 — Greater leaps require High Jump or Long Jump Athletics actions
- **Positive:** High Jump used → can leap vertical jump + extra horizontal
- **Negative:** Standard Leap → does NOT exceed 15 ft horizontal or 3 ft vertical

### REQ 2203 — Ready: 2 actions, designate action + trigger; becomes reaction until next turn
- **Positive:** Ready with trigger "enemy enters threatened square" and action "Strike" → fires as reaction when trigger met
- **Negative:** Ready with a free-action-already-triggered action → rejected (REQ 2205)

### REQ 2204 — Readied attack uses MAP from when Ready was used
- **Positive:** Ready declared as 2nd attack → readied Strike has MAP −5 applied
- **Negative:** Readied attack does NOT recalculate MAP at the time it fires

### REQ 2205 — Cannot Ready a free action that already has a trigger
- **Positive:** Attempting to Ready a triggered free action → error returned
- **Negative:** Ready a free action without a trigger → allowed

### REQ 2206 — Release: free action, drop held item; does NOT trigger AoO
- **Positive:** Release item → item in hand removed from equipment; no reaction triggered
- **Negative:** Releasing does NOT cost an action (free action)

### REQ 2207 — Seek: 1 action, concentrate, secret — GM rolls Perception vs Stealth DCs
- **Positive:** Seek on area → GM rolls Perception check; result determines detection of hidden creatures
- **Negative:** Seek without GM-side result → no auto-detection; secret check hidden from player

### REQ 2208 — Seek creature outcomes: crit success = observed; success = undetected→hidden or hidden→observed
- **Positive:** Seeking hidden creature with success → becomes observed
- **Negative:** Seeking creature with failure → creature status unchanged

### REQ 2209 — Seek object: crit success = exact location; success = location/clue
- **Positive:** Seek on hidden object with crit success → exact position revealed
- **Negative:** Seek with failure → no information about hidden object revealed

### REQ 2210 — Imprecise sense: detected creature can be at best hidden (not observed)
- **Positive:** Creature found via imprecise sense (hearing) → status is "hidden", not "observed"
- **Negative:** Observed condition requires precise sense

### REQ 2211 — Sense Motive: 1 action, concentrate, secret — assess deception
- **Positive:** Sense Motive on NPC → GM rolls Perception vs NPC Deception; result informs player
- **Negative:** Sense Motive result is secret (player does not see raw roll)

### REQ 2212 — Sense Motive: typically cannot retry until situation changes
- **Positive:** First Sense Motive → result. Second attempt with no change → error/warning
- **Negative:** New information or changed circumstances → retry allowed

### REQ 2213 — Stand: 1 action, removes prone condition
- **Positive:** Prone character Stands → prone condition removed; costs 1 action
- **Negative:** Non-prone character Stands → no effect (no prone to remove)

### REQ 2214 — Step: 1 action, move exactly 5 ft without triggering move-triggered reactions
- **Positive:** Step into adjacent square → no AoO or move-triggered reaction fires
- **Negative:** Stride past an enemy → AoO can fire; Step should NOT trigger AoO

### REQ 2215 — Cannot Step into difficult terrain; cannot Step with non-land Speed
- **Positive:** Step into normal terrain → allowed
- **Negative:** Step into difficult terrain → rejected; Step via fly/swim Speed → rejected

### REQ 2216 — Stride: 1 action, move up to full land Speed
- **Positive:** Character with Speed 30 Strides → moves up to 30 ft
- **Negative:** Stride cannot exceed Speed value

### REQ 2217 — Strike: 1 action, attack roll vs AC; success = damage; crit = double dice
- **Positive:** Strike hits → damage applied; crit → double dice
- **Negative:** Strike miss → no damage

### REQ 2218 — Take Cover: 1 action, upgrade cover tier; ends on move/attack/unconscious
- **Positive:** No cover → Take Cover → standard cover (+2 AC). Standard cover → Take Cover → greater (+4 AC)
- **Negative:** Taking an attack action → cover ends automatically

---

## Expected Failures

All actions except Stride, Strike, Interact are NOT in `getLegalIntents()`:
- Crawl, Drop Prone, Escape, Leap, Stand, Step, Take Cover, Seek, Sense Motive, Aid, Release → `unknown action` error
- Ready → in legal intents list but no case handler in EncounterPhaseHandler
- Delay → flag set (`delayed=TRUE`) but no re-entry or turn-effect enforcement (2194/2195 fail)
- Agent: qa-dungeoncrawler
- Status: pending
