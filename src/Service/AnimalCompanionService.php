<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Resolves PF2e animal companions as owner-linked NPC-style records.
 *
 * Companion ownership and selections are stored on the owning character's
 * library character_data, while the resolved runtime shape is exposed in the
 * same style as other NPC/ally payloads.
 */
class AnimalCompanionService {

  public function __construct(protected readonly Connection $database) {}

  /**
   * Return the available companion species catalog.
   *
   * @return array<int,array<string,mixed>>
   *   Companion species definitions.
   */
  public function getSpeciesCatalog(): array {
    return array_values(CharacterManager::ANIMAL_COMPANIONS['species'] ?? []);
  }

  /**
   * Return the active resolved companion for a character.
   */
  public function getCompanion(string $character_id): array {
    $record = $this->loadRecord($character_id);
    $char_data = json_decode($record->character_data ?? '', TRUE) ?? [];
    $companion = $this->resolveCompanionFromCharacterData($char_data, $character_id);

    if ($companion === NULL) {
      return ['success' => FALSE, 'error' => 'No animal companion found for this character.', 'code' => 404];
    }

    return [
      'success' => TRUE,
      'companion' => $companion,
      'species_catalog' => $this->getSpeciesCatalog(),
    ];
  }

  /**
   * Create or update the active companion selection for a character.
   *
   * @param string $character_id
   *   Owner character ID (library record, campaign_id = 0).
   * @param array $params
   *   Expected keys:
   *   - species_id: required companion species ID.
   *   - name: optional custom display name.
   */
  public function createCompanion(string $character_id, array $params): array {
    $record = $this->loadRecord($character_id);
    $char_data = json_decode($record->character_data ?? '', TRUE) ?? [];

    $base_feat_id = $this->resolveBaseCompanionFeatId($char_data);
    if ($base_feat_id === NULL) {
      return ['success' => FALSE, 'error' => 'Character does not currently qualify for an animal companion.', 'code' => 400];
    }

    $species_id = strtolower(trim((string) ($params['species_id'] ?? $params['selected_companion_species'] ?? '')));
    $species = $this->getSpeciesDefinition($species_id);
    if ($species === NULL) {
      return [
        'success' => FALSE,
        'error' => 'Invalid animal companion species. Use GET /api/character/{id}/animal-companion/catalog to inspect valid options.',
        'code' => 400,
      ];
    }

    $display_name = trim((string) ($params['name'] ?? $params['display_name'] ?? $species['name']));
    if ($display_name === '') {
      $display_name = (string) $species['name'];
    }

    $existing = is_array($char_data['animal_companion'] ?? NULL) ? $char_data['animal_companion'] : [];
    $resolved_existing = $this->resolveCompanionFromCharacterData($char_data, $character_id);
    $current_hp = (int) ($existing['current_hp'] ?? $resolved_existing['stats']['currentHp'] ?? 0);
    $now = time();

    $char_data['animal_companion'] = array_replace($existing, [
      'companion_id' => $existing['companion_id'] ?? ($character_id . '_animal_companion'),
      'owner_character_id' => $character_id,
      'name' => $display_name,
      'species_id' => $species_id,
      'state' => 'active',
      'created_at' => (int) ($existing['created_at'] ?? $now),
      'updated_at' => $now,
    ]);

    $char_data['feat_selections'] = is_array($char_data['feat_selections'] ?? NULL) ? $char_data['feat_selections'] : [];
    $char_data['feat_selections'][$base_feat_id] = array_replace(
      is_array($char_data['feat_selections'][$base_feat_id] ?? NULL) ? $char_data['feat_selections'][$base_feat_id] : [],
      [
        'selected_companion_species' => $species_id,
        'species_id' => $species_id,
        'name' => $display_name,
      ]
    );

    $resolved = $this->resolveCompanionFromCharacterData($char_data, $character_id);
    if ($resolved !== NULL) {
      $char_data['animal_companion']['current_hp'] = max(0, min(
        $current_hp > 0 ? $current_hp : (int) ($resolved['stats']['maxHp'] ?? 0),
        (int) ($resolved['stats']['maxHp'] ?? 0)
      ));
      $resolved = $this->resolveCompanionFromCharacterData($char_data, $character_id);
    }

    $this->persistCharacterData($character_id, $char_data);

    return [
      'success' => TRUE,
      'companion' => $resolved,
    ];
  }

  /**
   * Select a specialization for a specialized companion feat owner.
   */
  public function selectSpecialization(string $character_id, string $specialization_id): array {
    $record = $this->loadRecord($character_id);
    $char_data = json_decode($record->character_data ?? '', TRUE) ?? [];

    if (!$this->hasFeat($char_data, 'specialized-companion-druid')) {
      return ['success' => FALSE, 'error' => 'Character does not currently qualify for companion specialization.', 'code' => 400];
    }
    if (!$this->hasFeat($char_data, 'mature-animal-companion-druid')) {
      return ['success' => FALSE, 'error' => 'Companion must be mature before selecting a specialization.', 'code' => 400];
    }

    $companion = $this->resolveCompanionFromCharacterData($char_data, $character_id);
    if ($companion === NULL) {
      return ['success' => FALSE, 'error' => 'Create an animal companion before selecting a specialization.', 'code' => 409];
    }

    $specialization_id = strtolower(trim($specialization_id));
    $specialization = CharacterManager::ANIMAL_COMPANIONS['specializations'][$specialization_id] ?? NULL;
    if (!is_array($specialization)) {
      return ['success' => FALSE, 'error' => 'Invalid animal companion specialization.', 'code' => 400];
    }

    $char_data['feat_selections'] = is_array($char_data['feat_selections'] ?? NULL) ? $char_data['feat_selections'] : [];
    $char_data['feat_selections']['specialized-companion-druid'] = array_replace(
      is_array($char_data['feat_selections']['specialized-companion-druid'] ?? NULL) ? $char_data['feat_selections']['specialized-companion-druid'] : [],
      [
        'selected_specialization' => $specialization_id,
        'specialization' => $specialization_id,
      ]
    );

    $resolved = $this->resolveCompanionFromCharacterData($char_data, $character_id);
    if ($resolved !== NULL) {
      $char_data['animal_companion']['current_hp'] = min(
        (int) ($char_data['animal_companion']['current_hp'] ?? $resolved['stats']['maxHp'] ?? 0),
        (int) ($resolved['stats']['maxHp'] ?? 0)
      );
      $char_data['animal_companion']['updated_at'] = time();
      $resolved = $this->resolveCompanionFromCharacterData($char_data, $character_id);
    }

    $this->persistCharacterData($character_id, $char_data);

    return [
      'success' => TRUE,
      'companion' => $resolved,
    ];
  }

  /**
   * Resolve the active companion into an NPC-style runtime payload.
   */
  public function resolveCompanionFromCharacterData(array $char_data, string $character_id): ?array {
    if ($this->resolveBaseCompanionFeatId($char_data) === NULL) {
      return NULL;
    }

    $species_id = $this->resolveSelectedSpeciesId($char_data);
    if ($species_id === NULL) {
      return NULL;
    }

    $species = $this->getSpeciesDefinition($species_id);
    if ($species === NULL) {
      return NULL;
    }

    $companion_data = is_array($char_data['animal_companion'] ?? NULL) ? $char_data['animal_companion'] : [];
    $stage = $this->resolveStage($char_data);
    $specialization_id = $this->resolveSelectedSpecializationId($char_data);
    $specialization = $specialization_id !== NULL
      ? (CharacterManager::ANIMAL_COMPANIONS['specializations'][$specialization_id] ?? NULL)
      : NULL;

    $owner_level = max(1, (int) (
      $char_data['basicInfo']['level']
      ?? $char_data['level']
      ?? 1
    ));

    $stats = $this->buildResolvedStats($species, $owner_level, $stage, is_array($specialization) ? $specialization : NULL);
    $current_hp = (int) ($companion_data['current_hp'] ?? $stats['maxHp']);
    $stats['currentHp'] = max(0, min($current_hp, $stats['maxHp']));

    $display_name = $this->resolveSelectedCompanionName($char_data, (string) ($companion_data['name'] ?? ''));
    if ($display_name === '') {
      $display_name = (string) $species['name'];
    }

    $stage_definition = CharacterManager::ANIMAL_COMPANIONS['advancement'][$stage] ?? [];

    return [
      'companion_id' => $companion_data['companion_id'] ?? ($character_id . '_animal_companion'),
      'owner_character_id' => $character_id,
      'name' => $display_name,
      'species_id' => $species_id,
      'species' => $species,
      'stage' => $stage,
      'stage_label' => (string) ($stage_definition['label'] ?? ucfirst($stage)),
      'specialization' => $specialization_id,
      'specialization_definition' => is_array($specialization) ? $specialization : NULL,
      'traits' => array_values(array_unique(array_merge(
        array_map('strval', $species['traits'] ?? []),
        ['Animal Companion']
      ))),
      'senses' => array_values($species['senses'] ?? []),
      'attacks' => array_values($species['attacks'] ?? []),
      'support_benefit' => $species['support_benefit'] ?? '',
      'stats' => $stats,
      'movement_speed' => (int) ($stats['speed'] ?? 25),
      'actions_per_turn' => 2,
      'team' => 'ally',
      'metadata' => [
        'role' => 'animal_companion',
        'type' => 'npc',
      ],
    ];
  }

  /**
   * Resolve current available specialization catalog for the character.
   */
  public function getAvailableSpecializations(string $character_id): array {
    $record = $this->loadRecord($character_id);
    $char_data = json_decode($record->character_data ?? '', TRUE) ?? [];
    if (!$this->hasFeat($char_data, 'specialized-companion-druid')) {
      return [];
    }

    return array_values(CharacterManager::ANIMAL_COMPANIONS['specializations'] ?? []);
  }

  /**
   * Determine whether the character currently qualifies for base companion use.
   */
  private function resolveBaseCompanionFeatId(array $char_data): ?string {
    foreach (['animal-companion', 'animal-companion-druid'] as $feat_id) {
      if ($this->hasFeat($char_data, $feat_id)) {
        return $feat_id;
      }
    }

    $class_name = strtolower(trim((string) ($char_data['class'] ?? $char_data['basicInfo']['class'] ?? '')));
    $subclass = strtolower(trim((string) ($char_data['subclass'] ?? $char_data['basicInfo']['subclass'] ?? '')));
    if ($class_name === 'druid' && $subclass === 'animal') {
      return 'animal-companion-druid';
    }

    return NULL;
  }

  /**
   * Resolve stored species selection from canonical selection locations.
   */
  private function resolveSelectedSpeciesId(array $char_data): ?string {
    $companion = is_array($char_data['animal_companion'] ?? NULL) ? $char_data['animal_companion'] : [];
    foreach ([
      $companion['species_id'] ?? NULL,
      $char_data['feat_selections']['animal-companion']['selected_companion_species'] ?? NULL,
      $char_data['feat_selections']['animal-companion']['species_id'] ?? NULL,
      $char_data['feat_selections']['animal-companion-druid']['selected_companion_species'] ?? NULL,
      $char_data['feat_selections']['animal-companion-druid']['species_id'] ?? NULL,
    ] as $candidate) {
      if (is_string($candidate) && trim($candidate) !== '') {
        return strtolower(trim($candidate));
      }
    }
    return NULL;
  }

  /**
   * Resolve stored specialization selection.
   */
  private function resolveSelectedSpecializationId(array $char_data): ?string {
    foreach ([
      $char_data['feat_selections']['specialized-companion-druid']['selected_specialization'] ?? NULL,
      $char_data['feat_selections']['specialized-companion-druid']['specialization'] ?? NULL,
    ] as $candidate) {
      if (is_string($candidate) && trim($candidate) !== '') {
        return strtolower(trim($candidate));
      }
    }
    return NULL;
  }

  /**
   * Resolve the stored display name from canonical companion selection locations.
   */
  private function resolveSelectedCompanionName(array $char_data, string $fallback = ''): string {
    foreach ([
      $fallback,
      $char_data['feat_selections']['animal-companion']['name'] ?? NULL,
      $char_data['feat_selections']['animal-companion']['display_name'] ?? NULL,
      $char_data['feat_selections']['animal-companion-druid']['name'] ?? NULL,
      $char_data['feat_selections']['animal-companion-druid']['display_name'] ?? NULL,
    ] as $candidate) {
      if (is_string($candidate) && trim($candidate) !== '') {
        return trim($candidate);
      }
    }

    return '';
  }

  /**
   * Resolve active stage from owned feats.
   */
  private function resolveStage(array $char_data): string {
    if ($this->hasFeat($char_data, 'specialized-companion-druid') || $this->hasFeat($char_data, 'mature-animal-companion-druid')) {
      return 'mature';
    }
    return 'young';
  }

  /**
   * Check whether a feat is currently owned.
   */
  private function hasFeat(array $char_data, string $feat_id): bool {
    $target_feat_id = $this->normalizeFeatId($feat_id);
    foreach (($char_data['features']['feats'] ?? $char_data['feats'] ?? []) as $feat) {
      if (is_array($feat) && $this->normalizeFeatId((string) ($feat['id'] ?? '')) === $target_feat_id) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Normalize feat IDs across legacy underscore and current hyphen storage.
   */
  private function normalizeFeatId(string $feat_id): string {
    return strtolower(str_replace('_', '-', trim($feat_id)));
  }

  /**
   * Build resolved companion stats from species + advancement.
   */
  private function buildResolvedStats(array $species, int $owner_level, string $stage, ?array $specialization = NULL): array {
    $base_speed = (int) (($species['speed']['walk'] ?? 25));
    $stage_definition = CharacterManager::ANIMAL_COMPANIONS['advancement'][$stage] ?? [];

    $size = (string) ($species['size'] ?? 'Medium');
    if (!empty($stage_definition['size_increase'])) {
      $size = $this->increaseSize($size);
    }

    $speed_bonus = (int) ($stage_definition['speed_bonus'] ?? 0);
    $hp_bonus = (int) ($stage_definition['hp_bonus'] ?? 0);
    $ac_bonus = (int) ($stage_definition['ac_bonus'] ?? 0);
    $save_bonus = (int) ($stage_definition['save_bonus'] ?? 0);
    $attack_bonus = (int) ($stage_definition['attack_mod_bonus'] ?? 0);

    if (is_array($specialization)) {
      $hp_bonus += (int) ($specialization['hp_bonus'] ?? 0);
      $ac_bonus += (int) ($specialization['ac_bonus'] ?? 0);
      $save_bonus += (int) ($specialization['save_bonus'] ?? 0);
      $attack_bonus += (int) ($specialization['attack_mod_bonus'] ?? 0);
      $speed_bonus += (int) ($specialization['speed_bonus'] ?? 0);
    }

    $max_hp = max(1, ((int) ($species['hp_per_level'] ?? 6) * $owner_level) + $hp_bonus);
    $ac = max(10, (int) ($species['base_ac'] ?? 10) + $ac_bonus);
    $perception = max(
      (int) ($species['base_saves']['will'] ?? 0),
      (int) ($species['base_saves']['reflex'] ?? 0),
      (int) ($species['base_saves']['fortitude'] ?? 0)
    ) + $save_bonus;

    return [
      'maxHp' => $max_hp,
      'currentHp' => $max_hp,
      'ac' => $ac,
      'perception' => $perception,
      'speed' => $base_speed + $speed_bonus,
      'initiative_bonus' => $perception,
      'attack_bonus' => max(0, $owner_level + $attack_bonus),
      'size' => $size,
      'owner_level' => $owner_level,
    ];
  }

  /**
   * Get a single species definition.
   */
  private function getSpeciesDefinition(string $species_id): ?array {
    $species = CharacterManager::ANIMAL_COMPANIONS['species'][$species_id] ?? NULL;
    return is_array($species) ? $species : NULL;
  }

  /**
   * Increase size by one PF2e step.
   */
  private function increaseSize(string $size): string {
    $order = ['Tiny', 'Small', 'Medium', 'Large', 'Huge', 'Gargantuan'];
    $index = array_search($size, $order, TRUE);
    if ($index === FALSE) {
      return $size;
    }
    return $order[min($index + 1, count($order) - 1)];
  }

  /**
   * Load character record from dc_campaign_characters (library slot, campaign_id = 0).
   *
   * @throws \InvalidArgumentException
   */
  private function loadRecord(string $character_id): object {
    $record = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('id', $character_id)
      ->condition('campaign_id', 0)
      ->execute()
      ->fetchObject();

    if (!$record) {
      throw new \InvalidArgumentException("Character not found: {$character_id}", 404);
    }

    return $record;
  }

  /**
   * Persist character_data back to the database.
   */
  private function persistCharacterData(string $character_id, array $char_data): void {
    $this->database->update('dc_campaign_characters')
      ->fields([
        'character_data' => json_encode($char_data),
        'changed' => time(),
      ])
      ->condition('id', $character_id)
      ->condition('campaign_id', 0)
      ->execute();
  }

}
