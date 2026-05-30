<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Normalizes institution-domain inputs into canonical first-slice values.
 */
class InstitutionNormalizationService {

  /**
   * Canonical institution domains supported by the first slice.
   */
  private const DOMAIN_ALIASES = [
    'ancestry' => 'ancestry',
    'race' => 'ancestry',
    'species' => 'ancestry',
    'class' => 'profession',
    'profession' => 'profession',
    'calling' => 'profession',
    'occupation' => 'profession',
    'settlement' => 'settlement',
    'community' => 'settlement',
    'hometown' => 'settlement',
    'government' => 'government',
    'polity' => 'government',
    'authority' => 'government',
    'nation' => 'government',
    'faction' => 'allegiance',
    'allegiance' => 'allegiance',
    'institution' => 'allegiance',
    'law' => 'security',
    'military' => 'security',
    'security' => 'security',
    'watch' => 'security',
    'family' => 'family',
    'house' => 'family',
    'clan' => 'family',
    'lineage' => 'family',
    'religion' => 'religion',
    'faith' => 'religion',
    'church' => 'religion',
    'cult' => 'religion',
    'guild' => 'employer',
    'employer' => 'employer',
    'trade' => 'employer',
    'order' => 'education',
    'education' => 'education',
    'training' => 'education',
    'school' => 'education',
    'academy' => 'education',
    'noble' => 'noble',
    'patron' => 'noble',
    'criminal' => 'criminal',
    'covert' => 'criminal',
    'culture' => 'culture',
    'ethnicity' => 'culture',
    'tribe' => 'culture',
  ];

  /**
   * Normalize a domain name into the canonical first-slice domain.
   */
  public function normalizeDomain(string $domain): string {
    $normalized = $this->normalizeToken($domain);
    return self::DOMAIN_ALIASES[$normalized] ?? '';
  }

  /**
   * Normalize a full institution input payload.
   *
   * @return array{
   *   domain: string,
   *   display_name: string,
   *   normalized_label: string,
   *   subject_id: string,
   *   parent_subject_id: string
   * }
   */
  public function normalizeInstitutionInput(array $input): array {
    $domain = $this->normalizeDomain((string) ($input['domain'] ?? ''));
    if ($domain === '') {
      throw new \InvalidArgumentException('Institution domain is required and must be supported.');
    }

    $display_name = $this->normalizeDisplayName((string) ($input['display_name'] ?? $input['label'] ?? ''));
    if ($display_name === '') {
      throw new \InvalidArgumentException('Institution display name is required.');
    }

    $normalized_label = $this->normalizeToken((string) ($input['normalized_label'] ?? $display_name));
    if ($normalized_label === '') {
      throw new \InvalidArgumentException('Institution display name must normalize to a non-empty identifier.');
    }

    $subject_id = trim((string) ($input['subject_id'] ?? ''));
    if ($subject_id === '') {
      $subject_id = $this->buildInstitutionSubjectId($domain, $normalized_label);
    }

    $parent_subject_id = $this->normalizeToken((string) ($input['parent_subject_id'] ?? ''));

    return [
      'domain' => $domain,
      'display_name' => $display_name,
      'normalized_label' => $normalized_label,
      'subject_id' => $subject_id,
      'parent_subject_id' => $parent_subject_id,
    ];
  }

  /**
   * Build a deterministic institution subject id.
   */
  public function buildInstitutionSubjectId(string $domain, string $normalized_label): string {
    $domain = $this->normalizeDomain($domain);
    $normalized_label = $this->normalizeToken($normalized_label);
    if ($domain === '' || $normalized_label === '') {
      throw new \InvalidArgumentException('Institution subject ids require a supported domain and normalized label.');
    }

    return sprintf('institution_%s_%s', $domain, $normalized_label);
  }

  /**
   * Normalize human-facing labels without destroying authored capitalization.
   */
  public function normalizeDisplayName(string $value): string {
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return trim($value);
  }

  /**
   * Normalize identifier fragments to the module's canonical slug style.
   */
  public function normalizeToken(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
    return trim($value, '-_');
  }

}
