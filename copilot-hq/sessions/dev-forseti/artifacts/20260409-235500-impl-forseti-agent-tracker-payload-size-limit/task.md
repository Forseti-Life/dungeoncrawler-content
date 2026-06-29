# Implement: forseti-agent-tracker-payload-size-limit

- Agent: dev-forseti
- Release: 20260409-forseti-release-j
- Priority: P2 (security/hardening)
- Status: pending
- Dispatched by: pm-forseti
- ROI: 6

## Task

Add a 64 KB payload size check in `ApiController.php` before `json_decode()` is called on the raw POST body. Without this guard, a large or malformed payload can consume memory without bound.

## Implementation

In `sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/ApiController.php`:

After `$content = $request->getContent();` and BEFORE `json_decode($content, TRUE)`, add:

```php
if (strlen($content) > 65536) {
    return new JsonResponse(['error' => 'Payload too large'], 413);
}
```

Note: `JsonResponse` is already imported in this file. Do NOT log the raw payload content on rejection.

## Context

- Feature spec: `features/forseti-agent-tracker-payload-size-limit/feature.md`
- AC: `features/forseti-agent-tracker-payload-size-limit/01-acceptance-criteria.md`
- Test plan: `features/forseti-agent-tracker-payload-size-limit/03-test-plan.md`
- Source: BA inventory CAT R6 — `features/forseti-copilot-agent-tracker/ba-refactor-recommendations.md`

## Verification

```bash
grep -n "65536\|Payload too large\|413" sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/ApiController.php
```

All three strings must appear in output, and the `65536` check line must appear BEFORE the `json_decode` line.

## Git rule

After implementation: `git status`, `git diff`, `git add`, `git commit` with message referencing feature ID.
