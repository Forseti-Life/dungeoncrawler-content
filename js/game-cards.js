/**
 * @file
 * JavaScript behaviors for game content cards.
 *
 * Provides interactive enhancements for item cards, including:
 * - Legendary item hover effects with enhanced glow
 * - Rarity-based visual feedback
 *
 * This file implements client-side UI enhancements for game content rendered
 * from the dungeoncrawler_content_registry table:
 *
 * - Database schema: dungeoncrawler_content_registry stores game content with
 *   rarity field (common, uncommon, rare, epic, legendary)
 * - Templates render rarity data into CSS classes (item-card--{rarity})
 * - This JS file enhances rendered DOM with dynamic hover effects
 * - CSS (game-cards.css) provides base rarity-specific styling
 *
 * Data flow:
 * 1. Game content stored in dungeoncrawler_content_registry.rarity (varchar)
 * 2. Template (item-card.html.twig) renders rarity as CSS class modifier
 * 3. CSS applies base styling per rarity tier
 * 4. JavaScript adds enhanced interactive effects for legendary items
 *
 * This approach follows the module's unified JSON/hot-column architecture
 * where frequently-accessed display properties are pre-rendered in templates
 * rather than queried via client-side JavaScript.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Attach game card behaviors.
   *
   * Adds enhanced visual effects to game content cards, particularly
   * legendary items which receive an intensified glow effect on hover.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches hover effects to legendary item cards.
   */
  Drupal.behaviors.dungeonCrawlerGameCards = {
    attach: function (context) {
      // Add enhanced rarity glow effect on hover for legendary items.
      // The .legendary-glow class is defined in css/game-cards.css
      // and provides an intensified golden glow effect.
      $(context).find('.item-card--legendary').once('dc-legendary').each(function () {
        $(this).on('mouseenter', function () {
          $(this).addClass('legendary-glow');
        }).on('mouseleave', function () {
          $(this).removeClass('legendary-glow');
        });
      });
    }
  };

})(jQuery, Drupal);
