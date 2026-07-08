<?php

namespace Drupal\dungeoncrawler_content\Service\RoomChat;

/**
 * Canonical builder for NPC prompt boundary and tool-surface text.
 */
final class NpcPromptBoundaryTextBuilder {

  /**
   * Build canonical player-surface capability prompt text.
   */
  public static function buildCapabilityPromptText(
    string $prefix = 'The tools available to you are only the same player-facing lookup and action surfaces available to a player character. '
  ): string {
    return $prefix
      . "Lookup tools: /api/spells, /api/spells/{spell_id}, /api/focus-spells, /api/feats, and /api/feats/{feat_id}. "
      . "Action tools: only the player action-bar / coordinator functions move or stride, strike, interact, talk, search, cast_spell, consume_item, skill actions, feat actions, navigate, and end_turn, as represented by GameCoordinatorApi sendAction()/move()/strike()/interact()/talk()/search()/castSpell()/endTurn(), the direct action-rail handlers executeDirectAttack/executeDirectNavigate/executeDirectInteract/executeDirectSpell/executeDirectConsumable/executeDirectSkill/executeDirectFeat, and the matching player routes /api/game/{campaign_id}/action, /api/character/{character_id}/cast-spell, /api/character/{character_id}/actions, and /api/character/{character_id}/inventory. "
      . "No GM-only, admin-only, campaign-state mutation, library mutation, or code-changing tool is available to you.\n";
  }

  /**
   * Build the system-prompt boundary clause for NPC dialogue calls.
   */
  public static function buildSystemBoundaryPromptClause(): string {
    return 'You have no authority to change campaign state, room state, character sheets, the content library, rules, or application code; '
      . 'you can only speak as this NPC using the same player-facing lookup and action surfaces available to a player character: '
      . '/api/spells, /api/spells/{spell_id}, /api/focus-spells, /api/feats, /api/feats/{feat_id}, and the player action-bar / coordinator actions move/stride, '
      . 'strike/attack, interact, talk, search, cast_spell, consume_item, skill, feat, navigate, and end_turn via GameCoordinatorApi and the matching player routes.';
  }

}
