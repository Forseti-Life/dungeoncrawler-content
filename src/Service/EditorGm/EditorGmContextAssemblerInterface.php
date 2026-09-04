<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

/**
 * Builds the grounded context snapshot an assistant turn starts from.
 *
 * Every surface's snapshot shares the keys the intent parser grounds on:
 * `tool_id`, `draft`, `validation_summary`, `publication`, `authority_boundary`,
 * `assistant`, `tools`. Surface-specific summaries (`room`, `dungeon`) sit
 * beside them.
 */
interface EditorGmContextAssemblerInterface {

  public function assemble(EditorGmToolContext $context): array;

}
