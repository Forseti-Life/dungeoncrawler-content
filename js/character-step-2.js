/**
 * @file
 * Character Creation Step 2 - Ancestry & Heritage Selection.
 *
 * Handles client-side ancestry card UI and heritage card rendering.
 * Server-authoritative: ancestry select triggers AJAX which rebuilds
 * the heritage select and ancestry feat radios via heritage-path-wrapper.
 *
 * Data Flow:
 *   CharacterManager::HERITAGES → PHP form data-heritages attr → parseHeritageData()
 *   → showHeritages() → JS-rendered heritage cards → $selectedHeritage hidden select
 *
 * Schema: character_options_step2.json v1.0.0
 *   ancestry:  hot-column in dc_characters + character_data.ancestry.name
 *   heritage:  JSON-only  (character_data.ancestry.heritage)
 *
 * @see CharacterManager::HERITAGES
 * @see CharacterCreationStepForm::buildStep2Fields()
 */

(function ($, Drupal, once) {
  'use strict';

  const SELECTORS = {
    FORM: 'form.character-creation-form',
    ANCESTRY_CARD: '.ancestry-card',
    HERITAGE_SECTION: '#heritageSelection',
    HERITAGE_OPTIONS: '#heritageOptions',
    SUBMIT_BUTTON: 'button[type="submit"], input[type="submit"]',
    SELECTED_ANCESTRY: 'select[name="ancestry"]',
    SELECTED_HERITAGE: 'select[name="heritage"]',
    SELECTED_ANCESTRY_FEAT: 'input[name="ancestry_feat"]',
    HERITAGE_DATA_HOST: '.ancestry-selection',
  };

  const CSS = {
    SELECTED: 'selected',
    HIDDEN: 'hidden',
    HERITAGE_CARD: 'heritage-card',
  };

  Drupal.behaviors.characterStep2 = {
    attach(context) {
      once('step2-init', SELECTORS.FORM, context).forEach(function (formEl) {
        const $form           = $(formEl);
        const $cards          = $(SELECTORS.ANCESTRY_CARD, context);
        const $heritageSection = $(SELECTORS.HERITAGE_SECTION, context);
        const $heritageOptions = $(SELECTORS.HERITAGE_OPTIONS, context);
        const $submitButton   = $(SELECTORS.SUBMIT_BUTTON, context).first();
        const $ancestrySelect = $(SELECTORS.SELECTED_ANCESTRY, context);

        // Live lookup — the heritage <select> lives inside #heritage-path-wrapper
        // which Drupal AJAX replaces on ancestry change, invalidating any cached
        // reference. Always query the current DOM element.
        function $heritageSelect() {
          return $(SELECTORS.SELECTED_HERITAGE);
        }

        // ── Heritage data ────────────────────────────────────────────────────

        /**
         * Parse the data-heritages attribute on the ancestry-selection host.
         *
         * Source: CharacterManager::HERITAGES JSON-encoded by the server.
         * Keys are normalised to lowercase-hyphenated ancestry IDs to match
         * the values written to $ancestrySelect.
         *
         * @return {Object.<string, Array<{id:string, name:string, benefit:string}>>}
         */
        function parseHeritageData() {
          try {
            const raw = $(SELECTORS.HERITAGE_DATA_HOST, context).first()
              .attr('data-heritages') || '{}';
            const parsed = JSON.parse(raw);

            if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
              return {};
            }

            const out = {};
            Object.keys(parsed).forEach(key => {
              out[key.toLowerCase().replace(/\s+/g, '-')] = parsed[key];
            });
            return out;
          }
          catch (e) {
            console.error('[step2] Failed to parse heritage data:', e);
            return {};
          }
        }

        const heritages = parseHeritageData();

        // ── Utilities ────────────────────────────────────────────────────────

        /** Safely escape a string for insertion into HTML. */
        function esc(str) {
          const d = document.createElement('div');
          d.textContent = str;
          return d.innerHTML;
        }

        /**
         * Clear heritage and ancestry feat selections.
         * Called whenever the selected ancestry changes, before the AJAX
         * response that rebuilds heritage-path-wrapper arrives.
         */
        function clearDependentSelections() {
          $heritageSelect().val('');
          $(SELECTORS.SELECTED_ANCESTRY_FEAT, context).prop('checked', false);
        }

        // ── Heritage card rendering ──────────────────────────────────────────

        /**
         * Render one heritage card.
         *
         * @param {{id:string, name:string, benefit:string}} heritage
         * @param {boolean} selected
         * @return {string} HTML
         */
        function renderCard(heritage, selected) {
          return `<div class="${CSS.HERITAGE_CARD}${selected ? ' ' + CSS.SELECTED : ''}"
                       data-heritage="${esc(heritage.id)}">
            <h4>${esc(heritage.name)}</h4>
            <p>${esc(heritage.benefit)}</p>
          </div>`;
        }

        /**
         * Render heritage cards for the given ancestry.
         * The section stays hidden when the ancestry has no heritages.
         *
         * @param {string} ancestryId  Lowercase hyphenated ancestry identifier.
         */
        function showHeritages(ancestryId) {
          const list = heritages[ancestryId];
          if (!list || list.length === 0) {
            $heritageSection.addClass(CSS.HIDDEN);
            return;
          }

          const current = $heritageSelect().val();
          $heritageOptions.html(
            list.map(h => renderCard(h, current === h.id)).join('')
          );
          $heritageSection.removeClass(CSS.HIDDEN);
        }

        // ── Heritage card clicks (delegated — element persists across AJAX) ───

        $heritageOptions.on('click', `.${CSS.HERITAGE_CARD}`, function () {
          $heritageOptions.find(`.${CSS.HERITAGE_CARD}`).removeClass(CSS.SELECTED);
          $(this).addClass(CSS.SELECTED);
          $heritageSelect().val($(this).data('heritage'));
        });

        // ── Ancestry card clicks ─────────────────────────────────────────────

        once('ancestry-click', SELECTORS.ANCESTRY_CARD, context).forEach(function (card) {
          $(card).on('click', function () {
            const ancestryId = $(this).data('ancestry');

            $cards.removeClass(CSS.SELECTED);
            $(this).addClass(CSS.SELECTED);

            clearDependentSelections();
            showHeritages(ancestryId);

            // Triggers the Drupal AJAX request that rebuilds heritage-path-wrapper
            // (server-rendered heritage select + ancestry feat radios).
            $ancestrySelect.val(ancestryId).trigger('change');
          });
        });

        // ── Ancestry select change (keyboard / programmatic) ─────────────────
        // AJAX handles the server-authoritative heritage select inside
        // heritage-path-wrapper; we refresh the JS heritage cards here so
        // keyboard and assistive-tech users see the same card UI as pointer users.

        once('ancestry-change', SELECTORS.SELECTED_ANCESTRY, context).forEach(function (sel) {
          $(sel).on('change', function () {
            clearDependentSelections();
            showHeritages($(this).val());
          });
        });

        // ── Initialise from saved/pre-selected ancestry ───────────────────────

        const initial = $ancestrySelect.val();
        if (initial) {
          showHeritages(initial);
        }

        // ── Form submit ───────────────────────────────────────────────────────

        $form.on('submit', function (e) {
          window.setTimeout(function () {
            if (e.isDefaultPrevented() || (formEl.checkValidity && !formEl.checkValidity())) {
              return;
            }
            $submitButton.prop('disabled', true).text('Saving\u2026');
          }, 0);
        });
      });
    },
  };

})(jQuery, Drupal, once);
