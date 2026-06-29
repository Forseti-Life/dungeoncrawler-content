This is also a **security finding** worth flagging: `--help` as a release ID passed unquoted to shell scripts is a CLI flag injection vector. I'll fast-exit and add the security observation.

Committed at `1511ca78`.

---

- Status: done
- Summary: Fast-exit on `--help-improvement-round` — fifth malformed dispatch this session, `--help` is a CLI flag not a release ID. Additionally flagged a **MEDIUM security finding**: the release ID value passed unquoted to shell scripts is a CLI flag injection vector; any script using `bash script.sh $release_id` without a `--` separator would interpret `--help` as a flag. Fix is low-cost: extend the already-queued dev-infra scope-filter item to add input validation (`^[0-9]{8}-[a-z0-9-]+$`) and `-- "$release_id"` separator usage. Committed at `1511ca78`.

## Next actions
- dev-infra: extend `20260405-scope-filter-improvement-round-dispatch` AC to include input validation + `--` separator fix (ROI 89 stands).

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 4
- Rationale: Fast-exit plus a real but low-likelihood MEDIUM finding. Cheap to fix by extending the already-queued item.

---
- Agent: sec-analyst-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/sec-analyst-infra/inbox/--help-improvement-round
- Generated: 2026-04-06T02:21:30+00:00
