<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Schema;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\EventSubscriber\AnonymousNavigationRedirectSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Route;

/**
 * Unit tests for AnonymousNavigationRedirectSubscriber.
 *
 * @coversDefaultClass \Drupal\dungeoncrawler_content\EventSubscriber\AnonymousNavigationRedirectSubscriber
 * @group dungeoncrawler_content
 */
class AnonymousNavigationRedirectSubscriberTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $url_generator = $this->createMock(UrlGeneratorInterface::class);
    $url_generator->method('generateFromRoute')
      ->willReturnCallback(static function (string $name, array $parameters = [], array $options = []): string {
        $base = 'https://dungeoncrawler.forseti.life/user/register';
        if (!empty($options['query']['destination'])) {
          return $base . '?destination=' . rawurlencode((string) $options['query']['destination']);
        }
        return $base;
      });

    $container = new ContainerBuilder();
    $container->set('url_generator', $url_generator);
    \Drupal::setContainer($container);
  }

  /**
   * Tests that anonymous GET requests to main nav routes redirect to register.
   */
  public function testAnonymousNavRouteRedirectsToRegister(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(FALSE);

    $database = $this->createMock(Connection::class);
    $subscriber = new AnonymousNavigationRedirectSubscriber($account, $database);

    foreach ([
      'dungeoncrawler_content.characters_roster' => '/characters',
      'dungeoncrawler_content.campaigns' => '/campaigns',
      'dungeoncrawler_content.storyline_management' => '/storylines',
      'dungeoncrawler_content.gm_storyline_builder' => '/game-master/storyline-builder',
      'dungeoncrawler_content.definition_index' => '/dungeoncrawler/canonical-library',
      'dungeoncrawler_content.room_editor' => '/dungeoncrawler/room-editor',
      'dungeoncrawler_content.dungeon_editor' => '/dungeoncrawler/dungeon-editor',
    ] as $route_name => $path) {
      $request = Request::create($path, 'GET');
      $request->setRequestFormat('html');
      $request->attributes->set(RouteObjectInterface::ROUTE_NAME, $route_name);
      $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, new Route($path));

      $event = new ExceptionEvent(
        $this->createMock(HttpKernelInterface::class),
        $request,
        HttpKernelInterface::MAIN_REQUEST,
        new AccessDeniedHttpException()
      );

      $subscriber->on403($event);
      $response = $event->getResponse();
      $this->assertInstanceOf(RedirectResponse::class, $response, $route_name . ' should redirect anonymous users.');
      $this->assertSame(302, $response->getStatusCode());
      $this->assertStringContainsString('/user/register?destination=', $response->getTargetUrl());
    }
  }

  /**
   * Tests that authenticated users and admin routes are not redirected.
   */
  public function testAuthenticatedAndAdminRoutesAreNotRedirected(): void {
    $auth_account = $this->createMock(AccountInterface::class);
    $auth_account->method('isAuthenticated')->willReturn(TRUE);

    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->willReturn(FALSE);
    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);

    $auth_subscriber = new AnonymousNavigationRedirectSubscriber($auth_account, $database);
    $request = Request::create('/campaigns', 'GET');
    $request->setRequestFormat('html');
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, 'dungeoncrawler_content.campaigns');
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, new Route('/campaigns'));

    $event = new ExceptionEvent(
      $this->createMock(HttpKernelInterface::class),
      $request,
      HttpKernelInterface::MAIN_REQUEST,
      new AccessDeniedHttpException()
    );
    $auth_subscriber->on403($event);
    $this->assertNull($event->getResponse(), 'Authenticated 403 must not redirect to /user/register.');

    $anon_account = $this->createMock(AccountInterface::class);
    $anon_account->method('isAuthenticated')->willReturn(FALSE);
    $anon_subscriber = new AnonymousNavigationRedirectSubscriber($anon_account, $database);

    $admin_request = Request::create('/admin/content/dungeoncrawler', 'GET');
    $admin_request->setRequestFormat('html');
    $admin_request->attributes->set(RouteObjectInterface::ROUTE_NAME, 'dungeoncrawler_content.dashboard');
    $admin_request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, new Route('/admin/content/dungeoncrawler'));

    $admin_event = new ExceptionEvent(
      $this->createMock(HttpKernelInterface::class),
      $admin_request,
      HttpKernelInterface::MAIN_REQUEST,
      new AccessDeniedHttpException()
    );
    $anon_subscriber->on403($admin_event);
    $this->assertNull($admin_event->getResponse(), 'Admin route 403 must remain 403 for anonymous users.');
  }

}
