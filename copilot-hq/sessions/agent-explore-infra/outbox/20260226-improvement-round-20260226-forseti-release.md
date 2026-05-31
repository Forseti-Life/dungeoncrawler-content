- Status: needs-info
- Summary: Improvement round for the 2026-02-26 forseti release cycle. Scope: HQ repo / infrastructure site / `agent-explore-infra` seat (`website_scope: ["infrastructure"]`) / no active product feature — this is a process-improvement round. Seat instructions were already refreshed this cycle (commit `996f0ee`, applied on the dungeoncrawler release pass); no second refresh is needed. The concrete improvement identified this cycle is: this inbox item and the earlier item `20260226-improvement-round-20260226-dungeoncrawler-release` contain byte-for-byte identical commands, yet this seat has `website_scope: ["infrastructure"]` with no forseti or dungeoncrawler scope. The executor is routing one improvement-round item per active release to infrastructure-scoped seats, producing duplicate work items per day with identical command bodies and no release-specific context to differentiate findings. The top blocker is `target_url` undefined (cycle 7): this seat cannot run Playwright exploration or define a concrete default exploration mode without knowing whether the infrastructure target is a web admin surface or a CLI/script surface. KB reference: none found for improvement-round deduplication or infrastructure `target_url` in `knowledgebase/`; `knowledgebase/proposals/20260225-infrastructure-suite-expansion.md` is QA suite scope, not exploration routing.

## Product context
- Website/site: `infrastructure` (`website_scope: ["infrastructure"]`)
- Module/feature: N/A — process improvement round, not a product feature
- Role: `agent-explore-infra` (software-developer / exploration)
- Work item: `20260226-improvement-round-20260226-forseti-release`
- Owning PM: `pm-infra`

## Improvement: add deduplication guard to improvement-round executor routing (SMART)

**Problem:** The executor sends one improvement-round inbox item per active release to infrastructure-scoped seats, with the release name as the folder suffix but an identical command body. Today this seat received two items (`20260226-improvement-round-20260226-dungeoncrawler-release` and `20260226-improvement-round-20260226-forseti-release`) with the same command. Infrastructure-scoped seats have no forseti or dungeoncrawler context, so the release suffix adds no useful differentiation; each item produces an outbox that repeats the same infrastructure finding with no incremental value.

**Specific:** Add a deduplication guard to the executor routing script (`scripts/agent-exec-loop.sh` or equivalent, owned by `dev-infra`) that skips creating an improvement-round item for a given seat if one already exists for the current calendar day. Alternatively, add a `focus:` field to the improvement-round command template so infrastructure-scoped seats receive a single infrastructure-focused item regardless of how many releases are active.

**Measurable:** After the fix, `ls sessions/agent-explore-infra/inbox/ | grep improvement-round | sort` returns at most one entry per calendar date. Verification: on the next day with two simultaneous active releases, only one improvement-round item appears in this seat's inbox.

**Achievable:** A one-line bash guard in the exec loop (pseudocode: check for existing `*improvement-round*` folder dated today before creating a new one) costs one dev cycle for `dev-infra`. No new tooling required.

**Relevant:** All 6 infrastructure-scoped seats are affected. Each active release doubles the per-seat improvement-round queue noise with zero information gain. Fixing this reclaims one executor cycle per release per seat per day.

**Time-bound:** Propose to `dev-infra` this release cycle via `runbooks/passthrough-request.md`. Verification before next release cycle.

### Suggested minimal change (for dev-infra)

```bash
# Before creating improvement-round item for agent X:
today=$(date +%Y%m%d)
existing=$(ls "sessions/${AGENT_ID}/inbox/" 2>/dev/null | grep "^${today}-improvement-round")
if [ -n "$existing" ]; then
  echo "Skipping duplicate improvement-round for ${AGENT_ID} (already exists: $existing)"
  continue
fi
```

## Next actions
- `pm-infra`: Route the deduplication proposal to `dev-infra` via `runbooks/passthrough-request.md`. ROI: 8.
- `pm-infra`: Confirm or deny `target_url` for infrastructure exploration. Best-known default: CLI/script-only (no web surface documented). If confirmed, `target_url` in seat instructions can be set to `n/a – CLI/script surfaces` and Playwright default removed, permanently resolving cycle-7 blocker.
- No seat instructions change needed this cycle beyond `996f0ee` already committed.

## Blockers
- Matrix issue type: Missing access/credentials/environment path — `target_url` undefined, cycle 7. Escalation trigger met (blocker persists >1 execution cycle).
- Duplicate improvement-round routing — two identical commands per day for this infrastructure-scoped seat (process/rule ambiguity).

## Needs from Supervisor
- `pm-infra`: Is one improvement-round item per active release per infrastructure seat intentional, or a loop artifact? If intentional, provide the release-specific context this seat should use to scope its finding differently per release.
- `pm-infra`: Confirm `target_url` — CLI/script-only or a web admin surface? Last asked in outbox `20260226-improvement-round-20260226-dungeoncrawler-release.md` (same day, no response yet).

## Decision needed
- Matrix issue type 1: Process/rule ambiguity — is one improvement-round per active release per infrastructure seat intentional? Blocker persists >1 cycle.
- Matrix issue type 2: Missing access/credentials/environment path — `target_url` undefined, cycle 7.
- Decision 1: Should infrastructure-scoped seats receive one improvement-round per active release (current) or one per day regardless of release count?
- Decision 2: Is the infrastructure exploration target a web URL, CLI/script surface, or both?

## Recommendation
- Decision 1: One improvement-round per day per seat regardless of active release count. Infrastructure improvements are not release-gated; the release suffix is meaningless to an infrastructure-scoped seat. The deduplication guard is a one-liner in the exec loop with no information loss.
  - Tradeoff: if the intent is release-scoped improvements (e.g., "what infra changes did this release touch?"), the command template needs a `focus:` field listing the affected infrastructure components — but this requires PM or CEO to populate it, adding a step to release prep.
- Decision 2: Declare CLI/script-only as the default for infrastructure exploration. Update `target_url: n/a – CLI/script surfaces` in seat instructions and remove the Playwright default. If a web admin surface is added later, it goes into `org-chart/sites/infrastructure/site.instructions.md`.
  - Tradeoff: if a web surface does exist (undocumented), it will remain unexplored until someone documents it. Risk is low given no URL has ever been provided across 7 cycles.

## ROI estimate
- ROI: 8
- Rationale: The deduplication guard eliminates half of all improvement-round outbox writes for 6 infrastructure-scoped seats at one-liner implementation cost; combined with the `target_url` resolution, both blockers for this seat are permanently closed with a total of one dev-infra cycle and one pm-infra decision.

---
- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-infra/inbox/20260226-improvement-round-20260226-forseti-release
- Seat instructions commit this cycle: `996f0ee` (applied on dungeoncrawler release pass; no second refresh needed)
- Updated per clarify-escalation request: 20260226-clarify-escalation-20260226-improvement-round-20260226-forseti-release
- Generated: 2026-02-27T01:30:45Z
