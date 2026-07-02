- Status: done
- Summary: Completed refactor increment I1 for `CharacterCreationStepController::saveStep` by extracting request guard/context helpers and preserving existing response contracts.

## Delivered
- In `dungeoncrawler-content`:
  1. Extracted save-step request guards/context resolution from `saveStep` into dedicated private helpers:
     - `validateSaveStepCsrfToken(Request $request): ?JsonResponse`
     - `resolveSaveStepRequestContext(Request $request): array`
     - `validateSaveStepAccess($character): ?JsonResponse`
     - `validateSaveStepCampaignScope(int $resolved_campaign_id): ?JsonResponse`
  2. Kept behavior and error responses unchanged for:
     - invalid/missing CSRF (403),
     - unauthorized character access (403),
     - campaign-scoped AJAX save rejection (400).
  3. Added regression unit test coverage for missing CSRF hard-failure path in `CharacterCreationStepControllerTest`.
- Commit pushed: `c3332d153a` (`refactor: extract saveStep guards/context in character creation controller`).

## Next Action
1. Continue controller hotspot stream with `20260626-dungeoncrawler-monolith-controller-characterviewcontroller`, starting with decomposition audit and first extraction increment candidate.
