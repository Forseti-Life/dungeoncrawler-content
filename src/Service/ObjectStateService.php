<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Unified retrieval facade for canonical object state.
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

  public function __construct(
    protected CampaignStateService $campaignStateService,
    protected DungeonStateService $dungeonStateService,
    protected RoomStateService $roomStateService,
    protected ActorStateService $actorStateService,
    protected EncounterStateService $encounterStateService,
    protected ItemStateService $itemStateService,
    protected InventoryStateService $inventoryStateService,
    protected QuestStateService $questStateService,
    protected EffectStateService $effectStateService,
  ) {}

  /**
   * Retrieve current state for one object by type and identifier.
   *
   * @param array<string,mixed> $context
   *   Additional routing context such as campaign_id, instance_id, or owner_type.
   *
   * @return array<string,mixed>
   *   Envelope containing object metadata and canonical state payload.
   */
  public function getCurrentState(string $object_type, string|int $object_id, array $context = []): array {
    $normalized_type = $this->normalizeObjectType($object_type);
    $normalized_id = trim((string) $object_id);
    if ($normalized_id === '') {
      throw new \InvalidArgumentException('Object id is required.');
    }

    $state = match ($normalized_type) {
      self::TYPE_CAMPAIGN => $this->campaignStateService->getState((int) $normalized_id),
      self::TYPE_DUNGEON => $this->dungeonStateService->getState(
        $normalized_id,
        $this->requireContextInt($context, 'campaign_id', 'Dungeon state requires campaign_id context.')
      ),
      self::TYPE_ROOM => $this->roomStateService->getState(
        $this->requireContextInt($context, 'campaign_id', 'Room state requires campaign_id context.'),
        $normalized_id
      ),
      self::TYPE_ACTOR => $this->actorStateService->getState(
        $normalized_id,
        $this->optionalContextInt($context, 'campaign_id'),
        $this->optionalContextString($context, 'instance_id')
      ),
      self::TYPE_ENCOUNTER => $this->encounterStateService->getState((int) $normalized_id),
      self::TYPE_ITEM => $this->itemStateService->getState(
        $normalized_id,
        $this->optionalContextInt($context, 'campaign_id')
      ),
      self::TYPE_INVENTORY => $this->inventoryStateService->getState(
        $normalized_id,
        $this->optionalContextString($context, 'owner_type') ?: 'character',
        $this->optionalContextInt($context, 'campaign_id')
      ),
      self::TYPE_QUEST => $this->questStateService->getState(
        $this->requireContextInt($context, 'campaign_id', 'Quest state requires campaign_id context.'),
        $normalized_id,
        $this->optionalContextInt($context, 'character_id')
      ),
      self::TYPE_EFFECTS => $this->effectStateService->getState(
        $normalized_id,
        $this->optionalContextInt($context, 'campaign_id'),
        $this->optionalContextString($context, 'instance_id')
      ),
      default => throw new \InvalidArgumentException(sprintf('Unsupported object type: %s', $object_type)),
    };

    return [
      'object_type' => $normalized_type,
      'object_id' => $normalized_id,
      'context' => $context,
      'state' => $state,
    ];
  }

  /**
   * Retrieve current state from a structured object reference.
   *
   * @param array<string,mixed> $object_ref
   *   Keys: object_type, object_id, optional context.
   *
   * @return array<string,mixed>
   *   Canonical state envelope.
   */
  public function getCurrentStateByRef(array $object_ref): array {
    $object_type = (string) ($object_ref['object_type'] ?? $object_ref['type'] ?? '');
    $object_id = (string) ($object_ref['object_id'] ?? $object_ref['id'] ?? '');
    $context = is_array($object_ref['context'] ?? NULL) ? $object_ref['context'] : [];

    foreach (['campaign_id', 'character_id', 'instance_id', 'owner_type'] as $key) {
      if (array_key_exists($key, $object_ref) && !array_key_exists($key, $context)) {
        $context[$key] = $object_ref[$key];
      }
    }

    return $this->getCurrentState($object_type, $object_id, $context);
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
      default => $normalized,
    };
  }

  /**
   * @param array<string,mixed> $context
   */
  protected function requireContextInt(array $context, string $key, string $message): int {
    $value = $this->optionalContextInt($context, $key);
    if ($value === NULL || $value <= 0) {
      throw new \InvalidArgumentException($message);
    }

    return $value;
  }

  /**
   * @param array<string,mixed> $context
   */
  protected function optionalContextInt(array $context, string $key): ?int {
    if (!array_key_exists($key, $context) || $context[$key] === NULL || $context[$key] === '') {
      return NULL;
    }
    if (!is_numeric($context[$key])) {
      throw new \InvalidArgumentException(sprintf('Context value for %s must be numeric.', $key));
    }

    return (int) $context[$key];
  }

  /**
   * @param array<string,mixed> $context
   */
  protected function optionalContextString(array $context, string $key): ?string {
    if (!array_key_exists($key, $context) || $context[$key] === NULL) {
      return NULL;
    }

    $value = trim((string) $context[$key]);
    return $value !== '' ? $value : NULL;
  }

}
