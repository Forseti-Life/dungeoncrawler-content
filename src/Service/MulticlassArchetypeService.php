<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Multiclass Archetype system — PF2e CRB Chapter 3 + APG Chapter 3.
 *
 * Enforces dedication feat prerequisites (AC-001), breadth limit
 * (AC-004), class feat slot availability (AC-002, AC-003), and
 * source-tagging for APG integration (AC-005).
 *
 * Data sources:
 * - CharacterManager::MULTICLASS_ARCHETYPES — CRB + APG class multiclass
 *   dedications (source: 'CRB' | 'APG').
 * - CharacterManager::ARCHETYPES — APG general/non-multiclass archetypes
 *   (Acrobat, Archer, etc.); normalized and tagged source: 'APG' here.
 */
class MulticlassArchetypeService {

  // ── AC-001: Archetype catalog ───────────────────────────────────────────────

  /**
   * Return all multiclass archetypes, optionally filtered by source.
   *
   * Merges CharacterManager::MULTICLASS_ARCHETYPES (CRB + APG class
   * multiclass) with CharacterManager::ARCHETYPES (APG general archetypes,
   * tagged source: APG on the fly).
   *
   * @param string $source
   *   'CRB', 'APG', or 'all' (default).
   *
   * @return array
   *   Keyed archetype array (normalized to MULTICLASS_ARCHETYPES format).
   */
  public function getArchetypeCatalog(string $source = 'all'): array {
    $multiclass = CharacterManager::MULTICLASS_ARCHETYPES;

    // Merge APG general archetypes (CharacterManager::ARCHETYPES) tagged source APG.
    // These are non-multiclass archetypes from the APG (Acrobat, Archer, etc.).
    $apg_general = $this->normalizeApgArchetypes();

    $merged = array_merge($multiclass, $apg_general);

    if ($source === 'all') {
      return $merged;
    }
    return array_filter($merged, static fn(array $a): bool => ($a['source'] ?? '') === $source);
  }

  /**
   * Return the count of archetypes by source.
   *
   * Used to verify AC-001 (12 CRB) and AC-005 (>26 with APG).
   *
   * @param string $source
   *   'CRB', 'APG', or 'all'.
   *
   * @return int
   *   Number of archetypes.
   */
  public function countArchetypes(string $source = 'all'): int {
    return count($this->getArchetypeCatalog($source));
  }

  // ── AC-002 / AC-003: Eligible dedication feats for a character ───────────────

  /**
   * Return dedication feats available to a character.
   *
   * Filters by:
   * - Character level >= dedication minimum_dedication_level (always 2).
   * - Character does not already hold a dedication feat for this archetype.
   * - Second dedication only allowed after taking >= 2 archetype feats from
   *   first archetype (breadth rule — AC-004).
   *
   * @param array $char_data
   *   Full character_data JSON array.
   *
   * @return array
   *   List of available dedication feat entries.
   */
  public function getEligibleDedicationFeats(array $char_data): array {
    $level = (int) ($char_data['basicInfo']['level'] ?? 1);
    if ($level < 2) {
      return [];
    }

    $owned_feat_ids = array_column($char_data['features']['feats'] ?? [], 'id');
    $held_archetypes = $this->getHeldArchetypeIds($owned_feat_ids);

    // Breadth check: allow a second (different) archetype only when >= 2
    // archetype feats from the first are already taken.
    $second_dedication_allowed = $this->isSecondDedicationAllowed($owned_feat_ids, $held_archetypes);

    $eligible = [];
    foreach ($this->getArchetypeCatalog() as $archetype) {
      $dedication = $archetype['dedication'];
      $archetype_id = $archetype['id'];

      // Skip if already have this dedication.
      if (in_array($dedication['id'], $owned_feat_ids, TRUE)) {
        continue;
      }

      // Skip if level too low.
      if ($level < ($archetype['minimum_dedication_level'] ?? 2)) {
        continue;
      }

      // If already holds at least one dedication: only allow if breadth met.
      if (!empty($held_archetypes) && !$second_dedication_allowed) {
        continue;
      }

      $eligible[] = $dedication;
    }

    return $eligible;
  }

  // ── AC-003: Archetype feats available after dedication ──────────────────────

  /**
   * Return all archetype feats available to a character for a class feat slot.
   *
   * Includes only feats from archetypes the character has dedicated to.
   * Applies level prerequisite filter. Deduplicates against owned feats.
   *
   * @param array $char_data
   *   Full character_data JSON array.
   *
   * @return array
   *   Flat list of eligible archetype feat entries.
   */
  public function getEligibleArchetypeFeats(array $char_data): array {
    $level = (int) ($char_data['basicInfo']['level'] ?? 1);
    $owned_feat_ids = array_column($char_data['features']['feats'] ?? [], 'id');
    $held_archetypes = $this->getHeldArchetypeIds($owned_feat_ids);

    if (empty($held_archetypes)) {
      return [];
    }

    $eligible = [];
    foreach ($this->getArchetypeCatalog() as $archetype) {
      // Only include feats from archetypes the character is dedicated to.
      if (!in_array($archetype['id'], $held_archetypes, TRUE)) {
        continue;
      }

      foreach ($archetype['archetype_feats'] as $feat) {
        // Level prerequisite (AC-003).
        if (isset($feat['level']) && (int) $feat['level'] > $level) {
          continue;
        }
        // Already owned.
        if (in_array($feat['id'], $owned_feat_ids, TRUE)) {
          continue;
        }
        $eligible[] = $feat;
      }
    }

    return $eligible;
  }

  // ── AC-002 / AC-004: Dedication selection validation ─────────────────────────

  /**
   * Validate a dedication feat selection against all multiclass rules.
   *
   * Throws \InvalidArgumentException with HTTP-status code on failure.
   *
   * @param string $feat_id
   *   Dedication feat ID being selected.
   * @param array $char_data
   *   Full character_data JSON array.
   *
   * @return array
   *   The validated dedication feat entry.
   *
   * @throws \InvalidArgumentException
   */
  public function validateDedicationSelection(string $feat_id, array $char_data): array {
    $level = (int) ($char_data['basicInfo']['level'] ?? 1);
    $owned_feat_ids = array_column($char_data['features']['feats'] ?? [], 'id');

    // Find archetype for this dedication (searches MULTICLASS_ARCHETYPES + ARCHETYPES).
    $archetype = $this->findArchetypeByDedicationId($feat_id);
    if ($archetype === NULL) {
      throw new \InvalidArgumentException("Unknown multiclass dedication feat '{$feat_id}'", 400);
    }

    $dedication = $archetype['dedication'];

    // AC-002: minimum level 2.
    $min_level = $archetype['minimum_dedication_level'] ?? 2;
    if ($level < $min_level) {
      throw new \InvalidArgumentException(
        "Dedication feat '{$feat_id}' requires level {$min_level}; character is level {$level}", 400
      );
    }

    // AC-004: cannot re-take same archetype dedication.
    if (in_array($feat_id, $owned_feat_ids, TRUE)) {
      throw new \InvalidArgumentException(
        "Character already has dedication feat '{$feat_id}'", 409
      );
    }

    // AC-004: breadth rule — second dedication requires >= 2 feats from first.
    $held_archetypes = $this->getHeldArchetypeIds($owned_feat_ids);
    if (!empty($held_archetypes)) {
      if (!$this->isSecondDedicationAllowed($owned_feat_ids, $held_archetypes)) {
        throw new \InvalidArgumentException(
          'You must take at least 2 archetype feats from your current dedication before taking a second dedication.', 400
        );
      }
    }

    return $dedication;
  }

  // ── Helpers ─────────────────────────────────────────────────────────────────

  /**
   * Return the archetype IDs the character has dedicated to.
   *
   * @param array $owned_feat_ids
   *   Array of feat ID strings the character owns.
   *
   * @return string[]
   *   Array of archetype ID strings.
   */
  public function getHeldArchetypeIds(array $owned_feat_ids): array {
    $held = [];
    foreach ($this->getArchetypeCatalog() as $archetype) {
      if (in_array($archetype['dedication']['id'], $owned_feat_ids, TRUE)) {
        $held[] = $archetype['id'];
      }
    }
    return $held;
  }

  /**
   * Check whether the character may take a second (different) dedication.
   *
   * A second dedication is allowed when the character has taken >= 2
   * archetype feats from at least one of their existing dedications.
   *
   * @param array $owned_feat_ids
   * @param array $held_archetype_ids
   *
   * @return bool
   */
  protected function isSecondDedicationAllowed(array $owned_feat_ids, array $held_archetype_ids): bool {
    $catalog = $this->getArchetypeCatalog();
    foreach ($held_archetype_ids as $archetype_id) {
      $archetype = $catalog[$archetype_id] ?? NULL;
      if ($archetype === NULL) {
        continue;
      }
      $feat_ids_in_archetype = array_column($archetype['archetype_feats'], 'id');
      $taken_count = count(array_intersect($owned_feat_ids, $feat_ids_in_archetype));
      if ($taken_count >= 2) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Find an archetype entry by its dedication feat ID.
   *
   * Searches both CharacterManager::MULTICLASS_ARCHETYPES and the normalized
   * APG general archetypes from CharacterManager::ARCHETYPES.
   *
   * @param string $dedication_feat_id
   *
   * @return array|null
   */
  protected function findArchetypeByDedicationId(string $dedication_feat_id): ?array {
    foreach ($this->getArchetypeCatalog() as $archetype) {
      if (($archetype['dedication']['id'] ?? '') === $dedication_feat_id) {
        return $archetype;
      }
    }
    return NULL;
  }

  /**
   * Normalize CharacterManager::ARCHETYPES entries to MULTICLASS_ARCHETYPES format.
   *
   * APG general archetypes (Acrobat, Archer, etc.) use `feats` key and omit
   * `source`, `source_class`, and `minimum_dedication_level`. This method
   * normalizes them so they can be merged into the unified catalog.
   *
   * @return array
   *   Keyed array in MULTICLASS_ARCHETYPES format, all tagged source: 'APG'.
   */
  protected function normalizeApgArchetypes(): array {
    $normalized = [];
    foreach (CharacterManager::ARCHETYPES as $key => $archetype) {
      $normalized[$key] = [
        'id'                      => $archetype['id'],
        'name'                    => $archetype['name'],
        'source_class'            => NULL,
        'source'                  => 'APG',
        'minimum_dedication_level' => 2,
        'dedication'              => $archetype['dedication'],
        // Normalize 'feats' → 'archetype_feats' for structural consistency.
        'archetype_feats'         => $archetype['feats'] ?? [],
        // Preserve extra fields (type, etc.).
        'type'                    => $archetype['type'] ?? NULL,
      ];
    }
    return $normalized;
  }

}
