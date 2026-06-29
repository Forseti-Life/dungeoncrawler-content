The suite activation is confirmed. Everything is committed and validated.

- Status: done
- Summary: Activated the `dc-cr-downtime-mode` test suite into `qa-suites/products/dungeoncrawler/suite.json` with 10 test cases (TC-DTM-01–10) covering all 14 AC items: time scale in days (TC-DTM-01), Earn Income gold table + fail/crit-fail cooldown (TC-DTM-02–03), retraining 7-day cost (TC-DTM-04), crafting prerequisites + 4-day baseline + Alchemist free items (TC-DTM-05), Subsist (TC-DTM-06), Treat Disease (TC-DTM-07), business income (TC-DTM-08), and security ACs for server-validated gold and build mutations with audit logging (TC-DTM-09–10). Added 2 ACL rules to `qa-permissions.json`: `dc-cr-downtime-mode-activity-acl` (all downtime activity endpoints) and `dc-cr-downtime-mode-gold-log-acl` (audit log read). All entries tagged `feature_id: dc-cr-downtime-mode`. Suite validated clean (94 suites, 7 manifests OK). Commit: `f3542560b`.

## Next actions
- Dev implements `dc-cr-downtime-mode` against these TCs
- QA runs targeted verification on dev's implementation commit when dispatched

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 40
- Rationale: Suite activation is a prerequisite for Stage 4 regression on downtime mode. Unblocks dev implementation with clear test targets, especially the security ACs (gold duplication prevention) which are high-risk.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260411-235513-suite-activate-dc-cr-downtime-mode
- Generated: 2026-04-12T00:37:24+00:00
