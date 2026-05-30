<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Normalizes class advancement data into a planner-friendly registry shape.
 */
class CharacterProgressionRegistry {

  /**
   * Build normalized advancement data for a class and target level.
   *
   * @throws \InvalidArgumentException
   */
  public function buildLevelPlan(string $class_name, int $level, array $character_data = []): array {
    $class_id = strtolower(trim($class_name));
    if ($class_id === '' || !isset(CharacterManager::CLASSES[$class_id])) {
      throw new \InvalidArgumentException("Unsupported class '{$class_name}'", 400);
    }
    if ($level < 2 || $level > 20) {
      throw new \InvalidArgumentException("Unsupported target level '{$level}'", 400);
    }

    $class_data = CharacterManager::CLASSES[$class_id];
    $legacy_advancement = CharacterManager::getClassAdvancement($class_id, $level);
    $feat_slots = $this->buildFeatSlots($class_id, $level);
    $skill_increases = $this->resolveSkillIncreaseCount($class_id, $level);
    $ability_boosts = in_array($level, [5, 10, 15, 20], TRUE) ? 4 : 0;
    $spellcasting_deltas = $this->resolveSpellcastingDeltas($class_data, $level);

    return [
      'class_id' => $class_id,
      'target_level' => $level,
      'family' => $this->resolveClassFamily($class_id, $spellcasting_deltas, $class_data),
      'hp_bonus' => (int) ($legacy_advancement['hp_bonus'] ?? ($class_data['hp'] ?? 8)),
      'auto_grants' => array_values($legacy_advancement['auto_features'] ?? []),
      'choice_slots' => $this->buildChoiceSlots($feat_slots, $skill_increases, $ability_boosts),
      'proficiency_deltas' => [],
      'spellcasting_deltas' => $spellcasting_deltas,
      'focus_or_class_resource_deltas' => $this->resolveFocusDeltas($spellcasting_deltas),
      'subclass_branch_requirements' => $this->resolveBranchRequirements($class_data, $character_data),
      'universal_track_overrides' => [
        'feat_slots' => $feat_slots,
        'skill_increases' => $skill_increases,
        'ability_boosts' => $ability_boosts,
      ],
      'raw_advancement' => $legacy_advancement,
    ];
  }

  /**
   * Build the choice slot list for a target level.
   */
  private function buildChoiceSlots(array $feat_slots, int $skill_increases, int $ability_boosts): array {
    $slots = [];

    if ($ability_boosts > 0) {
      $slots[] = [
        'type' => 'ability_boosts',
        'count' => $ability_boosts,
        'label' => "Choose {$ability_boosts} ability boosts",
        'resolved' => FALSE,
      ];
    }

    for ($i = 0; $i < $skill_increases; $i++) {
      $slots[] = [
        'type' => 'skill_increase',
        'label' => 'Raise one skill proficiency rank by one step',
        'resolved' => FALSE,
      ];
    }

    foreach ($feat_slots as $slot) {
      $slots[] = [
        'type' => 'feat_choice',
        'slot_type' => $slot['slot_type'],
        'label' => $slot['label'],
        'resolved' => FALSE,
      ];
    }

    return $slots;
  }

  /**
   * Resolve the feat slots granted at a target level.
   */
  private function buildFeatSlots(string $class_id, int $level): array {
    $slots = [];

    if ($level % 2 === 0) {
      $slots[] = ['slot_type' => 'class_feat', 'label' => 'Class Feat'];
      $slots[] = ['slot_type' => 'skill_feat', 'label' => 'Skill Feat'];
    }

    if (in_array($level, [3, 7, 11, 15, 19], TRUE)) {
      $slots[] = ['slot_type' => 'general_feat', 'label' => 'General Feat'];
    }

    if (in_array($level, [5, 9, 13, 17], TRUE)) {
      $slots[] = ['slot_type' => 'ancestry_feat', 'label' => 'Ancestry Feat'];
    }

    if ($class_id === 'rogue' || $class_id === 'investigator') {
      // These classes gain skill increases every level, but feat cadence remains core.
      return $slots;
    }

    return $slots;
  }

  /**
   * Resolve the number of skill increases granted at a target level.
   */
  private function resolveSkillIncreaseCount(string $class_id, int $level): int {
    if ($class_id === 'rogue' || $class_id === 'investigator') {
      return 1;
    }

    return $level >= 3 && $level % 2 === 1 ? 1 : 0;
  }

  /**
   * Resolve spellcasting deltas from class metadata.
   */
  private function resolveSpellcastingDeltas(array $class_data, int $level): array {
    $slot_config = $this->findSpellSlotConfig($class_data);
    $meta_config = $this->findSpellcastingMetadataConfig($class_data);
    $config = array_replace(is_array($meta_config) ? $meta_config : [], is_array($slot_config) ? $slot_config : []);
    if ($config === []) {
      return [];
    }

    $slot_table = $config['spell_slots_by_level'] ?? $this->buildDefaultFullCasterSlotTable($config);
    $current_slots = $this->normalizeSpellSlotMap((array) ($slot_table[$level] ?? []));
    $previous_slots = $this->normalizeSpellSlotMap((array) ($slot_table[$level - 1] ?? []));
    $gained_slots = [];

    foreach ($current_slots as $slot_key => $count) {
      $previous = (int) ($previous_slots[$slot_key] ?? 0);
      if ($count !== $previous) {
        $gained_slots[$slot_key] = $count - $previous;
      }
    }

    $cantrip_total = (int) ($config['cantrips'] ?? $config['starting_cantrips'] ?? $config['cantrips_at_start'] ?? 0);
    $spellbook_delta = (int) ($config['spells_per_level_gained'] ?? $config['spells_per_level_up'] ?? 0);

    return array_filter([
      'tradition' => strtolower(trim((string) ($config['tradition'] ?? ''))),
      'casting_ability' => strtolower(trim((string) ($config['ability'] ?? $config['spellcasting_ability'] ?? $class_data['key_ability'] ?? ''))),
      'casting_type' => strtolower(trim((string) ($config['prepared_type'] ?? $config['casting_type'] ?? ''))),
      'slots' => $current_slots,
      'gained_slots' => $gained_slots,
      'cantrips' => $cantrip_total > 0 ? $cantrip_total : NULL,
      'focus_pool_start' => (int) ($config['focus_pool_start'] ?? 0) ?: NULL,
      'spellbook_size_delta' => $spellbook_delta > 0 ? $spellbook_delta : NULL,
    ], static fn($value): bool => $value !== NULL && $value !== [] && $value !== '');
  }

  /**
   * Find the first spellcasting configuration nested under class metadata.
   */
  private function findSpellcastingConfig(array $class_data): ?array {
    return $this->findSpellSlotConfig($class_data) ?? $this->findSpellcastingMetadataConfig($class_data);
  }

  /**
   * Find the config block that owns the slot table.
   */
  private function findSpellSlotConfig(array $class_data): ?array {
    foreach ($class_data as $value) {
      if (is_array($value) && isset($value['spell_slots_by_level'])) {
        return $value;
      }
    }

    return NULL;
  }

  /**
   * Find the config block that owns casting tradition/ability metadata.
   */
  private function findSpellcastingMetadataConfig(array $class_data): ?array {
    foreach ($class_data as $value) {
      if (!is_array($value)) {
        continue;
      }
      if (isset($value['tradition']) || isset($value['ability']) || isset($value['spellcasting_ability'])) {
        return $value;
      }
    }

    foreach ($class_data as $value) {
      if (!is_array($value)) {
        continue;
      }
      if (isset($value['spell_slots_by_level'])
        || isset($value['starting_cantrips'])
        || isset($value['cantrips'])
        || isset($value['cantrips_at_start'])
        || isset($value['prepared_type'])
        || isset($value['casting_type'])
      ) {
        return $value;
      }
    }

    return NULL;
  }

  /**
   * Convert class slot tables into canonical sheet slot keys.
   */
  private function normalizeSpellSlotMap(array $slot_map): array {
    $normalized = [];
    foreach ($slot_map as $rank => $count) {
      $slot_key = $this->normalizeSpellSlotKey((string) $rank);
      if ($slot_key === NULL) {
        continue;
      }
      $normalized[$slot_key] = max(0, (int) $count);
    }
    ksort($normalized, SORT_NATURAL);
    return $normalized;
  }

  /**
   * Build a fallback full-caster slot table when class data omits explicit rows.
   */
  private function buildDefaultFullCasterSlotTable(array $config): array {
    $casting_type = strtolower(trim((string) ($config['prepared_type'] ?? $config['casting_type'] ?? $config['type'] ?? '')));
    if ($casting_type !== 'prepared' && $casting_type !== 'spontaneous') {
      return [];
    }

    return [
      1 => ['1st' => 2],
      2 => ['1st' => 3],
      3 => ['1st' => 3, '2nd' => 2],
      4 => ['1st' => 3, '2nd' => 3],
      5 => ['1st' => 3, '2nd' => 3, '3rd' => 2],
      6 => ['1st' => 3, '2nd' => 3, '3rd' => 3],
      7 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 2],
      8 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3],
      9 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 2],
      10 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3],
      11 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 2],
      12 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3],
      13 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 2],
      14 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3],
      15 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3, '8th' => 2],
      16 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3, '8th' => 3],
      17 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3, '8th' => 3, '9th' => 2],
      18 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3, '8th' => 3, '9th' => 3],
      19 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3, '8th' => 3, '9th' => 3],
      20 => ['1st' => 3, '2nd' => 3, '3rd' => 3, '4th' => 3, '5th' => 3, '6th' => 3, '7th' => 3, '8th' => 3, '9th' => 3],
    ];
  }

  /**
   * Normalize rank labels like 1 / 1st / first to the sheet slot keys.
   */
  private function normalizeSpellSlotKey(string $rank): ?string {
    $normalized = strtolower(trim($rank));
    return match ($normalized) {
      '1', '1st', 'first' => 'first',
      '2', '2nd', 'second' => 'second',
      '3', '3rd', 'third' => 'third',
      '4', '4th', 'fourth' => 'fourth',
      '5', '5th', 'fifth' => 'fifth',
      '6', '6th', 'sixth' => 'sixth',
      '7', '7th', 'seventh' => 'seventh',
      '8', '8th', 'eighth' => 'eighth',
      '9', '9th', 'ninth' => 'ninth',
      default => NULL,
    };
  }

  /**
   * Resolve a coarse class family for rollout/reporting.
   */
  private function resolveClassFamily(string $class_id, array $spellcasting_deltas, array $class_data): string {
    if ($spellcasting_deltas !== []) {
      $casting_type = (string) ($spellcasting_deltas['casting_type'] ?? '');
      if ($casting_type === 'prepared') {
        return 'prepared-caster';
      }
      if ($casting_type === 'spontaneous') {
        return 'spontaneous-caster';
      }
      return 'caster';
    }

    if ($class_id === 'rogue' || $class_id === 'investigator') {
      return 'skill-specialist';
    }

    return !empty($class_data['familiar']) ? 'resource-specialist' : 'martial';
  }

  /**
   * Surface focus pool/resource deltas separately from spell slots.
   */
  private function resolveFocusDeltas(array $spellcasting_deltas): array {
    if (empty($spellcasting_deltas['focus_pool_start'])) {
      return [];
    }

    return [
      'focus_pool_start' => (int) $spellcasting_deltas['focus_pool_start'],
    ];
  }

  /**
   * Report unresolved subclass branch requirements.
   */
  private function resolveBranchRequirements(array $class_data, array $character_data): array {
    $current = strtolower(trim((string) ($character_data['subclass'] ?? $character_data['basicInfo']['subclass'] ?? '')));
    $branch_fields = [
      'instincts' => 'instinct',
      'causes' => 'cause',
      'rackets' => 'racket',
      'muses' => 'muse',
      'orders' => 'order',
      'mysteries' => 'mystery',
      'bloodlines' => 'bloodline',
      'arcane_theses' => 'arcane thesis',
      'patrons' => 'patron',
      'methodologies' => 'methodology',
      'styles' => 'style',
      'edges' => 'hunter edge',
    ];

    foreach ($branch_fields as $field => $label) {
      if (!empty($class_data[$field]) && $current === '') {
        return [[
          'field' => $field,
          'label' => ucfirst($label),
          'required' => TRUE,
        ]];
      }
    }

    return [];
  }

}
