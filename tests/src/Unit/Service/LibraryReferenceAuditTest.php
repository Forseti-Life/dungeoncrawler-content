<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Tests\UnitTestCase;

/**
 * Audits authored storyline and quest references against library assets.
 *
 * @group dungeoncrawler_content
 * @group content
 */
class LibraryReferenceAuditTest extends UnitTestCase {

  /**
   * Verifies literal storyline asset references resolve to library content.
   */
  public function testStorylineAssetReferencesResolveToLibraryContent(): void {
    $assets = $this->buildAssetIndex();
    $storylines = $this->loadJsonRows(dirname(__DIR__, 4) . '/config/examples/templates/dungeoncrawler_content_storylines/default_storyline_templates.json');

    $missing = [];
    foreach ($storylines as $row) {
      $template = is_array($row['template_data'] ?? NULL) ? $row['template_data'] : [];
      foreach (($template['asset_references'] ?? []) as $reference) {
        if (!is_array($reference)) {
          continue;
        }
        $asset_id = (string) ($reference['asset_id'] ?? '');
        $asset_type = (string) ($reference['asset_type'] ?? '');
        if ($asset_id === '') {
          continue;
        }
        $resolved = match ($asset_type) {
          'location' => isset($assets['locations'][$asset_id]),
          'room' => isset($assets['rooms'][$asset_id]),
          'npc' => isset($assets['characters'][$asset_id]),
          'item' => isset($assets['items'][$asset_id]) || isset($assets['registry'][$asset_id]),
          default => TRUE,
        };
        if (!$resolved) {
          $missing[] = "{$asset_type}:{$asset_id}";
        }
      }

      foreach (($template['contacts'] ?? []) as $contact) {
        if (!is_array($contact)) {
          continue;
        }
        $entity_type = (string) ($contact['entity_type'] ?? '');
        $entity_id = (string) ($contact['entity_id'] ?? '');
        if ($entity_type === 'npc_template' && $entity_id !== '' && !isset($assets['characters'][$entity_id])) {
          $missing[] = "contact:{$entity_id}";
        }
      }
    }

    $this->assertSame([], $missing, implode('; ', $missing));
  }

  /**
   * Verifies literal quest objective references resolve to library content.
   */
  public function testQuestObjectiveReferencesResolveToLibraryContent(): void {
    $assets = $this->buildAssetIndex();
    $quests = $this->loadQuestRows();
    $missing = [];

    foreach ($quests as $quest) {
      $quest_id = (string) ($quest['quest_id'] ?? $quest['template_id'] ?? '');
      foreach (($quest['objectives_schema'] ?? []) as $phase) {
        foreach (($phase['objectives'] ?? []) as $objective) {
          $missing = array_merge($missing, $this->auditObjectiveNode($quest_id, $objective, $assets));
        }
      }
    }

    $this->assertSame([], $missing, implode('; ', $missing));
  }

  /**
   * Audits a single objective node recursively.
   */
  private function auditObjectiveNode(string $quest_id, mixed $objective, array $assets): array {
    if (!is_array($objective)) {
      return [];
    }

    $missing = [];
    $type = (string) ($objective['type'] ?? '');

    $location = (string) ($objective['location'] ?? $objective['destination'] ?? '');
    if ($this->isLiteralReference($location) && !isset($assets['rooms'][$location]) && !isset($assets['locations'][$location])) {
      $missing[] = "{$quest_id}:location:{$location}";
    }

    $target = (string) ($objective['target'] ?? '');
    if ($this->isLiteralReference($target)) {
      $target_exists = isset($assets['characters'][$target])
        || isset($assets['rooms'][$target])
        || isset($assets['locations'][$target])
        || isset($assets['items'][$target])
        || isset($assets['registry'][$target])
        || isset($assets['creatures'][$target]);
      if (!$target_exists && in_array($type, ['interact', 'investigate', 'kill'], TRUE)) {
        $missing[] = "{$quest_id}:target:{$target}";
      }
    }

    $item = (string) ($objective['item'] ?? '');
    if ($this->isLiteralReference($item) && !isset($assets['items'][$item]) && !isset($assets['registry'][$item])) {
      $missing[] = "{$quest_id}:item:{$item}";
    }

    foreach (($objective['children'] ?? []) as $child) {
      $missing = array_merge($missing, $this->auditObjectiveNode($quest_id, $child, $assets));
    }

    return $missing;
  }

  /**
   * Builds a lookup index of authored library assets.
   */
  private function buildAssetIndex(): array {
    $base = dirname(__DIR__, 4);

    $characters = [];
    foreach (glob($base . '/config/examples/templates/dungeoncrawler_content_characters/*.json') as $file) {
      foreach ($this->loadJsonRows($file) as $row) {
        $id = (string) ($row['instance_id'] ?? '');
        if ($id !== '') {
          $characters[$id] = TRUE;
        }
      }
    }

    $rooms = [];
    foreach (glob($base . '/config/examples/templates/dungeoncrawler_content_rooms/*.json') as $file) {
      foreach ($this->loadJsonRows($file) as $row) {
        $id = (string) ($row['room_id'] ?? '');
        if ($id !== '') {
          $rooms[$id] = TRUE;
        }
      }
    }

    $registry = [];
    $locations = [];
    foreach (glob($base . '/config/examples/templates/dungeoncrawler_content_registry/*.json') as $file) {
      foreach ($this->loadJsonRows($file) as $row) {
        $id = (string) ($row['content_id'] ?? '');
        if ($id === '') {
          continue;
        }
        $registry[$id] = TRUE;
        if ((string) ($row['content_type'] ?? '') === 'location') {
          $locations[$id] = TRUE;
        }
      }
    }

    $items = [];
    foreach (glob($base . '/config/examples/templates/dungeoncrawler_content_item_instances/*.json') as $file) {
      foreach ($this->loadJsonRows($file) as $row) {
        $id = (string) ($row['item_id'] ?? '');
        if ($id !== '') {
          $items[$id] = TRUE;
        }
      }
    }

    $creatures = [];
    foreach (glob($base . '/content/creatures/*.json') as $file) {
      $decoded = json_decode((string) file_get_contents($file), TRUE);
      $id = (string) ($decoded['creature_id'] ?? pathinfo($file, PATHINFO_FILENAME));
      if ($id !== '') {
        $creatures[$id] = TRUE;
      }
    }

    return [
      'characters' => $characters,
      'rooms' => $rooms,
      'registry' => $registry,
      'locations' => $locations,
      'items' => $items,
      'creatures' => $creatures,
    ];
  }

  /**
   * Loads quest rows across authored quest files.
   */
  private function loadQuestRows(): array {
    $base = dirname(__DIR__, 4);
    $files = array_merge(
      [$base . '/content/quest_templates.json'],
      glob($base . '/templates/quests/*.json'),
      glob($base . '/config/examples/templates/dungeoncrawler_content_quest_templates/*.json')
    );

    $rows = [];
    foreach ($files as $file) {
      $decoded = json_decode((string) file_get_contents($file), TRUE);
      $file_rows = isset($decoded[0]) ? $decoded : (isset($decoded['rows']) ? $decoded['rows'] : [$decoded]);
      foreach ($file_rows as $row) {
        if (is_array($row)) {
          $rows[] = $row;
        }
      }
    }

    return $rows;
  }

  /**
   * Loads a JSON file that stores rows.
   */
  private function loadJsonRows(string $file): array {
    $decoded = json_decode((string) file_get_contents($file), TRUE);
    if (!is_array($decoded)) {
      return [];
    }
    return isset($decoded['rows']) && is_array($decoded['rows'])
      ? $decoded['rows']
      : (isset($decoded[0]) ? $decoded : [$decoded]);
  }

  /**
   * Returns whether the reference is a literal asset id rather than a placeholder.
   */
  private function isLiteralReference(string $value): bool {
    return $value !== '' && !str_contains($value, '{') && !str_contains($value, '}');
  }

}
