<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * GMG Running Guide reference content storage and search.
 *
 * Stores PF2e Gamemastery Guide ch01 reference sections as searchable,
 * tag-filtered content entries. Surfaces structured data tables (encounter
 * budgets, XP thresholds) as JSON alongside markdown narrative content.
 *
 * Implements: dc-gmg-running-guide (GmReferenceContent entity, GmReferenceSearchService)
 */
class GmReferenceService {

  /**
   * Known section tags (GMG ch01 DB sections).
   */
  const VALID_SECTIONS = [
    'general_advice',
    'adjudicating_rules',
    'adventure_design',
    'campaign_structure',
    'encounter_design',
    'running_encounters',
    'running_exploration',
    'running_downtime',
    'rarity',
    'narrative_collaboration',
    'resolving_problems',
    'special_circumstances',
    'drawing_maps',
  ];

  /**
   * Known content tags.
   */
  const VALID_TAGS = [
    'encounter_building', 'exploration', 'downtime', 'social',
    'adjudication', 'adventure', 'campaign', 'safety', 'rarity',
    'narrative', 'session_zero', 'gm_advice', 'map', 'encounter_budget',
    'xp_thresholds', 'encounter_design', 'running', 'structured_data',
  ];

  public function __construct(
    private readonly Connection $database
  ) {}

  // ── CRUD ──────────────────────────────────────────────────────────────────

  /**
   * Create or upsert a reference entry by (section + title).
   *
   * @param array $data
   *   Keys: section (required), title (required), content_markdown,
   *         tags (array), source_book (default: 'gmg'), structured_data (array).
   *
   * @return array  Saved entry.
   */
  public function upsert(array $data): array {
    $section = $data['section'] ?? NULL;
    $title   = $data['title'] ?? NULL;
    if (!$section || !$title) {
      throw new \InvalidArgumentException('section and title are required', 400);
    }
    if (!in_array($section, self::VALID_SECTIONS, TRUE)) {
      throw new \InvalidArgumentException("Unknown section: {$section}", 400);
    }

    $tags_json       = json_encode(array_values((array) ($data['tags'] ?? [])));
    $structured_json = json_encode($data['structured_data'] ?? null);
    $source_book     = $data['source_book'] ?? 'gmg';
    $content         = $data['content_markdown'] ?? '';
    $now             = time();

    $existing = $this->database->select('dc_gm_reference', 'r')
      ->fields('r', ['id'])
      ->condition('section', $section)
      ->condition('title', $title)
      ->execute()->fetchField();

    if ($existing) {
      $this->database->update('dc_gm_reference')
        ->fields([
          'content_markdown' => $content,
          'tags_json'        => $tags_json,
          'structured_data_json' => $structured_json,
          'source_book'      => $source_book,
          'updated'          => $now,
        ])
        ->condition('id', $existing)
        ->execute();
      return $this->getById((int) $existing);
    }

    $id = $this->database->insert('dc_gm_reference')
      ->fields([
        'section'              => $section,
        'title'                => $title,
        'content_markdown'     => $content,
        'tags_json'            => $tags_json,
        'structured_data_json' => $structured_json,
        'source_book'          => $source_book,
        'created'              => $now,
        'updated'              => $now,
      ])->execute();

    return $this->getById((int) $id);
  }

  /**
   * Retrieve a single entry by ID.
   */
  public function getById(int $id): ?array {
    $row = $this->database->select('dc_gm_reference', 'r')
      ->fields('r')
      ->condition('id', $id)
      ->execute()->fetchAssoc();
    return $row ? $this->decode($row) : NULL;
  }

  /**
   * Search reference entries.
   *
   * @param array $filters
   *   Optional: section, tag, source_book, q (full-text substring).
   * @param int $limit
   *
   * @return array[]
   */
  public function search(array $filters = [], int $limit = 50): array {
    $query = $this->database->select('dc_gm_reference', 'r')->fields('r');

    if (!empty($filters['section'])) {
      $query->condition('section', $filters['section']);
    }
    if (!empty($filters['source_book'])) {
      $query->condition('source_book', $filters['source_book']);
    }
    if (!empty($filters['tag'])) {
      $query->condition('tags_json', '%' . $filters['tag'] . '%', 'LIKE');
    }
    if (!empty($filters['q'])) {
      $q = '%' . $filters['q'] . '%';
      $or = $query->orConditionGroup()
        ->condition('title', $q, 'LIKE')
        ->condition('content_markdown', $q, 'LIKE');
      $query->condition($or);
    }

    $rows = $query->range(0, $limit)->orderBy('section')->orderBy('title')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return array_map([$this, 'decode'], $rows);
  }

  /**
   * Return all entries for a section.
   */
  public function getBySection(string $section): array {
    return $this->search(['section' => $section], 200);
  }

  // ── Seed import ───────────────────────────────────────────────────────────

  /**
   * Import the canonical GMG ch01 seed catalog.
   *
   * @return int  Number of entries created/updated.
   */
  public function importSeedCatalog(): int {
    $count = 0;
    foreach ($this->getSeedEntries() as $entry) {
      $this->upsert($entry);
      $count++;
    }
    return $count;
  }

  // ── Private helpers ───────────────────────────────────────────────────────

  private function decode(array $row): array {
    $row['tags']           = json_decode($row['tags_json'] ?? '[]', TRUE) ?? [];
    $row['structured_data'] = json_decode($row['structured_data_json'] ?? 'null', TRUE);
    return $row;
  }

  private function getSeedEntries(): array {
    return [
      // General Advice ─────────────────────────────────────────────────────
      [
        'section'          => 'general_advice',
        'title'            => 'Chapter Scope and GM Priorities',
        'tags'             => ['gm_advice'],
        'content_markdown' => "The GM's primary responsibilities are preparation, improvisation, consistency, and player engagement. Preparation means having enough material to run a session; improvisation fills the gaps. Consistency builds player trust and world coherence.",
      ],
      [
        'section'          => 'general_advice',
        'title'            => 'Session Zero',
        'tags'             => ['session_zero', 'gm_advice'],
        'content_markdown' => "Session zero is a pre-play meeting to: establish tone and themes, confirm safety tools, collect character backstory hooks, define party relationships, and set expectations for lethality and TPK handling. Record party links and character integration notes.",
      ],
      [
        'section'          => 'general_advice',
        'title'            => 'Between-Session Tasks',
        'tags'             => ['gm_advice', 'downtime'],
        'content_markdown' => "Between sessions: divide treasure, advance XP/leveling, resolve downtime asynchronously. Use the between-session resolution workflow to handle tasks before the next session starts.",
      ],
      [
        'section'          => 'general_advice',
        'title'            => 'GM Dashboard Modifier Cache',
        'tags'             => ['gm_advice'],
        'content_markdown' => "Maintain a quick-reference cache of key PC modifiers: Perception, Will save, and common Recall Knowledge skills. Refresh on every level-up or stat change so the GM can call for secret checks without looking up PC sheets.",
      ],
      // Adjudicating Rules ──────────────────────────────────────────────────
      [
        'section'          => 'adjudicating_rules',
        'title'            => 'Ruling Records and Precedents',
        'tags'             => ['adjudication'],
        'content_markdown' => "Track precedent linkage: when a ruling is made, link it to prior analogous decisions. Accumulate precedents as candidate house-rule seeds. Mark rulings as provisional with a deferred post-session review flag. Publish clarified rulings before the next session.",
      ],
      [
        'section'          => 'adjudicating_rules',
        'title'            => 'Creative-Action Resolution Templates',
        'tags'             => ['adjudication'],
        'content_markdown' => "When players attempt actions not covered by rules, use one of four templates: (1) Minor bonus — success grants a small advantage. (2) Minor penalty — failure imposes a small setback. (3) Minor damage plus rider — success or fail with a status effect. (4) Object-triggered save — the environment requires a saving throw.",
        'structured_data'  => [
          'templates' => [
            ['name' => 'minor_bonus',           'description' => 'Success grants a small advantage (e.g., +1 circ bonus on next roll)'],
            ['name' => 'minor_penalty',          'description' => 'Failure imposes a small setback (e.g., –1 circ penalty)'],
            ['name' => 'minor_damage_plus_rider','description' => 'Damage plus a status effect (e.g., 1d6 fire + off-guard)'],
            ['name' => 'object_triggered_save',  'description' => 'Environment triggers a saving throw (DC = 15 + proficiency tier)'],
          ],
        ],
      ],
      // Adventure Design ────────────────────────────────────────────────────
      [
        'section'          => 'adventure_design',
        'title'            => 'Per-Player Motivation Hooks',
        'tags'             => ['adventure', 'gm_advice'],
        'content_markdown' => "For each player, define 1-2 personal motivation hooks tied to their character backstory. Track spotlight rotation across sessions to ensure each character gets moments to shine. Capture engagement targets (what excites each player: combat, roleplay, puzzle, mystery).",
      ],
      [
        'section'          => 'adventure_design',
        'title'            => 'Scene-Type Diversity',
        'tags'             => ['adventure', 'encounter_design'],
        'content_markdown' => "Track scene types per session and arc: combat, social/negotiation, problem-solving, and stealth. Warn when consecutive sessions overweight any single type. Aim for a mix that matches player preference touchstones captured in session zero.",
      ],
      [
        'section'          => 'adventure_design',
        'title'            => 'Adventure Generator Six-Step Pipeline',
        'tags'             => ['adventure', 'gm_advice'],
        'content_markdown' => "1. Define GM style and threat type. 2. Establish faction motivations. 3. Outline PC arcs. 4. Design encounter chain. 5. Add mechanical content hooks. 6. Layer emotional beats (triumph, dread, optimism) by scene.",
        'structured_data'  => [
          'steps' => [
            ['step' => 1, 'label' => 'GM style and threat'],
            ['step' => 2, 'label' => 'Faction motivations'],
            ['step' => 3, 'label' => 'PC arcs'],
            ['step' => 4, 'label' => 'Encounter chain'],
            ['step' => 5, 'label' => 'Mechanical content'],
            ['step' => 6, 'label' => 'Emotional beats'],
          ],
        ],
      ],
      // Campaign Structure ──────────────────────────────────────────────────
      [
        'section'          => 'campaign_structure',
        'title'            => 'Campaign Scope Templates',
        'tags'             => ['campaign'],
        'content_markdown' => "Four scope templates: (1) One-shot: 1 session, max level 5. (2) Brief: 3-6 sessions, levels 1-8. (3) Extended: 10-20 sessions, levels 1-15. (4) Epic: 30+ sessions, levels 1-20. Templates are promotable — begin as a brief and expand without data loss.",
        'structured_data'  => [
          'scopes' => [
            ['name' => 'one_shot', 'sessions' => 1,   'level_ceiling' => 5,  'description' => 'Single session'],
            ['name' => 'brief',    'sessions_range' => '3-6',  'level_ceiling' => 8,  'description' => 'Short arc'],
            ['name' => 'extended', 'sessions_range' => '10-20','level_ceiling' => 15, 'description' => 'Medium campaign'],
            ['name' => 'epic',     'sessions_range' => '30+',  'level_ceiling' => 20, 'description' => 'Full campaign'],
          ],
        ],
      ],
      [
        'section'          => 'campaign_structure',
        'title'            => 'Campaign Intake',
        'tags'             => ['campaign', 'session_zero'],
        'content_markdown' => "At campaign start, collect: player-preference touchstones (tone, themes, mechanics preferences), character goals, and level of desired roleplay depth. Track goal coverage across sessions to confirm all players remain engaged.",
      ],
      // Encounter Design ────────────────────────────────────────────────────
      [
        'section'          => 'encounter_design',
        'title'            => 'Encounter Budget and XP Thresholds',
        'tags'             => ['encounter_building', 'encounter_budget', 'xp_thresholds', 'structured_data'],
        'content_markdown' => "PF2e XP budget system. Party of 4 is baseline. Each creature costs XP based on its level relative to the party. Budget thresholds determine encounter difficulty.",
        'structured_data'  => [
          'difficulty_budgets' => [
            ['difficulty' => 'trivial', 'budget' => 40,  'description' => 'No real threat; resource drain only'],
            ['difficulty' => 'low',     'budget' => 60,  'description' => 'Minor threat; some resources used'],
            ['difficulty' => 'moderate','budget' => 80,  'description' => 'Meaningful threat; ~20% resources'],
            ['difficulty' => 'severe',  'budget' => 120, 'description' => 'Dangerous; ~40% resources, PC risk'],
            ['difficulty' => 'extreme', 'budget' => 160, 'description' => 'Lethal set-piece; use sparingly'],
          ],
          'creature_xp_by_level_delta' => [
            ['delta' => -4, 'xp' => 10],
            ['delta' => -3, 'xp' => 15],
            ['delta' => -2, 'xp' => 20],
            ['delta' => -1, 'xp' => 30],
            ['delta' => 0,  'xp' => 40],
            ['delta' => 1,  'xp' => 60],
            ['delta' => 2,  'xp' => 80],
            ['delta' => 3,  'xp' => 120],
            ['delta' => 4,  'xp' => 160],
          ],
        ],
      ],
      [
        'section'          => 'encounter_design',
        'title'            => 'Encounter Narrative Metadata',
        'tags'             => ['encounter_design', 'encounter_building'],
        'content_markdown' => "Every encounter should record: narrative purpose (what story beat it serves), adversary rationale (why these creatures are here), location hooks (what the terrain offers beyond XP math), and any dynamic twists or phase changes. Setup profiles: ambush, negotiation-collapse, duel, chase transition, retreat, surrender.",
        'structured_data'  => [
          'setup_profiles' => ['ambush', 'negotiation_collapse', 'duel', 'chase_transition', 'retreat', 'surrender'],
        ],
      ],
      [
        'section'          => 'encounter_design',
        'title'            => 'Threat Scheduling',
        'tags'             => ['encounter_building', 'encounter_design'],
        'content_markdown' => "Mix difficulty levels across a session: trivial and low are resource drains; moderate is standard; severe tests the party; extreme is a set-piece climax. Never run consecutive extreme encounters. Gate extreme threats as deliberate set pieces.",
      ],
      // Running Encounters ──────────────────────────────────────────────────
      [
        'section'          => 'running_encounters',
        'title'            => 'Turn Manager and Rewind Rules',
        'tags'             => ['running', 'encounter_building'],
        'content_markdown' => "Same-turn rewinds are permitted (player made an error this turn). Cross-turn rewinds are blocked by default to maintain game state integrity — enable only via campaign setting. Lightweight corrections outside the turn structure are always allowed (e.g., applying omitted static damage).",
      ],
      [
        'section'          => 'running_encounters',
        'title'            => 'Stealth Initiative',
        'tags'             => ['running', 'exploration'],
        'content_markdown' => "When using Avoid Notice (Stealth for initiative), compare each sneaking character's Stealth result against each observing enemy's Perception DC. Characters whose result meets or exceeds the DC are Undetected by that observer. Results are per-observer — a character can be Undetected by some enemies but Observed by others.",
      ],
      [
        'section'          => 'running_encounters',
        'title'            => 'Grouped Initiative',
        'tags'             => ['running'],
        'content_markdown' => "Optionally group identical enemies to reduce bookkeeping. Grouped creatures act on the same initiative count but each takes their own full turn. If one creature in a group uses Delay, it is removed from the group and placed individually in initiative order.",
      ],
      [
        'section'          => 'running_encounters',
        'title'            => 'Aid and Ready Validation',
        'tags'             => ['adjudication', 'running'],
        'content_markdown' => "Aid requires: preparation, valid position, and communication feasibility. Aid timing scales with task scope. Ready triggers must be in-world observables — reject purely meta triggers (HP thresholds, unobservable tags). Cover adjudication: physical silhouette plausibility determines cover; prone integration applies when terrain requires.",
      ],
      // Running Exploration ─────────────────────────────────────────────────
      [
        'section'          => 'running_exploration',
        'title'            => 'Exploration Flow',
        'tags'             => ['exploration'],
        'content_markdown' => "Exploration uses sensory-scene prompts (sight, sound, smell, temperature, texture), variable time compression (10-minute increments default, hour-scale for travel), mystery hooks, and GM slow-time controls for key decisions and new-area entry.",
      ],
      [
        'section'          => 'running_exploration',
        'title'            => 'Environment Authoring',
        'tags'             => ['exploration', 'map'],
        'content_markdown' => "Tag environments as familiar or novel to guide description emphasis. Novel areas deserve full multi-sensory descriptions. Familiar areas can use shorthand. Both should highlight any changed elements since last visit.",
      ],
      // Running Downtime ────────────────────────────────────────────────────
      [
        'section'          => 'running_downtime',
        'title'            => 'Downtime Subsystem',
        'tags'             => ['downtime'],
        'content_markdown' => "Downtime ties world-state updates to PC accomplishments. Resolve at low-roll-count (summary format) by default; branch into encounter or exploration when a downtime roll triggers something significant. Campaign downtime depth: light, medium, or deep — adjustable over the campaign lifetime.",
        'structured_data'  => [
          'depth_levels' => [
            ['level' => 'light',  'description' => 'Quick summary; skip individual checks; focus on outcomes'],
            ['level' => 'medium', 'description' => 'Roll for key tasks; brief scenes for results; skip filler'],
            ['level' => 'deep',   'description' => 'Full resolution; individual task checks; narrative scenes for each PC'],
          ],
        ],
      ],
      [
        'section'          => 'running_downtime',
        'title'            => 'Scene Fusion and Opt-Out',
        'tags'             => ['downtime'],
        'content_markdown' => "When multiple PCs perform downtime tasks in the same location or context, fuse their scenes to save table time. Provide a low-detail summary option for players who want to skip downtime roleplay, preserving their mechanical outcomes without requiring engagement.",
      ],
      // Rarity ──────────────────────────────────────────────────────────────
      [
        'section'          => 'rarity',
        'title'            => 'Content Rarity Tiers',
        'tags'             => ['rarity'],
        'content_markdown' => "Rarity is context-sensitive, not globally static. Common: freely available. Uncommon: requires training, faction membership, or GM permission. Rare: exceptional — typically quest reward or narrative discovery. Unique: singular; only one exists in the world.",
        'structured_data'  => [
          'tiers' => [
            ['rarity' => 'common',   'description' => 'Freely available anywhere'],
            ['rarity' => 'uncommon', 'description' => 'Requires special access, training, or GM allowlist'],
            ['rarity' => 'rare',     'description' => 'Exceptional; quest reward or narrative discovery'],
            ['rarity' => 'unique',   'description' => 'Singular; only one exists'],
          ],
        ],
      ],
      // Narrative Collaboration ──────────────────────────────────────────────
      [
        'section'          => 'narrative_collaboration',
        'title'            => 'Collaboration Modes',
        'tags'             => ['narrative'],
        'content_markdown' => "Three collaboration modes: (1) GM-led: GM controls setting, plot, and outcome. Players are protagonists. (2) Shared content: players author setting elements and NPC components; GM tracks ownership. (3) Decentralized narration: players take authorial turns; GM arbitrates tone consistency.",
        'structured_data'  => [
          'modes' => [
            ['mode' => 'gm_led',              'description' => 'GM controls all setting and plot; players are protagonists'],
            ['mode' => 'shared_content',       'description' => 'Players author setting/NPC components; ownership tracked'],
            ['mode' => 'decentralized',        'description' => 'Players take authorial turns; GM arbitrates consistency'],
          ],
        ],
      ],
      [
        'section'          => 'narrative_collaboration',
        'title'            => 'Story Point Economy',
        'tags'             => ['narrative'],
        'content_markdown' => "Story Points are bounded narrative interventions. Players spend points to: introduce a minor twist, establish a scene fact, or adjust an NPC's attitude one step. Cannot auto-resolve whole scenes. GM sets the pool size per session. Typical: 1 point per PC per session. Points reset each session.",
      ],
      // Resolving Problems ──────────────────────────────────────────────────
      [
        'section'          => 'resolving_problems',
        'title'            => 'Safety Tools',
        'tags'             => ['safety'],
        'content_markdown' => "Configure table-level safety tools at campaign start: (1) Lethality level (heroic/gritty/deadly). (2) TPK handling (pause and discuss vs. consequence mode). (3) X-Card: any player can tap/invoke to skip or adjust content, no explanation required. (4) Lines: content never introduced. (5) Veils: content handled off-screen only. Safety interrupts must be accessible in 1 click from session UI.",
      ],
      [
        'section'          => 'resolving_problems',
        'title'            => 'Power-Imbalance Mitigation',
        'tags'             => ['safety', 'gm_advice'],
        'content_markdown' => "Mechanisms: consensual retraining (rebuild an unplayable character), narrative off-ramping (give a poorly-fitting character a graceful exit), and encounter recalibration (reduce difficulty if a character's build creates persistent exclusion). All require explicit player consent.",
      ],
      // Special Circumstances ───────────────────────────────────────────────
      [
        'section'          => 'special_circumstances',
        'title'            => 'Organized Play Mode',
        'tags'             => ['gm_advice'],
        'content_markdown' => "Organized-play campaigns use campaign-level allowlists/denylists maintained externally. GMs cannot override org-play restrictions but can add further restrictions. Setting is toggled at campaign level and must be recorded before play begins.",
      ],
      [
        'section'          => 'special_circumstances',
        'title'            => 'Group-Size Adaptation',
        'tags'             => ['gm_advice', 'encounter_building'],
        'content_markdown' => "For groups outside 3-5 players: (1) Add or remove NPC companions to reach effective party count. (2) Use character flexibility variants (rebuild options for small parties). (3) Adjust XP budget proportionally. GM-controlled support entities must not make major decisions or overshadow PC roles.",
      ],
      // Drawing Maps ────────────────────────────────────────────────────────
      [
        'section'          => 'drawing_maps',
        'title'            => 'Map Authoring',
        'tags'             => ['map', 'encounter_design'],
        'content_markdown' => "Model maps with first-class features: maneuverability zones (difficult terrain, narrow passages), line-of-sight blocking (walls, pillars), range lanes (long-range position control), and cover anchors. Account for inhabitant terrain familiarity and movement modes (burrow, climb, swim, fly) when placing adversaries.",
      ],
    ];
  }

}
