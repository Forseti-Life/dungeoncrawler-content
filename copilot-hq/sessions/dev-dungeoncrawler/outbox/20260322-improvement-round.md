Now I have full context. The most recent completed release is `20260322-dungeoncrawler-release-next`. Production bugs found post-ship reveal 3 clear dev-level process gaps. Let me fix the stale paths in my seat instructions and write the outbox.

Now also update the production paths in the instructions — the verified commands reference the dev site, but production drush uses `/var/www/html/dungeoncrawler`:

Now let me write the outbox file and commit everything together:

Now update the outbox file to include the actual commit hash:

---

- Status: done
- Summary: Post-release gap review for `20260322-dungeoncrawler-release-next` from the dev seat. Three production bugs (error-fixes-batch-1) revealed three dev-process gaps: (1) schema update hooks not run on production post-deploy — caused 2 CRITICAL bugs; (2) shared `ai_conversation` Bedrock fix not propagated to dungeoncrawler — caused EOL model error; (3) seat instructions had 8 stale `/home/keithaumiller/` paths. All three gaps closed directly in seat instructions (`6c690bc9`). No new inbox items needed — all gaps are dev-seat-owned.

## Next actions
- Process `20260405-error-fixes-batch-1` (4 production bugs, CRITICAL priority)
- Process 4 impl inbox items (`20260405-impl-dc-cr-*`)
- Fast-exit `20260402-improvement-round-20260322-dungeoncrawler-release-next` (same release, gaps now covered)

## Blockers
- None

## ROI estimate
- ROI: 8
- Rationale: Schema deploy gate prevents CRITICAL-class production bugs from any future feature with schema changes. Cross-site module sync prevents recurring model-error class. Both are high-frequency patterns in this module.

**Commits:** `6c690bc9`, `2a74d502`

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260322-improvement-round
- Generated: 2026-04-05T16:30:42+00:00
