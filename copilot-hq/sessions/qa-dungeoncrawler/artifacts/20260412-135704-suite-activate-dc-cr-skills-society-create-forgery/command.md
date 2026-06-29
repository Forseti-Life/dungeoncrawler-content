- Status: done
- Completed: 2026-04-12T16:55:32Z

# Suite Activation: dc-cr-skills-society-create-forgery

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T13:57:04+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-skills-society-create-forgery"`**  
   This links the test to the living requirements doc at `features/dc-cr-skills-society-create-forgery/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-skills-society-create-forgery-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-skills-society-create-forgery",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-skills-society-create-forgery"`**  
   Example:
   ```json
   {
     "id": "dc-cr-skills-society-create-forgery-<route-slug>",
     "feature_id": "dc-cr-skills-society-create-forgery",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-skills-society-create-forgery",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-skills-society-create-forgery

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Society skill — Recall Knowledge (Society), Create a Forgery
**KB reference:** None found for forgery mechanics specifically. Detection pattern (viewer roll vs character's Deception DC) is structurally similar to Impersonate (Deception) detection — see DEC test plan for pattern reference.
**Dependency note:** All TCs immediately activatable on dc-cr-skill-system. No external module dependencies. Create a Forgery detection (TC-SOC-10) requires Deception DC to be stored on the forgery record — confirm whether this is the character's current Deception modifier at creation time or dynamic. Forgery is a downtime-only action; no encounter-mode dependency.

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All Society/Forgery business logic: RK domain scope, untrained RK, Trained gate, writing materials gate, DC by document tier, 4 degrees, Crit Fail self-notification, detection (viewer Society vs forger Deception DC), untrained block |
| `role-url-audit` | HTTP role audit | ACL regression — no new routes; existing downtime handler routes only |

---

## Test Cases

### Recall Knowledge (Society)

### TC-SOC-01 — Recall Knowledge (Society): domain covers cultures, laws, social structures, history, humanoid organizations, nations, settlements
- **Suite:** module-test-suite
- **Description:** A Recall Knowledge check using Society resolves for queries within Society's knowledge domain. Out-of-domain queries are out-of-scope.
- **Expected:** query_domain IN [cultures, laws, social_structures, history, humanoid_organizations, nations, settlements] → check resolves; query_domain NOT IN that list → out-of-scope response.
- **Notes to PM:** Confirm whether "humanoid organizations" includes monstrous organizations (e.g., goblin tribes) or only civilized factions. Automation needs an enumerated domain list.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOC-02 — Recall Knowledge (Society): untrained use permitted
- **Suite:** module-test-suite
- **Description:** A character with Untrained proficiency in Society may use Recall Knowledge (Society) for any Society-domain query, rolling at untrained modifier.
- **Expected:** society_rank = untrained AND query_domain IN society_scope → check allowed; rolls at untrained modifier.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Create a Forgery

### TC-SOC-03 — Create a Forgery: Trained gate
- **Suite:** module-test-suite
- **Description:** Create a Forgery requires Trained Society. Untrained characters are blocked from initiating the activity.
- **Expected:** society_rank = untrained → Create a Forgery blocked; society_rank >= trained → allowed (subject to materials gate).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOC-04 — Create a Forgery: writing materials required
- **Suite:** module-test-suite
- **Description:** Create a Forgery requires appropriate writing materials. Attempting to forge without materials is blocked before the check.
- **Expected:** character.has_writing_materials = false → blocked; character.has_writing_materials = true → check proceeds.
- **Notes to PM:** Confirm how writing materials are modeled: inventory item flag, equipment slot, or assumed-available-in-downtime. Automation needs a deterministic gate condition.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (gate logic independent of materials model detail)

### TC-SOC-05 — Create a Forgery: downtime activity, 10 minutes per page
- **Suite:** module-test-suite
- **Description:** Create a Forgery is a downtime activity. Each page costs 10 minutes of downtime. Multi-page documents require proportionally more time.
- **Expected:** downtime_activity = true; time_cost = 10 minutes × page_count.
- **Notes to PM:** Confirm whether page count is a player-declared input or derived from document type. Automation needs a deterministic time calculation.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOC-06 — Create a Forgery: DC 20 for common documents
- **Suite:** module-test-suite
- **Description:** Common documents (letters, standard permits, routine certificates) use DC 20.
- **Expected:** document_tier = common → dc = 20.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOC-07 — Create a Forgery: DC 30+ for specialist documents
- **Suite:** module-test-suite
- **Description:** Specialist documents (professional certifications, guild charters, legal deeds) use DC 30 or higher.
- **Expected:** document_tier = specialist → dc >= 30.
- **Notes to PM:** Confirm the exact DC for each specialist tier (is it fixed at 30, or a range 30–39?). Automation needs a deterministic value per tier.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOC-08 — Create a Forgery: DC 40+ for official government seals
- **Suite:** module-test-suite
- **Description:** Official government seals and royal documents use DC 40 or higher — effectively the hardest tier.
- **Expected:** document_tier = official_seal → dc >= 40.
- **Notes to PM:** Confirm whether special tools (forgery kit, specific materials) can lower this DC or if it is always DC 40 minimum. AC says "without special tools: DC 40" which implies tools may modify the DC.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOC-09 — Create a Forgery: Failure — forgery is detectable
- **Suite:** module-test-suite
- **Description:** On Failure, the forgery is created but flagged internally as detectable (quality = poor). A viewer examining it will have an easier time identifying it as fake.
- **Expected:** result = failure → forgery.quality = detectable; forgery created (not blocked).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOC-10 — Create a Forgery: Critical Failure — obviously fake AND character notified
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — on Critical Failure, the forgery quality is "obviously fake" AND the system notifies the character that it failed (they are not surprised; can retry). The character must receive explicit failure feedback.
- **Expected:** result = critical_failure → forgery.quality = obviously_fake; notification_to_character = true; retry_allowed = true.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOC-11 — Create a Forgery: Success — forgery passes standard inspection
- **Suite:** module-test-suite
- **Description:** On Success, the forgery is created with standard quality — it will not be flagged as obviously fake under casual inspection.
- **Expected:** result = success → forgery.quality = standard; forgery_record created.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-SOC-12 — Create a Forgery: Critical Success — exceptional quality forgery
- **Suite:** module-test-suite
- **Description:** On Critical Success, the forgery is of exceptional quality — harder to detect.
- **Expected:** result = critical_success → forgery.quality = exceptional (or equivalent high-quality flag).
- **Notes to PM:** Confirm whether Critical Success produces a distinct quality tier from Success or just the same quality. AC does not explicitly describe Crit Success separately; if omitted, automation defaults to treating it the same as Success.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Detection

### TC-SOC-13 — Detection: viewer uses Society vs forger's Deception DC
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — when a character examines a forgery, they roll Society against the forger's Deception DC (not the forgery creation DC). Two separate rolls are involved: creation and detection.
- **Expected:** viewer.society_check vs forger.deception_dc → viewer success = forgery detected; viewer failure = forgery accepted.
- **Notes to PM:** Confirm whether the forger's Deception DC is: (a) their Deception modifier at time of creation (snapshot), or (b) their current Deception modifier at time of detection. Automation needs to know which value to store on the forgery record.
- **Roles covered:** authenticated player (forger), authenticated player (viewer)
- **Status:** immediately activatable (detection path independent of Deception DC snapshot question — both produce a deterministic check)

### TC-SOC-14 — Detection: viewer must actively examine to trigger detection check
- **Suite:** module-test-suite
- **Description:** The detection roll is not automatic — a viewer only checks the forgery if they actively examine it. Passive receipt of a forged document does not trigger an immediate Society check.
- **Expected:** viewer_action = passive_receipt → no detection roll triggered; viewer_action = examine → Society vs Deception DC check fires.
- **Notes to PM:** Confirm whether "actively examine" is an explicit player action in the system or whether GMs/NPCs auto-examine. For player-to-player interactions, automation needs a deterministic trigger condition.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### ACL regression

### TC-SOC-15 — ACL regression: no new routes introduced by Society/Create a Forgery
- **Suite:** role-url-audit
- **Description:** Society and Create a Forgery implementation adds no new HTTP routes; existing downtime handler routes retain their ACL.
- **Expected:** HTTP 200 for authenticated player on existing downtime handler routes; HTTP 403 for anonymous.
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Dependency summary

No conditional TCs — all 15 TCs are immediately activatable at Stage 0 (TC-SOC-01 through TC-SOC-15).

---

## Notes to PM

1. **TC-SOC-01 (Society domain list):** Confirm whether "humanoid organizations" includes monstrous/non-civilized factions (goblin tribes, gnoll warbands). Automation needs an enumerated domain list.
2. **TC-SOC-04 (writing materials model):** Confirm how writing materials are gated: inventory item, equipment slot, or assumed-available in any downtime context. Automation needs a deterministic gate condition.
3. **TC-SOC-05 (page count input):** Confirm whether page count is player-declared or derived from document type. Automation needs a time calculation rule.
4. **TC-SOC-07 (specialist DC range):** Confirm exact DC for specialist documents — fixed at 30, or a 30–39 range based on specifics?
5. **TC-SOC-08 (special tools + seal DC):** Confirm whether a forgery kit or special materials reduce the DC 40 seal tier. If yes, provide the modifier formula.
6. **TC-SOC-12 (Crit Success quality):** AC does not explicitly describe Crit Success separately from Success for Create a Forgery. Confirm whether Crit Success produces a distinct quality level.
7. **TC-SOC-13 (Deception DC snapshot vs live):** Confirm whether the forger's Deception DC is snapshotted at creation time or evaluated at detection time. This determines the forgery data model (stored vs calculated).
8. **TC-SOC-14 (examine trigger):** Confirm whether "actively examine" is an explicit player action or automatic for any NPC/viewer who receives a forged document in-system.

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-skills-society-create-forgery

## Gap analysis reference
- DB sections: core/ch04/Society (Int) (REQs 1710–1714)
- Depends on: dc-cr-skill-system ✓

---

## Happy Path

### Recall Knowledge (Society)
- [ ] `[NEW]` Society covers: cultures, laws, social structures, history, humanoid organizations, nations, settlements.
- [ ] `[NEW]` Untrained Recall Knowledge permitted.

### Create a Forgery [Downtime, Secret, Trained]
- [ ] `[NEW]` Create a Forgery is a downtime activity (10 min per page); requires Trained Society and appropriate writing materials.
- [ ] `[NEW]` Difficulty: common documents (DC 20), specialist documents (DC 30+), official government seals (DC 40+).
- [ ] `[NEW]` On Failure: forgery is detectable; on Crit Failure: forgery is obviously fake AND character becomes aware it failed (can retry).
- [ ] `[NEW]` Detection: viewers use Society vs character's Deception DC when examining the forgery.

---

## Edge Cases
- [ ] `[NEW]` Create Forgery untrained: blocked.
- [ ] `[NEW]` Official seal forgery without special tools: DC 40 (highest tier, often auto-fail).

## Failure Modes
- [ ] `[TEST-ONLY]` Crit Fail: character is informed the forgery failed (not a surprise to them).
- [ ] `[TEST-ONLY]` Detection uses Deception DC not Forgery DC (two separate rolls).

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing downtime handlers
- Agent: qa-dungeoncrawler
- Status: pending
