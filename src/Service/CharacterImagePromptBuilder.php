<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Builds portrait prompts for character image generation.
 */
class CharacterImagePromptBuilder {

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
    $base_ancestry = $this->getBaseAncestry($authoritative_ancestry);
    $class = $this->buildClassLine($character_data);
    $background = $this->humanizeShortValue($this->extractScalarValue($character_data, [['background']]));
    $concept = $this->extractScalarValue($character_data, [['concept']]);
    $personality = $this->extractScalarValue($character_data, [['personality'], ['personality', 'personality']]);
    $equipment = $this->buildEquipmentLine($character_data);
    $ability_line = $this->buildAbilityLine($character_data['abilities'] ?? []);
    $ability_guidance = $this->buildAbilityAppearanceGuidance($character_data['abilities'] ?? []);
    $resolved_user_prompt = trim($user_prompt);
    $role = $this->resolvePortraitRole($character_data);
    $familiar_species = $this->resolveFamiliarSpeciesName($character_data);

    $subject = $this->buildSubjectPhrase($base_ancestry, $class, $background, $role, $familiar_species);
    $lines = [];
    $lines[] = 'Full-body portrait illustration of ' . $subject . ', standing alone with the entire body visible from head to toe.';

    if ($role === 'familiar') {
      $familiar_profile = $this->buildFamiliarPromptLine($character_data, $familiar_species);
      if ($familiar_profile !== '') {
        $lines[] = $familiar_profile;
      }
      $lines[] = 'Render a true animal body plan only: non-anthropomorphic anatomy, on all fours, realistic animal proportions, and natural facial structure.';
      $lines[] = 'Do not depict humanoid posture, human hands, human feet, human torso, clothing, armor, tools, or weapons.';
      $lines[] = 'Keep scene composition simple and clear with minimal background detail so the familiar remains the sole focal point.';
      $lines[] = 'Pure illustration only: no readable text, no labels, no signs, no runes, no spell circles, and no decorative borders.';

      if ($resolved_user_prompt !== '') {
        $lines[] = 'Additional art direction: ' . $this->truncateValue($resolved_user_prompt, 140) . '.';
      }

      return implode("\n", $lines);
    }

    $visual_traits = [];
    if ($equipment !== '') {
      $visual_traits[] = 'visible gear including ' . $equipment;
    }
    if (!empty($visual_traits)) {
      $lines[] = 'Use ' . implode(', ', $visual_traits) . '.';
    }

    if ($ability_line !== '') {
      $lines[] = 'Character abilities: ' . $ability_line . '.';
    }

    if ($ability_guidance !== '') {
      $lines[] = $ability_guidance;
    }

    $mood_line = $this->buildPortraitMoodLine($character_data, $concept, $personality);
    if ($mood_line !== '') {
      $lines[] = $mood_line;
    }

    $lines[] = 'The background should be grounded and context-appropriate, with subtle environmental cues and no symbolic text elements.';

    $lines[] = 'Pure illustration only: no readable text, no labels, no posters, no parchment sheets, no books or scrolls with writing, no signs, no runes, no spell circles, no side panels, and no decorative borders.';

    if ($resolved_user_prompt !== '') {
      $lines[] = 'Additional art direction: ' . $this->truncateValue($resolved_user_prompt, 140) . '.';
    }

    return implode("\n", $lines);
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
    $map = [
      'Ancestry' => $this->buildAncestryLine($character_data),
      'Class' => $this->buildClassLine($character_data),
      'Background' => $this->humanizeShortValue($this->extractScalarValue($character_data, [['background']])),
      'Alignment' => $this->extractScalarValue($character_data, [['alignment'], ['personality', 'alignment']]),
      'Deity' => $this->humanizeShortValue($this->extractScalarValue($character_data, [['deity'], ['personality', 'deity']])),
      'Age' => $this->extractScalarValue($character_data, [['age'], ['personality', 'age']]),
      'Gender/Pronouns' => $this->extractScalarValue($character_data, [['gender'], ['personality', 'gender']]),
      'Concept' => $this->extractScalarValue($character_data, [['concept']]),
      'Appearance' => $this->extractScalarValue($character_data, [['appearance'], ['personality', 'appearance']]),
      'Personality' => $this->extractScalarValue($character_data, [['personality'], ['personality', 'personality']]),
      'Backstory' => $this->extractScalarValue($character_data, [['backstory'], ['personality', 'backstory']]),
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

    return 'The character should read as ' . $charisma_descriptor . ', ' . $strength_descriptor . ', ' . $dexterity_descriptor . ', ' . $constitution_descriptor . ', ' . $intelligence_descriptor . ', with a ' . $wisdom_descriptor . '.';
  }

  /**
   * Builds a short mood-and-pose line without leaking narrative text.
   */
  private function buildPortraitMoodLine(array $character_data, string $concept, string $personality): string {
    $keywords = [];
    $source = strtolower(trim($concept . ' ' . $personality . ' ' . ($character_data['background'] ?? '') . ' ' . ($character_data['class'] ?? '')));

    $map = [
      'sly' => 'cunning',
      'sharp-tongued' => 'wry',
      'illusion' => 'mischievous',
      'illusions' => 'mischievous',
      'enchantment' => 'self-possessed',
      'enchantments' => 'self-possessed',
      'scholar' => 'studious',
      'wizard' => 'intellectually focused',
      'confuse' => 'playful',
      'three steps ahead' => 'alert',
    ];

    foreach ($map as $needle => $label) {
      if (str_contains($source, $needle) && !in_array($label, $keywords, TRUE)) {
        $keywords[] = $label;
      }
    }

    if (empty($keywords)) {
      $keywords = ['confident', 'grounded', 'composed'];
    }

    $keywords = array_slice($keywords, 0, 4);
    return 'Expression and pose should feel ' . implode(', ', $keywords) . ', with a grounded, natural physical presence.';
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

  /**
   * Returns the base ancestry label without heritage.
   */
  private function getBaseAncestry(string $ancestry): string {
    return strtolower(trim(preg_replace('/\s*\(.*$/', '', $ancestry)));
  }

  /**
   * Builds a compact subject phrase for the image model.
   */
  private function buildSubjectPhrase(string $base_ancestry, string $class, string $background, string $role = '', string $familiar_species = ''): string {
    if ($role === 'familiar') {
      $species = trim($familiar_species) !== '' ? strtolower(trim($familiar_species)) : ($base_ancestry !== '' ? $base_ancestry : 'small magical');
      return 'a ' . $species . ' familiar companion';
    }

    $parts = [];

    if ($base_ancestry !== '') {
      $parts[] = 'an adult ' . $base_ancestry;
    }
    else {
      $parts[] = 'an adult humanoid character';
    }

    if ($class !== '') {
      $parts[] = strtolower($class);
    }
    if ($background !== '') {
      $parts[] = strtolower($background);
    }

    return implode(' ', $parts);
  }

  /**
   * Resolve the actor role used for portrait prompt specialization.
   */
  private function resolvePortraitRole(array $character_data): string {
    return strtolower(trim((string) (
      $character_data['role']
      ?? $character_data['follower_kind']
      ?? $character_data['familiar']['role']
      ?? ''
    )));
  }

  /**
   * Resolve familiar species display label from known familiar fields.
   */
  private function resolveFamiliarSpeciesName(array $character_data): string {
    $raw_species = trim((string) (
      $character_data['familiar_species_name']
      ?? $character_data['species_name']
      ?? $character_data['species']
      ?? ''
    ));
    if ($raw_species !== '') {
      return $this->humanizeShortValue($raw_species);
    }

    $familiar_type = strtolower(trim((string) (
      $character_data['familiar_type']
      ?? $character_data['familiar']['familiar_type']
      ?? ''
    )));
    if ($familiar_type !== '') {
      if ($familiar_type !== 'standard' && isset(FamiliarService::FAMILIAR_TYPES[$familiar_type]['name'])) {
        return (string) FamiliarService::FAMILIAR_TYPES[$familiar_type]['name'];
      }
      return $this->humanizeShortValue($familiar_type);
    }

    return '';
  }

  /**
   * Build a concise familiar descriptor line for portrait generation.
   */
  private function buildFamiliarPromptLine(array $character_data, string $species_name): string {
    $parts = [];
    if ($species_name !== '') {
      $parts[] = 'Species: ' . $species_name;
    }
    else {
      $parts[] = 'Species: Familiar';
    }

    $description = trim((string) (
      $character_data['description']
      ?? $character_data['familiar']['description']
      ?? ''
    ));
    if ($description !== '') {
      $parts[] = 'Description: ' . rtrim($this->truncateValue($description, 180), ". \t\n\r\0\x0B");
    }

    $ability_ids = [];
    $candidate_abilities = $character_data['abilities'] ?? $character_data['familiar']['abilities'] ?? NULL;
    if (is_array($candidate_abilities)) {
      $ability_ids = array_slice(array_values(array_filter(array_map(static function ($value): string {
        return strtolower(trim((string) $value));
      }, $candidate_abilities))), 0, 4);
    }
    if ($ability_ids !== []) {
      $parts[] = 'Abilities: ' . implode(', ', array_map([$this, 'humanizeShortValue'], $ability_ids));
    }

    return $parts !== [] ? 'Familiar profile — ' . implode('. ', $parts) . '.' : '';
  }

}
