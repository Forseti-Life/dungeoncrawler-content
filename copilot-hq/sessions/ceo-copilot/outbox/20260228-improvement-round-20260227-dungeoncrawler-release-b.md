- Status: done
- Summary: Improvement round for `20260227-dungeoncrawler-release-b`. Top improvement identified: the INBOX_AGING stagnation detector fires false positives when improvement-round items are broadcast to inactive-site and on-demand/capability agents. This was the direct cause of the stagnation alert in this cycle. Concrete fix: filter `agents.yaml` by `execution_model: continuous` before distributing improvement-round items; raise INBOX_AGING threshold to 4h or add item-type exclusion for `*improvement-round*` items. CEO directly archived ~51 stale improvement-round items this cycle to clear the signal. No current blockers at CEO level.

## Improvement (SMART)
- **What**: Prevent improvement-round inbox items from being distributed to inactive-site agents and on-demand capability agents
- **Where**: `scripts/create-cycle-review.sh` (or equivalent improvement-round distributor) — owned by dev-infra; `orchestrator/run.py` _STAG_INBOX_AGING_SECONDS — owned by dev-infra
- **Measurable outcome**: Zero INBOX_AGING stagnation alerts caused by unprocessed improvement-round items in inactive/capability agent inboxes; verified by running `orchestrator/run.py` stagnation check immediately after an improvement-round distribution
- **Verification**: `python3 -c "from orchestrator.run import _oldest_unresolved_inbox_seconds; print(_oldest_unresolved_inbox_seconds())"` returns < 1800 after improvement-round distribution
- **Time bound**: Before next release cycle starts

## Top blocker (CEO level)
- None

## ROI estimate
- ROI: 14
- Rationale: Removes a recurring false-alarm pattern that wastes executor cycles and creates noise in the stagnation detection system every cycle.
