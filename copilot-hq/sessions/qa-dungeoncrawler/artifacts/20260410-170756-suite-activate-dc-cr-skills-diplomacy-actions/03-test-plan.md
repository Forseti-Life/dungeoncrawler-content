# Test Plan: dc-cr-skills-diplomacy-actions

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Diplomacy and Intimidation skill actions — Gather Information, Make an Impression, Request, Coerce, Demoralize
**KB reference:** NPC attitude-shift pattern is new to this feature; no prior KB entry found. Condition dependency pattern (frightened) follows dc-cr-skills-athletics-actions/dc-cr-skills-deception-actions (dc-cr-conditions). Immunity-timer pattern follows Demoralize (10-min window) and Coerce (1-week window).
**Dependency note:** dc-cr-conditions (in-progress Release B) — frightened condition application (TC-DIP-19/20) and attitude-shift state tracking conditional. dc-cr-npc-system — NPC attitude data model (Unfriendly/Neutral/Friendly/Helpful) required for Make an Impression, Request, and Coerce.

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All Diplomacy/Intimidation action business logic: activity types, time costs, DC tiers, NPC attitude shifts, degree outcomes, immunity timers, language-barrier gate, leverage gate |
| `role-url-audit` | HTTP role audit | ACL regression — no new routes; existing downtime/encounter handler routes only |

---

## Test Cases

### Gather Information

### TC-DIP-01 — Gather Information is a downtime activity (~2 hours)
- **Suite:** module-test-suite
- **Description:** Gather Information is classified as a downtime activity with a time cost of approximately 2 hours.
- **Expected:** action_type = downtime; time_cost ≈ 2 hours.
- **Notes to PM:** AC uses "~2 hrs" — confirm whether the system uses a fixed 2-hour cost or a GM-variable window. Automation needs a deterministic scalar.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-DIP-02 — Gather Information DC scales with information secrecy
- **Suite:** module-test-suite
- **Description:** The DC for Gather Information is lower for common knowledge and higher for secrets or rare information. The system must accept a topic-tier parameter that maps to a DC modifier.
- **Expected:** topic_tier = common → lower DC; topic_tier = secret → higher DC; DC difference is deterministic per tier.
- **Notes to PM:** Confirm the DC tiers and their numeric offsets. Automation needs the exact mapping (e.g., common = base DC, uncommon = base+2, secret = base+5).
- **Roles covered:** authenticated player
- **Status:** immediately activatable (DC tier logic; no external module)

### TC-DIP-03 — Gather Information Success: yields rumors/info on the topic
- **Suite:** module-test-suite
- **Description:** On a successful check, the system returns relevant information (rumors, facts) about the specified topic.
- **Expected:** result = success → information_payload non-empty; content pertains to requested topic.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-DIP-04 — Gather Information Critical Failure: community becomes aware of investigation
- **Suite:** module-test-suite
- **Description:** Failure mode — on Critical Failure, the system flags the target community as "aware" that the character is asking questions about this topic. This is distinct from a plain Failure (which simply yields no information).
- **Expected:** result = critical_failure → community_awareness_flag = true for topic; NOT a generic "no info" result.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (outcome type logic; no condition module)

---

### Make an Impression

### TC-DIP-05 — Make an Impression is a downtime activity (~10 minutes)
- **Suite:** module-test-suite
- **Description:** Make an Impression is classified as a downtime activity with a time cost of approximately 10 minutes.
- **Expected:** action_type = downtime; time_cost ≈ 10 minutes.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-DIP-06 — Make an Impression: initial NPC attitude loaded from NPC data
- **Suite:** module-test-suite
- **Description:** Before the check resolves, the system reads the target NPC's current attitude from NPC data. The attitude scale is: Hostile → Unfriendly → Indifferent → Friendly → Helpful.
- **Expected:** npc.attitude loaded from npc_data before check; attitude value is one of the five scale points.
- **Notes to PM:** Confirm whether dc-cr-npc-system is a Release B dependency or whether a stub/placeholder attitude field is acceptable for Make an Impression Stage 0 activation.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-npc-system (NPC attitude data model)

### TC-DIP-07 — Make an Impression Critical Success: attitude shifts two steps toward Friendly
- **Suite:** module-test-suite
- **Description:** On Critical Success, target NPC's attitude shifts two steps in the Friendly direction (e.g., Hostile → Indifferent, or Unfriendly → Friendly). Cannot exceed Helpful.
- **Expected:** new_attitude = min(Helpful, current_attitude + 2).
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-npc-system (NPC attitude write)

### TC-DIP-08 — Make an Impression Success: attitude shifts one step toward Friendly
- **Suite:** module-test-suite
- **Description:** On Success, target NPC's attitude shifts one step in the Friendly direction. Cannot exceed Helpful.
- **Expected:** new_attitude = min(Helpful, current_attitude + 1).
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-npc-system

### TC-DIP-09 — Make an Impression Failure: no attitude change
- **Suite:** module-test-suite
- **Description:** On Failure, NPC attitude is unchanged.
- **Expected:** new_attitude = current_attitude.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-npc-system

### TC-DIP-10 — Make an Impression Critical Failure: attitude shifts one step toward Hostile
- **Suite:** module-test-suite
- **Description:** On Critical Failure, NPC attitude shifts one step toward Hostile. Cannot go below Hostile.
- **Expected:** new_attitude = max(Hostile, current_attitude - 1).
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-npc-system

### TC-DIP-11 — Make an Impression cannot shift attitude beyond Helpful or below Hostile (clamp)
- **Suite:** module-test-suite
- **Description:** Edge case — attitude is clamped to the valid range [Hostile, Helpful]. A Crit Success on an already-Helpful NPC stays at Helpful; a Crit Fail on an already-Hostile NPC stays at Hostile.
- **Expected:** attitude clamped at both bounds; no out-of-range value stored.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-npc-system

---

### Request

### TC-DIP-12 — Request requires Friendly or Helpful attitude (or leverage)
- **Suite:** module-test-suite
- **Description:** Attempting a Request against a target with Indifferent, Unfriendly, or Hostile attitude and no leverage results in an auto-fail or a –4 circumstance penalty.
- **Expected:** attitude IN [hostile, unfriendly] AND leverage = false → action auto-fails or applies –4 penalty; exact behavior per AC note ("auto-fail or –4 per GM").
- **Notes to PM:** AC lists both "auto-fail" and "–4 penalty." For automation, recommend defining a system default (auto-fail at Hostile/Unfriendly, –4 at Indifferent without leverage?). Please clarify.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-npc-system (attitude read)

### TC-DIP-13 — Request: unreasonable requests carry –2 to –4 circumstance penalty
- **Suite:** module-test-suite
- **Description:** Requests flagged as "unreasonable" carry a –2 to –4 circumstance penalty to the Diplomacy check.
- **Expected:** request_reasonableness = unreasonable → penalty applied in range [–2, –4].
- **Notes to PM:** Confirm whether "unreasonableness" is a request-level flag set by GM or derived from request type. Automation needs a deterministic penalty value per tier.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (penalty application logic; no NPC system needed for the check modifier itself)

### TC-DIP-14 — Request Critical Success: target complies and becomes one step more helpful
- **Suite:** module-test-suite
- **Description:** On Critical Success, the target complies with the request AND their attitude shifts one step toward Helpful.
- **Expected:** request_result = critical_success → comply = true; npc_attitude = min(Helpful, current + 1).
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-npc-system (attitude write)

### TC-DIP-15 — Request Success: target complies, attitude unchanged
- **Suite:** module-test-suite
- **Description:** On Success, the target complies but their attitude does not change.
- **Expected:** request_result = success → comply = true; npc_attitude = current_attitude (unchanged).
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-npc-system

### TC-DIP-16 — Request Failure: target declines, attitude unchanged
- **Suite:** module-test-suite
- **Description:** On Failure, the target declines and attitude does not change.
- **Expected:** request_result = failure → comply = false; npc_attitude unchanged.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-npc-system

### TC-DIP-17 — Request Critical Failure: target declines and becomes one step less friendly
- **Suite:** module-test-suite
- **Description:** Failure mode — on Critical Failure, the target declines AND attitude shifts one step toward Hostile.
- **Expected:** request_result = critical_failure → comply = false; npc_attitude = max(Hostile, current - 1).
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-npc-system

---

### Coerce

### TC-DIP-18 — Coerce is a downtime activity (~10 minutes, one-on-one)
- **Suite:** module-test-suite
- **Description:** Coerce is classified as a downtime activity requiring approximately 10 minutes of one-on-one interaction.
- **Expected:** action_type = downtime; time_cost ≈ 10 minutes; requires single target.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-DIP-19 — Coerce Success: target complies for ~1 week then becomes Unfriendly
- **Suite:** module-test-suite
- **Description:** On Success, the target complies with the coercion for approximately 1 week. After that window, their attitude automatically becomes Unfriendly.
- **Expected:** coerce_result = success → compliance_window ≈ 7 days; post_window_attitude = Unfriendly.
- **Notes to PM:** Confirm time resolution: are compliance windows stored in days, hours, or real-time timestamps? Automation needs a testable scalar.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-npc-system (NPC attitude write and timer)

### TC-DIP-20 — Coerce Critical Success: compliance for ~1 month then becomes Unfriendly
- **Suite:** module-test-suite
- **Description:** On Critical Success, compliance window extends to approximately 1 month before attitude reverts to Unfriendly.
- **Expected:** coerce_result = critical_success → compliance_window ≈ 30 days; post_window_attitude = Unfriendly.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-npc-system

### TC-DIP-21 — Coerce immunity: target immune to further Coerce for ~1 week
- **Suite:** module-test-suite
- **Description:** After any Coerce attempt (success or failure), the target is immune to further Coerce attempts from the same character for approximately 1 week.
- **Expected:** post_coerce → coerce_immunity_flag = true; duration ≈ 7 days; further Coerce attempt within window → action blocked.
- **Notes to PM:** Does the immunity window start on the coerce attempt or on the compliance window expiry? Clarify for automation.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-npc-system (immunity timer tracking)

### TC-DIP-22 — Coerce immunity timer blocks further attempts (edge case)
- **Suite:** module-test-suite
- **Description:** Edge case — attempting to Coerce a target while the immunity timer is active returns a block (no check rolled); the timer is visible to the system even if not to the player.
- **Expected:** coerce_immunity_active = true → action blocked; no check; no compliance change.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-npc-system

---

### Demoralize

### TC-DIP-23 — Demoralize is a 1-action with Auditory, Emotion, Mental traits
- **Suite:** module-test-suite
- **Description:** Demoralize is classified as a 1-action encounter action with traits: auditory, emotion, mental.
- **Expected:** action_cost = 1; action_type = encounter; traits = [auditory, emotion, mental].
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-DIP-24 — Demoralize blocked if target cannot understand the character's language
- **Suite:** module-test-suite
- **Description:** Failure mode — Demoralize is hard-blocked (not just penalized) if the target does not share a language with the character. No check is rolled.
- **Expected:** shared_language = false → action blocked; error returned; no check made.
- **Notes to PM:** Confirm whether language-check logic lives in dc-cr-skill-system, dc-cr-npc-system, or a separate language module. This gate needs to be resolvable at action time.
- **Roles covered:** authenticated player
- **Status:** immediately activatable for the block logic; language data source may require dc-cr-npc-system

### TC-DIP-25 — Demoralize Critical Success: target gains frightened 2
- **Suite:** module-test-suite
- **Description:** On Critical Success, the target gains the frightened 2 condition.
- **Expected:** demoralize_result = critical_success → target condition = frightened 2.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (frightened condition with severity)

### TC-DIP-26 — Demoralize Success: target gains frightened 1
- **Suite:** module-test-suite
- **Description:** On Success, the target gains the frightened 1 condition.
- **Expected:** demoralize_result = success → target condition = frightened 1.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions

### TC-DIP-27 — Demoralize: target becomes immune to Demoralize for 10 minutes after any attempt
- **Suite:** module-test-suite
- **Description:** After any Demoralize attempt (success or failure), the target becomes immune to further Demoralize from any source for 10 minutes. AC note: "target cannot be immune" refers to pre-existing immunity; Demoralize itself always creates a 10-minute post-attempt immunity.
- **Expected:** post_demoralize → demoralize_immunity_flag = true; duration = 10 minutes; further Demoralize attempt within window → action blocked.
- **Notes to PM:** AC says "target cannot be immune" but also "becomes immune for 10 minutes" — confirm the intended reading: (a) the target cannot be pre-immune before being Demoralized, but WILL become immune for 10 min afterward; (b) there is no permanent immunity. Automation needs to validate post-attempt immunity timer.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (immunity timer per condition)

### TC-DIP-28 — Demoralize blocked vs immune target (edge case)
- **Suite:** module-test-suite
- **Description:** Edge case — attempting Demoralize on a target currently within their 10-minute immunity window is blocked; no check is rolled.
- **Expected:** demoralize_immunity_active = true → action blocked; no check; no condition applied.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (immunity tracking)

---

### ACL regression

### TC-DIP-29 — ACL regression: no new routes introduced by Diplomacy/Intimidation actions
- **Suite:** role-url-audit
- **Description:** Diplomacy and Intimidation action implementation adds no new HTTP routes; existing downtime/encounter handler routes retain their ACL.
- **Expected:** HTTP 200 for authenticated player on existing handler routes; HTTP 403 for anonymous.
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Conditional dependency summary

| TC | Dependency feature | Reason conditional |
|---|---|---|
| TC-DIP-06 | `dc-cr-npc-system` | NPC attitude read |
| TC-DIP-07 | `dc-cr-npc-system` | NPC attitude write (Crit Success +2) |
| TC-DIP-08 | `dc-cr-npc-system` | NPC attitude write (Success +1) |
| TC-DIP-09 | `dc-cr-npc-system` | NPC attitude write (Failure no change) |
| TC-DIP-10 | `dc-cr-npc-system` | NPC attitude write (Crit Fail –1) |
| TC-DIP-11 | `dc-cr-npc-system` | Attitude clamp at Helpful/Hostile bounds |
| TC-DIP-12 | `dc-cr-npc-system` | Attitude gate for Request |
| TC-DIP-14 | `dc-cr-npc-system` | Attitude write on Request Crit Success |
| TC-DIP-15 | `dc-cr-npc-system` | Attitude read on Request Success |
| TC-DIP-16 | `dc-cr-npc-system` | Attitude read on Request Failure |
| TC-DIP-17 | `dc-cr-npc-system` | Attitude write on Request Crit Fail |
| TC-DIP-19 | `dc-cr-npc-system` | Compliance window + attitude revert |
| TC-DIP-20 | `dc-cr-npc-system` | Extended compliance window |
| TC-DIP-21 | `dc-cr-npc-system` | Coerce immunity timer |
| TC-DIP-22 | `dc-cr-npc-system` | Immunity timer block |
| TC-DIP-25 | `dc-cr-conditions` | Frightened 2 condition |
| TC-DIP-26 | `dc-cr-conditions` | Frightened 1 condition |
| TC-DIP-27 | `dc-cr-conditions` | Demoralize 10-min immunity timer |
| TC-DIP-28 | `dc-cr-conditions` | Immunity block check |

7 TCs immediately activatable at Stage 0 (TC-DIP-01/02/03/04/05/13/18/23/24/29 minus NPC/condition deps — see below).
Immediately activatable: TC-DIP-01, TC-DIP-02, TC-DIP-03, TC-DIP-04, TC-DIP-05, TC-DIP-13, TC-DIP-18, TC-DIP-23, TC-DIP-24, TC-DIP-29 = **10 TCs**.
19 TCs conditional on dc-cr-npc-system (13) or dc-cr-conditions (4) or both (2).

---

## Notes to PM

1. **TC-DIP-01 (Gather Information time cost):** AC says "~2 hrs" — confirm fixed vs GM-variable; automation requires a deterministic value.
2. **TC-DIP-02 (DC tiers):** Confirm the numeric DC offsets per topic-tier (common/uncommon/rare/secret). This maps directly to the system DC parameter.
3. **TC-DIP-06 (NPC system dependency):** Confirm whether dc-cr-npc-system is a Release B in-progress dependency or if a stub NPC attitude field exists already. If a stub exists, Make an Impression TCs (06–11) may be immediately activatable.
4. **TC-DIP-12 (Request at low attitude):** AC says "auto-fail or –4 penalty per GM." For automation, recommend: Hostile/Unfriendly without leverage = hard block (auto-fail); Indifferent without leverage = –4 penalty. Please confirm.
5. **TC-DIP-13 (Unreasonableness tiers):** Confirm whether "unreasonable" is a binary flag or a tiered enum, and what the exact penalty is per tier (–2 for slightly unreasonable, –4 for very unreasonable?).
6. **TC-DIP-21 (Coerce immunity start):** Confirm whether the 1-week immunity window starts at the moment of the Coerce attempt or at the end of the compliance window.
7. **TC-DIP-24 (Language check module):** Confirm which module owns the language-barrier data needed by Demoralize (dc-cr-skill-system, dc-cr-npc-system, or a separate language module).
8. **TC-DIP-27 (Demoralize immunity intent):** AC says both "target cannot be immune" and "becomes immune for 10 minutes." Confirm the intended reading: no pre-existing permanent immunity allowed, but a post-attempt 10-minute immunity is always created. This is the standard PF2e reading — please confirm for implementation.
9. **Intimidation grouping note:** AC groups Intimidation (Coerce, Demoralize) with Diplomacy in this feature. Confirm dev agrees with this grouping or if Intimidation should be split into a separate feature stub before Stage 0.
10. **Staging recommendation:** 10 TCs activatable now (action types, DC tier logic, activity classification, language gate, ACL). 13 TCs wait on dc-cr-npc-system (NPC attitude model); 4 TCs wait on dc-cr-conditions (frightened condition). NPC system is the critical path for the bulk of this feature.
