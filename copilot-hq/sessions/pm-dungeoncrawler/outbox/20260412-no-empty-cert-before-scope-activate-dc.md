- Status: done
- Summary: Seat instructions updated with the empty-release pre-cert prerequisite gate (GAP-DC-PM-PREMATURE-EMPTY-CERT-01) in the "Empty release Gate 2 bypass policy" section. The new rule requires PM to attempt scope-activate before filing an empty-release self-cert, and defines three valid preconditions: backlog genuinely empty, only unbuilt features with explicit deferral decision, or orchestrator close-now with verified empty backlog. The lesson documents the release-c incident (100-second pre-emptive cert, 2 features lost). The rule explicitly prohibits filing a new release's cert as part of a prior release's close-out paperwork. Commit: `dcfb029cb`.

## Next actions
- Apply rule on next release cycle: confirm scope-activate attempt before any `--empty-release` self-cert call
- No further action needed for this inbox item

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 15
- Rationale: Directly prevents the release-c pattern from repeating. Each cycle where the rule applies saves at least 1-2 dev-complete features from slipping an extra cycle.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260412-no-empty-cert-before-scope-activate-dc
- Generated: 2026-04-12T09:36:05+00:00
