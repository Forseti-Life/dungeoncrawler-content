<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorSuite\EditorSuiteService;

/**
 * Lists every canonical dungeon with its publication status.
 */
final class ListDungeonsTool implements EditorGmToolInterface {

  public function __construct(private readonly EditorSuiteService $suite) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'list_dungeons',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'List every canonical dungeon with publication status and published version id.',
      FALSE,
      'DungeonEditorService::listDungeons()',
      [],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $dungeons = $this->suite->dungeons();
    return ['dungeons' => $dungeons, 'count' => count($dungeons)];
  }

}
