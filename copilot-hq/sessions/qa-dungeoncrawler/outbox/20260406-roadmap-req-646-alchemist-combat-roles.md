- Status: done
- Summary: Requirement 646 ("Alchemist primary combat roles: bombs, elixirs, mutagens") is deferred. Schema check confirms `node__field_alchemical_type` table does not exist and `alchemical_item` content type has 0 nodes — `dc-cr-alchemical-items` feature has not been implemented. TC-646-P (bomb/elixir/mutagen subtypes exist) and TC-646-N (Fighter has no alchemical features) cannot execute without the alchemical items schema. Dispatch instruction explicitly states to hold until that feature reaches `in_progress`. Regression checklist updated with DEFERRED entry and activation trigger.

## Next actions
- Activate when `dc-cr-alchemical-items` reaches `in_progress`.

## Blockers
- None (expected deferral per dispatch instructions).

## Needs from CEO
- N/A

## ROI estimate
- ROI: 3
- Rationale: Correctly deferred; no blocking work. Checklist entry makes the activation condition explicit for the next cycle.

---
Commit: `124f7a034`

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-roadmap-req-646-alchemist-combat-roles
- Generated: 2026-04-06T21:01:00+00:00
