<?php

namespace Drupal\dungeoncrawler_content\Service;

trait RoomChatServiceConversationStateAndQuestActivationTrait {

  protected function buildRoomActorGroundingSummary(int $campaign_id, string $room_id, array $dungeon_data): string {
    $room_slug = $this->resolveRoomSlugForQuery($campaign_id, $room_id, $dungeon_data);
    $location_candidates = array_values(array_unique(array_filter([
      $room_slug,
      $room_id,
    ], static fn($value): bool => is_string($value) && $value !== '')));
    if ($location_candidates === []) {
      return '';
    }
    $query = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['name', 'type', 'role', 'class', 'ancestry', 'instance_id', 'character_data', 'last_room_id', 'location_ref'])
      ->condition('campaign_id', $campaign_id)
      ->condition('type', ['pc', 'npc'], 'IN')
      ->range(0, 8);

    $room_match = $query->orConditionGroup()
      ->condition('last_room_id', $location_candidates, 'IN')
      ->condition('location_ref', $location_candidates, 'IN');
    $rows = $query
      ->condition($room_match)
      ->execute()
      ->fetchAll();

    if (!$rows) {
      return '';
    }

    $lines = [];
    $seen_names = [];
    foreach ($rows as $row) {
      $name = trim((string) ($row->name ?? ''));
      if ($name === '') {
        continue;
      }
      $name_key = strtolower($name);
      if (isset($seen_names[$name_key])) {
        continue;
      }
      $seen_names[$name_key] = TRUE;

      $character_data = json_decode((string) ($row->character_data ?? '{}'), TRUE);
      if (!is_array($character_data)) {
        $character_data = [];
      }
      $basic_info = is_array($character_data['basicInfo'] ?? NULL) ? $character_data['basicInfo'] : [];
      $profile = is_array($character_data['profile'] ?? NULL) ? $character_data['profile'] : [];
      $sheet = is_array($profile['character_sheet'] ?? NULL)
        ? $profile['character_sheet']
        : (is_array($character_data['character_sheet'] ?? NULL) ? $character_data['character_sheet'] : []);

      $type = strtolower((string) ($row->type ?? ''));
      $role = trim((string) ($profile['role'] ?? $row->role ?? ''));
      $ancestry = trim((string) ($basic_info['ancestry'] ?? $row->ancestry ?? ''));
      $class = trim((string) ($basic_info['class'] ?? $row->class ?? ''));
      $appearance = trim((string) ($basic_info['appearance'] ?? $profile['appearance'] ?? $sheet['appearance'] ?? $sheet['description'] ?? $character_data['appearance'] ?? ''));
      $personality = trim((string) ($basic_info['personality'] ?? $profile['personality_traits'] ?? $profile['personality'] ?? $character_data['personality'] ?? ''));
      $attitude = trim((string) ($profile['attitude'] ?? $character_data['attitude'] ?? ''));
      $motivations = trim((string) ($profile['motivations'] ?? $character_data['motivations'] ?? ''));

      $parts = [];
      if ($type === 'pc') {
        $identity = trim(implode(' ', array_filter([$ancestry, $class])));
        if ($identity !== '') {
          $parts[] = $identity;
        }
      }
      elseif ($role !== '') {
        $parts[] = 'role: ' . $this->truncateContextBlock($role, 72, 0.9);
      }
      if ($appearance !== '') {
        $parts[] = 'appearance: ' . $this->truncateContextBlock($appearance, 120, 0.8);
      }
      if ($personality !== '') {
        $parts[] = 'personality: ' . $this->truncateContextBlock($personality, 96, 0.8);
      }
      if ($attitude !== '') {
        $parts[] = 'attitude: ' . $this->truncateContextBlock($attitude, 72, 0.9);
      }
      if ($motivations !== '') {
        $parts[] = 'motivations: ' . $this->truncateContextBlock($motivations, 96, 0.8);
      }

      if ($parts !== []) {
        $lines[] = '- ' . $name . ' — ' . implode('; ', $parts);
      }
    }

    return $lines !== []
      ? "Canonical actor notes for named room occupants:\n" . implode("\n", $lines)
      : '';
  }

  /**
   * Recover the currently active direct NPC thread from the recent room chat.
   */

  protected function resolveActiveDirectConversationNpc(array $chat, array $room_npcs): ?array {
    $recent = array_values(array_filter(array_slice($chat, -8), static function ($entry): bool {
      return is_array($entry) && (string) ($entry['channel'] ?? 'room') === 'room';
    }));

    for ($i = count($recent) - 1; $i >= 0; $i--) {
      $entry = $recent[$i] ?? [];
      if (($entry['type'] ?? '') !== 'player') {
        continue;
      }

      $candidate = $this->resolveDirectlyAddressedNpc($room_npcs, (string) ($entry['message'] ?? ''));
      if ($candidate === NULL) {
        continue;
      }

      for ($j = $i + 1; $j < count($recent); $j++) {
        $follow_up = $recent[$j] ?? [];
        if (($follow_up['type'] ?? '') === 'player') {
          $follow_up_addressed_npc = $this->resolveDirectlyAddressedNpc($room_npcs, (string) ($follow_up['message'] ?? ''));
          $follow_up_intent = $this->classifyRoomTurnIntent(
            (string) ($follow_up['message'] ?? ''),
            $room_npcs,
            $follow_up_addressed_npc,
            $candidate
          );

          $same_candidate = $follow_up_addressed_npc === NULL
            || ($follow_up_addressed_npc['entity_ref'] ?? '') === ($candidate['entity_ref'] ?? '');
          if (!$same_candidate || !in_array($follow_up_intent, [
            'direct_npc_dialogue',
            'direct_npc_transaction',
            'gm_narration',
            'quest_query',
          ], TRUE)) {
            continue 2;
          }
          continue;
        }

        $speaker = trim((string) ($follow_up['speaker'] ?? ''));
        if ($speaker === '' || in_array($speaker, ['Game Master', 'System'], TRUE)) {
          continue;
        }

        $resolved = $this->resolveNamedRoomNpc($room_npcs, $speaker);
        if ($resolved === NULL || ($resolved['entity_ref'] ?? '') !== ($candidate['entity_ref'] ?? '')) {
          continue 2;
        }
      }

      return $candidate;
    }

    return NULL;
  }

  /**
   * Recover the explicit room conversation target when one is already tracked.
   */

  protected function resolveExplicitRoomConversationNpc(array $room_meta, array $room_npcs): ?array {
    $state = is_array($room_meta['conversation_state'] ?? NULL) ? $room_meta['conversation_state'] : [];
    $entity_ref = trim((string) ($state['entity_ref'] ?? ''));
    if ($entity_ref !== '') {
      foreach ($room_npcs as $npc) {
        if ((string) ($npc['entity_ref'] ?? '') === $entity_ref) {
          return $npc;
        }
      }
    }

    $speaker_name = trim((string) ($state['speaker_name'] ?? ''));
    return $speaker_name !== '' ? $this->resolveNamedRoomNpc($room_npcs, $speaker_name) : NULL;
  }

  /**
   * Persist or clear the explicit room conversation target for the next turn.
   */

  protected function synchronizeExplicitRoomConversationState(
    array &$dungeon_data,
    int|string $room_index,
    string $turn_intent,
    ?array $conversation_npc = NULL,
    array $room_npcs = [],
    string $player_message = '',
    ?int $character_id = NULL,
    array $response_context = []
  ): void {
    if (!isset($dungeon_data['rooms'][$room_index]) || !is_array($dungeon_data['rooms'][$room_index])) {
      return;
    }

    $tracked_ref = trim((string) ($conversation_npc['entity_ref'] ?? ''));
    if ($tracked_ref === '') {
      unset($dungeon_data['rooms'][$room_index]['conversation_state']);
      unset($dungeon_data['rooms'][$room_index]['conversation_queue']);
      return;
    }

    $tracked_name = trim((string) ($conversation_npc['profile']['display_name'] ?? $conversation_npc['entity']['name'] ?? $tracked_ref));
    $dungeon_data['rooms'][$room_index]['conversation_state'] = [
      'entity_ref' => $tracked_ref,
      'speaker_name' => $tracked_name,
      'intent' => $turn_intent,
      'channel' => 'room',
      'character_id' => $character_id,
      'updated_at' => date('c'),
    ];
    unset($dungeon_data['rooms'][$room_index]['conversation_queue']);
  }


  public function consumePendingEncounterRoomDialogue(
    int $campaign_id,
    string $room_id,
    string $actor_id,
    array &$dungeon_data
  ): ?array {
    $room_index = $this->roomLocator->findRoomIndex($dungeon_data['rooms'] ?? [], $room_id);
    if ($room_index === NULL) {
      return NULL;
    }

    $room_meta = is_array($dungeon_data['rooms'][$room_index] ?? NULL) ? $dungeon_data['rooms'][$room_index] : [];
    $room_npcs = $this->gatherRoomNpcsWithProfiles($campaign_id, $room_id, $dungeon_data);
    $current_npc = $this->findRoomNpcMatchingActorTurn($room_npcs, $actor_id);
    if ($current_npc === NULL) {
      return NULL;
    }
    $queue = is_array($room_meta['conversation_queue'] ?? NULL)
      ? array_values(array_filter($room_meta['conversation_queue'], 'is_array'))
      : [];
    $state = NULL;
    $matched_queue_index = NULL;
    foreach ($queue as $index => $queued_state) {
      if (!$this->pendingRoomMessageAppliesToActorTurn($queued_state, $current_npc, $room_npcs)) {
        continue;
      }
      $state = $queued_state;
      $matched_queue_index = $index;
      break;
    }
    if ($state === NULL && is_array($room_meta['conversation_state'] ?? NULL)) {
      if ($this->pendingRoomMessageAppliesToActorTurn($room_meta['conversation_state'], $current_npc, $room_npcs)) {
        $state = $room_meta['conversation_state'];
      }
    }
    if ($state === NULL) {
      return NULL;
    }

    $player_message = trim((string) ($state['pending_player_message'] ?? ''));
    if ($player_message === '') {
      $player_message = $this->findLatestRoomPlayerMessage($room_meta);
    }
    $character_id = isset($state['character_id']) && is_numeric($state['character_id'])
      ? (int) $state['character_id']
      : NULL;
    $entity_ref = (string) ($current_npc['entity_ref'] ?? '');
    $display_name = trim((string) ($current_npc['profile']['display_name'] ?? ''));

    if ($matched_queue_index !== NULL) {
      unset($queue[$matched_queue_index]);
      $queue = array_values($queue);
      if ($queue !== []) {
        $dungeon_data['rooms'][$room_index]['conversation_queue'] = $queue;
        $dungeon_data['rooms'][$room_index]['conversation_state'] = end($queue);
      }
      else {
        unset($dungeon_data['rooms'][$room_index]['conversation_queue']);
        unset($dungeon_data['rooms'][$room_index]['conversation_state']);
      }
    }
    else {
      unset($dungeon_data['rooms'][$room_index]['conversation_state']);
    }

    if ($entity_ref === '' || $player_message === '') {
      return NULL;
    }

    $dialogue = $this->buildDeterministicNpcDialogue(
      $campaign_id,
      $entity_ref,
      $display_name,
      $player_message,
      $room_id,
      $dungeon_data,
      $character_id
    );
    if ($dialogue === NULL) {
      return NULL;
    }
    if (!$this->consumeNpcResponseOnce(
      $dungeon_data,
      $room_index,
      $room_id,
      $entity_ref,
      $player_message,
      $dialogue
    )) {
      return NULL;
    }

    return [
      'narrative' => $dialogue,
      'speaker_name' => $display_name !== '' ? $display_name : $entity_ref,
      'entity_ref' => $entity_ref,
      'character_id' => $character_id,
      'player_message' => $player_message,
      'intent' => (string) ($state['intent'] ?? ''),
    ];
  }


  protected function pendingRoomMessageAppliesToActorTurn(array $state, array $current_npc, array $room_npcs): bool {
    $intent = (string) ($state['intent'] ?? '');
    $message = trim((string) ($state['pending_player_message'] ?? ''));
    if ($message === '') {
      return FALSE;
    }

    return match ($intent) {
      'direct_npc_dialogue', 'direct_npc_transaction' => (($this->resolveDirectlyAddressedNpc($room_npcs, $message)['entity_ref'] ?? '') === (string) ($current_npc['entity_ref'] ?? '')),
      'quest_query' => $this->npcSupportsQuestOrLeadDialogue($current_npc),
      'merchant_inquiry' => $this->npcSupportsMerchantDialogue($current_npc),
      default => FALSE,
    };
  }

  /**
   * Build a shared consume-once key for one NPC response in one encounter step.
   */

  protected function buildNpcResponseConsumptionKey(
    array $dungeon_data,
    string $room_id,
    string $entity_ref,
    string $player_message,
    string $npc_dialogue
  ): string {
    $game_state = is_array($dungeon_data['game_state'] ?? NULL) ? $dungeon_data['game_state'] : [];
    $round_raw = is_numeric($game_state['round'] ?? NULL) ? (int) $game_state['round'] : -1;
    $turn = is_array($game_state['turn'] ?? NULL) ? $game_state['turn'] : [];
    $turn_index_raw = is_numeric($turn['index'] ?? NULL) ? (int) $turn['index'] : -1;

    $normalized_room = trim(strtolower($room_id));
    $normalized_entity = $this->normalizeNpcResponseConsumptionEntityRef($entity_ref);
    $normalized_player_message = $this->normalizeNpcNameForMatch($this->encounterTranscriptPrefixService->stripPrefix($player_message));
    $normalized_npc_dialogue = $this->normalizeNpcNameForMatch($this->encounterTranscriptPrefixService->stripPrefix($npc_dialogue));
    $signature = sha1(implode('|', [
      $normalized_room,
      $normalized_entity,
      $normalized_player_message,
      $normalized_npc_dialogue,
      (string) $round_raw,
      (string) $turn_index_raw,
    ]));

    return 'npc_response:' . $signature;
  }

  /**
   * Consume-once guard for NPC dialogue emission across room-chat paths.
   */

  protected function consumeNpcResponseOnce(
    array &$dungeon_data,
    int|string $room_index,
    string $room_id,
    string $entity_ref,
    string $player_message,
    string $npc_dialogue
  ): bool {
    $consumption_key = $this->buildNpcResponseConsumptionKey(
      $dungeon_data,
      $room_id,
      $entity_ref,
      $player_message,
      $npc_dialogue
    );
    if ($this->hasConsumedNpcResponse($dungeon_data, $room_index, $consumption_key)) {
      return FALSE;
    }

    $this->markConsumedNpcResponse($dungeon_data, $room_index, $consumption_key);
    return TRUE;
  }

  /**
   * Normalize NPC identity for consume-once keys.
   */

  protected function normalizeNpcResponseConsumptionEntityRef(string $entity_ref): string {
    $normalized = trim(strtolower($entity_ref));
    return trim((string) preg_replace('/^npc[_-]?/', '', $normalized));
  }

  /**
   * Check whether this NPC response was already emitted in the current payload.
   */

  protected function hasConsumedNpcResponse(array $dungeon_data, int|string $room_index, string $consumption_key): bool {
    if ($consumption_key === '') {
      return FALSE;
    }

    $room = is_array($dungeon_data['rooms'][$room_index] ?? NULL) ? $dungeon_data['rooms'][$room_index] : [];
    $consumed = is_array($room['npc_response_consumption'] ?? NULL) ? $room['npc_response_consumption'] : [];
    return !empty($consumed[$consumption_key]);
  }

  /**
   * Mark one NPC response as emitted and prune old consume-once markers.
   */

  protected function markConsumedNpcResponse(array &$dungeon_data, int|string $room_index, string $consumption_key): void {
    if ($consumption_key === '' || !isset($dungeon_data['rooms'][$room_index]) || !is_array($dungeon_data['rooms'][$room_index])) {
      return;
    }

    if (!isset($dungeon_data['rooms'][$room_index]['npc_response_consumption']) || !is_array($dungeon_data['rooms'][$room_index]['npc_response_consumption'])) {
      $dungeon_data['rooms'][$room_index]['npc_response_consumption'] = [];
    }

    $consumed = $dungeon_data['rooms'][$room_index]['npc_response_consumption'];
    $consumed[$consumption_key] = time();
    if (count($consumed) > self::NPC_RESPONSE_CONSUMPTION_MAX) {
      $consumed = array_slice($consumed, -self::NPC_RESPONSE_CONSUMPTION_MAX, NULL, TRUE);
    }
    $dungeon_data['rooms'][$room_index]['npc_response_consumption'] = $consumed;
  }


  protected function findRoomNpcMatchingActorTurn(array $room_npcs, string $actor_id): ?array {
    foreach ($room_npcs as $npc) {
      if ($this->roomConversationNpcMatchesActorTurn($npc, $actor_id)) {
        return $npc;
      }
    }

    return NULL;
  }


  protected function roomConversationNpcMatchesActorTurn(array $npc, string $actor_id): bool {
    $actor_id = trim($actor_id);
    if ($actor_id === '') {
      return FALSE;
    }

    $candidates = array_filter([
      (string) ($npc['entity_ref'] ?? ''),
      (string) ($npc['entity']['entity_instance_id'] ?? ''),
      (string) ($npc['entity']['instance_id'] ?? ''),
      (string) ($npc['entity']['id'] ?? ''),
      (string) ($npc['entity']['state']['metadata']['runtime_entity_id'] ?? ''),
      (string) ($npc['entity']['state']['content_id'] ?? ''),
    ]);

    foreach ($candidates as $candidate) {
      if ($candidate === $actor_id) {
        return TRUE;
      }
      if (trim((string) preg_replace('/^npc[_-]?/', '', $candidate)) === trim((string) preg_replace('/^npc[_-]?/', '', $actor_id))) {
        return TRUE;
      }
    }

    return FALSE;
  }


  protected function findLatestRoomPlayerMessage(array $room_meta): string {
    $chat = is_array($room_meta['chat'] ?? NULL) ? $room_meta['chat'] : [];
    for ($index = count($chat) - 1; $index >= 0; $index--) {
      $entry = is_array($chat[$index] ?? NULL) ? $chat[$index] : [];
      if (strtolower((string) ($entry['type'] ?? '')) !== 'player') {
        continue;
      }
      $message = trim((string) ($entry['message'] ?? ''));
      if ($message !== '') {
        return $this->encounterTranscriptPrefixService->stripPrefix($message);
      }
    }

    return '';
  }

  /**
   * Resolve or seed a psychology profile for a room-local campaign NPC row.
   *
   * @param array $seen_refs
   *   Entity refs already added to the room NPC set.
   *
   * @return array
   *   ['entity_ref' => string, 'profile' => array|null]
   */

  protected function resolveCampaignCharacterNpcProfile(int $campaign_id, object $row, array $seen_refs = []): array {
    $candidates = array_values(array_filter([
      $row->instance_id ?: NULL,
      !empty($row->instance_id) ? preg_replace('/^npc_/', '', (string) $row->instance_id) : NULL,
      strtolower(str_replace(' ', '_', $row->name)),
    ]));

    $ref = '';
    $profile = NULL;
    foreach ($candidates as $candidate) {
      if (isset($seen_refs[$candidate])) {
        return [];
      }

      $profile = $this->psychologyService->loadProfile($campaign_id, $candidate);
      if ($profile) {
        $ref = $candidate;
        break;
      }
    }

    if ($ref === '' && !empty($candidates)) {
      $ref = (string) reset($candidates);
    }

    if ($ref !== '' && !$profile) {
      $profile = $this->psychologyService->getOrCreateProfile($campaign_id, $ref, [
        'display_name' => $row->name,
        'creature_type' => $row->instance_id ?: $ref,
        'role' => $row->role ?: 'npc',
        'initial_attitude' => 'indifferent',
      ]);
    }

    return ($ref !== '' && $profile)
      ? ['entity_ref' => $ref, 'profile' => $profile]
      : [];
  }

  /**
   * Register an NPC in the gathered room set, deduplicating by ref and name.
   */

  protected function registerGatheredRoomNpc(
    array &$result,
    array &$seen_refs,
    array &$seen_names,
    string $entity_ref,
    array $entity,
    array $profile
  ): void {
    if ($entity_ref === '' || isset($seen_refs[$entity_ref])) {
      return;
    }

    $display_name = trim((string) ($profile['display_name'] ?? ''));
    $display_key = $display_name !== '' ? strtolower($display_name) : '';
    if ($display_key !== '' && isset($seen_names[$display_key])) {
      $seen_refs[$entity_ref] = TRUE;
      return;
    }

    $result[] = [
      'entity_ref' => $entity_ref,
      'entity' => $entity,
      'profile' => $profile,
    ];
    $seen_refs[$entity_ref] = TRUE;
    if ($display_key !== '') {
      $seen_names[$display_key] = TRUE;
    }
  }

  /**
   * Feed room chat activity to all NPC AI sessions for passive awareness.
   *
   * Even when NPCs don't interject, they observe what's happening. This
   * records the conversation in their AI session so they can reference it
   * in future interactions.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param array $room_npcs
   *   Array from gatherRoomNpcsWithProfiles().
   * @param string $player_message
   *   Player's message.
   * @param string $gm_narrative
   *   GM's reply.
   * @param string|null $skip_ref
   *   Entity ref to skip (already recorded as speaker).
   */

  protected function feedRoomChatToNpcSessions(
    int $campaign_id,
    array $room_npcs,
    string $player_message,
    string $gm_narrative,
    array|string|null $skip_refs = NULL,
    ?string $room_observation = NULL
  ): void {
    $observation = $room_observation ?: "Overheard in the room — Player: {$player_message} | GM reply: {$gm_narrative}";
    $skip_lookup = [];
    if (is_string($skip_refs) && $skip_refs !== '') {
      $skip_lookup[$skip_refs] = TRUE;
    }
    elseif (is_array($skip_refs)) {
      foreach ($skip_refs as $skip_ref) {
        if (is_string($skip_ref) && $skip_ref !== '') {
          $skip_lookup[$skip_ref] = TRUE;
        }
      }
    }

    foreach ($room_npcs as $npc) {
      if (isset($skip_lookup[$npc['entity_ref']])) {
        continue;
      }

      $session_key = $this->sessionManager->npcSessionKey($campaign_id, $npc['entity_ref']);
      // Record as a system/observation message — the NPC "overhears" the exchange.
      $this->sessionManager->appendMessage(
        $session_key,
        $campaign_id,
        'user',
        "[Room observation] {$observation}"
      );
    }
  }

  /**
   * Build a concise room-conversation transcript for NPC prompting.
   */

  protected function buildRoomConversationTranscript(array $chat, int $limit = 8): string {
    $visible_chat = array_values(array_filter($chat, fn(array $msg): bool => !$this->isInternalRoomLogMessage($msg)));
    $recent = array_slice($visible_chat, -1 * max(1, $limit));
    $history_lines = [];
    foreach ($recent as $msg) {
      $speaker = $msg['speaker'] ?? 'Unknown';
      $text = $msg['message'] ?? '';
      if (strlen($text) > 180) {
        $text = substr($text, 0, 177) . '...';
      }
      $history_lines[] = "{$speaker}: {$text}";
    }

    return $history_lines === [] ? '[No recent room dialogue]' : implode("\n", $history_lines);
  }

  /**
   * Detect internal room turn-log messages that should stay out of prompts.
   */

  protected function isInternalRoomLogMessage(array $msg): bool {
    return !empty($msg['internal_log']);
  }

  /**
   * Build a compact character-sheet block for player automation prompts.
   */

  protected function buildPlayerAutomationCharacterContext(array $character_data): string {
    $basic_info = is_array($character_data['basicInfo'] ?? NULL) ? $character_data['basicInfo'] : [];
    $profile = is_array($character_data['profile'] ?? NULL) ? $character_data['profile'] : [];
    $sheet = is_array($profile['character_sheet'] ?? NULL)
      ? $profile['character_sheet']
      : (is_array($character_data['character_sheet'] ?? NULL) ? $character_data['character_sheet'] : []);

    $equipment_names = [];
    foreach (array_slice(array_values(is_array($character_data['equipment'] ?? NULL) ? $character_data['equipment'] : []), 0, 6) as $item) {
      if (!is_array($item)) {
        continue;
      }
      $name = trim((string) ($item['name'] ?? $item['label'] ?? ''));
      if ($name !== '') {
        $equipment_names[] = $name;
      }
    }

    $inventory_names = [];
    foreach (array_slice(array_values(is_array($character_data['inventory'] ?? NULL) ? $character_data['inventory'] : []), 0, 8) as $item) {
      if (!is_array($item)) {
        continue;
      }
      $name = trim((string) ($item['name'] ?? $item['label'] ?? ''));
      if ($name !== '') {
        $inventory_names[] = $name;
      }
    }

    $excerpt = array_filter([
      'basic_info' => array_filter([
        'name' => $basic_info['name'] ?? $character_data['name'] ?? '',
        'ancestry' => $basic_info['ancestry'] ?? $sheet['ancestry'] ?? '',
        'class' => $basic_info['class'] ?? $sheet['class'] ?? '',
        'level' => $basic_info['level'] ?? $sheet['level'] ?? '',
        'background' => $basic_info['background'] ?? $sheet['background'] ?? '',
        'appearance' => $basic_info['appearance'] ?? $sheet['appearance'] ?? '',
      ], static fn($value) => $value !== '' && $value !== NULL),
      'personality' => array_filter([
        'personality' => $basic_info['personality'] ?? $profile['personality_traits'] ?? $profile['personality'] ?? $character_data['personality'] ?? '',
        'motivations' => $profile['motivations'] ?? $character_data['motivations'] ?? '',
        'goals' => $profile['goals'] ?? [],
        'backstory' => $profile['backstory'] ?? $character_data['backstory'] ?? '',
      ], static fn($value) => $value !== '' && $value !== [] && $value !== NULL),
      'sheet' => array_filter([
        'summary' => $sheet['summary'] ?? '',
        'traits' => $sheet['traits'] ?? [],
        'languages' => $sheet['languages'] ?? [],
        'skills' => $sheet['skills'] ?? [],
        'feats' => $sheet['feats'] ?? [],
        'spells' => $sheet['spells'] ?? [],
      ], static fn($value) => $value !== '' && $value !== [] && $value !== NULL),
      'equipment' => $equipment_names,
      'inventory' => $inventory_names,
    ], static fn($value) => $value !== '' && $value !== [] && $value !== NULL);

    $json = json_encode($excerpt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || trim($json) === '' || $json === '[]' || $json === '{}') {
      $json = json_encode($character_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[No character sheet data available]';
    }

    return $this->truncateContextBlock($json, 2600, 0.75);
  }

  /**
   * Build a compact active-quest block for player automation prompts.
   */

  protected function buildPlayerAutomationQuestContext(int $campaign_id, int $character_id, int $max_quests = 3): string {
    if ($campaign_id <= 0 || !$this->questTracker) {
      return '';
    }

    $quests = $character_id > 0
      ? $this->questTracker->getActiveQuests($campaign_id, $character_id)
      : [];

    if ($quests === []) {
      $quests = array_values(array_filter($this->questTracker->getCampaignQuestTracking($campaign_id), static function (array $quest): bool {
        return empty($quest['completed_at']) && (($quest['status'] ?? '') === 'active');
      }));
    }

    if ($quests === []) {
      return '';
    }

    usort($quests, static function (array $a, array $b): int {
      return ((int) ($b['last_updated'] ?? 0)) <=> ((int) ($a['last_updated'] ?? 0));
    });

    $lines = [];
    foreach (array_slice($quests, 0, max(1, $max_quests)) as $quest) {
      $quest_name = trim((string) ($quest['quest_name'] ?? $quest['quest_id'] ?? 'Active quest'));
      $status = trim((string) ($quest['status'] ?? 'active'));
      $current_phase = max(1, (int) ($quest['current_phase'] ?? 1));
      $lines[] = sprintf('- %s [status: %s, phase: %d]', $quest_name, $status !== '' ? $status : 'active', $current_phase);

      foreach (array_slice($this->extractIncompleteObjectivesForPhase($quest, $current_phase), 0, 3) as $objective) {
        $lines[] = '  Objective: ' . $this->truncateContextBlock($objective, 180, 0.85);
      }
    }

    return $lines === [] ? '' : $this->truncateContextBlock(implode("\n", $lines), 900, 0.75);
  }

  /**
   * Build current-room questbook context for GM prompts.
   */

  protected function buildRoomQuestbookPromptContext(int $campaign_id, string $room_id, ?int $character_id, int $max_quests = 5): string {
    if ($campaign_id <= 0 || $room_id === '' || !$this->questTracker) {
      return '';
    }

    $quests = array_values(array_filter($this->questTracker->getCampaignQuestTracking($campaign_id), static function (array $quest) use ($room_id): bool {
      $status = strtolower(trim((string) ($quest['status'] ?? '')));
      if (!in_array($status, ['active', 'lead', 'offered'], TRUE)) {
        return FALSE;
      }
      if (!empty($quest['completed_at'])) {
        return FALSE;
      }
      return trim((string) ($quest['location_id'] ?? '')) === $room_id;
    }));

    if ($quests === []) {
      return '';
    }

    usort($quests, static function (array $a, array $b): int {
      $status_rank = ['active' => 0, 'lead' => 1, 'offered' => 2];
      $a_status = strtolower(trim((string) ($a['status'] ?? '')));
      $b_status = strtolower(trim((string) ($b['status'] ?? '')));
      $rank_compare = ($status_rank[$a_status] ?? 99) <=> ($status_rank[$b_status] ?? 99);
      if ($rank_compare !== 0) {
        return $rank_compare;
      }
      return ((int) ($b['last_updated'] ?? $b['created_at'] ?? 0)) <=> ((int) ($a['last_updated'] ?? $a['created_at'] ?? 0));
    });

    $lines = [
      '=== ROOM QUESTBOOK CONTEXT ===',
      'These are non-completed quests tied to the current room. Treat this as authoritative questbook state for guidance, item searches, and NPC quest dialogue.',
    ];

    foreach (array_slice($quests, 0, max(1, $max_quests)) as $quest) {
      $quest_id = trim((string) ($quest['quest_id'] ?? 'unknown_quest'));
      $quest_name = trim((string) ($quest['quest_name'] ?? $quest_id));
      $status = strtolower(trim((string) ($quest['status'] ?? 'lead'))) ?: 'lead';
      $current_phase = max(1, (int) ($quest['current_phase'] ?? 1));
      $lines[] = sprintf('- %s {quest_id: %s} [status: %s, phase: %d]', $quest_name, $quest_id, $status, $current_phase);

      foreach (array_slice($this->extractIncompleteObjectivesForPhase($quest, $current_phase), 0, 3) as $objective) {
        $lines[] = '  Objective: ' . $this->truncateContextBlock($objective, 240, 0.85);
      }
    }

    return $this->truncateContextBlock(implode("\n", $lines), 1400, 0.75);
  }

  /**
   * Start clearly mentioned location quests for the acting character.
   */

  protected function activateMentionedAvailableQuests(
    int $campaign_id,
    string $room_id,
    ?int $character_id,
    array $dungeon_data,
    ?array $gm_response,
    array $npc_interjections,
    array $quest_touchpoint_hint = [],
    string $player_message = ''
  ): array {
    if (!$this->questTracker || $campaign_id <= 0 || $room_id === '' || !$character_id) {
      return [];
    }

    $message_chunks = [];
    $message_entries = [];
    if (!empty($npc_interjections)) {
      foreach ($npc_interjections as $entry) {
        if (!is_array($entry)) {
          continue;
        }
        $text = trim((string) ($entry['message'] ?? ''));
        if ($text !== '') {
          $message_chunks[] = $text;
          $message_entries[] = [
            'entity_ref' => trim((string) ($entry['entity_ref'] ?? '')),
            'speaker' => trim((string) ($entry['speaker'] ?? $entry['name'] ?? '')),
            'message' => $text,
          ];
        }
      }
    }
    elseif (!empty($gm_response['message'])) {
      $text = trim((string) $gm_response['message']);
      if ($text !== '') {
        $message_chunks[] = $text;
        $message_entries[] = [
          'entity_ref' => trim((string) ($gm_response['entity_ref'] ?? '')),
          'speaker' => trim((string) ($gm_response['speaker_name'] ?? $gm_response['speaker'] ?? '')),
          'message' => $text,
        ];
      }
    }

    $combined_text = trim(implode("\n", array_filter($message_chunks)));
    $player_message = trim($player_message);
    if ($player_message !== '') {
      $combined_text = trim($combined_text !== '' ? ($combined_text . "\n" . $player_message) : $player_message);
    }
    if ($combined_text === '') {
      return [];
    }

    $location_id = $this->resolveRoomSlugForQuery($campaign_id, $room_id, $dungeon_data) ?? $room_id;
    $this->ensureRoomStorylineRuntimeContactsMaterialized($campaign_id, $room_id, $location_id);

    $matches = $this->questTracker->findMentionedAvailableQuests(
      $campaign_id,
      $location_id,
      (int) $character_id,
      $combined_text,
      2,
      5
    );
    $updates = [];
    $status_signal_text = $player_message !== '' ? $player_message : $combined_text;
    foreach ($matches as $quest) {
      $update = $this->surfaceMentionedQuestForDialogue($campaign_id, $quest, $combined_text, 'available_quest', '', $status_signal_text);
      if ($update !== NULL) {
        $updates[] = $update;
      }
      if ($this->didQuestgiverSpeakForQuest($campaign_id, $quest, $message_entries)) {
        $this->applyQuestgiverLeadTouchpoint($campaign_id, (int) $character_id, $room_id, $quest);
      }
    }

    $storyline_updates = $this->activateMentionedBrokeredStorylineQuests(
      $campaign_id,
      $room_id,
      $location_id,
      (int) $character_id,
      $combined_text,
      $message_entries,
      $dungeon_data
    );
    if ($storyline_updates !== []) {
      $updates = array_merge($updates, $storyline_updates);
    }

    $touchpoint_entries = [];
    foreach ($message_entries as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      $entry_ref = trim((string) ($entry['entity_ref'] ?? ''));
      $entry_speaker = trim((string) ($entry['speaker'] ?? $entry['name'] ?? ''));
      if ($entry_ref === '' && $entry_speaker === '') {
        continue;
      }
      $touchpoint_entries[strtolower($entry_ref . '|' . $entry_speaker)] = [
        'entity_ref' => $entry_ref,
        'speaker' => $entry_speaker,
      ];
    }
    foreach ($touchpoint_entries as $touchpoint_entry) {
      $this->applyConversationQuestTouchpoint(
        $campaign_id,
        (int) $character_id,
        $room_id,
        (string) ($touchpoint_entry['entity_ref'] ?? ''),
        (string) ($touchpoint_entry['speaker'] ?? ''),
        $quest_touchpoint_hint,
        $player_message
      );
    }

    if ($updates === [] && $this->looksLikeQuestOrLeadRequest($this->normalizeQuestLeadMatchText($combined_text))) {
      $this->logger->info('Quest-like NPC dialogue had no backed quest match in campaign {campaign_id} for character {character_id}. Speakers: {speakers}. Text: {text}', [
        'campaign_id' => $campaign_id,
        'character_id' => (int) $character_id,
        'speakers' => implode(', ', array_values(array_unique(array_filter(array_map(static function ($entry): string {
          return trim((string) ($entry['speaker'] ?? $entry['name'] ?? $entry['entity_ref'] ?? ''));
        }, $npc_interjections))))),
        'text' => $this->truncateContextBlock($combined_text, 240, 0.85),
      ]);
    }

    return $updates;
  }

  /**
   * Materialize room-scoped storyline contacts required for runtime activation.
   */
  protected function ensureRoomStorylineRuntimeContactsMaterialized(int $campaign_id, string $room_id, string $location_id): void {
    if ($campaign_id <= 0 || !$this->storylineManager) {
      return;
    }

    $candidates = array_values(array_unique(array_filter([
      trim($room_id),
      trim($location_id),
    ])));
    foreach ($candidates as $candidate) {
      $this->storylineManager->ensureRoomRuntimeMaterializationContractsResolved($campaign_id, $candidate);
    }
  }

  /**
   * Merge quest update payloads with deterministic de-duplication.
   */

  protected function mergeQuestUpdatePayloads(array ...$update_sets): array {
    $merged = [];
    foreach ($update_sets as $set) {
      foreach ($set as $update) {
        if (!is_array($update)) {
          continue;
        }
        $quest_id = trim((string) ($update['quest_id'] ?? $update['quest_key'] ?? ''));
        $status = trim((string) ($update['status'] ?? ''));
        $type = trim((string) ($update['type'] ?? ''));
        $source = trim((string) ($update['source'] ?? ''));
        $key = strtolower($quest_id . '|' . $status . '|' . $type . '|' . $source);
        if ($key === '|||') {
          $key = 'raw:' . sha1(json_encode($update));
        }
        if (!array_key_exists($key, $merged)) {
          $merged[$key] = $update;
        }
      }
    }

    return array_values($merged);
  }

  /**
   * Determine whether one of the current dialogue entries came from the quest giver.
   */

  protected function didQuestgiverSpeakForQuest(int $campaign_id, array $quest, array $message_entries): bool {
    $giver_npc_id = (int) ($quest['giver_npc_id'] ?? 0);
    if ($campaign_id <= 0 || $giver_npc_id <= 0 || $message_entries === []) {
      return FALSE;
    }

    $row = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'name', 'instance_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('id', $giver_npc_id)
      ->condition('type', 'npc')
      ->range(0, 1)
      ->execute()
      ->fetchObject();
    if (!$row) {
      return FALSE;
    }

    $resolved = $this->resolveCampaignCharacterNpcProfile($campaign_id, $row);
    $giver_refs = array_values(array_filter(array_map('strtolower', [
      trim((string) ($resolved['entity_ref'] ?? '')),
      trim((string) ($row->instance_id ?? '')),
      trim((string) ($row->name ?? '')),
    ])));
    if ($giver_refs === []) {
      return FALSE;
    }

    foreach ($message_entries as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      $entry_refs = array_values(array_filter(array_map('strtolower', [
        trim((string) ($entry['entity_ref'] ?? '')),
        trim((string) ($entry['speaker'] ?? $entry['name'] ?? '')),
      ])));
      if ($entry_refs === []) {
        continue;
      }
      if (array_intersect($giver_refs, $entry_refs) !== []) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Mark "speak to X" objectives complete when a questgiver directly surfaces a quest.
   */

  protected function applyQuestgiverLeadTouchpoint(int $campaign_id, int $character_id, string $room_id, array $quest): void {
    if ($campaign_id <= 0 || $character_id <= 0) {
      return;
    }

    $giver_npc_id = (int) ($quest['giver_npc_id'] ?? 0);
    if ($giver_npc_id <= 0) {
      return;
    }

    $row = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'name', 'role', 'instance_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('id', $giver_npc_id)
      ->condition('type', 'npc')
      ->range(0, 1)
      ->execute()
      ->fetchObject();
    if (!$row) {
      return;
    }

    $resolved = $this->resolveCampaignCharacterNpcProfile($campaign_id, $row);
    $npc_ref = trim((string) ($resolved['entity_ref'] ?? ''));
    $target_name = trim((string) ($row->name ?? ''));
    if ($npc_ref === '' && $target_name === '') {
      return;
    }

    $this->applyConversationQuestTouchpoint($campaign_id, $character_id, $room_id, $npc_ref, $target_name, [], '');

    $quest_id = trim((string) ($quest['quest_id'] ?? ''));
    if ($quest_id === '' || !$this->questTracker) {
      return;
    }

    $status = strtolower(trim((string) ($quest['status'] ?? '')));
    if ($status === 'lead') {
      if ($this->storylineQuestLifecycleService === NULL) {
        throw new \RuntimeException('StorylineQuestLifecycleService is required to promote lead quests.');
      }
      $this->storylineQuestLifecycleService->promoteLeadToOfferedByQuestId($campaign_id, $quest_id);
      $status = 'offered';
    }

    // Do not auto-start offered quests on dialogue touchpoint alone.
    // Offer visibility in the quest journal must be preserved until explicit acceptance/start.
  }

  /**
   * Start preferred available quest templates for the acting character.
   *
   * @param array<int, string> $template_ids
   */

  protected function ensurePreferredQuestTemplatesActive(int $campaign_id, int $character_id, array $template_ids): void {
    if ($campaign_id <= 0 || $character_id <= 0 || !$this->questTracker) {
      return;
    }
    $activation_character_id = $this->resolveQuestActivationCharacterId($campaign_id, $character_id);
    if ($activation_character_id === NULL) {
      return;
    }

    $template_ids = array_values(array_filter(array_map('strval', $template_ids)));
    if ($template_ids === []) {
      return;
    }

    $available = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q', ['quest_id', 'source_template_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('status', ['offered', 'active', 'ready_for_turn_in'], 'IN')
      ->condition('source_template_id', $template_ids, 'IN')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($template_ids as $template_id) {
      foreach ($available as $quest) {
        if (($quest['source_template_id'] ?? '') !== $template_id) {
          continue;
        }

        $quest_id = (string) ($quest['quest_id'] ?? '');
        if ($quest_id !== '') {
          if ($this->storylineQuestLifecycleService === NULL) {
            throw new \RuntimeException('StorylineQuestLifecycleService is required to start offered quests from preferred templates.');
          }
          $this->storylineQuestLifecycleService->startOfferedQuest($campaign_id, $quest_id, $activation_character_id);
        }
        break;
      }
    }
  }

  /**
   * Materialize brokered storyline leads into real quest rows and activate them.
   */

  protected function activateMentionedBrokeredStorylineQuests(
    int $campaign_id,
    string $room_id,
    string $location_id,
    int $character_id,
    string $combined_text,
    array $message_entries = [],
    array $dungeon_data = []
  ): array {
    if (
      $campaign_id <= 0
      || $location_id === ''
      || $character_id <= 0
      || !$this->questTracker
      || !$this->relationshipManager
      || !$this->questGenerator
    ) {
      return [];
    }

    $updates = [];
    $entries = [];
    foreach ($message_entries as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      $message = trim((string) ($entry['message'] ?? ''));
      if ($message === '') {
        continue;
      }
      $entries[] = [
        'entity_ref' => trim((string) ($entry['entity_ref'] ?? '')),
        'speaker' => trim((string) ($entry['speaker'] ?? $entry['name'] ?? '')),
        'message' => $message,
      ];
    }

    if ($entries === []) {
      $entries[] = [
        'entity_ref' => '',
        'speaker' => '',
        'message' => $combined_text,
      ];
    }

    foreach ($entries as $entry) {
      $entity_ref = (string) ($entry['entity_ref'] ?? '');
      $speaker = (string) ($entry['speaker'] ?? $entity_ref);
      $message = (string) ($entry['message'] ?? '');
      $this->logger->info('Brokered storyline entry inspection: campaign={campaign_id} character={character_id} room={room_id} speaker={speaker} entity_ref={entity_ref} brokered_npc={brokered_npc} message={message}', [
        'campaign_id' => $campaign_id,
        'character_id' => $character_id,
        'room_id' => $room_id,
        'speaker' => $speaker,
        'entity_ref' => $entity_ref,
        'brokered_npc' => $this->isBrokeredStorylineNpcRef($entity_ref) ? 'yes' : 'no',
        'message' => $this->truncateContextBlock($message, 240, 0.85),
      ]);
      if (!$this->isBrokeredStorylineNpcRef($entity_ref)) {
        continue;
      }

      $contacts = $this->loadBrokeredStorylineContacts($campaign_id, $entity_ref);
      if ($contacts === []) {
        $this->logger->info('Brokered storyline entry had no contacts: campaign={campaign_id} character={character_id} room={room_id} speaker={speaker} entity_ref={entity_ref}', [
          'campaign_id' => $campaign_id,
          'character_id' => $character_id,
          'room_id' => $room_id,
          'speaker' => $speaker,
          'entity_ref' => $entity_ref,
        ]);
        continue;
      }

      $matched_contacts = $this->selectMentionedBrokeredStorylineContacts($contacts, $message);
      $this->logger->info('Brokered storyline contact match result: campaign={campaign_id} character={character_id} room={room_id} speaker={speaker} entity_ref={entity_ref} contact_count={contact_count} matched_count={matched_count} matched_templates={matched_templates}', [
        'campaign_id' => $campaign_id,
        'character_id' => $character_id,
        'room_id' => $room_id,
        'speaker' => $speaker,
        'entity_ref' => $entity_ref,
        'contact_count' => count($contacts),
        'matched_count' => count($matched_contacts),
        'matched_templates' => implode(', ', array_values(array_filter(array_map(static function (array $contact): string {
          return (string) ($contact['template_id'] ?? $contact['storyline_id'] ?? '');
        }, $matched_contacts)))),
      ]);
      if ($matched_contacts === []) {
        continue;
      }

      $this->logger->info('Brokered storyline dialogue matched contacts: campaign={campaign_id} character={character_id} room={room_id} speaker={speaker} contact_templates={templates}', [
        'campaign_id' => $campaign_id,
        'character_id' => $character_id,
        'room_id' => $room_id,
        'speaker' => $speaker,
        'templates' => implode(', ', array_values(array_filter(array_map(static fn(array $contact): string => (string) ($contact['template_id'] ?? $contact['storyline_id'] ?? ''), $matched_contacts)))),
      ]);

      $this->applyConversationQuestTouchpoint(
        $campaign_id,
        $character_id,
        $room_id,
        $entity_ref,
        (string) ($entry['speaker'] ?? ''),
        [],
        $message
      );

      foreach ($matched_contacts as $contact) {
        $quest_rows = $this->ensureBrokeredStorylineQuestRows($campaign_id, $location_id, $character_id, $contact, $dungeon_data);
        foreach ($quest_rows as $quest) {
          $update = $this->surfaceMentionedQuestForDialogue(
            $campaign_id,
            $quest,
            $message,
            'brokered_storyline',
            (string) ($quest['storyline_id'] ?? '')
          );
          if ($update === NULL) {
            continue;
          }

          $updates[] = $update;

          $this->logger->info('Surfaced brokered storyline quest {quest_id} in campaign {campaign_id} for character {character_id} from NPC {speaker} with status {status}', [
            'quest_id' => (string) ($quest['quest_id'] ?? ''),
            'campaign_id' => $campaign_id,
            'character_id' => $character_id,
            'speaker' => (string) ($entry['speaker'] ?? $entity_ref),
            'status' => (string) ($update['status'] ?? ''),
          ]);
        }
      }
    }

    return $updates;
  }

  /**
   * Select brokered storyline contacts explicitly referenced by dialogue text.
   *
   * @param array<int, array<string, mixed>> $contacts
   * @return array<int, array<string, mixed>>
   */

  protected function selectMentionedBrokeredStorylineContacts(array $contacts, string $text, int $max_matches = 3, int $minimum_score = 2): array {
    $normalized_text = $this->normalizeQuestLeadMatchText($text);
    if ($normalized_text === '' || $contacts === []) {
      return [];
    }

    $matches = [];
    foreach ($contacts as $contact) {
      if (!is_array($contact)) {
        continue;
      }

      $needles = array_filter([
        $this->normalizeQuestLeadMatchText((string) ($contact['storyline_id'] ?? '')),
        $this->normalizeQuestLeadMatchText((string) ($contact['template_id'] ?? '')),
        $this->normalizeQuestLeadMatchText((string) ($contact['name'] ?? '')),
        $this->normalizeQuestLeadMatchText((string) ($contact['quest_giver']['display_name'] ?? '')),
        $this->normalizeQuestLeadMatchText((string) ($contact['lead_location']['label'] ?? '')),
        $this->normalizeQuestLeadMatchText((string) ($contact['quest_giver']['notes'] ?? '')),
        $this->normalizeQuestLeadMatchText((string) ($contact['synopsis'] ?? '')),
      ]);

      $score = 0;
      foreach ($needles as $needle) {
        if ($needle === '') {
          continue;
        }
        if (str_contains($normalized_text, $needle)) {
          $score += 4;
          continue;
        }
        $needle_tokens = array_values(array_filter(explode(' ', $needle), static fn(string $token): bool => strlen($token) >= 4));
        $token_matches = 0;
        foreach ($needle_tokens as $token) {
          if (str_contains($normalized_text, $token)) {
            $token_matches++;
          }
        }
        $score += min(3, $token_matches);
      }

      if ($score < $minimum_score) {
        continue;
      }

      $contact['match_score'] = $score;
      $matches[] = $contact;
    }

    usort($matches, static function (array $a, array $b): int {
      return ((int) ($b['match_score'] ?? 0)) <=> ((int) ($a['match_score'] ?? 0));
    });

    return array_slice($matches, 0, max(1, $max_matches));
  }

  /**
   * Ensure campaign quest rows exist for a brokered storyline contact.
   *
   * @return array<int, array<string, mixed>>
   */

  protected function ensureBrokeredStorylineQuestRows(int $campaign_id, string $location_id, int $character_id, array $contact, array $dungeon_data = []): array {
    $storyline_id = trim((string) ($contact['storyline_id'] ?? ''));
    $template_id = trim((string) ($contact['template_id'] ?? ''));
    if ($storyline_id === '' && $template_id === '') {
      return [];
    }

    $query = $this->database->select('dc_campaign_storylines', 's')
      ->fields('s', ['storyline_id', 'storyline_data'])
      ->condition('campaign_id', $campaign_id)
      ->range(0, 1);
    $storyline_condition = $query->orConditionGroup();
    if ($storyline_id !== '') {
      $storyline_condition->condition('storyline_id', $storyline_id);
    }
    if ($template_id !== '') {
      $storyline_condition->condition('template_id', $template_id);
    }
    $storyline_row = $query
      ->condition($storyline_condition)
      ->execute()
      ->fetchAssoc();

    if (!is_array($storyline_row) && $template_id !== '' && $this->storylineManager) {
      try {
        $instantiated = $this->storylineManager->instantiateStorylineTemplate($campaign_id, $template_id, [
          'status' => 'lead',
          'priority' => (int) ($contact['priority'] ?? 0),
          'realize_storyline_assets' => TRUE,
        ]);
        if ($instantiated !== []) {
          $storyline_row = [
            'storyline_id' => (string) ($instantiated['storyline_id'] ?? $template_id),
            'storyline_data' => json_encode($instantiated['storyline_data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          ];
        }
      }
      catch (\Throwable $e) {
        $this->logger->warning('Failed to instantiate brokered storyline template {template_id} in campaign {campaign_id}: {message}', [
          'template_id' => $template_id,
          'campaign_id' => $campaign_id,
          'message' => $e->getMessage(),
        ]);
      }
    }

    if (!is_array($storyline_row)) {
      $this->logger->warning('Brokered storyline contact {storyline_id} has no campaign storyline row in campaign {campaign_id}.', [
        'storyline_id' => $storyline_id !== '' ? $storyline_id : $template_id,
        'campaign_id' => $campaign_id,
      ]);
      return [];
    }

    $storyline_data = json_decode((string) ($storyline_row['storyline_data'] ?? '{}'), TRUE);
    if (!is_array($storyline_data)) {
      return [];
    }

    $template_ids = $this->getStorylineQuestTemplateIdsForActivation($storyline_data);
    if ($template_ids === []) {
      $this->logger->warning('Brokered storyline contact {storyline_id} has no linked quest templates ready for activation in campaign {campaign_id}.', [
        'storyline_id' => $storyline_id,
        'campaign_id' => $campaign_id,
      ]);
      return [];
    }

    $quest_rows = [];
    if ($this->storylineQuestLifecycleService === NULL) {
      throw new \RuntimeException('StorylineQuestLifecycleService is required for brokered storyline quest materialization.');
    }
    foreach ($template_ids as $template_id) {
      $existing = $this->storylineQuestLifecycleService->loadQuestByTemplate($campaign_id, $template_id);
      if (!is_array($existing) || $existing === []) {
        $entry_point = is_array($storyline_data['metadata']['generated_outline']['entry_point'] ?? NULL)
          ? $storyline_data['metadata']['generated_outline']['entry_point']
          : [];
        $primary_dungeon_id = trim((string) ($entry_point['primary_dungeon_id'] ?? ''));
        $generation_context = [
          'party_level' => $this->loadCharacterQuestLevel($campaign_id, $character_id),
          'difficulty' => 'moderate',
          'location' => $location_id,
          'location_tags' => [$location_id, 'storyline_lead'],
          'storyline_id' => $storyline_id,
          'storyline_template_id' => (string) ($contact['template_id'] ?? ''),
          'giver_npc_id' => $this->resolveCampaignNpcIdForBrokeredContact($campaign_id, $contact),
        ];
        $generation_dungeon_data = [];
        if ($primary_dungeon_id !== '') {
          $generation_context['dungeon_id'] = $primary_dungeon_id;
          $generation_context['map_id'] = $primary_dungeon_id;
          $generation_dungeon_data = $this->resolveBrokeredGenerationDungeonData($campaign_id, $primary_dungeon_id);
          if ($generation_dungeon_data === []) {
            $this->logger->info('Deferred brokered storyline quest generation until dungeon is instantiated: campaign={campaign_id} storyline_id={storyline_id} template_id={template_id} required_dungeon={required_dungeon}', [
              'campaign_id' => $campaign_id,
              'storyline_id' => $storyline_id,
              'template_id' => $template_id,
              'required_dungeon' => $primary_dungeon_id,
            ]);
            continue;
          }
        }
        elseif ($dungeon_data !== []) {
          $generation_dungeon_data = $dungeon_data;
        }
        if ($generation_dungeon_data !== []) {
          $generation_context['dungeon_data'] = $generation_dungeon_data;
        }
        $this->logger->info('Attempting brokered storyline quest generation: campaign={campaign_id} storyline_id={storyline_id} template_id={template_id} character_id={character_id} location={location} giver_npc_id={giver_npc_id}', [
          'campaign_id' => $campaign_id,
          'storyline_id' => $storyline_id,
          'template_id' => $template_id,
          'character_id' => $character_id,
          'location' => $location_id,
          'giver_npc_id' => $this->resolveCampaignNpcIdForBrokeredContact($campaign_id, $contact),
        ]);
        try {
          $quest_data = $this->questGenerator->generateQuestFromTemplate($template_id, $campaign_id, $generation_context);
        }
        catch (\Throwable $e) {
          $this->logger->warning('Failed to generate brokered storyline quest from template {template_id} in campaign {campaign_id}: {message}', [
            'template_id' => $template_id,
            'campaign_id' => $campaign_id,
            'message' => $e->getMessage(),
          ]);
          continue;
        }
        if ($quest_data === []) {
          $template_row = $this->database->select('dc_canonical_quests', 't')
            ->fields('t')
            ->condition('template_id', $template_id)
            ->range(0, 1)
            ->execute()
            ->fetchAssoc();
          $this->logger->warning('Failed to generate brokered storyline quest from template {template_id} in campaign {campaign_id}. storyline_id={storyline_id} contact_template_id={contact_template_id} template_found={template_found} template_columns={template_columns}', [
            'template_id' => $template_id,
            'campaign_id' => $campaign_id,
            'storyline_id' => $storyline_id,
            'contact_template_id' => (string) ($contact['template_id'] ?? ''),
            'template_found' => is_array($template_row) ? 'yes' : 'no',
            'template_columns' => is_array($template_row) ? implode(',', array_keys($template_row)) : '',
          ]);
          continue;
        }

        $existing = $this->storylineQuestLifecycleService->ensureOfferedQuestFromTemplateAndLoad(
          $campaign_id,
          $template_id,
          static fn(): array => $quest_data
        );
      }

      if (!is_array($existing) || $existing === []) {
        continue;
      }

      $this->attachStorylineReferenceToQuestRow(
        $campaign_id,
        (string) ($existing['quest_id'] ?? ''),
        $storyline_id,
        is_array($storyline_data['linked_quests'][$template_id] ?? NULL) ? $storyline_data['linked_quests'][$template_id] : []
      );
      $quest_rows[] = $existing;
    }

    return $quest_rows;
  }

  /**
   * Resolve an active player character id for quest activation scopes.
   */
  protected function resolveQuestActivationCharacterId(int $campaign_id, int $character_id): ?int {
    if ($campaign_id <= 0 || $character_id <= 0) {
      return NULL;
    }

    $row = $this->database->select('dc_campaign_characters', 'cc')
      ->fields('cc', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('id', $character_id)
      ->condition('type', 'pc')
      ->condition('role', 'player')
      ->condition('is_active', 1)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    $resolved_id = is_array($row) ? (int) ($row['id'] ?? 0) : 0;
    return $resolved_id > 0 ? $resolved_id : NULL;
  }

  /**
   * Load instantiated dungeon_data for one brokered storyline primary dungeon id.
   */
  protected function resolveBrokeredGenerationDungeonData(int $campaign_id, string $primary_dungeon_id): array {
    if ($campaign_id <= 0 || trim($primary_dungeon_id) === '') {
      return [];
    }

    $query = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->range(0, 1);
    $or = $query->orConditionGroup()
      ->condition('dungeon_id', $primary_dungeon_id);
    if ($this->database->schema()->fieldExists('dc_campaign_dungeons', 'source_dungeon_id')) {
      $or->condition('source_dungeon_id', $primary_dungeon_id);
    }
    $row = $query
      ->condition($or)
      ->orderBy('updated', 'DESC')
      ->execute()
      ->fetchAssoc();

    $dungeon_data = json_decode((string) ($row['dungeon_data'] ?? '{}'), TRUE);
    return is_array($dungeon_data) ? $dungeon_data : [];
  }

  /**
   * Determine which storyline-linked quest templates should activate next.
   *
   * @return array<int, string>
   */

  protected function getStorylineQuestTemplateIdsForActivation(array $storyline_data): array {
    $chapter_id = trim((string) ($storyline_data['current_chapter_id'] ?? ''));
    $scene_id = trim((string) ($storyline_data['current_scene_id'] ?? ''));
    $chapters = array_values(is_array($storyline_data['chapters'] ?? NULL) ? $storyline_data['chapters'] : []);

    if ($chapter_id === '' && !empty($chapters[0]['chapter_id'])) {
      $chapter_id = (string) $chapters[0]['chapter_id'];
    }

    if ($scene_id === '' && $chapter_id !== '') {
      foreach ($chapters as $chapter) {
        if ((string) ($chapter['chapter_id'] ?? '') !== $chapter_id) {
          continue;
        }
        $scene_id = (string) ($chapter['scenes'][0]['scene_id'] ?? '');
        break;
      }
    }

    foreach ($chapters as $chapter) {
      if ($chapter_id !== '' && (string) ($chapter['chapter_id'] ?? '') !== $chapter_id) {
        continue;
      }

      if ($scene_id !== '') {
        foreach (($chapter['scenes'] ?? []) as $scene) {
          if ((string) ($scene['scene_id'] ?? '') === $scene_id) {
            return array_values(array_unique(array_filter(array_map('strval', $scene['quest_ids'] ?? []))));
          }
        }
      }

      $chapter_quest_ids = array_values(array_unique(array_filter(array_map('strval', $chapter['quest_ids'] ?? []))));
      if ($chapter_quest_ids !== []) {
        return $chapter_quest_ids;
      }
    }

    $linked = is_array($storyline_data['linked_quests'] ?? NULL) ? $storyline_data['linked_quests'] : [];
    return array_values(array_unique(array_filter(array_map('strval', array_keys($linked)))));
  }

  /**
   * Attach storyline metadata to a materialized quest row.
   */

}
