I have a clear picture. New in this release: `credentials_delete` and `credentials_test` are POST routes with **no `_csrf_token`** — and they handle ATS credential data. The Playwright bridge reads credentials from a temp file (good: immediate delete pattern). Let me produce the forseti pre-flight and outbox.

Both credential mutation routes (`credentials_delete`, `credentials_test`) are missing `_csrf_token` and are accessible to **any authenticated user with `access job hunter`** (not admin-only) — higher risk than admin-only CSRF gaps. Now write the artifact and outbox.

Now write the patch artifact for dev-infra:

---

- Status: done
- Summary: Completed forseti release-d security pre-flight (diff-scoped to 2 unmerged commits). Found 2 HIGH severity CSRF findings in the new ATS Credentials surface: `credentials_delete` and `credentials_test` both accept POST with no `_csrf_token` under the `access job hunter` permission (all authenticated users, not admin-only — wider attack surface than prior admin-only gaps). `credentials_test` also carries a potential SSRF vector. Playwright bridge passes (immediate temp file delete pattern). DCC-0331 status unverified. Ready-to-apply patches produced for dev-infra. Gate 2 HOLD recommended.

## Next actions
- dev-infra: apply 2 one-liner YAML diffs from `sessions/sec-analyst-infra/artifacts/20260227-forseti-release-preflight/patches.md`, run `drush cr`, verify 403 on POST without CSRF token
- dev-infra: review `CredentialController::testCredential` for SSRF — confirm ATS test URL is not user-supplied or is allowlisted
- pm-infra: confirm DCC-0331 remediation status before Gate 2
- Playwright platform handlers (greenhouse.js, lever.js) not reviewed — recommend follow-on review before releases touching those files

## Blockers
- None

## Artifacts
- `sessions/sec-analyst-infra/artifacts/20260227-forseti-release-preflight/preflight.md` — commit `49eeb0b`
- `sessions/sec-analyst-infra/artifacts/20260227-forseti-release-preflight/patches.md` — commit `49eeb0b`
- Outbox — commit `ffa26c6`

## ROI estimate
- ROI: 18
- Rationale: Two HIGH CSRF findings on a wider-than-admin credentials surface; patches are one-liners. Blocking release with unpatched credential mutation routes carries disproportionate data-loss and reputational risk relative to the fix cost.

---
- Agent: sec-analyst-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-infra/inbox/20260227-improvement-round-20260226-forseti-release-d
- Generated: 2026-02-27T12:31:14-05:00
