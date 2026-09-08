<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalGenerationService;

/**
 * Generates a preview-only Dungeon Editor command plan.
 */
final class GenerateDungeonLayoutTool implements EditorGmToolInterface {

  public function __construct(private readonly CanonicalGenerationService $generation) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'generate_dungeon_layout',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Generate an original dungeon layout as place_room/link_ports command_plan proposal using published room versions only.',
      FALSE,
      'propose only; execution requires preview_command_plan/apply_dungeon_commands through DungeonEditorService',
      [
        EditorGmToolDefinition::argument('prompt', 'string', TRUE, 'Original dungeon request, 1..2000 chars.'),
        EditorGmToolDefinition::argument('theme', 'string', FALSE, 'Optional theme, 0..100 chars.'),
        EditorGmToolDefinition::argument('room_count', 'integer', FALSE, 'Number of room placements, 3..20, default 3.'),
        EditorGmToolDefinition::argument('level', 'integer', FALSE, 'Dungeon level -1..25, default 1.'),
        EditorGmToolDefinition::argument('link_kind', 'string', FALSE, 'hallway|archway|door|hatch|portcullis|secret_door|magical_barrier|collapsed|bridge|one_way_drop.'),
        EditorGmToolDefinition::argument('link_direction', 'string', FALSE, 'bidirectional|one_way.'),
        EditorGmToolDefinition::argument('default_state', 'string', FALSE, 'open|closed|locked|barred|trapped|triggered|destroyed.'),
        EditorGmToolDefinition::argument('seed', 'integer', FALSE, 'Optional deterministic seed 0..2147483647.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    return $this->generation->generateDungeonLayout($arguments, $context);
  }

}
