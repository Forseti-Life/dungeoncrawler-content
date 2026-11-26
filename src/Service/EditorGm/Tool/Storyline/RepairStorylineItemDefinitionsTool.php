<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;

/**
 * Repairs redundant IDs in existing canonical items linked by the draft.
 */
final class RepairStorylineItemDefinitionsTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'repair_storyline_item_definitions',
      EditorGmToolDefinition::FAMILY_DEFINITION,
      'Remove an unsupported duplicate content_id from the existing canonical item definitions named by current Storyline findings. Never creates missing items or changes the draft.',
      TRUE,
      'CanonicalDefinitionService::repairRedundantItemContentIds()',
      [
        EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Current saved draft revision.'),
        EditorGmToolDefinition::argument('item_ids', 'array', TRUE, 'Exact existing canonical item IDs in scope.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    if (!\Drupal::currentUser()->hasPermission('edit canonical dungeoncrawler definitions')) {
      throw new \UnexpectedValueException('Canonical item repair requires permission to edit canonical definitions.');
    }
    $context = StorylineBuilderGmToolContext::of($context);
    if ($context->draft()['revision'] !== EditorGmToolContext::requireInt($arguments, 'expected_revision')) {
      throw new \DomainException('storyline_draft_revision_conflict');
    }
    $ids = EditorGmToolContext::requireArray($arguments, 'item_ids');
    if (!array_is_list($ids) || $ids === []) {
      throw new \InvalidArgumentException('Canonical item repair requires a nonempty list of item IDs.');
    }
    $findings = self::repairableFindings($context->validation());
    $hashes = $labels = $already_resolved = [];
    foreach ($ids as $id) {
      if (!is_string($id) || isset($hashes[$id])) {
        throw new \DomainException('Canonical item repair requires distinct string IDs.');
      }
      if (!isset($findings[$id])) {
        try {
          $payload = $context->definitions->definitionPayload('item', $id);
        }
        catch (\OutOfBoundsException $exception) {
          throw new \DomainException('Canonical item repair cannot find: ' . $id, 0, $exception);
        }
        if (isset($payload['content_id']) || $context->definitions->validateDefinition('item', $payload) !== []) {
          throw new \DomainException('Canonical item is not repairable by this operation: ' . $id);
        }
        $already_resolved[$id] = $id;
        continue;
      }
      $hashes[$id] = $findings[$id]['definition_hash'];
      $labels[] = $findings[$id]['entity_label'];
    }
    $receipts = $hashes === [] ? [] : $context->definitions->repairRedundantItemContentIds($hashes);
    $context->invalidate();
    return ['items' => $labels, 'already_resolved' => array_values($already_resolved), 'definitions' => $receipts];
  }

  /**
   * Indexes actionable schema findings by their owning item ID.
   */
  public static function repairableFindings(array $validation): array {
    $items = [];
    foreach ($validation['stages'] ?? [] as $stage) {
      foreach ($stage['findings'] ?? [] as $finding) {
        if (($finding['code'] ?? '') === 'canonical_item_definition_invalid'
          && $finding['definition_pointer'] === '/content_id'
          && $finding['schema_error_code'] === 'additional_property') {
          $items[$finding['entity_id']] = $finding;
        }
      }
    }
    return $items;
  }

}
