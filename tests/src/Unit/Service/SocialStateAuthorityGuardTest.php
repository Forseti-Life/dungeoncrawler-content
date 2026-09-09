<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\DungeoncrawlerContentServiceProvider;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateContractException;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateEnvelope;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderInterface;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderRegistry;
use Drupal\dungeoncrawler_content\Service\SocialStateService;
use PHPUnit\Framework\TestCase;

/**
 * Static guards for the single social current-state authority (Phase 5).
 *
 * @group dungeoncrawler_content
 * @group social_state
 */
class SocialStateAuthorityGuardTest extends TestCase {

  /**
   * Provider uniqueness: two social providers hard-fail in the registry.
   */
  public function testRegistryRejectsCompetingSocialProviders(): void {
    $this->expectException(ObjectStateContractException::class);
    $this->expectExceptionMessage('Competing object-state providers for type "social"');
    new ObjectStateProviderRegistry([$this->socialProvider(), $this->socialProvider()]);
  }

  /**
   * The single registered social provider is resolvable and unique.
   */
  public function testRegistryResolvesSingleSocialProvider(): void {
    $provider = $this->socialProvider();
    $registry = new ObjectStateProviderRegistry([$provider]);

    $this->assertTrue($registry->hasProvider('social'));
    $this->assertSame($provider, $registry->getProvider('social'));
    $this->assertSame(['social'], $registry->registeredTypes());
  }

  /**
   * The social object-state provider tag is on the canonical owner class.
   */
  public function testSocialProviderTagPointsToOwner(): void {
    $services = $this->readModuleFile('dungeoncrawler_content.services.yml');
    $this->assertMatchesRegularExpression(
      '/dungeoncrawler_content\.social_state:\s*\n\s*class:\s*Drupal\\\\dungeoncrawler_content\\\\Service\\\\SocialStateService/',
      $services,
      'social_state service must be the SocialStateService class.'
    );
    $this->assertStringContainsString('object_type: social', $services);
  }

  /**
   * The owner merges named components with no default/fallback synthesis.
   */
  public function testOwnerHasNoFallbackSynthesis(): void {
    $source = $this->readModuleFile('src/Service/SocialStateService.php');

    // Scan executable code only; docblocks legitimately describe the guard.
    $code = $this->stripPhpComments($source);
    foreach (['fallback', 'backward', 'legacy', 'compatib'] as $needle) {
      $this->assertStringNotContainsString(
        $needle,
        strtolower($code),
        sprintf('SocialStateService code must not contain "%s" logic.', $needle)
      );
    }
    // Explicit named components.
    foreach ([
      "'aggression_state' =>",
      "'disposition_state' =>",
      "'stance_state' =>",
      "'relationship_attitude' =>",
    ] as $component) {
      $this->assertStringContainsString($component, $source);
    }
    // Campaign guard on reads.
    $this->assertStringContainsString('assertLaunchable', $source);
  }

  /**
   * Remove PHP comments/docblocks, leaving executable code and string literals.
   */
  private function stripPhpComments(string $source): string {
    $out = '';
    foreach (token_get_all($source) as $token) {
      if (is_array($token)) {
        if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], TRUE)) {
          continue;
        }
        $out .= $token[1];
      }
      else {
        $out .= $token;
      }
    }
    return $out;
  }

  /**
   * The frozen social-store allowlist matches services.yml injectors exactly.
   */
  public function testFrozenSocialStoreAllowlistMatchesServiceDefinitions(): void {
    $ref = new \ReflectionClass(DungeoncrawlerContentServiceProvider::class);
    /** @var array<int,string> $store_ids */
    $store_ids = $ref->getConstant('SOCIAL_STATE_STORE_IDS');
    /** @var array<string,array<string,string>> $allowlist */
    $allowlist = $ref->getConstant('SOCIAL_STATE_STORE_ALLOWLIST');
    $this->assertIsArray($store_ids);
    $this->assertIsArray($allowlist);

    foreach ($store_ids as $store_id) {
      $actual = $this->serviceDefinitionsInjecting($store_id);
      sort($actual);
      $allowed = array_keys($allowlist[$store_id] ?? []);
      sort($allowed);
      $this->assertSame(
        $allowed,
        $actual,
        sprintf('The frozen social allowlist for %s must match services.yml injectors exactly.', $store_id)
      );
      // The canonical owner must be permitted for every social store.
      $this->assertContains('dungeoncrawler_content.social_state', $allowed);
    }
  }

  /**
   * No presentation/controller injects a raw social state store.
   */
  public function testNoControllerInjectsRawSocialStores(): void {
    $ref = new \ReflectionClass(DungeoncrawlerContentServiceProvider::class);
    /** @var array<int,string> $store_ids */
    $store_ids = $ref->getConstant('SOCIAL_STATE_STORE_IDS');

    $controller_dir = dirname(__DIR__, 4) . '/src/Controller';
    $files = glob($controller_dir . '/*.php') ?: [];
    foreach ($files as $file) {
      $src = (string) file_get_contents($file);
      foreach (['AggressionStateStoreService', 'DispositionStateStoreService', 'RelationshipAttitudeStateStoreService', 'StanceStateStoreService'] as $store_class) {
        $this->assertStringNotContainsString(
          $store_class,
          $src,
          sprintf('%s must not reference the raw social store %s; read social state through the social owner.', basename($file), $store_class)
        );
      }
      foreach ($store_ids as $store_id) {
        $this->assertStringNotContainsString(
          "get('" . $store_id . "')",
          $src,
          sprintf('%s must not fetch the raw social store %s from the container.', basename($file), $store_id)
        );
      }
    }
  }

  /**
   * The cross-domain runtime read model no longer injects raw social stores.
   */
  public function testRuntimeReadModelDelegatesToSocialOwner(): void {
    $source = $this->readModuleFile('src/Service/RuntimeStateReadModelAssembler.php');

    $this->assertStringContainsString('protected ?SocialStateService $socialStateService;', $source);
    $this->assertStringContainsString('$this->socialStateService->readRoomAggression(', $source);
    $this->assertStringContainsString('$this->socialStateService->readActorDisposition(', $source);
    $this->assertStringContainsString('$this->socialStateService->readActorStance(', $source);
    // The raw store fields were removed from the assembler.
    $this->assertStringNotContainsString('$this->aggressionStateStoreService', $source);
    $this->assertStringNotContainsString('$this->dispositionStateStoreService', $source);
    $this->assertStringNotContainsString('$this->stanceStateStoreService', $source);
  }

  /**
   * Enumerate service ids in services.yml that inject a given service id.
   *
   * @return list<string>
   */
  private function serviceDefinitionsInjecting(string $service_id): array {
    $yaml = $this->readModuleFile('dungeoncrawler_content.services.yml');
    $lines = explode("\n", $yaml);
    $current = NULL;
    $ids = [];
    foreach ($lines as $line) {
      if (preg_match('/^  ([a-zA-Z0-9_.]+):\s*$/', $line, $m)) {
        $current = $m[1];
      }
      if ($current !== NULL && (
        str_contains($line, "'@" . $service_id . "'")
        || str_contains($line, "'@?" . $service_id . "'")
      )) {
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
   * A minimal social object-state provider.
   */
  private function socialProvider(): ObjectStateProviderInterface {
    return new class implements ObjectStateProviderInterface {

      public function objectType(): string {
        return SocialStateService::OBJECT_TYPE;
      }

      public function supports(string $object_type): bool {
        return $object_type === SocialStateService::OBJECT_TYPE;
      }

      public function getObjectState(ObjectRef $ref): ObjectStateEnvelope {
        return ObjectStateEnvelope::create(
          'social',
          $ref->objectId,
          SocialStateService::class,
          SocialStateService::class,
          'social_state_stores',
          []
        );
      }

    };
  }

}
