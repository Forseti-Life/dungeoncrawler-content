<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\dungeoncrawler_content\Service\NameGeneratorService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for NameGeneratorService.
 *
 * @group dungeoncrawler_content
 * @group names
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\NameGeneratorService
 */
class NameGeneratorServiceTest extends UnitTestCase {

  protected NameGeneratorService $service;

  protected function setUp(): void {
    parent::setUp();

    $module_list = $this->createMock(ModuleExtensionList::class);
    $module_list->method('getPath')
      ->with('dungeoncrawler_content')
      ->willReturn(dirname(__DIR__, 4));

    $this->service = new NameGeneratorService($module_list);
  }

  /**
   * @covers ::generate
   */
  public function testGenerationIsDeterministicForSameSeed(): void {
    $first = $this->service->generate('Elf', 12345);
    $second = $this->service->generate('Elf', 12345);

    $this->assertSame($first, $second);
  }

  /**
   * @covers ::generate
   */
  public function testDifferentSeedsProduceDifferentNames(): void {
    $first = $this->service->generate('Goblin', 101);
    $second = $this->service->generate('Goblin', 202);

    $this->assertNotSame($first, $second);
  }

  /**
   * @covers ::generate
   */
  public function testUnknownAncestryFallsBackToHumanProfile(): void {
    $name = $this->service->generate('Unknown Ancestry', 777);

    $this->assertMatchesRegularExpression('/^[A-Z][a-z]+(?: [A-Z][a-z]+)?$/', $name);
  }

  /**
   * @covers ::generateWithSeed
   */
  public function testGenerateWithSeedReturnsSeedAndName(): void {
    $result = $this->service->generateWithSeed('Dwarf', 9090);

    $this->assertSame(9090, $result['seed']);
    $this->assertNotEmpty($result['name']);
    $this->assertIsString($result['name']);
  }

}
