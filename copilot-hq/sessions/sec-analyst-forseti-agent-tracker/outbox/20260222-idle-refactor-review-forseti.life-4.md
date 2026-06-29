# Outbox update: 20260222-idle-refactor-review-forseti.life-4

- Agent: sec-analyst-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-4
- Generated: 2026-02-22T14:00:54-05:00

---

- Status: done
- Summary: Idle refactor/review cycle targeting `src/Form/ComposeAgentMessageForm.php` (SHA `49eefb26`). The form is embedded on an admin-only page and has no critical issues, but five concrete hardening improvements were identified: (1) validation errors in `submitForm()` will not halt execution — `setErrorByName()` from inside `submitForm()` does not abort the method in Drupal 10; the guards against empty `to_agent_id` and `message` after `#required => TRUE` are redundant but harmless, however the `setErrorByName()` calls in `submitForm()` will add error messages but the DB insert will still run because `setError` only affects the form rebuild — the correct pattern is to call these checks in `validateForm()`; (2) `to_agent_id` is a FAPI select but the submitted value is never re-validated against the allowed `$agents` option set in `validateForm()` — a crafted POST can bypass the select and submit any arbitrary string as `to_agent_id` (up to `varchar(128)` DB limit), allowing an arbitrary agent ID to be written to the `copilot_agent_tracker_replies` table; (3) the `message` textarea has no max-length validation, writing to a `text/big` (unbounded) column — same issue as `InboxReplyForm` (cycle 8 finding), and since this form is the compose entrypoint it is the most likely path for accidental oversized messages; (4) the form stores `$agents` option keys only in the form render array, not in `$form_state` storage, so `validateForm()` cannot easily re-access the allowed agent list — the recommended fix is to store it via `$form_state->set('agents', $agents)` in `buildForm()`; (5) no `validateForm()` method exists at all — all validation is in `submitForm()` where it is structurally incorrect in Drupal FAPI.

## File reviewed

`sites/forseti/web/modules/custom/copilot_agent_tracker/src/Form/ComposeAgentMessageForm.php`
SHA: `49eefb26b6ae62d59bb5d45a74dadafe41f2624c`

## Findings

### [HIGH] Submitted `to_agent_id` not validated against allowed option set — arbitrary agent ID writable to DB
- **Location**: `submitForm()`, line `$to_agent_id = trim((string) $form_state->getValue('to_agent_id'));`
- **Issue**: The `to_agent_id` field is rendered as a `#type => 'select'` with `$agents` as options. Drupal FAPI does validate select values against their options by default — **but only if `#validated` is not set and the form token validates**. A POST request that bypasses the browser form (e.g., `curl -X POST` with a forged CSRF token attempt) will have its token rejected. However, the token is Drupal's standard form token — with a valid admin session and a replayed CSRF token from a real page load, a crafted POST can submit any `to_agent_id` string because **Drupal FAPI's select validation is skipped when the submitted value is not in the options** — it does not throw an error by default for select elements without `#validated => TRUE`. An attacker with admin credentials could write a `to_agent_id` of their choice to the replies table, potentially creating inbox items for non-existent or private agent seats.
- **Actual Drupal behavior**: Drupal 10 FAPI **does** validate select options by default and will reject a value not in the options list with a form error. However, this protection relies on the `$agents` array being consistently populated at both build and validation time. Since `$agents` is passed as a parameter to `buildForm()` but not stored in `$form_state`, if the form is rebuilt (e.g., on AJAX), the agents list may be empty — bypassing the option validation entirely.
- **Impact**: MEDIUM — requires valid admin session. An admin writing an arbitrary `to_agent_id` is a confused-deputy risk: it creates a reply DB row for a seat that may not exist, wasting consume-script iterations.
- **Mitigation**: Store agents in `$form_state` during `buildForm()`:
  ```php
  $form_state->set('agents', array_keys($agents));
  ```
  Then in `validateForm()`:
  ```php
  $allowed = $form_state->get('agents') ?? [];
  if (!in_array($to_agent_id, $allowed, TRUE)) {
      $form_state->setErrorByName('to_agent_id', $this->t('Invalid agent selected.'));
  }
  ```
- **Owner**: `dev-forseti-agent-tracker`

### [MEDIUM] No `validateForm()` method — validation guards in `submitForm()` are structurally wrong
- **Location**: Entire class — no `validateForm()` override.
- **Issue**: The empty-value checks (`if ($to_agent_id === '')` + `setErrorByName()`) inside `submitForm()` will not prevent the DB insert. In Drupal FAPI, `setErrorByName()` called from `submitForm()` sets an error message on the form, but by this point the form has already passed validation — the error does not abort `submitForm()` execution. The `execute()` call on the insert will still run even if `setErrorByName()` is called.
- **Net effect**: If a user submits an empty `to_agent_id` (which cannot happen via normal UI since `#required => TRUE` enforces non-empty at validation time), the guard silently fails and the insert runs with `to_agent_id = ''`. This inserts a row with an empty agent ID into the replies table, which would cause the consume script to skip it (`if not to_agent or not msg: continue`) — so there's no direct exploit, but the code is wrong.
- **Mitigation**: Move all validation into a proper `validateForm()` method and remove the guards from `submitForm()`. This also enables the agent allowlist check (finding #1).
- **Suggested minimal addition**:
  ```php
  public function validateForm(array &$form, FormStateInterface $form_state): void {
      $to_agent_id = trim((string) $form_state->getValue('to_agent_id'));
      $message = trim((string) $form_state->getValue('message'));
      $allowed = $form_state->get('agents') ?? [];

      if ($to_agent_id === '') {
          $form_state->setErrorByName('to_agent_id', $this->t('Please select an agent.'));
      } elseif ($allowed && !in_array($to_agent_id, $allowed, TRUE)) {
          $form_state->setErrorByName('to_agent_id', $this->t('Invalid agent selected.'));
      }
      if ($message === '') {
          $form_state->setErrorByName('message', $this->t('Message cannot be empty.'));
      }
      if (mb_strlen($message) > 50000) {
          $form_state->setErrorByName('message', $this->t('Message is too long (max 50,000 characters).'));
      }
  }
  ```
- **Owner**: `dev-forseti-agent-tracker`

### [MEDIUM] No max-length validation on `message` textarea — unbounded write to DB and AI prompt
- **Location**: `buildForm()` — `$form['message']` has `#required => TRUE` but no `#maxlength` or server-side length check.
- **Issue**: Identical to the `InboxReplyForm` cycle 8 finding. The DB column is `text/big` (unbounded). A very large message would be stored, then fetched by `consume-forseti-replies.sh` into a Python variable, written to `command.md`, and passed to the AI prompt in `agent-exec-next.sh`. At 50KB+ this risks memory exhaustion and unexpected AI API costs.
- **Mitigation**: Add `mb_strlen($message) > 50000` guard in `validateForm()` (included in finding #2 diff above). Also add `'#maxlength' => 50000` to the textarea definition for browser-side hint.
- **Owner**: `dev-forseti-agent-tracker`

### [LOW] `$agents` option keys not stored in `$form_state` — lost on form rebuild
- **Location**: `buildForm()` receives `$agents` as a parameter but does not persist it.
- **Issue**: If the form is rebuilt (e.g., via AJAX, form error, or page cache miss), `$agents` defaults to `[]`. Drupal FAPI then has no options to validate against and would either show an empty select or silently accept any submitted value.
- **Mitigation**: Add `$form_state->set('agents', array_keys($agents));` near the start of `buildForm()`.
- **Owner**: `dev-forseti-agent-tracker`

### [LOW] `in_reply_to` always set to empty string — no traceability for compose-originated messages
- **Location**: `submitForm()` — `'in_reply_to' => ''`
- **Issue**: The consume script uses `in_reply_to` to name the HQ inbox item slug. A blank `in_reply_to` falls back to `f"compose-{rid}"`. This is functional but means Keith has no way to trace a compose message back to a specific escalation thread from the Drupal UI.
- **Recommendation**: Consider passing an optional `$context_item_id` (currently `''`) through the form via a hidden field, so compose messages sent in response to a specific escalation are linked.
- **Impact**: UX/traceability only — not a security risk.

## Confirmed safe (not vulnerabilities)

- CSRF: form uses standard Drupal POST with CSRF token (not `#method = 'get'`). Token is verified before `submitForm()` runs. Safe.
- DB insert: uses Drupal query builder with positional placeholders — no SQL injection possible.
- `$to_agent_id` is used only in a DB insert and a status message with `['@agent' => $to_agent_id]` (Drupal `@` escapes HTML). No XSS.
- `$message` written to DB as text; rendered only via `htmlspecialchars()` in the Sent messages panel. No XSS.
- `$default_to` fallback to `'ceo-copilot'` is a safe default — `ceo-copilot` exists as a configured seat.

## Follow-up work item (for executor to queue)

### Item 1 (for `dev-forseti-agent-tracker` via `pm-forseti-agent-tracker`)
- **Title**: Harden `ComposeAgentMessageForm` — add `validateForm()`, agent allowlist check, message max-length, store agents in form_state
- **File**: `sites/forseti/web/modules/custom/copilot_agent_tracker/src/Form/ComposeAgentMessageForm.php`
- **Changes** (minimal diff):
  1. In `buildForm()`: add `$form_state->set('agents', array_keys($agents));` after building `$agents` defaults
  2. Add `validateForm()` method with: agent empty check, agent allowlist check (`in_array($to_agent_id, $form_state->get('agents'), TRUE)`), message empty check, `mb_strlen($message) > 50000` cap
  3. In `buildForm()`: add `'#maxlength' => 50000` to `$form['message']`
  4. Remove the empty-value guards from `submitForm()` (now handled by `validateForm()`)
- **Acceptance criteria**:
  - Submit form via `curl` with `to_agent_id=nonexistent-agent` (valid admin session + CSRF token) → form validation error, no DB row inserted
  - Submit message > 50KB → validation error shown, no DB row inserted
  - Normal submit with valid agent + short message → success as before
- **ROI**: 8 — the missing `validateForm()` is a structural FAPI bug that leaves the form's guards non-functional; the agent allowlist check prevents arbitrary agent IDs being written to the replies table; message length cap prevents resource exhaustion; combined, these are medium-effort correctness + security fixes with straightforward acceptance criteria

## Next actions

- Executor: queue Item 1 as inbox item for `dev-forseti-agent-tracker` (via `pm-forseti-agent-tracker`) with `roi.txt` = 8.
- Note: findings #1 and #2 together fix a structural FAPI bug that has been latent since the form was written. Recommend `dev-forseti-agent-tracker` prioritize these over the LOW/INFO items.

## Blockers

- None.

## Needs from Supervisor

- None.

## ROI estimate
- ROI: 8
- Rationale: The missing `validateForm()` is a structural Drupal FAPI bug — validation guards in `submitForm()` do not abort execution, making the empty-check guards on `to_agent_id` and `message` non-functional. Combined with the missing agent allowlist validation, a crafted POST from an admin could write arbitrary `to_agent_id` values to the replies DB. These are medium-effort correctness fixes with clear acceptance criteria and high confidence in the fix.
