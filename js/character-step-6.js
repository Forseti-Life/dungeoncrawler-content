/**
 * @file
 * Character Creation Step 6 - Skills, Alignment & Details
 *
 * Enforces the server-provided skill count limit with live feedback.
 * Alignment card sync (if .alignments-grid exists) and submit loading state.
 *
 * @see CharacterCreationStepForm::buildStep6Fields()
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.characterStep6 = {
    attach: function (context, settings) {
      once('step6-init', 'form.character-creation-form', context).forEach(function (element) {
        var $form = $(element);
        var $submit = $form.find('[type="submit"]');

        // ── Skill count enforcement ──────────────────────────────────────────

        var requiredSkills = (settings.characterStep6 && settings.characterStep6.requiredSkills) || 0;
        var $skillCheckboxes = $form.find('input[name^="trained_skills["]');

        if (requiredSkills > 0 && $skillCheckboxes.length) {
          // Inject a live counter above the checkboxes.
          var $counter = $('<div class="skill-counter" style="margin-bottom:12px;font-weight:bold;"></div>');
          $skillCheckboxes.first().closest('.form-checkboxes, .form-item').before($counter);

          function updateSkillCount() {
            var checked = $skillCheckboxes.filter(':checked').length;
            var remaining = requiredSkills - checked;

            if (remaining > 0) {
              $counter.text('Skills selected: ' + checked + ' / ' + requiredSkills + '  (' + remaining + ' remaining)')
                .css('color', '#856404');
            }
            else if (remaining === 0) {
              $counter.text('Skills selected: ' + checked + ' / ' + requiredSkills + '  \u2714')
                .css('color', '#28a745');
            }
            else {
              $counter.text('Skills selected: ' + checked + ' / ' + requiredSkills + '  (too many!)')
                .css('color', '#dc3545');
            }

            // Disable unchecked boxes once the limit is reached.
            $skillCheckboxes.each(function () {
              var $cb = $(this);
              if (!$cb.is(':checked')) {
                $cb.prop('disabled', checked >= requiredSkills);
                $cb.closest('.form-item').toggleClass('skill-disabled', checked >= requiredSkills);
              }
            });
          }

          $skillCheckboxes.on('change', updateSkillCount);
          updateSkillCount();
        }

        // ── Alignment card sync ──────────────────────────────────────────────

        var $alignmentSelect = $form.find('select[name="alignment"]');
        var CARD_SEL = '.alignment-card';

        function syncCards(value) {
          $(CARD_SEL, context).removeClass('selected');
          if (value) {
            $(CARD_SEL + '[data-alignment="' + value + '"]', context).addClass('selected');
          }
        }

        once('alignment-click', CARD_SEL, context).forEach(function (card) {
          $(card).on('click', function () {
            var alignId = $(this).data('alignment');
            $alignmentSelect.val(alignId).trigger('change');
            syncCards(alignId);
          });
        });

        if ($alignmentSelect.length) {
          $alignmentSelect.on('change', function () { syncCards($(this).val()); });
          syncCards($alignmentSelect.val());
        }

        // ── Submit loading state ─────────────────────────────────────────────

        $form.on('submit', function () {
          $submit.prop('disabled', true).text('Saving\u2026');
        });
      });
    },
  };

})(jQuery, Drupal, once);
