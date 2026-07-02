<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Contract for encounter AI providers.
 */
interface EncounterAiProviderInterface {

  /**
   * Recommend a non-player turn action based on encounter context.
   *
   * @param array<string, mixed> $context
   *   Encounter context payload.
   *
   * @return array<string, mixed>
   *   Recommendation envelope.
   */
  public function recommendNpcAction(array $context): array;

  /**
   * Generate flavor narration for the current encounter state.
   *
   * @param array<string, mixed> $context
   *   Encounter context payload.
   *
   * @return array<string, mixed>
   *   Narration payload.
   */
  public function generateEncounterNarration(array $context): array;

  /**
   * Return provider name for telemetry/debugging.
   */
  public function getProviderName(): string;

}
