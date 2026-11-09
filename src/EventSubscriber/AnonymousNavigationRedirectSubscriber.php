<?php

namespace Drupal\dungeoncrawler_content\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Redirects unauthenticated visitors to Create Account for main nav routes.
 *
 * When an anonymous visitor clicks any navigation menu item in the main menu
 * that requires authentication or player permissions, this subscriber intercepts
 * the 403 Access Denied exception on HTML requests and redirects them to the
 * registration page (/user/register) with a destination query parameter.
 */
class AnonymousNavigationRedirectSubscriber extends HttpExceptionSubscriberBase {

  /**
   * Constructs an AnonymousNavigationRedirectSubscriber.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    protected AccountInterface $account,
    protected Connection $database,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats(): array {
    return ['html'];
  }

  /**
   * {@inheritdoc}
   */
  protected static function getPriority(): int {
    // Run before ExceptionLoggingSubscriber (50) and DefaultExceptionHtmlSubscriber (-128),
    // and alongside user AccessDeniedSubscriber (75).
    return 80;
  }

  /**
   * Redirects anonymous users on 403 for main navigation menu routes.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The exception event.
   */
  public function on403(ExceptionEvent $event): void {
    if ($this->account->isAuthenticated()) {
      return;
    }

    $request = $event->getRequest();
    if (!$request->isMethodSafe()) {
      return;
    }

    $route_name = (string) RouteMatch::createFromRequest($request)->getRouteName();
    if ($route_name === '' || $route_name === 'user.register' || $route_name === 'user.login') {
      return;
    }

    if (!$this->isNavigableMainMenuRoute($route_name)) {
      return;
    }

    $destination = $request->getRequestUri();
    $options = ['absolute' => TRUE];
    if ($destination !== '' && str_starts_with($destination, '/')) {
      $options['query'] = ['destination' => $destination];
    }

    $redirect_url = Url::fromRoute('user.register', [], $options)->toString();
    $response = new RedirectResponse($redirect_url, RedirectResponse::HTTP_FOUND);
    $response->headers->set('Cache-Control', 'no-cache, must-revalidate, private');
    $event->setResponse($response);
  }

  /**
   * Determines whether the route is a public/player main navigation route.
   *
   * @param string $route_name
   *   The route name being requested.
   *
   * @return bool
   *   TRUE if the route belongs to the non-admin main navigation menu.
   */
  protected function isNavigableMainMenuRoute(string $route_name): bool {
    if (in_array($route_name, self::playerNavigationRouteNames(), TRUE)) {
      return TRUE;
    }

    try {
      if (!$this->database->schema()->tableExists('menu_tree')) {
        return FALSE;
      }

      $rows = $this->database->select('menu_tree', 'mt')
        ->fields('mt', ['id', 'parent'])
        ->condition('menu_name', 'main')
        ->condition('enabled', 1)
        ->condition('route_name', $route_name)
        ->execute()
        ->fetchAll();

      foreach ($rows as $row) {
        $id = (string) ($row->id ?? '');
        $parent = (string) ($row->parent ?? '');
        if (
          !str_starts_with($id, 'dungeoncrawler_content.menu.dc_administration')
          && !str_starts_with($parent, 'dungeoncrawler_content.menu.dc_administration')
          && !str_starts_with($id, 'dungeoncrawler_tester.')
          && !str_starts_with($parent, 'dungeoncrawler_tester.')
        ) {
          return TRUE;
        }
      }
    }
    catch (\Throwable) {
      return FALSE;
    }

    return FALSE;
  }

  /**
   * Explicit list of player/authoring navigation routes in the main menu.
   *
   * @return string[]
   *   Route names.
   */
  public static function playerNavigationRouteNames(): array {
    return [
      'dungeoncrawler_content.characters_roster',
      'dungeoncrawler_content.campaigns',
      'dungeoncrawler_content.storyline_management',
      'dungeoncrawler_content.storyline_explorer',
      'dungeoncrawler_content.gm_storyline_builder',
      'dungeoncrawler_content.editor_suite',
      'dungeoncrawler_content.definition_index',
      'dungeoncrawler_content.definition_family',
      'dungeoncrawler_content.room_editor',
      'dungeoncrawler_content.dungeon_editor',
      'dungeoncrawler_content.module_manager',
      'dungeoncrawler_content.module_explorer',
      'dungeoncrawler_content.analysis_explorer_home',
      'dungeoncrawler_content.dungeon_analysis',
      'dungeoncrawler_content.analysis_explorer_rooms',
      'dungeoncrawler_content.analysis_explorer_items',
      'dungeoncrawler_content.analysis_explorer_spells',
      'dungeoncrawler_content.analysis_explorer_feats',
      'dungeoncrawler_content.analysis_explorer_actors',
    ];
  }

}
