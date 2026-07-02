<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Implements the PF2e Crafting downtime activity (CRB Chapter 4 / Chapter 9).
 *
 * Resolution flow:
 *   1. validateCraftingPrerequisites() — formula, tools, skill rank, item level
 *   2. beginCrafting()    — deduct half price; store crafting_in_progress on state
 *   3. resolveCrafting()  — apply check degree; complete/fail item; atomic gold+item grant
 *   4. advanceCraftingDay() — deduct daily rate from remaining cost; auto-complete when ≤ 0
 *
 * Alchemist daily crafting (Advanced Alchemy) and Quick Alchemy have separate entry points:
 *   5. resolveAdvancedAlchemy() — daily-prep batch creation; no gold cost
 *   6. resolveQuickAlchemy()    — in-field single item; spend 1 reagent
 *
 * Security: all gold mutations and item grants are wrapped in DB transactions.
 */
class CraftingService {

  protected Connection $database;
  protected LoggerInterface $logger;
  protected CharacterStateService $characterStateService;
  protected InventoryManagementService $inventoryManagement;

  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    CharacterStateService $character_state_service,
    InventoryManagementService $inventory_management
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->characterStateService = $character_state_service;
    $this->inventoryManagement = $inventory_management;
  }

  // ---------------------------------------------------------------------------
  // 1. Prerequisites validation (AC-001)
  // ---------------------------------------------------------------------------

  /**
   * Validate all prerequisites before a crafting attempt.
   *
   * Checks: formula in formula_book, tools/workshop, crafting rank, item level.
   *
   * @param array $character_state  Full character state from CharacterStateService::getState().
   * @param array $item             Item definition with keys: id, name, level, rarity, price_gp.
   * @param bool  $downtime_mode    TRUE if current phase is 'downtime' (AC-006).
   *
   * @return array  ['valid' => bool, 'failures' => string[]]
   */
  public function validateCraftingPrerequisites(array $character_state, array $item, bool $downtime_mode = TRUE): array {
    $failures = [];

    // AC-006: Must be in downtime context.
    if (!$downtime_mode) {
      $failures[] = 'requires_downtime: Crafting requires downtime mode; cannot craft during encounter or exploration.';
    }

    $item_level  = (int) ($item['level'] ?? 0);
    $item_rarity = strtolower($item['rarity'] ?? 'common');
    $item_id     = $item['id'] ?? '';

    // AC-001: Character level must be ≥ item level.
    $char_level = (int) ($character_state['basicInfo']['level'] ?? 0);
    if (!CharacterManager::canCraftItem($item_level, $char_level)) {
      $failures[] = "item_level: Character level {$char_level} is too low to craft a level-{$item_level} item (requires level {$item_level}).";
    }

    // AC-001: Must have the formula in their formula_book.
    $formula_book = $character_state['formula_book'] ?? $character_state['crafting']['formula_book'] ?? [];
    if (!$this->hasFormula($formula_book, $item_id)) {
      $failures[] = "missing_formula: Character does not have the formula for '{$item['name']}' in their formula book.";
    }

    // AC-001: Must have appropriate tools/workshop (tracked as an inventory item or character flag).
    $has_tools = $this->hasRequiredTools($character_state, $item);
    if (!$has_tools) {
      $failures[] = "missing_tools: Crafting '{$item['name']}' requires alchemist's tools or an appropriate workshop.";
    }

    // AC-001/AC-004: Crafting rank requirement by rarity.
    $required_rank = CharacterManager::craftingMinRank($item_rarity);
    $actual_rank   = $this->getCraftingRank($character_state);
    if (!CharacterManager::meetsRankRequirement($actual_rank, $required_rank)) {
      $failures[] = "insufficient_rank: Crafting '{$item['name']}' (rarity: {$item_rarity}) requires {$required_rank} in Crafting; character is {$actual_rank}.";
    }

    return [
      'valid'    => empty($failures),
      'failures' => $failures,
    ];
  }

  // ---------------------------------------------------------------------------
  // 2. Begin crafting — pay initial half price, store in-progress state (AC-002)
  // ---------------------------------------------------------------------------

  /**
   * Begin a crafting project: validate prerequisites, deduct half the item price.
   *
   * Stores crafting_in_progress on the character state for later resolution.
   * The 4-day minimum commitment starts here.
   *
   * @param string $character_id   Character ID.
   * @param array  $item           Item definition (id, name, level, rarity, price_gp).
   * @param int    $campaign_id    Campaign context.
   * @param bool   $downtime_mode  TRUE if currently in downtime phase.
   *
   * @return array  Result with success/error, remaining_cost_cp, crafting_state snapshot.
   */
  public function beginCrafting(string $character_id, array $item, int $campaign_id, bool $downtime_mode = TRUE): array {
    $state = $this->characterStateService->getState($character_id, $campaign_id);

    $prereq = $this->validateCraftingPrerequisites($state, $item, $downtime_mode);
    if (!$prereq['valid']) {
      return [
        'success'  => FALSE,
        'error'    => 'prerequisites_not_met',
        'failures' => $prereq['failures'],
        'message'  => 'Crafting prerequisites not met: ' . implode('; ', $prereq['failures']),
      ];
    }

    $price_gp      = (float) ($item['price_gp'] ?? 0);
    $full_cost_cp  = (int) round($price_gp * 100);
    $initial_cp    = (int) ceil($full_cost_cp / 2); // Pay half upfront.
    $remaining_cp  = $full_cost_cp - $initial_cp;

    // Deduct initial materials cost and store crafting_in_progress atomically.
    $transaction = $this->database->startTransaction();
    try {
      $result = $this->deductCurrency($character_id, $initial_cp);
      if (!$result['success']) {
        $transaction->rollBack();
        return $result;
      }

      // Store in-progress crafting project on character state.
      $state = $this->characterStateService->getState($character_id, $campaign_id);
      $state['crafting_in_progress'] = [
        'item'          => $item,
        'days_spent'    => 0,
        'remaining_cp'  => $remaining_cp,
        'full_cost_cp'  => $full_cost_cp,
        'initial_cp_paid' => $initial_cp,
        'check_degree'  => NULL, // Set by resolveCrafting().
        'started_at'    => time(),
      ];
      $this->characterStateService->setState($character_id, $state, NULL, $campaign_id);
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      $this->logger->error('beginCrafting failed for character @id: @error', [
        '@id'    => $character_id,
        '@error' => $e->getMessage(),
      ]);
      throw $e;
    }
    unset($transaction);

    return [
      'success'        => TRUE,
      'initial_cp_paid' => $initial_cp,
      'remaining_cp'   => $remaining_cp,
      'full_cost_cp'   => $full_cost_cp,
      'message'        => "Crafting '{$item['name']}' started. Paid {$initial_cp} cp. Remaining: {$remaining_cp} cp over additional days.",
    ];
  }

  // ---------------------------------------------------------------------------
  // 3. Resolve crafting check — apply degree of success (AC-003)
  // ---------------------------------------------------------------------------

  /**
   * Resolve the crafting skill check at the end of the initial 4-day period.
   *
   * Degree outcomes (AC-003):
   *   - critical_success: item complete in half standard time; any remaining cost waived
   *   - success:          item complete; standard time; remaining cost tracked
   *   - failure:          4 days spent; item not created; initial cost lost
   *   - critical_failure: all materials ruined; full cost lost
   *
   * @param string $character_id  Character ID.
   * @param string $degree        'critical_success'|'success'|'failure'|'critical_failure'.
   * @param int    $campaign_id   Campaign context.
   *
   * @return array  Resolution result.
   */
  public function resolveCrafting(string $character_id, string $degree, int $campaign_id): array {
    $state = $this->characterStateService->getState($character_id, $campaign_id);

    $project = $state['crafting_in_progress'] ?? NULL;
    if (!$project) {
      return [
        'success' => FALSE,
        'error'   => 'no_active_project',
        'message' => 'No active crafting project found for this character.',
      ];
    }

    $item         = $project['item'];
    $full_cost_cp = (int) $project['full_cost_cp'];
    $initial_paid = (int) $project['initial_cp_paid'];
    $remaining_cp = (int) $project['remaining_cp'];

    $transaction = $this->database->startTransaction();
    try {
      switch ($degree) {
        case 'critical_success':
          // Item complete; no further cost; grant item.
          unset($state['crafting_in_progress']);
          $this->characterStateService->setState($character_id, $state, NULL, $campaign_id);
          $grant = $this->inventoryManagement->addItemToInventory($character_id, 'character', $item, 'carried', 1, $campaign_id);
          $result = [
            'success'       => TRUE,
            'degree'        => 'critical_success',
            'item_granted'  => TRUE,
            'remaining_cp'  => 0,
            'message'       => "Critical success! '{$item['name']}' crafted in half the time. Item added to inventory.",
            'inventory'     => $grant,
          ];
          break;

        case 'success':
          // Item complete; record degree so advanceCraftingDay can reduce remaining cost.
          $state['crafting_in_progress']['check_degree'] = 'success';
          $state['crafting_in_progress']['days_spent']   = 4;
          $this->characterStateService->setState($character_id, $state, NULL, $campaign_id);
          $result = [
            'success'      => TRUE,
            'degree'       => 'success',
            'item_granted' => FALSE,
            'remaining_cp' => $remaining_cp,
            'message'      => "Success! '{$item['name']}' crafting progressing. Spend additional days to reduce remaining cost of {$remaining_cp} cp, then receive item.",
          ];
          break;

        case 'failure':
          // 4 days spent, no item, initial cost lost (already deducted in beginCrafting).
          unset($state['crafting_in_progress']);
          $this->characterStateService->setState($character_id, $state, NULL, $campaign_id);
          $result = [
            'success'      => FALSE,
            'degree'       => 'failure',
            'item_granted' => FALSE,
            'cost_lost_cp' => $initial_paid,
            'message'      => "Failure. '{$item['name']}' not completed. {$initial_paid} cp in materials lost.",
          ];
          break;

        case 'critical_failure':
          // All materials ruined; deduct remaining half as well.
          $extra_lost = $remaining_cp;
          unset($state['crafting_in_progress']);
          $this->characterStateService->setState($character_id, $state, NULL, $campaign_id);
          if ($extra_lost > 0) {
            $this->deductCurrency($character_id, $extra_lost);
          }
          $result = [
            'success'      => FALSE,
            'degree'       => 'critical_failure',
            'item_granted' => FALSE,
            'cost_lost_cp' => $full_cost_cp,
            'message'      => "Critical failure! Materials ruined. Full cost of {$full_cost_cp} cp lost.",
          ];
          break;

        default:
          $transaction->rollBack();
          return [
            'success' => FALSE,
            'error'   => 'invalid_degree',
            'message' => "Unknown check degree: {$degree}.",
          ];
      }
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      $this->logger->error('resolveCrafting failed for character @id: @error', [
        '@id'    => $character_id,
        '@error' => $e->getMessage(),
      ]);
      throw $e;
    }
    unset($transaction);

    return $result;
  }

  // ---------------------------------------------------------------------------
  // 4. Advance crafting day — reduce remaining cost (AC-002)
  // ---------------------------------------------------------------------------

  /**
   * Advance one crafting day for an in-progress success project.
   *
   * Each additional day beyond the 4-day minimum reduces remaining_cost by the
   * daily income rate for the item level and check degree.
   * When remaining_cost ≤ 0, the item is granted at no additional gold cost.
   *
   * @param string $character_id  Character ID.
   * @param int    $campaign_id   Campaign context.
   *
   * @return array  Result: remaining_cp, item_granted (bool), message.
   */
  public function advanceCraftingDay(string $character_id, int $campaign_id): array {
    $state   = $this->characterStateService->getState($character_id, $campaign_id);
    $project = $state['crafting_in_progress'] ?? NULL;

    if (!$project || ($project['check_degree'] ?? NULL) !== 'success') {
      return [
        'success' => FALSE,
        'error'   => 'no_active_success_project',
        'message' => 'No in-progress crafting project (success degree) found.',
      ];
    }

    $item         = $project['item'];
    $item_level   = (int) ($item['level'] ?? 0);
    $remaining_cp = (int) $project['remaining_cp'];
    $daily_rate   = CharacterManager::craftingDailyRate($item_level, 'success');

    $remaining_cp -= $daily_rate;
    $state['crafting_in_progress']['remaining_cp'] = max(0, $remaining_cp);
    $state['crafting_in_progress']['days_spent']   = ((int) $project['days_spent']) + 1;

    $transaction = $this->database->startTransaction();
    try {
      if ($remaining_cp <= 0) {
        // Item complete — grant without further payment.
        unset($state['crafting_in_progress']);
        $this->characterStateService->setState($character_id, $state, NULL, $campaign_id);
        $grant = $this->inventoryManagement->addItemToInventory($character_id, 'character', $item, 'carried', 1, $campaign_id);
        $result = [
          'success'      => TRUE,
          'item_granted' => TRUE,
          'remaining_cp' => 0,
          'message'      => "'{$item['name']}' completed! Item added to inventory.",
          'inventory'    => $grant,
        ];
      }
      else {
        $this->characterStateService->setState($character_id, $state, NULL, $campaign_id);
        $result = [
          'success'      => TRUE,
          'item_granted' => FALSE,
          'remaining_cp' => $remaining_cp,
          'message'      => "Crafting day advanced. Remaining cost: {$remaining_cp} cp.",
        ];
      }
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    }
    unset($transaction);

    return $result;
  }

  // ---------------------------------------------------------------------------
  // 5. Alchemist — Advanced Alchemy (AC-005)
  // ---------------------------------------------------------------------------

  /**
   * Resolve Alchemist Advanced Alchemy at daily preparations.
   *
   * Creates (2 × proficiency_rank_bonus) alchemical items at no gold cost.
   * Signature items create 3 copies per batch instead of 2.
   * All items must be in the formula book.
   *
   * @param string $character_id      Character ID.
   * @param array  $item_selections   Array of ['item' => array, 'signature' => bool] for each batch.
   * @param int    $character_level   Character's current level.
   * @param string $crafting_rank     Character's Crafting proficiency rank.
   * @param int    $campaign_id       Campaign context.
   * @param array  $formula_book      Formula book item ID list.
   *
   * @return array  Result with items_created list.
   */
  public function resolveAdvancedAlchemy(
    string $character_id,
    array  $item_selections,
    int    $character_level,
    string $crafting_rank,
    int    $campaign_id,
    array  $formula_book
  ): array {
    $rank_values = CharacterManager::PROFICIENCY_RANK_ORDER;
    $rank_value  = $rank_values[strtolower($crafting_rank)] ?? 0;
    // Proficiency bonus = rank bonus (2/4/6/8 per rank) + level.
    $rank_bonus   = $rank_value * 2;
    $total_free   = max(1, 2 * ($rank_bonus + $character_level));

    $items_created = [];
    $batches_used  = 0;

    foreach ($item_selections as $selection) {
      $item      = $selection['item'] ?? [];
      $signature = (bool) ($selection['signature'] ?? FALSE);
      $copies    = $signature ? 3 : 2;

      if (empty($item['id'])) {
        continue;
      }

      // Must be in formula book.
      if (!$this->hasFormula($formula_book, $item['id'])) {
        $items_created[] = [
          'item'    => $item,
          'granted' => FALSE,
          'error'   => "Formula for '{$item['name']}' not in formula book.",
        ];
        continue;
      }

      // Item level must not exceed character level.
      if ((int) ($item['level'] ?? 0) > $character_level) {
        $items_created[] = [
          'item'    => $item,
          'granted' => FALSE,
          'error'   => "Item level exceeds character level.",
        ];
        continue;
      }

      if ($batches_used >= $total_free) {
        $items_created[] = [
          'item'    => $item,
          'granted' => FALSE,
          'error'   => "No infused reagent batches remaining (used {$total_free}).",
        ];
        continue;
      }

      $grant = $this->inventoryManagement->addItemToInventory(
        $character_id, 'character', $item, 'carried', $copies, $campaign_id
      );
      $batches_used++;
      $items_created[] = [
        'item'    => $item,
        'copies'  => $copies,
        'granted' => TRUE,
        'inventory' => $grant,
      ];
    }

    return [
      'success'          => TRUE,
      'batches_total'    => $total_free,
      'batches_used'     => $batches_used,
      'items_created'    => $items_created,
      'message'          => "Advanced Alchemy: {$batches_used} of {$total_free} reagent batches used.",
    ];
  }

  // ---------------------------------------------------------------------------
  // 6. Alchemist — Quick Alchemy (AC-005)
  // ---------------------------------------------------------------------------

  /**
   * Resolve Quick Alchemy (1 action, 1 reagent, 1 alchemical item for immediate use).
   *
   * @param string $character_id    Character ID.
   * @param array  $item            Item definition (must be in formula book).
   * @param int    $campaign_id     Campaign context.
   * @param int    $reagents_available  Current reagent count.
   * @param array  $formula_book    Formula book item ID list.
   * @param int    $character_level Character's current level for level cap.
   *
   * @return array  Result with item_granted bool, reagents_remaining int.
   */
  public function resolveQuickAlchemy(
    string $character_id,
    array  $item,
    int    $campaign_id,
    int    $reagents_available,
    array  $formula_book,
    int    $character_level
  ): array {
    if ($reagents_available < 1) {
      return [
        'success' => FALSE,
        'error'   => 'no_reagents',
        'message' => 'No infused reagents remaining for Quick Alchemy.',
      ];
    }

    if (!$this->hasFormula($formula_book, $item['id'] ?? '')) {
      return [
        'success' => FALSE,
        'error'   => 'missing_formula',
        'message' => "Formula for '{$item['name']}' not in formula book.",
      ];
    }

    if ((int) ($item['level'] ?? 0) > $character_level) {
      return [
        'success' => FALSE,
        'error'   => 'item_level_too_high',
        'message' => "Item level exceeds character level; cannot create via Quick Alchemy.",
      ];
    }

    $transaction = $this->database->startTransaction();
    try {
      $grant = $this->inventoryManagement->addItemToInventory(
        $character_id, 'character', $item, 'carried', 1, $campaign_id
      );
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    }
    unset($transaction);

    return [
      'success'           => TRUE,
      'item_granted'      => TRUE,
      'reagents_remaining' => $reagents_available - 1,
      'item_expiry'       => 'start of alchemist\'s next turn if not used',
      'message'           => "Quick Alchemy: '{$item['name']}' created. 1 reagent spent.",
      'inventory'         => $grant,
    ];
  }

  // ---------------------------------------------------------------------------
  // Formula book helpers (AC-004)
  // ---------------------------------------------------------------------------

  /**
   * Add a formula to a character's formula book.
   *
   * @param string $character_id  Character ID.
   * @param string $item_id       Item ID of the formula to add.
   * @param int    $campaign_id   Campaign context.
   * @param string $source        How acquired: 'purchased', 'found', 'class_grant', 'level_up', 'reverse_engineer'.
   *
   * @return array  Updated formula_book list.
   */
  public function addFormula(string $character_id, string $item_id, int $campaign_id, string $source = 'purchased'): array {
    $state = $this->characterStateService->getState($character_id, $campaign_id);

    $formula_book = $state['formula_book'] ?? $state['crafting']['formula_book'] ?? [];
    if (in_array($item_id, $formula_book, TRUE)) {
      return [
        'success'      => TRUE,
        'already_known' => TRUE,
        'formula_book' => $formula_book,
        'message'      => "Formula '{$item_id}' already in formula book.",
      ];
    }

    $formula_book[] = $item_id;
    $state['formula_book'] = $formula_book;

    $this->characterStateService->setState($character_id, $state, NULL, $campaign_id);

    $this->logger->info('Formula @item added to character @char (source: @src)', [
      '@item' => $item_id,
      '@char' => $character_id,
      '@src'  => $source,
    ]);

    return [
      'success'      => TRUE,
      'already_known' => FALSE,
      'formula_book' => $formula_book,
      'message'      => "Formula '{$item_id}' added to formula book (source: {$source}).",
    ];
  }

  /**
   * Grant free class-level formulas (Alchemist per-level, Inventor, etc.) on level-up.
   *
   * @param string $character_id   Character ID.
   * @param int    $new_level      The level just gained.
   * @param string $class_id       Class key (e.g. 'alchemist', 'inventor').
   * @param int    $campaign_id    Campaign context.
   * @param array  $item_ids       Item IDs of formulas to add.
   *
   * @return array  Result with formulas_added list.
   */
  public function grantLevelUpFormulas(string $character_id, int $new_level, string $class_id, int $campaign_id, array $item_ids): array {
    $added = [];
    foreach ($item_ids as $item_id) {
      $result = $this->addFormula($character_id, $item_id, $campaign_id, 'level_up');
      if (!$result['already_known']) {
        $added[] = $item_id;
      }
    }

    return [
      'success'        => TRUE,
      'class'          => $class_id,
      'level'          => $new_level,
      'formulas_added' => $added,
      'message'        => count($added) . " formula(s) added at level {$new_level} ({$class_id}).",
    ];
  }

  // ---------------------------------------------------------------------------
  // Private helpers
  // ---------------------------------------------------------------------------

  /**
   * Check if $item_id is in the formula book.
   */
  private function hasFormula(array $formula_book, string $item_id): bool {
    return in_array($item_id, $formula_book, TRUE);
  }

  /**
   * Get the character's Crafting proficiency rank (lowercase).
   *
   * @param array $character_state  Full character state.
   *
   * @return string  Rank: 'untrained', 'trained', 'expert', 'master', 'legendary'.
   */
  private function getCraftingRank(array $character_state): string {
    $skills = $character_state['skills'] ?? [];
    $stored = $skills['crafting'] ?? 'untrained';
    if (is_numeric($stored)) {
      $ranks = CharacterManager::PROFICIENCY_RANK_ORDER;
      $flip  = array_flip($ranks);
      return $flip[(int) $stored] ?? 'untrained';
    }
    return strtolower((string) $stored);
  }

  /**
   * Check if a character has required crafting tools for the item type.
   *
   * Items tagged 'alchemical' require alchemist's tools (or an alchemist lab).
   * All other crafted items require a set of artisan's tools or workshop access.
   * Represented in character state as inventory items or crafting flags.
   *
   * @param array $character_state  Full character state.
   * @param array $item             Item definition.
   *
   * @return bool
   */
  private function hasRequiredTools(array $character_state, array $item): bool {
    // Check for a crafting tools flag on the character (set at character creation
    // or by purchasing/finding the item).
    $crafting_flags = $character_state['crafting'] ?? [];
    if (!empty($crafting_flags['has_tools'])) {
      return TRUE;
    }

    // Fall back: scan inventory for alchemist's tools or artisan's tools.
    $inventory    = $character_state['inventory'] ?? [];
    $carried      = array_merge(
      $inventory['worn']['accessories'] ?? [],
      $inventory['carried'] ?? []
    );
    $tool_item_ids = ['alchemists-tools', 'artisans-tools', 'alchemist-lab', 'workshop'];
    foreach ($carried as $inv_item) {
      if (in_array($inv_item['id'] ?? '', $tool_item_ids, TRUE)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Deduct copper pieces from a character's currency.
   *
   * @param string $character_id  Character ID.
   * @param int    $amount_cp     Amount to deduct in copper pieces.
   *
   * @return array  ['success' => bool, ...]
   */
  private function deductCurrency(string $character_id, int $amount_cp): array {
    $record = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['character_data'])
      ->condition('id', $character_id)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      return ['success' => FALSE, 'error' => 'character_not_found', 'message' => "Character not found: {$character_id}"];
    }

    $char_data = json_decode($record['character_data'] ?? '{}', TRUE) ?: [];
    $currency  = $char_data['character']['equipment']['currency']
      ?? $char_data['equipment']['currency']
      ?? $char_data['currency']
      ?? ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0];

    // Normalise gold/silver/copper key aliases.
    if (!isset($currency['gp']) && isset($currency['gold'])) {
      $currency = [
        'pp' => (int) ($currency['pp'] ?? 0),
        'gp' => (int) $currency['gold'],
        'sp' => (int) ($currency['silver'] ?? 0),
        'cp' => (int) ($currency['copper'] ?? 0),
      ];
    }

    $rates = ['cp' => 1, 'sp' => 10, 'gp' => 100, 'pp' => 1000];
    $total_cp = 0;
    foreach ($rates as $denom => $rate) {
      $total_cp += ((int) ($currency[$denom] ?? 0)) * $rate;
    }

    if ($total_cp < $amount_cp) {
      return [
        'success'       => FALSE,
        'error'         => 'insufficient_funds',
        'required_cp'   => $amount_cp,
        'available_cp'  => $total_cp,
        'message'       => "Insufficient funds: need {$amount_cp} cp, have {$total_cp} cp.",
      ];
    }

    $new_total   = $total_cp - $amount_cp;
    $new_currency = ['cp' => 0, 'sp' => 0, 'gp' => 0, 'pp' => 0];
    foreach (['pp', 'gp', 'sp', 'cp'] as $denom) {
      $new_currency[$denom] = intdiv($new_total, $rates[$denom]);
      $new_total %= $rates[$denom];
    }

    if (isset($char_data['character']['equipment']['currency'])) {
      $char_data['character']['equipment']['currency'] = $new_currency;
    }
    elseif (isset($char_data['equipment']['currency'])) {
      $char_data['equipment']['currency'] = $new_currency;
    }
    else {
      $char_data['currency'] = $new_currency;
    }

    $this->database->update('dc_campaign_characters')
      ->fields(['character_data' => json_encode($char_data)])
      ->condition('id', $character_id)
      ->execute();

    return ['success' => TRUE, 'new_currency' => $new_currency, 'deducted_cp' => $amount_cp];
  }

}
