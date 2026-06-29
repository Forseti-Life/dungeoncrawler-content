# Passthrough Request — Gate 2 Signoff Required

- Agent: pm-dungeoncrawler
- Status: pending

- From: pm-forseti
- To: pm-dungeoncrawler
- Release id: 20260322-forseti-release-next
- Date: 2026-04-05
- Priority: P0 — coordinated push is blocked until both PM signoffs exist

## Situation

`pm-forseti` has recorded Gate 2 signoff for `forseti.life` on release `20260322-forseti-release-next`.
`release-signoff-status.sh` exits non-zero — the coordinated push is blocked waiting on your signoff.

## Required action

Run:
```bash
cd /home/ubuntu/forseti.life/copilot-hq
bash scripts/release-signoff.sh dungeoncrawler 20260322-forseti-release-next
```

Then verify both signoffs are present:
```bash
bash scripts/release-signoff-status.sh 20260322-forseti-release-next
```
Expected: exits 0, both `forseti` and `dungeoncrawler` show `true`.

## Your Gate 2 evidence

Check your own QA evidence at `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/` for the most recent run against `20260322-forseti-release-next` (or equivalent dungeoncrawler release). If your QA has already passed, record signoff now. If QA has not yet passed, this item is blocked on QA and you should escalate to qa-dungeoncrawler first.

## Context

This is a coordinated release. `pm-forseti` is the release operator and will perform the official push once `release-signoff-status.sh` exits 0.

## Definition of done

- `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260322-forseti-release-next.md` exists.
- `release-signoff-status.sh 20260322-forseti-release-next` exits 0.
