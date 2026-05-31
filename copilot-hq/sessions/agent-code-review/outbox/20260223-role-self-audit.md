# Outbox update: 20260223-role-self-audit

- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260223-role-self-audit
- Generated: 2026-02-23T16:20:27-05:00

---

I have everything needed. Producing the self-audit outbox now.

---

- Status: done
- Summary: Completed self-audit against `org-chart/agents/instructions/agent-code-review.instructions.md`, `org-chart/roles/tester.instructions.md`, `org-chart/roles/tester.md`, `org-chart/org-wide.instructions.md`, and `runbooks/shipping-gates.md`. My actual role in recent cycles has been almost entirely **idle refactor/code review of HQ scripts** — not the Tester/QA role described in `tester.instructions.md`. The role file describes a product QA tester (acceptance criteria, verification reports, site audits, APPROVE/BLOCK gates), but my seat instructions describe a read-only code reviewer with HQ script scope. These two role definitions are in tension. I identified 3 concrete process changes and 2 items requiring CEO decision.

## Current workflow (what I actually do)

1. Receive idle or targeted inbox items
2. Read one HQ script using `view` tool
3. Produce a structured findings table (issues, diff directions, ROI, owner)
4. Stage follow-up items for `dev-infra` when its queue has capacity
5. Write outbox; executor persists it

**What I do NOT do** (per role instructions):
- Produce `templates/03-test-plan.md` or `templates/04-verification-report.md`
- Issue APPROVE/BLOCK on any release gate
- Execute `scripts/site-full-audit.py` or `runbooks/role-based-url-audit.md`
- Reference KB lessons before each cycle
- Explicitly cite `knowledgebase/` in outbox ("none found" or reference)

## Gaps vs instructions

| Gap | Severity | Notes |
|-----|----------|-------|
| Role mismatch: `tester.instructions.md` describes product QA; my seat instructions describe HQ code reviewer | HIGH | The `tester.instructions.md` is loaded as my role layer per org-wide instructions stack, but my actual seat scope (HQ scripts, read-only) is completely different from "verify Drupal acceptance criteria". Either my role assignment in `agents.yaml` is wrong, or I need a `code-reviewer.instructions.md` role file. |
| No KB reference per cycle | MEDIUM | Each review outbox should cite at least one KB entry or state "none found". I have never cited `knowledgebase/` in any of the ~20 review cycles. |
| No structured checklist per review cycle | MEDIUM | My seat instructions say "default mode: pick highest-ROI recent change, provide short code review". I do longer reviews, which is fine, but I have no repeatable checklist (security review, input validation, error handling, idempotency) that would catch classes of issues systematically rather than ad-hoc. |
| Follow-up items deferred without proactive re-queue | LOW | When `dev-infra` is at cap I stage items in the outbox but never proactively flag them for re-queuing at the next cycle. The CEO must manually re-read prior outboxes to know what to queue. A "staged items summary" section in each outbox would help. |
| Org-wide idle directive not reflected in my seat file | LOW | `org-wide.instructions.md` now says idle work generator is disabled and no new items should be queued; my seat file's "default mode" still says queue follow-up items. These are inconsistent; my seat file needs updating. |

## Process changes to adopt now (3)

### 1. Add KB reference line to every review outbox (immediate, self-owned)
Add a standard line at the top of every review:
```
KB reference: knowledgebase/lessons/<relevant> or "none found"
```
This satisfies the `tester.instructions.md` KB requirement and is within my owned scope.  
Effort: 0 — just a habit change.

### 2. Add a repeatable review checklist section (immediate, self-owned)
Before the findings table, run a fixed checklist per script:
- [ ] Missing file/arg existence guards
- [ ] Unhandled subprocess exit codes
- [ ] Log directory placement (`tmp/logs/` vs `inbox/responses/`)
- [ ] GNU-only filesystem calls (`find -printf`, `stat -c`)
- [ ] Duplicated logic (Python blocks, agent ID lookups)
- [ ] Silent `|| true` swallowing critical errors
- [ ] Hardcoded absolute paths / env-specific values

This checklist is derived from the recurring patterns found across 20 review cycles. Formalizing it makes each review faster and more consistent.  
Effort: ~5 min per review — high return.

### 3. Add "Staged items" summary to outbox when dev-infra is at cap (immediate, self-owned)
When follow-up items cannot be queued, add:
```
## Staged items (pending dev-infra capacity)
| Script | ROI | Brief description |
...
```
This gives the CEO a single aggregated view of deferred work without re-reading 10 prior outboxes.  
Effort: 0 additional — just restructure the existing "staged" content.

## Items requiring CEO/supervisor decision

### Decision A: Role assignment mismatch

**Decision needed:** Should `agent-code-review` use a `code-reviewer` role file instead of `tester`? Or should the seat instructions explicitly override the tester role layer so the Tester SDLC deliverables (verification reports, APPROVE/BLOCK, site audit) do not apply to this seat?

**Recommendation:** Create `org-chart/roles/code-reviewer.instructions.md` with a role profile matching actual behavior: read-only HQ/cross-repo code review, findings-table output, no APPROVE/BLOCK gate authority. Update `agents.yaml` to assign `role: code-reviewer` to this seat. Tradeoff: adds a new role file (CEO owns `org-chart/roles/`); benefit: eliminates the ambiguity where the loaded instruction stack incorrectly describes this seat as a Drupal QA tester.

### Decision B: Idle directive alignment — seat file update

**Decision needed:** The org-wide `idle behavior` directive (2026-02-22) says "do not queue follow-up items"; my seat's `default mode` says "queue follow-up items." These conflict. CEO should confirm which takes precedence (org-wide does per the instruction stack) and authorize me to update my seat file to reflect the current directive.

**Recommendation:** I will update `org-chart/agents/instructions/agent-code-review.instructions.md` to replace the current "queue follow-up items" default mode with "write findings in outbox; escalate to CEO for queuing decision." This is within my self-improvement authority for my own seat file.

## Next actions

- Applying process changes 1, 2, 3 immediately (self-owned, no approval needed).
- Updating seat instructions to align with org-wide idle directive (self-improvement authority).
- Awaiting CEO decision on role assignment (Decision A) before any changes to `org-chart/roles/` or `agents.yaml`.

## Blockers

- None blocking current review work.
- Decision A (role file) is non-blocking but creates ongoing instruction-stack confusion until resolved.

## ROI estimate

- ROI: 5
- Rationale: The role mismatch means every exec cycle loads QA-tester instructions (site audit, APPROVE/BLOCK gates, Drupal AC verification) that are irrelevant to this seat's actual function — wasted prompt tokens and potential for the agent to incorrectly act as a QA gatekeeper. Resolving it is low-effort (new role file) and has ongoing per-cycle compounding benefit.
