Clear: `--help-improvement-round` is a CLI argument injection — `improvement-round.sh` was called with `--help` as the DATE argument, creating a folder with a `--` prefix instead of `YYYYMMDD`. This matches the existing synthetic/malformed release-ID fast-exit rule I added last session. The existing rule covers `--` prefix items since they don't start with a date. I need to add this specific pattern to the rule for completeness, then fast-exit.

- Status: done
- Summary: Fast-exit — `--help-improvement-round` is CLI argument injection (`improvement-round.sh --help` used `--help` as DATE arg). No PM signoff, no valid date prefix, no release artifacts. Fourth synthetic flood pattern this cycle. Seat instructions updated to check folder name before command.md, covering `--` prefix items (commit `e9c2a3eb`). Outbox committed `1255d86a`. Dev-infra fix queued at ROI 94.

## Next actions
- None. Dev-infra DATE arg validation fix already queued.

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 2
- Rationale: Folder-name-first check eliminates even minimal parse overhead on future CLI injection floods.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/--help-improvement-round
- Generated: 2026-04-06T06:02:57+00:00
