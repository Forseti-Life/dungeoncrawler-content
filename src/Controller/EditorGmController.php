<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\ChatSessionManager;
use Drupal\dungeoncrawler_content\Service\DungeonEditorFindingsInterface;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionValidationException;
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
      $data = $this->harness->describe($_surface, $draft_id, $profile, $this->definitionScopeFromQuery($request));
      if ($session = $this->conversationSession($_surface, $draft_id)) {
        $data['conversation'] = $this->chatSessions->getRecentMessagesChronological((int) $session['id'], ChatSessionManager::MAX_QUERY_MESSAGES);
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
      $session = $this->conversationSession($_surface, $draft_id, FALSE);
      $scope = $session
        ? ['conversation_context' => $this->chatSessions->buildSessionContext((int) $session['id'], 20)]
        : [];
      $session ??= $this->conversationSession($_surface, $draft_id);
      if ($session) {
        $this->persistAuthorTurn($session, $body);
      }
      $data = $this->harness->handle($_surface, $draft_id, $body, $scope);
      if ($session) {
        $this->persistAssistantTurn($session, $data);
      }
      return new JsonResponse(['data' => $data]);
    }

    catch (\Throwable $exception) {
      return $this->errorResponse($exception);
    }
  }

  /**
   * Resolves persistent conversation storage for draft-scoped Editor GM surfaces.
   */
  private function conversationSession(string $surface, ?string $draft_id, bool $create = TRUE): ?array {
    if (!in_array($surface, ['storyline_builder', 'dungeon_editor', 'room_editor', 'character_creation'], TRUE) || $draft_id === NULL || trim($draft_id) === '') {
      return NULL;
    }
    if ($surface === 'character_creation') {
      $this->harness->surface($surface)->createContext($draft_id, 'editing');
    }
    if (!$create) {
      return $this->chatSessions->loadSession($this->chatSessions->editorGmSessionKey($surface, $draft_id));
    }
    $label = match ($surface) {
      'storyline_builder' => 'Storyline Builder GM',
      'dungeon_editor' => 'Dungeon Editor GM',
      'room_editor' => 'Room Editor GM',
      'character_creation' => 'Character Creation GM',
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
          trim((string) ($option['selection'] ?? $option['description'] ?? '')),
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
      $exception instanceof \InvalidArgumentException && $code === 'definition_exists' => 409,
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
    elseif ($exception instanceof \DomainException) {
      $this->getLogger('dungeoncrawler_content')->warning('Editor GM contract rejected request: @code', [
        '@code' => $code,
      ]);
    }
    $error = [
      'code' => $code,
      'message' => str_replace('_', ' ', ucfirst($code)),
    ];
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
