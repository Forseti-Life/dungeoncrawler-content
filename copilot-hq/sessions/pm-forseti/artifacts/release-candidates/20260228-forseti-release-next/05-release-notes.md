# Release Notes: 20260228-forseti-release-next

## Metadata
- Release id: 20260228-forseti-release-next
- Release candidate path: sessions/pm-forseti/artifacts/release-candidates/20260228-forseti-release-next/
- Release window: 2026-02-28
- Release coordinator: pm-forseti
- Streams included: forseti.life, dungeoncrawler (coordinated)
- Generated at (ISO-8601): 2026-02-28T17:58:11Z
- Last updated at (ISO-8601): 2026-02-28T17:58:11Z

## Summary
- Enabled ai_conversation, dungeoncrawler_content, dungeoncrawler_tester modules on forseti.life (dungeoncrawler MVP integration)
- Restricted talk-with-forseti routes to authenticated users only (ACL fix)
- HQ: grooming artifacts, QA improvements, process/tooling improvements across forseti + dungeoncrawler streams

## Change Log by Stream
### Forseti
- Feature/defect: Enable dungeoncrawler modules on forseti.life
  - Work item: forseti-release-next scope
  - Commit(s): a382af780, cf808dd76
  - Owner: dev-forseti
- Feature/defect: HQ process improvements (seat instructions, QA tooling, grooming artifacts)
  - Work item: multiple improvement rounds
  - Commit(s): see HQ repo log since 20260228-dungeoncrawler-release push
  - Owner: multiple seats

### Dungeoncrawler
- Feature/defect: Coordinated release alignment — no new dungeoncrawler code scope in this release-id; signoff confirms dungeoncrawler gates are met alongside forseti changes
  - Work item: N/A (coordinated window)
  - Commit(s): N/A
  - Owner: pm-dungeoncrawler

## User-visible changes
- forseti.life users: dungeoncrawler module surfaces are now active on forseti.life platform
- talk-with-forseti routes now require authentication (previously accessible to anonymous)

## Admin / operational changes
- No config import required (no new config in sync dir — config drift PASS confirmed at QA run 20260228-124535)
- No DB migration steps required

## Verification evidence
- QA report path(s): sessions/qa-forseti/artifacts/auto-site-audit/20260228-124535/findings-summary.md
- Automated suite run(s): 20260228-110942, 20260228-114150, 20260228-120311, 20260228-122421, 20260228-124535 (all PASS — 0 violations, 0 404s, 0 failures)
- Post-release audit path(s) (fill after push): TBD — qa-forseti to run post-release audit against production

## Risk / caveats
- Low risk: ACL and module-enable changes only; no schema migrations
- talk-with-forseti now requires auth: intentional per ACL hardening; previously allowed anon access was a misconfiguration

## Rollback
- Primary rollback steps: `git revert a382af780 cf808dd76` on forseti.life repo; redeploy via GitHub Actions workflow_dispatch
- Rollback owner: dev-forseti

## Known issues / follow-ups
- forseti-jobhunter-browser-automation: test plan written; feature ready for next dev cycle (not in this release scope)
- forseti-release-b: separate release-id still has pending dungeoncrawler signoff; unrelated to this push

## Signoffs
- PM signoff status: pm-forseti (forseti.life) signed 2026-02-28T11:40:25-05:00; pm-dungeoncrawler (dungeoncrawler) signed 2026-02-28T12:56:51-05:00
- QA signoff status: PASS — 5 consecutive clean runs (latest: 20260228-124535)
- Security note status: No Critical/High open findings in forseti scope (improvement rounds confirm 0 violations)
