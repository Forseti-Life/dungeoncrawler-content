# Suite Activation: forseti-agent-tracker-payload-size-limit

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-09T23:54:31+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-agent-tracker-payload-size-limit"`**  
   This links the test to the living requirements doc at `features/forseti-agent-tracker-payload-size-limit/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-agent-tracker-payload-size-limit-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-agent-tracker-payload-size-limit",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-agent-tracker-payload-size-limit"`**  
   Example:
   ```json
   {
     "id": "forseti-agent-tracker-payload-size-limit-<route-slug>",
     "feature_id": "forseti-agent-tracker-payload-size-limit",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-agent-tracker-payload-size-limit",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan — forseti-agent-tracker-payload-size-limit

- Feature: Add 64 KB payload size limit to telemetry API
- QA owner: qa-forseti
- Release target: 20260409-forseti-release-j
- Date written: 2026-04-09

## Overview

Validate the 64 KB size guard in `ApiController.php`. Two test paths: normal payload passes; oversized payload returns 413. Static check confirms the guard is present.

---

## Test Cases

### TC-01 — Static: size check present in ApiController
- **Suite:** static grep
- **AC item:** `strlen($content) > 65536` check added before `json_decode`
- **Steps:**
  1. `grep -n "65536\|Payload too large\|413" sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/ApiController.php`
- **Expected:** Lines containing `65536`, `Payload too large`, and `413` all appear in output
- **Automated:** Yes

### TC-02 — Functional: oversized payload returns 413
- **Suite:** HTTP functional test
- **AC item:** Payload > 64 KB returns `{"error": "Payload too large"}` with HTTP 413
- **Steps:**
  1. POST a JSON body of 65537+ bytes (e.g., a large string field) to the telemetry endpoint with valid auth
  2. Check response status code and body
- **Expected:** HTTP 413; response body `{"error": "Payload too large"}`
- **Roles covered:** authenticated agent user
- **Automated:** Yes — programmatic payload generation trivial

### TC-03 — Functional: normal payload still processed
- **Suite:** HTTP functional test
- **AC item:** Valid small payload (< 1 KB) proceeds to existing validation
- **Steps:**
  1. POST a valid < 1 KB telemetry JSON payload to the telemetry endpoint with valid auth
  2. Check response
- **Expected:** Existing success response (not 413); normal processing flow unchanged
- **Roles covered:** authenticated agent user
- **Automated:** Yes

### TC-04 — Static: size check placed BEFORE json_decode
- **Suite:** static analysis
- **AC item:** Guard must precede `json_decode` call to be effective
- **Steps:**
  1. Inspect `ApiController.php` — line number of size check must be < line number of `json_decode($content`
- **Expected:** Size check line appears before `json_decode` line in file
- **Automated:** Manually verify line ordering; note line numbers in verification report

## Pass/Block criteria

- **PASS:** TC-01 confirms all three strings present; TC-02 returns 413; TC-03 shows normal path unchanged; TC-04 confirms ordering
- **BLOCK:** Oversized payload does not return 413 (check missing or placed after json_decode); or normal payload broken by check

### Acceptance criteria (reference)

# Acceptance Criteria — forseti-agent-tracker-payload-size-limit

- Feature: Add 64 KB payload size limit to telemetry API
- Release target: 20260409-forseti-release-j
- PM owner: pm-forseti
- Date groomed: 2026-04-09
- Priority: P2

## Gap analysis reference

Feature type: `security` — BA inventory CAT R6. `ApiController.php` calls `json_decode()` on raw POST body with no size check. Memory exhaustion risk on oversized payloads. Fix: add `strlen($content) > 65536` check before `json_decode`, returning HTTP 413 on violation.

## Knowledgebase check
- No prior lessons on payload size limiting in this module.
- Normal telemetry payloads are well under 1 KB; 64 KB is a conservative safe cap.

## Happy Path

- [ ] Normal telemetry POST (< 64 KB): passes size check, proceeds to existing validation flow unchanged
- [ ] Response on valid payload: unchanged (201 Created or whatever current success response is)

## Edge Cases

- [ ] Payload exactly 65536 bytes: accepted (boundary inclusive)
- [ ] Payload exactly 65537 bytes: rejected with HTTP 413

## Failure Modes

- [ ] If size check placed after `json_decode`, no protection is provided — dev must place check before `json_decode`
- [ ] `getContent()` on multipart request may behave differently — but telemetry API is JSON-only, so this is not a concern

## Permissions / Access Control

- [ ] Telemetry API routes require authenticated agent session; the size check applies to all callers equally.

## Data Integrity

- [ ] No DB changes required.
- [ ] Rollback: remove the size check block.

## Dev definition of done
- [ ] `strlen($content) > 65536` check added in `ApiController.php` before `json_decode($content, TRUE)`
- [ ] Returns `new JsonResponse(['error' => 'Payload too large'], 413)` when limit exceeded
- [ ] Verify: `grep -n "65536\|Payload too large\|413" sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/ApiController.php`

## QA test path
- TC: POST a 65537+ byte body to the telemetry endpoint; expect HTTP 413 response with `{"error": "Payload too large"}`.
- TC: POST a normal (<1 KB) valid telemetry payload; expect existing success response unchanged.
- Static check: `grep "65536" ApiController.php` returns the size check line.
