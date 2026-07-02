<?php

namespace Drupal\dungeoncrawler_tester\Service;

/**
 * Thin client contract for GitHub issue/PR interactions.
 */
interface GithubIssuePrClientInterface {

  /**
   * Resolve repository and token candidates from module/app/environment settings.
   */
  public function resolveContext(): array;

  /**
   * Fetch a single GitHub issue payload.
   */
  public function getIssue(string $repo, int $number, ?string $token = NULL): ?array;

  /**
   * Fetch a single GitHub pull request payload.
   */
  public function getPullRequest(string $repo, int $number, ?string $token = NULL): ?array;

  /**
   * List open GitHub issues for a single label.
   */
  public function listOpenIssuesByLabel(string $repo, string $label, ?string $token = NULL, int $perPage = 100): array;

  /**
   * List open GitHub pull requests.
   */
  public function listOpenPullRequests(string $repo, ?string $token = NULL, int $perPage = 100): array;

  /**
   * Create a GitHub issue and return payload when successful.
   */
  public function createIssue(string $repo, array $issueData, ?string $token = NULL): ?array;

  /**
   * Add one or more assignees to a GitHub issue.
   */
  public function addIssueAssignees(string $repo, int $issueNumber, array $assignees, ?string $token = NULL): ?array;

  /**
   * Return total count from GitHub issue search API.
   */
  public function searchIssuesTotalCount(string $query, ?string $token = NULL): int;

  /**
   * Execute a GitHub JSON read using a single token.
   */
  public function requestJson(string $url, ?string $token = NULL, array $extraHeaders = [], bool $paginate = FALSE): array;

  /**
   * Execute a GitHub JSON read with token failover.
   */
  public function requestJsonWithFallback(string $url, array $tokenCandidates, array $extraHeaders = [], bool $paginate = FALSE): array;

  /**
   * Execute a mutation request against the GitHub REST API.
   */
  public function mutate(string $method, string $url, array $json, ?string $token = NULL, int $timeout = 10): bool;

}
