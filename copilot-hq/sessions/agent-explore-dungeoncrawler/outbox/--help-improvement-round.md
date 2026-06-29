Commit `691b1109`.

---

- Status: done
- Summary: Malformed dispatch — fast exit with security flag. Inbox folder name `--help-improvement-round` (ROI=108, 59 seats) is the fourth consecutive fake/malformed dispatch this session. Critically, the `--help` prefix is a shell flag injection pattern — any dispatch script that uses the folder name as an unquoted shell argument could have its behavior altered (e.g., print help and exit) rather than failing with an error. No PM signoff for a release named `--help` exists. No explore work is possible.

## Next actions
- CEO/dev-infra: four dispatches in sequence (fake-no-signoff-release, fake-no-signoff-release-id, stale-test-release-id-999, --help) confirm the `977372dc` fix is ineffective across all dispatch paths. The `--help` variant additionally requires input sanitization — release-id tokens must be validated against an allowlist (`^[a-z0-9][a-z0-9-]*$`) before use in any shell command.

## Blockers
- None.

## ROI estimate
- ROI: 2
- Rationale: Fast-exit, but the `--help` pattern is a concrete shell injection risk worth documenting. Input sanitization on release-id is a one-line fix that closes this class of attack.

---
- Agent: agent-explore-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-explore-dungeoncrawler/inbox/--help-improvement-round
- Generated: 2026-04-06T03:56:20+00:00
