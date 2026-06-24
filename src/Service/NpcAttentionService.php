<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Manages NPC attention and conversation focus mechanics.
 *
 * Implements a multi-factor attention allocation system where NPCs decide
 * whether and when to participate in conversations based on:
 * - Topic relevance to their interests/expertise
 * - Personality alignment with the speaker
 * - Recent interaction history
 * - Base charisma (natural talkativeness)
 * - Engagement fatigue
 *
 * Attention is persistent across turns, allowing NPCs to "stay dialed in"
 * to conversations and for players to build rapport.
 *
 * OPTION B: DEFENSIVE WITH VALIDATION PATTERN
 * ============================================
 * This service implements a defensive validation pattern that:
 * 1. Accepts optional parameters for backward compatibility
 * 2. Attempts to recover missing parameters from available context
 * 3. Hard-fails with explicit errors if contract cannot be satisfied
 *
 * Examples:
 *   - calculateAttentionScore: If player_speaker_id not passed, tries to use
 *     conversation_state['last_speaker']. If both missing, throws InvalidArgumentException.
 *   - validateNpcProfile: Throws exception if required profile fields missing.
 *
 * This ensures:
 *   ✅ Backward compatible (optional signatures)
 *   ✅ No silent failures (hard errors on contract violations)
 *   ✅ Forgiving (defensive recovery from available context)
 *   ✅ Clear errors (actionable error messages)
 *
 * DATA STRUCTURE CONTRACTS
 * ========================
 *
 * conversation_state array format:
 *   - last_speaker: (string) Entity ref of most recent speaker (e.g., "pc:123" or "npc:ref")
 *   - speaker_chain: (array) Full ordered list of all speakers in this conversation
 *   - recent_speakers: (array) Last 5 speakers with turn numbers for interaction bonus
 *   - current_topic: (string|NULL) Active conversation topic (quest, commerce, etc.)
 *   - topic_history: (array) History of topics discussed, keyed by topic name
 *   - engagement_scores: (array) Per-NPC fatigue penalties, keyed by entity_ref
 *   - topic_drift_penalty: (int) Penalty applied when topic changes abruptly
 *
 * npc_profile array format (passed from RoomChatService):
 *   - entity_ref: (string) Unique NPC identifier
 *   - ability_scores: (array) Abilities with at minimum 'charisma' key (int 1-20)
 *   - profile: (array) Contains 'display_name' (string)
 *   - attitude: (string, optional) One of: friendly, helpful, neutral, suspicious, hostile
 *   - personality_type: (string, optional) One of: talkative, quiet, gregarious, reserved
 *   - quest_leads: (array, optional) Quest topics this NPC can discuss
 *   - is_merchant: (bool, optional) Whether NPC sells items
 *   - is_guide: (bool, optional) Whether NPC provides navigation help
 *
 * game_state array format (passed from RoomChatService):
 *   - phase: (string) Current encounter/exploration phase
 *   - initiative_order: (array, optional) NPC initiative ranks for scoring
 */
class NpcAttentionService {

  /**
   * Initializes or retrieves conversation attention state for a room.
   *
   * @param array &$dungeon_data
   *   The dungeon data (modified in place).
   * @param int|string $room_index
   *   The room index in dungeon_data['rooms'].
   *
   * @return array
   *   The attention state array.
   */
  public function ensureConversationAttentionState(
    array &$dungeon_data,
    int|string $room_index
  ): array {
    $room_index = (int) $room_index;
    if (!isset($dungeon_data['rooms'][$room_index])) {
      return $this->initializeAttentionState();
    }

    $room = &$dungeon_data['rooms'][$room_index];
    if (!isset($room['conversation_state'])) {
      $room['conversation_state'] = $this->initializeAttentionState();
    }

    return $room['conversation_state'];
  }

  /**
   * Creates a fresh attention state structure.
   *
   * @return array
   *   Initialized attention state.
   */
  public function initializeAttentionState(): array {
    return [
      'primary_focus_npc' => NULL,
      'last_speaker' => NULL,
      'last_speaker_display_name' => '',
      'last_speaker_timestamp' => 0,
      'speaker_chain' => [],
      'recent_speakers' => [],
      'engagement_turn_started' => 0,
      'engagement_duration' => 0,
      'participants' => [],
      'current_topic' => NULL,
      'topic_keywords' => [],
      'topic_turn_started' => 0,
      'topic_drift_penalty' => 0,
      'engagement_scores' => [],
      'conversation_history_turns' => 0,
      'topic_history' => [],
    ];
  }

  /**
   * Records a speaker in conversation history.
   *
   * @param array &$conversation_state
   *   The attention state (modified in place).
   * @param string $speaker_id
   *   The speaker identifier (npc:ref or pc:id).
   * @param string $display_name
   *   The speaker's display name.
   * @param int $turn
   *   The current turn number.
   */
  public function recordSpeaker(
    array &$conversation_state,
    string $speaker_id,
    string $display_name,
    int $turn
  ): void {
    $speaker_id = trim($speaker_id);
    if ($speaker_id === '') {
      return;
    }

    // Update last speaker
    $conversation_state['last_speaker'] = $speaker_id;
    $conversation_state['last_speaker_display_name'] = trim($display_name);
    $conversation_state['last_speaker_timestamp'] = time();

    // Append to speaker chain
    $chain = (array) ($conversation_state['speaker_chain'] ?? []);
    $chain[] = $speaker_id;
    $conversation_state['speaker_chain'] = $chain;

    // Track recent speakers (last 5)
    $recent = (array) ($conversation_state['recent_speakers'] ?? []);
    $recent[] = [
      'speaker' => $speaker_id,
      'turn' => $turn,
      'display_name' => $display_name,
    ];
    if (count($recent) > 5) {
      array_shift($recent);
    }
    $conversation_state['recent_speakers'] = $recent;
  }

  /**
   * Detects the topic from a player message.
   *
   * Extracts keywords related to quests, merchants, navigation, combat, etc.
   *
   * @param string $message
   *   The player message to analyze.
   *
   * @return array
   *   ['topic' => 'identifier'|NULL, 'keywords' => [strings], 'confidence' => 0-100]
   */
  public function detectTopic(string $message): array {
    $normalized = strtolower(trim($message));
    $keywords = [];
    $topic = NULL;
    $confidence = 0;

    // Quest/mission keywords
    if (preg_match('/(quest|mission|task|objective|help.*with|need|seeking|looking for)/i', $message)) {
      $keywords[] = 'quest';
      $topic = 'quest';
      $confidence = max($confidence, 75);
    }

    // Merchant/commerce keywords
    if (preg_match('/(buy|sell|price|cost|trade|merchant|shop|goods|item)/i', $message)) {
      $keywords[] = 'commerce';
      if ($topic === NULL) {
        $topic = 'commerce';
        $confidence = 75;
      }
    }

    // Navigation/location keywords
    if (preg_match('/(where|location|room|chamber|vault|path|go.*to|travel|direction|navigate)/i', $message)) {
      $keywords[] = 'navigation';
      if ($topic === NULL) {
        $topic = 'navigation';
        $confidence = 60;
      }
    }

    // Combat/danger keywords
    if (preg_match('/(fight|combat|attack|danger|threat|enemy|monster|creature)/i', $message)) {
      $keywords[] = 'combat';
      if ($topic === NULL) {
        $topic = 'combat';
        $confidence = 70;
      }
    }

    // Social/gossip keywords
    if (preg_match('/(hello|greet|how.*are|thanks|thank you|please|story|tale|gossip)/i', $message)) {
      $keywords[] = 'social';
      if ($topic === NULL) {
        $topic = 'social';
        $confidence = 40;
      }
    }

    return [
      'topic' => $topic,
      'keywords' => $keywords,
      'confidence' => $confidence,
    ];
  }

  /**
   * Updates conversation topic and applies drift penalty if changed.
   *
   * @param array &$conversation_state
   *   The attention state (modified in place).
   * @param string $new_topic
   *   The newly detected topic.
   * @param int $turn
   *   The current turn.
   *
   * @return bool
   *   TRUE if topic changed, FALSE if same topic.
   */
  public function updateTopic(
    array &$conversation_state,
    string $new_topic,
    int $turn
  ): bool {
    $new_topic = trim($new_topic) ?: NULL;
    $current_topic = (string) ($conversation_state['current_topic'] ?? '');
    $current_topic = $current_topic === '' ? NULL : $current_topic;

    if ($new_topic === $current_topic) {
      return FALSE; // No change
    }

    // Topic changed: apply drift penalty
    if ($current_topic !== NULL && $new_topic !== NULL) {
      $conversation_state['topic_drift_penalty'] = min(
        40,
        ((int) ($conversation_state['topic_drift_penalty'] ?? 0)) + 15
      );
    }

    // Update topic history
    if ($new_topic !== NULL) {
      $history = (array) ($conversation_state['topic_history'] ?? []);
      // Check if last topic is same, just increment turn count
      if (!empty($history) && $history[count($history) - 1]['topic'] === $new_topic) {
        $history[count($history) - 1]['turns']++;
      } else {
        $history[] = [
          'topic' => $new_topic,
          'turns' => 1,
        ];
      }
      $conversation_state['topic_history'] = $history;
    }

    $conversation_state['current_topic'] = $new_topic;
    $conversation_state['topic_turn_started'] = $turn;

    return TRUE; // Topic changed
  }

  /**
   * Calculates attention score for an NPC in the current conversation.
   *
   * Combines topic relevance, personality alignment, recent interaction,
   * base charisma, fatigue, and initiative into a single attention score.
   *
   * @param array $npc_profile
   *   The NPC's profile data (includes ability scores, psychology).
   * @param array $conversation_state
   *   The conversation attention state.
   * @param string $player_message
   *   The player's message being responded to.
   * @param array $game_state
   *   Current game state (for initiative).
   * @param string $player_speaker_id
   *   The player character speaking (for personality alignment).
   *
   * @return array
   *   ['total_score' => int, 'component_scores' => array, 'qualified' => bool]
   */
  public function calculateAttentionScore(
    array $npc_profile,
    array $conversation_state,
    string $player_message,
    array $game_state,
    string $player_speaker_id = ''
  ): array {
    // Enforce data contract: validate NPC profile structure
    $this->validateNpcProfile($npc_profile);

    // VALIDATION: player_speaker_id is required for personality alignment scoring.
    // If empty (default), attempt to recover from conversation_state['last_speaker'].
    // If both missing, hard-fail with clear error message.
    if ($player_speaker_id === '') {
      $last_speaker = $conversation_state['last_speaker'] ?? '';
      if ($last_speaker === '') {
        throw new InvalidArgumentException(
          'player_speaker_id parameter is required for calculateAttentionScore() personality alignment. ' .
          'Speaker ID must be either: (1) passed as a parameter, or (2) available in conversation_state[\'last_speaker\']. ' .
          'Personality alignment cannot function without knowing the current speaker.'
        );
      }
      // Defensive recovery: use last_speaker from conversation_state since player_speaker_id wasn't provided.
      // This maintains backward compatibility while still calculating personality alignment.
      $player_speaker_id = $last_speaker;
    }

    $topic_relevance = $this->scoreTopicRelevance($npc_profile, $player_message);
    $personality_alignment = $this->scorePersonalityAlignment(
      $npc_profile,
      $conversation_state,
      $player_speaker_id
    );
    $recent_interaction = $this->scoreRecentInteraction($npc_profile, $conversation_state);
    $base_charisma = min(100, (int) ($npc_profile['ability_scores']['charisma'] ?? 10));
    $fatigue_penalty = $this->calculateFatiguePenalty($npc_profile, $conversation_state);
    $initiative_bonus = $this->getInitiativeBonus($npc_profile, $game_state);

    // Weighted calculation
    // Note: personality_alignment ranges -50~+50, so we shift by +50 to normalize to 0~100 range
    $total = (int) (
      (0.30 * $topic_relevance) +
      (0.25 * ($personality_alignment + 50)) +
      (0.15 * $recent_interaction) +
      (0.20 * $base_charisma) +
      (0.10 * $initiative_bonus)
      - $fatigue_penalty
    );

    $total = max(0, $total);

    return [
      'total_score' => $total,
      'component_scores' => [
        'topic_relevance' => $topic_relevance,
        'personality_alignment' => $personality_alignment,
        'recent_interaction' => $recent_interaction,
        'base_charisma' => $base_charisma,
        'initiative_bonus' => $initiative_bonus,
        'fatigue_penalty' => $fatigue_penalty,
      ],
      'qualified' => $total >= 40, // Threshold for LLM evaluation
    ];
  }

  /**
   * Scores topic relevance for an NPC.
   *
   * @param array $npc_profile
   *   NPC data.
   * @param string $player_message
   *   Player's message.
   *
   * @return int
   *   Score 0-100.
   */
  protected function scoreTopicRelevance(array $npc_profile, string $player_message): int {
    $score = 0;

    // Quest/mission relevance
    if (!empty($npc_profile['quest_leads'] ?? []) && preg_match('/(quest|mission|task|help)/i', $player_message)) {
      $score = max($score, 85);
    }

    // Merchant relevance
    if (!empty($npc_profile['is_merchant']) && preg_match('/(buy|sell|price|trade|shop)/i', $player_message)) {
      $score = max($score, 85);
    }

    // Navigation/guide relevance
    if (!empty($npc_profile['is_guide']) && preg_match('/(where|direction|location|go.*to)/i', $player_message)) {
      $score = max($score, 80);
    }

    // General social relevance
    if (preg_match('/(hello|greet|thanks|story|tale)/i', $player_message)) {
      $score = max($score, 40);
    }

    return $score;
  }

  /**
   * Scores personality alignment between NPC and current speaker.
   *
   * @param array $npc_profile
   *   NPC profile.
   * @param array $conversation_state
   *   Conversation state (contains recent speakers).
   * @param string $player_speaker_id
   *   The player speaking.
   *
   * @return int
   *   Score -50 to +50.
   */
  protected function scorePersonalityAlignment(
    array $npc_profile,
    array $conversation_state,
    string $player_speaker_id = ''
  ): int {
    $npc_attitude = strtolower(trim((string) ($npc_profile['attitude'] ?? 'neutral')));
    $player_speaker_id = trim($player_speaker_id);

    // Base attitude modifier
    $score = match ($npc_attitude) {
      'friendly' => 20,
      'helpful' => 15,
      'neutral' => 0,
      'suspicious' => -15,
      'hostile' => -30,
      default => 0,
    };

    // Personality type modifiers
    $personality = strtolower(trim((string) ($npc_profile['personality_type'] ?? '')));
    if ($personality === 'talkative' || $personality === 'gregarious') {
      $score += 15;
    } elseif ($personality === 'quiet' || $personality === 'reserved') {
      $score -= 10;
    }

    // Bonus if this player spoke recently (conversational continuity)
    if ($player_speaker_id !== '') {
      $recent = (array) ($conversation_state['recent_speakers'] ?? []);
      foreach (array_slice($recent, -3) as $speaker_record) {
        if ((string) ($speaker_record['speaker'] ?? '') === $player_speaker_id) {
          $score += 10;
          break;
        }
      }
    }

    return max(-50, min(50, $score));
  }

  /**
   * Scores recent interaction bonus.
   *
   * @param array $npc_profile
   *   NPC profile.
   * @param array $conversation_state
   *   Conversation state.
   *
   * @return int
   *   Score 0-20.
   */
  protected function scoreRecentInteraction(
    array $npc_profile,
    array $conversation_state
  ): int {
    $entity_ref = (string) ($npc_profile['entity_ref'] ?? '');
    if ($entity_ref === '') {
      return 0;
    }

    $recent = (array) ($conversation_state['recent_speakers'] ?? []);
    if (empty($recent)) {
      return 0;
    }

    $score = 0;

    // Bonus if NPC was the last speaker
    $last_speaker = end($recent);
    if ((string) ($last_speaker['speaker'] ?? '') === $entity_ref) {
      $score += 10;
    }

    // Smaller bonus if NPC spoke within last 2 turns
    foreach (array_slice($recent, -2) as $record) {
      if ((string) ($record['speaker'] ?? '') === $entity_ref) {
        $score += 5;
        break;
      }
    }

    return min(20, $score);
  }

  /**
   * Calculates fatigue penalty from repeated speaking.
   *
   * @param array $npc_profile
   *   NPC profile.
   * @param array $conversation_state
   *   Conversation state with engagement scores.
   *
   * @return int
   *   Penalty 0-30.
   */
  protected function calculateFatiguePenalty(
    array $npc_profile,
    array $conversation_state
  ): int {
    $entity_ref = (string) ($npc_profile['entity_ref'] ?? '');
    if ($entity_ref === '') {
      return 0;
    }

    $scores = (array) ($conversation_state['engagement_scores'] ?? []);
    $npc_score_record = $scores[$entity_ref] ?? [];

    return (int) ($npc_score_record['fatigue_penalty'] ?? 0);
  }

  /**
   * Gets initiative bonus for NPC.
   *
   * @param array $npc_profile
   *   NPC profile.
   * @param array $game_state
   *   Game state with initiative.
   *
   * @return int
   *   Bonus 0-20 (weak signal).
   */
  protected function getInitiativeBonus(
    array $npc_profile,
    array $game_state
  ): int {
    $initiative = (int) ($npc_profile['initiative_total'] ?? 0);
    if ($initiative <= 0) {
      return 0;
    }

    // Weak bonus: initiative_total capped at 20 points
    return min(20, $initiative);
  }

  /**
   * Increments fatigue penalty for an NPC.
   *
   * Called after NPC speaks to prevent spam.
   *
   * @param array &$conversation_state
   *   The attention state (modified in place).
   * @param string $entity_ref
   *   The NPC entity ref.
   */
  public function incrementFatiguePenalty(
    array &$conversation_state,
    string $entity_ref
  ): void {
    $entity_ref = trim($entity_ref);
    if ($entity_ref === '') {
      return;
    }

    $scores = (array) ($conversation_state['engagement_scores'] ?? []);
    if (!isset($scores[$entity_ref])) {
      $scores[$entity_ref] = [];
    }

    $current = (int) ($scores[$entity_ref]['fatigue_penalty'] ?? 0);
    $scores[$entity_ref]['fatigue_penalty'] = min(30, $current + 5);
    $conversation_state['engagement_scores'] = $scores;
  }

  /**
   * Decays fatigue penalty for NPCs that didn't speak.
   *
   * Called at the end of each NPC turn cycle to let penalties fade.
   *
   * @param array &$conversation_state
   *   The attention state (modified in place).
   */
  public function decayFatiguePenalties(array &$conversation_state): void {
    $scores = (array) ($conversation_state['engagement_scores'] ?? []);
    foreach ($scores as &$npc_score) {
      $current = (int) ($npc_score['fatigue_penalty'] ?? 0);
      if ($current > 0) {
        $npc_score['fatigue_penalty'] = max(0, $current - 1);
      }
    }
    unset($npc_score);
    $conversation_state['engagement_scores'] = $scores;
  }

  /**
   * Clears conversation state (on combat start, NPC departure, etc).
   *
   * @param array &$conversation_state
   *   The attention state (modified in place).
   */
  public function resetAttentionState(array &$conversation_state): void {
    $conversation_state = $this->initializeAttentionState();
  }

  /**
   * Validates that an NPC profile has required fields for attention scoring.
   *
   * @param array $npc_profile
   *   NPC profile data to validate.
   *
   * @return bool
   *   TRUE if valid, FALSE if missing required fields.
   *
   * @throws InvalidArgumentException
   *   If critical fields are missing.
   */
  protected function validateNpcProfile(array $npc_profile): bool {
    // Critical fields that must exist
    if (empty($npc_profile['entity_ref'])) {
      throw new InvalidArgumentException('NPC profile missing required entity_ref');
    }
    if (empty($npc_profile['ability_scores']['charisma'])) {
      throw new InvalidArgumentException('NPC profile missing required ability_scores.charisma');
    }
    if (empty($npc_profile['profile']['display_name'])) {
      throw new InvalidArgumentException('NPC profile missing required profile.display_name');
    }
    return TRUE;
  }

}
