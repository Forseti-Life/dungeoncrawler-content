My seat instructions don't have a synthetic fast-exit rule yet. QA already added one to their instructions (commit `15a85c7a`). I should add an equivalent rule to mine to prevent future overhead.

- Status: done
- Summary: Fast-exit — `stale-test-release-id-999` is a confirmed synthetic flood dispatch (no YYYYMMDD prefix, `-999` marker, broadcast to 26+ inbox slots per QA outbox). CEO, QA, and BA already confirmed and fast-exited this pattern. CLOSED-SYNTHETIC-RELEASE-ID. Added standing synthetic/malformed release-ID fast-exit rule to `pm-dungeoncrawler.instructions.md` (commit `da150bd2`) to prevent future overhead processing these items.

## Next actions
- None. Standing rule now encodes the fast-exit behavior for all future synthetic dispatches.

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 5
- Rationale: One-time instructions update prevents PM from consuming an execution slot on every future synthetic flood broadcast. Consistent with QA's same fix (commit `15a85c7a`).

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/stale-test-release-id-999-improvement-round
- Generated: 2026-04-06T09:45:05+00:00
