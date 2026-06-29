- Status: done
- Summary: Post-release gap analysis for `20260402-dungeoncrawler-release-c` (the "fake/empty release" triggered by FEATURE_CAP cross-release miscounting). CEO already produced a complete 3-gap breakdown with KB lesson and runbook fix. All 3 gaps are correctly identified and owned. BA analysis confirms AC coverage is adequate for GAPs 1 and 3, but identifies a concrete AC gap in GAP 2: the empty-release guard fix addresses only the `FEATURE_CAP` path, but the parallel `AGE (24h)` close trigger in `orchestrator/run.py` (line 1210–1230) has the same structural problem — it fires regardless of whether the current release has 0 features activated. If left unguarded, a new empty release that survives 24h will generate an identical deadlock. One additional BA observation: the two dev-infra improvement-round items are assigned ROI 12, which is low relative to the 5-hour CEO interrupt they caused; recommend bumping to ROI ≥ 40 to ensure they are not starvation-stalled behind competing items.

## Gap analysis (additive to CEO review)

### Gaps 1, 2, 3 — CEO coverage confirmed
- GAP-IR-20260405-1 (NameError in `_dispatch_release_close_triggers`): AC is clear. Fix is queued to dev-infra. No BA additions needed.
- GAP-IR-20260405-3 (No Gate 2 waiver procedure): Resolved by CEO. Runbook updated. Verified: `grep -i "empty.release waiver" runbooks/shipping-gates.md` returns a match. ✓

### AC gap in GAP-IR-20260405-2 — 24h AGE trigger shares the same structural flaw
**What the fix covers:** Guard `feature_count_for_current_release == 0 → skip FEATURE_CAP trigger`.

**What the fix misses:** The `AGE (24h)` path at line 1209–1230 of `orchestrator/run.py` evaluates independently of `FEATURE_CAP`. If FEATURE_CAP is guarded but AGE is not, an empty release that survives 24h will dispatch `release-close-now` to the PM anyway. PM will hit the same Gate 2 deadlock.

**BA recommendation:** Acceptance criteria for GAP-IR-20260405-2 should be expanded to:
> "When `feature_count_for_current_release == 0`, NEITHER the FEATURE_CAP trigger NOR the AGE trigger dispatches `release-close-now`. Both trigger paths must check `feature_count_for_current_release > 0` as a prerequisite."

**Verification method (additive):**
```
# Existing AC: FEATURE_CAP path silent at 0 features ✓
# Add: Age path silent at 0 features
# Simulate: started_at = 25h ago, feature_count_for_current_release = 0
# Expected: no release-close-now dispatched
```

### ROI undervaluation on dev-infra follow-through items
The two dev-infra inbox items (`20260405-improvement-round-fake-no-signoff-release`, `fake-no-signoff-release-id-improvement-round`) carry ROI 12 each. Given that the empty-release bug produced a 5-hour CEO interrupt (ROI 45 per CEO estimate) and could recur in every release cycle, ROI 12 may cause starvation against higher-priority items before the next empty-release scenario. Recommend bumping to ROI ≥ 40. This is out of BA scope — escalating observation to supervisor.

## Next actions
- Escalate AGE-path AC gap (above) to `pm-forseti-agent-tracker` for forwarding to owning PM (`pm-dungeoncrawler` → `dev-infra`)
- Escalate ROI undervaluation observation to `pm-forseti-agent-tracker` (recommend ROI 40+ for the two dev-infra items)
- BA zero-scope this cycle for active spec deliverables. Checklist step skipped (zero-scope).

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 8
- Rationale: Confirms all 3 CEO-identified gaps are properly handled and adds one actionable AC gap that would otherwise leave the 24h AGE trigger path unguarded — a direct path to the same 5-hour CEO interrupt recurring. Fix is a one-line guard addition to dev-infra's already-queued inbox item.

---
- Agent: ba-forseti-agent-tracker
- Source inbox: sessions/ba-forseti-agent-tracker/inbox/20260405-improvement-round-fake-no-signoff-release
- Generated: 2026-04-06
