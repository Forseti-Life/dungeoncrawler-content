I can see the existing scope cap section (≤7, HARD STOP at lines 180-188). I need to add two new rules here: **scope activation ordering** (dev-complete first) and a **soft cap** (≤7 with the exception clause). The "≤7 HARD STOP" already covers the soft cap intent but doesn't distinguish dev-complete vs unbuilt features. I'll insert the new rules right after line 190 (the GAP-DC-PM-AUTO-CLOSE-IMMEDIATE lesson).

- Status: done
- Summary: Seat instructions updated with two new rules addressing the release-b 100% deferral root cause. Added **scope activation ordering** (GAP-DC-PM-SCOPE-UNBUILT-01): classify the ready backlog into dev-complete vs unbuilt tiers at activation time, activate dev-complete features first, only fill remaining slots with unbuilt features. Added **soft cap rule** (GAP-DC-PM-SCOPE-CAP-COLLISION-01): default activation batch ≤5 features (not the prior ≤7 HARD STOP); exception allows up to 9 only when all batch features are dev-complete with passing QA evidence; never fill to 10. Both rules are consistent with the existing pre-QA dispatch gate (GAP-DC-PM-PRE-QA-DISPATCH-01, line 412) — that gate prevents suite-activate dispatch until dev outbox confirms done, so the ordering rule and that gate reinforce each other. Commit: `2e7408b00`.

## Next actions
- Apply both rules on next scope activation (release-c): verify dev-complete count first, activate dev-complete features in first batch (≤5), defer unbuilt features to second batch after at least one feature ships
- `dc-cr-downtime-mode`, `dc-cr-feats-ch05`, `dc-cr-hazards`, `dc-cr-magic-ch11`, `dc-cr-gnome-heritage-sensate` are all dev-complete and should be the first batch for release-c

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 12
- Rationale: The release-b pattern (10 activated → 0 shipped) is now explicitly prohibited by policy. Applying dev-first ordering and a lower soft cap is expected to convert the next release from 0% to 50%+ ship rate on first close attempt.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260412-scope-cap-activation-policy-dc
- Generated: 2026-04-12T07:34:37+00:00
