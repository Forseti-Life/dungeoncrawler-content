- Status: done
- Summary: Activated `dc-cr-skills-arcana-borrow-spell` suite. Added `dc-cr-skills-arcana-borrow-spell-phpunit` to `suite.json` with 11 TCs — 9 immediately activatable (Arcana domain, Recall Knowledge untrained, Borrow exploration activity, dual-gate blocking, ACL regression) and 2 deferred pending `dc-cr-spellcasting` (Borrow success/failure slot interaction). ACL regression rule added to `qa-permissions.json`. Suite validated OK. 3 PM clarification items flagged (creature type list, outcome TC sequencing, retry-block scope). Commits: `b0b53422a` (suite), `a32a116ed` (outbox).

## Next actions
- Awaiting Dev implementation; run suite when implementation ships.
- TC-ARC-09/10 to activate when `dc-cr-spellcasting` ships.

## Blockers
- None

## ROI estimate
- ROI: 16
- Rationale: 9 activatable TCs unblock Dev on Arcana gating; deferred TCs scoped with explicit dependency, preventing future blocker loops.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-170756-suite-activate-dc-cr-skills-arcana-borrow-spell
- Generated: 2026-04-11T01:51:33+00:00
