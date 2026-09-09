<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\DungeoncrawlerContentServiceProvider;
use Drupal\dungeoncrawler_content\Service\EncounterStateService;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateContractException;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateEnvelope;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderInterface;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Static guards for the single encounter current-state authority (Phase 2).
 *
 * @group dungeoncrawler_content
 * @group encounter_state
 */
class EncounterStateAuthorityGuardTest extends TestCase {

  /**
   * Provider uniqueness: two encounter providers hard-fail in the registry.
   */
  public function testRegistryRejectsCompetingEncounterProviders(): void {
    $a = $this->encounterProvider();
    $b = $this->encounterProvider();

    $this->expectException(ObjectStateContractException::class);
    $this->expectExceptionMessage('Competing object-state providers for type "encounter"');
    new ObjectStateProviderRegistry([$a, $b]);
  }

  /**
   * The single registered encounter provider is resolvable and unique.
   */
  public function testRegistryResolvesSingleEncounterProvider(): void {
    $provider = $this->encounterProvider();
    $registry = new ObjectStateProviderRegistry([$provider]);

    $this->assertTrue($registry->hasProvider('encounter'));
    $this->assertSame($provider, $registry->getProvider('encounter'));
    $this->assertSame(['encounter'], $registry->registeredTypes());
  }

  /**
   * Presentation/read controllers no longer stitch the raw encounter store:
   * they route encounter current-state reads through the canonical owner.
   *
   * @dataProvider presentationControllerProvider
   */
  public function testPresentationControllersRouteThroughOwner(string $relative_path): void {
    $source = $this->readModuleFile($relative_path);

    $this->assertStringNotContainsString(
      'use Drupal\\dungeoncrawler_content\\Service\\CombatEncounterStore;',
      $source,
      $relative_path . ' must not import the raw CombatEncounterStore.'
    );
    $this->assertStringNotContainsString(
      "get('dungeoncrawler_content.combat_encounter_store')",
      $source,
      $relative_path . ' must not fetch the raw store service; route through the owner.'
    );
    $this->assertStringNotContainsString(
      '->loadEncounter(',
      $source,
      $relative_path . ' must not call loadEncounter() directly; route through EncounterStateService.'
    );
    $this->assertStringContainsString(
      'encounter_state',
      $source,
      $relative_path . ' must depend on the canonical encounter_state owner.'
    );
  }

  /**
   * Controllers under test.
   *
   * @return array<string,array{0:string}>
   */
  public static function presentationControllerProvider(): array {
    return [
      'combat encounter api controller' => ['src/Controller/CombatEncounterApiController.php'],
      'combat action controller' => ['src/Controller/CombatActionController.php'],
      'encounter ai preview controller' => ['src/Controller/EncounterAiPreviewController.php'],
    ];
  }

  /**
   * The mixed read/write CombatApiController routes every encounter
   * current-state READ through the canonical owner. It may retain the raw
   * store ONLY for participant persistence writes (updateParticipant), so it
   * is intentionally not in the strict no-store provider list above.
   */
  public function testMixedReadWriteControllerRoutesReadsThroughOwner(): void {
    $source = $this->readModuleFile('src/Controller/CombatApiController.php');

    $this->assertStringNotContainsString(
      '->loadEncounter(',
      $source,
      'CombatApiController read endpoints must route through the owner, not loadEncounter().'
    );
    $this->assertStringContainsString(
      "get('dungeoncrawler_content.encounter_state')",
      $source,
      'CombatApiController must fetch the canonical encounter_state owner for reads.'
    );
    $this->assertStringContainsString(
      '$this->encounterStore->updateParticipant(',
      $source,
      'CombatApiController may retain the store only for participant persistence writes.'
    );
  }

  /**
   * The container guard allowlist matches the services.yml injectors exactly.
   *
   * This freezes the set of services permitted to inject the low-level store.
   * A drift (new injector added without an allowlist rationale, or an allowlist
   * entry that no longer injects the store) fails here and at container build.
   */
  public function testFrozenStoreAllowlistMatchesServiceDefinitions(): void {
    $ref = new \ReflectionClass(DungeoncrawlerContentServiceProvider::class);
    /** @var array<string,string> $allowlist */
    $allowlist = $ref->getConstant('COMBAT_ENCOUNTER_STORE_ALLOWLIST');
    $this->assertIsArray($allowlist);

    $actual = $this->serviceDefinitionsInjectingStore();

    sort($actual);
    $allowed = array_keys($allowlist);
    sort($allowed);

    $this->assertSame(
      $allowed,
      $actual,
      'The frozen CombatEncounterStore allowlist must match services.yml injectors exactly.'
    );

    // The canonical owner must be part of the allowlist.
    $this->assertArrayHasKey('dungeoncrawler_content.encounter_state', $allowlist);
  }

  /**
   * The encounter object-state provider tag is on the promoted owner.
   */
  public function testEncounterProviderTagPointsToOwner(): void {
    $services = $this->readModuleFile('dungeoncrawler_content.services.yml');
    // The encounter_state service is tagged as the encounter object-state
    // provider and is the EncounterStateService class.
    $this->assertMatchesRegularExpression(
      '/dungeoncrawler_content\.encounter_state:\s*\n\s*class:\s*Drupal\\\\dungeoncrawler_content\\\\Service\\\\EncounterStateService/',
      $services,
      'encounter_state service must be the EncounterStateService class.'
    );
    $this->assertStringContainsString('object_type: encounter', $services);
  }

  /**
   * Enumerate service ids in services.yml that inject the encounter store.
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
      if ($current !== NULL && str_contains($line, "'@dungeoncrawler_content.combat_encounter_store'")) {
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
   * A minimal encounter object-state provider.
   */
  private function encounterProvider(): ObjectStateProviderInterface {
    return new class implements ObjectStateProviderInterface {

      public function objectType(): string {
        return EncounterStateService::OBJECT_TYPE;
      }

      public function supports(string $object_type): bool {
        return $object_type === EncounterStateService::OBJECT_TYPE;
      }

      public function getObjectState(ObjectRef $ref): ObjectStateEnvelope {
        return ObjectStateEnvelope::create(
          'encounter',
          $ref->objectId,
          EncounterStateService::class,
          EncounterStateService::class,
          'combat_encounter_store',
          []
        );
      }

    };
  }

}
