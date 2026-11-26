<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\SiteGuide;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Site Guide knowledge and navigation for the DungeonCrawler product.
 */
final class DungeonCrawlerSiteGuideProvider implements SiteGuideProviderInterface {

  public function id(): string {
    return 'dungeoncrawler';
  }

  public function label(): string {
    return 'DungeonCrawler';
  }

  public function personas(AccountInterface $account, array $context): array {
    if ($account->isAnonymous()) {
      return [];
    }
    $personas = [];
    if ($account->hasPermission('access dungeoncrawler characters')) {
      $personas[] = 'player';
    }
    if ($account->hasPermission('edit canonical dungeoncrawler storylines')
      || $account->hasPermission('edit canonical dungeoncrawler dungeons')
      || $account->hasPermission('edit canonical dungeoncrawler rooms')
      || $account->hasPermission('edit canonical dungeoncrawler definitions')) {
      $personas[] = 'creator';
    }
    if (!empty($context['campaign_access']['is_member'])) {
      $personas[] = !empty($context['campaign_access']['can_use_gm_mode']) ? 'campaign_gm' : 'campaign_player';
    }
    if ($account->hasPermission('access dungeoncrawler editor suite')) {
      $personas[] = 'developer';
    }
    if ($account->hasPermission('administer dungeoncrawler content')) {
      $personas[] = 'administrator';
    }
    return array_values(array_unique($personas));
  }

  public function navigation(AccountInterface $account, array $context): array {
    $routes = [
      ['title' => 'Campaigns', 'route' => 'dungeoncrawler_content.campaigns'],
      ['title' => 'Character Setup', 'route' => 'dungeoncrawler_content.character_setup'],
      ['title' => 'Living Multiverse', 'route' => 'dungeoncrawler_content.world'],
      ['title' => 'Storyline Builder', 'route' => 'dungeoncrawler_content.gm_storyline_builder'],
      ['title' => 'Dungeon Editor', 'route' => 'dungeoncrawler_content.dungeon_editor'],
      ['title' => 'Room Editor', 'route' => 'dungeoncrawler_content.room_editor'],
      ['title' => 'Definition Library', 'route' => 'dungeoncrawler_content.definition_index'],
      ['title' => 'Architecture', 'route' => 'dungeoncrawler_content.architecture'],
    ];
    $items = [];
    foreach ($routes as $entry) {
      $url = Url::fromRoute($entry['route']);
      if ($url->access($account)) {
        $items[] = $entry + ['url' => $url->toString()];
      }
    }
    return $items;
  }

  public function resources(AccountInterface $account, array $context): array {
    $resources = [[
      'id' => 'dungeoncrawler.onboarding',
      'title' => 'DungeonCrawler getting started',
      'type' => 'public',
      'url' => '/',
    ]];
    $architecture = Url::fromRoute('dungeoncrawler_content.architecture');
    if ($architecture->access($account)) {
      $resources[] = [
        'id' => 'dungeoncrawler.architecture',
        'title' => 'DungeonCrawler architecture and agent scopes',
        'type' => 'architecture',
        'url' => $architecture->toString(),
      ];
    }
    return $resources;
  }

  public function handoffs(AccountInterface $account, array $context): array {
    $agents = [
      ['agent_id' => 'editor_gm', 'label' => 'Editor GM Harness', 'scope' => 'draft'],
      ['agent_id' => 'runtime_gm_actor', 'label' => 'Runtime GM Actor Harness', 'scope' => 'campaign_room'],
      ['agent_id' => 'player_automation_actor', 'label' => 'Player Automation Actor Harness', 'scope' => 'campaign_room'],
    ];
    foreach ($agents as &$agent) {
      $agent['available'] = match ($agent['agent_id']) {
          'editor_gm' => $account->hasPermission('edit canonical dungeoncrawler storylines')
            || $account->hasPermission('edit canonical dungeoncrawler dungeons')
            || $account->hasPermission('edit canonical dungeoncrawler rooms')
            || $account->hasPermission('edit canonical dungeoncrawler definitions'),
          'runtime_gm_actor' => $account->isAuthenticated() && !empty($context['campaign_access']['is_member']),
          'player_automation_actor' => $account->hasPermission('access dungeoncrawler characters')
            && !empty($context['campaign_access']['is_member']),
          default => FALSE,
        };
    }
    unset($agent);
    return $agents;
  }

}
