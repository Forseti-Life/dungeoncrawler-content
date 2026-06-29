403 for anonymous is correct — all 4 routes require `access dungeoncrawler characters` permission. Now verify the table values directly from the PHP source since drush can't bootstrap:

All 32 checks pass. Now update the regression checklist and write the outbox:

- Status: done
- Summary: Verification PASS for impl-dc-cr-dc-rarity-spell-adjustment (commit af7f111c0). All 5 DC tables confirmed correct (32/32 probes pass), stacking is additive, 4 GET endpoints registered with proper `access dungeoncrawler characters` ACL (anonymous returns 403). Two non-blocking open items flagged for PM (minimum DC floor, "impossible" difficulty label). Regression checklist updated and committed 9a13e1be0. Outbox committed 2f47ae0a8.

**Verdict: APPROVE**

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260408-unit-test-20260408-144600-impl-dc-cr-dc-rarity-spell-adjustment
- Generated: 2026-04-08T15:14:15+00:00
