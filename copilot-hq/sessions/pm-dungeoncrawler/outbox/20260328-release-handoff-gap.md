Now I have enough context. This is a process gap documentation item — specifically the gap where the orchestrator pre-populated the signoff artifact, creating a false "signed-off" signal before Gate 2 was done. Let me fill the artifacts.

Now write the KB lesson learned and update seat instructions:

- Status: done
- Summary: Documented the release handoff gap where the orchestrator pre-populated `20260327-dungeoncrawler-release-b` signoff with a stale `20260326` reference before Gate 2 ran, creating a false "signed-off" signal. Three gap artifacts written, KB lesson created at `knowledgebase/lessons/20260328-orchestrator-premature-signoff-artifact.md`, and seat instructions updated with a mandatory pre-signoff Gate 2 validation + stale-artifact detection step. Current release-b is unaffected (push gate remains blocked on pm-forseti).

## Next actions
- **qa-dungeoncrawler**: process 4 Gate 2 verification items (unchanged).
- **pm-dungeoncrawler**: after 4th QA APPROVE — re-run `scripts/release-signoff.sh dungeoncrawler 20260327-dungeoncrawler-release-b` to replace stale artifact.
- **ceo-copilot**: consider routing same stale-artifact detection recommendation to pm-forseti seat instructions.

## Blockers
- None new.

## ROI estimate
- ROI: 7
- Rationale: Prevents a future push with zero QA verification if both PM signoffs are pre-populated; high-severity failure mode now documented with prevention steps.

Commit: `a0eac1ec3`

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260328-release-handoff-gap
- Generated: 2026-03-28T00:44:14-04:00
