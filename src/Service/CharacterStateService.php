<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;

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
  protected GeneratedImageRepository $imageRepository;
  protected NumberGenerationService $numberGeneration;
  protected ImpactContractService $impactContractService;
  protected ActiveEffectStoreService $activeEffectStore;

  /**
   * Constructor.
   */
  public function __construct(Connection $database, AccountProxyInterface $current_user, FeatEffectManager $feat_effect_manager, GeneratedImageRepository $image_repository, NumberGenerationService $number_generation, ImpactContractService $impact_contract_service, ActiveEffectStoreService $active_effect_store) {
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->featEffectManager = $feat_effect_manager;
    $this->imageRepository = $image_repository;
    $this->numberGeneration = $number_generation;
    $this->impactContractService = $impact_contract_service;
    $this->activeEffectStore = $active_effect_store;
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
      $portrait_url = $this->resolvePortraitUrl($record, $campaign_id);
      $descriptors = $this->buildEntityDescriptors($merged_library, $type, (string) $record->name);
      $features = is_array($merged_library['features'] ?? NULL) ? $merged_library['features'] : [];
      $features = array_replace([
        'ancestryFeatures' => [],
        'classFeatures' => [],
        'feats' => [],
        'featSelections' => [],
      ], $features);
      if (is_array($merged_library['feats'] ?? NULL) && $merged_library['feats'] !== []) {
        $features['feats'] = $merged_library['feats'];
      }
      if (is_array($merged_library['feat_selections'] ?? NULL) && $merged_library['feat_selections'] !== []) {
        $features['featSelections'] = $merged_library['feat_selections'];
      }
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
          'backstory' => $merged_library['basicInfo']['backstory'] ?? ($merged_library['backstory'] ?? NULL),
        ],

        'abilities' => $merged_library['abilities'] ?? [
          'strength' => 10,
          'dexterity' => 10,
          'constitution' => 10,
          'intelligence' => 10,
          'wisdom' => 10,
          'charisma' => 10,
        ],

        'resources' => is_array($merged_library['resources'] ?? NULL) ? $merged_library['resources'] : [
          'hitPoints' => [
            'current' => (int) ($record->hp_current ?? 0),
            'max' => (int) ($record->hp_max ?? 0),
            'temporary' => 0,
          ],
          'heroPoints' => [
            'current' => 1,
            'max' => 3,
          ],
        ],

        'defenses' => is_array($merged_library['defenses'] ?? NULL) ? $merged_library['defenses'] : [
          'armorClass' => (int) ($record->armor_class ?? 0),
          'fortitude' => 0,
          'reflex' => 0,
          'will' => 0,
        ],
        'conditions' => is_array($merged_library['conditions'] ?? NULL) ? $merged_library['conditions'] : [],
        'actions' => $merged_library['actions'] ?? [
          'threeActionEconomy' => [
            'actionsRemaining' => 3,
            'reactionAvailable' => TRUE,
          ],
          'availableActions' => [],
        ],
        'spells' => $merged_library['spells'] ?? [],
        'skills' => is_array($merged_library['skills'] ?? NULL) ? $merged_library['skills'] : [],
        'inventory' => is_array($merged_library['inventory'] ?? NULL) ? $merged_library['inventory'] : [
          'worn' => ['weapons' => [], 'armor' => NULL, 'shield' => NULL, 'accessories' => []],
          'carried' => [],
          'currency' => ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0],
          'totalBulk' => 0,
          'encumbrance' => 'unencumbered',
        ],
        'features' => $features,
        'portrait_url' => $portrait_url,
        'portrait' => $portrait_url, // Legacy alias still read by older character-sheet callers.
        'descriptors' => $descriptors,
        'perception' => (int) ($merged_library['perception'] ?? 0),
        'speed' => (int) ($merged_library['speed'] ?? 25),

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
      $descriptors = $this->buildEntityDescriptors($merged_library, $type, (string) $record->name);
      $state = [
        'characterId' => (string) $record->id,
        'userId' => (string) $record->uid,
        'campaignId' => $merged_library['campaignId'] ?? NULL,
        'instanceId' => $merged_library['instanceId'] ?? NULL,
        'type' => $type,
        'descriptors' => $descriptors,
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

    $state = $this->resolveEffectiveState($state);

    return $state;
  }

  /**
   * Build a normalized cache-friendly descriptor block for an entity.
   */
  protected function buildEntityDescriptors(array $library_data, string $type, string $fallback_name = ''): array {
    $basic_info = is_array($library_data['basicInfo'] ?? NULL) ? $library_data['basicInfo'] : [];
    $profile = is_array($library_data['profile'] ?? NULL) ? $library_data['profile'] : [];
    $character_sheet = is_array($profile['character_sheet'] ?? NULL)
      ? $profile['character_sheet']
      : (is_array($library_data['character_sheet'] ?? NULL) ? $library_data['character_sheet'] : []);

    $name = trim((string) ($basic_info['name'] ?? $profile['display_name'] ?? $library_data['name'] ?? $fallback_name));
    // character_sheet.description is treated as a legacy visual-description field.
    $appearance = trim((string) ($basic_info['appearance'] ?? $profile['appearance'] ?? $character_sheet['appearance'] ?? $character_sheet['description'] ?? $library_data['appearance'] ?? ''));
    $personality = trim((string) ($basic_info['personality'] ?? $profile['personality_traits'] ?? $profile['personality'] ?? $library_data['personality'] ?? ''));
    $attitude = trim((string) ($profile['attitude'] ?? $library_data['attitude'] ?? ''));
    $motivations = trim((string) ($profile['motivations'] ?? $library_data['motivations'] ?? ''));
    $role = trim((string) ($profile['role'] ?? $library_data['role'] ?? ''));

    $summary_parts = array_values(array_filter([
      $name !== '' ? $name : NULL,
      $type === 'pc'
        ? trim(implode(' ', array_filter([
          (string) ($basic_info['ancestry'] ?? $library_data['ancestry'] ?? ''),
          (string) ($basic_info['class'] ?? $library_data['class'] ?? ''),
        ])))
        : $role,
      $appearance !== '' ? 'Appearance: ' . $appearance : NULL,
      $personality !== '' ? 'Personality: ' . $personality : NULL,
      $attitude !== '' ? 'Attitude: ' . $attitude : NULL,
      $motivations !== '' ? 'Motivations: ' . $motivations : NULL,
    ], static fn($value) => is_string($value) && trim($value) !== ''));

    return [
      'summary' => substr(implode('. ', $summary_parts), 0, 420),
      'appearance' => $appearance,
      'personality' => $personality,
      'attitude' => $attitude,
      'motivations' => $motivations,
      'role' => $role,
    ];
  }

  /**
   * Resolve the best available portrait URL for a character record.
   */
  protected function resolvePortraitUrl(object $record, ?int $campaign_id = NULL): ?string {
    $char_id = isset($record->id) ? (string) $record->id : '';
    $sheet_character_id = isset($record->character_id) ? (string) $record->character_id : '';
    if ($char_id === '' && $sheet_character_id === '') {
      return NULL;
    }

    $candidate_ids = array_values(array_unique(array_filter([$char_id, $sheet_character_id])));
    foreach ($candidate_ids as $candidate_id) {
      $portrait_rows = $this->imageRepository->loadImagesForObject(
        'dc_campaign_characters',
        $candidate_id,
        $campaign_id > 0 ? $campaign_id : NULL,
        'portrait',
        'original'
      );
      if (empty($portrait_rows) && $campaign_id > 0) {
        $portrait_rows = $this->imageRepository->loadImagesForObject(
          'dc_campaign_characters',
          $candidate_id,
          NULL,
          'portrait',
          'original'
        );
      }

      if (!empty($portrait_rows)) {
        $resolved = $this->imageRepository->resolveClientUrl($portrait_rows[0]);
        if (is_string($resolved) && $resolved !== '') {
          return $resolved;
        }
      }
    }

    return !empty($record->portrait) && is_string($record->portrait) ? $record->portrait : NULL;
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
  public function updateHitPoints(string $character_id, int $delta, bool $temporary = FALSE, ?int $campaign_id = NULL, ?string $instance_id = NULL): array {
    $state = $this->getState($character_id, $campaign_id, $instance_id);
    
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
    $this->saveState($character_id, $state, $this->loadCampaignCharacter($campaign_id, $instance_id, (int) $character_id));
    
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
  public function addCondition(string $character_id, array $condition, ?int $campaign_id = NULL, ?string $instance_id = NULL): array {
    $state = $this->getState($character_id, $campaign_id, $instance_id);
    
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
    $this->saveState($character_id, $state, $this->loadCampaignCharacter($campaign_id, $instance_id, (int) $character_id));
    
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
  public function removeCondition(string $character_id, string $condition_id, ?int $campaign_id = NULL, ?string $instance_id = NULL): array {
    $state = $this->getState($character_id, $campaign_id, $instance_id);
    
    // Filter out the condition with matching ID
    $state['conditions'] = array_values(array_filter(
      $state['conditions'],
      function ($condition) use ($condition_id) {
        return $condition['id'] !== $condition_id;
      }
    ));
    
    // Save updated state
    $this->saveState($character_id, $state, $this->loadCampaignCharacter($campaign_id, $instance_id, (int) $character_id));
    
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
  public function castSpell(string $character_id, string $spell_id, int $level, bool $is_focus_spell = FALSE, ?int $campaign_id = NULL, ?string $instance_id = NULL): array {
    $state = $this->getState($character_id, $campaign_id, $instance_id);
    
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
      if ($level <= 0) {
        return [
          'level' => 0,
          'remaining' => NULL,
          'consumed' => FALSE,
        ];
      }

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
    $this->saveState($character_id, $state, $this->loadCampaignCharacter($campaign_id, $instance_id, (int) $character_id));
    
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
  public function updateInventory(string $character_id, string $action, array $item, ?int $campaign_id = NULL, ?string $instance_id = NULL): array {
    $state = $this->getState($character_id, $campaign_id, $instance_id);
    
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

      case 'consume':
        $consumed = FALSE;
        $consume_collection = function (array $collection) use ($item, &$consumed): array {
          $collection = array_values(array_map(
            function ($inventory_item) use ($item, &$consumed) {
              $inventory_item_id = (string) ($inventory_item['id'] ?? $inventory_item['item_id'] ?? '');
              $target_item_id = (string) ($item['id'] ?? $item['item_id'] ?? '');
              $inventory_item_name = strtolower(trim((string) ($inventory_item['name'] ?? '')));
              $target_item_name = strtolower(trim((string) ($item['name'] ?? '')));
              $matches = $target_item_id !== ''
                ? ($inventory_item_id !== '' && $inventory_item_id === $target_item_id)
                : ($inventory_item_name !== '' && $inventory_item_name === $target_item_name);
              if ($consumed || !$matches) {
                return $inventory_item;
              }

              $quantity = (int) ($inventory_item['quantity'] ?? 1);
              if ($quantity > 1) {
                $inventory_item['quantity'] = $quantity - 1;
                $consumed = TRUE;
                return $inventory_item;
              }

              $consumed = TRUE;
              return NULL;
            },
            $collection
          ));

          return array_values(array_filter($collection));
        };

        $state['inventory']['carried'] = $consume_collection($state['inventory']['carried'] ?? []);
        if (!$consumed) {
          $state['inventory']['worn']['weapons'] = $consume_collection($state['inventory']['worn']['weapons'] ?? []);
        }
        if (!$consumed) {
          $state['inventory']['worn']['accessories'] = $consume_collection($state['inventory']['worn']['accessories'] ?? []);
        }
        if (!$consumed && !empty($state['inventory']['worn']['armor'])) {
          $consumed_armor = $consume_collection([$state['inventory']['worn']['armor']]);
          $state['inventory']['worn']['armor'] = !empty($consumed_armor) ? reset($consumed_armor) : NULL;
          if ($state['inventory']['worn']['armor'] === NULL) {
            unset($state['inventory']['worn']['armor']);
          }
        }
        if (!$consumed && !empty($state['inventory']['worn']['shield'])) {
          $consumed_shield = $consume_collection([$state['inventory']['worn']['shield']]);
          $state['inventory']['worn']['shield'] = !empty($consumed_shield) ? reset($consumed_shield) : NULL;
          if ($state['inventory']['worn']['shield'] === NULL) {
            unset($state['inventory']['worn']['shield']);
          }
        }
        $state['inventory']['carried'] = array_values(array_filter($state['inventory']['carried']));
        if (!$consumed) {
          throw new \InvalidArgumentException('Consumable item not found in inventory');
        }
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
        elseif ($item['type'] === 'shield') {
          $state['inventory']['worn']['shield'] = $item;
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
        elseif ($item['type'] === 'shield' && !empty($state['inventory']['worn']['shield'])) {
          if ($state['inventory']['worn']['shield']['id'] === $item['id']) {
            unset($state['inventory']['worn']['shield']);
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
    $this->saveState($character_id, $state, $this->loadCampaignCharacter($campaign_id, $instance_id, (int) $character_id));
    
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
  public function gainExperience(string $character_id, int $xp, ?int $campaign_id = NULL, ?string $instance_id = NULL): array {
    $state = $this->getState($character_id, $campaign_id, $instance_id);
    
    // Add XP
    $current_xp = $state['basicInfo']['experiencePoints'] + $xp;
    $state['basicInfo']['experiencePoints'] = $current_xp;
    
    // Check if level up is available
    $current_level = $state['basicInfo']['level'];
    $level_up_available = $this->isLevelUpAvailable($current_level, $current_xp);
    $xp_to_next_level = (1000 * $current_level) - $current_xp;
    
    // Save updated state
    $this->saveState($character_id, $state, $this->loadCampaignCharacter($campaign_id, $instance_id, (int) $character_id));
    
    return [
      'experiencePoints' => $current_xp,
      'level' => $current_level,
      'levelUpAvailable' => $level_up_available,
      'xpToNextLevel' => max(0, $xp_to_next_level),
    ];
  }

  /**
   * Apply canonical self-targeted consumable effects to character state.
   */
  public function applyConsumableEffects(string $character_id, array $item, ?int $campaign_id = NULL, ?string $instance_id = NULL): array {
    $state = $this->getState($character_id, $campaign_id, $instance_id);
    $campaign_row = $this->loadCampaignCharacter($campaign_id, $instance_id, (int) $character_id);
    $effects = [];
    $changed = FALSE;

    $healing = $this->resolveConsumableScalarValue([
      $item['healing']['amount'] ?? NULL,
      $item['healing_amount'] ?? NULL,
      $item['heal_amount'] ?? NULL,
      $item['healing'] ?? NULL,
      $item['effects']['healing'] ?? NULL,
      $item['effects']['heal_amount'] ?? NULL,
      $item['consumable_stats']['healing_amount'] ?? NULL,
      $item['consumable_stats']['effect'] ?? NULL,
      $item['effect'] ?? NULL,
      $item['description'] ?? NULL,
    ], ['hit point', 'hit points', 'hp', 'heal', 'healing', 'regain', 'restore']);
    if ($healing > 0) {
      $resources = is_array($state['resources'] ?? NULL) ? $state['resources'] : [];
      $hit_points = is_array($resources['hitPoints'] ?? NULL) ? $resources['hitPoints'] : ['current' => 0, 'max' => 0, 'temporary' => 0];
      $hit_points['current'] = max(0, min((int) ($hit_points['max'] ?? 0), (int) ($hit_points['current'] ?? 0) + $healing));
      $state['resources']['hitPoints'] = $hit_points;
      $effects['healing'] = [
        'amount' => $healing,
        'current' => $hit_points['current'],
        'max' => (int) ($hit_points['max'] ?? 0),
      ];
      $changed = TRUE;
    }

    $temporary_hit_points = $this->resolveConsumableScalarValue([
      $item['temporary_hp'] ?? NULL,
      $item['temp_hp'] ?? NULL,
      $item['effects']['temporary_hp'] ?? NULL,
      $item['effects']['temp_hp'] ?? NULL,
      $item['consumable_stats']['temporary_hp'] ?? NULL,
      $item['consumable_stats']['effect'] ?? NULL,
      $item['effect'] ?? NULL,
      $item['description'] ?? NULL,
    ], ['temporary hit points', 'temp hp', 'temporary hp']);
    if ($temporary_hit_points > 0) {
      $current_temporary = (int) ($state['resources']['hitPoints']['temporary'] ?? 0);
      $state['resources']['hitPoints']['temporary'] = max($current_temporary, $temporary_hit_points);
      $effects['temporary_hit_points'] = [
        'amount' => $temporary_hit_points,
        'current' => (int) $state['resources']['hitPoints']['temporary'],
      ];
      $changed = TRUE;
    }

    $hero_point_delta = $this->resolveConsumableScalarValue([
      $item['hero_points'] ?? NULL,
      $item['effects']['hero_points'] ?? NULL,
      $item['restore_hero_points'] ?? NULL,
      $item['consumable_stats']['hero_points'] ?? NULL,
    ]);
    if ($hero_point_delta > 0) {
      $hero_max = (int) ($state['resources']['heroPoints']['max'] ?? 3);
      $hero_current = (int) ($state['resources']['heroPoints']['current'] ?? 0);
      $state['resources']['heroPoints']['current'] = min($hero_max, $hero_current + $hero_point_delta);
      $effects['hero_points'] = [
        'delta' => $hero_point_delta,
        'current' => (int) $state['resources']['heroPoints']['current'],
        'max' => $hero_max,
      ];
      $changed = TRUE;
    }

    $focus_point_delta = $this->resolveConsumableScalarValue([
      $item['focus_points'] ?? NULL,
      $item['effects']['focus_points'] ?? NULL,
      $item['restore_focus_points'] ?? NULL,
      $item['consumable_stats']['focus_points'] ?? NULL,
    ]);
    if ($focus_point_delta > 0) {
      $focus_max = (int) ($state['resources']['focusPoints']['max'] ?? $focus_point_delta);
      $focus_current = (int) ($state['resources']['focusPoints']['current'] ?? 0);
      $state['resources']['focusPoints']['current'] = min($focus_max, $focus_current + $focus_point_delta);
      $state['resources']['focusPoints']['max'] = max($focus_max, (int) $state['resources']['focusPoints']['current']);
      $effects['focus_points'] = [
        'delta' => $focus_point_delta,
        'current' => (int) $state['resources']['focusPoints']['current'],
        'max' => (int) $state['resources']['focusPoints']['max'],
      ];
      $changed = TRUE;
    }

    $experience_delta = $this->resolveConsumableScalarValue([
      $item['experience_points'] ?? NULL,
      $item['xp'] ?? NULL,
      $item['effects']['experience_points'] ?? NULL,
      $item['effects']['xp'] ?? NULL,
    ]);
    if ($experience_delta > 0) {
      $state['basicInfo']['experiencePoints'] = (int) ($state['basicInfo']['experiencePoints'] ?? 0) + $experience_delta;
      $effects['experience_points'] = (int) $state['basicInfo']['experiencePoints'];
      $changed = TRUE;
    }

    $removed_conditions = $this->extractConsumableConditionNames($item, TRUE);
    if (!empty($removed_conditions) && is_array($state['conditions'] ?? NULL)) {
      $before = count($state['conditions']);
      $state['conditions'] = array_values(array_filter($state['conditions'], function ($condition) use ($removed_conditions) {
        $condition_data = is_array($condition) ? $condition : ['name' => (string) $condition];
        $condition_id = strtolower((string) ($condition_data['id'] ?? ''));
        $condition_name = strtolower((string) ($condition_data['name'] ?? $condition_data['condition'] ?? $condition_data['type'] ?? ''));
        foreach ($removed_conditions as $needle) {
          if ($needle !== '' && ($condition_id === $needle || $condition_name === $needle)) {
            return FALSE;
          }
        }
        return TRUE;
      }));
      if (count($state['conditions']) !== $before) {
        $effects['conditions_removed'] = $removed_conditions;
        $changed = TRUE;
      }
    }

    $added_conditions = $this->extractConsumableAddedConditions($item);
    if (!empty($added_conditions)) {
      if (!is_array($state['conditions'] ?? NULL)) {
        $state['conditions'] = [];
      }
      foreach ($added_conditions as $condition) {
        if (empty($condition['id'])) {
          $condition['id'] = uniqid('cond_', TRUE);
        }
        if (empty($condition['appliedAt'])) {
          $condition['appliedAt'] = date('c');
        }
        $state['conditions'][] = $condition;
      }
      $effects['conditions_added'] = array_map(static function (array $condition) {
        return $condition['name'] ?? $condition['condition'] ?? $condition['type'] ?? $condition['id'] ?? 'condition';
      }, $added_conditions);
      $changed = TRUE;
    }

    $restored_slots = $this->extractConsumableSpellSlotRestoration($item);
    if (!empty($restored_slots)) {
      $effects['spell_slots'] = [];
      foreach ($restored_slots as $slot_restore) {
        $slot_key = (string) ($slot_restore['level'] ?? '');
        $restore_amount = max(1, (int) ($slot_restore['amount'] ?? 1));
        if ($slot_key === '') {
          continue;
        }
        $current = (int) ($state['resources']['spellSlots'][$slot_key]['current'] ?? 0);
        $max = (int) ($state['resources']['spellSlots'][$slot_key]['max'] ?? $current);
        $next = min($max, $current + $restore_amount);
        $state['resources']['spellSlots'][$slot_key]['current'] = $next;
        $effects['spell_slots'][] = [
          'level' => (int) $slot_key,
          'restored' => $next - $current,
          'current' => $next,
          'max' => $max,
        ];
        $changed = $changed || $next !== $current;
      }
      if (empty($effects['spell_slots'])) {
        unset($effects['spell_slots']);
      }
    }

    $nutrition_days = $this->resolveProvisionDays($item, 'food');
    if ($nutrition_days > 0) {
      $state['days_without_food'] = 0;
      unset($state['starvation_damage_phase']);
      $effects['nutrition_days'] = $nutrition_days;
      $changed = TRUE;
    }

    $hydration_days = $this->resolveProvisionDays($item, 'water');
    if ($hydration_days > 0) {
      $state['days_without_water'] = 0;
      unset($state['thirst_damage_phase']);
      $effects['hydration_days'] = $hydration_days;
      $changed = TRUE;
    }

    if ($changed) {
      $this->saveState($character_id, $state, $campaign_row);
    }

    return $effects;
  }

  /**
   * Persist a player-triggered action in canonical character state.
   */
  public function recordPlayerAction(string $character_id, array $action, ?int $campaign_id = NULL, ?string $instance_id = NULL): array {
    $state = $this->getState($character_id, $campaign_id, $instance_id);
    $campaign_row = $this->loadCampaignCharacter($campaign_id, $instance_id, (int) $character_id);

    $entry = [
      'id' => uniqid('action_', TRUE),
      'type' => trim((string) ($action['type'] ?? 'action')),
      'name' => trim((string) ($action['name'] ?? $action['type'] ?? 'action')),
      'summary' => trim((string) ($action['summary'] ?? '')),
      'source' => trim((string) ($action['source'] ?? 'action_rail')),
      'performedAt' => date('c'),
      'payload' => is_array($action['payload'] ?? NULL) ? $action['payload'] : [],
    ];

    if ($entry['summary'] === '') {
      $entry['summary'] = sprintf('%s uses %s.', (string) ($state['basicInfo']['name'] ?? 'Character'), $entry['name']);
    }

    if (!isset($state['activity']) || !is_array($state['activity'])) {
      $state['activity'] = [];
    }
    $recent_actions = is_array($state['activity']['recentActions'] ?? NULL) ? $state['activity']['recentActions'] : [];
    $recent_actions[] = $entry;
    $state['activity']['recentActions'] = array_slice($recent_actions, -25);
    $state['metadata']['lastPlayerAction'] = $entry;

    $this->saveState($character_id, $state, $campaign_row);

    return $entry;
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
    if (!empty($state['inventory']['worn']['shield'])) {
      $total_bulk += $state['inventory']['worn']['shield']['bulk'] ?? 0;
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
   * Resolve a scalar consumable effect from raw values or inline text.
   */
  private function resolveConsumableScalarValue(array $candidates, array $required_terms = []): int {
    foreach ($candidates as $candidate) {
      if (is_numeric($candidate)) {
        return max(0, (int) $candidate);
      }
      if (!is_string($candidate)) {
        continue;
      }
      $text = trim($candidate);
      if ($text === '') {
        continue;
      }
      $normalized = strtolower($text);
      if (!empty($required_terms)) {
        $matched = FALSE;
        foreach ($required_terms as $term) {
          if (str_contains($normalized, strtolower($term))) {
            $matched = TRUE;
            break;
          }
        }
        if (!$matched) {
          continue;
        }
      }
      if (preg_match('/(\d+d\d+(?:\s*[+-]\s*\d+)?)/i', $text, $matches)) {
        $roll = $this->numberGeneration->rollExpression(str_replace(' ', '', $matches[1]));
        if (empty($roll['error'])) {
          return max(0, (int) ($roll['total'] ?? 0));
        }
      }
      if (preg_match('/\b(\d+)\b/', $text, $matches)) {
        return max(0, (int) $matches[1]);
      }
    }

    return 0;
  }

  /**
   * Extract conditions explicitly removed by an item.
   */
  private function extractConsumableConditionNames(array $item, bool $removal = FALSE): array {
    $names = [];
    $sources = $removal
      ? [
        $item['remove_condition'] ?? NULL,
        $item['remove_conditions'] ?? NULL,
        $item['removes_condition'] ?? NULL,
        $item['removes_conditions'] ?? NULL,
        $item['effects']['remove_condition'] ?? NULL,
        $item['effects']['remove_conditions'] ?? NULL,
        $item['effects']['removes_condition'] ?? NULL,
        $item['effects']['removes_conditions'] ?? NULL,
      ]
      : [];

    foreach ($sources as $source) {
      foreach ((array) $source as $value) {
        if (is_string($value) && trim($value) !== '') {
          $names[] = strtolower(trim($value));
        }
      }
    }

    $text_sources = [
      $item['consumable_stats']['effect'] ?? NULL,
      $item['effect'] ?? NULL,
      $item['description'] ?? NULL,
    ];
    foreach ($text_sources as $text) {
      if (!is_string($text) || trim($text) === '') {
        continue;
      }
      if (preg_match_all('/removes? (?:the )?([a-z][a-z _-]+?) condition/i', $text, $matches)) {
        foreach ($matches[1] as $match) {
          $names[] = strtolower(trim($match));
        }
      }
      if (preg_match_all('/cures? (?:the )?([a-z][a-z _-]+?)(?: condition|\\b)/i', $text, $matches)) {
        foreach ($matches[1] as $match) {
          $names[] = strtolower(trim($match));
        }
      }
    }

    return array_values(array_unique(array_filter($names)));
  }

  /**
   * Extract conditions explicitly granted by an item.
   */
  private function extractConsumableAddedConditions(array $item): array {
    $conditions = [];
    $sources = [
      $item['condition'] ?? NULL,
      $item['conditions'] ?? NULL,
      $item['add_condition'] ?? NULL,
      $item['add_conditions'] ?? NULL,
      $item['grant_condition'] ?? NULL,
      $item['grant_conditions'] ?? NULL,
      $item['effects']['condition'] ?? NULL,
      $item['effects']['conditions'] ?? NULL,
      $item['effects']['add_condition'] ?? NULL,
      $item['effects']['add_conditions'] ?? NULL,
      $item['effects']['grant_condition'] ?? NULL,
      $item['effects']['grant_conditions'] ?? NULL,
    ];

    foreach ($sources as $source) {
      if ($source === NULL) {
        continue;
      }
      $values = is_array($source) && array_is_list($source) ? $source : [$source];
      foreach ($values as $value) {
        if (is_string($value) && trim($value) !== '') {
          $conditions[] = ['name' => trim($value)];
        }
        elseif (is_array($value) && !empty($value)) {
          $conditions[] = $value;
        }
      }
    }

    return $conditions;
  }

  /**
   * Extract spell slot restoration directives from an item.
   */
  private function extractConsumableSpellSlotRestoration(array $item): array {
    $sources = [
      $item['restore_spell_slot'] ?? NULL,
      $item['restore_spell_slots'] ?? NULL,
      $item['spell_slots'] ?? NULL,
      $item['effects']['restore_spell_slot'] ?? NULL,
      $item['effects']['restore_spell_slots'] ?? NULL,
      $item['effects']['spell_slots'] ?? NULL,
    ];

    $restorations = [];
    foreach ($sources as $source) {
      if ($source === NULL) {
        continue;
      }
      if (is_numeric($source)) {
        $restorations[] = ['level' => (int) $source, 'amount' => 1];
        continue;
      }
      if (is_array($source) && !array_is_list($source)) {
        foreach ($source as $level => $amount) {
          if (is_numeric($level) && is_numeric($amount)) {
            $restorations[] = ['level' => (int) $level, 'amount' => (int) $amount];
          }
        }
        continue;
      }
      foreach ((array) $source as $entry) {
        if (is_numeric($entry)) {
          $restorations[] = ['level' => (int) $entry, 'amount' => 1];
        }
        elseif (is_array($entry) && isset($entry['level'])) {
          $restorations[] = [
            'level' => (int) $entry['level'],
            'amount' => max(1, (int) ($entry['amount'] ?? 1)),
          ];
        }
      }
    }

    return $restorations;
  }

  /**
   * Resolve food or water provisioning from explicit fields or item text.
   */
  private function resolveProvisionDays(array $item, string $type): int {
    $explicit = $this->resolveConsumableScalarValue([
      $type === 'food' ? ($item['nutrition_days'] ?? NULL) : ($item['hydration_days'] ?? NULL),
      $type === 'food' ? ($item['food_days'] ?? NULL) : ($item['water_days'] ?? NULL),
      $type === 'food' ? ($item['effects']['nutrition_days'] ?? NULL) : ($item['effects']['hydration_days'] ?? NULL),
      $type === 'food' ? ($item['effects']['food_days'] ?? NULL) : ($item['effects']['water_days'] ?? NULL),
    ]);
    if ($explicit > 0) {
      return $explicit;
    }

    $search_space = strtolower(implode(' ', array_filter([
      is_array($item['traits'] ?? NULL) ? implode(' ', $item['traits']) : '',
      (string) ($item['name'] ?? ''),
      (string) ($item['description'] ?? ''),
      (string) ($item['consumable_stats']['effect'] ?? ''),
    ])));

    $type_keywords = $type === 'food'
      ? ['ration', 'food', 'meal', 'cheese', 'jerky', 'sustain', 'eat', 'eating']
      : ['water', 'drink', 'drinking', 'waterskin', 'hydration'];

    $matches_type = FALSE;
    foreach ($type_keywords as $keyword) {
      if (str_contains($search_space, $keyword)) {
        $matches_type = TRUE;
        break;
      }
    }
    if (!$matches_type) {
      return 0;
    }

    if (preg_match('/(\d+)\s*rations?\b/i', $search_space, $matches)) {
      return max(1, (int) $matches[1]);
    }
    if (preg_match('/(\d+)\s*week[s]?\b/i', $search_space, $matches)) {
      return max(1, (int) $matches[1]) * 7;
    }
    if (preg_match('/(\d+)\s*day[s]?\b/i', $search_space, $matches)) {
      return max(1, (int) $matches[1]);
    }

    return str_contains($search_space, 'ration') || str_contains($search_space, 'waterskin') ? 1 : 0;
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
    $transaction = $this->database->startTransaction();

    $state = $this->resolveEffectiveState($state);
    $persisted_state = $this->stripEffectiveStateFromPersistence($state);

    $type = $persisted_state['type'] ?? ($campaign_row['type'] ?? 'pc');

    // Extract fields for columns with fallbacks for non-PC entities.
    if ($type === 'pc') {
      $name = $persisted_state['basicInfo']['name'] ?? '';
      $level = $persisted_state['basicInfo']['level'] ?? 0;
      $ancestry = $persisted_state['basicInfo']['ancestry'] ?? '';
      $class = $persisted_state['basicInfo']['class'] ?? '';
    }
    else {
      $npc_def = $persisted_state['npcDefinition'] ?? [];
      $name = $npc_def['id'] ?? ($persisted_state['basicInfo']['name'] ?? '');
      $level = $npc_def['level'] ?? ($persisted_state['basicInfo']['level'] ?? 0);
      $ancestry = $persisted_state['basicInfo']['ancestry'] ?? '';
      $class = $persisted_state['basicInfo']['class'] ?? '';
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
    $experiencePoints = (int) ($persisted_state['basicInfo']['experiencePoints'] ?? 0);
    $positionQ = (int) ($position['q'] ?? 0);
    $positionR = (int) ($position['r'] ?? 0);
    $lastRoomId = (string) ($location['roomId'] ?? ($persisted_state['roomId'] ?? ''));

    if ($campaign_row) {
      // Campaign-scoped runtime record
      $persisted_state['metadata']['version'] = $now;
      $persisted_state['metadata']['updatedAt'] = date('c', $now);
      $persisted_state['characterId'] = (string) $character_id;
      $persisted_state['campaignId'] = (string) $campaign_row['campaign_id'];
      $persisted_state['instanceId'] = $campaign_row['instance_id'];

      $this->database->update('dc_campaign_characters')
        ->fields([
          'state_data' => json_encode($persisted_state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
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
      $character_data = $persisted_state;
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

      $this->activeEffectStore->syncCharacterImpacts(
        $character_id,
        is_array($state['effectiveState']['impacts'] ?? NULL) ? $state['effectiveState']['impacts'] : [],
        isset($campaign_row['campaign_id']) ? (int) $campaign_row['campaign_id'] : NULL,
        isset($campaign_row['instance_id']) ? (string) $campaign_row['instance_id'] : NULL,
      );

      unset($transaction);
      return;
    }

    // Library-only record
    $persisted_state['metadata']['version'] = ($persisted_state['metadata']['version'] ?? 0) + 1;
    $persisted_state['metadata']['updatedAt'] = date('c');

    $character_data = $persisted_state;
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
      $update_fields['state_data'] = json_encode($persisted_state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
      $update_fields['updated'] = $now;
    }

    $this->database->update('dc_campaign_characters')
      ->fields($update_fields)
      ->condition('id', $character_id)
      ->execute();

    $this->activeEffectStore->syncCharacterImpacts(
      $character_id,
      is_array($state['effectiveState']['impacts'] ?? NULL) ? $state['effectiveState']['impacts'] : [],
      NULL,
      NULL,
    );

    unset($transaction);
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
   * Resolves the full persistent character sheet into effective state.
   */
  private function resolveEffectiveState(array $state): array {
    $state = $this->normalizePersistentStateInputs($state);
    $effect_context = $this->buildPersistentEffectContext($state);
    $state = $this->applyPersistentDerivedStats($state, $effect_context);

    return $this->attachEffectiveStateMetadata($state, $effect_context);
  }

  /**
   * Normalizes persisted state into canonical sheet inputs.
   */
  private function normalizePersistentStateInputs(array $state): array {
    if (!isset($state['basicInfo']) || !is_array($state['basicInfo'])) {
      $state['basicInfo'] = [];
    }
    if (!isset($state['resources']) || !is_array($state['resources'])) {
      $state['resources'] = [];
    }
    if (!isset($state['defenses']) || !is_array($state['defenses'])) {
      $state['defenses'] = [];
    }
    if (!isset($state['conditions']) || !is_array($state['conditions'])) {
      $state['conditions'] = [];
    }
    if (!isset($state['movement']) || !is_array($state['movement'])) {
      $state['movement'] = [];
    }
    if (!isset($state['movement']['speed']) || !is_array($state['movement']['speed'])) {
      $state['movement']['speed'] = [];
    }
    if (!array_key_exists('base', $state['movement']['speed'])) {
      $state['movement']['speed']['base'] = (int) ($state['speed'] ?? 25);
    }

    $normalized_spellcasting = CharacterManager::normalizeSpellcastingResources(
      is_array($state['spells'] ?? NULL) ? $state['spells'] : [],
      is_array($state['resources'] ?? NULL) ? $state['resources'] : [],
      (string) ($state['basicInfo']['class'] ?? '')
    );
    $state['spells'] = $normalized_spellcasting['spells'];
    $state['resources'] = $normalized_spellcasting['resources'];
    $state['inventory'] = CharacterEquipmentSlotHelper::normalizeInventory(
      is_array($state['inventory'] ?? NULL) ? $state['inventory'] : []
    );

    return $state;
  }

  /**
   * Builds persistent effect source context for effective-state resolution.
   */
  private function buildPersistentEffectContext(array $state): array {
    $feat_effects = $this->buildFeatEffectState($state);
    $equipment_effects = $this->extractEquipmentEffects(is_array($state['inventory'] ?? NULL) ? $state['inventory'] : []);
    $condition_effects = $this->extractPersistentConditionEffects(is_array($state['conditions'] ?? NULL) ? $state['conditions'] : []);
    $active_effect_store_enabled = $this->activeEffectStore->hasStorage();
    $persisted_active_effects = $this->activeEffectStore->listActiveEffects(
      (string) ($state['characterId'] ?? ''),
      isset($state['campaignId']) && $state['campaignId'] !== '' ? (int) $state['campaignId'] : NULL,
      isset($state['instanceId']) && $state['instanceId'] !== '' ? (string) $state['instanceId'] : NULL,
    );
    $impacts = $this->impactContractService->normalizeImpactContracts(
      $this->impactContractService->buildPersistentImpacts($feat_effects, $equipment_effects, $condition_effects)
    );
    $persisted_impact_status = $this->buildPersistedActiveEffectStatus(
      $persisted_active_effects,
      $impacts,
      $active_effect_store_enabled,
    );
    $flags = is_array($feat_effects['derived_adjustments']['flags'] ?? NULL) ? $feat_effects['derived_adjustments']['flags'] : [];
    if (!empty($persisted_impact_status['desynced'])) {
      $flags['active_effect_store_desynced'] = TRUE;
    }

    return [
      'sources' => [
        'feats' => $feat_effects,
        'equipment' => $equipment_effects,
        'conditions' => $condition_effects,
        'active_effects' => $persisted_active_effects,
        'active_effect_store' => $persisted_impact_status,
      ],
      'flags' => $flags,
      'impacts' => $impacts,
    ];
  }

  /**
   * Builds drift metadata comparing persisted active effects to resolved impacts.
   */
  private function buildPersistedActiveEffectStatus(array $persisted_rows, array $resolved_impacts, bool $store_enabled): array {
    $persisted_impacts = $this->impactContractService->normalizeImpactContracts(
      $this->activeEffectStore->extractStoredImpacts($persisted_rows)
    );
    $persisted_keys = array_map(
      fn (array $impact): string => $this->activeEffectStore->buildImpactIdentity($impact),
      $persisted_impacts
    );
    $resolved_keys = array_map(
      fn (array $impact): string => $this->activeEffectStore->buildImpactIdentity($impact),
      $resolved_impacts
    );

    $missing_keys = array_values(array_diff($resolved_keys, $persisted_keys));
    $unexpected_keys = array_values(array_diff($persisted_keys, $resolved_keys));

    return [
      'enabled' => $store_enabled,
      'row_count' => count($persisted_rows),
      'impact_count' => count($persisted_impacts),
      'matches_resolved_state' => $store_enabled ? $missing_keys === [] && $unexpected_keys === [] : FALSE,
      'desynced' => $store_enabled ? $missing_keys !== [] || $unexpected_keys !== [] : FALSE,
      'missing_impacts' => $missing_keys,
      'unexpected_impacts' => $unexpected_keys,
    ];
  }

  /**
   * Builds feat effect state for the current character sheet.
   */
  private function buildFeatEffectState(array $state): array {
    $features = is_array($state['features'] ?? NULL) ? $state['features'] : [];
    $feats = is_array($features['feats'] ?? NULL) ? $features['feats'] : [];
    $feat_selections = is_array($features['featSelections'] ?? NULL) ? $features['featSelections'] : [];
    $base_speed = (int) ($state['movement']['speed']['base'] ?? $state['speed'] ?? 25);
    $level = max(1, (int) ($state['basicInfo']['level'] ?? 1));

    return $this->featEffectManager->buildEffectState([
      'level' => $level,
      'feats' => $feats,
      'feat_selections' => $feat_selections,
      'feat_resources' => is_array($state['resources']['featResources'] ?? NULL) ? $state['resources']['featResources'] : [],
      'heritage' => $state['basicInfo']['heritage'] ?? '',
      'ancestry' => $state['basicInfo']['ancestry'] ?? '',
      'class' => $state['basicInfo']['class'] ?? '',
      'deity' => $state['basicInfo']['deity'] ?? '',
      'class_features' => is_array($features['classFeatures'] ?? NULL) ? $features['classFeatures'] : [],
    ], [
      'level' => $level,
      'base_speed' => $base_speed,
      'existing_hp_max' => (int) ($state['resources']['hitPoints']['max'] ?? 0),
    ]);
  }

  /**
   * Extracts equipment-derived effect inputs from normalized inventory.
   */
  private function extractEquipmentEffects(array $inventory): array {
    $worn = is_array($inventory['worn'] ?? NULL) ? $inventory['worn'] : [];
    $armor = is_array($worn['armor'] ?? NULL) ? $worn['armor'] : [];
    $armor_stats = is_array($armor['armor_stats'] ?? NULL) ? $armor['armor_stats'] : [];
    $shield = is_array($worn['shield'] ?? NULL) ? $worn['shield'] : [];

    return [
      'armor' => [
        'item_id' => (string) ($armor['item_id'] ?? $armor['id'] ?? ''),
        'name' => (string) ($armor['name'] ?? ''),
        'armor_bonus' => (int) ($armor_stats['ac_bonus'] ?? 0),
        'dex_cap' => array_key_exists('dex_cap', $armor_stats) ? (int) $armor_stats['dex_cap'] : NULL,
        'speed_penalty' => (int) ($armor_stats['speed_penalty'] ?? 0),
        'check_penalty' => (int) ($armor_stats['check_penalty'] ?? 0),
      ],
      'shield' => [
        'item_id' => (string) ($shield['item_id'] ?? $shield['id'] ?? ''),
        'name' => (string) ($shield['name'] ?? ''),
        'shield_bonus' => (int) ($shield['ac_bonus'] ?? 0),
      ],
      'accessories' => is_array($worn['accessories'] ?? NULL) ? $worn['accessories'] : [],
    ];
  }

  /**
   * Extracts persistent condition effect inputs from stored sheet conditions.
   */
  private function extractPersistentConditionEffects(array $conditions): array {
    $normalized_conditions = [];
    $supported_adjustments = [
      'armor_class' => 0,
      'speed' => 0,
    ];
    $unsupported = [];

    foreach ($conditions as $condition) {
      if (!is_array($condition)) {
        continue;
      }

      $raw_code = (string) ($condition['condition_type'] ?? $condition['id'] ?? $condition['name'] ?? '');
      $code = strtolower(str_replace([' ', '-'], '_', trim($raw_code)));
      $value = (int) ($condition['value'] ?? $condition['amount'] ?? $condition['penalty'] ?? 0);
      if ($value === 0 && preg_match('/_(\d+)$/', $code, $matches) === 1) {
        $value = (int) $matches[1];
      }

      $normalized = [
        'id' => (string) ($condition['id'] ?? ''),
        'code' => $code,
        'label' => (string) ($condition['name'] ?? $condition['condition_type'] ?? $condition['id'] ?? ''),
        'value' => $value,
      ];
      $normalized_conditions[] = $normalized;

      switch ($code) {
        case 'flat_footed':
          $supported_adjustments['armor_class'] -= 2;
          break;

        case 'frightened':
          $supported_adjustments['armor_class'] -= max(1, $value);
          break;

        default:
          if (str_starts_with($code, 'speed_penalty_')) {
            $supported_adjustments['speed'] -= max(0, $value);
          }
          else {
            $unsupported[] = $normalized;
          }
          break;
      }
    }

    return [
      'active' => $normalized_conditions,
      'supported_adjustments' => $supported_adjustments,
      'unsupported' => $unsupported,
    ];
  }


  /**
   * Applies persistent derived stat calculations from effect context.
   */
  private function applyPersistentDerivedStats(array $state, array $effect_context): array {
    $state = $this->applyFeatEffectsToState($state, $effect_context['sources']['feats'] ?? NULL);
    $state = $this->applyDerivedDefensesToState($state, $effect_context);

    return $state;
  }

  /**
   * Attaches effective-state metadata for debugging and consumers.
   */
  private function attachEffectiveStateMetadata(array $state, array $effect_context): array {
    $armor_class = is_array($state['defenses']['armorClass'] ?? NULL) ? $state['defenses']['armorClass'] : [];
    $speed = is_array($state['movement']['speed'] ?? NULL) ? $state['movement']['speed'] : [];
    $hit_points = is_array($state['resources']['hitPoints'] ?? NULL) ? $state['resources']['hitPoints'] : [];

    $state['effectiveState'] = [
      'sources' => $effect_context['sources'] ?? [],
      'flags' => $effect_context['flags'] ?? [],
      'impacts' => is_array($effect_context['impacts'] ?? NULL) ? $effect_context['impacts'] : [],
      'applied' => [
        'armorClass' => [
          'total' => (int) ($armor_class['total'] ?? $armor_class['value'] ?? 10),
          'armorBonus' => (int) ($armor_class['armorBonus'] ?? 0),
          'armorDexCap' => $armor_class['armorDexCap'] ?? NULL,
        ],
        'speed' => [
          'base' => (int) ($speed['base'] ?? 25),
          'total' => (int) ($speed['total'] ?? $speed['base'] ?? 25),
        ],
        'hitPoints' => [
          'baseMax' => (int) ($hit_points['baseMax'] ?? $hit_points['max'] ?? 0),
          'max' => (int) ($hit_points['max'] ?? 0),
          'current' => (int) ($hit_points['current'] ?? 0),
          'temporary' => (int) ($hit_points['temporary'] ?? 0),
        ],
      ],
      'breakdowns' => [
        'armorClass' => is_array($armor_class['breakdown'] ?? NULL) ? $armor_class['breakdown'] : [],
        'speed' => [
          'base' => (int) ($speed['base'] ?? 25),
          'featComputed' => (int) (($effect_context['sources']['feats']['derived_adjustments']['computed_speed'] ?? $speed['base'] ?? 25)),
          'armorPenalty' => (int) (($effect_context['sources']['equipment']['armor']['speed_penalty'] ?? 0)),
          'ignoreArmorPenalty' => (bool) (($effect_context['flags']['ignore_armor_speed_penalty'] ?? FALSE)),
          'total' => (int) ($speed['total'] ?? $speed['base'] ?? 25),
        ],
        'hitPoints' => [
          'baseMax' => (int) ($hit_points['baseMax'] ?? $hit_points['max'] ?? 0),
          'featBonus' => (int) (($effect_context['sources']['feats']['derived_adjustments']['hp_max_bonus'] ?? 0)),
          'totalMax' => (int) ($hit_points['max'] ?? 0),
        ],
      ],
    ];

    return $state;
  }

  /**
   * Removes computed effective-state metadata before persistence.
   */
  private function stripEffectiveStateFromPersistence(array $state): array {
    unset($state['effectiveState']);

    return $state;
  }

  /**
   * Applies feat-derived effects into character state payload.
   */
  private function applyFeatEffectsToState(array $state, ?array $effects = NULL): array {
    $features = is_array($state['features'] ?? NULL) ? $state['features'] : [];
    $feat_selections = is_array($features['featSelections'] ?? NULL) ? $features['featSelections'] : [];

    if ($effects === NULL) {
      $effects = $this->buildFeatEffectState($state);
    }

    $base_speed = (int) ($state['movement']['speed']['base'] ?? $state['speed'] ?? 25);

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
    $state['features']['featSelections'] = $feat_selections;
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
   * Applies inventory-driven defense calculations into character state.
   */
  private function applyDerivedDefensesToState(array $state, ?array $effect_context = NULL): array {
    if (!isset($state['defenses']) || !is_array($state['defenses'])) {
      $state['defenses'] = [];
    }

    $calculator = new CharacterCalculator();
    $effect_context = $effect_context ?? $this->buildPersistentEffectContext($state);
    $equipment = is_array($effect_context['sources']['equipment'] ?? NULL) ? $effect_context['sources']['equipment'] : [];
    $armor = is_array($equipment['armor'] ?? NULL) ? $equipment['armor'] : [];
    $existing_armor_class = is_array($state['defenses']['armorClass'] ?? NULL) ? $state['defenses']['armorClass'] : [];
    $feat_flags = is_array($effect_context['flags'] ?? NULL) ? $effect_context['flags'] : [];

    $armor_bonus = (int) ($armor['armor_bonus'] ?? 0);
    $armor_dex_cap = array_key_exists('dex_cap', $armor) ? $armor['dex_cap'] : NULL;
    $condition_adjustments = is_array($effect_context['sources']['conditions']['supported_adjustments'] ?? NULL)
      ? $effect_context['sources']['conditions']['supported_adjustments']
      : [];
    $other_ac_bonus = (int) (
      $existing_armor_class['otherBonuses']
      ?? $state['other_ac_bonus']
      ?? 0
    ) + (int) ($condition_adjustments['armor_class'] ?? 0);
    $proficiency_rank = (string) (
      $existing_armor_class['proficiencyRank']
      ?? 'untrained'
    );

    $armor_class = $calculator->calculateArmorClass([
      'abilities' => is_array($state['abilities'] ?? NULL) ? $state['abilities'] : [],
      'level' => (int) ($state['basicInfo']['level'] ?? 1),
      'armor_bonus' => $armor_bonus,
      'armor_dex_cap' => $armor_dex_cap,
      'shield_bonus' => 0,
      'other_ac_bonus' => $other_ac_bonus,
      'proficiency_rank' => $proficiency_rank,
    ]);

    $state['defenses']['armorClass'] = [
      ...$existing_armor_class,
      'base' => (int) ($armor_class['total'] ?? 10),
      'value' => (int) ($armor_class['total'] ?? 10),
      'total' => (int) ($armor_class['total'] ?? 10),
      'armorBonus' => $armor_bonus,
      'armorDexCap' => $armor_dex_cap,
      'shieldBonus' => 0,
      'otherBonuses' => $other_ac_bonus,
      'proficiencyRank' => $proficiency_rank,
      'breakdown' => $armor_class['breakdown'] ?? [],
    ];

    if (!isset($state['movement']) || !is_array($state['movement'])) {
      $state['movement'] = [];
    }
    if (!isset($state['movement']['speed']) || !is_array($state['movement']['speed'])) {
      $state['movement']['speed'] = [];
    }
    $base_speed = (int) ($state['movement']['speed']['base'] ?? $state['speed'] ?? 25);
    $feat_speed = (int) ($state['movement']['speed']['total'] ?? $base_speed);
    $armor_speed_penalty = (int) ($armor['speed_penalty'] ?? 0);
    if (!($feat_flags['ignore_armor_speed_penalty'] ?? FALSE)) {
      $feat_speed += $armor_speed_penalty;
    }
    $feat_speed += (int) ($condition_adjustments['speed'] ?? 0);
    $state['movement']['speed']['base'] = $base_speed;
    $state['movement']['speed']['total'] = max(0, $feat_speed);

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
