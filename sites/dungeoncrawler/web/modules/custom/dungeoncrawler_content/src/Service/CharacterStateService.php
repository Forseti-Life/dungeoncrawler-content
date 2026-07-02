<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\CharacterManager;

/**
 * Manages character state for real-time gameplay.
 * 
 * This service implements the CharacterState management system as designed in:
 * docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md
 * 
 * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#characterstate-service-pseudocode
 */
class CharacterStateService {

  protected Connection $database;
  protected AccountProxyInterface $currentUser;
  protected FeatEffectManager $featEffectManager;

  /**
   * Constructor.
   */
  public function __construct(Connection $database, AccountProxyInterface $current_user, FeatEffectManager $feat_effect_manager) {
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->featEffectManager = $feat_effect_manager;
  }

  /**
   * Get current character state.
   * 
   * @param string $character_id
   *   The character ID.
   * 
   * @return array
   *   Character state array matching CharacterState interface.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#characterstate-object
   */
  public function getState(string $character_id, ?int $campaign_id = NULL, ?string $instance_id = NULL): array {
    $record = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('id', $character_id)
      ->execute()
      ->fetchObject();

    if (!$record) {
      throw new \InvalidArgumentException("Character not found: {$character_id}");
    }

    // Parse library payloads (defaults + overrides).
    $character_data = json_decode($record->character_data, TRUE) ?? [];
    $default_data = json_decode($record->default_character_data ?? '', TRUE);
    if (!is_array($default_data)) {
      $default_data = [];
    }
    $merged_library = array_replace_recursive($default_data, $character_data);
    
    $type = $record->type ?? ($merged_library['type'] ?? 'pc');

    // Build CharacterState structure. For PCs we hydrate the sheet; for other types
    // (npc/obstacle/trap/hazard) we expose the full library payload under npcDefinition/statePayload.
    if ($type === 'pc') {
      $state = [
        'characterId' => (string) $record->id,
        'userId' => (string) $record->uid,
        'campaignId' => $merged_library['campaignId'] ?? NULL,
        'instanceId' => $merged_library['instanceId'] ?? NULL,
        'type' => $type,

        'basicInfo' => [
          'name' => $merged_library['basicInfo']['name'] ?? $record->name,
          'level' => (int) ($merged_library['basicInfo']['level'] ?? $record->level),
          'experiencePoints' => $merged_library['basicInfo']['experiencePoints'] ?? ($merged_library['experiencePoints'] ?? 0),
          'ancestry' => $merged_library['basicInfo']['ancestry'] ?? $record->ancestry,
          'heritage' => $merged_library['basicInfo']['heritage'] ?? ($merged_library['heritage'] ?? ''),
          'background' => $merged_library['basicInfo']['background'] ?? ($merged_library['background'] ?? ''),
          'class' => $merged_library['basicInfo']['class'] ?? $record->class,
          'alignment' => $merged_library['basicInfo']['alignment'] ?? ($merged_library['alignment'] ?? ''),
          'deity' => $merged_library['basicInfo']['deity'] ?? ($merged_library['deity'] ?? NULL),
          'age' => $merged_library['basicInfo']['age'] ?? ($merged_library['age'] ?? NULL),
          'appearance' => $merged_library['basicInfo']['appearance'] ?? ($merged_library['appearance'] ?? NULL),
          'personality' => $merged_library['basicInfo']['personality'] ?? ($merged_library['personality'] ?? NULL),
        ],

        'abilities' => $merged_library['abilities'] ?? [
          'strength' => 10,
          'dexterity' => 10,
          'constitution' => 10,
          'intelligence' => 10,
          'wisdom' => 10,
          'charisma' => 10,
        ],

        'resources' => $merged_library['resources'] ?? [
          'hitPoints' => [
            'current' => $merged_library['hitPoints']['current'] ?? 0,
            'max' => $merged_library['hitPoints']['max'] ?? 0,
            'temporary' => 0,
          ],
          'heroPoints' => ['current' => 1, 'max' => 3],
        ],

        'defenses' => $merged_library['defenses'] ?? [],
        'conditions' => $merged_library['conditions'] ?? [],
        'actions' => $merged_library['actions'] ?? [
          'threeActionEconomy' => [
            'actionsRemaining' => 3,
            'reactionAvailable' => TRUE,
          ],
          'availableActions' => [],
        ],
        'spells' => $merged_library['spells'] ?? [],
        'skills' => $merged_library['skills'] ?? [],
        'inventory' => $merged_library['inventory'] ?? [
          'worn' => ['weapons' => [], 'accessories' => []],
          'carried' => [],
          'currency' => ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0],
          'totalBulk' => 0,
          'encumbrance' => 'unencumbered',
        ],
        'features' => $merged_library['features'] ?? [
          'ancestryFeatures' => [],
          'classFeatures' => [],
          'feats' => (is_array($merged_library['feats'] ?? NULL) ? $merged_library['feats'] : []),
        ],

        // Ancestry creature traits — auto-assigned at creation, persisted in character_data.
        // Falls back to ancestry defaults if traits were not stored (e.g. legacy characters).
        'traits' => $this->resolveCharacterTraits($merged_library),

        'metadata' => [
          'createdAt' => date('c', $record->created),
          'updatedAt' => date('c', $record->changed),
          'lastSyncedAt' => date('c'),
          'version' => $merged_library['version'] ?? 0,
        ],
      ];
    }
    else {
      // Non-PC entities: return the full library payload under npcDefinition so NPC/obstacle/trap/hazard
      // structures (including influence/relationship frameworks) are preserved end-to-end.
      $npc_definition = $merged_library;
      $state = [
        'characterId' => (string) $record->id,
        'userId' => (string) $record->uid,
        'campaignId' => $merged_library['campaignId'] ?? NULL,
        'instanceId' => $merged_library['instanceId'] ?? NULL,
        'type' => $type,
        'npcDefinition' => $npc_definition,
        'metadata' => [
          'createdAt' => date('c', $record->created),
          'updatedAt' => date('c', $record->changed),
          'lastSyncedAt' => date('c'),
          'version' => $merged_library['version'] ?? 0,
        ],
      ];
    }

    // If campaign runtime state exists, layer it over the library defaults.
    $campaign_row = $this->loadCampaignCharacter($campaign_id, $instance_id, (int) $character_id);
    if ($campaign_row) {
      $campaign_state = json_decode($campaign_row['state_data'] ?? '', TRUE);
      if (is_array($campaign_state) && !empty($campaign_state)) {
        $state = array_replace_recursive($state, $campaign_state);
      }

      $state['campaignId'] = (string) $campaign_row['campaign_id'];
      $state['instanceId'] = $campaign_row['instance_id'];
      $state['location'] = [
        'type' => $campaign_row['location_type'] ?? 'global',
        'ref' => $campaign_row['location_ref'] ?? '',
      ];
      $state['metadata']['version'] = (int) ($campaign_row['updated'] ?? 0);
      $state['metadata']['updatedAt'] = $campaign_row['updated'] ? date('c', (int) $campaign_row['updated']) : date('c');
    }

    // Ensure feat effects are reflected in returned state shape.
    $state = $this->applyFeatEffectsToState($state);

    return $state;
  }

  /**
   * Replace and persist full character state with optional optimistic lock.
   *
   * @param string $character_id
   *   Character ID.
   * @param array $state
   *   Incoming state payload (must contain basicInfo and metadata.version).
   * @param int|null $expected_version
   *   When provided, enforces optimistic locking against current version.
   *
   * @return array
   *   Fresh state after persistence.
   *
   * @throws \InvalidArgumentException
   *   On version conflict or invalid payload.
   */
  public function setState(string $character_id, array $state, ?int $expected_version = NULL, ?int $campaign_id = NULL, ?string $instance_id = NULL): array {
    // Prefer campaign-scoped runtime row when available.
    $campaign_row = $this->loadCampaignCharacter($campaign_id, $instance_id, (int) $character_id);

    if ($campaign_row) {
      $current_version = (int) ($campaign_row['updated'] ?? 0);
      if ($expected_version !== NULL && $expected_version !== $current_version) {
        throw new \InvalidArgumentException('Version conflict', 409);
      }

      $state['characterId'] = (string) $character_id;
      $state['campaignId'] = (string) $campaign_row['campaign_id'];
      $state['instanceId'] = $campaign_row['instance_id'];

      $this->saveState($character_id, $state, $campaign_row);
      return $this->getState($character_id, (int) $campaign_row['campaign_id'], $campaign_row['instance_id']);
    }

    // Library-only fallback (PCs not attached to a campaign yet).
    $current = $this->getState($character_id);
    $current_version = (int) ($current['metadata']['version'] ?? 0);

    if ($expected_version !== NULL && $expected_version !== $current_version) {
      throw new \InvalidArgumentException('Version conflict', 409);
    }

    $state['characterId'] = (string) $character_id;
    $state['userId'] = $current['userId'];
    $state['basicInfo'] = $state['basicInfo'] ?? $current['basicInfo'];
    $state['metadata'] = $state['metadata'] ?? [];

    $this->saveState($character_id, $state, NULL);

    return $this->getState($character_id);
  }

  /**
   * Update hit points.
   * 
   * @param string $character_id
   *   The character ID.
   * @param int $delta
   *   HP change (positive for healing, negative for damage).
   * @param bool $temporary
   *   Whether this affects temporary HP.
   * 
   * @return array
   *   Updated HP values.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#update-hit-points
   */
  public function updateHitPoints(string $character_id, int $delta, bool $temporary = FALSE): array {
    $state = $this->getState($character_id);
    
    if ($temporary) {
      // Temporary HP doesn't stack - take the higher value
      $new_temp_hp = max($state['resources']['hitPoints']['temporary'] ?? 0, $delta);
      $state['resources']['hitPoints']['temporary'] = $new_temp_hp;
    }
    else {
      // Update current HP with bounds checking
      $current = $state['resources']['hitPoints']['current'];
      $max = $state['resources']['hitPoints']['max'];
      
      $new_current = $current + $delta;
      // Cap between 0 and max HP
      $new_current = max(0, min($max, $new_current));
      
      $state['resources']['hitPoints']['current'] = $new_current;
    }
    
    // Save updated state
    $this->saveState($character_id, $state);
    
    return $state['resources']['hitPoints'];
  }

  /**
   * Add condition to character.
   * 
   * @param string $character_id
   *   The character ID.
   * @param array $condition
   *   Condition data matching Condition interface.
   * 
   * @return array
   *   All active conditions.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#add-condition-to-character
   */
  public function addCondition(string $character_id, array $condition): array {
    $state = $this->getState($character_id);
    
    // Add required fields if not present
    if (empty($condition['id'])) {
      $condition['id'] = uniqid('cond_', TRUE);
    }
    if (empty($condition['appliedAt'])) {
      $condition['appliedAt'] = date('c');
    }
    
    // Add condition to state
    $state['conditions'][] = $condition;
    
    // Save updated state
    $this->saveState($character_id, $state);
    
    return $state['conditions'];
  }

  /**
   * Remove condition from character.
   * 
   * @param string $character_id
   *   The character ID.
   * @param string $condition_id
   *   The condition ID to remove.
   * 
   * @return array
   *   Remaining active conditions.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#remove-condition-from-character
   */
  public function removeCondition(string $character_id, string $condition_id): array {
    $state = $this->getState($character_id);
    
    // Filter out the condition with matching ID
    $state['conditions'] = array_values(array_filter(
      $state['conditions'],
      function ($condition) use ($condition_id) {
        return $condition['id'] !== $condition_id;
      }
    ));
    
    // Save updated state
    $this->saveState($character_id, $state);
    
    return $state['conditions'];
  }

  /**
   * Cast a spell (consume slot or focus point).
   * 
   * @param string $character_id
   *   The character ID.
   * @param string $spell_id
   *   The spell ID.
   * @param int $level
   *   Spell level.
   * @param bool $is_focus_spell
   *   Whether this is a focus spell.
   * 
   * @return array
   *   Updated spell slot/focus point data.
   * 
   * @throws \InvalidArgumentException
   *   If no spell slots/focus points available.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#cast-a-spell-consume-slot-or-focus-point
   */
  public function castSpell(string $character_id, string $spell_id, int $level, bool $is_focus_spell = FALSE): array {
    $state = $this->getState($character_id);
    
    if ($is_focus_spell) {
      // Check and consume focus point
      $current = $state['resources']['focusPoints']['current'] ?? 0;
      if ($current <= 0) {
        throw new \InvalidArgumentException('No focus points remaining');
      }
      $state['resources']['focusPoints']['current'] = $current - 1;
      
      $result = [
        'level' => 'focus',
        'remaining' => $state['resources']['focusPoints']['current'],
      ];
    }
    else {
      // Check and consume spell slot
      $slot_key = (string) $level;
      $current = $state['resources']['spellSlots'][$slot_key]['current'] ?? 0;
      if ($current <= 0) {
        throw new \InvalidArgumentException("No level {$level} spell slots remaining");
      }
      $state['resources']['spellSlots'][$slot_key]['current'] = $current - 1;
      
      $result = [
        'level' => $level,
        'remaining' => $state['resources']['spellSlots'][$slot_key]['current'],
      ];
    }
    
    // Save updated state
    $this->saveState($character_id, $state);
    
    return $result;
  }

  /**
   * Use an action (track three-action economy).
   * 
   * @param string $character_id
   *   The character ID.
   * @param int $action_cost
   *   Number of actions to consume (1-3).
   * 
   * @return array
   *   Updated action economy state.
   * 
   * @throws \InvalidArgumentException
   *   If not enough actions remaining.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#use-an-action-track-three-action-economy
   */
  public function useAction(string $character_id, int $action_cost = 1): array {
    $state = $this->getState($character_id);
    
    $actions_remaining = $state['actions']['threeActionEconomy']['actionsRemaining'] ?? 0;
    if ($actions_remaining < $action_cost) {
      throw new \InvalidArgumentException("Not enough actions remaining (need {$action_cost}, have {$actions_remaining})");
    }
    
    $state['actions']['threeActionEconomy']['actionsRemaining'] = $actions_remaining - $action_cost;
    
    // Save updated state
    $this->saveState($character_id, $state);
    
    return $state['actions']['threeActionEconomy'];
  }

  /**
   * Use reaction.
   * 
   * @param string $character_id
   *   The character ID.
   * 
   * @return array
   *   Updated reaction state.
   * 
   * @throws \InvalidArgumentException
   *   If reaction already used.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#use-reaction
   */
  public function useReaction(string $character_id): array {
    $state = $this->getState($character_id);
    
    if (empty($state['actions']['threeActionEconomy']['reactionAvailable'])) {
      throw new \InvalidArgumentException('Reaction already used');
    }
    
    $state['actions']['threeActionEconomy']['reactionAvailable'] = FALSE;
    
    // Save updated state
    $this->saveState($character_id, $state);
    
    return $state['actions']['threeActionEconomy'];
  }

  /**
   * Start new turn (reset actions and reaction).
   * 
   * @param string $character_id
   *   The character ID.
   * 
   * @return array
   *   Reset action economy state.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#start-new-turn-reset-actions-and-reaction
   */
  public function startNewTurn(string $character_id): array {
    $state = $this->getState($character_id);
    
    // Reset action economy
    $state['actions']['threeActionEconomy']['actionsRemaining'] = 3;
    $state['actions']['threeActionEconomy']['reactionAvailable'] = TRUE;
    
    // Update condition durations (decrement round-based durations)
    $updated_conditions = [];
    foreach ($state['conditions'] as $condition) {
      if (!empty($condition['duration']) && $condition['duration']['type'] === 'rounds') {
        $condition['duration']['value'] = max(0, ($condition['duration']['value'] ?? 1) - 1);
        // Only keep conditions with duration remaining
        if ($condition['duration']['value'] > 0) {
          $updated_conditions[] = $condition;
        }
      }
      else {
        // Keep conditions without round-based duration
        $updated_conditions[] = $condition;
      }
    }
    $state['conditions'] = $updated_conditions;
    
    // Save updated state
    $this->saveState($character_id, $state);
    
    return $state['actions']['threeActionEconomy'];
  }

  /**
   * Update inventory (add, remove, equip items).
   * 
   * @param string $character_id
   *   The character ID.
   * @param string $action
   *   Action: 'add', 'remove', 'equip', 'unequip'.
   * @param array $item
   *   Item data matching Item interface.
   * 
   * @return array
   *   Updated inventory state including bulk calculation.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#update-inventory-add-remove-equip-items
   */
  public function updateInventory(string $character_id, string $action, array $item): array {
    $state = $this->getState($character_id);
    
    switch ($action) {
      case 'add':
        $state['inventory']['carried'][] = $item;
        break;
        
      case 'remove':
        $state['inventory']['carried'] = array_values(array_filter(
          $state['inventory']['carried'],
          function ($i) use ($item) {
            return $i['id'] !== $item['id'];
          }
        ));
        break;
        
      case 'equip':
        // Remove from carried
        $state['inventory']['carried'] = array_values(array_filter(
          $state['inventory']['carried'],
          function ($i) use ($item) {
            return $i['id'] !== $item['id'];
          }
        ));
        // Add to worn
        if ($item['type'] === 'weapon') {
          $state['inventory']['worn']['weapons'][] = $item;
        }
        elseif ($item['type'] === 'armor') {
          $state['inventory']['worn']['armor'] = $item;
        }
        else {
          $state['inventory']['worn']['accessories'][] = $item;
        }
        break;
        
      case 'unequip':
        // Remove from worn and add to carried
        if ($item['type'] === 'weapon') {
          $state['inventory']['worn']['weapons'] = array_values(array_filter(
            $state['inventory']['worn']['weapons'],
            function ($i) use ($item) {
              return $i['id'] !== $item['id'];
            }
          ));
        }
        elseif ($item['type'] === 'armor' && !empty($state['inventory']['worn']['armor'])) {
          if ($state['inventory']['worn']['armor']['id'] === $item['id']) {
            unset($state['inventory']['worn']['armor']);
          }
        }
        else {
          $state['inventory']['worn']['accessories'] = array_values(array_filter(
            $state['inventory']['worn']['accessories'] ?? [],
            function ($i) use ($item) {
              return $i['id'] !== $item['id'];
            }
          ));
        }
        $state['inventory']['carried'][] = $item;
        break;
    }
    
    // Recalculate bulk
    $bulk_data = $this->calculateBulk($state);
    $state['inventory']['totalBulk'] = $bulk_data['totalBulk'];
    $state['inventory']['encumbrance'] = $bulk_data['encumbrance'];
    
    // Save updated state
    $this->saveState($character_id, $state);
    
    return $state['inventory'];
  }

  /**
   * Gain experience points.
   * 
   * @param string $character_id
   *   The character ID.
   * @param int $xp
   *   Experience points to add.
   * 
   * @return array
   *   Updated XP and level up status.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#gain-experience-points
   */
  public function gainExperience(string $character_id, int $xp): array {
    $state = $this->getState($character_id);
    
    // Add XP
    $current_xp = $state['basicInfo']['experiencePoints'] + $xp;
    $state['basicInfo']['experiencePoints'] = $current_xp;
    
    // Check if level up is available
    $current_level = $state['basicInfo']['level'];
    $level_up_available = $this->isLevelUpAvailable($current_level, $current_xp);
    $xp_to_next_level = (1000 * $current_level) - $current_xp;
    
    // Save updated state
    $this->saveState($character_id, $state);
    
    return [
      'experiencePoints' => $current_xp,
      'level' => $current_level,
      'levelUpAvailable' => $level_up_available,
      'xpToNextLevel' => max(0, $xp_to_next_level),
    ];
  }

  /**
   * Apply optimistic update operation.
   * 
   * @param string $character_id
   *   The character ID.
   * @param array $operation
   *   Update operation with type, path, value, version.
   * 
   * @return array
   *   Result with success status and new version.
   * 
   * @throws \InvalidArgumentException
   *   If version conflict occurs.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#process-queued-updates-batch-send-to-server
   */
  public function applyUpdate(string $character_id, array $operation): array {
    // TODO: Implement optimistic locking
    // - Check operation['version'] matches current version
    // - Apply update to database
    // - Increment version
    // - Return new version
    // - Broadcast to WebSocket subscribers
    throw new \InvalidArgumentException('Not implemented');
  }

  /**
   * Recalculate bulk and encumbrance.
   * 
   * @param string $character_id
   *   The character ID.
   * 
   * @return array
   *   Bulk and encumbrance data.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#recalculate-total-bulk-and-encumbrance
   */
  protected function recalculateBulk(string $character_id): array {
    $state = $this->getState($character_id);
    return $this->calculateBulk($state);
  }

  /**
   * Calculate bulk from inventory state.
   * 
   * @param array $state
   *   The character state.
   * 
   * @return array
   *   Bulk and encumbrance data.
   */
  protected function calculateBulk(array $state): array {
    $total_bulk = 0;
    
    // Add bulk from worn armor
    if (!empty($state['inventory']['worn']['armor'])) {
      $total_bulk += $state['inventory']['worn']['armor']['bulk'] ?? 0;
    }
    
    // Add bulk from worn weapons
    foreach ($state['inventory']['worn']['weapons'] ?? [] as $weapon) {
      $total_bulk += $weapon['bulk'] ?? 0;
    }
    
    // Add bulk from worn accessories
    foreach ($state['inventory']['worn']['accessories'] ?? [] as $accessory) {
      $total_bulk += $accessory['bulk'] ?? 0;
    }
    
    // Add bulk from carried items
    foreach ($state['inventory']['carried'] ?? [] as $item) {
      $total_bulk += ($item['bulk'] ?? 0) * ($item['quantity'] ?? 1);
    }
    
    // Calculate encumbrance based on STR
    $str_score = $state['abilities']['strength'] ?? 10;
    $encumbered_at = 5 + $str_score;
    $overloaded_at = 10 + $str_score;
    
    if ($total_bulk >= $overloaded_at) {
      $encumbrance = 'overloaded';
    }
    elseif ($total_bulk >= $encumbered_at) {
      $encumbrance = 'encumbered';
    }
    else {
      $encumbrance = 'unencumbered';
    }
    
    return [
      'totalBulk' => $total_bulk,
      'encumbrance' => $encumbrance,
    ];
  }

  /**
   * Check if character has enough XP to level up.
   * 
   * @param int $current_level
   *   Current character level.
   * @param int $current_xp
   *   Current experience points.
   * 
   * @return bool
   *   TRUE if level up is available.
   * 
   * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#check-if-character-has-enough-xp-to-level-up
   */
  protected function isLevelUpAvailable(int $current_level, int $current_xp): bool {
    // PF2E XP table: 1000 XP per level (simplified)
    $xp_for_next_level = 1000 * $current_level;
    return $current_xp >= $xp_for_next_level;
  }

  /**
   * Save character state to database.
   * 
   * @param string $character_id
   *   The character ID.
   * @param array $state
   *   The character state array.
   * 
   * @return void
   */
  protected function saveState(string $character_id, array $state, ?array $campaign_row = NULL): void {
    $campaign_row = $campaign_row ?? $this->loadCampaignCharacter(NULL, NULL, (int) $character_id);
    $now = time();

    // Keep feat-derived effects authoritative in persisted JSON.
    $state = $this->applyFeatEffectsToState($state);

    $type = $state['type'] ?? ($campaign_row['type'] ?? 'pc');

    // Extract fields for columns with fallbacks for non-PC entities.
    if ($type === 'pc') {
      $name = $state['basicInfo']['name'] ?? '';
      $level = $state['basicInfo']['level'] ?? 0;
      $ancestry = $state['basicInfo']['ancestry'] ?? '';
      $class = $state['basicInfo']['class'] ?? '';
    }
    else {
      $npc_def = $state['npcDefinition'] ?? [];
      $name = $npc_def['id'] ?? ($state['basicInfo']['name'] ?? '');
      $level = $npc_def['level'] ?? ($state['basicInfo']['level'] ?? 0);
      $ancestry = $state['basicInfo']['ancestry'] ?? '';
      $class = $state['basicInfo']['class'] ?? '';
    }

    $resources = is_array($state['resources'] ?? NULL) ? $state['resources'] : [];
    $hitPoints = is_array($resources['hitPoints'] ?? NULL) ? $resources['hitPoints'] : [];
    $defenses = is_array($state['defenses'] ?? NULL) ? $state['defenses'] : [];
    $armorClassState = is_array($defenses['armorClass'] ?? NULL) ? $defenses['armorClass'] : [];
    $position = is_array($state['position'] ?? NULL) ? $state['position'] : [];
    $location = is_array($state['location'] ?? NULL) ? $state['location'] : [];

    $hpCurrent = (int) ($hitPoints['current'] ?? 0);
    $hpMax = (int) ($hitPoints['max'] ?? 0);
    $armorClass = (int) ($armorClassState['total'] ?? ($armorClassState['value'] ?? 10));
    $experiencePoints = (int) ($state['basicInfo']['experiencePoints'] ?? 0);
    $positionQ = (int) ($position['q'] ?? 0);
    $positionR = (int) ($position['r'] ?? 0);
    $lastRoomId = (string) ($location['roomId'] ?? ($state['roomId'] ?? ''));

    if ($campaign_row) {
      // Campaign-scoped runtime record
      $state['metadata']['version'] = $now;
      $state['metadata']['updatedAt'] = date('c', $now);
      $state['characterId'] = (string) $character_id;
      $state['campaignId'] = (string) $campaign_row['campaign_id'];
      $state['instanceId'] = $campaign_row['instance_id'];

      $this->database->update('dc_campaign_characters')
        ->fields([
          'state_data' => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
          'updated' => $now,
          'type' => $type,
          'hp_current' => $hpCurrent,
          'hp_max' => $hpMax,
          'armor_class' => $armorClass,
          'experience_points' => $experiencePoints,
          'position_q' => $positionQ,
          'position_r' => $positionR,
          'last_room_id' => $lastRoomId,
          'changed' => $now,
        ])
        ->condition('id', $campaign_row['id'])
        ->execute();

      // Keep library basics in sync for PCs/NPCs.
      $character_data = $state;
      unset($character_data['characterId']);
      unset($character_data['userId']);
      $this->database->update('dc_campaign_characters')
        ->fields([
          'name' => $name,
          'level' => $level,
          'ancestry' => $ancestry,
          'class' => $class,
          'type' => $type,
          'hp_current' => $hpCurrent,
          'hp_max' => $hpMax,
          'armor_class' => $armorClass,
          'experience_points' => $experiencePoints,
          'position_q' => $positionQ,
          'position_r' => $positionR,
          'last_room_id' => $lastRoomId,
          'character_data' => json_encode($character_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
          'changed' => $now,
        ])
        ->condition('id', $character_id)
        ->execute();

      return;
    }

    // Library-only record
    $state['metadata']['version'] = ($state['metadata']['version'] ?? 0) + 1;
    $state['metadata']['updatedAt'] = date('c');

    $character_data = $state;
    unset($character_data['characterId']);
    unset($character_data['userId']);

    $target_row = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['campaign_id'])
      ->condition('id', $character_id)
      ->execute()
      ->fetchAssoc();

    $is_campaign_instance_row = !empty($target_row) && ((int) ($target_row['campaign_id'] ?? 0) > 0);

    $update_fields = [
      'name' => $name,
      'level' => $level,
      'ancestry' => $ancestry,
      'class' => $class,
      'type' => $type,
      'hp_current' => $hpCurrent,
      'hp_max' => $hpMax,
      'armor_class' => $armorClass,
      'experience_points' => $experiencePoints,
      'position_q' => $positionQ,
      'position_r' => $positionR,
      'last_room_id' => $lastRoomId,
      'character_data' => json_encode($character_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
      'changed' => $now,
    ];

    if ($is_campaign_instance_row) {
      $update_fields['state_data'] = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
      $update_fields['updated'] = $now;
    }

    $this->database->update('dc_campaign_characters')
      ->fields($update_fields)
      ->condition('id', $character_id)
      ->execute();
  }

  /**
   * Load a campaign-scoped character row if it exists.
   */
  private function loadCampaignCharacter(?int $campaign_id, ?string $instance_id, int $character_id): ?array {
    $query = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id', 'campaign_id', 'character_id', 'instance_id', 'type', 'state_data', 'location_type', 'location_ref', 'updated'])
      ->condition('character_id', $character_id)
      ->condition('campaign_id', 0, '>');

    if ($campaign_id !== NULL) {
      $query->condition('campaign_id', $campaign_id);
    }

    if ($instance_id !== NULL) {
      $query->condition('instance_id', $instance_id);
    }

    $query->orderBy('updated', 'DESC');
    $query->range(0, 1);

    $row = $query->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  /**
   * Applies feat-derived effects into character state payload.
   */
  private function applyFeatEffectsToState(array $state): array {
    $features = is_array($state['features'] ?? NULL) ? $state['features'] : [];
    $feats = is_array($features['feats'] ?? NULL) ? $features['feats'] : [];

    $base_speed = (int) ($state['movement']['speed']['base'] ?? 25);
    $level = max(1, (int) ($state['basicInfo']['level'] ?? 1));

    $effects = $this->featEffectManager->buildEffectState([
      'level' => $level,
      'feats' => $feats,
      'feat_resources' => is_array($state['resources']['featResources'] ?? NULL) ? $state['resources']['featResources'] : [],
      'heritage' => $state['basicInfo']['heritage'] ?? '',
      'ancestry' => $state['basicInfo']['ancestry'] ?? '',
      'class_features' => is_array($features['classFeatures'] ?? NULL) ? $features['classFeatures'] : [],
    ], [
      'level' => $level,
      'base_speed' => $base_speed,
      'existing_hp_max' => (int) ($state['resources']['hitPoints']['max'] ?? 0),
    ]);

    $state['features']['featEffects'] = $effects;

    // Promote feat actions into canonical actions bucket.
    if (!isset($state['actions']) || !is_array($state['actions'])) {
      $state['actions'] = [];
    }
    if (!isset($state['actions']['availableActions']) || !is_array($state['actions']['availableActions'])) {
      $state['actions']['availableActions'] = [];
    }
    $state['actions']['availableActions']['feat'] = $effects['available_actions'] ?? [];

    // Persist feat resource counters for rest-cycle resets.
    if (!isset($state['resources']) || !is_array($state['resources'])) {
      $state['resources'] = [];
    }
    $state['resources']['featResources'] = [
      'perShortRest' => $effects['rest_resources']['per_short_rest'] ?? [],
      'perLongRest' => $effects['rest_resources']['per_long_rest'] ?? [],
    ];

    // Persist sense and spell augmentation effects.
    $state['senses'] = $effects['senses'] ?? [];
    if (!isset($state['spells']) || !is_array($state['spells'])) {
      $state['spells'] = [];
    }
    $state['spells']['featAugments'] = $effects['spell_augments'] ?? [];
    $state['features']['featTraining'] = $effects['training_grants'] ?? [
      'skills' => [],
      'lore' => [],
      'weapons' => [],
    ];
    $state['features']['featConditionalModifiers'] = $effects['conditional_modifiers'] ?? [
      'saving_throws' => [],
      'skills' => [],
      'movement' => [],
      'outcome_upgrades' => [],
    ];
    $state['features']['featSelectionGrants'] = $effects['selection_grants'] ?? [];
    $state['features']['featTodoReview'] = $effects['todo_review_features'] ?? [];

    // Apply selected core stat adjustments directly into state.
    $hp_bonus = (int) ($effects['derived_adjustments']['hp_max_bonus'] ?? 0);
    if (!isset($state['resources']['hitPoints']) || !is_array($state['resources']['hitPoints'])) {
      $state['resources']['hitPoints'] = ['current' => 0, 'max' => 0, 'temporary' => 0];
    }
    $base_hp_max = (int) ($state['resources']['hitPoints']['baseMax'] ?? $state['resources']['hitPoints']['max'] ?? 0);
    $state['resources']['hitPoints']['baseMax'] = $base_hp_max;
    $state['resources']['hitPoints']['max'] = $base_hp_max + $hp_bonus;
    $state['resources']['hitPoints']['current'] = min((int) ($state['resources']['hitPoints']['current'] ?? 0), (int) $state['resources']['hitPoints']['max']);

    if (!isset($state['movement']) || !is_array($state['movement'])) {
      $state['movement'] = [];
    }
    if (!isset($state['movement']['speed']) || !is_array($state['movement']['speed'])) {
      $state['movement']['speed'] = [];
    }
    $state['movement']['speed']['base'] = $base_speed;
    $state['movement']['speed']['total'] = (int) ($effects['derived_adjustments']['computed_speed'] ?? $base_speed);

    if (!isset($state['defenses']) || !is_array($state['defenses'])) {
      $state['defenses'] = [];
    }
    if (!isset($state['defenses']['initiative']) || !is_array($state['defenses']['initiative'])) {
      $state['defenses']['initiative'] = [];
    }
    $state['defenses']['initiative']['featBonus'] = (int) ($effects['derived_adjustments']['initiative_bonus'] ?? 0);
    if (!isset($state['defenses']['perception']) || !is_array($state['defenses']['perception'])) {
      $state['defenses']['perception'] = [];
    }
    $state['defenses']['perception']['featBonus'] = (int) ($effects['derived_adjustments']['perception_bonus'] ?? 0);

    return $state;
  }

  /**
   * Resolves a character's traits array from stored data or ancestry fallback.
   *
   * If the character_data has a stored 'traits' array (set at creation time),
   * it is returned directly. For legacy characters without stored traits, the
   * ancestry machine ID is used to derive traits from CharacterManager::ANCESTRIES.
   *
   * @param array $library
   *   The merged character library (default_data merged with character_data).
   *
   * @return string[]
   *   The character's canonical creature trait strings.
   */
  private function resolveCharacterTraits(array $library): array {
    if (!empty($library['traits']) && is_array($library['traits'])) {
      return $library['traits'];
    }
    $ancestry_machine_id = $library['basicInfo']['ancestry'] ?? ($library['ancestry'] ?? '');
    if ($ancestry_machine_id === '') {
      return [];
    }
    return CharacterManager::getAncestryTraits($ancestry_machine_id);
  }

}
