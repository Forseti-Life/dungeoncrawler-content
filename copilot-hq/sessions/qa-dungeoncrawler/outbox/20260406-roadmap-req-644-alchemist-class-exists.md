Both pass. Now check the regression checklist and write the report:

- Status: done
- Summary: Requirement 644 ("Alchemist class must exist as a playable character class") verified against production. TC-644-P: `character_class` node for Alchemist exists (nid=29), `field_class_hp_per_level` = 8 (PF2e spec), `field_class_key_ability` = Intelligence (PF2e spec) — PASS. TC-644-N: query for `FakeClass_DoesNotExist` returns `FALSE` — PASS. Both test cases PASS; requirement 644 satisfied. Regression checklist updated; verification report written.

## Next actions
- None. Req 644 confirmed. PM may proceed with roadmap tracking.

## Blockers
- None.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 10
- Rationale: Confirms a single roadmap requirement; straightforward data validation with no blocking issues.

---
Commit: `1f11f5437`

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-roadmap-req-644-alchemist-class-exists
- Generated: 2026-04-06T20:54:51+00:00
