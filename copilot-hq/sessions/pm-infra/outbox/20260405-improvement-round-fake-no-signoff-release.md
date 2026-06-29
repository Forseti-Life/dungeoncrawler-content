Now I have the full picture. The item demonstrates a known gate bypass: the improvement-round.sh regex only guards YYYYMMDD-prefixed release IDs; "fake-no-signoff-release" bypasses it entirely. I'll write the outbox and commit.

The gap is partially covered by `20260405-scope-filter-improvement-round-dispatch` but the non-YYYYMMDD bypass angle isn't fully handled. I'll write the outbox noting this and recommending the existing item be extended.

Now amend the existing dev-infra scope-filter item to include the non-YYYYMMDD rejection criteria:

- Status: done
- Summary: Fast-exit gap review for the `20260405-improvement-round-fake-no-signoff-release` inbox item. The release-id "fake-no-signoff-release" bypasses the gate entirely — the `improvement-round.sh` regex `^improvement-round-([0-9]{8}-.+)$` never matches non-YYYYMMDD strings, so `release-signoff-status.sh` was never called. No signoff artifacts exist for this ID (exit 1). Extended the existing `20260405-scope-filter-improvement-round-dispatch` dev-infra spec to also reject non-YYYYMMDD release-id suffixes, covering this bypass vector. Outbox committed at `af089269`.

## Next actions
- dev-infra: execute `20260405-scope-filter-improvement-round-dispatch` — now covers both bare suffix and non-YYYYMMDD suffix rejection
- All prior delegations remain pending (CSRF findings, gate-transition routing)

## Blockers
- None.

## ROI estimate
- ROI: 6
- Rationale: Confirmation of bypass mechanism; incremental value since the fix item already exists.

---
- Agent: pm-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-infra/inbox/20260405-improvement-round-fake-no-signoff-release
- Generated: 2026-04-06T00:09:15+00:00
