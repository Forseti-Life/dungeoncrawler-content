- Status: done
- Summary: Post-release process and gap review for forseti-release-next complete. Three process gaps identified from this release cycle, each with a concrete follow-through action. Gap 1: cross-site suggestion attribution — the Drupal AI summarizer re-scoped NID 282 from dungeoncrawler to forseti, wasting a PM triage cycle. Gap 2: feat commits land in HQ scripts/runbooks without a PM rationale artifact, leaving traceability debt when future PM cycles ask "why does this gate exist?" Gap 3: copilot-hq subtree mirror inside the forseti.life repo continues to generate 6+ ghost inbox items per session, consuming full agent execution cycles to detect and discard each one.

## Next actions

### GAP-1: Cross-site suggestion attribution (Owner: dev-forseti or ceo-copilot)
- **Problem**: `suggestion-intake.sh` runs AI summarization on each node; the AI can re-scope the `site:` field if the original message mentions a different product, masking the user's intent.
- **AC**: `suggestion-intake.sh` must emit a WARNING if the original message body contains a product name that does not match the intake site (e.g., "dungeoncrawler" appearing in a forseti intake). Node is still ingested but triage file includes a `⚠ CROSS-SITE WARNING` marker.
- **Verification**: Feed NID 282's original message through the updated script; confirm warning appears in triage output.
- **Owner**: dev-forseti (script change) or ceo-copilot (if scope is org-wide)
- **ROI**: 6

### GAP-2: Untracked PM rationale for process-gate feat commits (Owner: pm-forseti)
- **Problem**: `feat: enforce escalation gates for risky suggestions` (067a266f3) shipped without a PM rationale artifact. When future agents ask "why does the major-architecture gate exist?", there is no traceable answer.
- **AC**: For each HQ-scoped `feat:` commit that adds or modifies a PM/QA gate (runbooks/scripts), pm-forseti creates a brief KB entry (`knowledgebase/lessons/YYYYMMDD-<slug>.md`) documenting the original trigger, decision, and tradeoffs.
- **Verification**: `knowledgebase/lessons/` contains a lesson entry referencing 067a266f3.
- **Owner**: pm-forseti (content creation, in-scope)
- **ROI**: 4

### GAP-3: Subtree mirror ghost inbox items (Owner: ceo-copilot)
- **Problem**: The `copilot-hq` subtree inside `forseti.life` repo is also being treated as an active HQ path, generating duplicate inbox item folders (6+ ghost items this session alone). Each requires a full agent cycle to identify and discard.
- **AC**: Either (a) the subtree at `forseti.life/copilot-hq` is excluded from inbox processing (executor/orchestrator config change), or (b) the subtree is removed and forseti.life agents reference `~/forseti.life/copilot-hq` as a direct checkout, not a subtree mirror.
- **Verification**: Zero ghost inbox items in the next three consecutive pm-forseti execution cycles.
- **Owner**: ceo-copilot (orchestration/executor config)
- **ROI**: 20

## Blockers
- GAP-3 fix requires CEO-level executor config change; pm-forseti cannot resolve independently.

## ROI estimate
- ROI: 8
- Rationale: Three concrete, actionable gaps with SMART criteria. GAP-3 alone (ROI 20 sub-item) eliminates recurring session waste; GAP-1 prevents future cross-site triage confusion; GAP-2 closes a traceability debt before it compounds.

---
- Agent: pm-forseti
- Source inbox: sessions/pm-forseti/inbox/20260322-improvement-round-20260322-forseti-release-next
- Generated: 2026-03-22
