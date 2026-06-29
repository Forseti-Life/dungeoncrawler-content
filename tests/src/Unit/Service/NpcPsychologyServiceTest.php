<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\NpcPsychologyService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests role-pool normalization behavior in NpcPsychologyService.
 *
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\NpcPsychologyService
 */
class NpcPsychologyServiceTest extends UnitTestCase {

  /**
   * @covers ::resolveRolePsychologyPool
   */
  public function testResolveRolePsychologyPoolNormalizesRoleAndFallsBackToNeutral(): void {
    $service = $this->buildService();

    $pools = [
      'merchant' => ['Turn a profit', 'Acquire rare goods'],
      'neutral' => ['Mind own business', 'Avoid conflict'],
    ];

    $this->assertSame(
      ['Turn a profit', 'Acquire rare goods'],
      $service->exposeResolveRolePsychologyPool($pools, '  MERCHANT ')
    );
    $this->assertSame(
      ['Mind own business', 'Avoid conflict'],
      $service->exposeResolveRolePsychologyPool($pools, 'unknown_role')
    );
  }

  /**
   * @covers ::generateMotivations
   */
  public function testGenerateMotivationsUsesNeutralFallbackPoolForUnknownRole(): void {
    $service = $this->buildService();

    $result = $service->exposeGenerateMotivations('humanoid', 'unknown_role');
    $parts = array_values(array_filter(array_map('trim', explode(';', $result))));

    $this->assertCount(2, $parts);
    foreach ($parts as $part) {
      $this->assertContains($part, [
        'Mind own business',
        'Survive and prosper',
        'Gather information',
        'Avoid conflict',
      ]);
    }
  }

  /**
   * @covers ::generateFears
   */
  public function testGenerateFearsUsesNeutralFallbackPoolForUnknownRole(): void {
    $service = $this->buildService();

    $fear = $service->exposeGenerateFears('humanoid', 'unknown_role');

    $this->assertContains($fear, [
      'Getting caught in conflict',
      'Starvation',
      'The unknown',
    ]);
  }

  /**
   * Build a testable NpcPsychologyService instance.
   */
  private function buildService(): NpcPsychologyServiceTestDouble {
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')
      ->with('dungeoncrawler_npc_psychology')
      ->willReturn($this->createMock(LoggerInterface::class));

    return new NpcPsychologyServiceTestDouble(
      $this->createMock(Connection::class),
      $logger_factory
    );
  }

}

/**
 * Test double exposing protected psychology helpers.
 */
class NpcPsychologyServiceTestDouble extends NpcPsychologyService {

  /**
   * Exposes resolveRolePsychologyPool for unit coverage.
   *
   * @param array<string, array<int, string>> $role_pools
   *
   * @return array<int, string>
   */
  public function exposeResolveRolePsychologyPool(array $role_pools, string $role): array {
    return $this->resolveRolePsychologyPool($role_pools, $role);
  }

  /**
   * Exposes generateMotivations for unit coverage.
   */
  public function exposeGenerateMotivations(string $creature_type, string $role): string {
    return $this->generateMotivations($creature_type, $role);
  }

  /**
   * Exposes generateFears for unit coverage.
   */
  public function exposeGenerateFears(string $creature_type, string $role): string {
    return $this->generateFears($creature_type, $role);
  }

}
