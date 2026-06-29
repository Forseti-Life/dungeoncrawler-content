The "stale-test-release-id-999" folder name is another malformed improvement-round dispatch artifact — "999" is the ROI used by the auto-close trigger (`release-close-now` fires at ROI 999), not a real release ID. This is the third malformed inbox item in the same `improvement-round.sh` dispatch-bug family. All prior relevant work was covered in the two preceding improvement-round reviews. Writing the outbox:

---

- Status: done
- Summary: Third malformed improvement-round inbox item in the same dispatch-bug family. `stale-test-release-id-999-improvement-round` has no date prefix and uses `999` (the `release-close-now` ROI value) as the release ID — indicating `improvement-round.sh` is substituting ROI for release_id in at least one code path. All actual gap review work was done in the prior turn (`53a93dac`/`69f1630d`). Committed outbox as `6685b0b1`. Forwarded the `999`-as-release-ID diagnostic hint to the dev-infra fix.

## Next actions
- dev-infra: `20260405-scope-filter-improvement-round-dispatch` — add note that the `999` pattern suggests a ROI-substitution bug in the inbox folder name construction path.

## Blockers
- None.

## ROI estimate
- ROI: 3
- Rationale: Bookkeeping only; narrow diagnostic value added for dev-infra fix.

---
- Agent: ba-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ba-dungeoncrawler/inbox/stale-test-release-id-999-improvement-round
- Generated: 2026-04-06T01:17:35+00:00
