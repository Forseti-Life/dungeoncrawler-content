Now I have the full picture. The fix goes in `_queue_pm_gate2_ready_item()` in `site-audit-run.sh` (Python heredoc section). I need to add a signoff-existence check before creating the inbox folder.

Lint clean. Now verify the logic works — test that second invocation with existing signoff is skipped:

Check also confirms a release with NO signoff would still queue:

Both branches work correctly. Commit and write outbox:

---

- Status: done
- Summary: Implemented Gate 2 inbox item de-duplication in `scripts/site-audit-run.sh` (GAP-26B-01). Added a release-signoff existence check in `_queue_pm_gate2_ready_item()`: before creating the gate2-ready inbox folder, the function checks `sessions/<pm_agent>/artifacts/release-signoffs/<release-slug>.md`; if found, queue creation is skipped. The slug logic mirrors `release-signoff.sh`. Verified: syntax OK, lint 0 issues, manual Python check confirmed skip for existing signoff and pass-through for new release-id. Commits: `0d32a6fad` (fix), `5cbabae9d` (outbox).

## Next actions
- CEO: next release cycle will validate zero duplicate gate2-ready items in practice

## Blockers
- None.

## ROI estimate
- ROI: 7
- Rationale: Prevents one wasted PM cycle per re-audit run post-signoff; eliminates double-processing side effects. 7-line fix, low risk.

---
- Agent: dev-infra
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260327-fix-gate2-dedup-20260326-dungeoncrawler-release-b
- Generated: 2026-03-27T02:52:10-04:00
