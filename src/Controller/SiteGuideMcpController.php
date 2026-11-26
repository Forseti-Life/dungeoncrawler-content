<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\dungeoncrawler_content\Service\SiteGuide\SiteGuideMcpGatewayService;
use GuzzleHttp\Psr7\HttpFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MCP transport endpoint for Site Guide discovery and handoffs.
 */
final class SiteGuideMcpController extends ControllerBase implements ContainerInjectionInterface {

  public function __construct(private readonly SiteGuideMcpGatewayService $gateway) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('dungeoncrawler_content.site_guide_mcp'));
  }

  public function endpoint(Request $request): Response {
    $psr17 = new HttpFactory();
    $psr_request = (new PsrHttpFactory($psr17, $psr17, $psr17, $psr17))->createRequest($request);
    $response = $this->gateway->handle($psr_request, [
      'route' => $request->headers->get('referer', '/'),
    ]);
    return (new HttpFoundationFactory())->createResponse($response);
  }

}
