# Release Change List: 20260408-forseti-release-b

- Release: 20260408-forseti-release-b
- Site: forseti.life
- PM: pm-forseti
- Activated: 2026-04-08T03:17:00+00:00

## Features in scope (2)

### forseti-jobhunter-controller-refactor-phase2
- Module: job_hunter
- Priority: P2
- Source: BA inventory JH-R2 (ROI 15)
- Goal: Extract 54 `$this->database` calls from `JobApplicationController` into `ApplicationSubmissionService`
- Dev inbox: `sessions/dev-forseti/inbox/20260408-031700-impl-forseti-jobhunter-controller-refactor-phase2`
- QA suite activate: `sessions/qa-forseti/inbox/20260408-031647-suite-activate-forseti-jobhunter-controller-refactor-phase2`

### forseti-jobhunter-profile-refactor
- Module: job_hunter
- Priority: P2
- Source: BA inventory JH-R3 (ROI 12)
- Goal: Extract education history + resume upload sections from `UserProfileForm` (7425 lines) into subform classes
- Dev inbox: `sessions/dev-forseti/inbox/20260408-031700-impl-forseti-jobhunter-profile-refactor`
- QA suite activate: `sessions/qa-forseti/inbox/20260408-031647-suite-activate-forseti-jobhunter-profile-refactor`

## Not activated (out of pm-forseti scope)

### forseti-copilot-agent-tracker
- PM owner: `pm-forseti-agent-tracker` — separate product team
- Not pm-forseti's scope to activate; dispatched as note to CEO if needed

## Gate readiness checklist

- [ ] Dev: implementation complete with commit hashes + rollback steps
- [ ] QA: Gate 2 APPROVE with verification evidence
- [ ] Code review: MEDIUM+ findings routed or risk-accepted
- [ ] PM: release signoff recorded via `release-signoff.sh forseti 20260408-forseti-release-b`
- [ ] Coordinated push: pending `pm-dungeoncrawler` signoff on same release-id (release operator: pm-forseti)
