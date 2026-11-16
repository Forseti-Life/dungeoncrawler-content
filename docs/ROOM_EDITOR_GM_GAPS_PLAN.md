# Room Editor GM gap implementation

## Scope and authority

Extend the existing Room Editor GM surface. Do not introduce a second command,
validation, definition, or storage implementation.

1. Draft lifecycle: register list/create/load/abandon tools. Add a draft-free GM
   endpoint for initial authoring. Create/load returns an explicit active draft
   handoff; the harness and browser rebind subsequent work to it. Abandon is
   revision-checked and preserves the aggregate and command log. Published
   drafts cannot be abandoned. No permanent deletion.
2. Single command: expose `apply_room_command` as an adapter to
   `apply_room_commands`, preserving RoomEditorService revision checks,
   idempotency and undo history. Natural-language mutations still require
   approval; task queues may not bypass that boundary.
3. History: expose a bounded, revision-ordered command-history query through
   RoomEditorService. Return command IDs, types, payloads, author and revision
   metadata, not inverse room snapshots.
4. Definition lifecycle: add reversible retirement/restoration through
   CanonicalDefinitionService with administrator authorization, reason,
   definition-version and lifecycle-revision checks. Keep payloads and
   published references intact. Exclude retired definitions from new catalog
   selection and generation; reject new placements/duplication. Inspection and
   editing of existing references remain available. Never reuse quarantine as
   voluntary retirement.

## Delivery and exit gates

- Register lifecycle and history tools in the live surface manifest; document
  argument and approval requirements for agent routing.
- Preserve draft-scope checks on every other editor surface.
- Propagate create/load handoffs to context, conversation and browser state;
  clear stale plans and undo stacks on a draft switch.
- Keep catalog filtering and lifecycle state in the definition authority.
- Add a scoped schema update; do not run unrelated pending database updates.
- Exercise real service/tool/harness paths in isolated rollback transactions:
  create/load/switch, one-shot/undo/history, revision conflicts, access denial,
  abandoned/published draft protection, dry runs, retirement/restoration,
  retired selection exclusion and existing-reference preservation.
- Run focused PHP/JavaScript validation and editor handoff checks only.

## Non-goals

No destructive definition/draft deletion, new simulator, campaign mutation,
automatic publication or removal of author approval.

## Delivered behavior

The Room GM manifest now exposes 32 tools (nine additions). Draft-free GET/POST
routes use the existing controller and harness. Draft handoffs also update
conversation continuity and the reload URL, and discard stale selections,
clipboard, history and plans. Older asynchronous context responses cannot
overwrite a newer draft. Room queues retain lifecycle order and stop for
approval (or a mutating dry run); remaining tasks are explicitly returned.

Retirement/restoration stores the latest transition's reason, actor, timestamp
and monotonic lifecycle revision; it is not a complete transition event log.
Catalog filtering is batched and catalog stamps include lifecycle changes.
Existing room/item reference reads remain available. Abandonment retains audit
data but does not provide a restore-abandoned-draft operation.

Only the lifecycle table installation hook (10229) was executed. No unrelated
pending database updates, content deletion, automation restart or publication.

## Verification evidence

- 53 isolated live-container checks passed, including controller/CSRF,
  conversation handoffs, queue approval/dry-run boundaries, permissions,
  conflicts, retirement/restoration, generation exclusion and reference reads.
  Fixture transactions rolled back; no fixture drafts remain.
- Seven JavaScript behavior checks passed for bootstrap requests/context,
  switch/reset/reload URLs, abandonment, stale responses/proposals and
  preservation of same-draft undo state.
- Fourteen selected existing PHPUnit cases passed (57 assertions). Existing
  PHPUnit deprecations and Node module-type warnings were not changed.
- Touched PHP/JavaScript syntax, YAML parsing and diff-whitespace checks passed.
  Live manifest reports 32 tools; authenticated bootstrap routes are installed.
- Correct-site caches rebuilt. Changes remain live on disk and uncommitted in
  the shared owning worktree; unrelated changes are preserved.
