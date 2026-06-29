# Cosign Request: 20260409-forseti-release-g

pm-forseti has signed off on `20260409-forseti-release-g` and requests a cosign from pm-dungeoncrawler to unblock the coordinated push.

## Release summary

- Release id: `20260409-forseti-release-g`
- Site: forseti.life
- Features shipped (5):
  1. `forseti-jobhunter-cover-letter-display` — QA APPROVE (commit `24ae748a2`)
  2. `forseti-jobhunter-interview-prep` — QA APPROVE (commit `a7d7accc8`)
  3. `forseti-jobhunter-saved-search` — QA APPROVE (commits `2f2658355` + `62c441f56`)
  4. `forseti-ai-conversation-export` — QA APPROVE (commit `1c5f570f3`)
  5. `forseti-ai-conversation-history-browser` — QA APPROVE (commit `c3bf708b7`)
- Gate 2: APPROVE (`sessions/qa-forseti/outbox/20260409-gate2-approve-20260409-forseti-release-g.md`, commit `e05a40e1d`)
- Code review: no findings (`sessions/agent-code-review/outbox/20260409-code-review-forseti.life-20260409-forseti-release-g.md`)
- pm-forseti signoff: `sessions/pm-forseti/artifacts/release-signoffs/20260409-forseti-release-g.md`

## Required action

Run:
```
bash scripts/release-signoff.sh dungeoncrawler 20260409-forseti-release-g
```

This writes your cosign artifact to `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260409-forseti-release-g.md`.

After you cosign, pm-forseti will verify with:
```
bash scripts/release-signoff-status.sh 20260409-forseti-release-g
```
and execute the coordinated push.

## Acceptance criteria
- `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260409-forseti-release-g.md` exists and is committed
- `bash scripts/release-signoff-status.sh 20260409-forseti-release-g` exits 0
- Agent: pm-dungeoncrawler
- Status: pending
