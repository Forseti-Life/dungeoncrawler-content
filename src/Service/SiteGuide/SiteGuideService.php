<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\SiteGuide;

use Drupal\ai_conversation\Service\AIApiService;
use Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService;

/**
 * Read-only Site Guide response service.
 */
final class SiteGuideService {

  public function __construct(
    private readonly SiteGuidePersonaResolver $personas,
    private readonly AgentHarnessManagementService $harnessManagement,
    private readonly ?AIApiService $ai = NULL,
  ) {}

  public function context(array $context = []): array {
    return $this->personas->resolve($context);
  }

  public function answer(string $message, array $context = []): array {
    $message = trim($message);
    if ($message === '') {
      throw new \InvalidArgumentException('site_guide_message_required');
    }
    $grounding = $this->context($context);
    $fallback = $this->fallbackAnswer($message, $grounding);
    if ($this->ai === NULL) {
      return $fallback;
    }
    $system_prompt = <<<'PROMPT'
You are the DungeonCrawler Site Guide. Your job is to help the visitor understand
where to go and which specialized agent owns a task. You are read-only.

Use only the supplied navigation, resources, personas, and handoffs. Never claim
that a route, campaign, draft, permission, or mutation exists when it is not in
the context. Never call or imitate specialized GM/editor tools. For a task that
requires mutation, explain the owning agent and return a handoff card; do not
claim the task is complete.

Actor/player callers may ask for GM help, but must never receive GM privileged
tools. Prefer concise answers with direct links.

Return JSON only:
{"answer":"...", "links":[{"title":"...","url":"..."}],
 "quick_actions":["..."], "handoff":null|{"agent_id":"...","label":"...","purpose":"..."}}
PROMPT;
    $options = $this->harnessManagement->resolveExecutionOptions(
      AgentHarnessManagementService::PROFILE_RESPONSE_SYNTHESIS,
      [
        'system_prompt' => $system_prompt,
        'max_tokens' => 700,
        'json_object' => TRUE,
        'skip_cache' => TRUE,
      ],
    );
    $result = $this->ai->invokeModelDirect(
      "## Visitor message\n{$message}\n\n## Permission-filtered site context\n"
      . $this->harnessManagement->encodeCompactJson($grounding),
      'dungeoncrawler_content',
      'site_guide_chat',
      ['persona_count' => count($grounding['personas'] ?? [])],
      $options,
    );
    if (!is_array($result) || empty($result['success'])) {
      throw new \RuntimeException(sprintf(
        'site_guide_provider_request_failed: %s',
        is_array($result) ? (string) ($result['error'] ?? 'unknown') : get_debug_type($result),
      ));
    }
    $decoded = json_decode((string) ($result['response'] ?? ''), TRUE);
    if (!is_array($decoded) || trim((string) ($decoded['answer'] ?? '')) === '') {
      throw new \UnexpectedValueException(
        'site_guide_provider_response_invalid: expected an object with a non-empty answer',
      );
    }
    return [
      'answer' => trim((string) $decoded['answer']),
      'links' => $this->allowedLinks($decoded['links'] ?? [], $grounding),
      'quick_actions' => array_values(array_filter((array) ($decoded['quick_actions'] ?? []), 'is_string')),
      'handoff' => $this->allowedHandoff($decoded['handoff'] ?? NULL, $grounding),
      'personas' => $grounding['personas'],
      'mode' => 'ai',
    ];
  }

  private function fallbackAnswer(string $message, array $grounding): array {
    $terms = mb_strtolower($message);
    $links = [];
    foreach ((array) ($grounding['navigation'] ?? []) as $entry) {
      $title = mb_strtolower((string) ($entry['title'] ?? ''));
      if ($terms !== '' && (str_contains($terms, $title) || str_contains($title, $terms))) {
        $links[] = ['title' => $entry['title'], 'url' => $entry['url']];
      }
    }
    $answer = $links !== []
      ? 'I found a route that matches your question.'
      : 'Tell me whether you want to play, run a campaign, build content, or inspect the system. I will take you to the owning page or specialized agent.';
    return [
      'answer' => $answer,
      'links' => array_slice($links, 0, 6),
      'quick_actions' => [
        'How do I get started?',
        'Where are my campaigns?',
        'Which agent or editor should I use?',
        'Show me the system architecture.',
      ],
      'handoff' => NULL,
      'personas' => $grounding['personas'],
      'mode' => 'deterministic',
    ];
  }

  private function allowedLinks(mixed $links, array $grounding): array {
    $allowed = [];
    foreach ((array) ($grounding['navigation'] ?? []) as $entry) {
      $allowed[(string) ($entry['url'] ?? '')] = TRUE;
    }
    foreach ((array) ($grounding['resources'] ?? []) as $entry) {
      $allowed[(string) ($entry['url'] ?? '')] = TRUE;
    }
    return array_values(array_filter(
      (array) $links,
      static fn(mixed $link): bool => is_array($link)
        && isset($allowed[(string) ($link['url'] ?? '')]),
    ));
  }

  private function allowedHandoff(mixed $handoff, array $grounding): ?array {
    if (!is_array($handoff) || trim((string) ($handoff['agent_id'] ?? '')) === '') {
      return NULL;
    }
    foreach ((array) ($grounding['handoffs'] ?? []) as $allowed) {
      if (($allowed['agent_id'] ?? '') === ($handoff['agent_id'] ?? '') && ($allowed['available'] ?? FALSE) === TRUE) {
        return [
          'agent_id' => (string) $allowed['agent_id'],
          'label' => (string) ($allowed['label'] ?? $allowed['agent_id']),
          'purpose' => trim((string) ($handoff['purpose'] ?? '')),
        ];
      }
    }
    return NULL;
  }

}
