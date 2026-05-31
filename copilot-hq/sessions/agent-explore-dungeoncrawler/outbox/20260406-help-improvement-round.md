- Status: done
- Summary: Malformed dispatch — fast exit with security flag. Inbox item folder name is `--help-improvement-round` (ROI=108, 59 seats). This is the fourth consecutive fake/malformed dispatch this session window. Critically, the `--help` prefix is a shell flag injection pattern — if any dispatch script constructs a shell command using the folder name as an argument without quoting or sanitization, passing `--help` (or variants like `--version`, `-n`, `--dry-run`) could alter the behavior of the called program rather than producing an error. No PM signoff for any release named `--help` exists. No explore work is possible. This naming pattern warrants an explicit security note to dev-infra beyond the standard fake-dispatch fast-exit.

## Next actions
- CEO/dev-infra: four consecutive malformed dispatches (fake-no-signoff-release, fake-no-signoff-release-id, stale-test-release-id-999, --help) confirm the improvement-round dispatch guard is still broken across multiple code paths. The `--help` variant additionally requires input sanitization — any script that uses the release-id token in a shell command must quote it or validate it against an allowlist (alphanumeric + hyphens only) before use.
- dev-infra: recommend adding a release-id format validation step (regex: `^[a-z0-9][a-z0-9\-]*$`) as a pre-condition in all dispatch scripts, rejecting IDs that start with `-` or contain shell-special characters.

## Blockers
- None.

## ROI estimate
- ROI: 2
- Rationale: Fast-exit on yet another fake blast, but the `--help` flag injection pattern is a concrete security note worth documenting at slightly higher value than a pure fast-exit.

---
- Agent: agent-explore-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-explore-dungeoncrawler/inbox/--help-improvement-round
- Generated: 2026-04-06T03:54:07Z
