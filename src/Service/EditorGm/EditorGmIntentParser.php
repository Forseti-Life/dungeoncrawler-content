<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\ai_conversation\Service\AIApiService;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Translates author utterances into grounded editor tool calls.
 *
 * The parser is the only place natural language enters the harness, and it is
 * deliberately narrow: it may only select a tool that is already registered,
 * with arguments it declares. It cannot invent capabilities, cannot mutate
 * anything, and cannot fall back to a canned answer. If the model is
 * unavailable or returns something that is not a valid grounded tool call, the
 * request hard-fails so the author is never shown a fabricated result.
 */
class EditorGmIntentParser {

  public const MODULE = 'dungeoncrawler_content';
  public const OPERATION = 'editor_gm_intent';

  private const MAX_UTTERANCE_LENGTH = 2000;

  protected LoggerInterface $logger;

  public function __construct(
    protected ?AIApiService $ai,
    protected EditorGmToolRegistry $registry,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('dungeoncrawler_content');
  }

  /**
   * Returns TRUE when a model backend is wired up.
   */
  public function isAvailable(): bool {
    return $this->ai !== NULL;
  }

  /**
   * Resolves one utterance into a tool call or a clarification request.
   *
   * @return array
   *   Either ['type' => 'tool_call', 'tool_name' => ..., 'arguments' => [...],
   *   'reasoning' => ...] or ['type' => 'clarification', 'question' => ...].
   */
  public function parse(string $utterance, array $context_snapshot): array {
    $utterance = trim($utterance);
    if ($utterance === '') {
      throw new \InvalidArgumentException('editor_gm_utterance_required');
    }
    if (mb_strlen($utterance) > self::MAX_UTTERANCE_LENGTH) {
      throw new \InvalidArgumentException('editor_gm_utterance_too_long');
    }
    if ($this->ai === NULL) {
      throw new \RuntimeException('editor_gm_intent_parser_unavailable');
    }

    $result = $this->ai->invokeModelDirect(
      $this->buildPrompt($utterance, $context_snapshot),
      self::MODULE,
      self::OPERATION,
      ['draft_id' => (string) ($context_snapshot['draft']['draft_id'] ?? '')],
      [
        // Each turn depends on live draft state, so a cached completion would
        // be a correctness bug rather than an optimization.
        'skip_cache' => TRUE,
        'max_tokens' => 1500,
        'system_prompt' => $this->systemPrompt(),
      ],
    );

    if (!is_array($result) || empty($result['success'])) {
      $this->logger->error('Editor GM intent parsing failed: @error', [
        '@error' => is_array($result) ? (string) ($result['error'] ?? 'unknown') : 'malformed provider response',
      ]);
      throw new \RuntimeException('editor_gm_intent_model_failed');
    }

    return $this->decodeIntent((string) ($result['response'] ?? ''));
  }

  /**
   * Parses and validates the model's JSON response against the registry.
   */
  private function decodeIntent(string $response): array {
    $json = trim($response);
    if (str_starts_with($json, '```')) {
      $json = trim(preg_replace('/^```(?:json)?|```$/m', '', $json));
    }
    $start = strpos($json, '{');
    $end = strrpos($json, '}');
    if ($start === FALSE || $end === FALSE || $end < $start) {
      throw new \DomainException('editor_gm_intent_response_not_json');
    }

    try {
      $decoded = json_decode(substr($json, $start, $end - $start + 1), TRUE, 32, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      throw new \DomainException('editor_gm_intent_response_not_json');
    }
    if (!is_array($decoded)) {
      throw new \DomainException('editor_gm_intent_response_not_json');
    }

    $type = (string) ($decoded['type'] ?? '');
    if ($type === 'clarification') {
      $question = trim((string) ($decoded['question'] ?? ''));
      if ($question === '') {
        throw new \DomainException('editor_gm_intent_clarification_empty');
      }
      return ['type' => 'clarification', 'question' => $question];
    }
    if ($type !== 'tool_call') {
      throw new \DomainException('editor_gm_intent_type_unsupported');
    }

    $tool_name = (string) ($decoded['tool_name'] ?? '');
    if (!$this->registry->has($tool_name)) {
      throw new \DomainException(sprintf('editor_gm_intent_tool_unsupported:%s', $tool_name));
    }
    $arguments = $decoded['arguments'] ?? [];
    if (!is_array($arguments)) {
      throw new \DomainException('editor_gm_intent_arguments_invalid');
    }

    $definition = $this->registry->get($tool_name)->definition();
    foreach ($definition->arguments as $argument) {
      if (!empty($argument['required']) && !array_key_exists($argument['name'], $arguments)) {
        throw new \DomainException(sprintf(
          'editor_gm_intent_argument_missing:%s.%s',
          $tool_name,
          $argument['name']
        ));
      }
    }

    return [
      'type' => 'tool_call',
      'tool_name' => $tool_name,
      'arguments' => $arguments,
      'reasoning' => trim((string) ($decoded['reasoning'] ?? '')),
    ];
  }

  /**
   * Returns the invariant behavioural contract for the parser.
   */
  private function systemPrompt(): string {
    return <<<'PROMPT'
You are the intent parser for the Dungeoncrawler canonical Room Editor assistant.

Your only job is to translate an author's request into exactly one registered
tool call, using only the grounded editor context you are given.

Hard rules:
- Respond with a single JSON object and nothing else. No prose, no code fences.
- You may only use a tool name from the provided toolset. Never invent one.
- Never invent room ids, hex coordinates, instance ids, or definition ids. Use
  only values present in the grounded context, or values the author stated.
- If the request is ambiguous, or you would have to guess a required value,
  return a clarification instead of a tool call.
- Prefer planning tools over execution tools. A mutating tool call is a
  proposal that the author must approve; it is never applied by you.

Response shapes:
{"type":"tool_call","tool_name":"<registered tool>","arguments":{...},"reasoning":"<one short sentence>"}
{"type":"clarification","question":"<what you need the author to specify>"}
PROMPT;
  }

  /**
   * Builds the grounded prompt for one turn.
   */
  private function buildPrompt(string $utterance, array $context_snapshot): string {
    $manifest = $this->registry->manifest();
    $tools = [];
    foreach ($manifest['families'] as $family => $definitions) {
      foreach ($definitions as $definition) {
        $tools[] = [
          'name' => $definition['name'],
          'family' => $family,
          'mutating' => $definition['mutating'],
          'summary' => $definition['summary'],
          'arguments' => $definition['arguments'],
        ];
      }
    }

    $grounding = [
      'draft' => $context_snapshot['draft'] ?? [],
      'room' => $context_snapshot['room'] ?? [],
      'validation_summary' => $context_snapshot['validation_summary'] ?? [],
      'publication' => $context_snapshot['publication'] ?? [],
    ];

    return implode("\n\n", [
      '## Grounded editor context',
      json_encode($grounding, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      '## Registered toolset',
      json_encode($tools, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      '## Supported command types for planning',
      implode(', ', $manifest['supported_command_types']),
      '## Required payload keys per command type',
      json_encode($manifest['command_payload_contracts'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      '## Author request',
      $utterance,
      '## Your response (single JSON object)',
    ]);
  }

}
