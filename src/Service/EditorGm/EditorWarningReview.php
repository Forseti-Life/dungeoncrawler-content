<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Url;

/**
 * Stores draft-scoped decisions about current, non-blocking findings.
 */
final class EditorWarningReview {

  public function __construct(private readonly KeyValueFactoryInterface $storage) {}

  public function warnings(string $surface, string $draft_id, array $validation): array {
    $store = $this->storage->get("dungeoncrawler.warning_review.$surface.$draft_id");
    $accepted = $store->getAll();
    $rows = [];
    foreach ($validation['findings'] ?? $validation['warnings'] ?? [] as $finding) {
      if (is_array($finding) && ($finding['severity'] ?? NULL) !== 'warning') {
        continue;
      }
      $message = is_array($finding) ? $finding['message'] : $finding;
      $identity = is_array($finding) ? $finding : ['message' => $message];
      $id = hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
      $rows[$id] = [
        'message' => $message,
        'accepted' => isset($accepted[$id]),
      ];
    }
    return $rows;
  }

  public function summary(string $surface, array $draft, array $validation): array {
    $id = (string) $draft['draft_id'];
    $rows = $this->warnings($surface, $id, $validation);
    $accepted = count(array_filter(array_column($rows, 'accepted')));
    $open = count($rows) - $accepted;
    $open_rows = array_filter($rows, static fn(array $row): bool => !$row['accepted']);
    return [
      'open_warning_count' => $open,
      'accepted_warning_count' => $accepted,
      'warning_review_fraction' => $rows === [] ? NULL : (float) $accepted / count($rows),
      'warning_review_url' => $rows !== [] && self::isEditable($surface, $draft)
        ? Url::fromRoute('dungeoncrawler_content.editor_warning_review', ['surface' => $surface, 'draft_id' => $id])->toString()
        : NULL,
      'first_open_warning' => $open_rows[array_key_first($open_rows)]['message'] ?? NULL,
    ];
  }

  public static function isEditable(string $surface, array $draft): bool {
    return $draft['status'] === ($surface === 'storyline' ? 'editing' : 'active');
  }

  /**
   * Records only changes made on this form, preserving other decisions.
   */
  public function save(string $surface, string $draft_id, array $updates, int $uid): void {
    $store = $this->storage->get("dungeoncrawler.warning_review.$surface.$draft_id");
    foreach ($updates as $id => $accepted) {
      if ($accepted) {
        $store->setIfNotExists($id, ['uid' => $uid]);
      }
      else {
        $store->delete($id);
      }
    }
  }

}
