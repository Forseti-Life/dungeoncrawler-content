<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Site\Settings;

/**
 * Resolves roadmap requirement status from feature pipeline metadata.
 */
class RoadmapPipelineStatusResolver {

  /**
   * Maps feature pipeline statuses to roadmap display statuses.
   *
   * - 'done'    = code written and unit-tested; NOT yet QA-verified → in_progress
   * - 'shipped' = QA-verified and released to production → implemented
   * - 'backlog' = deferred/unstarted work → pending
   */
  private const PIPELINE_TO_ROADMAP = [
    'pre-triage' => 'pending',
    'planned'    => 'pending',
    'pending'    => 'pending',
    'ready'      => 'pending',
    'deferred'   => 'pending',
    'backlog'    => 'pending',
    'in_progress' => 'in_progress',
    'done'       => 'in_progress',
    'shipped'    => 'implemented',
  ];

  /**
   * Pipeline statuses that should be visible in backlog groupings.
   */
  private const BACKLOG_VISIBLE_STATUSES = ['ready', 'in_progress'];

  /**
   * Absolute path to the HQ features directory.
   */
  private string $featuresPath;

  /**
   * Request-local cache of parsed feature statuses.
   *
   * @var array<string, string|null>
   */
  private array $statusCache = [];

  /**
   * Constructs the resolver.
   */
  public function __construct(?string $features_path = NULL) {
    $module_path = \Drupal::service('extension.list.module')
      ->getPath('dungeoncrawler_content');
    $this->featuresPath = rtrim(
      $features_path ?: Settings::get('dungeoncrawler_pipeline_features_path', $module_path . '/features'),
      DIRECTORY_SEPARATOR
    );
  }

  /**
   * Resolves the roadmap status for a requirement.
   */
  public function resolveRoadmapStatus(?string $feature_id, string $fallback_status): string {
    if (empty($feature_id)) {
      return $fallback_status;
    }

    $pipeline_status = $this->getPipelineStatus($feature_id);
    if ($pipeline_status === NULL) {
      return $fallback_status;
    }

    return self::PIPELINE_TO_ROADMAP[$pipeline_status] ?? $fallback_status;
  }

  /**
   * Returns the raw pipeline status for a feature, if available.
   */
  public function getPipelineStatus(string $feature_id): ?string {
    if (array_key_exists($feature_id, $this->statusCache)) {
      return $this->statusCache[$feature_id];
    }

    if ($feature_id === '' || str_contains($feature_id, '/') || str_contains($feature_id, '\\') || str_contains($feature_id, '..')) {
      $this->statusCache[$feature_id] = NULL;
      return NULL;
    }

    $feature_path = $this->featuresPath . DIRECTORY_SEPARATOR . $feature_id . DIRECTORY_SEPARATOR . 'feature.md';
    if (!is_readable($feature_path)) {
      $this->statusCache[$feature_id] = NULL;
      return NULL;
    }

    $contents = file_get_contents($feature_path);
    if ($contents === FALSE || !preg_match('/^- Status:\s*(.+)$/m', $contents, $matches)) {
      $this->statusCache[$feature_id] = NULL;
      return NULL;
    }

    $status = mb_strtolower(trim($matches[1]));
    $this->statusCache[$feature_id] = $status;
    return $status;
  }

  /**
   * Returns grouped backlog features from HQ feature briefs.
   *
   * These are separate from requirement-linked roadmap rows and are used to
   * surface groomed work such as UI modernization epics before every item is
   * mapped into dc_requirements.
   *
   * @return array<int, array<string, mixed>>
   *   Group arrays containing title, counts, and feature lists.
   */
  public function getFeatureBacklogGroups(string $website = 'dungeoncrawler', array $visible_statuses = self::BACKLOG_VISIBLE_STATUSES): array {
    if (!is_dir($this->featuresPath)) {
      return [];
    }

    $feature_dirs = glob($this->featuresPath . DIRECTORY_SEPARATOR . 'dc-*', GLOB_ONLYDIR) ?: [];
    sort($feature_dirs);

    $visible_lookup = array_fill_keys(array_map('mb_strtolower', $visible_statuses), TRUE);
    $groups = [];

    foreach ($feature_dirs as $dir) {
      $feature_id = basename($dir);
      if ($feature_id === '' || str_contains($feature_id, '/') || str_contains($feature_id, '\\') || str_contains($feature_id, '..')) {
        continue;
      }

      $feature_path = $dir . DIRECTORY_SEPARATOR . 'feature.md';
      if (!is_readable($feature_path)) {
        continue;
      }

      $contents = file_get_contents($feature_path);
      if ($contents === FALSE) {
        continue;
      }

      $feature_website = mb_strtolower($this->extractFieldValue($contents, 'Website', ''));
      if ($feature_website !== mb_strtolower($website)) {
        continue;
      }

      $status = mb_strtolower($this->extractFieldValue($contents, 'Status', ''));
      if (!isset($visible_lookup[$status])) {
        continue;
      }

      $roadmap_group = $this->extractFieldValue($contents, 'Roadmap', '');
      if ($roadmap_group === '') {
        continue;
      }

      $display_status = $status === 'ready' ? 'queued' : 'in_progress';
      $feature = [
        'feature_id' => $feature_id,
        'title' => $this->extractFeatureTitle($contents, $feature_id),
        'status' => $status,
        'display_status' => $display_status,
        'status_label' => $display_status === 'queued' ? 'Queued' : 'In Progress',
        'priority' => $this->extractFieldValue($contents, 'Priority', '-'),
        'release' => $this->extractFieldValue($contents, 'Release', '-'),
      ];

      if (!isset($groups[$roadmap_group])) {
        $groups[$roadmap_group] = [
          'title' => $roadmap_group,
          'counts' => ['queued' => 0, 'in_progress' => 0],
          'features' => [],
        ];
      }

      $groups[$roadmap_group]['counts'][$display_status]++;
      $groups[$roadmap_group]['features'][] = $feature;
    }

    foreach ($groups as &$group) {
      usort($group['features'], function (array $a, array $b): int {
        $priority_compare = $this->priorityRank($a['priority']) <=> $this->priorityRank($b['priority']);
        if ($priority_compare !== 0) {
          return $priority_compare;
        }

        return strnatcasecmp($a['title'], $b['title']);
      });
    }
    unset($group);

    uasort($groups, static function (array $a, array $b): int {
      return strnatcasecmp($a['title'], $b['title']);
    });

    return array_values($groups);
  }

  /**
   * Extract markdown field values from "- Label: value" patterns.
   */
  private function extractFieldValue(string $markdown, string $label, string $fallback): string {
    $pattern = '/^-\s*' . preg_quote($label, '/') . ':\s*(.+)$/mi';
    if (preg_match($pattern, $markdown, $matches)) {
      return trim((string) ($matches[1] ?? $fallback));
    }

    return $fallback;
  }

  /**
   * Extracts a display title from the feature brief heading.
   */
  private function extractFeatureTitle(string $markdown, string $fallback): string {
    if (preg_match('/^#\s*(.+)$/m', $markdown, $matches)) {
      $heading = trim((string) $matches[1]);
      $heading = preg_replace('/^Feature Brief:\s*/i', '', $heading);
      return $heading !== NULL && $heading !== '' ? $heading : $fallback;
    }

    return $fallback;
  }

  /**
   * Provides a stable sort order for feature priorities.
   */
  private function priorityRank(string $priority): int {
    return match (mb_strtoupper(trim($priority))) {
      'P0' => 0,
      'P1' => 1,
      'P2' => 2,
      'P3' => 3,
      default => 9,
    };
  }

}
