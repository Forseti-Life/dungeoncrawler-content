<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\ChatSessionManager;
use Drupal\dungeoncrawler_content\Service\DungeonEditorFindingsInterface;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionValidationException;
use Drupal\dungeoncrawler_content\Service\EditorGm\CharacterCreationGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmHarnessService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalGenerationException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * JSON transport for the editor-embedded GM harness.
 *
 * This controller is the only browser-facing entrypoint for the editor GM
 * assistant on every surface. Each route binds its surface through the
 * `_surface` default, so the same controller serves the Room Editor and the
 * Dungeon Editor without either being able to reach the other's toolset. It
 * never reaches campaign runtime authority; all work resolves through
 * EditorGmHarnessService and, beneath it, the surface's editor authority.
 */
class EditorGmController extends ControllerBase {

  public function __construct(
    protected EditorGmHarnessService $harness,
    protected CsrfTokenGenerator $csrfToken,
    protected ChatSessionManager $chatSessions,
    protected AccountProxyInterface $account,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.editor_gm_harness'),
      $container->get('csrf_token'),
      $container->get('dungeoncrawler_content.chat_session_manager'),
      $container->get('current_user'),
    );
  }

  /**
   * Returns the grounded context snapshot and declared toolset for a draft.
   */
  public function describe(string $_surface, Request $request, ?string $draft_id = NULL): JsonResponse {
    try {
      $profile = trim((string) $request->query->get('profile', 'editing')) ?: 'editing';
      $definition_scope = $this->definitionScopeFromQuery($request);
      $data = $this->harness->describe($_surface, $draft_id, $profile, $definition_scope);
      if ($session = $this->conversationSession($_surface, $draft_id, TRUE, $definition_scope)) {
        $data['conversation'] = $this->chatSessions->getRecentMessagesChronological((int) $session['id'], ChatSessionManager::MAX_QUERY_MESSAGES);
        if ($_surface === 'definition_editor') {
          $last_result = $this->latestEditorAssistantResult((int) $session['id']);
          if (!empty($last_result['proposed_execution'])) {
            $data['pending_editor_proposal'] = $last_result;
          }
        }
      }
      return new JsonResponse(['data' => $data]);
    }
    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Executes one editor GM request envelope.
   */
  public function execute(string $_surface, Request $request, ?string $draft_id = NULL): JsonResponse {
    if ($csrf = $this->validateCsrf($request)) {
      return $csrf;
    }
    try {
      $body = $this->decodeBody($request);
      $definition_scope = is_array($body['tool_context']['scope'] ?? NULL)
        ? $body['tool_context']['scope']
        : [];
      $session = $this->conversationSession($_surface, $draft_id, FALSE, $definition_scope);
      $scope = $session
        ? ['conversation_context' => $this->chatSessions->buildSessionContext((int) $session['id'], 20)]
        : [];
      if ($_surface === 'character_creation' && $session) {
        $scope['conversation_context'] .= $this->characterReferenceContext($session, (string) $draft_id);
      }
      if ($session) {
        $last_result = $this->latestEditorAssistantResult((int) $session['id']);
        $proposal = $last_result['proposed_execution'] ?? [];
        if ($_surface === 'storyline_builder') {
          if (!empty($last_result['requires_approval']) && ($proposal['tool_name'] ?? NULL) === 'apply_storyline_remediation') {
            $scope['pending_storyline_remediation'] = $proposal;
          }
          if (!empty($last_result['requires_approval']) && ($proposal['tool_name'] ?? NULL) === 'apply_storyline_draft_operations') {
            $scope['pending_storyline_draft_operations'] = $proposal;
          }
        }
        elseif (
          in_array($_surface, ['room_editor', 'dungeon_editor'], TRUE)
          && !empty($last_result['requires_approval'])
          && is_array($proposal)
        ) {
          $scope['pending_editor_execution'] = $proposal;
        }
        elseif (
          $_surface === 'definition_editor'
          && !empty($last_result['requires_approval'])
          && is_array($proposal)
        ) {
          $scope['pending_definition_execution'] = $proposal;
        }
      }
      $session ??= $this->conversationSession($_surface, $draft_id, TRUE, $definition_scope);
      if ($session) {
        $this->persistAuthorTurn($session, $body);
      }
      $data = $this->harness->handle($_surface, $draft_id, $body, $scope);
      if ($session) {
        $this->persistAssistantTurn($session, $data);
      }
      $active_draft_id = $data['tool_result']['active_draft_id'] ?? NULL;
      if ($_surface === 'room_editor' && is_string($active_draft_id) && $active_draft_id !== $draft_id) {
        $target_session = $this->conversationSession($_surface, $active_draft_id);
        $this->persistAuthorTurn($target_session, $body);
        $this->persistAssistantTurn($target_session, $data);
      }
      return new JsonResponse(['data' => $data]);
    }

    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

    /**
     * Reads the most recent assistant turn's structured storyline result.
     */
    private function latestEditorAssistantResult(int $session_id): array {
      foreach (array_reverse($this->chatSessions->getRecentMessagesChronological($session_id, 20)) as $message) {
        if (($message['metadata']['editor_gm_role'] ?? NULL) !== 'assistant') {
          continue;
        }
        return $message['metadata']['editor_gm_response']['tool_result'] ?? [];
      }
      return [];
    }

  /**
   * Retain verified conditional choices without creating another actor draft.
   */
  private function characterReferenceContext(array $session, string $draft_id): string {
    $context = CharacterCreationGmToolContext::of($this->harness->surface('character_creation')->createContext($draft_id, 'editing'));
    $revision = $context->draft()['revision'];
    $messages = $this->chatSessions->getRecentMessagesChronological((int) $session['id'], 20);
    foreach (array_reverse($messages) as $message) {
      $result = $message['metadata']['editor_gm_response']['tool_result'] ?? [];
      $steps = $result['candidate_step_choices'] ?? $result['validation']['steps'] ?? NULL;
      $inspected_revision = $result['draft_revision'] ?? $result['expected_revision'] ?? NULL;
      if (is_array($steps) && $inspected_revision === $revision) {
        return "\nVERIFIED CHARACTER CHOICES (read-only reference; nothing was saved):\n" . json_encode([
          'draft_revision' => $revision,
          'candidate_updates' => $result['candidate_updates'] ?? $result['updates'],
          'blocking_errors' => $result['blocking_errors'] ?? [],
          'steps' => array_map(static fn(array $step): array => [
            'number' => $step['number'], 'choices' => $step['choices'],
          ], $steps),
        ], JSON_THROW_ON_ERROR);
      }
    }
    return '';
  }

  /**
   * Resolves persistent conversation storage for draft-scoped Editor GM surfaces.
   */
  private function conversationSession(string $surface, ?string $draft_id, bool $create = TRUE, array $definition_scope = []): ?array {
    if ($surface === 'definition_editor') {
      $this->harness->surface($surface)->createContext(NULL, 'editing', $definition_scope);
      $family = trim((string) ($definition_scope['family'] ?? ''));
      $definition_id = trim((string) ($definition_scope['definition_id'] ?? ''));
      $draft_id = $family . ':' . ($definition_id !== '' ? $definition_id : 'new');
    }
    if (
      !in_array($surface, ['definition_editor', 'storyline_builder', 'dungeon_editor', 'room_editor', 'character_creation'], TRUE)
      || $draft_id === NULL
      || trim($draft_id) === ''
    ) {
      return NULL;
    }
    if ($surface === 'character_creation') {
      $this->harness->surface($surface)->createContext($draft_id, 'editing');
    }
    elseif ($surface === 'room_editor') {
      $this->harness->surface($surface)->createContext($draft_id, 'editing')->draft();
    }
    if (!$create) {
      return $this->chatSessions->loadSession($this->chatSessions->editorGmSessionKey($surface, $draft_id));
    }
    $label = match ($surface) {
      'storyline_builder' => 'Storyline Builder GM',
      'dungeon_editor' => 'Dungeon Editor GM',
      'room_editor' => 'Room Editor GM',
      'character_creation' => 'Character Creation GM',
      'definition_editor' => 'Definition Editor GM',
      default => 'Editor GM',
    };
    return $this->chatSessions->ensureEditorGmSession($surface, $draft_id, $label);
  }

  /**
   * Persists the author's message prior to execution so failed turns do not lose user input.
   */
  private function persistAuthorTurn(array $session, array $request): void {
    $intent = is_array($request['intent'] ?? NULL) ? $request['intent'] : [];
    $author_message = trim((string) ($intent['author_message'] ?? $intent['utterance'] ?? ''));
    if ($author_message !== '') {
      $this->chatSessions->postMessage(
        (int) $session['id'],
        0,
        $this->account->getDisplayName(),
        'player',
        (string) $this->account->id(),
        $author_message,
        'dialogue',
        'private',
        ['editor_gm_role' => 'author'],
        FALSE,
      );
    }
  }

  /**
   * Persists the structured GM assistant response turn.
   */
  private function persistAssistantTurn(array $session, array $response): void {
    $tool_result = is_array($response['tool_result'] ?? NULL) ? $response['tool_result'] : [];
    if (($tool_result['work_progress']['status'] ?? '') === 'running') {
      return;
    }
    if (($tool_result['schema_version'] ?? '') === 'storyline-definition-preview-v1') {
      unset($tool_result['definition']);
    }
    $persisted_response = [
      'route_family' => (string) ($response['route_family'] ?? ''),
      'tool_result' => $tool_result,
      'tool_calls' => array_values((array) ($response['tool_calls'] ?? $tool_result['tool_calls'] ?? [])),
      'messages' => array_values((array) ($response['messages'] ?? [])),
    ];
    $this->chatSessions->postMessage(
      (int) $session['id'],
      0,
      'Game Master Agent',
      'gm',
      '',
      $this->conversationContinuityMessage($tool_result),
      'dialogue',
      'private',
      [
        'editor_gm_role' => 'assistant',
        'editor_gm_response' => $persisted_response,
      ],
      FALSE,
    );
  }

  /**
   * Stores concise conversational continuity while metadata retains full data.
   */
  private function conversationContinuityMessage(array $tool_result): string {
    if (($tool_result['intent'] ?? '') === 'clarification') {
      $lines = ['QUESTION: ' . trim((string) ($tool_result['question'] ?? ''))];
      foreach (array_values((array) ($tool_result['options'] ?? [])) as $index => $option) {
        if (!is_array($option)) {
          continue;
        }
        $lines[] = sprintf(
          '%d. %s — %s',
          $index + 1,
          trim((string) ($option['label'] ?? '')),
          trim((string) ($option['description'] ?? '')),
        );
      }
      return implode("\n", $lines);
    }

    $lines = [];
    $next_steps = array_values(array_filter(
      (array) ($tool_result['next_steps'] ?? []),
      static fn($step): bool => is_string($step) && trim($step) !== '',
    ));
    if ($next_steps !== []) {
      $lines[] = 'NEXT STEPS:';
      foreach ($next_steps as $index => $step) {
        $lines[] = sprintf('%d. %s', $index + 1, trim($step));
      }
    }
    $answer = trim((string) ($tool_result['assistant_response'] ?? ''));
    if ($answer !== '') {
      $lines[] = 'RESPONSE: ' . $answer;
    }
    if ($lines === []) {
      $lines[] = 'RESULT: ' . trim((string) ($tool_result['tool'] ?? 'completed'));
    }
    return implode("\n", $lines);
  }


  /**
   * Extracts the definition-editor scope from GET query parameters.
   */
  private function definitionScopeFromQuery(Request $request): array {
    $scope = [];
    if ($request->query->has('family')) {
      $scope['family'] = (string) $request->query->get('family');
    }
    if ($request->query->has('definition_id')) {
      $scope['definition_id'] = (string) $request->query->get('definition_id');
    }
    return $scope;
  }

  /**
   * Decodes a strict JSON object request body.
   */
  private function decodeBody(Request $request): array {
    if (!str_starts_with((string) $request->headers->get('Content-Type'), 'application/json')) {
      throw new \InvalidArgumentException('content_type_must_be_json');
    }
    $body = json_decode($request->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($body) || array_is_list($body)) {
      throw new \InvalidArgumentException('json_object_required');
    }
    return $body;
  }

  /**
   * Validates Drupal's REST CSRF header token.
   */
  private function validateCsrf(Request $request): ?JsonResponse {
    $token = $request->headers->get('X-CSRF-Token');
    if (!$token || !$this->csrfToken->validate($token, CsrfRequestHeaderAccessCheck::TOKEN_KEY)) {
      return new JsonResponse([
        'error' => [
          'code' => 'csrf_token_invalid',
          'message' => 'A valid X-CSRF-Token is required.',
        ],
      ], 403);
    }
    return NULL;
  }

  /**
   * Maps domain failures to stable API errors.
   */
  private function errorResponse(\Throwable $exception): JsonResponse {
    $code = $exception->getMessage() ?: 'editor_gm_error';
    $status = match (TRUE) {
      $exception instanceof CanonicalGenerationException => $exception->httpStatus(),
      in_array($code, ['editor_gm_intent_output_truncated', 'editor_gm_context_window_exhausted'], TRUE) => 502,
      $exception instanceof \InvalidArgumentException && $code === 'definition_exists' => 409,
      $exception instanceof \InvalidArgumentException && $exception->getCode() === 422 => 422,
      $exception instanceof \JsonException,
      $exception instanceof \InvalidArgumentException => 400,
      $exception instanceof \OutOfBoundsException => 404,
      $exception instanceof \UnexpectedValueException => 403,
      $exception instanceof \RuntimeException && in_array($exception->getCode(), [403, 409, 422], TRUE) => $exception->getCode(),
      in_array($code, ['revision_conflict', 'idempotency_conflict', 'base_version_conflict', 'connector_identity_conflict', 'publication_blocked_by_active_campaign', 'definition_version_conflict'], TRUE) => 409,
      $exception instanceof DefinitionValidationException,
      $exception instanceof \DomainException => 422,
      default => 500,
    };
    if ($status === 500 && !$exception instanceof CanonicalGenerationException) {
      $this->getLogger('dungeoncrawler_content')->error('Editor GM harness failure: @message', [
        '@message' => $exception->getMessage(),
      ]);
      $code = 'editor_gm_internal_error';
    }
    elseif ($exception instanceof \DomainException || $exception instanceof \InvalidArgumentException) {
      $this->getLogger('dungeoncrawler_content')->warning('Editor GM contract rejected request: @code', [
        '@code' => $code,
      ]);
    }
    $error = [
      'code' => $code,
      'message' => str_replace('_', ' ', ucfirst($code)),
    ];
    if ($code === 'editor_gm_context_window_exhausted') {
      $error['message'] = 'This draft and its findings no longer fit in the GM model\'s context, so its reply was cut off and nothing was executed. Nothing was changed. This needs a fix to the GM, not a different request.';
    }
    if ($code === 'editor_gm_intent_output_truncated') {
      $error['message'] = 'The GM tried to write the whole change in one reply and ran out of room, so nothing was executed. Ask for it in smaller parts, or ask the GM to plan it as saved work. Batches already saved remain saved.';
    }
    if (str_starts_with($code, 'storyline_gm_lookup_continuation_limit:')) {
      $error['message'] = 'The GM repeated a lookup or reached its lookup limit. No requested draft changes were saved.';
    }
    if ($exception instanceof DefinitionValidationException) {
      $error['code'] = 'definition_validation_failed';
      $error['message'] = 'Definition validation failed';
      $error['findings'] = $exception->findings;
    }
    elseif ($exception instanceof DungeonEditorFindingsInterface) {
      $error['findings'] = $exception->getFindings();
    }
    return new JsonResponse(['error' => $error], $status);
  }

}
