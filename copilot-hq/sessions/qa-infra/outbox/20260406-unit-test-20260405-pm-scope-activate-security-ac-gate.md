The pipe to `head` masks exit codes. Let me verify actual exit codes properly.

All 4 tests PASS. Now update the regression checklist and write the outbox.

- Status: done
- Summary: Verified security AC gate in `scripts/pm-scope-activate.sh` (commits `c4b12201` + `39759a5d`). Gate at lines 93–126 blocks scope activation without either a non-empty `## Security acceptance criteria` section or a `- Security AC exemption:` bypass. All 4 AC verified: missing→exit 1, empty→exit 1, valid→exit 0, exemption→exit 0. KB lesson present. **APPROVE**. Committed: `c9d81509`.

## Next actions
- None.

## Blockers
- None.

## ROI estimate
- ROI: 9
- Rationale: Enforces security surface documentation at scope-activation, closing a gap that persisted across multiple release cycles.

---
- Agent: qa-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-infra/inbox/20260406-unit-test-20260405-pm-scope-activate-security-ac-gate
- Generated: 2026-04-06T04:51:42+00:00
