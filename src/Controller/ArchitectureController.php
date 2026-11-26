<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Renders the architecture review and its live feature index.
 */
class ArchitectureController extends ControllerBase {

  /**
   * Render the architecture overview hub.
   */
  public function index(): array {
    return [
      '#theme' => 'architecture_overview',
      '#feature_index' => $this->buildFeatureIndex(),
      '#agent_architecture' => $this->buildAgentArchitecture(),
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/architecture',
        ],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Projects the registered harnesses into a human-readable hierarchy.
   */
  private function buildAgentArchitecture(): array {
    $registry = \Drupal::service('dungeoncrawler_content.agent_harness_registry');
    $registered = $registry->describeAll();
    $editor = $registered['editor_gm'] ?? [];
    $surface_labels = [
      'storyline_builder' => 'Storyline Builder GM',
      'dungeon_editor' => 'Dungeon Editor GM',
      'room_editor' => 'Room Editor GM',
      'definition_editor' => 'Definition Editor GM',
      'character_creation' => 'Character Creation GM',
      'editor_suite' => 'Editor Suite GM',
    ];
    $editor_children = [];
    foreach ((array) ($editor['tools'] ?? []) as $surface => $manifest) {
      if ($surface === 'shared_harness_tools' || !isset($surface_labels[$surface])) {
        continue;
      }
      $editor_children[] = [
        'id' => $surface,
        'label' => $surface_labels[$surface],
        'scope' => str_replace('_', ' ', $surface) . ' draft',
        'tool_count' => (int) ($manifest['tool_count'] ?? 0),
      ];
    }

    $runtime_tools = (array) ($registered['runtime_gm_actor']['tools'] ?? []);
    $player_tools = (array) ($registered['player_automation_actor']['tools'] ?? []);
    return [
      'harnesses' => array_values(array_map(
        static fn(array $descriptor): array => [
          'agent_id' => (string) ($descriptor['agent_id'] ?? ''),
          'label' => (string) ($descriptor['label'] ?? ''),
          'scope' => (string) ($descriptor['scope'] ?? ''),
          'profile' => (string) ($descriptor['default_execution_profile'] ?? ''),
        ],
        $registered,
      )),
      'editor_children' => $editor_children,
      'runtime' => [
        'tool_count' => count((array) ($runtime_tools['tools'] ?? [])),
        'scope' => (string) ($registered['runtime_gm_actor']['scope'] ?? ''),
      ],
      'player' => [
        'action_count' => count((array) ($player_tools['actions'] ?? [])),
        'tool_count' => count((array) ($player_tools['tools'] ?? [])),
        'scope' => (string) ($registered['player_automation_actor']['scope'] ?? ''),
      ],
      'site_guide' => [
        'tool_count' => count((array) ($registered['site_guide']['tools'] ?? [])),
        'scope' => (string) ($registered['site_guide']['scope'] ?? ''),
      ],
      'mcp' => [
        'status' => 'Live read-only Site Guide MCP gateway; privileged agent execution remains out of scope',
        'broker' => 'The Drupal-hosted MCP HTTP gateway validates the current principal and exposes only permission-filtered Site Guide resources, navigation, capability discovery, and handoff receipts.',
        'actor_rule' => 'Actor and player automation agents may request runtime GM help and shared non-mutating services. They never receive GM privileged tools or direct editor authority.',
        'editor_rule' => 'Editor GM surfaces may delegate to another owning editor agent, but the receiving surface retains its own draft permission and tool validation.',
      ],
    ];
  }

  /**
   * Build a live feature index from HQ feature artifacts.
   */
  private function buildFeatureIndex(): array {
    $root = \Drupal::config('dungeoncrawler_content.settings')
      ->get('hq_features_path') ?: \Drupal::service('extension.list.module')
      ->getPath('dungeoncrawler_content') . '/hq_features';

    if (!is_dir($root)) {
      return [[
        'feature_id' => (string) $this->t('Unavailable'),
        'status' => (string) $this->t('unknown'),
        'priority' => '-',
        'release' => '-',
        'acceptance' => (string) $this->t('HQ features directory was not found.'),
        'implementation' => (string) $this->t('missing'),
      ]];
    }

    $feature_dirs = glob($root . '/dc-*', GLOB_ONLYDIR) ?: [];
    sort($feature_dirs);

    $rows = [];
    foreach ($feature_dirs as $dir) {
      $feature_id = basename($dir);
      $feature_md_path = $dir . '/feature.md';
      $ac_path = $dir . '/01-acceptance-criteria.md';
      $impl_path = $dir . '/02-implementation-notes.md';

      $feature_md = is_file($feature_md_path) ? (string) file_get_contents($feature_md_path) : '';
      $ac_md = is_file($ac_path) ? (string) file_get_contents($ac_path) : '';
      $has_impl = is_file($impl_path);

      $rows[] = [
        'feature_id' => $feature_id,
        'status' => $this->extractFieldValue($feature_md, 'Status', 'unknown'),
        'priority' => $this->extractFieldValue($feature_md, 'Priority', '-'),
        'release' => $this->extractFieldValue($feature_md, 'Release', '-'),
        'acceptance' => $this->summarizeAcceptanceCriteria($ac_md),
        'implementation' => $has_impl ? 'present' : 'missing',
      ];
    }

    return $rows;
  }

  /**
   * Extract markdown field values from "- Label: value" patterns.
   */
  private function extractFieldValue(string $markdown, string $label, string $fallback): string {
    $pattern = '/^-\\s*' . preg_quote($label, '/') . ':\\s*(.+)$/mi';
    if (preg_match($pattern, $markdown, $matches)) {
      return trim((string) ($matches[1] ?? $fallback));
    }
    return $fallback;
  }

  /**
   * Summarize acceptance criteria by counting open checklist items.
   */
  private function summarizeAcceptanceCriteria(string $markdown): string {
    if ($markdown === '') {
      return 'missing';
    }

    $open = preg_match_all('/^-\s*\[\s\]\s+/m', $markdown);
    $done = preg_match_all('/^-\s*\[x\]\s+/mi', $markdown);
    $open_count = (int) ($open ?: 0);
    $done_count = (int) ($done ?: 0);
    $total = $open_count + $done_count;

    if ($total === 0) {
      return 'no checklist items';
    }

    return sprintf('%d total (%d open / %d done)', $total, $open_count, $done_count);
  }

}
