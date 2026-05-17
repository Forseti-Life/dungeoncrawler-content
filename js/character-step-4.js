/**
 * @file
 * Character Creation Step 4 - Class & Spell Selection
 *
 * Enforces server-provided cantrip and spell count limits with live
 * feedback counters and disabled-checkbox guardrails, matching the
 * pattern used in Step 6 for skill selection.
 *
 * @see CharacterCreationStepForm::buildStep4Fields()
 */

(function ($, Drupal, once) {
  'use strict';

  function activateTab($wrapper, tabId) {
    var $tabs = $wrapper.find('[data-step4-tab]');
    var $panels = $wrapper.find('[data-step4-tab-panel]');
    var $hidden = $wrapper.find('[data-step4-active-tab-input]');

    $tabs.each(function () {
      var $tab = $(this);
      var active = $tab.data('step4Tab') === tabId;
      $tab.toggleClass('is-active', active);
      $tab.attr('aria-selected', active ? 'true' : 'false');
    });

    $panels.each(function () {
      var $panel = $(this);
      var active = $panel.data('step4TabPanel') === tabId;
      $panel.toggleClass('is-active', active);
      $panel.prop('hidden', !active);
    });

    if ($hidden.length) {
      $hidden.val(tabId);
    }
  }

  function wireTabShell($wrapper) {
    var $tabs = $wrapper.find('[data-step4-tab]');
    var $panels = $wrapper.find('[data-step4-tab-panel]');
    if ($tabs.length < 2 || !$panels.length) {
      return;
    }

    var $hidden = $wrapper.find('[data-step4-active-tab-input]');
    var initialTab = $hidden.val() || '';
    var $errorPanel = $panels.filter(function () {
      return $(this).find('[aria-invalid="true"], .form-item--error-message, .messages--error, .error').length > 0;
    }).first();
    if ($errorPanel.length) {
      initialTab = $errorPanel.data('step4TabPanel');
    }
    if (!initialTab) {
      initialTab = $tabs.first().data('step4Tab');
    }

    $tabs.each(function () {
      var $tab = $(this);
      $tab.on('click.step4Tabs', function () {
        activateTab($wrapper, $tab.data('step4Tab'));
      });
    });

    activateTab($wrapper, initialTab);
  }

  /**
   * Wires up a checkbox-limit enforcer for a group of checkboxes.
   *
   * @param {jQuery} $container  The form or wrapper that holds the checkboxes.
   * @param {string} namePrefix  The name= prefix, e.g. 'cantrips['.
   * @param {number} limit       Maximum number of checked boxes allowed.
   * @param {string} label       Human label for the counter ("Cantrips", "1st-Level Spells").
   * @param {boolean} exact      If true, counter shows "exactly N"; if false, "up to N".
   */
  function enforceCheckboxLimit($container, namePrefix, limit, label, exact) {
    var $checkboxes = $container.find('input[type="checkbox"][name^="' + namePrefix + '"]');
    if (!$checkboxes.length || limit <= 0) {
      return;
    }

    // Inject a live counter above the checkboxes (inside the AJAX wrapper).
    var counterId = 'spell-counter-' + namePrefix.replace(/[\[\]]/g, '');
    var $existing = $container.find('#' + counterId);
    if ($existing.length) {
      $existing.remove();
    }
    var $counter = $('<div id="' + counterId + '" class="spell-counter" style="margin-bottom:12px;font-weight:bold;"></div>');
    $checkboxes.first().closest('.form-checkboxes, .form-item').before($counter);

    function update() {
      var checked = $checkboxes.filter(':checked').length;
      var remaining = limit - checked;

      if (remaining > 0) {
        $counter
          .text(label + ' selected: ' + checked + ' / ' + limit + '  (' + remaining + ' remaining)')
          .css('color', '#856404');
      }
      else if (remaining === 0) {
        $counter
          .text(label + ' selected: ' + checked + ' / ' + limit + '  \u2714')
          .css('color', '#28a745');
      }
      else {
        $counter
          .text(label + ' selected: ' + checked + ' / ' + limit + '  (too many!)')
          .css('color', '#dc3545');
      }

      // Disable unchecked boxes once the limit is reached.
      $checkboxes.each(function () {
        var $cb = $(this);
        var disableUnchecked = checked >= limit;
        if (!$cb.is(':checked')) {
          $cb.prop('disabled', disableUnchecked);
          $cb.closest('.form-item')
            .toggleClass('spell-disabled', disableUnchecked)
            .toggleClass('option-selector-card--disabled', disableUnchecked);
        } else {
          $cb.prop('disabled', false);
          $cb.closest('.form-item')
            .removeClass('spell-disabled')
            .removeClass('option-selector-card--disabled');
        }
      });
    }

    $checkboxes.off('change.spellLimit').on('change.spellLimit', update);
    update();
  }

  Drupal.behaviors.characterStep4 = {
    attach: function (context, settings) {
      // After AJAX, context IS the replaced wrapper element itself.
      // On full page load, context is document — search for the wrapper.
      var $ctx = $(context);
      var $wrapper = $ctx.is('#class-dynamic-wrapper')
        ? $ctx
        : $ctx.find('#class-dynamic-wrapper');
      if (!$wrapper.length) {
        return;
      }

      once('step4-tabs', $wrapper.get()).forEach(function (el) {
        wireTabShell($(el));
      });

      var cfg = (settings && settings.characterStep4) || {};
      var cantripLimit = cfg.cantripLimit || 0;
      var firstSpellLimit = cfg.firstSpellLimit || 0;

      if (cantripLimit > 0) {
        enforceCheckboxLimit($wrapper, 'cantrips[', cantripLimit, 'Cantrips', true);
      }
      if (firstSpellLimit > 0) {
        enforceCheckboxLimit($wrapper, 'spells_first[', firstSpellLimit, '1st-Level Spells', false);
      }

      // ── Submit loading state ─────────────────────────────────────────────
      once('step4-submit', 'form.character-creation-form', context).forEach(function (el) {
        var $form = $(el);
        var $submit = $form.find('[type="submit"]');
        $form.on('submit', function () {
          // Re-enable disabled checkboxes so their values submit.
          $form.find('.spell-disabled input[type="checkbox"]').prop('disabled', false);
          $submit.prop('disabled', true).text('Saving\u2026');
        });
      });
    },
  };

})(jQuery, Drupal, once);
