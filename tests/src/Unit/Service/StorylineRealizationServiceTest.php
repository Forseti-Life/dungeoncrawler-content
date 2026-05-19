<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Service\StateValidationService;
use Drupal\dungeoncrawler_content\Service\StorylineRealizationService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests generated storyline asset realization contracts.
 *
 * @group dungeoncrawler_content
 * @group storyline
 */
class StorylineRealizationServiceTest extends UnitTestCase {

  /**
   * Verifies generated storyline items are normalized to the canonical contract.
   */
  public function testBuildGeneratedItemContractReturnsCanonicalItemPayload(): void {
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
    $state_validation = new StateValidationService($logger_factory);

    $service = new class($this->createMock(Connection::class), NULL, $state_validation) extends StorylineRealizationService {
      public function exposeBuildGeneratedItemContract(string $content_id, array $item): array {
        return $this->buildGeneratedItemContract($content_id, $item);
      }
    };

    $item = $service->exposeBuildGeneratedItemContract('ashen-crown-fragment', [
      'name' => 'Ashen Crown Fragment',
      'description' => 'A glowing crown fragment generated from a storyline room.',
      'tags' => ['storyline', 'generated', 'boss'],
    ]);

    $this->assertSame('1.0.0', $item['schema_version']);
    $this->assertSame('ashen-crown-fragment', $item['item_id']);
    $this->assertSame('artifact', $item['item_type']);
    $this->assertSame('common', $item['rarity']);
    $this->assertSame(['storyline', 'generated', 'boss'], $item['traits']);
  }

}
