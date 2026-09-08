<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Generation;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Single canonical generation pipeline for Dungeoncrawler content.
 *
 * Board directive 2026-09-08, org-wide "Single path for system logic": no
 * competing paths for generation. All generation callers must converge on this
 * core for prompt assembly, provider call, JSON extraction, validation retry,
 * and provenance; adapters may only translate the validated result into their
 * local transport/persistence shape.
 */
class CanonicalGenerationService {

  private const MAX_ATTEMPTS = 2;
  private const MAX_PROMPT_CHARS = 32768;

  private const ORIGINALITY_INSTRUCTION = 'Create original project-authored Dungeoncrawler content only. Do not reproduce or closely imitate third-party settings, named characters, locations, adventure text, item names, monster names, lore, maps, or protected trade dress. Do not use Paizo/Golarion/Otari/Absalom/Foundry-community fixture content. If the user asks for a protected third-party work, produce a legally distinct alternative with original names and descriptions.';

  public function __construct(
    private readonly ?object $aiApiService,
    private readonly TimeInterface $time,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('dungeoncrawler_content');
  }

  private readonly object $logger;

  public function originalityInstruction(): string {
    return self::ORIGINALITY_INSTRUCTION;
  }

  /**
   * Runs the canonical generation stages and bounded retry loop.
   *
   * @param callable(array): string $prompt_builder
   *   Builds a JSON-schema-constrained prompt. Receives prior findings after
   *   a nonconforming attempt.
   * @param callable(array,array): array $validator
   *   Validates decoded provider JSON and returns the caller-facing result.
   *   Throw CanonicalGenerationException('generation_nonconforming') to retry.
   */
  public function completeJson(
    string $tool,
    string $operation,
    int $seed,
    int $max_tokens,
    callable $prompt_builder,
    callable $validator,
  ): array {
    if ($this->aiApiService === NULL || !method_exists($this->aiApiService, 'invokeModelDirect')) {
      throw $this->exception('generation_provider_unavailable', [$this->finding('generation_provider_unavailable', '/provider', 'ai_conversation.ai_api_service is not available.')], 500);
    }

    $prior_findings = [];
    $all_findings = [];
    for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
      $prompt = $prompt_builder($prior_findings);
      if (mb_strlen($prompt) > self::MAX_PROMPT_CHARS) {
        throw $this->exception('generation_size_limit_exceeded', [$this->finding('generation_size_limit_exceeded', '/prompt', 'Generation prompt exceeds the 32768 character cap.')]);
      }
      $result = $this->aiApiService->invokeModelDirect($prompt, 'dungeoncrawler_content', $operation, [
        'seed' => $seed,
        'attempt' => $attempt,
        'tool' => $tool,
      ], [
        'skip_cache' => TRUE,
        'max_tokens' => $max_tokens,
        'thinking' => 'disabled',
      ]);
      if (!is_array($result) || empty($result['success'])) {
        $message = is_array($result) ? (string) ($result['error'] ?? 'Provider returned an unsuccessful completion.') : 'Provider returned a non-object result.';
        throw $this->exception('generation_provider_failed', [$this->finding('generation_provider_failed', '/provider', $message)], 500);
      }
      $response = (string) ($result['response'] ?? '');
      $decoded = $this->extractSingleJsonObject($response);
      $model = trim((string) ($result['model_id'] ?? $result['model'] ?? $result['provider'] ?? 'ai_conversation.invokeModelDirect'));
      $provenance = [
        'tool' => $tool,
        'model' => $model !== '' ? $model : 'ai_conversation.invokeModelDirect',
        'prompt_hash' => 'sha256:' . hash('sha256', $prompt),
        'seed' => $seed,
        'generated_at' => gmdate(DATE_RFC3339, $this->time->getRequestTime()),
      ];
      if (array_key_exists('finish_reason', $result)) {
        $provenance['finish_reason'] = is_scalar($result['finish_reason']) ? (string) $result['finish_reason'] : NULL;
      }
      if (array_key_exists('reasoning_tokens', $result)) {
        $provenance['reasoning_tokens'] = $result['reasoning_tokens'] === NULL ? NULL : (int) $result['reasoning_tokens'];
      }
      $this->logger->info('Canonical generation provider response for @tool/@operation had finish_reason=@finish_reason reasoning_tokens=@reasoning_tokens.', [
        '@tool' => $tool,
        '@operation' => $operation,
        '@finish_reason' => (string) ($provenance['finish_reason'] ?? 'unknown'),
        '@reasoning_tokens' => array_key_exists('reasoning_tokens', $provenance) && $provenance['reasoning_tokens'] !== NULL ? (string) $provenance['reasoning_tokens'] : 'null',
      ]);

      try {
        return $validator($decoded, $provenance);
      }
      catch (CanonicalGenerationException $exception) {
        if ($exception->getMessage() !== 'generation_nonconforming') {
          throw $exception;
        }
        $prior_findings = $exception->getFindings();
        $all_findings = array_merge($all_findings, $prior_findings);
      }
    }

    throw $this->exception('generation_nonconforming', $all_findings !== [] ? $all_findings : [$this->finding('generation_nonconforming', '/', 'Generated output failed validation.')]);
  }

  private function extractSingleJsonObject(string $response): array {
    $text = trim($response);
    if (preg_match('/^```(?:json)?\s*(\{[\s\S]*\})\s*```$/i', $text, $matches)) {
      $text = trim($matches[1]);
    }
    if ($text === '' || !str_starts_with($text, '{') || !str_ends_with($text, '}')) {
      throw $this->exception('generation_response_not_json', [$this->finding('generation_response_not_json', '/', 'Completion must contain exactly one JSON object and no prose.')]);
    }
    try {
      $decoded = json_decode($text, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      throw $this->exception('generation_response_not_json', [$this->finding('generation_response_not_json', '/', $exception->getMessage())], 422, $exception);
    }
    if (!is_array($decoded) || array_is_list($decoded)) {
      throw $this->exception('generation_response_not_json', [$this->finding('generation_response_not_json', '/', 'Completion JSON root must be an object.')]);
    }
    return $decoded;
  }

  public function finding(string $code, string $pointer, string $message): array {
    return ['code' => $code, 'pointer' => $pointer, 'message' => $message, 'severity' => 'error'];
  }

  private function exception(string $code, array $findings, int $status = 422, ?\Throwable $previous = NULL): CanonicalGenerationException {
    return new CanonicalGenerationException($code, $findings, $status, $previous);
  }

}
