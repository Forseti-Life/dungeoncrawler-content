<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ai_conversation\Service\AIApiService;
use Psr\Log\LoggerInterface;

/**
 * Manages NPC personality matrices, inner monologues, and attitude shifts.
 *
 * Each NPC in a campaign gets a full psychology profile that informs how the AI
 * portrays them in chat, combat, and exploration. Inner monologues are generated
 * when NPCs observe significant events, and can shift their attitudes and
 * motivations over time.
 *
 * The psychology matrix follows PF2e attitude rules:
 * - Attitudes: helpful → friendly → indifferent → unfriendly → hostile
 * - Motivations: survival, greed, duty, revenge, curiosity, fear, loyalty, etc.
 * - Personality axes: boldness, honesty, empathy, discipline, cunning
 */
class NpcPsychologyService {

  /**
   * PF2e attitude ladder (ordered from most positive to most negative).
   */
  const ATTITUDE_LADDER = [
    'helpful',
    'friendly',
    'indifferent',
    'unfriendly',
    'hostile',
  ];

  /**
   * Default personality axes with neutral midpoint (0-10 scale, 5 = neutral).
   */
  const PERSONALITY_AXES = [
    'boldness'   => 5,  // 0=cowardly, 10=reckless
    'honesty'    => 5,  // 0=deceitful, 10=bluntly honest
    'empathy'    => 5,  // 0=callous, 10=deeply compassionate
    'discipline' => 5,  // 0=chaotic/impulsive, 10=rigidly disciplined
    'cunning'    => 5,  // 0=simple/direct, 10=scheming/manipulative
  ];

  /**
   * Maximum inner monologue entries kept per NPC.
   */
  const MAX_MONOLOGUE_ENTRIES = 50;

  /**
   * Maximum character sheet context length (chars) to avoid prompt bloat.
   */
  const MAX_CONTEXT_LENGTH = 2000;

  protected Connection $database;
  protected LoggerInterface $logger;
  protected ?AIApiService $aiApiService;
  protected ?AiSessionManager $sessionManager;

  /**
   * Constructor.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    AIApiService $ai_api_service = NULL,
    AiSessionManager $session_manager = NULL
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_npc_psychology');
    $this->aiApiService = $ai_api_service;
    $this->sessionManager = $session_manager;
  }

  // -------------------------------------------------------------------------
  // CRUD: Psychology profiles.
  // -------------------------------------------------------------------------

  /**
   * Get or create a psychology profile for an NPC in a campaign.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $entity_ref
   *   Entity reference key (e.g. "goblin_guard_1").
   * @param array $seed_data
   *   Optional initial data to seed the profile with. Keys:
   *   - display_name: NPC display name
   *   - creature_type: e.g. "goblin", "hobgoblin"
   *   - level: NPC level
   *   - description: Text description from dungeon data
   *   - stats: Array of {hp, max_hp, ac, perception, ...}
   *   - role: ally/contact/merchant/villain/neutral/guard/beast
   *   - initial_attitude: Starting attitude toward party
   *
   * @return array
   *   Full psychology profile.
   */
  public function getOrCreateProfile(int $campaign_id, string $entity_ref, array $seed_data = []): array {
    $existing = $this->loadProfile($campaign_id, $entity_ref);
    if ($existing) {
      return $existing;
    }

    return $this->createProfile($campaign_id, $entity_ref, $seed_data);
  }

  /**
   * Load an existing psychology profile.
   *
   * @return array|null
   *   Profile data or NULL if not found.
   */
  public function loadProfile(int $campaign_id, string $entity_ref): ?array {
    try {
      $row = $this->database->select('dc_npc_psychology', 'p')
        ->fields('p')
        ->condition('campaign_id', $campaign_id)
        ->condition('entity_ref', $entity_ref)
        ->execute()
        ->fetchAssoc();

      if (!$row) {
        return NULL;
      }

      return $this->hydrateProfile($row);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to load NPC psychology: @err', ['@err' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Create a new psychology profile from seed data.
   */
  protected function createProfile(int $campaign_id, string $entity_ref, array $seed_data): array {
    $display_name = $seed_data['display_name'] ?? $entity_ref;
    $creature_type = $seed_data['creature_type'] ?? $this->inferCreatureType($entity_ref);
    $level = (int) ($seed_data['level'] ?? 1);
    $role = $seed_data['role'] ?? 'neutral';
    $initial_attitude = $seed_data['initial_attitude'] ?? $this->defaultAttitudeForRole($role);

    // Build personality from creature archetype + randomization.
    $personality = $this->generatePersonalityAxes($creature_type, $role);
    $traits = $seed_data['personality_traits'] ?? $this->generateTraitsFromAxes($personality);
    $motivations = $seed_data['motivations'] ?? $this->generateMotivations($creature_type, $role);
    $fears = $seed_data['fears'] ?? $this->generateFears($creature_type, $role);
    $bonds = $seed_data['bonds'] ?? '';

    // Stats snapshot.
    $stats = $seed_data['stats'] ?? [];

    // Character sheet fields.
    $character_sheet = [
      'display_name' => $display_name,
      'creature_type' => $creature_type,
      'ancestry' => $seed_data['ancestry'] ?? '',
      'class' => $seed_data['class'] ?? '',
      'occupation' => $seed_data['occupation'] ?? '',
      'level' => $level,
      'description' => $seed_data['description'] ?? '',
      'backstory' => $seed_data['backstory'] ?? '',
      'role' => $role,
      'stats' => $stats,
      'abilities' => $seed_data['abilities'] ?? [],
      'equipment' => $seed_data['equipment'] ?? [],
      'languages' => $seed_data['languages'] ?? ['Common'],
      'senses' => $seed_data['senses'] ?? [],
    ];

    $now = time();

    try {
      $this->database->insert('dc_npc_psychology')
        ->fields([
          'campaign_id' => $campaign_id,
          'entity_ref' => $entity_ref,
          'display_name' => $display_name,
          'character_sheet' => json_encode($character_sheet),
          'attitude' => $initial_attitude,
          'personality_axes' => json_encode($personality),
          'personality_traits' => $traits,
          'motivations' => $motivations,
          'fears' => $fears,
          'bonds' => $bonds,
          'inner_monologue' => json_encode([]),
          'attitude_history' => json_encode([
            ['attitude' => $initial_attitude, 'reason' => 'Initial disposition', 'timestamp' => date('c')],
          ]),
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to create NPC psychology for @ref: @err', [
        '@ref' => $entity_ref,
        '@err' => $e->getMessage(),
      ]);
    }

    return $this->loadProfile($campaign_id, $entity_ref) ?? [
      'campaign_id' => $campaign_id,
      'entity_ref' => $entity_ref,
      'display_name' => $display_name,
      'character_sheet' => $character_sheet,
      'attitude' => $initial_attitude,
      'personality_axes' => $personality,
      'personality_traits' => $traits,
      'motivations' => $motivations,
      'fears' => $fears,
      'bonds' => $bonds,
      'inner_monologue' => [],
      'attitude_history' => [],
    ];
  }

  /**
   * Update a profile's fields.
   */
  public function updateProfile(int $campaign_id, string $entity_ref, array $updates): bool {
    $allowed = [
      'display_name', 'character_sheet', 'attitude', 'personality_axes',
      'personality_traits', 'motivations', 'fears', 'bonds',
      'inner_monologue', 'attitude_history',
    ];

    $fields = [];
    foreach ($updates as $key => $value) {
      if (in_array($key, $allowed, TRUE)) {
        $fields[$key] = is_array($value) ? json_encode($value) : $value;
      }
    }

    if (empty($fields)) {
      return FALSE;
    }

    $fields['updated'] = time();

    try {
      $affected = $this->database->update('dc_npc_psychology')
        ->fields($fields)
        ->condition('campaign_id', $campaign_id)
        ->condition('entity_ref', $entity_ref)
        ->execute();
      return $affected > 0;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to update NPC psychology: @err', ['@err' => $e->getMessage()]);
      return FALSE;
    }
  }

  // -------------------------------------------------------------------------
  // Inner monologue: NPC reacts to events.
  // -------------------------------------------------------------------------

  /**
   * Record an inner monologue entry — NPC's private reaction to an event.
   *
   * This is the core mechanism for NPCs having evolving attitudes. When a
   * significant event occurs (PC action, another NPC action, combat outcome,
   * diplomacy check, etc.), the NPC generates a private thought that may shift
   * their attitude or motivations.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $entity_ref
   *   NPC entity reference.
   * @param string $event_type
   *   Event classification: 'pc_action', 'combat_outcome', 'diplomacy',
   *   'intimidation', 'deception', 'observation', 'npc_action', 'betrayal',
   *   'gift', 'threat', 'help', 'room_event'.
   * @param string $event_description
   *   Human-readable description of what happened.
   * @param array $context
   *   Additional context:
   *   - actor: Who did it (PC name or NPC ref)
   *   - target: Who it was done to
   *   - skill_check_result: success/failure/critical_success/critical_failure
   *   - severity: minor/moderate/major/extreme
   *
   * @return array|null
   *   The monologue entry with potential attitude shift, or NULL on failure.
   */
  public function recordInnerMonologue(
    int $campaign_id,
    string $entity_ref,
    string $event_type,
    string $event_description,
    array $context = []
  ): ?array {
    $profile = $this->loadProfile($campaign_id, $entity_ref);
    if (!$profile) {
      $this->logger->warning('No psychology profile for @ref — cannot record monologue', [
        '@ref' => $entity_ref,
      ]);
      return NULL;
    }

    // Generate inner thought via AI (or fallback).
    $monologue_result = $this->generateInnerThought($profile, $event_type, $event_description, $context);

    $entry = [
      'timestamp' => date('c'),
      'event_type' => $event_type,
      'event' => $event_description,
      'thought' => $monologue_result['thought'] ?? '',
      'emotion' => $monologue_result['emotion'] ?? 'neutral',
      'attitude_shift' => $monologue_result['attitude_shift'] ?? 0,
      'motivation_update' => $monologue_result['motivation_update'] ?? NULL,
    ];

    // Apply attitude shift.
    $new_attitude = $profile['attitude'];
    if ($entry['attitude_shift'] !== 0) {
      $new_attitude = $this->shiftAttitude($profile['attitude'], $entry['attitude_shift']);
      $entry['attitude_before'] = $profile['attitude'];
      $entry['attitude_after'] = $new_attitude;
    }

    // Append to monologue log (with cap).
    $monologue = $profile['inner_monologue'] ?? [];
    $monologue[] = $entry;
    if (count($monologue) > self::MAX_MONOLOGUE_ENTRIES) {
      $monologue = array_slice($monologue, -self::MAX_MONOLOGUE_ENTRIES);
    }

    // Update attitude history if changed.
    $attitude_history = $profile['attitude_history'] ?? [];
    if ($new_attitude !== $profile['attitude']) {
      $attitude_history[] = [
        'attitude' => $new_attitude,
        'reason' => $event_description,
        'timestamp' => date('c'),
      ];
    }

    // Persist.
    $updates = [
      'inner_monologue' => $monologue,
      'attitude' => $new_attitude,
      'attitude_history' => $attitude_history,
    ];

    // Update motivations if AI suggested a change.
    if (!empty($entry['motivation_update'])) {
      $updates['motivations'] = $entry['motivation_update'];
    }

    $this->updateProfile($campaign_id, $entity_ref, $updates);

    $this->logger->info('NPC @name inner monologue: @emotion / attitude shift @shift (@before→@after)', [
      '@name' => $profile['display_name'],
      '@emotion' => $entry['emotion'],
      '@shift' => $entry['attitude_shift'],
      '@before' => $profile['attitude'],
      '@after' => $new_attitude,
    ]);

    return $entry;
  }

  /**
   * Generate inner thought using AI or fallback templates.
   */
  protected function generateInnerThought(array $profile, string $event_type, string $event_description, array $context): array {
    // Try AI generation first.
    if ($this->aiApiService) {
      try {
        return $this->generateAiInnerThought($profile, $event_type, $event_description, $context);
      }
      catch (\Exception $e) {
        $this->logger->warning('AI inner thought generation failed: @err', ['@err' => $e->getMessage()]);
      }
    }

    // Deterministic fallback.
    return $this->generateFallbackThought($profile, $event_type, $event_description, $context);
  }

  /**
   * AI-powered inner thought generation.
   */
  protected function generateAiInnerThought(array $profile, string $event_type, string $event_description, array $context): array {
    $sheet = $this->buildCharacterSheetContext($profile);
    $recent_thoughts = $this->getRecentThoughts($profile, 3);

    $prompt = "You are the inner mind of {$profile['display_name']}, an NPC.\n\n";
    $prompt .= "=== CHARACTER SHEET ===\n{$sheet}\n\n";
    $prompt .= "=== CURRENT ATTITUDE TOWARD PARTY ===\n{$profile['attitude']}\n\n";

    if ($recent_thoughts) {
      $prompt .= "=== RECENT INNER THOUGHTS ===\n{$recent_thoughts}\n\n";
    }

    $prompt .= "=== EVENT THAT JUST OCCURRED ===\n";
    $prompt .= "Type: {$event_type}\n";
    $prompt .= "Description: {$event_description}\n";
    if (!empty($context['actor'])) {
      $prompt .= "Actor: {$context['actor']}\n";
    }
    if (!empty($context['skill_check_result'])) {
      $prompt .= "Skill check: {$context['skill_check_result']}\n";
    }

    $prompt .= "\nGenerate this NPC's private inner thought reaction. Consider their personality, motivations, and current attitude.\n\n";
    $prompt .= "Respond ONLY with valid JSON:\n";
    $prompt .= "{\n";
    $prompt .= "  \"thought\": \"1-2 sentence inner monologue in first person\",\n";
    $prompt .= "  \"emotion\": \"one word: angry|fearful|grateful|suspicious|amused|determined|conflicted|hopeful|contemptuous|neutral\",\n";
    $prompt .= "  \"attitude_shift\": integer from -2 to +2 (negative=more hostile, positive=more friendly, 0=no change),\n";
    $prompt .= "  \"motivation_update\": \"updated motivation string if this event changes their goals, or null\"\n";
    $prompt .= "}";

    $result = $this->aiApiService->invokeModelDirect(
      $prompt,
      'dungeoncrawler_content',
      'npc_inner_monologue',
      ['campaign_id' => $profile['campaign_id'], 'npc' => $profile['entity_ref']],
      [
        'system_prompt' => "You generate NPC inner monologue as JSON. Stay in character. Be concise. The attitude_shift should reflect how significant the event is — most events are 0 or ±1, only extreme events warrant ±2.",
        'max_tokens' => 250,
        'skip_cache' => TRUE,
      ]
    );

    if (!empty($result['success']) && !empty($result['response'])) {
      $parsed = json_decode(trim($result['response']), TRUE);
      if (is_array($parsed)) {
        return [
          'thought' => $parsed['thought'] ?? '',
          'emotion' => $parsed['emotion'] ?? 'neutral',
          'attitude_shift' => max(-2, min(2, (int) ($parsed['attitude_shift'] ?? 0))),
          'motivation_update' => $parsed['motivation_update'] ?? NULL,
        ];
      }
    }

    // If AI response wasn't valid JSON, fall back.
    return $this->generateFallbackThought($profile, $event_type, $event_description, $context);
  }

  /**
   * Deterministic fallback inner thought based on personality axes.
   */
  protected function generateFallbackThought(array $profile, string $event_type, string $event_description, array $context): array {
    $axes = $profile['personality_axes'] ?? self::PERSONALITY_AXES;
    $attitude = $profile['attitude'] ?? 'indifferent';
    $name = $profile['display_name'];

    $severity = $context['severity'] ?? 'moderate';
    $shift = 0;
    $emotion = 'neutral';
    $thought = '';

    switch ($event_type) {
      case 'diplomacy':
        $check = $context['skill_check_result'] ?? 'success';
        if ($check === 'critical_success') {
          $shift = 2;
          $emotion = 'grateful';
          $thought = "These adventurers... perhaps they can be trusted after all.";
        }
        elseif ($check === 'success') {
          $shift = 1;
          $emotion = 'hopeful';
          $thought = "Hmm, they make a fair point. Maybe I was too quick to judge.";
        }
        elseif ($check === 'failure') {
          $shift = 0;
          $emotion = 'suspicious';
          $thought = "Nice words, but I'm not convinced. I'll keep my guard up.";
        }
        else {
          $shift = -1;
          $emotion = 'contemptuous';
          $thought = "Do they think I'm a fool? That was insulting.";
        }
        break;

      case 'intimidation':
        $boldness = $axes['boldness'] ?? 5;
        if ($boldness >= 7) {
          $shift = -1;
          $emotion = 'angry';
          $thought = "They dare threaten me? I won't forget this.";
        }
        else {
          $shift = 0;
          $emotion = 'fearful';
          $thought = "I... I should be more careful around them.";
        }
        break;

      case 'combat_outcome':
        $empathy = $axes['empathy'] ?? 5;
        if (str_contains($event_description, 'spared') || str_contains($event_description, 'mercy')) {
          $shift = 2;
          $emotion = 'grateful';
          $thought = "They could have killed me but chose mercy. I owe them.";
        }
        elseif (str_contains($event_description, 'ally') && str_contains($event_description, 'killed')) {
          $shift = -2;
          $emotion = 'angry';
          $thought = "They killed one of ours. I won't forgive this easily.";
        }
        else {
          $emotion = 'fearful';
          $thought = "The fighting was brutal. I need to decide which side to be on.";
        }
        break;

      case 'gift':
        $shift = 1;
        $emotion = 'grateful';
        $thought = "A gift? I didn't expect kindness from outsiders.";
        break;

      case 'betrayal':
        $shift = -2;
        $emotion = 'angry';
        $thought = "I trusted them and they betrayed that trust. Never again.";
        break;

      case 'help':
        $shift = 1;
        $emotion = 'grateful';
        $thought = "They helped when they didn't have to. That means something.";
        break;

      case 'threat':
        $shift = -1;
        $emotion = 'fearful';
        $thought = "I feel threatened. I need to protect myself.";
        break;

      default:
        // observation, pc_action, room_event, npc_action
        $cunning = $axes['cunning'] ?? 5;
        if ($cunning >= 7) {
          $emotion = 'suspicious';
          $thought = "Interesting. I should remember this — it may prove useful later.";
        }
        else {
          $emotion = 'neutral';
          $thought = "I noticed what happened. It doesn't change much for me right now.";
        }
        break;
    }

    // Scale shift by severity.
    if ($severity === 'minor') {
      $shift = (int) round($shift * 0.5);
    }
    elseif ($severity === 'extreme') {
      $shift = max(-2, min(2, $shift * 2));
    }

    return [
      'thought' => $thought,
      'emotion' => $emotion,
      'attitude_shift' => $shift,
      'motivation_update' => NULL,
    ];
  }

  // -------------------------------------------------------------------------
  // Character sheet context builder (for AI prompts).
  // -------------------------------------------------------------------------

  /**
   * Build a full character sheet context string for AI prompt injection.
   *
   * This is the primary method called by RoomChatService and EncounterPhaseHandler
   * to give the AI everything it needs to portray the NPC authentically.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $entity_ref
   *   NPC entity reference.
   * @param array $live_entity
   *   Optional live entity_instance data from dungeon_data (for real-time HP etc).
   *
   * @return string
   *   Formatted context string ready for prompt injection.
   */
  public function buildNpcContextForPrompt(int $campaign_id, string $entity_ref, array $live_entity = []): string {
    $profile = $this->loadProfile($campaign_id, $entity_ref);

    if (!$profile) {
      // Minimal context if no profile exists yet.
      $name = $live_entity['state']['metadata']['display_name']
        ?? $live_entity['name']
        ?? $entity_ref;
      return "You are {$name}. No detailed background is available.";
    }

    return $this->buildCharacterSheetContext($profile, $live_entity);
  }

  /**
   * Build the formatted character sheet context from a profile.
   */
  protected function buildCharacterSheetContext(array $profile, array $live_entity = []): string {
    $sheet = $profile['character_sheet'] ?? [];
    $name = $profile['display_name'];

    $parts = [];

    // Identity.
    $parts[] = "=== {$name} — NPC CHARACTER SHEET ===";

    if (!empty($sheet['ancestry'])) {
      $parts[] = "Ancestry/Race: {$sheet['ancestry']}";
    }
    if (!empty($sheet['class'])) {
      $parts[] = "Class: {$sheet['class']}";
    }
    if (!empty($sheet['occupation'])) {
      $parts[] = "Occupation: {$sheet['occupation']}";
    }
    if (!empty($sheet['creature_type'])) {
      $parts[] = "Creature Type: {$sheet['creature_type']}";
    }
    if (!empty($sheet['level'])) {
      $parts[] = "Level: {$sheet['level']}";
    }
    if (!empty($sheet['role'])) {
      $parts[] = "Role: {$sheet['role']}";
    }
    if (!empty($sheet['description'])) {
      $parts[] = "Description: {$sheet['description']}";
    }
    if (!empty($sheet['backstory'])) {
      $parts[] = "Backstory: {$sheet['backstory']}";
    }

    // Stats — prefer live data, fall back to sheet snapshot.
    $stats = [];
    if (!empty($live_entity['state']['hit_points'])) {
      $hp = $live_entity['state']['hit_points'];
      $stats[] = "HP: {$hp['current']}/{$hp['max']}";
    }
    elseif (!empty($sheet['stats']['currentHp'])) {
      $stats[] = "HP: {$sheet['stats']['currentHp']}/{$sheet['stats']['maxHp']}";
    }
    if (!empty($live_entity['state']['metadata']['stats']['ac'] ?? $sheet['stats']['ac'] ?? NULL)) {
      $ac = $live_entity['state']['metadata']['stats']['ac'] ?? $sheet['stats']['ac'];
      $stats[] = "AC: {$ac}";
    }
    if (!empty($sheet['stats']['perception'])) {
      $stats[] = "Perception: +{$sheet['stats']['perception']}";
    }
    // Include saves if available.
    foreach (['fortitude', 'reflex', 'will'] as $save) {
      if (!empty($sheet['stats'][$save])) {
        $stats[] = ucfirst($save) . ": +{$sheet['stats'][$save]}";
      }
    }
    if ($stats) {
      $parts[] = "Stats: " . implode(', ', $stats);
    }

    // Abilities/attacks.
    if (!empty($sheet['abilities'])) {
      $abilities = is_array($sheet['abilities']) ? implode(', ', $sheet['abilities']) : $sheet['abilities'];
      $parts[] = "Abilities: {$abilities}";
    }
    if (!empty($sheet['equipment'])) {
      $equip = is_array($sheet['equipment']) ? implode(', ', $sheet['equipment']) : $sheet['equipment'];
      $parts[] = "Equipment: {$equip}";
    }
    if (!empty($sheet['languages'])) {
      $lang = is_array($sheet['languages']) ? implode(', ', $sheet['languages']) : $sheet['languages'];
      $parts[] = "Languages: {$lang}";
    }
    if (!empty($sheet['senses'])) {
      $senses = is_array($sheet['senses']) ? implode(', ', $sheet['senses']) : $sheet['senses'];
      $parts[] = "Senses: {$senses}";
    }

    // Psychology.
    $parts[] = '';
    $parts[] = '=== PERSONALITY & PSYCHOLOGY ===';
    $parts[] = "Attitude toward party: {$profile['attitude']}";
    if (!empty($profile['personality_traits'])) {
      $parts[] = "Personality traits: {$profile['personality_traits']}";
    }
    if (!empty($profile['motivations'])) {
      $parts[] = "Motivations: {$profile['motivations']}";
    }
    if (!empty($profile['fears'])) {
      $parts[] = "Fears: {$profile['fears']}";
    }
    if (!empty($profile['bonds'])) {
      $parts[] = "Bonds: {$profile['bonds']}";
    }

    // Personality axes as behavioral guidance.
    $axes = $profile['personality_axes'] ?? [];
    if ($axes) {
      $axis_labels = [];
      foreach ($axes as $axis => $val) {
        $label = $this->axisLabel($axis, $val);
        if ($label) {
          $axis_labels[] = $label;
        }
      }
      if ($axis_labels) {
        $parts[] = "Behavioral tendencies: " . implode(', ', $axis_labels);
      }
    }

    // Recent inner monologue (last 3 entries for context).
    $recent_thoughts = $this->getRecentThoughts($profile, 3);
    if ($recent_thoughts) {
      $parts[] = '';
      $parts[] = '=== RECENT PRIVATE THOUGHTS ===';
      $parts[] = $recent_thoughts;
    }

    $context = implode("\n", $parts);

    // Truncate if too long.
    if (strlen($context) > self::MAX_CONTEXT_LENGTH) {
      $context = substr($context, 0, self::MAX_CONTEXT_LENGTH - 20) . "\n[...truncated]";
    }

    return $context;
  }

  /**
   * Get recent inner thoughts formatted as text.
   */
  protected function getRecentThoughts(array $profile, int $count = 3): string {
    $monologue = $profile['inner_monologue'] ?? [];
    if (empty($monologue)) {
      return '';
    }

    $recent = array_slice($monologue, -$count);
    $lines = [];
    foreach ($recent as $entry) {
      $emotion = $entry['emotion'] ?? 'neutral';
      $thought = $entry['thought'] ?? '';
      $event = $entry['event'] ?? '';
      $lines[] = "[{$emotion}] (after: {$event}) \"{$thought}\"";
    }

    return implode("\n", $lines);
  }

  // -------------------------------------------------------------------------
  // Attitude management.
  // -------------------------------------------------------------------------

  /**
   * Shift an attitude along the PF2e attitude ladder.
   *
   * @param string $current
   *   Current attitude.
   * @param int $shift
   *   Steps to shift (-2 to +2). Positive = more friendly, negative = more hostile.
   *
   * @return string
   *   New attitude.
   */
  public function shiftAttitude(string $current, int $shift): string {
    $idx = array_search($current, self::ATTITUDE_LADDER, TRUE);
    if ($idx === FALSE) {
      $idx = 2; // Default to indifferent.
    }

    // Negative shift moves toward hostile (higher index).
    // Positive shift moves toward helpful (lower index).
    $new_idx = $idx - $shift;
    $new_idx = max(0, min(count(self::ATTITUDE_LADDER) - 1, $new_idx));

    return self::ATTITUDE_LADDER[$new_idx];
  }

  /**
   * Get the current attitude of an NPC toward the party.
   */
  public function getAttitude(int $campaign_id, string $entity_ref): string {
    $profile = $this->loadProfile($campaign_id, $entity_ref);
    return $profile['attitude'] ?? 'indifferent';
  }

  // -------------------------------------------------------------------------
  // Bulk operations: process event for all NPCs in a room.
  // -------------------------------------------------------------------------

  /**
   * Broadcast an event to all NPCs in a room, generating inner monologues.
   *
   * Call this when something significant happens (combat, diplomacy check,
   * PC action, etc.) and all present NPCs should react internally.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param array $npc_entity_refs
   *   Array of entity_ref strings for NPCs in the room.
   * @param string $event_type
   *   Event classification.
   * @param string $event_description
   *   What happened.
   * @param array $context
   *   Additional context.
   *
   * @return array
   *   Array of monologue entries keyed by entity_ref.
   */
  public function broadcastEventToNpcs(
    int $campaign_id,
    array $npc_entity_refs,
    string $event_type,
    string $event_description,
    array $context = []
  ): array {
    $results = [];
    foreach ($npc_entity_refs as $ref) {
      $entry = $this->recordInnerMonologue($campaign_id, $ref, $event_type, $event_description, $context);
      if ($entry) {
        $results[$ref] = $entry;
      }
    }
    return $results;
  }

  /**
   * Get all NPC profiles in a campaign.
   */
  public function getCampaignProfiles(int $campaign_id): array {
    try {
      $rows = $this->database->select('dc_npc_psychology', 'p')
        ->fields('p')
        ->condition('campaign_id', $campaign_id)
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC);

      return array_map([$this, 'hydrateProfile'], $rows);
    }
    catch (\Exception $e) {
      return [];
    }
  }

  /**
   * Ensure all NPCs in a room have psychology profiles.
   *
   * Call this at room entry to auto-create profiles for any NPCs that don't
   * have one yet.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param array $room_entities
   *   Entities array from dungeon_data room.
   *
   * @return int
   *   Number of profiles created.
   */
  public function ensureRoomNpcProfiles(int $campaign_id, array $room_entities): int {
    $created = 0;
    foreach ($room_entities as $entity) {
      $type = $entity['entity_type']
        ?? $entity['entity_ref']['content_type']
        ?? '';

      if (!in_array($type, ['npc', 'creature'], TRUE)) {
        continue;
      }

      $ref = $entity['entity_ref']['content_id']
        ?? $entity['entity_ref']
        ?? $entity['entity_instance_id']
        ?? NULL;

      if (!$ref || !is_string($ref)) {
        continue;
      }

      $existing = $this->loadProfile($campaign_id, $ref);
      if ($existing) {
        continue;
      }

      // Seed from entity data.
      $metadata = $entity['state']['metadata'] ?? [];
      $stats = $metadata['stats'] ?? [];
      $hp = $entity['state']['hit_points'] ?? [];

      $seed = [
        'display_name' => $metadata['display_name'] ?? $ref,
        'creature_type' => $entity['entity_ref']['content_id'] ?? $ref,
        'ancestry' => $metadata['ancestry'] ?? $entity['ancestry'] ?? '',
        'class' => $metadata['class'] ?? $entity['class'] ?? '',
        'occupation' => $metadata['occupation'] ?? $entity['occupation'] ?? '',
        'level' => $entity['level'] ?? $stats['level'] ?? 1,
        'description' => $entity['description'] ?? $metadata['description'] ?? '',
        'backstory' => $metadata['backstory'] ?? $entity['backstory'] ?? '',
        'stats' => array_merge($stats, [
          'currentHp' => $hp['current'] ?? $stats['currentHp'] ?? 10,
          'maxHp' => $hp['max'] ?? $stats['maxHp'] ?? 10,
        ]),
        'abilities' => $metadata['abilities'] ?? $entity['abilities'] ?? [],
        'equipment' => $metadata['equipment'] ?? $entity['equipment'] ?? [],
        'languages' => $metadata['languages'] ?? $entity['languages'] ?? ['Common'],
        'senses' => $metadata['senses'] ?? $entity['senses'] ?? [],
        'role' => $metadata['role'] ?? $entity['role'] ?? ($type === 'npc' ? 'neutral' : 'hostile'),
        'initial_attitude' => $entity['attitude'] ?? ($type === 'npc' ? 'indifferent' : 'hostile'),
      ];

      $this->createProfile($campaign_id, $ref, $seed);
      $created++;
    }

    return $created;
  }

  // -------------------------------------------------------------------------
  // Personality generation helpers.
  // -------------------------------------------------------------------------

  /**
   * Generate personality axes based on creature type and role archetype.
   */
  protected function generatePersonalityAxes(string $creature_type, string $role): array {
    $axes = self::PERSONALITY_AXES;

    // Creature type biases.
    $type_lower = strtolower($creature_type);
    if (str_contains($type_lower, 'goblin')) {
      $axes['boldness'] = rand(2, 5);
      $axes['cunning'] = rand(6, 9);
      $axes['discipline'] = rand(1, 4);
    }
    elseif (str_contains($type_lower, 'hobgoblin')) {
      $axes['boldness'] = rand(5, 8);
      $axes['discipline'] = rand(7, 10);
      $axes['cunning'] = rand(4, 7);
    }
    elseif (str_contains($type_lower, 'skeleton') || str_contains($type_lower, 'zombie')) {
      $axes['empathy'] = rand(0, 2);
      $axes['discipline'] = rand(7, 10);
      $axes['cunning'] = rand(0, 3);
    }
    elseif (str_contains($type_lower, 'dragon')) {
      $axes['boldness'] = rand(7, 10);
      $axes['cunning'] = rand(7, 10);
      $axes['empathy'] = rand(1, 5);
    }
    elseif (str_contains($type_lower, 'guard') || str_contains($type_lower, 'soldier')) {
      $axes['discipline'] = rand(6, 9);
      $axes['boldness'] = rand(4, 7);
    }
    else {
      // Random variation for generic creatures.
      foreach ($axes as $key => $val) {
        $axes[$key] = rand(2, 8);
      }
    }

    // Role overrides.
    if ($role === 'merchant') {
      $axes['cunning'] = max($axes['cunning'], rand(5, 8));
      $axes['honesty'] = rand(3, 7);
    }
    elseif ($role === 'villain') {
      $axes['cunning'] = max($axes['cunning'], rand(7, 10));
      $axes['empathy'] = min($axes['empathy'], rand(1, 4));
    }
    elseif ($role === 'ally') {
      $axes['empathy'] = max($axes['empathy'], rand(5, 8));
      $axes['honesty'] = max($axes['honesty'], rand(5, 8));
    }

    return $axes;
  }

  /**
   * Generate trait keywords from personality axes.
   */
  protected function generateTraitsFromAxes(array $axes): string {
    $traits = [];

    $boldness = $axes['boldness'] ?? 5;
    if ($boldness >= 8) $traits[] = 'reckless';
    elseif ($boldness >= 6) $traits[] = 'bold';
    elseif ($boldness <= 2) $traits[] = 'cowardly';
    elseif ($boldness <= 4) $traits[] = 'cautious';

    $honesty = $axes['honesty'] ?? 5;
    if ($honesty >= 8) $traits[] = 'blunt';
    elseif ($honesty >= 6) $traits[] = 'forthright';
    elseif ($honesty <= 2) $traits[] = 'deceptive';
    elseif ($honesty <= 4) $traits[] = 'evasive';

    $empathy = $axes['empathy'] ?? 5;
    if ($empathy >= 8) $traits[] = 'compassionate';
    elseif ($empathy >= 6) $traits[] = 'sympathetic';
    elseif ($empathy <= 2) $traits[] = 'callous';
    elseif ($empathy <= 4) $traits[] = 'detached';

    $discipline = $axes['discipline'] ?? 5;
    if ($discipline >= 8) $traits[] = 'regimented';
    elseif ($discipline >= 6) $traits[] = 'disciplined';
    elseif ($discipline <= 2) $traits[] = 'chaotic';
    elseif ($discipline <= 4) $traits[] = 'impulsive';

    $cunning = $axes['cunning'] ?? 5;
    if ($cunning >= 8) $traits[] = 'scheming';
    elseif ($cunning >= 6) $traits[] = 'clever';
    elseif ($cunning <= 2) $traits[] = 'simple-minded';
    elseif ($cunning <= 4) $traits[] = 'straightforward';

    return implode(', ', $traits);
  }

  /**
   * Generate motivation text from creature type and role.
   */
  protected function generateMotivations(string $creature_type, string $role): string {
    $motivations = [
      'hostile' => ['Protect territory', 'Survive at all costs', 'Serve a master', 'Hunger and predation'],
      'villain' => ['Dominate others', 'Accumulate power', 'Enact revenge', 'Spread fear'],
      'merchant' => ['Turn a profit', 'Acquire rare goods', 'Build reputation', 'Avoid trouble'],
      'ally' => ['Help those in need', 'Fulfill a debt', 'Seek adventure', 'Protect the weak'],
      'guard' => ['Maintain order', 'Follow duty', 'Protect charges', 'Uphold the law'],
      'neutral' => ['Mind own business', 'Survive and prosper', 'Gather information', 'Avoid conflict'],
    ];

    $pool = $motivations[$role] ?? $motivations['neutral'];
    shuffle($pool);
    return implode('; ', array_slice($pool, 0, 2));
  }

  /**
   * Generate fear text from creature type and role.
   */
  protected function generateFears(string $creature_type, string $role): string {
    $fears = [
      'hostile' => ['Being overpowered', 'Losing territory', 'Betrayal by allies'],
      'villain' => ['Losing control', 'Being exposed', 'A greater power'],
      'merchant' => ['Bankruptcy', 'Theft', 'Dangerous customers'],
      'ally' => ['Failing those who depend on them', 'Losing friends', 'Powerlessness'],
      'guard' => ['Dereliction of duty', 'Shame', 'Overwhelming force'],
      'neutral' => ['Getting caught in conflict', 'Starvation', 'The unknown'],
    ];

    $pool = $fears[$role] ?? $fears['neutral'];
    shuffle($pool);
    return array_shift($pool);
  }

  /**
   * Infer creature type from entity reference string.
   */
  protected function inferCreatureType(string $entity_ref): string {
    // Strip trailing numbers and underscores: "goblin_warrior_2" -> "goblin warrior"
    $cleaned = preg_replace('/_?\d+$/', '', $entity_ref);
    return str_replace('_', ' ', $cleaned);
  }

  /**
   * Default attitude for a given NPC role.
   */
  protected function defaultAttitudeForRole(string $role): string {
    return match ($role) {
      'ally' => 'friendly',
      'contact' => 'friendly',
      'merchant' => 'indifferent',
      'villain' => 'hostile',
      'hostile', 'beast' => 'hostile',
      'guard' => 'unfriendly',
      default => 'indifferent',
    };
  }

  /**
   * Convert a personality axis value to a human-readable label.
   */
  protected function axisLabel(string $axis, int $val): ?string {
    if ($val >= 4 && $val <= 6) {
      return NULL; // Neutral, skip.
    }

    $labels = [
      'boldness' => $val > 6 ? 'bold/aggressive' : 'cautious/avoidant',
      'honesty' => $val > 6 ? 'honest/direct' : 'evasive/deceitful',
      'empathy' => $val > 6 ? 'compassionate' : 'callous/indifferent',
      'discipline' => $val > 6 ? 'disciplined/methodical' : 'impulsive/chaotic',
      'cunning' => $val > 6 ? 'cunning/strategic' : 'simple/straightforward',
    ];

    return $labels[$axis] ?? NULL;
  }

  /**
   * Hydrate a database row into a profile array.
   */
  protected function hydrateProfile(array $row): array {
    $row['character_sheet'] = json_decode($row['character_sheet'] ?? '{}', TRUE) ?: [];
    $row['personality_axes'] = json_decode($row['personality_axes'] ?? '{}', TRUE) ?: self::PERSONALITY_AXES;
    $row['inner_monologue'] = json_decode($row['inner_monologue'] ?? '[]', TRUE) ?: [];
    $row['attitude_history'] = json_decode($row['attitude_history'] ?? '[]', TRUE) ?: [];
    return $row;
  }

}
