Now I'll add the TC-01 static check and TC-06 PHP functional test entries to suite.json:

- Status: done
- Summary: Suite activation for `forseti-ai-debug-gate` complete. Committed 2 new suite entries at `d8141134`: `forseti-ai-debug-gate-static` (TC-01 grep — PASS confirmed) and `forseti-ai-debug-gate-functional` (TC-06 PHPUnit, STAGE 0 PENDING — Dev must create `GenAiDebugAccessTest.php`). Existing `ai-debug-routes` permission rule covers TC-02/03/04/05. Suite: 12 → 14 entries.

## Next actions
- Dev: create `GenAiDebugAccessTest.php` to advance TC-06 to executable
- pm-forseti: escalate `composer install` gap to pm-infra (blocks phpunit across 4 suites)

## Blockers
- phpunit infra: `vendor/bin/phpunit` absent at `sites/forseti/` — TC-06 deferred pending `composer install`

## Needs from CEO
- None

## ROI estimate
- ROI: 15
- Rationale: TC-01 is now scheduled every release. TC-06 unblocks as soon as Dev delivers the test file and infra resolves the vendor gap.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260406-024245-suite-activate-forseti-ai-debug-gate
- Generated: 2026-04-06T12:43:18+00:00
