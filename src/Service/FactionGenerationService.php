<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Creates canonical library-backed factions from narrative-generation requests.
 */
class FactionGenerationService {

  protected const MANIFEST_SOURCE_TABLE = 'generated_faction';
  protected const MANIFEST_SOURCE_FILE = '__generated__/factions';
  protected const MANIFEST_ROW_TYPE = 'institution';
  protected const MANIFEST_CLASSIFICATION = 'canonical_faction';
  protected const MANIFEST_STATUS = 'normalized';

  public function __construct(
    protected Connection $database,
    protected InstitutionNormalizationService $institutionNormalization,
    protected CampaignSubjectRegistryService $campaignSubjectRegistry,
  ) {}

  /**
   * Returns whether faction-generation storage is ready.
   */
  public function isGenerationStorageReady(): bool {
    return $this->database->schema()->tableExists('dc_library_institution_manifest')
      && $this->campaignSubjectRegistry->isSubjectRegistryReady();
  }

  /**
   * Validates and normalizes one faction-generation request.
   *
   * @return array<string, mixed>
   */
  public function normalizeNarrativeNeedRequest(int $campaign_id, array $request): array {
    if ($campaign_id <= 0) {
      throw new \InvalidArgumentException('Faction generation requires a valid campaign id.');
    }

    $canonical_label = trim((string) ($request['canonicalLabel'] ?? $request['canonical_label'] ?? $request['label'] ?? ''));
    if ($canonical_label === '') {
      throw new \InvalidArgumentException('Faction generation requires a canonical label.');
    }

    $why_existing_is_insufficient = trim((string) ($request['whyExistingFactionIsInsufficient'] ?? $request['why_existing_faction_is_insufficient'] ?? ''));
    if ($why_existing_is_insufficient === '') {
      throw new \InvalidArgumentException('Explain why an existing faction cannot satisfy this narrative need.');
    }

    $public_face = trim((string) ($request['publicFace'] ?? $request['public_face'] ?? ''));
    $hidden_face = trim((string) ($request['hiddenFace'] ?? $request['hidden_face'] ?? ''));
    $ideology_tags = $this->parseTagList($request['ideologyTags'] ?? $request['ideology_tags'] ?? []);
    $method_tags = $this->parseTagList($request['methodTags'] ?? $request['method_tags'] ?? []);
    $role_in_story = trim((string) ($request['roleInStory'] ?? $request['role_in_story'] ?? ''));
    if ($public_face === '' && $hidden_face === '' && $ideology_tags === [] && $method_tags === [] && $role_in_story === '') {
      throw new \InvalidArgumentException('Describe at least one faction characteristic before creating a new canonical faction.');
    }

    $normalized = $this->institutionNormalization->normalizeInstitutionInput([
      'domain' => (string) ($request['domain'] ?? 'faction'),
      'display_name' => $canonical_label,
    ]);

    return [
      'campaign_id' => $campaign_id,
      'domain' => (string) $normalized['domain'],
      'canonical_label' => (string) $normalized['display_name'],
      'normalized_label' => (string) $normalized['normalized_label'],
      'canonical_slug' => (string) $normalized['normalized_label'],
      'library_subject_id' => (string) $normalized['subject_id'],
      'parent_subject_id' => trim((string) ($request['parentSubjectId'] ?? $request['parent_subject_id'] ?? '')),
      'provenance_note' => trim((string) ($request['provenanceNote'] ?? $request['provenance_note'] ?? '')),
      'request_source' => trim((string) ($request['requestSource'] ?? $request['request_source'] ?? 'narrative_need')),
      'role_in_story' => $role_in_story,
      'why_existing_faction_is_insufficient' => $why_existing_is_insufficient,
      'public_face' => $public_face,
      'hidden_face' => $hidden_face,
      'ideology_tags' => $ideology_tags,
      'method_tags' => $method_tags,
      'membership_style' => trim((string) ($request['membershipStyle'] ?? $request['membership_style'] ?? 'invite_only')),
      'initial_known_factions' => $this->parseTagList($request['initialKnownFactions'] ?? $request['initial_known_factions'] ?? []),
      'initial_unknown_factions' => $this->parseTagList($request['initialUnknownFactions'] ?? $request['initial_unknown_factions'] ?? []),
    ];
  }

  /**
   * Generates a deterministic canonical faction draft.
   *
   * @param array<string, mixed> $normalized_request
   *   Validated faction-generation request.
   *
   * @return array<string, mixed>
   *   Canonical faction draft payload.
   */
  public function generateFactionDraft(array $normalized_request): array {
    $slug = (string) ($normalized_request['canonical_slug'] ?? '');
    $summary_parts = array_values(array_filter([
      (string) ($normalized_request['role_in_story'] ?? ''),
      (string) ($normalized_request['public_face'] ?? ''),
      (string) ($normalized_request['hidden_face'] ?? ''),
    ]));
    $summary = implode(' | ', array_slice($summary_parts, 0, 3));
    if ($summary === '') {
      $summary = 'Narrative-generated faction draft.';
    }

    $proxy_roles = array_values(array_unique(array_filter([
      (string) ($normalized_request['role_in_story'] ?? ''),
      (string) ($normalized_request['public_face'] ?? ''),
      'representative',
    ])));

    return [
      'draftKey' => 'faction-draft-' . $slug,
      'canonicalLabel' => (string) ($normalized_request['canonical_label'] ?? ''),
      'canonicalSlug' => $slug,
      'domain' => (string) ($normalized_request['domain'] ?? 'allegiance'),
      'librarySubjectId' => (string) ($normalized_request['library_subject_id'] ?? ''),
      'parentSubjectId' => (string) ($normalized_request['parent_subject_id'] ?? ''),
      'summary' => $summary,
      'proxyRoles' => $proxy_roles,
      'membershipModel' => [
        'membership_domain' => (string) ($normalized_request['domain'] ?? 'allegiance'),
        'default_mutability' => 'mutable',
        'membership_style' => (string) ($normalized_request['membership_style'] ?? 'invite_only'),
      ],
      'seedProfile' => [
        'profile_key' => 'generated-' . $slug . '-seed',
        'initial_known_factions' => array_values($normalized_request['initial_known_factions'] ?? []),
        'initial_unknown_factions' => array_values($normalized_request['initial_unknown_factions'] ?? []),
      ],
      'requestedCharacteristics' => [
        'role_in_story' => (string) ($normalized_request['role_in_story'] ?? ''),
        'public_face' => (string) ($normalized_request['public_face'] ?? ''),
        'hidden_face' => (string) ($normalized_request['hidden_face'] ?? ''),
        'ideology_tags' => array_values($normalized_request['ideology_tags'] ?? []),
        'method_tags' => array_values($normalized_request['method_tags'] ?? []),
      ],
      'requestContext' => [
        'campaign_id' => (int) ($normalized_request['campaign_id'] ?? 0),
        'request_source' => (string) ($normalized_request['request_source'] ?? 'narrative_need'),
        'provenance_note' => (string) ($normalized_request['provenance_note'] ?? ''),
        'why_existing_faction_is_insufficient' => (string) ($normalized_request['why_existing_faction_is_insufficient'] ?? ''),
      ],
    ];
  }

  /**
   * Creates or reuses a canonical faction, then instantiates it in the campaign.
   *
   * @return array<string, mixed>
   *   Generation result, including canonical and campaign identifiers.
   */
  public function createOrReuseFactionForNeed(int $campaign_id, array $request): array {
    if (!$this->isGenerationStorageReady()) {
      throw new \RuntimeException('Faction generation storage is not installed.');
    }

    $normalized_request = $this->normalizeNarrativeNeedRequest($campaign_id, $request);
    $draft = $this->generateFactionDraft($normalized_request);
    $existing = $this->findExistingLibraryFactionBySlug((string) $draft['canonicalSlug']);

    if ($existing !== NULL) {
      $campaign_subject = $this->instantiateCampaignFactionSubject($campaign_id, $draft);
      return [
        'status' => 'reused',
        'created' => FALSE,
        'manifestId' => (int) ($existing['id'] ?? 0),
        'canonicalSlug' => (string) $draft['canonicalSlug'],
        'librarySubjectId' => (string) $draft['librarySubjectId'],
        'campaignSubjectId' => (string) ($campaign_subject['subject_id'] ?? ''),
        'draft' => $draft,
      ];
    }

    $manifest_id = $this->upsertLibraryFactionManifest($draft);
    $campaign_subject = $this->instantiateCampaignFactionSubject($campaign_id, $draft);

    return [
      'status' => 'created',
      'created' => TRUE,
      'manifestId' => $manifest_id,
      'canonicalSlug' => (string) $draft['canonicalSlug'],
      'librarySubjectId' => (string) $draft['librarySubjectId'],
      'campaignSubjectId' => (string) ($campaign_subject['subject_id'] ?? ''),
      'draft' => $draft,
    ];
  }

  /**
   * Loads an existing canonical faction manifest row by stable slug.
   *
   * @return array<string, mixed>|null
   *   Existing row or NULL when none exists.
   */
  protected function findExistingLibraryFactionBySlug(string $canonical_slug): ?array {
    if ($canonical_slug === '') {
      return NULL;
    }

    $row = $this->database->select('dc_library_institution_manifest', 'm')
      ->fields('m')
      ->condition('source_table', self::MANIFEST_SOURCE_TABLE)
      ->condition('source_file', self::MANIFEST_SOURCE_FILE)
      ->condition('source_asset_id', $canonical_slug)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return is_array($row) ? $row : NULL;
  }

  /**
   * Writes or updates the canonical faction manifest row.
   */
  protected function upsertLibraryFactionManifest(array $draft): int {
    $now = time();
    $fields = [
      'source_table' => self::MANIFEST_SOURCE_TABLE,
      'source_file' => self::MANIFEST_SOURCE_FILE,
      'source_asset_id' => (string) $draft['canonicalSlug'],
      'library_character_id' => NULL,
      'row_type' => self::MANIFEST_ROW_TYPE,
      'classification' => self::MANIFEST_CLASSIFICATION,
      'status' => self::MANIFEST_STATUS,
      'normalized_payload_json' => json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'review_reasons_json' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'provenance_json' => json_encode($draft['requestContext'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'changed' => $now,
    ];

    $existing = $this->findExistingLibraryFactionBySlug((string) $draft['canonicalSlug']);
    if ($existing !== NULL) {
      $this->database->update('dc_library_institution_manifest')
        ->fields($fields)
        ->condition('id', (int) $existing['id'])
        ->execute();
      return (int) $existing['id'];
    }

    $fields['created'] = $now;
    return (int) $this->database->insert('dc_library_institution_manifest')
      ->fields($fields)
      ->execute();
  }

  /**
   * Instantiates the canonical faction in the campaign registry.
   *
   * @return array<string, mixed>
   *   Resolved campaign subject row details.
   */
  protected function instantiateCampaignFactionSubject(int $campaign_id, array $draft): array {
    return $this->campaignSubjectRegistry->resolveOrCreateInstitutionSubject($campaign_id, [
      'domain' => (string) ($draft['domain'] ?? 'allegiance'),
      'display_name' => (string) ($draft['canonicalLabel'] ?? ''),
      'parent_subject_id' => (string) ($draft['parentSubjectId'] ?? ''),
      'source_asset_type' => 'library_faction',
      'source_asset_id' => (string) ($draft['canonicalSlug'] ?? ''),
      'metadata' => [
        'created_via' => 'faction_generation_service',
        'draft_key' => (string) ($draft['draftKey'] ?? ''),
        'summary' => (string) ($draft['summary'] ?? ''),
        'seed_profile_key' => (string) (($draft['seedProfile']['profile_key'] ?? '')),
        'request_context' => $draft['requestContext'] ?? [],
        'requested_characteristics' => $draft['requestedCharacteristics'] ?? [],
      ],
    ]);
  }

  /**
   * @return array<int, string>
   *   Normalized tags.
   */
  protected function parseTagList(mixed $value): array {
    $parts = is_array($value)
      ? $value
      : (preg_split('/[\r\n,]+/', (string) $value) ?: []);
    $normalized = [];
    foreach ($parts as $part) {
      $tag = trim((string) $part);
      if ($tag === '') {
        continue;
      }
      $normalized[] = $tag;
    }
    return array_values(array_unique($normalized));
  }

}
