<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\DeityService;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for DeityService resolution helpers.
 *
 * @group dungeoncrawler_content
 * @group deity
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\DeityService
 */
class DeityServiceTest extends UnitTestCase {

  /**
   * Builds a DeityService that falls back to the seed catalog.
   */
  private function buildService(): DeityService {
    $database = $this->createMock(Connection::class);
    $database->method('select')
      ->willThrowException(new \RuntimeException('dc_deities table not available in unit test'));

    return new DeityService($database);
  }

  /**
   * @covers ::resolveId
   * @covers ::getByInput
   * @covers ::getDomainsForInput
   */
  public function testDomainHelpersResolveDisplayNameInput(): void {
    $service = $this->buildService();

    $this->assertSame('desna', $service->resolveId('Desna'));
    $this->assertSame('desna', $service->resolveId('desna'));
    $this->assertSame('Desna', $service->getByInput('Desna')['name'] ?? NULL);
    $this->assertContains('travel', $service->getDomainsForInput('Desna'));
  }

}
