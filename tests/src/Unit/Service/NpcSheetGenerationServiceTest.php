<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalDefinitionGenerationService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalGenerationService;
use Drupal\dungeoncrawler_content\Service\Generation\RuntimeGenerationException;
use Drupal\dungeoncrawler_content\Service\NpcSheetGenerationService;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests richer NPC sheet psychology generation.
 *
 * @group dungeoncrawler_content
 * @group npc
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\NpcSheetGenerationService
 */
class NpcSheetGenerationServiceTest extends UnitTestCase {

  protected NpcSheetGenerationService $service;
  protected LoggerChannelFactoryInterface $loggerFactory;
  protected StateValidationService $stateValidation;

  protected function setUp(): void {
    parent::setUp();

    $logger_factory = $this->loggerFactory();

    $state_validation = new StateValidationService($logger_factory);
    $this->loggerFactory = $logger_factory;
    $this->stateValidation = $state_validation;
    $this->service = new class($this->createMock(Connection::class), $logger_factory, NULL, NULL, $state_validation) extends NpcSheetGenerationService {
      public function exposedGenerateNpcSheet(int $campaign_id, string $content_id, array $seed_data): array {
        return $this->generateNpcSheet($campaign_id, $content_id, $seed_data);
      }

      public function exposedGenerateFallbackNpcSheet(int $campaign_id, string $content_id, array $seed_data): array {
        return $this->generateFallbackNpcSheet($campaign_id, $content_id, $seed_data);
      }

      public function exposedNormalizeGeneratedSheet(string $content_id, array $seed_data, array $sheet): array {
        return $this->normalizeGeneratedSheet($content_id, $seed_data, $sheet);
      }

      public function exposedBuildQueuedNpcSheetContract(string $content_id, array $seed_data): array {
        return $this->buildQueuedNpcSheetContract($content_id, $seed_data);
      }

      public function exposedNormalizeSeedData(int $campaign_id, string $content_id, array $seed_data): array {
        return $this->normalizeSeedData($campaign_id, $content_id, $seed_data);
      }
    };
  }

  private function loggerFactory(): LoggerChannelFactoryInterface {
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));
    return $logger_factory;
  }

  /**
   * @covers ::generateFallbackNpcSheet
   */
  public function testFallbackNpcSheetIncludesRichPsychology(): void {
    $sheet = $this->service->exposedGenerateFallbackNpcSheet(63, 'campaign_63_npc_mira', [
      'name' => 'Mira Deep-Pockets',
      'role' => 'merchant',
      'class' => 'Expert',
      'occupation' => 'pawnbroker',
    ]);

    $this->assertArrayHasKey('psychology', $sheet);
    $this->assertSame('1.0.0', $sheet['schema_version'] ?? NULL);
    $this->assertNotEmpty($sheet['psychology']['inner_conflict'] ?? '');
    $this->assertNotEmpty($sheet['psychology']['coping_mechanism'] ?? '');
    $this->assertNotEmpty($sheet['psychology']['stress_response'] ?? '');
    $this->assertNotEmpty($sheet['motivations'] ?? '');
    $this->assertNotEmpty($sheet['fears'] ?? '');
    $this->assertNotEmpty($sheet['bonds'] ?? '');
  }

  /**
   * @covers ::normalizeGeneratedSheet
   */
  public function testNormalizeGeneratedSheetDerivesLegacyPsychologyStrings(): void {
    $normalized = $this->service->exposedNormalizeGeneratedSheet('npc_ref', [], [
      'name' => 'Eldric',
      'role' => 'ally',
      'class' => 'Wizard',
      'occupation' => 'scholar',
      'psychology' => [
        'inner_conflict' => 'He wants to guide others but resents being treated as infallible.',
        'coping_mechanism' => 'He intellectualizes his fear.',
        'stress_response' => 'He becomes terse and overexplains.',
        'insecurity' => 'Being seen as a fraud.',
        'secret' => 'He once abandoned an expedition partner.',
        'desire' => 'To protect the next generation of explorers.',
        'need' => 'To admit he cannot control every outcome.',
        'trigger' => 'Public failures in front of students.',
        'anchor' => 'His former apprentices and the archive they built together.',
      ],
    ]);

    $this->assertSame('To protect the next generation of explorers.; To admit he cannot control every outcome.', $normalized['motivations']);
    $this->assertStringContainsString('Being seen as a fraud.', $normalized['fears']);
    $this->assertSame('His former apprentices and the archive they built together.', $normalized['bonds']);
    $this->assertSame('He once abandoned an expedition partner.', $normalized['psychology']['secret']);
  }

  /**
   * @covers ::normalizeGeneratedSheet
   */
  public function testNormalizeGeneratedSheetRejectsContractViolations(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('NPC sheet contract violation');

    $this->service->exposedNormalizeGeneratedSheet('npc_ref', [], [
      'name' => 'Broken NPC',
      'role' => 'ally',
      'class' => 'Wizard',
      'occupation' => 'scholar',
      'skills' => [
        ['name' => 'Arcana', 'modifier' => 'high'],
      ],
    ]);
  }

  /**
   * @covers ::buildQueuedNpcSheetContract
   */
  public function testQueuedNpcSheetPlaceholderStillMatchesContract(): void {
    $sheet = $this->service->exposedBuildQueuedNpcSheetContract('queued_npc', [
      'name' => 'Queued NPC',
      'role' => 'contact',
      'class' => 'Expert',
    ]);

    $this->assertSame('queued', $sheet['generation_status']);
    $this->assertSame('ai_generated', $sheet['source']);
    $this->assertSame('queued_npc', $sheet['content_id']);
    $this->assertNotEmpty($sheet['abilities']);
    $this->assertNotEmpty($sheet['stats']);
  }

  /**
   * @covers ::normalizeSeedData
   */
  public function testNormalizeSeedDataPreservesInstanceId(): void {
    $seed = $this->service->exposedNormalizeSeedData(63, 'room_1_shopkeeper', [
      'instance_id' => 'npc_instance_room_1_shopkeeper',
      'name' => 'Shopkeeper',
    ]);

    $this->assertSame('room_1_shopkeeper', $seed['content_id']);
    $this->assertSame('npc_instance_room_1_shopkeeper', $seed['instance_id']);
  }

  public function testGenerateNpcSheetUsesCanonicalActorDefinition(): void {
    $canonical = new CanonicalDefinitionGenerationService(
      new CanonicalGenerationService($this->ai([
        'actor_id' => 'nervous-warden',
        'version' => '1.0.0',
        'actor_type' => 'npc',
        'display_name' => 'Mara Cisternwatch',
        'source_module' => 'gen-evidence',
        'state_data' => [
          'name' => 'Mara Cisternwatch',
          'class' => 'informant',
          'level' => 2,
          'hp_current' => 28,
          'max_hp' => 28,
          'ac' => 17,
          'species' => 'human',
          'attitude' => 'friendly',
          'source_module' => 'gen-evidence',
          'description' => 'A nervous cistern warden with too many keys.',
          'conditions' => [],
        ],
      ]), $this->time(), $this->loggerFactory),
      $this->definitions()
    );
    $service = new class($this->createMock(Connection::class), $this->loggerFactory, NULL, NULL, $this->stateValidation, $canonical) extends NpcSheetGenerationService {
      public function exposedGenerateNpcSheet(int $campaign_id, string $content_id, array $seed_data): array {
        return $this->generateNpcSheet($campaign_id, $content_id, $seed_data);
      }
    };

    $sheet = $service->exposedGenerateNpcSheet(77, 'cistern-warden', [
      'name' => 'Cistern Warden',
      'level' => 2,
      'role' => 'informant',
      'attitude' => 'friendly',
    ]);

    $this->assertSame('runtime_generated_canonical_actor', $sheet['source']);
    $this->assertSame('cistern-warden', $sheet['canonical_actor_id']);
    $this->assertSame('1.0.0', $sheet['canonical_actor_version']);
    $this->assertSame(2, $sheet['level']);
    $this->assertSame(2, $sheet['canonical_actor_payload']['state_data']['level']);
    $this->assertSame('fixture-model', $sheet['canonical_actor_payload']['metadata']['generated_by']['model']);
    $this->assertSame('fixture-provider', $sheet['canonical_actor_payload']['metadata']['generated_by']['provider']);
  }

  public function testGenerateNpcSheetHardFailsWhenCanonicalServiceMissing(): void {
    $this->expectException(RuntimeGenerationException::class);
    $this->expectExceptionMessage('runtime_generation_failed');

    $this->service->exposedGenerateNpcSheet(77, 'missing-core', ['name' => 'Missing Core']);
  }

  private function definitions(): CanonicalDefinitionService {
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    $definitions->method('schemaForFamily')->with('actor')->willReturn(['title' => 'Actor', 'properties' => []]);
    $definitions->method('idProperty')->with('actor')->willReturn('actor_id');
    $definitions->method('nameProperty')->with('actor')->willReturn('display_name');
    $definitions->method('validateDefinition')->willReturn([]);
    $definitions->method('currentVersion')->willReturn(NULL);
    $definitions->method('saveDefinition')->willReturnCallback(static function (string $family, ?string $id, array $payload): array {
      return [
        'family' => $family,
        'definition_id' => (string) ($payload['actor_id'] ?? ''),
        'created' => TRUE,
        'previous_version' => NULL,
        'version' => (string) ($payload['version'] ?? '1.0.0'),
        'affected_rooms' => [],
      ];
    });
    return $definitions;
  }

  private function ai(array $payload): object {
    return new class($payload) {
      public function __construct(private array $payload) {}

      public function invokeModelDirect(string $prompt, string $module, string $operation, array $metadata, array $options): array {
        return [
          'success' => TRUE,
          'response' => json_encode($this->payload, JSON_UNESCAPED_SLASHES),
          'model_id' => 'fixture-model',
          'provider' => 'fixture-provider',
          'finish_reason' => 'stop',
          'reasoning_tokens' => 0,
        ];
      }
    };
  }

  private function time(): TimeInterface {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1788888888);
    return $time;
  }

}
