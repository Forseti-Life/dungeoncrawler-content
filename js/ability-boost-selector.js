/**
 * @file
 * Interactive Ability Score Boost Selector (Pathbuilder-style)
 *
 * Handles interactive ability boost selection with debounced API-driven
 * recalculation. Used on Steps 3 (background boosts) and 5 (free boosts).
 *
 * Each widget instance gets its own scoped state via closure so multiple
 * widgets on different pages (or even the same page) do not collide.
 *
 * @see AbilityScoreTracker  (server-side calculation)
 * @see AbilityScoreApiController (API endpoints)
 */

(function ($, Drupal, once) {
  'use strict';

  var API = {
    CALCULATE: '/api/characters/ability-scores/calculate',
    VALIDATE_BOOST: '/api/characters/ability-scores/validate-boost',
    AVAILABLE_BOOSTS: '/api/characters/ability-scores/available-boosts',
  };

  var DEBOUNCE_MS = 300;
  var ANIM_MS = 300;
  var SESSION_TOKEN_URL = '/session/token';
  var csrfToken = null;
  var csrfTokenRequest = null;

  // ── Per-widget initialisation ──────────────────────────────────────────────

  /**
   * Detect step and config from the widget container's data attributes,
   * falling back to DOM probing if the template didn't render them.
   */
  function detectConfig($container) {
    var step = $container.data('step');
    var maxBoosts = parseInt($container.data('max-boosts'), 10);

    // Fallback: determine step by which hidden field exists in the page.
    if (!step) {
      if ($('#ancestry-boosts-field').length) {
        step = 'ancestry';
      } else if ($('#background-boosts-field').length) {
        step = 'background';
      } else {
        step = 'free';
      }
    }
    if (!maxBoosts || isNaN(maxBoosts)) {
      maxBoosts = step === 'free' ? 4 : 2;
    }

    var fieldId = '#free-boosts-field';
    if (step === 'ancestry') {
      fieldId = '#ancestry-boosts-field';
    } else if (step === 'background') {
      fieldId = '#background-boosts-field';
    }

    return { step: step, maxBoosts: maxBoosts, fieldId: fieldId };
  }

  function initWidget($widget) {
    var $container = $widget.closest('.ability-score-widget');
    var cfg = detectConfig($container);
    var maxBoosts = cfg.maxBoosts;
    var step = cfg.step;
    var selectedBoosts = [];
    var characterData = {};
    var calculating = false;
    var recalcQueued = false;
    var debounceTimer = null;
    var pendingSyncRequest = null;
    var pendingRecalcRequest = null;
    var latestSyncSeq = 0;
    var latestRecalcSeq = 0;

    // Hidden field that stores the JSON array of selected abilities.
    var $hidden = $(cfg.fieldId);

    // ── Load existing selections ────────────────────────────────────────────

    if ($hidden.length && $hidden.val()) {
      try {
        selectedBoosts = JSON.parse($hidden.val());
        selectedBoosts.forEach(function (ability) {
          var $card = $widget.find('.ability-card[data-ability="' + ability + '"]');
          $card.addClass('ability-card--selected');
          $card.find('.ability-checkbox').prop('checked', true);
        });
      } catch (e) {
        selectedBoosts = [];
      }
    }

    // ── Load character data ─────────────────────────────────────────────────

    if ($container.data('character-data')) {
      characterData = $container.data('character-data');
      if (typeof characterData === 'string') {
        try {
          var decodedCharacterData = $('<textarea/>').html(characterData).text();
          characterData = JSON.parse(decodedCharacterData);
        } catch (e) {
          characterData = {};
        }
      }
    }
    ['background_boosts', 'free_boosts'].forEach(function (key) {
      if (typeof characterData[key] === 'string') {
        try { characterData[key] = JSON.parse(characterData[key]); }
        catch (_) { characterData[key] = []; }
      }
    });

    // ── Helpers ─────────────────────────────────────────────────────────────

    function updateHiddenField() {
      if ($hidden.length) {
        $hidden.val(JSON.stringify(selectedBoosts));
      }
    }

    function updateCounter() {
      var remaining = maxBoosts - selectedBoosts.length;
      $widget.find('.boosts-remaining')
        .text(remaining)
        .attr('data-remaining', remaining)
        .toggleClass('at-max', remaining === 0);
    }

    function buildPayload() {
      var data = $.extend({}, characterData);
      if (step === 'ancestry') { data.ancestry_boosts = selectedBoosts; }
      else if (step === 'background') { data.background_boosts = selectedBoosts; }
      else if (step === 'class')  { data.class_key_ability = selectedBoosts[0] || null; }
      else                        { data.free_boosts = selectedBoosts; }
      return data;
    }

    function showToast(message, bg) {
      var $el = $('<div>').text(message).css({
        position: 'fixed', top: '20px', left: '50%', transform: 'translateX(-50%)',
        background: bg, color: bg === '#ffc107' ? '#000' : '#fff',
        padding: '1rem 2rem', borderRadius: '8px', zIndex: 9999,
        boxShadow: '0 4px 12px rgba(0,0,0,.2)', fontWeight: 'bold',
      });
      $('body').append($el);
      setTimeout(function () { $el.fadeOut(ANIM_MS, function () { $(this).remove(); }); }, 2500);
    }

    function getCsrfToken() {
      if (csrfToken) {
        return $.Deferred().resolve(csrfToken).promise();
      }
      if (csrfTokenRequest) {
        return csrfTokenRequest;
      }

      csrfTokenRequest = $.ajax({
        url: SESSION_TOKEN_URL,
        method: 'GET',
        dataType: 'text',
      }).done(function (token) {
        csrfToken = token;
      }).fail(function () {
        csrfTokenRequest = null;
      });

      return csrfTokenRequest;
    }

    // ── Select / deselect ───────────────────────────────────────────────────

    function selectAbility($card, ability) {
      selectedBoosts.push(ability);
      $card.addClass('ability-card--selected');
      $card.find('.ability-checkbox').prop('checked', true);
      updateHiddenField();
      $card.css('transform', 'scale(1.05)');
      setTimeout(function () { $card.css('transform', ''); }, ANIM_MS);
    }

    function deselectAbility($card, ability) {
      var idx = selectedBoosts.indexOf(ability);
      if (idx > -1) { selectedBoosts.splice(idx, 1); }
      $card.removeClass('ability-card--selected');
      $card.find('.ability-checkbox').prop('checked', false);
      updateHiddenField();
    }

    // ── API helpers ─────────────────────────────────────────────────────────

    function syncAvailable() {
      latestSyncSeq += 1;
      var requestSeq = latestSyncSeq;

      if (pendingSyncRequest && pendingSyncRequest.readyState !== 4) {
        pendingSyncRequest.abort();
      }

      pendingSyncRequest = $.ajax({
        url: API.AVAILABLE_BOOSTS + '/' + step,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
          selected_boosts: selectedBoosts,
          character_data: buildPayload()
        }),
        dataType: 'json',
      }).done(function (res) {
        if (requestSeq !== latestSyncSeq) { return; }
        if (!res || !res.available) { return; }
        if (res.max_selections && !isNaN(parseInt(res.max_selections, 10))) {
          maxBoosts = parseInt(res.max_selections, 10);
          updateCounter();
        }
        var avail = res.available || [];
        var disabled = res.disabled || [];
        $widget.find('.ability-card--selectable').each(function () {
          var $c = $(this);
          var ab = $c.data('ability');
          var isSelected = selectedBoosts.indexOf(ab) !== -1;
          var off = disabled.indexOf(ab) !== -1 || (avail.indexOf(ab) === -1 && !isSelected);
          $c.toggleClass('ability-card--disabled', off);
          $c.find('.ability-checkbox').prop('disabled', off);
        });
      });
    }

    function recalculate() {
      if (calculating) {
        recalcQueued = true;
        return;
      }

      latestRecalcSeq += 1;
      var requestSeq = latestRecalcSeq;
      calculating = true;

      if (pendingRecalcRequest && pendingRecalcRequest.readyState !== 4) {
        pendingRecalcRequest.abort();
      }

      getCsrfToken().done(function (token) {
        pendingRecalcRequest = $.ajax({
          url: API.CALCULATE,
          method: 'POST',
          contentType: 'application/json',
          headers: {
            'X-CSRF-Token': token
          },
          data: JSON.stringify({ character_data: buildPayload() }),
          dataType: 'json',
        }).done(function (res) {
          if (requestSeq !== latestRecalcSeq) { return; }
          if (res && res.success) { updateScores(res); }
        }).always(function () {
          calculating = false;
          if (recalcQueued) {
            recalcQueued = false;
            recalculate();
          }
        });
      }).fail(function () {
        calculating = false;
        showToast('Failed to load CSRF token for score recalculation.', '#dc3545');
      });
    }

    function debouncedRecalc() {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(recalculate, DEBOUNCE_MS);
    }

    function updateScores(response) {
      var scores = response.scores || {};
      var modifiers = response.modifiers || {};
      $widget.find('.ability-card').each(function () {
        var $c = $(this);
        var ab = $c.data('ability');
        if (scores[ab] === undefined) { return; }
        var s = scores[ab];
        var m = modifiers[ab];
        var mText = m >= 0 ? '+' + m : '' + m;
        var $sv = $c.find('.score-value, .current-score');
        if (parseInt($sv.text(), 10) !== s) {
          $sv.addClass('score-display--changed');
          setTimeout(function () { $sv.text(s); $c.data('score', s); }, 100);
          setTimeout(function () { $sv.removeClass('score-display--changed'); }, 600);
        }
        $c.find('.modifier-value, .current-modifier')
          .text(mText)
          .removeClass('modifier-positive modifier-negative positive negative')
          .addClass(m >= 0 ? 'modifier-positive positive' : 'modifier-negative negative');
      });
    }

    // ── Card interaction ────────────────────────────────────────────────────

    function handleToggle($card, ability) {
      if ($card.hasClass('ability-card--selected')) {
        deselectAbility($card, ability);
        updateCounter();
        debouncedRecalc();
        syncAvailable();
        return;
      }
      if (selectedBoosts.length >= maxBoosts) {
        showToast('Maximum ' + maxBoosts + ' boosts selected. Deselect one first.', '#ffc107');
        return;
      }
      getCsrfToken().done(function (token) {
        $.ajax({
          url: API.VALIDATE_BOOST,
          method: 'POST',
          contentType: 'application/json',
          headers: {
            'X-CSRF-Token': token
          },
          data: JSON.stringify({
            ability: ability,
            step: step,
            selected_boosts: selectedBoosts,
            current_character_data: buildPayload(),
          }),
          dataType: 'json',
        }).done(function (res) {
          if (!res || res.valid === false) {
            showToast(res && res.error ? res.error : 'This boost selection is not valid.', '#dc3545');
            return;
          }
          selectAbility($card, ability);
          updateCounter();
          debouncedRecalc();
          syncAvailable();
        }).fail(function () {
          showToast('Failed to validate boost selection.', '#dc3545');
        });
      }).fail(function () {
        showToast('Failed to load CSRF token for boost validation.', '#dc3545');
      });
    }

    // ── Bind events ─────────────────────────────────────────────────────────

    $widget.find('.ability-card--selectable').each(function () {
      var $card = $(this);
      var ability = $card.data('ability');

      $card.on('click', function (e) {
        e.preventDefault();
        if (!$card.hasClass('ability-card--disabled')) {
          handleToggle($card, ability);
        }
      });

      $card.on('keypress', function (e) {
        if (e.which === 13 || e.which === 32) {
          e.preventDefault();
          if (!$card.hasClass('ability-card--disabled')) {
            handleToggle($card, ability);
          }
        }
      });

      // Hover preview.
      $card.on('mouseenter', function () {
        if (!$card.hasClass('ability-card--selected') && !$card.hasClass('ability-card--disabled')) {
          var cur = parseInt($card.data('score'), 10);
          var preview = cur < 18 ? cur + 2 : cur + 1;
          $card.find('.preview-score').text(preview).show();
          $card.find('.arrow-icon').show();
          $card.addClass('ability-card--preview');
        }
      });
      $card.on('mouseleave', function () {
        if (!$card.hasClass('ability-card--selected')) {
          $card.find('.preview-score').hide();
          $card.find('.arrow-icon').hide();
          $card.removeClass('ability-card--preview');
        }
      });
    });

    updateCounter();
    syncAvailable();
  }

  // ── Drupal behavior ────────────────────────────────────────────────────────

  Drupal.behaviors.abilityScoreBoostSelector = {
    attach: function (context) {
      $(once('ability-boost-selector', '.abilities-interactive', context)).each(function () {
        initWidget($(this));
      });
    },
  };

  // ── Form validation ────────────────────────────────────────────────────────

  Drupal.behaviors.abilityBoostFormValidation = {
    attach: function (context) {
      $(once('ability-boost-form-validation', 'form.character-creation-form', context)).each(function () {
        var $form = $(this);
        $form.on('submit', function (e) {
          var $widget = $form.find('.ability-score-widget');
          if (!$widget.length) { return; }

          var cfg = detectConfig($widget);
          var $hidden = $(cfg.fieldId);
          var boosts = [];
          if ($hidden.length && $hidden.val()) {
            try { boosts = JSON.parse($hidden.val()); } catch (_) {}
          }

          if (boosts.length < cfg.maxBoosts) {
            e.preventDefault();
            alert('Please select ' + cfg.maxBoosts + ' ability boosts before continuing. You have selected ' + boosts.length + '.');
            return false;
          }
        });
      });
    },
  };

})(jQuery, Drupal, once);
