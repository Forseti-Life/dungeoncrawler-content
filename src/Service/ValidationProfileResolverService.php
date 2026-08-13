<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Resolves validation profiles and profile-specific field policies.
 */
class ValidationProfileResolverService {

  public const PROFILE_CANONICAL_REGISTRY = 'canonical_registry';
  public const PROFILE_INTERMEDIARY_INGEST = 'intermediary_ingest';

  /**
   * Fields allowed in intermediary ingest but blocked in canonical registry.
   *
   * @var array<string, array<int, string>>
   */
  private const INTERMEDIARY_ONLY_FIELDS_BY_TYPE = [
    'spell' => ['source_book', 'source_display', 'parser_version', 'extraction_method', 'references'],
    'feat' => ['source_book', 'source_display', 'parser_version', 'extraction_method', 'references'],
    'item' => ['source_book', 'source_display', 'parser_version', 'extraction_method', 'references', 'price_gp'],
  ];

  /**
   * Resolve profile name with canonical registry as the safe default.
   */
  public function resolveProfile(?string $profile): string {
    $candidate = strtolower(trim((string) $profile));
    if ($candidate === self::PROFILE_INTERMEDIARY_INGEST) {
      return self::PROFILE_INTERMEDIARY_INGEST;
    }
    return self::PROFILE_CANONICAL_REGISTRY;
  }

  /**
   * Validate profile-specific payload field policy.
   *
   * @param array<string, mixed> $payload
   *   Contract payload.
   * @param string $content_type
   *   Contract type (spell|feat|item|...).
   * @param string|null $profile
   *   Profile string.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  public function validatePayloadProfile(array $payload, string $content_type, ?string $profile = NULL): array {
    $resolved_profile = $this->resolveProfile($profile);
    if ($resolved_profile === self::PROFILE_INTERMEDIARY_INGEST) {
      return [];
    }

    $type = strtolower(trim($content_type));
    $intermediary_fields = self::INTERMEDIARY_ONLY_FIELDS_BY_TYPE[$type] ?? [];
    $errors = [];
    foreach ($intermediary_fields as $field) {
      if (array_key_exists($field, $payload)) {
        $errors[] = sprintf(
          "Field '%s' is intermediary-only and is not allowed in canonical_registry profile.",
          $field
        );
      }
    }
    return $errors;
  }

}

