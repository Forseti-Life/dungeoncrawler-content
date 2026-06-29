# Feature Brief

- Work item id: forseti-ai-debug-gate
- Website: forseti.life
- Module: ai_conversation
- Status: ready
- Release: 20260406-forseti-release-b
- Priority: P1
- Feature type: needs-testing
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Release target: 20260405-forseti-release-c
- Source: BA inventory AI-R2 (ROI 5)

## Goal

Verify and document that `GenAiDebugController` (586 lines) is properly gated so that none of its routes are accessible to anonymous or authenticated non-admin users in production. If any route is misconfigured, fix the routing permission or add an environment-based guard.

## PM Decision (AI-R2)

`GenAiDebugController` stays in production code (no environment flag needed) **on the condition** that all routes require `administer site configuration` or equivalent admin-only permission. This avoids code churn while ensuring the risk is contained. If QA finds any route is accessible to non-admins, dev must fix the routing immediately — no risk acceptance for exposed debug endpoints.

## Non-goals

- Removing or rewriting `GenAiDebugController`.
- Adding feature flags or environment-specific build steps.

## Gap Analysis

### Implementation status

| Requirement | Existing code path | Coverage status |
|---|---|---|
| All GenAiDebugController routes require admin permission | `ai_conversation.routing.yml` | Partial (unverified; no test coverage) |
| Anonymous 403 on all debug routes | routing config | None (no test) |
| Authenticated non-admin 403 on all debug routes | routing config | None (no test) |

### Coverage determination

- **Feature type: needs-testing** — Implementation likely exists (admin-only routing). Need QA verification and a permanent test to prevent regression.

### Test path guidance for QA

- `web/modules/custom/ai_conversation/tests/src/Functional/` — add tests confirming each `GenAiDebugController` route returns 403 for anonymous and authenticated non-admin users.

## Risks

- Low ROI (5) relative to other release items; if resource-constrained, may be deferred to release-c.

## Latest updates

- 2026-04-06: Grooming complete — test plan written by QA. Ready for next Stage 0 scope selection.

- 2026-04-06: Scoped into release — suite activation sent to QA.

- 2026-04-06: Grooming complete — test plan written by QA. Ready for next Stage 0 scope selection.

- 2026-04-05: Handed off to QA for test generation (pm-qa-handoff.sh)
- 2026-04-05: Feature brief created from BA inventory AI-R2; PM decision recorded above.

## Security acceptance criteria
- Authentication/permission surface: All `GenAiDebugController` routes require `administer site configuration` permission. Anonymous and authenticated non-admin users must receive 403 on all debug routes.
- CSRF expectations: GET-only debug routes — no state-changing POSTs, no CSRF token required.
- Input validation: No user-supplied input accepted on debug routes (read-only debug info only).
- PII/logging constraints: Debug output must not expose user PII, credentials, or API keys in response bodies or watchdog logs.
