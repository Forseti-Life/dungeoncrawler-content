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
