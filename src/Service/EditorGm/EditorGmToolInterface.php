<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

/**
 * Contract implemented by every editor GM harness tool.
 */
interface EditorGmToolInterface {

  /**
   * Declares the tool contract published to editor clients.
   */
  public function definition(): EditorGmToolDefinition;

  /**
   * Executes the tool against grounded editor context.
   *
   * @return array
   *   An associative tool result payload.
   */
  public function execute(array $arguments, EditorGmToolContext $context): array;

}
