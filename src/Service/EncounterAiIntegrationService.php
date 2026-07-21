<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Orchestrates encounter AI recommendation and validation in read-only mode.
 */
class EncounterAiIntegrationService {

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
   * Shared actor action-availability resolver.
   */
  protected ActorActionAvailabilityService $actionAvailability;

  /**
   * Constructs service.
   */
  public function __construct(
    EncounterAiProviderInterface $provider,
    TimeInterface $time,
    LoggerChannelFactoryInterface $logger_factory,
    ?ActorActionAvailabilityService $action_availability = NULL
  ) {
    $this->provider = $provider;
    $this->time = $time;
    $this->loggerFactory = $logger_factory;
    $this->actionAvailability = $action_availability ?? new ActorActionAvailabilityService();
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

    $actor_id = $this->resolveCurrentActorId($current_actor);
    $actions_remaining = max(0, (int) ($current_actor['actions_remaining'] ?? 3));
    $reaction_available = !empty($current_actor['reaction_available']);
    $availability = $this->actionAvailability->resolveEncounterAvailability(
      [
        'encounter_id' => $encounter_id,
        'turn' => [
          'entity' => $actor_id,
          'actions_remaining' => $actions_remaining,
          'reaction_available' => $reaction_available,
        ],
        'encounter_context' => [
          'mode' => 'encounter',
          'room_id' => (string) ($encounter['room_id'] ?? ''),
        ],
      ],
      $this->buildAvailabilityDungeonSnapshot($participants, $encounter),
      $actor_id !== '' ? $actor_id : NULL
    );
    $allowed_actions = is_array($availability['available_actions'] ?? NULL)
      ? $availability['available_actions']
      : [];
    $actor_action_contract = is_array($availability['action_contract'] ?? NULL)
      ? $availability['action_contract']
      : [];
    $actions_available_to_me_this_turn = is_array($availability['availability_envelope'] ?? NULL)
      ? $availability['availability_envelope']
      : [];
    $action_contract_hash = $this->buildActionContractHash($actor_action_contract, $allowed_actions);

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
      'action_contract_hash' => $action_contract_hash,
      'action_option_families' => is_array($actor_action_contract['action_option_families'] ?? NULL)
        ? $actor_action_contract['action_option_families']
        : [],
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
    if (empty($validation['valid'])) {
      throw new \RuntimeException('Encounter AI recommendation contract violation: ' . implode('; ', (array) ($validation['errors'] ?? [])));
    }

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
    $shape_errors = $this->validateRecommendationShape($recommendation, $context);
    if ($shape_errors !== []) {
      $errors = array_merge($errors, $shape_errors);
    }

    $current_actor = is_array($context['current_actor'] ?? NULL) ? $context['current_actor'] : [];
    $current_actor_ref = $this->resolveCurrentActorId($current_actor);
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
    $action_definitions = $action_context['action_definitions'];

    if ($action_type === '' || !in_array($action_type, $allowed_actions, TRUE)) {
      $errors[] = 'recommended_action.type is not supported by server action handlers.';
    }

    $action_definition = is_array($action_definitions[$action_type] ?? NULL)
      ? $action_definitions[$action_type]
      : NULL;
    if ($action_definition === NULL) {
      $errors[] = 'recommended_action.type is missing from the canonical action contract.';
    }
    else {
      $canonical_cost = $action_definition['cost'] ?? NULL;
      if (is_numeric($canonical_cost)) {
        $canonical_cost = (int) $canonical_cost;
        if ($action_cost !== $canonical_cost) {
          $errors[] = 'recommended_action.action_cost does not match the canonical action cost.';
        }
        if ($action_cost > $actions_remaining) {
          $errors[] = 'recommended_action.action_cost exceeds actions remaining.';
        }
      }
      elseif ($canonical_cost === 'reaction' && $action_cost < 0) {
        $errors[] = 'recommended_action.action_cost is invalid for reaction actions.';
      }
    }

    return [
      'valid' => count($errors) === 0,
      'errors' => $errors,
    ];
  }

  /**
   * Validate strict recommendation shape and action-parameter requirements.
   *
   * @param array<string, mixed> $recommendation
   *   Provider recommendation payload.
   * @param array<string, mixed> $context
   *   Encounter context payload.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  protected function validateRecommendationShape(array $recommendation, array $context): array {
    $errors = [];
    foreach (['version', 'actor_instance_id', 'recommended_action', 'decision_reason', 'decision_basis', 'confidence', 'contract_version'] as $required_field) {
      if (!array_key_exists($required_field, $recommendation)) {
        $errors[] = sprintf('missing required field "%s".', $required_field);
      }
    }

    $version = trim((string) ($recommendation['version'] ?? ''));
    if ($version === '') {
      $errors[] = 'version must be a non-empty string.';
    }

    $decision_reason = trim((string) ($recommendation['decision_reason'] ?? ''));
    if ($decision_reason === '') {
      $errors[] = 'decision_reason must be a non-empty string.';
    }

    $confidence_raw = $recommendation['confidence'] ?? NULL;
    if (!is_numeric($confidence_raw)) {
      $errors[] = 'confidence must be numeric.';
    }
    else {
      $confidence = (float) $confidence_raw;
      if ($confidence < 0.0 || $confidence > 1.0) {
        $errors[] = 'confidence must be between 0 and 1.';
      }
    }

    $decision_basis = $recommendation['decision_basis'] ?? NULL;
    if (!is_array($decision_basis)) {
      $errors[] = 'decision_basis must be an object.';
    }
    else {
      foreach (['used_profile', 'used_psychology', 'used_availability'] as $basis_flag) {
        if (!array_key_exists($basis_flag, $decision_basis) || !is_bool($decision_basis[$basis_flag])) {
          $errors[] = sprintf('decision_basis.%s must be a boolean.', $basis_flag);
        }
      }
    }

    $recommended_action = $recommendation['recommended_action'] ?? NULL;
    if (!is_array($recommended_action)) {
      $errors[] = 'recommended_action must be an object.';
      return $errors;
    }

    foreach (['type', 'action_cost', 'parameters'] as $required_action_field) {
      if (!array_key_exists($required_action_field, $recommended_action)) {
        $errors[] = sprintf('recommended_action.%s is required.', $required_action_field);
      }
    }

    $action_type = strtolower(trim((string) ($recommended_action['type'] ?? '')));
    if ($action_type === '') {
      $errors[] = 'recommended_action.type must be a non-empty string.';
    }

    if (!is_numeric($recommended_action['action_cost'] ?? NULL)) {
      $errors[] = 'recommended_action.action_cost must be numeric.';
    }

    $parameters = $recommended_action['parameters'] ?? NULL;
    if (!is_array($parameters)) {
      $errors[] = 'recommended_action.parameters must be an object.';
    }

    $target = $recommended_action['target_instance_id'] ?? NULL;
    if (!is_null($target) && !is_string($target)) {
      $errors[] = 'recommended_action.target_instance_id must be string or null.';
    }

    $action_context = $this->resolveValidationActionContext($context, is_array($context['current_actor'] ?? NULL) ? $context['current_actor'] : []);
    $action_definition = $action_context['action_definitions'][$action_type] ?? NULL;
    if (is_array($action_definition)) {
      $targeting = strtolower(trim((string) ($action_definition['targeting'] ?? '')));
      if (in_array($targeting, ['hostile_entity', 'entity_or_object', 'entity_or_room', 'connected_room', 'room_hazard'], TRUE)) {
        if (!is_string($target) || trim($target) === '') {
          $errors[] = sprintf('recommended_action.target_instance_id is required for targeting "%s".', $targeting);
        }
      }
    }

    if (is_array($parameters) && $action_type !== '') {
      $errors = array_merge($errors, $this->validateActionSpecificParameters($action_type, $parameters));
    }

    $expected_contract_hash = $this->resolveContextActionContractHash($context);
    $contract_version = trim((string) ($recommendation['contract_version'] ?? ''));
    if ($contract_version === '') {
      $errors[] = 'contract_version must be a non-empty string.';
    }
    elseif ($expected_contract_hash !== '' && $contract_version !== $expected_contract_hash) {
      $errors[] = 'contract_version does not match current action contract hash.';
    }

    return $errors;
  }

  /**
   * Validate action-type-specific required parameters.
   *
   * @param string $action_type
   *   Recommended action type.
   * @param array<string, mixed> $parameters
   *   Recommended action parameter map.
   *
   * @return array<int, string>
   *   Validation errors.
   */
  protected function validateActionSpecificParameters(string $action_type, array $parameters): array {
    $errors = [];
    if ($action_type === 'talk') {
      $message = trim((string) ($parameters['message'] ?? ''));
      if ($message === '') {
        $errors[] = 'recommended_action.parameters.message is required for talk.';
      }
    }

    if (in_array($action_type, ['cast_spell', 'use_feat', 'use_consumable', 'activate_item', 'trigger_hazard'], TRUE)) {
      $option_id = trim((string) ($parameters['option_id'] ?? ''));
      if ($option_id === '') {
        $errors[] = sprintf('recommended_action.parameters.option_id is required for %s.', $action_type);
      }
    }

    if (in_array($action_type, ['stride', 'step'], TRUE)) {
      $target_hex = $parameters['target_hex'] ?? NULL;
      $has_q = is_array($target_hex) && is_numeric($target_hex['q'] ?? NULL);
      $has_r = is_array($target_hex) && is_numeric($target_hex['r'] ?? NULL);
      if (!$has_q || !$has_r) {
        $errors[] = sprintf('recommended_action.parameters.target_hex.{q,r} is required for %s.', $action_type);
      }
    }

    if ($action_type === 'transition') {
      $target_room_id = trim((string) ($parameters['target_room_id'] ?? ''));
      if ($target_room_id === '') {
        $errors[] = 'recommended_action.parameters.target_room_id is required for transition.';
      }
    }

    return $errors;
  }

  /**
   * Resolve deterministic hash representing the current action contract.
   *
   * @param array<string, mixed> $action_contract
   *   Canonical action contract.
   * @param array<int, string> $allowed_actions
   *   Currently allowed action IDs.
   */
  protected function buildActionContractHash(array $action_contract, array $allowed_actions): string {
    $payload = [
      'available_actions' => array_values(array_unique(array_map(
        static fn($action): string => strtolower(trim((string) $action)),
        $allowed_actions
      ))),
      'action_contract' => $action_contract,
    ];
    return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  }

  /**
   * Resolve expected contract hash from context or derive it from action fields.
   *
   * @param array<string, mixed> $context
   *   Encounter context payload.
   */
  protected function resolveContextActionContractHash(array $context): string {
    $existing = trim((string) ($context['action_contract_hash'] ?? ''));
    if ($existing !== '') {
      return $existing;
    }

    $availability = is_array($context['actions_available_to_me_this_turn'] ?? NULL)
      ? $context['actions_available_to_me_this_turn']
      : [];
    $action_contract = is_array($availability['action_contract'] ?? NULL)
      ? $availability['action_contract']
      : (is_array($context['action_contract'] ?? NULL) ? $context['action_contract'] : []);
    $allowed_actions = is_array($availability['available_actions'] ?? NULL)
      ? $availability['available_actions']
      : (is_array($context['allowed_actions'] ?? NULL) ? $context['allowed_actions'] : []);

    if ($action_contract === [] && $allowed_actions === []) {
      return '';
    }

    return $this->buildActionContractHash($action_contract, $allowed_actions);
  }

  /**
   * Resolve canonical action availability for recommendation validation.
   *
   * @param array<string, mixed> $context
   *   Encounter context payload.
   * @param array<string, mixed> $current_actor
   *   Active actor payload.
   *
   * @return array{allowed_actions: string[], actions_remaining: int, action_definitions: array<string, array<string, mixed>>}
   *   Canonical action context fields.
   */
  protected function resolveValidationActionContext(array $context, array $current_actor): array {
    $availability = is_array($context['actions_available_to_me_this_turn'] ?? NULL)
      ? $context['actions_available_to_me_this_turn']
      : [];
    $action_contract = is_array($availability['action_contract'] ?? NULL)
      ? $availability['action_contract']
      : (is_array($context['action_contract'] ?? NULL) ? $context['action_contract'] : []);
    if (!is_array($action_contract['action_option_families'] ?? NULL) && is_array($availability['action_option_families'] ?? NULL)) {
      $action_contract['action_option_families'] = $availability['action_option_families'];
    }

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

    $action_definitions = [];
    foreach ((array) ($action_contract['actions'] ?? []) as $action_definition) {
      if (!is_array($action_definition)) {
        continue;
      }
      $action_id = strtolower(trim((string) ($action_definition['id'] ?? '')));
      if ($action_id === '') {
        continue;
      }
      $action_definitions[$action_id] = $action_definition;
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
      'action_definitions' => $action_definitions,
    ];
  }

  /**
   * Resolve a stable actor id from preview/current actor payloads.
   */
  protected function resolveCurrentActorId(array $current_actor): string {
    $entity_id = trim((string) ($current_actor['entity_id'] ?? ''));
    if ($entity_id !== '') {
      return $entity_id;
    }

    $entity_ref = $current_actor['entity_ref'] ?? NULL;
    if (is_string($entity_ref)) {
      $decoded = json_decode($entity_ref, TRUE);
      if (is_array($decoded)) {
        $content_id = trim((string) ($decoded['content_id'] ?? ''));
        if ($content_id !== '') {
          return $content_id;
        }
      }
      return trim($entity_ref);
    }

    if (is_array($entity_ref)) {
      $content_id = trim((string) ($entity_ref['content_id'] ?? ''));
      if ($content_id !== '') {
        return $content_id;
      }
    }

    return '';
  }

  /**
   * Build a minimal dungeon snapshot for shared actor availability resolution.
   *
   * @param array<int, mixed> $participants
   *   Encounter participant rows.
   * @param array<string, mixed> $encounter
   *   Encounter snapshot payload.
   *
   * @return array<string, mixed>
   *   Dungeon-like snapshot with entity and room collections.
   */
  protected function buildAvailabilityDungeonSnapshot(array $participants, array $encounter): array {
    $entities = [];
    foreach ($participants as $participant) {
      if (!is_array($participant)) {
        continue;
      }

      $entity_id = $this->resolveCurrentActorId($participant);
      if ($entity_id === '') {
        continue;
      }

      $entities[] = [
        'entity_instance_id' => $entity_id,
        'entity_ref' => $participant['entity_ref'] ?? $entity_id,
        'state' => is_array($participant['state'] ?? NULL) ? $participant['state'] : [],
        'heritage' => $participant['heritage'] ?? NULL,
      ];
    }

    $room_id = (string) ($encounter['room_id'] ?? '');
    return [
      'active_room_id' => $room_id,
      'entities' => $entities,
      'rooms' => $room_id !== '' ? [['room_id' => $room_id]] : [],
    ];
  }

}
