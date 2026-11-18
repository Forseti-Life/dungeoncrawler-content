<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * The canonical institution library.
 *
 * One record per institution, independent of any campaign, in
 * dc_library_institution_manifest. Everything that names an institution
 * registers it here and everything that looks one up reads here: storyline
 * authoring, runtime faction generation, and the five alignment
 * institutions. A campaign's subject row points back at its record through
 * source_asset_type "library_faction" and the record's slug.
 */
class InstitutionLibraryService {

  public const TABLE = 'dc_library_institution_manifest';
  public const SOURCE_TABLE = 'generated_faction';
  public const SOURCE_FILE = '__generated__/factions';
  public const ROW_TYPE = 'institution';
  public const CLASSIFICATION = 'canonical_faction';
  public const STATUS_NORMALIZED = 'normalized';
  public const CAMPAIGN_SOURCE_ASSET_TYPE = 'library_faction';

  public function __construct(
    protected readonly Connection $database,
  ) {}

  /**
   * The record with this slug, or NULL.
   *
   * @return array<string, mixed>|null
   *   The manifest row.
   */
  public function loadRow(string $slug): ?array {
    if ($slug === '') {
      return NULL;
    }
    $row = $this->database->select(self::TABLE, 'm')
      ->fields('m')
      ->condition('source_table', self::SOURCE_TABLE)
      ->condition('source_file', self::SOURCE_FILE)
      ->condition('source_asset_id', $slug)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return is_array($row) ? $row : NULL;
  }

  /**
   * Writes one record, keyed by its slug.
   *
   * @param array<string, mixed> $record
   *   canonicalLabel, canonicalSlug, domain and librarySubjectId are
   *   required; summary, parentSubjectId, requestContext and the rest of the
   *   canonical faction shape are kept as given.
   *
   * @return int
   *   The manifest row ID.
   */
  public function upsert(array $record, string $status = self::STATUS_NORMALIZED): int {
    foreach (['canonicalLabel', 'canonicalSlug', 'domain', 'librarySubjectId'] as $field) {
      if (trim((string) ($record[$field] ?? '')) === '') {
        throw new \InvalidArgumentException('institution_library_record_incomplete:' . $field);
      }
    }
    $now = time();
    $fields = [
      'source_table' => self::SOURCE_TABLE,
      'source_file' => self::SOURCE_FILE,
      'source_asset_id' => (string) $record['canonicalSlug'],
      'library_character_id' => NULL,
      'row_type' => self::ROW_TYPE,
      'classification' => self::CLASSIFICATION,
      'status' => $status,
      'normalized_payload_json' => json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
      'review_reasons_json' => json_encode([], JSON_THROW_ON_ERROR),
      'provenance_json' => json_encode($record['requestContext'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
      'changed' => $now,
    ];
    // The nesting order holds for every writer. A parent registered later
    // in the same save is checked when it arrives, by its children's rows.
    $parent = trim((string) ($record['parentSubjectId'] ?? ''));
    $parent_record = $parent === '' ? NULL : $this->loadByReference($parent);
    if ($parent_record !== NULL && !InstitutionNormalizationService::nestingAllowed((string) $record['domain'], (string) $parent_record['domain'])) {
      throw new \InvalidArgumentException(sprintf(
        'institution_library_parent_type: a %s institution cannot sit inside a %s institution ("%s" inside "%s").',
        (string) $record['domain'], (string) $parent_record['domain'], (string) $record['canonicalLabel'], (string) $parent_record['canonicalLabel'],
      ));
    }
    $existing = $this->loadRow((string) $record['canonicalSlug']);
    if ($existing !== NULL) {
      $this->assertSameInstitution($existing, (string) $record['librarySubjectId'], (string) $record['canonicalLabel'], (string) $record['domain']);
      $this->database->update(self::TABLE)->fields($fields)->condition('id', (int) $existing['id'])->execute();
      return (int) $existing['id'];
    }
    $fields['created'] = $now;
    return (int) $this->database->insert(self::TABLE)->fields($fields)->execute();
  }

  /**
   * Registers the institutions a storyline draft defines.
   *
   * A record belongs to the storyline that first defined it: that storyline's
   * later saves update it, and removing or renaming the institution there
   * retires the record unless another storyline uses it. Any other storyline
   * reuses the record as it stands.
   *
   * @param array<int, array<string, mixed>> $institutions
   *   The draft's objects.institutions rows.
   * @param array<int, array<string, mixed>> $previous
   *   The same rows before this save.
   */
  public function registerStorylineInstitutions(string $draft_id, string $storyline_title, array $institutions, array $previous): void {
    $normalizer = new InstitutionNormalizationService();
    $kept = [];
    foreach ($institutions as $institution) {
      $normalized = $normalizer->normalizeInstitutionInput([
        'domain' => (string) ($institution['domain'] ?? ''),
        'display_name' => (string) ($institution['name'] ?? ''),
      ]);
      $slug = (string) $normalized['normalized_label'];
      $kept[$slug] = TRUE;
      $existing = $this->loadRow($slug);
      $payload = $existing === NULL ? [] : json_decode((string) $existing['normalized_payload_json'], TRUE, 512, JSON_THROW_ON_ERROR);
      if ($existing !== NULL) {
        $this->assertSameInstitution($existing, (string) $normalized['subject_id'], (string) $normalized['display_name'], (string) $normalized['domain']);
      }
      if ($existing !== NULL && (string) ($payload['requestContext']['defined_by_draft_id'] ?? '') !== $draft_id) {
        continue;
      }
      $this->upsert([
        'canonicalLabel' => (string) $normalized['display_name'],
        'canonicalSlug' => $slug,
        'domain' => (string) $normalized['domain'],
        'librarySubjectId' => (string) $normalized['subject_id'],
        'parentSubjectId' => trim((string) ($institution['parent_institution_id'] ?? '')),
        // Purpose, seat, leader and stances belong to the story, not the
        // library record.
        'summary' => trim((string) ($institution['description'] ?? '')),
        'requestContext' => [
          'request_source' => 'storyline_authoring',
          'defined_by_draft_id' => $draft_id,
          'defined_by_storyline' => $storyline_title,
        ],
      ]);
    }

    foreach ($previous as $institution) {
      $slug = (string) $normalizer->normalizeInstitutionInput([
        'domain' => (string) ($institution['domain'] ?? ''),
        'display_name' => (string) ($institution['name'] ?? ''),
      ])['normalized_label'];
      $existing = isset($kept[$slug]) ? NULL : $this->loadRow($slug);
      if ($existing === NULL) {
        continue;
      }
      $payload = json_decode((string) $existing['normalized_payload_json'], TRUE, 512, JSON_THROW_ON_ERROR);
      if ((string) ($payload['requestContext']['defined_by_draft_id'] ?? '') === $draft_id
        && !$this->usedByAnotherStoryline((string) $payload['librarySubjectId'], $draft_id)) {
        $this->database->delete(self::TABLE)->condition('id', (int) $existing['id'])->execute();
      }
    }
  }

  /**
   * Registers the institutions the rules define: alignments, ancestries, classes.
   */
  public function registerSystemInstitutions(): void {
    $names = [
      'alignment' => array_keys(InstitutionNormalizationService::ALIGNMENT_INSTITUTIONS),
      'ancestry' => [...array_keys(CharacterRulesCatalog::ANCESTRIES), 'Undead'],
      'profession' => array_column(CharacterRulesCatalog::CLASSES, 'name'),
    ];
    $summaries = [
      'alignment' => 'Everyone whose alignment is %s. Membership follows from the actor\'s own alignment and is never authored.',
      'ancestry' => 'Everyone of the %s ancestry. Membership follows from the actor\'s own sheet and is never authored.',
      'profession' => 'Everyone whose class or calling is %s. Membership follows from the actor\'s own sheet and is never authored.',
    ];
    foreach ($names as $domain => $domain_names) {
      foreach ($domain_names as $name) {
        $this->ensureRecord($domain, (string) $name, 'system_' . $domain, sprintf($summaries[$domain], $name));
      }
    }
  }

  /**
   * Makes sure an institution has a library record; an existing one is kept.
   *
   * @return string
   *   The record's slug.
   */
  public function ensureRecord(string $domain, string $display_name, string $request_source, string $summary = ''): string {
    $normalized = (new InstitutionNormalizationService())->normalizeInstitutionInput(['domain' => $domain, 'display_name' => $display_name]);
    $slug = (string) $normalized['normalized_label'];
    $existing = $this->loadRow($slug);
    if ($existing !== NULL) {
      $this->assertSameInstitution($existing, (string) $normalized['subject_id'], (string) $normalized['display_name'], (string) $normalized['domain']);
      return $slug;
    }
    $this->upsert([
      'canonicalLabel' => (string) $normalized['display_name'],
      'canonicalSlug' => $slug,
      'domain' => (string) $normalized['domain'],
      'librarySubjectId' => (string) $normalized['subject_id'],
      'parentSubjectId' => '',
      'summary' => $summary,
      'requestContext' => ['request_source' => $request_source],
    ]);
    return $slug;
  }

  /**
   * The record a storyline reference names, by slug or by institution ID.
   *
   * @return array<string, mixed>|null
   *   The record payload.
   */
  public function loadByReference(string $reference): ?array {
    $reference = trim($reference);
    $row = $this->loadRow($reference);
    if ($row === NULL && preg_match('/^institution_[a-z]+_(.+)$/', $reference, $matches) === 1) {
      $row = $this->loadRow($matches[1]);
    }
    if ($row === NULL) {
      return NULL;
    }
    $payload = json_decode((string) $row['normalized_payload_json'], TRUE, 512, JSON_THROW_ON_ERROR);
    return $reference === (string) $row['source_asset_id'] || $reference === (string) $payload['librarySubjectId'] ? $payload : NULL;
  }

  /**
   * A record and every record it sits inside, nearest first.
   *
   * The library is the authority for the hierarchy: a campaign builds its
   * parent links from here.
   *
   * @return array<int, array<string, mixed>>
   *   Record payloads, the named institution first.
   */
  public function chain(string $reference): array {
    $chain = [];
    $seen = [];
    $record = $this->loadByReference($reference);
    while ($record !== NULL && !isset($seen[(string) $record['librarySubjectId']])) {
      $seen[(string) $record['librarySubjectId']] = TRUE;
      $chain[] = $record;
      $parent = trim((string) ($record['parentSubjectId'] ?? ''));
      $record = $parent === '' ? NULL : $this->loadByReference($parent);
    }
    return $chain;
  }

  /**
   * The institutions a character belongs to through its own record.
   *
   * A library character holds its memberships in
   * character_data.institution_refs, the field play reads when the
   * character enters a campaign. Membership is inherited upward.
   *
   * @return array<string, array{institution_id: string, domain: string, name: string, standing: string, through: string}>
   *   By institution ID; `through` names the part an inherited one comes from.
   */
  public function characterMemberships(array $character_data): array {
    $memberships = [];
    foreach (array_filter((array) ($character_data[InstitutionMembershipService::INSTITUTION_REFS_FIELD] ?? []), 'is_array') as $ref) {
      $chain = $this->chain((string) ($ref['source_asset_id'] ?? ''));
      if ($chain === []) {
        throw new \RuntimeException(sprintf('A character is a member of "%s", which is not in the institution library.', (string) ($ref['display_name'] ?? $ref['source_asset_id'] ?? '')));
      }
      foreach ($chain as $position => $record) {
        $id = (string) $record['librarySubjectId'];
        if ($position === 0 || !isset($memberships[$id])) {
          $memberships[$id] = [
            'institution_id' => $id,
            'domain' => (string) $record['domain'],
            'name' => (string) $record['canonicalLabel'],
            'standing' => $position === 0 ? (string) ($ref['standing'] ?? 'member') : 'member',
            'through' => $position === 0 ? '' : (string) $chain[0]['canonicalLabel'],
          ];
        }
      }
    }
    return $memberships;
  }

  /**
   * Adds and removes memberships on a library character's own record.
   *
   * @param array<string, string> $add
   *   Standing (member or leader) by institution ID or slug.
   * @param string[] $remove
   *   Institution IDs or slugs the character no longer belongs to.
   *
   * @return bool
   *   Whether the record changed.
   */
  public function updateCharacterMemberships(int $character_row_id, array $add, array $remove): bool {
    $row = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'character_data', 'version'])
      ->condition('id', $character_row_id)
      ->condition('campaign_id', 0)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      throw new \RuntimeException(sprintf('Library character %d does not exist; memberships are stored on library characters.', $character_row_id));
    }
    $data = json_decode((string) $row['character_data'], TRUE, 512, JSON_THROW_ON_ERROR);
    $refs = [];
    foreach (array_filter((array) ($data[InstitutionMembershipService::INSTITUTION_REFS_FIELD] ?? []), 'is_array') as $ref) {
      $refs[(string) $ref['source_asset_id']] = $ref;
    }
    $before = $refs;
    foreach ($remove as $reference) {
      $record = $this->loadByReference((string) $reference);
      if ($record !== NULL) {
        unset($refs[(string) $record['canonicalSlug']]);
      }
    }
    foreach ($add as $reference => $standing) {
      $record = $this->loadByReference((string) $reference);
      if ($record === NULL) {
        throw new \RuntimeException(sprintf('Institution "%s" is not in the institution library.', (string) $reference));
      }
      if (isset(InstitutionNormalizationService::DERIVED_DOMAINS[(string) $record['domain']])) {
        throw new \InvalidArgumentException(sprintf('"%s" follows from the character sheet and cannot be given as a membership.', (string) $record['canonicalLabel']));
      }
      $refs[(string) $record['canonicalSlug']] = [
        'domain' => (string) $record['domain'],
        'display_name' => (string) $record['canonicalLabel'],
        'source_asset_type' => self::CAMPAIGN_SOURCE_ASSET_TYPE,
        'source_asset_id' => (string) $record['canonicalSlug'],
      ] + ($standing === 'leader' ? ['standing' => 'leader'] : []);
    }
    ksort($refs);
    ksort($before);
    if ($refs === $before) {
      return FALSE;
    }
    $data[InstitutionMembershipService::INSTITUTION_REFS_FIELD] = array_values($refs);
    $this->database->update('dc_campaign_characters')
      ->fields([
        // Memberships are not part of the character sheet: its revision,
        // which storyline casts pin, does not move.
        'character_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        'changed' => time(),
      ])
      ->condition('id', $character_row_id)
      ->execute();
    return TRUE;
  }

  /**
   * What every social actor has: the critical ties, by key and label.
   */
  public const CRITICAL_TIES = [
    'ancestry' => 'an ancestry',
    'class' => 'a class',
    'alignment' => 'an alignment',
    'home' => 'a home: a kin group or a place',
  ];

  /**
   * Whether the critical ties apply: a person, not a creature stat block.
   */
  public static function isSocialActor(array $sheet): bool {
    return !in_array(strtolower(trim((string) ($sheet['class'] ?? ''))), ['', 'npc', 'creature'], TRUE);
  }

  /**
   * The critical ties an actor lacks.
   *
   * @param array<string, mixed> $sheet
   *   ancestry, class and alignment as the actor's sheet states them.
   * @param string[] $institution_domains
   *   The type of every institution the actor belongs to, inherited included.
   *
   * @return string[]
   *   Keys of CRITICAL_TIES.
   */
  public static function criticalTieGaps(array $sheet, array $institution_domains): array {
    $gaps = [];
    foreach (['ancestry', 'class', 'alignment'] as $field) {
      if (trim((string) ($sheet[$field] ?? '')) === '') {
        $gaps[] = $field;
      }
    }
    if (array_intersect(['kin', 'place'], $institution_domains) === []) {
      $gaps[] = 'home';
    }
    return $gaps;
  }

  /**
   * One name is one institution: refuses the same name in a second domain.
   */
  protected function assertSameInstitution(array $existing, string $subject_id, string $display_name, string $domain): void {
    $payload = json_decode((string) $existing['normalized_payload_json'], TRUE, 512, JSON_THROW_ON_ERROR);
    if ((string) ($payload['librarySubjectId'] ?? '') !== $subject_id) {
      throw new \InvalidArgumentException(sprintf(
        'institution_library_name_taken: the library already holds "%s" as a %s institution; "%s" cannot also be a %s institution. Reuse the existing one or choose another name.',
        (string) ($payload['canonicalLabel'] ?? $existing['source_asset_id']),
        (string) ($payload['domain'] ?? ''),
        $display_name,
        $domain,
      ));
    }
  }

  /**
   * Searches the library.
   *
   * @param string $search
   *   Words that must all appear in the name, kind or description.
   * @param string $domain
   *   One institution domain, or '' for all.
   *
   * @return array{total: int, institutions: array<int, array<string, mixed>>}
   */
  public function find(string $search = '', string $domain = '', int $limit = 25): array {
    $kinds = InstitutionNormalizationService::AUTHORABLE_DOMAINS + InstitutionNormalizationService::DERIVED_DOMAINS;
    if ($domain !== '' && !isset($kinds[$domain])) {
      throw new \InvalidArgumentException('institution_domain_unsupported:' . $domain);
    }
    if ($limit < 1 || $limit > 50) {
      throw new \InvalidArgumentException('institution_library_limit_invalid');
    }
    $rows = $this->database->select(self::TABLE, 'm')
      ->fields('m', ['normalized_payload_json'])
      ->condition('source_table', self::SOURCE_TABLE)
      ->condition('source_file', self::SOURCE_FILE)
      ->condition('row_type', self::ROW_TYPE)
      ->condition('status', self::STATUS_NORMALIZED)
      ->execute()
      ->fetchCol();
    $terms = array_values(array_filter(preg_split('/\s+/', mb_strtolower(trim($search))) ?: []));
    $matches = [];
    foreach ($rows as $json) {
      $payload = json_decode((string) $json, TRUE, 512, JSON_THROW_ON_ERROR);
      $row_domain = (string) $payload['domain'];
      if ($domain !== '' && $row_domain !== $domain) {
        continue;
      }
      $kind = $kinds[$row_domain] ?? $row_domain;
      $haystack = mb_strtolower($payload['canonicalLabel'] . ' ' . $kind . ' ' . $row_domain . ' ' . ($payload['summary'] ?? ''));
      foreach ($terms as $term) {
        if (!str_contains($haystack, $term)) {
          continue 2;
        }
      }
      $derived = isset(InstitutionNormalizationService::DERIVED_DOMAINS[$row_domain]);
      $matches[] = [
        'institution_id' => (string) $payload['librarySubjectId'],
        'domain' => $row_domain,
        'kind' => $kind,
        'name' => (string) $payload['canonicalLabel'],
        'description' => mb_substr((string) ($payload['summary'] ?? ''), 0, 300),
        'parent_institution_id' => (string) ($payload['parentSubjectId'] ?? ''),
        'defined_by' => (string) ($payload['requestContext']['defined_by_storyline'] ?? $payload['requestContext']['request_source'] ?? ''),
        // A derived institution is never added to a storyline: an actor
        // belongs to it through its own sheet.
        'membership' => $derived ? 'follows the actor\'s ' . $row_domain : 'authored',
      ];
    }
    // Institutions a storyline can use come before the ones a sheet implies.
    usort($matches, static fn(array $a, array $b): int => [$a['membership'] !== 'authored', $a['name'], $a['domain']] <=> [$b['membership'] !== 'authored', $b['name'], $b['domain']]);
    return ['total' => count($matches), 'institutions' => array_slice($matches, 0, $limit)];
  }

  /**
   * Whether a storyline other than this draft names the institution.
   */
  protected function usedByAnotherStoryline(string $institution_id, string $draft_id): bool {
    $needle = '%' . $this->database->escapeLike(json_encode($institution_id, JSON_THROW_ON_ERROR)) . '%';
    $in_draft = $this->database->query(
      'SELECT 1 FROM {dungeoncrawler_content_storyline_authoring_drafts} WHERE draft_id <> :draft AND draft_payload LIKE :needle LIMIT 1',
      [':draft' => $draft_id, ':needle' => $needle],
    )->fetchField();
    if ($in_draft) {
      return TRUE;
    }
    return (bool) $this->database->query(
      'SELECT 1 FROM {dc_canonical_storylines} WHERE template_data LIKE :needle LIMIT 1',
      [':needle' => $needle],
    )->fetchField();
  }

}
