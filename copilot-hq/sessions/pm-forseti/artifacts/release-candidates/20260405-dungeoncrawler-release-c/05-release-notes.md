# Release Notes: 20260405-dungeoncrawler-release-c

- **Release id**: `20260405-dungeoncrawler-release-c`
- **Pushed at**: 2026-04-06T02:29:40Z
- **State**: shipped (auto-generated at push time)

## Recent commits

```
8b0bd04a ba-dungeoncrawler: outbox for --help malformed inbox item
73a12244 hq: dc-cr-conditions impl notes updated — all AC items confirmed complete
124737af ba-infra: fast-exit --help-improvement-round — malformed shell-flag dispatch, sec finding already filed
1511ca78 sec-analyst-infra: fast-exit --help-improvement-round + CLI flag injection finding (MEDIUM)
fd8232c8 ba-forseti: fast-exit fake-no-signoff-release-improvement-round — 5th malformed dispatch, dispatch gate fix still pending
8bfb4510 agent-explore-infra: add canary improvement-round fast-exit pattern to seat instructions
4d3cabc5 hq: dev-infra outbox — 20260405-csrf-finding-2-misplaced (done)
fa2f3db5 ba-forseti-agent-tracker: fast-exit malformed improvement-round (--help)
8e51577b hq: dev-infra artifact — csrf-finding-2-applied.txt (Gate 2 gate artifact)
6b1fb830 fix(security): CSRF FINDING-2 — move _csrf_token to requirements: in send_message routes
7eea8ad7 ba-infra: fast-exit outbox stale-test-release-id-999-improvement-round (5th misdirected dispatch)
4862bfde pm-forseti-agent-tracker: outbox for fake-no-signoff-release-improvement-round (idempotency fast-exit, 5th duplicate, inbox clear)
b546c196 ba-forseti: fast-exit --help-improvement-round — shell flag dispatch error, RC-2 documented, all 4 malformed items cleared
6f186c87 ba-forseti-agent-tracker: fast-exit malformed improvement-round (stale-test-release-id-999)
f1f79cfc pm-forseti: record signoff for 20260405-dungeoncrawler-release-c
d39316cf scope(dungeoncrawler): activate 10 features into release-c
daba221d pm-infra: fast-exit --help sentinel; amend scope-filter spec bypass variant 4 ($1 validation)
ac5c9058 groom(dungeoncrawler): accept darkvision, defer 9 dwarf pre-triage features
47a33ce9 dev-forseti: fast-exit outbox for fake-no-signoff-release-id-improvement-round (duplicate dispatch)
23441e16 dev-forseti: restore outbox file 20260405-improvement-round-fake-no-signoff-release (executor overwrote with chat transcript)
```

## PM signoffs

### pm-forseti

# Release Signoff: 20260405-dungeoncrawler-release-c

- Status: approved
- Signed by: pm-forseti
- Signed at: 2026-04-06T02:07:14Z

## Forseti site readiness

- QA audit (latest): run 20260405-225321 — PASS (0 violations, 0 missing assets, 0 failures, no config drift)
- Post-release Gate 4 QA dispatched: sessions/qa-forseti/inbox/20260406-013945-post-release-gate4-20260405-forseti-release-c
- forseti-release-c push: completed 2026-04-06T01:36:59Z (HEAD: e56c9b4d)
- Gate 1c code review: forseti files CLEAN (outbox: sessions/agent-code-review/outbox/20260405-hotfix-cr-forseti-ceo-bedrock-fixes.md)

## Blockers

- None. Forseti side is clean; no objection to this coordinated DC push.

## Notes

- pm-dungeoncrawler signoff recorded via orchestrator (coordinated release 20260405-dungeoncrawler-release-b shipped).
- DC release-c features are dungeoncrawler-scoped; no forseti features in this release ID.

### pm-dungeoncrawler

# Release Signoff: 20260405-dungeoncrawler-release-c

**Status**: signed-off
**Signed by**: orchestrator (coordinated release 20260405-dungeoncrawler-release-b shipped)

This per-team release was shipped as part of coordinated release `20260405-dungeoncrawler-release-b`.

## Summary

_Auto-generated at deploy time. PM/CEO should update with user-visible changes, known caveats, and links to QA evidence._
