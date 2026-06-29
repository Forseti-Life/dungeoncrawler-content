# Feature Brief

- Work item id: forseti-agent-tracker-payload-size-limit
- Website: forseti.life
- Module: copilot_agent_tracker
- Status: ready
- Release: 20260409-forseti-release-j
- Priority: P2
- Feature type: security
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Release target: 20260409-forseti-release-j
- Source: BA inventory CAT R6 (ROI 6) — `features/forseti-copilot-agent-tracker/ba-refactor-recommendations.md`

## Goal

Add a 64 KB payload size check to `ApiController.php` before `json_decode()` is called on the raw POST body. Without this guard, a large or malformed payload can consume memory without bound.

## Acceptance criteria

- [ ] `strlen($content) > 65536` check added in `ApiController.php` before the `json_decode($content, TRUE)` call
- [ ] If content exceeds 64 KB, return `JsonResponse` with HTTP 413 and body `{"error": "Payload too large"}`
- [ ] Existing validation flow (missing JSON payload, invalid JSON, etc.) unchanged
- [ ] Unit test added confirming that a 65537+ byte payload returns HTTP 413

## Affected file

- `sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/ApiController.php`

## Implementation sketch

```php
if (strlen($content) > 65536) {
    return new JsonResponse(['error' => 'Payload too large'], 413);
}
```

Insert after `$content = $request->getContent();` and before `json_decode`.

## Verification

```bash
grep -n "65536\|Payload too large\|413" sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/ApiController.php
```

Both the size check and the 413 response string must appear.

QA test: POST a body larger than 64 KB to the telemetry endpoint and confirm HTTP 413 response.

## Non-goals

- Changing the 64 KB limit (follow BA recommendation)
- Adding size checks to other endpoints (scope-limited to the telemetry API in ApiController)

## Risks

Low — defensive addition that only affects oversized payloads. Normal telemetry payloads are well under 1 KB.

## Security acceptance criteria
- Authentication/permission surface: Telemetry API routes require authenticated agent session; unauthenticated POSTs already blocked at routing layer.
- CSRF expectations: POST routes use `_csrf_token: 'TRUE'` per copilot_agent_tracker routing; no change.
- Input validation: 64 KB hard cap is the change; existing validation (JSON structure, required fields) unchanged.
- PII/logging constraints: Oversized payload rejection must not log the raw payload content to watchdog (log only size and HTTP 413 status).
