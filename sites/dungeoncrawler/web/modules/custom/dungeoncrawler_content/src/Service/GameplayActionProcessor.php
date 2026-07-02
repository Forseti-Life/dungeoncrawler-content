<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Psr\Log\LoggerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Processes gameplay actions from AI responses and applies state mutations.
 *
 * When a player describes an action in chat (e.g., "I cast Light on my staff"),
 * the AI returns structured mechanical data alongside narrative text. This
 * service parses that data and applies state changes to both dungeon_data
 * (room state, entities, effects) and character_data (HP, spell slots, conditions).
 */
class GameplayActionProcessor {

  /**
   * Currency denomination values in copper pieces.
   */
  protected const CURRENCY_CP_VALUES = [
    'cp' => 1,
    'sp' => 10,
    'gp' => 100,
    'pp' => 1000,
  ];

  protected Connection $database;
  protected LoggerInterface $logger;
  protected InventoryManagementService $inventoryManagementService;
  protected CanonicalActionRegistryService $canonicalActionRegistry;

  /**
   * Constructor.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    InventoryManagementService $inventory_management_service,
    CanonicalActionRegistryService $canonical_action_registry
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_gameplay');
    $this->inventoryManagementService = $inventory_management_service;
    $this->canonicalActionRegistry = $canonical_action_registry;
  }

  /**
   * Build the enhanced system prompt that includes character abilities.
   *
   * Provides the AI with full mechanical context so it can declare structured
   * actions alongside narrative responses.
   *
   * @param string $base_system_prompt
   *   The base GM system prompt from PromptManager.
   * @param array $character_data
   *   Full character_data JSON from dc_campaign_characters.
   * @param array $room_meta
   *   Room metadata (name, description, entities, state).
   * @param array $room_inventory
   *   Room inventory (NPCs, items, obstacles, etc.).
   * @param array $dungeon_data
   *   Full dungeon_data payload (for location awareness context).
   * @param int|string|null $room_index
   *   Current room index in dungeon_data['rooms'].
   *
   * @return string
   *   Enhanced system prompt with mechanical instructions.
   */
  public function buildEnhancedSystemPrompt(string $base_system_prompt, array $character_data, array $room_meta, array $room_inventory = [], array $dungeon_data = [], $room_index = NULL): string {
    $char_name = $character_data['name'] ?? 'the character';
    $char_class = $character_data['class'] ?? 'unknown';
    $char_level = $character_data['level'] ?? 1;
    $ancestry = $character_data['ancestry'] ?? 'unknown';
    $heritage = $character_data['heritage'] ?? '';
    $background = $character_data['background'] ?? '';

    // Abilities
    $abilities = $character_data['abilities'] ?? [];
    $ability_str = [];
    foreach ($abilities as $key => $val) {
      $mod = floor(($val - 10) / 2);
      $sign = $mod >= 0 ? '+' : '';
      $ability_str[] = strtoupper(substr($key, 0, 3)) . " {$val} ({$sign}{$mod})";
    }

    // HP
    $hp = $character_data['hit_points'] ?? [];
    $hp_current = $hp['current'] ?? $hp['max'] ?? 0;
    $hp_max = $hp['max'] ?? 0;
    $hero_points = $character_data['hero_points'] ?? 0;

    // Saves
    $saves = $character_data['saves'] ?? [];
    $save_str = [];
    foreach ($saves as $key => $val) {
      $save_str[] = ucfirst($key) . " +{$val}";
    }

    // Perception
    $perception = $character_data['perception'] ?? 0;

    // Skills
    $trained_skills = $character_data['trained_skills'] ?? [];
    $skills_str = implode(', ', $trained_skills);

    // Feats
    $feats = $character_data['feats'] ?? [];
    $feat_lines = [];
    foreach ($feats as $feat) {
      if (!is_array($feat)) {
        $feat_lines[] = "  - {$feat}";
        continue;
      }
      $fname = $feat['name'] ?? 'Unknown';
      $ftype = $feat['type'] ?? 'general';
      $fdesc = $feat['description'] ?? '';
      if (is_array($fdesc)) {
        $fdesc = implode(' ', array_filter($fdesc));
      }
      $feat_lines[] = "  - {$fname} ({$ftype})" . ($fdesc ? ": " . substr($fdesc, 0, 100) : '');
    }

    // Spells
    $spells = $character_data['spells'] ?? [];
    $tradition = $spells['tradition'] ?? 'arcane';
    $casting_ability = $spells['casting_ability'] ?? 'intelligence';
    $spell_dc = $spells['spell_dc'] ?? 0;
    $spell_attack = $spells['spell_attack'] ?? 0;
    $slots = $spells['slots'] ?? [];
    $cantrips = $spells['cantrips'] ?? [];
    $spellbook = $spells['spellbook'] ?? [];
    $slots_used = $spells['slots_used'] ?? [];

    $cantrip_names = array_map(function($s) {
      return is_array($s) ? ($s['name'] ?? $s) : $s;
    }, $cantrips);

    // 1st level spells may be in 'first_level' or 'spellbook' key
    $first_level_spells = $spells['first_level'] ?? $spells['spellbook'] ?? [];
    $spell_1_names = [];
    foreach ($first_level_spells as $spell) {
      $spell_1_names[] = is_array($spell) ? ($spell['name'] ?? $spell) : $spell;
    }

    $slot_info = [];
    // Map named keys to display format
    $slot_display_map = ['cantrips' => 'Cantrips', 'first' => '1st', 'second' => '2nd', 'third' => '3rd'];
    $slots_used = $spells['slots_used'] ?? [];
    foreach ($slots as $level_key => $count) {
      if ($level_key === 'cantrips') continue; // cantrips are at-will
      $display = $slot_display_map[$level_key] ?? $level_key;
      $used = $slots_used[$level_key] ?? 0;
      $remaining = $count - $used;
      $slot_info[] = "{$display} level: {$remaining}/{$count} remaining";
    }

    // Inventory
    $inventory = $character_data['inventory'] ?? [];
    $inv_lines = [];
    foreach ($this->extractInventoryItems($character_data) as $item) {
      $iname = $item['name'] ?? 'Unknown';
      $qty = max(1, (int) ($item['quantity'] ?? 1));
      $instance_id = $item['item_instance_id'] ?? '';
      $ref_suffix = $instance_id ? " {id: {$instance_id}}" : '';
      $inv_lines[] = $qty > 1 ? "{$iname} (x{$qty}){$ref_suffix}" : "{$iname}{$ref_suffix}";
    }

    $currency = $this->extractCurrency($character_data);
    $currency_parts = [];
    foreach (['pp', 'gp', 'sp', 'cp'] as $denomination) {
      $amount = (float) ($currency[$denomination] ?? 0);
      if ($amount > 0) {
        $currency_parts[] = rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.') . ' ' . $denomination;
      }
    }

    // Conditions
    $conditions = $character_data['conditions'] ?? [];

    // Room context
    $room_name = $room_meta['name'] ?? 'Unknown Room';
    $room_desc = $room_meta['description'] ?? '';
    $room_lighting = $room_meta['lighting'] ?? 'normal';
    if (is_array($room_lighting)) {
      $room_lighting = $room_lighting['level'] ?? 'normal';
    }
    $room_terrain = $room_meta['terrain'] ?? 'normal';
    if (is_array($room_terrain)) {
      $room_terrain = $room_terrain['type'] ?? 'normal';
    }

    // Room inventory from caller (entities, items, environment, effects).
    $room_inventory_data = $room_inventory;

    $enhanced = $base_system_prompt . "\n\n";
    $enhanced .= "=== ACTIVE CHARACTER ===\n";
    $enhanced .= "Name: {$char_name} | Level {$char_level} {$ancestry}" . ($heritage ? " ({$heritage})" : '') . " {$char_class}\n";
    if ($background) {
      $enhanced .= "Background: {$background}\n";
    }
    $enhanced .= "HP: {$hp_current}/{$hp_max} | Hero Points: {$hero_points}\n";
    $enhanced .= "Abilities: " . implode(', ', $ability_str) . "\n";
    $enhanced .= "Saves: " . implode(', ', $save_str) . " | Perception: +{$perception}\n";
    $enhanced .= "Trained Skills: {$skills_str}\n";
    $enhanced .= "\nFeats:\n" . implode("\n", $feat_lines) . "\n";
    $enhanced .= "\nSpellcasting ({$tradition}, DC {$spell_dc}, attack +{$spell_attack}):\n";
    $enhanced .= "  Cantrips (at will): " . implode(', ', $cantrip_names) . "\n";
    if (!empty($slot_info)) {
      $enhanced .= "  Spell Slots: " . implode('; ', $slot_info) . "\n";
    }
    if (!empty($spell_1_names)) {
      $enhanced .= "  Spellbook (1st): " . implode(', ', $spell_1_names) . "\n";
    }
    $enhanced .= "\nInventory: " . (!empty($inv_lines) ? implode(', ', $inv_lines) : 'None listed') . "\n";
    $enhanced .= "Currency: " . (!empty($currency_parts) ? implode(', ', $currency_parts) : '0 gp') . "\n";
    if (!empty($conditions)) {
      $enhanced .= "Conditions: " . implode(', ', $conditions) . "\n";
    }

    // Personality and roleplay style — drives GM narration of this PC's turn.
    $personality = $character_data['personality'] ?? '';
    $roleplay_style = $character_data['roleplay_style'] ?? 'balanced';
    $roleplay_descriptions = [
      'talker'   => 'leads with words — negotiates, narrates their actions aloud, and speaks on most turns',
      'balanced' => 'mixes dialogue and action naturally, reading the room each turn',
      'doer'     => 'acts first and talks later — brief, purposeful speech; actions speak louder than words',
      'observer' => 'watches and listens; speaks rarely but deliberately, acts on gathered information',
    ];
    $roleplay_desc = $roleplay_descriptions[$roleplay_style] ?? $roleplay_descriptions['balanced'];
    $enhanced .= "\n=== CHARACTER VOICE & ROLEPLAY ===\n";
    if ($personality) {
      $enhanced .= "Personality: {$personality}\n";
    }
    $enhanced .= "Roleplay style: " . ucfirst($roleplay_style) . " — {$char_name} {$roleplay_desc}.\n";
    $enhanced .= <<<PARTYRULES

PARTY TURN RULES (apply whenever multiple PCs are present):
- Only ONE PC speaks or acts per GM turn. The active PC is {$char_name}.
- Other party members may react silently (nods, gestures) but do NOT speak for them.
- After resolving {$char_name}'s turn, prompt the next PC naturally if appropriate.
- {$char_name}'s roleplay style ({$roleplay_style}) should be reflected in your narration:
  "{$roleplay_desc}."
PARTYRULES;

    $enhanced .= "\n=== CURRENT ROOM ===\n";
    $enhanced .= "Room: {$room_name}\n";
    if ($room_desc) {
      $enhanced .= "Description: {$room_desc}\n";
    }
    $enhanced .= "Lighting: {$room_lighting} | Terrain: {$room_terrain}\n";

    // Environment tags.
    $env_tags = $room_inventory_data['environment_tags'] ?? [];
    if (!empty($env_tags)) {
      $enhanced .= "Environment: " . implode(', ', $env_tags) . "\n";
    }

    // NPCs present.
    $npcs = $room_inventory_data['npcs'] ?? [];
    $is_fallback_room = !empty($room_inventory_data['_fallback_room']);
    if (!empty($npcs)) {
      $enhanced .= "\nNPCs present:\n";
      foreach ($npcs as $npc) {
        $npc_line = "  - {$npc['name']}";
        if (!empty($npc['type'])) {
          $npc_line .= " ({$npc['type']})";
        }
        if (!empty($npc['role'])) {
          $npc_line .= " [" . $npc['role'] . "]";
        }
        if (!empty($npc['hp_status'])) {
          $npc_line .= " - HP: " . $npc['hp_status'];
        }
        if (!empty($npc['description'])) {
          $npc_line .= " — " . substr($npc['description'], 0, 120);
        }
        if (!empty($npc['entity_instance_id'])) {
          $npc_line .= " {entity_instance_id: {$npc['entity_instance_id']}}";
        }
        if (!empty($npc['owner_id'])) {
          $npc_line .= " {owner_id: {$npc['owner_id']}}";
        }
        $enhanced .= $npc_line . "\n";
      }
      if ($is_fallback_room) {
        $enhanced .= "FALLBACK ROOM — the system could not resolve the party's current location."
          . " The NPC listed above is from the campaign's starting location and is present as"
          . " a last-resort anchor. This NPC knows something is wrong — the party should not be"
          . " here, or the dungeon state has become inconsistent. Critically: this NPC has seen"
          . " this happen before. It is NOT the party's first time ending up here under strange"
          . " circumstances, and the NPC will treat them accordingly — with the weary familiarity"
          . " of someone who has greeted unexpected, confused visitors more than once. The NPC"
          . " should, through body language, tone, and choice of words, make it clear that"
          . " something is amiss and that they recognise this situation — without breaking the"
          . " fourth wall or mentioning game systems. Keep it fully in character.\n";
      }
      else {
        $enhanced .= "STRICT NPC RULE: ONLY the NPCs listed above are physically present in this room."
          . " Do NOT introduce, narrate, or speak as any NPC from a previous room or location."
          . " If a character from another location appears in your session memory, they are NOT here.\n";
      }
    }
    else {
      $enhanced .= "\nNo NPCs are present in this room."
        . " STRICT: Do NOT invent or introduce any NPC characters."
        . " The room is empty of named beings unless they arrive narratively in this session.\n";
    }

    // Obstacles / furniture / environmental objects.
    $obstacles = $room_inventory_data['obstacles'] ?? [];
    if (!empty($obstacles)) {
      $enhanced .= "\nObstacles/objects:\n";
      foreach ($obstacles as $obj) {
        $obj_line = "  - {$obj['name']}";
        if (!empty($obj['description'])) {
          $obj_line .= " — " . substr($obj['description'], 0, 100);
        }
        if (!empty($obj['impassable'])) {
          $obj_line .= " [impassable]";
        }
        $enhanced .= $obj_line . "\n";
      }
    }

    // Hazards.
    $hazards = $room_inventory_data['hazards'] ?? [];
    if (!empty($hazards)) {
      $enhanced .= "\nHazards:\n";
      foreach ($hazards as $hazard) {
        $h_line = "  - {$hazard['name']}";
        if (!empty($hazard['description'])) {
          $h_line .= " — " . substr($hazard['description'], 0, 100);
        }
        if (!empty($hazard['detected'])) {
          $h_line .= " [detected]";
        }
        $enhanced .= $h_line . "\n";
      }
    }

    // Detected traps.
    $traps = $room_inventory_data['traps'] ?? [];
    if (!empty($traps)) {
      $enhanced .= "\nDetected traps:\n";
      foreach ($traps as $trap) {
        $t_line = "  - {$trap['name']}";
        if (!empty($trap['description'])) {
          $t_line .= " — " . substr($trap['description'], 0, 100);
        }
        $enhanced .= $t_line . "\n";
      }
    }

    // Items on the ground / loot.
    $ground_items = $room_inventory_data['items'] ?? [];
    if (!empty($ground_items)) {
      $enhanced .= "\nItems in room:\n";
      foreach ($ground_items as $item) {
        $i_line = "  - {$item['name']}";
        if (!empty($item['quantity']) && $item['quantity'] > 1) {
          $i_line .= " (x{$item['quantity']})";
        }
        if (!empty($item['item_instance_id'])) {
          $i_line .= " {id: {$item['item_instance_id']}}";
        }
        if (!empty($item['description'])) {
          $i_line .= " — " . substr($item['description'], 0, 80);
        }
        $enhanced .= $i_line . "\n";
      }
    }

    $storage_owners = $room_inventory_data['storage_owners'] ?? [];
    if (!empty($storage_owners)) {
      $enhanced .= "\nStorage owners in scope:\n";
      foreach ($storage_owners as $owner) {
        $owner_name = $owner['name'] ?? ($owner['owner_type'] ?? 'storage');
        $owner_type = $owner['owner_type'] ?? 'unknown';
        $owner_id = $owner['owner_id'] ?? '';
        if ($owner_id === '') {
          continue;
        }
        $enhanced .= "  - {$owner_name} ({$owner_type}) {owner_id: {$owner_id}}\n";
      }
    }

    // Active room effects (spells, environmental hazards, etc.).
    $active_effects = $room_inventory_data['active_effects'] ?? [];
    if (!empty($active_effects)) {
      $enhanced .= "\nActive effects:\n";
      foreach ($active_effects as $effect) {
        $eff_name = is_array($effect) ? ($effect['name'] ?? 'Unknown') : $effect;
        $eff_desc = is_array($effect) ? ($effect['description'] ?? '') : '';
        $eff_line = "  - {$eff_name}";
        if ($eff_desc) {
          $eff_line .= " — " . substr($eff_desc, 0, 80);
        }
        $enhanced .= $eff_line . "\n";
      }
    }

    // Location awareness context (exits, world map, history).
    if (!empty($dungeon_data)) {
      $enhanced .= $this->buildLocationContext($dungeon_data, $room_meta, $room_index);
    }

    $enhanced .= "\n" . $this->canonicalActionRegistry->buildPromptGuidance();

    $enhanced .= <<<'GROUNDING'

=== ENTITY GROUNDING RULES ===
You must ONLY reference NPCs, creatures, items, obstacles, hazards, and traps
that are listed in the CURRENT ROOM section above. Do NOT invent new characters,
creatures, or objects that are not listed. Use their exact names as given.
If the room inventory lists "Eldric (tavern_keeper)" refer to him as Eldric —
do NOT substitute a generic "dwarf barkeep" or any other made-up NPC.
If no NPCs are listed, the room is empty of characters — narrate accordingly.
If the player asks about someone not in the room, inform them that person is
not present. You may describe atmospheric details (sounds, smells, lighting,
mood) freely, but every named entity must match the provided inventory exactly.
GROUNDING;

    $enhanced .= <<<'INSTRUCTIONS'

=== MECHANICAL ACTION INSTRUCTIONS ===
When the player describes an action that has mechanical effects, you MUST include a JSON block at the END of your narrative response, wrapped in ```json and ``` markers.

The JSON block should declare ALL mechanical state changes that result from the action. Format:

```json
{
  "actions": [
    {
      "type": "cast_spell|use_skill|use_feat|strike|stride|interact|recall_knowledge|perception_check|save|navigate_to_location|transfer_inventory|quest_turn_in|combat_initiation|other",
      "name": "Specific action name (e.g., 'Cast Light', 'Recall Knowledge: Arcana')",
      "details": {
        "spell_name": "light",
        "spell_level": "cantrip",
        "skill_used": "arcana",
        "feat_used": "experienced_smuggler",
        "roll_needed": "d20+5",
        "dc": 15,
        "result_description": "Brief mechanical outcome"
      },
      "state_changes": {
        "character": {
          "hp_delta": 0,
          "temp_hp": 0,
          "spell_slot_used": null,
          "currency_delta": {"cp": 0, "sp": 0, "gp": 0, "pp": 0},
          "conditions_add": [],
          "conditions_remove": [],
          "hero_points_delta": 0,
          "inventory_add": [],
          "inventory_remove": []
        },
        "room": {
          "lighting_change": null,
          "effects_add": [{"name": "light_spell", "hex": "3,2", "duration": "until_dismissed", "description": "Magical light illuminates the area"}],
          "effects_remove": [],
          "entities_add": [],
          "entities_modify": [],
          "terrain_change": null
        }
      }
    }
  ],
  "dice_rolls": [
    {"type": "d20", "modifier": 5, "result": 18, "total": 23, "purpose": "Arcana check"}
  ]
}
```

Inventory transfer format:
```json
{
  "actions": [
    {
      "type": "transfer_inventory",
      "name": "Give Eldric the bottle",
      "details": {
        "transfer": {
          "source_owner_type": "character",
          "source_owner_id": "ACTING_CHARACTER",
          "dest_owner_type": "container|character|room",
          "dest_owner_id": "target storage owner id",
          "item_instance_id": "exact item instance id from prompt context",
          "quantity": 1,
          "dest_location_type": "carried|container|room"
        },
        "result_description": "Brief transfer outcome"
      },
      "state_changes": { "character": {}, "room": {} }
    }
  ]
}
```

Quest turn-in format:
```json
{
  "actions": [
    {
      "type": "quest_turn_in",
      "name": "Turn in the herbalist bundle",
      "details": {
        "quest": {
          "objective_type": "deliver",
          "quest_id": "optional quest id if known",
          "objective_id": "optional objective id if known",
          "npc_ref": "target npc owner_id if known",
          "item_ref": "quest item name or item id",
          "quantity": 1,
          "claim_rewards": false
        },
        "result_description": "Brief quest progress outcome"
      },
      "state_changes": { "character": {}, "room": {} }
    }
  ]
}
```

Combat initiation format:
```json
{
  "actions": [
    {
      "type": "combat_initiation",
      "name": "Draw steel and begin combat",
      "details": {
        "combat": {
          "reason": "Hostilities break out with the bandits",
          "enemy_entity_ids": ["exact enemy entity ids if known"],
          "enemy_names": ["exact enemy names if ids are unavailable"],
          "target_entity_id": "single enemy entity id if only one matters",
          "target_name": "single enemy name if ids are unavailable"
        },
        "result_description": "Brief combat start outcome"
      },
      "state_changes": { "character": {}, "room": {} }
    }
  ]
}
```

Rules for mechanical responses:
1. ALWAYS include the JSON block when the player attempts any mechanical action (spell, skill check, attack, feat usage, exploration action).
2. For spells that consume a spell slot (non-cantrips), set spell_slot_used to the level number (e.g., 1 for 1st-level).
3. For cantrips, spell_slot_used should be null.
4. Roll dice for the player when checks are needed. Use the character's actual modifiers.
5. If no mechanical action occurs (pure roleplay/conversation), do NOT include a JSON block.
6. Keep narrative text BEFORE the JSON block. The JSON block must be the LAST thing in your response.
7. Respect the character's current resources - don't let them cast if they have no slots.
8. Before narrating a payment, gift, trade, use, or consumption of an item, verify the acting character actually has that currency or item in the ACTIVE CHARACTER inventory/currency above.
9. Use currency_delta for coin changes. Negative values spend currency, positive values gain currency.
10. If the character lacks the required item/currency/spell slot, narrate the failed attempt honestly and do NOT declare a successful transfer, payment, or use.
11. Track conditions properly (frightened reduces by 1 each turn, etc.).
12. For moving an item between characters, containers, or rooms, use the action type "transfer_inventory" instead of manually pairing inventory_add and inventory_remove.
13. A transfer_inventory action must reference the exact item_instance_id and the exact source/destination owner ids from the authoritative prompt context.
14. Do not invent storage owner ids, item instance ids, or container ids. If the source/destination cannot be identified exactly, narrate uncertainty instead of claiming a completed transfer.
15. For delivering or turning in a quest item/objective, use "quest_turn_in" with the quest payload instead of only describing it narratively.
16. For starting a fight, use "combat_initiation" instead of only narrating that combat begins.
17. If the player says or clearly implies an aggressive action against a present NPC or creature (for example, "I attack Gribbles"), emit a "combat_initiation" action immediately.
18. When a target NPC or creature is listed in CURRENT ROOM, prefer that target's exact entity_instance_id for combat_initiation.target_entity_id. If only the exact name is available, use target_name/enemy_names with the exact grounded room name.
19. Do not invent quest ids, objective ids, npc ids, names, or enemy entity ids. If they are unclear, narrate uncertainty rather than claiming the action completed.

=== NAVIGATION / TRAVEL ===
When the player decides to LEAVE the current location and travel to a new place
(e.g., "I leave the tavern", "Let's head to the market", "I go outside"),
use the action type "navigate_to_location". This triggers the map generator
to create the new setting.

Example navigation JSON:
```json
{
  "actions": [
    {
      "type": "navigate_to_location",
      "name": "Travel to the marketplace",
      "details": {
        "destination": "The town marketplace",
        "destination_description": "A bustling open-air market in the town center",
        "travel_type": "walk",
        "estimated_distance": "short"
      },
      "state_changes": { "character": {}, "room": {} }
    }
  ]
}
```

Rules for navigation:
- "destination" is the canonical name of where the player is going.
- "destination_description" is a brief description to seed the new setting.
- "travel_type" is one of: walk, ride, teleport, climb, swim, fly.
- "estimated_distance" is one of: adjacent, short, medium, long.
- Write a vivid transition narrative (the player leaving, the journey, arrival).
- Do NOT navigate if the player is in combat or has unresolved mechanical events.
- If the destination is unclear, ask the player to clarify rather than guessing.

INSTRUCTIONS;

    return $enhanced;
  }

  /**
   * Build location awareness context for the GM system prompt.
   *
   * Provides the GM with knowledge of:
   * - Connected rooms / exits from the current location
   * - All known locations in the campaign world (with visit status)
   * - Location history (where the party has been, in order)
   * - Arrival context (where the player just came from, if applicable)
   *
   * @param array $dungeon_data
   *   Full dungeon_data payload with rooms, hex_map, location_history.
   * @param array $room_meta
   *   Current room metadata.
   * @param int|string|null $room_index
   *   Current room index in dungeon_data['rooms'].
   *
   * @return string
   *   Formatted location context block for the system prompt.
   */
  protected function buildLocationContext(array $dungeon_data, array $room_meta, $room_index = NULL): string {
    $current_room_id = $room_meta['room_id'] ?? '';
    $rooms = $dungeon_data['rooms'] ?? [];

    $ctx = "\n=== LOCATION & WORLD AWARENESS ===\n";

    // --- Connected Rooms / Exits ---
    $exits = $this->resolveRoomExits($dungeon_data, $current_room_id);
    if (!empty($exits)) {
      $ctx .= "\nExits from this location:\n";
      foreach ($exits as $exit) {
        $exit_line = "  - {$exit['name']}";
        if (!empty($exit['connection_type'])) {
          $exit_line .= " (via {$exit['connection_type']})";
        }
        if (!empty($exit['room_type'])) {
          $exit_line .= " [{$exit['room_type']}]";
        }
        if (!empty($exit['explored'])) {
          $exit_line .= " — visited";
        }
        else {
          $exit_line .= " — unexplored";
        }
        $ctx .= $exit_line . "\n";
      }
    }
    else {
      $ctx .= "\nExits: None known. The party may need to discover exits or navigate to a new location.\n";
    }

    // --- Known Locations (World Map) ---
    if (count($rooms) > 1) {
      $ctx .= "\nKnown locations in this world:\n";
      foreach ($rooms as $idx => $room) {
        $is_current = ($room['room_id'] ?? '') === $current_room_id || (string) $idx === (string) $room_index;
        $name = $room['name'] ?? "Room {$idx}";
        $type = $room['room_type'] ?? 'unknown';
        $explored = !empty($room['state']['explored']);
        $marker = $is_current ? ' ← YOU ARE HERE' : '';
        $status = $explored ? 'explored' : 'unexplored';

        $ctx .= "  {$idx}. {$name} ({$type}) [{$status}]{$marker}\n";
      }
    }

    // --- Location History ---
    $history = $dungeon_data['location_history'] ?? [];
    if (!empty($history)) {
      $ctx .= "\nTravel history (most recent first):\n";
      // Show last 8 entries, most-recent first.
      $recent_history = array_slice(array_reverse($history), 0, 8);
      foreach ($recent_history as $entry) {
        $h_name = $entry['room_name'] ?? 'Unknown';
        $h_action = $entry['action'] ?? 'visited';
        $ctx .= "  - {$h_action}: {$h_name}";
        if (!empty($entry['timestamp'])) {
          $ctx .= " ({$entry['timestamp']})";
        }
        $ctx .= "\n";
      }
    }

    // --- Arrival Context ---
    if (!empty($dungeon_data['last_navigation'])) {
      $nav = $dungeon_data['last_navigation'];
      $from_name = $nav['from_room_name'] ?? 'Unknown';
      $travel_type = $nav['travel_type'] ?? 'walked';
      $ctx .= "\nArrival context: The party just {$travel_type} here from {$from_name}.\n";
    }

    $ctx .= <<<'LOCATION_RULES'

LOCATION RULES:
- When the player asks about nearby locations or exits, reference the exits listed above.
- You can describe what the player sees/hears in the direction of connected rooms.
- Do NOT invent exits or locations that are not listed above.
- When the player wants to travel, use the navigate_to_location action type.
- Reference the travel history to maintain continuity (e.g., "you came from the tavern").
LOCATION_RULES;

    $ctx .= <<<'ENTRY_NARRATION_RULES'

=== ROOM ENTRY NARRATION RULES ===
Whenever the party enters or first speaks in a new environment, you MUST open
your response with a full environmental description before addressing anything
the player said. This description is non-negotiable and must cover ALL of:

1. ATMOSPHERE — The overall mood, tension, and feel of the space.
   e.g. "A strained quiet hangs here, broken only by the creak of old timber."

2. SIGHT — What the party sees: dimensions, lighting, notable features,
   colours, state of the space (tidy/ruined/busy), interesting objects.

3. SOUND — Ambient sounds. Voices, music, animals, machinery, wind, silence.

4. SMELL / TASTE — Explicit smell and, where relevant, taste in the air.
   e.g. "The air tastes of iron and old smoke." Never skip this field.

5. NPCs / CREATURES PRESENT (non-party only):
   - Count them. "Three figures..." "A lone shape..."
   - Describe appearance, clothing, posture, what they are doing.
   - Do NOT reveal their names until they introduce themselves or are
     introduced — refer to them by appearance only.
     e.g. "a stout woman in an apron" NOT "Mara the innkeeper".
   - State their general demeanour explicitly:
     e.g. "unaware", "bored", "watchful", "tense", "aggressive",
     "fearful", "hostile", "friendly", "suspicious", "drunk".

Format guidance:
- Lead with atmosphere in 1–2 sentences, then move through sight → sound
  → smell/taste → NPCs in that order.
- NPCs go LAST, after the environment is established.
- Keep individual sections tight (1–3 sentences each).
- Do NOT skip any section — even "The room is silent." or
  "The air is odourless" is better than omitting the field.
ENTRY_NARRATION_RULES;

    return $ctx . "\n";
  }

  /**
   * Resolve all exits from the current room.
   *
   * Combines data from:
   * 1. Per-room connections[] array (from MapGeneratorService)
   * 2. hex_map.connections[] (from initial dungeon creation)
   *
   * @param array $dungeon_data
   *   Full dungeon data.
   * @param string $current_room_id
   *   Current room UUID.
   *
   * @return array
   *   Array of exit info: [name, room_id, connection_type, room_type, explored].
   */
  protected function resolveRoomExits(array $dungeon_data, string $current_room_id): array {
    $rooms = $dungeon_data['rooms'] ?? [];
    $exits = [];
    $seen_room_ids = [];

    // Build room lookup by room_id.
    $room_lookup = [];
    foreach ($rooms as $idx => $room) {
      $rid = $room['room_id'] ?? '';
      if ($rid) {
        $room_lookup[$rid] = $room;
      }
    }

    // Source 1: Per-room connections[].
    $current_room = $room_lookup[$current_room_id] ?? [];
    $room_connections = $current_room['connections'] ?? [];
    foreach ($room_connections as $conn) {
      $target_id = $conn['target_room_id'] ?? '';
      if ($target_id && !isset($seen_room_ids[$target_id])) {
        $target_room = $room_lookup[$target_id] ?? [];
        $exits[] = [
          'name' => $target_room['name'] ?? 'Unknown passage',
          'room_id' => $target_id,
          'connection_type' => $conn['type'] ?? 'passage',
          'room_type' => $target_room['room_type'] ?? 'unknown',
          'explored' => !empty($target_room['state']['explored']),
        ];
        $seen_room_ids[$target_id] = TRUE;
      }
    }

    // Source 2: hex_map.connections[] (bidirectional).
    $hex_connections = $dungeon_data['hex_map']['connections'] ?? [];
    foreach ($hex_connections as $conn) {
      // New-format connections from MapGeneratorService have from_room/to_room.
      $from_room = $conn['from_room'] ?? NULL;
      $to_room = $conn['to_room'] ?? NULL;

      $target_id = NULL;
      if ($from_room === $current_room_id) {
        $target_id = $to_room;
      }
      elseif ($to_room === $current_room_id) {
        $target_id = $from_room;
      }
      else {
        // Old-format connections: match by hex coordinates via regions.
        $target_id = $this->resolveHexConnectionToRoom($conn, $current_room_id, $dungeon_data);
      }

      if ($target_id && !isset($seen_room_ids[$target_id])) {
        $target_room = $room_lookup[$target_id] ?? [];
        $exits[] = [
          'name' => $target_room['name'] ?? ($conn['description'] ?? 'Unknown passage'),
          'room_id' => $target_id,
          'connection_type' => $conn['type'] ?? 'passage',
          'room_type' => $target_room['room_type'] ?? 'unknown',
          'explored' => !empty($target_room['state']['explored']),
        ];
        $seen_room_ids[$target_id] = TRUE;
      }
    }

    return $exits;
  }

  /**
   * Resolve a hex-coordinate connection to a room_id.
   *
   * For old-format hex_map connections that use {from: {q,r}, to: {q,r}},
   * we need to map hex coordinates to rooms via regions.
   *
   * @param array $conn
   *   Hex connection with 'from' and 'to' as {q, r} coordinates.
   * @param string $current_room_id
   *   Current room UUID.
   * @param array $dungeon_data
   *   Full dungeon data.
   *
   * @return string|null
   *   Target room_id, or NULL if unresolvable.
   */
  protected function resolveHexConnectionToRoom(array $conn, string $current_room_id, array $dungeon_data): ?string {
    $regions = $dungeon_data['hex_map']['regions'] ?? [];
    $rooms = $dungeon_data['rooms'] ?? [];

    // Build a hex→room_id index from regions and room hexes.
    $hex_to_room = [];
    foreach ($rooms as $room) {
      $rid = $room['room_id'] ?? '';
      foreach ($room['hexes'] ?? [] as $hex) {
        $q = $hex['q'] ?? NULL;
        $r = $hex['r'] ?? NULL;
        if ($q !== NULL && $r !== NULL) {
          $hex_to_room["{$q},{$r}"] = $rid;
        }
      }
    }

    // Alternatively, use regions → room_ids mapping.
    if (empty($hex_to_room)) {
      foreach ($regions as $region) {
        $region_room_ids = $region['room_ids'] ?? [];
        // Assign all region hexes to the first room_id in the region.
        if (!empty($region_room_ids)) {
          $region_rid = $region_room_ids[0];
          // We don't have hex ranges in regions, so use a simple approach:
          // map region index to its room.
        }
      }
    }

    $from_key = ($conn['from']['q'] ?? '?') . ',' . ($conn['from']['r'] ?? '?');
    $to_key = ($conn['to']['q'] ?? '?') . ',' . ($conn['to']['r'] ?? '?');

    $from_room = $hex_to_room[$from_key] ?? NULL;
    $to_room = $hex_to_room[$to_key] ?? NULL;

    if ($from_room === $current_room_id && $to_room && $to_room !== $current_room_id) {
      return $to_room;
    }
    if ($to_room === $current_room_id && $from_room && $from_room !== $current_room_id) {
      return $from_room;
    }

    // Fallback: use regions to find the "other" room.
    // If the connection crosses a region boundary, find which region
    // contains the current room and return the other region's room.
    $current_region_ids = [];
    $all_region_rooms = [];
    foreach ($regions as $region) {
      $rids = $region['room_ids'] ?? [];
      foreach ($rids as $rid) {
        $all_region_rooms[$rid] = TRUE;
        if ($rid === $current_room_id) {
          $current_region_ids = $rids;
        }
      }
    }

    // Find any room NOT in the current region but in a connected region.
    foreach ($regions as $region) {
      $rids = $region['room_ids'] ?? [];
      foreach ($rids as $rid) {
        if ($rid !== $current_room_id && !in_array($rid, $current_region_ids)) {
          return $rid;
        }
      }
    }

    return NULL;
  }

  /**
   * Parse structured action data from an AI response.
   *
   * Extracts the JSON block (if any) from the GM's narrative response.
   *
   * @param string $response
   *   The full AI response text.
   *
   * @return array
   *   ['narrative' => string, 'actions' => array|null, 'dice_rolls' => array]
   */
  public function parseResponse(string $response): array {
    $result = [
      'narrative' => $response,
      'actions' => NULL,
      'dice_rolls' => [],
    ];

    // Look for a JSON block wrapped in ```json ... ```
    if (preg_match('/```json\s*\n?(.*?)\n?\s*```/s', $response, $matches)) {
      $json_str = trim($matches[1]);
      $parsed = json_decode($json_str, TRUE);

      if ($parsed !== NULL && is_array($parsed)) {
        $result['actions'] = $parsed['actions'] ?? [];
        $result['dice_rolls'] = $parsed['dice_rolls'] ?? [];

        // Strip the JSON block from the narrative
        $result['narrative'] = trim(preg_replace('/```json\s*\n?.*?\n?\s*```/s', '', $response));
      }
      else {
        $this->logger->warning('Failed to parse JSON action block from GM response: @json', [
          '@json' => substr($json_str, 0, 500),
        ]);
      }
    }

    return $result;
  }

  /**
   * Apply character state changes from parsed actions.
   *
   * @param int $character_id
   *   The character row ID in dc_campaign_characters.
   * @param array $actions
   *   Parsed actions array from parseResponse().
   *
   * @return array
   *   State diff: what changed.
   */
  public function applyCharacterStateChanges(int $character_id, array $actions, ?int $campaign_id = NULL): array {
    $diff = [
      'hp_before' => NULL,
      'hp_after' => NULL,
      'spell_slots_before' => [],
      'spell_slots_after' => [],
      'conditions_added' => [],
      'conditions_removed' => [],
      'inventory_added' => [],
      'inventory_removed' => [],
      'hero_points_before' => NULL,
      'hero_points_after' => NULL,
    ];

    if (empty($actions)) {
      return $diff;
    }

    // Load character data
    $record = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['character_data', 'state_data'])
      ->condition('id', $character_id)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      $this->logger->error('Character @id not found for state update.', ['@id' => $character_id]);
      return $diff;
    }

    $char_data = $this->hydrateCharacterDataFromRecord($record);
    $runtime_state = json_decode($record['state_data'] ?? '', TRUE);
    if (!is_array($runtime_state)) {
      $runtime_state = [];
    }
    $changed = FALSE;

    // Snapshot before values
    $diff['hp_before'] = $this->getCurrentHp($char_data);
    $diff['spell_slots_before'] = $char_data['spells']['slots_used'] ?? [];
    $diff['hero_points_before'] = $this->getHeroPoints($char_data);

    foreach ($actions as $action) {
      if (($action['type'] ?? '') === 'transfer_inventory') {
        $transfer_result = $this->applyTransferInventoryAction($action, $character_id, $campaign_id);
        if (!empty($transfer_result['applied'])) {
          if (!empty($transfer_result['removed_label'])) {
            $diff['inventory_removed'][] = $transfer_result['removed_label'];
          }
          if (!empty($transfer_result['added_label'])) {
            $diff['inventory_added'][] = $transfer_result['added_label'];
          }
          $changed = TRUE;
        }
        continue;
      }

      $state_changes = $action['state_changes']['character'] ?? [];

      // HP delta
      if (!empty($state_changes['hp_delta'])) {
        $delta = (int) $state_changes['hp_delta'];
        $current = $this->getCurrentHp($char_data);
        $max = $this->getMaxHp($char_data);
        $new_hp = max(0, min($max, $current + $delta));
        $this->setCurrentHp($char_data, $new_hp);
        $changed = TRUE;
      }

      // Temp HP
      if (!empty($state_changes['temp_hp'])) {
        $new_temp_hp = max($this->getTempHp($char_data), (int) $state_changes['temp_hp']);
        $this->setTempHp($char_data, $new_temp_hp);
        $changed = TRUE;
      }

      // Spell slot usage
      if (!empty($state_changes['spell_slot_used'])) {
        $slot_key = $this->mapSpellSlotKey((string) $state_changes['spell_slot_used']);

        if (!isset($char_data['spells']['slots_used'])) {
          $char_data['spells']['slots_used'] = [];
        }
        $used = ($char_data['spells']['slots_used'][$slot_key] ?? 0) + 1;
        $max_slots = $char_data['spells']['slots'][$slot_key] ?? 0;
        // Don't exceed max slots
        $char_data['spells']['slots_used'][$slot_key] = min($used, $max_slots);
        $changed = TRUE;
      }

      // Currency delta.
      if (!empty($state_changes['currency_delta']) && is_array($state_changes['currency_delta'])) {
        $currency = $this->extractCurrency($char_data);
        $delta = $this->normalizeCurrencyDelta($state_changes['currency_delta']);
        $total_cp = $this->currencyToCopper($currency) + $this->currencyToCopper($delta);
        if ($total_cp >= 0) {
          $this->setCurrency($char_data, $this->copperToCurrency($total_cp));
          $changed = TRUE;
        }
      }

      // Conditions add
      if (!empty($state_changes['conditions_add'])) {
        if (!isset($char_data['conditions'])) {
          $char_data['conditions'] = [];
        }
        foreach ($state_changes['conditions_add'] as $condition) {
          $cond_name = is_array($condition) ? ($condition['name'] ?? $condition) : $condition;
          // Avoid duplicates
          $exists = FALSE;
          foreach ($char_data['conditions'] as $existing) {
            $existing_name = is_array($existing) ? ($existing['name'] ?? $existing) : $existing;
            if ($existing_name === $cond_name) {
              $exists = TRUE;
              break;
            }
          }
          if (!$exists) {
            $char_data['conditions'][] = is_array($condition) ? $condition : ['name' => $condition, 'value' => 1];
            $diff['conditions_added'][] = $cond_name;
          }
        }
        $changed = TRUE;
      }

      // Conditions remove
      if (!empty($state_changes['conditions_remove'])) {
        if (isset($char_data['conditions'])) {
          foreach ($state_changes['conditions_remove'] as $cond_to_remove) {
            $cond_name = is_array($cond_to_remove) ? ($cond_to_remove['name'] ?? $cond_to_remove) : $cond_to_remove;
            $char_data['conditions'] = array_values(array_filter($char_data['conditions'], function($existing) use ($cond_name) {
              $existing_name = is_array($existing) ? ($existing['name'] ?? $existing) : $existing;
              return $existing_name !== $cond_name;
            }));
            $diff['conditions_removed'][] = $cond_name;
          }
          $changed = TRUE;
        }
      }

      // Hero points
      if (!empty($state_changes['hero_points_delta'])) {
        $hero_points_current = $this->getHeroPoints($char_data);
        $this->setHeroPoints($char_data, max(0, min(3, $hero_points_current + (int) $state_changes['hero_points_delta'])));
        $changed = TRUE;
      }

      // Inventory add
      if (!empty($state_changes['inventory_add'])) {
        foreach ($state_changes['inventory_add'] as $item) {
          if ($currency_add = $this->parseCurrencyDescriptor($item)) {
            $currency = $this->extractCurrency($char_data);
            $currency[$currency_add['denomination']] += $currency_add['amount'];
            $this->setCurrency($char_data, $currency);
            $diff['inventory_added'][] = $currency_add['amount'] . ' ' . $currency_add['denomination'];
            $changed = TRUE;
            continue;
          }

          $normalized_item = $this->normalizeInventoryEntry($item);
          $this->addInventoryItemToCharacterData($char_data, $normalized_item);
          $diff['inventory_added'][] = $normalized_item['quantity'] > 1
            ? $normalized_item['quantity'] . 'x ' . $normalized_item['name']
            : $normalized_item['name'];
        }
        $changed = TRUE;
      }

      // Inventory remove
      if (!empty($state_changes['inventory_remove'])) {
        foreach ($state_changes['inventory_remove'] as $item_to_remove) {
          if ($currency_remove = $this->parseCurrencyDescriptor($item_to_remove)) {
            $currency = $this->extractCurrency($char_data);
            $total_cp = $this->currencyToCopper($currency) - ($currency_remove['amount'] * self::CURRENCY_CP_VALUES[$currency_remove['denomination']]);
            if ($total_cp >= 0) {
              $this->setCurrency($char_data, $this->copperToCurrency($total_cp));
              $diff['inventory_removed'][] = $currency_remove['amount'] . ' ' . $currency_remove['denomination'];
              $changed = TRUE;
            }
            continue;
          }

          $normalized_item = $this->normalizeInventoryEntry($item_to_remove);
          if ($this->removeInventoryItemFromCharacterData($char_data, $normalized_item)) {
            $diff['inventory_removed'][] = $normalized_item['quantity'] > 1
              ? $normalized_item['quantity'] . 'x ' . $normalized_item['name']
              : $normalized_item['name'];
          }
        }
        $changed = TRUE;
      }
    }

    // Snapshot after values
    $diff['hp_after'] = $this->getCurrentHp($char_data);
    $diff['spell_slots_after'] = $char_data['spells']['slots_used'] ?? [];
    $diff['hero_points_after'] = $this->getHeroPoints($char_data);

    // Persist changes
    if ($changed) {
      $runtime_state = $this->syncRuntimeStateFromCharacterData($char_data, $runtime_state);

      $this->database->update('dc_campaign_characters')
        ->fields([
          'character_data' => json_encode($char_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
          'state_data' => json_encode($runtime_state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
          'changed' => time(),
          'updated' => time(),
        ])
        ->condition('id', $character_id)
        ->execute();

      $this->logger->info('Character @id state updated: HP @hp_before->@hp_after, conditions added: @added, removed: @removed', [
        '@id' => $character_id,
        '@hp_before' => $diff['hp_before'],
        '@hp_after' => $diff['hp_after'],
        '@added' => implode(',', $diff['conditions_added']),
        '@removed' => implode(',', $diff['conditions_removed']),
      ]);
    }

    return $diff;
  }

  /**
   * Apply room/dungeon state changes from parsed actions.
   *
   * @param int $dungeon_id
   *   The dungeon record ID.
   * @param int $campaign_id
   *   The campaign ID.
   * @param int|string $room_index
   *   Array index of the room in dungeon_data['rooms'].
   * @param array &$dungeon_data
   *   The full dungeon_data array (modified in place).
   * @param array $actions
   *   Parsed actions array from parseResponse().
   *
   * @return array
   *   State diff: what changed in the room.
   */
  public function applyRoomStateChanges(int|string $dungeon_id, int $campaign_id, int|string $room_index, array &$dungeon_data, array $actions): array {
    $diff = [
      'effects_added' => [],
      'effects_removed' => [],
      'entities_modified' => [],
      'entities_added' => [],
      'lighting_change' => NULL,
      'terrain_change' => NULL,
    ];

    if (empty($actions)) {
      return $diff;
    }

    // Initialize room state storage if needed
    if (!isset($dungeon_data['rooms'][$room_index]['gameplay_state'])) {
      $dungeon_data['rooms'][$room_index]['gameplay_state'] = [
        'active_effects' => [],
        'explored_hexes' => [],
        'environmental_changes' => [],
      ];
    }

    $gameplay_state = &$dungeon_data['rooms'][$room_index]['gameplay_state'];

    foreach ($actions as $action) {
      $room_changes = $action['state_changes']['room'] ?? [];

      // Lighting change
      if (!empty($room_changes['lighting_change'])) {
        $old_lighting = $dungeon_data['rooms'][$room_index]['lighting'] ?? 'normal';
        $dungeon_data['rooms'][$room_index]['lighting'] = $room_changes['lighting_change'];
        $diff['lighting_change'] = [
          'from' => $old_lighting,
          'to' => $room_changes['lighting_change'],
        ];
      }

      // Effects add
      if (!empty($room_changes['effects_add'])) {
        foreach ($room_changes['effects_add'] as $effect) {
          $effect_entry = is_array($effect) ? $effect : ['name' => $effect];
          $effect_entry['added_at'] = date('c');
          $gameplay_state['active_effects'][] = $effect_entry;
          $diff['effects_added'][] = $effect_entry;
        }
      }

      // Effects remove
      if (!empty($room_changes['effects_remove'])) {
        foreach ($room_changes['effects_remove'] as $effect_to_remove) {
          $effect_name = is_array($effect_to_remove) ? ($effect_to_remove['name'] ?? $effect_to_remove) : $effect_to_remove;
          $gameplay_state['active_effects'] = array_values(array_filter(
            $gameplay_state['active_effects'],
            function($e) use ($effect_name) {
              $ename = is_array($e) ? ($e['name'] ?? $e) : $e;
              return $ename !== $effect_name;
            }
          ));
          $diff['effects_removed'][] = $effect_name;
        }
      }

      // Entities add
      if (!empty($room_changes['entities_add'])) {
        if (!isset($dungeon_data['entities'])) {
          $dungeon_data['entities'] = [];
        }
        foreach ($room_changes['entities_add'] as $entity) {
          $entity_entry = is_array($entity) ? $entity : ['entity_type' => 'object', 'entity_ref' => $entity];
          if (empty($entity_entry['entity_instance_id'])) {
            $entity_entry['entity_instance_id'] = 'gameplay_' . uniqid();
          }
          $dungeon_data['entities'][] = $entity_entry;
          $diff['entities_added'][] = $entity_entry;
        }
      }

      // Entities modify
      if (!empty($room_changes['entities_modify'])) {
        foreach ($room_changes['entities_modify'] as $mod) {
          $target_id = $mod['entity_instance_id'] ?? '';
          if ($target_id && isset($dungeon_data['entities'])) {
            foreach ($dungeon_data['entities'] as &$entity) {
              if (($entity['entity_instance_id'] ?? '') === $target_id) {
                if (isset($mod['state'])) {
                  $entity['state'] = array_merge($entity['state'] ?? [], $mod['state']);
                }
                $diff['entities_modified'][] = $mod;
                break;
              }
            }
            unset($entity);
          }
        }
      }

      // Terrain change
      if (!empty($room_changes['terrain_change'])) {
        $old_terrain = $dungeon_data['rooms'][$room_index]['terrain'] ?? 'normal';
        $dungeon_data['rooms'][$room_index]['terrain'] = $room_changes['terrain_change'];
        $diff['terrain_change'] = [
          'from' => $old_terrain,
          'to' => $room_changes['terrain_change'],
        ];
      }

      // Environmental changes log
      $gameplay_state['environmental_changes'][] = [
        'action_type' => $action['type'] ?? 'unknown',
        'action_name' => $action['name'] ?? 'Unknown Action',
        'timestamp' => date('c'),
        'details' => $action['details'] ?? [],
      ];
    }

    return $diff;
  }

  /**
   * Load character data for prompt building.
   *
   * @param int $character_id
   *   Character ID.
   *
   * @return array|null
   *   Character data array, or NULL if not found.
   */
  public function loadCharacterData(int $character_id): ?array {
    $record = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['character_data', 'state_data', 'name', 'level', 'ancestry', 'class'])
      ->condition('id', $character_id)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      return NULL;
    }

    $char_data = $this->hydrateCharacterDataFromRecord($record);
    // Ensure top-level fields are available
    $char_data['name'] = $char_data['name'] ?? $record['name'];
    $char_data['class'] = $char_data['class'] ?? $record['class'];
    $char_data['ancestry'] = $char_data['ancestry'] ?? $record['ancestry'];
    $char_data['level'] = $char_data['level'] ?? $record['level'];

    return $char_data;
  }

  /**
   * Validate that declared character-side resource usage is actually possible.
   *
   * Invalid actions are rejected before any state changes are applied.
   *
   * @param int $character_id
   *   Character row ID.
   * @param array $actions
   *   Parsed actions.
   *
   * @return array
   *   Validation result.
   */
  public function validateCharacterActionResources(int $character_id, array $actions, ?int $campaign_id = NULL): array {
    $char_data = $this->loadCharacterData($character_id);
    if (!$char_data) {
      return [
        'valid' => FALSE,
        'actions' => [],
        'errors' => [[
          'action_name' => 'unknown',
          'message' => 'Character resources could not be verified.',
        ]],
      ];
    }

    $validated_actions = [];
    $errors = [];
    $simulated_state = $char_data;

    foreach ($actions as $action) {
      $action_name = $action['name'] ?? ($action['type'] ?? 'Unknown action');
      $state_changes = $action['state_changes']['character'] ?? [];
      $action_errors = [];

      if (($action['type'] ?? '') === 'transfer_inventory') {
        $transfer_validation = $this->validateTransferInventoryAction($action, $character_id, $campaign_id);
        if (empty($transfer_validation['valid'])) {
          $action_errors = array_merge($action_errors, $transfer_validation['errors'] ?? []);
        }
        if (!empty($action_errors)) {
          $errors[] = [
            'action_name' => $action_name,
            'message' => "Cannot resolve {$action_name}: " . implode('; ', array_unique($action_errors)) . '.',
          ];
          continue;
        }

        $validated_actions[] = $action;
        continue;
      }

      if (($action['type'] ?? '') === 'quest_turn_in') {
        $quest_validation = $this->validateQuestTurnInStructure($action);
        if (empty($quest_validation['valid'])) {
          $action_errors = array_merge($action_errors, $quest_validation['errors'] ?? []);
        }
        if (!empty($action_errors)) {
          $errors[] = [
            'action_name' => $action_name,
            'message' => "Cannot resolve {$action_name}: " . implode('; ', array_unique($action_errors)) . '.',
          ];
          continue;
        }

        $validated_actions[] = $action;
        continue;
      }

      if (($action['type'] ?? '') === 'combat_initiation') {
        $combat_validation = $this->validateCombatInitiationStructure($action);
        if (empty($combat_validation['valid'])) {
          $action_errors = array_merge($action_errors, $combat_validation['errors'] ?? []);
        }
        if (!empty($action_errors)) {
          $errors[] = [
            'action_name' => $action_name,
            'message' => "Cannot resolve {$action_name}: " . implode('; ', array_unique($action_errors)) . '.',
          ];
          continue;
        }

        $validated_actions[] = $action;
        continue;
      }

      if (!empty($state_changes['spell_slot_used'])) {
        $slot_key = $this->mapSpellSlotKey((string) $state_changes['spell_slot_used']);
        $slots = $simulated_state['spells']['slots'][$slot_key] ?? 0;
        $used = $simulated_state['spells']['slots_used'][$slot_key] ?? 0;
        if (($slots - $used) < 1) {
          $action_errors[] = "no {$slot_key}-level spell slots remain";
        }
      }

      if (!empty($state_changes['currency_delta']) && is_array($state_changes['currency_delta'])) {
        $currency_total = $this->currencyToCopper($this->extractCurrency($simulated_state));
        $delta_total = $this->currencyToCopper($this->normalizeCurrencyDelta($state_changes['currency_delta']));
        if (($currency_total + $delta_total) < 0) {
          $action_errors[] = 'insufficient currency is available';
        }
      }

      foreach (($state_changes['inventory_remove'] ?? []) as $item_to_remove) {
        if ($currency_remove = $this->parseCurrencyDescriptor($item_to_remove)) {
          $currency_total = $this->currencyToCopper($this->extractCurrency($simulated_state));
          $remove_total = $currency_remove['amount'] * self::CURRENCY_CP_VALUES[$currency_remove['denomination']];
          if ($currency_total < $remove_total) {
            $action_errors[] = 'insufficient currency is available';
          }
          continue;
        }

        $normalized_item = $this->normalizeInventoryEntry($item_to_remove);
        if (!$this->inventoryHasItemQuantity($simulated_state, $normalized_item['name'], $normalized_item['quantity'])) {
          $required = $normalized_item['quantity'] > 1
            ? $normalized_item['quantity'] . 'x ' . $normalized_item['name']
            : $normalized_item['name'];
          $action_errors[] = "required item not found in inventory: {$required}";
        }
      }

      if (!empty($action_errors)) {
        $errors[] = [
          'action_name' => $action_name,
          'message' => "Cannot resolve {$action_name}: " . implode('; ', array_unique($action_errors)) . '.',
        ];
        continue;
      }

      $this->simulateCharacterStateChanges($simulated_state, $state_changes);
      $validated_actions[] = $action;
    }

    return [
      'valid' => empty($errors),
      'actions' => $validated_actions,
      'errors' => $errors,
    ];
  }

  /**
   * Build a concise user-facing correction summary for validation failures.
   */
  public function buildValidationFailureSummary(array $errors): string {
    if (empty($errors)) {
      return '';
    }

    $messages = array_map(static function (array $error): string {
      return $error['message'] ?? 'A resource validation check failed.';
    }, $errors);

    return 'However, the action cannot resolve as described: ' . implode(' ', array_unique($messages));
  }

  /**
   * Build an authoritative reality snapshot for regeneration prompts.
   *
   * This is the centralized lookup payload for inventory, currency, spell
   * slots, conditions, and room context that the model must obey.
   *
   * @param array|null $character_data
   *   Hydrated character data.
   * @param array $room_inventory
   *   Structured room inventory.
   *
   * @return array
   *   Snapshot payload.
   */
  public function buildRealitySnapshot(?array $character_data, array $room_inventory = []): array {
    $inventory_items = [];
    foreach ($this->extractInventoryItems($character_data ?? []) as $item) {
      $inventory_items[] = [
        'item_instance_id' => $item['item_instance_id'] ?? NULL,
        'name' => $item['name'] ?? 'Unknown item',
        'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
      ];
    }

    $room_items = [];
    foreach (($room_inventory['items'] ?? []) as $item) {
      $room_items[] = [
        'item_instance_id' => $item['item_instance_id'] ?? NULL,
        'name' => $item['name'] ?? 'Unknown item',
        'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
      ];
    }

    return [
      'character' => [
        'name' => $character_data['name'] ?? 'Unknown character',
        'currency' => $this->extractCurrency($character_data ?? []),
        'inventory' => $inventory_items,
        'spell_slots_remaining' => $this->buildSpellSlotAvailabilitySnapshot($character_data ?? []),
        'conditions' => $character_data['conditions'] ?? [],
        'hp' => [
          'current' => $this->getCurrentHp($character_data ?? []),
          'max' => $this->getMaxHp($character_data ?? []),
          'temp' => $this->getTempHp($character_data ?? []),
        ],
      ],
      'room' => [
        'npcs' => array_values(array_map(static function (array $npc): string {
          return (string) ($npc['name'] ?? 'Unknown NPC');
        }, $room_inventory['npcs'] ?? [])),
        'storage_owners' => array_values(array_map(static function (array $owner): array {
          return [
            'owner_id' => $owner['owner_id'] ?? '',
            'owner_type' => $owner['owner_type'] ?? '',
            'name' => $owner['name'] ?? '',
          ];
        }, $room_inventory['storage_owners'] ?? [])),
        'items' => $room_items,
        'obstacles' => array_values(array_map(static function (array $entry): string {
          return (string) ($entry['name'] ?? 'Unknown obstacle');
        }, $room_inventory['obstacles'] ?? [])),
        'hazards' => array_values(array_map(static function (array $entry): string {
          return (string) ($entry['name'] ?? 'Unknown hazard');
        }, $room_inventory['hazards'] ?? [])),
        'active_effects' => array_values(array_map(static function ($entry): string {
          if (is_array($entry)) {
            return (string) ($entry['name'] ?? 'Unknown effect');
          }
          return (string) $entry;
        }, $room_inventory['active_effects'] ?? [])),
      ],
    ];
  }

  /**
   * Build a regeneration instruction block after a failed reality check.
   *
   * @param array $errors
   *   Validation errors.
   * @param array $snapshot
   *   Authoritative reality snapshot.
   *
   * @return string
   *   Retry prompt suffix.
   */
  public function buildRealityRetryPrompt(array $errors, array $snapshot): string {
    $error_lines = array_map(static function (array $error): string {
      return '- ' . ($error['message'] ?? 'Unknown validation error.');
    }, $errors);

    $character = $snapshot['character'] ?? [];
    $room = $snapshot['room'] ?? [];

    return "=== REALITY CHECK FAILED ===\n"
      . "Your prior response attempted mechanics that do not match server reality.\n"
      . "Regenerate the FULL response from scratch using ONLY the authoritative values below.\n"
      . "If a requested action cannot happen, narrate the failed attempt honestly and do not claim success.\n\n"
      . "Validation failures:\n"
      . implode("\n", $error_lines)
      . "\n\n=== AUTHORITATIVE CHARACTER STATE ===\n"
      . json_encode($character, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
      . "\n\n=== AUTHORITATIVE ROOM STATE ===\n"
      . json_encode($room, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
      . "\n\nReturn corrected narrative text. If mechanics still apply, include a corrected JSON block."
      ;
  }

  /**
   * Build a summary of state changes for chat display.
   *
   * @param array $char_diff
   *   Character state diff from applyCharacterStateChanges().
   * @param array $room_diff
   *   Room state diff from applyRoomStateChanges().
   * @param array $dice_rolls
   *   Dice rolls from the parsed response.
   * @param array $actions
   *   Parsed actions.
   *
   * @return array
   *   A summary suitable for JSON response to the client.
   */
  public function buildStateDiffSummary(array $char_diff, array $room_diff, array $dice_rolls, array $actions, array $validation_errors = []): array {
    $summary = [
      'has_mechanical_effects' => !empty($actions),
      'actions_taken' => [],
      'validation_errors' => $validation_errors,
      'dice_rolls' => $dice_rolls,
      'character_changes' => [],
      'room_changes' => [],
    ];

    foreach ($actions as $action) {
      $summary['actions_taken'][] = [
        'type' => $action['type'] ?? 'unknown',
        'name' => $action['name'] ?? 'Unknown',
        'details' => $action['details'] ?? [],
      ];
    }

    // Character changes
    if ($char_diff['hp_before'] !== $char_diff['hp_after']) {
      $summary['character_changes'][] = [
        'field' => 'hp',
        'from' => $char_diff['hp_before'],
        'to' => $char_diff['hp_after'],
      ];
    }
    if ($char_diff['spell_slots_before'] !== $char_diff['spell_slots_after']) {
      $summary['character_changes'][] = [
        'field' => 'spell_slots_used',
        'from' => $char_diff['spell_slots_before'],
        'to' => $char_diff['spell_slots_after'],
      ];
    }
    if (!empty($char_diff['conditions_added'])) {
      $summary['character_changes'][] = [
        'field' => 'conditions',
        'added' => $char_diff['conditions_added'],
      ];
    }
    if (!empty($char_diff['conditions_removed'])) {
      $summary['character_changes'][] = [
        'field' => 'conditions',
        'removed' => $char_diff['conditions_removed'],
      ];
    }
    if (!empty($char_diff['inventory_added'])) {
      $summary['character_changes'][] = [
        'field' => 'inventory',
        'added' => $char_diff['inventory_added'],
      ];
    }
    if (!empty($char_diff['inventory_removed'])) {
      $summary['character_changes'][] = [
        'field' => 'inventory',
        'removed' => $char_diff['inventory_removed'],
      ];
    }
    if ($char_diff['hero_points_before'] !== $char_diff['hero_points_after']) {
      $summary['character_changes'][] = [
        'field' => 'hero_points',
        'from' => $char_diff['hero_points_before'],
        'to' => $char_diff['hero_points_after'],
      ];
    }

    // Room changes
    if (!empty($room_diff['effects_added'])) {
      $summary['room_changes'][] = [
        'type' => 'effects_added',
        'effects' => $room_diff['effects_added'],
      ];
    }
    if (!empty($room_diff['effects_removed'])) {
      $summary['room_changes'][] = [
        'type' => 'effects_removed',
        'effects' => $room_diff['effects_removed'],
      ];
    }
    if (!empty($room_diff['lighting_change'])) {
      $summary['room_changes'][] = [
        'type' => 'lighting',
        'from' => $room_diff['lighting_change']['from'],
        'to' => $room_diff['lighting_change']['to'],
      ];
    }
    if (!empty($room_diff['entities_added'])) {
      $summary['room_changes'][] = [
        'type' => 'entities_added',
        'entities' => $room_diff['entities_added'],
      ];
    }
    if (!empty($room_diff['entities_modified'])) {
      $summary['room_changes'][] = [
        'type' => 'entities_modified',
        'entities' => $room_diff['entities_modified'],
      ];
    }

    return $summary;
  }

  /**
   * Merge persisted record payloads into a single working character array.
   */
  protected function hydrateCharacterDataFromRecord(array $record): array {
    $char_data = json_decode($record['character_data'] ?? '', TRUE);
    if (!is_array($char_data)) {
      $char_data = [];
    }

    $runtime_state = json_decode($record['state_data'] ?? '', TRUE);
    if (is_array($runtime_state) && !empty($runtime_state)) {
      if (!empty($runtime_state['inventory'])) {
        $char_data['inventory'] = $runtime_state['inventory'];
      }
      if (!empty($runtime_state['conditions'])) {
        $char_data['conditions'] = $runtime_state['conditions'];
      }
      if (!empty($runtime_state['spells'])) {
        $char_data['spells'] = array_replace_recursive($char_data['spells'] ?? [], $runtime_state['spells']);
      }
      if (!empty($runtime_state['resources']['hitPoints'])) {
        $char_data['hit_points'] = [
          'current' => $runtime_state['resources']['hitPoints']['current'] ?? ($char_data['hit_points']['current'] ?? 0),
          'max' => $runtime_state['resources']['hitPoints']['max'] ?? ($char_data['hit_points']['max'] ?? 0),
          'temp' => $runtime_state['resources']['hitPoints']['temporary'] ?? ($char_data['hit_points']['temp'] ?? 0),
        ];
      }
      if (isset($runtime_state['resources']['heroPoints']['current'])) {
        $char_data['hero_points'] = (int) $runtime_state['resources']['heroPoints']['current'];
      }
    }

    return $char_data;
  }

  /**
   * Mirror gameplay-relevant fields back into runtime state.
   */
  protected function syncRuntimeStateFromCharacterData(array $char_data, array $runtime_state): array {
    $runtime_state['inventory'] = $char_data['inventory'] ?? ($runtime_state['inventory'] ?? []);
    $runtime_state['conditions'] = $char_data['conditions'] ?? ($runtime_state['conditions'] ?? []);
    $runtime_state['spells'] = $char_data['spells'] ?? ($runtime_state['spells'] ?? []);
    $runtime_state['resources']['hitPoints'] = [
      'current' => $this->getCurrentHp($char_data),
      'max' => $this->getMaxHp($char_data),
      'temporary' => $this->getTempHp($char_data),
    ];
    $runtime_state['resources']['heroPoints']['current'] = $this->getHeroPoints($char_data);
    return $runtime_state;
  }

  /**
   * Build remaining spell slot snapshot for prompts.
   */
  protected function buildSpellSlotAvailabilitySnapshot(array $character_data): array {
    $slots = $character_data['spells']['slots'] ?? [];
    $used = $character_data['spells']['slots_used'] ?? [];
    $remaining = [];
    foreach ($slots as $slot_key => $count) {
      if ($slot_key === 'cantrips') {
        continue;
      }
      $remaining[$slot_key] = max(0, (int) $count - (int) ($used[$slot_key] ?? 0));
    }
    return $remaining;
  }

  /**
   * Simulate character changes for validation across multiple actions.
   */
  protected function simulateCharacterStateChanges(array &$char_data, array $state_changes): void {
    if (!empty($state_changes['spell_slot_used'])) {
      $slot_key = $this->mapSpellSlotKey((string) $state_changes['spell_slot_used']);
      $char_data['spells']['slots_used'][$slot_key] = ($char_data['spells']['slots_used'][$slot_key] ?? 0) + 1;
    }

    if (!empty($state_changes['currency_delta']) && is_array($state_changes['currency_delta'])) {
      $total_cp = $this->currencyToCopper($this->extractCurrency($char_data)) + $this->currencyToCopper($this->normalizeCurrencyDelta($state_changes['currency_delta']));
      $this->setCurrency($char_data, $this->copperToCurrency(max(0, $total_cp)));
    }

    foreach (($state_changes['inventory_remove'] ?? []) as $item_to_remove) {
      if ($currency_remove = $this->parseCurrencyDescriptor($item_to_remove)) {
        $total_cp = $this->currencyToCopper($this->extractCurrency($char_data)) - ($currency_remove['amount'] * self::CURRENCY_CP_VALUES[$currency_remove['denomination']]);
        $this->setCurrency($char_data, $this->copperToCurrency(max(0, $total_cp)));
        continue;
      }
      $this->removeInventoryItemFromCharacterData($char_data, $this->normalizeInventoryEntry($item_to_remove));
    }

    foreach (($state_changes['inventory_add'] ?? []) as $item_to_add) {
      if ($currency_add = $this->parseCurrencyDescriptor($item_to_add)) {
        $currency = $this->extractCurrency($char_data);
        $currency[$currency_add['denomination']] += $currency_add['amount'];
        $this->setCurrency($char_data, $currency);
        continue;
      }
      $this->addInventoryItemToCharacterData($char_data, $this->normalizeInventoryEntry($item_to_add));
    }
  }

  /**
   * Map spell slot identifiers to stored keys.
   */
  protected function mapSpellSlotKey(string $level): string {
    $slot_key_map = [
      '1' => 'first', '2' => 'second', '3' => 'third',
      '4' => 'fourth', '5' => 'fifth', '6' => 'sixth',
      '7' => 'seventh', '8' => 'eighth', '9' => 'ninth', '10' => 'tenth',
      'first' => 'first', 'second' => 'second', 'third' => 'third',
      'fourth' => 'fourth', 'fifth' => 'fifth', 'sixth' => 'sixth',
      'seventh' => 'seventh', 'eighth' => 'eighth', 'ninth' => 'ninth', 'tenth' => 'tenth',
    ];
    return $slot_key_map[strtolower($level)] ?? strtolower($level);
  }

  /**
   * Extract a flattened list of inventory items from mixed schemas.
   */
  protected function extractInventoryItems(array $char_data): array {
    $inventory = $char_data['inventory'] ?? [];
    if (!is_array($inventory)) {
      return [];
    }

    if (isset($inventory['carried']) || isset($inventory['worn']) || isset($inventory['equipped']) || isset($inventory['stashed'])) {
      $items = [];
      foreach (($inventory['carried'] ?? []) as $item) {
        if (is_array($item)) {
          $items[] = $item;
        }
      }
      foreach (($inventory['equipped'] ?? []) as $item) {
        if (is_array($item)) {
          $items[] = $item;
        }
      }
      foreach (($inventory['stashed'] ?? []) as $item) {
        if (is_array($item)) {
          $items[] = $item;
        }
      }
      $worn = $inventory['worn'] ?? [];
      foreach (($worn['weapons'] ?? []) as $item) {
        if (is_array($item)) {
          $items[] = $item;
        }
      }
      if (!empty($worn['armor']) && is_array($worn['armor'])) {
        $items[] = $worn['armor'];
      }
      foreach (($worn['accessories'] ?? []) as $item) {
        if (is_array($item)) {
          $items[] = $item;
        }
      }
      return $items;
    }

    return array_values(array_filter($inventory, 'is_array'));
  }

  /**
   * Validate a centralized inventory transfer action.
   */
  protected function validateTransferInventoryAction(array $action, int $acting_character_id, ?int $campaign_id = NULL): array {
      $transfer = $this->extractTransferSpec($action, $acting_character_id);
      if (empty($transfer['valid'])) {
        return $transfer;
      }

      $validation = $this->inventoryManagementService->validateTransferTransaction(
        $transfer['source'],
        $transfer['destination'],
        $transfer['item_instance_id'],
        $transfer['quantity'],
        $campaign_id
      );

      if (empty($validation['valid'])) {
        return [
          'valid' => FALSE,
          'errors' => $validation['errors'] ?? ['Inventory transfer could not be validated.'],
        ];
      }

      return ['valid' => TRUE, 'errors' => []];
  }

  /**
   * Execute a centralized inventory transfer action.
   */
  protected function applyTransferInventoryAction(array $action, int $acting_character_id, ?int $campaign_id = NULL): array {
      $transfer = $this->extractTransferSpec($action, $acting_character_id);
      if (empty($transfer['valid'])) {
        return ['applied' => FALSE];
      }

      $result = $this->inventoryManagementService->transferItemTransaction(
        $transfer['source'],
        $transfer['destination'],
        $transfer['item_instance_id'],
        $transfer['quantity'],
        $campaign_id
      );

      $label = ($result['item_name'] ?? 'Item');
      if (($transfer['quantity'] ?? 1) > 1) {
        $label = $transfer['quantity'] . 'x ' . $label;
      }

      return [
        'applied' => !empty($result['success']),
        'removed_label' => ((string) $transfer['source']['owner_id'] === (string) $acting_character_id && $transfer['source']['owner_type'] === 'character') ? $label : NULL,
        'added_label' => ((string) $transfer['destination']['owner_id'] === (string) $acting_character_id && $transfer['destination']['owner_type'] === 'character') ? $label : NULL,
      ];
  }

  /**
   * Extract and normalize the transfer spec from an action payload.
   */
  protected function extractTransferSpec(array $action, int $acting_character_id): array {
      $transfer = $action['details']['transfer'] ?? [];
      if (!is_array($transfer)) {
        return [
          'valid' => FALSE,
          'errors' => ['transfer_inventory action is missing details.transfer.'],
        ];
      }

      $item_instance_id = (string) ($transfer['item_instance_id'] ?? '');
      $source_owner_type = (string) ($transfer['source_owner_type'] ?? 'character');
      $source_owner_id = (string) ($transfer['source_owner_id'] ?? $acting_character_id);
      $dest_owner_type = (string) ($transfer['dest_owner_type'] ?? '');
      $dest_owner_id = (string) ($transfer['dest_owner_id'] ?? '');
      $quantity = max(1, (int) ($transfer['quantity'] ?? 1));

      if (strtoupper($source_owner_id) === 'ACTING_CHARACTER') {
        $source_owner_id = (string) $acting_character_id;
      }
      if (strtoupper($dest_owner_id) === 'ACTING_CHARACTER') {
        $dest_owner_id = (string) $acting_character_id;
      }

      $errors = [];
      if ($item_instance_id === '') {
        $errors[] = 'missing item_instance_id';
      }
      if ($dest_owner_type === '' || $dest_owner_id === '') {
        $errors[] = 'missing destination storage owner';
      }

      if (!empty($errors)) {
        return ['valid' => FALSE, 'errors' => $errors];
      }

      return [
        'valid' => TRUE,
        'item_instance_id' => $item_instance_id,
        'quantity' => $quantity,
        'source' => [
          'owner_type' => $source_owner_type,
          'owner_id' => $source_owner_id,
          'location_type' => $transfer['source_location_type'] ?? NULL,
        ],
        'destination' => [
          'owner_type' => $dest_owner_type,
          'owner_id' => $dest_owner_id,
          'location_type' => $transfer['dest_location_type'] ?? NULL,
        ],
      ];
  }

  /**
   * Validate the structure of a quest turn-in action.
   */
  protected function validateQuestTurnInStructure(array $action): array {
    $quest = $action['details']['quest'] ?? NULL;
    if (!is_array($quest)) {
      return ['valid' => FALSE, 'errors' => ['missing details.quest payload']];
    }

    $errors = [];
    if (empty($quest['objective_type'])) {
      $errors[] = 'missing objective_type';
    }
    if (empty($quest['objective_id']) && empty($quest['quest_id']) && empty($quest['npc_ref']) && empty($quest['item_ref'])) {
      $errors[] = 'quest_turn_in needs at least one of objective_id, quest_id, npc_ref, or item_ref';
    }

    return ['valid' => empty($errors), 'errors' => $errors];
  }

  /**
   * Validate the structure of a combat initiation action.
   */
  protected function validateCombatInitiationStructure(array $action): array {
    $combat = $action['details']['combat'] ?? NULL;
    if (!is_array($combat)) {
      return ['valid' => FALSE, 'errors' => ['missing details.combat payload']];
    }

    $errors = [];
    if (empty($combat['reason']) && empty($combat['enemy_entity_ids']) && empty($combat['target_entity_id']) && empty($combat['enemy_names']) && empty($combat['target_name'])) {
      $errors[] = 'combat_initiation needs a reason or target enemy reference';
    }

    return ['valid' => empty($errors), 'errors' => $errors];
  }


  /**
   * Extract currency from mixed schemas.
   */
  protected function extractCurrency(array $char_data): array {
    $currency = $char_data['inventory']['currency'] ?? [];
    if (!is_array($currency)) {
      $currency = [];
    }

    return [
      'cp' => (float) ($currency['cp'] ?? 0),
      'sp' => (float) ($currency['sp'] ?? 0),
      'gp' => (float) ($currency['gp'] ?? ($char_data['gold'] ?? 0)),
      'pp' => (float) ($currency['pp'] ?? 0),
    ];
  }

  /**
   * Persist currency into mixed schemas.
   */
  protected function setCurrency(array &$char_data, array $currency): void {
    if (!isset($char_data['inventory']) || !is_array($char_data['inventory'])) {
      $char_data['inventory'] = [];
    }
    $char_data['inventory']['currency'] = [
      'cp' => (int) ($currency['cp'] ?? 0),
      'sp' => (int) ($currency['sp'] ?? 0),
      'gp' => (int) ($currency['gp'] ?? 0),
      'pp' => (int) ($currency['pp'] ?? 0),
    ];
    $char_data['gold'] = (int) ($currency['gp'] ?? 0);
  }

  /**
   * Normalize currency deltas.
   */
  protected function normalizeCurrencyDelta(array $delta): array {
    return [
      'cp' => (int) ($delta['cp'] ?? 0),
      'sp' => (int) ($delta['sp'] ?? 0),
      'gp' => (int) ($delta['gp'] ?? 0),
      'pp' => (int) ($delta['pp'] ?? 0),
    ];
  }

  /**
   * Convert currency arrays to copper pieces.
   */
  protected function currencyToCopper(array $currency): int {
    $total = 0;
    foreach (self::CURRENCY_CP_VALUES as $denomination => $cp_value) {
      $total += ((int) ($currency[$denomination] ?? 0)) * $cp_value;
    }
    return $total;
  }

  /**
   * Convert copper pieces to normalized currency breakdown.
   */
  protected function copperToCurrency(int $total_cp): array {
    $remaining = max(0, $total_cp);
    $currency = ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0];
    foreach (['pp', 'gp', 'sp', 'cp'] as $denomination) {
      $cp_value = self::CURRENCY_CP_VALUES[$denomination];
      $currency[$denomination] = intdiv($remaining, $cp_value);
      $remaining %= $cp_value;
    }
    return $currency;
  }

  /**
   * Parse a currency descriptor from a string or array.
   */
  protected function parseCurrencyDescriptor($item): ?array {
    if (is_array($item) && isset($item['currency'], $item['amount'])) {
      $denomination = strtolower((string) $item['currency']);
      if (isset(self::CURRENCY_CP_VALUES[$denomination])) {
        return ['denomination' => $denomination, 'amount' => max(1, (int) $item['amount'])];
      }
    }

    if (!is_string($item)) {
      return NULL;
    }

    $value = strtolower(trim($item));
    if (preg_match('/^(\d+)\s*(pp|gp|sp|cp)$/', $value, $matches)) {
      return ['denomination' => $matches[2], 'amount' => (int) $matches[1]];
    }
    if (preg_match('/^(\d+)\s*(platinum|gold|silver|copper)(?:\s+pieces?)?$/', $value, $matches)) {
      $map = ['platinum' => 'pp', 'gold' => 'gp', 'silver' => 'sp', 'copper' => 'cp'];
      return ['denomination' => $map[$matches[2]], 'amount' => (int) $matches[1]];
    }

    return NULL;
  }

  /**
   * Normalize inventory descriptors.
   */
  protected function normalizeInventoryEntry($item): array {
    if (is_array($item)) {
      return [
        'name' => trim((string) ($item['name'] ?? $item['item_id'] ?? 'Unknown item')),
        'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
      ];
    }

    $value = trim((string) $item);
    if (preg_match('/^(\d+)\s*x?\s+(.+)$/i', $value, $matches)) {
      return ['name' => trim($matches[2]), 'quantity' => max(1, (int) $matches[1])];
    }

    return ['name' => $value, 'quantity' => 1];
  }

  /**
   * Determine whether inventory contains a required quantity.
   */
  protected function inventoryHasItemQuantity(array $char_data, string $item_name, int $required_quantity): bool {
    $available = 0;
    foreach ($this->extractInventoryItems($char_data) as $item) {
      $name = trim((string) ($item['name'] ?? ''));
      if (strcasecmp($name, $item_name) === 0) {
        $available += max(1, (int) ($item['quantity'] ?? 1));
      }
      if ($available >= $required_quantity) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Add an inventory entry to character data.
   */
  protected function addInventoryItemToCharacterData(array &$char_data, array $item): void {
    if (!isset($char_data['inventory']) || !is_array($char_data['inventory'])) {
      $char_data['inventory'] = [];
    }

    if (isset($char_data['inventory']['carried']) || isset($char_data['inventory']['worn']) || isset($char_data['inventory']['equipped']) || isset($char_data['inventory']['stashed'])) {
      if (!isset($char_data['inventory']['carried']) || !is_array($char_data['inventory']['carried'])) {
        $char_data['inventory']['carried'] = [];
      }
      $char_data['inventory']['carried'][] = $item;
      return;
    }

    $char_data['inventory'][] = $item;
  }

  /**
   * Remove an inventory entry from character data.
   */
  protected function removeInventoryItemFromCharacterData(array &$char_data, array $item): bool {
    $remaining_to_remove = max(1, (int) ($item['quantity'] ?? 1));
    $target_name = trim((string) ($item['name'] ?? ''));

    if (isset($char_data['inventory']['carried']) || isset($char_data['inventory']['worn']) || isset($char_data['inventory']['equipped']) || isset($char_data['inventory']['stashed'])) {
      return $this->removeInventoryItemFromStructuredInventory($char_data['inventory'], $target_name, $remaining_to_remove);
    }

    if (!isset($char_data['inventory']) || !is_array($char_data['inventory'])) {
      return FALSE;
    }

    foreach ($char_data['inventory'] as $key => &$existing_item) {
      if (!is_array($existing_item)) {
        continue;
      }
      $existing_name = trim((string) ($existing_item['name'] ?? ''));
      if (strcasecmp($existing_name, $target_name) !== 0) {
        continue;
      }
      $existing_qty = max(1, (int) ($existing_item['quantity'] ?? 1));
      if ($existing_qty > $remaining_to_remove) {
        $existing_item['quantity'] = $existing_qty - $remaining_to_remove;
        return TRUE;
      }
      unset($char_data['inventory'][$key]);
      $char_data['inventory'] = array_values($char_data['inventory']);
      return TRUE;
    }
    unset($existing_item);

    return FALSE;
  }

  /**
   * Remove item from structured inventory sections.
   */
  protected function removeInventoryItemFromStructuredInventory(array &$inventory, string $target_name, int $remaining_to_remove): bool {
    $sections = ['carried', 'equipped', 'stashed'];
    foreach ($sections as $section) {
      if (!isset($inventory[$section]) || !is_array($inventory[$section])) {
        $inventory[$section] = [];
      }
      if ($this->removeInventoryItemFromList($inventory[$section], $target_name, $remaining_to_remove)) {
        return TRUE;
      }
    }

    if (!isset($inventory['worn']) || !is_array($inventory['worn'])) {
      $inventory['worn'] = [];
    }
    if (!isset($inventory['worn']['weapons']) || !is_array($inventory['worn']['weapons'])) {
      $inventory['worn']['weapons'] = [];
    }
    if (!isset($inventory['worn']['accessories']) || !is_array($inventory['worn']['accessories'])) {
      $inventory['worn']['accessories'] = [];
    }

    if (!empty($inventory['worn']['weapons']) && $this->removeInventoryItemFromList($inventory['worn']['weapons'], $target_name, $remaining_to_remove)) {
      return TRUE;
    }
    if (!empty($inventory['worn']['accessories']) && $this->removeInventoryItemFromList($inventory['worn']['accessories'], $target_name, $remaining_to_remove)) {
      return TRUE;
    }
    if (!empty($inventory['worn']['armor']) && is_array($inventory['worn']['armor'])) {
      $armor_name = trim((string) ($inventory['worn']['armor']['name'] ?? ''));
      if (strcasecmp($armor_name, $target_name) === 0) {
        $inventory['worn']['armor'] = [];
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Remove item from a list section.
   */
  protected function removeInventoryItemFromList(?array &$items, string $target_name, int $remaining_to_remove): bool {
    if (!is_array($items)) {
      return FALSE;
    }

    foreach ($items as $key => &$existing_item) {
      if (!is_array($existing_item)) {
        continue;
      }
      $existing_name = trim((string) ($existing_item['name'] ?? ''));
      if (strcasecmp($existing_name, $target_name) !== 0) {
        continue;
      }
      $existing_qty = max(1, (int) ($existing_item['quantity'] ?? 1));
      if ($existing_qty > $remaining_to_remove) {
        $existing_item['quantity'] = $existing_qty - $remaining_to_remove;
      }
      else {
        unset($items[$key]);
        $items = array_values($items);
      }
      return TRUE;
    }
    unset($existing_item);

    return FALSE;
  }

  /**
   * Current HP getter for mixed schemas.
   */
  protected function getCurrentHp(array $char_data): int {
    return (int) ($char_data['hit_points']['current'] ?? $char_data['resources']['hitPoints']['current'] ?? $char_data['hit_points']['max'] ?? 0);
  }

  /**
   * Max HP getter for mixed schemas.
   */
  protected function getMaxHp(array $char_data): int {
    return (int) ($char_data['hit_points']['max'] ?? $char_data['resources']['hitPoints']['max'] ?? 0);
  }

  /**
   * Temp HP getter for mixed schemas.
   */
  protected function getTempHp(array $char_data): int {
    return (int) ($char_data['hit_points']['temp'] ?? $char_data['resources']['hitPoints']['temporary'] ?? 0);
  }

  /**
   * Set current HP for mixed schemas.
   */
  protected function setCurrentHp(array &$char_data, int $value): void {
    $char_data['hit_points']['current'] = $value;
    $char_data['resources']['hitPoints']['current'] = $value;
  }

  /**
   * Set temp HP for mixed schemas.
   */
  protected function setTempHp(array &$char_data, int $value): void {
    $char_data['hit_points']['temp'] = $value;
    $char_data['resources']['hitPoints']['temporary'] = $value;
  }

  /**
   * Hero point getter for mixed schemas.
   */
  protected function getHeroPoints(array $char_data): int {
    return (int) ($char_data['hero_points'] ?? $char_data['resources']['heroPoints']['current'] ?? 0);
  }

  /**
   * Hero point setter for mixed schemas.
   */
  protected function setHeroPoints(array &$char_data, int $value): void {
    $char_data['hero_points'] = $value;
    $char_data['resources']['heroPoints']['current'] = $value;
  }

  /**
   * Build full room inventory context for the GM system prompt.
   *
   * Collects NPCs, obstacles, hazards, traps, items on the ground,
   * environment tags, and active effects from dungeon_data and DB.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room ID within the dungeon.
   * @param array $room_meta
   *   Room metadata from dungeon_data['rooms'][$index].
   * @param array $dungeon_data
   *   Full dungeon_data payload.
   *
   * @return array
   *   Structured room inventory:
   *   - environment_tags: string[]
   *   - npcs: array[]
   *   - obstacles: array[]
   *   - hazards: array[]
   *   - traps: array[] (only detected ones)
   *   - items: array[]
   *   - active_effects: array[]
   */
  public function buildRoomInventory(int $campaign_id, string $room_id, array $room_meta, array $dungeon_data): array {
    $inventory = [
      'environment_tags' => [],
      'npcs' => [],
      'obstacles' => [],
      'hazards' => [],
      'traps' => [],
      'items' => [],
      'storage_owners' => [],
      'active_effects' => [],
    ];

    // Resolve the DB room_id slug. The caller may pass a dungeon_data UUID
    // (e.g. "7f2f1051-...") while dc_campaign_rooms stores a slug
    // (e.g. "tavern_entrance"). Try the exact value first, then fall back to
    // matching by campaign + room name, and finally first room for the campaign.
    $db_room_id = $room_id;
    try {
      $exists = $this->database->select('dc_campaign_rooms', 'r')
        ->fields('r', ['room_id'])
        ->condition('campaign_id', $campaign_id)
        ->condition('room_id', $room_id)
        ->range(0, 1)
        ->execute()
        ->fetchField();

      if ($exists === FALSE && !empty($room_meta['name'])) {
        // UUID didn't match — try by room name.
        $slug = $this->database->select('dc_campaign_rooms', 'r')
          ->fields('r', ['room_id'])
          ->condition('campaign_id', $campaign_id)
          ->condition('name', $room_meta['name'])
          ->range(0, 1)
          ->execute()
          ->fetchField();
        if ($slug !== FALSE) {
          $db_room_id = $slug;
        }
      }

      // Last resort: first room for this campaign.
      // If no room matched by UUID or name, fall back to the first room
      // (typically the tavern/starting area). When this fires it means the
      // party is somewhere unexpected; the $fallback_room flag below will
      // inject a GM note so Eldric/the starting NPC can acknowledge it.
      if ($db_room_id === $room_id && $exists === FALSE) {
        $first = $this->database->select('dc_campaign_rooms', 'r')
          ->fields('r', ['room_id'])
          ->condition('campaign_id', $campaign_id)
          ->range(0, 1)
          ->execute()
          ->fetchField();
        if ($first !== FALSE) {
          $db_room_id = $first;
          $inventory['_fallback_room'] = TRUE;
        }
      }
    }
    catch (\Exception $e) {
      // Proceed with original room_id.
    }

    // 1. Environment tags from static room definition.
    try {
      $room_row = $this->database->select('dc_campaign_rooms', 'r')
        ->fields('r', ['environment_tags', 'contents_data'])
        ->condition('campaign_id', $campaign_id)
        ->condition('room_id', $db_room_id)
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();

      if ($room_row) {
        $env_tags = json_decode($room_row['environment_tags'] ?? '', TRUE);
        if (is_array($env_tags)) {
          $inventory['environment_tags'] = $env_tags;
        }

        // Static contents_data (placed objects from room JSON).
        $static_contents = json_decode($room_row['contents_data'] ?? '', TRUE);
        $static_npc_names = [];
        if (is_array($static_contents)) {
          // Static NPCs.
          foreach ($static_contents['npcs'] ?? [] as $npc) {
            $npc_name = $npc['name'] ?? 'Unknown NPC';
            $inventory['npcs'][] = [
              'name' => $npc_name,
              'type' => $npc['type'] ?? '',
              'role' => $npc['role'] ?? 'neutral',
              'description' => $npc['description'] ?? '',
              'hp_status' => '',
              'team' => $npc['team'] ?? 'neutral',
              '_static' => TRUE,
            ];
            $static_npc_names[] = $npc_name;
          }
          // Static items (placed in the room definition).
          foreach ($static_contents['items'] ?? [] as $item) {
            $inventory['items'][] = [
              'name' => $item['name'] ?? 'Unknown Item',
              'description' => $item['description'] ?? '',
              'quantity' => 1,
            ];
          }
          // Static obstacles.
          foreach ($static_contents['obstacles'] ?? [] as $obs) {
            $inventory['obstacles'][] = [
              'name' => $obs['name'] ?? 'Unknown Object',
              'description' => $obs['description'] ?? '',
              'impassable' => !empty($obs['impassable']),
            ];
          }
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to load static room data for inventory: @error', ['@error' => $e->getMessage()]);
    }

    // 2. Runtime entities from dungeon_data (live state).
    // The authoritative entity list lives in the top-level dungeon_data['entities']
    // array, with each entity's placement.room_id indicating its room.
    // rooms[].entities is historically empty; merge both just in case.
    $room_entities = $room_meta['entities'] ?? [];
    $top_level_entities = $dungeon_data['entities'] ?? [];
    $entities_from_payload = [];
    foreach ($top_level_entities as $tle) {
      $tle_room = $tle['placement']['room_id'] ?? '';
      if ($tle_room === $room_id) {
        $entities_from_payload[] = $tle;
      }
    }
    // Merge room-level (legacy) and top-level entities, top-level first.
    $entities = array_merge($entities_from_payload, $room_entities);
    $runtime_entity_names = [];
    $all_known_names = $static_npc_names ?? [];

    foreach ($entities as $entity) {
      $name = $entity['state']['metadata']['display_name']
        ?? $entity['name']
        ?? 'Unknown';
      $type = $entity['type']
        ?? $entity['entity_type']
        ?? ($entity['entity_ref']['type'] ?? ($entity['entity_ref']['content_type'] ?? 'npc'));
      $description = $entity['description']
        ?? $entity['state']['metadata']['description']
        ?? '';
      $role = $entity['role'] ?? ($entity['state']['metadata']['role'] ?? '');
      $team = $entity['state']['metadata']['team'] ?? '';
      $is_hidden = !empty($entity['hidden']) || !empty($entity['state']['hidden']);
      $is_detected = !empty($entity['detected']) || !empty($entity['state']['detected']);

      // Skip non-interactive entity types the GM doesn't need to narrate.
      if (in_array($type, ['obstacle', 'player_character'], TRUE)) {
        continue;
      }

      // Skip completely hidden entities the party hasn't detected.
      if ($is_hidden && !$is_detected) {
        continue;
      }

      // HP status for encountered creatures.
      $hp_status = '';
      $stats = $entity['state']['metadata']['stats'] ?? $entity['stats'] ?? [];
      if (!empty($stats['hp_current']) && !empty($stats['hp_max'])) {
        $pct = round(($stats['hp_current'] / $stats['hp_max']) * 100);
        if ($pct >= 75) {
          $hp_status = 'healthy';
        }
        elseif ($pct >= 50) {
          $hp_status = 'hurt';
        }
        elseif ($pct >= 25) {
          $hp_status = 'bloodied';
        }
        else {
          $hp_status = 'near death';
        }
      }

      // Conditions on this entity.
      $conditions = $entity['state']['conditions'] ?? [];
      $cond_str = !empty($conditions) ? implode(', ', $conditions) : '';

      switch ($type) {
        case 'npc':
        case 'creature':
          $npc_entry = [
            'name' => $name,
            'type' => $entity['entity_ref']['content_id'] ?? $type,
            'role' => $role,
            'team' => $team,
            'description' => $description,
            'hp_status' => $hp_status,
            'entity_instance_id' => (string) ($entity['entity_instance_id'] ?? $entity['instance_id'] ?? $entity['id'] ?? ''),
            'owner_id' => (string) ($entity['character_id'] ?? $entity['id'] ?? $entity['entity_instance_id'] ?? ''),
          ];
          if ($cond_str) {
            $npc_entry['conditions'] = $cond_str;
          }
          $inventory['npcs'][] = $npc_entry;
          $runtime_owner_id = (string) ($entity['character_id'] ?? $entity['id'] ?? $entity['entity_instance_id'] ?? '');
          if ($runtime_owner_id !== '') {
            $inventory['storage_owners'][] = [
              'owner_id' => $runtime_owner_id,
              'owner_type' => 'character',
              'name' => $name,
            ];
          }
          $runtime_entity_names[] = $name;
          $all_known_names[] = $name;
          break;

        case 'obstacle':
          $inventory['obstacles'][] = [
            'name' => $name,
            'description' => $description,
            'impassable' => !empty($entity['impassable']),
          ];
          $runtime_entity_names[] = $name;
          $all_known_names[] = $name;
          break;

        case 'hazard':
          $inventory['hazards'][] = [
            'name' => $name,
            'description' => $description,
            'detected' => $is_detected,
          ];
          $runtime_entity_names[] = $name;
          $all_known_names[] = $name;
          break;

        case 'trap':
          // Only include detected traps.
          if ($is_detected) {
            $inventory['traps'][] = [
              'name' => $name,
              'description' => $description,
            ];
            $runtime_entity_names[] = $name;
            $all_known_names[] = $name;
          }
          break;
      }
    }

    // Deduplicate: remove static NPCs/obstacles that are also in the runtime
    // dungeon_data entities. Static entries from contents_data (source 1) are
    // superseded by richer runtime entries (source 2) that carry hp_status etc.
    // We track which indices are "static" (added before the entity loop).
    if (!empty($runtime_entity_names)) {
      $inventory['npcs'] = array_values(array_filter($inventory['npcs'], function ($npc) use ($runtime_entity_names) {
        // Remove static entries (from contents_data) whose name appears in
        // runtime entities. Static entries are identified by the '_source' marker.
        $is_static = !empty($npc['_static']);
        if ($is_static && in_array($npc['name'], $runtime_entity_names, TRUE)) {
          return FALSE;
        }
        return TRUE;
      }));
      $inventory['obstacles'] = array_values(array_filter($inventory['obstacles'], function ($obj) use ($runtime_entity_names) {
        return !in_array($obj['name'], $runtime_entity_names, TRUE);
      }));
    }
    // Strip internal _static markers before returning.
    $inventory['npcs'] = array_map(function ($npc) {
      unset($npc['_static']);
      return $npc;
    }, $inventory['npcs']);

    // 3. Runtime entity instances from dc_campaign_characters (NPC/hazard/trap records).
    try {
      $entity_rows = $this->database->select('dc_campaign_characters', 'e')
        ->fields('e', ['name', 'type', 'state_data', 'character_data'])
        ->condition('campaign_id', $campaign_id)
        ->condition('location_type', 'room')
        ->condition('location_ref', $db_room_id)
        ->execute()
        ->fetchAll();

      foreach ($entity_rows as $row) {
        $ename = $row->name ?? 'Unknown';
        $etype = $row->type ?? 'npc';
        // Skip if we already have this entity from contents_data or dungeon_data.
        if (in_array($ename, $all_known_names, TRUE)) {
          continue;
        }

        $estate = json_decode($row->state_data ?? '{}', TRUE) ?: [];
        $echar = json_decode($row->character_data ?? '{}', TRUE) ?: [];
        $is_hidden = !empty($estate['hidden']);
        $is_detected = !empty($estate['detected']);

        if ($is_hidden && !$is_detected) {
          continue;
        }

        $edesc = $echar['description'] ?? ($estate['description'] ?? '');

        switch ($etype) {
          case 'npc':
          case 'creature':
            $inventory['npcs'][] = [
              'name' => $ename,
              'type' => $etype,
              'role' => $echar['role'] ?? 'neutral',
              'description' => $edesc,
              'hp_status' => '',
              'entity_instance_id' => (string) ($estate['entityInstanceId'] ?? $estate['entity_instance_id'] ?? $echar['entity_instance_id'] ?? ''),
              'owner_id' => (string) ($echar['id'] ?? $estate['characterId'] ?? ''),
            ];
            $entity_owner_id = (string) ($echar['id'] ?? $estate['characterId'] ?? '');
            if ($entity_owner_id !== '') {
              $inventory['storage_owners'][] = [
                'owner_id' => $entity_owner_id,
                'owner_type' => 'character',
                'name' => $ename,
              ];
            }
            break;
          case 'hazard':
            $inventory['hazards'][] = [
              'name' => $ename,
              'description' => $edesc,
              'detected' => $is_detected,
            ];
            break;
          case 'trap':
            if ($is_detected) {
              $inventory['traps'][] = [
                'name' => $ename,
                'description' => $edesc,
              ];
            }
            break;
          case 'obstacle':
            $inventory['obstacles'][] = [
              'name' => $ename,
              'description' => $edesc,
              'impassable' => !empty($estate['impassable']),
            ];
            break;
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to load entity instances for room inventory: @error', ['@error' => $e->getMessage()]);
    }

    // 4. Items on the ground from dc_campaign_item_instances.
    try {
      $item_rows = $this->database->select('dc_campaign_item_instances', 'i')
        ->fields('i', ['item_id', 'item_instance_id', 'quantity', 'state_data'])
        ->condition('campaign_id', $campaign_id)
        ->condition('location_type', 'room')
        ->condition('location_ref', $db_room_id)
        ->execute()
        ->fetchAll();

      foreach ($item_rows as $irow) {
        $istate = json_decode($irow->state_data ?? '{}', TRUE) ?: [];
        $iname = $istate['name'] ?? $irow->item_id;
        $idesc = $istate['description'] ?? '';

        // Try to resolve the display name from the content registry if not in state_data.
        if ($iname === $irow->item_id) {
          try {
            $registry = $this->database->select('dc_campaign_content_registry', 'cr')
              ->fields('cr', ['schema_data'])
              ->condition('content_id', $irow->item_id)
              ->condition('content_type', 'item')
              ->range(0, 1)
              ->execute()
              ->fetchField();

            if ($registry) {
              $schema = json_decode($registry, TRUE) ?: [];
              $iname = $schema['name'] ?? $irow->item_id;
              if (!$idesc) {
                $idesc = $schema['description'] ?? '';
              }
            }
          }
          catch (\Exception $e) {
            // Swallow — name will remain item_id.
          }
        }

        $inventory['items'][] = [
          'item_instance_id' => $irow->item_instance_id,
          'name' => $iname,
          'description' => $idesc,
          'quantity' => (int) ($irow->quantity ?? 1),
        ];
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to load item instances for room inventory: @error', ['@error' => $e->getMessage()]);
    }

    // 5. Active effects from gameplay_state.
    $gameplay_state = $room_meta['gameplay_state'] ?? [];
    $inventory['active_effects'] = $gameplay_state['active_effects'] ?? [];

    if (!empty($db_room_id)) {
      $inventory['storage_owners'][] = [
        'owner_id' => (string) $db_room_id,
        'owner_type' => 'room',
        'name' => $room_meta['name'] ?? 'Current room',
      ];
    }

    $deduped_storage = [];
    foreach ($inventory['storage_owners'] as $owner) {
      $key = ($owner['owner_type'] ?? '') . ':' . ($owner['owner_id'] ?? '');
      if ($key === ':' || isset($deduped_storage[$key])) {
        continue;
      }
      $deduped_storage[$key] = $owner;
    }
    $inventory['storage_owners'] = array_values($deduped_storage);

    return $inventory;
  }

}
