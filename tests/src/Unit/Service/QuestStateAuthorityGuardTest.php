<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\DungeoncrawlerContentServiceProvider;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateContractException;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateEnvelope;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderInterface;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderRegistry;
use Drupal\dungeoncrawler_content\Service\QuestStateService;
use PHPUnit\Framework\TestCase;

/**
 * Static guards for the single quest current-state authority (Phase 4).
 *
 * @group dungeoncrawler_content
 * @group quest_state
 */
class QuestStateAuthorityGuardTest extends TestCase {

  /**
   * Provider uniqueness: two quest providers hard-fail in the registry.
   */
  public function testRegistryRejectsCompetingQuestProviders(): void {
    $this->expectException(ObjectStateContractException::class);
    $this->expectExceptionMessage('Competing object-state providers for type "quest"');
    new ObjectStateProviderRegistry([$this->questProvider(), $this->questProvider()]);
  }

  /**
   * The single registered quest provider is resolvable and unique.
   */
  public function testRegistryResolvesSingleQuestProvider(): void {
    $provider = $this->questProvider();
    $registry = new ObjectStateProviderRegistry([$provider]);

    $this->assertTrue($registry->hasProvider('quest'));
    $this->assertSame($provider, $registry->getProvider('quest'));
    $this->assertSame(['quest'], $registry->registeredTypes());
  }

  /**
   * The quest object-state provider tag is on the promoted owner class.
   */
  public function testQuestProviderTagPointsToOwner(): void {
    $services = $this->readModuleFile('dungeoncrawler_content.services.yml');
    $this->assertMatchesRegularExpression(
      '/dungeoncrawler_content\.quest_state:\s*\n\s*class:\s*Drupal\\\\dungeoncrawler_content\\\\Service\\\\QuestStateService/',
      $services,
      'quest_state service must be the QuestStateService class.'
    );
    $this->assertStringContainsString('object_type: quest', $services);
  }

  /**
   * The owner consumes the pure persistence lane, not QuestTrackerService.
   *
   * The single-quest read was MOVED off the tracker: the owner must not import
   * or inject QuestTrackerService, and must depend on the extracted
   * QuestStateStore + the quest-template definition authority + the campaign
   * lifecycle guard.
   */
  public function testOwnerDependsOnStoreNotTracker(): void {
    $source = $this->readModuleFile('src/Service/QuestStateService.php');

    $this->assertStringNotContainsString(
      'QuestTrackerService $',
      $source,
      'QuestStateService must not inject QuestTrackerService (no delegation back to the tracker).'
    );
    $this->assertStringContainsString('QuestStateStore $questStateStore', $source);
    $this->assertStringContainsString('CanonicalQuestTemplateService $canonicalQuestTemplates', $source);
    $this->assertStringContainsString('CampaignLifecycleService $campaignLifecycle', $source);
  }

  /**
   * The legacy public single-quest shape is removed from QuestTrackerService.
   *
   * QuestTrackerService is collection/progress write orchestration only; it no
   * longer exposes a competing single-quest current-state read.
   */
  public function testTrackerHasNoSingleQuestReadShape(): void {
    $source = $this->readModuleFile('src/Service/QuestTrackerService.php');

    $this->assertStringNotContainsString(
      'function getQuestState(',
      $source,
      'QuestTrackerService must not expose getQuestState(); the owner is QuestStateService.'
    );
    // The collection read helpers remain (quest list/write behavior preserved).
    $this->assertStringContainsString('function getActiveQuests(', $source);
    $this->assertStringContainsString('function getCampaignQuestTracking(', $source);
    $this->assertStringContainsString('function getCharacterQuestTracking(', $source);
  }

  /**
   * No-list-inference: the owner never reads quest lists to infer a single quest.
   *
   * A single-quest current-state read must not be inferred from a
   * getActiveQuests()/getCampaignQuestTracking()/getCharacterQuestTracking()
   * collection, nor from raw quest-table selects. The owner reads exactly one
   * instance row through the store and assembles it directly.
   */
  public function testOwnerDoesNotInferFromQuestLists(): void {
    $source = $this->readModuleFile('src/Service/QuestStateService.php');

    foreach (['->getActiveQuests(', '->getCampaignQuestTracking(', '->getCharacterQuestTracking('] as $list_api) {
      $this->assertStringNotContainsString(
        $list_api,
        $source,
        'QuestStateService must not infer a single quest from tracker list APIs.'
      );
    }
    $this->assertStringNotContainsString(
      "select('dc_campaign_quests'",
      $source,
      'QuestStateService must read through QuestStateStore, not raw table selects.'
    );
    $this->assertStringNotContainsString(
      "select('dc_campaign_quest_progress'",
      $source,
      'QuestStateService must read progress through QuestStateStore, not raw table selects.'
    );
  }

  /**
   * The pure persistence lane never assembles an envelope, binds a template,
   * or guards campaigns.
   */
  public function testStoreIsPurePersistence(): void {
    $source = $this->readModuleFile('src/Service/QuestStateStore.php');

    $this->assertStringContainsString('function loadQuestInstanceRow(', $source);
    $this->assertStringContainsString('function loadProgressRows(', $source);
    $this->assertStringNotContainsString('ObjectStateEnvelope', $source);
    $this->assertStringNotContainsString('CampaignLifecycleService', $source);
    $this->assertStringNotContainsString('CanonicalQuestTemplateService', $source);
  }

  /**
   * The container guard allowlist matches the services.yml injectors exactly.
   *
   * This freezes the set of services permitted to inject the low-level quest
   * store. A drift (new injector without an allowlist rationale, or an
   * allowlist entry that no longer injects the store) fails here and at build.
   */
  public function testFrozenStoreAllowlistMatchesServiceDefinitions(): void {
    $ref = new \ReflectionClass(DungeoncrawlerContentServiceProvider::class);
    /** @var array<string,string> $allowlist */
    $allowlist = $ref->getConstant('QUEST_STATE_STORE_ALLOWLIST');
    $this->assertIsArray($allowlist);

    $actual = $this->serviceDefinitionsInjectingStore();
    sort($actual);
    $allowed = array_keys($allowlist);
    sort($allowed);

    $this->assertSame(
      $allowed,
      $actual,
      'The frozen QuestStateStore allowlist must match services.yml injectors exactly.'
    );
    // Only the canonical owner may inject the store.
    $this->assertSame(['dungeoncrawler_content.quest_state'], $allowed);
  }

  /**
   * Enumerate service ids in services.yml that inject the quest store.
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
      if ($current !== NULL && str_contains($line, "'@dungeoncrawler_content.quest_state_store'")) {
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
   * A minimal quest object-state provider.
   */
  private function questProvider(): ObjectStateProviderInterface {
    return new class implements ObjectStateProviderInterface {

      public function objectType(): string {
        return QuestStateService::OBJECT_TYPE;
      }

      public function supports(string $object_type): bool {
        return $object_type === QuestStateService::OBJECT_TYPE;
      }

      public function getObjectState(ObjectRef $ref): ObjectStateEnvelope {
        return ObjectStateEnvelope::create(
          'quest',
          $ref->objectId,
          QuestStateService::class,
          QuestStateService::class,
          QuestStateService::AUTHORITY_SOURCE,
          []
        );
      }

    };
  }

}
