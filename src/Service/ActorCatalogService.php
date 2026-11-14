<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\Validation\ActorReferenceCatalogInterface;

/**
 * Single local catalog authority for actor authoring and validation.
 *
 * Reads the existing rules catalogs and feat/spell/item services. It never
 * performs a network lookup, substitutes a near match, or creates an entry.
 * Catalog membership is not eligibility; validation decides what a specific
 * actor may select.
 */
class ActorCatalogService implements ActorReferenceCatalogInterface {

  public const FAMILIES = ['ancestry', 'heritage', 'background', 'class', 'feat', 'spell', 'equipment', 'familiar_ability'];

  /**
   * Entry ids per family, built on first use.
   *
   * @var array<string, array<string, true>>
   */
  private array $ids = [];

  /**
   * Lower-cased id and name => matching ids, per kind.
   *
   * @var array<string, array<string, array<string, true>>>
   */
  private array $names = [];

  public function __construct(
    protected FeatLibraryService $featLibrary,
    protected SpellCatalogService $spellCatalog,
    protected CharacterCreationValidationService $creationRules,
    protected CanonicalDefinitionService $definitions,
  ) {}

  /**
   * Bounded, paginated search of one local catalog family.
   */
  public function search(string $family, string $query = '', int $limit = 10, int $offset = 0): array {
    if ($limit < 1 || $limit > 25 || $offset < 0) {
      throw new \InvalidArgumentException('Character catalog limit must be 1-25 and offset must be nonnegative.');
    }
    $entries = $this->entries($family);
    $query = mb_strtolower(trim($query));
    if ($query !== '') {
      $entries = array_values(array_filter($entries, static fn(array $entry): bool =>
        str_contains(mb_strtolower(json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), $query)
      ));
    }
    usort($entries, static fn(array $left, array $right): int =>
      [mb_strtolower($left['name']), $left['id']] <=> [mb_strtolower($right['name']), $right['id']]
    );
    $total = count($entries);
    return [
      'source' => 'local_character_catalog',
      'family' => $family,
      'query' => $query,
      'total' => $total,
      'offset' => $offset,
      'next_offset' => $offset + $limit < $total ? $offset + $limit : NULL,
      'entries' => array_slice($entries, $offset, $limit),
      'message' => $total === 0 ? 'No matching entries exist in this local catalog. No external lookup was attempted.' : '',
      'selection_authority' => 'Catalog membership is not eligibility. Use native wizard choices and validation before saving selections.',
    ];
  }

  /**
   * Reads exactly one complete stored entry by id or name.
   */
  public function entry(string $family, string $entry_id, ?string $ancestry = NULL): array {
    if (trim($entry_id) === '') {
      throw new \InvalidArgumentException('Character catalog entry ID or name is required.');
    }
    if ($ancestry !== NULL && ($family !== 'heritage' || trim($ancestry) === '')) {
      throw new \InvalidArgumentException('An ancestry qualifier is valid only for a heritage lookup and must not be empty.');
    }
    $matches = array_values(array_filter($this->entries($family), static function (array $entry) use ($entry_id, $ancestry): bool {
      $identity_matches = strcasecmp($entry['id'], $entry_id) === 0 || strcasecmp($entry['name'], $entry_id) === 0;
      return $identity_matches && ($ancestry === NULL
        || strcasecmp(str_replace(' ', '-', $entry['ancestry']), str_replace(' ', '-', $ancestry)) === 0);
    }));
    if ($matches === []) {
      throw new \OutOfBoundsException("No local $family catalog entry matches '$entry_id'.", 404);
    }
    if (count($matches) !== 1) {
      throw new \InvalidArgumentException("The local $family ID '$entry_id' is shared. Use its full name or qualify its ancestry. Matches: " . implode(', ', array_column($matches, 'name')) . '.');
    }
    $entry = $matches[0];
    return [
      'source' => 'local_character_catalog',
      'family' => $family,
      'entry' => $entry,
      'description_available' => trim((string) ($entry['description'] ?? $entry['benefit'] ?? '')) !== '',
      'selection_authority' => 'Use native wizard choices and validation to determine whether this entry is selectable for the current character.',
    ];
  }

  /**
   * All complete entries of a family, each with an id and name.
   *
   * @return array<int, array<string, mixed>>
   *   Entries.
   */
  public function entries(string $family): array {
    if ($family === 'feat') {
      return $this->featLibrary->getFeats();
    }
    if ($family === 'spell') {
      return $this->spellCatalog->getSpells();
    }
    if ($family === 'equipment') {
      return array_merge(...array_values($this->creationRules->getEquipmentCatalog()));
    }
    if ($family === 'heritage') {
      $entries = [];
      foreach (CharacterRulesCatalog::HERITAGES as $ancestry => $heritages) {
        foreach ($heritages as $heritage) {
          $entries[] = [...$heritage, 'ancestry' => $ancestry];
        }
      }
      return $entries;
    }
    $catalog = match ($family) {
      'ancestry' => CharacterRulesCatalog::ANCESTRIES,
      'background' => CharacterRulesCatalog::BACKGROUNDS,
      'class' => CharacterRulesCatalog::CLASSES,
      'familiar_ability' => FamiliarService::ABILITY_CATALOG,
      default => throw new \InvalidArgumentException('Unsupported local character catalog family: ' . $family),
    };
    $entries = [];
    foreach ($catalog as $id => $entry) {
      $entries[] = [
        ...$entry,
        'id' => $entry['id'] ?? strtolower(str_replace(' ', '-', $id)),
        'name' => $entry['name'] ?? $id,
      ];
    }
    return $entries;
  }

  /**
   * Resolves an exact id or exact (case-insensitive) name to one catalog id.
   *
   * Used by one-time conversion of stored names. Returns NULL when nothing or
   * more than one entry matches; it never picks a near match.
   */
  public function resolveId(string $kind, string $id_or_name): ?string {
    $needle = mb_strtolower(trim($id_or_name));
    if ($needle === '') {
      return NULL;
    }
    if ($kind === 'skill') {
      $id = str_replace([' ', '_'], '-', $needle);
      return $this->has('skill', $id) ? $id : NULL;
    }
    if ($kind === 'item') {
      $id = str_replace(' ', '-', $needle);
      return $this->has('item', $needle) ? $needle : ($this->has('item', $id) ? $id : NULL);
    }
    if (!isset($this->names[$kind])) {
      $this->names[$kind] = [];
      foreach ($this->entries($kind) as $entry) {
        $entry_id = (string) $entry['id'];
        foreach (array_unique([mb_strtolower($entry_id), mb_strtolower((string) $entry['name'])]) as $key) {
          $this->names[$kind][$key][$entry_id] = TRUE;
        }
      }
    }
    $matches = array_keys($this->names[$kind][$needle] ?? $this->names[$kind][str_replace(' ', '-', $needle)] ?? []);

    return count($matches) === 1 ? (string) $matches[0] : NULL;
  }

  /**
   * The catalog's own slot kind for a feat: ancestry, class, general or skill.
   */
  public function featType(string $feat_id): ?string {
    foreach ($this->entries('feat') as $feat) {
      if ((string) $feat['id'] === $feat_id) {
        $type = (string) ($feat['type'] ?? '');
        return in_array($type, ['ancestry', 'class', 'general', 'skill'], TRUE) ? $type : NULL;
      }
    }

    return NULL;
  }

  /**
   * The catalog's base rank for a spell (0 for cantrips).
   */
  public function spellRank(string $spell_id): ?int {
    foreach ($this->entries('spell') as $spell) {
      if ((string) $spell['id'] === $spell_id) {
        if (!empty($spell['is_cantrip'])) {
          return 0;
        }
        return is_numeric($spell['rank'] ?? NULL) ? (int) $spell['rank'] : NULL;
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function has(string $kind, string $id): bool {
    if (!in_array($kind, self::KINDS, TRUE)) {
      throw new \InvalidArgumentException('actor_reference_kind_invalid:' . $kind);
    }
    if ($kind === 'skill') {
      // Lore skills are open-ended by rule; every other skill is enumerated.
      return isset(CharacterCalculator::SKILLS[$id]) || (bool) preg_match('/^lore-[a-z0-9]+(?:-[a-z0-9]+)*$/', $id);
    }
    if ($kind === 'item') {
      return $this->definitions->currentVersion('item', $id) !== NULL;
    }
    $this->ids[$kind] ??= array_fill_keys(array_map('strval', array_column($this->entries($kind), 'id')), TRUE);

    return isset($this->ids[$kind][$id]);
  }

}
