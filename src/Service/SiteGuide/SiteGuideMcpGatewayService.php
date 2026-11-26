<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\SiteGuide;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Mcp\Server\Server;
use Mcp\Server\Transport\Http\Middleware\PassthroughMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Permission-filtered MCP endpoint for Site Guide discovery and handoffs.
 */
final class SiteGuideMcpGatewayService {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly SiteGuidePersonaResolver $personas,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Handles one MCP HTTP request.
   */
  public function handle(ServerRequestInterface $request, array $context = []): ResponseInterface {
    $guide_context = $this->context($context);
    $server = Server::builder()
      ->setServerInfo('DungeonCrawler Site Guide', '1.0.0')
      ->setLogger($this->loggerFactory->get('dungeoncrawler_content'))
      ->addTool(
        fn(): CallToolResult => $this->toolResult($guide_context),
        'site_guide.describe_context',
        'Describe Site Guide context',
        'Return permission-filtered personas, navigation, resources, and agent handoffs.',
        inputSchema: ['type' => 'object', 'additionalProperties' => FALSE, 'properties' => []],
      )
      ->addTool(
        fn(): CallToolResult => $this->toolResult([
          'navigation' => $guide_context['navigation'],
        ]),
        'site_guide.list_navigation',
        'List site navigation',
        'List routes the current user may navigate to.',
        inputSchema: ['type' => 'object', 'additionalProperties' => FALSE, 'properties' => []],
      )
      ->addTool(
        fn(): CallToolResult => $this->toolResult([
          'agents' => $guide_context['handoffs'],
        ]),
        'agents.list_capabilities',
        'List agent handoffs',
        'List specialized agents, scopes, and whether the current principal may request each handoff.',
        inputSchema: ['type' => 'object', 'additionalProperties' => FALSE, 'properties' => []],
      )
      ->addTool(
        fn(string $target_agent_id, string $purpose, ?string $scope_id = NULL): CallToolResult =>
          $this->toolResult($this->requestHandoff(
            $context,
            $target_agent_id,
            $purpose,
            $scope_id,
          )),
        'agents.request_handoff',
        'Request an agent handoff',
        'Create a scoped read-only handoff receipt. The target agent revalidates authority before any later execution.',
        inputSchema: [
          'type' => 'object',
          'additionalProperties' => FALSE,
          'required' => ['target_agent_id', 'purpose', 'scope_id'],
          'properties' => [
            'target_agent_id' => ['type' => 'string', 'minLength' => 1],
            'purpose' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 1000],
            'scope_id' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 150],
          ],
        ],
      )
      ->addResource(
        fn(): array => $guide_context,
        'site://dungeoncrawler/guide/context',
        'site_guide_context',
        'Site Guide context',
        'Permission-filtered personas, routes, resources, and handoffs.',
        'application/json',
      )
      ->build();

    $factory = new HttpFactory();
    return $server->run(new StreamableHttpTransport(
      $request,
      $factory,
      $factory,
      $this->loggerFactory->get('dungeoncrawler_content'),
      middleware: [new PassthroughMiddleware()],
    ));
  }

  private function context(array $context): array {
    return $this->personas->resolve($context);
  }

  public function requestHandoff(
    array $context,
    string $target_agent_id,
    string $purpose,
    ?string $scope_id,
  ): array {
    $scope_id = trim((string) $scope_id);
    $purpose = trim($purpose);
    if ($purpose === '' || $scope_id === '') {
      return $this->handoffResult($target_agent_id, FALSE, 'agent_handoff_scope_required');
    }
    if (in_array($target_agent_id, ['runtime_gm_actor', 'player_automation_actor'], TRUE)) {
      if (!ctype_digit($scope_id) || (int) $scope_id <= 0) {
        return $this->handoffResult($target_agent_id, FALSE, 'agent_handoff_campaign_scope_invalid');
      }
      $context['campaign_id'] = (int) $scope_id;
    }
    $guide = $this->personas->resolveHandoffs($context);
    foreach ($guide['handoffs'] as $handoff) {
      if (($handoff['agent_id'] ?? '') !== $target_agent_id) {
        continue;
      }
      if (($handoff['available'] ?? FALSE) !== TRUE) {
        return $this->handoffResult($target_agent_id, FALSE, 'agent_handoff_not_available_for_current_principal');
      }
      $receipt = hash('sha256', implode('|', [
        (string) $this->currentUser->id(),
        $target_agent_id,
        $scope_id,
        $purpose,
        bin2hex(random_bytes(16)),
      ]));
      $result = [
        'accepted' => TRUE,
        'receipt' => $receipt,
        'target_agent_id' => $target_agent_id,
        'scope' => (string) ($handoff['scope'] ?? ''),
        'scope_id' => $scope_id,
        'purpose' => $purpose,
        'mutation_authorized' => FALSE,
      ];
      $this->loggerFactory->get('dungeoncrawler_content')->info(
        'Site Guide handoff accepted for target @target in scope @scope; principal @uid; receipt @receipt; purpose hash @purpose_hash.',
        [
          '@target' => $target_agent_id,
          '@scope' => (string) ($handoff['scope'] ?? ''),
          '@uid' => (string) $this->currentUser->id(),
          '@receipt' => $receipt,
          '@purpose_hash' => hash('sha256', $purpose),
        ],
      );
      return $result;
    }
    return $this->handoffResult($target_agent_id, FALSE, 'agent_handoff_unknown');
  }

  private function handoffResult(string $target_agent_id, bool $accepted, string $reason): array {
    $this->loggerFactory->get('dungeoncrawler_content')->notice(
      'Site Guide handoff rejected for target @target; principal @uid; reason @reason.',
      [
        '@target' => $target_agent_id,
        '@uid' => (string) $this->currentUser->id(),
        '@reason' => $reason,
      ],
    );
    return [
      'accepted' => $accepted,
      'reason' => $reason,
      'target_agent_id' => $target_agent_id,
    ];
  }

  private function toolResult(array $result): CallToolResult {
    return new CallToolResult(
      [new TextContent(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))],
      structuredContent: $result,
    );
  }

}
