/**
 * @file
 * JavaScript behaviors for character sheet and character list pages.
 *
 * This file implements client-side UI enhancements for character data rendered
 * from the hybrid columnar storage system:
 *
 * - Hot columns (hp_current, hp_max, armor_class, experience_points) provide
 *   fast database access for high-frequency gameplay mechanics
 * - JSON schema (character.schema.json) stores complete character data in
 *   character_data column for flexibility
 * - CharacterManager service handles mapping between JSON schema fields and
 *   hot columns (see extractHotColumnsFromData and resolveHotColumnsForRecord)
 * - Templates render hot column values into DOM elements
 * - This JS file enhances the rendered DOM with dynamic styling
 *
 * Key behaviors:
 * - HP colorization based on current/max ratio (green/orange/red)
 * - JSON data section toggle for debugging/inspection
 */

(function (Drupal, once) {
  'use strict';

  // Guard: this file may be loaded outside Drupal (e.g. iframe embed).
  if (typeof Drupal === 'undefined' || typeof Drupal.behaviors === 'undefined') {
    return;
  }

  /**
   * Gets CSS custom property value from the document root.
   *
   * @param {string} propertyName
   *   The CSS custom property name (e.g., '--dc-danger').
   *
   * @return {string}
   *   The computed CSS property value.
   */
  function getCssVariable(propertyName) {
    return getComputedStyle(document.documentElement)
      .getPropertyValue(propertyName)
      .trim();
  }

  /**
   * Calculates and returns the appropriate HP status color based on ratio.
   *
   * @param {number} current
   *   The current HP value.
   * @param {number} max
   *   The maximum HP value.
   *
   * @return {string}
   *   The color hex value or CSS variable value for the HP status.
   */
  function getHpStatusColor(current, max) {
    // Calculate HP ratio (default to full health if max is 0)
    var ratio = max > 0 ? current / max : 1;

    // Return appropriate color based on HP threshold
    if (ratio <= 0.25) {
      return getCssVariable('--dc-danger');  // Critical (≤25%)
    }
    else if (ratio <= 0.5) {
      return getCssVariable('--dc-warning'); // Warning (26-50%)
    }
    else {
      return getCssVariable('--dc-success'); // Healthy (>50%)
    }
  }

  /**
   * Toggle raw JSON display on character sheet.
   *
   * Attaches click handlers to toggle buttons that show/hide JSON data sections.
   */
  Drupal.behaviors.dcCharacterSheetJson = {
    attach: function (context) {
      once('dc-json-toggle', '.dc-sheet__json-toggle', context).forEach(function (toggle) {
        toggle.addEventListener('click', function () {
          var targetId = toggle.getAttribute('data-dc-toggle');
          if (!targetId) {
            return; // Exit early if no target specified
          }

          var target = document.getElementById(targetId);
          if (target) {
            var isHidden = target.style.display === 'none' || !target.style.display;
            target.style.display = isHidden ? 'block' : 'none';
            toggle.classList.toggle('dc-sheet__json-toggle--open', isHidden);
          }
        });
      });
    }
  };

  /**
   * Colorize HP display on character sheet based on current/max ratio.
   *
   * Applies dynamic color styling to HP values to indicate health status:
   * - Green (>50%): Healthy
   * - Orange (26-50%): Warning
   * - Red (≤25%): Critical
   */
  Drupal.behaviors.dcCharacterHp = {
    attach: function (context) {
      once('dc-hp-color', '.dc-sheet__hp-current', context).forEach(function (hpElement) {
        var currentHp = parseInt(hpElement.textContent, 10);
        
        // Exit early if current HP is not a valid number
        if (isNaN(currentHp)) {
          return;
        }

        var maxHpElement = hpElement.parentElement.querySelector('.dc-sheet__hp-max');
        if (maxHpElement) {
          var maxHp = parseInt(maxHpElement.textContent, 10);
          
          // Apply color based on HP ratio
          if (!isNaN(maxHp)) {
            hpElement.style.color = getHpStatusColor(currentHp, maxHp);
          }
        }
      });
    }
  };

  /**
   * Colorize HP display on character list cards.
   *
   * Parses HP values from "current/max" format and applies appropriate
   * status color based on the HP ratio.
   */
  Drupal.behaviors.dcCharacterListHp = {
    attach: function (context) {
      once('dc-list-hp', '.dc-character-card__hp', context).forEach(function (hpElement) {
        var hpText = hpElement.textContent;
        var parts = hpText.split('/');
        
        // Validate format is "current/max"
        if (parts.length === 2) {
          var currentHp = parseInt(parts[0], 10);
          var maxHp = parseInt(parts[1], 10);
          
          // Apply color if both values are valid numbers
          if (!isNaN(currentHp) && !isNaN(maxHp)) {
            hpElement.style.color = getHpStatusColor(currentHp, maxHp);
          }
        }
      });
    }
  };

})(typeof Drupal !== 'undefined' ? Drupal : undefined, typeof once !== 'undefined' ? once : undefined);
