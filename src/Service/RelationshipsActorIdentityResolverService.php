<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Resolves canonical actor identity and fallback institution memberships.
 */
class RelationshipsActorIdentityResolverService {

  public function __construct(
    protected readonly Connection $database,
    protected readonly InstitutionMembershipService $institutionMembershipService,
  ) {}

  /**
   * Resolve actor identity for institution subsystem lookups.
   *
   * @return array<string,string>|null
   *   source_type/source_id identity, or null when unresolved.
   */
  public function resolveInstitutionActorIdentity(int $campaign_id, string $entity_ref): ?array {
    $entity_ref = trim($entity_ref);
    if ($campaign_id <= 0 || $entity_ref === '' || !$this->database->schema()->tableExists('dc_campaign_characters')) {
      return NULL;
    }
    $candidates = $this->buildEntityRefCandidates($entity_ref);
    if ($candidates === []) {
      return NULL;
    }

    $row = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'instance_id', 'type'])
      ->condition('campaign_id', $campaign_id)
      ->condition('instance_id', $candidates, 'IN')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      return NULL;
    }

    $source_id = trim((string) ($row['instance_id'] ?? ''));
    if ($source_id === '') {
      return NULL;
    }
    $type = strtolower(trim((string) ($row['type'] ?? 'npc')));
    $source_type = in_array($type, ['player', 'character', 'pc'], TRUE) ? 'campaign_character' : 'campaign_npc';

    return [
      'source_type' => $source_type,
      'source_id' => $source_id,
    ];
  }

  /**
   * Build fallback institution memberships from character ancestry/class fields.
   *
   * @return array<int,array<string,mixed>>
   *   Membership-like rows compatible with institution breakdown scoring.
   */
  public function buildFallbackTargetInstitutionMemberships(int $campaign_id, string $target_ref): array {
    if ($campaign_id <= 0 || trim($target_ref) === '' || !$this->database->schema()->tableExists('dc_campaign_characters')) {
      return [];
    }

    $row = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['instance_id', 'name', 'character_data', 'type'])
      ->condition('campaign_id', $campaign_id)
      ->condition('instance_id', $this->buildEntityRefCandidates($target_ref), 'IN')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($row)) {
      return [];
    }

    $character_data = json_decode((string) ($row['character_data'] ?? '{}'), TRUE);
    if (!is_array($character_data)) {
      $character_data = [];
    }
    $canonical_actor_data = isset($character_data['character']) && is_array($character_data['character'])
      ? $character_data['character']
      : $character_data;
    if (!is_array($canonical_actor_data)) {
      $canonical_actor_data = [];
    }

    $memberships = [];
    $type = strtolower(trim((string) ($row['type'] ?? 'npc')));
    $institution_inputs = in_array($type, ['player', 'character', 'pc'], TRUE)
      ? $this->institutionMembershipService->buildCharacterInstitutionInputs($canonical_actor_data, 'relationships_matrix_fallback')
      : $this->institutionMembershipService->buildNpcInstitutionInputs($canonical_actor_data, 'relationships_matrix_fallback');

    foreach ($institution_inputs as $input) {
      if (!is_array($input)) {
        continue;
      }
      $domain = strtolower(trim((string) ($input['domain'] ?? '')));
      $display_name = trim((string) ($input['display_name'] ?? ''));
      if ($domain === '' || $display_name === '') {
        continue;
      }
      $metadata = is_array($input['metadata'] ?? NULL) ? $input['metadata'] : [];
      $subject_id = trim((string) ($input['subject_id'] ?? $metadata['subject_id'] ?? ''));
      if ($subject_id === '') {
        $subject_id = $this->buildFallbackInstitutionSubjectId($domain, $display_name);
      }
      if ($subject_id === '') {
        continue;
      }
      $memberships[] = [
        'target_id' => $subject_id,
        'target_display_name' => $display_name,
        'sentiment_domain' => $this->mapInstitutionDomainToSentimentDomain($domain),
        'membership_domain' => $this->mapInstitutionDomainToMembershipDomain($domain),
        'membership_mutability' => $this->mapInstitutionDomainToMembershipMutability($domain),
        'membership_status' => 'active',
      ];
    }

    return $this->dedupeMembershipRows($memberships);
  }

  /**
   * Normalize a freeform institution label into a canonical id fragment.
   */
  protected function normalizeInstitutionLabel(string $value): string {
    $normalized = strtolower(trim($value));
    $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
    return trim($normalized, '-');
  }

  /**
   * Build canonical entity-ref candidate variants.
   *
   * @return array<int,string>
   *   Candidate entity refs.
   */
  protected function buildEntityRefCandidates(string $entity_ref): array {
    $normalized = strtolower(trim($entity_ref));
    if ($normalized === '') {
      return [];
    }
    $candidates = [$normalized];
    if (str_starts_with($normalized, 'npc_')) {
      $candidates[] = substr($normalized, 4);
    }
    if (str_starts_with($normalized, 'npc-')) {
      $candidates[] = substr($normalized, 4);
    }
    $base = str_starts_with($normalized, 'npc_')
      ? substr($normalized, 4)
      : (str_starts_with($normalized, 'npc-') ? substr($normalized, 4) : $normalized);
    if ($base !== '') {
      $candidates[] = 'npc_' . $base;
      $candidates[] = 'npc-' . $base;
      $hyphen_base = str_replace('_', '-', $base);
      $underscore_base = str_replace('-', '_', $base);
      $candidates[] = $hyphen_base;
      $candidates[] = $underscore_base;
      $candidates[] = 'npc_' . $hyphen_base;
      $candidates[] = 'npc-' . $hyphen_base;
      $candidates[] = 'npc_' . $underscore_base;
      $candidates[] = 'npc-' . $underscore_base;
    }
    return array_values(array_unique(array_filter(array_map('trim', $candidates), static fn(string $value): bool => $value !== '')));
  }

  /**
   * Build a deterministic fallback institution subject id.
   */
  protected function buildFallbackInstitutionSubjectId(string $domain, string $display_name): string {
    $normalized_label = $this->normalizeInstitutionLabel($display_name);
    if ($normalized_label === '') {
      return '';
    }
    return match ($domain) {
      'ancestry' => 'institution_ancestry_' . $normalized_label,
      'profession' => 'institution_profession_' . $normalized_label,
      default => 'institution_' . $this->normalizeInstitutionLabel($domain) . '_' . $normalized_label,
    };
  }

  /**
   * Convert an institution domain into matrix sentiment domain.
   */
  protected function mapInstitutionDomainToSentimentDomain(string $domain): string {
    return match ($domain) {
      'ancestry' => 'ancestry',
      'profession' => 'class',
      default => 'political',
    };
  }

  /**
   * Convert institution domain into membership domain classification.
   */
  protected function mapInstitutionDomainToMembershipDomain(string $domain): string {
    return match ($domain) {
      'ancestry' => 'identity',
      'profession' => 'vocation',
      default => 'social',
    };
  }

  /**
   * Convert institution domain into mutability classification.
   */
  protected function mapInstitutionDomainToMembershipMutability(string $domain): string {
    return match ($domain) {
      'ancestry' => 'immutable',
      'profession' => 'sticky',
      default => 'mutable',
    };
  }

  /**
   * De-duplicate membership rows by target_id while preserving order.
   *
   * @param array<int,array<string,mixed>> $memberships
   *   Membership rows.
   *
   * @return array<int,array<string,mixed>>
   *   Unique rows.
   */
  protected function dedupeMembershipRows(array $memberships): array {
    $resolved = [];
    $seen = [];
    foreach ($memberships as $membership) {
      if (!is_array($membership)) {
        continue;
      }
      $target_id = trim((string) ($membership['target_id'] ?? ''));
      if ($target_id === '' || isset($seen[$target_id])) {
        continue;
      }
      $seen[$target_id] = TRUE;
      $resolved[] = $membership;
    }

    return $resolved;
  }

}
