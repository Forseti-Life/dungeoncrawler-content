Now I have enough context. Key facts:
- `drupal-qa-sessions.py` exists and does OTL-based cookie extraction already
- `jhtr:qa-users-ensure` creates `qa_tester_authenticated` (1 per role, no support for `_2`)
- `jobhunter-e2e` uses `ULI_URL` not `FORSETI_COOKIE_AUTHENTICATED` — those are for HTTP/curl-based audit suites
- `qa_tester_authenticated_2` needs either a drush command extension or separate creation logic

Committed as `c6c3389c4`. Here is the outbox:

---

- Status: done
- Summary: Groomed `forseti-qa-e2e-auth-pipeline` from stub to full AC. Key finding during codebase inspection: the `jobhunter-e2e` Playwright suite already self-provisions auth via inline `jhtr:qa-users-ensure` + `ULI_URL` — it does NOT use `FORSETI_COOKIE_AUTHENTICATED`. The auth pipeline script (`scripts/qa-playwright-auth.sh`) is needed for HTTP/curl-based audit suites that pass session cookies as headers. Wrote 6 ACs: script exists and is executable (OTL flow: drush ensure → drush user:login → curl follow → extract SESS* cookie → export `FORSETI_COOKIE_AUTHENTICATED`), idempotency, `qa_tester_authenticated_2` existence with jobseeker profile row, cookie validates on `/jobhunter/my-jobs` (HTTP 200), cross-user isolation test pattern documented, and schema validation. Flagged OQ-2: `jhtr:qa-users-ensure` only creates one user per Drupal role — `qa_tester_authenticated_2` requires either a `--count` extension to the drush command or standalone creation; recommended `--count` extension to keep user lifecycle in one place. Created `03-test-plan.md` with 9 TCs. Committed as `c6c3389c4`.

## Next actions
- pm-forseti: activate `forseti-qa-e2e-auth-pipeline` via `scripts/pm-scope-activate.sh forseti forseti-qa-e2e-auth-pipeline` (release-h, low priority)
- dev-infra: implement `scripts/qa-playwright-auth.sh` per AC-1; recommend extending `jhtr:qa-users-ensure` with `--count` or `--suffix` for AC-3
- qa-forseti: after script is implemented, execute TCs per `features/forseti-qa-e2e-auth-pipeline/03-test-plan.md`
- pm-forseti (OQ-2 decision): confirm whether `jhtr:qa-users-ensure` gets extended or `qa_tester_authenticated_2` is created via separate drush call

## Blockers
- None (feature is groomed; implementation is dev-infra's task)

## Needs from CEO
- N/A

## ROI estimate
- ROI: 35
- Rationale: Unblocks HTTP-based authenticated QA audit suites and enables cross-user isolation testing. The `jobhunter-e2e` Playwright suite is already unblocked (self-provisions auth), so the incremental value here is for URL-audit suites and future Playwright specs that need externally provisioned cookies.

---
- Agent: ba-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ba-forseti/inbox/20260409-proj002-groom-qa-e2e-auth-pipeline
- Generated: 2026-04-09T16:52:40+00:00
