<?php

namespace Drupal\dungeoncrawler_content\Service;

trait RoomChatServiceIntentAndDeterminismTrait {

  protected function looksLikeNavigationQuery(string $normalized_message): bool {
    if ($this->textContainsAny($normalized_message, [
      'what exits do i have',
      'what exits do we have',
      'what exits are here',
      'what exits are available',
      'what exits do i see',
      'what are my exits',
      'what are the exits',
      'which exits do i have',
      'which exits are available',
      'any exits',
      'what is the next room',
      'what s the next room',
      'what is in the next room',
      'what s in the next room',
      'what do we find in the next room',
      'what is beyond the door',
      'what s beyond the door',
      'what is through the door',
      'what s through the door',
      'where does the door go',
      'where does this door go',
      'where does that door go',
      'which way have not i been',
      'which way havent i been',
      'which way haven t i been',
      'which way have we not been',
      'which way havent we been',
      'where have not i been',
      'where havent i been',
      'where have not we been',
      'where havent we been',
      'what have i not explored',
      'what have we not explored',
      'which path is unexplored',
      'which tunnel is unexplored',
      'which passage is unexplored',
      'which door is unexplored',
      'what is the unexplored path',
      'what is the unexplored tunnel',
      'what is the unexplored passage',
      'what is ahead',
      'what s ahead',
    ])) {
      return TRUE;
    }

    if (preg_match('/\b(?:what|which|where|any)\b.{0,40}\b(?:exit|exits|door|doors|path|paths|passage|passages|tunnel|tunnels|way|ways|route|routes)\b/u', $normalized_message)) {
      return TRUE;
    }

    if (preg_match('/\b(?:how|where)\b.{0,40}\b(?:leave|get out|go(?:\s+from)?\s+here|head\s+from\s+here)\b/u', $normalized_message)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Determine whether the player is asking who is present in the room.
   */

  protected function looksLikeRoomRosterQuery(string $normalized_message): bool {
    if ($this->textContainsAny($normalized_message, [
      'who is here',
      'who else is here',
      'who all is here',
      'who is in here',
      'who is in the room',
      'who all is in the room',
      'who is present',
      'who is around',
      'who can we talk',
      'who can i talk',
      'their demeanor',
      'their demeanour',
      'what is their demeanor',
      'what is their demeanour',
      'describe everyone here',
      'who is in the room and',
      'just this one',
      'just this kobold',
      'only one kobold',
      'only kobold',
      'any others',
      'anyone else',
      'anybody else',
      'others hiding',
      'any others hiding',
      'anyone hiding',
      'is anyone hiding',
      'is anybody hiding',
    ])) {
      return TRUE;
    }

    if ($this->looksLikeExpectedOccupantsIssue($normalized_message)) {
      return TRUE;
    }

    if (preg_match('/\b(?:are|is)\s+there\b.{0,40}\b(?:anyone|anybody|others?|people|npcs?|occupants?|kobolds?)\b/u', $normalized_message)) {
      return TRUE;
    }

    return (bool) preg_match('/\b(?:who|anyone|anybody|someone|somebody|everyone|others?)\b.{0,40}\b(?:here|present|around|inside|in\s+(?:the|this)\s+room)\b/u', $normalized_message);
  }

  /**
   * Determine whether the player is flagging missing expected room occupants.
   */

  protected function looksLikeExpectedOccupantsIssue(string $normalized_message): bool {
    if ($this->textContainsAny($normalized_message, [
      'shouldnt there be',
      'shouldn t there be',
      'should there be',
      'where is everyone',
      'where are the kobolds',
      'where are the hookclaw kobolds',
      'why is no one here',
      'why isnt anyone here',
      'why isn t anyone here',
    ])) {
      return TRUE;
    }

    if (preg_match('/\b(?:should(?:\s*not|n\s*t|nt)?\s+there\s+be|where\s+(?:is|are)|why\s+(?:is|are))\b.{0,80}\b(?:everyone|anyone|anybody|kobolds?|hookclaws?|people|occupants?|npcs?|meet(?:ing)?(?:\s+us)?|supposed\s+to\s+meet)\b/u', $normalized_message)) {
      return TRUE;
    }

    if (preg_match('/\bwhere\b.{0,80}\b(?:people|anyone|anybody|kobolds?|hookclaws?|occupants?|npcs?)\b.{0,40}\b(?:supposed\s+to\s+meet|meet(?:ing)?\s+us)\b/u', $normalized_message)) {
      return TRUE;
    }

    return (bool) preg_match('/\b(?:no\s+one|nobody|still\s+no)\b.{0,40}\b(?:here|around|present)\b/u', $normalized_message);
  }

  /**
   * Determine whether to force grounded GM analysis for unmatched phrasing.
   */

  protected function looksLikeGmBackstopQuery(string $player_message, string $normalized_message): bool {
    if (preg_match('/\?$/u', trim($player_message))) {
      return TRUE;
    }

    if (preg_match('/^\s*(?:what|where|which|who|why|how|is|are|can|could|would|should|do|does|did)\b/u', $normalized_message)) {
      return TRUE;
    }

    return $this->textContainsAny($normalized_message, [
      'expected',
      'supposed to',
      'still missing',
      'not here',
      'no one here',
      'nobody here',
      'something is wrong',
    ]);
  }

  /**
   * Determine whether the player wants a grounded room description.
   */

  protected function looksLikeRoomDescriptionQuery(string $normalized_message): bool {
    return $this->textContainsAny($normalized_message, [
      'describe the room',
      'describe this room',
      'describe this place',
      'describe the area',
      'describe where i am',
      'describe where we are',
      'what do i see',
      'what do we see',
      'what does it look like',
      'what does this place look like',
      'what is this place',
      'what is this room',
      'what s this place',
      'what s this room',
      'what is here',
      'what s here',
      'look around',
      'room description',
      'give me the description',
      'give me a description',
      'description please',
      'explanation',
      'description',
    ]);
  }

  /**
   * Determine whether the player is explicitly asking the GM for adjudication.
   */

  protected function looksLikeGmAdjudicationQuery(string $player_message, string $normalized_message): bool {
    if (preg_match('/(?:^|[.!?]\s+|,\s*)(?:gm|game master)\b\s*[,:-]?/iu', trim($player_message))) {
      return TRUE;
    }

    if ($this->textContainsAny($normalized_message, [
      'whose turn',
      'who s turn',
      'whos turn',
      'who is up',
      'who goes next',
      'my turn',
      'your turn',
      'what turn is it',
      'which npc is getting resolved',
      'which npc is being resolved',
      'nothing is happening',
      'its stuck',
      'it s stuck',
      'stuck again',
      'broken',
      'why is nothing happening',
      'do them one at a time',
      'something is wrong',
      'something is really fucked up',
      'do i know',
      'would i know',
      'do we know',
      'would we know',
      'what do i know',
      'what would i know',
      'have i heard',
      'have we heard',
      'have i seen',
      'have we seen',
      'do i recognize',
      'would i recognize',
      'do i recognise',
      'would i recognise',
      'would i remember',
      'do i remember',
      'would i recall',
      'do i recall',
      'can i tell',
      'can we tell',
      'do i notice',
      'what do i notice',
      'would burasco know',
      'would burasco recognize',
      'would burasco recognise',
      'would burasco remember',
      'would burasco recall',
      'would burasco notice',
    ])) {
      return TRUE;
    }

    return (bool) preg_match('/\bwould\s+[a-z][a-z\'-]*\s+(?:know|recognize|recognise|remember|recall|notice)\b/ui', $normalized_message);
  }

  /**
   * Detect explicit user correction about GM/player role boundaries.
   */

  protected function looksLikeGmRoleBoundaryCorrection(string $normalized_message): bool {
    return $this->textContainsAny($normalized_message, [
      'gm isnt supposed to act as the player',
      'gm isn t supposed to act as the player',
      'gm shouldnt act as the player',
      'gm shouldn t act as the player',
      'dont act as the player',
      'don t act as the player',
      'dont play my character',
      'don t play my character',
      'dont speak for me',
      'don t speak for me',
      'leave my character to me',
      'you are not supposed to act as the player',
      'you should not act as the player',
      'stay in referee narration',
    ]);
  }

  /**
   * Treat scoped follow-ups as continuing the active direct NPC thread.
   */

  protected function looksLikeDirectNpcConversationContinuation(string $player_message, string $normalized_message = ''): bool {
    if ($normalized_message === '') {
      $normalized_message = $this->normalizeNpcNameForMatch($player_message);
    }
    if ($normalized_message === '') {
      return FALSE;
    }

    if (preg_match('/\?$/u', trim($player_message))) {
      return TRUE;
    }

    // Player-facing chat often includes emotes or quoted speech while staying
    // on the same NPC thread. Keep those turns scoped to the active NPC
    // instead of dropping them into the generic GM narration path.
    if (preg_match('/^\s*\*/u', $player_message) || preg_match('/["“”]/u', $player_message)) {
      return TRUE;
    }

    if (preg_match('/\b(?:ok|okay|alright|all right|yes)\b.{0,24}\b(?:give|tell|show|point|explain|continue|go on)\b/u', $normalized_message)) {
      return TRUE;
    }

    if ($this->textContainsAny($normalized_message, [
      'deal',
      'done',
      'agreed',
      'ill take it',
      'i ll take it',
      'take it',
      'no more',
      'point me to',
      'give me the information',
      'give me info',
      'give me the details',
      'give me details',
      'nearest tavern',
      'sent me',
      'tell me',
      'tell me more',
      'show me',
      'go on',
      'continue',
      'go ahead',
      'let me look',
      'im looking',
      'i m looking',
      'look at',
      'what does',
      'what is',
      'what s',
      'can you',
      'could you',
      'would you',
      'do you',
      'did you',
      'are you',
      'text',
      'note',
      'letter',
      'phrase',
      'presented',
      'showed',
      'handed',
      'mission',
      'job',
      'quest',
      'work',
      'story',
      'stories',
      'storyline',
      'storylines',
      'module',
      'modules',
    ])) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Keep an explicit room conversation thread active until the player clearly pivots.
   */

  protected function shouldContinueActiveRoomConversation(
    string $player_message,
    string $normalized_message,
    array $active_conversation_npc
  ): bool {
    if (trim($normalized_message) === '') {
      return FALSE;
    }
    if ($this->isExplicitRoomGmAddress($player_message)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Detect room-action pivots that should break an active direct NPC thread.
   */

  protected function looksLikeActiveRoomConversationPivot(string $normalized_message): bool {
    if ($normalized_message === '') {
      return TRUE;
    }

    if ((bool) preg_match('/\b(?:i|we|let me|i ll|i will)?\s*(?:wait|hold|delay|end)\s+(?:my\s+)?turn\b/u', $normalized_message)) {
      return TRUE;
    }

    if ((bool) preg_match('/\b(?:i|we|let me|i ll|i will)\s+(?:search|inspect|examine|investigate|check|study|look around|open|take|pick up|grab|cast|use|attack|strike|shoot|loot|listen|hide|sneak|climb|jump|push|pull|move)\b/u', $normalized_message)) {
      return TRUE;
    }

    return (bool) preg_match('/\b(?:search|inspect|examine|investigate|look around|open|cast|attack|use)\b(?:\s+the\s+room|\s+around|\s+nearby)\b/u', $normalized_message);
  }

  /**
   * Determine whether the player is clearly starting a fight.
   */

  protected function looksLikeCombatEngagementTurn(string $normalized_message): bool {
    return $this->textContainsAny($normalized_message, [
      'i attack',
      'we attack',
      'attack the ',
      'kill the ',
      'kill all these ',
      'kill all the ',
      'kill those ',
      'fight the ',
      'fight those ',
      'engage the ',
      'engage those ',
      'lets kill',
      'let us kill',
      'lets fight',
      'let us fight',
      'smash them',
      'start smashing',
      'wipe them out',
      'take them down',
      'start combat',
      'begin combat',
    ]);
  }

  /**
   * Build a deterministic navigation action from a travel-style player turn.
   */

  protected function buildDeterministicNavigationAction(
    int $campaign_id,
    ?int $character_id,
    string $player_message,
    array $room_meta = [],
    string $room_id = '',
    array $dungeon_data = []
  ): ?array {
    $this->logger->notice('Deterministic navigation entry: room_id=@room_id room_name=@room_name player_message=@player_message room_count=@room_count', [
      '@room_id' => $room_id,
      '@room_name' => (string) ($room_meta['name'] ?? ''),
      '@player_message' => $player_message,
      '@room_count' => count($dungeon_data['rooms'] ?? []),
    ]);
    $destination = $this->extractNavigationDestination($player_message, $room_meta, $room_id, $dungeon_data);
    if ($destination === NULL) {
      $this->logger->notice('Deterministic navigation exit: room_id=@room_id result=no_destination_match', [
        '@room_id' => $room_id,
      ]);
      return NULL;
    }

    $origin_name = trim((string) ($room_meta['name'] ?? 'the room'));
    $destination_description = 'A new area reached from ' . $origin_name . ' by moving toward ' . $destination . '.';
    $return_trip = $this->hasVisitedDestinationName($dungeon_data, $destination);
    $normalized = $this->normalizeNpcNameForMatch($player_message);
    $door_move = $this->textContainsAny($normalized, [
      'open the door',
      'go in',
      'go inside',
      'head in',
      'head inside',
      'enter the ',
      'enter through',
    ]);
    if ($door_move) {
      $narrative = $return_trip
        ? 'Beyond the door, the way back toward ' . $destination . ' lies open.'
        : 'Beyond the door, the way onward toward ' . $destination . ' opens up.';
    }
    else {
      $narrative = $return_trip
        ? 'The route back toward ' . $destination . ' opens from ' . $origin_name . '.'
        : 'From ' . $origin_name . ', the way onward leads toward ' . $destination . '.';
    }

    $this->logger->notice('Deterministic navigation exit: room_id=@room_id destination=@destination destination_description=@destination_description return_trip=@return_trip door_move=@door_move', [
      '@room_id' => $room_id,
      '@destination' => $destination,
      '@destination_description' => $destination_description,
      '@return_trip' => $return_trip ? 'yes' : 'no',
      '@door_move' => $door_move ? 'yes' : 'no',
    ]);

    $action_payload = $this->buildCanonicalNavigationActionPayload(
      [
        'name' => 'Travel to ' . $destination,
        'details' => [
          'destination' => $destination,
          'destination_description' => $destination_description,
          'travel_type' => 'walk',
          'estimated_distance' => 'short',
          'source_room_id' => $room_id,
        ],
        'state_changes' => [
          'character' => [],
          'room' => [],
        ],
      ],
      $campaign_id,
      $room_id,
      $character_id !== NULL ? 'pc_' . $character_id : NULL
    );

    return [
      'narrative' => $narrative,
      'action' => $action_payload,
    ];
  }

  /**
   * Extract a destination phrase from a player navigation message.
   */

  protected function extractNavigationDestination(string $player_message, array $room_meta = [], string $room_id = '', array $dungeon_data = []): ?string {
    $patterns = [
      '/(?:lets|let\'s|let\s+us)\s+had\s+to\s+(?:the\s+)?([a-z0-9][a-z0-9\'\-\s]+)/i',
      '/(?:leave(?:\s+for)?|head(?:ing)?\s+(?:to|for)|travel(?:ing)?\s+(?:to|for)|move(?:ing)?\s+(?:to|toward|towards)|journey(?:ing)?\s+(?:to|for)|set out for|depart for|go to|navigation to|navigating to)\s+(?:the\s+)?([a-z0-9][a-z0-9\'\-\s]+)/i',
      '/(?:exit via)\s+(?:the\s+)?([a-z0-9][a-z0-9\'\-]*(?:\s+[a-z0-9][a-z0-9\'\-]*)+)[.!?]*$/i',
      '/(?:use)\s+(?:the\s+)?([a-z0-9][a-z0-9\'\-]*(?:\s+[a-z0-9][a-z0-9\'\-]*)*\s+(?:door|exit|passage|path|tunnel|stairs?|gate|portal|bridge))[.!?]*$/i',
      '/(?:meet you there\.?\s*then i leave for)\s+(?:the\s+)?([a-z0-9][a-z0-9\'\-\s]+)/i',
      '/(?:open(?:ing)?\s+(?:the\s+)?door\s+and\s+(?:go|head|step|move|walk|enter)\s+(?:in|inside|through)|enter(?:ing)?\s+(?:the\s+)?door|go(?:ing)?\s+(?:in|inside)|head(?:ing)?\s+(?:in|inside))\b/i',
    ];
    foreach ($patterns as $pattern) {
      if (!preg_match($pattern, $player_message, $matches)) {
        continue;
      }
      if (count($matches) < 2) {
        return 'Beyond the door';
      }
      $destination = trim((string) ($matches[1] ?? ''));
      $destination = preg_replace('/\s+to\s+(?:talk|speak|meet|ask|check(?:\s+on)?|find|get|grab|collect|turn\s*[- ]?in|hand\s*[- ]?in)\b.*$/i', '', $destination) ?? $destination;
      $destination = preg_replace('/\s+(?:with|and|then|after|before)\b.*$/i', '', $destination) ?? $destination;
      $destination = preg_replace('/\s+(?:again|now|please|today|tonight|tomorrow|asap|immediately|right now)\b.*$/i', '', $destination) ?? $destination;
      $destination = trim($destination, " \t\n\r\0\x0B.,!?;:\"'");
      if ($destination !== '') {
        return ucwords(strtolower($destination));
      }
    }

    $normalized = $this->normalizeNpcNameForMatch($player_message);
    if ($this->textContainsAny($normalized, ['go outside', 'head outside', 'step outside', 'leave the tavern'])) {
      $origin_name = trim((string) ($room_meta['name'] ?? 'the building'));
      return 'Outside ' . $origin_name;
    }

    $preferred_exit = $this->resolvePreferredNavigationExit($room_meta, $room_id, $dungeon_data);
    $directional_exit = $this->resolveDirectionalNavigationExit($normalized, $room_meta, $room_id, $dungeon_data);
    if ($directional_exit !== NULL) {
      return (string) $directional_exit['name'];
    }

    if ($preferred_exit !== NULL && $this->textContainsAny($normalized, [
      'next room',
      'through the door',
      'beyond the door',
      'go deeper',
      'head deeper',
      'move deeper',
      'press farther',
      'press further',
      'push on',
      'break down the door',
      'kick in the door',
      'bust it loose',
      'go there',
      'head there',
      'move there',
      'go that way',
      'head that way',
      'lets go there',
      'let us go there',
      'lets go',
      'let us go',
      'unexplored path',
      'unexplored tunnel',
      'unexplored passage',
    ])) {
      return $preferred_exit['name'];
    }

    if ($preferred_exit !== NULL && preg_match('/^(?:ok|okay|yeah|yea|yep|sure|alright|all right)?\s*(?:(?:lets|let us)\s+go(?:\s+there|\s+that way)?|do it|proceed|take it|take that exit|take that door)[.!?]*$/i', trim($player_message))) {
      return $preferred_exit['name'];
    }

    return NULL;
  }

  /**
   * Determine whether the latest player turn should still count as room entry.
   */

  protected function isEffectiveRoomEntryTurn(array $chat): bool {
    if ($chat === []) {
      return FALSE;
    }

    $latest = end($chat);
    if (!is_array($latest) || ($latest['type'] ?? '') !== 'player') {
      return FALSE;
    }

    $prior = array_slice($chat, 0, -1);
    if ($prior === []) {
      return TRUE;
    }

    foreach ($prior as $entry) {
      if (!$this->isArrivalNarrationMessage($entry)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Check whether a chat entry is the automatic arrival/return narration.
   */

  protected function isArrivalNarrationMessage(array $entry): bool {
    $speaker = (string) ($entry['speaker'] ?? '');
    $message = trim((string) ($entry['message'] ?? ''));

    return in_array($speaker, ['Game Master', 'System'], TRUE)
      && preg_match('/^You (arrive|return) at .+\.$/i', $message) === 1;
  }

  /**
   * Build a direct, grounded room description without NPC dialogue.
   */

  protected function buildDeterministicRoomDescriptionNarrative(
    int $campaign_id,
    string $room_id,
    array $room_meta,
    array $dungeon_data,
    array $room_npcs = []
  ): string {
    $parts = [];

    $room_name = trim((string) ($room_meta['name'] ?? ''));
    $room_description = trim((string) ($room_meta['description'] ?? ''));
    if ($room_description !== '') {
      $parts[] = $room_name !== '' && !str_starts_with(strtolower($room_description), strtolower($room_name))
        ? $room_name . ': ' . $room_description
        : $room_description;
    }
    elseif ($room_name !== '') {
      $parts[] = 'You are in ' . $room_name . '.';
    }

    $present_names = [];
    foreach (($room_meta['characters'] ?? []) as $character) {
      $name = trim((string) ($character['name'] ?? $character['display_name'] ?? ''));
      if ($name !== '') {
        $present_names[strtolower($name)] = $name;
      }
    }
    foreach ($room_npcs as $npc) {
      $name = trim((string) ($npc['profile']['display_name'] ?? ''));
      if ($name !== '') {
        $present_names[strtolower($name)] = $name;
      }
    }

    if ($present_names !== []) {
      $parts[] = 'Visible here: ' . implode(', ', array_values($present_names)) . '.';
    }
    else {
      $actor_grounding = trim($this->buildRoomActorGroundingSummary($campaign_id, $room_id, $dungeon_data));
      if ($actor_grounding !== '') {
        $actor_grounding = preg_replace('/^Canonical actor notes for named room occupants:\s*/', '', $actor_grounding) ?? $actor_grounding;
        $actor_grounding = preg_replace('/^- /m', '', $actor_grounding) ?? $actor_grounding;
        $parts[] = 'Named occupants here: ' . str_replace("\n", ' ', trim($actor_grounding));
      }
    }

    return implode(' ', array_filter($parts, static fn(string $part): bool => $part !== ''));
  }

  /**
   * Build a grounded answer for nearby-room / exit questions.
   */

  protected function buildDeterministicNavigationQueryNarrative(array $room_meta = [], string $room_id = '', array $dungeon_data = []): string {
    $current_room_id = $room_id !== '' ? $room_id : (string) ($room_meta['room_id'] ?? '');
    if ($current_room_id === '') {
      return '';
    }

    $exits = $this->actionProcessor->getResolvedRoomExits($dungeon_data, $current_room_id);
    if ($exits === []) {
      return 'No grounded exit is mapped from this room yet. Use "travel to <location>" once a destination is known.';
    }

    $origin_name = trim((string) ($room_meta['name'] ?? 'this room'));
    $preferred_exit = $this->resolvePreferredNavigationExit($room_meta, $room_id, $dungeon_data);
    $formatted_exits = array_map(function (array $exit): string {
      $name = trim((string) ($exit['name'] ?? 'Unknown passage'));
      $type = trim((string) ($exit['connection_type'] ?? 'passage'));
      $status = !empty($exit['explored']) ? 'visited' : 'unexplored';
      return "{$name} ({$type}, {$status})";
    }, $exits);

    $narrative = 'From ' . $origin_name . ', the grounded exits are ' . implode('; ', $formatted_exits) . '.';
    if ($preferred_exit !== NULL) {
      $preferred_name = trim((string) ($preferred_exit['name'] ?? 'the next passage'));
      $preferred_status = !empty($preferred_exit['explored']) ? 'already explored' : 'the next unexplored room';
      $narrative .= ' If you press forward, ' . $preferred_name . ' is ' . $preferred_status . '.';
    }

    return $narrative . ' Use "travel to <location>" or "exit via <exit name>" to move.';
  }

  /**
   * Choose the most likely forward exit for generic travel language.
   */

  protected function resolvePreferredNavigationExit(array $room_meta = [], string $room_id = '', array $dungeon_data = []): ?array {
    $current_room_id = $room_id !== '' ? $room_id : (string) ($room_meta['room_id'] ?? '');
    if ($current_room_id === '') {
      return NULL;
    }

    $exits = $this->actionProcessor->getResolvedRoomExits($dungeon_data, $current_room_id);
    if ($exits === []) {
      return NULL;
    }

    foreach ($exits as $exit) {
      if (empty($exit['explored']) && !empty($exit['name'])) {
        return $exit;
      }
    }

    $backtrack_room_id = (string) ($dungeon_data['last_navigation']['from_room_id'] ?? '');
    foreach ($exits as $exit) {
      if (($exit['room_id'] ?? '') !== $backtrack_room_id && !empty($exit['name'])) {
        return $exit;
      }
    }

    foreach ($exits as $exit) {
      if (!empty($exit['name'])) {
        return $exit;
      }
    }

    return NULL;
  }

  /**
   * Resolve directional travel language (north/south/east/west) to an exit.
   */

  protected function resolveDirectionalNavigationExit(string $normalized_message, array $room_meta = [], string $room_id = '', array $dungeon_data = []): ?array {
    $current_room_id = $room_id !== '' ? $room_id : (string) ($room_meta['room_id'] ?? '');
    if ($current_room_id === '') {
      return NULL;
    }

    $direction_needles = [
      'north' => ['north', 'northward', 'northern'],
      'south' => ['south', 'southward', 'southern'],
      'east' => ['east', 'eastward', 'eastern'],
      'west' => ['west', 'westward', 'western'],
      'northeast' => ['northeast', 'north east'],
      'northwest' => ['northwest', 'north west'],
      'southeast' => ['southeast', 'south east'],
      'southwest' => ['southwest', 'south west'],
    ];

    $requested = NULL;
    foreach ($direction_needles as $canonical => $needles) {
      if ($this->textContainsAny($normalized_message, $needles)) {
        $requested = $canonical;
        break;
      }
    }

    if ($requested === NULL) {
      return NULL;
    }

    $exits = $this->actionProcessor->getResolvedRoomExits($dungeon_data, $current_room_id);
    if ($exits === []) {
      return NULL;
    }

    $matches = [];
    foreach ($exits as $exit) {
      if (!is_array($exit) || empty($exit['name'])) {
        continue;
      }
      $exit_text = strtolower(trim((string) ($exit['name'] ?? '')));
      if ($this->textContainsAny($exit_text, $direction_needles[$requested])) {
        $matches[] = $exit;
      }
    }

    if ($matches === []) {
      return NULL;
    }

    foreach ($matches as $match) {
      if (empty($match['explored'])) {
        return $match;
      }
    }

    return $matches[0];
  }

  /**
   * Collect hostile entities present in the current room.
   */

  protected function findRoomHostileEntities(string $room_id, array $dungeon_data, string $player_message = ''): array {
    if ($room_id === '') {
      return [];
    }

    $room_entities = [];
    $hostiles = [];
    foreach ($dungeon_data['entities'] ?? [] as $entity) {
      if (($entity['placement']['room_id'] ?? '') !== $room_id) {
        continue;
      }
      $room_entities[] = $entity;
      $team = strtolower((string) ($entity['state']['metadata']['team'] ?? $entity['team'] ?? ''));
      if (in_array($team, ['hostile', 'enemy', 'monsters'], TRUE)) {
        $hostiles[] = $entity;
      }
    }

    if ($hostiles !== []) {
      return $hostiles;
    }

    $normalized_message = $this->normalizeNpcNameForMatch($player_message);
    if ($normalized_message === '' || $room_entities === []) {
      return [];
    }

    $keywords = [];
    foreach (['rat', 'vermin', 'goblin', 'spider', 'skeleton', 'zombie', 'wolf', 'bat'] as $keyword) {
      if (str_contains($normalized_message, $keyword)) {
        $keywords[] = $keyword;
      }
    }
    if ($keywords === []) {
      return [];
    }

    $matched = [];
    foreach ($room_entities as $entity) {
      $team = strtolower((string) ($entity['state']['metadata']['team'] ?? $entity['team'] ?? ''));
      if (in_array($team, ['player', 'ally', 'friendly', 'party'], TRUE)) {
        continue;
      }

      $haystack = strtolower(implode(' ', array_filter([
        (string) ($entity['state']['metadata']['display_name'] ?? ''),
        (string) ($entity['name'] ?? ''),
        (string) ($entity['entity_ref']['content_id'] ?? ''),
        (string) ($entity['state']['metadata']['creature_type'] ?? ''),
        (string) ($entity['type'] ?? ''),
      ])));
      foreach ($keywords as $keyword) {
        if (str_contains($haystack, $keyword)) {
          $matched[] = $entity;
          break;
        }
      }
    }

    if ($matched !== []) {
      return $matched;
    }

    return $hostiles;
  }

  /**
   * Trim clearly incomplete GM output back to the last complete sentence.
   */

  protected function trimIncompleteNarrative(string $narrative): string {
    $narrative = trim($narrative);
    if ($narrative === '') {
      return '';
    }

    if (preg_match('/[.!?]["\')\]}]*$/u', $narrative)) {
      return $narrative;
    }

    $looks_truncated = (bool) preg_match('/\b[\pL\pN]{1,3}$/u', $narrative);
    $length = strlen($narrative);
    for ($i = $length - 1; $i >= 0; $i--) {
      if (!in_array($narrative[$i], ['.', '!', '?'], TRUE)) {
        continue;
      }
      if (!$looks_truncated && $i < (int) floor($length * 0.55)) {
        break;
      }
      return trim(substr($narrative, 0, $i + 1));
    }

    return $narrative;
  }

  /**
   * Remove visible JSON/code-block action output from player-facing narrative.
   */

  protected function stripPlayerVisibleActionBlocks(string $narrative): string {
    $narrative = preg_replace('/\n?```(?:json)?[\s\S]*$/i', '', $narrative) ?? $narrative;
    $narrative = preg_replace('/\n?\{\s*"actions"\s*:\s*\[[\s\S]*$/i', '', $narrative) ?? $narrative;
    $narrative = preg_replace('/\s*(?:Here(?:\'s| is)\s+the\s+JSON\s+action\s+block.*|JSON\s+action\s+block:.*)$/i', '', $narrative) ?? $narrative;

    return trim($narrative);
  }

  /**
   * Remove prompt scaffolding or summary headings that should never be visible.
   */

  protected function sanitizePlayerVisibleNarrative(string $narrative): string {
    $sanitized = preg_replace([
      '/^\s*===.*?===\s*$/m',
      '/^\s*PRIOR SESSION CONTEXT.*$/mi',
      '/^\s*RECENT CONVERSATION.*$/mi',
      '/^\s*Current room:.*$/mi',
      '/^\s*Room description:.*$/mi',
      '/^\s*People ready to answer in this room:.*$/mi',
      '/^\s*NPC profile notes for GM use:.*$/mi',
      '/^\s*Canonical actor notes for named room occupants:.*$/mi',
      '/^\s*\[(?:USER|ASSISTANT)\]:.*$/mi',
    ], '', $narrative);

    $sanitized = preg_replace("/\n{3,}/", "\n\n", (string) $sanitized) ?? $sanitized;
    return trim((string) $sanitized);
  }

  /**
   * Resolve an NPC by model-returned speaker name.
   */

  protected function resolveNamedRoomNpc(array $room_npcs, string $speaker_name): ?array {
    $normalized_speaker = $this->normalizeNpcNameForMatch($speaker_name);
    if ($normalized_speaker === '') {
      return NULL;
    }

    foreach ($room_npcs as $npc) {
      $display_name = (string) ($npc['profile']['display_name'] ?? '');
      if ($display_name !== '' && strcasecmp($display_name, $speaker_name) === 0) {
        return $npc;
      }
    }

    $matches = [];
    foreach ($room_npcs as $npc) {
      $display_name = (string) ($npc['profile']['display_name'] ?? '');
      if ($display_name === '') {
        continue;
      }

      $score = $this->scoreNpcDirectAddressMatch($display_name, $normalized_speaker);
      if ($score <= 0) {
        continue;
      }
      $matches[] = ['score' => $score, 'npc' => $npc];
    }

    if ($matches === []) {
      if (count($room_npcs) === 1) {
        $only_npc = $room_npcs[0];
        $this->logger->info('NPC alias resolved: @alias → @canonical', [
          '@alias' => $speaker_name,
          '@canonical' => $only_npc['profile']['display_name'] ?? $only_npc['entity_ref'],
        ]);
        return $only_npc;
      }

      return NULL;
    }

    return $this->selectHighestScoredNpc($matches);
  }

  /**
   * Select the highest-scored NPC, rejecting ambiguous ties.
   */

  protected function selectHighestScoredNpc(array $matches): ?array {
    if ($matches === []) {
      return NULL;
    }

    usort($matches, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    if (count($matches) > 1 && $matches[0]['score'] === $matches[1]['score']) {
      return NULL;
    }

    return $matches[0]['npc'] ?? NULL;
  }

  /**
   * Normalize free text for forgiving NPC name matching.
   */

  protected function normalizeNpcNameForMatch(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9\s]+/', ' ', $value) ?? '';
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return trim($value);
  }

  /**
   * Generate NPC dialogue for a room chat interjection using full psychology context.
   *
   * This is the second step of the two-phase interjection system:
   * 1. evaluateNpcInterjections() decides WHO speaks.
   * 2. This method generates WHAT they say, using the NPC's full character sheet,
   *    personality, backstory, inner monologue, and session memory.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * @param int|string $room_index
   *   Room index in dungeon_data.
   * @param array $dungeon_data
   *   Full dungeon data.
   * @param string $entity_ref
   *   NPC entity reference (e.g., 'gribbles_rindsworth').
   * @param string $display_name
   *   NPC display name (e.g., 'Gribbles Rindsworth').
   * @param string $player_message
   *   The player's message that triggered this.
   * @param string $gm_narrative
   *   The GM's narrative response.
   *
   * @return string|null
   *   The NPC's dialogue text, or NULL on failure.
   */

  protected function generateNpcRoomDialogue(
    int $campaign_id,
    string $room_id,
    int|string $room_index,
    array $dungeon_data,
    string $entity_ref,
    string $display_name,
    string $player_message,
    string $gm_narrative
  ): ?array {
    $deterministic_reply = $this->buildDeterministicNpcDialogue($campaign_id, $entity_ref, $display_name, $player_message, $room_id, $dungeon_data);
    if ($deterministic_reply !== NULL) {
      $this->recordDebugStage('npc.deterministic_reply', hrtime(true), [
        'npc_entity' => $entity_ref,
        'length' => strlen($deterministic_reply),
      ]);
      return $this->buildCharacterDialoguePayload(
        $campaign_id,
        $room_id,
        $entity_ref,
        $display_name,
        'room',
        'room_interjection',
        $deterministic_reply,
        'deterministic',
        NULL,
        NULL,
        TRUE,
        FALSE
      );
    }

    // Find the live entity instance for real-time stats.
    $live_entity = [];
    $room_meta = $dungeon_data['rooms'][$room_index] ?? [];
    $entities = $room_meta['entities'] ?? [];
    foreach ($entities as $ent) {
      $ent_ref = $ent['entity_ref']['content_id'] ?? $ent['entity_ref'] ?? '';
      if ($ent_ref === $entity_ref) {
        $live_entity = $ent;
        break;
      }
    }

    // Build full NPC psychology context (character sheet + personality + monologue).
    $npc_context = $this->psychologyService->buildNpcContextForPrompt(
      $campaign_id,
      $entity_ref,
      $live_entity
    );

    // Build NPC session context (conversation memory).
    $session_key = $this->sessionManager->npcSessionKey($campaign_id, $entity_ref);
    $session_context = $this->buildCompactSessionContext($session_key, $campaign_id, 3, 900, 320);

    // Get recent room chat for conversational flow.
    $chat = $dungeon_data['rooms'][$room_index]['chat'] ?? [];
    $recent = array_slice($chat, -4);
    $history_lines = [];
    foreach ($recent as $msg) {
      $speaker = $msg['speaker'] ?? 'Unknown';
      $text = $msg['message'] ?? '';
      if (strlen($text) > 220) {
        $text = substr($text, 0, 217) . '...';
      }
      $history_lines[] = "{$speaker}: {$text}";
    }

    // Room scene context.
    $scene = '';
    if (!empty($room_meta['name'])) {
      $scene .= 'Current room: ' . $room_meta['name'] . "\n";
    }

    // Build the user prompt.
    $storyline_leads_context = $this->buildBrokeredStorylinePromptContext($campaign_id, $entity_ref);
    $prompt = NpcPromptAssembler::buildRoomDialogueUserPrompt(
      $session_context,
      $scene,
      $npc_context,
      $storyline_leads_context,
      $history_lines,
      $player_message,
      $gm_narrative,
      $display_name
    );

    // Get NPC's current attitude for system prompt.
    $npc_attitude = $this->psychologyService->getAttitude($campaign_id, $entity_ref) ?? 'indifferent';

    $system_prompt = NpcPromptAssembler::buildRoomDialogueSystemPrompt($display_name, $npc_attitude);

    try {
      $result = $this->invokeTimedModelCall(
        $prompt,
        'dungeoncrawler_content',
        'npc_room_dialogue',
        [
          'campaign_id' => $campaign_id,
          'room_id' => $room_id,
          'npc_entity' => $entity_ref,
        ],
        [
          'system_prompt' => $system_prompt,
          'max_tokens' => 400,
          'skip_cache' => TRUE,
        ],
        [
          'npc_entity' => $entity_ref,
          'display_name' => $display_name,
          'history_line_count' => count($history_lines),
          'session_context_length' => strlen($session_context),
          'npc_context_length' => strlen($npc_context),
        ]
      );
    }
    catch (\Throwable $e) {
      $this->logger->warning('NPC room dialogue generation failed for @npc: @err', [
        '@npc' => $entity_ref,
        '@err' => $e->getMessage(),
      ]);
      $fallback_reply = $this->buildFallbackNpcRoomDialogue($campaign_id, $entity_ref, $display_name, $player_message);
      return $fallback_reply !== NULL
        ? $this->buildCharacterDialoguePayload($campaign_id, $room_id, $entity_ref, $display_name, 'room', 'room_interjection', $fallback_reply, 'fallback', NULL, NULL, TRUE, FALSE)
        : NULL;
    }

    if (empty($result['success']) || empty($result['response'])) {
      $fallback_reply = $this->buildFallbackNpcRoomDialogue($campaign_id, $entity_ref, $display_name, $player_message);
      return $fallback_reply !== NULL
        ? $this->buildCharacterDialoguePayload($campaign_id, $room_id, $entity_ref, $display_name, 'room', 'room_interjection', $fallback_reply, 'fallback', NULL, NULL, TRUE, FALSE)
        : NULL;
    }

    return $this->buildCharacterDialoguePayload(
      $campaign_id,
      $room_id,
      $entity_ref,
      $display_name,
      'room',
      'room_interjection',
      trim((string) $result['response']),
      'model',
      NULL,
      NULL,
      TRUE,
      FALSE
    );
  }

  /**
   * Build one canonical character dialogue payload for room-chat consumers.
   */

  protected function buildCharacterDialoguePayload(
    int $campaign_id,
    string $room_id,
    ?string $speaker_ref,
    string $speaker_name,
    string $channel,
    string $delivery_type,
    string $text,
    string $generation_source,
    ?string $target_entity = NULL,
    ?string $source_ability = NULL,
    bool $interjection = FALSE,
    bool $direct_addressed = FALSE
  ): array {
    $resolved_entity_ref = ($speaker_ref !== NULL && trim($speaker_ref) !== '') ? trim($speaker_ref) : NULL;
    $payload = [
      'schema_version' => self::CHARACTER_DIALOGUE_SCHEMA_VERSION,
      'entity_ref' => $resolved_entity_ref,
      'speaker_name' => trim($speaker_name),
      'channel' => trim($channel) !== '' ? trim($channel) : 'room',
      'delivery_type' => $delivery_type,
      'text' => trim($text),
      'context' => [
        'campaign_id' => $campaign_id,
        'room_id' => $room_id,
        'generation_source' => $generation_source,
        'target_entity' => ($target_entity !== NULL && trim($target_entity) !== '') ? trim($target_entity) : NULL,
        'source_ability' => ($source_ability !== NULL && trim($source_ability) !== '') ? trim($source_ability) : NULL,
      ],
      'flags' => [
        'interjection' => $interjection,
        'direct_addressed' => $direct_addressed,
      ],
    ];

    if (!$this->stateValidationService) {
      return $payload;
    }

    $validation = $this->stateValidationService->validateCharacterDialogue($payload);
    if (!empty($validation['valid'])) {
      return $payload;
    }

    throw new \RuntimeException('Character dialogue contract violation: ' . implode('; ', $validation['errors'] ?? []));
  }

  /**
   * Convert a canonical character dialogue payload into a persisted chat message.
   */

  protected function buildCharacterDialogueChatMessage(array $dialogue_payload, ?int $character_id = NULL, ?string $encounter_prefix = NULL): array {
    $message_text = (string) ($dialogue_payload['text'] ?? '');
    $message_text = $this->encounterTranscriptPrefixService->prefixChatText($message_text, $encounter_prefix);

    $message = [
      'speaker' => (string) ($dialogue_payload['speaker_name'] ?? 'Unknown'),
      'message' => $message_text,
      'type' => 'npc',
      'channel' => (string) ($dialogue_payload['channel'] ?? 'room'),
      'timestamp' => date('c'),
      'character_id' => $character_id,
      'user_id' => 0,
      'dialogue_payload' => $dialogue_payload,
    ];

    $resolved_entity_ref = trim((string) ($dialogue_payload['entity_ref'] ?? ''));
    if ($resolved_entity_ref !== '') {
      $message['entity_ref'] = $resolved_entity_ref;
    }

    if (!empty($dialogue_payload['flags']['interjection'])) {
      $message['interjection'] = TRUE;
    }

    return $message;
  }

  /**
   * Ensure the GM always emits visible chat text, even for pure mechanics.
   */

  protected function buildVisibleGmNarrative(string $narrative, array $actions = [], ?array $state_diff = NULL, ?array $navigation_result = NULL): string {
    $text = trim($narrative);
    if ($text !== '') {
      return $text;
    }

    $parts = [];
    if (is_array($navigation_result) && empty($navigation_result['error'])) {
      $destination = trim((string) ($navigation_result['destination'] ?? $navigation_result['target_room_id'] ?? ''));
      $parts[] = $destination !== ''
        ? sprintf('Game Master update: the scene shifts toward %s.', $destination)
        : 'Game Master update: the scene changes.';
    }

    $action_names = array_values(array_filter(array_map(static function (array $action): string {
      return trim((string) ($action['name'] ?? $action['type'] ?? ''));
    }, $actions)));
    if ($action_names !== []) {
      $parts[] = 'Game Master update: resolved ' . implode(', ', $action_names) . '.';
    }

    if (is_array($state_diff)) {
      $validation_errors = array_values(array_filter(array_map('strval', (array) ($state_diff['validation_errors'] ?? []))));
      if ($validation_errors !== []) {
        $parts[] = 'Rules note: ' . implode(' ', $validation_errors);
      }

      $character_changes = count((array) ($state_diff['character_changes'] ?? []));
      $room_changes = count((array) ($state_diff['room_changes'] ?? []));
      if ($character_changes > 0 || $room_changes > 0) {
        $parts[] = sprintf(
          'Situational update: %d character change%s, %d room change%s.',
          $character_changes,
          $character_changes === 1 ? '' : 's',
          $room_changes,
          $room_changes === 1 ? '' : 's'
        );
      }
    }

    if ($parts === []) {
      return 'Game Master update: the situation shifts.';
    }

    return trim(implode(' ', $parts));
  }

  /**
   * Build one canonical GM room-response payload.
   */

  protected function buildGmRoomResponsePayload(
    string $narrative,
    array $actions = [],
    array $dice_rolls = [],
    bool $suppress_npc_interjections = FALSE
  ): array {
    $normalized_actions = [];
    foreach (array_slice($actions, 0, 20) as $action) {
      if (!is_array($action)) {
        continue;
      }
      $normalized_actions[] = [
        'type' => $this->truncateContractString((string) ($action['type'] ?? 'unknown'), 100, 'unknown'),
        'name' => $this->truncateContractString((string) ($action['name'] ?? 'Unknown'), 255, 'Unknown'),
      ];
    }

    $payload = [
      'schema_version' => self::GM_ROOM_RESPONSE_SCHEMA_VERSION,
      'speaker' => 'Game Master',
      'channel' => 'room',
      'narrative' => $this->truncateContractString($narrative, 4000, 'Game Master update: the situation shifts.'),
      'mechanical_actions' => $normalized_actions,
      'dice_rolls' => array_values(array_slice($dice_rolls, 0, 20)),
      'flags' => [
        'suppress_npc_interjections' => $suppress_npc_interjections,
      ],
    ];

    if (!$this->stateValidationService) {
      return $payload;
    }

    $validation = $this->stateValidationService->validateGmRoomResponse($payload);
    if (!empty($validation['valid'])) {
      return $payload;
    }

    throw new \RuntimeException('GM room response contract violation: ' . implode('; ', $validation['errors'] ?? []));
  }

  /**
   * Build one canonical room-turn harness payload.
   */

  protected function buildRoomTurnHarnessPayload(array $payload): array {
    $turn_log_key = trim((string) ($payload['turn_log_key'] ?? ''));
    if ($turn_log_key === '') {
      $turn_log_key = uniqid('room_turn_', TRUE);
    }

    $result = [
      'schema_version' => self::ROOM_TURN_HARNESS_SCHEMA_VERSION,
      'player' => is_array($payload['player'] ?? NULL) ? $payload['player'] : ['message' => ''],
      'gm' => is_array($payload['gm'] ?? NULL) ? $payload['gm'] : ['narrative' => ''],
      'gm_addressed' => !empty($payload['gm_addressed']),
      'directly_addressed_npc' => $payload['directly_addressed_npc'] ?? NULL,
      'npc_turns' => array_values(is_array($payload['npc_turns'] ?? NULL) ? $payload['npc_turns'] : []),
      'turn_sequence' => array_values(is_array($payload['turn_sequence'] ?? NULL) ? $payload['turn_sequence'] : []),
      'turn_log_key' => $turn_log_key,
      'turn_logs' => array_values(is_array($payload['turn_logs'] ?? NULL) ? $payload['turn_logs'] : []),
      'messages' => array_map(
        fn (array $message): array => $this->normalizeRoomTurnHarnessMessage($message),
        array_values(array_filter(
          is_array($payload['messages'] ?? NULL) ? $payload['messages'] : [],
          static fn ($message): bool => is_array($message)
        ))
      ),
    ];

    if (!$this->stateValidationService) {
      return $result;
    }

    $validation = $this->stateValidationService->validateRoomTurnHarness($result);
    if (!empty($validation['valid'])) {
      return $result;
    }

    throw new \RuntimeException('Room turn harness contract violation: ' . implode('; ', $validation['errors'] ?? []));
  }

  /**
   * Normalize one room-turn harness message to the strict contract shape.
   */

  protected function normalizeRoomTurnHarnessMessage(array $message): array {
    $normalized = [
      'speaker' => $this->truncateContractString((string) ($message['speaker'] ?? 'Unknown'), 255, 'Unknown'),
      'message' => $this->truncateContractString((string) ($message['message'] ?? ''), 4000),
      'type' => $this->truncateContractString((string) ($message['type'] ?? 'npc'), 64, 'npc'),
      'channel' => $this->truncateContractString((string) ($message['channel'] ?? 'room'), 64, 'room'),
      'timestamp' => $this->truncateContractString((string) ($message['timestamp'] ?? date('c')), 64, date('c')),
      'character_id' => isset($message['character_id']) && $message['character_id'] !== '' ? (int) $message['character_id'] : NULL,
      'user_id' => isset($message['user_id']) ? (int) $message['user_id'] : 0,
    ];

    $entity_ref = trim((string) ($message['entity_ref'] ?? ''));
    if ($entity_ref !== '') {
      $normalized['entity_ref'] = $this->truncateContractString($entity_ref, 160);
    }

    if (array_key_exists('interjection', $message)) {
      $normalized['interjection'] = !empty($message['interjection']);
    }

    $dialogue_payload = $message['dialogue_payload'] ?? NULL;
    if ($dialogue_payload === NULL || is_array($dialogue_payload)) {
      $normalized['dialogue_payload'] = $dialogue_payload;
    }

    return $normalized;
  }

  /**
   * Build one canonical outer room-chat response envelope for postMessage().
   */

  protected function buildRoomChatResponsePayload(array $payload): array {
    $result = [
      'schema_version' => self::ROOM_CHAT_RESPONSE_SCHEMA_VERSION,
      'message' => is_array($payload['message'] ?? NULL) ? $payload['message'] : [],
      'totalMessages' => (int) ($payload['totalMessages'] ?? 0),
      'dungeon_data' => is_array($payload['dungeon_data'] ?? NULL) ? $payload['dungeon_data'] : [],
    ];

    foreach ([
      'gm_response',
      'state_diff',
      'turn_harness',
      'canonical_actions',
      'combat_transition',
      'navigation',
      'timing',
      'debug_trace',
    ] as $optional_object_key) {
      if (array_key_exists($optional_object_key, $payload)) {
        $result[$optional_object_key] = $payload[$optional_object_key];
      }
    }

    foreach (['npc_interjections', 'quest_updates', 'turn_logs', 'turn_sequence'] as $optional_array_key) {
      if (array_key_exists($optional_array_key, $payload)) {
        $result[$optional_array_key] = array_values(is_array($payload[$optional_array_key]) ? $payload[$optional_array_key] : []);
      }
    }

    foreach (['gm_deferred', 'npc_interjections_deferred'] as $optional_bool_key) {
      if (array_key_exists($optional_bool_key, $payload)) {
        $result[$optional_bool_key] = (bool) $payload[$optional_bool_key];
      }
    }

    if (array_key_exists('turn_log_key', $payload)) {
      $result['turn_log_key'] = $payload['turn_log_key'] !== NULL ? (string) $payload['turn_log_key'] : NULL;
    }

    if (array_key_exists('client_request_id', $payload)) {
      $result['client_request_id'] = $payload['client_request_id'] !== NULL ? (string) $payload['client_request_id'] : NULL;
    }

    if (!$this->stateValidationService) {
      return $result;
    }

    $validation = $this->stateValidationService->validateRoomChatResponse($result);
    if (!empty($validation['valid'])) {
      return $result;
    }

    throw new \RuntimeException('Room chat response contract violation: ' . implode('; ', $validation['errors'] ?? []));
  }

  /**
   * Build one canonical queued conversation continuation envelope.
   */

}
