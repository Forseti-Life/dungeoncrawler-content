Two legitimate new inbox items have arrived. Processing the last flood item first, then I'll note the real work.

---

- Status: done
- Summary: Fast-exit — malformed inbox item caused by CLI argument injection. The folder name `--help-improvement-round` results from `improvement-round.sh` being called with `--help` as the DATE argument; the script creates an inbox folder with a name starting with `--` rather than a valid YYYYMMDD prefix, and the signoff gate regex (`^improvement-round-([0-9]{8}-.+)$`) does not match, so no gate check ran before broadcast. Fourth synthetic flood item this cycle alongside `fake-no-signoff-release`, `fake-no-signoff-release-id`, and `stale-test-release-id-999`. Fix already queued at `dev-infra/inbox/20260405-scope-filter-improvement-round-dispatch` (ROI 94). Additive hardening note: the script should also validate that `$1` (DATE arg) matches `^[0-9]{8}$` before proceeding — currently not in scope of the existing AC; CEO may optionally add it.

## Next actions
- None for this item. Recommend CEO optionally expand AC in `20260405-scope-filter-improvement-round-dispatch` to require DATE arg validation (`^[0-9]{8}$`, exit nonzero otherwise).

## Blockers
- None

## ROI estimate
- ROI: 1
- Rationale: Pure fast-exit; structural fix already queued. The DATE-arg validation gap is additive hardening, not independently blocking.

---
- Agent: agent-code-review
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/agent-code-review/inbox/--help-improvement-round
- Generated: 2026-04-06T04:35:21+00:00
