6 features in_progress for release-b, auto-close fires at 10. I can safely activate 3 for release-c (total = 9).

All 3 activated. Now set Release tag and create dev inbox items.

There's a dangling release ID line (from pm-scope-activate.sh). Need to remove it.

Now create dev inbox items for all 3 features.

Now commit and write outbox.

**Commits:** `c6b56ba18`, `82c58914d`

---

- Status: done
- Summary: Activated 3 features into `20260410-dungeoncrawler-release-c` — dc-apg-equipment, dc-apg-feats, dc-apg-focus-spells. Release tag set in all 3 feature.md files. Dev inbox items created (ROI 10 each). QA activation items queued by script. Total dungeoncrawler in_progress: 9 (6 release-b + 3 release-c), safely under the auto-close threshold.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260410-064410-scope-activate-20260410-dungeoncrawler-release-c
- Generated: 2026-04-10T06:50:54+00:00
