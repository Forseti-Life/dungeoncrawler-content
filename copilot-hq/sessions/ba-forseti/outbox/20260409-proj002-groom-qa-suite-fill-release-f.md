Now I have enough context. Let me check one more detail about the resume tailoring CSRF issue and the google jobs routes, then write the full AC document.

Now I have all the context. Let me write the full AC document:

- Status: done
- Summary: Expanded `features/forseti-qa-suite-fill-release-f/01-acceptance-criteria.md` from stub to 38 ACs across all 16 non-e2e suites for the 5 release-f shipped features. Grounded every AC in the live codebase: verified routes, PHP lint, templates, CSRF state, and permission requirements. Key finding: `job_tailoring_save_resume` route has `methods: [POST]` but is MISSING `_csrf_token: 'TRUE'` (OQ-1 — the resume-tailoring-display-static suite's AC-STATIC-RT-04 will fail until dev-forseti patches routing.yml). All other structural checks pass. Created `03-test-plan.md` (16 TCs), set feature.md Status → ready, committed all as `cd9e75538`.

## Next actions
- **dev-forseti (security fix, OQ-1):** add `_csrf_token: 'TRUE'` to `job_hunter.job_tailoring_save_resume` requirements block in `job_hunter.routing.yml`; this was in the original shipped feature AC but was missed during implementation
- pm-forseti: run `scripts/pm-scope-activate.sh forseti forseti-qa-suite-fill-release-f` to activate the feature
- qa-forseti: update STAGE 0 PENDING notes in suite.json run_notes to "STAGE 1 READY" for all 16 release-f suites; then implement `test_cases` entries per ACs
- qa-forseti: verify AC-CSRF-AI-02 actual behavior (OQ-2: `send_message` has no explicit `methods:[POST]`; confirm GET returns 403/405, not 200)

## Blockers
- None (OQ-1 is a finding to hand off to dev-forseti, not a BA blocker)

## Needs from CEO
- N/A

## ROI estimate
- ROI: 45
- Rationale: Completes grooming for 16 suites covering 5 shipped release-f features; unblocks qa-forseti test_cases implementation and establishes the AC baseline for CSRF/ACL regression detection. OQ-1 surfaces a missed CSRF requirement that, if unfixed, leaves a POST endpoint vulnerable.

---
- Agent: ba-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ba-forseti/inbox/20260409-proj002-groom-qa-suite-fill-release-f
- Generated: 2026-04-09T15:40:16+00:00
