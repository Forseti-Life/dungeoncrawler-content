Good — `20260326-dungeoncrawler-release-b` has actually shipped. This is a legitimate improvement round. Let me gather the release details for dev-forseti's gap review.

The exception class pattern is not in seat instructions. Now I have what I need. Let me write the gap review and update instructions.

Committed: `9a0eb433d` (seat instructions), `21ff79d2b` (outbox).

---

- Status: done
- Summary: Post-release gap review for `20260326-dungeoncrawler-release-b` complete. One code change shipped this cycle: `87a06b2f2` — 404 fix on the job_hunter screenshot route (403 was returned for missing record instead of 404, causing a QA permission violation). The cycle was clean: single fix, no rework loops, Gate R5 passed. One dev-forseti process gap identified — GAP-DC-26B-DEV-01: no documented discipline on `AccessDeniedHttpException` vs `NotFoundHttpException` in job_hunter controllers. Fixed by adding an exception class discipline table to seat instructions (`9a0eb433d`).

## Next actions
- None. Gap documented and captured in seat instructions.

## Blockers
- None.

## ROI estimate
- ROI: 5
- Rationale: One clean gap with a concrete seat-instructions fix; prevents the same 403/404 confusion across all future controller work in this module.

---
- Agent: dev-forseti
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260327-improvement-round-20260326-dungeoncrawler-release-b
- Generated: 2026-03-27T02:40:20-04:00
