<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Extension\ModuleExtensionList;

/**
 * Non-LLM ancestry-aware random name generation.
 *
 * Uses an onset-nucleus-coda syllable generator backed by local JSON tables,
 * so name generation stays fast, deterministic when seeded, and offline.
 */
class NameGeneratorService {

  /**
   * Cached name profiles loaded from the module content directory.
   */
  protected ?array $profiles = NULL;

  public function __construct(
    protected readonly ModuleExtensionList $moduleList,
  ) {}

  /**
   * Generate a character or NPC name for an ancestry.
   */
  public function generate(string $ancestry = 'Human', ?int $seed = NULL, bool $allow_surname = TRUE): string {
    $profiles = $this->loadProfiles();
    $profile_key = $this->resolveProfileKey($ancestry, $profiles);
    $profile = $profiles[$profile_key] ?? $profiles['Human'] ?? reset($profiles);

    $seed ??= random_int(1, PHP_INT_MAX);
    $rng = new SeededRandomSequence($seed);

    $name = $this->buildNamePart($rng, $profile['given'] ?? []);
    $surname_chance = max(0, min(100, (int) ($profile['surname_chance'] ?? 0)));

    if ($allow_surname && !empty($profile['surname']) && $surname_chance > 0 && $rng->chance($surname_chance)) {
      $name .= ' ' . $this->buildNamePart($rng, $profile['surname']);
    }

    return $name;
  }

  /**
   * Generate a name and return the seed used.
   */
  public function generateWithSeed(string $ancestry = 'Human', ?int $seed = NULL, bool $allow_surname = TRUE): array {
    $seed ??= random_int(1, PHP_INT_MAX);
    return [
      'name' => $this->generate($ancestry, $seed, $allow_surname),
      'seed' => $seed,
    ];
  }

  /**
   * Build one given name or surname from a syllable profile.
   */
  protected function buildNamePart(SeededRandomSequence $rng, array $part): string {
    $syllables = $part['syllables'] ?? [2];
    $syllable_count = max(1, (int) $rng->pick(is_array($syllables) ? $syllables : [2]));
    $max_length = max(4, (int) ($part['max_length'] ?? 12));
    $attempts = 0;

    do {
      $value = '';
      for ($i = 0; $i < $syllable_count; $i++) {
        $value .= (string) $rng->pick($part['onsets'] ?? ['']);
        $value .= (string) $rng->pick($part['nuclei'] ?? ['a']);

        $is_final = $i === ($syllable_count - 1);
        $mid_coda_chance = max(0, min(100, (int) ($part['mid_coda_chance'] ?? 35)));
        if ($is_final || (!empty($part['codas']) && $rng->chance($mid_coda_chance))) {
          $value .= (string) $rng->pick($part['codas'] ?? ['']);
        }
      }

      $normalized = $this->normalizePart($value);
      $attempts++;
      if (strlen($normalized) <= $max_length || $syllable_count === 1 || $attempts >= 3) {
        return strlen($normalized) <= $max_length ? $normalized : substr($normalized, 0, $max_length);
      }

      $syllable_count--;
    } while (TRUE);
  }

  /**
   * Normalize generated parts into readable names.
   */
  protected function normalizePart(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/([^aeiou])\1{2,}/', '$1$1', $value);
    $value = preg_replace('/([aeiou])\1+/', '$1', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return ucfirst((string) $value);
  }

  /**
   * Resolve the best matching profile key for an ancestry.
   */
  protected function resolveProfileKey(string $ancestry, array $profiles): string {
    $canonical = CharacterManager::resolveAncestryCanonicalName($ancestry);
    if ($canonical !== '' && isset($profiles[$canonical])) {
      return $canonical;
    }

    foreach (array_keys($profiles) as $key) {
      if ($this->normalizeLookupKey($key) === $this->normalizeLookupKey($ancestry)) {
        return $key;
      }
    }

    return 'Human';
  }

  /**
   * Load generator profiles from the module content directory.
   */
  protected function loadProfiles(): array {
    if ($this->profiles !== NULL) {
      return $this->profiles;
    }

    $module_path = $this->moduleList->getPath('dungeoncrawler_content');
    $profile_path = $module_path . '/content/name_generation_profiles.json';
    $raw = file_get_contents($profile_path);
    $decoded = json_decode((string) $raw, TRUE);

    if (!is_array($decoded) || $decoded === []) {
      throw new \RuntimeException('Unable to load name generation profiles.');
    }

    unset($decoded['_meta']);
    $this->profiles = $decoded;
    return $this->profiles;
  }

  /**
   * Normalize ancestry/profile keys for loose matching.
   */
  protected function normalizeLookupKey(string $value): string {
    $value = strtolower(trim($value));
    $value = str_replace(['_', '-'], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return (string) $value;
  }

}
