<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\MapGeneratorService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\NullLogger;

/**
 * Tests deterministic map-navigation helpers retained after R4 reconciliation.
 *
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\MapGeneratorService
 */
class MapGeneratorServiceDeterminismTest extends UnitTestCase {

  protected function createService(): MapGeneratorService {
    return new class extends MapGeneratorService {
      public function __construct() {
        $this->logger = new NullLogger();
      }

      public function callExtractSearchKeywords(string $text): array {
        return $this->extractSearchKeywords($text);
      }

      public function callInferSettingType(string $destination): ?string {
        return $this->inferSettingType($destination);
      }

      public function callSettingTypeToRoomType(string $setting_type): string {
        return $this->settingTypeToRoomType($setting_type);
      }

      public function callRuntimeSelectionSeed(string $destination, int $party_level, array $context): int {
        return $this->runtimeSelectionSeed($destination, $party_level, $context);
      }

      public function callBuildNavigationRuntimeRoomId(string $dungeon_id, string $destination, int $room_index): string {
        return $this->buildNavigationRuntimeRoomId($dungeon_id, $destination, $room_index);
      }
    };
  }

  /**
   * @covers ::extractSearchKeywords
   */
  public function testExtractSearchKeywordsDedupesAndBoundsTerms(): void {
    $service = $this->createService();

    $keywords = $service->callExtractSearchKeywords('The Flooded Sewer, flooded cellar altar!');

    $this->assertContains('flooded', $keywords);
    $this->assertContains('sewer', $keywords);
    $this->assertContains('cellar', $keywords);
    $this->assertContains('altar', $keywords);
    $this->assertNotContains('the', $keywords);
    $this->assertSame(array_values(array_unique($keywords)), $keywords);
  }

  /**
   * @covers ::inferSettingType
   * @covers ::settingTypeToRoomType
   */
  public function testSettingTypeInferenceFeedsCanonicalRoomType(): void {
    $service = $this->createService();

    $this->assertSame('sewer', $service->callInferSettingType('the old sewer outlet'));
    $this->assertSame('corridor', $service->callSettingTypeToRoomType('street'));
    $this->assertSame('shrine', $service->callSettingTypeToRoomType('temple'));
  }

  /**
   * @covers ::runtimeSelectionSeed
   */
  public function testRuntimeSelectionSeedIsDeterministicUnlessExplicit(): void {
    $service = $this->createService();

    $first = $service->callRuntimeSelectionSeed('Sewer Outlet', 2, []);
    $second = $service->callRuntimeSelectionSeed('Sewer Outlet', 2, []);

    $this->assertSame($first, $second);
    $this->assertSame(42, $service->callRuntimeSelectionSeed('Sewer Outlet', 2, ['seed' => 42]));
  }

  /**
   * @covers ::buildNavigationRuntimeRoomId
   */
  public function testNavigationRuntimeRoomIdIsBoundedAndReadable(): void {
    $service = $this->createService();

    $id = $service->callBuildNavigationRuntimeRoomId('dungeon alpha', 'Flooded Sewer Outlet', 12);

    $this->assertLessThanOrEqual(100, strlen($id));
    $this->assertStringStartsWith('room_dungeon_alpha_flooded_sewer_outlet_12_', $id);
  }

  public function testEnsureGeneratedNpcPsychologyProfilesMethodIsDeletedAfterR4(): void {
    $this->assertFalse(
      method_exists(MapGeneratorService::class, 'ensureGeneratedNpcPsychologyProfiles'),
      'R4 deletes ad hoc generated-NPC contract/profile generation from MapGeneratorService.'
    );
  }

}
