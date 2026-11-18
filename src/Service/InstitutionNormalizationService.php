<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Normalizes institution-domain inputs into canonical first-slice values.
 */
class InstitutionNormalizationService {

  /**
   * Canonical institution domains supported by the first slice.
   */
  /**
   * Institution domains an author may place an actor in, with their labels.
   *
   * These are the canonical domains of the institution engine. Ancestry and
   * profession are also domains, but an actor's membership in them is derived
   * from its ancestry and class, not authored.
   */
  public const AUTHORABLE_DOMAINS = [
    'state' => 'State',
    'place' => 'Place',
    'kin' => 'Kin',
    'organization' => 'Organisation',
    'faith' => 'Faith',
  ];

  /** What each authored type covers, for the author choosing one. */
  public const DOMAIN_HINTS = [
    'state' => 'a nation or realm, with its law and military',
    'place' => 'a settlement, district or region',
    'kin' => 'a culture, tribe, clan, house or family',
    'organization' => 'a faction, guild, order, school, company or covert group',
    'faith' => 'a religion, church or cult',
  ];

  /**
   * The nesting order: the types an institution of each type may sit inside.
   *
   * A state holds places, a place holds kin; each type also nests within
   * itself to any depth (nation, army, company; culture, tribe, clan,
   * household). Organisations and faiths cut across and may sit anywhere.
   * Two institutions with the same parent are peers.
   */
  public const PARENT_DOMAINS = [
    'state' => ['state'],
    'place' => ['place', 'state'],
    'kin' => ['kin', 'place', 'state'],
    'organization' => ['organization', 'faith', 'kin', 'place', 'state'],
    'faith' => ['faith', 'organization', 'kin', 'place', 'state'],
  ];

  /** Domains whose membership follows from the actor's own sheet. */
  public const DERIVED_DOMAINS = [
    'ancestry' => 'Ancestry',
    'profession' => 'Class or profession',
    'alignment' => 'Alignment',
  ];

  /**
   * The five alignment institutions and the axis each stands on.
   *
   * An actor belongs to one per axis its alignment names: Lawful Good is a
   * member of Lawful and of Good; a neutral axis is Neutral.
   */
  public const ALIGNMENT_INSTITUTIONS = [
    'Lawful' => 'ethical',
    'Chaotic' => 'ethical',
    'Good' => 'moral',
    'Evil' => 'moral',
    'Neutral' => 'both',
  ];

  /** An actor whose sheet states no alignment is Neutral. */
  public const DEFAULT_ALIGNMENT = 'N';

  private const ALIGNMENT_LETTERS = ['L' => 'Lawful', 'C' => 'Chaotic', 'G' => 'Good', 'E' => 'Evil', 'N' => 'Neutral'];

  /**
   * The alignment institutions an alignment makes an actor a member of.
   *
   * @param string $alignment
   *   A sheet code (LG, N, CE, ...) or its spelled-out form (Lawful Good).
   *
   * @return string[]
   *   One or two of the ALIGNMENT_INSTITUTIONS names.
   */
  public static function alignmentInstitutionNames(string $alignment): array {
    $value = trim($alignment);
    if ($value === '') {
      $value = self::DEFAULT_ALIGNMENT;
    }
    $words = preg_split('/[\s_-]+/', ucwords(strtolower($value), " _-")) ?: [];
    $words = array_values(array_diff($words, ['True']));
    if (count($words) === 1 && strlen($words[0]) <= 2) {
      $words = array_map(static fn(string $letter): string => self::ALIGNMENT_LETTERS[$letter] ?? $letter, str_split(strtoupper($words[0])));
    }
    $names = array_values(array_unique($words));
    $axes = array_map(static fn(string $name): ?string => self::ALIGNMENT_INSTITUTIONS[$name] ?? NULL, $names);
    $valid = $names !== [] && count($words) <= 2 && !in_array(NULL, $axes, TRUE)
      // Alone only Neutral; as a pair, the ethical axis then the moral one.
      && (count($names) === 1
        ? $axes[0] === 'both'
        : ($axes[0] !== 'moral' && $axes[1] !== 'ethical' && $axes[0] !== $axes[1]));
    if (!$valid) {
      throw new \InvalidArgumentException(sprintf('Alignment "%s" is not one of the nine alignments.', $alignment));
    }
    return $names;
  }

  private const DOMAIN_ALIASES = [
    'alignment' => 'alignment',
    'ancestry' => 'ancestry',
    'race' => 'ancestry',
    'species' => 'ancestry',
    'class' => 'profession',
    'profession' => 'profession',
    'calling' => 'profession',
    'occupation' => 'profession',
    'state' => 'state',
    'government' => 'state',
    'polity' => 'state',
    'authority' => 'state',
    'nation' => 'state',
    'law' => 'state',
    'military' => 'state',
    'security' => 'state',
    'watch' => 'state',
    'place' => 'place',
    'settlement' => 'place',
    'community' => 'place',
    'hometown' => 'place',
    'kin' => 'kin',
    'family' => 'kin',
    'house' => 'kin',
    'clan' => 'kin',
    'lineage' => 'kin',
    'noble' => 'kin',
    'patron' => 'kin',
    'culture' => 'kin',
    'ethnicity' => 'kin',
    'tribe' => 'kin',
    'organization' => 'organization',
    'organisation' => 'organization',
    'faction' => 'organization',
    'allegiance' => 'organization',
    'institution' => 'organization',
    'guild' => 'organization',
    'employer' => 'organization',
    'trade' => 'organization',
    'order' => 'organization',
    'education' => 'organization',
    'training' => 'organization',
    'school' => 'organization',
    'academy' => 'organization',
    'criminal' => 'organization',
    'covert' => 'organization',
    'faith' => 'faith',
    'religion' => 'faith',
    'church' => 'faith',
    'cult' => 'faith',
  ];

  /**
   * Whether an institution of one type may sit inside one of another.
   */
  public static function nestingAllowed(string $domain, string $parent_domain): bool {
    return in_array($parent_domain, self::PARENT_DOMAINS[$domain] ?? [], TRUE);
  }

  /**
   * The current ID of an institution whose ID was built under an older type name.
   */
  public function currentSubjectId(string $subject_id): string {
    if (preg_match('/^institution_([a-z]+)_(.+)$/', trim($subject_id), $matches) !== 1) {
      return trim($subject_id);
    }
    $domain = $this->normalizeDomain($matches[1]);
    return $domain === '' ? trim($subject_id) : $this->buildInstitutionSubjectId($domain, $matches[2]);
  }

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
