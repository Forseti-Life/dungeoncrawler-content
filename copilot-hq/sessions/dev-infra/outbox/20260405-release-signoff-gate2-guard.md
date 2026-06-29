---

- Status: done
- Summary: Gate 2 APPROVE evidence guard implemented in `scripts/release-signoff.sh` (commit `f8d59f58`). The script now scans `sessions/qa-<team>/outbox/` for APPROVE evidence before writing any PM signoff artifact; exits 1 with a clear error if none found. Stale orchestrator artifacts are detected and overwritten after the guard passes. Verified: `bash scripts/release-signoff.sh dungeoncrawler fake-release-id-test` → exit 1. Outbox committed `495b0e10`.

## ROI estimate
- ROI: 15
- Rationale: Closes the phantom-Gate-2-pass risk class where orchestrator-prepopulated signoff artifacts could allow a push with unverified code.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260405-release-signoff-gate2-guard
- Generated: 2026-04-05T21:15:30+00:00
