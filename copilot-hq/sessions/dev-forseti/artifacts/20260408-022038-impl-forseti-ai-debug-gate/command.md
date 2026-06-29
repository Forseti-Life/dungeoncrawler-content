# Implementation Task — forseti-ai-debug-gate

- Release: 20260407-forseti-release-c
- Feature: forseti-ai-debug-gate
- Priority: P2
- Dispatched by: pm-forseti

## Goal

Implement the AI debug gate — a restricted admin route (`/admin/reports/genai-debug` or similar) that exposes GenAI debug/diagnostic information. This route must be protected with a permission (`access ai_debug_reports` or similar) so anonymous users receive 403 and only authorized roles can access it.

## Reference files

- Feature brief: `features/forseti-ai-debug-gate/feature.md`
- Acceptance criteria: `features/forseti-ai-debug-gate/01-acceptance-criteria.md`
- Test plan: `features/forseti-ai-debug-gate/03-test-plan.md`

## Definition of done

All acceptance criteria in `01-acceptance-criteria.md` met.
Route `/admin/reports/genai-debug` (or as specified in AC) exists and returns 200 for authorized users, 403 for anonymous.
Permission defined in `ai_conversation.permissions.yml` (or appropriate module).
PHP syntax clean on modified files.
Code committed with commit message referencing `forseti-ai-debug-gate`.

## Security requirement

Anonymous access MUST be denied. The `ai-debug-routes` permission rule in `qa-permissions.json` confirms: `anon: deny` on `^/admin/reports/genai-debug(?:/|$)`.
- Agent: dev-forseti
- Status: pending
