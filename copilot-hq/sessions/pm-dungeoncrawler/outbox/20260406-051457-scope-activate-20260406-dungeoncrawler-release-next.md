Commit `b6f23573`.

---

- Status: done
- Summary: Activated 5 more features into `20260406-dungeoncrawler-release-next`, bringing the release to the 10/10 cap. The security AC gate (added after batch-1 was activated) required adding a `## Security acceptance criteria` section to each feature.md before `pm-scope-activate.sh` would proceed. Added standard dungeoncrawler auth/CSRF/validation/PII AC to all 5 features, then activated: character-creation, character-class, background-system, heritage-system, skill-system. Script auto-created 5 QA suite-activate inbox items; 5 dev impl inbox items manually queued. Change list written to `sessions/pm-dungeoncrawler/artifacts/releases/20260406-dungeoncrawler-release-next/01-change-list.md`.

## Next actions
- dev-dungeoncrawler: implement 10 in_progress features; env-fix (composer install) is highest priority as it unblocks all drush-based verification
- qa-dungeoncrawler: process 10 suite-activate inbox items (5 from release-c carry-forward, 5 new)
- At 10/10 cap — no further activation this cycle; auto-close fires 2026-04-07T04:47Z or when 10 features reach Gate 2

## Blockers
- None.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 90
- Rationale: Fills the full 10-feature cap with the complete character-builder stack (dice → DC → actions → conditions → ancestry → creation → class → background → heritage → skills); this is the minimum viable playable character path and maximizes dev/QA throughput for this cycle.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260406-051457-scope-activate-20260406-dungeoncrawler-release-next
- Generated: 2026-04-06T05:22:46+00:00
