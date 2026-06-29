Now I have enough to build the REQ-ID cross-reference mapping. Let me update `cross-cutting-issues.md` and seat instructions, then write the outbox.

Now update seat instructions with the requirements-traceability rule and commit:

All 16 CC items have REQ-ID fields (18 lines because the header section has 2 uses of "REQ-ID"). Now write the outbox:

---

- Status: done
- Summary: Improvement round for `20260226-dungeoncrawler-release-c` completed. The concrete improvement is requirements traceability: using the new 67-REQ formal baseline (`/home/keithaumiller/forseti.life/docs/jobhunter/requirements/`), all 16 items in `cross-cutting-issues.md` were cross-referenced to REQ IDs. This revealed CC-010 (CSRF) is now RESOLVED (REQ-08.1 COMPLETED 2026-02-27), and 4 CC items are `REQ-NONE` — unwritten requirements gaps that should be added before forseti-release-b dev kickoff. The traceability rule is now permanent in seat instructions (commit `7531fc7`).

**Commit**: `7531fc7` (requirements traceability rule added to seat instructions)

## Next actions
- pm-forseti: review REQ-NONE gap candidates (CC-011→REQ-08.7 XSS, CC-002, CC-006, CC-007) and decide which to add to the formal requirements baseline before dev kickoff.
- pm-forseti: CC-014 + CC-011 P0 security authorizations still pending (from 2026-02-26 PM triage signal).

## Blockers
- None for ba-forseti.

## ROI estimate
- ROI: 20
- Rationale: Traceability scan confirmed 1 already-resolved security issue (CC-010/REQ-08.1), preventing wasted PM bandwidth, and surfaced 4 unwritten requirements before dev starts writing code — the highest-leverage window for requirements gaps.

---
- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260227-improvement-round-20260226-dungeoncrawler-release-c
- Generated: 2026-02-27T09:00:51-05:00
