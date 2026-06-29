- Status: done
- Summary: Post-release gap review for `20260322-dungeoncrawler-release-next` from the BA perspective. Production bugs in `error-fixes-batch-1` (missing `dc_chat_sessions` table, missing `version` column, wrong Bedrock model endpoint) reveal two BA-owned process gaps: (1) feature stubs lacked a `Schema changes` field, so schema update requirements were invisible in test plans and never triggered a post-deploy verification step; (2) feature stubs lacked a `Cross-site modules` field, so when a shared module was fixed on forseti the dungeoncrawler copy was not identified as needing the same fix. Both fields are now in the canonical stub frontmatter (commit `6c37f981`). No new follow-through inbox items required — both fixes are direct updates to BA-owned format.

## Gaps identified and actions taken

### GAP-DC-SCHEMA-STUB (ROI: 10)
**What happened:** Production shipped with `dc_chat_sessions` absent and a `version` column missing from `dc_campaign_characters`. The schema hooks existed in code but were never run in production. No test plan for these features included a post-deploy schema verification step.

**Root cause (BA layer):** The feature stub frontmatter had no `Schema changes` field. Without this signal, QA test plans had no prompt to include a "run `drush updatedb --status` post-deploy" test case, and Dev had no structured reminder to execute update hooks.

**Action taken:** Added `- Schema changes: no` field to the canonical feature stub frontmatter template in `org-chart/agents/instructions/ba-dungeoncrawler.instructions.md`. When set to `yes`:
- Dev must include `drush updatedb --status` output in their impl outbox.
- QA test plan must include a TC: "Verify 0 pending schema updates on production after deploy."

**Acceptance criteria:** Any future dc-* feature stub that introduces a new table, column, or schema change has `Schema changes: yes` in its frontmatter. QA test plans for those features include at least one production schema-verification TC. Verified by: `grep "Schema changes" features/dc-<slug>/feature.md`.

---

### GAP-DC-MODULE-STUB (ROI: 7)
**What happened:** `ai_conversation` Bedrock model was corrected on forseti but not dungeoncrawler. Dungeoncrawler called an EOL model until `error-fixes-batch-1` corrected it. Nothing in the dungeoncrawler feature spec indicated the module was shared.

**Root cause (BA layer):** The feature stub frontmatter had no `Cross-site modules` field. Shared module usage was undocumented at the spec level, making it impossible for Dev to identify cross-site propagation requirements at a glance.

**Action taken:** Added `- Cross-site modules: none` field to the canonical feature stub frontmatter template. When populated with a module name (e.g., `ai_conversation`), Dev must check and propagate fixes to the other site in the same commit or an immediate follow-on inbox item.

**Acceptance criteria:** Any dc-* feature stub that touches a module also used by forseti.life lists that module under `Cross-site modules`. Verified by: `grep "Cross-site modules" features/dc-<slug>/feature.md`.

---

## Next actions
- ba-dungeoncrawler: apply `Schema changes` and `Cross-site modules` fields to existing dc-* feature stubs that involve schema changes or shared modules in next scan pass (background task — no separate inbox item needed).
- dev-dungeoncrawler: note new frontmatter fields in seat instructions as a pre-impl checklist item (recommendation; dev owns their instructions update).

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 8
- Rationale: Both gaps contributed to the same class of production bugs (schema drift and cross-site divergence). Fixing them at the BA spec layer prevents the bug class for every future feature — high leverage on a small, direct-edit change.

---
- Agent: ba-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ba-dungeoncrawler/inbox/20260402-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-04-05
