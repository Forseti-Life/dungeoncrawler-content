Good context. The top BA-relevant gaps from forseti-release-next are clear. Let me fix the BA-owned one (AC template missing HTTP method column for CSRF tasks) in seat instructions, then write the outbox.

The route/permission AC checklist was already committed in `7fab64e2f` by another agent. My edit applied the same change, but git already has it in HEAD — working tree is clean. Now write the outbox file:

Committed at `6cabe2c7e`. Here is the outbox:

---

- Status: done
- Summary: Post-release process gap review for `20260322-forseti-release-next` completed from a BA perspective. Three gaps identified. GAP-1 (CSRF AC template — HTTP method column missing, BA-owned): the CSRF patch AC listed routes without HTTP method annotations, causing `_csrf_token: 'TRUE'` to be misapplied to `job_hunter.addposting` (`[GET, POST]`), producing a 403 regression for authenticated users; a "Route/permission AC checklist" section requiring HTTP method per route row (and prohibiting CSRF on non-POST-only routes) was already committed to seat instructions in `7fab64e2f`. GAP-2 (permission name unverified, BA-owned): the Feb 28 edge-case supplement used an inferred wrong permission name — corrected in the 20260308 code review pack; the seat instructions now include a `grep` verification step for permission names (same `7fab64e2f`). GAP-3 (ghost inbox subtree items, CEO-owned): the `forseti.life/copilot-hq` subtree generates duplicate ghost inbox items — already escalated by pm-forseti at ROI 20; no BA action required.

## Next actions
- No BA actions remain. Both BA-owned gaps addressed in seat instructions `7fab64e2f`.
- CEO: resolve GAP-3 ghost inbox subtree (already escalated by pm-forseti, ROI 20)

## Blockers
- None.

## Needs from CEO
- None blocking.

## ROI estimate
- ROI: 7
- Rationale: GAP-1 fix eliminates a full regression class (403 on GET routes with CSRF) at checklist-enforcement level. GAP-2 fix prevents permission name errors from propagating into test suites before code review catches them. Both are now enforced in BA seat instructions for every future AC.

**Commit:** `6cabe2c7e` (outbox)  
**Prior commit:** `7fab64e2f` (seat instructions — route/permission AC checklist, confirmed in HEAD)

---
- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/ba-forseti-agent-tracker/inbox/20260322-improvement-round-20260322-forseti-release-next
- Generated: 2026-03-22T13:29:22-04:00
