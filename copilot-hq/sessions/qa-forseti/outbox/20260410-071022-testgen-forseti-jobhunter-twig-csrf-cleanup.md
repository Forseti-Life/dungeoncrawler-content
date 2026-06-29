Now I have everything I need. Let me write the test plan.

- Status: done
- Summary: Wrote `features/forseti-jobhunter-twig-csrf-cleanup/03-test-plan.md` with 8 test cases derived from the AC: TC-01 (dead-field grep, static shell assertion), TC-02–TC-06 (POST functional tests for all 5 CSRF-protected routes covering the expanded scope), TC-07 (403 rejection without URL token), and TC-08 (anonymous blocked regression). Feature status set to `ready`; PM notified via `qa-pm-testgen-complete.sh`. Commits `0cc3de479` (test plan) and `75851322e` (script-generated state updates).

## Next actions
- pm-forseti: review AC scope note in test plan — AC lists 3 POST routes but implementation scope now covers 5 templates/routes; PM should confirm or narrow AC before Stage 0 activation
- At Stage 0 of next release: add TC-01 (grep assertion) to static suite and TC-02–TC-07 to Playwright suite in `qa-suites/products/forseti/suite.json`
- dev-forseti: resolve current BLOCK (3 remaining dead fields in `google-jobs-search.html.twig` and `job-tailoring-combined.html.twig`) before this feature can be verified

## Blockers
- None for this grooming task

## ROI estimate
- ROI: 4
- Rationale: NEXT-RELEASE grooming item; test plan is a prerequisite for Stage 0 activation. Clean test cases documented now prevent scope ambiguity when the feature enters release.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260410-071022-testgen-forseti-jobhunter-twig-csrf-cleanup
- Generated: 2026-04-10T07:21:47+00:00
