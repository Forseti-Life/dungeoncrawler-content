<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;

/**
 * Revalidates dungeons owned by lower-tier Dungeon Editor authorities.
 */
final class RevalidateChildDungeonsTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'revalidate_child_dungeons',
      EditorGmToolDefinition::FAMILY_VALIDATION,
      'Rerun Dungeon Editor validation for one linked dungeon or every dungeon in this storyline.',
      FALSE,
      'DungeonEditorService::validationStatus()',
      [
        EditorGmToolDefinition::argument('dungeon_id', 'string', FALSE, 'One linked dungeon ID; omit to validate all linked dungeons.'),
        EditorGmToolDefinition::argument('profile', 'string', FALSE, 'editing or publication. Defaults to publication.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = StorylineBuilderGmToolContext::of($context);
    $profile = isset($arguments['profile']) ? (string) $arguments['profile'] : 'publication';
    $specs = (array) ($context->draft()['payload']['objects']['dungeons']['generation_specs'] ?? []);
    $ids = array_values(array_unique(array_map(
      static fn(array $spec): string => trim((string) ($spec['dungeon_id'] ?? '')),
      array_filter($specs, 'is_array'),
    )));
    if (isset($arguments['dungeon_id'])) {
      $requested = EditorGmToolContext::requireString($arguments, 'dungeon_id');
      if (!in_array($requested, $ids, TRUE)) {
        throw new \InvalidArgumentException('dungeon_not_in_storyline');
      }
      $ids = [$requested];
    }
    return [
      'profile' => $profile,
      'dungeon_ids' => array_values(array_filter($ids)),
    ];
  }

}
