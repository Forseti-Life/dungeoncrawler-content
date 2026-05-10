<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\dungeoncrawler_content\Service\CampaignNameGeneratorService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for CampaignNameGeneratorService.
 *
 * @group dungeoncrawler_content
 * @group campaign-names
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CampaignNameGeneratorService
 */
class CampaignNameGeneratorServiceTest extends UnitTestCase {

  protected CampaignNameGeneratorService $service;

  protected function setUp(): void {
    parent::setUp();

    $module_list = $this->createMock(ModuleExtensionList::class);
    $module_list->method('getPath')
      ->with('dungeoncrawler_content')
      ->willReturn(dirname(__DIR__, 4));

    $this->service = new CampaignNameGeneratorService($module_list);
  }

  /**
   * @covers ::generate
   */
  public function testGenerationIsDeterministicForSameSeed(): void {
    $first = $this->service->generate('classic_dungeon', 12345);
    $second = $this->service->generate('classic_dungeon', 12345);

    $this->assertSame($first, $second);
  }

  /**
   * @covers ::generate
   */
  public function testUnknownThemeFallsBackToClassicDungeon(): void {
    $known = $this->service->generate('classic_dungeon', 777);
    $unknown = $this->service->generate('unknown_theme', 777);

    $this->assertSame($known, $unknown);
  }

  /**
   * @covers ::generate
   */
  public function testGeneratedNameLooksReadable(): void {
    $name = $this->service->generate('undead_crypt', 2026);

    $this->assertMatchesRegularExpression('/^[A-Z][A-Za-z\'-]+(?: [A-Za-z][A-Za-z\'-]+)+$/', $name);
    $this->assertStringNotContainsString('{', $name);
    $this->assertStringNotContainsString('}', $name);
  }

  /**
   * @covers ::generateWithSeed
   */
  public function testGenerateWithSeedReturnsSeedAndName(): void {
    $result = $this->service->generateWithSeed('goblin_warrens', 9090);

    $this->assertSame(9090, $result['seed']);
    $this->assertNotEmpty($result['name']);
    $this->assertIsString($result['name']);
  }

}
