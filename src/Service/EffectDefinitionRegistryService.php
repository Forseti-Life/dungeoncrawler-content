<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Canonical registry for condition/effect lifecycle definitions.
 */
class EffectDefinitionRegistryService {

  public const TRIGGER_NEXT_DAILY_PREPARATIONS = 'next_daily_preparations';

  /**
   * Returns all canonical effect definitions keyed by definition id.
   */
  public function getDefinitions(): array {
    return [
      'mage_armor' => [
        'definition_id' => 'mage_armor',
        'label' => 'Mage Armor',
        'category' => 'condition',
        'phase_scope' => 'persistent-sheet',
        'stacking_type' => 'status',
        'condition_code' => 'mage_armor',
        'instance_managed' => TRUE,
        'impacts' => [
          [
            'target' => ImpactContractService::TARGET_AC_OTHER_BONUSES,
            'operation' => ImpactContractService::OPERATION_ADD,
            'value' => 1,
          ],
        ],
        'expiration_policy' => [
          'trigger' => self::TRIGGER_NEXT_DAILY_PREPARATIONS,
        ],
        'tooltip' => [
          'summary' => 'You gain +1 status bonus to AC until your next daily preparations.',
          'mechanics' => [
            [
              'target' => 'Armor Class',
              'operation' => '+',
              'value' => 1,
              'type' => 'status',
            ],
          ],
        ],
      ],
      'flat_footed' => [
        'definition_id' => 'flat_footed',
        'label' => 'Flat-Footed',
        'category' => 'condition',
        'phase_scope' => 'persistent-sheet',
        'stacking_type' => 'status',
        'condition_code' => 'flat_footed',
        'instance_managed' => FALSE,
        'impacts' => [
          [
            'target' => ImpactContractService::TARGET_AC_OTHER_BONUSES,
            'operation' => ImpactContractService::OPERATION_ADD,
            'value' => -2,
          ],
        ],
        'tooltip' => [
          'summary' => 'Your defenses are compromised.',
        ],
      ],
      'frightened' => [
        'definition_id' => 'frightened',
        'label' => 'Frightened',
        'category' => 'condition',
        'phase_scope' => 'persistent-sheet',
        'stacking_type' => 'status',
        'condition_code' => 'frightened',
        'instance_managed' => FALSE,
        'impacts' => [
          [
            'target' => ImpactContractService::TARGET_AC_OTHER_BONUSES,
            'operation' => ImpactContractService::OPERATION_ADD,
            'value' => -1,
          ],
        ],
        'tooltip' => [
          'summary' => 'Fear unsettles your defenses.',
        ],
      ],
    ];
  }

  /**
   * Returns a canonical effect definition.
   */
  public function getDefinition(string $definition_id): ?array {
    $key = strtolower(trim($definition_id));
    if ($key === '') {
      return NULL;
    }
    return $this->getDefinitions()[$key] ?? NULL;
  }

  /**
   * Returns true when a canonical definition exists.
   */
  public function hasDefinition(string $definition_id): bool {
    return $this->getDefinition($definition_id) !== NULL;
  }

  /**
   * Returns true when a definition should be persisted as effect instances.
   */
  public function isInstanceManagedDefinition(string $definition_id): bool {
    $definition = $this->getDefinition($definition_id);
    return is_array($definition) && !empty($definition['instance_managed']);
  }

  /**
   * Returns definitions that expire on a specific trigger.
   */
  public function listDefinitionsByExpirationTrigger(string $trigger): array {
    $needle = strtolower(trim($trigger));
    if ($needle === '') {
      return [];
    }

    return array_values(array_filter(
      $this->getDefinitions(),
      static function (array $definition) use ($needle): bool {
        $policy = is_array($definition['expiration_policy'] ?? NULL)
          ? $definition['expiration_policy']
          : [];
        $configured = strtolower(trim((string) ($policy['trigger'] ?? '')));
        return $configured !== '' && $configured === $needle;
      }
    ));
  }

  /**
   * Builds a tooltip payload from a definition and optional instance context.
   */
  public function buildTooltipModel(string $definition_id, array $instance = [], array $context = []): ?array {
    $definition = $this->getDefinition($definition_id);
    if (!is_array($definition)) {
      return NULL;
    }

    $tooltip = is_array($definition['tooltip'] ?? NULL) ? $definition['tooltip'] : [];
    $impacts = is_array($definition['impacts'] ?? NULL) ? $definition['impacts'] : [];
    $value_payload = is_array($instance['value_payload'] ?? NULL) ? $instance['value_payload'] : [];
    $instance_impacts = is_array($value_payload['impacts'] ?? NULL) ? $value_payload['impacts'] : [];
    $resolved_impacts = $instance_impacts !== [] ? $instance_impacts : $impacts;
    if ($instance_impacts === [] && strtolower((string) ($definition['definition_id'] ?? '')) === 'frightened') {
      $value = (int) ($context['value'] ?? 1);
      $value = max(1, $value);
      $resolved_impacts = [[
        'target' => ImpactContractService::TARGET_AC_OTHER_BONUSES,
        'operation' => ImpactContractService::OPERATION_ADD,
        'value' => -$value,
      ]];
    }

    $stats = [];
    foreach ($resolved_impacts as $impact) {
      if (!is_array($impact)) {
        continue;
      }
      $target = (string) ($impact['target'] ?? '');
      $operation = strtolower(trim((string) ($impact['operation'] ?? '')));
      $value = (int) ($impact['value'] ?? 0);
      if ($target === ImpactContractService::TARGET_AC_OTHER_BONUSES && $operation === ImpactContractService::OPERATION_ADD) {
        $stats[] = ['stat' => 'Armor Class', 'val' => sprintf('%+d AC', $value)];
      }
      if ($target === ImpactContractService::TARGET_SPEED_TOTAL && $operation === ImpactContractService::OPERATION_ADD) {
        $stats[] = ['stat' => 'Speed', 'val' => sprintf('%+d ft', $value)];
      }
    }

    $notes = [];
    $trigger = strtolower(trim((string) (($definition['expiration_policy']['trigger'] ?? ''))));
    if ($trigger !== '') {
      $notes[] = 'Expires: ' . str_replace('_', ' ', $trigger);
    }

    return [
      'name' => (string) ($definition['label'] ?? $definition_id),
      'type' => 'condition',
      'desc' => (string) ($tooltip['summary'] ?? 'Active condition or effect.'),
      'stats' => $stats,
      'effects' => [],
      'notes' => $notes,
      'definition_id' => (string) ($definition['definition_id'] ?? $definition_id),
      'condition_code' => (string) ($definition['condition_code'] ?? $definition_id),
    ];
  }

}
