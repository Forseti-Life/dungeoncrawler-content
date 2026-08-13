<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Projects canonical effect instances onto actor-sheet read models.
 */
class EffectProjectionService {

  protected EffectInstanceService $effectInstanceService;

  public function __construct(EffectInstanceService $effect_instance_service) {
    $this->effectInstanceService = $effect_instance_service;
  }

  /**
   * Builds actor-scoped projection slices from active effect instances.
   *
   * @return array{instances:array<int,array>,adjustments:array<string,int>,condition_tooltips:array<string,array>}
   */
  public function projectPersistentActorEffects(
    string $character_id,
    ?int $campaign_id,
    ?string $instance_id,
    array $conditions = [],
  ): array {
    if (trim($character_id) === '' || !$this->effectInstanceService->hasStorage()) {
      return [
        'instances' => [],
        'adjustments' => ['armor_class' => 0, 'speed' => 0],
        'condition_tooltips' => [],
      ];
    }

    $instances = $this->effectInstanceService->listActivePersistentActorEffectInstances(
      $character_id,
      $campaign_id,
      $instance_id
    );
    $adjustments = $this->effectInstanceService->buildPersistentAdjustmentProjection($instances);

    return [
      'instances' => $instances,
      'adjustments' => [
        'armor_class' => (int) ($adjustments['armor_class'] ?? 0),
        'speed' => (int) ($adjustments['speed'] ?? 0),
      ],
      'condition_tooltips' => $this->buildConditionTooltipProjection($conditions, $instances),
    ];
  }

  /**
   * Resolves a definition-backed condition tooltip.
   */
  public function resolveConditionTooltip(string $condition_code, array $condition = []): ?array {
    $value = (int) ($condition['value'] ?? $condition['amount'] ?? $condition['penalty'] ?? 0);
    return $this->effectInstanceService->buildTooltipModelForDefinition($condition_code, ['value' => $value]);
  }

  /**
   * Builds tooltip metadata projection for active condition entries.
   */
  private function buildConditionTooltipProjection(array $conditions, array $effect_instances): array {
    $tooltips = [];
    foreach ($effect_instances as $instance) {
      if (!is_array($instance)) {
        continue;
      }
      $code = strtolower(trim((string) ($instance['target_subscope'] ?? '')));
      if ($code === '') {
        continue;
      }
      $tooltip = $this->effectInstanceService->buildTooltipModelForInstance($instance);
      if (is_array($tooltip) && $tooltip !== []) {
        $tooltips[$code] = $tooltip;
      }
    }

    foreach ($conditions as $condition) {
      if (!is_array($condition)) {
        continue;
      }
      $code = strtolower(str_replace([' ', '-'], '_', trim((string) ($condition['condition_type'] ?? $condition['id'] ?? $condition['name'] ?? ''))));
      if ($code === '' || isset($tooltips[$code])) {
        continue;
      }
      $tooltip = $this->resolveConditionTooltip($code, $condition);
      if (is_array($tooltip) && $tooltip !== []) {
        $tooltips[$code] = $tooltip;
      }
    }

    return $tooltips;
  }

}
