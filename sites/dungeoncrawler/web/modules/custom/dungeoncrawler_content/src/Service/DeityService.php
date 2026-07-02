<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Deity catalog and rules service (dc-gam-gods-magic).
 *
 * Owns:
 * - Deity data model constants (alignments, domains, divine font types, divine skills)
 * - Static seed catalog for representative deities (extensible via dc_deities DB table)
 * - Deity FK validation for character creation and feat selection
 * - Domain-to-deity lookup for domain feat eligibility
 * - Divine font type resolution per deity (heal / harm / both)
 * - Favored weapon grant at Cleric L1
 *
 * Data source hierarchy (first non-empty wins):
 *   1. dc_deities DB table (loaded via drush import or update hook)
 *   2. SEED_CATALOG constant (static representative deities for testing)
 */
class DeityService {

  const ALIGNMENTS = ['LG', 'NG', 'CG', 'LN', 'N', 'CN', 'LE', 'NE', 'CE'];

  const DIVINE_FONT_TYPES = ['heal', 'harm', 'both'];

  const DOMAINS = [
    'air', 'ambition', 'change', 'cities', 'cold', 'confidence', 'creation',
    'darkness', 'death', 'destruction', 'dreams', 'dust', 'duty', 'earth',
    'family', 'fate', 'fire', 'freedom', 'glyph', 'healing', 'indulgence',
    'knowledge', 'luck', 'magic', 'might', 'moon', 'nature', 'nightmares',
    'pain', 'passion', 'perfection', 'plague', 'protection', 'repose',
    'secrecy', 'soul', 'star', 'sun', 'swarm', 'toil', 'travel', 'trickery',
    'tyranny', 'undead', 'vigil', 'void', 'water', 'wealth', 'wyrmkin',
    'zeal',
  ];

  const DIVINE_SKILLS = [
    'Acrobatics', 'Arcana', 'Athletics', 'Crafting', 'Deception', 'Diplomacy',
    'Intimidation', 'Medicine', 'Nature', 'Occultism', 'Performance', 'Religion',
    'Society', 'Stealth', 'Survival', 'Thievery',
  ];

  const ABILITY_CHOICES = ['STR', 'DEX', 'CON', 'INT', 'WIS', 'CHA'];

  /**
   * Representative seed catalog (used when dc_deities table is empty).
   *
   * Structure per deity:
   *   id: machine-readable slug
   *   name: display name
   *   alignment: one of ALIGNMENTS
   *   edicts: string[]
   *   anathema: string[]
   *   domains: { primary: string[], alternate: string[] }
   *   divine_font: 'heal' | 'harm' | 'both'
   *   divine_skill: one of DIVINE_SKILLS
   *   favored_weapon: equipment catalog slug (e.g. 'longsword')
   *   cleric_spells: { "1": spell_id, "2": spell_id, ... }
   *   divine_ability: string[] — two ability choices
   */
  const SEED_CATALOG = [
    [
      'id' => 'abadar',
      'name' => 'Abadar',
      'alignment' => 'LN',
      'edicts' => [
        'Bring civilization to the frontiers',
        'Obey the rule of law',
        'Respect legitimate authority',
        'Seek the best trades',
      ],
      'anathema' => [
        'Steal',
        'Act dishonestly in trade',
        'Undermine a legitimate government',
      ],
      'domains' => [
        'primary' => ['cities', 'duty', 'travel', 'wealth'],
        'alternate' => ['earth', 'protection'],
      ],
      'divine_font' => 'heal',
      'divine_skill' => 'Society',
      'favored_weapon' => 'crossbow',
      'cleric_spells' => [
        '1' => 'illusory-object',
        '4' => 'creation',
        '7' => 'magnificent-mansion',
      ],
      'divine_ability' => ['INT', 'WIS'],
    ],
    [
      'id' => 'desna',
      'name' => 'Desna',
      'alignment' => 'CG',
      'edicts' => [
        'Aid fellow travelers',
        'Explore new places',
        'Express yourself through art and song',
        'Find what life has to offer',
      ],
      'anathema' => [
        'Cause fear or despair',
        'Prevent others from experiencing something new',
        'Settle in one place for too long',
      ],
      'domains' => [
        'primary' => ['dreams', 'fate', 'luck', 'moon', 'star', 'travel'],
        'alternate' => ['change', 'freedom'],
      ],
      'divine_font' => 'heal',
      'divine_skill' => 'Acrobatics',
      'favored_weapon' => 'starknife',
      'cleric_spells' => [
        '1' => 'sleep',
        '5' => 'dreaming-potential',
        '6' => 'dreaming-potential',
      ],
      'divine_ability' => ['DEX', 'WIS'],
    ],
    [
      'id' => 'gorum',
      'name' => 'Gorum',
      'alignment' => 'CN',
      'edicts' => [
        'Seek out the most challenging and worthy opponents',
        'Prove your mettle in battle',
        'Hone your battle skills constantly',
      ],
      'anathema' => [
        'Engage in dishonest tactics or flee from battle',
        'Treat warfare as something other than a sacred pursuit',
      ],
      'domains' => [
        'primary' => ['confidence', 'destruction', 'might', 'zeal'],
        'alternate' => ['ambition', 'pain'],
      ],
      'divine_font' => 'harm',
      'divine_skill' => 'Athletics',
      'favored_weapon' => 'greatsword',
      'cleric_spells' => [
        '1' => 'true-strike',
        '2' => 'enlarge',
        '4' => 'weapon-storm',
      ],
      'divine_ability' => ['STR', 'CON'],
    ],
    [
      'id' => 'nethys',
      'name' => 'Nethys',
      'alignment' => 'N',
      'edicts' => [
        'Seek the power of magic above all',
        'Destroy and create in equal measure',
        'Pursue new magical knowledge',
      ],
      'anathema' => [
        'Deny a spell to a worthy student',
        'Destroy magic permanently without cause',
      ],
      'domains' => [
        'primary' => ['destruction', 'knowledge', 'magic', 'protection'],
        'alternate' => ['change', 'glyph'],
      ],
      'divine_font' => 'both',
      'divine_skill' => 'Arcana',
      'favored_weapon' => 'staff',
      'cleric_spells' => [
        '1' => 'magic-missile',
        '2' => 'magic-mouth',
        '3' => 'slow',
      ],
      'divine_ability' => ['INT', 'WIS'],
    ],
    [
      'id' => 'pharasma',
      'name' => 'Pharasma',
      'alignment' => 'N',
      'edicts' => [
        'Lay the dead to rest',
        'Protect the living from undue harm by the undead',
        'Strive to live your full, fated life',
      ],
      'anathema' => [
        'Animate the dead',
        'Intentionally postpone your fate',
        'Disrupt the transition of the soul after death',
      ],
      'domains' => [
        'primary' => ['death', 'fate', 'healing', 'knowledge', 'soul', 'time'],
        'alternate' => ['cold', 'darkness', 'plague', 'water'],
      ],
      'divine_font' => 'heal',
      'divine_skill' => 'Medicine',
      'favored_weapon' => 'dagger',
      'cleric_spells' => [
        '1' => 'grim-tendrils',
        '3' => 'ghostly-weapon',
        '5' => 'call-spirit',
      ],
      'divine_ability' => ['WIS', 'CHA'],
    ],
    [
      'id' => 'rovagug',
      'name' => 'Rovagug',
      'alignment' => 'CE',
      'edicts' => [
        'Destroy all things',
        'Free the Rough Beast from his prison',
        'Revel in death and carnage',
      ],
      'anathema' => [
        'Create something new',
        'Show mercy',
        'Preserve something from destruction',
      ],
      'domains' => [
        'primary' => ['air', 'destruction', 'earth', 'swarm', 'void'],
        'alternate' => ['darkness', 'plague', 'trickery'],
      ],
      'divine_font' => 'harm',
      'divine_skill' => 'Athletics',
      'favored_weapon' => 'greataxe',
      'cleric_spells' => [
        '1' => 'burning-hands',
        '2' => 'acid-arrow',
        '4' => 'fly',
      ],
      'divine_ability' => ['STR', 'CON'],
    ],
    [
      'id' => 'sarenrae',
      'name' => 'Sarenrae',
      'alignment' => 'NG',
      'edicts' => [
        'Destroy the irredeemably evil',
        'Offer redemption to those who seek it',
        'Protect the innocent',
      ],
      'anathema' => [
        'Create or spread undead',
        'Deny healing to a good creature',
        'Lie or deceive',
      ],
      'domains' => [
        'primary' => ['fire', 'healing', 'sun', 'truth'],
        'alternate' => ['ambition', 'destruction', 'light'],
      ],
      'divine_font' => 'heal',
      'divine_skill' => 'Medicine',
      'favored_weapon' => 'scimitar',
      'cleric_spells' => [
        '1' => 'burning-hands',
        '3' => 'fireball',
        '4' => 'fire-shield',
      ],
      'divine_ability' => ['WIS', 'CHA'],
    ],
    [
      'id' => 'shelyn',
      'name' => 'Shelyn',
      'alignment' => 'NG',
      'edicts' => [
        'Beautify the world',
        'Seek out and create art',
        'Fall in love',
        'Protect love when threatened',
      ],
      'anathema' => [
        'Destroy something beautiful without necessity',
        'Refuse to put forth your best effort in an artistic endeavor',
        'Attack a creature that has offered no offense',
      ],
      'domains' => [
        'primary' => ['creation', 'family', 'passion', 'protection'],
        'alternate' => ['freedom', 'healing', 'perfection'],
      ],
      'divine_font' => 'heal',
      'divine_skill' => 'Crafting',
      'favored_weapon' => 'glaive',
      'cleric_spells' => [
        '1' => 'charm',
        '3' => 'enthrall',
        '4' => 'creation',
      ],
      'divine_ability' => ['WIS', 'CHA'],
    ],
    [
      'id' => 'torag',
      'name' => 'Torag',
      'alignment' => 'LG',
      'edicts' => [
        'Protect family, clan, and forge',
        'Follow and enforce Torag\'s codes of honor',
        'Create, build, and produce',
        'Oppose cheating and trickery',
      ],
      'anathema' => [
        'Become complacent in your craft',
        'Give insincere praise',
        'Fail to oppose evil',
      ],
      'domains' => [
        'primary' => ['cities', 'creation', 'earth', 'family', 'protection', 'toil'],
        'alternate' => ['cold', 'duty', 'fire', 'might'],
      ],
      'divine_font' => 'heal',
      'divine_skill' => 'Crafting',
      'favored_weapon' => 'warhammer',
      'cleric_spells' => [
        '1' => 'mindlink',
        '3' => 'earthbind',
        '4' => 'creation',
      ],
      'divine_ability' => ['STR', 'WIS'],
    ],
    [
      'id' => 'urgathoa',
      'name' => 'Urgathoa',
      'alignment' => 'NE',
      'edicts' => [
        'Become undead upon death',
        'Create undead minions',
        'Indulge your appetites',
      ],
      'anathema' => [
        'Deny your hunger (if undead)',
        'Destroy undead',
        'Prevent someone from indulging their urges',
      ],
      'domains' => [
        'primary' => ['indulgence', 'plague', 'undead'],
        'alternate' => ['darkness', 'death', 'might'],
      ],
      'divine_font' => 'harm',
      'divine_skill' => 'Medicine',
      'favored_weapon' => 'scythe',
      'cleric_spells' => [
        '1' => 'goblin-pox',
        '2' => 'false-life',
        '7' => 'finger-of-death',
      ],
      'divine_ability' => ['CON', 'WIS'],
    ],
  ];

  public function __construct(protected Connection $database) {}

  /**
   * Return all deities. Prefers DB table over seed catalog.
   *
   * @return array[]
   */
  public function getAll(): array {
    $rows = $this->loadFromDb();
    if (!empty($rows)) {
      return $rows;
    }
    return self::SEED_CATALOG;
  }

  /**
   * Return a single deity by ID.
   *
   * @param string $deity_id
   * @return array|null  Deity array or NULL if not found.
   */
  public function getById(string $deity_id): ?array {
    $rows = $this->loadFromDb();
    if (!empty($rows)) {
      foreach ($rows as $row) {
        if (($row['id'] ?? '') === $deity_id) {
          return $row;
        }
      }
      return NULL;
    }

    foreach (self::SEED_CATALOG as $deity) {
      if ($deity['id'] === $deity_id) {
        return $deity;
      }
    }
    return NULL;
  }

  /**
   * Validate that a deity ID exists in the catalog.
   *
   * @param string $deity_id
   * @return bool
   */
  public function isValid(string $deity_id): bool {
    return $this->getById($deity_id) !== NULL;
  }

  /**
   * Return all deities that have a given domain (primary or alternate).
   *
   * @param string $domain
   * @return array[]
   */
  public function getByDomain(string $domain): array {
    return array_values(array_filter($this->getAll(), function (array $d) use ($domain): bool {
      $all_domains = array_merge($d['domains']['primary'] ?? [], $d['domains']['alternate'] ?? []);
      return in_array($domain, $all_domains, TRUE);
    }));
  }

  /**
   * Resolve divine font type for a deity.
   *
   * @param string $deity_id
   * @return string|null  'heal', 'harm', 'both', or NULL if deity unknown.
   */
  public function getDivineFont(string $deity_id): ?string {
    $deity = $this->getById($deity_id);
    return $deity ? ($deity['divine_font'] ?? NULL) : NULL;
  }

  /**
   * Resolve favored weapon for a deity.
   *
   * @param string $deity_id
   * @return string|null  Equipment catalog slug or NULL.
   */
  public function getFavoredWeapon(string $deity_id): ?string {
    $deity = $this->getById($deity_id);
    return $deity ? ($deity['favored_weapon'] ?? NULL) : NULL;
  }

  /**
   * Return all domains (primary + alternate) for a deity.
   *
   * @param string $deity_id
   * @return string[]
   */
  public function getDomains(string $deity_id): array {
    $deity = $this->getById($deity_id);
    if (!$deity) {
      return [];
    }
    return array_unique(array_merge(
      $deity['domains']['primary'] ?? [],
      $deity['domains']['alternate'] ?? []
    ));
  }

  /**
   * Validate that a domain is valid (exists in DOMAINS constant).
   *
   * @param string $domain
   * @return bool
   */
  public function isDomainValid(string $domain): bool {
    return in_array($domain, self::DOMAINS, TRUE);
  }

  /**
   * Champion alignment compatibility check.
   *
   * Holy Champions (good-aligned) must choose a good-aligned deity.
   * Unholy Champions (evil-aligned) must choose an evil-aligned deity.
   *
   * @param string $deity_id
   * @param string $champion_alignment  Two-letter alignment code, e.g. 'LG'.
   * @return bool  TRUE if the combination is valid.
   */
  public function isChampionDeityCompatible(string $deity_id, string $champion_alignment): bool {
    $deity = $this->getById($deity_id);
    if (!$deity) {
      return FALSE;
    }
    $deity_alignment = $deity['alignment'] ?? 'N';
    $champion_is_good  = in_array($champion_alignment, ['LG', 'NG', 'CG'], TRUE);
    $champion_is_evil  = in_array($champion_alignment, ['LE', 'NE', 'CE'], TRUE);
    $deity_is_good     = strpos($deity_alignment, 'G') !== FALSE;
    $deity_is_evil     = strpos($deity_alignment, 'E') !== FALSE;

    if ($champion_is_good) {
      return $deity_is_good;
    }
    if ($champion_is_evil) {
      return $deity_is_evil;
    }
    // Neutral champion: any deity is permissible.
    return TRUE;
  }

  /**
   * Import/upsert a deity row into dc_deities table.
   *
   * Used by Drush import command and update hooks.
   *
   * @param array $deity  Single deity array (same structure as SEED_CATALOG).
   */
  public function upsert(array $deity): void {
    $this->database->merge('dc_deities')
      ->key(['deity_id' => $deity['id']])
      ->fields([
        'deity_id'       => $deity['id'],
        'name'           => $deity['name'],
        'alignment'      => $deity['alignment'],
        'edicts'         => json_encode($deity['edicts'] ?? []),
        'anathema'       => json_encode($deity['anathema'] ?? []),
        'domains'        => json_encode($deity['domains'] ?? ['primary' => [], 'alternate' => []]),
        'divine_font'    => $deity['divine_font'],
        'divine_skill'   => $deity['divine_skill'],
        'favored_weapon' => $deity['favored_weapon'] ?? '',
        'cleric_spells'  => json_encode($deity['cleric_spells'] ?? []),
        'divine_ability' => json_encode($deity['divine_ability'] ?? []),
        'changed'        => \Drupal::time()->getRequestTime(),
      ])
      ->execute();
  }

  /**
   * Import all seed catalog deities into DB.
   *
   * Safe to run multiple times (upsert semantics).
   */
  public function importSeedCatalog(): int {
    foreach (self::SEED_CATALOG as $deity) {
      $this->upsert($deity);
    }
    return count(self::SEED_CATALOG);
  }

  // -----------------------------------------------------------------------
  // Private helpers
  // -----------------------------------------------------------------------

  /**
   * Load deities from dc_deities table and decode JSON fields.
   *
   * @return array[]  Decoded rows in same shape as SEED_CATALOG, or [] if table is empty / missing.
   */
  private function loadFromDb(): array {
    try {
      $rows = $this->database->select('dc_deities', 'd')
        ->fields('d')
        ->orderBy('name')
        ->execute()
        ->fetchAllAssoc('deity_id', \PDO::FETCH_ASSOC);
    }
    catch (\Exception $e) {
      // Table may not exist yet (before update hook runs).
      return [];
    }

    if (empty($rows)) {
      return [];
    }

    return array_values(array_map(function (array $row): array {
      return [
        'id'             => $row['deity_id'],
        'name'           => $row['name'],
        'alignment'      => $row['alignment'],
        'edicts'         => json_decode($row['edicts'] ?? '[]', TRUE) ?? [],
        'anathema'       => json_decode($row['anathema'] ?? '[]', TRUE) ?? [],
        'domains'        => json_decode($row['domains'] ?? '{"primary":[],"alternate":[]}', TRUE) ?? ['primary' => [], 'alternate' => []],
        'divine_font'    => $row['divine_font'],
        'divine_skill'   => $row['divine_skill'],
        'favored_weapon' => $row['favored_weapon'],
        'cleric_spells'  => json_decode($row['cleric_spells'] ?? '{}', TRUE) ?? [],
        'divine_ability' => json_decode($row['divine_ability'] ?? '[]', TRUE) ?? [],
      ];
    }, $rows));
  }

}
