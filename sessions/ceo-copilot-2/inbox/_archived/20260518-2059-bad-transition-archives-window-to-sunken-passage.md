Issue: Archive-side navigation generated the wrong destination

Priority: High
Campaign: 70
Character: 267

Summary:
From **Archives Approach**, the player appears to have taken an archive/window-adjacent navigation and been transitioned into **The Sunken Passage** via a generic destination label: **"Beyond the door"**. The destination does not match the room fiction.

Evidence:
- Room progression in `dc_campaign_rooms`:
  - `117`: `Garnet Mill Approach`
  - `118`: `Archives Approach`
  - `119`: `The Sunken Passage`
- The active transition log is in `dc_campaign_log`:
  - `id 416`
  - `message = canonical_action:navigate_to_location:executed`
  - `room_id = c9ab93d2-3897-4452-8759-227e0d26052a` (**Archives Approach**)
  - `action_name = Travel to Beyond the door`
  - `destination = Beyond the door`
  - `destination_description = A new area reached from Archives Approach by moving toward Beyond the door.`
- `Archives Approach` layout includes archive-adjacent world objects like:
  - `scriptorium_window`
  - `academy_gate`
  - `archive_signpost`
- The generated destination room became `28409134-3c7a-4743-8be8-d8c6158a0a9e = The Sunken Passage`, which does not line up with an archive/window expectation.

Why this matters:
- Navigation semantics are drifting from the actual room affordances.
- The player cannot trust that choosing an archive/window-style transition will preserve the scene's spatial fiction.
- Generic fallback labels like `Beyond the door` are masking incorrect route generation.

Root cause:
- `RoomChatService::handleNavigationActions()` extracts the action payload and sets:
  - `$destination = $details['destination'] ?? ...`
  - `$destination_desc = $details['destination_description'] ?? $destination`
- For this turn, the canonical action carried:
  - `destination = Beyond the door`
  - `destination_description = A new area reached from Archives Approach by moving toward Beyond the door.`
- The service then passes that generic text into `MapGeneratorService::generateSetting($campaign_id, $destination_desc, $origin_room_id, $narrative_context)`.
- That means setting generation is driven by fallback prose instead of the actual archive/window landmark semantics present in the origin room.
- The map generator therefore produced a plausible but ungrounded new room (`The Sunken Passage`) from generic transition text, not from the specific affordance the player actually chose.

Why the system behaved this way:
- Navigation action generation is allowed to emit low-fidelity destination labels.
- The downstream room generator treats those labels as authoritative worldbuilding input.
- There is no validation step ensuring that the generated destination remains semantically anchored to the clicked landmark or route source.

Suggested corrective direction:
1. Carry source-object provenance through navigation actions so generation knows the player chose an archive/window affordance, not just `Beyond the door`.
2. Stop using generic `destination_description` fallback text as primary generation input when richer landmark context exists.
3. Reject or regenerate navigation outputs whose generated room fiction materially diverges from the originating landmark/action context.
