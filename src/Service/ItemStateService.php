<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateEnvelope;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderInterface;

/**
 * Canonical current-state retrieval owner for item instances (Phase 3).
 *
 * This is the single public authority that answers "what is this item
 * instance's current state?". Before Phase 3 that question was answered by
 * competing paths: the single-item assembly embedded inside
 * InventoryManagementService::getItemState(), raw
 * `dc_campaign_item_instances` reads scattered across controllers/services, and
 * inference of one item from inventory-shaped `getInventory()` arrays. Phase 3
 * collapses that split:
 *
 * - The reusable single-item current-state assembly is OWNED here, moved (not
 *   copied) out of InventoryManagementService. InventoryManagement is now
 *   inventory collection/write orchestration only and no longer exposes a
 *   competing single-item read shape.
 * - Raw persistence stays in {@see ItemInstanceStore}; this owner is the only
 *   service permitted to consume it for current-state reads (frozen container
 *   allowlist). The store is a pure query lane, extracted only to break the DI
 *   cycle so exactly one current-state authority remains.
 * - The canonical library definition (immutable) is owned by
 *   {@see CanonicalDefinitionService}; this owner binds a mutable campaign
 *   instance to its canonical definition ref/version explicitly and never
 *   substitutes an item by slug/name.
 *
 * Every read is guarded by {@see CampaignLifecycleService}: archived/legacy
 * campaigns hard-fail with `legacy_campaign_archived` and are never read by the
 * current runtime. The read also hard-fails visibly when the instance is
 * missing, or references a missing/quarantined/noncanonical definition, or is
 * scoped to the wrong campaign. There is no compatibility adapter, inventory
 * fallback, inferred legacy shape, or default authority.
 */
class ItemStateService implements ObjectStateProviderInterface {

  public const OBJECT_TYPE = 'item';

  /**
   * Canonical item-instance authority source label.
   */
  public const AUTHORITY_SOURCE = 'campaign_item_instances';

  /**
   * Canonical definition family backing item instances.
   */
  private const DEFINITION_FAMILY = 'item';

  /**
   * Location types that mean the item is held by an actor/character.
   */
  private const ACTOR_LOCATION_TYPES = [
    'character',
    'inventory',
    'carried',
    'equipped',
    'worn',
    'held',
    'stashed',
  ];

  /**
   * Location types that mean the item is equipped/worn/held.
   */
  private const EQUIPPED_LOCATION_TYPES = ['equipped', 'worn', 'held'];

  public function __construct(
    protected ItemInstanceStore $itemInstanceStore,
    protected CanonicalDefinitionService $canonicalDefinitions,
    protected CampaignLifecycleService $campaignLifecycle,
  ) {}

  /**
   * Retrieve the canonical current item-instance state (hard-fail if missing).
   *
   * @param string $item_instance_id
   *   Canonical item instance id.
   * @param int|null $campaign_id
   *   Optional campaign scope. When provided, an instance in a different
   *   campaign is treated as not found (never silently substituted).
   *
   * @return array<string,mixed>
   *   Canonical item-instance state: identity, canonical definition ref/version,
   *   campaign, owner actor/entity, location/container/slot, quantity, state
   *   flags (equipped/consumed/visibility), instance version/timestamps.
   *
   * @throws \InvalidArgumentException
   *   When the instance id is empty or the instance cannot be found.
   * @throws \OutOfBoundsException
   *   When the instance references a missing/quarantined definition.
   * @throws \RuntimeException
   *   When the owning campaign is archived/noncanonical (legacy_campaign_archived).
   */
  public function getState(string $item_instance_id, ?int $campaign_id = NULL): array {
    $item_instance_id = trim($item_instance_id);
    if ($item_instance_id === '') {
      throw new \InvalidArgumentException('Item instance id is required.');
    }

    $row = $this->itemInstanceStore->loadInstanceRow($item_instance_id, $campaign_id);
    if ($row === NULL) {
      throw new \InvalidArgumentException(sprintf('Item instance not found: %s', $item_instance_id));
    }

    return $this->assembleItemState($row);
  }

  /**
   * Assemble the canonical current-state read from a raw item-instance row.
   *
   * Guards the owning campaign first, then binds the canonical definition,
   * then projects owner/location/quantity/state flags uniformly, isolated from
   * inventory list shape.
   *
   * @param array<string,mixed> $row
   *
   * @return array<string,mixed>
   */
  protected function assembleItemState(array $row): array {
    $item_instance_id = (string) ($row['item_instance_id'] ?? '');
    $item_id = (string) ($row['item_id'] ?? '');
    $campaign_id = isset($row['campaign_id']) ? (int) $row['campaign_id'] : NULL;

    // 1) Campaign lifecycle guard: archived/legacy campaigns hard-fail. This is
    // applied to every read, including world/room/container-located items.
    if ($campaign_id !== NULL && $campaign_id > 0) {
      $this->campaignLifecycle->assertLaunchable($campaign_id);
    }

    // 2) Bind the canonical, non-quarantined definition explicitly. Missing or
    // quarantined/noncanonical definitions hard-fail; no slug/name substitution.
    if ($item_id === '') {
      throw new \OutOfBoundsException('item_definition_missing: instance references no definition id.');
    }
    $definition = $this->canonicalDefinitions->requireCanonicalDefinitionRef(self::DEFINITION_FAMILY, $item_id);

    $state = json_decode((string) ($row['state_data'] ?? '{}'), TRUE);
    if (!is_array($state)) {
      $state = [];
    }

    $location_type = strtolower(trim((string) ($row['location_type'] ?? '')));
    $location_ref = (string) ($row['location_ref'] ?? '');
    $quantity = (int) ($row['quantity'] ?? 0);
    $created = isset($row['created']) ? (int) $row['created'] : 0;
    $updated = isset($row['updated']) ? (int) $row['updated'] : 0;

    $metadata = is_array($state['inventory_metadata'] ?? NULL) ? $state['inventory_metadata'] : [];
    $slot = $this->firstNonEmptyString([
      $metadata['equip_slot'] ?? NULL,
      $metadata['worn_slot'] ?? NULL,
      $state['equip_slot'] ?? NULL,
      $state['slot'] ?? NULL,
    ]);

    $flags = $this->deriveStateFlags($state, $location_type, $quantity);
    $owner = $this->resolveOwner($location_type, $location_ref);
    $version = isset($state['version']) && is_numeric($state['version'])
      ? (int) $state['version']
      : ($updated > 0 ? $updated : NULL);
    $updated_at = $this->firstNonEmptyString([
      $state['updated_at'] ?? NULL,
      $updated > 0 ? date(DATE_ATOM, $updated) : NULL,
    ]);

    return [
      'item_instance_id' => $item_instance_id,
      'item_id' => $item_id,
      'campaign_id' => $campaign_id,
      'definition' => $definition,
      'owner' => $owner,
      'location' => [
        'type' => $location_type,
        'ref' => $location_ref,
        'container' => $owner['type'] === 'container' ? $location_ref : NULL,
        'slot' => $slot,
      ],
      'quantity' => $quantity,
      'flags' => $flags,
      'state' => $state,
      'version' => $version,
      'updated_at' => $updated_at,
      'created' => $created,
      'updated' => $updated,
    ];
  }

  /**
   * Derive uniform current-state flags for an item instance.
   *
   * @param array<string,mixed> $state
   *
   * @return array<string,bool|string>
   */
  protected function deriveStateFlags(array $state, string $location_type, int $quantity): array {
    $metadata = is_array($state['inventory_metadata'] ?? NULL) ? $state['inventory_metadata'] : [];

    $equipped = in_array($location_type, self::EQUIPPED_LOCATION_TYPES, TRUE)
      || (bool) ($state['equipped'] ?? FALSE);
    $consumed = (bool) ($state['consumed'] ?? FALSE) || $quantity <= 0;
    $hidden = (bool) ($state['hidden'] ?? FALSE)
      || strtolower(trim((string) ($state['visibility'] ?? ''))) === 'hidden';
    $visibility = $hidden ? 'hidden' : (string) ($state['visibility'] ?? 'visible');

    return [
      'equipped' => $equipped,
      'consumed' => $consumed,
      'hidden' => $hidden,
      'visibility' => $visibility,
      'stackable' => (bool) ($metadata['stackable'] ?? FALSE),
      'container' => (bool) ($metadata['container'] ?? FALSE),
    ];
  }

  /**
   * Resolve the owning actor/entity or the non-actor location holder.
   *
   * @return array{type:string,ref:string}
   */
  protected function resolveOwner(string $location_type, string $location_ref): array {
    if (in_array($location_type, self::ACTOR_LOCATION_TYPES, TRUE)) {
      return ['type' => 'character', 'ref' => $location_ref];
    }
    if ($location_type === 'room' || $location_type === 'world') {
      return ['type' => 'room', 'ref' => $location_ref];
    }
    if ($location_type === 'container') {
      return ['type' => 'container', 'ref' => $location_ref];
    }

    return ['type' => $location_type !== '' ? $location_type : 'unknown', 'ref' => $location_ref];
  }

  /**
   * First non-empty trimmed string in a candidate list, or NULL.
   *
   * @param array<int,mixed> $candidates
   */
  protected function firstNonEmptyString(array $candidates): ?string {
    foreach ($candidates as $candidate) {
      if ($candidate === NULL) {
        continue;
      }
      $value = trim((string) $candidate);
      if ($value !== '') {
        return $value;
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function objectType(): string {
    return self::OBJECT_TYPE;
  }

  /**
   * {@inheritdoc}
   */
  public function supports(string $object_type): bool {
    return $object_type === self::OBJECT_TYPE;
  }

  /**
   * {@inheritdoc}
   */
  public function getObjectState(ObjectRef $ref): ObjectStateEnvelope {
    $state = $this->getState($ref->objectId, $ref->contextInt('campaign_id'));

    $flags = is_array($state['flags'] ?? NULL) ? $state['flags'] : [];
    $owner = is_array($state['owner'] ?? NULL) ? $state['owner'] : [];

    return ObjectStateEnvelope::create(
      self::OBJECT_TYPE,
      $ref->objectId,
      self::class,
      self::class,
      self::AUTHORITY_SOURCE,
      $state,
      isset($state['version']) ? (int) $state['version'] : NULL,
      isset($state['updated_at']) ? (string) $state['updated_at'] : NULL,
      [
        'owner_kind' => (string) ($owner['type'] ?? 'unknown'),
        'is_equipped' => (bool) ($flags['equipped'] ?? FALSE),
        'is_consumed' => (bool) ($flags['consumed'] ?? FALSE),
        'is_hidden' => (bool) ($flags['hidden'] ?? FALSE),
        'definition_version' => (string) ($state['definition']['version'] ?? ''),
      ],
    );
  }

}
