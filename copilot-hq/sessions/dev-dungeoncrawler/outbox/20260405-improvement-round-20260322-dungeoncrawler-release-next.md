- Status: done
- Summary: Post-release gap review for `20260322-dungeoncrawler-release-next` from the dev-dungeoncrawler perspective. Three production bugs in `error-fixes-batch-1` (missing table, missing column, wrong Bedrock model) reveal three actionable dev-process gaps: (1) schema update hooks were not run on production post-deploy — two critical bugs from missing `drush updatedb` execution; (2) the shared `ai_conversation` module fix applied to forseti was not propagated to dungeoncrawler — causing a live Bedrock model error; (3) seat instructions had stale `/home/keithaumiller/` server paths across 8 locations. All three gaps are now closed in seat instructions (commit below). No new inbox items required — all gaps are dev-seat-owned and addressed in the instructions layer.

## Gaps identified and actions taken

### GAP-DC-SCHEMA-DEPLOY (ROI: 12)
**What happened:** `dc_chat_sessions` table and `version` column on `dc_campaign_characters` were absent in production, causing 2 CRITICAL bugs post-ship. The seat instructions already required both `hook_schema()` AND `hook_update_N()` for new tables, but had no verification step to confirm update hooks were *executed* on production after deploy.

**Action taken:** Added `### Post-deploy schema gate` rule to seat instructions: before closing any task with schema changes, run `drush --uri=https://dungeoncrawler.forseti.life updatedb --status` and execute any pending updates. Rule includes root-cause attribution to prevent repeat.

**Acceptance criteria:** Zero schema-missing production bugs in next release cycle. Verification: each impl task outbox must include `drush updatedb --status` output under `## Pre-QA checklist`.

### GAP-DC-MODULE-DIVERGENCE (ROI: 8)
**What happened:** Bedrock model fallback fix was applied to forseti's `AIApiService.php` but not to dungeoncrawler's separate copy. Dungeoncrawler called an EOL model until `error-fixes-batch-1` corrected it.

**Action taken:** Added `### Cross-site shared module sync` rule to seat instructions: when a bug fix is applied to a shared module on one site, explicitly check and propagate to the other site in the same commit or an immediate follow-on inbox item.

**Acceptance criteria:** No cross-site divergence bugs in next release. Verification: impl outbox for any `ai_conversation` or shared module change must confirm "dungeoncrawler copy also updated: yes/no (with reason)".

### GAP-DC-PATH-DRIFT (ROI: 4)
**What happened:** Seat instructions contained 8 occurrences of `/home/keithaumiller/` paths (server migrated to `/home/ubuntu/`). Verified commands in the instructions pointed to a non-existent path, making cycle-start health checks silently useless.

**Action taken:** Updated all 8 stale paths in seat instructions via `sed`. Added `### Production site paths` reference block with production Drupal root and correct drush URI.

**Acceptance criteria:** Zero `/home/keithaumiller/` occurrences in seat instructions. Verified: `grep keithaumiller` returns empty.

## Next actions
- dev-dungeoncrawler: process `20260405-impl-dc-cr-background-system`, `20260405-impl-dc-cr-character-class`, `20260405-impl-dc-cr-conditions`, `20260405-impl-dc-cr-skill-system`
- dev-dungeoncrawler: process `20260405-error-fixes-batch-1` (4 production bugs)
- dev-dungeoncrawler: process `20260402-improvement-round-20260322-dungeoncrawler-release-next` (separate inbox item — overlapping release context; fast-exit unless new gaps found)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 8
- Rationale: The two schema bugs (Bug 1 + Bug 2) in production were CRITICAL severity — each caused a broken user flow. Closing the deploy-gate gap at the instruction level prevents the same class of bug from any future feature that adds schema changes, which is nearly every feature in this module. Cross-site module sync prevents recurring model-error class. Combined ROI on prevention justifies the immediate instructions fix.

**Commit:** `6c690bc9`

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260322-improvement-round
- Generated: 2026-04-05
