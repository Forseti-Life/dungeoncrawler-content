/**
 * @file
 * Character Creation Step 7 - Starting Equipment
 *
 * Enhances the server-rendered checkboxes with client-side gold budget
 * tracking. Equipment costs are read from drupalSettings.characterStep7
 * (passed by PHP) instead of regex-parsing label text.
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.characterStep7 = {
    attach: function (context, settings) {
      once('step7-init', 'form.character-creation-form', context).forEach(function (element) {
        var $form = $(element);
        var $submit = $form.find('[type="submit"]');
        var $goldDisplay = $form.find('.gold-display');

        var config = settings.characterStep7 || {};
        var BUDGET = config.budget || 15;
        var catalog = config.catalog || {};
        var presets = config.presets || {};
        var activePresetId = config.activePresetId || '';
        var isApplyingPreset = false;

        // Collect all equipment checkboxes across the three categories.
        var $checkboxes = $form.find(
          'input[name^="weapons["], input[name^="armor["], input[name^="gear["]'
        );
        var $presetButtons = $form.find('[data-step7-loadout-apply]');
        var $clearPresetButtons = $form.find('[data-step7-loadout-clear]');

        if (!$checkboxes.length) {
          return;
        }

        function getSelectedIds() {
          var ids = [];
          $checkboxes.filter(':checked').each(function () {
            ids.push($(this).val());
          });
          return ids.sort();
        }

        function presetMatches(presetId) {
          var preset = presets[presetId] || {};
          var presetIds = Array.isArray(preset.ids) ? preset.ids.slice().sort() : [];
          var selectedIds = getSelectedIds();
          if (presetIds.length !== selectedIds.length) {
            return false;
          }

          for (var index = 0; index < presetIds.length; index += 1) {
            if (presetIds[index] !== selectedIds[index]) {
              return false;
            }
          }

          return true;
        }

        function updatePresetUi() {
          Object.keys(presets).forEach(function (presetId) {
            var isActive = presetMatches(presetId);
            var label = presets[presetId].label || 'Class';
            var $card = $form.find('[data-step7-preset-card="' + presetId + '"]');
            var $status = $form.find('[data-step7-preset-status="' + presetId + '"]');

            $card.toggleClass('step7-loadout-preset--active', isActive);
            $card.attr('data-step7-preset-active', isActive ? '1' : '0');

            if ($status.length) {
              $status.text(isActive
                ? label + ' loadout applied. You can still fine-tune items below.'
                : label + ' loadout ready to apply.');
            }

            if (isActive) {
              activePresetId = presetId;
            } else if (activePresetId === presetId) {
              activePresetId = '';
            }
          });
        }

        function applyPreset(presetId) {
          var preset = presets[presetId] || {};
          var ids = Array.isArray(preset.ids) ? preset.ids : [];
          if (!ids.length) {
            return;
          }

          var selected = {};
          var changedInputs = [];
          ids.forEach(function (id) {
            selected[id] = true;
          });

          isApplyingPreset = true;
          $checkboxes.each(function () {
            var shouldBeChecked = !!selected[$(this).val()];
            if (this.checked !== shouldBeChecked) {
              this.checked = shouldBeChecked;
              changedInputs.push(this);
            }
          });
          isApplyingPreset = false;

          changedInputs.forEach(function (input) {
            input.dispatchEvent(new Event('change', { bubbles: true }));
          });

          recalcGold();
          updatePresetUi();
        }

        function clearPreset() {
          var changedInputs = [];
          isApplyingPreset = true;
          $checkboxes.each(function () {
            if (this.checked) {
              this.checked = false;
              changedInputs.push(this);
            }
          });
          isApplyingPreset = false;

          changedInputs.forEach(function (input) {
            input.dispatchEvent(new Event('change', { bubbles: true }));
          });

          recalcGold();
          updatePresetUi();
        }

        function recalcGold() {
          var spent = 0;
          var ids = getSelectedIds();
          ids.forEach(function (id) {
            if (catalog[id]) {
              spent += catalog[id].cost;
            }
          });
          spent = Math.round(spent * 100) / 100;
          var remaining = Math.round((BUDGET - spent) * 100) / 100;
          var overBudget = remaining < 0;

          // Update the server-rendered gold display if it exists.
          if ($goldDisplay.length) {
            $goldDisplay.find('strong').first().text(spent.toFixed(1) + ' gp');
            var $rem = $goldDisplay.find('strong').last();
            $rem.text(remaining.toFixed(1) + ' gp');
            $rem.css('color', overBudget ? '#dc3545' : '#28a745');
          }

          // Disable unchecked items when over budget to prevent further adds.
          if (overBudget) {
            $checkboxes.not(':checked').prop('disabled', true);
          } else {
            $checkboxes.prop('disabled', false);
          }

          $checkboxes.each(function () {
            $(this).closest('.form-item').toggleClass('option-selector-card--disabled', this.disabled);
          });

          // Toggle submit depending on budget.
          $submit.prop('disabled', overBudget);

          // Also sync the hidden equipment JSON field.
          var $hidden = $form.find('input[name="equipment"]');
          if ($hidden.length) {
            $hidden.val(JSON.stringify(ids));
          }
        }

        $checkboxes.on('change', function () {
          if (!isApplyingPreset) {
            recalcGold();
            updatePresetUi();
          }
        });
        $presetButtons.on('click', function (event) {
          event.preventDefault();
          applyPreset(this.getAttribute('data-step7-loadout-apply'));
        });
        $clearPresetButtons.on('click', function (event) {
          event.preventDefault();
          clearPreset();
        });

        // Initial calculation for pre-selected items.
        recalcGold();
        updatePresetUi();

        // Submit loading state.
        $form.on('submit', function () {
          $submit.prop('disabled', true).text('Saving...');
        });
      });
    },
  };

})(jQuery, Drupal, once);
