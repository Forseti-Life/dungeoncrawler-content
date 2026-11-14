<?php

declare(strict_types=1);

namespace DungeoncrawlerContent\Tools\ActorAuthority;

/**
 * Read-only source scanner for DC-ACTOR-AUTHORITY-20261114 (S0).
 *
 * Finds database access and generic mutation-entry calls that touch
 * actor-owned or actor-adjacent stores. It never connects to a database and
 * never executes scanned code.
 *
 * Coverage is intentionally explicit. The scanner reports three classes:
 *  - literal: table named by a string literal (classified by lane);
 *  - dynamic: table argument is not a literal, so the target cannot be proven
 *    statically. These are review obligations, never silently "not actor";
 *  - raw_sql: SQL strings naming a tracked table with a write verb, or write
 *    SQL whose table is interpolated.
 *
 * A clean scan is therefore evidence about literal sites only; dynamic and
 * raw-SQL findings must be classified by a human-reviewed register.
 */
final class ActorAuthorityScanner {

  /**
   * Tracked tables keyed by table name, valued by ownership lane.
   *
   * Lanes follow 01-authority-and-data-model.md A3. "core" lanes are
   * actor-owned facts that must move behind the CharacterManager command
   * facade. "domain" lanes keep their specialized owner but must be enlisted
   * in the actor unit of work. "shared" tables mix actor and nonactor rows and
   * need per-site classification.
   */
  public const TABLE_LANES = [
    'dc_campaign_characters' => 'core.actor_row',
    'dungeoncrawler_content_characters' => 'core.legacy_template',
    'dc_canonical_actors' => 'core.canonical_definition',
    'dc_character_advancement' => 'core.progression',
    'dc_actor_history' => 'core.history',
    'dc_campaign_actor_runtime_state' => 'domain.runtime',
    'dc_psychology' => 'domain.psychology',
    'dc_active_effects' => 'domain.effects',
    'dc_campaign_item_instances' => 'domain.inventory',
    'dungeoncrawler_content_item_instances' => 'domain.inventory',
    'dc_disposition_state' => 'domain.social',
    'dc_disposition_events' => 'domain.social',
    'dc_relationship_attitude_state' => 'domain.social',
    'dc_relationship_attitude_events' => 'domain.social',
    'dc_stance_state' => 'domain.social',
    'dc_stance_events' => 'domain.social',
    'dc_aggression_state' => 'domain.social',
    'dc_aggression_events' => 'domain.social',
    'dc_campaign_relationships' => 'domain.social',
    'dungeoncrawler_content_relationships' => 'domain.social',
    'dc_canonical_storyline_actors' => 'domain.storyline_binding',
    'dc_canonical_objective_actor_refs' => 'domain.storyline_binding',
    'dc_npc_sheet_generation_jobs' => 'domain.generation_job',
    'dc_generated_image_links' => 'domain.media',
    'dc_focus_spell_sources' => 'domain.runtime',
    'dc_knowledge_attempt_state' => 'domain.runtime',
    'dc_creature_id_attempts' => 'domain.runtime',
    'dungeoncrawler_content_registry' => 'shared.content_registry',
    'dc_campaign_content_registry' => 'shared.content_registry',
    'dungeoncrawler_content_definition_quarantine' => 'shared.content_registry',
  ];

  /**
   * Database API methods whose first argument is a table name.
   */
  private const TABLE_METHODS = [
    'insert' => 'write',
    'update' => 'write',
    'merge' => 'write',
    'upsert' => 'write',
    'delete' => 'write',
    'truncate' => 'write',
    'select' => 'read',
  ];

  /**
   * Generic mutation entry points that bypass a typed actor command today.
   *
   * Call sites of these methods are part of the writer register even though
   * they contain no table name.
   */
  public const MUTATION_ENTRY_METHODS = [
    'updateCharacter',
    'deleteCharacter',
    'createCharacter',
    'saveCharacterData',
    'saveCanonicalActorTemplate',
    'materializeCanonicalActorToCharacterRecord',
    'instantiateCampaignActorFromTemplate',
    'loadActorBySlugOrId',
    'persistEntities',
    'syncFromEntities',
    'setState',
    'updateHitPoints',
    'addCondition',
    'removeCondition',
    'updateInventory',
    'applyConsumableEffects',
    'applyUpdate',
    'updateProfile',
    'getOrCreateProfile',
    'saveDefinition',
  ];

  private const SQL_WRITE_PATTERN = '/\b(INSERT\s+(?:IGNORE\s+)?INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM|TRUNCATE(?:\s+TABLE)?|ALTER\s+TABLE)\b/i';

  private const SCANNED_EXTENSIONS = ['php', 'module', 'install', 'inc', 'theme', 'profile'];

  private const EXCLUDED_DIRECTORIES = ['vendor', 'node_modules', '.git', 'tmp', 'screenshots', 'images'];

  /**
   * Scans one or more roots.
   *
   * @param array<string, string> $roots
   *   Root label => absolute directory.
   *
   * @return array<string, mixed>
   *   contract, roots, files_scanned, findings, summary.
   */
  public function scan(array $roots): array {
    $findings = [];
    $files_scanned = 0;
    foreach ($roots as $label => $root) {
      if (!is_dir($root)) {
        throw new \InvalidArgumentException('actor_authority_scan_root_missing:' . $label);
      }
      foreach ($this->sourceFiles($root) as $absolute) {
        $files_scanned++;
        $relative = ltrim(substr($absolute, strlen(rtrim($root, '/'))), '/');
        $source = file_get_contents($absolute);
        if ($source === FALSE) {
          throw new \RuntimeException('actor_authority_scan_unreadable:' . $label . ':' . $relative);
        }
        foreach ($this->scanSource($source) as $finding) {
          $findings[] = ['root' => $label, 'file' => $relative] + $finding;
        }
      }
    }
    usort($findings, static fn(array $a, array $b): int => [$a['root'], $a['file'], $a['line'], $a['kind'], $a['subject']] <=> [$b['root'], $b['file'], $b['line'], $b['kind'], $b['subject']]);

    return [
      'contract' => 'actor-authority-scan-v1',
      'roots' => array_keys($roots),
      'files_scanned' => $files_scanned,
      'findings' => $findings,
      'summary' => $this->summarize($findings),
    ];
  }

  /**
   * Scans one PHP source string.
   *
   * @return array<int, array<string, mixed>>
   *   Findings without root/file keys.
   */
  public function scanSource(string $source): array {
    $tokens = array_values(array_filter(
      token_get_all($source),
      static fn($token): bool => !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], TRUE),
    ));
    $findings = [];
    $count = count($tokens);
    $constants = $this->stringConstants($tokens);
    $mentioned = $this->mentionedTables($tokens);
    for ($i = 0; $i < $count; $i++) {
      $token = $tokens[$i];
      if (!is_array($token)) {
        continue;
      }
      // SQL carried in string literals or heredocs, wherever they are used.
      if (in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], TRUE)) {
        $finding = $this->sqlFinding($token[1], $token[2], $this->isInterpolated($tokens, $i));
        if ($finding !== NULL) {
          $findings[] = $finding;
        }
        continue;
      }
      if ($token[0] !== T_STRING || ($tokens[$i + 1] ?? NULL) !== '(') {
        continue;
      }
      $previous = $tokens[$i - 1] ?? NULL;
      if (!is_array($previous) || !in_array($previous[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], TRUE)) {
        continue;
      }
      $method = $token[1];
      if (in_array($method, self::MUTATION_ENTRY_METHODS, TRUE)) {
        // Method names are shared by unrelated classes; the receiver makes
        // each call site reviewable without type inference.
        $receiver = $tokens[$i - 2] ?? NULL;
        $findings[] = [
          'line' => $token[2],
          'kind' => 'mutation_entry_call',
          'access' => 'write',
          'subject' => ltrim(is_array($receiver) ? $receiver[1] : (string) $receiver, '$') . '->' . $method,
          'lane' => 'entry.generic_mutation',
        ];
        continue;
      }
      if (!isset(self::TABLE_METHODS[$method])) {
        continue;
      }
      [$argument, $is_literal] = $this->firstArgument($tokens, $i + 2);
      if ($argument === NULL) {
        // insert()/delete() with no argument is not a table-addressed call.
        continue;
      }
      $access = self::TABLE_METHODS[$method];
      // self::TABLE / static::TABLE declared as a string in this file is as
      // provable as a literal.
      if (!$is_literal && preg_match('/^(?:self|static)::([A-Za-z_][A-Za-z0-9_]*)$/', $argument, $constant) && isset($constants[$constant[1]])) {
        $argument = $constants[$constant[1]];
        $is_literal = TRUE;
      }
      if ($is_literal) {
        $table = trim($argument, "'\"");
        if (!isset(self::TABLE_LANES[$table])) {
          continue;
        }
        $findings[] = [
          'line' => $token[2],
          'kind' => 'literal_table_' . $access,
          'access' => $access,
          'subject' => $table,
          'operation' => $method,
          'lane' => self::TABLE_LANES[$table],
        ];
        continue;
      }
      if ($access === 'read') {
        // Dynamic reads cannot mutate; they are out of the write register.
        continue;
      }
      $findings[] = [
        'line' => $token[2],
        'kind' => 'dynamic_table_write',
        'access' => 'write',
        'subject' => $argument,
        'operation' => $method,
        'lane' => 'unclassified.dynamic',
        // Review aid only: tracked tables named anywhere in this file.
        'tracked_tables_in_file' => $mentioned,
      ];
    }

    return $findings;
  }

  /**
   * Class constants declared as one string literal, keyed by constant name.
   *
   * @return array<string, string>
   *   Constant name => string value.
   */
  private function stringConstants(array $tokens): array {
    $constants = [];
    $count = count($tokens);
    for ($i = 0; $i + 4 < $count; $i++) {
      if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_CONST) {
        continue;
      }
      // const [type] NAME = 'literal';
      for ($j = $i + 1; $j < $i + 4; $j++) {
        if ($tokens[$j] === '=' && is_array($tokens[$j - 1]) && is_array($tokens[$j + 1]) && $tokens[$j + 1][0] === T_CONSTANT_ENCAPSED_STRING && $tokens[$j + 2] === ';') {
          $constants[$tokens[$j - 1][1]] = trim($tokens[$j + 1][1], "'\"");
          break;
        }
      }
    }

    return $constants;
  }

  /**
   * Tracked table names that appear as string literals in the file.
   *
   * @return array<int, string>
   *   Sorted unique table names.
   */
  private function mentionedTables(array $tokens): array {
    $mentioned = [];
    foreach ($tokens as $token) {
      if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
        $value = trim($token[1], "'\"");
        if (isset(self::TABLE_LANES[$value])) {
          $mentioned[$value] = TRUE;
        }
      }
    }
    ksort($mentioned);

    return array_keys($mentioned);
  }

  /**
   * Aggregates findings into the comparable register shape.
   *
   * @param array<int, array<string, mixed>> $findings
   *   Findings including root/file keys.
   *
   * @return array<string, mixed>
   *   Counts plus the per-file write register.
   */
  public function summarize(array $findings): array {
    $register = [];
    $by_kind = [];
    $by_lane = [];
    foreach ($findings as $finding) {
      $by_kind[$finding['kind']] = ($by_kind[$finding['kind']] ?? 0) + 1;
      if ($finding['access'] !== 'write') {
        continue;
      }
      $by_lane[$finding['lane']] = ($by_lane[$finding['lane']] ?? 0) + 1;
      $file_key = $finding['root'] . ':' . $finding['file'];
      $site_key = $finding['kind'] . ':' . ($finding['operation'] ?? 'call') . ':' . $finding['subject'];
      $register[$file_key][$site_key] = ($register[$file_key][$site_key] ?? 0) + 1;
    }
    ksort($by_kind);
    ksort($by_lane);
    ksort($register);
    foreach ($register as &$sites) {
      ksort($sites);
    }
    unset($sites);

    return [
      'by_kind' => $by_kind,
      'write_sites_by_lane' => $by_lane,
      'write_site_total' => array_sum($by_lane),
      'write_file_total' => count($register),
      'write_register' => $register,
    ];
  }

  /**
   * Yields scanned source files in stable order.
   *
   * @return \Generator<int, string>
   *   Absolute paths.
   */
  private function sourceFiles(string $root): \Generator {
    $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
      new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
      static fn(\SplFileInfo $entry): bool => !$entry->isDir() || !in_array($entry->getFilename(), self::EXCLUDED_DIRECTORIES, TRUE),
    ));
    $files = [];
    foreach ($iterator as $entry) {
      if ($entry->isFile() && in_array(strtolower($entry->getExtension()), self::SCANNED_EXTENSIONS, TRUE)) {
        $files[] = $entry->getPathname();
      }
    }
    sort($files);
    yield from $files;
  }

  /**
   * Returns the first call argument and whether it is one string literal.
   *
   * @return array{0: ?string, 1: bool}
   *   Argument source text (NULL when the call has no arguments), is-literal.
   */
  private function firstArgument(array $tokens, int $start): array {
    $depth = 0;
    $parts = [];
    $count = count($tokens);
    for ($i = $start; $i < $count; $i++) {
      $token = $tokens[$i];
      $text = is_array($token) ? $token[1] : $token;
      if (in_array($text, ['(', '[', '{'], TRUE)) {
        $depth++;
      }
      elseif (in_array($text, [')', ']', '}'], TRUE)) {
        if ($depth === 0) {
          break;
        }
        $depth--;
      }
      elseif ($text === ',' && $depth === 0) {
        break;
      }
      $parts[] = $token;
    }
    if ($parts === []) {
      return [NULL, FALSE];
    }
    $is_literal = count($parts) === 1 && is_array($parts[0]) && $parts[0][0] === T_CONSTANT_ENCAPSED_STRING;
    $text = implode('', array_map(static fn($part): string => is_array($part) ? $part[1] : $part, $parts));

    return [$text, $is_literal];
  }

  private function isInterpolated(array $tokens, int $index): bool {
    if ($tokens[$index][0] === T_ENCAPSED_AND_WHITESPACE) {
      return TRUE;
    }

    return ($tokens[$index + 1] ?? NULL) === '.' || ($tokens[$index - 1] ?? NULL) === '.';
  }

  /**
   * Classifies one string fragment as write SQL, or NULL.
   *
   * @return array<string, mixed>|null
   *   Finding without root/file keys.
   */
  private function sqlFinding(string $text, int $line, bool $interpolated): ?array {
    if (!preg_match(self::SQL_WRITE_PATTERN, $text, $verb)) {
      return NULL;
    }
    $operation = strtoupper((string) preg_replace('/\s+/', ' ', $verb[1]));
    $tables = [];
    foreach (array_keys(self::TABLE_LANES) as $table) {
      if (preg_match('/(?<![A-Za-z0-9_])' . preg_quote($table, '/') . '(?![A-Za-z0-9_])/', $text)) {
        $tables[] = $table;
      }
    }
    if ($tables !== []) {
      return [
        'line' => $line,
        'kind' => 'raw_sql_write',
        'access' => 'write',
        'subject' => implode('+', $tables),
        'operation' => $operation,
        'lane' => self::TABLE_LANES[$tables[0]],
      ];
    }
    // SQL verbs also occur in prose. Only SQL whose table is interpolated is a
    // review obligation: the fragment must end where a table name belongs.
    if ($interpolated && preg_match('/\b(?:INTO|UPDATE|FROM|TABLE)\s*[`{]?\s*$/i', rtrim($text, "'\""))) {
      return [
        'line' => $line,
        'kind' => 'dynamic_sql_write',
        'access' => 'write',
        'subject' => $operation,
        'operation' => 'query',
        'lane' => 'unclassified.dynamic',
      ];
    }

    return NULL;
  }

}
