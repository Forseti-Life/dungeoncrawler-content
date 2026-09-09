<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\DungeoncrawlerContentServiceProvider;
use Drupal\dungeoncrawler_content\Service\ItemStateService;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateContractException;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateEnvelope;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderInterface;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Static guards for the single item current-state authority (Phase 3).
 *
 * @group dungeoncrawler_content
 * @group item_state
 */
class ItemStateAuthorityGuardTest extends TestCase {

  /**
   * Provider uniqueness: two item providers hard-fail in the registry.
   */
  public function testRegistryRejectsCompetingItemProviders(): void {
    $this->expectException(ObjectStateContractException::class);
    $this->expectExceptionMessage('Competing object-state providers for type "item"');
    new ObjectStateProviderRegistry([$this->itemProvider(), $this->itemProvider()]);
  }

  /**
   * The single registered item provider is resolvable and unique.
   */
  public function testRegistryResolvesSingleItemProvider(): void {
    $provider = $this->itemProvider();
    $registry = new ObjectStateProviderRegistry([$provider]);

    $this->assertTrue($registry->hasProvider('item'));
    $this->assertSame($provider, $registry->getProvider('item'));
    $this->assertSame(['item'], $registry->registeredTypes());
  }

  /**
   * The item object-state provider tag is on the promoted owner class.
   */
  public function testItemProviderTagPointsToOwner(): void {
    $services = $this->readModuleFile('dungeoncrawler_content.services.yml');
    $this->assertMatchesRegularExpression(
      '/dungeoncrawler_content\.item_state:\s*\n\s*class:\s*Drupal\\\\dungeoncrawler_content\\\\Service\\\\ItemStateService/',
      $services,
      'item_state service must be the ItemStateService class.'
    );
    $this->assertStringContainsString('object_type: item', $services);
  }

  /**
   * The owner consumes the pure persistence lane, not InventoryManagement.
   *
   * The single-item assembly was MOVED (not copied): the owner must not import
   * or inject InventoryManagementService, and must depend on the extracted
   * ItemInstanceStore + the definition authority + the campaign lifecycle guard.
   */
  public function testOwnerDependsOnStoreNotInventoryManagement(): void {
    $source = $this->readModuleFile('src/Service/ItemStateService.php');

    $this->assertStringNotContainsString(
      'use Drupal\\dungeoncrawler_content\\Service\\InventoryManagementService;',
      $source,
      'ItemStateService must not import InventoryManagementService (no delegation back to inventory).'
    );
    $this->assertStringNotContainsString(
      'InventoryManagementService $',
      $source,
      'ItemStateService must not inject InventoryManagementService.'
    );
    $this->assertStringContainsString('ItemInstanceStore $itemInstanceStore', $source);
    $this->assertStringContainsString('CanonicalDefinitionService $canonicalDefinitions', $source);
    $this->assertStringContainsString('CampaignLifecycleService $campaignLifecycle', $source);
  }

  /**
   * The legacy public single-item shape is removed from InventoryManagement.
   *
   * InventoryManagementService is inventory collection/write orchestration
   * only; it no longer exposes a competing single-item current-state read.
   */
  public function testInventoryManagementHasNoSingleItemReadShape(): void {
    $source = $this->readModuleFile('src/Service/InventoryManagementService.php');

    $this->assertStringNotContainsString(
      'function getItemState(',
      $source,
      'InventoryManagementService must not expose getItemState(); the owner is ItemStateService.'
    );
    // The inventory collection read remains.
    $this->assertStringContainsString('function getInventory(', $source);
  }

  /**
   * The pure persistence lane never assembles an envelope or guards campaigns.
   *
   * ItemInstanceStore is a raw query lane only. Assembly, definition binding
   * and lifecycle guards live in the owner.
   */
  public function testStoreIsPurePersistence(): void {
    $source = $this->readModuleFile('src/Service/ItemInstanceStore.php');

    $this->assertStringContainsString('function loadInstanceRow(', $source);
    $this->assertStringNotContainsString('ObjectStateEnvelope', $source);
    $this->assertStringNotContainsString('CampaignLifecycleService', $source);
    $this->assertStringNotContainsString('CanonicalDefinitionService', $source);
  }

  /**
   * No-list-inference: the owner never reads inventory lists to infer an item.
   *
   * A single-item current-state read must not be inferred from a
   * getInventory()-shaped array. The owner reads exactly one instance row and
   * assembles it directly.
   */
  public function testOwnerDoesNotInferFromInventoryLists(): void {
    $source = $this->readModuleFile('src/Service/ItemStateService.php');

    $this->assertStringNotContainsString(
      '->getInventory(',
      $source,
      'ItemStateService must not infer a single item from getInventory() lists.'
    );
    $this->assertStringNotContainsString(
      "select('dc_campaign_item_instances'",
      $source,
      'ItemStateService must read through ItemInstanceStore, not raw table selects.'
    );
  }

  /**
   * The container guard allowlist matches the services.yml injectors exactly.
   *
   * This freezes the set of services permitted to inject the low-level item
   * store. A drift (new injector without an allowlist rationale, or an
   * allowlist entry that no longer injects the store) fails here and at build.
   */
  public function testFrozenStoreAllowlistMatchesServiceDefinitions(): void {
    $ref = new \ReflectionClass(DungeoncrawlerContentServiceProvider::class);
    /** @var array<string,string> $allowlist */
    $allowlist = $ref->getConstant('ITEM_INSTANCE_STORE_ALLOWLIST');
    $this->assertIsArray($allowlist);

    $actual = $this->serviceDefinitionsInjectingStore();
    sort($actual);
    $allowed = array_keys($allowlist);
    sort($allowed);

    $this->assertSame(
      $allowed,
      $actual,
      'The frozen ItemInstanceStore allowlist must match services.yml injectors exactly.'
    );
    // Only the canonical owner may inject the store.
    $this->assertSame(['dungeoncrawler_content.item_state'], $allowed);
  }

  /**
   * Enumerate service ids in services.yml that inject the item store.
   *
   * @return list<string>
   */
  private function serviceDefinitionsInjectingStore(): array {
    $yaml = $this->readModuleFile('dungeoncrawler_content.services.yml');
    $lines = explode("\n", $yaml);
    $current = NULL;
    $ids = [];
    foreach ($lines as $line) {
      if (preg_match('/^  ([a-zA-Z0-9_.]+):\s*$/', $line, $m)) {
        $current = $m[1];
      }
      if ($current !== NULL && str_contains($line, "'@dungeoncrawler_content.item_instance_store'")) {
        $ids[$current] = TRUE;
      }
    }
    return array_keys($ids);
  }

  /**
   * Read a file relative to the module root.
   */
  private function readModuleFile(string $relative_path): string {
    $module_root = dirname(__DIR__, 4);
    $path = $module_root . '/' . $relative_path;
    $this->assertFileExists($path);
    return (string) file_get_contents($path);
  }

  /**
   * A minimal item object-state provider.
   */
  private function itemProvider(): ObjectStateProviderInterface {
    return new class implements ObjectStateProviderInterface {

      public function objectType(): string {
        return ItemStateService::OBJECT_TYPE;
      }

      public function supports(string $object_type): bool {
        return $object_type === ItemStateService::OBJECT_TYPE;
      }

      public function getObjectState(ObjectRef $ref): ObjectStateEnvelope {
        return ObjectStateEnvelope::create(
          'item',
          $ref->objectId,
          ItemStateService::class,
          ItemStateService::class,
          ItemStateService::AUTHORITY_SOURCE,
          []
        );
      }

    };
  }

}
