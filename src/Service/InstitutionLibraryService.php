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
    $existing = $this->loadRow((string) $record['canonicalSlug']);
    if ($existing !== NULL) {
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
      if ($existing !== NULL && (string) ($payload['librarySubjectId'] ?? '') !== (string) $normalized['subject_id']) {
        throw new \InvalidArgumentException(sprintf(
          'institution_library_name_taken: the library already holds "%s" as a %s institution; "%s" cannot also be a %s institution. Reuse the existing one or choose another name.',
          (string) ($payload['canonicalLabel'] ?? $slug),
          (string) ($payload['domain'] ?? ''),
          (string) $normalized['display_name'],
          (string) $normalized['domain'],
        ));
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
        'summary' => trim((string) ($institution['description'] ?? '')),
        'purpose' => trim((string) ($institution['purpose'] ?? '')),
        'stances' => array_values(array_filter((array) ($institution['stances'] ?? []), 'is_array')),
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
   * Registers the five alignment institutions.
   */
  public function registerAlignmentInstitutions(): void {
    $normalizer = new InstitutionNormalizationService();
    foreach (array_keys(InstitutionNormalizationService::ALIGNMENT_INSTITUTIONS) as $name) {
      $normalized = $normalizer->normalizeInstitutionInput(['domain' => 'alignment', 'display_name' => $name]);
      $this->upsert([
        'canonicalLabel' => (string) $normalized['display_name'],
        'canonicalSlug' => (string) $normalized['normalized_label'],
        'domain' => 'alignment',
        'librarySubjectId' => (string) $normalized['subject_id'],
        'parentSubjectId' => '',
        'summary' => sprintf('Everyone whose alignment is %s. Membership follows from the actor\'s own alignment and is never authored.', $name),
        'requestContext' => ['request_source' => 'system_alignment'],
      ]);
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
    usort($matches, static fn(array $a, array $b): int => [$a['name'], $a['domain']] <=> [$b['name'], $b['domain']]);
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
