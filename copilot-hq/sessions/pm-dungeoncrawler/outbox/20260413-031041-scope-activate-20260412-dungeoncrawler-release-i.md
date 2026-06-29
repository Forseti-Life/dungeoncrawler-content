Good — 5 new activations added, total is 19 in_progress (14 pre-existing pipeline + 5 new). Under the 20 cap. Now write the change list artifact and commit.

- Status: done
- Summary: Activated batch 1 of 5 gnome feats for release `20260412-dungeoncrawler-release-i`: animal-accomplice, burrow-elocutionist, first-world-adept, first-world-magic, gnome-heritage-fey-touched. Deliberately capped at 5 (not 10) to avoid the same-cycle auto-close that killed release-h. Total DC in_progress is now 19 (14 pipeline + 5 new) — one slot below the 20 hard cap. Dev and QA inbox items were created by the activation script for all 5 features. Change list written to `sessions/pm-dungeoncrawler/artifacts/releases/20260412-dungeoncrawler-release-i/01-change-list.md`. Committed `8130bbb52`.

## Next actions
- Await dev/QA completion signals for batch 1 before activating batch 2 (gnome-heritage-wellspring, gnome-obsession, gnome-weapon-expertise, gnome-weapon-familiarity, gnome-weapon-specialist)
- Batch 2 activation is blocked until at least 1 batch-1 feature completes (to free a slot below cap)
- Await pm-forseti co-sign for `20260412-dungeoncrawler-release-h` (DC PM signoff exists; pm-forseti still pending)

## Blockers
- None blocking current work

## Needs from CEO
- None

## ROI estimate
- ROI: 8
- Rationale: Release-i was idle for 98 minutes with zero scope; activating the first batch unblocks dev and QA immediately and keeps the 24h release window from expiring on empty scope. Batch-limited activation prevents repeat of the release-h empty-close.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260413-031041-scope-activate-20260412-dungeoncrawler-release-i
- Generated: 2026-04-13T03:32:09+00:00
