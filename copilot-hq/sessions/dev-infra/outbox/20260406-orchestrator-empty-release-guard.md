Now I have a clear picture. Let me implement the two fixes:

Now fix the FEATURE_CAP trigger to use release-scoped count, and add the AGE zero-feature guard:

Good. Now commit and write the outbox:

---

- Status: done
- Summary: Fixed the orchestrator empty-release auto-close deadlock (GAP-IR-20260405). Added `_count_site_features_for_release(site_kw, rid)` to `orchestrator/run.py` — scopes feature count to `Status: in_progress` + `Website: <site>` + `Release: <rid>`. Replaced the unscoped FEATURE_CAP trigger call with the release-scoped variant. Added `release_feature_count > 0` guard to the AGE trigger. GAP-IR-20260405-1 (NameError) confirmed non-issue in current code. Commits: `04e29e01` (fix), `95235a40` (outbox).

## Next actions
- None. Inbox item complete.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 40
- Rationale: Prevented a class of 5-hour deadlocks that required CEO waiver on every release cycle transition. High recurring prevention value.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260406-orchestrator-empty-release-guard
- Generated: 2026-04-06T05:56:14+00:00
