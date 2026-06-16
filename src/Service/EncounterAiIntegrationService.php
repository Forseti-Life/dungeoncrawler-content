<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Orchestrates encounter AI recommendation and validation in read-only mode.
 */
class EncounterAiIntegrationService {

  /**
   * Deterministic action definitions for encounter AI context contracts.
   */
  protected const AI_ACTION_DEFINITIONS = [
    'strike' => [
      'cost' => 1,
      'category' => 'offense',
      'requires_turn' => TRUE,
      'targeting' => 'hostile_entity',
    ],
    'step' => [
      'cost' => 1,
      'category' => 'movement',
      'requires_turn' => TRUE,
      'targeting' => 'hex',
    ],
    'stride' => [
      'cost' => 1,
      'category' => 'movement',
      'requires_turn' => TRUE,
      'targeting' => 'hex',
    ],
    'interact' => [
      'cost' => 1,
      'category' => 'utility',
      'requires_turn' => TRUE,
      'targeting' => 'entity_or_object',
    ],
    'talk' => [
      'cost' => 1,
      'category' => 'conversation',
      'requires_turn' => TRUE,
      'targeting' => 'entity_or_room',
    ],
    'demoralize' => [
      'cost' => 1,
      'category' => 'social',
      'requires_turn' => TRUE,
      'targeting' => 'hostile_entity',
    ],
    'raise_shield' => [
      'cost' => 1,
      'category' => 'defense',
      'requires_turn' => TRUE,
      'targeting' => 'self',
    ],
    'end_turn' => [
      'cost' => 0,
      'category' => 'turn',
      'requires_turn' => TRUE,
      'targeting' => 'none',
    ],
    'reaction' => [
      'cost' => 'reaction',
      'category' => 'reaction',
      'requires_turn' => FALSE,
      'targeting' => 'contextual',
    ],
  ];

  /**
   * Encounter AI provider implementation.
   */
  protected EncounterAiProviderInterface $provider;

  /**
   * Time service.
   */
  protected TimeInterface $time;

  /**
   * Logger factory.
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Constructs service.
   */
  public function __construct(EncounterAiProviderInterface $provider, TimeInterface $time, LoggerChannelFactoryInterface $logger_factory) {
    $this->provider = $provider;
    $this->time = $time;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Build normalized encounter context for provider recommendation calls.
   *
   * @param int $campaign_id
   *   Campaign ID (0 when encounter has no campaign context).
   * @param int $encounter_id
   *   Encounter ID.
   * @param array<string, mixed>|null $encounter
   *   Encounter snapshot from store.
   *
   * @return array<string, mixed>
   *   Normalized context envelope.
   */
  public function buildEncounterContext(int $campaign_id, int $encounter_id, ?array $encounter = NULL): array {
    if ($encounter === NULL) {
      throw new \InvalidArgumentException('Encounter context requires encounter snapshot.');
    }

    $participants = is_array($encounter['participants'] ?? NULL) ? $encounter['participants'] : [];
    $turn_index = (int) ($encounter['turn_index'] ?? 0);
    $current_actor = $participants[$turn_index] ?? NULL;

    if (!is_array($current_actor)) {
      throw new \InvalidArgumentException('Encounter has no active participant.');
    }

    $allowed_actions = $this->buildAllowedActionsForCurrentActor($current_actor);
    $actor_action_contract = $this->buildActorActionContract($current_actor, $allowed_actions);
    $actions_available_to_me_this_turn = $this->buildActorTurnActionAvailabilityEnvelope(
      $current_actor,
      $turn_index,
      $allowed_actions,
      $actor_action_contract
    );

    return [
      'campaign_id' => $campaign_id,
      'encounter_id' => $encounter_id,
      'status' => (string) ($encounter['status'] ?? 'unknown'),
      'current_round' => (int) ($encounter['current_round'] ?? 1),
      'turn_index' => $turn_index,
      'current_actor' => $current_actor,
      'participants' => $participants,
      'allowed_actions' => $allowed_actions,
      'action_contract' => $actor_action_contract,
      'actions_available_to_me_this_turn' => $actions_available_to_me_this_turn,
      'context_built_at' => $this->time->getCurrentTime(),
    ];
  }

  /**
   * Request a recommendation and validate it against encounter constraints.
   *
   * @param array<string, mixed> $context
   *   Encounter context.
   *
   * @return array<string, mixed>
   *   Recommendation and validation envelope.
   */
  public function requestNpcActionRecommendation(array $context): array {
    $recommendation = $this->provider->recommendNpcAction($context);
    $validation = $this->validateRecommendation($recommendation, $context);

    $this->loggerFactory->get('dungeoncrawler_content')->notice('Encounter AI recommendation preview generated.', [
      'encounter_id' => (int) ($context['encounter_id'] ?? 0),
      'campaign_id' => (int) ($context['campaign_id'] ?? 0),
      'provider' => $this->provider->getProviderName(),
      'valid' => $validation['valid'] ? 1 : 0,
    ]);

    return [
      'success' => TRUE,
      'provider' => $this->provider->getProviderName(),
      'recommendation' => $recommendation,
      'validation' => $validation,
      'requested_at' => $this->time->getCurrentTime(),
    ];
  }

  /**
   * Request encounter narration snippet from provider.
   *
   * @param array<string, mixed> $context
   *   Encounter context.
   *
   * @return array<string, mixed>
   *   Narration envelope.
   */
  public function requestEncounterNarration(array $context): array {
    return [
      'success' => TRUE,
      'provider' => $this->provider->getProviderName(),
      'narration' => $this->provider->generateEncounterNarration($context),
      'requested_at' => $this->time->getCurrentTime(),
    ];
  }

  /**
   * Validate recommendation against current turn actor and action constraints.
   *
   * @param array<string, mixed> $recommendation
   *   Provider recommendation payload.
   * @param array<string, mixed> $context
   *   Encounter context payload.
   *
   * @return array<string, mixed>
   *   Validation results.
   */
  public function validateRecommendation(array $recommendation, array $context): array {
    $errors = [];

    $current_actor = is_array($context['current_actor'] ?? NULL) ? $context['current_actor'] : [];
    $current_actor_ref = (string) ($current_actor['entity_ref'] ?? $current_actor['entity_id'] ?? '');
    $recommended_actor_ref = (string) ($recommendation['actor_instance_id'] ?? '');

    if ($current_actor_ref === '' || $recommended_actor_ref === '' || $recommended_actor_ref !== $current_actor_ref) {
      $errors[] = 'actor_instance_id must match active turn actor.';
    }

    if (($current_actor['team'] ?? '') === 'player') {
      $errors[] = 'active turn actor is a player; NPC recommendation is not applicable.';
    }

    $recommended_action = is_array($recommendation['recommended_action'] ?? NULL) ? $recommendation['recommended_action'] : [];
    $action_type = (string) ($recommended_action['type'] ?? '');
    $action_cost = (int) ($recommended_action['action_cost'] ?? 0);
    $action_context = $this->resolveValidationActionContext($context, $current_actor);
    $actions_remaining = $action_context['actions_remaining'];
    $allowed_actions = $action_context['allowed_actions'];

    if ($action_type === '' || !in_array($action_type, $allowed_actions, TRUE)) {
      $errors[] = 'recommended_action.type is not supported by server action handlers.';
    }

    if ($action_cost <= 0 || $action_cost > $actions_remaining) {
      $errors[] = 'recommended_action.action_cost exceeds actions remaining.';
    }

    return [
      'valid' => count($errors) === 0,
      'errors' => $errors,
    ];
  }

  /**
   * Resolve canonical action availability for recommendation validation.
   *
   * @param array<string, mixed> $context
   *   Encounter context payload.
   * @param array<string, mixed> $current_actor
   *   Active actor payload.
   *
   * @return array{allowed_actions: string[], actions_remaining: int}
   *   Canonical action context fields.
   */
  protected function resolveValidationActionContext(array $context, array $current_actor): array {
    $availability = is_array($context['actions_available_to_me_this_turn'] ?? NULL)
      ? $context['actions_available_to_me_this_turn']
      : [];
    $action_contract = is_array($availability['action_contract'] ?? NULL)
      ? $availability['action_contract']
      : (is_array($context['action_contract'] ?? NULL) ? $context['action_contract'] : []);

    $allowed_actions = is_array($availability['available_actions'] ?? NULL)
      ? $availability['available_actions']
      : [];

    if ($allowed_actions === []) {
      $allowed_actions = is_array($action_contract['available_actions'] ?? NULL)
        ? $action_contract['available_actions']
        : [];
    }

    if ($allowed_actions === []) {
      $allowed_actions = is_array($context['allowed_actions'] ?? NULL)
        ? $context['allowed_actions']
        : [];
    }

    $actions_remaining = is_numeric($availability['actions_remaining'] ?? NULL)
      ? max(0, (int) $availability['actions_remaining'])
      : max(0, (int) ($current_actor['actions_remaining'] ?? 3));

    return [
      'allowed_actions' => array_values(array_unique(array_filter(array_map(
        static fn($action): string => strtolower(trim((string) $action)),
        $allowed_actions
      )))),
      'actions_remaining' => $actions_remaining,
    ];
  }

  /**
   * Build deterministic action list for the active actor from turn resources.
   *
   * @param array<string, mixed> $current_actor
   *   Active actor payload.
   *
   * @return string[]
   *   Canonical allowed action IDs.
   */
  protected function buildAllowedActionsForCurrentActor(array $current_actor): array {
    // TODO(actor-action-availability): Delete this duplicate narrow builder once
    // preview/integration paths consume EncounterPhaseHandler's canonical actor
    // action-availability subsystem directly.
    $actions = [];
    $actions_remaining = max(0, (int) ($current_actor['actions_remaining'] ?? 3));
    $reaction_available = !empty($current_actor['reaction_available']);

    if ($actions_remaining >= 1) {
      $actions = array_merge($actions, [
        'strike',
        'step',
        'stride',
        'interact',
        'talk',
        'demoralize',
        'raise_shield',
      ]);
    }
    $actions[] = 'end_turn';
    if ($reaction_available) {
      $actions[] = 'reaction';
    }

    return array_values(array_unique($actions));
  }

  /**
   * Build canonical actor-scoped action-availability envelope.
   *
   * @param array<string, mixed> $current_actor
   *   Active actor payload.
   * @param int $turn_index
   *   Current turn index in participants.
   * @param string[] $allowed_actions
   *   Canonical allowed action IDs.
   *
   * @return array<string, mixed>
   *   Action-availability envelope.
   */
  protected function buildActorTurnActionAvailabilityEnvelope(array $current_actor, int $turn_index, array $allowed_actions, array $action_contract): array {
    $actor_ref = trim((string) ($current_actor['entity_ref'] ?? $current_actor['entity_id'] ?? ''));
    $actions_remaining = max(0, (int) ($current_actor['actions_remaining'] ?? 3));

    return [
      'actor_instance_id' => $actor_ref !== '' ? $actor_ref : NULL,
      'turn_index' => $turn_index,
      'actions_remaining' => $actions_remaining,
      'reaction_available' => !empty($current_actor['reaction_available']),
      'available_actions' => array_values(array_unique(array_filter(array_map(
        static fn($action): string => strtolower(trim((string) $action)),
        $allowed_actions
      )))),
      'action_contract' => $action_contract,
    ];
  }

  /**
   * Build structured encounter action contract for the active actor.
   *
   * @param array<string, mixed> $current_actor
   *   Active actor payload.
   * @param string[] $allowed_actions
   *   Canonical available actions for this actor.
   *
   * @return array<string, mixed>
   *   Structured action contract.
   */
  protected function buildActorActionContract(array $current_actor, array $allowed_actions): array {
    $available_map = [];
    foreach ($allowed_actions as $allowed_action) {
      $normalized = strtolower(trim((string) $allowed_action));
      if ($normalized !== '') {
        $available_map[$normalized] = TRUE;
      }
    }

    $actions = [];
    foreach (self::AI_ACTION_DEFINITIONS as $action_id => $definition) {
      $actions[] = [
        'id' => $action_id,
        'cost' => $definition['cost'],
        'category' => $definition['category'],
        'requires_turn' => $definition['requires_turn'],
        'targeting' => $definition['targeting'],
        'available' => !empty($available_map[$action_id]),
      ];
    }

    $actor_ref = trim((string) ($current_actor['entity_ref'] ?? $current_actor['entity_id'] ?? ''));
    return [
      'phase' => 'encounter',
      'actor_id' => $actor_ref !== '' ? $actor_ref : NULL,
      'current_turn_entity' => $actor_ref !== '' ? $actor_ref : NULL,
      'available_actions' => array_values(array_keys($available_map)),
      'actions' => $actions,
    ];
  }

}
