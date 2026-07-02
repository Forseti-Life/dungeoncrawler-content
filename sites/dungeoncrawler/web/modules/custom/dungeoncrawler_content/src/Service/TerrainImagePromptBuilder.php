<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Builds guardrailed prompts for terrain and habitat image generation.
 */
class TerrainImagePromptBuilder {

  /**
   * Default negative prompt for terrain generation.
   */
  private const DEFAULT_NEGATIVE_PROMPT = 'text, watermark, logo, signature, UI, frame, blurry, low quality, deformed';

  /**
   * Builds a provider-ready prompt from terrain payload attributes.
   *
   * @param array $payload
   *   Terrain generation attributes.
   *
   * @return string
   *   The prompt text.
   */
  public function buildTerrainPrompt(array $payload): string {
    $entity_type = $this->stringValue($payload['entity_type'] ?? 'floortile');

    $lines = [
      'Create a hexmap-ready terrain image for a tactical RPG.',
      'No text, logos, watermarks, or copyrighted characters.',
      'Readable at 64-128 px with clean, consistent lighting.',
      'Keep surface detail controlled and game-ready.',
      'Entity type: ' . ($entity_type !== '' ? $entity_type : 'floortile'),
    ];

    $lines = array_merge($lines, $this->buildRenderLines($payload, $entity_type));
    $lines = array_merge($lines, $this->buildContextLines($payload));

    return implode("\n", $lines);
  }

  /**
   * Returns the default negative prompt.
   */
  public function getDefaultNegativePrompt(): string {
    return self::DEFAULT_NEGATIVE_PROMPT;
  }

  /**
   * Build render specification lines.
   *
   * @param array $payload
   *   Prompt payload.
   * @param string $entity_type
   *   Entity type.
   *
   * @return array
   *   Render lines.
   */
  private function buildRenderLines(array $payload, string $entity_type): array {
    $lines = ['Render specs:'];

    $view = $this->stringValue($payload['view'] ?? '');
    if ($view === '') {
      $view = $this->defaultViewForEntity($entity_type);
    }
    if ($view !== '') {
      $lines[] = '- Camera: ' . $view;
    }

    $aspect_ratio = $this->stringValue($payload['aspect_ratio'] ?? '');
    if ($aspect_ratio !== '') {
      $lines[] = '- Aspect ratio: ' . $aspect_ratio;
    }

    $resolution = $payload['resolution'] ?? NULL;
    if (is_numeric($resolution)) {
      $lines[] = '- Resolution: ' . (int) $resolution . 'px';
    }

    $tileable = $this->normalizeBoolean($payload['tileable'] ?? NULL);
    if ($tileable !== NULL) {
      $lines[] = '- Tileable edges: ' . ($tileable ? 'yes' : 'no');
    }

    $background = $this->stringValue($payload['background'] ?? '');
    if ($background === '') {
      $background = $this->defaultBackgroundForEntity($entity_type);
    }
    if ($background !== '') {
      $lines[] = '- Background: ' . $background;
    }

    return $lines;
  }

  /**
   * Build narrative context lines from payload.
   *
   * @param array $payload
   *   Prompt payload.
   *
   * @return array
   *   Context lines.
   */
  private function buildContextLines(array $payload): array {
    $lines = [];

    $sections = [
      'Campaign' => $payload['campaign'] ?? NULL,
      'Dungeon' => $payload['dungeon'] ?? NULL,
      'Room' => $payload['room'] ?? NULL,
      'Habitat' => $payload['habitat'] ?? NULL,
      'Creature' => $payload['creature'] ?? NULL,
      'Terrain' => $payload['terrain'] ?? NULL,
    ];

    foreach ($sections as $label => $section) {
      $section_lines = $this->formatContextSection($label, $section);
      if (!empty($section_lines)) {
        $lines = array_merge($lines, $section_lines);
      }
    }

    $environment = $this->buildEnvironmentLines($payload);
    if (!empty($environment)) {
      $lines[] = 'Environment cues:';
      $lines = array_merge($lines, $environment);
    }

    return $lines;
  }

  /**
   * Format a labeled section into prompt lines.
   */
  private function formatContextSection(string $label, $section): array {
    if ($section === NULL) {
      return [];
    }

    $lines = [];
    if (is_string($section)) {
      $value = trim($section);
      if ($value !== '') {
        $lines[] = $label . ': ' . $value;
      }
      return $lines;
    }

    if (!is_array($section)) {
      return [];
    }

    $filtered = [];
    foreach ($section as $key => $value) {
      if (!is_scalar($value)) {
        continue;
      }
      $value = trim((string) $value);
      if ($value === '') {
        continue;
      }
      $filtered[] = $this->formatKeyValue($key, $value);
    }

    if (!empty($filtered)) {
      $lines[] = $label . ' details:';
      foreach ($filtered as $entry) {
        $lines[] = '- ' . $entry;
      }
    }

    return $lines;
  }

  /**
   * Build environment cue lines.
   */
  private function buildEnvironmentLines(array $payload): array {
    $keys = [
      'tile_id' => 'Tile ID',
      'category' => 'Category',
      'biome_theme' => 'Biome',
      'palette' => 'Palette',
      'mood' => 'Mood',
      'lighting' => 'Lighting',
      'weather' => 'Weather',
      'season' => 'Season',
      'time_of_day' => 'Time of day',
      'terrain_type' => 'Terrain type',
      'materials' => 'Materials',
      'flora' => 'Flora',
      'props' => 'Props',
      'hazards' => 'Hazards',
      'room_type' => 'Room type',
      'notes' => 'Notes',
    ];

    $lines = [];
    foreach ($keys as $key => $label) {
      if (!array_key_exists($key, $payload)) {
        continue;
      }
      $value = $this->stringValue($payload[$key]);
      if ($value !== '') {
        $lines[] = '- ' . $label . ': ' . $value;
      }
    }

    return $lines;
  }

  /**
   * Resolve a default view description based on entity type.
   */
  private function defaultViewForEntity(string $entity_type): string {
    $normalized = strtolower($entity_type);
    if (in_array($normalized, ['floortile', 'tile', 'terrain'], TRUE)) {
      return 'top-down orthographic';
    }

    return 'top-down 3/4';
  }

  /**
   * Resolve a default background instruction based on entity type.
   */
  private function defaultBackgroundForEntity(string $entity_type): string {
    $normalized = strtolower($entity_type);
    if (in_array($normalized, ['floortile', 'tile', 'terrain'], TRUE)) {
      return 'opaque, full-bleed';
    }

    return 'transparent PNG';
  }

  /**
   * Format key/value pairs for prompt readability.
   */
  private function formatKeyValue($key, string $value): string {
    $label = is_string($key) ? trim($key) : '';
    if ($label === '' || is_numeric($label)) {
      return $value;
    }

    return ucfirst(str_replace('_', ' ', $label)) . ': ' . $value;
  }

  /**
   * Normalize a boolean-like value.
   *
   * @return bool|null
   *   TRUE/FALSE when recognizable, NULL otherwise.
   */
  private function normalizeBoolean($value): ?bool {
    if (is_bool($value)) {
      return $value;
    }

    if (is_numeric($value)) {
      return ((int) $value) === 1;
    }

    if (is_string($value)) {
      $normalized = strtolower(trim($value));
      if (in_array($normalized, ['1', 'true', 'yes', 'on'], TRUE)) {
        return TRUE;
      }
      if (in_array($normalized, ['0', 'false', 'no', 'off'], TRUE)) {
        return FALSE;
      }
    }

    return NULL;
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

}
