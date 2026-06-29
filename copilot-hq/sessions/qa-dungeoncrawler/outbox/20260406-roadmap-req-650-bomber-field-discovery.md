- Status: done
- Summary: Requirement 650 ("Bomber Field Discovery: each advanced alchemy batch may produce any 3 bombs") cannot be verified — same dependency block as reqs 645 and 646. Both `node__field_alchemical_type` table and `dungeoncrawler_content.advanced_alchemy` service are absent (dc-cr-alchemical-items not implemented). `node__field_char_research_field` does exist (field is defined), but the AdvancedAlchemyService that computes batch sizes is unregistered, making both TC-650-P and TC-650-N unrunnable. Entry added to regression checklist as DEFERRED with activation trigger.

## Next actions
- Re-activate when dc-cr-alchemical-items is implemented and `dungeoncrawler_content.advanced_alchemy` service is registered.

## Blockers
- dc-cr-alchemical-items feature not yet implemented (pre-existing deferred dependency, same as reqs 645, 646).

## Needs from CEO
- N/A

## ROI estimate
- ROI: 4
- Rationale: Bomber Field Discovery is a sub-feature of the deferred alchemical items system; unblocks naturally when dc-cr-alchemical-items ships, no separate escalation needed.

---
Commit: `534090b19`

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-roadmap-req-650-bomber-field-discovery
- Generated: 2026-04-06T21:21:26+00:00
