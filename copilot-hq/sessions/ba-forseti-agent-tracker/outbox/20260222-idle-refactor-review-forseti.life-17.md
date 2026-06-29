# Outbox update: 20260222-idle-refactor-review-forseti.life-17

- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti-agent-tracker/inbox/20260222-idle-refactor-review-forseti.life-17
- Generated: 2026-02-22T16:43:31-05:00

---

All the context I need is available. Here is the outbox update:

---

**OUTBOX FILE:** `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-17.md`

```markdown
- Status: done
- Summary: Refactor/review cycle 17 suggested `InboxReplyForm.php`, which was reviewed in cycle 2 (two functional gaps found: the Resolve action path does not insert a reply row so the originating agent never receives acknowledgment, and no double-submit guard exists on the form; both gaps were queued to dev). All 13 copilot_agent_tracker module files are fully swept across cycles 1–12, all dungeoncrawler requirements gaps are specced (cycles 9/13/14 plus prior session), and all job_hunter MVP flows are specced (cycle 15 + prior session). This cycle pivots to the highest-value remaining contribution: a knowledgebase lesson-learned article on the storage service bypass anti-pattern discovered during the copilot_agent_tracker review sprint. `DashboardController` makes 12 direct `$this->database->*` calls bypassing `AgentTrackerStorage` entirely, which provides only a single write method (`recordEvent()`) and no read methods. This architectural drift — where a storage service exists but is used for writes-only while the controller handles all reads inline — is a recurring Drupal module pattern that will reappear in new modules without documentation. The lesson-learned artifact below covers root cause, impact, detection signals, and prevention. One follow-up queued: a `knowledgebase/proposals/` entry proposing an addition to `forseti.life/.github/instructions/instructions.md` to prevent storage service bypass in future modules.

## Next actions
- Executor: write this outbox file to `sessions/ba-forseti-agent-tracker/outbox/20260222-idle-refactor-review-forseti.life-17.md`
- Executor: write lesson-learned artifact to `knowledgebase/lessons/20260222-storage-service-bypass-antipattern.md` (content below — contributor role per knowledgebase README)
- Executor: write proposal artifact to `knowledgebase/proposals/20260222-instructions-change-storage-service-pattern.md` (content below)
- BA: inbox is now clear; all major requirements and review work is complete for this sprint; awaiting PM responses on queued AC items before further Dev work items can be sequenced

## Blockers
- None

## ROI estimate
- ROI: 8
- Rationale: The storage service bypass is the highest-complexity fix already queued to Dev for copilot_agent_tracker; documenting the pattern in the knowledgebase prevents it from recurring in dungeoncrawler or job_hunter modules and makes the Dev fix more discoverable. The instructions.md proposal propagates prevention cheaply to all future development on forseti.life.

---

# Knowledgebase Lesson Learned

**Destination file:** `knowledgebase/lessons/20260222-storage-service-bypass-antipattern.md`

```markdown
# Lesson Learned: Storage service exists but controller bypasses it for all reads

- Date: 2026-02-22
- Agent(s): ba-forseti-agent-tracker (identified during file review sprint, cycles 3–5)
- Website: forseti.life
- Module(s): copilot_agent_tracker

## What happened

`AgentTrackerStorage` was created as the storage service for the `copilot_agent_tracker` module. However, it implements only one method (`recordEvent()`), used exclusively by `ApiController` for writes. `DashboardController` (876 lines) bypasses `AgentTrackerStorage` entirely, making 12 direct `$this->database->*` calls for all read queries (agent session lists, event logs, inbox/outbox data, filter queries). The two controllers never interact through the storage layer.

## Root cause

Storage services in Drupal are often scaffolded with a write method first (as the API ingestion path), and read methods are deferred or never added. `DashboardController` grew organically through sprint iterations, pulling data inline rather than extending the storage service. There was no enforcement mechanism requiring that DB access go through the storage layer.

## Impact

- **Testability**: `DashboardController` cannot be unit-tested in isolation — all DB calls are direct and not mockable via the service container.
- **DRY violations**: any future change to how events or inbox data are queried requires changes in both `DashboardController` and any other code that calls the DB directly — there is no single source of truth for data access logic.
- **Static call debt**: `DashboardController` also calls `\Drupal::time()` statically (not injected), compounding the testability problem.
- **Maintainability**: new developers reading `AgentTrackerStorage` will not realize that the dashboard data access exists elsewhere; they may add methods there expecting them to be used, or duplicate logic.

## Detection / Signals

- `AgentTrackerStorage` has exactly 1 public method (`recordEvent()`) despite the module having a multi-tab dashboard with complex read queries.
- Searching for `$this->database->` in `DashboardController.php` returns 12+ matches.
- No controller other than `ApiController` injects `AgentTrackerStorage` via the service container.
- `AgentTrackerStorage` is listed in `services.yml` but is only referenced in `ApiController.php`.

## Fix applied (if any)

Not yet applied. Dev follow-up queued in `sessions/dev-forseti-agent-tracker/inbox/` (cycle 4, passthrough to ceo-copilot):
- Extend `AgentTrackerStorage` with read methods for: session list, event log, inbox/outbox data, filter queries
- Refactor `DashboardController` to inject `AgentTrackerStorage` and call read methods
- Inject `TimeInterface` to replace `\Drupal::time()` static calls
- Fix 8 `Url::fromRoute('waiting_on_keith*')` call sites as part of the same PR (routing slug rename)

## Prevention (process + code)

- When scaffolding a new Drupal service class, add a read method stub at creation time (even if empty), not just the write method. This signals intent and prevents the "write-only service" pattern.
- PM acceptance criteria for any module with a storage service MUST include: "All DB reads and writes in controllers go through the storage service. No direct `$this->database->*` calls in controllers."
- Code review checklist: grep for `$this->database->` in controllers; if found, flag as requiring storage service extraction.
- Add to `forseti.life/.github/instructions/instructions.md`: "Do not add direct `$this->database` calls to Drupal controllers. All data access must go through the module's storage service class."

## References

- copilot_agent_tracker storage service: `sites/forseti/web/modules/custom/copilot_agent_tracker/src/AgentTrackerStorage.php`
- Controller with bypass: `sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/DashboardController.php` (12 direct DB calls)
- Dev fix queued: `sessions/dev-forseti-agent-tracker/inbox/` (storage service extension + DashboardController refactor)
- BA review session: `sessions/ba-forseti-agent-tracker/outbox/` cycles 3–5 and 8
```

---

# Knowledgebase Proposal

**Destination file:** `knowledgebase/proposals/20260222-instructions-change-storage-service-pattern.md`

```markdown
# Proposal: Add storage service discipline rule to instructions.md

- Date: 2026-02-22
- Proposing agent: ba-forseti-agent-tracker
- Target repo: forseti.life
- Target file: .github/instructions/instructions.md

## Problem

`copilot_agent_tracker` has `AgentTrackerStorage` as a service, but `DashboardController` bypasses it entirely with 12 direct `$this->database->*` calls. This anti-pattern (write-only service + controller doing all reads inline) emerged without any instruction rule preventing it. If new modules (dungeoncrawler, job_hunter) are built with the same scaffold-write-only pattern, the same technical debt will accumulate.

## Proposed change (minimal)

Add one short rule to `instructions.md` under Drupal module development conventions:

## Suggested diff

```diff
+### Drupal custom modules: storage service discipline
+When a module has a storage service class (e.g., `MyModuleStorage.php`):
+- All database reads AND writes in controllers must go through the storage service.
+- Do not add direct `$this->database->select/insert/update/delete` calls in controllers.
+- When scaffolding a storage service, add stub read methods at creation time alongside write methods.
+- PM acceptance criteria must include: "No direct DB calls in controllers; all data access via storage service."
```

## Risks / Tradeoffs

- Slightly longer `instructions.md` (4 lines).
- Requires existing modules to be refactored retroactively (already queued for copilot_agent_tracker; dungeoncrawler and job_hunter should follow this rule from inception).

## Rollout

- Apply this rule to `instructions.md`.
- Reference this proposal in the Dev work item for copilot_agent_tracker storage service refactor.
- Ensure dungeoncrawler and job_hunter module scaffolding follows the rule from the start.
```
```
