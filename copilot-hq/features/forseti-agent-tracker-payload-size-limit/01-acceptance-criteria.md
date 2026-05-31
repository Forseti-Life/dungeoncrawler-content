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
