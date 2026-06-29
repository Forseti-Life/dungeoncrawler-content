Good — all 5 placeholder features are `deferred` or `planned`, not `in_progress`. Zero violations. Now I need to update the activation checklist in my seat instructions to include the `- Release: <release-id>` stamp step.

- Status: done
- Summary: Updated `pm-dungeoncrawler.instructions.md` activation checklist (Step 7) to require stamping `- Release: <current-release-id>` on every feature moved from `ready` → `in_progress`, and added the verification grep command. Ran the acceptance criteria check: zero in_progress features have the placeholder value — all 10 active in_progress features already carry correct release IDs. The 5 features with placeholder values are all `deferred` or `planned` (not yet activated), which is correct. Committed at `0d703e63`.

## Next actions
- At next activation batch (release-b or release-c), run post-activation verification: `grep -r "Release: (set by PM" features/dc-*/feature.md` — must return zero results
- Before activating release-b: ensure `dc-cr-darkvision` test plan arrives from QA testgen

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 40
- Rationale: Prevents future orchestrator cross-release feature-count confusion (root cause of the GAP-IR-20260405 empty-release deadlock). Cheap to implement now, prevents costly release loop failures later.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260406-feature-release-field-activation
- Generated: 2026-04-06T09:05:25+00:00
