<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
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

  protected function setUp(): void {
    parent::setUp();

    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerInterface::class));

    $state_validation = new StateValidationService($logger_factory);
    $this->service = new class($this->createMock(Connection::class), $logger_factory, NULL, NULL, $state_validation) extends NpcSheetGenerationService {
      public function exposedGenerateFallbackNpcSheet(int $campaign_id, string $content_id, array $seed_data): array {
        return $this->generateFallbackNpcSheet($campaign_id, $content_id, $seed_data);
      }

      public function exposedNormalizeGeneratedSheet(string $content_id, array $seed_data, array $sheet): array {
        return $this->normalizeGeneratedSheet($content_id, $seed_data, $sheet);
      }

      public function exposedBuildQueuedNpcSheetContract(string $content_id, array $seed_data): array {
        return $this->buildQueuedNpcSheetContract($content_id, $seed_data);
      }
    };
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

}
