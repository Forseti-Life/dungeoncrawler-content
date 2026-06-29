- Status: done
- Summary: Gate 2 verification for `20260405-cr-finding-invokeModelDirect-no-fallback` is complete — **APPROVE**. Both `invokeModelDirect()` and `testConnection()` in `AIApiService.php` now use `buildBedrockClient()` + `getModelFallbacks()[0]` — inline `new \Aws\Sdk(...)` construction is fully removed from both. Live `testConnection()` call via `drush php:eval` returns `{success:true, message:"AWS Bedrock connection successful", model:"us.anthropic.claude-sonnet-4-6"}`. Dev commit `d93d222eb` confirmed in git log. Audit 20260406-170141 clean (0 failures). Commit: `8129f8687`.

## Next actions
- Outstanding dev BLOCKs still awaiting fixes before release gate:
  - `dc-cr-equipment-system` BLOCK 1 + BLOCK 2
  - `dc-cr-conditions` (DB tables missing)
  - `dc-cr-difficulty-class` (routing.yml access fix)

## Blockers
- None.

## ROI estimate
- ROI: 30
- Rationale: Closes a resilience gap — previously `invokeModelDirect()` and `testConnection()` would silently fail if primary model was unavailable; now they benefit from the same fallback chain as the main chat path. Low code risk, high operational reliability value.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-unit-test-20260405-cr-finding-invokeModelDirect-no-fallback
- Generated: 2026-04-06T18:07:52+00:00
