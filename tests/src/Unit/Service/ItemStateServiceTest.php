<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Exception\LegacyCampaignArchivedException;
use Drupal\dungeoncrawler_content\Service\CampaignLifecycleService;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\ItemInstanceStore;
use Drupal\dungeoncrawler_content\Service\ItemStateService;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use PHPUnit\Framework\TestCase;

/**
 * Owner-assembly tests for the canonical item current-state authority (Phase 3).
 *
 * ItemStateService owns the single-item current-state assembly, binds the
 * canonical definition explicitly, and guards the owning campaign. These tests
 * mock the pure persistence lane (ItemInstanceStore), the definition authority
 * (CanonicalDefinitionService), and the campaign lifecycle guard.
 *
 * @group dungeoncrawler_content
 * @group item_state
 */
class ItemStateServiceTest extends TestCase {

  /**
   * A definition ref returned by the definition authority for a valid item.
   *
   * @return array<string,string>
   */
  private function definitionRef(string $item_id = 'dagger'): array {
    return [
      'definition_id' => $item_id,
      'version' => '1.2.0',
      'family' => 'item',
      'label' => ucfirst($item_id),
      'category' => 'weapon',
      'source_table' => 'dungeoncrawler_content_registry',
      'source_hash' => 'abc123',
    ];
  }

  /**
   * Build a service with a stubbed store row and definition/lifecycle behavior.
   *
   * @param array<string,mixed>|null $row
   */
  private function service(
    ?array $row,
    array $definition_ref = NULL,
    bool $archived = FALSE,
    ?\Throwable $definition_exception = NULL,
  ): ItemStateService {
    $store = $this->createMock(ItemInstanceStore::class);
    $store->method('loadInstanceRow')->willReturn($row);

    $definitions = $this->createMock(CanonicalDefinitionService::class);
    if ($definition_exception !== NULL) {
      $definitions->method('requireCanonicalDefinitionRef')->willThrowException($definition_exception);
    }
    else {
      $definitions->method('requireCanonicalDefinitionRef')->willReturn($definition_ref ?? $this->definitionRef());
    }

    $lifecycle = $this->createMock(CampaignLifecycleService::class);
    if ($archived) {
      $lifecycle->method('assertLaunchable')->willThrowException(
        new LegacyCampaignArchivedException('legacy_campaign_archived: campaign 310 is archived and cannot be launched or read by the current runtime.')
      );
    }

    return new ItemStateService($store, $definitions, $lifecycle);
  }

  /**
   * A raw item-instance row.
   *
   * @param array<string,mixed> $overrides
   *
   * @return array<string,mixed>
   */
  private function row(array $overrides = []): array {
    return $overrides + [
      'item_instance_id' => 'inst-1',
      'item_id' => 'dagger',
      'campaign_id' => 986,
      'location_type' => 'carried',
      'location_ref' => '5435',
      'quantity' => 1,
      'state_data' => '{}',
      'created' => 1000,
      'updated' => 2000,
    ];
  }

  /**
   * Inventory-held item: owner is the character, not equipped.
   */
  public function testInventoryHeldItem(): void {
    $state = $this->service($this->row())->getState('inst-1', 986);

    $this->assertSame('inst-1', $state['item_instance_id']);
    $this->assertSame('dagger', $state['item_id']);
    $this->assertSame(986, $state['campaign_id']);
    $this->assertSame(['type' => 'character', 'ref' => '5435'], $state['owner']);
    $this->assertSame('1.2.0', $state['definition']['version']);
    $this->assertFalse($state['flags']['equipped']);
    $this->assertFalse($state['flags']['consumed']);
  }

  /**
   * Equipped item: worn/equipped location projects equipped=true with a slot.
   */
  public function testEquippedItem(): void {
    $row = $this->row([
      'location_type' => 'worn',
      'state_data' => json_encode(['inventory_metadata' => ['equip_slot' => 'armor']]),
    ]);
    $state = $this->service($row)->getState('inst-1', 986);

    $this->assertTrue($state['flags']['equipped']);
    $this->assertSame('armor', $state['location']['slot']);
    $this->assertSame('character', $state['owner']['type']);
  }

  /**
   * Room-located item: owner kind is room, container is null.
   */
  public function testRoomLocatedItem(): void {
    $row = $this->row(['location_type' => 'room', 'location_ref' => 'tavern_entrance']);
    $state = $this->service($row)->getState('inst-1', 986);

    $this->assertSame(['type' => 'room', 'ref' => 'tavern_entrance'], $state['owner']);
    $this->assertNull($state['location']['container']);
  }

  /**
   * Container-located item: owner kind is container and container ref is set.
   */
  public function testContainerLocatedItem(): void {
    $row = $this->row(['location_type' => 'container', 'location_ref' => 'chest_7']);
    $state = $this->service($row)->getState('inst-1', 986);

    $this->assertSame('container', $state['owner']['type']);
    $this->assertSame('chest_7', $state['location']['container']);
  }

  /**
   * World-located item: owner kind is room (world placement).
   */
  public function testWorldLocatedItem(): void {
    $row = $this->row(['location_type' => 'world', 'location_ref' => 'overworld']);
    $state = $this->service($row)->getState('inst-1', 986);

    $this->assertSame('room', $state['owner']['type']);
  }

  /**
   * Stack quantity is preserved and stackable flag reflects metadata.
   */
  public function testStackQuantity(): void {
    $row = $this->row([
      'quantity' => 7,
      'state_data' => json_encode(['inventory_metadata' => ['stackable' => TRUE]]),
    ]);
    $state = $this->service($row)->getState('inst-1', 986);

    $this->assertSame(7, $state['quantity']);
    $this->assertTrue($state['flags']['stackable']);
  }

  /**
   * Consumed state: explicit consumed flag or zero quantity projects consumed.
   */
  public function testConsumedState(): void {
    $row = $this->row(['quantity' => 0, 'state_data' => json_encode(['consumed' => TRUE])]);
    $state = $this->service($row)->getState('inst-1', 986);

    $this->assertTrue($state['flags']['consumed']);
  }

  /**
   * Hidden state: hidden flag projects hidden=true and visibility=hidden.
   */
  public function testHiddenState(): void {
    $row = $this->row(['state_data' => json_encode(['hidden' => TRUE])]);
    $state = $this->service($row)->getState('inst-1', 986);

    $this->assertTrue($state['flags']['hidden']);
    $this->assertSame('hidden', $state['flags']['visibility']);
  }

  /**
   * Missing instance hard-fails visibly.
   */
  public function testMissingInstanceHardFails(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Item instance not found: nope');
    $this->service(NULL)->getState('nope', 986);
  }

  /**
   * Missing/quarantined definition hard-fails (propagated from the authority).
   */
  public function testQuarantinedDefinitionHardFails(): void {
    $service = $this->service(
      $this->row(),
      definition_exception: new \OutOfBoundsException('definition_quarantined:item:dagger'),
    );

    $this->expectException(\OutOfBoundsException::class);
    $this->expectExceptionMessage('definition_quarantined:item:dagger');
    $service->getState('inst-1', 986);
  }

  /**
   * Instance with no definition id hard-fails before any substitution.
   */
  public function testEmptyDefinitionIdHardFails(): void {
    $service = $this->service($this->row(['item_id' => '']));

    $this->expectException(\OutOfBoundsException::class);
    $this->expectExceptionMessage('item_definition_missing');
    $service->getState('inst-1', 986);
  }

  /**
   * Archived campaign rejects with legacy_campaign_archived before assembly.
   */
  public function testArchivedCampaignRejected(): void {
    $service = $this->service($this->row(['campaign_id' => 310]), archived: TRUE);

    $this->expectException(LegacyCampaignArchivedException::class);
    $this->expectExceptionMessage('legacy_campaign_archived');
    $service->getState('inst-1', 310);
  }

  /**
   * Empty instance id hard-fails.
   */
  public function testEmptyInstanceIdHardFails(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Item instance id is required.');
    $this->service($this->row())->getState('   ', 986);
  }

  /**
   * getObjectState returns a canonical envelope with self-owner and projection.
   */
  public function testObjectStateEnvelope(): void {
    $row = $this->row(['location_type' => 'worn', 'state_data' => json_encode(['equipped' => TRUE])]);
    $service = $this->service($row);

    $envelope = $service->getObjectState(ObjectRef::create('item', 'inst-1', ['campaign_id' => 986]));
    $array = $envelope->toArray();

    $this->assertSame(1, $array['envelope_version']);
    $this->assertSame('item', $array['object_type']);
    $this->assertSame('inst-1', $array['object_id']);
    $this->assertSame(ItemStateService::class, $array['authority']['owner']);
    $this->assertSame(ItemStateService::class, $array['authority']['provider']);
    $this->assertSame(ItemStateService::AUTHORITY_SOURCE, $array['authority']['source']);
    $this->assertTrue($array['projection']['is_equipped']);
    $this->assertSame('character', $array['projection']['owner_kind']);
    $this->assertSame('1.2.0', $array['projection']['definition_version']);
  }

  /**
   * The canonical owner declares the item object type.
   */
  public function testObjectTypeAndSupports(): void {
    $service = $this->service($this->row());
    $this->assertSame('item', $service->objectType());
    $this->assertTrue($service->supports('item'));
    $this->assertFalse($service->supports('inventory'));
  }

}
