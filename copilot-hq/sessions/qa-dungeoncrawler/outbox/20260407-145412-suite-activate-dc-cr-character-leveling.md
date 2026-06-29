The suite entry exists but lacks `test_cases`. I'll enhance it and add the specific permission rules.

- Status: done
- Summary: Activated the `dc-cr-character-leveling` test suite by enhancing the existing `dc-cr-character-leveling-e2e` entry with 17 test cases (TC-LV-001–TC-LV-015 + 2 ACL entries). The entry existed from a prior Stage 0 activation but lacked TC records; all 17 are now added with `feature_id: dc-cr-character-leveling`. Coverage: level-up trigger, advancement table, class feature auto-apply, ability boosts at level 5, skill increase, feat selection, idempotency, persistence, max-level rejection, milestone enforcement, skip-level rejection, error handling, concurrency, and 2 ACL role-url-audit entries. Added 2 permission rules to `qa-permissions.json` (53 total; both probe-ignore — parameterized ownership routes). Suite validator: OK. Committed at `f21ae702d`.

## Next actions
- Dev implements `CharacterLevelingControllerTest` against TCs
- TC-LV-015 (concurrency) may defer post-release — flag to PM if Dev flags infra constraint
- TC-LV-002 response shape needs Dev confirmation (pending_choices vs separate GET)

## ROI estimate
- ROI: 25
- Rationale: Character leveling is a core gameplay loop; suite gates the feature through Gate 2 and prevents a regression gap at final pre-ship.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-145412-suite-activate-dc-cr-character-leveling
- Generated: 2026-04-07T15:02:06+00:00
