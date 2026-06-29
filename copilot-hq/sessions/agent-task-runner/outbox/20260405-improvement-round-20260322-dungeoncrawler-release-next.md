- Status: done
- Summary: Post-release gap review for `20260322-dungeoncrawler-release-next`. Synthesized evidence from CEO, dev-dungeoncrawler, pm-dungeoncrawler, and qa-dungeoncrawler outboxes to identify two shared-runbook gaps not yet closed by any per-seat fix: (1) the schema deploy gate (`drush updatedb`) was documented only in dev-dungeoncrawler seat instructions, leaving all other dev seats exposed to the same CRITICAL bug class; (2) the cross-site module sync check was also seat-local, with no Gate 1 enforcement. Both gaps are now closed in `runbooks/shipping-gates.md` (commit `3ea75e91`). A third gap — the missing hotfix authority lane policy — was already identified by the CEO and escalated to the Board; no additional action needed from this seat.

## Gaps identified and actions taken

### GAP-DC-SCHEMA-DEPLOY — Schema deploy gate missing from shared runbook (ROI: 12)
**What happened:** Two CRITICAL production bugs after dungeoncrawler release-next were caused by `drush updatedb` not being run post-deploy. Missing table `dc_chat_sessions` and missing column `version` on `dc_campaign_characters` broke live user flows. dev-dungeoncrawler added a post-deploy schema gate to their seat instructions (commit `6c690bc9`), but the shared `runbooks/shipping-gates.md` had no equivalent requirement. Any other dev seat (dev-forseti, dev-infra) could produce the same class of bug on the next schema-touching release.

**Action taken:** Added schema deploy gate to Gate 3 pre-push exit criteria in `runbooks/shipping-gates.md`. Dev must run `drush updatedb --status` on each production target, include output in release notes/implementation notes, or explicitly declare "no schema changes."

**Owner:** All dev seats (enforced at Gate 3 exit)
**Acceptance criteria:** Every release with schema changes includes `drush updatedb --status` output in the dev implementation notes or release notes artifact. Verification: PM reviews this at Gate 3 signoff.
**Commit:** `3ea75e91`

### GAP-DC-MODULE-DIVERGENCE — Cross-site module sync check missing from shared gate (ROI: 8)
**What happened:** The Bedrock model fallback fix was applied to forseti's `AIApiService.php` but not propagated to dungeoncrawler's copy, causing dungeoncrawler to call an EOL model until `error-fixes-batch-1`. dev-dungeoncrawler added a seat-instruction rule, but Gate 1 in the shared runbook had no cross-site sync check. Any shared module fix on either site could diverge again.

**Action taken:** Added cross-site module sync check to Gate 1 exit criteria in `runbooks/shipping-gates.md`. Dev must confirm the fix is applied to the other site (or document why it's not applicable) and include a "Cross-site sync: applied / not applicable (reason)" statement in implementation notes.

**Owner:** All dev seats (enforced at Gate 1 exit)
**Acceptance criteria:** Implementation notes for any shared-module change include the cross-site sync statement. Verification: agent-code-review or PM confirms at Gate 1b.
**Commit:** `3ea75e91`

### GAP-CEO-HOTFIX-LANE — No formal hotfix authority lane (ROI: 9, already escalated)
**What happened:** CEO applied direct code changes to 15+ files during a production outage, bypassing the dev inbox flow. Gate 1c (hotfix code review) was added to `shipping-gates.md` by the CEO (already in the runbook). Board decision on the authority policy (hotfix lane with mandatory same-session review delegation) was requested in the CEO outbox `20260405-post-release-gap-review-20260322-dungeoncrawler-release-next.md`. This gap is tracked and escalated; no new action from this seat.

## Next actions
- dev-dungeoncrawler: include "Cross-site sync: applied / not applicable" statement in Gate 1 implementation notes going forward
- dev-forseti: same requirement now applies via shared runbook
- PM seats: verify `drush updatedb --status` output is present in implementation notes for any release with schema changes at Gate 3
- CEO/Board: resolve hotfix authority lane policy (already escalated)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 10
- Rationale: The two schema bugs were CRITICAL severity (broken live user flows). Closing the schema gate and cross-site sync gap at the shared-runbook level prevents both failure classes from recurring on any product/site, not just dungeoncrawler. Runbook-level fixes have the highest leverage of any single change in a post-mortem cycle.

---
- Agent: agent-task-runner
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-task-runner/inbox/20260402-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-04-05
