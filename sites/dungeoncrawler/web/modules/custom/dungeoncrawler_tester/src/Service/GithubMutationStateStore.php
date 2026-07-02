<?php

namespace Drupal\dungeoncrawler_tester\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Handles mutation lock/throttle, cooldown, and dedupe state for GitHub client.
 */
class GithubMutationStateStore {

  /**
   * Minimum delay between serialized mutative API requests.
   */
  private const MUTATION_MIN_INTERVAL_SECONDS = 1.0;

  /**
   * Lock file name used to serialize mutative calls.
   */
  private const MUTATION_LOCK_FILE = 'dungeoncrawler_tester_github_mutation.lock';

  /**
   * State file for mutative rate-limit failure/cooldown tracking.
   */
  private const COOLDOWN_STATE_FILE = 'dungeoncrawler_tester_github_cooldown.json';

  /**
   * Consecutive mutative rate-limit failures before cooldown activates.
   */
  private const COOLDOWN_FAILURE_THRESHOLD = 3;

  /**
   * Cooldown duration (seconds) once threshold is reached.
   */
  private const COOLDOWN_SECONDS = 300;

  /**
   * State file for recently applied mutative dedupe keys.
   */
  private const MUTATION_DEDUPE_STATE_FILE = 'dungeoncrawler_tester_github_mutation_dedupe.json';

  /**
   * Time window (seconds) for mutative dedupe suppression.
   */
  private const MUTATION_DEDUPE_WINDOW_SECONDS = 300;

  /**
   * Logger channel.
   */
  private LoggerChannelInterface $logger;

  public function __construct(
    private readonly GithubClientContextResolver $contextResolver,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('dungeoncrawler_tester');
  }

  /**
   * Execute operation under a cross-process mutation lock.
   */
  public function withMutationLock(callable $operation) {
    $lockPath = $this->getTempStatePath(self::MUTATION_LOCK_FILE);
    $handle = @fopen($lockPath, 'c+');

    if ($handle === FALSE) {
      $lastMutationAt = 0.0;
      return call_user_func_array($operation, [&$lastMutationAt]);
    }

    $locked = @flock($handle, LOCK_EX);
    $lastMutationAt = $locked ? $this->readLastMutationTimestamp($handle) : 0.0;

    try {
      return call_user_func_array($operation, [&$lastMutationAt]);
    }
    finally {
      if ($locked) {
        $this->writeLastMutationTimestamp($handle, $lastMutationAt);
        @flock($handle, LOCK_UN);
      }
      @fclose($handle);
    }
  }

  /**
   * Enforce minimum interval between mutative API calls.
   */
  public function enforceMutationThrottle(float $lastMutationAt): void {
    if ($lastMutationAt <= 0) {
      return;
    }

    $elapsed = microtime(TRUE) - $lastMutationAt;
    $remaining = self::MUTATION_MIN_INTERVAL_SECONDS - $elapsed;
    if ($remaining > 0) {
      $this->sleepSeconds($remaining);
    }
  }

  /**
   * Determine whether mutation cooldown is active.
   */
  public function isMutationCooldownActive(): bool {
    $state = $this->loadCooldownState();
    $cooldownUntil = (int) ($state['cooldown_until'] ?? 0);

    if ($cooldownUntil <= 0) {
      return FALSE;
    }

    if ($cooldownUntil <= time()) {
      $state['cooldown_until'] = 0;
      $state['failures'] = 0;
      $this->saveCooldownState($state);
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Remaining cooldown seconds.
   */
  public function getCooldownRemainingSeconds(): int {
    $state = $this->loadCooldownState();
    return max(0, (int) ($state['cooldown_until'] ?? 0) - time());
  }

  /**
   * Record a mutative rate-limit failure and activate cooldown if threshold met.
   */
  public function recordMutativeRateLimitFailure(string $method, string $url): void {
    $state = $this->loadCooldownState();
    $failures = (int) ($state['failures'] ?? 0) + 1;
    $state['failures'] = $failures;

    if ($failures >= self::COOLDOWN_FAILURE_THRESHOLD) {
      $state['cooldown_until'] = time() + self::COOLDOWN_SECONDS;
      $state['failures'] = 0;
      $this->logger->warning('GitHub mutation cooldown activated after repeated rate-limit failures (@method @url). Cooldown: @seconds second(s).', [
        '@method' => strtoupper($method),
        '@url' => $url,
        '@seconds' => self::COOLDOWN_SECONDS,
      ]);
    }

    $this->saveCooldownState($state);
  }

  /**
   * Clear mutative rate-limit failure counters after successful mutation.
   */
  public function resetMutativeRateLimitFailures(): void {
    $state = $this->loadCooldownState();
    $state['failures'] = 0;
    $state['cooldown_until'] = 0;
    $this->saveCooldownState($state);
  }

  /**
   * Build dedupe key for idempotent mutation patterns.
   */
  public function buildMutationDedupeKey(string $method, string $url, array $json): ?string {
    $normalizedMethod = strtoupper(trim($method));

    if ($normalizedMethod === 'PATCH' && str_contains($url, '/issues/')) {
      $state = strtolower(trim((string) ($json['state'] ?? '')));
      if ($state === 'closed') {
        return sha1($normalizedMethod . '|' . $url . '|state=closed');
      }
    }

    if ($normalizedMethod === 'POST' && str_contains($url, '/comments')) {
      $body = trim((string) ($json['body'] ?? ''));
      if ($body !== '') {
        return sha1($normalizedMethod . '|' . $url . '|body=' . $body);
      }
    }

    return NULL;
  }

  /**
   * Determine whether a mutation key is a recent duplicate.
   */
  public function isRecentMutationDuplicate(string $key): bool {
    $state = $this->loadMutationDedupeState();
    $lastApplied = (int) ($state[$key] ?? 0);
    if ($lastApplied <= 0) {
      return FALSE;
    }

    return (time() - $lastApplied) < self::MUTATION_DEDUPE_WINDOW_SECONDS;
  }

  /**
   * Persist successful mutation key.
   */
  public function rememberAppliedMutation(string $key): void {
    $state = $this->loadMutationDedupeState();
    $state[$key] = time();
    $this->saveMutationDedupeState($state);
  }

  /**
   * Load cooldown state from local storage.
   */
  private function loadCooldownState(): array {
    $path = $this->getTempStatePath(self::COOLDOWN_STATE_FILE);
    if (!is_file($path)) {
      return ['failures' => 0, 'cooldown_until' => 0];
    }

    $json = @file_get_contents($path);
    if (!is_string($json) || $json === '') {
      return ['failures' => 0, 'cooldown_until' => 0];
    }

    $decoded = json_decode($json, TRUE);
    if (!is_array($decoded)) {
      return ['failures' => 0, 'cooldown_until' => 0];
    }

    return [
      'failures' => max(0, (int) ($decoded['failures'] ?? 0)),
      'cooldown_until' => max(0, (int) ($decoded['cooldown_until'] ?? 0)),
    ];
  }

  /**
   * Persist cooldown state to local storage.
   */
  private function saveCooldownState(array $state): void {
    $path = $this->getTempStatePath(self::COOLDOWN_STATE_FILE);
    $payload = [
      'failures' => max(0, (int) ($state['failures'] ?? 0)),
      'cooldown_until' => max(0, (int) ($state['cooldown_until'] ?? 0)),
    ];

    @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_SLASHES));
  }

  /**
   * Load mutation dedupe state and prune expired keys.
   */
  private function loadMutationDedupeState(): array {
    $path = $this->getTempStatePath(self::MUTATION_DEDUPE_STATE_FILE);
    $now = time();
    $state = [];

    if (is_file($path)) {
      $json = @file_get_contents($path);
      if (is_string($json) && $json !== '') {
        $decoded = json_decode($json, TRUE);
        if (is_array($decoded)) {
          foreach ($decoded as $key => $timestamp) {
            $key = (string) $key;
            $timestamp = (int) $timestamp;
            if ($key === '' || $timestamp <= 0) {
              continue;
            }
            if (($now - $timestamp) < self::MUTATION_DEDUPE_WINDOW_SECONDS) {
              $state[$key] = $timestamp;
            }
          }
        }
      }
    }

    return $state;
  }

  /**
   * Save mutation dedupe state.
   */
  private function saveMutationDedupeState(array $state): void {
    $path = $this->getTempStatePath(self::MUTATION_DEDUPE_STATE_FILE);
    @file_put_contents($path, json_encode($state, JSON_UNESCAPED_SLASHES));
  }

  /**
   * Build a namespaced temp-state file path for this repository context.
   */
  private function getTempStatePath(string $baseFilename): string {
    return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . $this->getNamespacedStateFilename($baseFilename);
  }

  /**
   * Create a repository-scoped file name from a base state/lock file name.
   */
  private function getNamespacedStateFilename(string $baseFilename): string {
    $namespace = $this->contextResolver->getStateNamespace();
    $dotPos = strrpos($baseFilename, '.');

    if ($dotPos === FALSE) {
      return $baseFilename . '_' . $namespace;
    }

    $name = substr($baseFilename, 0, $dotPos);
    $ext = substr($baseFilename, $dotPos + 1);
    return $name . '_' . $namespace . '.' . $ext;
  }

  /**
   * Sleep for a floating-point number of seconds.
   */
  private function sleepSeconds(float $seconds): void {
    if ($seconds <= 0) {
      return;
    }

    usleep((int) round($seconds * 1000000));
  }

  /**
   * Read last mutation timestamp from lock file.
   *
   * @param resource $handle
   *   Lock file handle.
   */
  private function readLastMutationTimestamp($handle): float {
    rewind($handle);
    $raw = stream_get_contents($handle);
    if (!is_string($raw)) {
      return 0.0;
    }

    $raw = trim($raw);
    return is_numeric($raw) ? (float) $raw : 0.0;
  }

  /**
   * Persist last mutation timestamp to lock file.
   *
   * @param resource $handle
   *   Lock file handle.
   */
  private function writeLastMutationTimestamp($handle, float $timestamp): void {
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, sprintf('%.6F', $timestamp));
    fflush($handle);
  }

}
