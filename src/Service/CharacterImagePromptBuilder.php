<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Builds guardrailed prompts for character portrait generation.
 */
class CharacterImagePromptBuilder {

  /**
   * Default negative prompt for portrait generation.
   */
  private const DEFAULT_NEGATIVE_PROMPT = 'text, letters, words, captions, subtitles, nameplate, watermark, logo, signature, UI overlay, speech bubble, blurry, low quality, deformed, cropped feet, cropped legs, close-up portrait, headshot, bust shot';

  /**
   * Builds a provider-ready portrait prompt from character data.
   *
   * @param array $character_data
   *   Character data payload.
   * @param string $user_prompt
   *   Optional user-provided prompt guidance.
   *
   * @return string
   *   The prompt text.
   */
  public function buildPortraitPrompt(array $character_data, string $user_prompt = ''): string {
    $authoritative_ancestry = $this->buildAncestryLine($character_data);
    $lines = [
      'Create a high-fantasy full-body character portrait for a tabletop RPG.',
      'Show the entire character from head to toe with a fully rendered environmental background.',
      'Do not render any text, letters, names, captions, runes, logos, watermarks, signatures, or interface elements anywhere in the image.',
      'Keep a clear silhouette, consistent lighting, visible gear, and game-ready detail.',
      'Use the character attributes below to inform the outfit, pose, equipment, deity motifs, moral tone, and background scenery.',
    ];

    if ($authoritative_ancestry !== '') {
      $lines[] = 'Authoritative ancestry: ' . $authoritative_ancestry . '. If any other note conflicts, follow this ancestry and do not add physical traits from a different ancestry.';
    }

    $ability_guidance = $this->buildAbilityAppearanceGuidance($character_data['abilities'] ?? []);
    if ($ability_guidance !== '') {
      $lines[] = 'Appearance weighting:';
      $lines[] = $ability_guidance;
    }

    $attribute_lines = $this->buildAttributeLines($character_data);
    if (!empty($attribute_lines)) {
      $lines[] = 'Character attributes:';
      $lines = array_merge($lines, $attribute_lines);
    }

    $resolved_user_prompt = trim($user_prompt);
    if ($resolved_user_prompt !== '') {
      $lines[] = 'User direction:';
      $lines[] = $resolved_user_prompt;
    }

    return implode("\n", $lines);
  }

  /**
   * Returns the default negative prompt.
   */
  public function getDefaultNegativePrompt(): string {
    return self::DEFAULT_NEGATIVE_PROMPT;
  }

  /**
   * Builds a list of character attribute lines.
   *
   * @param array $character_data
   *   Character data payload.
   *
   * @return array
   *   Prompt-ready attribute lines.
   */
  private function buildAttributeLines(array $character_data): array {
    $lines = [];
    $authoritative_ancestry = $this->buildAncestryLine($character_data);
    $map = [
      'Ancestry' => $authoritative_ancestry,
      'Class' => $this->buildClassLine($character_data),
      'Background' => $this->humanizeShortValue($this->extractScalarValue($character_data, [['background']])),
      'Alignment' => $this->extractScalarValue($character_data, [['alignment'], ['personality', 'alignment']]),
      'Deity' => $this->humanizeShortValue($this->extractScalarValue($character_data, [['deity'], ['personality', 'deity']])),
      'Age' => $this->extractScalarValue($character_data, [['age'], ['personality', 'age']]),
      'Gender/Pronouns' => $this->extractScalarValue($character_data, [['gender'], ['personality', 'gender']]),
      'Concept' => $this->sanitizeAncestryConflicts($this->extractScalarValue($character_data, [['concept']]), $authoritative_ancestry),
      'Appearance' => $this->sanitizeAncestryConflicts($this->extractScalarValue($character_data, [['appearance'], ['personality', 'appearance']]), $authoritative_ancestry),
      'Personality' => $this->extractScalarValue($character_data, [['personality'], ['personality', 'personality']]),
      'Backstory' => $this->sanitizeAncestryConflicts($this->extractScalarValue($character_data, [['backstory'], ['personality', 'backstory']]), $authoritative_ancestry),
      'Visible equipment' => $this->buildEquipmentLine($character_data),
    ];

    foreach ($map as $label => $value) {
      if ($value !== '') {
        $lines[] = '- ' . $label . ': ' . $this->truncateValue($value);
      }
    }

    $ability_line = $this->buildAbilityLine($character_data['abilities'] ?? []);
    if ($ability_line !== '') {
      $lines[] = "- Abilities: {$ability_line}";
    }

    return $lines;
  }

  /**
   * Builds ability-informed appearance guidance for portrait generation.
   *
   * Charisma dominates the overall visual impression. Other abilities only add
   * subtle secondary cues.
   *
   * @param array $abilities
   *   Ability map.
   *
   * @return string
   *   Prompt line or empty string.
   */
  private function buildAbilityAppearanceGuidance(array $abilities): string {
    $normalized = $this->normalizeAbilities($abilities);
    if (empty($normalized)) {
      return '';
    }

    $charisma = $normalized['cha'] ?? 10;
    $strength = $normalized['str'] ?? 10;
    $dexterity = $normalized['dex'] ?? 10;
    $constitution = $normalized['con'] ?? 10;
    $intelligence = $normalized['int'] ?? 10;
    $wisdom = $normalized['wis'] ?? 10;

    $charisma_descriptor = $this->describeAbility($charisma, [
      'very plain and socially unassuming',
      'plain and modest in presence',
      'ordinary and approachable',
      'pleasant and likable',
      'strikingly attractive and magnetic',
      'exceptionally captivating, beautiful, and unforgettable',
    ]);
    $strength_descriptor = $this->describeAbility($strength, [
      'slight and physically frail',
      'lean and not especially imposing',
      'physically average',
      'fit and capable',
      'powerfully built',
      'heroically powerful in build',
    ]);
    $dexterity_descriptor = $this->describeAbility($dexterity, [
      'stiff and somewhat awkward in bearing',
      'a little rigid in movement',
      'balanced and natural in posture',
      'light and agile',
      'graceful and precise',
      'almost impossibly graceful and fluid',
    ]);
    $constitution_descriptor = $this->describeAbility($constitution, [
      'fragile and weathered',
      'slightly delicate',
      'healthy and ordinary',
      'hardy and resilient',
      'rugged and durable',
      'iron-hardy and exceptionally robust',
    ]);
    $intelligence_descriptor = $this->describeAbility($intelligence, [
      'simple and unstudied presentation',
      'plain, practical presentation',
      'unremarkable presentation',
      'thoughtful and attentive presentation',
      'clever, refined presentation',
      'keen, brilliant, highly refined presentation',
    ]);
    $wisdom_descriptor = $this->describeAbility($wisdom, [
      'naive and unfocused gaze',
      'somewhat unseasoned expression',
      'ordinary, neutral expression',
      'grounded and observant gaze',
      'perceptive and seasoned expression',
      'deeply insightful, calm, and perceptive presence',
    ]);

    return 'Use the Pathfinder-style ability scale from 3 to 18, where 18 is near-perfect. Weight visual impression roughly 50% from Charisma and 10% each from Strength, Dexterity, Constitution, Intelligence, and Wisdom. Charisma should dominate attractiveness, facial beauty, expression, confidence, and social magnetism. The other abilities should only add subtle cues to build, posture, movement, resilience, styling, and gaze. For this character: Charisma suggests ' . $charisma_descriptor . '; Strength suggests ' . $strength_descriptor . '; Dexterity suggests ' . $dexterity_descriptor . '; Constitution suggests ' . $constitution_descriptor . '; Intelligence suggests ' . $intelligence_descriptor . '; Wisdom suggests ' . $wisdom_descriptor . '. Keep non-Charisma influence noticeable but secondary.';
  }

  /**
   * Builds a compact ability summary line.
   *
   * @param array $abilities
   *   Ability map.
   *
   * @return string
   *   Summary line or empty string.
   */
  private function buildAbilityLine(array $abilities): string {
    $normalized = $this->normalizeAbilities($abilities);
    if (empty($normalized)) {
      return '';
    }

    $order = ['str', 'dex', 'con', 'int', 'wis', 'cha'];
    $parts = [];
    foreach ($order as $key) {
      if (!array_key_exists($key, $normalized)) {
        continue;
      }
      $value = is_numeric($normalized[$key]) ? (int) $normalized[$key] : NULL;
      if ($value === NULL) {
        continue;
      }
      $parts[] = strtoupper($key) . ' ' . $value;
    }

    return implode(', ', $parts);
  }

  /**
   * Normalizes a value to a trimmed string.
   */
  private function stringValue($value): string {
    if (!is_scalar($value)) {
      return '';
    }

    return trim((string) $value);
  }

  /**
   * Extracts the first non-empty scalar value from a list of nested paths.
   *
   * @param array $character_data
   *   Character payload.
   * @param array<int, array<int, string>> $paths
   *   Candidate key paths.
   */
  private function extractScalarValue(array $character_data, array $paths): string {
    foreach ($paths as $path) {
      $value = $character_data;
      foreach ($path as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) {
          $value = NULL;
          break;
        }
        $value = $value[$key];
      }

      $normalized = $this->stringValue($value);
      if ($normalized !== '') {
        return $normalized;
      }
    }

    return '';
  }

  /**
   * Humanizes short tag-like values such as scholar or old-faith.
   */
  private function humanizeShortValue(string $value): string {
    $value = trim($value);
    if ($value === '') {
      return '';
    }
    if (preg_match('/^[A-Z]{1,4}$/', $value)) {
      return $value;
    }
    if (preg_match('/[A-Z]/', $value) || str_contains($value, ' ')) {
      return $value;
    }

    return ucwords(str_replace(['_', '-'], ' ', $value));
  }

  /**
   * Builds ancestry and heritage guidance.
   */
  private function buildAncestryLine(array $character_data): string {
    $ancestry = $this->humanizeShortValue($this->extractScalarValue($character_data, [['ancestry']]));
    $heritage = $this->humanizeShortValue($this->extractScalarValue($character_data, [['heritage']]));
    if ($ancestry === '') {
      return $heritage;
    }
    if ($heritage === '') {
      return $ancestry;
    }
    return $ancestry . ' (' . $heritage . ')';
  }

  /**
   * Builds class and subclass guidance.
   */
  private function buildClassLine(array $character_data): string {
    $class = $this->humanizeShortValue($this->extractScalarValue($character_data, [['class']]));
    $subclass = $this->humanizeShortValue($this->extractScalarValue($character_data, [['subclass']]));
    if ($class === '') {
      return $subclass;
    }
    if ($subclass === '') {
      return $class;
    }
    return $class . ' (' . $subclass . ')';
  }

  /**
   * Builds a concise list of visible gear to influence outfit and silhouette.
   */
  private function buildEquipmentLine(array $character_data): string {
    $inventory = is_array($character_data['inventory'] ?? NULL) ? $character_data['inventory'] : [];
    $items = [];

    foreach (['worn', 'carried'] as $bucket) {
      foreach (($inventory[$bucket] ?? []) as $item) {
        if (!is_array($item)) {
          continue;
        }

        $name = $this->humanizeShortValue($this->stringValue($item['name'] ?? ($item['id'] ?? '')));
        if ($name === '') {
          continue;
        }

        $quantity = is_numeric($item['quantity'] ?? NULL) ? (int) $item['quantity'] : 1;
        $label = $quantity > 1 ? $name . ' x' . $quantity : $name;
        if (!in_array($label, $items, TRUE)) {
          $items[] = $label;
        }

        if (count($items) >= 8) {
          break 2;
        }
      }
    }

    return implode(', ', $items);
  }

  /**
   * Keeps verbose narrative fields prompt-safe.
   */
  private function truncateValue(string $value, int $limit = 280): string {
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if (strlen($value) <= $limit) {
      return $value;
    }

    return rtrim(substr($value, 0, $limit - 1)) . '…';
  }

  /**
   * Prevent freeform text from overriding the authoritative ancestry.
   */
  private function sanitizeAncestryConflicts(string $value, string $authoritative_ancestry): string {
    $value = trim($value);
    $authoritative_ancestry = strtolower(trim(preg_replace('/\s*\(.*$/', '', $authoritative_ancestry)));
    if ($value === '' || $authoritative_ancestry === '') {
      return $value;
    }

    $conflicting_terms = [
      'human',
      'elf',
      'elven',
      'dwarf',
      'dwarven',
      'gnome',
      'gnomish',
      'goblin',
      'halfling',
      'orc',
      'orcish',
      'leshy',
      'catfolk',
      'kobold',
      'ratfolk',
      'tengu',
    ];

    foreach ($conflicting_terms as $term) {
      if ($term === $authoritative_ancestry) {
        continue;
      }
      $value = preg_replace('/\b' . preg_quote($term, '/') . '\b/i', $authoritative_ancestry, $value) ?? $value;
    }

    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
  }

  /**
   * Normalizes abilities to the standard PF2e short keys.
   *
   * @param array $abilities
   *   Ability map.
   *
   * @return array<string, int>
   *   Normalized map.
   */
  private function normalizeAbilities(array $abilities): array {
    if (!is_array($abilities)) {
      return [];
    }

    $mapping = [
      'str' => ['str', 'strength'],
      'dex' => ['dex', 'dexterity'],
      'con' => ['con', 'constitution'],
      'int' => ['int', 'intelligence'],
      'wis' => ['wis', 'wisdom'],
      'cha' => ['cha', 'charisma'],
    ];

    $normalized = [];
    foreach ($mapping as $target => $aliases) {
      foreach ($aliases as $alias) {
        if (!array_key_exists($alias, $abilities) || !is_numeric($abilities[$alias])) {
          continue;
        }

        $value = (int) $abilities[$alias];
        $normalized[$target] = max(3, min(18, $value));
        break;
      }
    }

    return $normalized;
  }

  /**
   * Maps an ability score to a descriptive band.
   *
   * @param int $score
   *   Score on a 3-18 scale.
   * @param array<int, string> $bands
   *   Six descriptive bands from lowest to highest.
   *
   * @return string
   *   Descriptor.
   */
  private function describeAbility(int $score, array $bands): string {
    $score = max(3, min(18, $score));
    if ($score <= 5) {
      return $bands[0] ?? '';
    }
    if ($score <= 8) {
      return $bands[1] ?? '';
    }
    if ($score <= 12) {
      return $bands[2] ?? '';
    }
    if ($score <= 15) {
      return $bands[3] ?? '';
    }
    if ($score <= 17) {
      return $bands[4] ?? '';
    }

    return $bands[5] ?? '';
  }

}
