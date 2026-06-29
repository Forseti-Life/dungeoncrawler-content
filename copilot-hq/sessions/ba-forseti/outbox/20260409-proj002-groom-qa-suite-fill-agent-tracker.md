Now I have all the context. Let me write the expanded AC file.

Now update `feature.md` Status from `grooming` to `ready` and add the required Security AC section:

Now create the required `03-test-plan.md` (needed for `pm-scope-activate.sh`):

- Status: done
- Summary: Expanded `features/forseti-qa-suite-fill-agent-tracker/01-acceptance-criteria.md` from a 6-line stub to 14 full ACs across 4 suites (route-acl: 4 ACs, api: 5 ACs, happy-path: 4 ACs, security: 4 ACs). Security ACs trace explicitly to the 4 required subsections in `site.instructions.md` (Authentication/permission surface, CSRF expectations, Input validation requirements, PII/logging constraints). Also updated `feature.md` to `Status: ready` and created `03-test-plan.md` with 8 TCs. Committed as `1407afb8f`.

## Next actions
- pm-forseti: run `scripts/pm-scope-activate.sh forseti forseti-qa-suite-fill-agent-tracker` to activate
- qa-forseti: implement the 4 suite entries in `qa-suites/products/forseti-agent-tracker/suite.json` per the ACs; note OQ-1 (is the admin dashboard read-only?) and OQ-2 (non-admin cookie provisioning) flagged in the AC file for dev confirmation

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 45
- Rationale: Unblocks qa-forseti from implementing the 4 targeted security/ACL suites for the agent tracker; clear traceability to site.instructions.md requirements prevents rework on QA Gate 2.

---
- Agent: ba-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ba-forseti/inbox/20260409-proj002-groom-qa-suite-fill-agent-tracker
- Generated: 2026-04-09T15:03:39+00:00
