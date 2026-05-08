<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Traits;

/**
 * Trait for JSON API request helpers.
 *
 * Provides standardized methods for making JSON requests in functional tests.
 * Centralizes JSON request logic used across campaign and entity tests.
 */
trait JsonRequestTrait {

  /**
   * Issue a JSON request with the given method and payload.
   *
   * @param string $method
   *   HTTP method (GET, POST, PUT, DELETE, etc.).
   * @param string $path
   *   Request path (e.g., '/api/campaign/123/state').
   * @param array|null $payload
   *   Optional array payload to send as JSON body.
   *
   * @return array
   *   Decoded JSON response as associative array.
   */
  protected function requestJson(string $method, string $path, ?array $payload = NULL): array {
    $body = $payload !== NULL ? json_encode($payload) : NULL;
    $this->getSession()->getDriver()->getClient()->request(
      $method,
      $this->buildUrl($path),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      $body
    );

    $content = $this->getSession()->getPage()->getContent();
    return json_decode($content, TRUE) ?? [];
  }

  /**
   * Issue a JSON request with raw body content.
   *
   * Useful for testing malformed JSON or specific raw payloads.
   *
   * @param string $method
   *   HTTP method (GET, POST, PUT, DELETE, etc.).
   * @param string $path
   *   Request path (e.g., '/api/campaign/123/state').
   * @param string $body
   *   Raw request body content.
   *
   * @return array
   *   Decoded JSON response as associative array.
   */
  protected function requestRaw(string $method, string $path, string $body): array {
    $this->getSession()->getDriver()->getClient()->request(
      $method,
      $this->buildUrl($path),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      $body
    );

    $content = $this->getSession()->getPage()->getContent();
    return json_decode($content, TRUE) ?? [];
  }

}
