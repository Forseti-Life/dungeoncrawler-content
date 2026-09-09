<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderRegistry;

/**
 * Unified gateway for canonical object state.
 *
 * This service normalizes an object-type alias, builds a canonical
 * {@see ObjectRef}, and delegates to the single registered provider for that
 * type via {@see ObjectStateProviderRegistry}. It routes every supported read
 * through exactly one provider and hard-fails on missing/duplicate providers or
 * invalid refs/envelopes. It never composes state itself and never falls back
 * to a second owner.
 */
class ObjectStateService {

  public const TYPE_CAMPAIGN = 'campaign';
  public const TYPE_DUNGEON = 'dungeon';
  public const TYPE_ROOM = 'room';
  public const TYPE_ACTOR = 'actor';
  public const TYPE_ENCOUNTER = 'encounter';
  public const TYPE_ITEM = 'item';
  public const TYPE_INVENTORY = 'inventory';
  public const TYPE_QUEST = 'quest';
  public const TYPE_EFFECTS = 'effects';
  public const TYPE_SOCIAL = 'social';

  public function __construct(
    protected ObjectStateProviderRegistry $providerRegistry,
  ) {}

  /**
   * Retrieve current state for one object by type and identifier.
   *
   * @param array<string,mixed> $context
   *   Additional routing context such as campaign_id, instance_id, or owner_type.
   *
   * @return array<string,mixed>
   *   Canonical object-state envelope.
   */
  public function getCurrentState(string $object_type, string|int $object_id, array $context = []): array {
    $ref = ObjectRef::create($this->normalizeObjectType($object_type), $object_id, $context);

    return $this->providerRegistry->getObjectState($ref)->toArray();
  }

  /**
   * Retrieve current state from a structured object reference.
   *
   * @param array<string,mixed> $object_ref
   *   Keys: object_type, object_id, optional context.
   *
   * @return array<string,mixed>
   *   Canonical object-state envelope.
   */
  public function getCurrentStateByRef(array $object_ref): array {
    $ref = ObjectRef::fromArray($object_ref);
    $normalized = ObjectRef::create($this->normalizeObjectType($ref->objectType), $ref->objectId, $ref->context);

    return $this->providerRegistry->getObjectState($normalized)->toArray();
  }

  /**
   * Whether a canonical provider is registered for the given object type.
   */
  public function supports(string $object_type): bool {
    return $this->providerRegistry->hasProvider($this->normalizeObjectType($object_type));
  }

  protected function normalizeObjectType(string $object_type): string {
    $normalized = strtolower(trim($object_type));

    return match ($normalized) {
      'campaign' => self::TYPE_CAMPAIGN,
      'dungeon', 'map' => self::TYPE_DUNGEON,
      'room' => self::TYPE_ROOM,
      'actor', 'character', 'pc', 'npc' => self::TYPE_ACTOR,
      'encounter', 'combat' => self::TYPE_ENCOUNTER,
      'item', 'item_instance' => self::TYPE_ITEM,
      'inventory' => self::TYPE_INVENTORY,
      'quest' => self::TYPE_QUEST,
      'effects', 'active_effects' => self::TYPE_EFFECTS,
      'social', 'social_runtime' => self::TYPE_SOCIAL,
      default => $normalized,
    };
  }

}
