<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorCanonicalGenerationPlanService;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalGenerationException;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalGenerationService;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use PHPUnit\Framework\TestCase;

/**
 * Fixture-driven coverage for Slice G1 editor generation.
 *
 * @group dungeoncrawler_content
 */
final class CanonicalGenerationServiceTest extends TestCase {

  private function loggerFactory(): LoggerChannelFactoryInterface {
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
    return $factory;
  }

  private function time(): TimeInterface {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1788888888);
    return $time;
  }

  private function definitions(array $catalog = []): CanonicalDefinitionService {
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    $definitions->method('catalog')->willReturn(['definitions' => $catalog]);
    $definitions->method('definitionExists')->willReturnCallback(static function (string $family, string $id, string $version) use ($catalog): bool {
      foreach ($catalog as $definition) {
        if (($definition['family'] ?? NULL) === $family && ($definition['definition_id'] ?? NULL) === $id && ($definition['version'] ?? '1.0.0') === $version) {
          return TRUE;
        }
      }
      return FALSE;
    });
    return $definitions;
  }

  private function service(?object $ai, CanonicalDefinitionService $definitions, ?RoomEditorService $roomEditor = NULL, ?DungeonEditorService $dungeonEditor = NULL): EditorCanonicalGenerationPlanService {
    $core = new CanonicalGenerationService(
      $ai,
      $this->time(),
      $this->loggerFactory(),
    );
    return new EditorCanonicalGenerationPlanService($core, $definitions);
  }

  private function ai(array $responses): object {
    return new class($responses) {
      public int $calls = 0;
      public array $prompts = [];
      public array $options = [];

      public function __construct(private array $responses) {}

      public function invokeModelDirect(string $prompt, string $module, string $operation, array $metadata, array $options): array {
        $this->prompts[] = $prompt;
        $this->options[] = $options;
        $response = $this->responses[min($this->calls, count($this->responses) - 1)];
        $this->calls++;
        return [
          'success' => TRUE,
          'response' => is_string($response) ? $response : json_encode($response, JSON_UNESCAPED_SLASHES),
          'model_id' => 'fixture-model',
          'finish_reason' => 'stop',
          'reasoning_tokens' => 0,
        ];
      }
    };
  }

  private function conformingRoom(): array {
    $hexes = [];
    for ($q = 0; $q < 4; $q++) {
      for ($r = 0; $r < 4; $r++) {
        $hexes[] = ['q' => $q, 'r' => $r, 'terrain_type' => 'water_shallow', 'elevation_ft' => 0, 'lighting' => 'dim_light'];
      }
    }
    return [
      'name' => 'Flooded Cellar',
      'description' => 'Original flooded cellar with a broken altar.',
      'room_type' => 'chamber',
      'size_category' => 'medium',
      'terrain_type' => 'water_shallow',
      'lighting' => 'dim_light',
      'hexes' => $hexes,
      'entry_ports' => [['port_id' => 'entry-1', 'hex' => ['q' => 0, 'r' => 0], 'label' => 'Cellar steps']],
      'exit_ports' => [['port_id' => 'exit-1', 'hex' => ['q' => 3, 'r' => 3], 'label' => 'Drain gate']],
      'placements' => [['family' => 'item', 'definition_id' => 'broken-altar', 'version' => '1.0.0', 'anchor_hex' => ['q' => 1, 'r' => 1]]],
    ];
  }

  private function roomContext(RoomEditorService $roomEditor, CanonicalDefinitionService $definitions): RoomEditorGmToolContext {
    return new RoomEditorGmToolContext('draft-room', 'editing', $roomEditor, $definitions);
  }

  private function roomEditorExpectingSimulation(): RoomEditorService {
    $editor = $this->createMock(RoomEditorService::class);
    $editor->method('getDraft')->willReturn([
      'draft_id' => 'draft-room',
      'revision' => 0,
      'status' => 'draft',
      'room' => ['room_id' => 'gen-room', 'name' => '', 'hexes' => [], 'entry_ports' => [], 'exit_ports' => [], 'placements' => []],
    ]);
    $editor->method('simulateCommands')->willReturnCallback(static function (string $draft_id, array $commands): array {
      $room = ['hexes' => [], 'entry_ports' => [], 'exit_ports' => [], 'placements' => []];
      foreach ($commands as $command) {
        if ($command['type'] === 'add_hex') {
          $room['hexes'][] = $command['payload']['hex'];
        }
        if ($command['type'] === 'add_entry_port') {
          $room['entry_ports'][] = $command['payload']['port'];
        }
        if ($command['type'] === 'add_exit_port') {
          $room['exit_ports'][] = $command['payload']['port'];
        }
        if ($command['type'] === 'place_object') {
          $room['placements'][] = $command['payload'];
        }
      }
      return [
        'applies_cleanly' => TRUE,
        'projected_room' => $room,
        'validation' => ['valid' => TRUE, 'errors' => [], 'warnings' => []],
      ];
    });
    return $editor;
  }

  public function testConformingRoomResponseProducesCommandPlanWithProvenance(): void {
    $catalog = [['family' => 'item', 'definition_id' => 'broken-altar', 'version' => '1.0.0', 'label' => 'Broken altar']];
    $definitions = $this->definitions($catalog);
    $roomEditor = $this->roomEditorExpectingSimulation();
    $ai = $this->ai([$this->conformingRoom()]);

    $result = $this->service($ai, $definitions, $roomEditor)->generateRoomLayout([
      'prompt' => 'a flooded cellar with a broken altar, ~20 hexes',
      'seed' => 42,
    ], $this->roomContext($roomEditor, $definitions));

    $this->assertSame('room_layout', $result['generation_type']);
    $this->assertSame(42, $result['seed']);
    $this->assertSame('fixture-model', $result['metadata']['generated_by']['model']);
    $this->assertSame('stop', $result['metadata']['generated_by']['finish_reason']);
    $this->assertSame(0, $result['metadata']['generated_by']['reasoning_tokens']);
    $this->assertSame('disabled', $ai->options[0]['thinking']);
    $this->assertArrayNotHasKey('timeout_sec', $ai->options[0]);
    $this->assertSame('set_room_metadata', $result['command_plan']['steps'][0]['command_type']);
    $this->assertSame('fixture-model', $result['command_plan']['steps'][0]['payload']['changes']['metadata']['generated_by']['model']);
    $this->assertContains('place_object', array_column($result['command_plan']['steps'], 'command_type'));
    $this->assertSame(1, $ai->calls);
  }

  public function testNonconformingThenConformingRetriesWithFindings(): void {
    $catalog = [['family' => 'item', 'definition_id' => 'broken-altar', 'version' => '1.0.0']];
    $definitions = $this->definitions($catalog);
    $roomEditor = $this->roomEditorExpectingSimulation();
    $bad = $this->conformingRoom();
    $bad['hexes'] = array_slice($bad['hexes'], 0, 3);
    $ai = $this->ai([$bad, $this->conformingRoom()]);

    $result = $this->service($ai, $definitions, $roomEditor)->generateRoomLayout([
      'prompt' => 'a flooded cellar with a broken altar, ~20 hexes',
      'seed' => 42,
    ], $this->roomContext($roomEditor, $definitions));

    $this->assertSame('room_layout', $result['generation_type']);
    $this->assertSame(2, $ai->calls);
    $this->assertStringContainsString('room_hex_count_below_min', $ai->prompts[1]);
  }

  public function testGeneratedCatalogReferenceFailuresRetryWithFindings(): void {
    $catalog = [['family' => 'item', 'definition_id' => 'broken-altar', 'version' => '1.0.0']];
    $definitions = $this->definitions($catalog);
    $roomEditor = $this->roomEditorExpectingSimulation();
    $bad = $this->conformingRoom();
    $bad['placements'][0]['definition_id'] = 'invented-altar';
    $ai = $this->ai([$bad, $this->conformingRoom()]);

    $result = $this->service($ai, $definitions, $roomEditor)->generateRoomLayout([
      'prompt' => 'a flooded cellar with a broken altar, ~20 hexes',
      'seed' => 42,
    ], $this->roomContext($roomEditor, $definitions));

    $this->assertSame('room_layout', $result['generation_type']);
    $this->assertSame(2, $ai->calls);
    $this->assertStringContainsString('generation_catalog_reference_unresolved', $ai->prompts[1]);
  }

  public function testNonconformingTwiceHardFailsWithFindings(): void {
    $definitions = $this->definitions([]);
    $roomEditor = $this->roomEditorExpectingSimulation();
    $bad = $this->conformingRoom();
    $bad['hexes'] = array_slice($bad['hexes'], 0, 3);

    $this->expectException(CanonicalGenerationException::class);
    $this->expectExceptionMessage('generation_nonconforming');
    try {
      $this->service($this->ai([$bad, $bad]), $definitions, $roomEditor)->generateRoomLayout([
        'prompt' => 'too small',
        'seed' => 42,
      ], $this->roomContext($roomEditor, $definitions));
    }
    catch (CanonicalGenerationException $exception) {
      $this->assertSame('room_hex_count_below_min', $exception->getFindings()[0]['code']);
      throw $exception;
    }
  }

  public function testProviderMissingHardFailsWithoutFallback(): void {
    $definitions = $this->definitions([]);
    $roomEditor = $this->roomEditorExpectingSimulation();

    $this->expectException(CanonicalGenerationException::class);
    $this->expectExceptionMessage('generation_provider_unavailable');
    $this->service(NULL, $definitions, $roomEditor)->generateRoomLayout([
      'prompt' => 'a flooded cellar',
      'seed' => 42,
    ], $this->roomContext($roomEditor, $definitions));
  }

  public function testRoomSizeCapExceededHardFails(): void {
    $definitions = $this->definitions([]);
    $roomEditor = $this->roomEditorExpectingSimulation();

    $this->expectException(CanonicalGenerationException::class);
    $this->expectExceptionMessage('generation_size_limit_exceeded');
    $this->service($this->ai([$this->conformingRoom()]), $definitions, $roomEditor)->generateRoomLayout([
      'prompt' => 'too large',
      'max_hexes' => 81,
      'seed' => 42,
    ], $this->roomContext($roomEditor, $definitions));
  }

  public function testConformingDungeonResponseUsesPublishedRoomsAndPlacementIds(): void {
    $definitions = $this->definitions([]);
    $library = [
      [
        'room_id' => 'published-a',
        'version_id' => 'version-a',
        'version' => '1.0.0',
        'name' => 'Published A',
        'room_type' => 'chamber',
        'hex_count' => 1,
        'entry_port_count' => 1,
        'exit_port_count' => 1,
        'footprint' => [['q' => 0, 'r' => 0]],
        'ports' => [
          ['kind' => 'entry', 'port_id' => 'entry-1', 'q' => 0, 'r' => 0, 'edge' => 3],
          ['kind' => 'exit', 'port_id' => 'exit-1', 'q' => 0, 'r' => 0, 'edge' => 0],
        ],
      ],
    ];
    $dungeonEditor = $this->createMock(DungeonEditorService::class);
    $dungeonEditor->method('getDraft')->willReturn([
      'draft_id' => 'draft-dungeon',
      'revision' => 0,
      'dungeon' => ['dungeon_id' => 'generated', 'name' => '', 'room_placements' => [], 'port_links' => []],
    ]);
    $dungeonEditor->method('roomLibrary')->willReturn($library);
    $dungeonEditor->method('simulateCommands')->willReturnCallback(static function (string $draft_id, array $commands): array {
      $placements = array_values(array_filter($commands, static fn(array $command): bool => $command['type'] === 'place_room'));
      $links = array_values(array_filter($commands, static fn(array $command): bool => $command['type'] === 'link_ports'));
      return [
        'rejected' => NULL,
        'validation' => ['is_valid' => TRUE, 'findings' => []],
        'dungeon' => [
          'room_placements' => array_map(static fn(array $command): array => $command['payload'], $placements),
          'port_links' => array_map(static fn(array $command): array => $command['payload'], $links),
        ],
      ];
    });
    $ai = $this->ai([[
      'name' => 'Sewer Chain',
      'description' => 'Three original connected sewer rooms.',
      'theme' => 'sewer',
      'rooms' => [
        ['room_id' => 'published-a', 'role' => 'entrance'],
        ['room_id' => 'published-a', 'role' => 'middle'],
        ['room_id' => 'published-a', 'role' => 'goal'],
      ],
    ]]);

    $result = $this->service($ai, $definitions, NULL, $dungeonEditor)->generateDungeonLayout([
      'prompt' => '3 connected rooms, sewer theme',
      'seed' => 77,
    ], new DungeonEditorGmToolContext('draft-dungeon', 'editing', $dungeonEditor, $definitions));

    $this->assertSame('dungeon_layout', $result['generation_type']);
    $placeSteps = array_values(array_filter($result['command_plan']['steps'], static fn(array $step): bool => $step['command_type'] === 'place_room'));
    $linkSteps = array_values(array_filter($result['command_plan']['steps'], static fn(array $step): bool => $step['command_type'] === 'link_ports'));
    $this->assertCount(3, $placeSteps);
    $this->assertCount(2, $linkSteps);
    $this->assertArrayHasKey('placement_id', $placeSteps[0]['payload']);
    $this->assertSame($placeSteps[0]['payload']['placement_id'], $linkSteps[0]['payload']['from']['placement_id']);
    $this->assertSame($placeSteps[1]['payload']['placement_id'], $linkSteps[0]['payload']['to']['placement_id']);
  }

}
