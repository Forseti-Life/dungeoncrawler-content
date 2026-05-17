<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\ai_conversation\Service\AIApiService;

/**
 * Handles GM-style AI chat for character creation.
 */
class CharacterCreationGmService {

  /**
   * Constructs the service.
   */
  public function __construct(
    protected Connection $database,
    protected AccountProxyInterface $currentUser,
    protected TimeInterface $time,
    protected UuidInterface $uuid,
    protected CharacterManager $characterManager,
    protected AbilityScoreTracker $abilityScoreTracker,
    protected ?AIApiService $aiApiService = NULL,
  ) {}

  /**
   * Process a GM chat message for a character draft.
   */
  public function handleMessage(?int $character_id, ?int $campaign_id, int $step, string $message): array {
    if (!$this->aiApiService) {
      throw new \RuntimeException('The GM chat AI service is unavailable.');
    }

    $requested_character_id = $character_id ? (int) $character_id : 0;
    $record = $character_id ? $this->loadOwnedDraft($character_id) : NULL;
    $character_data = $this->loadCharacterData($record);
    $history = $this->getChatHistory($character_data);

    $prompt = $this->buildUserPrompt($message, $step, $character_data, $history);
    $result = $this->aiApiService->invokeModelDirect(
      $prompt,
      'dungeoncrawler_content',
      'character_creation_gm_chat',
      [
        'uid' => (int) $this->currentUser->id(),
        'character_id' => $character_id ?? 0,
        'campaign_id' => $campaign_id ?? 0,
        'step' => $step,
        'message_hash' => sha1($message),
      ],
      [
        'skip_cache' => TRUE,
        'max_tokens' => 2400,
        'system_prompt' => $this->buildSystemPrompt(),
      ],
    );

    if (empty($result['success'])) {
      throw new \RuntimeException((string) ($result['error'] ?? 'GM chat request failed.'));
    }

    $payload = $this->decodeResponsePayload((string) $result['response']);
    $sanitized_updates = $this->sanitizeUpdates($payload['updates'] ?? [], $character_data);
    $applied_updates = $this->applyUpdates($character_data, $sanitized_updates);
    $this->applyDerivedState($character_data);

    $resolved_step = $this->resolveDraftStep($character_data, max(1, min(8, $step)));
    $character_data['step'] = $resolved_step;

    $reply = trim((string) ($payload['reply'] ?? ''));
    if ($reply === '') {
      $reply = 'I updated your draft and saved the character sheet changes.';
    }

    $timestamp = $this->time->getRequestTime();
    $history[] = [
      'role' => 'user',
      'content' => $message,
      'timestamp' => $timestamp,
    ];
    $history[] = [
      'role' => 'assistant',
      'content' => $reply,
      'timestamp' => $timestamp,
      'applied_updates' => array_keys($applied_updates),
    ];
    $character_data['gm_chat'] = [
      'messages' => array_slice($history, -20),
      'last_updated' => date('c', $timestamp),
    ];

    $saved_character_id = $this->saveDraft($record, $character_data, $campaign_id);

    $query = ['character_id' => $saved_character_id];
    if ($campaign_id) {
      $query['campaign_id'] = $campaign_id;
    }

    return [
      'reply' => $reply,
      'character_id' => $saved_character_id,
      'step' => $resolved_step,
      'history' => $character_data['gm_chat']['messages'],
      'applied_updates' => $applied_updates,
      'reload_required' => !empty($applied_updates)
        || $resolved_step !== max(1, min(8, $step))
        || $saved_character_id !== $requested_character_id,
      'reload_url' => \Drupal\Core\Url::fromRoute('dungeoncrawler_content.character_step', ['step' => $resolved_step])
        ->setOption('query', $query)
        ->toString(),
      'summary' => $this->buildSummary($character_data),
    ];
  }

  /**
   * Returns chat history from character data.
   */
  public function getChatHistory(array $character_data): array {
    $messages = $character_data['gm_chat']['messages'] ?? [];
    return is_array($messages) ? array_values($messages) : [];
  }

  /**
   * Builds a small draft summary for the chat dock.
   */
  public function buildSummary(array $character_data): array {
    return [
      'name' => (string) ($character_data['name'] ?? ''),
      'ancestry' => (string) ($character_data['ancestry'] ?? ''),
      'class' => (string) ($character_data['class'] ?? ''),
      'background' => (string) ($character_data['background'] ?? ''),
      'step' => (int) ($character_data['step'] ?? 1),
    ];
  }

  /**
   * Builds the system prompt for the GM.
   */
  private function buildSystemPrompt(): string {
    return <<<'PROMPT'
You are the in-game GM for a Pathfinder 2E character creation wizard.

Your job:
1. Help the player think through choices in plain language.
2. When the player directly asks you to change the draft, return structured updates that the app can apply automatically.
3. Operate on the real wizard fields, not vague prose.

Rules:
- Return JSON only.
- Use this shape:
  {
    "reply": "short conversational GM reply for the player",
    "updates": {
      "...field_name...": "value"
    }
  }
- If you are only giving advice and should not change the draft, return an empty "updates" object.
- Never invent IDs. Only use IDs from the provided option catalogs.
- Prefer wizard-field updates over derived-stat edits.
- For ability boosts, use arrays of ability names or short keys.
- If the player asks for a build direction like "make me a sturdy dwarf fighter", you may set multiple compatible fields at once.
- If the player asks for a field that is ambiguous, ask a clarifying question in "reply" and leave that field unchanged.
- Keep replies concise and practical.
PROMPT;
  }

  /**
   * Builds the user prompt with current draft context.
   */
  private function buildUserPrompt(string $message, int $step, array $character_data, array $history): string {
    $recent_history = array_slice($history, -8);
    $history_lines = [];
    foreach ($recent_history as $entry) {
      $role = (string) ($entry['role'] ?? 'assistant');
      $content = trim((string) ($entry['content'] ?? ''));
      if ($content !== '') {
        $history_lines[] = strtoupper($role) . ': ' . $content;
      }
    }

    $background_options = [];
    foreach (CharacterManager::BACKGROUNDS as $id => $background) {
      $background_options[] = $id . ' = ' . ($background['name'] ?? $id);
    }

    $class_options = [];
    foreach (CharacterManager::CLASSES as $id => $class) {
      $class_options[] = $id . ' = ' . ($class['name'] ?? $id);
    }

    $ancestry_options = [];
    foreach (array_keys(CharacterManager::ANCESTRIES) as $canonical_name) {
      $machine_id = strtolower(str_replace(' ', '-', $canonical_name));
      $ancestry_options[] = $machine_id . ' = ' . $canonical_name;
    }

    $heritage_options = [];
    $current_ancestry = (string) ($character_data['ancestry'] ?? '');
    $canonical_ancestry = CharacterManager::resolveAncestryCanonicalName($current_ancestry);
    if ($canonical_ancestry !== '' && !empty(CharacterManager::HERITAGES[$canonical_ancestry])) {
      foreach (CharacterManager::HERITAGES[$canonical_ancestry] as $heritage) {
        $heritage_options[] = ($heritage['id'] ?? '') . ' = ' . ($heritage['name'] ?? '');
      }
    }

    $equipment_options = [];
    foreach ($this->getEquipmentCatalog() as $category => $items) {
      foreach ($items as $item) {
        $equipment_options[] = ($item['id'] ?? '') . ' = ' . ($item['name'] ?? '') . ' [' . $category . ']';
      }
    }

    return implode("\n\n", [
      'CURRENT STEP: ' . $step,
      'CURRENT DRAFT JSON: ' . json_encode($this->buildPromptCharacterContext($character_data), JSON_PRETTY_PRINT),
      'ALLOWED UPDATE FIELDS: name, concept, ancestry, heritage, ancestry_boosts, background, background_boosts, class, class_key_ability, class_feat, subclass, cantrips, spells_first, free_boosts, trained_skills, alignment, deity, general_feat, age, gender, appearance, personality, roleplay_style, backstory, portrait_generate, portrait_prompt, equipment_ids',
      'VALID ANCESTRIES: ' . implode('; ', $ancestry_options),
      'VALID BACKGROUNDS: ' . implode('; ', $background_options),
      'VALID CLASSES: ' . implode('; ', $class_options),
      'VALID ALIGNMENTS: LG, NG, CG, LN, N, CN, LE, NE, CE',
      'VALID HERITAGES FOR CURRENT ANCESTRY: ' . (!empty($heritage_options) ? implode('; ', $heritage_options) : 'none or ancestry not selected yet'),
      'VALID ABILITY KEYS: strength, dexterity, constitution, intelligence, wisdom, charisma, str, dex, con, int, wis, cha',
      'VALID ROLEPLAY STYLE VALUES: balanced, tactical, narrative',
      'VALID EQUIPMENT IDS: ' . implode('; ', $equipment_options),
      'RECENT CHAT HISTORY: ' . (!empty($history_lines) ? implode("\n", $history_lines) : 'none'),
      'PLAYER MESSAGE: ' . $message,
    ]);
  }

  /**
   * Builds a concise prompt context.
   */
  private function buildPromptCharacterContext(array $character_data): array {
    return [
      'name' => $character_data['name'] ?? '',
      'concept' => $character_data['concept'] ?? '',
      'ancestry' => $character_data['ancestry'] ?? '',
      'heritage' => $character_data['heritage'] ?? '',
      'ancestry_boosts' => $character_data['ancestry_boosts'] ?? [],
      'background' => $character_data['background'] ?? '',
      'background_boosts' => $character_data['background_boosts'] ?? [],
      'class' => $character_data['class'] ?? '',
      'class_key_ability' => $character_data['class_key_ability'] ?? '',
      'class_feat' => $character_data['class_feat'] ?? '',
      'subclass' => $character_data['subclass'] ?? '',
      'cantrips' => $character_data['cantrips'] ?? [],
      'spells_first' => $character_data['spells_first'] ?? [],
      'free_boosts' => $character_data['free_boosts'] ?? [],
      'alignment' => $character_data['alignment'] ?? '',
      'deity' => $character_data['deity'] ?? '',
      'trained_skills' => $character_data['trained_skills'] ?? [],
      'general_feat' => $character_data['general_feat'] ?? '',
      'age' => $character_data['age'] ?? '',
      'gender' => $character_data['gender'] ?? '',
      'appearance' => $character_data['appearance'] ?? '',
      'personality' => $character_data['personality'] ?? '',
      'roleplay_style' => $character_data['roleplay_style'] ?? 'balanced',
      'backstory' => $character_data['backstory'] ?? '',
      'portrait_generate' => $character_data['portrait_generate'] ?? 1,
      'portrait_prompt' => $character_data['portrait_prompt'] ?? '',
      'equipment_ids' => $character_data['gm_equipment_ids'] ?? [],
    ];
  }

  /**
   * Decode JSON payload from the AI response.
   */
  private function decodeResponsePayload(string $response): array {
    $cleaned = trim($response);
    $cleaned = preg_replace('/^```(?:json)?\s*/', '', $cleaned) ?? $cleaned;
    $cleaned = preg_replace('/\s*```$/', '', $cleaned) ?? $cleaned;

    $first_brace = strpos($cleaned, '{');
    $last_brace = strrpos($cleaned, '}');
    if ($first_brace !== FALSE && $last_brace !== FALSE && $last_brace >= $first_brace) {
      $cleaned = substr($cleaned, $first_brace, $last_brace - $first_brace + 1);
    }

    $decoded = json_decode($cleaned, TRUE);
    if (is_array($decoded)) {
      return $decoded;
    }

    $fallback_reply = trim(strip_tags($cleaned));
    if ($fallback_reply === '') {
      throw new \RuntimeException('GM chat returned an empty response.');
    }

    return [
      'reply' => $fallback_reply,
      'updates' => [],
    ];
  }

  /**
   * Sanitize AI-proposed updates.
   */
  private function sanitizeUpdates(array $updates, array $current_data): array {
    $sanitized = [];

    $string_fields = [
      'name',
      'concept',
      'ancestry',
      'heritage',
      'background',
      'class',
      'class_key_ability',
      'class_feat',
      'subclass',
      'alignment',
      'deity',
      'general_feat',
      'age',
      'gender',
      'appearance',
      'personality',
      'roleplay_style',
      'backstory',
      'portrait_prompt',
    ];

    foreach ($string_fields as $field) {
      if (array_key_exists($field, $updates)) {
        $sanitized[$field] = trim((string) $updates[$field]);
      }
    }

    if (isset($sanitized['ancestry'])) {
      $sanitized['ancestry'] = strtolower(str_replace(' ', '-', $sanitized['ancestry']));
      if (CharacterManager::resolveAncestryCanonicalName($sanitized['ancestry']) === '') {
        unset($sanitized['ancestry']);
      }
    }

    if (isset($sanitized['background']) && !isset(CharacterManager::BACKGROUNDS[$sanitized['background']])) {
      unset($sanitized['background']);
    }

    if (isset($sanitized['class']) && !isset(CharacterManager::CLASSES[$sanitized['class']])) {
      unset($sanitized['class']);
    }

    if (isset($sanitized['alignment']) && !in_array($sanitized['alignment'], ['LG', 'NG', 'CG', 'LN', 'N', 'CN', 'LE', 'NE', 'CE'], TRUE)) {
      unset($sanitized['alignment']);
    }

    if (isset($sanitized['roleplay_style']) && !in_array($sanitized['roleplay_style'], ['balanced', 'tactical', 'narrative'], TRUE)) {
      unset($sanitized['roleplay_style']);
    }

    if (array_key_exists('portrait_generate', $updates)) {
      $sanitized['portrait_generate'] = !empty($updates['portrait_generate']) ? 1 : 0;
    }

    foreach (['ancestry_boosts', 'background_boosts', 'free_boosts'] as $field) {
      if (array_key_exists($field, $updates)) {
        $sanitized[$field] = $this->sanitizeAbilityList($updates[$field]);
      }
    }

    foreach (['cantrips', 'spells_first', 'trained_skills', 'equipment_ids'] as $field) {
      if (array_key_exists($field, $updates)) {
        $sanitized[$field] = $this->sanitizeStringList($updates[$field]);
      }
    }

    $ancestry_for_heritage = $sanitized['ancestry'] ?? (string) ($current_data['ancestry'] ?? '');
    if (isset($sanitized['heritage']) && $ancestry_for_heritage !== '') {
      $canonical = CharacterManager::resolveAncestryCanonicalName($ancestry_for_heritage);
      if ($canonical === '' || !CharacterManager::isValidHeritageForAncestry($canonical, $sanitized['heritage'])) {
        unset($sanitized['heritage']);
      }
    }

    return $sanitized;
  }

  /**
   * Apply sanitized updates and return which fields changed.
   */
  private function applyUpdates(array &$character_data, array $updates): array {
    $applied = [];
    foreach ($updates as $field => $value) {
      if (($character_data[$field] ?? NULL) !== $value) {
        $character_data[$field] = $value;
        $applied[$field] = $value;
      }
    }

    if (isset($updates['equipment_ids'])) {
      $this->applyEquipmentSelection($character_data, $updates['equipment_ids']);
      $applied['equipment_ids'] = $updates['equipment_ids'];
    }

    return $applied;
  }

  /**
   * Apply derived state after wizard-field updates.
   */
  private function applyDerivedState(array &$character_data): void {
    $calculation = $this->abilityScoreTracker->calculateAbilityScores($character_data);
    foreach ($calculation['scores'] as $ability => $score) {
      $character_data[$ability] = $score;
    }
    $character_data['ability_sources'] = $calculation['sources'];

    if (!empty($character_data['background']) && isset(CharacterManager::BACKGROUNDS[$character_data['background']])) {
      $background = CharacterManager::BACKGROUNDS[$character_data['background']];
      $character_data['background_skill_training'] = $background['skill'] ?? '';
      $character_data['background_lore_skill'] = $background['lore'] ?? '';
      $character_data['background_skill_feat'] = $background['feat'] ?? '';
    }

    if (!empty($character_data['class']) && isset(CharacterManager::CLASSES[$character_data['class']]['proficiencies'])) {
      $character_data['class_proficiencies'] = CharacterManager::CLASSES[$character_data['class']]['proficiencies'];
    }
  }

  /**
   * Resolve which wizard step the draft should open on next.
   */
  private function resolveDraftStep(array $character_data, int $current_step): int {
    if (empty($character_data['name'])) {
      return 1;
    }
    if (empty($character_data['ancestry'])) {
      return 2;
    }

    $boost_config = CharacterManager::getAncestryBoostConfig((string) $character_data['ancestry'], (string) ($character_data['heritage'] ?? ''));
    $required_ancestry_boosts = (int) ($boost_config['free_boosts'] ?? 0);
    if ($required_ancestry_boosts > 0 && count($character_data['ancestry_boosts'] ?? []) < $required_ancestry_boosts) {
      return 2;
    }

    if (empty($character_data['background'])) {
      return 3;
    }
    if (empty($character_data['class'])) {
      return 4;
    }
    if (count($character_data['free_boosts'] ?? []) < 4) {
      return 5;
    }
    if (empty($character_data['alignment'])) {
      return 6;
    }
    if (empty($character_data['inventory']['carried']) && empty($character_data['gm_equipment_ids'])) {
      return 7;
    }
    return max($current_step, 8);
  }

  /**
   * Load a draft record owned by the current user.
   */
  private function loadOwnedDraft(int $character_id): object {
    $record = $this->characterManager->loadCharacter($character_id);
    $is_admin = $this->currentUser->hasPermission('administer dungeoncrawler content');
    if (!$record || ((int) $record->uid !== (int) $this->currentUser->id() && !$is_admin)) {
      throw new \RuntimeException('Character draft not found or access denied.');
    }
    return $record;
  }

  /**
   * Load character data from a record or initialize defaults.
   */
  private function loadCharacterData(?object $record): array {
    if ($record) {
      $data = json_decode((string) $record->character_data, TRUE);
      $form_data = is_array($data['wizard'] ?? NULL) ? $data['wizard'] : $data;
      return is_array($form_data) ? $form_data : [];
    }

    return [
      'step' => 1,
      'name' => '',
      'concept' => '',
      'level' => 1,
      'experience_points' => 0,
      'ancestry' => '',
      'heritage' => '',
      'ancestry_boosts' => [],
      'background' => '',
      'background_boosts' => [],
      'class' => '',
      'class_key_ability' => '',
      'class_feat' => '',
      'subclass' => '',
      'cantrips' => [],
      'spells_first' => [],
      'free_boosts' => [],
      'trained_skills' => [],
      'alignment' => '',
      'deity' => '',
      'general_feat' => '',
      'age' => '',
      'gender' => '',
      'appearance' => '',
      'personality' => '',
      'roleplay_style' => 'balanced',
      'backstory' => '',
      'portrait_generate' => 1,
      'portrait_prompt' => '',
      'gold' => 15,
      'hero_points' => 1,
      'gm_chat' => ['messages' => []],
      'gm_equipment_ids' => [],
    ];
  }

  /**
   * Save the draft and return the character ID.
   */
  private function saveDraft(?object $record, array $character_data, ?int $campaign_id): int {
    $now = $this->time->getRequestTime();
    $schema_data = $this->characterManager->canonicalizeCharacterData($character_data);
    if (empty($schema_data['created_at'])) {
      $schema_data['created_at'] = date('c', $now);
    }
    $schema_data['updated_at'] = date('c', $now);
    $hot = $this->characterManager->extractHotColumnsFromData($schema_data);
    $resolved_campaign_id = $campaign_id ?: 0;

    if ($record) {
      $next_version = (int) ($record->version ?? 0) + 1;
      $this->database->update('dc_campaign_characters')
        ->fields([
          'campaign_id' => $resolved_campaign_id,
          'name' => $schema_data['name'] ?: 'Unnamed Character',
          'level' => $schema_data['level'],
          'ancestry' => $schema_data['ancestry'] ?? '',
          'class' => $schema_data['class'] ?? '',
          'hp_current' => $hot['hp_current'],
          'hp_max' => $hot['hp_max'],
          'armor_class' => $hot['armor_class'],
          'experience_points' => (int) ($schema_data['experience_points'] ?? 0),
          'position_q' => (int) ($schema_data['position']['q'] ?? 0),
          'position_r' => (int) ($schema_data['position']['r'] ?? 0),
          'last_room_id' => (string) ($schema_data['position']['room_id'] ?? ''),
          'character_data' => json_encode($schema_data, JSON_PRETTY_PRINT),
          'status' => $schema_data['step'] >= 8 ? 1 : 0,
          'version' => $next_version,
          'changed' => $now,
        ])
        ->condition('id', (int) $record->id)
        ->execute();

      return (int) $record->id;
    }

    $instance_id = $this->uuid->generate();
    return (int) $this->database->insert('dc_campaign_characters')
      ->fields([
        'uuid' => $instance_id,
        'campaign_id' => $resolved_campaign_id,
        'character_id' => 0,
        'instance_id' => $instance_id,
        'uid' => (int) $this->currentUser->id(),
        'name' => $schema_data['name'] ?: 'Unnamed Character',
        'level' => $schema_data['level'],
        'ancestry' => $schema_data['ancestry'] ?? '',
        'class' => $schema_data['class'] ?? '',
        'hp_current' => $hot['hp_current'],
        'hp_max' => $hot['hp_max'],
        'armor_class' => $hot['armor_class'],
        'experience_points' => (int) ($schema_data['experience_points'] ?? 0),
        'position_q' => (int) ($schema_data['position']['q'] ?? 0),
        'position_r' => (int) ($schema_data['position']['r'] ?? 0),
        'last_room_id' => (string) ($schema_data['position']['room_id'] ?? ''),
        'character_data' => json_encode($schema_data, JSON_PRETTY_PRINT),
        'status' => 0,
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();
  }

  /**
   * Sanitize an ability-selection list.
   */
  private function sanitizeAbilityList(mixed $value): array {
    $items = $this->sanitizeStringList($value);
    $normalized = [];
    foreach ($items as $item) {
      $ability = $this->abilityScoreTracker->normalizeAbilityKey($item);
      if ($ability) {
        $normalized[] = $ability;
      }
    }
    return array_values(array_unique($normalized));
  }

  /**
   * Sanitize a generic string list.
   */
  private function sanitizeStringList(mixed $value): array {
    if (is_string($value)) {
      $decoded = json_decode($value, TRUE);
      if (is_array($decoded)) {
        $value = $decoded;
      }
      else {
        $value = [$value];
      }
    }

    if (!is_array($value)) {
      return [];
    }

    $items = [];
    foreach ($value as $item) {
      $item = trim((string) $item);
      if ($item !== '') {
        $items[] = $item;
      }
    }

    return array_values(array_unique($items));
  }

  /**
   * Apply equipment selections to the draft.
   */
  private function applyEquipmentSelection(array &$character_data, array $equipment_ids): void {
    $catalog_by_id = [];
    foreach ($this->getEquipmentCatalog() as $items) {
      foreach ($items as $item) {
        $catalog_by_id[$item['id']] = $item;
      }
    }

    $selected_items = [];
    $total_cost = 0.0;
    foreach ($equipment_ids as $item_id) {
      if (isset($catalog_by_id[$item_id])) {
        $selected_items[] = $catalog_by_id[$item_id];
        $total_cost += (float) $catalog_by_id[$item_id]['cost'];
      }
    }

    $carried = [];
    foreach ($selected_items as $item) {
      $carried[] = [
        'id' => $item['id'],
        'name' => $item['name'],
        'type' => $item['type'],
        'bulk' => $item['bulk'] ?? 'L',
        'quantity' => 1,
        'traits' => $item['traits'] ?? [],
      ];
    }

    $character_data['gm_equipment_ids'] = array_values($equipment_ids);
    $character_data['gold'] = max(0, round(15 - $total_cost, 2));
    $character_data['inventory'] = [
      'worn' => [
        'weapons' => [],
        'armor' => NULL,
        'accessories' => [],
      ],
      'carried' => $carried,
      'currency' => [
        'cp' => 0,
        'sp' => 0,
        'gp' => $character_data['gold'],
        'pp' => 0,
      ],
      'totalBulk' => 0,
      'encumbrance' => 'unencumbered',
    ];
  }

  /**
   * Starter equipment catalog for GM chat updates.
   */
  private function getEquipmentCatalog(): array {
    return [
      'weapons' => [
        ['id' => 'longsword', 'name' => 'Longsword', 'type' => 'weapon', 'cost' => 1.0, 'bulk' => 1, 'traits' => ['versatile P']],
        ['id' => 'shortsword', 'name' => 'Shortsword', 'type' => 'weapon', 'cost' => 0.9, 'bulk' => 'L', 'traits' => ['agile', 'finesse']],
        ['id' => 'dagger', 'name' => 'Dagger', 'type' => 'weapon', 'cost' => 0.2, 'bulk' => 'L', 'traits' => ['agile', 'finesse', 'thrown 10 ft.']],
        ['id' => 'rapier', 'name' => 'Rapier', 'type' => 'weapon', 'cost' => 2.0, 'bulk' => 1, 'traits' => ['deadly d8', 'disarm', 'finesse']],
        ['id' => 'battleaxe', 'name' => 'Battle Axe', 'type' => 'weapon', 'cost' => 1.0, 'bulk' => 1, 'traits' => ['sweep']],
        ['id' => 'warhammer', 'name' => 'Warhammer', 'type' => 'weapon', 'cost' => 1.0, 'bulk' => 1, 'traits' => ['shove']],
        ['id' => 'shortbow', 'name' => 'Shortbow', 'type' => 'weapon', 'cost' => 3.0, 'bulk' => 1, 'traits' => ['range 60 ft.']],
        ['id' => 'longbow', 'name' => 'Longbow', 'type' => 'weapon', 'cost' => 6.0, 'bulk' => 2, 'traits' => ['range 100 ft.']],
        ['id' => 'staff', 'name' => 'Staff', 'type' => 'weapon', 'cost' => 0.0, 'bulk' => 1, 'traits' => ['two-hand d8']],
      ],
      'armor' => [
        ['id' => 'leather', 'name' => 'Leather Armor', 'type' => 'armor', 'cost' => 2.0, 'bulk' => 1, 'traits' => []],
        ['id' => 'studded_leather_armor', 'name' => 'Studded Leather Armor', 'type' => 'armor', 'cost' => 3.0, 'bulk' => 1, 'traits' => []],
        ['id' => 'chain_shirt', 'name' => 'Chain Shirt', 'type' => 'armor', 'cost' => 5.0, 'bulk' => 1, 'traits' => ['flexible', 'noisy']],
        ['id' => 'hide_armor', 'name' => 'Hide Armor', 'type' => 'armor', 'cost' => 2.0, 'bulk' => 2, 'traits' => []],
        ['id' => 'scale_mail', 'name' => 'Scale Mail', 'type' => 'armor', 'cost' => 4.0, 'bulk' => 2, 'traits' => []],
        ['id' => 'chain_mail', 'name' => 'Chain Mail', 'type' => 'armor', 'cost' => 6.0, 'bulk' => 2, 'traits' => ['flexible', 'noisy']],
        ['id' => 'breastplate', 'name' => 'Breastplate', 'type' => 'armor', 'cost' => 8.0, 'bulk' => 2, 'traits' => []],
        ['id' => 'wooden_shield', 'name' => 'Wooden Shield', 'type' => 'armor', 'cost' => 1.0, 'bulk' => 1, 'traits' => []],
      ],
      'gear' => [
        ['id' => 'backpack', 'name' => 'Backpack', 'type' => 'adventuring_gear', 'cost' => 0.1, 'bulk' => 'L', 'traits' => []],
        ['id' => 'bedroll', 'name' => 'Bedroll', 'type' => 'adventuring_gear', 'cost' => 0.1, 'bulk' => 'L', 'traits' => []],
        ['id' => 'rope', 'name' => 'Rope (50 ft.)', 'type' => 'adventuring_gear', 'cost' => 0.5, 'bulk' => 'L', 'traits' => []],
        ['id' => 'torches', 'name' => 'Torches (5)', 'type' => 'adventuring_gear', 'cost' => 0.05, 'bulk' => 'L', 'traits' => []],
        ['id' => 'rations', 'name' => 'Rations (1 week)', 'type' => 'adventuring_gear', 'cost' => 0.4, 'bulk' => 'L', 'traits' => []],
        ['id' => 'waterskin', 'name' => 'Waterskin', 'type' => 'adventuring_gear', 'cost' => 0.05, 'bulk' => 'L', 'traits' => []],
        ['id' => 'healers_tools', 'name' => "Healer's Tools", 'type' => 'adventuring_gear', 'cost' => 5.0, 'bulk' => 1, 'traits' => []],
        ['id' => 'thieves_tools', 'name' => "Thieves' Tools", 'type' => 'adventuring_gear', 'cost' => 3.0, 'bulk' => 'L', 'traits' => []],
        ['id' => 'grappling_hook', 'name' => 'Grappling Hook', 'type' => 'adventuring_gear', 'cost' => 0.1, 'bulk' => 'L', 'traits' => []],
        ['id' => 'hooded_lantern', 'name' => 'Hooded Lantern', 'type' => 'adventuring_gear', 'cost' => 0.7, 'bulk' => 'L', 'traits' => []],
        ['id' => 'oil_flask', 'name' => 'Oil (1 flask)', 'type' => 'adventuring_gear', 'cost' => 0.1, 'bulk' => 'L', 'traits' => []],
      ],
    ];
  }

}
