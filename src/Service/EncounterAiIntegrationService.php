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

    return [
      'campaign_id' => $campaign_id,
      'encounter_id' => $encounter_id,
      'status' => (string) ($encounter['status'] ?? 'unknown'),
      'current_round' => (int) ($encounter['current_round'] ?? 1),
      'turn_index' => $turn_index,
      'current_actor' => $current_actor,
      'participants' => $participants,
      'allowed_actions' => [
        'strike',
        'step',
        'stride',
        'interact',
        'talk',
        'demoralize',
        'raise_shield',
        'end_turn',
      ],
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
    $actions_remaining = (int) ($current_actor['actions_remaining'] ?? 3);
    $allowed_actions = is_array($context['allowed_actions'] ?? NULL) ? $context['allowed_actions'] : [];

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

}
