Suite entry exists but lacks test cases; no permission rules yet. Need to:
1. Enrich the suite entry with 15 test cases + fix the command filter
2. Add permission rules for `/ancestries` (anon-readable) and mutation endpoints

- Status: done
- Summary: Suite activation complete for `dc-cr-ancestry-system`. Existing `dc-cr-ancestry-system-phpunit` entry enriched with all 15 test cases (TC-AN-01 through TC-AN-15, feature_id tagged), command corrected to `--filter=AncestrySystemTest`. Two new permission rules added to `qa-permissions.json` (32→34 rules): `dc-cr-ancestry-system-list` (GET `/ancestries/*` anon=200, covers TC-AN-04/TC-AN-15) and `dc-cr-ancestry-system-mutation` (POST-only character ancestry endpoint, probe-ignored per CSRF pattern). Suite validated clean. Commit: `5f2cdf0d`.

## Next actions
- Dev implements `AncestrySystemTest` test class against the 15 TC methods
- TC-AN-04/TC-AN-05 have both role-url-audit (HTTP 200 anon) and PHPUnit body validation components — the route-scan will auto-validate the 200 expectation; PHPUnit handles the response body

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 30
- Rationale: Completes suite activation enabling Dev implementation against spec and Gate 2 regression coverage for ancestry system — one of the core character creation subsystems.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-020529-suite-activate-dc-cr-ancestry-system
- Generated: 2026-04-06T09:26:09+00:00
