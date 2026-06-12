<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;

/**
 * Combat action and turn management controller.
 *
 * Handles turn flow, action execution, and reactions as defined in:
 * /docs/dungeoncrawler/issues/combat-state-machine.md (Turn States)
 * /docs/dungeoncrawler/issues/combat-action-validation.md
 * /docs/dungeoncrawler/issues/combat-engine-service.md (ActionProcessor)
 *
 * @see /docs/dungeoncrawler/issues/issue-4-combat-encounter-system-design.md
 */
class CombatActionController extends ControllerBase {

  /**
   * Legacy mutation error code.
   */
  protected const LEGACY_MUTATION_DISABLED_CODE = 'legacy_combat_mutation_disabled';

  /**
   * Combat encounter store.
   *
   * @var \Drupal\dungeoncrawler_content\Service\CombatEncounterStore
   */
  protected $store;

  /**
   * Constructor.
   */
  public function __construct(CombatEncounterStore $store) {
    $this->store = $store;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.combat_encounter_store')
    );
  }

  /**
   * Get current turn information.
   *
   * GET /encounters/{encounter_id}/turn
   *
   * @param int $encounter_id
   *   The encounter ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Current turn participant info.
   *
   * @see /docs/dungeoncrawler/issues/combat-api-endpoints.md#get-current-turn
   */
  public function getCurrentTurn($encounter_id) {
    $encounter = $this->store->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return new JsonResponse(['error' => 'Encounter not found'], 404);
    }

    $turn_index = (int) ($encounter['turn_index'] ?? 0);
    $participants = $encounter['participants'] ?? [];
    $current = $participants[$turn_index] ?? NULL;

    if (!$current) {
      return new JsonResponse(['error' => 'No participants'], 400);
    }

    return new JsonResponse([
      'participant_id' => (int) $current['id'],
      'name' => $current['name'] ?? '',
      'actions_remaining' => (int) ($current['actions_remaining'] ?? 0),
      'attacks_this_turn' => (int) ($current['attacks_this_turn'] ?? 0),
      'turn_index' => $turn_index,
      'current_round' => (int) ($encounter['current_round'] ?? 1),
    ]);
  }

  /**
   * Start participant's turn.
   *
   * POST /encounters/{encounter_id}/participants/{participant_id}/turn/start
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Turn state with granted actions.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#startturn
   * @see /docs/dungeoncrawler/issues/combat-state-machine.md (Turn States)
   */
  public function startTurn($encounter_id, $participant_id) {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * End participant's turn.
   *
   * POST /encounters/{encounter_id}/participants/{participant_id}/turn/end
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   End effects and next turn info.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#endturn
   */
  public function endTurn($encounter_id, $participant_id) {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * Delay turn to act later.
   *
   * POST /encounters/{encounter_id}/participants/{participant_id}/delay
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Delay confirmation.
   *
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md (Delay Rules)
   */
  public function delay($encounter_id, $participant_id) {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * Resume from delay at new initiative.
   *
   * POST /encounters/{encounter_id}/participants/{participant_id}/resume-delay
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param int $participant_id
   *   The participant ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   New initiative value.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Updated initiative order.
   */
  public function resumeDelay($encounter_id, $participant_id, Request $request) {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * Execute combat action.
   *
   * POST /encounters/{encounter_id}/actions
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Action data.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Action result.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#executeaction
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md
   */
  public function executeAction($encounter_id, Request $request) {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * Execute Strike action.
   *
   * POST /encounters/{encounter_id}/actions/strike
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Strike data (participant_id, target_id, weapon_id).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Attack and damage results.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#executestrike
   * @see /docs/dungeoncrawler/issues/combat-database-schema.md (combat_actions)
   */
  public function strike($encounter_id, Request $request) {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * Execute Stride (movement) action.
   *
   * POST /encounters/{encounter_id}/actions/stride
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Movement data (participant_id, distance, path).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Movement result.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#executestride
   */
  public function stride($encounter_id, Request $request) {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * Execute Cast Spell action.
   *
   * POST /encounters/{encounter_id}/actions/cast-spell
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Spell data (participant_id, spell_id, spell_level, targets[]).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Spell results.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#executecastspell
   */
  public function castSpell($encounter_id, Request $request) {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * Ready an action with trigger.
   *
   * POST /encounters/{encounter_id}/actions/ready
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Readied action and trigger.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Ready confirmation.
   *
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md (Ready Rules)
   */
  public function ready($encounter_id, Request $request) {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * Execute reaction.
   *
   * POST /encounters/{encounter_id}/reactions
   *
   * @param int $encounter_id
   *   The encounter ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Reaction data.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Reaction result.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md (ReactionHandler)
   */
  public function executeReaction($encounter_id, Request $request) {
    return $this->legacyMutationDisabledResponse('/api/game/{campaign_id}/action');
  }

  /**
   * Return standardized response for disabled legacy mutation endpoints.
   */
  protected function legacyMutationDisabledResponse(string $canonical_path): JsonResponse {
    return new JsonResponse([
      'success' => FALSE,
      'error_code' => self::LEGACY_MUTATION_DISABLED_CODE,
      'error' => sprintf('Legacy combat mutation endpoints are disabled. Use %s as the single canonical turn/round authority.', $canonical_path),
      'canonical_endpoint' => $canonical_path,
    ], 409);
  }

}
