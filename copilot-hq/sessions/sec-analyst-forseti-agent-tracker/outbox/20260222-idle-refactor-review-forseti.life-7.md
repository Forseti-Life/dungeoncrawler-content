# Outbox update: 20260222-idle-refactor-review-forseti.life-7

- Agent: sec-analyst-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-7
- Generated: 2026-02-22T14:44:46-05:00

---

`dev-forseti-agent-tracker` inbox is at cap (3/3). I'll produce the review with full findings and note that queuing is deferred.

Here is the outbox content for `sessions/sec-analyst-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-7.md`:

---

```markdown
- Status: done
- Summary: Suggested file `copilot_agent_tracker.routing.yml` has been reviewed three prior times (adversarial cycle 7, refactor cycle 2, refactor cycle 5 pivot). Per idle cycle policy I pivoted to `copilot_agent_tracker.install` (SHA `0b845e4a`) — the DB schema and install/update hooks, not yet reviewed in the refactor series. Review produced 2 MEDIUM and 3 LOW findings. The `dev-forseti-agent-tracker` inbox is at the 3-item cap; follow-up queuing is deferred until cap clears. Findings are documented here in full so they are actionable immediately once capacity opens.

## Findings — copilot_agent_tracker.install

### MEDIUM-1: Update hooks delegate to hook_schema() — fragile update pattern
- **Surface:** `update_8001` through `update_8004` all call `copilot_agent_tracker_schema()` to get table/field specs rather than inlining them.
- **Impact:** If `hook_schema()` is modified in a future commit before `drush updb` runs, update hooks apply the NEW schema definition instead of the original spec at the time the update was written. For table creation hooks (`8001`, `8002`), this could silently create tables with columns that do not yet exist in prior update history. For field additions (`8003`, `8004`), if the field spec changes shape, the added column diverges from what the code expects. This is a Drupal update anti-pattern that compounds in risk as the schema evolves.
- **Mitigation:** Inline the exact field/table spec in each `update_800x` function. For example, `update_8003` should contain a hardcoded `$spec = ['type' => 'varchar', 'length' => 255, ...]` rather than `$schema['copilot_agent_tracker_replies']['fields']['hq_item_id']`. This makes each update self-contained regardless of future schema changes.
- **Verification:** Run `drush updb --simulate` against a fresh install — no divergence between installed schema and expected spec.

### MEDIUM-2: `details` field is LONGTEXT (size: big) — drastically oversized, no PHP-layer cap
- **Surface:** `copilot_agent_tracker_events.details` field: `'type' => 'text', 'size' => 'big'` = MySQL LONGTEXT (up to 4 GB per row, off-page storage).
- **Impact:** For agent status event details, LONGTEXT is unnecessary. More critically, cycle 5 identified (HIGH-1) that `ApiController.php` has no PHP-layer size cap on this field. The combination means an uncapped POST payload writes uncapped data into an uncapped LONGTEXT column. Off-page LONGTEXT storage can degrade InnoDB performance as row count grows (row pointer overhead, read amplification on full-row scans). Without the PHP-layer cap from cycle 5 being implemented, this is the DB-layer expression of the same risk.
- **Mitigation:** Change `'size' => 'big'` to no `size` key (defaults to `text`, ~65KB) or `'size' => 'medium'` (MEDIUMTEXT, 16MB), aligned with whatever PHP-layer cap is implemented (recommended: 10,000 chars). Apply via a new `update_8005()`. Bundle with the PHP-layer cap (already in the ApiController hardening work item at ROI 7).
- **Verification:** After update, `SHOW CREATE TABLE copilot_agent_tracker_events\G` — confirm `details` column is `text` or `mediumtext`, not `longtext`.

### LOW-1: No hook_uninstall() — telemetry token persists in state indefinitely
- **Surface:** `hook_install()` generates token via `Crypt::randomBytesBase64(32)` and stores in state. No `hook_uninstall()` exists.
- **Impact:** On module uninstall, `copilot_agent_tracker.telemetry_token` remains in the Drupal `{state}` table. On reinstall, the `if (!$state->get(...))` guard silently reuses the old token. There is no token rotation mechanism — if the token is compromised (it is already printed cleartext in admin HTML per prior HIGH finding), the only remediation is a manual `drush state:del copilot_agent_tracker.telemetry_token` followed by reinstall. Operators unaware of this are likely to leave a compromised token in place.
- **Mitigation:** Add `hook_uninstall()` that calls `\Drupal::state()->delete('copilot_agent_tracker.telemetry_token')`. Add a note to README: "Token is not auto-rotated; run `drush state:del copilot_agent_tracker.telemetry_token && drush cr` to force a new token on next install." Also add a Drupal admin notice or drush command for token rotation (out of scope for this finding; flag for PM).
- **Verification:** Install module → note token → uninstall → verify state entry is deleted → reinstall → verify new token generated.

### LOW-2: Missing composite index for primary consume query pattern on replies table
- **Surface:** `copilot_agent_tracker_replies` has separate indexes on `consumed` and `to_agent_id` but no composite.
- **Impact:** HQ consume script queries roughly `WHERE to_agent_id = ? AND consumed = 0`. MySQL must pick one index and then filter the other; with separate indexes, the query planner typically uses `to_agent_id` and filters on `consumed`, or vice versa. A composite `(to_agent_id, consumed)` index handles this pattern with a single index range scan. Minor performance concern now, but relevant if the replies table grows large (e.g., high-volume agent operation).
- **Mitigation:** Add to `copilot_agent_tracker_replies` indexes block: `'to_agent_consumed' => ['to_agent_id', 'consumed']`. Apply via update hook.
- **Verification:** `EXPLAIN SELECT * FROM copilot_agent_tracker_replies WHERE to_agent_id = 'x' AND consumed = 0` — confirm composite index is used.

### LOW-3: `metadata` field description misleads future maintainers
- **Surface:** `copilot_agent_tracker_agents.metadata` field description: `'Arbitrary JSON metadata (sanitized, no secrets).'`
- **Impact:** "Sanitized" is aspirational — neither `ApiController.php` nor `AgentTrackerStorage.php` performs any secret scrubbing or content validation on metadata. A future maintainer reading the schema comment may assume sanitization is applied and skip adding it. This is also inconsistent with findings in cycle 5 (no metadata size/depth cap).
- **Mitigation:** Change description to: `'Arbitrary JSON metadata from agent payload. Caller is responsible for excluding secrets. No server-side content sanitization.'` This accurately describes the current state and creates no false confidence.
- **Verification:** Read field description in `drush php:eval "var_dump(\Drupal::database()->schema()->getFieldInformation(...))"` or simply grep schema after change.

## Already confirmed safe
- `hook_install()` uses `Crypt::randomBytesBase64(32)` — 256-bit token, cryptographically sound.
- All update hooks check `tableExists`/`fieldExists` before acting — idempotent and safe to re-run.
- `last_seen` stored as Unix int (not user-supplied string) — no injection surface.
- All timestamps are server-generated (`$this->time->getRequestTime()`) — not client-supplied.

## Follow-up queuing
- `dev-forseti-agent-tracker` inbox is at 3/3 cap — follow-up for MEDIUM-1 + MEDIUM-2 + LOW-1 deferred until cap clears. Findings are fully documented here and ready to queue.

## Next actions
- Process remaining inbox item: `20260222-idle-security-explore-forseti.life-9`.
- When `dev-forseti-agent-tracker` inbox drops below cap: queue install hardening item covering MEDIUM-1 (inline update hook specs), MEDIUM-2 (downsize details field), LOW-1 (hook_uninstall + token cleanup), LOW-2 (composite index), LOW-3 (metadata description fix). Bundle with ApiController hardening item already pending (ROI 7).

## Blockers
- `dev-forseti-agent-tracker` inbox at 3-item cap — install hardening follow-up item cannot be queued now.

## ROI estimate
- ROI: 5
- Rationale: MEDIUM-1 (fragile update hooks) is a correctness risk that compounds with each new schema migration; the longer it stays, the harder it gets to fix. MEDIUM-2 (LONGTEXT + no PHP cap) is the DB-layer component of the already-HIGH ApiController finding, making the combined fix higher leverage. LOW-1 (no uninstall/token rotation) directly reduces the impact window of the confirmed token-exposure finding.
```
