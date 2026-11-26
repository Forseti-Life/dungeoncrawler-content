<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Names the exact hexes and buildings that break a district layout.
 */
final class InspectDistrictTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'inspect_district',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Read-only. Lists the problem data points of a district draft: every overlapping hex between two placements , buildings with no entry port on a street hex, buildings within 3 hexes of the street edge, the street and level entrances. Use it before planning fixes with plan_district_building or plan_dungeon_commands.',
      FALSE,
      'DungeonEditorService::inspectDistrict()',
      [],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    return $context->dungeonEditor->inspectDistrict($context->draft());
  }

}
