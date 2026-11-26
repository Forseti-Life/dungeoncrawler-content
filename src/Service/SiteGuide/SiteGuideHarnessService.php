<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\SiteGuide;

use Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessInterface;
use Drupal\dungeoncrawler_content\Service\AgentHarness\AgentHarnessManagementService;

/**
 * Read-only site navigation and agent-handoff harness.
 */
final class SiteGuideHarnessService implements AgentHarnessInterface {

  public function __construct(
    private readonly SiteGuideService $siteGuide,
    private readonly AgentHarnessManagementService $harnessManagement,
  ) {}

  public function agentId(): string {
    return 'site_guide';
  }

  public function label(): string {
    return 'Site Guide Harness';
  }

  public function scope(): string {
    return self::SCOPE_SITE_SESSION;
  }

  public function defaultExecutionProfile(): string {
    return AgentHarnessManagementService::PROFILE_RESPONSE_SYNTHESIS;
  }

  public function listAgentTools(string $profile = 'default'): array {
    return [
      'site_guide.describe_context' => 'Describe permission-filtered personas, navigation, resources, and handoffs.',
      'site_guide.search_routes' => 'Find permission-filtered site routes.',
      'site_guide.search_docs' => 'Search curated architecture and onboarding resources.',
      'agents.list_capabilities' => 'List the specialized agents and their scopes.',
      'agents.request_handoff' => 'Create a scoped handoff receipt without granting mutation authority.',
      AgentHarnessManagementService::TOOL_SUBMIT_BACKLOG_SUGGESTION =>
        $this->harnessManagement->getBacklogSuggestionToolDescriptor(),
    ];
  }

  public function describeToolCall(string $tool_name, array $arguments = []): array {
    return $this->harnessManagement->describeToolCall($tool_name, $arguments);
  }

  public function answer(string $message, array $context = []): array {
    return $this->siteGuide->answer($message, $context);
  }

  public function describe(array $context = []): array {
    return $this->siteGuide->context($context);
  }

}
