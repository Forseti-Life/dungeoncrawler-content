<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Builds canonical impact contracts for persistent effect sources.
 */
class ImpactContractService {

  public const PHASE_PERSISTENT_SHEET = 'persistent-sheet';

  public const SOURCE_EQUIPMENT = 'equipment';
  public const SOURCE_FEAT = 'feat';
  public const SOURCE_SPELL_AUGMENT = 'spell-augment';
  public const SOURCE_CONDITION = 'condition';

  public const OPERATION_ADD = 'add';
  public const OPERATION_CAP = 'cap';
  public const OPERATION_MAX = 'max';
  public const OPERATION_GRANT = 'grant';

  public const STACKING_ITEM = 'item';
  public const STACKING_STATUS = 'status';
  public const STACKING_CIRCUMSTANCE = 'circumstance';
  public const STACKING_UNTYPED = 'untyped';

  public const TARGET_AC_ARMOR_BONUS = 'defenses.armorClass.armorBonus';
  public const TARGET_AC_DEX_MODIFIER = 'defenses.armorClass.dexterityModifier';
  public const TARGET_AC_SHIELD_BONUS = 'defenses.armorClass.shieldBonus';
  public const TARGET_AC_OTHER_BONUSES = 'defenses.armorClass.otherBonuses';
  public const TARGET_SPEED_TOTAL = 'movement.speed.total';
  public const TARGET_HP_MAX = 'resources.hitPoints.max';
  public const TARGET_INITIATIVE_BONUS = 'defenses.initiative.featBonus';
  public const TARGET_PERCEPTION_BONUS = 'defenses.perception.featBonus';
  public const TARGET_CHECKS_ARMOR_PENALTY = 'checks.armorPenalty';
  public const TARGET_SPELLS_METAMAGIC = 'spells.metamagic';
  public const TARGET_SPELLS_INNATE = 'spells.innate';
  public const TARGET_SENSES = 'senses';

  /**
   * Builds and normalizes impact contracts for persistent-sheet sources.
   */
  public function buildPersistentImpacts(
    array $feat_effects,
    array $equipment_effects,
    array $condition_effects,
  ): array {
    return $this->normalizeImpactContracts(array_merge(
      $this->buildEquipmentImpactContracts($equipment_effects),
      $this->buildFeatImpactContracts($feat_effects),
      $this->buildConditionImpactContracts($condition_effects),
    ));
  }

  /**
   * Normalizes and stabilizes canonical impact contract ordering.
   */
  public function normalizeImpactContracts(array $impacts): array {
    $normalized = array_map(function (array $impact): array {
      return [
        'source_type' => (string) ($impact['source_type'] ?? ''),
        'source_id' => (string) ($impact['source_id'] ?? ''),
        'target' => (string) ($impact['target'] ?? ''),
        'operation' => (string) ($impact['operation'] ?? ''),
        'value' => $impact['value'] ?? 0,
        'stacking' => (string) ($impact['stacking'] ?? self::STACKING_UNTYPED),
        'phase' => (string) ($impact['phase'] ?? self::PHASE_PERSISTENT_SHEET),
        'conditions' => is_array($impact['conditions'] ?? NULL) ? $impact['conditions'] : [],
        'breakdown_key' => $impact['breakdown_key'] ?? NULL,
        'metadata' => is_array($impact['metadata'] ?? NULL) ? $impact['metadata'] : [],
      ];
    }, array_values(array_filter($impacts, 'is_array')));

    usort($normalized, function (array $left, array $right): int {
      return [$left['source_type'], $left['target'], $left['source_id']]
        <=> [$right['source_type'], $right['target'], $right['source_id']];
    });

    return $normalized;
  }

  /**
   * Builds impact contracts for normalized equipment effects.
   */
  public function buildEquipmentImpactContracts(array $equipment_effects): array {
    $impacts = [];
    $armor = is_array($equipment_effects['armor'] ?? NULL) ? $equipment_effects['armor'] : [];
    $shield = is_array($equipment_effects['shield'] ?? NULL) ? $equipment_effects['shield'] : [];

    if (($armor['item_id'] ?? '') !== '' && (int) ($armor['armor_bonus'] ?? 0) !== 0) {
      $impacts[] = $this->buildImpactContractEntry(
        self::SOURCE_EQUIPMENT,
        (string) $armor['item_id'],
        self::TARGET_AC_ARMOR_BONUS,
        self::OPERATION_ADD,
        (int) $armor['armor_bonus'],
        self::STACKING_ITEM,
        self::PHASE_PERSISTENT_SHEET,
        [],
        'armorClass',
        ['label' => (string) ($armor['name'] ?? ''), 'kind' => 'armor']
      );
    }
    if (($armor['item_id'] ?? '') !== '' && array_key_exists('dex_cap', $armor) && $armor['dex_cap'] !== NULL) {
      $impacts[] = $this->buildImpactContractEntry(
        self::SOURCE_EQUIPMENT,
        (string) $armor['item_id'],
        self::TARGET_AC_DEX_MODIFIER,
        self::OPERATION_CAP,
        (int) $armor['dex_cap'],
        self::STACKING_ITEM,
        self::PHASE_PERSISTENT_SHEET,
        [],
        'armorClass',
        ['label' => (string) ($armor['name'] ?? ''), 'kind' => 'armor']
      );
    }
    if (($armor['item_id'] ?? '') !== '' && (int) ($armor['speed_penalty'] ?? 0) !== 0) {
      $impacts[] = $this->buildImpactContractEntry(
        self::SOURCE_EQUIPMENT,
        (string) $armor['item_id'],
        self::TARGET_SPEED_TOTAL,
        self::OPERATION_ADD,
        (int) $armor['speed_penalty'],
        self::STACKING_ITEM,
        self::PHASE_PERSISTENT_SHEET,
        [],
        'speed',
        ['label' => (string) ($armor['name'] ?? ''), 'kind' => 'armor']
      );
    }
    if (($armor['item_id'] ?? '') !== '' && (int) ($armor['check_penalty'] ?? 0) !== 0) {
      $impacts[] = $this->buildImpactContractEntry(
        self::SOURCE_EQUIPMENT,
        (string) $armor['item_id'],
        self::TARGET_CHECKS_ARMOR_PENALTY,
        self::OPERATION_ADD,
        (int) ($armor['check_penalty'] ?? 0),
        self::STACKING_ITEM,
        self::PHASE_PERSISTENT_SHEET,
        [],
        'checks',
        ['label' => (string) ($armor['name'] ?? ''), 'kind' => 'armor']
      );
    }
    if (($shield['item_id'] ?? '') !== '' && (int) ($shield['shield_bonus'] ?? 0) !== 0) {
      $impacts[] = $this->buildImpactContractEntry(
        self::SOURCE_EQUIPMENT,
        (string) $shield['item_id'],
        self::TARGET_AC_SHIELD_BONUS,
        self::OPERATION_ADD,
        (int) ($shield['shield_bonus'] ?? 0),
        self::STACKING_ITEM,
        self::PHASE_PERSISTENT_SHEET,
        [],
        'armorClass',
        ['label' => (string) ($shield['name'] ?? ''), 'kind' => 'shield']
      );
    }

    return $impacts;
  }

  /**
   * Builds impact contracts for feat-derived persistent effects.
   */
  public function buildFeatImpactContracts(array $feat_effects): array {
    $impacts = [];
    $applied_feats = array_values(array_filter(array_map('strval', is_array($feat_effects['applied_feats'] ?? NULL) ? $feat_effects['applied_feats'] : [])));
    $derived = is_array($feat_effects['derived_adjustments'] ?? NULL) ? $feat_effects['derived_adjustments'] : [];
    $spell_augments = is_array($feat_effects['spell_augments'] ?? NULL) ? $feat_effects['spell_augments'] : [];
    $senses = is_array($feat_effects['senses'] ?? NULL) ? $feat_effects['senses'] : [];

    if ((int) ($derived['hp_max_bonus'] ?? 0) !== 0) {
      $impacts[] = $this->buildImpactContractEntry(
        self::SOURCE_FEAT,
        'derived_adjustments.hp_max_bonus',
        self::TARGET_HP_MAX,
        self::OPERATION_ADD,
        (int) $derived['hp_max_bonus'],
        self::STACKING_UNTYPED,
        self::PHASE_PERSISTENT_SHEET,
        [],
        'hitPoints',
        ['applied_feats' => $applied_feats]
      );
    }
    if ((int) ($derived['speed_bonus'] ?? 0) !== 0) {
      $impacts[] = $this->buildImpactContractEntry(
        self::SOURCE_FEAT,
        'derived_adjustments.speed_bonus',
        self::TARGET_SPEED_TOTAL,
        self::OPERATION_ADD,
        (int) $derived['speed_bonus'],
        self::STACKING_STATUS,
        self::PHASE_PERSISTENT_SHEET,
        [],
        'speed',
        ['applied_feats' => $applied_feats]
      );
    }
    if (($derived['speed_override'] ?? NULL) !== NULL) {
      $impacts[] = $this->buildImpactContractEntry(
        self::SOURCE_FEAT,
        'derived_adjustments.speed_override',
        self::TARGET_SPEED_TOTAL,
        self::OPERATION_MAX,
        (int) $derived['speed_override'],
        self::STACKING_STATUS,
        self::PHASE_PERSISTENT_SHEET,
        [],
        'speed',
        ['applied_feats' => $applied_feats]
      );
    }
    if ((int) ($derived['initiative_bonus'] ?? 0) !== 0) {
      $impacts[] = $this->buildImpactContractEntry(
        self::SOURCE_FEAT,
        'derived_adjustments.initiative_bonus',
        self::TARGET_INITIATIVE_BONUS,
        self::OPERATION_ADD,
        (int) $derived['initiative_bonus'],
        self::STACKING_CIRCUMSTANCE,
        self::PHASE_PERSISTENT_SHEET,
        [],
        'initiative',
        ['applied_feats' => $applied_feats]
      );
    }
    if ((int) ($derived['perception_bonus'] ?? 0) !== 0) {
      $impacts[] = $this->buildImpactContractEntry(
        self::SOURCE_FEAT,
        'derived_adjustments.perception_bonus',
        self::TARGET_PERCEPTION_BONUS,
        self::OPERATION_ADD,
        (int) $derived['perception_bonus'],
        self::STACKING_CIRCUMSTANCE,
        self::PHASE_PERSISTENT_SHEET,
        [],
        'perception',
        ['applied_feats' => $applied_feats]
      );
    }

    foreach (is_array($spell_augments['metamagic'] ?? NULL) ? $spell_augments['metamagic'] : [] as $augment) {
      if (!is_array($augment)) {
        continue;
      }
      $impacts[] = $this->buildImpactContractEntry(
        self::SOURCE_SPELL_AUGMENT,
        (string) ($augment['id'] ?? 'metamagic'),
        self::TARGET_SPELLS_METAMAGIC,
        self::OPERATION_GRANT,
        1,
        self::STACKING_UNTYPED,
        self::PHASE_PERSISTENT_SHEET,
        [],
        'spells',
        $augment
      );
    }
    foreach (is_array($spell_augments['innate_spells'] ?? NULL) ? $spell_augments['innate_spells'] : [] as $augment) {
      if (!is_array($augment)) {
        continue;
      }
      $impacts[] = $this->buildImpactContractEntry(
        self::SOURCE_SPELL_AUGMENT,
        (string) ($augment['id'] ?? $augment['spell_id'] ?? 'innate-spell'),
        self::TARGET_SPELLS_INNATE,
        self::OPERATION_GRANT,
        1,
        self::STACKING_UNTYPED,
        self::PHASE_PERSISTENT_SHEET,
        [],
        'spells',
        $augment
      );
    }
    foreach ($senses as $sense) {
      if (!is_array($sense)) {
        continue;
      }
      $impacts[] = $this->buildImpactContractEntry(
        self::SOURCE_FEAT,
        (string) ($sense['id'] ?? $sense['name'] ?? 'sense'),
        self::TARGET_SENSES,
        self::OPERATION_GRANT,
        1,
        self::STACKING_UNTYPED,
        self::PHASE_PERSISTENT_SHEET,
        [],
        'senses',
        $sense
      );
    }

    return $impacts;
  }

  /**
   * Builds impact contracts for persistent conditions.
   */
  public function buildConditionImpactContracts(array $condition_effects): array {
    $impacts = [];

    foreach (is_array($condition_effects['active'] ?? NULL) ? $condition_effects['active'] : [] as $condition) {
      if (!is_array($condition)) {
        continue;
      }
      $code = (string) ($condition['code'] ?? '');
      $value = (int) ($condition['value'] ?? 0);
      $base_metadata = [
        'label' => (string) ($condition['label'] ?? ''),
      ];

      if ($code === 'flat_footed') {
        $impacts[] = $this->buildImpactContractEntry(
          self::SOURCE_CONDITION,
          (string) ($condition['id'] ?? $code),
          self::TARGET_AC_OTHER_BONUSES,
          self::OPERATION_ADD,
          -2,
          self::STACKING_STATUS,
          self::PHASE_PERSISTENT_SHEET,
          [],
          'armorClass',
          $base_metadata + ['code' => $code]
        );
        continue;
      }
      if ($code === 'frightened') {
        $impacts[] = $this->buildImpactContractEntry(
          self::SOURCE_CONDITION,
          (string) ($condition['id'] ?? $code),
          self::TARGET_AC_OTHER_BONUSES,
          self::OPERATION_ADD,
          -max(1, $value),
          self::STACKING_STATUS,
          self::PHASE_PERSISTENT_SHEET,
          [],
          'armorClass',
          $base_metadata + ['code' => $code]
        );
        continue;
      }
      if (str_starts_with($code, 'speed_penalty_')) {
        $impacts[] = $this->buildImpactContractEntry(
          self::SOURCE_CONDITION,
          (string) ($condition['id'] ?? $code),
          self::TARGET_SPEED_TOTAL,
          self::OPERATION_ADD,
          -max(0, $value),
          self::STACKING_STATUS,
          self::PHASE_PERSISTENT_SHEET,
          [],
          'speed',
          $base_metadata + ['code' => $code]
        );
        continue;
      }
    }

    return $impacts;
  }

  /**
   * Creates a canonical impact contract entry.
   */
  public function buildImpactContractEntry(
    string $source_type,
    string $source_id,
    string $target,
    string $operation,
    int|float $value,
    string $stacking,
    string $phase,
    array $conditions = [],
    ?string $breakdown_key = NULL,
    array $metadata = [],
  ): array {
    return [
      'source_type' => $source_type,
      'source_id' => $source_id,
      'target' => $target,
      'operation' => $operation,
      'value' => $value,
      'stacking' => $stacking,
      'phase' => $phase,
      'conditions' => $conditions,
      'breakdown_key' => $breakdown_key,
      'metadata' => $metadata,
    ];
  }

}
