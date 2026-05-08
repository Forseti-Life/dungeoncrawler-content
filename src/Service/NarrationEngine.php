<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ai_conversation\Service\AIApiService;
use Drupal\ai_conversation\Service\PromptManager;
use Psr\Log\LoggerInterface;

/**
 * Batch narration engine for the GM-as-coordinator chat model.
 *
 * Implements the "C" narration strategy:
 * - Room events accumulate in a buffer per room.
 * - Periodically (or on flush), the GM generates a single narrated "beat"
 *   per character covering multiple events.
 * - Exception: speech events (dialogue) trigger IMMEDIATE GenAI narration
 *   so conversations feel responsive.
 *
 * The GM is the coordinator who decides what each character perceives:
 *   - Can they hear it? (distance, deafened condition, walls)
 *   - Can they see it? (darkvision, invisible, stealth vs Perception DC)
 *   - Are they conscious? (unconscious, sleeping)
 *   - Do they understand it? (languages known)
 *   - Secret checks (Perception, Recall Knowledge — GM rolls, player doesn't know DC)
 *
 * Room Chat = objective reality (GM "God view")
 * Character Narrative = what that character actually perceived (filtered)
 *
 * @see ChatSessionManager for session hierarchy management.
 */
class NarrationEngine {

  /**
   * Maximum events to buffer before auto-flushing a scene beat.
   */
  const MAX_BUFFER_SIZE = 8;

  /**
   * Event types that trigger immediate GenAI narration (no batching).
   */
  const IMMEDIATE_NARRATION_TYPES = [
    'dialogue',
    'speech',
    'shout',
    'npc_speech',
  ];

  /**
   * Event types that are purely mechanical / system (no narration needed).
   */
  const MECHANICAL_EVENT_TYPES = [
    'dice_roll',
    'skill_check_result',
    'damage_applied',
    'condition_applied',
    'condition_removed',
    'initiative_set',
  ];

  /**
   * Perception-based event types that require checks.
   */
  const PERCEPTION_GATED_TYPES = [
    'stealth_movement',
    'hidden_action',
    'trap_trigger',
    'secret_door',
    'whispered_speech',
    'pickpocket',
  ];

  protected Connection $database;
  protected LoggerInterface $logger;
  protected ChatSessionManager $sessionManager;
  protected AIApiService $aiApiService;
  protected PromptManager $promptManager;
  protected GameplayActionProcessor $actionProcessor;

  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    ChatSessionManager $session_manager,
    AIApiService $ai_api_service,
    PromptManager $prompt_manager,
    GameplayActionProcessor $action_processor
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_narration');
    $this->sessionManager = $session_manager;
    $this->aiApiService = $ai_api_service;
    $this->promptManager = $prompt_manager;
    $this->actionProcessor = $action_processor;
  }

  // =========================================================================
  // Helpers for callers.
  // =========================================================================

  /**
   * Build a present_characters array from dungeon_data for a given room.
   *
   * This is the canonical way to extract the perception-filtering data
   * that queueRoomEvent() expects in its $present_characters parameter.
   *
   * @param array $dungeon_data
   *   Full dungeon data structure.
   * @param string|null $room_id
   *   Room ID to extract characters from. NULL = active room.
   *
   * @return array
   *   Array of character descriptors suitable for queueRoomEvent().
   */
  public static function buildPresentCharacters(array $dungeon_data, ?string $room_id = NULL): array {
    $room_id = $room_id ?? ($dungeon_data['active_room_id'] ?? NULL);
    if (!$room_id) {
      return [];
    }

    $characters = [];

    // Find the room by ID.
    $room = NULL;
    foreach ($dungeon_data['rooms'] ?? [] as $r) {
      $rid = $r['room_id'] ?? $r['id'] ?? '';
      if ($rid === $room_id) {
        $room = $r;
        break;
      }
    }
    if (!$room) {
      return [];
    }

    // PC characters in the room.
    foreach ($room['characters'] ?? [] as $pc) {
      $char_id = $pc['character_id'] ?? $pc['id'] ?? NULL;
      if ($char_id === NULL) {
        continue;
      }
      $characters[] = [
        'character_id' => $char_id,
        'name' => $pc['name'] ?? $pc['display_name'] ?? 'Unknown',
        'perception' => $pc['perception'] ?? ($pc['stats']['perception'] ?? 0),
        'languages' => $pc['languages'] ?? ['Common'],
        'senses' => $pc['senses'] ?? [],
        'conditions' => $pc['conditions'] ?? [],
        'position' => $pc['position'] ?? NULL,
      ];
    }

    // Also check top-level entities placed in this room.
    foreach ($dungeon_data['entities'] ?? [] as $ent) {
      $ent_room = $ent['placement']['room_id'] ?? '';
      if ($ent_room !== $room_id) {
        continue;
      }
      $meta = $ent['state']['metadata'] ?? [];
      $stats = $meta['stats'] ?? [];
      $characters[] = [
        'character_id' => $ent['entity_instance_id'] ?? ($ent['entity_ref']['content_id'] ?? ''),
        'name' => $meta['display_name'] ?? $ent['name'] ?? 'Unknown Entity',
        'perception' => $stats['perception'] ?? 0,
        'languages' => $ent['languages'] ?? ['Common'],
        'senses' => $ent['senses'] ?? [],
        'conditions' => $ent['conditions'] ?? ($meta['conditions'] ?? []),
        'position' => $ent['position'] ?? NULL,
      ];
    }

    // Room-level entities (different schema variant).
    foreach ($room['entities'] ?? [] as $ent) {
      $ent_ref = $ent['entity_ref']['content_id'] ?? ($ent['entity_ref'] ?? '');
      $ent_id = $ent['entity_instance_id'] ?? $ent_ref;
      // Skip if already added from top-level entities.
      $already = FALSE;
      foreach ($characters as $c) {
        if ($c['character_id'] === $ent_id) {
          $already = TRUE;
          break;
        }
      }
      if ($already) {
        continue;
      }
      $meta = $ent['state']['metadata'] ?? [];
      $stats = $meta['stats'] ?? [];
      $characters[] = [
        'character_id' => $ent_id,
        'name' => $meta['display_name'] ?? $ent['name'] ?? 'Unknown Entity',
        'perception' => $stats['perception'] ?? 0,
        'languages' => $ent['languages'] ?? ['Common'],
        'senses' => $ent['senses'] ?? [],
        'conditions' => $ent['conditions'] ?? ($meta['conditions'] ?? []),
        'position' => $ent['position'] ?? NULL,
      ];
    }

    return $characters;
  }

  // =========================================================================
  // Event queueing.
  // =========================================================================

  /**
   * Queue a room event for narration processing.
   *
   * This is the primary entry point. Everything that happens in the room
   * goes through here. The engine decides whether to:
   *   (a) Immediately narrate (speech/dialogue → GenAI)
   *   (b) Buffer for batch scene beat
   *   (c) Record as mechanical-only (system log, no narration)
   *
   * @param int $campaign_id
   * @param int|string $dungeon_id
   * @param string $room_id
   * @param array $event
   *   [
   *     'type'        => string (dialogue|action|movement|stealth_movement|...),
   *     'speaker'     => string (display name),
   *     'speaker_type'=> string (player|npc|gm|system),
   *     'speaker_ref' => string (character_id or entity_ref),
   *     'content'     => string (what happened / what was said),
   *     'language'    => string|null (language spoken, if dialogue),
   *     'volume'      => string (normal|whisper|shout), default normal,
   *     'perception_dc' => int|null (DC to notice, for stealth/hidden actions),
   *     'mechanical_data' => array (dice_rolls, damage, etc.),
   *     'visibility'  => string (public|gm_only), default public,
   *   ]
   * @param array $present_characters
   *   Characters currently in the room. Each:
   *   [
   *     'character_id' => int|string,
   *     'name'         => string,
   *     'perception'   => int (Perception modifier),
   *     'languages'    => string[] (known languages),
   *     'senses'       => string[] (darkvision, low-light, etc.),
   *     'conditions'   => string[] (deafened, blinded, unconscious, etc.),
   *     'position'     => array|null (hex position for distance calcs),
   *   ]
   *
   * @return array
   *   [
   *     'event_recorded' => bool,
   *     'immediate_narrations' => array (if speech, per-character narrations),
   *     'buffer_flushed' => bool (if buffer hit threshold),
   *     'scene_beats'    => array (if flushed, the per-character scene beats),
   *   ]
   */
  public function queueRoomEvent(
    int $campaign_id,
    int|string $dungeon_id,
    string $room_id,
    array $event,
    array $present_characters = []
  ): array {
    $result = [
      'event_recorded' => FALSE,
      'immediate_narrations' => [],
      'buffer_flushed' => FALSE,
      'scene_beats' => [],
    ];

    $event_type = $event['type'] ?? 'unknown';
    $visibility = $event['visibility'] ?? 'public';

    // 1. Record raw event in Room Chat (objective reality — GM "God view").
    $room_session = $this->sessionManager->ensureRoomSession($campaign_id, $dungeon_id, $room_id);
    $room_session_id = (int) $room_session['id'];

    $msg_id = $this->sessionManager->postMessage(
      $room_session_id,
      $campaign_id,
      $event['speaker'] ?? 'Unknown',
      $event['speaker_type'] ?? 'system',
      $event['speaker_ref'] ?? '',
      $event['content'] ?? '',
      $this->mapEventTypeToMessageType($event_type),
      $visibility,
      array_merge(
        $event['mechanical_data'] ?? [],
        [
          'event_type' => $event_type,
          'language' => $event['language'] ?? NULL,
          'volume' => $event['volume'] ?? 'normal',
          'perception_dc' => $event['perception_dc'] ?? NULL,
        ]
      ),
      TRUE // feed up to dungeon + campaign
    );

    $result['event_recorded'] = TRUE;

    // 2. Record mechanical events to system log (no narration).
    if (in_array($event_type, self::MECHANICAL_EVENT_TYPES, TRUE)) {
      $sys_session = $this->sessionManager->loadSession(
        $this->sessionManager->systemLogSessionKey($campaign_id)
      );
      if ($sys_session) {
        $this->sessionManager->postMessage(
          (int) $sys_session['id'],
          $campaign_id,
          'System',
          'system',
          '',
          $event['content'] ?? '',
          'mechanical',
          'public',
          $event['mechanical_data'] ?? [],
          FALSE // don't feed up again (room already fed)
        );
      }
      return $result;
    }

    // 3. Speech/dialogue → IMMEDIATE GenAI narration per character.
    if (in_array($event_type, self::IMMEDIATE_NARRATION_TYPES, TRUE)) {
      $narrations = $this->narrateSpeechEvent($campaign_id, $dungeon_id, $room_id, $event, $present_characters);
      $result['immediate_narrations'] = $narrations;
      return $result;
    }

    // 4. All other events → buffer for batch scene beat.
    $this->addToNarrationBuffer($room_session_id, $event);

    // Check if buffer should auto-flush.
    $buffer = $this->getNarrationBuffer($room_session_id);
    if (count($buffer) >= self::MAX_BUFFER_SIZE) {
      $beats = $this->flushNarration($campaign_id, $dungeon_id, $room_id, $present_characters);
      $result['buffer_flushed'] = TRUE;
      $result['scene_beats'] = $beats;
    }

    return $result;
  }

  // =========================================================================
  // Immediate speech narration (GenAI).
  // =========================================================================

  /**
   * Generate immediate per-character narration for a speech event.
   *
   * For each present character, determines what they perceived:
   * - Did they hear the speech? (deafened, distance, etc.)
   * - Do they understand the language?
   * - Were they the target? (for whispered speech)
   *
   * @return array
   *   Keyed by character_id => ['narration' => string, 'understood' => bool]
   */
  protected function narrateSpeechEvent(
    int $campaign_id,
    int|string $dungeon_id,
    string $room_id,
    array $event,
    array $present_characters
  ): array {
    $speaker = $event['speaker'] ?? 'Someone';
    $content = $event['content'] ?? '';
    $language = $event['language'] ?? 'Common';
    $volume = $event['volume'] ?? 'normal';
    $speaker_ref = $event['speaker_ref'] ?? '';
    $speaker_type = $event['speaker_type'] ?? 'npc';

    $narrations = [];

    foreach ($present_characters as $char) {
      $char_id = $char['character_id'] ?? 'unknown';
      $char_name = $char['name'] ?? 'Unknown';
      $conditions = $char['conditions'] ?? [];
      $languages = $char['languages'] ?? ['Common'];

      // Skip the speaker themselves.
      if ((string) $char_id === (string) $speaker_ref) {
        continue;
      }

      // Check perception filters.
      $perception = $this->buildPerceptionContext($char, $event);

      if (!$perception['can_hear']) {
        // Character can't hear at all — no narration.
        continue;
      }

      // Determine if they understand the language.
      $understood = in_array($language, $languages, TRUE) || $language === 'Common';

      // Build per-character narration via GenAI.
      $narration = $this->generateSpeechNarration(
        $campaign_id,
        $dungeon_id,
        $room_id,
        $char_id,
        $char_name,
        $speaker,
        $content,
        $language,
        $volume,
        $understood,
        $perception
      );

      // Post to character's narrative session.
      $char_session = $this->sessionManager->ensureCharacterNarrativeSession(
        $campaign_id, $dungeon_id, $room_id, $char_id, $char_name
      );
      $this->sessionManager->postMessage(
        (int) $char_session['id'],
        $campaign_id,
        'Game Master',
        'gm',
        '',
        $narration,
        'dialogue',
        'private',
        [
          'original_speaker' => $speaker,
          'language' => $language,
          'understood' => $understood,
          'volume' => $volume,
        ],
        FALSE // character narrative doesn't feed up further
      );

      $narrations[$char_id] = [
        'narration' => $narration,
        'understood' => $understood,
        'character_name' => $char_name,
      ];
    }

    return $narrations;
  }

  /**
   * Generate AI narration for a speech event as perceived by a specific character.
   */
  protected function generateSpeechNarration(
    int $campaign_id,
    int|string $dungeon_id,
    string $room_id,
    int|string $character_id,
    string $character_name,
    string $speaker,
    string $content,
    string $language,
    string $volume,
    bool $understood,
    array $perception
  ): string {
    // Build context for the AI.
    $prompt_parts = [];
    $prompt_parts[] = "You are the Game Master narrating what {$character_name} perceives.";
    $prompt_parts[] = "Event: {$speaker} speaks.";
    $prompt_parts[] = "Volume: {$volume}.";
    $prompt_parts[] = "Language spoken: {$language}.";

    if ($understood) {
      $prompt_parts[] = "{$character_name} understands {$language}.";
      $prompt_parts[] = "What was said: \"{$content}\"";
      $prompt_parts[] = "Narrate this from {$character_name}'s perspective. Include the speech content naturally. Keep it to 1-2 sentences.";
    }
    else {
      $prompt_parts[] = "{$character_name} does NOT understand {$language}.";
      $prompt_parts[] = "Narrate that {$speaker} said something in a language {$character_name} doesn't understand. Do NOT reveal the content. Keep it to 1 sentence.";
    }

    if ($volume === 'whisper') {
      $prompt_parts[] = "The speech was whispered. {$character_name} can barely make it out.";
    }
    elseif ($volume === 'shout') {
      $prompt_parts[] = "The speech was shouted loudly.";
    }

    $prompt = implode("\n", $prompt_parts);

    try {
      $result = $this->aiApiService->invokeModelDirect(
        $prompt,
        'dungeoncrawler_content',
        'speech_narration',
        [
          'campaign_id' => $campaign_id,
          'character_id' => $character_id,
          'event_type' => 'speech',
        ],
        [
          'system_prompt' => 'You are a concise Game Master narrator for a Pathfinder 2e dungeon crawl. Narrate in second person ("You hear..."). Keep narration under 2 sentences. Do not add actions the character did not take. ONLY reference NPCs, creatures, items, and objects provided in the context. Do NOT invent new characters or objects — use exact names from the room data.',
          'max_tokens' => 200,
          'skip_cache' => TRUE,
        ]
      );

      if (!empty($result['success']) && !empty($result['response'])) {
        return trim($result['response']);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Speech narration AI error: @msg', ['@msg' => $e->getMessage()]);
    }

    // Fallback: template-based narration.
    return $this->templateSpeechNarration($speaker, $content, $language, $volume, $understood);
  }

  /**
   * Template-based speech narration fallback (no AI call).
   */
  protected function templateSpeechNarration(
    string $speaker,
    string $content,
    string $language,
    string $volume,
    bool $understood
  ): string {
    $volume_desc = match ($volume) {
      'whisper' => 'whispers',
      'shout' => 'shouts',
      default => 'says',
    };

    if ($understood) {
      return "{$speaker} {$volume_desc}: \"{$content}\"";
    }

    return "{$speaker} {$volume_desc} something in {$language} that you don't understand.";
  }

  // =========================================================================
  // Batch scene beat narration.
  // =========================================================================

  /**
   * Flush the narration buffer for a room, generating per-character scene beats.
   *
   * Processes all buffered events, groups them by perception, and generates
   * a single narrated "beat" per present character summarizing what they
   * perceived since the last beat.
   *
   * @return array
   *   Keyed by character_id => scene beat text.
   */
  public function flushNarration(
    int $campaign_id,
    int|string $dungeon_id,
    string $room_id,
    array $present_characters
  ): array {
    $room_session = $this->sessionManager->ensureRoomSession($campaign_id, $dungeon_id, $room_id);
    $room_session_id = (int) $room_session['id'];

    $buffer = $this->getNarrationBuffer($room_session_id);
    if (empty($buffer)) {
      return [];
    }

    $scene_beats = [];

    foreach ($present_characters as $char) {
      $char_id = $char['character_id'] ?? 'unknown';
      $char_name = $char['name'] ?? 'Unknown';

      // Filter events by what this character can perceive.
      $perceived_events = [];
      foreach ($buffer as $event) {
        $perception = $this->buildPerceptionContext($char, $event);
        if ($perception['can_perceive']) {
          $perceived_events[] = [
            'event' => $event,
            'perception' => $perception,
          ];
        }
      }

      if (empty($perceived_events)) {
        continue;
      }

      // Generate the scene beat.
      $beat = $this->generateSceneBeat($campaign_id, $dungeon_id, $room_id, $char_id, $char_name, $perceived_events);

      // Post to character's narrative session.
      $char_session = $this->sessionManager->ensureCharacterNarrativeSession(
        $campaign_id, $dungeon_id, $room_id, $char_id, $char_name
      );
      $this->sessionManager->postMessage(
        (int) $char_session['id'],
        $campaign_id,
        'Game Master',
        'gm',
        '',
        $beat,
        'scene_beat',
        'private',
        ['events_count' => count($perceived_events)],
        FALSE
      );

      $scene_beats[$char_id] = $beat;
    }

    // Clear the buffer.
    $this->clearNarrationBuffer($room_session_id);

    return $scene_beats;
  }

  /**
   * Generate a scene beat via AI for a character from buffered events.
   */
  protected function generateSceneBeat(
    int $campaign_id,
    int|string $dungeon_id,
    string $room_id,
    int|string $character_id,
    string $character_name,
    array $perceived_events
  ): string {
    $event_descriptions = [];
    foreach ($perceived_events as $pe) {
      $event = $pe['event'];
      $perception = $pe['perception'];

      $desc = $event['content'] ?? '';
      if (!$perception['full_detail']) {
        $desc = $perception['partial_description'] ?? 'Something happens nearby.';
      }
      $event_descriptions[] = "- {$desc}";
    }

    $prompt = "You are the Game Master narrating a scene beat for {$character_name}.\n";
    $prompt .= "The following events occurred in the room:\n";
    $prompt .= implode("\n", $event_descriptions);
    $prompt .= "\n\nNarrate these events from {$character_name}'s perspective as a single cohesive scene beat.";
    $prompt .= " Use second person (\"You notice...\", \"You see...\"). 2-4 sentences.";
    $prompt .= " This should read like a Game Master's description at the table.";

    try {
      $result = $this->aiApiService->invokeModelDirect(
        $prompt,
        'dungeoncrawler_content',
        'scene_beat_narration',
        [
          'campaign_id' => $campaign_id,
          'character_id' => $character_id,
        ],
        [
          'system_prompt' => 'You are a concise Game Master narrator for a Pathfinder 2e dungeon crawl. Combine multiple events into a single narrative beat. Use second person. Do not add events that were not listed. ONLY reference NPCs, creatures, items, and objects provided in the context. Do NOT invent new characters or objects — use exact names from the room data.',
          'max_tokens' => 400,
          'skip_cache' => TRUE,
        ]
      );

      if (!empty($result['success']) && !empty($result['response'])) {
        return trim($result['response']);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Scene beat narration AI error: @msg', ['@msg' => $e->getMessage()]);
    }

    // Fallback: concatenate event descriptions.
    return implode(' ', array_map(fn($pe) => $pe['event']['content'] ?? '', $perceived_events));
  }

  // =========================================================================
  // Perception filtering.
  // =========================================================================

  /**
   * Determine what a character can perceive of an event.
   *
   * @param array $character
   *   Character data (conditions, senses, perception, languages, position).
   * @param array $event
   *   Event data.
   *
   * @return array
   *   [
   *     'can_perceive'        => bool,
   *     'can_hear'            => bool,
   *     'can_see'             => bool,
   *     'full_detail'         => bool (can they perceive EVERYTHING about the event?),
   *     'partial_description' => string|null (if partial, what they do perceive),
   *     'perception_check'    => array|null (if a check was needed: ['dc' => int, 'result' => int, 'success' => bool]),
   *   ]
   */
  public function buildPerceptionContext(array $character, array $event): array {
    $conditions = $character['conditions'] ?? [];
    $senses = $character['senses'] ?? [];
    $event_type = $event['type'] ?? 'unknown';
    $volume = $event['volume'] ?? 'normal';
    $perception_dc = $event['perception_dc'] ?? NULL;

    $result = [
      'can_perceive' => TRUE,
      'can_hear' => TRUE,
      'can_see' => TRUE,
      'full_detail' => TRUE,
      'partial_description' => NULL,
      'perception_check' => NULL,
    ];

    // Unconscious characters perceive nothing.
    if (in_array('unconscious', $conditions, TRUE) || in_array('sleeping', $conditions, TRUE)) {
      $result['can_perceive'] = FALSE;
      $result['can_hear'] = FALSE;
      $result['can_see'] = FALSE;
      $result['full_detail'] = FALSE;
      return $result;
    }

    // Deafened characters can't hear speech.
    if (in_array('deafened', $conditions, TRUE)) {
      $result['can_hear'] = FALSE;
      if (in_array($event_type, ['dialogue', 'speech', 'shout', 'npc_speech', 'whispered_speech'], TRUE)) {
        $result['full_detail'] = FALSE;
        $result['partial_description'] = "You see someone's lips moving but can't make out any sound.";
      }
    }

    // Blinded characters can't see visual events.
    if (in_array('blinded', $conditions, TRUE)) {
      $result['can_see'] = FALSE;
      if (in_array($event_type, ['stealth_movement', 'hidden_action', 'movement'], TRUE)) {
        $result['full_detail'] = FALSE;
        $result['partial_description'] = 'You hear something nearby but cannot see what happened.';
      }
    }

    // Whispered speech — only adjacent characters hear.
    if ($volume === 'whisper' && $event_type !== 'shout') {
      // TODO: Check actual distance between speaker and character positions.
      // For now, assume all characters in the room are close enough for whisper
      // EXCEPT for the Perception-checkable aspect.
      $result['full_detail'] = FALSE;
      $result['partial_description'] = 'You notice someone whispering nearby but can\'t make out the words.';
    }

    // Perception-gated events require a check.
    if (in_array($event_type, self::PERCEPTION_GATED_TYPES, TRUE) && $perception_dc !== NULL) {
      $perception_mod = $character['perception'] ?? 0;
      // Simulate a Perception check (d20 + modifier vs DC).
      $roll = mt_rand(1, 20);
      $total = $roll + $perception_mod;
      $success = $total >= $perception_dc;

      $result['perception_check'] = [
        'dc' => $perception_dc,
        'roll' => $roll,
        'modifier' => $perception_mod,
        'total' => $total,
        'success' => $success,
      ];

      if (!$success) {
        $result['full_detail'] = FALSE;
        $result['can_perceive'] = FALSE;
        $result['partial_description'] = NULL; // They noticed nothing.
      }
    }

    // If they can't hear AND can't see, they can't perceive.
    if (!$result['can_hear'] && !$result['can_see']) {
      $result['can_perceive'] = FALSE;
    }

    return $result;
  }

  // =========================================================================
  // Narration buffer (stored in session metadata).
  // =========================================================================

  /**
   * Add an event to the narration buffer for a room session.
   */
  protected function addToNarrationBuffer(int $room_session_id, array $event): void {
    $session = $this->sessionManager->loadSessionById($room_session_id);
    if (!$session) {
      return;
    }

    $metadata = $session['metadata'] ?? [];
    $buffer = $metadata['narration_buffer'] ?? [];
    $buffer[] = [
      'type' => $event['type'] ?? 'unknown',
      'speaker' => $event['speaker'] ?? 'Unknown',
      'speaker_type' => $event['speaker_type'] ?? 'system',
      'speaker_ref' => $event['speaker_ref'] ?? '',
      'content' => $event['content'] ?? '',
      'language' => $event['language'] ?? NULL,
      'volume' => $event['volume'] ?? 'normal',
      'perception_dc' => $event['perception_dc'] ?? NULL,
      'visibility' => $event['visibility'] ?? 'public',
      'mechanical_data' => $event['mechanical_data'] ?? [],
      'timestamp' => time(),
    ];

    $metadata['narration_buffer'] = $buffer;

    $this->database->update('dc_chat_sessions')
      ->fields([
        'metadata' => json_encode($metadata),
        'updated' => time(),
      ])
      ->condition('id', $room_session_id)
      ->execute();
  }

  /**
   * Get the narration buffer for a room session.
   */
  protected function getNarrationBuffer(int $room_session_id): array {
    $session = $this->sessionManager->loadSessionById($room_session_id);
    if (!$session) {
      return [];
    }
    return $session['metadata']['narration_buffer'] ?? [];
  }

  /**
   * Clear the narration buffer.
   */
  protected function clearNarrationBuffer(int $room_session_id): void {
    $session = $this->sessionManager->loadSessionById($room_session_id);
    if (!$session) {
      return;
    }

    $metadata = $session['metadata'] ?? [];
    $metadata['narration_buffer'] = [];

    $this->database->update('dc_chat_sessions')
      ->fields([
        'metadata' => json_encode($metadata),
        'updated' => time(),
      ])
      ->condition('id', $room_session_id)
      ->execute();
  }

  // =========================================================================
  // GM Private channel helpers.
  // =========================================================================

  /**
   * Record a secret player action in the GM private channel.
   *
   * Used for pickpocketing, stealth declarations, hidden motives, etc.
   * Only the player and GM see this.
   *
   * @param int $campaign_id
   * @param int|string $character_id
   * @param string $character_name
   * @param string $action_description
   * @param array $metadata
   *
   * @return int
   *   Message ID.
   */
  public function recordSecretAction(
    int $campaign_id,
    int|string $character_id,
    string $character_name,
    string $action_description,
    array $metadata = []
  ): int {
    $session = $this->sessionManager->ensureGmPrivateSession($campaign_id, $character_id, $character_name);

    return $this->sessionManager->postMessage(
      (int) $session['id'],
      $campaign_id,
      $character_name,
      'player',
      (string) $character_id,
      $action_description,
      'action',
      'gm_only',
      $metadata,
      TRUE // feeds to campaign root (GM master memory)
    );
  }

  /**
   * Post a GM response to a secret action in the private channel.
   */
  public function respondToSecretAction(
    int $campaign_id,
    int|string $character_id,
    string $gm_response,
    array $metadata = []
  ): int {
    $session = $this->sessionManager->ensureGmPrivateSession($campaign_id, $character_id);

    return $this->sessionManager->postMessage(
      (int) $session['id'],
      $campaign_id,
      'Game Master',
      'gm',
      '',
      $gm_response,
      'narrative',
      'gm_only',
      $metadata,
      TRUE
    );
  }

  // =========================================================================
  // Helpers.
  // =========================================================================

  /**
   * Map event type to message_type for storage.
   */
  protected function mapEventTypeToMessageType(string $event_type): string {
    if (in_array($event_type, self::IMMEDIATE_NARRATION_TYPES, TRUE)) {
      return 'dialogue';
    }
    if (in_array($event_type, self::MECHANICAL_EVENT_TYPES, TRUE)) {
      return 'mechanical';
    }
    if (in_array($event_type, self::PERCEPTION_GATED_TYPES, TRUE)) {
      return 'action';
    }
    return match ($event_type) {
      'movement', 'exploration' => 'action',
      'system' => 'system',
      default => 'narrative',
    };
  }

}
