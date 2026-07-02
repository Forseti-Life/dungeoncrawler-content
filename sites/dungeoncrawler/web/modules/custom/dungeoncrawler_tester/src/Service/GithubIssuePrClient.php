<?php

namespace Drupal\dungeoncrawler_tester\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Thin GitHub API client for issue/PR operations.
 */
class GithubIssuePrClient implements GithubIssuePrClientInterface {

  /**
   * Maximum retry attempts for rate-limited requests.
   */
  private const RETRY_MAX_ATTEMPTS = 5;

  /**
   * Base delay used for exponential backoff.
   */
  private const RETRY_BASE_DELAY_SECONDS = 1.0;

  /**
   * Maximum delay cap used for backoff calculations.
   */
  private const RETRY_MAX_DELAY_SECONDS = 30.0;


  /**
   * Logger channel.
   */
  private LoggerChannelInterface $logger;

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly GithubClientContextResolver $contextResolver,
    private readonly GithubMutationStateStore $mutationStateStore,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('dungeoncrawler_tester');
  }

  /**
   * {@inheritdoc}
   */
  public function resolveContext(): array {
    return $this->contextResolver->resolveContext();
  }

  /**
   * {@inheritdoc}
   */
  public function getIssue(string $repo, int $number, ?string $token = NULL): ?array {
    if ($number <= 0) {
      return NULL;
    }

    $url = "https://api.github.com/repos/{$repo}/issues/{$number}";
    return $this->requestJsonObject($url, $token, 8);
  }

  /**
   * {@inheritdoc}
   */
  public function getPullRequest(string $repo, int $number, ?string $token = NULL): ?array {
    if ($number <= 0) {
      return NULL;
    }

    $url = "https://api.github.com/repos/{$repo}/pulls/{$number}";
    return $this->requestJsonObject($url, $token, 8);
  }

  /**
   * {@inheritdoc}
   */
  public function listOpenIssuesByLabel(string $repo, string $label, ?string $token = NULL, int $perPage = 100): array {
    $encodedLabel = rawurlencode($label);
    $url = "https://api.github.com/repos/{$repo}/issues?state=open&labels={$encodedLabel}&per_page={$perPage}";
    return $this->requestJsonList($url, $token, 10);
  }

  /**
   * {@inheritdoc}
   */
  public function listOpenPullRequests(string $repo, ?string $token = NULL, int $perPage = 100): array {
    $url = "https://api.github.com/repos/{$repo}/pulls?state=open&per_page={$perPage}";
    return $this->requestJsonList($url, $token, 10);
  }

  /**
   * {@inheritdoc}
   */
  public function createIssue(string $repo, array $issueData, ?string $token = NULL): ?array {
    $url = "https://api.github.com/repos/{$repo}/issues";
    return $this->requestMutationPayload('POST', $url, $issueData, $token, 10);
  }

  /**
   * {@inheritdoc}
   */
  public function addIssueAssignees(string $repo, int $issueNumber, array $assignees, ?string $token = NULL): ?array {
    if ($issueNumber <= 0 || empty($assignees)) {
      return NULL;
    }

    $url = "https://api.github.com/repos/{$repo}/issues/{$issueNumber}/assignees";
    return $this->requestMutationPayload('POST', $url, ['assignees' => array_values($assignees)], $token, 10);
  }

  /**
   * {@inheritdoc}
   */
  public function searchIssuesTotalCount(string $query, ?string $token = NULL): int {
    $encoded = rawurlencode($query);
    $url = "https://api.github.com/search/issues?q={$encoded}&per_page=1";
    $payload = $this->requestJsonObject($url, $token, 10);
    return (int) (($payload['total_count'] ?? 0));
  }

  /**
   * {@inheritdoc}
   */
  public function requestJson(string $url, ?string $token = NULL, array $extraHeaders = [], bool $paginate = FALSE): array {
    $resolvedToken = $this->contextResolver->resolveToken($token);
    if (!$resolvedToken) {
      return ['items' => [], 'error' => 'No GitHub token configured.'];
    }

    $headers = $this->buildHeaders($resolvedToken);
    foreach ($extraHeaders as $name => $value) {
      $headers[(string) $name] = (string) $value;
    }

    return $this->requestJsonInternal($url, $headers, $paginate);
  }

  /**
   * {@inheritdoc}
   */
  public function requestJsonWithFallback(string $url, array $tokenCandidates, array $extraHeaders = [], bool $paginate = FALSE): array {
    if (empty($tokenCandidates)) {
      return ['items' => [], 'error' => 'No GitHub token configured.'];
    }

    $lastError = 'GitHub request failed.';
    foreach ($tokenCandidates as $tokenCandidate) {
      $tokenCandidate = trim((string) $tokenCandidate);
      if ($tokenCandidate === '') {
        continue;
      }

      $headers = $this->buildHeaders($tokenCandidate);
      foreach ($extraHeaders as $name => $value) {
        $headers[(string) $name] = (string) $value;
      }

      $response = $this->requestJsonInternal($url, $headers, $paginate);
      if (empty($response['error'])) {
        return $response;
      }

      $lastError = (string) ($response['error'] ?? $lastError);
      if (stripos($lastError, 'rate limit') === FALSE) {
        continue;
      }
    }

    return ['items' => [], 'error' => $lastError];
  }

  /**
   * {@inheritdoc}
   */
  public function mutate(string $method, string $url, array $json, ?string $token = NULL, int $timeout = 10): bool {
    $resolvedToken = $this->contextResolver->resolveToken($token);
    if (!$resolvedToken) {
      return FALSE;
    }

    $dedupeKey = $this->mutationStateStore->buildMutationDedupeKey($method, $url, $json);
    if ($dedupeKey !== NULL && $this->mutationStateStore->isRecentMutationDuplicate($dedupeKey)) {
      $this->logger->notice('Skipping duplicate GitHub mutation (@method @url) within dedupe window.', [
        '@method' => strtoupper($method),
        '@url' => $url,
      ]);
      return TRUE;
    }

    $response = $this->sendRequestWithRateLimitHandling($method, $url, [
      'headers' => $this->buildHeaders($resolvedToken),
      'json' => $json,
      'timeout' => $timeout,
    ], TRUE);

    if (!$response) {
      return FALSE;
    }

    $status = $response->getStatusCode();
    $success = $status >= 200 && $status < 300;
    if ($success && $dedupeKey !== NULL) {
      $this->mutationStateStore->rememberAppliedMutation($dedupeKey);
    }

    return $success;
  }

  /**
   * Execute a JSON GET request.
   */
  private function requestJsonObject(string $url, ?string $token = NULL, int $timeout = 8): ?array {
    $resolvedToken = $this->contextResolver->resolveToken($token);
    if (!$resolvedToken) {
      return NULL;
    }

    $response = $this->sendRequestWithRateLimitHandling('GET', $url, [
      'headers' => $this->buildHeaders($resolvedToken),
      'timeout' => $timeout,
    ], FALSE);

    if (!$response) {
      return NULL;
    }

    $status = $response->getStatusCode();
    if ($status < 200 || $status >= 300) {
      return NULL;
    }

    $payload = json_decode((string) $response->getBody(), TRUE);
    return is_array($payload) ? $payload : NULL;
  }

  /**
   * Execute a JSON-list GET request.
   */
  private function requestJsonList(string $url, ?string $token = NULL, int $timeout = 10): array {
    $payload = $this->requestJsonObject($url, $token, $timeout);
    if (!is_array($payload) || !array_is_list($payload)) {
      return [];
    }

    return $payload;
  }

  /**
   * Execute a mutation and return decoded payload for successful responses.
   */
  private function requestMutationPayload(string $method, string $url, array $json, ?string $token = NULL, int $timeout = 10): ?array {
    $resolvedToken = $this->contextResolver->resolveToken($token);
    if (!$resolvedToken) {
      return NULL;
    }

    $response = $this->sendRequestWithRateLimitHandling($method, $url, [
      'headers' => $this->buildHeaders($resolvedToken),
      'json' => $json,
      'timeout' => $timeout,
    ], TRUE);

    if (!$response) {
      return NULL;
    }

    $status = $response->getStatusCode();
    if ($status < 200 || $status >= 300) {
      return NULL;
    }

    $payload = json_decode((string) $response->getBody(), TRUE);
    return is_array($payload) ? $payload : [];
  }

  /**
   * Execute GitHub JSON request with optional pagination.
   */
  private function requestJsonInternal(string $url, array $headers, bool $paginate = FALSE): array {
    $items = [];
    $nextUrl = $url;
    $pages = 0;

    while ($nextUrl !== '' && $nextUrl !== NULL) {
      $response = $this->sendRequestWithRateLimitHandling('GET', $nextUrl, [
        'headers' => $headers,
        'timeout' => 10,
      ], FALSE);

      if (!$response) {
        return [
          'items' => [],
          'error' => 'GitHub request failed.',
        ];
      }

      $status = $response->getStatusCode();
      if ($status < 200 || $status >= 300) {
        return [
          'items' => [],
          'error' => 'GitHub API status: ' . $status,
        ];
      }

      $payload = json_decode((string) $response->getBody(), TRUE);
      if (is_array($payload) && array_is_list($payload)) {
        $items = array_merge($items, $payload);
      }
      else {
        return [
          'items' => is_array($payload) ? $payload : [],
          'error' => NULL,
        ];
      }

      $pages++;
      if (!$paginate || $pages >= 20) {
        break;
      }

      $nextUrl = $this->extractNextLink((string) $response->getHeaderLine('Link'));
    }

    return [
      'items' => $items,
      'error' => NULL,
    ];
  }

  /**
   * Send request with rate-limit handling and retries.
   */
  private function sendRequestWithRateLimitHandling(string $method, string $url, array $options, bool $isMutation): ?ResponseInterface {
    if ($isMutation) {
      return $this->mutationStateStore->withMutationLock(function (float &$lastMutationAt) use ($method, $url, $options): ?ResponseInterface {
        if ($this->mutationStateStore->isMutationCooldownActive()) {
          $remaining = $this->mutationStateStore->getCooldownRemainingSeconds();
          $this->logger->warning('GitHub mutative request skipped due to cooldown (@method @url). Remaining cooldown: @seconds second(s).', [
            '@method' => strtoupper($method),
            '@url' => $url,
            '@seconds' => $remaining,
          ]);
          return NULL;
        }

        return $this->sendWithRetry($method, $url, $options, TRUE, $lastMutationAt);
      });
    }

    $lastMutationAt = 0.0;
    return $this->sendWithRetry($method, $url, $options, FALSE, $lastMutationAt);
  }

  /**
   * Execute request with retries and optional mutative throttling.
   */
  private function sendWithRetry(string $method, string $url, array $options, bool $isMutation, float &$lastMutationAt): ?ResponseInterface {
    $sawRateLimit = FALSE;

    for ($attempt = 0; $attempt < self::RETRY_MAX_ATTEMPTS; $attempt++) {
      if ($isMutation) {
        $this->mutationStateStore->enforceMutationThrottle($lastMutationAt);
      }

      try {
        $requestOptions = $options;
        $requestOptions['http_errors'] = FALSE;

        $response = $this->httpClient->request($method, $url, $requestOptions);
        if ($isMutation) {
          $lastMutationAt = microtime(TRUE);
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        if ($this->shouldRetryRateLimitResponse($status, $response, $body) && $attempt < self::RETRY_MAX_ATTEMPTS - 1) {
          $sawRateLimit = TRUE;
          $delay = $this->computeRetryDelaySeconds($attempt, $response);
          $this->logger->warning('GitHub rate limit encountered (@method @url, status @status). Retrying in @delay seconds (attempt @attempt/@max).', [
            '@method' => strtoupper($method),
            '@url' => $url,
            '@status' => $status,
            '@delay' => number_format($delay, 2),
            '@attempt' => $attempt + 1,
            '@max' => self::RETRY_MAX_ATTEMPTS,
          ]);
          $this->sleepSeconds($delay);
          continue;
        }

        if ($this->shouldRetryRateLimitResponse($status, $response, $body) && $attempt >= self::RETRY_MAX_ATTEMPTS - 1) {
          $sawRateLimit = TRUE;
          if ($isMutation) {
            $this->mutationStateStore->recordMutativeRateLimitFailure($method, $url);
          }
          return $response;
        }

        if ($isMutation && $status >= 200 && $status < 300) {
          $this->mutationStateStore->resetMutativeRateLimitFailures();
        }

        return $response;
      }
      catch (GuzzleException $e) {
        if ($attempt >= self::RETRY_MAX_ATTEMPTS - 1) {
          $this->logger->warning('GitHub request failed after retries (@method @url): @message', [
            '@method' => strtoupper($method),
            '@url' => $url,
            '@message' => $e->getMessage(),
          ]);
          return NULL;
        }

        $delay = $this->computeFallbackDelaySeconds($attempt);
        $this->logger->warning('GitHub request exception (@method @url): @message. Retrying in @delay seconds (attempt @attempt/@max).', [
          '@method' => strtoupper($method),
          '@url' => $url,
          '@message' => $e->getMessage(),
          '@delay' => number_format($delay, 2),
          '@attempt' => $attempt + 1,
          '@max' => self::RETRY_MAX_ATTEMPTS,
        ]);
        $this->sleepSeconds($delay);
      }
      catch (\Throwable $e) {
        if ($attempt >= self::RETRY_MAX_ATTEMPTS - 1) {
          $this->logger->warning('Unexpected GitHub request error (@method @url): @message', [
            '@method' => strtoupper($method),
            '@url' => $url,
            '@message' => $e->getMessage(),
          ]);
          return NULL;
        }

        $delay = $this->computeFallbackDelaySeconds($attempt);
        $this->sleepSeconds($delay);
      }
    }

    if ($isMutation && $sawRateLimit) {
      $this->mutationStateStore->recordMutativeRateLimitFailure($method, $url);
    }

    return NULL;
  }

  /**
   * Determine whether a response should trigger rate-limit retry handling.
   */
  private function shouldRetryRateLimitResponse(int $status, ResponseInterface $response, string $body): bool {
    if ($status === 429) {
      return TRUE;
    }

    if ($status !== 403) {
      return FALSE;
    }

    $retryAfter = trim((string) $response->getHeaderLine('Retry-After'));
    if ($retryAfter !== '') {
      return TRUE;
    }

    $remaining = trim((string) $response->getHeaderLine('X-RateLimit-Remaining'));
    if ($remaining === '0') {
      return TRUE;
    }

    $bodyLower = strtolower($body);
    return str_contains($bodyLower, 'rate limit') || str_contains($bodyLower, 'secondary rate limit');
  }

  /**
   * Compute retry delay honoring Retry-After and rate-limit reset headers.
   */
  private function computeRetryDelaySeconds(int $attempt, ResponseInterface $response): float {
    $retryAfter = trim((string) $response->getHeaderLine('Retry-After'));
    if ($retryAfter !== '') {
      if (is_numeric($retryAfter)) {
        return min(self::RETRY_MAX_DELAY_SECONDS, max(1.0, (float) $retryAfter));
      }

      $retryAfterTs = strtotime($retryAfter);
      if (is_int($retryAfterTs)) {
        return min(self::RETRY_MAX_DELAY_SECONDS, max(1.0, (float) ($retryAfterTs - time())));
      }
    }

    $resetHeader = trim((string) $response->getHeaderLine('X-RateLimit-Reset'));
    if ($resetHeader !== '' && is_numeric($resetHeader)) {
      $untilReset = (float) $resetHeader - time();
      if ($untilReset > 0) {
        return min(self::RETRY_MAX_DELAY_SECONDS, max(1.0, $untilReset));
      }
    }

    return $this->computeFallbackDelaySeconds($attempt);
  }

  /**
   * Compute exponential backoff delay with jitter.
   */
  private function computeFallbackDelaySeconds(int $attempt): float {
    $baseDelay = self::RETRY_BASE_DELAY_SECONDS * (2 ** $attempt);
    $jitter = mt_rand(0, 1000) / 1000;
    return min(self::RETRY_MAX_DELAY_SECONDS, $baseDelay + $jitter);
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
   * Extract next-page URL from GitHub Link header.
   */
  private function extractNextLink(string $linkHeader): ?string {
    if ($linkHeader === '') {
      return NULL;
    }

    foreach (explode(',', $linkHeader) as $part) {
      if (stripos($part, 'rel="next"') === FALSE) {
        continue;
      }

      if (preg_match('/<([^>]+)>/', $part, $matches) === 1) {
        return (string) ($matches[1] ?? NULL);
      }
    }

    return NULL;
  }

  /**
   * Build GitHub API headers.
   */
  private function buildHeaders(string $token): array {
    return [
      'Authorization' => "Bearer {$token}",
      'Accept' => 'application/vnd.github+json',
      'User-Agent' => 'dungeoncrawler-tester-github-client',
    ];
  }

}
