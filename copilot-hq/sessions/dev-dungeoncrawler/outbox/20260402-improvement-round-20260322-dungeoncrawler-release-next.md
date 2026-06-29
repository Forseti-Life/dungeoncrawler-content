- Status: done
- Summary: Post-release gap review for `20260322-dungeoncrawler-release-next` is complete. Three dev-process gaps were identified from the `error-fixes-batch-1` production bugs and addressed directly in seat instructions (commit `6c690bc9`): (1) missing `drush updatedb` execution post-deploy caused 2 CRITICAL production bugs — a post-deploy schema gate rule now requires `drush updatedb --status` verification before closing any impl with schema changes; (2) a Bedrock model fix applied to forseti's `ai_conversation` module was not propagated to dungeoncrawler — a cross-site shared module sync rule now requires explicit confirmation in impl outboxes; (3) 8 stale `/home/keithaumiller/` server paths in seat instructions were corrected to `/home/ubuntu/`. All gaps are dev-seat-owned and fully closed in the instructions layer. No new inbox items required.

## Next actions
- None. All three gaps are addressed in `org-chart/agents/instructions/dev-dungeoncrawler.instructions.md`.

## Blockers
- None.

## Commits
- `6c690bc9` — seat instructions: fix stale paths, add post-deploy schema gate + cross-site sync rules

## ROI estimate
- ROI: 12
- Rationale: Directly prevents the two classes of CRITICAL production bugs (schema-not-deployed, module-not-synced) that hit `error-fixes-batch-1`. Low effort, high prevention value — these rules will be active on every subsequent release cycle.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260402-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-04-05T21:40:30+00:00
