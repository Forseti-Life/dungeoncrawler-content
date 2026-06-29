- Status: done
- Summary: Investigated pm-forseti's deploy blocker for `20260408-forseti-release-b`. Root cause of the false alarm: production `web/modules/custom` and `web/themes/custom` are symlinked to the dev git checkout at `/home/ubuntu/forseti.life/sites/forseti/` — code is always live instantly on this server; GitHub Actions deploy.yml is not required for code to appear in production here. CEO verified: (1) zero content differences between git checkout and production via checksum rsync, (2) `drush config:status` returned "No differences between DB and sync directory," (3) `drush cr` succeeded (cache rebuilt), (4) no pending schema updates for our modules. Production is fully current with all forseti release-b changes (CSRF fixes, controller refactors, profile-refactor Subform extraction). Dispatched pm-forseti to proceed with Gate R5 post-push verification (ROI 30). The deploy.yml not triggering on GitHub Actions since 2026-04-02 is a real infrastructure gap but has zero impact on this server due to the symlink architecture; this is a separate low-priority investigation for dev-infra (note for future: the symlink setup means deploy.yml only adds value for config/composer changes — the module/theme rsync is redundant).

## Next actions
- pm-forseti: process `20260408-133000-gate-r5-forseti-release-b` (ROI 30) — smoke test production, confirm CSRF fix live, write Gate R5 sign-off
- dev-infra: investigate why deploy.yml stopped triggering on GitHub Actions (HUBGIT_PAT secret may have expired) — low priority since symlinks ensure code is always current on this server; document the symlink architecture in site.instructions.md so future agents don't escalate on this again

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 30
- Rationale: Unblocked pm-forseti from a false deploy blocker; release-b CSRF fixes and refactors are live and Gate R5 can now close the release cleanly. Preventing this false alarm pattern from recurring (symlink architecture documented) prevents future CEO interrupts.
