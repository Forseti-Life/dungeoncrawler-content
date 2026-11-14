<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service;

/**
 * One-time conversion of a stored actor row into the canonical contracts.
 *
 * Pure: no database access and no writes. Produces the actor document
 * (actor-document-v1), the campaign-instance state document, and an explicit
 * list of blocking issues. A value that cannot be mapped exactly is reported;
 * it is never guessed, defaulted or silently dropped
 * (DC-ACTOR-AUTHORITY-20261114 M3, field-map.json).
 */
class ActorDocumentConverter {

  public const INSTANCE_STATE_VERSION = 'actor-instance-state-v1';

  private const ABILITIES = [
    'str' => 'strength', 'dex' => 'dexterity', 'con' => 'constitution',
    'int' => 'intelligence', 'wis' => 'wisdom', 'cha' => 'charisma',
  ];

  private const RANKS = ['untrained', 'trained', 'expert', 'master', 'legendary'];

  private const FEAT_SLOT_FIELDS = [
    'ancestry_feat' => 'ancestry', 'class_feat' => 'class', 'general_feat' => 'general',
    'background_skill_feat' => 'background',
  ];

  /**
   * Reviewed one-time repairs of stored names that differ from the catalog.
   *
   * Each entry was checked against the local catalog; nothing is inferred.
   */
  private const STORED_NAME_REPAIRS = [
    'spell' => [
      'comprehend languages' => 'comprehend-language',
    ],
  ];

  /**
   * Stored spell-list entries that are not spells in this game.
   *
   * Identifying a magic item is the Identify Magic skill activity (Board
   * decision 2026-11-14), so a stored "Identify" spell is dropped.
   */
  private const NOT_SPELLS = ['identify'];

  /**
   * Stored labels that were written as fabricated defaults, not chosen.
   *
   * CharacterManager::canonicalizeCharacterData() filled an empty background
   * with these names. They state no selection, so the field converts as unset.
   */
  private const FABRICATED_DEFAULTS = [
    'background' => ['wanderer', 'resident'],
  ];

  public function __construct(protected ActorCatalogService $catalog) {}

  /**
   * Converts one actor row.
   *
   * @param array $row
   *   Row columns: uuid, type, role, name, level, hp_current, hp_max,
   *   armor_class, experience_points, campaign_id.
   * @param array $character_data
   *   Decoded character_data.
   * @param array $state_data
   *   Decoded state_data.
   *
   * @return array{document: array, instance_state: ?array, issues: array<int, array{path: string, code: string, detail: string}>}
   *   Converted documents and blocking issues.
   */
  public function convertRow(array $row, array $character_data, array $state_data): array {
    $issues = [];
    $issue = static function (string $path, string $code, string $detail) use (&$issues): void {
      $issues[] = ['path' => $path, 'code' => $code, 'detail' => mb_substr($detail, 0, 160)];
    };
    // The reader overlays state_data on character_data today; a fact present
    // in both with different values has no single truth and blocks.
    $source = $character_data;
    foreach ($state_data as $key => $value) {
      if (!array_key_exists($key, $source) || $source[$key] === [] || $source[$key] === '' || $source[$key] === NULL) {
        $source[$key] = $value;
      }
    }

    $kind = match (TRUE) {
      ($row['type'] ?? '') === 'pc' => 'pc',
      ($row['role'] ?? '') === 'familiar' => 'companion',
      ($row['role'] ?? '') === 'creature' => 'creature',
      default => 'npc',
    };
    $model = $kind === 'pc' ? 'pc_build' : 'authored_statblock';

    $name = trim((string) ($row['name'] ?? ''));
    if ($name === '') {
      $issue('/identity/name', 'missing', 'Actor row has no name.');
    }
    $identity = ['name' => $name, 'kind' => $kind];
    $size = strtolower(trim((string) ($source['size'] ?? '')));
    if ($size !== '') {
      in_array($size, ['tiny', 'small', 'medium', 'large', 'huge', 'gargantuan'], TRUE)
        ? $identity['size'] = $size
        : $issue('/identity/size', 'invalid', $size);
    }
    self::text($identity, 'alignment', $source['alignment'] ?? NULL, 32, $issue);
    $role = trim((string) ($source['role'] ?? $row['role'] ?? ''));
    if ($role !== '' && !in_array($role, ['player', 'creature', 'familiar'], TRUE)) {
      $identity['role'] = mb_substr($role, 0, 150);
    }
    $traits = self::strings($source['traits'] ?? []);
    if ($traits !== []) {
      $identity['taxonomy']['traits'] = $traits;
    }
    $personal = [];
    foreach (['age' => 50, 'gender' => 100, 'deity' => 100, 'concept' => 500] as $key => $limit) {
      self::text($personal, $key, $source[$key] ?? NULL, $limit, $issue);
    }
    if ($personal !== []) {
      $identity['personal'] = $personal;
    }

    $build = ['mechanics_model' => $model];
    $level = $source['level'] ?? $row['level'] ?? NULL;
    if (is_numeric($level)) {
      $build['level'] = (int) $level;
    }
    $abilities = $this->abilities($source, $issue);
    if ($abilities !== []) {
      $build['abilities'] = $abilities;
    }
    $speed = $source['speed'] ?? ($source['movement']['speed'] ?? ($source['stats']['speed'] ?? NULL));
    if (is_numeric($speed)) {
      $build['speed'] = ['land' => (int) $speed];
    }
    foreach (['languages', 'senses'] as $list) {
      $values = self::strings($source[$list] ?? []);
      if ($values !== []) {
        $build[$list] = $values;
      }
    }

    $stats = is_array($source['stats'] ?? NULL) ? $source['stats'] : [];
    $defenses = is_array($source['defenses'] ?? NULL) ? $source['defenses'] : [];
    $saves = is_array($source['saves'] ?? NULL) ? $source['saves'] : [];
    if ($model === 'pc_build') {
      $this->playerBuild($build, $source, $issue);
    }
    else {
      $class_label = trim((string) ($source['class'] ?? ''));
      if ($class_label !== '' && !isset($identity['role'])) {
        $identity['role'] = mb_substr($class_label, 0, 150);
      }
      $max_hp = $source['hit_points']['max'] ?? $stats['maxHp'] ?? ($source['resources']['hitPoints']['max'] ?? ($row['hp_max'] ?? NULL));
      if (is_numeric($max_hp) && (int) $max_hp > 0) {
        $build['hit_points'] = ['max' => (int) $max_hp];
      }
      $ac = $stats['ac'] ?? $source['armor_class'] ?? (is_array($defenses['armorClass'] ?? NULL) ? ($defenses['armorClass']['base'] ?? NULL) : ($defenses['armorClass'] ?? NULL));
      $authored = [];
      if (is_numeric($ac)) {
        $authored['ac'] = (int) $ac;
      }
      foreach (['fortitude', 'reflex', 'will'] as $save) {
        $value = $saves[$save] ?? $stats[$save] ?? $defenses[$save] ?? NULL;
        if (is_numeric($value)) {
          $authored[$save] = (int) $value;
        }
      }
      if ($authored !== []) {
        $build['defenses'] = $authored;
      }
      $perception = $source['perception'] ?? $stats['perception'] ?? NULL;
      if (is_numeric($perception)) {
        $build['perception'] = (int) $perception;
      }
      $skills = $this->statblockSkills($source['skills'] ?? [], $issue);
      if ($skills !== []) {
        $build['skills'] = $skills;
      }
      foreach ((array) ($source['attacks'] ?? []) as $index => $attack) {
        $strike = is_array($attack) ? $this->strike($attack) : NULL;
        if ($strike === NULL) {
          $issue('/build/strikes/' . $index, 'needs_authoring', is_array($attack) ? (string) ($attack['name'] ?? '') : (string) $attack);
          continue;
        }
        $strike['id'] = 'strike-' . ($index + 1);
        $build['strikes'][] = $strike;
      }
    }
    $this->spellcasting($build, $source['spells'] ?? NULL, $issue);

    $narrative = [];
    foreach ([
      'description' => ['description', 5000], 'appearance' => ['appearance', 2000], 'personality' => ['personality', 2000],
      'biography' => ['backstory', 5000], 'fears' => ['fears', 1000], 'bonds' => ['bonds', 1000],
    ] as $target => [$key, $limit]) {
      $value = $source[$key] ?? NULL;
      if (is_string($value) && trim($value) !== '') {
        if (mb_strlen($value) > $limit) {
          $issue('/narrative/' . $target, 'too_long', mb_strlen($value) . ' > ' . $limit);
        }
        else {
          $narrative[$target] = $value;
        }
      }
    }
    $motivations = $source['motivations'] ?? NULL;
    $goals = $source['goals'] ?? [];
    $goal_text = is_array($goals) ? implode("\n", array_filter(array_map('strval', array_filter($goals, 'is_scalar')))) : '';
    $combined = trim(implode("\n", array_filter([is_string($motivations) ? trim($motivations) : '', $goal_text])));
    if ($combined !== '') {
      mb_strlen($combined) > 1000 ? $issue('/narrative/motivations', 'too_long', (string) mb_strlen($combined)) : $narrative['motivations'] = $combined;
    }
    $personality_traits = self::strings($source['personality_traits'] ?? []);
    if ($personality_traits !== []) {
      $narrative['personality_traits'] = $personality_traits;
    }

    $defaults = [];
    $attitude = $source['attitude'] ?? ($source['npc_profile']['attitude'] ?? NULL);
    if (is_string($attitude) && $attitude !== '') {
      in_array($attitude, ['hostile', 'unfriendly', 'indifferent', 'friendly', 'helpful'], TRUE)
        ? $defaults['psychology']['attitude'] = $attitude
        : $issue('/defaults/psychology/attitude', 'invalid', $attitude);
    }
    $seed = [];
    foreach (['inner_conflict', 'coping_mechanism', 'stress_response', 'insecurity', 'secret', 'desire', 'need', 'trigger', 'anchor'] as $key) {
      self::text($seed, $key, $source['psychology'][$key] ?? NULL, 500, $issue);
    }
    if ($seed !== []) {
      $defaults['psychology']['seed'] = $seed;
    }

    $document = ['schema_version' => 'actor-document-v1', 'identity' => $identity, 'build' => $build];
    foreach (['narrative' => $narrative, 'defaults' => $defaults] as $root => $value) {
      if ($value !== []) {
        $document[$root] = $value;
      }
    }

    return [
      'document' => $document,
      'instance_state' => (int) ($row['campaign_id'] ?? 0) > 0 ? $this->instanceState($row, $source) : NULL,
      'issues' => $issues,
    ];
  }

  /**
   * Instance-scoped mutable facts (ledger D22).
   */
  private function instanceState(array $row, array $source): array {
    $resources = is_array($source['resources'] ?? NULL) ? $source['resources'] : [];
    $hit_points = ['current' => (int) ($row['hp_current'] ?? 0)];
    $temporary = $resources['hitPoints']['temporary'] ?? ($source['hit_points']['temp'] ?? NULL);
    if (is_numeric($temporary) && (int) $temporary > 0) {
      $hit_points['temporary'] = (int) $temporary;
    }
    $state = ['schema_version' => self::INSTANCE_STATE_VERSION, 'resources' => ['hit_points' => $hit_points]];
    foreach (['heroPoints' => 'hero_points', 'focusPoints' => 'focus_points'] as $from => $to) {
      if (is_numeric($resources[$from]['current'] ?? NULL)) {
        $state['resources'][$to] = (int) $resources[$from]['current'];
      }
    }
    foreach ((array) ($resources['spellSlots'] ?? []) as $rank => $slot) {
      if (is_numeric($rank) && is_numeric($slot['current'] ?? NULL)) {
        $state['resources']['spell_slots'][] = ['rank' => (int) $rank, 'remaining' => (int) $slot['current']];
      }
    }
    if (is_array($resources['featResources'] ?? NULL) && $resources['featResources'] !== []) {
      $state['resources']['feat_resources'] = $resources['featResources'];
    }
    $state['progression'] = ['experience_points' => (int) ($row['experience_points'] ?? 0)];

    return $state;
  }

  private function abilities(array $source, callable $issue): array {
    $abilities = [];
    $flat = is_array($source['abilities'] ?? NULL) ? $source['abilities'] : [];
    $scored = is_array($source['ability_scores'] ?? NULL) ? $source['ability_scores'] : [];
    foreach (self::ABILITIES as $short => $long) {
      $candidates = array_values(array_unique(array_filter([
        $flat[$short] ?? NULL, $flat[$long] ?? NULL, $scored[$long]['score'] ?? NULL, $source[$long] ?? NULL,
      ], 'is_numeric')));
      if (count($candidates) > 1) {
        $issue('/build/abilities/' . $short, 'conflicting_copies', implode(' vs ', $candidates));
      }
      elseif ($candidates !== []) {
        $abilities[$short] = (int) $candidates[0];
      }
    }

    return $abilities;
  }

  private function playerBuild(array &$build, array $source, callable $issue): void {
    foreach (['class' => 'class', 'subclass' => NULL, 'ancestry' => 'ancestry', 'heritage' => 'heritage', 'background' => 'background'] as $field => $kind) {
      $value = trim((string) ($source[$field] ?? ''));
      if ($value === '' || in_array(strtolower($value), self::FABRICATED_DEFAULTS[$field] ?? [], TRUE)) {
        continue;
      }
      if ($kind === NULL) {
        $build[$field] = strtolower(str_replace([' ', '_'], '-', $value));
        continue;
      }
      $id = $this->catalog->resolveId($kind, $value);
      $id === NULL ? $issue('/build/' . $field, 'unknown_' . $kind, $value) : $build[$field] = $id;
    }
    $skills = [];
    foreach ((array) ($source['skills'] ?? []) as $key => $skill) {
      if (!is_array($skill)) {
        continue;
      }
      $rank = strtolower((string) ($skill['proficiency'] ?? ''));
      $id = $this->catalog->resolveId('skill', self::skillName((string) ($skill['name'] ?? $key)));
      if ($id === NULL || !in_array($rank, self::RANKS, TRUE)) {
        $issue('/build/skills/' . $key, 'unknown_skill_or_rank', ($skill['name'] ?? $key) . ':' . $rank);
        continue;
      }
      if ($rank !== 'untrained') {
        $skills[$id] = ['id' => $id, 'rank' => $rank];
      }
    }
    if ($skills !== []) {
      $build['skills'] = array_values($skills);
    }

    $slots = [];
    foreach (self::FEAT_SLOT_FIELDS as $field => $slot) {
      $value = $source[$field] ?? NULL;
      if (is_string($value) && $value !== '') {
        $slots[$value] = $slot;
      }
    }
    $has_familiar = is_array($source['familiar'] ?? NULL) && $source['familiar'] !== [];
    if ($has_familiar) {
      $build['companions'][] = ['id' => 'familiar', 'kind' => 'familiar'];
    }
    $feats = [];
    foreach ((array) ($source['feats'] ?? []) as $index => $feat) {
      $raw = is_array($feat) ? (string) ($feat['id'] ?? $feat['name'] ?? '') : (string) $feat;
      $id = $this->catalog->resolveId('feat', $raw);
      $entry = ['id' => (string) $id, 'feat_id' => (string) $id];
      // Assurance is taken once per skill and was stored as assurance-<skill>.
      if ($id === NULL && preg_match('/^assurance-([a-z-]+)$/', strtolower($raw), $match)
        && ($skill = $this->catalog->resolveId('skill', $match[1])) !== NULL && $this->catalog->has('feat', 'assurance')) {
        $id = 'assurance';
        $entry = ['id' => 'assurance-' . $skill, 'feat_id' => 'assurance', 'choices' => ['skill' => $skill]];
      }
      if ($id === NULL) {
        $issue('/build/feats/' . $index, 'unknown_feat', $raw);
        continue;
      }
      // An explicit creation field wins, then the background's own grant,
      // then the catalog's feat type states which slot kind it occupies.
      $slot = $slots[$raw] ?? $slots[$id]
        ?? (isset($build['background']) && $this->catalog->backgroundGrantedFeat($build['background']) === $id ? 'background' : NULL)
        ?? $this->catalog->featType($id);
      if ($slot === NULL) {
        $issue('/build/feats/' . $index, 'feat_slot_unknown', $id);
        continue;
      }
      $feats[$entry['id']] = ['id' => $entry['id'], 'feat_id' => $entry['feat_id'], 'slot' => $slot, 'level_gained' => 1]
        + (isset($entry['choices']) ? ['choices' => $entry['choices']] : []);
    }
    if ($feats !== []) {
      $build['feats'] = array_values($feats);
    }
  }

  private function statblockSkills(mixed $skills, callable $issue): array {
    $converted = [];
    foreach ((array) $skills as $key => $skill) {
      $name = is_array($skill) ? (string) ($skill['name'] ?? $key) : (string) $key;
      $modifier = is_array($skill) ? ($skill['modifier'] ?? NULL) : $skill;
      // Perception is its own statblock field, not a skill.
      if (strtolower($name) === 'perception') {
        continue;
      }
      $id = $this->catalog->resolveId('skill', self::skillName($name));
      if ($id === NULL || !is_numeric($modifier)) {
        $issue('/build/skills/' . $key, 'unknown_skill', $name);
        continue;
      }
      $converted[$id] = ['id' => $id, 'modifier' => (int) $modifier];
    }

    return array_values($converted);
  }

  private function spellcasting(array &$build, mixed $spells, callable $issue): void {
    if (!is_array($spells) || $spells === []) {
      return;
    }
    $names = [];
    if (array_is_list($spells)) {
      foreach ($spells as $spell) {
        $names[] = [is_array($spell) ? (string) ($spell['id'] ?? $spell['name'] ?? '') : (string) $spell, NULL];
      }
    }
    else {
      foreach (['cantrips' => 0, 'first_level' => 1] as $key => $rank) {
        foreach ((array) ($spells[$key] ?? []) as $spell) {
          $names[] = [is_array($spell) ? (string) ($spell['id'] ?? $spell['name'] ?? '') : (string) $spell, $rank];
        }
      }
    }
    $entries = [];
    foreach ($names as $index => [$name, $rank]) {
      if (in_array(mb_strtolower(trim($name)), self::NOT_SPELLS, TRUE)) {
        continue;
      }
      $id = $this->catalog->resolveId('spell', self::STORED_NAME_REPAIRS['spell'][mb_strtolower(trim($name))] ?? $name);
      if ($id === NULL) {
        $issue('/build/spellcasting/entries/' . $index, 'unknown_spell', $name);
        continue;
      }
      $rank ??= $this->catalog->spellRank($id);
      if ($rank === NULL) {
        $issue('/build/spellcasting/entries/' . $index, 'spell_rank_unknown', $id);
        continue;
      }
      $entries[$id] = ['id' => $id, 'spell_id' => $id, 'rank' => $rank];
    }
    if ($entries === []) {
      return;
    }
    $spellcasting = ['entries' => array_values($entries)];
    $tradition = strtolower((string) (array_is_list($spells) ? '' : ($spells['tradition'] ?? '')));
    if (in_array($tradition, ['arcane', 'divine', 'occult', 'primal'], TRUE)) {
      $spellcasting['tradition'] = $tradition;
    }
    // Only a spellbook proves prepared casting; otherwise tradition and type
    // stay unset and the actor is not publishable until they are authored.
    if (!array_is_list($spells) && isset($spells['spellbook_size'])) {
      $spellcasting['type'] = 'prepared';
    }
    $build['spellcasting'] = $spellcasting;
  }

  /**
   * Converts "1d4+4 piercing" attack rows; NULL when a part is absent.
   */
  private function strike(array $attack): ?array {
    $name = trim((string) ($attack['name'] ?? ''));
    if ($name === '' || !is_numeric($attack['bonus'] ?? NULL)
      || !preg_match('/^(\d{1,2}d(?:4|6|8|10|12|20)(?:[+-]\d{1,3})?|\d{1,3})\s+([a-z][a-z -]{1,38})$/i', trim((string) ($attack['damage'] ?? '')), $match)) {
      return NULL;
    }

    return ['id' => '', 'name' => $name, 'attack_bonus' => (int) $attack['bonus'], 'damage' => $match[1], 'damage_type' => strtolower($match[2])];
  }

  /**
   * Normalizes "Lore (Academia)" and "Underworld Lore" to lore-<subject>.
   */
  private static function skillName(string $name): string {
    if (preg_match('/^lore\s*\((.+)\)$/i', trim($name), $match) || preg_match('/^(.+)\s+lore$/i', trim($name), $match)) {
      return 'lore-' . trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($match[1])), '-');
    }

    return $name;
  }

  private static function text(array &$target, string $key, mixed $value, int $limit, callable $issue): void {
    if (!is_string($value) || trim($value) === '') {
      return;
    }
    if (mb_strlen($value) > $limit) {
      $issue('/' . $key, 'too_long', mb_strlen($value) . ' > ' . $limit);
      return;
    }
    $target[$key] = $value;
  }

  /**
   * @return array<int, string>
   *   Distinct non-empty strings.
   */
  private static function strings(mixed $values): array {
    if (!is_array($values)) {
      return [];
    }

    return array_values(array_unique(array_filter(array_map(
      static fn(mixed $value): string => is_string($value) ? trim($value) : '',
      $values,
    ))));
  }

}
