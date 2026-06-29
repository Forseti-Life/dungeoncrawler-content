Now I have everything. Let me add the suite entry and permission rules.

- Status: done
- Summary: Activated the `dc-cr-darkvision` test suite into `qa-suites/products/dungeoncrawler/suite.json` with 15 TCs (TC-DV-01–TC-DV-15), all tagged `feature_id: dc-cr-darkvision`. Coverage: sense entity structure/effects, Low-Light Vision distinction, Dwarf ancestry senses reference, multi-ancestry no-duplication, encounter visibility Concealed/flat_footed bypasses, baseline behavior preserved (no darkvision), non-darkness Concealed not suppressed, bright-light no-op, null-senses crash guard, ancestry-swap integrity, character sheet API, and 2 ACL audit entries. TC-DV-15 is `pending-dev-confirmation` (admin UI route path TBD). Added 2 permission rules (55 total; both ignore/placeholder until Dev confirms paths). Suite validator: OK. Committed at `6cf6060eb`.

## Next actions
- Dev confirms sense entity admin route path → update `dc-cr-darkvision-admin-ui` path_regex + set TC-DV-15 to `active`
- Dev confirms `AncestryController` plain-string `vision` field migration path (regression risk #3)
- Dev confirms Low-Light Vision sense entity creation (required for TC-DV-03)

## Blockers
- None blocking suite activation; 2 open items need Dev follow-up

## ROI estimate
- ROI: 22
- Rationale: Darkvision integrates with ConditionManager and ancestry data; missing coverage would leave ConditionManager coupling regressions undetected at Gate 2.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260407-145412-suite-activate-dc-cr-darkvision
- Generated: 2026-04-07T15:05:44+00:00
