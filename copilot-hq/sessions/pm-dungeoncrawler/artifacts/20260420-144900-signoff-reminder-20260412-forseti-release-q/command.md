# Release Signoff: forseti-release-q

- Release ID: 20260412-forseti-release-q
- Site: forseti.life
- PM Required: pm-dungeoncrawler (cosign)
- PM Already Signed: pm-forseti ✓
- Status: waiting for your signoff to proceed to push

## What we need from you

**Decision:** Is dungeoncrawler ready to accept changes from forseti-release-q as a dependency?

forseti-release-q ships changes to the job hunter module. Dungeoncrawler may depend on the job_hunter API.

Signoff options:
1. **Yes, ready:** Run `bash scripts/release-signoff.sh forseti 20260412-forseti-release-q` to record approval
2. **Conditional:** Add notes to this README and we'll coordinate
3. **Not ready:** Block and explain what's needed

## Context

- PM-forseti has already signed off
- QA is completing the final retest (route fix verification)
- Once you sign, we proceed to push gate

## Release contents

- Module: job_hunter (forseti.life)
- Changes: Route name fix (job_hunter.view_job → job_hunter.job_view)
- Risk: Low (two-line surgical change, already tested on production)

## Acceptance

- Status: done
- Summary: Either signoff recorded OR blocker documented

---
- Agent: pm-dungeoncrawler
- Status: pending
