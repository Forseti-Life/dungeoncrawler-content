<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon\PlanPortLinksTool;
use PHPUnit\Framework\TestCase;

/**
 * The GM can link an exit to an entry on a room anywhere on the level.
 */
class PlanPortLinksToolTest extends TestCase {

  private function context(): DungeonEditorGmToolContext {
    $placement = static fn(string $id, array $origin, array $ports): array => [
      'placement_id' => $id,
      'label' => $id,
      'origin' => $origin,
      'rotation_steps' => 0,
      'resolved' => TRUE,
      'room' => ['ports' => $ports],
    ];
    $editor = new class($placement) extends DungeonEditorService {

      public function __construct(private \Closure $placement) {}

      public function getDraft(string $draft_id): array {
        return ['draft_id' => $draft_id, 'revision' => 7];
      }

      public function describe(string $draft_id): array {
        return [
          'placements' => [
            ($this->placement)('mill', ['q' => 0, 'r' => 0], [['port_id' => 'out', 'kind' => 'exit', 'q' => 0, 'r' => 0, 'edge' => 0]]),
            ($this->placement)('forge', ['q' => 20, 'r' => -7], [
              ['port_id' => 'west', 'kind' => 'entry', 'q' => 0, 'r' => 0, 'edge' => 3],
              ['port_id' => 'east', 'kind' => 'entry', 'q' => 1, 'r' => 0, 'edge' => 0],
            ]),
          ],
          'port_links' => [],
        ];
      }

    };
    return new DungeonEditorGmToolContext('draft', 'editing', $editor, $this->createMock(CanonicalDefinitionService::class));
  }

  public function testLinksRoomsThatDoNotTouch(): void {
    $link = ['kind' => 'hallway', 'direction' => 'bidirectional', 'default_state' => 'open'];
    $result = (new PlanPortLinksTool())->execute($link + ['placement_id' => 'mill', 'to_placement_id' => 'forge', 'to_port_id' => 'east'], $this->context());
    $this->assertSame(7, $result['command_plan']['base_revision']);
    $this->assertSame([
      'from' => ['placement_id' => 'mill', 'port_id' => 'out'],
      'to' => ['placement_id' => 'forge', 'port_id' => 'east'],
    ] + $link, $result['command_plan']['steps'][0]['payload']);

    // Two entry ports and none named: the tool names the choices, never picks.
    try {
      (new PlanPortLinksTool())->execute($link + ['placement_id' => 'mill', 'to_placement_id' => 'forge'], $this->context());
      $this->fail('entry_port_ambiguous expected.');
    }
    catch (\DomainException $exception) {
      $this->assertSame('entry_port_ambiguous:forge:west,east', $exception->getMessage());
    }

    // Without a target only touching ports are linked, and these are apart.
    $this->expectExceptionMessage('no_touching_port_pairs');
    (new PlanPortLinksTool())->execute($link, $this->context());
  }

}
