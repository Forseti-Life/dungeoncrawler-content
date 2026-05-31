# Shipping Gates (Checks & Balances)

Master process flow (authoritative): `runbooks/release-cycle-process-flow.md`

## Gate 0 — Intake (Any role)
Required artifacts:
- Problem Statement
- Acceptance Criteria
- Risk Assessment (initial)

Release-cycle rule:
- Intake is always allowed, but once a release cycle starts and scope is frozen, new intake is for the **next** release cycle (or deferred) unless PM explicitly re-baselines the cycle.

Exit criteria:
- Scope and non-goals are explicit.
- Permissions and failure modes are defined.

## Gate 1 — Implementation Ready (Dev)
Required artifacts:
- Implementation Notes (draft)

Exit criteria:
- Approach matches acceptance criteria.
- Identified tests to run.
- **Cross-site module sync check (required):** If this change touches a module present in both forseti and dungeoncrawler (`web/modules/custom/`), confirm the equivalent fix is applied to the other site in the same commit or an immediate follow-on inbox item. Implementation notes must state: "Cross-site sync: applied / not applicable (reason)." (Added 2026-04-05 — GAP-DC-MODULE-DIVERGENCE: Bedrock model fix applied to forseti was not propagated to dungeoncrawler until `error-fixes-batch-1`, causing a live EOL-model error.)

## Gate 1b — Code Review Finding Dispatch (PM, required before Gate 2)

After each `agent-code-review` run for a release cycle, PM must:
1. Read the code-review outbox for that release: `sessions/agent-code-review/outbox/<date>-code-review-<site>-<release-id>.md`
2. For every finding rated **MEDIUM or higher**, create a dev-seat inbox item **within the same release cycle**:
   - Folder: `sessions/<dev-seat>/inbox/<date>-cr-finding-<finding-id>/`
   - Required fields in `command.md`: finding ID, file path, severity, description, fix approach (if known), acceptance criteria
   - Required: `roi.txt` (use severity as proxy: CRITICAL→10, HIGH→8, MEDIUM→6)
3. If risk acceptance is chosen instead of a fix, record the decision explicitly in `sessions/pm-<site>/artifacts/risk-acceptances/` with rationale and sign-off owner.

**Exit criteria (Gate 1b):**
- All MEDIUM+ findings either have a dev-seat inbox item OR an explicit risk-acceptance record.
- No MEDIUM+ finding may be left unrouted (i.e., visible only in the code-review outbox).

**Gate sequencing:** Gate 1b must complete before PM may record a release signoff (`scripts/release-signoff.sh`).

**Lesson (2026-03-19):** In dungeoncrawler release-a, finding F-DC-A-1 (MEDIUM: CAST LIKE on LONGTEXT columns, `copilot_agent_tracker`) went untracked from Mar 9 to Mar 19 — triggering an unplanned extra QA cycle at Gate 2 (8 violations, commit `175b7c3b4`).

## Gate 1c — Hotfix Code Review (required for CEO/PM-applied changes)

When any CEO or PM seat applies code changes directly (bypassing a dev inbox item flow — e.g., during a production outage response), a code review inbox item MUST be created for `agent-code-review` within the same session:
- Folder: `sessions/agent-code-review/inbox/<date>-hotfix-cr-<site>-<description>/`
- Required fields in `command.md`: file paths changed, change summary, and reason for bypassing dev inbox flow
- Required: `roi.txt` (use severity: CRITICAL outage → 10, HIGH risk → 8)

**Exit criteria (Gate 1c):**
- `agent-code-review` outbox exists for the hotfix with explicit PASS/FAIL per file.
- Any MEDIUM+ finding triggers a dev inbox item for the owning PM seat.
- This gate does not block deployment in progress (hotfix may ship); it must complete within the same release cycle.

**Gate sequencing:** Gate 1c runs concurrently with or after hotfix deployment; it does not block Gate 1b for normal release scope. Gate 1c findings feed into Gate 1b dispatch for the current or next release cycle.

**KB reference:** `knowledgebase/lessons/20260405-hotfix-code-review-gate-gap.md`

## Gate 2 — Verification (Tester)
Required artifacts:
- Test Plan
- Verification Report
- Methodology reference (required): `runbooks/role-based-url-audit.md` (URL/access validation by role; localhost-first)

Gate 2 integration contract:
- **Inputs:** active release ID, scoped feature list, feature-level QA evidence, suite/audit outputs, and current acceptance criteria
- **Outputs:** one release-scoped QA decision artifact containing the exact release ID and explicit APPROVE/BLOCK, plus supporting test evidence
- **Consumers:** PM signoff flow, `scripts/release-signoff.sh`, `scripts/release-signoff-status.sh`, and `scripts/ceo-release-health.sh`

Test-case source of truth requirement:
- Test cases must reside in a central executable automation suite with PASS/FAIL outcomes.
- The release candidate must record which automated suites were run and the results (see `templates/release/02-test-evidence.md`).

Exit criteria:
- Evidence attached.
- Explicit APPROVE or BLOCK.

Clean-audit auto-approval rule:
- When the latest QA site audit is clean (`0` missing assets, `0` permission violations, `0` other failures, `0` config drift), Gate 2 APPROVE must be materialized automatically for the active release.
- Primary path: `scripts/site-audit-run.sh` calls `scripts/gate2-clean-audit-backstop.py` immediately after writing `findings-summary.json`.
- CEO backstop: the scheduled 2-hour CEO cycle (`scripts/ceo-ops-once.sh`, installed by `scripts/install-crons.sh`) re-runs the same remediation and queues a CEO root-cause review item if the backstop had to intervene.
- Purpose: a clean audit is sufficient Gate 2 evidence; duplicate or stale suite-activate churn must not keep PM signoff blocked.

### Release-critical QA testgen backlog intervention rule (PM-owned, added 2026-03-22)

**Trigger (hard threshold):** If a QA testgen backlog for a release-bound grooming pool reaches **2 consecutive groom/improvement cycles with 0 test plans delivered**, PM must intervene directly in the same cycle.

**Intervention decision owner:** PM.

**Default PM intervention (in priority order):**
1. **Resequence the executor**: set all release-bound testgen items to the highest ROI in the queue (`roi.txt` = 50) so they are processed before any other qa seat work.
2. **Cap testgen batch size**: if >8 testgen items are pending for a single release, split into sequential batches of 4 and ensure the first batch fully completes (outbox written, test plans committed) before the next batch starts.
3. **Block Stage-0 scope selection**: PM may NOT run `pm-scope-activate.sh` for any feature without `03-test-plan.md` present. Stage-0 activation is hard-blocked — no negotiation (already required by process flow, but must be explicitly enforced at escalation).
4. **Escalate to Board** only if intervention triggers 3+ consecutive times for the same site in a single release cycle (indicates a structural resourcing problem, not a sequencing problem).

**PM responsibility (required):**
- At every groom cycle where testgen items are pending: record the count of pending/completed in the outbox.
- If the threshold above is reached, PM acts immediately on steps 1–3 above and documents the intervention in the outbox.

**Evidence from dungeoncrawler release-b (2026-03-22):**
- 12 testgen items pending since 2026-03-20, 0 delivered, 3 consecutive groom cycles (pm-dungeoncrawler outboxes 20260322-groom-*).
- Stage-0 scope selection for release-b was blocked on missing test plans.
- Root cause: testgen items queued at ROI=43 were not processed before improvement-round items at higher ROI values from other cycles.
- Fix: ROI resequence + batch cap rule (see above).


Required artifacts:
- Release Notes

### Release auto-close policy (scope cap and age — added 2026-04-05)

**Scope cap is a maximum, not a target.** PM MUST NOT hold a release open waiting to fill remaining scope slots.

**Auto-close triggers (either condition closes the release immediately):**
- **Feature count:** ≥ 10 features in_progress for this site
- **Age:** ≥ 24 hours have elapsed since the release was started (i.e. since `release-cycle-start.sh` wrote `tmp/release-cycle-active/<team>.started_at`)

**When either trigger fires, the orchestrator dispatches a `release-close-now` item (ROI 999) to the PM.** PM must act on it in the same inbox cycle:
1. Confirm all in-scope features have Gate 2 APPROVE evidence
2. Defer (Status: ready) any feature that does NOT have Gate 2 APPROVE — it moves to the next release
3. Write Release Notes and record signoff: `./scripts/release-signoff.sh <team> <release-id>`
4. Notify the partner PM for coordinated releases

**PM must never wait for the scope cap to fill.** If QA has approved all in-scope features and either trigger has fired, ship immediately.

### Empty-release Gate 2 waiver procedure (added 2026-04-05 — GAP-IR-20260405-3)

An **empty release** is a release where the auto-close trigger fires but zero features have been activated (`Status: in_progress` with the current `release_id`). This can happen when the orchestrator fires FEATURE_CAP using a cross-release feature count immediately after a new release is created.

**PM responsibility:** if `release-close-now` arrives and 0 features have Gate 2 APPROVE evidence, PM must escalate to CEO immediately (same inbox cycle) with `Status: blocked`, stating "zero features shipped — Gate 2 waiver required."

**CEO waiver procedure:**
1. Create artifact: `sessions/qa-<team>/outbox/YYYYMMDD-gate2-waiver-<release-id>.md`
2. Required content format:
   ```
   # Gate 2 Waiver — <release-id>

   <release-id> — APPROVE — empty release, Gate 2 waived per CEO authority

   ## Waiver rationale
   - <reason release is empty>
   - All scoped features deferred to ready state; zero code changes shipped.
   - No QA evidence can exist for a release with no shipped work.
   - CEO authorizes this waiver to unblock the pipeline.
   - Issued by: ceo-copilot-2
   - Date: YYYY-MM-DD
   ```
3. PM may immediately run `./scripts/release-signoff.sh <team> <release-id>` after the waiver artifact is present.

**Authorization:** Only CEO may issue Gate 2 waivers. PMs may not self-authorize.

Exit criteria:
- Release coordinator confirms coordinated-window readiness (when applicable).
- Tester approves.
- Dev confirms deploy steps/rollback and that all changes are committed (commit hash(es) recorded).
- **Schema deploy gate (required when schema changes exist):** Dev must run `drush --uri=<site-uri> updatedb --status` on each production target and execute any pending updates. Output must appear in release notes or implementation notes. If no schema changes: explicitly state "no schema changes in this release." (Added 2026-04-05 — GAP-DC-SCHEMA-DEPLOY: two CRITICAL production bugs caused by missing `drush updatedb` post-deploy in dungeoncrawler release-next. Fix was applied in dev-dungeoncrawler seat instructions but was absent from the shared gate, leaving all other dev seats exposed to the same failure class.)

Coordinated release rule (Forseti + Dungeoncrawler):
- All required coordinated PM seats must sign off before the official push:
	- `./scripts/release-signoff.sh <site-or-team-alias> <release-id>`
- Required seats are resolved from `org-chart/products/product-teams.json` where `active=true` and `coordinated_release_default=true`.
- Release operator (`pm-forseti`) verifies:
	- `./scripts/release-signoff-status.sh <release-id>`
- Per-team release ID registration (required): each coordinated PM seat must also record a per-team signoff for their own release ID in addition to the shared coordinated ID:
	- `./scripts/release-signoff.sh dungeoncrawler <per-team-release-id>`
	- This ensures improvement-round.sh detects the release at the correct time and avoids retroactive signoff artifacts being created later by workspace merges.
- Cross-team PM signoff check (required): each coordinated PM seat must verify the OTHER team's release ID also has a signoff before the release operator pushes. Example: pm-forseti must confirm pm-dungeoncrawler signed `<dungeoncrawler-release-id>`, and vice versa. Verify with `./scripts/release-signoff-status.sh <partner-release-id>`. If missing, the push is blocked until the partner PM signs. (Added 2026-03-27 — GAP-FST-27-04: pm-forseti missed dungeoncrawler signoff in `20260326-dungeoncrawler-release-b` coordinated push.)

## Gate 4 — Post-release verification (Tester, production)
Required artifacts:
- Post-release verification note (may reuse Verification Report format)

Exit criteria:
- Tester runs the same audit protocol against production base URL(s).
- If clean: Tester explicitly reports “post-release QA clean” and “no new items identified for Dev”.
- If unclean: Tester records the unclean signal with evidence.

Policy:
- If post-release is unclean, the next release cycle is remediation-only (no new features).
- PM escalates to Board if there are 3 unclean releases in a row for a product/site.

## Coordinated Release
Additional rules for shipping:
- Forseti and Dungeoncrawler pushes happen in the same release window.
- Release operator (`pm-forseti`) owns the go/no-go decision for coordinated releases.

Runbook: `runbooks/coordinated-release.md`
