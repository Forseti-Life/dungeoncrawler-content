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

        // Collect all equipment checkboxes across the three categories.
        var $checkboxes = $form.find(
          'input[name^="weapons["], input[name^="armor["], input[name^="gear["]'
        );

        if (!$checkboxes.length) {
          return;
        }

        function recalcGold() {
          var spent = 0;
          $checkboxes.filter(':checked').each(function () {
            var id = $(this).val();
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

          // Toggle submit depending on budget.
          $submit.prop('disabled', overBudget);

          // Also sync the hidden equipment JSON field.
          var ids = [];
          $checkboxes.filter(':checked').each(function () {
            ids.push($(this).val());
          });
          var $hidden = $form.find('input[name="equipment"]');
          if ($hidden.length) {
            $hidden.val(JSON.stringify(ids));
          }
        }

        $checkboxes.on('change', recalcGold);

        // Initial calculation for pre-selected items.
        recalcGold();

        // Submit loading state.
        $form.on('submit', function () {
          $submit.prop('disabled', true).text('Saving...');
        });
      });
    },
  };

})(jQuery, Drupal, once);
