<?php

namespace Drupal\dungeoncrawler_content\Service;

trait RoomChatServiceNpcDialogueAndQuestLeadTrait {

  protected function buildQueuedRoomContinuationPayload(array $payload): array {
    $result = [
      'schema_version' => self::QUEUED_ROOM_CONTINUATION_SCHEMA_VERSION,
      'continued' => !empty($payload['continued']),
      'queued_player_count' => (int) ($payload['queued_player_count'] ?? 0),
      'queued_player_summary' => (string) ($payload['queued_player_summary'] ?? ''),
      'channel' => (string) ($payload['channel'] ?? 'room'),
    ];

    foreach ([
      'gm_response',
      'state_diff',
      'canonical_actions',
      'navigation',
      'turn_harness',
      'timing',
      'debug_trace',
    ] as $optional_object_key) {
      if (array_key_exists($optional_object_key, $payload)) {
        $result[$optional_object_key] = $payload[$optional_object_key];
      }
    }

    foreach (['turn_logs', 'npc_interjections', 'turn_sequence', 'quest_updates'] as $optional_array_key) {
      if (array_key_exists($optional_array_key, $payload)) {
        $result[$optional_array_key] = array_values(is_array($payload[$optional_array_key]) ? $payload[$optional_array_key] : []);
      }
    }

    if (array_key_exists('npc_interjections_deferred', $payload)) {
      $result['npc_interjections_deferred'] = (bool) $payload['npc_interjections_deferred'];
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

    $validation = $this->stateValidationService->validateQueuedRoomContinuation($result);
    if (!empty($validation['valid'])) {
      return $result;
    }

    throw new \RuntimeException('Queued room continuation contract violation: ' . implode('; ', $validation['errors'] ?? []));
  }

  /**
   * Truncate a contract-bound string while preserving a required fallback.
   */

  protected function truncateContractString(string $value, int $max_length, string $fallback = ''): string {
    $normalized = trim($value);
    if ($normalized === '') {
      $normalized = $fallback;
    }
    if ($normalized === '') {
      return '';
    }
    if (strlen($normalized) <= $max_length) {
      return $normalized;
    }
    return rtrim(substr($normalized, 0, $max_length));
  }

  /**
   * Build deterministic NPC dialogue for common low-variance turns.
   */

  protected function buildDeterministicNpcDialogue(
    int $campaign_id,
    string $entity_ref,
    string $display_name,
    string $player_message,
    string $room_id = '',
    array $dungeon_data = [],
    ?int $character_id = NULL
  ): ?string {
    $normalized = $this->normalizeNpcNameForMatch($player_message);
    if ($normalized === '') {
      return NULL;
    }

    $profile = $this->psychologyService->loadProfile($campaign_id, $entity_ref) ?? [];
    $attitude = strtolower((string) ($profile['attitude'] ?? 'indifferent'));
    $role = strtolower((string) ($profile['role'] ?? ''));
    $descriptor = strtolower(trim(implode(' ', array_filter([
      $display_name,
      $entity_ref,
      $role,
      (string) ($profile['motivations'] ?? ''),
      (string) ($profile['occupation'] ?? ''),
      (string) ($profile['backstory'] ?? ''),
    ]))));

    $asks_for_leads = $this->textContainsAny($normalized, [
      'quest', 'job', 'task', 'mission', 'work', 'reward', 'objective',
      'lead', 'where', 'go', 'start', 'contact', 'looking for work',
      'story', 'stories', 'storyline', 'storylines', 'module', 'modules',
    ]) || $this->looksLikeImplicitLeadRequest($normalized);
    if ($asks_for_leads) {
      $available_quest_offer = $this->buildAvailableQuestgiverQuestDialogue($campaign_id, $entity_ref, $display_name, $room_id, $dungeon_data);
      if ($available_quest_offer !== NULL) {
        $this->applyDirectQuestgiverDialogueQuestState($campaign_id, $character_id, $entity_ref, $display_name, $room_id, $dungeon_data);
        return $available_quest_offer;
      }

      $brokered_leads = $this->buildBrokeredStorylineLeadDialogue($campaign_id, $entity_ref, $display_name, $player_message);
      if ($brokered_leads !== NULL) {
        return $brokered_leads;
      }

      $generated_bootstrap = $this->buildGeneratedStorylineLeadDialogue($campaign_id, $entity_ref, $display_name, $player_message, $normalized, $room_id, $character_id);
      if ($generated_bootstrap !== NULL) {
        return $generated_bootstrap;
      }

      $alternate_lead_redirect = $this->buildAlternateQuestLeadRedirectDialogue($campaign_id, $entity_ref, $display_name, $player_message, $room_id, $dungeon_data);
      if ($alternate_lead_redirect !== NULL) {
        return $alternate_lead_redirect;
      }

      if ($this->textContainsAny($descriptor, ['informant', 'gossip', 'rumor', 'rumour', 'broker', 'lead', 'quest', 'mission', 'work', 'job'])) {
        return '"Depends what kind of work you are after. Tell me the kind of trouble you want, and I will point you if I can."';
      }

      return '"If you are after work, say what kind. I might know a lead, or I might know who does."';
    }

    if ($this->looksLikeQuestTurnInHandoff($normalized)) {
      return match ($attitude) {
        'friendly', 'helpful' => '"Got it. I\'ll take those now and verify this handoff against the current objective."',
        'unfriendly', 'hostile' => '"Fine. Leave them here. I\'ll verify the handoff against your current objective."',
        default => '"Understood. Hand them over and I\'ll verify this handoff against your current objective."',
      };
    }

    if ($this->looksLikeQuestOrLeadRequest($normalized) && ($this->npcSupportsQuestOrLeadRole($role) || $this->isBrokeredStorylineNpcRef($entity_ref))) {
      return "\"If you're asking about work, be specific — I can point you toward leads, objectives, or anything ready to turn in.\"";
    }

    if ($this->looksLikeMerchantTransactionText($normalized) && $this->npcSupportsMerchantDescriptor($descriptor)) {
      $merchant_reply = $this->merchantBotService->buildMerchantReply($player_message);
      if ($merchant_reply !== NULL) {
        return '"' . $merchant_reply . '"';
      }
      if ($this->textContainsAny($normalized, ['change', 'pay', 'paid', 'coin', 'gold', 'silver', 'copper'])) {
        return "\"State the item, quantity, and what coin you're paying with, and I'll settle the amount cleanly.\"";
      }
      return "\"Name what you want and how much of it, and I'll give you the price plainly.\"";
    }

    if (preg_match('/\b(?:hello|hi|hey|greetings)\b/u', $normalized)) {
      return match ($attitude) {
        'friendly', 'helpful' => "\"Hello. What can I do for you?\"",
        'unfriendly', 'hostile' => "\"Make it quick.\"",
        default => "\"What do you need?\"",
      };
    }

    if (preg_match('/\b(?:thanks|thank you)\b/u', $normalized)) {
      return match ($attitude) {
        'friendly', 'helpful' => "\"You're welcome.\"",
        'unfriendly', 'hostile' => "\"Mm.\"",
        default => "\"Right.\"",
      };
    }

    $answers = [];
    if ($room_id !== '') {
      $asks_if_alone = $this->textContainsAny($normalized, ['are you alone', 'you alone', 'by yourself', 'just you']);
      $asks_colony_size = $this->textContainsAny($normalized, ['how big is this colony', 'how big is this kobold colony', 'how big is the colony', 'how large is this colony', 'how large is this kobold colony', 'how large is the colony', 'how big is this burrow', 'how big is the burrow', 'how many kobolds', 'how many are in this colony']);

      if ($asks_if_alone) {
        $room_meta = $this->roomLocator->findRoomByRoomId($dungeon_data['rooms'] ?? [], $room_id);
        $other_names = [];
        foreach (($room_meta['entities'] ?? []) as $entity) {
          $entity_type = strtolower((string) ($entity['entity_type'] ?? $entity['type'] ?? ''));
          if ($entity_type !== 'npc') {
            continue;
          }
          $entity_ref_value = (string) ($entity['entity_ref']['content_id'] ?? $entity['entity_ref'] ?? '');
          if ($entity_ref_value === $entity_ref) {
            continue;
          }
          $name = trim((string) ($entity['state']['metadata']['display_name'] ?? $entity['name'] ?? ''));
          if ($name !== '') {
            $other_names[] = $name;
          }
        }
        if ($other_names !== []) {
          $answers[] = $other_names !== []
            ? '"No. You can see ' . implode(', ', array_slice($other_names, 0, 2)) . ' here too."'
            : '"No."';
        }
        else {
          $answers[] = $this->textContainsAny($normalized, ['colony', 'burrow', 'kobold'])
            ? '"In this chamber, yes. In the burrow, no."'
            : '"In this chamber, yes."';
        }
      }

      if ($asks_colony_size) {
        $room_meta = $this->roomLocator->findRoomByRoomId($dungeon_data['rooms'] ?? [], $room_id);
        $room_grounding = strtolower(trim((string) (($room_meta['name'] ?? '') . ' ' . ($room_meta['description'] ?? ''))));
        $answers[] = str_contains($room_grounding, 'network') || str_contains($room_grounding, 'burrow') || str_contains($room_grounding, 'tunnel')
          ? '"This chamber is only one piece of it. The burrow runs deeper through the tunnels beyond what you can see from here."'
          : '"You are only seeing one part of the colony from here."';
      }
    }

    if ($answers !== []) {
      return implode(' ', $answers);
    }

    return NULL;
  }

  /**
   * Provide a deterministic line when NPC dialogue generation fails.
   */

  protected function buildFallbackNpcRoomDialogue(
    int $campaign_id,
    string $entity_ref,
    string $display_name,
    string $player_message
  ): string {
    $attitude = $this->psychologyService->getAttitude($campaign_id, $entity_ref) ?? 'indifferent';
    $player_message = trim($player_message);

    return match ($attitude) {
      'helpful', 'friendly' => sprintf('%s nods. "I hear you. What do you need?"', $display_name),
      'hostile' => sprintf('%s glares. "Choose your next words carefully."', $display_name),
      'unfriendly' => sprintf('%s looks up with obvious reluctance. "I am listening. Speak quickly."', $display_name),
      default => sprintf('%s looks up. "You have my attention. What is it?"', $display_name),
    };
  }

  /**
   * Check whether the player is clearly trying to buy or sell something.
   */

  protected function looksLikeMerchantTransactionText(string $normalized): bool {
    return (bool) preg_match('/\b(?:buy|purchase|sell|price|cost|quote|rent|tab|wares)\b/u', $normalized)
      || (bool) preg_match('/\b(?:trade\s+in|looking\s+for|how much\s+(?:for|is))\b/u', $normalized)
      || (bool) preg_match('/\b(?:pay|paid|change)\b.+\b(?:for|with)\b/u', $normalized)
      || (bool) preg_match('/\b(?:coin|gold|silver|copper)\b.+\b(?:for|price|cost|buy|sell)\b/u', $normalized)
      || (bool) preg_match('/\b(?:\d+|one|two|three|four|five|six|seven|eight|nine|ten|twenty|thirty|forty|fifty|sixty|seventy|eighty|ninety|hundred)\s+(?:gold|silver|copper|coin|coins|silvers|coppers|golds)\b/u', $normalized)
      || (bool) preg_match('/\b(?:\d+|one|two|three|four|five|six|seven|eight|nine|ten|a|an)\s+(?:ale|beer|wine|mead|drink|drinks|round|rooms?|bed|meal|stew|ration|rations)\b/u', $normalized)
      || (bool) preg_match('/\b(?:deal|done|agreed|ill take it|i ll take it|take it|no more|too much|fair price|knock a copper off)\b/u', $normalized);
  }

  /**
   * Check whether the player is explicitly handing over quest items.
   */

  protected function looksLikeQuestTurnInHandoff(string $normalized): bool {
    return (bool) preg_match('/\b(?:here(?:\'?s| is| are)|i(?:\'?m| am)?\s*(?:bringing|brought)|turn(?:ing)?\s*in|hand(?:ing)?\s*(?:over|in)|deliver(?:ed|ing)?|for you)\b/u', $normalized)
      && (bool) preg_match('/\b(?:item|items|component|components|material|materials|bottle|bottles|torch|wine|package|parcel|supplies|goods|book|books|spellbook|spellbooks)\b/u', $normalized);
  }

  /**
   * Determine whether an NPC is merchant-capable.
   */

  protected function npcSupportsMerchantDialogue(array $npc): bool {
    $profile = $npc['profile'] ?? [];
    $descriptor = strtolower(trim((string) (
      ($profile['display_name'] ?? '') . ' '
      . ($profile['role'] ?? '') . ' '
      . ($profile['motivations'] ?? '')
    )));
    return $this->npcSupportsMerchantDescriptor($descriptor);
  }

  /**
   * Determine whether descriptor text implies merchant behavior.
   */

  protected function npcSupportsMerchantDescriptor(string $descriptor): bool {
    return $this->textContainsAny($descriptor, ['keeper', 'merchant', 'vendor', 'shop', 'tavern', 'inn', 'bar', 'sell']);
  }

  /**
   * Return the first obvious merchant in the room, if any.
   */

  protected function findMerchantNpc(array $room_npcs): ?array {
    foreach ($room_npcs as $npc) {
      if ($this->npcSupportsMerchantDialogue($npc)) {
        return $npc;
      }
    }
    return NULL;
  }

  /**
   * Build and execute a deterministic merchant response when possible.
   */

  protected function buildDeterministicMerchantResponse(
    int $campaign_id,
    string $room_id,
    ?array $merchant_npc,
    string $player_message,
    ?int $character_id = NULL
  ): ?array {
    $merchant_name = trim((string) ($merchant_npc['profile']['display_name'] ?? 'The merchant'));
    $merchant_ref = (string) (
      $merchant_npc['instance_id']
      ?? $merchant_npc['entity_instance_id']
      ?? $merchant_npc['profile']['instance_id']
      ?? $merchant_npc['entity_ref']
      ?? ''
    );

    if ($this->merchantTransactionService !== NULL && $merchant_ref !== '') {
      $result = $this->merchantTransactionService->executeChatTransaction(
        $campaign_id,
        $room_id,
        $merchant_ref,
        $player_message,
        $character_id
      );
      if ($result !== NULL) {
        $status = (string) ($result['status'] ?? '');
        $message = rtrim((string) ($result['message'] ?? ''), '. ');

        if ($status === 'needs_item') {
          return [
            'narrative' => $merchant_name . ' waits for you to name the item and quantity clearly.',
            'actions' => [],
            'dice_rolls' => [],
            'validation_errors' => [],
            'suppress_npc_interjections' => TRUE,
          ];
        }

        if ($status === 'quoted') {
          return [
            'narrative' => $merchant_name . ' quotes the trade plainly: ' . $message . '.',
            'actions' => [],
            'dice_rolls' => [],
            'validation_errors' => [],
            'suppress_npc_interjections' => TRUE,
          ];
        }

        if ($status === 'completed_purchase') {
          return [
            'narrative' => $merchant_name . ' completes the sale: ' . $message,
            'actions' => [],
            'dice_rolls' => [],
            'validation_errors' => [],
            'suppress_npc_interjections' => TRUE,
          ];
        }

        if ($status === 'completed_sale') {
          return [
            'narrative' => $merchant_name . ' closes the purchase: ' . $message,
            'actions' => [],
            'dice_rolls' => [],
            'validation_errors' => [],
            'suppress_npc_interjections' => TRUE,
          ];
        }

        if ($status === 'blocked') {
          return [
            'narrative' => $merchant_name . ' cannot close the deal: ' . $message . '.',
            'actions' => [],
            'dice_rolls' => [],
            'validation_errors' => [],
            'suppress_npc_interjections' => TRUE,
          ];
        }
      }
    }

    $plan = $this->merchantBotService->planMerchantTransaction($character_id, $player_message, $campaign_id);
    if ($plan === NULL) {
      return NULL;
    }

    if (($plan['status'] ?? '') === 'needs_item') {
      return [
        'narrative' => $merchant_name . ' waits for you to name the item and quantity clearly.',
        'actions' => [],
        'dice_rolls' => [],
        'validation_errors' => [],
        'suppress_npc_interjections' => TRUE,
      ];
    }

    if (($plan['status'] ?? '') === 'quoted') {
      return [
        'narrative' => $merchant_name . ' quotes the trade plainly: ' . rtrim((string) ($plan['message'] ?? ''), '. ') . '.',
        'actions' => [],
        'dice_rolls' => [],
        'validation_errors' => [],
        'suppress_npc_interjections' => TRUE,
      ];
    }

    if (($plan['status'] ?? '') === 'blocked') {
      return [
        'narrative' => $merchant_name . ' cannot close the deal: ' . rtrim((string) ($plan['message'] ?? 'The trade cannot be completed right now.'), '. ') . '.',
        'actions' => [],
        'dice_rolls' => [],
        'validation_errors' => [],
        'suppress_npc_interjections' => TRUE,
      ];
    }

    if ($character_id === NULL) {
      return [
        'narrative' => $merchant_name . ' is ready to trade, but no acting character is grounded for the transaction.',
        'actions' => [],
        'dice_rolls' => [],
        'validation_errors' => [],
        'suppress_npc_interjections' => TRUE,
      ];
    }

    if (($plan['status'] ?? '') === 'ready_purchase') {
      if ($this->inventoryManagementService === NULL) {
        return [
          'narrative' => $merchant_name . ' is ready to complete the sale, but the inventory service is unavailable in this context.',
          'actions' => [],
          'dice_rolls' => [],
          'validation_errors' => [],
          'suppress_npc_interjections' => TRUE,
        ];
      }
      $item = is_array($plan['item'] ?? NULL) ? $plan['item'] : NULL;
      if ($item === NULL) {
        return NULL;
      }

      $result = $this->inventoryManagementService->purchaseItem(
        (string) $character_id,
        $item,
        'downtime',
        max(1, (int) ($plan['quantity'] ?? 1)),
        $campaign_id
      );

      if (empty($result['success'])) {
        return [
          'narrative' => $merchant_name . ' cannot finish the sale: ' . rtrim((string) ($result['message'] ?? 'The transaction failed.'), '. ') . '.',
          'actions' => [],
          'dice_rolls' => [],
          'validation_errors' => [],
          'suppress_npc_interjections' => TRUE,
        ];
      }

      return [
        'narrative' => $merchant_name . ' completes the sale, handing over '
          . $this->formatMerchantQuantityLabel(max(1, (int) ($plan['quantity'] ?? 1)), (string) ($item['name'] ?? 'the item'))
          . ' for ' . $this->formatMerchantCpAmount((int) ($plan['price_cp'] ?? 0)) . '.',
        'actions' => [],
        'dice_rolls' => [],
        'validation_errors' => [],
        'suppress_npc_interjections' => TRUE,
      ];
    }

    if (($plan['status'] ?? '') === 'ready_sale') {
      if ($this->inventoryManagementService === NULL) {
        return [
          'narrative' => $merchant_name . ' is ready to buy the item, but the inventory service is unavailable in this context.',
          'actions' => [],
          'dice_rolls' => [],
          'validation_errors' => [],
          'suppress_npc_interjections' => TRUE,
        ];
      }
      $transaction = $this->database->startTransaction();
      try {
        foreach (($plan['sale_units'] ?? []) as $sale_unit) {
          $item_instance_id = (string) ($sale_unit['item_instance_id'] ?? '');
          $quantity = max(1, (int) ($sale_unit['quantity'] ?? 1));
          for ($i = 0; $i < $quantity; $i++) {
            $result = $this->inventoryManagementService->sellItem(
              (string) $character_id,
              'character',
              $item_instance_id,
              FALSE,
              $campaign_id,
              'downtime'
            );
            if (empty($result['success'])) {
              throw new \RuntimeException((string) ($result['message'] ?? 'The sale failed.'));
            }
          }
        }
      }
      catch (\Throwable $e) {
        $transaction->rollBack();
        return [
          'narrative' => $merchant_name . ' cannot finish the purchase: ' . rtrim($e->getMessage(), '. ') . '.',
          'actions' => [],
          'dice_rolls' => [],
          'validation_errors' => [],
          'suppress_npc_interjections' => TRUE,
        ];
      }

      return [
        'narrative' => $merchant_name . ' buys '
          . $this->formatMerchantQuantityLabel(max(1, (int) ($plan['quantity'] ?? 1)), (string) ($plan['item_name'] ?? 'the goods'))
          . ' from you for ' . $this->formatMerchantCpAmount((int) ($plan['offer_cp'] ?? 0)) . '.',
        'actions' => [],
        'dice_rolls' => [],
        'validation_errors' => [],
        'suppress_npc_interjections' => TRUE,
      ];
    }

    return NULL;
  }


  protected function formatMerchantCpAmount(int $cp): string {
    if ($cp <= 0) {
      return '0 cp';
    }

    $parts = [];
    $pp = intdiv($cp, 1000);
    $cp -= $pp * 1000;
    $gp = intdiv($cp, 100);
    $cp -= $gp * 100;
    $sp = intdiv($cp, 10);
    $cp -= $sp * 10;

    if ($pp > 0) {
      $parts[] = $pp . ' pp';
    }
    if ($gp > 0) {
      $parts[] = $gp . ' gp';
    }
    if ($sp > 0) {
      $parts[] = $sp . ' sp';
    }
    if ($cp > 0) {
      $parts[] = $cp . ' cp';
    }

    return implode(' ', $parts);
  }


  protected function formatMerchantQuantityLabel(int $quantity, string $item_name): string {
    return $quantity > 1 ? $quantity . ' x ' . $item_name : $item_name;
  }

  /**
   * Builds additional prompt context for brokers like Eldric.
   */

  protected function buildBrokeredStorylinePromptContext(int $campaign_id, string $entity_ref): string {
    $contacts = $this->loadBrokeredStorylineContacts($campaign_id, $entity_ref);
    if ($contacts === []) {
      return '';
    }

    $lines = ["=== AVAILABLE STORYLINE LEADS ==="];
    foreach (array_slice($contacts, 0, 5) as $contact) {
      $storyline_name = trim((string) ($contact['name'] ?? ''));
      $quest_giver_name = trim((string) ($contact['quest_giver']['display_name'] ?? ''));
      $lead_location = trim((string) ($contact['lead_location']['label'] ?? ''));
      $line = '- ';
      if ($storyline_name !== '') {
        $line .= $storyline_name;
      }
      if ($quest_giver_name !== '') {
        $line .= $storyline_name !== '' ? ': ' : '';
        $line .= 'contact ' . $quest_giver_name;
      }
      if ($lead_location !== '') {
        $line .= ' at ' . $lead_location;
      }
      $notes = trim((string) ($contact['lead_location']['notes'] ?? $contact['quest_giver']['relationship_state']['notes'] ?? $contact['synopsis'] ?? ''));
      if ($notes !== '') {
        $line .= '. ' . $notes;
      }
      $lines[] = rtrim($line, '. ') . '.';
    }

    return implode("\n", $lines);
  }

  /**
   * Builds Eldric's deterministic lead handoff.
   */

  protected function buildBrokeredStorylineLeadDialogue(int $campaign_id, string $entity_ref, string $display_name, string $player_message = ''): ?string {
    $contacts = $this->loadBrokeredStorylineContacts($campaign_id, $entity_ref);
    if ($contacts === []) {
      return NULL;
    }

    $selected_contact = $contacts[0] ?? NULL;
    if ($player_message !== '') {
      $mentioned = $this->selectMentionedBrokeredStorylineContacts($contacts, $player_message, 1, 1);
      if ($mentioned !== []) {
        $selected_contact = $mentioned[0];
      }
    }

    if (!is_array($selected_contact)) {
      return NULL;
    }

    $storyline_name = trim((string) ($selected_contact['name'] ?? ''));
    $quest_giver_name = trim((string) ($selected_contact['quest_giver']['display_name'] ?? ''));
    $lead_location = trim((string) ($selected_contact['lead_location']['label'] ?? ''));
    $lead_notes = trim((string) ($selected_contact['quest_giver']['notes'] ?? $selected_contact['quest_giver']['relationship_state']['notes'] ?? $selected_contact['lead_location']['notes'] ?? ''));
    if ($quest_giver_name === '' && $storyline_name === '') {
      return NULL;
    }

    $line = '';
    if ($storyline_name !== '') {
      $line .= 'For ' . $storyline_name . ', ';
    }
    $line .= 'look for ' . ($quest_giver_name !== '' ? $quest_giver_name : 'my contact');
    if ($lead_location !== '') {
      $line .= ' at ' . $lead_location;
    }
    if ($lead_notes !== '') {
      $line .= '; ' . $this->trimNpcDialogueClause($lead_notes);
    }

    $prefix = $display_name !== '' ? 'If you want work, ' : '';
    return '"' . $prefix . $line . '."';
  }

  /**
   * Bootstrap a new storyline lead directly from the active questgiver.
   */

  protected function buildGeneratedStorylineLeadDialogue(
    int $campaign_id,
    string $entity_ref,
    string $display_name,
    string $player_message,
    string $normalized_message,
    string $room_id = '',
    ?int $character_id = NULL
  ): ?string {
    if (!$this->looksLikeStorylineBootstrapRequest($normalized_message)) {
      return NULL;
    }

    $existing = $this->loadExistingQuestgiverStoryline($campaign_id, $entity_ref);
    if ($existing !== NULL) {
      return '"' . ($display_name !== '' ? $display_name . ' nods. ' : '')
        . 'I already gave you the first thread. Start with ' . ((string) ($existing['name'] ?? 'that lead')) . '."';
    }

    if ($this->storylineGenerationService === NULL) {
      $this->logger->notice(
        'Storyline generation bootstrap skipped for {campaign_id}/{entity_ref}; storyline service unavailable.',
        [
          'campaign_id' => $campaign_id,
          'entity_ref' => $entity_ref,
        ]
      );
      return NULL;
    }

    $result = $this->storylineGenerationService->bootstrapCampaignStoryline($campaign_id, [
      'prompt' => $player_message,
      'speaker_npc_id' => $entity_ref,
      'speaker_name' => $display_name,
      'lead_location_id' => $room_id,
      'character_id' => $character_id,
      'source' => 'npc-storyline-bootstrap',
      'status' => 'bootstrapping',
    ]);
    $storyline = is_array($result['storyline'] ?? NULL) ? $result['storyline'] : [];
    $initial_quest = is_array($result['initial_quest'] ?? NULL) ? $result['initial_quest'] : [];
    $storyline_data = is_array($storyline['storyline_data'] ?? NULL) ? $storyline['storyline_data'] : [];
    $outline = is_array($storyline_data['metadata']['generated_outline'] ?? NULL) ? $storyline_data['metadata']['generated_outline'] : [];
    $entry = is_array($outline['entry_dungeon'] ?? NULL) ? $outline['entry_dungeon'] : [];
    $lead_name = trim((string) ($entry['name'] ?? $storyline['name'] ?? 'the first lead'));
    $lead_hint = trim((string) ($entry['lead_location_hint'] ?? 'Follow the first dungeon entrance.'));
    $quest_name = trim((string) ($initial_quest['quest_name'] ?? ''));
    $objective_lines = $initial_quest !== [] ? $this->extractQuestObjectiveDescriptions($initial_quest) : [];
    $objective_hint = trim((string) ($objective_lines[0] ?? ''));

    $clauses = [];
    if ($quest_name !== '') {
      $clauses[] = 'I have work for you: ' . $quest_name;
    }
    if ($objective_hint !== '') {
      $clauses[] = $this->trimNpcDialogueClause($objective_hint);
    }
    $clauses[] = 'Start with ' . $lead_name;
    if ($lead_hint !== '') {
      $clauses[] = $this->trimNpcDialogueClause($lead_hint);
    }

    return '"' . ($display_name !== '' ? $display_name . ' says, ' : '')
      . implode('. ', array_filter($clauses, static fn(string $clause): bool => trim($clause) !== '')) . '."';
  }

  /**
   * Offer an existing campaign quest from the active questgiver before bootstrap.
   */

  protected function buildAvailableQuestgiverQuestDialogue(
    int $campaign_id,
    string $entity_ref,
    string $display_name,
    string $room_id,
    array $dungeon_data = []
  ): ?string {
    $giver_npc_id = $this->resolveCampaignQuestgiverNpcId($campaign_id, $entity_ref, $display_name, $room_id, $dungeon_data);
    if ($giver_npc_id === NULL) {
      return NULL;
    }

    $this->ensureQuestgiverRoomQuestsMaterialized($campaign_id, $giver_npc_id, $room_id, $dungeon_data);

    $location_candidates = array_values(array_unique(array_filter([
      $this->resolveRoomSlugForQuery($campaign_id, $room_id, $dungeon_data),
      $room_id,
    ], static fn($value): bool => is_string($value) && $value !== '')));

    $query = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q', ['quest_id', 'quest_name', 'quest_description', 'generated_objectives', 'status', 'location_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('giver_npc_id', $giver_npc_id)
      ->condition('status', ['offered', 'active'], 'IN')
      ->range(0, 8);

    if ($location_candidates !== []) {
      $query->condition('location_id', $location_candidates, 'IN');
    }

    $rows = $query->execute()->fetchAll();
    if (!$rows) {
      return NULL;
    }

    usort($rows, static function (object $a, object $b): int {
      $status_a = strtolower((string) ($a->status ?? ''));
      $status_b = strtolower((string) ($b->status ?? ''));
      if ($status_a !== $status_b) {
        return $status_a === 'offered' ? -1 : 1;
      }
      return strcmp((string) ($a->quest_name ?? ''), (string) ($b->quest_name ?? ''));
    });

    $lines = [];
    foreach ($rows as $row) {
      $line = $this->buildQuestgiverQuestDialogueLine($row, $display_name);
      if ($line === NULL) {
        continue;
      }
      $lines[] = $line;
      if (count($lines) >= 8) {
        break;
      }
    }

    if ($lines === []) {
      return NULL;
    }

    $speaker = $display_name !== '' ? $display_name . ' says, ' : '';
    return '"' . $speaker . implode(' ', $lines) . '"';
  }

  /**
   * Build the NPC-facing line for an offered or already-active quest.
   */

  protected function buildQuestgiverQuestDialogueLine(object $row, string $display_name = ''): ?string {
    $quest_name = trim((string) ($row->quest_name ?? ''));
    if ($quest_name === '') {
      return NULL;
    }

    $objective_hint = $this->extractQuestgiverObjectiveHint((string) ($row->generated_objectives ?? ''));
    $objective_hint = $this->sanitizeQuestgiverObjectiveHintForSpeaker($objective_hint, $display_name);
    $description_hint = trim((string) ($row->quest_description ?? ''));
    $status = strtolower(trim((string) ($row->status ?? 'offered')));

    if ($status === 'active') {
      $line = 'You are already on ' . $quest_name . '.';
      if ($objective_hint !== '') {
        $line .= ' Start with: ' . $this->trimNpcDialogueClause($objective_hint);
      }
      elseif ($description_hint !== '') {
        $line .= ' Follow this lead: ' . $this->trimNpcDialogueClause($description_hint);
      }
      return $line;
    }

    $line = 'I have work for you: ' . $quest_name . '.';
    if ($objective_hint !== '') {
      $line .= ' ' . $this->trimNpcDialogueClause($objective_hint);
    }
    elseif ($description_hint !== '') {
      $line .= ' ' . $this->trimNpcDialogueClause($description_hint);
    }

    return $line;
  }

  /**
   * Remove self-referential "speak to {current speaker}" objective prefixes.
   */

  protected function sanitizeQuestgiverObjectiveHintForSpeaker(string $objective_hint, string $display_name): string {
    $objective_hint = $this->trimNpcDialogueClause($objective_hint);
    if ($objective_hint === '') {
      return '';
    }

    $display_name = trim($display_name);
    if ($display_name === '') {
      return $objective_hint;
    }

    $display_name = preg_replace('/\s+/', ' ', $display_name) ?? $display_name;
    $objective_hint = preg_replace('/\s+/', ' ', $objective_hint) ?? $objective_hint;
    $self_reference_prefix = '/^(?:speak|talk)\s+(?:to|with)\s+' . preg_quote($display_name, '/') . '(?:\b|[,:;\.\!\?\-])\s*(?:and\s+)?/i';
    $rewritten = preg_replace($self_reference_prefix, '', $objective_hint, 1, $replacement_count);
    if (!is_string($rewritten) || $replacement_count === 0) {
      return $objective_hint;
    }

    $rewritten = $this->trimNpcDialogueClause($rewritten);
    if ($rewritten === '') {
      return '';
    }

    return ucfirst($rewritten);
  }

  /**
   * Apply deterministic quest state changes when a questgiver directly surfaces room quests.
   */

  protected function applyDirectQuestgiverDialogueQuestState(
    int $campaign_id,
    ?int $character_id,
    string $entity_ref,
    string $display_name,
    string $room_id,
    array $dungeon_data = []
  ): void {
    if ($campaign_id <= 0 || !$this->questTracker || !$character_id || $character_id <= 0) {
      return;
    }

    $giver_npc_id = $this->resolveCampaignQuestgiverNpcId($campaign_id, $entity_ref, $display_name, $room_id, $dungeon_data);
    if ($giver_npc_id === NULL) {
      return;
    }

    $location_candidates = array_values(array_unique(array_filter([
      $this->resolveRoomSlugForQuery($campaign_id, $room_id, $dungeon_data),
      $room_id,
    ], static fn($value): bool => is_string($value) && $value !== '')));

    $query = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q')
      ->condition('campaign_id', $campaign_id)
      ->condition('giver_npc_id', $giver_npc_id)
      ->condition('status', ['offered', 'active', 'ready_for_turn_in'], 'IN');

    if ($location_candidates !== []) {
      $query->condition('location_id', $location_candidates, 'IN');
    }

    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as $quest) {
      if (is_array($quest)) {
        $this->applyQuestgiverLeadTouchpoint($campaign_id, (int) $character_id, $room_id, $quest);
      }
    }
  }

  /**
   * Redirect a specific lead request toward the in-room questgiver who matches it.
   */

  protected function buildAlternateQuestLeadRedirectDialogue(
    int $campaign_id,
    string $entity_ref,
    string $display_name,
    string $player_message,
    string $room_id,
    array $dungeon_data = []
  ): ?string {
    $current_giver_npc_id = $this->resolveCampaignQuestgiverNpcId($campaign_id, $entity_ref, $display_name, $room_id, $dungeon_data);
    $candidates = $this->loadRoomQuestLeadCandidates($campaign_id, $room_id, $dungeon_data);
    $best_match = $this->selectBestMatchingQuestLeadCandidate($player_message, $candidates, $current_giver_npc_id ? [$current_giver_npc_id] : []);
    if ($best_match === NULL) {
      return NULL;
    }

    $giver_name = trim((string) ($best_match['giver_name'] ?? ''));
    if ($giver_name === '') {
      return NULL;
    }

    return '"That sounds like ' . $giver_name . '\'s work. Talk to ' . $giver_name . ' directly."';
  }

  /**
   * Materialize canonical quest rows for quests authored directly on a room questgiver.
   */

  protected function ensureQuestgiverRoomQuestsMaterialized(int $campaign_id, int $giver_npc_id, string $room_id, array $dungeon_data = []): void {
    if ($campaign_id <= 0 || $giver_npc_id <= 0 || $room_id === '') {
      return;
    }

    $giver_row = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'instance_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('id', $giver_npc_id)
      ->condition('type', 'npc')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($giver_row)) {
      return;
    }

    $giver_content_id = preg_replace('/^npc_/', '', trim((string) ($giver_row['instance_id'] ?? '')));
    if (!is_string($giver_content_id) || $giver_content_id === '') {
      return;
    }

    $canonical_room_id = $this->resolveRoomSlugForQuery($campaign_id, $room_id, $dungeon_data) ?? $room_id;
    $room_query = $this->database->select('dungeoncrawler_content_rooms', 'r')
      ->fields('r', ['environment_tags', 'contents_data']);
    $room_or = $room_query->orConditionGroup()
      ->condition('room_id', $canonical_room_id)
      ->condition('source_room_id', $canonical_room_id);
    $room_row = $room_query
      ->condition($room_or)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!is_array($room_row)) {
      return;
    }

    $contents = json_decode((string) ($room_row['contents_data'] ?? '{}'), TRUE);
    if (!is_array($contents)) {
      return;
    }

    $template_ids = [];
    foreach ((array) ($contents['npcs'] ?? []) as $npc) {
      if (!is_array($npc) || trim((string) ($npc['content_id'] ?? '')) !== $giver_content_id) {
        continue;
      }
      foreach ((array) ($npc['quests'] ?? []) as $quest) {
        $template_id = trim((string) ($quest['quest_id'] ?? ''));
        if ($template_id !== '') {
          $template_ids[] = $template_id;
        }
      }
      break;
    }

    $template_ids = array_values(array_unique($template_ids));
    if ($template_ids === [] || !\Drupal::hasService('dungeoncrawler_content.quest_generator')) {
      return;
    }

    /** @var \Drupal\dungeoncrawler_content\Service\QuestGeneratorService $quest_generator */
    $quest_generator = \Drupal::service('dungeoncrawler_content.quest_generator');
    $environment_tags = json_decode((string) ($room_row['environment_tags'] ?? '[]'), TRUE);
    $environment_tags = is_array($environment_tags) ? $environment_tags : [];

    foreach ($template_ids as $template_id) {
      $exists = $this->database->select('dc_campaign_quests', 'q')
        ->fields('q', ['quest_id'])
        ->condition('campaign_id', $campaign_id)
        ->condition('source_template_id', $template_id)
        ->range(0, 1)
        ->execute()
        ->fetchField();
      if ($exists) {
        continue;
      }

      $quest_data = $quest_generator->generateQuestFromTemplate($template_id, $campaign_id, [
        'party_level' => 1,
        'difficulty' => 'moderate',
        'location' => $canonical_room_id,
        'location_tags' => $environment_tags,
        'giver_npc_id' => $giver_npc_id,
        'initial_status' => 'offered',
        'dungeon_data' => $dungeon_data,
      ]);
      if ($quest_data !== []) {
        $this->database->insert('dc_campaign_quests')
          ->fields($quest_data)
          ->execute();
      }
    }
  }

  /**
   * Load candidate room quest leads that can satisfy a specific ask.
   */

  protected function loadRoomQuestLeadCandidates(int $campaign_id, string $room_id, array $dungeon_data = []): array {
    $location_candidates = array_values(array_unique(array_filter([
      $this->resolveRoomSlugForQuery($campaign_id, $room_id, $dungeon_data),
      $room_id,
    ], static fn($value): bool => is_string($value) && $value !== '')));

    $query = $this->database->select('dc_campaign_quests', 'q')
      ->fields('q', ['quest_name', 'quest_description', 'generated_objectives', 'giver_npc_id', 'status'])
      ->condition('q.campaign_id', $campaign_id)
      ->condition('q.status', ['lead', 'offered', 'active'], 'IN')
      ->range(0, 24);
    $query->leftJoin('dc_campaign_characters', 'c', 'c.id = q.giver_npc_id');
    $query->addField('c', 'name', 'giver_name');

    if ($location_candidates !== []) {
      $query->condition('q.location_id', $location_candidates, 'IN');
    }

    $rows = $query->execute()->fetchAll();
    return array_map(static fn(object $row): array => (array) $row, $rows ?: []);
  }

  /**
   * Pick the best alternate quest lead for a specific player request.
   */

  protected function selectBestMatchingQuestLeadCandidate(string $player_message, array $candidates, array $exclude_giver_npc_ids = []): ?array {
    $normalized_message = $this->normalizeQuestLeadMatchText($player_message);
    $specific_tokens = $this->extractSpecificQuestLeadTokens($normalized_message);
    if ($normalized_message === '' || $specific_tokens === [] || $candidates === []) {
      return NULL;
    }

    $best_match = NULL;
    $best_score = 0;
    foreach ($candidates as $candidate) {
      $giver_npc_id = (int) ($candidate['giver_npc_id'] ?? 0);
      if ($giver_npc_id > 0 && in_array($giver_npc_id, $exclude_giver_npc_ids, TRUE)) {
        continue;
      }

      $quest_name = $this->normalizeQuestLeadMatchText((string) ($candidate['quest_name'] ?? ''));
      $quest_description = $this->normalizeQuestLeadMatchText((string) ($candidate['quest_description'] ?? ''));
      $objective_hint = $this->normalizeQuestLeadMatchText($this->extractQuestgiverObjectiveHint((string) ($candidate['generated_objectives'] ?? '')));
      $haystacks = array_filter([$quest_name, $quest_description, $objective_hint]);
      if ($haystacks === []) {
        continue;
      }

      $score = 0;
      foreach ($specific_tokens as $token) {
        foreach ($haystacks as $haystack) {
          if (str_contains($haystack, $token)) {
            $score += ($haystack === $quest_name) ? 3 : 1;
            break;
          }
        }
      }

      if ($quest_name !== '' && str_contains($normalized_message, $quest_name)) {
        $score += 4;
      }

      if ($score <= $best_score) {
        continue;
      }

      $best_score = $score;
      $best_match = $candidate;
    }

    return $best_score > 0 ? $best_match : NULL;
  }

  /**
   * Extract topic tokens that indicate the player is already being specific.
   */

  protected function extractSpecificQuestLeadTokens(string $normalized_message): array {
    if ($normalized_message === '') {
      return [];
    }

    $generic_terms = array_flip([
      'quest', 'job', 'task', 'mission', 'reward', 'objective', 'work',
      'lead', 'contact', 'start', 'story', 'stories', 'storyline',
      'storylines', 'module', 'modules', 'looking', 'look', 'want',
      'need', 'after', 'where', 'find', 'talk', 'directly', 'got',
      'have', 'kind', 'does', 'know', 'work',
    ]);

    $tokens = array_values(array_filter(
      explode(' ', $normalized_message),
      static function (string $token) use ($generic_terms): bool {
        return strlen($token) >= 5 && !isset($generic_terms[$token]);
      }
    ));

    return array_values(array_unique($tokens));
  }

  /**
   * Loads brokered storyline contacts for an NPC that introduces module starts.
   */

  protected function loadBrokeredStorylineContacts(int $campaign_id, string $entity_ref): array {
    $canonical_entity_ref = $this->canonicalBrokeredStorylineEntityRef($entity_ref);
    if (!$this->relationshipManager || $canonical_entity_ref === NULL) {
      $this->logger->info('Brokered storyline contact load skipped: campaign={campaign_id} entity_ref={entity_ref} canonical_entity_ref={canonical_entity_ref} relationship_manager={relationship_manager}', [
        'campaign_id' => $campaign_id,
        'entity_ref' => $entity_ref,
        'canonical_entity_ref' => $canonical_entity_ref ?? '',
        'relationship_manager' => $this->relationshipManager ? 'yes' : 'no',
      ]);
      return [];
    }

    $contacts = $this->relationshipManager->getCampaignStorylineContacts($campaign_id, $canonical_entity_ref);
    $this->logger->info('Brokered storyline contacts loaded: campaign={campaign_id} entity_ref={entity_ref} canonical_entity_ref={canonical_entity_ref} contact_count={contact_count} contact_templates={contact_templates}', [
      'campaign_id' => $campaign_id,
      'entity_ref' => $entity_ref,
      'canonical_entity_ref' => $canonical_entity_ref,
      'contact_count' => count($contacts),
      'contact_templates' => implode(', ', array_values(array_filter(array_map(static function (array $contact): string {
        return (string) ($contact['template_id'] ?? $contact['storyline_id'] ?? '');
      }, $contacts)))),
    ]);
    return $contacts;
  }

  /**
   * Determine whether text is asking for quest, mission, or storyline leads.
   */

  protected function looksLikeQuestOrLeadRequest(string $normalized_message): bool {
    return $this->textContainsAny($normalized_message, [
      'quest', 'job', 'task', 'mission', 'reward', 'objective', 'work',
      'lead', 'contact', 'start', 'story', 'stories', 'storyline',
      'storylines', 'module', 'modules',
    ]);
  }

  /**
   * Detect short implied "any work?" follow-ups that omit explicit quest nouns.
   */

  protected function looksLikeImplicitLeadRequest(string $normalized_message): bool {
    return $this->textContainsAny($normalized_message, [
      'you have any',
      'got any',
      'any work',
      'any leads',
      'anything for me',
      'anything i can do',
      'anything available',
    ]);
  }

  /**
   * Determine whether the player is asking for a fresh storyline arc, not just any job.
   */

  protected function looksLikeStorylineBootstrapRequest(string $normalized_message): bool {
    if ($normalized_message === '') {
      return FALSE;
    }

    if ($this->textContainsAny($normalized_message, [
      'storyline',
      'story line',
      'campaign arc',
      'quest arc',
      'adventure arc',
      'main quest',
      'bigger quest',
      'bigger job',
      'long term job',
      'longer quest',
      'something bigger',
      'something major',
    ])) {
      return TRUE;
    }

    if ($this->looksLikeSpecificLeadLookup($normalized_message)) {
      return FALSE;
    }

    return $this->looksLikeGoalShapedStorylineAsk($normalized_message);
  }

  /**
   * Detect explicit requests for a new long-form goal, not a current lead lookup.
   */

  protected function looksLikeGoalShapedStorylineAsk(string $normalized_message): bool {
    $asks_for_new_work = $this->textContainsAny($normalized_message, [
      'looking for',
      'look for',
      'want',
      'need',
      'seeking',
      'after',
      'got any work',
      'any work',
      'any jobs',
      'something to do',
      'something dangerous',
    ]);
    if (!$asks_for_new_work) {
      return FALSE;
    }

    return (bool) preg_match('/\b(?:hunt|track|stop|find|recover|investigate|break|destroy|slay|deal with|help with)\b.{0,64}\b(?:cult|curse|relic|artifact|killer|necromancer|beast|bandit|ruin|mystery|plot|threat|conspiracy|warlord|dragon|missing)\b/u', $normalized_message);
  }

  /**
   * Detect asks that are about following an existing lead rather than starting a new arc.
   */

  protected function looksLikeSpecificLeadLookup(string $normalized_message): bool {
    return $this->textContainsAny($normalized_message, [
      'where is',
      'where can i find',
      'who should i talk to',
      'who do i talk to',
      'what room',
      'which room',
      'where do i go',
      'next step',
      'that quest',
      'this quest',
      'that lead',
      'follow up',
    ]);
  }

  /**
   * Find an existing generated storyline already attached to this questgiver.
   */

  protected function loadExistingQuestgiverStoryline(int $campaign_id, string $entity_ref): ?array {
    $rows = $this->database->select('dc_campaign_storylines', 's')
      ->fields('s')
      ->condition('campaign_id', $campaign_id)
      ->condition('status', ['bootstrapping', 'available', 'active'], 'IN')
      ->orderBy('created_at', 'DESC')
      ->range(0, 10)
      ->execute()
      ->fetchAllAssoc('storyline_id');

    foreach ($rows as $row) {
      $storyline_row = is_array($row) ? $row : (array) $row;
      $storyline_data = json_decode((string) ($storyline_row['storyline_data'] ?? '{}'), TRUE);
      if (!is_array($storyline_data)) {
        continue;
      }

      foreach ((array) ($storyline_data['contacts'] ?? []) as $contact) {
        if (
          is_array($contact)
          && (string) ($contact['role'] ?? '') === 'quest_giver'
          && strtolower(trim((string) ($contact['entity_id'] ?? ''))) === strtolower(trim($entity_ref))
        ) {
          $storyline_row['storyline_data'] = $storyline_data;
          return $storyline_row;
        }
      }
    }

    return NULL;
  }

  /**
   * Resolve the active campaign NPC row id for a canonical questgiver ref.
   */

  protected function resolveCampaignQuestgiverNpcId(
    int $campaign_id,
    string $entity_ref,
    string $display_name,
    string $room_id,
    array $dungeon_data = []
  ): ?int {
    $room_slug = $this->resolveRoomSlugForQuery($campaign_id, $room_id, $dungeon_data);
    $query = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'name', 'role', 'instance_id', 'last_room_id', 'location_ref'])
      ->condition('campaign_id', $campaign_id)
      ->condition('type', 'npc');

    if ($room_id !== '' || $room_slug) {
      $room_match = $query->orConditionGroup();
      if ($room_id !== '') {
        $room_match->condition('last_room_id', $room_id);
      }
      if ($room_slug) {
        $room_match->condition('location_ref', $room_slug);
      }
      $query->condition($room_match);
    }

    $rows = $query->range(0, 16)->execute()->fetchAll();
    foreach ($rows as $row) {
      $resolved = $this->resolveCampaignCharacterNpcProfile($campaign_id, $row);
      $resolved_ref = strtolower(trim((string) ($resolved['entity_ref'] ?? '')));
      if ($resolved_ref !== '' && $resolved_ref === strtolower(trim($entity_ref))) {
        return (int) ($row->id ?? 0) ?: NULL;
      }

      $row_name = strtolower(trim((string) ($row->name ?? '')));
      if ($display_name !== '' && $row_name !== '' && $row_name === strtolower(trim($display_name))) {
        return (int) ($row->id ?? 0) ?: NULL;
      }
    }

    return NULL;
  }

  /**
   * Extract a concise first objective hint from generated quest objectives.
   */

  protected function extractQuestgiverObjectiveHint(string $generated_objectives_json): string {
    $generated_objectives = json_decode($generated_objectives_json, TRUE);
    if (!is_array($generated_objectives)) {
      return '';
    }

    foreach ($generated_objectives as $phase) {
      if (!is_array($phase)) {
        continue;
      }
      $description = $this->findFirstObjectiveDescription((array) ($phase['objectives'] ?? []));
      if ($description !== '') {
        return $description;
      }
    }

    return '';
  }

  /**
   * Find the first non-empty description in a nested objective tree.
   */

  protected function findFirstObjectiveDescription(array $objectives): string {
    foreach ($objectives as $objective) {
      if (!is_array($objective)) {
        continue;
      }

      $description = trim((string) ($objective['description'] ?? $objective['objective_id'] ?? ''));
      if ($description !== '') {
        return $description;
      }

      $child_description = $this->findFirstObjectiveDescription((array) ($objective['children'] ?? []));
      if ($child_description !== '') {
        return $child_description;
      }
    }

    return '';
  }

  /**
   * Determine whether a room NPC should be treated as a quest/lead contact.
   */

  protected function npcSupportsQuestOrLeadDialogue(array $npc): bool {
    $role = strtolower((string) (($npc['profile']['role'] ?? $npc['entity']['role'] ?? '')));
    if ($this->npcSupportsQuestOrLeadRole($role)) {
      return TRUE;
    }

    $entity_ref = (string) ($npc['entity_ref'] ?? '');
    if ($this->isBrokeredStorylineNpcRef($entity_ref)) {
      return TRUE;
    }

    $motivations = strtolower((string) ($npc['profile']['motivations'] ?? ''));
    return $this->textContainsAny($motivations, [
      'work', 'guide', 'lead', 'quest', 'mission', 'objective', 'broker',
    ]);
  }

  /**
   * Determine whether a role label implies quest or guidance authority.
   */

  protected function npcSupportsQuestOrLeadRole(string $role): bool {
    return in_array(strtolower($role), ['quest_giver', 'guide'], TRUE);
  }

  /**
   * Normalize broker NPC aliases to the canonical storyline-contact key.
   */

  protected function canonicalBrokeredStorylineEntityRef(string $entity_ref): ?string {
    return $this->isBrokeredStorylineNpcRef($entity_ref)
      ? 'npc_tavern_keeper'
      : NULL;
  }

  /**
   * Determine whether an entity ref maps to the tavern broker NPC.
   */

  protected function isBrokeredStorylineNpcRef(string $entity_ref): bool {
    return in_array(strtolower(trim($entity_ref)), ['npc_tavern_keeper', 'tavern_keeper'], TRUE);
  }

  /**
   * Normalizes authored notes for short NPC dialogue clauses.
   */

  protected function trimNpcDialogueClause(string $value): string {
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return rtrim($value, ". \t\n\r\0\x0B");
  }

  /**
   * Gather all NPCs in the current room that have psychology profiles.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * @param array $dungeon_data
   *   Full dungeon data.
   *
   * @return array
   *   Array of ['entity_ref' => string, 'entity' => array, 'profile' => array].
   */

  protected function gatherRoomNpcsWithProfiles(int $campaign_id, string $room_id, array $dungeon_data): array {
    return RoomNpcProfileGatherer::gather(
      $campaign_id,
      $room_id,
      $dungeon_data,
      fn(string $ref): ?array => $this->psychologyService->loadProfile($campaign_id, $ref),
      fn(array &$result, array &$seen_refs, array &$seen_names, string $ref, array $entity, array $profile) => $this->registerGatheredRoomNpc($result, $seen_refs, $seen_names, $ref, $entity, $profile),
      fn(): array => $this->loadRoomCampaignNpcRows($campaign_id, $room_id, $dungeon_data),
      fn(object $row, array $seen_refs): array => $this->resolveCampaignCharacterNpcProfile($campaign_id, $row, $seen_refs),
      fn(string $message, array $context = []) => $this->logger->info($message, $context)
    );
  }

  /**
   * Load room-local NPC rows from dc_campaign_characters.
   *
   * @return array
   *   Character rows keyed with name/role/instance_id.
   */

  protected function loadRoomCampaignNpcRows(int $campaign_id, string $room_id, array $dungeon_data): array {
    $room_slug = $this->resolveRoomSlugForQuery($campaign_id, $room_id, $dungeon_data);
    $location_candidates = array_values(array_unique(array_filter([
      $room_slug,
      $room_id,
    ], static fn($value): bool => is_string($value) && $value !== '')));
    if ($location_candidates === []) {
      return [];
    }

    $query = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['name', 'role', 'instance_id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('type', 'npc');
    $room_match = $query->orConditionGroup();
    $room_match->condition('location_ref', $location_candidates, 'IN');
    $room_match->condition('last_room_id', $location_candidates, 'IN');

    return $query
      ->condition($room_match)
      ->execute()
      ->fetchAll();
  }

  /**
   * Load authoritative action availability for the active character.
   */

  protected function loadCharacterActionAvailabilityContext(int $campaign_id, ?int $character_id): array {
    if ($campaign_id <= 0 || ($character_id ?? 0) <= 0 || !\Drupal::hasService('dungeoncrawler_content.game_coordinator')) {
      return [];
    }

    /** @var \Drupal\dungeoncrawler_content\Service\GameCoordinatorService $coordinator */
    $coordinator = \Drupal::service('dungeoncrawler_content.game_coordinator');
    $actor_id = $coordinator->resolveActorIdForCharacterId($campaign_id, (int) $character_id);
    if ($actor_id === NULL || trim($actor_id) === '') {
      return [];
    }

    $availability = $coordinator->getActionAvailabilityForActor($campaign_id, $actor_id);
    if (!is_array($availability)) {
      return [];
    }

    return [
      'available_actions' => is_array($availability['available_actions'] ?? NULL)
        ? $availability['available_actions']
        : [],
      'action_contract' => is_array($availability['action_contract'] ?? NULL)
        ? $availability['action_contract']
        : NULL,
    ];
  }

  /**
   * Build canonical grounding notes for named campaign actors in the active room.
   */

}
