<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Generation;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\EncounterBalancer;
use Drupal\dungeoncrawler_content\Service\EncounterGeneratorService;
use Drupal\dungeoncrawler_content\Service\Generation\EncounterGenerationRules;
use Drupal\dungeoncrawler_content\Service\Generation\RuntimeGenerationException;
use Drupal\dungeoncrawler_content\Service\NumberGenerationService;
use Drupal\dungeoncrawler_content\Service\SchemaLoader;
use PHPUnit\Framework\TestCase;

/**
 * Tests R6 encounter planning against published canonical definitions.
 *
 * @group dungeoncrawler_content
 */
final class EncounterGeneratorCanonicalDefinitionTest extends TestCase {

  public function testEncounterPlanUsesOnlyPublishedCanonicalCreatureDefinitions(): void {
    $published = [
      $this->creature('creature-leech', 'Bloat Leech Swarm', 1, ['gen-evidence', 'sewer']),
    ];
    $service = $this->service($published);

    $encounter = $service->generateEncounter([
      'party_level' => 2,
      'party_size' => 4,
      'difficulty' => 'moderate',
      'theme' => 'sewer',
      'room_id' => 'room-a',
      'hexes' => [['q' => 0, 'r' => 0], ['q' => 1, 'r' => 0], ['q' => 0, 'r' => 1]],
      'seed' => 42,
    ]);

    $this->assertSame('encounter_population_plan-v1', $encounter['schema_version']);
    $this->assertSame(80, $encounter['xp_budget']['target_xp']);
    $this->assertGreaterThanOrEqual($encounter['xp_budget']['min_xp'], $encounter['actual_xp']);
    $this->assertLessThanOrEqual($encounter['xp_budget']['max_xp'], $encounter['actual_xp']);
    $this->assertNotEmpty($encounter['population_plan']);
    foreach ($encounter['population_plan'] as $entry) {
      $this->assertSame('creature', $entry['entity_type']);
      $this->assertSame('creature-leech', $entry['definition_id']);
      $this->assertSame('room-a', $entry['room_id']);
      $this->assertArrayHasKey('q', $entry['hex']);
      $this->assertArrayHasKey('r', $entry['hex']);
      $this->assertSame('hostile', $entry['disposition']);
    }
  }

  public function testEncounterPlanCanIncludePublishedCanonicalItemDefinitions(): void {
    $service = $this->service([
      $this->creature('creature-leech', 'Bloat Leech Swarm', 1, ['gen-evidence', 'sewer']),
    ], [
      $this->item('item-lantern', 'Sewer Lantern', 2, ['gen-evidence', 'sewer']),
    ]);

    $encounter = $service->generateEncounter([
      'party_level' => 2,
      'party_size' => 4,
      'difficulty' => 'moderate',
      'theme' => 'sewer',
      'room_id' => 'room-a',
      'hexes' => [['q' => 0, 'r' => 0]],
      'include_items' => TRUE,
      'seed' => 42,
    ]);

    $items = array_values(array_filter($encounter['population_plan'], static fn(array $entry): bool => $entry['entity_type'] === 'item'));
    $this->assertCount(1, $items);
    $this->assertSame('item-lantern', $items[0]['definition_id']);
    $this->assertSame('loot', $items[0]['disposition']);
  }

  public function testRequestedItemPlanHardFailsWhenNoPublishedItemMatches(): void {
    $service = $this->service([
      $this->creature('creature-leech', 'Bloat Leech Swarm', 1, ['gen-evidence', 'sewer']),
    ]);

    $this->expectException(RuntimeGenerationException::class);
    $this->expectExceptionMessage('runtime_selection_failed');
    $service->generateEncounter([
      'party_level' => 2,
      'party_size' => 4,
      'difficulty' => 'moderate',
      'theme' => 'sewer',
      'room_id' => 'room-a',
      'hexes' => [['q' => 0, 'r' => 0]],
      'include_items' => TRUE,
      'seed' => 42,
    ]);
  }

  public function testNoPublishedCandidateHardFailsWithSelectionReceiptFindings(): void {
    $service = $this->service([]);

    try {
      $service->generateEncounter([
        'party_level' => 25,
        'party_size' => 4,
        'difficulty' => 'extreme',
        'theme' => 'no-such-tag',
        'room_id' => 'room-a',
        'hexes' => [['q' => 0, 'r' => 0]],
        'seed' => 7,
      ]);
      $this->fail('Expected runtime_selection_failed.');
    }
    catch (RuntimeGenerationException $e) {
      $this->assertSame('runtime_selection_failed', $e->getMessage());
      $this->assertSame(422, $e->httpStatus());
      $finding = $e->getFindings()[0];
      $this->assertSame('runtime_selection_failed', $finding['code']);
      $this->assertSame(['min' => 25, 'max' => 29], $finding['level_band']);
      $this->assertSame(['no-such-tag'], $finding['tags_tried']);
      $this->assertSame(160, $finding['budget']['target_xp']);
    }
  }

  public function testEncounterGenerationRulesKeepLegacyBudgetMath(): void {
    $this->assertSame([
      'target_xp' => 100,
      'min_xp' => 85,
      'max_xp' => 115,
      'difficulty' => 'moderate',
      'threat_level' => 'moderate',
    ], EncounterGenerationRules::xpBudget(2, 5, 'moderate'));
    $this->assertSame('Lone Leech', EncounterGenerationRules::encounterName([
      ['name' => 'Leech', 'level' => 1, 'count' => 1],
    ], 'sewer'));
  }

  private function service(array $creatures, array $items = []): EncounterGeneratorService {
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    $definitions->method('publishedDefinitions')
      ->willReturnCallback(static fn(string $family, array $criteria): array => $family === 'item' ? $items : $creatures);

    return new EncounterGeneratorService(
      $this->createMock(Connection::class),
      $logger_factory,
      $this->createMock(EncounterBalancer::class),
      $this->createMock(SchemaLoader::class),
      $this->createMock(NumberGenerationService::class),
      $definitions,
    );
  }

  private function creature(string $id, string $name, int $level, array $tags): array {
    return [
      'family' => 'creature',
      'definition_id' => $id,
      'version' => '1.0.0',
      'label' => $name,
      'level' => $level,
      'tags' => $tags,
      'payload' => ['pf2e_stats' => ['hp' => ['max' => 24]]],
    ];
  }

  private function item(string $id, string $name, int $level, array $tags): array {
    return [
      'family' => 'item',
      'definition_id' => $id,
      'version' => '1.0.0',
      'label' => $name,
      'level' => $level,
      'tags' => $tags,
      'payload' => [],
    ];
  }

}
