<?php

namespace Drupal\dungeoncrawler_content;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Module service provider enforcing object-state authority guards.
 *
 * Collects every service tagged as an object-state provider and wires the
 * single canonical provider per object type into ObjectStateProviderRegistry.
 * It is the container-level guard against competing current-state owners: two
 * providers claiming the same object_type is a hard build-time failure.
 *
 * Phase 2 additionally freezes the encounter current-state authority:
 * - the single encounter object-state provider must be
 *   {@see \Drupal\dungeoncrawler_content\Service\EncounterStateService}, and
 * - only an explicit frozen allowlist of persistence/orchestration internals
 *   may inject the low-level CombatEncounterStore. Any new service definition
 *   that injects the store outside the allowlist hard-fails the container
 *   build, preventing new direct public read consumers of the raw encounter
 *   store or a competing coordinator encounter assembly.
 *
 * Phase 3 additionally freezes the item current-state authority:
 * - the single item object-state provider must be
 *   {@see \Drupal\dungeoncrawler_content\Service\ItemStateService}, and
 * - only an explicit frozen allowlist may inject the low-level
 *   ItemInstanceStore. Any new service definition that injects the store
 *   outside the allowlist hard-fails the container build, preventing new direct
 *   public read consumers of the raw item-instance persistence lane.
 */
class DungeoncrawlerContentServiceProvider extends ServiceProviderBase {

  /**
   * Low-level combat encounter persistence store service id.
   */
  private const COMBAT_ENCOUNTER_STORE_ID = 'dungeoncrawler_content.combat_encounter_store';

  /**
   * The single canonical encounter current-state provider service id.
   */
  private const ENCOUNTER_PROVIDER_ID = 'dungeoncrawler_content.encounter_state';

  /**
   * Low-level item-instance persistence store service id (Phase 3).
   */
  private const ITEM_INSTANCE_STORE_ID = 'dungeoncrawler_content.item_instance_store';

  /**
   * The single canonical item current-state provider service id (Phase 3).
   */
  private const ITEM_PROVIDER_ID = 'dungeoncrawler_content.item_state';

  /**
   * Frozen allowlist of services permitted to inject ItemInstanceStore.
   *
   * The raw item-instance persistence lane is consumed for current-state reads
   * by exactly one service: the canonical owner ItemStateService. This list is
   * expected to SHRINK over time, never grow. Adding an entry requires an
   * explicit rationale here; presentation/read consumers must instead route
   * item current-state reads through the canonical owner ItemStateService.
   *
   * @var array<string,string>
   *   Map of service id => rationale.
   */
  private const ITEM_INSTANCE_STORE_ALLOWLIST = [
    // The canonical item current-state owner (Phase 3). Persistence stays in the
    // store; this owner is the only public current-state authority for items.
    self::ITEM_PROVIDER_ID => 'canonical item current-state owner',
  ];

  /**
   * Frozen allowlist of services permitted to inject CombatEncounterStore.
   *
   * These are the only legitimate persistence/orchestration internals. This
   * list is expected to SHRINK over time, never grow. Adding an entry requires
   * an explicit rationale here; presentation/read consumers must instead route
   * through the canonical owner EncounterStateService.
   *
   * @var array<string,string>
   *   Map of service id => rationale.
   */
  private const COMBAT_ENCOUNTER_STORE_ALLOWLIST = [
    // The canonical encounter current-state owner (Phase 2). Persistence stays
    // in the store; this owner is the only public current-state authority.
    self::ENCOUNTER_PROVIDER_ID => 'canonical encounter current-state owner',
    // Combat write-lane engine: mutates encounter/participant rows.
    'dungeoncrawler_content.combat_engine' => 'combat write-lane engine (mutation orchestration)',
    // Encounter phase handler: turn/round mutation orchestration.
    'dungeoncrawler_content.encounter_phase_handler' => 'encounter phase-handler write lane',
    // Action executor: applies action resolution to encounter state.
    'dungeoncrawler_content.encounter_action_executor' => 'combat action executor write lane',
    // Reaction handler: reaction resolution write lane.
    'dungeoncrawler_content.reaction_handler' => 'reaction resolution write lane',
    // Unified damage engine: canonical damage application write lane.
    'dungeoncrawler_content.unified_damage_engine' => 'unified damage application write lane',
    // Canonical projection/sync support used inside the combat write lane.
    'dungeoncrawler_content.canonical_projection_service' => 'canonical projection/sync write-lane support',
  ];

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if (!$container->hasDefinition('dungeoncrawler_content.object_state_provider_registry')) {
      return;
    }

    $seen = [];
    $references = [];
    foreach ($container->findTaggedServiceIds('dungeoncrawler_content.object_state_provider') as $id => $tags) {
      foreach ($tags as $attributes) {
        $object_type = isset($attributes['object_type']) ? strtolower(trim((string) $attributes['object_type'])) : '';
        if ($object_type === '') {
          throw new \RuntimeException(sprintf(
            'Object-state provider "%s" must declare a non-empty object_type tag attribute.',
            $id
          ));
        }
        if (isset($seen[$object_type])) {
          throw new \RuntimeException(sprintf(
            'Competing object-state providers for type "%s": %s and %s. Exactly one provider is allowed per object type.',
            $object_type,
            $seen[$object_type],
            $id
          ));
        }
        $seen[$object_type] = $id;
        $references[$object_type] = new Reference($id);
      }
    }

    ksort($references);

    $registry = $container->getDefinition('dungeoncrawler_content.object_state_provider_registry');
    $registry->setArgument(0, array_values($references));

    $this->assertEncounterAuthority($container, $seen);
    $this->assertItemAuthority($container, $seen);
  }

  /**
   * Enforce the frozen encounter current-state authority (Phase 2).
   *
   * @param array<string,string> $providers_by_type
   *   Map of object_type => provider service id discovered above.
   */
  private function assertEncounterAuthority(ContainerBuilder $container, array $providers_by_type): void {
    // 1) The single encounter provider must be the promoted owner.
    $encounter_provider = $providers_by_type['encounter'] ?? NULL;
    if ($encounter_provider !== NULL && $encounter_provider !== self::ENCOUNTER_PROVIDER_ID) {
      throw new \RuntimeException(sprintf(
        'The single encounter object-state provider must be "%s", got "%s". Encounter current-state has exactly one canonical owner.',
        self::ENCOUNTER_PROVIDER_ID,
        $encounter_provider
      ));
    }

    // 2) Only allowlisted persistence/orchestration internals may inject the
    // low-level CombatEncounterStore. Everything else must route through the
    // canonical owner.
    foreach ($container->getDefinitions() as $id => $definition) {
      if (!$this->definitionReferencesService($definition->getArguments(), self::COMBAT_ENCOUNTER_STORE_ID)) {
        continue;
      }
      if (!array_key_exists($id, self::COMBAT_ENCOUNTER_STORE_ALLOWLIST)) {
        throw new \RuntimeException(sprintf(
          'Service "%s" injects the low-level %s but is not in the frozen encounter persistence/orchestration allowlist. '
          . 'Presentation/read consumers must route encounter current-state reads through the canonical owner "%s".',
          $id,
          self::COMBAT_ENCOUNTER_STORE_ID,
          self::ENCOUNTER_PROVIDER_ID
        ));
      }
    }
  }

  /**
   * Recursively determine whether arguments reference a given service id.
   *
   * @param mixed $arguments
   */
  private function definitionReferencesService($arguments, string $service_id): bool {
    if ($arguments instanceof Reference) {
      return (string) $arguments === $service_id;
    }
    if (is_array($arguments)) {
      foreach ($arguments as $argument) {
        if ($this->definitionReferencesService($argument, $service_id)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Enforce the frozen item current-state authority (Phase 3).
   *
   * @param array<string,string> $providers_by_type
   *   Map of object_type => provider service id discovered above.
   */
  private function assertItemAuthority(ContainerBuilder $container, array $providers_by_type): void {
    // 1) The single item provider must be the promoted owner.
    $item_provider = $providers_by_type['item'] ?? NULL;
    if ($item_provider !== NULL && $item_provider !== self::ITEM_PROVIDER_ID) {
      throw new \RuntimeException(sprintf(
        'The single item object-state provider must be "%s", got "%s". Item current-state has exactly one canonical owner.',
        self::ITEM_PROVIDER_ID,
        $item_provider
      ));
    }

    // 2) Only allowlisted internals may inject the low-level ItemInstanceStore.
    // Everything else must route item current-state reads through the owner.
    foreach ($container->getDefinitions() as $id => $definition) {
      if (!$this->definitionReferencesService($definition->getArguments(), self::ITEM_INSTANCE_STORE_ID)) {
        continue;
      }
      if (!array_key_exists($id, self::ITEM_INSTANCE_STORE_ALLOWLIST)) {
        throw new \RuntimeException(sprintf(
          'Service "%s" injects the low-level %s but is not in the frozen item persistence allowlist. '
          . 'Presentation/read consumers must route item current-state reads through the canonical owner "%s".',
          $id,
          self::ITEM_INSTANCE_STORE_ID,
          self::ITEM_PROVIDER_ID
        ));
      }
    }
  }

}