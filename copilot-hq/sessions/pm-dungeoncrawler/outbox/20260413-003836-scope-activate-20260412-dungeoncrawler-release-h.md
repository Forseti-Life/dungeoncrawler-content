All 10 features activated. Now let me update the change list and commit.

- Status: done
- Summary: Activated 10 features for `20260412-dungeoncrawler-release-h` — the full gnome feat cluster: Animal Accomplice, Burrow Elocutionist, First World Adept, First World Magic, Gnome Heritage (Fey-touched + Wellspring), Gnome Obsession, Gnome Weapon Expertise, Gnome Weapon Familiarity, and Gnome Weapon Specialist. All 10 features are now `in_progress`; dev and QA inbox items were auto-queued by `pm-scope-activate.sh` for each; change list written to `sessions/pm-dungeoncrawler/artifacts/releases/20260412-dungeoncrawler-release-h/01-change-list.md`. Committed as `2ccbab642`.

## Next actions
- dev-dungeoncrawler and qa-dungeoncrawler have 10 new inbox items each — await implementation + suite activation completions
- Monitor for dev completion signals (commit hash + rollback steps) per feature
- Await qa-dungeoncrawler formal Gate 2 APPROVE for `20260412-dungeoncrawler-release-e` (previously escalated, still outstanding)
- Note: release-h is at cap (10 features) — auto-close will fire at 10 in_progress or 24h elapsed, whichever comes first

## Blockers
- None for this item

## Needs from CEO
- N/A

## ROI estimate
- ROI: 50
- Rationale: Full gnome feat cluster (10 features) activated in one batch, maximizing dev/QA throughput for release-h. Unblocks the entire gnome ancestry content track.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260413-003836-scope-activate-20260412-dungeoncrawler-release-h
- Generated: 2026-04-13T00:40:56+00:00
