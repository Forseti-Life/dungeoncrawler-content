<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Extension\ModuleExtensionList;

/**
 * Generates local, deterministic campaign names from themed word tables.
 */
class CampaignNameGeneratorService {

  /**
   * Cached campaign naming tables.
   */
  protected ?array $tables = NULL;

  public function __construct(
    protected readonly ModuleExtensionList $moduleList,
  ) {}

  /**
   * Generate a campaign name for a theme.
   */
  public function generate(string $theme = 'classic_dungeon', ?int $seed = NULL): string {
    $tables = $this->loadTables();
    $theme_key = $this->resolveThemeKey($theme, $tables);
    $seed ??= random_int(1, PHP_INT_MAX);
    $rng = new SeededRandomSequence($seed);

    $pattern = $this->pickWeightedPattern($rng, $tables['patterns'] ?? []);
    $pool = $this->buildTokenPool($tables, $theme_key);

    return $this->resolveTemplate((string) ($pattern['template'] ?? 'The {adjective} {noun}'), $pool, $rng);
  }

  /**
   * Generate a campaign name and return the seed used.
   */
  public function generateWithSeed(string $theme = 'classic_dungeon', ?int $seed = NULL): array {
    $seed ??= random_int(1, PHP_INT_MAX);
    return [
      'name' => $this->generate($theme, $seed),
      'seed' => $seed,
    ];
  }

  /**
   * Resolve template tokens using the combined pool.
   */
  protected function resolveTemplate(string $template, array $pool, SeededRandomSequence $rng): string {
    $resolved = preg_replace_callback('/\{([a-z_]+)\}/', static function (array $matches) use ($pool, $rng): string {
      $token = $matches[1] ?? '';
      $values = $pool[$token] ?? [];
      if (!is_array($values) || $values === []) {
        return '';
      }
      return (string) $rng->pick($values);
    }, $template) ?? $template;

    $resolved = preg_replace('/\s+/', ' ', trim($resolved)) ?? trim($resolved);
    return $this->normalizeTitle($resolved);
  }

  /**
   * Normalize a generated title into a readable campaign name.
   */
  protected function normalizeTitle(string $value): string {
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    $value = preg_replace("/\\b([A-Za-z]+)'s's\\b/", '$1\'s', $value) ?? $value;
    return $value;
  }

  /**
   * Pick one weighted pattern definition.
   */
  protected function pickWeightedPattern(SeededRandomSequence $rng, array $patterns): array {
    if ($patterns === []) {
      return ['template' => 'The {adjective} {noun}', 'weight' => 1];
    }

    $weighted = [];
    foreach ($patterns as $pattern) {
      $weight = max(1, (int) ($pattern['weight'] ?? 1));
      for ($i = 0; $i < $weight; $i++) {
        $weighted[] = $pattern;
      }
    }

    return (array) $rng->pick($weighted);
  }

  /**
   * Build the merged token pool for a theme.
   */
  protected function buildTokenPool(array $tables, string $theme): array {
    $shared = $tables['shared'] ?? [];
    $theme_tables = $tables['themes'][$theme] ?? [];

    $pool = $shared;
    $pool['adjective'] = $this->mergeWeightedPool($shared['adjective'] ?? [], $theme_tables['adjective'] ?? []);
    $pool['noun'] = $this->mergeWeightedPool($shared['noun'] ?? [], $theme_tables['noun'] ?? []);
    $pool['place'] = $this->mergeWeightedPool($shared['place'] ?? [], $theme_tables['place'] ?? []);
    $pool['epithet'] = $this->mergeWeightedPool($shared['epithet'] ?? [], $theme_tables['epithet'] ?? []);

    return $pool;
  }

  /**
   * Bias merged token pools toward theme-specific entries.
   */
  protected function mergeWeightedPool(array $shared, array $theme_specific): array {
    return array_values(array_merge($shared, $theme_specific, $theme_specific));
  }

  /**
   * Resolve a best-fit theme key.
   */
  protected function resolveThemeKey(string $theme, array $tables): string {
    $themes = $tables['themes'] ?? [];
    if (isset($themes[$theme])) {
      return $theme;
    }

    return 'classic_dungeon';
  }

  /**
   * Load campaign naming tables from module content.
   */
  protected function loadTables(): array {
    if ($this->tables !== NULL) {
      return $this->tables;
    }

    $module_path = $this->moduleList->getPath('dungeoncrawler_content');
    $table_path = $module_path . '/content/campaign_name_tables.json';
    $raw = file_get_contents($table_path);
    $decoded = json_decode((string) $raw, TRUE);

    if (!is_array($decoded) || empty($decoded['patterns']) || empty($decoded['themes'])) {
      throw new \RuntimeException('Unable to load campaign name generation tables.');
    }

    $this->tables = $decoded;
    return $this->tables;
  }

}
