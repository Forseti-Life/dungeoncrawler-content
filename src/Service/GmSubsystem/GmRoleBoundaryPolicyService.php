<?php

namespace Drupal\dungeoncrawler_content\Service\GmSubsystem;

/**
 * Canonical GM/player role-boundary validation and fallback text policy.
 */
class GmRoleBoundaryPolicyService {

  /**
   * Extract active player-character display name from character data.
   */
  public function extractPlayerCharacterName(?array $character_data): string {
    if (!is_array($character_data)) {
      return '';
    }

    $basic_info = is_array($character_data['basicInfo'] ?? NULL) ? $character_data['basicInfo'] : [];
    return trim((string) ($basic_info['name'] ?? $character_data['name'] ?? ''));
  }

  /**
   * Detect when GM narrative slips into player-character roleplay.
   *
   * @return string[]
   *   Stable error codes describing boundary violations.
   */
  public function validateNarrative(string $narrative, ?array $character_data): array {
    $trimmed = trim($narrative);
    if ($trimmed === '') {
      return [];
    }

    $errors = [];
    if (preg_match('/(^|[\s\(\["“‘])I(?:\'m| am|\'ve|\'ll|\'d)?\b/ui', $trimmed)
      || preg_match('/(^|[\s\(\["“‘])(?:me|my|mine)\b/ui', $trimmed)) {
      $errors[] = 'gm_role_boundary_first_person_voice';
    }

    $player_character_name = $this->extractPlayerCharacterName($character_data);
    if ($player_character_name !== '') {
      $escaped_name = preg_quote($player_character_name, '/');
      if (preg_match('/\b' . $escaped_name . '\b.{0,120}\b(?:say|says|said|ask|asks|asked|reply|replies|replied|lean|leans|leaned|gesture|gestures|gestured|grin|grins|grinned|smile|smiles|smiled|nod|nods|nodded|flash|flashes|flashed|brace|braces|braced|tap|taps|tapped|wave|waves|waved|look|looks|looked|keep|keeps|kept|drum|drums|drummed)\b/uis', $trimmed)
        || preg_match('/\b' . $escaped_name . '\b.{0,120}(?:["“]|\'[A-Za-z])/uis', $trimmed)) {
        $errors[] = 'gm_role_boundary_player_character_roleplay';
      }
    }

    if (preg_match('/^\s*(?:\*.*?\*|(?:He|She|They)\s+(?:leans|braces|gestures|smiles|grins|nods|taps|waves|looks|keeps|drums|lets|takes|flashes)\b)/uis', $trimmed)) {
      $errors[] = 'gm_role_boundary_staged_in_world_roleplay';
    }

    return array_values(array_unique($errors));
  }

  /**
   * Build retry prompt when GM speaks as player or in-world actor.
   */
  public function buildRetryPrompt(string $player_character_name, array $role_boundary_errors): string {
    $character_label = $player_character_name !== '' ? $player_character_name : 'the player character';
    $codes = implode(', ', array_values(array_unique($role_boundary_errors)));

    return "Your previous response violated the GM role boundary ({$codes})."
      . "\nRegenerate the entire response as the Game Master referee layer only."
      . "\nDo NOT speak as {$character_label}."
      . "\nDo NOT write first-person player-character dialogue, inner thoughts, body language, or staged in-world performance."
      . "\nDo NOT write dialogue for NPCs from the GM layer."
      . "\nReturn only grounded scene narration/adjudication from the GM perspective.";
  }

  /**
   * Safe fallback narrative when retries still cross GM/player boundary.
   */
  public function buildSafeFallbackNarrative(string $player_character_name = ''): string {
    return 'The scene remains grounded around you, with the visible room occupants and current situation still before you.';
  }

}

