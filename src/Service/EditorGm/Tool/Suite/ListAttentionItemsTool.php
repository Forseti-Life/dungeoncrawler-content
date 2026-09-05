<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorSuiteGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorSuite\EditorReviewFlagService;

/**
 * Lists cross-editor attention rows, optionally with the audit findings behind one.
 */
final class ListAttentionItemsTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'list_attention_items',
      EditorGmToolDefinition::FAMILY_VALIDATION,
      'List every condition needing an author across the suite. Pass a code to include the individual audit findings behind that row.',
      FALSE,
      'EditorSuiteService::attention(), EditorReviewFlagService::findings()',
      [
        EditorGmToolDefinition::argument('code', 'string', FALSE, 'Attention code to expand, e.g. port_edge_unverified.'),
        EditorGmToolDefinition::argument('limit', 'integer', FALSE, 'Findings to include when expanding, 1-500.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = EditorSuiteGmToolContext::of($context);
    $attention = $context->summary()['attention'];
    $code = $arguments['code'] ?? NULL;
    if ($code === NULL) {
      return ['attention' => $attention, 'count' => count($attention)];
    }
    if (!is_string($code) || trim($code) === '') {
      throw new \InvalidArgumentException('argument_invalid:code');
    }
    $rows = array_values(array_filter($attention, static fn(array $a): bool => $a['code'] === $code));
    if ($rows === []) {
      throw new \OutOfBoundsException(sprintf('attention_code_not_present:%s', $code));
    }
    $limit = $arguments['limit'] ?? 50;
    if (!is_int($limit)) {
      throw new \InvalidArgumentException('argument_invalid:limit');
    }
    $row = $rows[0];
    $findings = match ($code) {
      'port_edge_unverified' => $context->reviewFlags->findings(EditorReviewFlagService::FLAG_PORT_EDGE, $limit),
      'definition_schema_nonconforming' => array_merge(
        $context->reviewFlags->findings(EditorReviewFlagService::FLAG_DEFINITION_SCHEMA, $limit),
        $context->reviewFlags->findings(EditorReviewFlagService::FLAG_ACTOR_SCHEMA, $limit),
      ),
      default => $row['subjects'] ?? [],
    };
    return ['attention' => $row, 'findings' => array_slice($findings, 0, $limit), 'finding_count' => count($findings)];
  }

}
