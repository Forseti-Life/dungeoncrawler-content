<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\FamiliarService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\FamiliarService
 * @group dungeoncrawler_content
 */
class FamiliarServiceTest extends UnitTestCase {

  /**
   * @covers ::buildFamiliarClassFeatureOptions
   */
  public function testBuildFamiliarClassFeatureOptionsUsesNamespacedIds(): void {
    $connection = $this->createMock(Connection::class);
    $service = new FamiliarService($connection);

    $options = $service->buildFamiliarClassFeatureOptions([
      'abilities' => ['speech', 'tough'],
    ]);

    $this->assertCount(2, $options);
    $this->assertSame('familiar:speech', $options[0]['id'] ?? NULL);
    $this->assertSame('speech', $options[0]['option_id'] ?? NULL);
    $this->assertSame('familiar', $options[0]['class_id'] ?? NULL);
    $this->assertSame('familiar_class_feature', $options[0]['feat_type'] ?? NULL);
    $this->assertSame('Speech', $options[0]['name'] ?? NULL);
    $this->assertSame('familiar:tough', $options[1]['id'] ?? NULL);
    $this->assertSame('Tough', $options[1]['name'] ?? NULL);
  }

  /**
   * @covers ::buildFamiliarClassFeatureOptions
   */
  public function testBuildFamiliarClassFeatureOptionsRejectsUnknownAbilityIds(): void {
    $connection = $this->createMock(Connection::class);
    $service = new FamiliarService($connection);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unknown familiar ability "not-real"');
    $service->buildFamiliarClassFeatureOptions([
      'abilities' => ['not-real'],
    ]);
  }

}
