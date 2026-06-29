Issue: NPC context leak - Greta persists into the wrong room

Priority: High
Campaign: 70
Character: 267

Summary:
Old Greta Millward is speaking inside **The Sunken Passage** while continuing to reference mill-specific knowledge and denial states from **Garnet Mill Approach**. This reads like NPC presence/context leakage across a room transition.

Evidence:
- Character 267 current location row:
  - `dc_campaign_characters.id = 267`
  - `last_room_id = 28409134-3c7a-4743-8be8-d8c6158a0a9e`
  - room `28409134-3c7a-4743-8be8-d8c6158a0a9e = The Sunken Passage`
- Recent room-turn logs in `dc_room_turn_logs` show Greta speaking in **The Sunken Passage**:
  - `id 1319`: `Archive? There's no archive down here...`
  - `id 1328`: `...I'll be watching from the mill window...`
  - `id 1334`: `Tunnels? Sunken passage? ... I've run this mill for fifty years...`
- `dc_campaign_rooms` for `The Sunken Passage` currently shows empty `contents_data` creature/interactable arrays, which makes Greta's active presence even more suspicious.

Why this matters:
- Room-local NPC truth is unreliable after transitions.
- Narrative grounding is breaking: the player is in a subterranean passage, but Greta is still asserting the mill frame.
- This will keep reappearing anywhere room-description text accidentally matches an unrelated NPC name token.

Root cause:
- `RoomChatService::gatherRoomNpcsWithProfiles()` has a final narrative fallback that runs when a room has no placed or room-local NPCs.
- That fallback scans the room name/description and matches NPCs by the **first word** of their display name.
- Greta's canonical display name is **"Old Greta Millward"**, so the fallback keyword becomes `old`.
- `The Sunken Passage` description includes the phrase **"old moss"`**, so the fallback incorrectly treats Greta as a room NPC even though:
  - her entity placement remains `cae1e37c-e69b-4437-b66f-ca0e94fd2b13 = Garnet Mill Approach`
  - `loadRoomCampaignNpcRows()` for `28409134-3c7a-4743-8be8-d8c6158a0a9e = The Sunken Passage` returns no NPC rows
- Once Greta is admitted into the room NPC roster, direct-address and turn-plan resolution treat her as a valid local speaker, which is why both the GM layer and room interjection layer continue responding as Greta inside the wrong room.

Why the system behaved this way:
- The fallback was meant to recover NPC grounding for rooms generated from narrative context without formal entity placement.
- The matching heuristic is too weak: first-word substring matching allows generic adjectives like `old`, `mysterious`, or `young` to bind unrelated NPCs to a room.
- Because the fallback runs before turn ownership is resolved, the rest of the pipeline assumes the false-positive NPC is legitimate.

Suggested corrective direction:
1. Remove first-word substring matching as a room-NPC admission rule.
2. Limit fallback grounding to stronger identifiers only, such as exact entity refs, explicit full-name matches, or authored room-to-NPC links.
3. Require that room responders come from the active room's placed entities or room-local campaign rows; if none exist, prefer no NPC responder over a guessed one.
