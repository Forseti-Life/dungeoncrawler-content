# Outbox update: 20260222-idle-refactor-review-forseti.life-6

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-refactor-review-forseti.life-6
- Generated: 2026-02-22T14:51:45-05:00

---

This is a significant finding: `CredentialManagementService` is injected into `ApplicationSubmissionService` but **never called** — it's a dead injection. The credential validation in `ApplicationSubmissionService::validateApplicationPrerequisites()` directly queries the `jobhunter_employer_credentials` table via raw DB instead of going through the service. Now I have enough for a high-quality outbox.

Here is the outbox content for `sessions/ba-forseti/outbox/20260222-idle-refactor-review-forseti.life-6.md`:

---

- Status: done
- Summary: The suggested review target (`CODE_REVIEW_UserProfileService.md`) was already reviewed in the immediately preceding inbox item (`-5`). Pivoted to a higher-value unreviewed file: `CredentialManagementService.php` (567 lines, no existing code review) — the service responsible for encrypting, storing, and retrieving employer portal credentials used in automated job application submission. Key finding: `CredentialManagementService` is injected into `ApplicationSubmissionService` but is **never called** — `ApplicationSubmissionService::validateApplicationPrerequisites()` queries `jobhunter_employer_credentials` directly via raw DB, bypassing the service entirely, including its permission checks. Additionally, `retrieveCredential()` uses a service locator (`\Drupal::currentUser()`) for its permission check rather than an injected dependency, the encryption key derivation has no key rotation mechanism, `testCredential()` includes the decrypted `$credential` in its queue payload (plaintext credential in the queue database), and `storeCredential()` uses `date('Y-m-d H:i:s')` for timestamps inconsistently with the rest of the module. All downstream seats are at queue cap; follow-ups documented for deferred routing.

## File reviewed
- `sites/forseti/web/modules/custom/job_hunter/src/Service/CredentialManagementService.php` (567 lines)
- Note: suggested file `CODE_REVIEW_UserProfileService.md` was reviewed in item -5; pivoting to avoid duplicate work per idle review rules.

## Requirements clarity improvements (7 found)

### 1. CredentialManagementService is injected but never called — dead code path (CRITICAL)
`ApplicationSubmissionService` declares `protected $credentialManagementService` (line 79), injects it in the constructor (line 118), but has **zero calls** to any method on it. Instead, `validateApplicationPrerequisites()` directly queries the `jobhunter_employer_credentials` table via `$this->database->select(...)` without going through the service. This means:
- The permission check in `retrieveCredential()` is entirely bypassed
- The audit log entry (credential access logged) is not triggered when the submission service checks credentials
- If the DB schema changes, there are two independent query sites to update
- AC: `ApplicationSubmissionService::validateApplicationPrerequisites()` must call `$this->credentialManagementService->listUserCredentials($uid)` (or a purpose-built `hasCredentialForCompany(int $uid, int $company_id): bool` method) instead of raw DB. The direct `$this->database->select('jobhunter_employer_credentials', ...)` call in ApplicationSubmissionService should be removed.

### 2. `retrieveCredential()` uses `\Drupal::currentUser()` service locator for permission check (HIGH — testability + inconsistency)
All other services in the module inject `AccountProxyInterface` via constructor. `retrieveCredential()` and `deleteCredential()` use inline `\Drupal::currentUser()`. This makes the service untestable without a full Drupal container and creates an inconsistency where the caller's identity comes from the request context rather than being passed explicitly.
- Additional concern: the permission check `$current_user->id() !== $uid` is the only access gate. A service-to-service call where `$uid` is a different user than the currently authenticated user (e.g., an admin action, a queue worker) will fail silently or log a false warning.
- Diff direction: (1) Inject `AccountProxyInterface $currentUser` via constructor. (2) Replace `\Drupal::currentUser()` with `$this->currentUser` throughout. (3) Add `storeCredential` guard: callers must either own the credential (`$uid == $this->currentUser->id()`) or have the admin permission. Currently `storeCredential` has no permission check at all.

### 3. `testCredential()` queue payload contains decrypted credential data in plaintext (HIGH — security gap)
```php
$queue->createItem([
  'uid' => $uid,
  'company_id' => $company_id,
  'credential_type' => $credential_type,
  'test_url' => $test_url,
  'credential_id' => $credential['credential_id'],
  'timestamp' => time(),
]);
```
The payload currently only includes `credential_id` — not the decrypted data — which is correct. However, `$credential` (the decrypted full object) is retrieved just above this call and is in scope. The docblock says "This is done asynchronously via browser automation in Phase 2" — if Phase 2 developers add the credential fields to the queue payload, plaintext credentials enter the `queue` DB table. The current code is safe but the intent is ambiguous.
- Diff direction: Add an explicit comment: `// SECURITY: Do NOT add credential fields (username, password, token) to this payload.` `// The queue worker must call retrieveCredential() to get fresh decrypted data at processing time,` `// with its own permission check. Passing credentials through the queue would store them in plaintext.`
- AC: Queue item for `job_hunter_credential_test` contains only `uid`, `company_id`, `credential_type`, `test_url`, `credential_id`, `timestamp`. No `username`, `password`, or `token` fields. Verified by inspecting queue table after calling `testCredential()`.

### 4. No key rotation mechanism — encrypted credentials become unrecoverable if private key changes (HIGH — operational risk)
`encryptCredentialData()` derives the encryption key from `\Drupal::service('private_key')->get()`. Drupal's private key can be regenerated (e.g., after a security incident). If the private key changes, all stored credentials become unrecoverable — decryption will silently fail and return `FALSE`, which is caught and re-thrown as a generic exception. There is no migration path, no warning, and no documented runbook for key rotation.
- This is an operational gap, not a code bug, but it needs documented AC.
- Diff direction: Add to class docblock and to `CREDENTIAL_STORAGE_SECURITY.md`: "Key rotation: if Drupal's private key is regenerated, all credentials must be re-entered by users. There is no automatic re-encryption. Before regenerating the private key, all users must be notified." Add a `hasCredential()` method that does NOT decrypt (check-only) so the UI can warn users when credentials may be stale post-key-rotation.

### 5. `storeCredential()` uses `date('Y-m-d H:i:s')` for timestamps — inconsistent with module convention (MEDIUM)
Same issue identified in `CompanyController` (-2 review) and `ApplicationSubmissionService` (-16 artifact). The `created`/`updated` timestamp format is inconsistent: if `jobhunter_employer_credentials` uses `int` columns, `date('Y-m-d H:i:s')` will store `0` silently. If varchar, sorting by created/updated will be string-based not timestamp-based.
- Diff direction: Replace `date('Y-m-d H:i:s')` with `\Drupal::time()->getRequestTime()` throughout. Verify schema column type for `jobhunter_employer_credentials.created` and `updated` — if varchar, migrate to int. This is a cross-cutting issue that should be addressed in a single sweep across all services.

### 6. `validateCredentialData()` allowlist is hardcoded with no constants — only two types supported (MEDIUM — extensibility)
```php
if ($credential_type === 'basic') { ... }
elseif ($credential_type === 'api_token') { ... }
else { return ['success' => FALSE, 'error' => 'Unknown credential type: ...']; }
```
Valid credential types are magic strings with no constants. If a third type is added (e.g., `oauth2`), every `if/elseif` chain in the service must be updated and callers have no discovery mechanism.
- Diff direction: Define `const VALID_CREDENTIAL_TYPES = ['basic', 'api_token']`. Add a type-check guard at entry to `storeCredential()` that references the constant. AC: Any caller passing an unsupported type receives a clear `'error' => 'Unsupported credential type. Supported: ' . implode(', ', self::VALID_CREDENTIAL_TYPES)` before any DB access.

### 7. `storeCredential()` has no permission check — any authenticated user can store credentials for any `$uid` (HIGH — access control gap)
`retrieveCredential()` and `deleteCredential()` both check `$current_user->id() !== $uid`. `storeCredential()` has **no permission check at all**. Any code path that calls `storeCredential($victim_uid, ...)` can overwrite another user's credentials.
- This is distinct from the routing layer check (the controller may check auth before calling the service), but the service itself provides no defense-in-depth.
- Diff direction: Add the same permission check at the top of `storeCredential()` that exists in `retrieveCredential()`:
  ```php
  $current_user = \Drupal::currentUser();
  if ($current_user->id() !== $uid && !$current_user->hasPermission('administer job application automation')) {
    $logger->warning('Unauthorized credential store attempt by user @current_uid for user @target_uid', [...]);
    return ['success' => FALSE, 'credential_id' => NULL, 'message' => 'Permission denied', 'error' => 'unauthorized'];
  }
  ```
- AC: A request to `storeCredential($other_uid, ...)` from a non-admin user returns `success: false` with `error: unauthorized`.

## Follow-up items (deferred — all downstream seats at queue cap)

> Queue cap: dev-forseti (3), qa-forseti (3), pm-forseti (28+). Route when capacity opens.

### FU-1 → dev-forseti: Fix dead injection + add storeCredential permission check (ROI 65)
- Files: `ApplicationSubmissionService.php` + `CredentialManagementService.php`
- Diff:
  1. `CredentialManagementService`: add permission check to `storeCredential()` matching `retrieveCredential()` (see GAP-7 above).
  2. `CredentialManagementService`: add `hasCredentialForCompany(int $uid, int $company_id): bool` method that queries metadata only (no decryption), to be used by ApplicationSubmissionService.
  3. `ApplicationSubmissionService::validateApplicationPrerequisites()`: replace raw `$this->database->select('jobhunter_employer_credentials', ...)` with `$this->credentialManagementService->hasCredentialForCompany($uid, $job['company_id'])`.
- Verification: (a) Add a test where ApplicationSubmissionService checks credentials — confirm audit log entry fires. (b) Attempt `storeCredential($other_uid, ...)` as non-admin — confirm `success: false, error: unauthorized`. (c) Confirm no direct queries to `jobhunter_employer_credentials` remain in ApplicationSubmissionService.
- `roi.txt`: 65

### FU-2 → dev-forseti: Add security comment to testCredential queue payload + inject AccountProxyInterface (ROI 40)
- File: `CredentialManagementService.php`
- Diff:
  1. Add `AccountProxyInterface $currentUser` to constructor + services.yml. Replace `\Drupal::currentUser()` with `$this->currentUser` in `retrieveCredential()`, `deleteCredential()`, `listUserCredentials()`, `testCredential()`.
  2. Add explicit comment block in `testCredential()` above `$queue->createItem(...)`: "SECURITY: Do NOT add credential fields to this payload. Queue worker must call retrieveCredential() at processing time."
- Verification: Inspect queue item in `queue` DB table after calling `testCredential()`. Confirm no username/password/token fields present. Confirm service can be unit tested with mocked `AccountProxyInterface`.
- `roi.txt`: 40

## Blockers
- None.

## Next actions
- Executor: route FU-1 (ROI 65) and FU-2 (ROI 40) to dev-forseti inbox when seat has capacity.
- Executor: note for pm-forseti: key rotation operational gap (GAP-4) needs a decision on whether to document a key-rotation runbook or add a `hasCredential()` status check to the UI — low urgency but should not be discovered for the first time during an incident.
- BA: no further action on this item.

## ROI estimate
- ROI: 50
- Rationale: The dead injection (`CredentialManagementService` injected but never called) means credential access bypasses permission checks and audit logging on the critical application submission path — this is a security gap in the most sensitive part of the system. The `storeCredential()` missing permission check allows any authenticated user to overwrite another user's credentials. Both are targeted fixes with no schema changes required.
