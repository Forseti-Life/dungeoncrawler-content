<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use GuzzleHttp\ClientInterface;

/**
 * Core spell rules service with registry-backed spell definition reads.
 *
 * Owns:
 * - Spell data model constants (traditions, schools, components, save types)
 * - Cantrip auto-heightening: effective_rank = ceil(character_level / 2)
 * - Heightened effect computation (specific-rank and cumulative-step entries)
 * - Spontaneous caster signature-spell heightening gate
 * - Innate spell daily consumption tracking
 * - Focus pool hard cap (3)
 *
 * Ordinary spell definitions are sourced from dungeoncrawler_content_registry.
 * Bundled/intermediary JSON files exist to seed that table, but runtime reads
 * should not treat local files or service-local arrays as the source of truth.
 */
class SpellCatalogService {

  public const ARCHIVES_OF_NETHYS_SPELLS_URL = 'https://2e.aonprd.com/Spells.aspx';

  public const ARCHIVES_OF_NETHYS_RITUALS_URL = 'https://2e.aonprd.com/Rituals.aspx';

  public const ARCHIVES_OF_NETHYS_SEARCH_URL = 'https://2e.aonprd.com/Search.aspx';

  public const ARCHIVES_OF_NETHYS_ELASTICSEARCH_URL = 'https://elasticsearch.aonprd.com/aon/_search';

  // -----------------------------------------------------------------------
  // Constants
  // -----------------------------------------------------------------------

  const TRADITIONS = ['arcane', 'divine', 'occult', 'primal'];

  const SPELL_SCHOOLS = [
    'abjuration',
    'conjuration',
    'divination',
    'enchantment',
    'evocation',
    'illusion',
    'necromancy',
    'transmutation',
  ];

  const SPELL_COMPONENTS = ['material', 'somatic', 'verbal', 'focus'];

  const SAVE_TYPES = ['fortitude', 'reflex', 'will', 'basic_fortitude', 'basic_reflex', 'basic_will'];

  const CAST_ACTION_TYPES = [
    '1_action',   // 1 action
    '2_actions',  // 2 actions
    '3_actions',  // 3 actions
    'reaction',   // reaction
    'free_action',// free action
    'one_minute', // 1 minute (exploration)
    'ten_minutes',// 10 minutes (exploration)
    'one_hour',   // 1 hour (exploration)
  ];

  const RARITY_LEVELS = ['common', 'uncommon', 'rare', 'unique'];

  /** Hard cap for Focus Pool (PF2e Core p. 300). */
  const FOCUS_POOL_MAX = 3;

  /**
   * Essence classification types (PF2e Core ch07).
   * Used for resistances, immunities, and lore classification.
   */
  const ESSENCE_TYPES = ['mental', 'vital', 'material', 'spiritual'];

  /**
   * Cast-time values that require the Exploration trait (cannot be used in encounters).
   */
  const EXPLORATION_CAST_TIMES = ['one_minute', 'ten_minutes', 'one_hour'];

  // -----------------------------------------------------------------------
  // Spell registry
  // -----------------------------------------------------------------------

  /**
   * Compatibility in-process spell registry used only by explicit import helpers.
   *
   * @var array<string, array>
   */
  protected array $spells = [];

  /**
   * Cached normalized spell records loaded from the DB registry.
   *
   * @var array<string, array>|null
   */
  protected ?array $registryCatalog = NULL;

  /**
   * Optional live content registry database connection.
   */
  protected ?Connection $database = NULL;

  /**
   * Optional HTTP client used for external Archives of Nethys lookup.
   */
  protected ?ClientInterface $httpClient = NULL;

  public function __construct(?Connection $database = NULL, ?ClientInterface $http_client = NULL) {
    $this->database = $database;
    $this->httpClient = $http_client;
  }

  // -----------------------------------------------------------------------
  // Public API
  // -----------------------------------------------------------------------

  /**
   * Look up a spell by ID.
   */
  public function getSpell(string $spell_id): ?array {
    $catalog = $this->getRegistryCatalog();
    $requested_id = $this->normalizeSpellId($spell_id);
    $candidate_ids = array_values(array_unique(array_filter([
      trim($spell_id),
      $requested_id,
      str_replace('-', '_', $requested_id),
    ])));

    foreach ($candidate_ids as $candidate_id) {
      if (isset($catalog[$candidate_id])) {
        return $this->normalizeSpellRecord($catalog[$candidate_id], $requested_id);
      }
    }

    return NULL;
  }

  /**
   * Build an Archives of Nethys lookup payload for a spell missing locally.
   *
   * @param string $spell_id
   *   The requested spell ID or name.
   *
   * @return array<string, mixed>
   *   Provider metadata and lookup URLs.
   */
  public function buildArchivesOfNethysLookup(string $spell_id): array {
    $requested_id = trim($spell_id);
    $query = trim(str_replace(['_', '-'], ' ', $requested_id));
    if ($query === '') {
      $query = 'spell';
    }

    $lookup = [
      'provider' => 'archives_of_nethys',
      'provider_label' => 'Archives of Nethys',
      'query' => $query,
      'spells_url' => self::ARCHIVES_OF_NETHYS_SPELLS_URL,
      'rituals_url' => self::ARCHIVES_OF_NETHYS_RITUALS_URL,
      'search_url' => self::ARCHIVES_OF_NETHYS_SEARCH_URL . '?Query=' . rawurlencode($query),
    ];

    return array_merge($lookup, $this->searchArchivesOfNethys($query));
  }

  /**
   * Query Archives of Nethys search for a direct match when possible.
   *
   * @param string $query
   *   Human-readable spell query.
   *
   * @return array<string, mixed>
   *   Direct match metadata when available, otherwise an empty array or error
   *   status details.
   */
  protected function searchArchivesOfNethys(string $query): array {
    if ($this->httpClient === NULL || $query === '') {
      return [];
    }

    try {
      $response = $this->httpClient->request('POST', self::ARCHIVES_OF_NETHYS_ELASTICSEARCH_URL, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode([
          'query' => [
            'multi_match' => [
              'query' => $query,
              'fields' => ['name^3', 'title^3', 'content'],
            ],
          ],
          'size' => 5,
        ], JSON_UNESCAPED_SLASHES),
        'timeout' => 5,
      ]);
    }
    catch (\Exception $e) {
      return [
        'lookup_status' => 'search_error',
        'lookup_error' => $e->getMessage(),
      ];
    }

    $payload = json_decode((string) $response->getBody(), TRUE);
    if (!is_array($payload)) {
      return [
        'lookup_status' => 'invalid_search_response',
      ];
    }

    $hits = $payload['hits']['hits'] ?? [];
    if (!is_array($hits) || $hits === []) {
      return [
        'lookup_status' => 'no_match',
      ];
    }

    $normalized_query = $this->normalizeArchivesOfNethysSearchName($query);
    $selected_hit = NULL;
    foreach ($hits as $candidate) {
      $source = $candidate['_source'] ?? NULL;
      if (!is_array($source) || empty($source['url'])) {
        continue;
      }
      if ($this->normalizeArchivesOfNethysSearchName((string) ($source['name'] ?? '')) === $normalized_query) {
        $selected_hit = $source;
        break;
      }
    }

    if ($selected_hit === NULL) {
      $selected_hit = $hits[0]['_source'] ?? NULL;
    }

    $hit = $selected_hit;
    if (!is_array($hit) || empty($hit['url'])) {
      return [
        'lookup_status' => 'no_match',
      ];
    }

    $direct_url = (string) $hit['url'];
    if (str_starts_with($direct_url, '/')) {
      $direct_url = 'https://2e.aonprd.com' . $direct_url;
    }

    return [
      'lookup_status' => 'matched',
      'matched_name' => (string) ($hit['name'] ?? $query),
      'matched_type' => (string) ($hit['type'] ?? ''),
      'matched_category' => (string) ($hit['category'] ?? ''),
      'direct_url' => $direct_url,
    ];
  }

  /**
   * Normalize AoN search names for exact-match comparison.
   */
  protected function normalizeArchivesOfNethysSearchName(string $value): string {
    $value = strtolower(trim($value));
    $value = str_replace(['’', '\''], '', $value);
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
    return trim($value);
  }

  /**
   * List all spells, optionally filtered.
   *
   * @param array $filters
   *   Supported keys:
   *     - tradition (string): one of self::TRADITIONS
   *     - school (string): one of self::SPELL_SCHOOLS
   *     - rank (int): 0–10
   *     - is_cantrip (bool)
   *     - spell_type (string): spell|cantrip|focus|ritual
   *     - rarity (string): one of self::RARITY_LEVELS
   */
  public function getSpells(array $filters = []): array {
    $result = $this->getRegistryCatalog();

    if (isset($filters['tradition'])) {
      $t = strtolower($filters['tradition']);
      $result = array_filter($result, fn($s) => in_array($t, $s['traditions'] ?? [], TRUE));
    }
    if (isset($filters['school'])) {
      $sch = strtolower($filters['school']);
      $result = array_filter($result, fn($s) => ($s['school'] ?? '') === $sch);
    }
    if (isset($filters['rank'])) {
      $r = (int) $filters['rank'];
      $result = array_filter($result, fn($s) => (int) ($s['rank'] ?? 0) === $r);
    }
    if (isset($filters['is_cantrip'])) {
      $ic = (bool) $filters['is_cantrip'];
      $result = array_filter($result, fn($s) => !empty($s['is_cantrip']) === $ic);
    }
    if (isset($filters['spell_type'])) {
      $spell_type = strtolower((string) $filters['spell_type']);
      $result = array_filter($result, fn($s) => strtolower((string) ($s['spell_type'] ?? 'spell')) === $spell_type);
    }
    if (isset($filters['rarity'])) {
      $rar = strtolower($filters['rarity']);
      $result = array_filter($result, fn($s) => ($s['rarity'] ?? 'common') === $rar);
    }

    $deduped = [];
    foreach ($result as $spell) {
      if (!is_array($spell) || empty($spell['id'])) {
        continue;
      }
      $deduped[$this->normalizeSpellId((string) $spell['id'])] = $this->normalizeSpellRecord($spell);
    }

    return array_values($deduped);
  }

  /**
   * Register a spell into the in-memory catalog.
   */
  public function addSpell(array $spell_data): void {
    $id = $spell_data['id'] ?? NULL;
    if (!$id) {
      throw new \InvalidArgumentException('Spell data must have an "id" field.');
    }
    $normalized = $this->normalizeSpellRecord($spell_data);
    $this->spells[$normalized['id']] = $normalized;
    $this->spells[str_replace('-', '_', $normalized['id'])] = $normalized;
  }

  /**
   * Bulk-load spells from a JSON file.
   *
   * JSON format: array of spell objects, each matching the spell data model.
   */
  public function loadFromJson(string $file_path): int {
    if (!file_exists($file_path)) {
      throw new \RuntimeException("Spell JSON file not found: {$file_path}");
    }
    $raw  = file_get_contents($file_path);
    $data = json_decode($raw, TRUE);
    if (!is_array($data)) {
      throw new \RuntimeException("Invalid JSON in {$file_path}");
    }
    return $this->ingestSpellDataset($data);
  }

  /**
   * Normalize spell IDs between underscore and hyphen variants.
   */
  protected function normalizeSpellId(string $spell_id): string {
    return strtolower(str_replace('_', '-', trim($spell_id)));
  }

  /**
   * Load the bundled intermediary spell catalog when present.
   *
   * @deprecated Runtime spell-definition reads must come from the DB registry.
   *   This helper remains only for explicit compatibility utilities/tests.
   */
  protected function loadBundledCatalog(): void {
    $bundled_path = dirname(__DIR__, 2) . '/content/intermediary/core_rulebook_spells_intermediary.json';
    if (!is_file($bundled_path)) {
      return;
    }

    try {
      $this->loadFromJson($bundled_path);
    }
    catch (\Throwable) {
      // Keep the representative sample available if the full catalog can't load.
    }
  }

  /**
   * Overlay live registry-backed spell records into the compatibility cache.
   *
   * @deprecated Runtime spell-definition reads must come from the DB registry.
   *   This helper remains only for explicit compatibility utilities/tests.
   */
  protected function loadRegistryCatalog(): void {
    foreach ($this->fetchRegistrySpellRows() as $row) {
      $spell = $this->buildRegistrySpellRecord($row);
      if ($spell === NULL) {
        continue;
      }

      $normalized = $this->normalizeSpellRecord($spell);
      $this->spells[$normalized['id']] = $normalized;
      $this->spells[str_replace('-', '_', $normalized['id'])] = $normalized;
    }
  }

  /**
   * Ingest supported spell dataset shapes into the in-memory registry.
   */
  protected function ingestSpellDataset(array $data): int {
    $count = 0;

    if (isset($data['records']) && is_array($data['records'])) {
      foreach ($data['records'] as $record) {
        if (!is_array($record) || ($record['content_type'] ?? '') !== 'spell') {
          continue;
        }

        $spell = is_array($record['schema_data'] ?? NULL) ? $record['schema_data'] : [];
        if (empty($spell['id']) && !empty($record['content_id'])) {
          $spell['id'] = $record['content_id'];
        }
        if (empty($spell['name']) && !empty($record['name'])) {
          $spell['name'] = $record['name'];
        }
        if (!isset($spell['rarity']) && !empty($record['rarity'])) {
          $spell['rarity'] = $record['rarity'];
        }
        if ((!isset($spell['traits']) || !is_array($spell['traits'])) && !empty($record['tags']) && is_array($record['tags'])) {
          $spell['traits'] = $record['tags'];
        }

        if (!empty($spell['id'])) {
          $normalized = $this->normalizeSpellRecord($spell);
          $this->spells[$normalized['id']] = $normalized;
          $this->spells[str_replace('-', '_', $normalized['id'])] = $normalized;
          $count++;
        }
      }

      return $count;
    }

    foreach ($data as $spell) {
      if (!is_array($spell) || empty($spell['id'])) {
        continue;
      }

      $normalized = $this->normalizeSpellRecord($spell);
      $this->spells[$normalized['id']] = $normalized;
      $this->spells[str_replace('-', '_', $normalized['id'])] = $normalized;
      $count++;
    }

    return $count;
  }

  /**
   * Normalize spell records to the API shape expected by tooltip consumers.
   *
   * @param array<string, mixed> $spell_data
   *   Raw spell data.
   * @param string|null $requested_id
   *   Optional originally requested ID.
   *
   * @return array<string, mixed>
   *   Normalized spell data.
   */
  protected function normalizeSpellRecord(array $spell_data, ?string $requested_id = NULL): array {
    $normalized = $spell_data;
    $normalized_id = $this->normalizeSpellId((string) ($spell_data['id'] ?? $requested_id ?? ''));
    $rank = isset($spell_data['rank']) ? (int) $spell_data['rank'] : (int) ($spell_data['level'] ?? 0);
    $description = trim((string) ($spell_data['description'] ?? ''));

    $normalized['id'] = $normalized_id;
    $normalized['rank'] = $rank;
    $normalized['level'] = $rank;
    $normalized['is_cantrip'] = !empty($spell_data['is_cantrip']) || $rank === 0;
    $normalized['description_source'] = $this->hasCompleteSpellDescription($description)
      ? 'description'
      : (!empty($spell_data['description_snippet']) ? 'description_snippet' : 'fallback');

    if (!empty($spell_data['school'])) {
      $normalized['school'] = strtolower((string) $spell_data['school']);
    }
    if (!empty($spell_data['cast_actions'])) {
      $normalized['cast_actions'] = $this->normalizeCastActions((string) $spell_data['cast_actions']);
    }
    elseif (!empty($spell_data['cast'])) {
      $normalized['cast_actions'] = $this->normalizeCastActions((string) $spell_data['cast']);
    }
    if (!empty($normalized['save_type'])) {
      $normalized['save_type'] = self::normalizeSaveType((string) $normalized['save_type']);
    }
    elseif (!empty($spell_data['save'])) {
      $normalized['save_type'] = self::normalizeSaveType((string) $spell_data['save']);
    }
    if (!isset($normalized['traditions']) && isset($spell_data['traditions']) && is_array($spell_data['traditions'])) {
      $normalized['traditions'] = $spell_data['traditions'];
    }

    return $normalized;
  }

  /**
   * Appends degree-of-success outcomes when the narrative text omits them.
   */
  protected function appendSpellOutcomeSummary(string $description, array $spell_data, string $fallback_name = ''): string {
    $description = trim($description);
    if ($description === '' && $fallback_name !== '') {
      $description = $fallback_name;
    }

    $outcomes = $spell_data['effects']['outcomes'] ?? [];
    if (!is_array($outcomes) || $outcomes === []) {
      return $description;
    }

    $ordered_labels = ['Critical Success', 'Success', 'Failure', 'Critical Failure'];
    $summary_parts = [];
    foreach ($ordered_labels as $label) {
      $text = trim((string) ($outcomes[$label] ?? ''));
      if ($text !== '') {
        if (stripos($description, $label . ':') !== FALSE) {
          return $description;
        }
        $summary_parts[] = $label . ': ' . $text;
      }
      unset($outcomes[$label]);
    }
    foreach ($outcomes as $label => $text) {
      $label = trim((string) $label);
      $text = trim((string) $text);
      if ($label === '' || $text === '') {
        continue;
      }
      if (stripos($description, $label . ':') !== FALSE) {
        return $description;
      }
      $summary_parts[] = $label . ': ' . $text;
    }

    if ($summary_parts === []) {
      return $description;
    }

    return trim($description . ' ' . implode(' ', $summary_parts));
  }

  /**
   * Heuristically determines whether a stored description reads as complete text.
   */
  protected function hasCompleteSpellDescription(string $description): bool {
    $description = trim($description);
    if ($description === '') {
      return FALSE;
    }

    if (preg_match('/[.!?][\'")\]]?$/u', $description)) {
      return TRUE;
    }

    return mb_strlen($description) >= 160;
  }

  /**
   * Normalize raw save-type labels into canonical tokens.
   */
  public static function normalizeSaveType(?string $value): ?string {
    if ($value === NULL) {
      return NULL;
    }

    if (in_array(strtolower(trim($value)), ['na', 'none'], TRUE)) {
      return 'none';
    }

    $normalized = strtolower(trim($value));
    if ($normalized === '') {
      return NULL;
    }

    $normalized = str_replace("'", '', $normalized);
    $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
    $normalized = trim($normalized, '_');
    $normalized = str_replace('targets_choice', 'choice', $normalized);
    $normalized = str_replace('target_s_choice', 'choice', $normalized);

    return $normalized !== '' ? $normalized : NULL;
  }

  /**
   * Determines whether a save-type token is supported by the spell schema.
   */
  public static function isSupportedSaveType(?string $value): bool {
    if ($value === NULL || $value === '' || $value === 'NA' || $value === 'none') {
      return TRUE;
    }

    $normalized = self::normalizeSaveType($value);
    if ($normalized === NULL) {
      return TRUE;
    }

    if (in_array($normalized, self::SAVE_TYPES, TRUE)) {
      return TRUE;
    }

    return preg_match('/^(basic_)?(fortitude|reflex|will)_or_(fortitude|reflex|will)(?:_choice)?$/', $normalized) === 1;
  }

  /**
   * Convert legacy cast-time strings to the tooltip API format.
   */
  protected function normalizeCastActions(string $value): string {
    $normalized = strtolower(trim($value));
    return match ($normalized) {
      '1 action' => '1_action',
      '2 actions' => '2_actions',
      '3 actions' => '3_actions',
      'reaction' => 'reaction',
      'free action' => 'free_action',
      '1 minute' => 'one_minute',
      '10 minutes' => 'ten_minutes',
      '1 hour' => 'one_hour',
      default => str_replace(' ', '_', $normalized),
    };
  }

  /**
   * Fetch live spell rows from the content registry.
   *
   * @return array<int, object>
   *   Registry rows keyed numerically.
   */
  protected function fetchRegistrySpellRows(): array {
    if ($this->database === NULL) {
      throw new \RuntimeException('Spell registry database connection is unavailable.');
    }

    if (!$this->database->schema()->tableExists('dungeoncrawler_content_registry')) {
      throw new \RuntimeException('Spell registry table dungeoncrawler_content_registry is unavailable.');
    }

    return $this->database->select('dungeoncrawler_content_registry', 'r')
      ->fields('r', ['content_id', 'name', 'level', 'tags', 'schema_data'])
      ->condition('content_type', 'spell')
      ->condition('r.content_id', '%\_c', 'NOT LIKE')
      ->execute()
      ->fetchAll();
  }

  /**
   * Build a normalized spell record from a content registry row.
   *
   * @param object $row
   *   Registry row with content_id, name, level, tags, and schema_data.
   *
   * @return array<string, mixed>|null
   *   Spell record, or NULL when the row should be skipped.
   */
  protected function buildRegistrySpellRecord(object $row): ?array {
    $schema = json_decode((string) ($row->schema_data ?? ''), TRUE);
    if (!is_array($schema)) {
      return NULL;
    }

    $school = strtolower((string) ($schema['school'] ?? ''));
    if ($school === 'none') {
      $school = '';
    }
    if ($school !== '' && !in_array($school, self::SPELL_SCHOOLS, TRUE)) {
      return NULL;
    }

    $tags = json_decode((string) ($row->tags ?? '[]'), TRUE);
    $traditions = array_values(array_intersect(
      self::TRADITIONS,
      is_array($tags) ? $tags : []
    ));

    $spell = $schema;
    $spell['id'] = (string) ($schema['id'] ?? $row->content_id ?? '');
    $spell['name'] = (string) ($schema['name'] ?? $row->name ?? $spell['id']);
    $spell['rank'] = isset($schema['rank']) ? (int) $schema['rank'] : (int) ($row->level ?? 0);

    if (!isset($spell['traditions']) || !is_array($spell['traditions']) || $spell['traditions'] === []) {
      $spell['traditions'] = $traditions;
    }

    $description = trim((string) ($spell['description'] ?? ''));
    if (!$this->hasCompleteSpellDescription($description) && !empty($schema['description_snippet'])) {
      $spell['description'] = (string) $schema['description_snippet'];
    }
    $spell['description'] = $this->appendSpellOutcomeSummary(
      (string) ($spell['description'] ?? ''),
      $spell,
      (string) ($spell['name'] ?? $row->name ?? '')
    );

    return $spell['id'] !== '' ? $spell : NULL;
  }

  /**
   * Load and cache normalized spell records from the DB registry.
   *
   * @return array<string, array>
   *   Registry-backed spell catalog keyed by normalized spell ID variant.
   */
  protected function getRegistryCatalog(): array {
    if ($this->registryCatalog !== NULL) {
      return $this->registryCatalog;
    }

    $catalog = [];
    foreach ($this->fetchRegistrySpellRows() as $row) {
      $spell = $this->buildRegistrySpellRecord($row);
      if ($spell === NULL) {
        continue;
      }

      $normalized = $this->normalizeSpellRecord($spell);
      $catalog[$normalized['id']] = $normalized;
      $catalog[str_replace('-', '_', $normalized['id'])] = $normalized;
    }

    if ($catalog === []) {
      throw new \RuntimeException('Spell registry contains no spell records. Import canonical spells into dungeoncrawler_content_registry before using spell APIs.');
    }

    $this->registryCatalog = $catalog;
    return $this->registryCatalog;
  }

  // -----------------------------------------------------------------------
  // Cantrip auto-heightening
  // -----------------------------------------------------------------------

  /**
   * Compute a cantrip's effective rank.
   *
   * Rule: effective_rank = ceil(character_level / 2).
   * A 1st-level caster casts cantrips as 1st-rank; 5th-level → 3rd-rank; etc.
   *
   * @param int $character_level  1–20.
   *
   * @return int  Effective cantrip rank (1–10).
   */
  public function computeCantripEffectiveRank(int $character_level): int {
    $level = max(1, min(20, $character_level));
    return (int) ceil($level / 2);
  }

  /**
   * Compute a focus spell's effective rank.
   *
   * Same formula as cantrips: effective_rank = ceil(character_level / 2).
   *
   * @param int $character_level  1–20.
   *
   * @return int  Effective focus spell rank (1–10).
   */
  public function computeFocusSpellEffectiveRank(int $character_level): int {
    $level = max(1, min(20, $character_level));
    return (int) ceil($level / 2);
  }

  /**
   * Validate that a spell's cast time is legal in the current phase.
   *
   * Spells with Exploration-trait cast times (1 minute, 10 minutes, 1 hour)
   * cannot be cast during encounters (PF2e Core ch07).
   *
   * @param string $cast_time  One of self::CAST_ACTION_TYPES.
   * @param string $phase      Current game phase: 'encounter', 'exploration', 'downtime'.
   *
   * @return array{valid: bool, error: string|null}
   */
  public function validateCastTimeForPhase(string $cast_time, string $phase): array {
    if ($phase === 'encounter' && in_array($cast_time, self::EXPLORATION_CAST_TIMES, TRUE)) {
      return ['valid' => FALSE, 'error' => "Cast time '{$cast_time}' has the Exploration trait and cannot be used in encounters."];
    }
    return ['valid' => TRUE, 'error' => NULL];
  }

  // -----------------------------------------------------------------------
  // Heightening
  // -----------------------------------------------------------------------

  /**
   * Compute the heightened version of a spell cast at a given rank.
   *
   * Applies two types of heightened entries:
   *   - Specific: "Heightened (4th)" — applies exactly at that rank.
   *   - Cumulative: "Heightened (+2)" — stacks from base rank at each step.
   *
   * Returns the spell array with heightened fields merged into 'effect_text'
   * and a 'heightened_applied' flag describing which entries fired.
   *
   * @param array $spell        Base spell data.
   * @param int   $target_rank  The rank at which the spell is cast.
   *
   * @return array  Spell data with applied heightened effects noted.
   */
  public function computeHeightenedEffect(array $spell, int $target_rank): array {
    $base_rank = (int) ($spell['rank'] ?? 0);
    if ($target_rank <= $base_rank) {
      return array_merge($spell, ['heightened_applied' => [], 'cast_rank' => $target_rank]);
    }

    $heightened_entries = $spell['heightened_entries'] ?? [];
    $applied = [];

    // Phase 1 — specific-rank entries (e.g. "Heightened (4th): ...").
    foreach ($heightened_entries as $entry) {
      $type = $entry['type'] ?? '';
      if ($type === 'specific') {
        $at_rank = (int) ($entry['rank'] ?? 0);
        if ($at_rank <= $target_rank) {
          $applied[] = $entry;
          // Merge modified_fields into the spell (shallow override).
          if (!empty($entry['modified_fields'])) {
            $spell = array_merge($spell, $entry['modified_fields']);
          }
          if (!empty($entry['additional_text'])) {
            $spell['effect_text'] = ($spell['effect_text'] ?? '') . ' ' . $entry['additional_text'];
          }
        }
      }
    }

    // Phase 2 — cumulative entries (e.g. "Heightened (+2): ...").
    foreach ($heightened_entries as $entry) {
      $type = $entry['type'] ?? '';
      if ($type === 'cumulative') {
        $step = (int) ($entry['rank_delta'] ?? 2);
        if ($step < 1) {
          continue;
        }
        $steps_fired = (int) floor(($target_rank - $base_rank) / $step);
        for ($i = 1; $i <= $steps_fired; $i++) {
          $applied[] = array_merge($entry, ['step_index' => $i]);
          if (!empty($entry['additional_text'])) {
            $spell['effect_text'] = ($spell['effect_text'] ?? '') . ' ' . $entry['additional_text'];
          }
        }
      }
    }

    return array_merge($spell, ['heightened_applied' => $applied, 'cast_rank' => $target_rank]);
  }

  // -----------------------------------------------------------------------
  // Spontaneous / signature spells
  // -----------------------------------------------------------------------

  /**
   * Check whether a spontaneous caster may heighten a given spell to target_rank.
   *
   * Rules:
   *   - Prepared casters may always heighten (into a higher slot).
   *   - Spontaneous casters may heighten ONLY if:
   *       (a) They have the spell in their repertoire at target_rank, OR
   *       (b) The spell is a signature spell (can be heightened to any rank the
   *           caster has slots for, even if not individually known at that rank).
   *
   * @param array  $char_state   Character state (includes casting_type, repertoire, signature_spells).
   * @param string $spell_id     Spell being heightened.
   * @param int    $target_rank  Rank to heighten to.
   *
   * @return array{can_heighten: bool, reason: string}
   */
  public function canHeightenSpontaneous(array $char_state, string $spell_id, int $target_rank): array {
    $casting_type = $char_state['casting_type'] ?? ($char_state['stats']['casting_type'] ?? 'spontaneous');
    if ($casting_type !== 'spontaneous') {
      return ['can_heighten' => TRUE, 'reason' => 'Prepared casters may always heighten into a higher slot.'];
    }

    // Check signature spells.
    $signature_spells = $char_state['signature_spells'] ?? ($char_state['state']['signature_spells'] ?? []);
    if (in_array($spell_id, $signature_spells, TRUE)) {
      return ['can_heighten' => TRUE, 'reason' => 'Signature spell may be spontaneously heightened to any available rank.'];
    }

    // Check spell repertoire at target_rank.
    $repertoire = $char_state['spell_repertoire'] ?? ($char_state['state']['spell_repertoire'] ?? []);
    $key = (string) $target_rank;
    $spells_at_rank = $repertoire[$key] ?? [];
    if (in_array($spell_id, $spells_at_rank, TRUE)) {
      return ['can_heighten' => TRUE, 'reason' => 'Spell is known at the target rank.'];
    }

    return [
      'can_heighten' => FALSE,
      'reason' => "Spontaneous casters cannot heighten '{$spell_id}' to rank {$target_rank} without knowing it at that rank (or making it a signature spell).",
    ];
  }

  // -----------------------------------------------------------------------
  // Innate spells
  // -----------------------------------------------------------------------

  /**
   * Check whether an innate non-cantrip spell can be used today.
   *
   * Innate non-cantrips: once per day; refresh at daily prep.
   * Innate cantrips: unlimited use (always returns TRUE).
   *
   * @param array  $entity_state  Character/entity state array.
   * @param string $spell_id      Spell being checked.
   *
   * @return array{can_use: bool, reason: string}
   */
  public function validateInnateSpellUse(array $entity_state, string $spell_id): array {
    $innate_spells = $entity_state['innate_spells'] ?? [];
    $spell_def = $innate_spells[$spell_id] ?? NULL;
    if (!$spell_def) {
      return ['can_use' => FALSE, 'reason' => "No innate spell '{$spell_id}' on this character."];
    }
    $is_cantrip = !empty($spell_def['is_cantrip']);
    if ($is_cantrip) {
      return ['can_use' => TRUE, 'reason' => 'Innate cantrips are unlimited.'];
    }
    $used = (bool) ($spell_def['used_today'] ?? FALSE);
    if ($used) {
      return ['can_use' => FALSE, 'reason' => "Innate spell '{$spell_id}' already used today; refreshes at daily preparation."];
    }
    return ['can_use' => TRUE, 'reason' => ''];
  }

  /**
   * Mark an innate spell as used today.
   *
   * @param array  $entity_state  Modified by reference.
   * @param string $spell_id      Spell to mark used.
   */
  public function markInnateSpellUsed(array &$entity_state, string $spell_id): void {
    if (isset($entity_state['innate_spells'][$spell_id])) {
      $entity_state['innate_spells'][$spell_id]['used_today'] = TRUE;
    }
  }

  /**
   * Reset all innate spell daily-use flags (call at daily preparation).
   *
   * @param array $entity_state  Modified by reference.
   */
  public function resetInnateSpells(array &$entity_state): void {
    // Avoid ?? [] in the foreach expression — using a null-coalescing expression
    // as the iterable causes PHP to iterate a copy, so by-reference writes
    // inside the loop would not propagate back to $entity_state.
    if (empty($entity_state['innate_spells'])) {
      return;
    }
    foreach ($entity_state['innate_spells'] as $spell_id => &$def) {
      if (empty($def['is_cantrip'])) {
        $def['used_today'] = FALSE;
      }
    }
    unset($def);
  }

  // -----------------------------------------------------------------------
  // Focus Pool
  // -----------------------------------------------------------------------

  /**
   * Compute the actual focus pool size for a character (capped at FOCUS_POOL_MAX).
   *
   * Each focus ability adds 1 to the pool; the hard cap is 3.
   *
   * @param array $char_state  Includes 'focus_sources' count or 'focus_pool_size'.
   *
   * @return int  Clamped pool size (1–3).
   */
  public function computeFocusPoolSize(array $char_state): int {
    // Accept either an explicit size or a count of sources.
    $raw = (int) ($char_state['focus_pool_size']
      ?? $char_state['stats']['focus_pool_size']
      ?? count($char_state['focus_sources'] ?? []));
    return max(0, min(self::FOCUS_POOL_MAX, $raw));
  }

  // -----------------------------------------------------------------------
  // Spell data model helpers
  // -----------------------------------------------------------------------

  /**
   * Validate a spell data structure against the expected model.
   *
   * Returns an array of error strings (empty = valid).
   */
  public function validateSpellData(array $spell): array {
    $errors = [];

    if (empty($spell['id'])) {
      $errors[] = 'Missing required field: id';
    }
    if (empty($spell['name'])) {
      $errors[] = 'Missing required field: name';
    }
    if (!isset($spell['rank']) || !is_int($spell['rank']) || $spell['rank'] < 0 || $spell['rank'] > 10) {
      $errors[] = 'Field rank must be an integer 0–10';
    }
    foreach ($spell['traditions'] ?? [] as $t) {
      if ($t === 'none') {
        continue;
      }
      if (!in_array($t, self::TRADITIONS, TRUE)) {
        $errors[] = "Invalid tradition: {$t}";
      }
    }
    if (isset($spell['school']) && $spell['school'] !== 'none' && !in_array($spell['school'], self::SPELL_SCHOOLS, TRUE)) {
      $errors[] = "Invalid school: {$spell['school']}";
    }
    foreach ($spell['components'] ?? [] as $c) {
      if ($c === 'none') {
        continue;
      }
      if (!in_array($c, self::SPELL_COMPONENTS, TRUE)) {
        $errors[] = "Invalid component: {$c}";
      }
    }
    if (isset($spell['save_type']) && !self::isSupportedSaveType((string) $spell['save_type'])) {
      $errors[] = "Invalid save_type: {$spell['save_type']}";
    }
    if (isset($spell['rarity']) && !in_array($spell['rarity'], self::RARITY_LEVELS, TRUE)) {
      $errors[] = "Invalid rarity: {$spell['rarity']}";
    }

    return $errors;
  }

  // -----------------------------------------------------------------------
  // Representative sample seed (used in dev/test; production uses loadFromJson)
  // -----------------------------------------------------------------------

  protected function seedRepresentativeSample(): void {
    $samples = [
      [
        'id'          => 'acid-splash',
        'name'        => 'Acid Splash',
        'rank'        => 0,
        'is_cantrip'  => TRUE,
        'school'      => 'evocation',
        'traditions'  => ['arcane', 'primal'],
        'rarity'      => 'common',
        'cast_actions'=> '2_actions',
        'components'  => ['somatic', 'verbal'],
        'range'       => 30,
        'area'        => NULL,
        'targets'     => '1 creature',
        'save_type'   => 'basic_reflex',
        'duration'    => 'instantaneous',
        'effect_text' => 'You splash acid dealing 1d6 acid damage and 1 persistent acid damage on a failure.',
        'heightened_entries' => [
          ['type' => 'cumulative', 'rank_delta' => 2, 'additional_text' => 'The initial damage increases by 1d6 and the persistent damage increases by 1.'],
        ],
      ],
      [
        'id'          => 'shield',
        'name'        => 'Shield',
        'rank'        => 0,
        'is_cantrip'  => TRUE,
        'school'      => 'abjuration',
        'traditions'  => ['arcane', 'divine', 'occult'],
        'rarity'      => 'common',
        'cast_actions'=> '1_action',
        'components'  => ['verbal'],
        'range'       => NULL,
        'area'        => NULL,
        'targets'     => 'self',
        'save_type'   => NULL,
        'duration'    => 'until_start_of_next_turn',
        'effect_text' => 'You conjure a magical shield granting +1 AC. You can use Shield Block as a reaction.',
        'heightened_entries' => [],
      ],
      [
        'id'          => 'magic-missile',
        'name'        => 'Magic Missile',
        'rank'        => 1,
        'is_cantrip'  => FALSE,
        'school'      => 'evocation',
        'traditions'  => ['arcane', 'occult'],
        'rarity'      => 'common',
        'cast_actions'=> '1_action',
        'components'  => ['somatic', 'verbal'],
        'range'       => 120,
        'area'        => NULL,
        'targets'     => '1 creature',
        'save_type'   => NULL,
        'duration'    => 'instantaneous',
        'effect_text' => 'You send a dart of magical force dealing 1d4+1 force damage that always hits.',
        'heightened_entries' => [
          ['type' => 'specific', 'rank' => 3, 'additional_text' => 'You can fire 2 missiles.'],
          ['type' => 'specific', 'rank' => 5, 'additional_text' => 'You can fire 3 missiles.'],
          ['type' => 'specific', 'rank' => 7, 'additional_text' => 'You can fire 4 missiles.'],
          ['type' => 'specific', 'rank' => 9, 'additional_text' => 'You can fire 5 missiles.'],
        ],
      ],
      [
        'id'          => 'fireball',
        'name'        => 'Fireball',
        'rank'        => 3,
        'is_cantrip'  => FALSE,
        'school'      => 'evocation',
        'traditions'  => ['arcane', 'primal'],
        'rarity'      => 'common',
        'cast_actions'=> '2_actions',
        'components'  => ['somatic', 'verbal'],
        'range'       => 500,
        'area'        => '20-foot burst',
        'targets'     => NULL,
        'save_type'   => 'basic_reflex',
        'duration'    => 'instantaneous',
        'effect_text' => 'A burst of fire deals 6d6 fire damage.',
        'heightened_entries' => [
          ['type' => 'cumulative', 'rank_delta' => 1, 'additional_text' => 'The damage increases by 2d6.'],
        ],
      ],
      [
        'id'          => 'heal',
        'name'        => 'Heal',
        'rank'        => 1,
        'is_cantrip'  => FALSE,
        'school'      => 'necromancy',
        'traditions'  => ['divine', 'primal'],
        'rarity'      => 'common',
        'cast_actions'=> '1_action',
        'components'  => ['verbal'],
        'range'       => 30,
        'area'        => NULL,
        'targets'     => '1 living creature',
        'save_type'   => NULL,
        'duration'    => 'instantaneous',
        'effect_text' => 'Positive energy heals the target for 1d8 HP.',
        'heightened_entries' => [
          ['type' => 'cumulative', 'rank_delta' => 1, 'additional_text' => 'The amount healed increases by 1d8.'],
        ],
      ],
      [
        'id'          => 'invisibility',
        'name'        => 'Invisibility',
        'rank'        => 2,
        'is_cantrip'  => FALSE,
        'school'      => 'illusion',
        'traditions'  => ['arcane', 'occult'],
        'rarity'      => 'common',
        'cast_actions'=> '2_actions',
        'components'  => ['material', 'somatic'],
        'range'       => 'touch',
        'area'        => NULL,
        'targets'     => '1 creature',
        'save_type'   => NULL,
        'duration'    => '10 minutes',
        'effect_text' => 'The target becomes invisible. If it attacks or casts a spell, it becomes visible until the start of its next turn.',
        'heightened_entries' => [
          ['type' => 'specific', 'rank' => 4, 'additional_text' => 'Duration increases to 1 minute and the target stays invisible even when attacking.'],
        ],
      ],
      [
        'id'          => 'detect-magic',
        'name'        => 'Detect Magic',
        'rank'        => 0,
        'is_cantrip'  => TRUE,
        'school'      => 'divination',
        'traditions'  => ['arcane', 'divine', 'occult', 'primal'],
        'rarity'      => 'common',
        'cast_actions'=> '2_actions',
        'components'  => ['somatic', 'verbal'],
        'range'       => NULL,
        'area'        => '30-foot emanation',
        'targets'     => NULL,
        'save_type'   => NULL,
        'duration'    => 'instantaneous',
        'effect_text' => 'You detect magical auras. Each aura above 0 in the area reveals its school of magic.',
        'heightened_entries' => [],
      ],
      [
        'id'          => 'command',
        'name'        => 'Command',
        'rank'        => 1,
        'is_cantrip'  => FALSE,
        'school'      => 'enchantment',
        'traditions'  => ['arcane', 'divine', 'occult'],
        'rarity'      => 'common',
        'cast_actions'=> '2_actions',
        'components'  => ['somatic', 'verbal'],
        'range'       => '30 feet',
        'area'        => NULL,
        'targets'     => '1 creature',
        'save_type'   => 'will',
        'duration'    => 'until the end of the target\'s next turn',
        'effect_text' => 'You issue a brief magical order that the target is compelled to follow on a failed Will save.',
        'heightened_entries' => [],
      ],
      [
        'id'          => 'pest-form',
        'name'        => 'Pest Form',
        'rank'        => 1,
        'is_cantrip'  => FALSE,
        'school'      => 'transmutation',
        'traditions'  => ['arcane', 'primal'],
        'rarity'      => 'common',
        'cast_actions'=> '2_actions',
        'components'  => ['somatic', 'verbal'],
        'range'       => NULL,
        'area'        => NULL,
        'targets'     => 'self',
        'save_type'   => NULL,
        'duration'    => '10 minutes',
        'effect_text' => 'You transform into a Tiny harmless animal suitable for scouting and slipping through tight spaces.',
        'heightened_entries' => [],
      ],
      [
        'id'          => 'thoughtful-gift',
        'name'        => 'Thoughtful Gift',
        'rank'        => 2,
        'is_cantrip'  => FALSE,
        'school'      => 'conjuration',
        'traditions'  => ['arcane', 'occult'],
        'rarity'      => 'common',
        'cast_actions'=> '2_actions',
        'components'  => ['somatic', 'verbal'],
        'range'       => '30 feet',
        'area'        => NULL,
        'targets'     => '1 willing creature',
        'save_type'   => NULL,
        'duration'    => 'instantaneous',
        'effect_text' => 'You teleport a small unattended object you are holding into the grasp of a willing nearby creature.',
        'heightened_entries' => [],
      ],
    ];

    foreach ($samples as $spell) {
      $this->spells[$spell['id']] = $spell;
    }
  }

}
