<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Stateless helpers for character rules metadata.
 */
final class CharacterRulesUtility {

  /**
   * Resolves an ancestry machine ID (e.g. "half-elf") to canonical name.
   */
  public static function resolveAncestryCanonicalName(string $machine_id): string {
    if ($machine_id === '') {
      return '';
    }
    foreach (array_keys(CharacterRulesCatalog::ANCESTRIES) as $canonical) {
      if (strtolower(str_replace(' ', '-', $canonical)) === strtolower($machine_id)) {
        return $canonical;
      }
    }
    return '';
  }

  /**
   * Checks whether all required traits are present.
   *
   * @param string[] $character_traits
   * @param string[] $required_traits
   */
  public static function hasTraits(array $character_traits, array $required_traits): bool {
    foreach ($required_traits as $trait) {
      if (!in_array($trait, $character_traits, TRUE)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Validates a trait string against the canonical catalog.
   */
  public static function isValidTrait(string $trait): bool {
    return in_array($trait, CharacterRulesCatalog::TRAIT_CATALOG, TRUE);
  }

  /**
   * Merges traits idempotently.
   *
   * @param string[] $existing
   * @param string[] $new_traits
   *
   * @return string[]
   */
  public static function mergeTraits(array $existing, array $new_traits): array {
    return array_values(array_unique(array_merge($existing, $new_traits)));
  }

}

