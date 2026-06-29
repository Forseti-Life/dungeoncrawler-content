# Acceptance Criteria: dc-cr-tiered-room-perception

- Feature ID: dc-cr-tiered-room-perception
- Website: dungeoncrawler
- PM owner: pm-dungeoncrawler
- Status: ready for QA test plan

## Grooming decision

Decision: ship as a single exploration enhancement with authored sensory tiers rather than as a chat-only prompt tweak.

Rationale: the user ask is about the structure of room exploration itself, not just flavor text. Treating the feature as authored room metadata plus Perception resolution keeps it testable and prevents optional details from leaking through ad hoc prompt changes.

## Acceptance criteria

### AC-1: Baseline room descriptions always include sight and sound
- Every explorable room continues to provide a default description containing the baseline visual and auditory readout without any check.
- A player who never invokes optional probing still receives enough information to continue normal exploration.
- Verification: room description payloads and rendered text continue to include baseline content when optional sensory tiers are absent or locked.

### AC-2: Optional sensory layers are authored separately from the baseline description
- Rooms may define additional authored sensory layers for at least smell, touch/texture, and atmosphere/mood.
- Each optional layer has an explicit Perception threshold or equivalent authored difficulty value.
- Unauthored optional layers are skipped cleanly and do not create broken UI affordances.
- Verification: room data can represent baseline + optional tier metadata without leaking locked text.

### AC-3: Perception checks unlock deeper sensory detail with escalating difficulty
- The easiest optional sensory tier has a lower threshold than the next tier, and so on.
- A successful Perception resolution reveals the matching sensory detail and any already-earned lower-tier detail for that room state.
- A failed higher-tier attempt does not retroactively hide previously revealed lower-tier details.
- Verification: deterministic tests can prove that higher thresholds require higher Perception outcomes than earlier tiers.

### AC-4: Revealed sensory detail is room-scoped and persists for the active room state
- Once a sensory tier is successfully revealed for the active room state, it remains visible on subsequent renders of that room state without forcing the player to re-roll immediately.
- Revealed detail is scoped to the room state and does not bleed into unrelated rooms.
- Verification: reload or repeated room render returns previously revealed room-scoped detail only for the correct room.

### AC-5: Locked detail does not leak on failure
- If a player fails to meet the threshold for an optional sensory tier, the locked detail text is not exposed in the response payload or UI.
- The failure response is explicit enough for the player to understand that no deeper detail was found.
- Verification: failed checks do not include the authored hidden text in returned payloads, logs, or rendered markup.

### AC-6: Exploration UI supports requesting optional sensory probes
- The room exploration shell exposes a clear way to probe for deeper sensory detail without replacing the baseline room description workflow.
- The client can request a specific supported sensory tier for the active room and current character.
- Successful reveals are displayed distinctly from the baseline description so players can tell they unlocked extra information.
- Verification: functional or JS coverage proves the shell can request and render optional sensory layers.

### AC-7: Existing exploration-mode behavior remains compatible
- Rooms without optional sensory tiers continue to behave exactly like current room descriptions.
- Existing exploration and room navigation flows remain usable if no Perception probe is attempted.
- The feature does not introduce mandatory extra checks for routine room traversal.
- Verification: regression coverage demonstrates unchanged behavior for rooms that do not opt into the new authored sensory tiers.

## Security acceptance criteria

- No new anonymous gameplay write surface is introduced.
- Character/session ownership is revalidated server-side on every sensory probe request.
- Failure paths are explicit; no silent fallback should reveal undiscovered content.
- Operational logging excludes hidden authored text and player-entered freeform content.

## KB reference

- None found in `knowledgebase/` for tiered room Perception in Dungeoncrawler exploration.
