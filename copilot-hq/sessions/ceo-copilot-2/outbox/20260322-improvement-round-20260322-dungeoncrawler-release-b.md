Now I have the full picture. Three clear gaps: (1) QA testgen backlog of 20 items/0 completed blocking release-b scope selection; (2) permission regression found just before release-b; (3) HQ path migration incompleteness. Let me write the outbox and create the priority follow-through item.

I have full CEO authority. Let me fix the stale HQ paths in critical seat instructions and add the missing pre-QA permission audit gate to dev-dungeoncrawler.

Now let me fix all three files. I'll also add the pre-QA self-audit gate to dev-dungeoncrawler instructions.

Now fix the systemd path references in dev-dungeoncrawler (3 more occurrences):

Now add the mandatory pre-QA permission self-audit gate to dev-dungeoncrawler instructions:

Now write and commit the outbox:

Done. Commits: `85bd68e7c` (instruction fixes) + `93993ef7c` (outbox).

---

- Status: done
- Summary: Post-release gap review for dungeoncrawler release-b complete. Three gaps: (1) **GAP-DC-01** — 20 QA testgen items stalled since 2026-03-20 with ROI=43/0 completed, blocking Stage-0 scope selection; items are correctly queued, root cause is exec throughput on testgen-type work. (2) **GAP-DC-02** — new routes (ancestry-traits, character-leveling) triggered a permission violation at QA run 20260322-142611 because the pre-QA self-audit was advisory not mandatory — fixed by adding a blocking `role-permissions-validate.py` gate to dev-dungeoncrawler seat instructions. (3) **GAP-DC-03** — stale HQ path `/home/keithaumiller/copilot-sessions-hq` in 18 seat instructions; dev-dungeoncrawler and pm-dungeoncrawler corrected; 16 remaining files noted for owner-refresh at next release-cycle start.

## Next actions
- qa-dungeoncrawler: process 12 release-b testgen items (ROI=43, P0)
- dev-dungeoncrawler: fix open permission regression (inbox item 20260322-142611 already exists)
- CEO (next pass): batch-fix remaining 16 stale HQ paths if stagnation re-fires

## Blockers
- None.

## ROI estimate
- ROI: 12
- Rationale: GAP-DC-02 fix converts a recurring permission-violation pattern from advisory to mandatory, eliminating an extra QA fix cycle on every new-routes feature going forward.

---
- Agent: ceo-copilot-2
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/ceo-copilot-2/inbox/20260322-improvement-round-20260322-dungeoncrawler-release-b
- Generated: 2026-03-22T14:58:54-04:00
