import {
  buildEditorGmRequest,
  shouldSubmitEditorGmKey,
} from './v2/editor/EditorGmChatClient.js?v=20261023-gm-chat-2';
import { StorylineFlowGraph } from './v2/editor/StorylineFlowGraph.js?v=20261117-review-2';

(function (Drupal, once, drupalSettings) {
  'use strict';

  function parseLines(raw) {
    return String(raw || '')
      .split('\n')
      .map((line) => line.trim())
      .filter((line) => line !== '');
  }

  function renderCurrentState(status, elements) {
    const current = status && typeof status === 'object' ? status : {};
    const {
      percent: storylineStatusPercent,
      progress: storylineStatusProgress,
      validity: storylineStatusValidity,
      focus: storylineStatusFocus,
      counts: storylineStatusCounts,
      stages: storylineStatusStages,
      missing: storylineStatusMissing,
    } = elements;
    const percent = Number(current.completion_percent || 0);
    if (storylineStatusPercent) {
      storylineStatusPercent.textContent = `${percent}%`;
    }
    if (storylineStatusProgress) {
      storylineStatusProgress.value = percent;
      storylineStatusProgress.textContent = `${percent}%`;
    }
    if (storylineStatusValidity) {
      storylineStatusValidity.textContent = current.draft_id
        ? current.canonical_valid
          ? `Valid · ${Number(current.warning_count || 0)} warnings`
          : `${Number(current.error_count || 0)} validation errors`
        : 'No draft';
    }
    if (storylineStatusFocus) {
      storylineStatusFocus.textContent = Number(current.current_stage || 1) <= 7
        ? `Stage ${Number(current.current_stage || 1)} of 7`
        : 'Final review';
    }
    if (storylineStatusCounts) {
      const counts = current.counts || {};
      storylineStatusCounts.textContent = `${Number(counts.scenes || 0)} scenes · ${Number(counts.conflicts || 0)} conflicts · ${Number(counts.narrative_beats_complete || 0)}/6 beats · ${Number(counts.adventure_requirements_complete || 0)}/4 adventure requirements`;
    }
    if (storylineStatusStages) {
      storylineStatusStages.replaceChildren();
      (Array.isArray(current.stages) ? current.stages : []).forEach((stage) => {
        const item = document.createElement('li');
        item.className = 'gm-storyline-status__stage';
        item.classList.toggle('is-complete', !!stage.complete);
        const heading = document.createElement('div');
        const label = document.createElement('strong');
        label.textContent = `${stage.stage}. ${stage.label}`;
        const percent = document.createElement('span');
        percent.textContent = `${Number(stage.completion_percent || 0)}%`;
        heading.append(label, percent);
        const metrics = document.createElement('small');
        const metricLabels = {
          fields_complete: 'fields',
          fields_total: 'required',
        };
        metrics.textContent = Object.entries(stage.metrics || {})
          .map(([key, value]) => `${metricLabels[key] || key.replaceAll('_', ' ')}: ${value}`)
          .join(' · ');
        item.append(heading, metrics);
        storylineStatusStages.appendChild(item);
      });
    }
    if (storylineStatusMissing) {
      storylineStatusMissing.replaceChildren();
      const missing = Array.isArray(current.missing_content) ? current.missing_content : [];
      const values = current.draft_id
        ? (missing.length > 0 ? missing : ['All minimum content is present.'])
        : ['Create or load a draft.'];
      values.slice(0, 6).forEach((label) => {
        const item = document.createElement('li');
        item.textContent = label;
        storylineStatusMissing.appendChild(item);
      });
    }
  }

  function parseCommaList(raw) {
    return String(raw || '')
      .split(',')
      .map((value) => value.trim())
      .filter((value) => value !== '');
  }

  function parseStateChanges(raw) {
    return parseLines(raw).map((line) => {
      const [key = '', operation = '', ...valueParts] = line.split('|').map((part) => part.trim());
      const rawValue = valueParts.join('|').trim();
      let value = rawValue;
      if (rawValue !== '') {
        try {
          value = JSON.parse(rawValue);
        } catch (error) {
          value = rawValue;
        }
      }
      return { key, operation, value };
    });
  }

  function formatStateChanges(changes) {
    return (Array.isArray(changes) ? changes : []).map((change) => {
      const value = typeof change?.value === 'string'
        ? JSON.stringify(change.value)
        : JSON.stringify(change?.value ?? '');
      return `${String(change?.key || '')} | ${String(change?.operation || '')} | ${value}`;
    }).join('\n');
  }

  function sanitizeIdentifier(value) {
    const raw = String(value || '').trim().toLowerCase();
    if (raw === '') {
      return '';
    }
    let normalized = raw;
    if (typeof normalized.normalize === 'function') {
      normalized = normalized.normalize('NFKD').replace(/[\u0300-\u036f]/g, '');
    }
    return normalized
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .replace(/-{2,}/g, '-');
  }

  function createIdentifierAllocator(existing = []) {
    const used = new Set(
      (Array.isArray(existing) ? existing : [])
        .map((value) => String(value || '').trim())
        .filter((value) => value !== '')
    );
    return (preferred, fallbackPrefix = 'id') => {
      const base = sanitizeIdentifier(preferred) || sanitizeIdentifier(fallbackPrefix) || 'id';
      let candidate = base;
      let suffix = 2;
      while (used.has(candidate)) {
        candidate = `${base}-${suffix}`;
        suffix += 1;
      }
      used.add(candidate);
      return candidate;
    };
  }

  function parseLegacyDelimitedRows(raw, keys) {
    return parseLines(raw).map((line) => {
      const parts = line.split('|').map((part) => part.trim());
      const row = {};
      keys.forEach((key, index) => {
        row[key] = parts[index] || '';
      });
      return row;
    });
  }

  Drupal.behaviors.gmStorylineBuilder = {
    attach(context) {
      const roots = [
        ...(context instanceof Element && context.matches('[data-gm-storyline-wizard]') ? [context] : []),
        ...Array.from(context.querySelectorAll?.('[data-gm-storyline-wizard]') || []),
      ].filter((wizard) => wizard.dataset.gmStorylineInitialized !== 'true');
      roots.forEach((wizard) => {
        wizard.dataset.gmStorylineInitialized = 'true';
        // The page heading is the name of the storyline being edited.
        const showStorylineName = () => {
          const heading = document.querySelector('[data-storyline-heading]');
          if (heading) {
            heading.textContent = (wizard.querySelector('[data-field="storyline_title"]')?.value || '').trim() || 'New storyline';
          }
        };
        wizard.querySelector('[data-field="storyline_title"]')?.addEventListener('input', showStorylineName);
        const stepTabs = Array.from(document.querySelectorAll('[data-step-tab]'));
        const stepPanels = Array.from(wizard.querySelectorAll('[data-step-panel]'));
        const titleEl = document.querySelector('[data-gm-storyline-active-step-title]');
        const metaEl = document.querySelector('[data-gm-storyline-active-step-meta]');
        const reviewSummaryEl = wizard.querySelector('[data-storyline-review-summary]');
        const validateBtn = wizard.querySelector('[data-gm-template-validate]');
        const publishBtn = wizard.querySelector('[data-gm-template-publish]');
        const publishStatusEl = wizard.querySelector('[data-gm-template-publish-status]');
        const validationSummaryEl = wizard.querySelector('[data-gm-template-validation-summary]');
        const validationListEl = wizard.querySelector('[data-gm-template-validation-list]');
        const analysisSummaryEl = wizard.querySelector('[data-gm-template-analysis-summary]');
        const analysisGraphEl = wizard.querySelector('[data-gm-template-analysis-graph]');
        const storylineFlowGraph = analysisGraphEl ? new StorylineFlowGraph(analysisGraphEl) : null;
        const fields = Array.from(wizard.querySelectorAll('[data-field]'));
        const maxStep = stepPanels.length;
        const settings = drupalSettings?.dungeoncrawlerGmStorylineBuilder || {};
        const initialFields = settings.initialFields || {};
        const assistantSettings = settings.assistant || {};
        const draftsSettings = settings.drafts || {};
        const publishSettings = settings.publish || {};
        const objectEditorTemplates = settings.objectEditors || {};
        const objectEditorLinks = document.querySelector('[data-storyline-object-editor-links]');

        const assistantPanel = wizard.querySelector('[data-gm-assistant-panel]');
        const assistantStatus = wizard.querySelector('[data-gm-assistant-status]');
        const assistantLog = wizard.querySelector('[data-gm-assistant-log]');
        const assistantInput = wizard.querySelector('[data-gm-assistant-input]');
        const assistantSend = wizard.querySelector('[data-gm-assistant-send]');
        const resumeWorkButton = document.createElement('button');
        const assistantBusyControls = new Map();
        resumeWorkButton.type = 'button';
        resumeWorkButton.className = 'btn btn-secondary btn-sm';
        resumeWorkButton.textContent = 'Resume saved work';
        resumeWorkButton.hidden = true;
        assistantSend?.parentElement.appendChild(resumeWorkButton);
        resumeWorkButton.addEventListener('click', () => askAssistant('Resume saved work'));
        const stopWorkButton = document.createElement('button');
        stopWorkButton.type = 'button';
        stopWorkButton.className = 'btn btn-secondary btn-sm';
        stopWorkButton.textContent = 'Stop saved work';
        stopWorkButton.hidden = true;
        assistantSend?.parentElement.appendChild(stopWorkButton);
        stopWorkButton.addEventListener('click', () => askAssistant('Stop saved work'));
        const skipWorkButton = document.createElement('button');
        skipWorkButton.type = 'button';
        skipWorkButton.className = 'btn btn-secondary btn-sm';
        skipWorkButton.textContent = 'Skip this step';
        skipWorkButton.hidden = true;
        assistantSend?.parentElement.appendChild(skipWorkButton);
        skipWorkButton.addEventListener('click', () => askAssistant('Skip this step of the saved work'));
        const draftCreateBtn = wizard.querySelector('[data-gm-draft-create]');
        const draftSaveBtn = wizard.querySelector('[data-gm-draft-save]');
        const draftDeleteBtn = wizard.querySelector('[data-gm-draft-delete]');
        const draftPicker = wizard.querySelector('[data-gm-draft-picker]');
        const draftCountEl = wizard.querySelector('[data-gm-draft-count]');
        const draftRevisionPicker = wizard.querySelector('[data-gm-draft-revision]');
        const draftStatusEl = wizard.querySelector('[data-gm-draft-status]');
        const storylineStatusPercent = document.querySelector('[data-storyline-status-percent]');
        const storylineStatusProgress = document.querySelector('[data-storyline-status-progress]');
        const storylineStatusValidity = document.querySelector('[data-storyline-status-validity]');
        const storylineStatusFocus = document.querySelector('[data-storyline-status-focus]');
        const storylineStatusCounts = document.querySelector('[data-storyline-status-counts]');
        const storylineStatusStages = document.querySelector('[data-storyline-status-stages]');
        const storylineStatusMissing = document.querySelector('[data-storyline-status-missing]');
        const storylineStatusElements = {
          percent: storylineStatusPercent,
          progress: storylineStatusProgress,
          validity: storylineStatusValidity,
          focus: storylineStatusFocus,
          counts: storylineStatusCounts,
          stages: storylineStatusStages,
          missing: storylineStatusMissing,
        };

        const collections = {
          journal_entries: ['chapter_id', 'scene_id', 'order', 'title', 'summary', 'trigger', 'room_ids', 'central_conflict', 'dramatic_function', 'themes', 'character_ids', 'foreshadows_scene_id'],
          conflict_entries: ['conflict_id', 'title', 'actor_ids', 'introduced_scene_id', 'escalation_scene_ids', 'resolved_scene_id', 'status'],
          relationship_entries: ['scene_id', 'source_actor_id', 'target_actor_id', 'before', 'after', 'reason'],
          task_entries: ['task_id', 'chapter_id', 'scene_id', 'status', 'goal_text', 'gm_notes', 'open_resolution'],
          branch_choice_entries: ['decision_id', 'source_task_id', 'choice_id', 'label', 'trigger_type', 'trigger_interaction', 'trigger_condition', 'trigger_source_id', 'effect_summary', 'target_task_id', 'target_scene_id', 'terminal', 'approach_visibility', 'approach_outcome_label', 'approach_reveal', 'approach_objective_ids', 'approach_reward_xp', 'approach_reward_gold'],
          branch_actor_effect_entries: ['choice_id', 'actor_id', 'interaction', 'reaction', 'effect', 'state_changes'],
          branch_environment_effect_entries: ['choice_id', 'target_type', 'target_id', 'effect', 'state_changes'],
          branch_storyline_effect_entries: ['choice_id', 'target_type', 'target_id', 'source_actor_id', 'operation', 'effect', 'follow_up_required', 'state_changes'],
          objective_entries: ['objective_id', 'task_id', 'type', 'next_step', 'target', 'requires_check', 'item', 'location', 'destination', 'kind', 'metric', 'description', 'required'],
          institution_entries: ['domain', 'name', 'description', 'purpose', 'seat_id', 'leader_actor_id', 'parent_institution_id'],
          actor_entries: ['contact_id', 'actor_id', 'role', 'display_name', 'motivation', 'dialogue', 'knowledge', 'attitude', 'alignment', 'availability', 'chapter_id', 'scene_id', 'location_id'],
          dungeon_entries: ['dungeon_id', 'chapter_id', 'purpose', 'entrance_room'],
          room_entries: ['dungeon_id', 'chapter_id', 'scene_id', 'room_id', 'narrative'],
          item_entries: ['item_id', 'purpose', 'acquisition', 'chapter_id', 'scene_id', 'quest_id', 'actor_id', 'prerequisite_ids', 'reward'],
        };

        const legacyToCollection = {
          journal_flow: { collection: 'journal_entries', keys: collections.journal_entries },
          task_flow: { collection: 'task_entries', keys: collections.task_entries },
          objective_criteria: { collection: 'objective_entries', keys: collections.objective_entries },
          actor_specs: { collection: 'actor_entries', keys: collections.actor_entries },
          dungeon_specs: { collection: 'dungeon_entries', keys: collections.dungeon_entries },
          room_specs: { collection: 'room_entries', keys: collections.room_entries },
          item_specs: { collection: 'item_entries', keys: collections.item_entries },
        };
        const collectionIdentityFields = {
          journal_entries: 'scene_id',
          conflict_entries: 'conflict_id',
          task_entries: 'task_id',
          branch_choice_entries: 'choice_id',
          objective_entries: 'objective_id',
          actor_entries: 'contact_id',
          dungeon_entries: 'dungeon_id',
          room_entries: 'room_id',
          item_entries: 'item_id',
        };

        let activeStep = 1;
        let csrfToken = '';
        let draftId = '';
        let draftRevision = 0;
        let viewedRevision = null;
        let draftCurrentRevision = 0;
        let draftLifecycle = '';
        let draftTemplateId = '';
        let draftDirty = false;
        let draftCanEdit = false;
        // Actor relationships are managed on the actor graph page; this form
        // carries them through a save untouched.
        let actorRelationships = [];
        // The saved draft's institutions: only these have an ID, so only these
        // can be chosen as a parent, in a stance, or on a cast member.
        let savedInstitutions = [];
        // The saved record each row or sub-row was loaded from, so fields the
        // form does not show survive a save.
        const rowSource = new WeakMap();
        let draftIsOwner = false;
        let draftLibrary = new Map();
        let assistantBusy = false;
        let conversationRestored = false;

        const stepLabels = {
          1: 'Title, Description, Premise',
          2: 'Journal/Task Objective Flow',
          3: 'Institutions',
          4: 'Actors Framework',
          5: 'Dungeons & Rooms Framework',
          6: 'Items Framework',
          7: 'Composition & Validation',
          8: 'Review Storyline',
        };

        function setDraftStatus(text) {
          if (draftStatusEl) {
            draftStatusEl.textContent = text;
          }
        }

        function updateDraftUrl() {
          if (!draftId) {
            return;
          }
          const url = new URL(window.location.href);
          url.searchParams.set('draft_id', draftId);
          url.searchParams.delete('template_id');
          if (viewedRevision === null) {
            url.searchParams.delete('revision');
          } else {
            url.searchParams.set('revision', String(viewedRevision));
          }
          window.history.replaceState({}, '', url);
        }

        function markDraftDirty() {
          draftDirty = true;
          setDraftStatus(draftId ? `Unsaved changes (draft ${draftId.slice(0, 8)}, rev ${draftRevision})` : 'Unsaved changes');
        }

        function setPublishStatus(text) {
          if (publishStatusEl) {
            publishStatusEl.textContent = text;
          }
        }

        function renderValidationResult(validation) {
          if (validationSummaryEl) {
            validationSummaryEl.textContent = '';
          }
          if (validationListEl) {
            validationListEl.innerHTML = '';
          }
          if (!validation || typeof validation !== 'object') {
            return;
          }
          const stages = Array.isArray(validation?.stages) ? validation.stages : [];
          const stageErrors = stages.filter((stage) => !stage?.valid);
          const warningCount = stages.reduce((count, stage) => count + Number(stage?.warning_count || 0), 0);
          if (validationSummaryEl) {
            validationSummaryEl.textContent = `${Number(validation.error_count || 0)} errors • ${Number(validation.warning_count ?? warningCount)} warnings • ${stages.length - stageErrors.length}/${stages.length} validation stages passed`;
            validationSummaryEl.classList.toggle('has-errors', Number(validation.error_count || 0) > 0);
          }
          if (!validationListEl) {
            return;
          }
          if (stages.length === 0) {
            const item = document.createElement('li');
            item.className = 'gm-storyline-wizard__validation-item';
            item.textContent = 'No stage findings returned.';
            validationListEl.appendChild(item);
            return;
          }
          stages.forEach((stage) => {
            const stageName = String(stage?.stage || 'unknown_stage');
            const stageItem = document.createElement('li');
            stageItem.className = `gm-storyline-wizard__validation-item gm-storyline-wizard__validation-item--${stage?.valid ? 'ok' : 'error'}`;
            stageItem.textContent = `${stageName}: ${stage?.valid ? 'pass' : 'fail'} (${Number(stage?.error_count || 0)} error${Number(stage?.error_count || 0) === 1 ? '' : 's'})`;
            validationListEl.appendChild(stageItem);
            if (!stage?.valid && Array.isArray(stage?.errors)) {
              stage.errors.forEach((error) => {
                const errorItem = document.createElement('li');
                errorItem.className = 'gm-storyline-wizard__validation-item gm-storyline-wizard__validation-item--error';
                errorItem.textContent = `↳ ${stageName}: ${String(error)}`;
                validationListEl.appendChild(errorItem);
              });
            }
            if (Array.isArray(stage?.warnings)) {
              stage.warnings.forEach((warning) => {
                const warningItem = document.createElement('li');
                warningItem.className = 'gm-storyline-wizard__validation-item gm-storyline-wizard__validation-item--warning';
                warningItem.textContent = `↳ ${stageName}: ${String(warning)}`;
                validationListEl.appendChild(warningItem);
              });
            }
          });
        }

        async function renderNarrativeAnalysis(analysis) {
          if (analysisSummaryEl) {
            analysisSummaryEl.textContent = '';
          }
          if (!analysis || typeof analysis !== 'object' || !storylineFlowGraph) {
            return;
          }
          const metrics = analysis.metrics || {};
          if (analysisSummaryEl) {
            analysisSummaryEl.textContent = `${Number(metrics.chapter_count || 0)} chapters • ${Number(metrics.scene_count || 0)} scenes • ${Number(metrics.actor_count || 0)} actors • ${Number(metrics.conflict_count || 0)} conflicts • ${Number(metrics.branch_decision_count || 0)} decisions • ${Number(metrics.branch_choice_count || 0)} choices`;
          }
          await storylineFlowGraph.render(analysis.graph);
        }

        function setAssistantStatus(text) {
          if (!assistantStatus) {
            return;
          }
          const message = String(text || '').trim();
          assistantStatus.textContent = message;
          assistantStatus.hidden = message === '';
          if (message !== '' && assistantLog) {
            assistantLog.appendChild(assistantStatus);
            assistantLog.scrollTop = assistantLog.scrollHeight;
          }
        }

        function setAssistantBusy(busy) {
          assistantBusy = busy;
          assistantPanel?.setAttribute('aria-busy', busy ? 'true' : 'false');
          if (busy) {
            wizard.querySelectorAll('input, textarea, select, button').forEach((control) => {
              if (!assistantBusyControls.has(control)) {
                assistantBusyControls.set(control, control.disabled);
              }
              control.disabled = true;
            });
          } else {
            assistantBusyControls.forEach((disabled, control) => {
              control.disabled = disabled;
            });
            assistantBusyControls.clear();
            applyDraftOwnershipState();
          }
          if (assistantSend) {
            assistantSend.disabled = busy || !draftCanEdit;
          }
          resumeWorkButton.disabled = busy || !draftCanEdit;
          stopWorkButton.disabled = busy;
          skipWorkButton.disabled = busy || !draftCanEdit;
          wizard.querySelectorAll('[data-gm-assistant-prompt]').forEach((button) => {
            button.disabled = busy;
          });
        }

        function applyWorkActionButtons(progress) {
          const actions = Array.isArray(progress?.allowed_actions) ? progress.allowed_actions : [];
          const canResume = actions.includes('resume');
          const canCancel = actions.includes('cancel');
          const canSkip = actions.includes('skip');
          resumeWorkButton.hidden = !canResume;
          stopWorkButton.hidden = !canCancel;
          skipWorkButton.hidden = !canSkip;
          resumeWorkButton.disabled = assistantBusy || !draftCanEdit || !canResume;
          stopWorkButton.disabled = assistantBusy || !canCancel;
          skipWorkButton.disabled = assistantBusy || !draftCanEdit || !canSkip;
        }

        function summarizeWorkFailure(progress) {
          const failure = progress?.failure || {};
          const firstFinding = Array.isArray(failure.findings) ? failure.findings.find((row) => row?.message) : null;
          const detail = firstFinding?.message || failure.error || 'A saved work batch failed validation.';
          const label = String(failure.label || '').trim();
          const attempts = Number(progress?.failed_attempts || 0);
          const maxAttempts = Number(progress?.max_failed_attempts || 0);
          const prefix = label ? `Saved batch paused at ${label}.` : 'Saved batch paused.';
          if (progress?.allowed_actions?.includes('resume')) {
            return `${prefix} ${detail} (attempt ${attempts} of ${maxAttempts}).`;
          }
          return `${prefix} ${detail} Attempts are exhausted; stop saved work and rephrase the request.`;
        }

        function resolveDraftUrl(template, id) {
          if (!template || !id) {
            return '';
          }
          return template.replace('{draft_id}', encodeURIComponent(id));
        }

        async function fetchCsrfToken() {
          const response = await fetch('/session/token', { credentials: 'same-origin' });
          if (!response.ok) {
            throw new Error(`token_${response.status}`);
          }
          return (await response.text()).trim();
        }

        function entriesFromCollection(collection, keys) {
          const rows = wizard.querySelectorAll(`[data-rows="${collection}"] .gm-storyline-wizard__row`);
          return Array.from(rows).map((row) => {
            const item = {};
            keys.forEach((key) => {
              item[key] = (row.querySelector(`[data-col-field="${key}"]`)?.value || '').trim();
            });
            return { row, item };
          }).filter(({ item }) => Object.values(item).some((value) => value !== ''));
        }

        function listFromCollection(collection, keys) {
          return entriesFromCollection(collection, keys).map(({ item }) => item);
        }

        // The saved record with the form's values over it. A field left empty
        // that the record never had stays absent.
        function withEdits(source, edited) {
          const result = { ...source };
          Object.entries(edited).forEach(([key, value]) => {
            if (value !== '' || key in source) {
              result[key] = value;
            }
          });
          return result;
        }

        function institutionOptionLabel(institution) {
          const kind = wizard.querySelector('[data-row-template="institution_entries"]')
            .content.querySelector(`[data-col-field="domain"] option[value="${institution.domain}"]`).dataset.short;
          return `${institution.name} (${kind})`;
        }

        function fillInstitutionOptions(scope, excludedId) {
          scope.querySelectorAll('select[data-institution-options]').forEach((select) => {
            savedInstitutions.filter((institution) => institution.institution_id !== excludedId).forEach((institution) => {
              const option = document.createElement('option');
              option.value = institution.institution_id;
              option.textContent = institutionOptionLabel(institution);
              select.appendChild(option);
            });
          });
        }

        // Sub-rows of a row: an institution's stances, an actor's affiliations.
        function addSubRow(row, kind, seed = {}) {
          const subrow = wizard.querySelector(`[data-subrow-template="${kind}"]`)
            .content.querySelector('.gm-storyline-wizard__subrow').cloneNode(true);
          // An institution holds no stance toward itself.
          fillInstitutionOptions(subrow, kind === 'stances' ? (rowSource.get(row).institution_id || '') : '');
          subrow.querySelectorAll('[data-sub-field]').forEach((field) => {
            const value = seed[field.getAttribute('data-sub-field')];
            if (value != null) {
              field.value = String(value);
            }
          });
          rowSource.set(subrow, seed);
          row.querySelector(`[data-subrows="${kind}"] [data-subrow-list]`).appendChild(subrow);
          return subrow;
        }

        function subRowsOf(row, kind) {
          return Array.from(row.querySelectorAll(`[data-subrows="${kind}"] .gm-storyline-wizard__subrow`)).map((subrow) => {
            const edited = {};
            subrow.querySelectorAll('[data-sub-field]').forEach((field) => {
              edited[field.getAttribute('data-sub-field')] = field.value.trim();
            });
            return { source: rowSource.get(subrow), edited };
          });
        }

        function institutionsFromForm() {
          return Array.from(wizard.querySelectorAll('[data-rows="institution_entries"] .gm-storyline-wizard__row')).map((row) => {
            const source = rowSource.get(row);
            const edited = {};
            collections.institution_entries.forEach((key) => {
              edited[key] = row.querySelector(`[data-col-field="${key}"]`).value.trim();
            });
            const stances = subRowsOf(row, 'stances')
              .filter((stance) => stance.edited.toward_institution_id !== '' || stance.edited.reason !== '')
              .map((stance) => withEdits(stance.source, stance.edited));
            if (stances.length === 0 && Object.values(edited).every((value) => value === '')) {
              return null;
            }
            const institution = withEdits(source, edited);
            if (stances.length > 0 || 'stances' in source) {
              institution.stances = stances;
            }
            return institution;
          }).filter((institution) => institution !== null);
        }

        function affiliationsFromRow(row) {
          return subRowsOf(row, 'affiliations')
            .filter((affiliation) => affiliation.edited.institution_id !== '')
            .map(({ source, edited }) => withEdits(source, {
              institution_id: edited.institution_id,
              // Member is the standing of an affiliation that states none.
              standing: edited.standing === 'member' && !('standing' in source) ? '' : edited.standing,
            }));
        }

        function addCollectionRow(collection, seed = {}) {
          const template = wizard.querySelector(`[data-row-template="${collection}"]`);
          const target = wizard.querySelector(`[data-rows="${collection}"]`);
          if (!template || !target) {
            return null;
          }
          const prototype = template.content?.querySelector('.gm-storyline-wizard__row');
          const row = prototype instanceof Element ? prototype.cloneNode(true) : null;
          if (row) {
            rowSource.set(row, seed);
            fillInstitutionOptions(row, seed.institution_id || '');
            Object.entries(seed).forEach(([key, value]) => {
              const input = row.querySelector(`[data-col-field="${key}"]`);
              if (input) {
                input.value = value == null ? '' : String(value);
              }
            });
            row.querySelectorAll('[data-subrows]').forEach((container) => {
              const kind = container.getAttribute('data-subrows');
              (Array.isArray(seed[kind]) ? seed[kind] : []).forEach((entry) => addSubRow(row, kind, entry));
            });
            const remove = row.querySelector('[data-remove-row]');
            if (remove) {
              const moveUp = document.createElement('button');
              moveUp.type = 'button';
              moveUp.className = 'btn btn-secondary btn-sm';
              moveUp.setAttribute('data-row-up', '');
              moveUp.textContent = 'Move up';
              const moveDown = document.createElement('button');
              moveDown.type = 'button';
              moveDown.className = 'btn btn-secondary btn-sm';
              moveDown.setAttribute('data-row-down', '');
              moveDown.textContent = 'Move down';
              const duplicate = document.createElement('button');
              duplicate.type = 'button';
              duplicate.className = 'btn btn-secondary btn-sm';
              duplicate.setAttribute('data-row-duplicate', collection);
              duplicate.textContent = 'Duplicate';
              remove.before(moveUp, moveDown, duplicate);
            }
          }
          if (row) {
            target.appendChild(row);
          }
          return row || null;
        }

        function duplicateCollectionRow(collection, row) {
          const values = {};
          collections[collection]?.forEach((key) => {
            values[key] = row.querySelector(`[data-col-field="${key}"]`)?.value || '';
          });
          const identityField = collectionIdentityFields[collection];
          if (identityField && values[identityField]) {
            const base = `${values[identityField]}-copy`;
            const existing = new Set(
              Array.from(wizard.querySelectorAll(`[data-rows="${collection}"] [data-col-field="${identityField}"]`))
                .map((field) => field.value)
            );
            let candidate = base;
            let suffix = 2;
            while (existing.has(candidate)) {
              candidate = `${base}-${suffix}`;
              suffix += 1;
            }
            values[identityField] = candidate;
          }
          addCollectionRow(collection, values);
        }

        function refreshRowOrder(container) {
          Array.from(container.children).forEach((row, index) => {
            const order = row.querySelector('[data-col-field="order"]');
            if (order) {
              order.value = String(index + 1);
            }
          });
        }

        function clearCollection(collection) {
          const target = wizard.querySelector(`[data-rows="${collection}"]`);
          if (target) {
            target.innerHTML = '';
          }
        }

        function buildBlueprint() {
          const get = (key) => (wizard.querySelector(`[data-field="${key}"]`)?.value || '').trim();
          const storylineId = get('storyline_id') || sanitizeIdentifier(get('storyline_title')) || 'storyline';
          const defaultChapterId = `${storylineId}-chapter`;
          const relationshipChanges = listFromCollection('relationship_entries', collections.relationship_entries);
          const actorEffects = listFromCollection('branch_actor_effect_entries', collections.branch_actor_effect_entries);
          const environmentEffects = listFromCollection('branch_environment_effect_entries', collections.branch_environment_effect_entries);
          const storylineEffects = listFromCollection('branch_storyline_effect_entries', collections.branch_storyline_effect_entries);
          const journalDraftRows = listFromCollection('journal_entries', collections.journal_entries);
          const sceneIdFor = createIdentifierAllocator(journalDraftRows.map((entry) => entry.scene_id));
          const journalEntriesBase = journalDraftRows.map((entry, index) => {
            const scene_id = entry.scene_id || sceneIdFor(entry.title || entry.summary || `scene-${index + 1}`, `${storylineId}-scene`);
            return {
              ...entry,
              scene_id,
              chapter_id: entry.chapter_id || defaultChapterId,
            };
          });
          const primarySceneId = journalEntriesBase[0]?.scene_id || '';
          const resolvedRelationshipChanges = relationshipChanges.map((change) => ({
            ...change,
            scene_id: change.scene_id || primarySceneId,
          }));
          const journalEntries = journalEntriesBase.map((entry) => ({
            ...entry,
            relationship_changes: resolvedRelationshipChanges
              .filter((change) => change.scene_id === entry.scene_id)
              .map(({ scene_id, ...change }) => change),
          }));
          const taskDraftRows = listFromCollection('task_entries', collections.task_entries);
          const taskIdFor = createIdentifierAllocator(taskDraftRows.map((task) => task.task_id));
          const tasks = taskDraftRows.map((task, index) => {
            const { goal_text: goalText, gm_notes: gmNotes, open_resolution: openResolution, ...rest } = task;
            return {
              ...rest,
              task_id: task.task_id || taskIdFor(task.status || task.scene_id || `task-${index + 1}`, `${storylineId}-task`),
              chapter_id: task.chapter_id || defaultChapterId,
              scene_id: task.scene_id || primarySceneId,
              // A quest has a goal only when the author wrote one.
              ...(goalText ? { goal_text: goalText, gm_notes: gmNotes, open_resolution: openResolution === 'true' } : {}),
            };
          });
          // "objective:ID" or "event:TYPE:REF", as typed in the approach row.
          const parseApproachReveal = (text) => {
            const [kind, ...rest] = String(text || '').split(':').map((part) => part.trim());
            if (kind === 'objective' && rest.length === 1 && rest[0]) {
              return { objective_id: rest[0] };
            }
            if (kind === 'event' && rest.length === 2 && rest[0] && rest[1]) {
              return { touchpoint: { objective_type: rest[0], ref: rest[1] } };
            }
            return null;
          };
          const buildApproach = (choice) => {
            if (!choice.approach_visibility) {
              return {};
            }
            const approach = {
              visibility: choice.approach_visibility,
              outcome_label: choice.approach_outcome_label,
              objective_ids: String(choice.approach_objective_ids || '').split(',').map((id) => id.trim()).filter(Boolean),
            };
            if (choice.approach_reveal !== '') {
              // Kept as typed when it does not parse, so validation reports it.
              approach.reveal_when = parseApproachReveal(choice.approach_reveal) || { unparsed: choice.approach_reveal };
            }
            if (choice.approach_reward_xp !== '' || choice.approach_reward_gold !== '') {
              approach.rewards = { xp: Number(choice.approach_reward_xp || 0), gold: Number(choice.approach_reward_gold || 0), items: [] };
            }
            return { approach };
          };
          const choiceDraftRows = listFromCollection('branch_choice_entries', collections.branch_choice_entries);
          const decisionIdFor = createIdentifierAllocator(choiceDraftRows.map((choice) => choice.decision_id));
          const choiceIdFor = createIdentifierAllocator(choiceDraftRows.map((choice) => choice.choice_id));
          const branchChoices = choiceDraftRows
            .filter((choice) => [
              'decision_id',
              'source_task_id',
              'choice_id',
              'label',
              'trigger_condition',
              'trigger_source_id',
              'effect_summary',
              'target_task_id',
              'target_scene_id',
            ].some((key) => choice[key] !== ''))
            .map((choice, index) => ({
            decision_id: choice.decision_id || decisionIdFor(choice.source_task_id || choice.label || `decision-${index + 1}`, `${storylineId}-decision`),
            source_task_id: choice.source_task_id,
            choice_id: choice.choice_id || choiceIdFor(choice.label || choice.trigger_condition || `choice-${index + 1}`, `${storylineId}-choice`),
            label: choice.label,
            trigger: {
              type: choice.trigger_type,
              condition: choice.trigger_condition,
              interaction: choice.trigger_interaction,
              source_id: choice.trigger_source_id,
            },
            effects: {
              summary: choice.effect_summary,
              actors: actorEffects
                .filter((effect) => effect.choice_id !== '' && effect.choice_id === choice.choice_id)
                .map(({ choice_id, state_changes, ...effect }) => ({
                  ...effect,
                  state_changes: parseStateChanges(state_changes),
                })),
              environment: environmentEffects
                .filter((effect) => effect.choice_id !== '' && effect.choice_id === choice.choice_id)
                .map(({ choice_id, state_changes, ...effect }) => ({
                  ...effect,
                  state_changes: parseStateChanges(state_changes),
                })),
              storyline: storylineEffects
                .filter((effect) => effect.choice_id !== '' && effect.choice_id === choice.choice_id)
                .map(({ choice_id, state_changes, follow_up_required, ...effect }) => ({
                  ...effect,
                  follow_up_required: follow_up_required === 'true',
                  state_changes: parseStateChanges(state_changes),
                })),
            },
            target_task_id: choice.target_task_id,
            target_scene_id: choice.terminal === 'true'
              ? choice.target_scene_id
              : choice.target_scene_id || primarySceneId,
            terminal: choice.terminal === 'true',
            ...buildApproach(choice),
          }));
          const conflictDraftRows = listFromCollection('conflict_entries', collections.conflict_entries);
          const conflictIdFor = createIdentifierAllocator(conflictDraftRows.map((conflict) => conflict.conflict_id));
          const objectiveDraftRows = listFromCollection('objective_entries', collections.objective_entries);
          const objectiveIdFor = createIdentifierAllocator(objectiveDraftRows.map((objective) => objective.objective_id));
          const actorEntries = entriesFromCollection('actor_entries', collections.actor_entries);
          const actorDraftRows = actorEntries.map(({ item }) => item);
          const actorIdFor = createIdentifierAllocator(actorDraftRows.map((actor) => actor.actor_id));
          const contactIdFor = createIdentifierAllocator(actorDraftRows.map((actor) => actor.contact_id));
          const actors = actorDraftRows.map((actor, index) => {
            const actor_id = actor.actor_id || actorIdFor(actor.display_name || actor.role || `actor-${index + 1}`, `${storylineId}-actor`);
            return {
              ...actor,
              actor_id,
              contact_id: actor.contact_id || contactIdFor(`contact-${actor_id}`, `${storylineId}-contact`),
              chapter_id: actor.chapter_id || defaultChapterId,
              scene_id: actor.scene_id || primarySceneId,
            };
          });
          const dungeonDraftRows = listFromCollection('dungeon_entries', collections.dungeon_entries);
          const dungeonIdFor = createIdentifierAllocator(dungeonDraftRows.map((dungeon) => dungeon.dungeon_id));
          const dungeons = dungeonDraftRows.map((dungeon, index) => ({
            ...dungeon,
            dungeon_id: dungeon.dungeon_id || dungeonIdFor(dungeon.purpose || `dungeon-${index + 1}`, `${storylineId}-dungeon`),
            chapter_id: dungeon.chapter_id || defaultChapterId,
          }));
          const primaryDungeonId = dungeons[0]?.dungeon_id || '';
          const roomDraftRows = listFromCollection('room_entries', collections.room_entries);
          const roomIdFor = createIdentifierAllocator(roomDraftRows.map((room) => room.room_id));
          const rooms = roomDraftRows.map((room, index) => ({
            ...room,
            room_id: room.room_id || roomIdFor(room.narrative || room.scene_id || `room-${index + 1}`, `${storylineId}-room`),
            chapter_id: room.chapter_id || defaultChapterId,
            scene_id: room.scene_id || primarySceneId,
            dungeon_id: room.dungeon_id || primaryDungeonId,
          }));
          const itemDraftRows = listFromCollection('item_entries', collections.item_entries);
          const itemIdFor = createIdentifierAllocator(itemDraftRows.map((item) => item.item_id));
          const items = itemDraftRows.map((item, index) => ({
            ...item,
            item_id: item.item_id || itemIdFor(item.purpose || item.reward || `item-${index + 1}`, `${storylineId}-item`),
            chapter_id: item.chapter_id || defaultChapterId,
            scene_id: item.scene_id || primarySceneId,
            actor_id: item.actor_id || actors[0]?.actor_id || '',
          }));
          return {
            schema_version: 'storyline-authoring-draft-v1',
            storyline_id: storylineId,
            title: get('storyline_title'),
            description: get('storyline_description'),
            premise: get('storyline_premise'),
            level_range: get('storyline_level_range'),
            tags: parseCommaList(get('storyline_tags')),
            player_hook: get('storyline_player_hook'),
            entry_actor_id: get('storyline_entry_actor') || actors[0]?.actor_id || '',
            entry_location_id: get('storyline_entry_location'),
            ending: get('storyline_ending'),
            process_flow: {
              journal_entries: journalEntries,
              tasks,
              branch_choices: branchChoices,
              objective_completion_criteria: objectiveDraftRows.map((objective, index) => {
                const row = {
                  ...objective,
                  objective_id: objective.objective_id || objectiveIdFor(objective.description || objective.metric || `objective-${index + 1}`, `${storylineId}-objective`),
                };
                // An unset type is absent, not an empty value: validation
                // then reports the objective as missing its type.
                if (row.type === '') {
                  delete row.type;
                }
                return row;
              }),
              conflicts: conflictDraftRows.map((conflict, index) => ({
                ...conflict,
                conflict_id: conflict.conflict_id || conflictIdFor(conflict.title || `conflict-${index + 1}`, `${storylineId}-conflict`),
                actor_ids: parseCommaList(conflict.actor_ids),
                escalation_scene_ids: parseCommaList(conflict.escalation_scene_ids),
              })),
              narrative_architecture: {
                beats: {
                  inciting_incident: {
                    disruption: get('narrative_inciting_disruption'),
                    stakes: get('narrative_inciting_stakes'),
                  },
                  threshold_of_no_return: {
                    crossing: get('narrative_threshold_crossing'),
                    point_of_no_return: get('narrative_point_of_no_return'),
                  },
                  progressive_complications: {
                    road_of_trials: get('narrative_road_of_trials'),
                    midpoint_shift: get('narrative_midpoint_shift'),
                  },
                  crisis: {
                    dark_night: get('narrative_dark_night'),
                    psychological_pivot: get('narrative_psychological_pivot'),
                  },
                  climax: {
                    central_confrontation: get('narrative_central_confrontation'),
                    premise_resolution: get('narrative_premise_resolution'),
                  },
                  denouement: {
                    new_equilibrium: get('narrative_new_equilibrium'),
                    internal_shift: get('narrative_internal_shift'),
                  },
                },
                adventure_requirements: {
                  physical_quest: get('adventure_physical_quest'),
                  antagonistic_force: get('adventure_antagonistic_force'),
                  mentor_guide: get('adventure_mentor_guide'),
                  boon_macguffin: get('adventure_boon_macguffin'),
                },
              },
            },
            objects: {
              actors: {
                generation_specs: actors.map((actor, index) => {
                  const { affiliations: saved, ...source } = rowSource.get(actorEntries[index].row);
                  const affiliations = affiliationsFromRow(actorEntries[index].row);
                  // No alignment chosen is no alignment field.
                  if (!actor.alignment) {
                    delete actor.alignment;
                    delete source.alignment;
                  }
                  return affiliations.length > 0 || saved !== undefined
                    ? { ...source, ...actor, affiliations }
                    : { ...source, ...actor };
                }),
                relationships: actorRelationships.filter((relationship) => {
                  const ids = actors.map((actor) => actor.actor_id);
                  return ids.includes(relationship.source_actor_id) && ids.includes(relationship.target_actor_id);
                }),
                validation_criteria: parseLines(get('actor_validation')),
              },
              dungeons: {
                generation_specs: dungeons,
              },
              rooms: {
                generation_specs: rooms,
                validation_criteria: parseLines(get('room_validation')),
              },
              items: {
                generation_specs: items,
                validation_criteria: parseLines(get('item_validation')),
              },
              institutions: institutionsFromForm(),
            },
            orchestration: {
              generation: parseLines(get('generation_orchestration')),
              validation: parseLines(get('validation_orchestration')),
            },
          };
        }

        // Suggestions for the ID fields that name a place or a cast member.
        function refreshReferenceLists(blueprint) {
          const lists = {
            places: [
              ...blueprint.objects.rooms.generation_specs.map((room) => [room.room_id, 'Room']),
              ...blueprint.objects.dungeons.generation_specs.map((dungeon) => [dungeon.dungeon_id, 'Site']),
            ],
            cast: blueprint.objects.actors.generation_specs.map((actor) => [actor.actor_id, actor.display_name]),
          };
          Object.entries(lists).forEach(([name, entries]) => {
            const list = wizard.querySelector(`[data-reference-list="${name}"]`);
            // Rebuilding an open suggestion list on every keystroke closes it.
            const signature = JSON.stringify(entries);
            if (list.dataset.signature === signature) {
              return;
            }
            list.dataset.signature = signature;
            list.replaceChildren(...entries.map(([value, label]) => {
              const option = document.createElement('option');
              option.value = value;
              option.label = label;
              return option;
            }));
          });
        }

        function refreshObjectEditorLinks() {
          if (!objectEditorLinks) {
            return;
          }
          const blueprint = buildBlueprint();
          refreshReferenceLists(blueprint);
          // Sites and rooms are built in their own editors, by their own GM:
          // each site is listed with its rooms beneath it.
          const rooms = (blueprint.objects?.rooms?.generation_specs || [])
            .map((room) => ({ id: String(room.room_id || '').trim(), site: String(room.dungeon_id || '').trim() }))
            .filter((room) => room.id);
          const roomEntry = (room) => ({ kind: 'room', id: room.id, label: room.id, nested: true });
          const objects = [
            ...(blueprint.objects?.dungeons?.generation_specs || []).flatMap((dungeon) => {
              const id = String(dungeon.dungeon_id || '').trim();
              return [{ kind: 'dungeon', id, label: id }, ...rooms.filter((room) => room.site === id).map(roomEntry)];
            }),
            ...rooms.filter((room) => !(blueprint.objects?.dungeons?.generation_specs || []).some((dungeon) => String(dungeon.dungeon_id || '').trim() === room.site)).map(roomEntry),
          ];
          const unique = new Map();
          objects.forEach((object) => {
            if (object.id && objectEditorTemplates[object.kind]) {
              unique.set(`${object.kind}:${object.id}`, object);
            }
          });
          objectEditorLinks.replaceChildren();
          const sceneCount = (blueprint.process_flow?.journal_entries || []).length;
          if (sceneCount > 0 && draftId && draftsSettings.sceneReviewUrl) {
            const sceneItem = document.createElement('li');
            const sceneLink = document.createElement('a');
            const sceneUrl = new URL(draftsSettings.sceneReviewUrl, window.location.origin);
            sceneUrl.searchParams.set('draft_id', draftId);
            sceneLink.href = sceneUrl.toString();
            sceneLink.target = '_blank';
            sceneLink.rel = 'noopener';
            sceneLink.textContent = `Scenes in order (${sceneCount})`;
            sceneLink.title = 'Open every scene of the saved draft on one page, in story order';
            sceneItem.appendChild(sceneLink);
            objectEditorLinks.appendChild(sceneItem);
          }
          const actorCount = (blueprint.objects?.actors?.generation_specs || []).length;
          const institutionCount = blueprint.objects.institutions.length;
          if ((institutionCount > 0 || actorCount > 0) && draftId && draftsSettings.institutionReviewUrl) {
            const institutionItem = document.createElement('li');
            const institutionLink = document.createElement('a');
            const institutionUrl = new URL(draftsSettings.institutionReviewUrl, window.location.origin);
            institutionUrl.searchParams.set('draft_id', draftId);
            institutionLink.href = institutionUrl.toString();
            institutionLink.target = '_blank';
            institutionLink.rel = 'noopener';
            institutionLink.textContent = `Institutions and their members (${institutionCount})`;
            institutionLink.title = 'Open every institution of the saved draft with the cast tied to it, and the cast tied to none';
            institutionItem.appendChild(institutionLink);
            objectEditorLinks.appendChild(institutionItem);
          }
          if (actorCount > 0 && draftId && draftsSettings.actorGraphUrl) {
            const graphItem = document.createElement('li');
            const graphLink = document.createElement('a');
            const graphUrl = new URL(draftsSettings.actorGraphUrl, window.location.origin);
            graphUrl.searchParams.set('draft_id', draftId);
            graphLink.href = graphUrl.toString();
            graphLink.target = '_blank';
            graphLink.rel = 'noopener';
            graphLink.textContent = `Actors and relationships (${actorCount})`;
            graphLink.title = 'Open the affiliation and relationship graph; each actor there links to its review page';
            graphItem.appendChild(graphLink);
            objectEditorLinks.appendChild(graphItem);
          }
          if (unique.size === 0 && (actorCount > 0 || sceneCount > 0)) {
            return;
          }
          if (unique.size === 0) {
            const empty = document.createElement('li');
            empty.textContent = 'Load a storyline to see its scenes, actors, sites and rooms.';
            objectEditorLinks.appendChild(empty);
            return;
          }
          unique.forEach((object) => {
            const item = document.createElement('li');
            const link = document.createElement('a');
            const href = String(objectEditorTemplates[object.kind]).replace('{object_id}', encodeURIComponent(object.id));
            link.href = href;
            link.target = '_blank';
            link.rel = 'noopener';
            const editorName = object.kind === 'dungeon' ? 'Dungeon Editor' : 'Room Editor';
            link.textContent = `${object.kind === 'dungeon' ? 'Site' : 'Room'}: ${object.label}`;
            link.title = `Open ${object.id} in the ${editorName}; its GM builds it from this story's brief`;
            link.dataset.editorShortcut = `${object.kind}:${object.id}`;
            if (object.nested) {
              item.style.marginLeft = '14px';
            }
            item.appendChild(link);
            objectEditorLinks.appendChild(item);
          });
        }

        function refreshPreview() {
          if (!reviewSummaryEl) {
            return;
          }
          const blueprint = buildBlueprint();
          const title = document.createElement('h4');
          title.textContent = blueprint.title || 'Untitled storyline';
          const premise = document.createElement('p');
          premise.textContent = blueprint.premise || blueprint.description || 'Add a premise to describe the adventure.';
          const counts = document.createElement('dl');
          counts.className = 'gm-storyline-wizard__review-counts';
          for (const [label, rows] of [
            ['Scenes', blueprint.process_flow.journal_entries],
            ['Quests', blueprint.process_flow.tasks],
            ['Choices', blueprint.process_flow.branch_choices],
            ['Institutions', blueprint.objects.institutions],
            ['Actors', blueprint.objects.actors.generation_specs],
            ['Dungeons', blueprint.objects.dungeons.generation_specs],
          ]) {
            const card = document.createElement('div');
            const term = document.createElement('dt');
            term.textContent = label;
            const value = document.createElement('dd');
            value.textContent = String(rows.length);
            card.append(term, value);
            counts.appendChild(card);
          }
          reviewSummaryEl.replaceChildren(title, premise, counts);
        }

        function hydrateFromPayload(payload) {
          if (!payload || typeof payload !== 'object') {
            return;
          }
          actorRelationships = Array.isArray(payload?.objects?.actors?.relationships) ? payload.objects.actors.relationships : [];
          savedInstitutions = Array.isArray(payload?.objects?.institutions) ? payload.objects.institutions : [];
          const setField = (key, value) => {
            const field = wizard.querySelector(`[data-field="${key}"]`);
            if (field) {
              field.value = value == null ? '' : String(value);
            }
          };
          setField('storyline_id', payload.storyline_id || '');
          setField('storyline_title', payload.title || '');
          showStorylineName();
          setField('storyline_description', payload.description || '');
          setField('storyline_premise', payload.premise || '');
          setField('storyline_level_range', payload.level_range || '');
          setField('storyline_tags', Array.isArray(payload.tags) ? payload.tags.join(', ') : (payload.tags || ''));
          setField('storyline_player_hook', payload.player_hook || '');
          setField('storyline_entry_actor', payload.entry_actor_id || '');
          setField('storyline_entry_location', payload.entry_location_id || '');
          setField('storyline_ending', payload.ending || '');
          const architecture = payload?.process_flow?.narrative_architecture || {};
          setField('narrative_inciting_disruption', architecture?.beats?.inciting_incident?.disruption || '');
          setField('narrative_inciting_stakes', architecture?.beats?.inciting_incident?.stakes || '');
          setField('narrative_threshold_crossing', architecture?.beats?.threshold_of_no_return?.crossing || '');
          setField('narrative_point_of_no_return', architecture?.beats?.threshold_of_no_return?.point_of_no_return || '');
          setField('narrative_road_of_trials', architecture?.beats?.progressive_complications?.road_of_trials || '');
          setField('narrative_midpoint_shift', architecture?.beats?.progressive_complications?.midpoint_shift || '');
          setField('narrative_dark_night', architecture?.beats?.crisis?.dark_night || '');
          setField('narrative_psychological_pivot', architecture?.beats?.crisis?.psychological_pivot || '');
          setField('narrative_central_confrontation', architecture?.beats?.climax?.central_confrontation || '');
          setField('narrative_premise_resolution', architecture?.beats?.climax?.premise_resolution || '');
          setField('narrative_new_equilibrium', architecture?.beats?.denouement?.new_equilibrium || '');
          setField('narrative_internal_shift', architecture?.beats?.denouement?.internal_shift || '');
          setField('adventure_physical_quest', architecture?.adventure_requirements?.physical_quest || '');
          setField('adventure_antagonistic_force', architecture?.adventure_requirements?.antagonistic_force || '');
          setField('adventure_mentor_guide', architecture?.adventure_requirements?.mentor_guide || '');
          setField('adventure_boon_macguffin', architecture?.adventure_requirements?.boon_macguffin || '');
          setField('actor_validation', (payload?.objects?.actors?.validation_criteria || []).join('\n'));
          setField('room_validation', (payload?.objects?.rooms?.validation_criteria || []).join('\n'));
          setField('item_validation', (payload?.objects?.items?.validation_criteria || []).join('\n'));
          setField('generation_orchestration', (payload?.orchestration?.generation || []).join('\n'));
          setField('validation_orchestration', (payload?.orchestration?.validation || []).join('\n'));

          const branchChoices = Array.isArray(payload?.process_flow?.branch_choices)
            ? payload.process_flow.branch_choices
            : [];
          const map = [
            ['journal_entries', payload?.process_flow?.journal_entries || []],
            ['relationship_entries', (payload?.process_flow?.journal_entries || []).flatMap((entry) => (
              Array.isArray(entry?.relationship_changes)
                ? entry.relationship_changes.map((change) => ({ scene_id: entry.scene_id || '', ...change }))
                : []
            ))],
            ['conflict_entries', (payload?.process_flow?.conflicts || []).map((conflict) => ({
              ...conflict,
              actor_ids: Array.isArray(conflict?.actor_ids) ? conflict.actor_ids.join(', ') : (conflict?.actor_ids || ''),
              escalation_scene_ids: Array.isArray(conflict?.escalation_scene_ids) ? conflict.escalation_scene_ids.join(', ') : (conflict?.escalation_scene_ids || ''),
            }))],
            ['task_entries', (payload?.process_flow?.tasks || []).map((task) => ({
              ...task,
              goal_text: task?.goal_text || '',
              gm_notes: task?.gm_notes || '',
              open_resolution: task?.open_resolution === true || task?.open_resolution === 'true' ? 'true' : 'false',
            }))],
            ['branch_choice_entries', branchChoices.map((choice) => ({
              decision_id: choice?.decision_id || '',
              source_task_id: choice?.source_task_id || '',
              choice_id: choice?.choice_id || '',
              label: choice?.label || '',
              trigger_type: choice?.trigger?.type || 'player_choice',
              trigger_interaction: choice?.trigger?.interaction || 'other',
              trigger_condition: choice?.trigger?.condition || '',
              trigger_source_id: choice?.trigger?.source_id || '',
              effect_summary: choice?.effects?.summary || '',
              target_task_id: choice?.target_task_id || '',
              target_scene_id: choice?.target_scene_id || '',
              terminal: choice?.terminal ? 'true' : 'false',
              approach_visibility: choice?.approach?.visibility || '',
              approach_outcome_label: choice?.approach?.outcome_label || '',
              approach_reveal: choice?.approach?.reveal_when?.objective_id
                ? `objective:${choice.approach.reveal_when.objective_id}`
                : (choice?.approach?.reveal_when?.touchpoint
                  ? `event:${choice.approach.reveal_when.touchpoint.objective_type}:${choice.approach.reveal_when.touchpoint.ref}`
                  : (choice?.approach?.reveal_when?.unparsed || '')),
              approach_objective_ids: [].concat(choice?.approach?.objective_ids || []).join(', '),
              approach_reward_xp: choice?.approach?.rewards ? String(choice.approach.rewards.xp) : '',
              approach_reward_gold: choice?.approach?.rewards ? String(choice.approach.rewards.gold) : '',
            }))],
            ['branch_actor_effect_entries', branchChoices.flatMap((choice) => (
              Array.isArray(choice?.effects?.actors)
                ? choice.effects.actors.map((effect) => ({
                  choice_id: choice.choice_id || '',
                  ...effect,
                  state_changes: formatStateChanges(effect?.state_changes),
                }))
                : []
            ))],
            ['branch_environment_effect_entries', branchChoices.flatMap((choice) => (
              Array.isArray(choice?.effects?.environment)
                ? choice.effects.environment.map((effect) => ({
                  choice_id: choice.choice_id || '',
                  ...effect,
                  state_changes: formatStateChanges(effect?.state_changes),
                }))
                : []
            ))],
            ['branch_storyline_effect_entries', branchChoices.flatMap((choice) => (
              Array.isArray(choice?.effects?.storyline)
                ? choice.effects.storyline.map((effect) => ({
                  choice_id: choice.choice_id || '',
                  ...effect,
                  follow_up_required: effect?.follow_up_required ? 'true' : 'false',
                  state_changes: formatStateChanges(effect?.state_changes),
                }))
                : []
            ))],
            ['objective_entries', payload?.process_flow?.objective_completion_criteria || []],
            ['institution_entries', savedInstitutions],
            ['actor_entries', payload?.objects?.actors?.generation_specs || []],
            ['dungeon_entries', payload?.objects?.dungeons?.generation_specs || []],
            ['room_entries', payload?.objects?.rooms?.generation_specs || []],
            ['item_entries', payload?.objects?.items?.generation_specs || []],
          ];
          map.forEach(([collection, rows]) => {
            clearCollection(collection);
            if (Array.isArray(rows) && rows.length > 0) {
              rows.forEach((row) => addCollectionRow(collection, row && typeof row === 'object' ? row : {}));
            } else {
              addCollectionRow(collection, {});
            }
          });
          refreshObjectEditorLinks();
        }

        async function createDraft(preserveFields = false, selectedTemplateId = '') {
          if (!draftsSettings.createUrl) {
            setDraftStatus('Draft API unavailable');
            return;
          }
          try {
            setDraftStatus('Creating draft…');
            if (!csrfToken) {
              csrfToken = await fetchCsrfToken();
            }
            const params = new URLSearchParams(window.location.search);
            const queryTemplateId = selectedTemplateId || (params.get('template_id') || '').trim();
            const response = await fetch(draftsSettings.createUrl, {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
              },
              body: JSON.stringify({
                template_id: queryTemplateId,
              }),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
              throw new Error(payload?.error?.code || `create_${response.status}`);
            }
            const draft = payload?.data || {};
            draftId = String(draft.draft_id || '');
            draftRevision = Number(draft.revision || 0);
            viewedRevision = null;
            draftCanEdit = draft.can_edit !== false;
            draftIsOwner = draft.is_owner === true;
            draftLifecycle = String(draft.status || '');
            draftTemplateId = String(draft.published_template_id || draft.source_template_id || '');
            draftDirty = preserveFields;
            applyDraftOwnershipState();
            updateDraftUrl();
            if (!preserveFields && queryTemplateId !== '') {
              hydrateFromPayload(draft.payload || {});
            }
            refreshPreview();
            setDraftStatus(`Draft ${draftId.slice(0, 8)} ready (rev ${draftRevision})`);
            await refreshDraftPicker();
            await refreshRevisionPicker();
            initAssistant();
          } catch (error) {
            setDraftStatus(`Draft create failed: ${error.message}`);
          }
        }

        async function loadDraftById(existingDraftId, requestedRevision = null) {
          const loadUrl = resolveDraftUrl(draftsSettings.loadUrlTemplate, existingDraftId);
          if (!loadUrl) {
            setDraftStatus('Draft load URL unavailable');
            return;
          }
          try {
            setDraftStatus('Loading draft…');
            const response = await fetch(loadUrl, { credentials: 'same-origin' });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
              throw new Error(payload?.error?.code || `load_${response.status}`);
            }
            const draft = payload?.data || {};
            draftId = String(draft.draft_id || existingDraftId);
            draftRevision = Number(draft.revision || 0);
            viewedRevision = null;
            draftCanEdit = draft.can_edit === true;
            draftIsOwner = draft.is_owner === true;
            draftLifecycle = String(draft.status || '');
            draftTemplateId = String(draft.published_template_id || draft.source_template_id || '');
            draftDirty = false;
            applyDraftOwnershipState();
            updateDraftUrl();
            hydrateFromPayload(draft.payload || {});
            refreshPreview();
            const redirected = String(draft.redirected_from_draft_id || '');
            setDraftStatus(redirected
              ? `Recovered retired draft ${redirected.slice(0, 8)} as active draft ${draftId.slice(0, 8)} (rev ${draftRevision}).`
              : (draftCanEdit
                ? `Loaded shared draft ${draftId.slice(0, 8)} (rev ${draftRevision}); last updated by ${draft.updated_by_name || 'unknown editor'}.`
                : `Viewing ${draftLifecycle} snapshot ${draftId.slice(0, 8)} (rev ${draftRevision}).`));
            if (draftCanEdit) {
              initAssistant();
            } else {
              setAssistantStatus('This snapshot is read-only. Open its working draft to edit or use the GM.');
            }
            await refreshRevisionPicker();
            if (requestedRevision !== null && Number(requestedRevision) !== draftRevision) {
              await loadDraftRevision(Number(requestedRevision));
            }
          } catch (error) {
            setDraftStatus(`Draft load failed: ${error.message}`);
          }
        }

        async function loadDraftRevision(revision) {
          const template = draftsSettings.revisionUrlTemplate || '';
          const revisionUrl = resolveDraftUrl(template, draftId).replace('{revision}', encodeURIComponent(String(revision)));
          if (!revisionUrl) {
            setDraftStatus('Draft revision URL unavailable');
            return;
          }
          try {
            setDraftStatus(`Loading revision ${revision}…`);
            const response = await fetch(revisionUrl, { credentials: 'same-origin' });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
              throw new Error(payload?.error?.code || `revision_${response.status}`);
            }
            const draft = payload?.data || {};
            draftRevision = Number(draft.revision || revision);
            viewedRevision = draftRevision;
            draftCanEdit = false;
            draftIsOwner = draft.is_owner === true;
            draftCurrentRevision = Number(draft.current_revision || draft.revision);
            draftLifecycle = String(draft.status || '');
            draftTemplateId = String(draft.published_template_id || draft.source_template_id || '');
            draftDirty = false;
            hydrateFromPayload(draft.payload || {});
            refreshPreview();
            applyDraftOwnershipState();
            updateDraftUrl();
            if (draftRevisionPicker) {
              draftRevisionPicker.value = String(draftRevision);
            }
            setDraftStatus(`Viewing immutable revision ${draftRevision}. Restore it as a new revision without changing the saved history.`);
            setAssistantStatus('Historical revisions are read-only. Return to the current revision to use the GM.');
          } catch (error) {
            setDraftStatus(`Draft revision load failed: ${error.message}`);
          }
        }

        async function saveDraft() {
          if (!draftId) {
            await createDraft(true);
            if (!draftId) {
              throw new Error('storyline_draft_create_failed');
            }
          }
          if (!draftCanEdit) {
            if (viewedRevision !== null && draftLifecycle === 'editing') {
              draftRevision = draftCurrentRevision;
            } else if (draftLifecycle === 'published' && draftTemplateId) {
              selectedTemplateId = draftTemplateId;
              await createDraft(false);
              return;
            } else {
              throw new Error('storyline_draft_not_editable');
            }
          }
          const saveUrl = resolveDraftUrl(draftsSettings.saveUrlTemplate, draftId);
          if (!saveUrl) {
            setDraftStatus('Draft save URL unavailable');
            throw new Error('storyline_draft_save_url_unavailable');
          }
          try {
            setDraftStatus('Saving draft…');
            if (!csrfToken) {
              csrfToken = await fetchCsrfToken();
            }
            const response = await fetch(saveUrl, {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
              },
              body: JSON.stringify({
                expected_revision: draftRevision,
                payload: buildBlueprint(),
              }),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
              if (response.status === 409) {
                setDraftStatus('Revision conflict. Reload this draft to review the newer saved revision before retrying.');
              }
              throw new Error(payload?.error?.code || `save_${response.status}`);
            }
            const draft = payload?.data || {};
            draftRevision = Number(draft.revision || draftRevision);
            viewedRevision = null;
            draftCanEdit = true;
            draftIsOwner = draft.is_owner === true;
            draftLifecycle = String(draft.status || '');
            draftDirty = false;
            // The saved payload carries the institution IDs the server derived.
            hydrateFromPayload(draft.payload);
            applyDraftOwnershipState();
            updateDraftUrl();
            setDraftStatus(`Saved draft ${draftId.slice(0, 8)} (rev ${draftRevision})`);
            await refreshDraftPicker();
            await refreshRevisionPicker();
          } catch (error) {
            setDraftStatus(`Draft save failed: ${error.message}`);
            throw error;
          }
        }

        function applyDraftOwnershipState() {
            if (draftSaveBtn) {
              draftSaveBtn.textContent = draftCanEdit || !draftId
                ? 'Save draft'
                : (viewedRevision !== null && draftLifecycle === 'editing' ? 'Restore as New Revision' : 'Open Working Draft');
              draftSaveBtn.disabled = draftLifecycle === 'archived';
            }
            if (assistantSend) {
              assistantSend.disabled = !draftCanEdit || assistantBusy;
            }
            if (publishBtn) {
              publishBtn.disabled = !draftCanEdit;
            }
            if (validateBtn) {
              validateBtn.disabled = !draftCanEdit;
            }
            if (draftDeleteBtn) {
              draftDeleteBtn.disabled = !draftIsOwner || !draftId;
            }
            if (assistantBusy) {
              setAssistantBusy(true);
            }
          }

        async function refreshDraftPicker() {
            if (!draftPicker || !draftsSettings.listUrl) {
              return;
            }
            const response = await fetch(draftsSettings.listUrl, { credentials: 'same-origin' });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
              throw new Error(payload?.error?.code || `draft_list_${response.status}`);
            }
            const drafts = Array.isArray(payload?.data) ? payload.data : [];
            draftLibrary = new Map(drafts
              .filter((draft) => draft.selector_type === 'draft' && draft.draft_id)
              .map((draft) => [String(draft.draft_id), draft]));
            if (draftCountEl) {
              draftCountEl.textContent = `${drafts.length} ${drafts.length === 1 ? 'storyline' : 'storylines'}`;
            }
            draftPicker.replaceChildren(new Option('Load a storyline…', ''));
            drafts.forEach((draft) => {
              const selectorType = draft.selector_type || 'draft';
              if (selectorType === 'template') {
                const option = new Option(`${draft.title || 'Untitled'} · Published`, `template:${draft.template_id || ''}`);
                draftPicker.appendChild(option);
                return;
              }
              const updater = `Updated by ${draft.updated_by_name || 'unknown editor'}`;
              const source = draftLibrary.get(String(draft.source_draft_id || ''));
              const lineage = source
                ? ` · from ${source.title || 'Untitled'} rev ${Number(draft.source_revision || source.revision || 0)}`
                : '';
              const versions = ` · ${Number(draft.revision_count || 0)} saved ${Number(draft.revision_count || 0) === 1 ? 'version' : 'versions'}`;
              const option = new Option(`${draft.title || 'Untitled'} · Draft · ${updater} · rev ${Number(draft.revision || 0)}${versions}${lineage}`, `draft:${String(draft.draft_id || '')}`);
              option.selected = String(draft.draft_id || '') === draftId;
              draftPicker.appendChild(option);
            });
        }

        async function refreshRevisionPicker() {
            if (!draftRevisionPicker) {
              return;
            }
            draftRevisionPicker.replaceChildren(new Option('Current revision', ''));
            draftRevisionPicker.disabled = !draftId;
            if (!draftId || !draftsSettings.revisionsUrlTemplate) {
              return;
            }
            const revisionsUrl = resolveDraftUrl(draftsSettings.revisionsUrlTemplate, draftId);
            const response = await fetch(revisionsUrl, { credentials: 'same-origin' });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
              throw new Error(payload?.error?.code || `revision_list_${response.status}`);
            }
            const revisions = Array.isArray(payload?.data) ? payload.data : [];
            const current = revisions.find((revision) => revision.is_current);
            draftRevisionPicker.options[0].textContent = `Current — revision ${Number(current?.revision || draftRevision || 0)} · ${current?.updated_by_name || 'unknown editor'}`;
            revisions.filter((revision) => !revision.is_current).forEach((revision) => {
              const date = new Date(Number(revision.created_at || 0) * 1000);
              const label = Number.isNaN(date.getTime())
                ? `Revision ${Number(revision.revision || 0)}`
                : `Revision ${Number(revision.revision || 0)} — ${date.toLocaleString()}`;
              draftRevisionPicker.appendChild(new Option(`${label} · ${revision.updated_by_name || 'unknown editor'}`, String(revision.revision || '')));
            });
            draftRevisionPicker.value = viewedRevision === null ? '' : String(viewedRevision);
        }

        async function deleteCurrentDraft() {
          if (!draftId || !draftIsOwner || !draftsSettings.deleteUrlTemplate) {
            return;
          }
          if (!window.confirm('Permanently delete this draft and all of its saved versions? Published canonical storylines are not deleted.')) {
            return;
          }
          if (!csrfToken) {
            csrfToken = await fetchCsrfToken();
          }
          setDraftStatus('Deleting draft…');
          const deleteUrl = resolveDraftUrl(draftsSettings.deleteUrlTemplate, draftId);
          const response = await fetch(deleteUrl, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
              'X-CSRF-Token': csrfToken,
            },
          });
          const payload = await response.json().catch(() => ({}));
          if (!response.ok) {
            setDraftStatus(`Draft delete failed: ${payload?.error?.code || `delete_${response.status}`}`);
            return;
          }
          const url = new URL(window.location.href);
          url.searchParams.delete('draft_id');
          url.searchParams.delete('revision');
          window.location.assign(url);
        }

        async function validateTemplateDraft() {
          if (!publishSettings.validateUrlTemplate) {
            setPublishStatus('Draft validation API unavailable');
            return null;
          }
          try {
            await saveDraft();
            const validateUrl = resolveDraftUrl(publishSettings.validateUrlTemplate, draftId);
            if (!validateUrl) {
              setPublishStatus('Validation URL unavailable');
              return null;
            }
            setPublishStatus('Validating storyline draft…');
            if (!csrfToken) {
              csrfToken = await fetchCsrfToken();
            }
            const response = await fetch(validateUrl, {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
              },
              body: JSON.stringify({
                expected_revision: draftRevision,
              }),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
              throw new Error(payload?.error?.message || payload?.error?.code || `validate_${response.status}`);
            }
            const validation = payload?.data || {};
            renderValidationResult(validation);
            await renderNarrativeAnalysis(validation.analysis);
            if (validation.valid) {
              setPublishStatus('Validation passed. Review the storyline, then publish when ready.');
            } else {
              setPublishStatus(`Validation failed with ${Number(validation.error_count || 0)} contract error(s).`);
            }
            return validation;
          } catch (error) {
            setPublishStatus(`Validation failed: ${error.message}`);
            renderValidationResult(null);
            renderCurrentState(null, storylineStatusElements);
            return null;
          }
        }

        async function publishTemplate() {
          if (!publishSettings.publishUrlTemplate) {
            setPublishStatus('Template publish API unavailable');
            return;
          }
          try {
            const validation = await validateTemplateDraft();
            if (!validation?.valid) {
              return;
            }
            const publishUrl = resolveDraftUrl(publishSettings.publishUrlTemplate, draftId);
            if (!publishUrl) {
              setPublishStatus('Publish URL unavailable');
              return;
            }
            setPublishStatus('Creating canonical storyline…');
            if (!csrfToken) {
              csrfToken = await fetchCsrfToken();
            }
            const response = await fetch(publishUrl, {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
              },
              body: JSON.stringify({
                expected_revision: draftRevision,
              }),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
              throw new Error(payload?.error?.message || payload?.error?.code || `publish_${response.status}`);
            }
            const templateId = payload?.data?.template_id || 'unknown';
            setPublishStatus(`Created canonical storyline: ${templateId}`);
          } catch (error) {
            setPublishStatus(`Creation failed: ${error.message}`);
          }
        }

        function pushAssistantLog(kind, text) {
          if (!assistantLog || !text) {
            return;
          }
          const item = document.createElement('li');
          item.className = `gm-storyline-wizard__assistant-item gm-storyline-wizard__assistant-item--${kind} gm-storyline-wizard__assistant-text`;
          item.textContent = text;
          assistantLog.appendChild(item);
          assistantLog.scrollTop = assistantLog.scrollHeight;
        }

        // The Storyline GM does not change sites or rooms: it hands the
        // author the shortcut into the editor whose GM does.
        function pushAssistantEditorShortcut(result) {
          const route = String(result?.route || '').trim();
          if (!assistantLog || !route || !['dungeon', 'room'].includes(result?.kind)) {
            return;
          }
          const editorName = result.kind === 'dungeon' ? 'Dungeon Editor' : 'Room Editor';
          const item = document.createElement('li');
          item.className = 'gm-storyline-wizard__assistant-item gm-storyline-wizard__assistant-item--assistant';
          const link = document.createElement('a');
          link.href = route;
          link.target = '_blank';
          link.rel = 'noopener';
          link.className = 'btn btn-secondary btn-sm';
          link.textContent = result.id ? `Open ${result.id} in the ${editorName}` : `Open the ${editorName}`;
          const note = document.createElement('span');
          note.textContent = ` Its GM does this work. The same shortcut is under "In this storyline".`;
          item.append(link, note);
          assistantLog.appendChild(item);
          assistantLog.scrollTop = assistantLog.scrollHeight;
          const shortcut = result.id && objectEditorLinks
            ? Array.from(objectEditorLinks.querySelectorAll('a')).find((candidate) => candidate.dataset.editorShortcut === `${result.kind}:${result.id}`)
            : null;
          if (shortcut) {
            shortcut.style.fontWeight = '700';
            shortcut.scrollIntoView({ block: 'nearest' });
          }
        }

        function pushAssistantToolCalls(toolCalls) {
          if (!assistantLog || !Array.isArray(toolCalls) || toolCalls.length === 0) {
            return;
          }
          const seen = new Set();
          toolCalls.forEach((call) => {
            const text = typeof call === 'string'
              ? call.trim()
              : String(call?.message || '').trim();
            if (!text || seen.has(text)) {
              return;
            }
            seen.add(text);
            pushAssistantLog('status', text);
          });
        }

        function pushAssistantNextSteps(nextSteps) {
          const steps = Array.isArray(nextSteps)
            ? nextSteps.filter((step) => typeof step === 'string' && step.trim() !== '')
            : [];
          if (steps.length > 0 && assistantLog) {
            const item = document.createElement('li');
            item.className = 'gm-storyline-wizard__assistant-item gm-storyline-wizard__assistant-item--assistant gm-storyline-wizard__assistant-next-steps';
            const heading = document.createElement('strong');
            heading.textContent = 'Next step';
            const list = document.createElement('ol');
            steps.forEach((step) => {
              const listItem = document.createElement('li');
              listItem.textContent = step.trim();
              list.appendChild(listItem);
            });
            item.append(heading, list);
            assistantLog.appendChild(item);
            assistantLog.scrollTop = assistantLog.scrollHeight;
          }
        }

        function formatAssistantFinding(finding) {
          if (typeof finding === 'string') {
            return finding;
          }
          if (!finding || typeof finding !== 'object') {
            return '';
          }
          return String(finding.message || finding.code || finding.path || '').trim();
        }

        function summarizeAssistantResult(result) {
          if (!result || typeof result !== 'object') {
            return 'The GM tool returned no result.';
          }
          if (typeof result.assistant_response === 'string' && result.assistant_response.trim() !== '') {
            return result.assistant_response.trim();
          }
          if (result.schema_version === 'storyline-draft-status-v1') {
            const counts = result.counts || {};
            const lines = [
              `Current state: ${Number(result.completion_percent || 0)}% complete.`,
              result.canonical_valid
                ? `Canonical validation passes with ${Number(result.warning_count || 0)} warnings.`
                : `Canonical validation has ${Number(result.error_count || 0)} errors and ${Number(result.warning_count || 0)} warnings.`,
              `Content: ${Number(counts.scenes || 0)} scenes, ${Number(counts.tasks || 0)} tasks, ${Number(counts.objectives || 0)} objectives, ${Number(counts.conflicts || 0)} conflicts, ${Number(counts.institutions || 0)} institutions, ${Number(counts.actors || 0)} actors, ${Number(counts.dungeons || 0)} dungeons, ${Number(counts.rooms || 0)} rooms, and ${Number(counts.items || 0)} items.`,
              Number(result.current_stage || 1) <= 7
                ? `Current focus: Stage ${Number(result.current_stage || 1)}.`
                : 'All seven authoring stages are complete; final review is next.',
            ];
            const missing = Array.isArray(result.missing_content) ? result.missing_content : [];
            if (missing.length > 0) {
              lines.push(`${missing.length} minimum-content checks remain open.`);
            }
            else {
              lines.push('All minimum content checks are satisfied.');
            }
            return lines.join('\n');
          }
          if (result.schema_version === 'storyline-synopsis-v1') {
            const lines = [
              result.qualifies_as_story
                ? 'This draft has enough connected structure to qualify as a story.'
                : 'There is not enough here to qualify as a complete story yet.',
            ];
            if (result.synopsis) {
              lines.push('', 'Current concept', String(result.synopsis));
            }
            if (result.objective) {
              lines.push('', 'Overall objective', String(result.objective));
            }
            const chapterCount = Number(result.chapter_count || 0);
            const sceneCount = Number(result.scene_count || 0);
            const conflictCount = Number(result.conflict_count || 0);
            lines.push(
              '',
              'Structure',
              result.act_structure_defined
                ? `${chapterCount} defined acts containing ${sceneCount} scenes.`
                : `No formal act structure is defined. The draft currently has ${chapterCount} chapter${chapterCount === 1 ? '' : 's'} containing ${sceneCount} scene${sceneCount === 1 ? '' : 's'}.`,
              `${conflictCount} explicit conflict${conflictCount === 1 ? '' : 's'} ${conflictCount === 1 ? 'is' : 'are'} defined.`,
            );
            const gaps = Array.isArray(result.gaps) ? result.gaps : [];
            if (gaps.length > 0) {
              lines.push('', `Still needed for complete story shape: ${gaps.join(', ')}.`);
            }
            return lines.join('\n');
          }
          if (result.schema_version === 'storyline-definition-preview-v1') {
            const overview = result.overview || {};
            const counts = overview.counts || {};
            const lines = [
              String(overview.title || 'Current storyline'),
              '',
              'Overall objective',
              String(overview.objective || 'No overall objective has been defined yet.'),
              '',
              'Story overview',
              String(overview.synopsis || 'No storyline synopsis has been defined yet.'),
            ];
            if (overview.opening) {
              lines.push(
                '',
                overview.quest_giver ? `Opening with ${overview.quest_giver}` : 'Opening',
                String(overview.opening),
              );
            }
            lines.push(
              '',
              'Current structure',
              `${Number(counts.scenes || 0)} scenes, ${Number(counts.tasks || 0)} tasks, ${Number(counts.conflicts || 0)} conflicts, ${Number(counts.actors || 0)} actors, ${Number(counts.dungeons || 0)} dungeons, ${Number(counts.rooms || 0)} rooms, and ${Number(counts.items || 0)} items.`,
            );
            const missing = Array.isArray(overview.missing_content) ? overview.missing_content : [];
            if (missing.length > 0) {
              lines.push('', `Still undefined: ${missing.join(', ')}.`);
            }
            return lines.join('\n');
          }
          if (typeof result.valid === 'boolean') {
            const errors = Array.isArray(result.errors) ? result.errors : [];
            const warnings = Array.isArray(result.warnings) ? result.warnings : [];
            const errorCount = Number(result.error_count ?? errors.length);
            const warningCount = Number(result.warning_count ?? warnings.length);
            const lines = [
              result.valid
                ? `Review complete: the storyline is valid with ${warningCount} warning${warningCount === 1 ? '' : 's'}.`
                : `Review complete: the storyline is not ready. It has ${errorCount} error${errorCount === 1 ? '' : 's'} and ${warningCount} warning${warningCount === 1 ? '' : 's'}.`,
            ];
            const failedStages = (Array.isArray(result.stages) ? result.stages : [])
              .filter((stage) => stage?.valid === false)
              .map((stage) => String(stage.stage || 'unknown'));
            if (failedStages.length > 0) {
              lines.push(`Failing validation stages: ${failedStages.join(', ')}.`);
            }
            const findings = [
              ...errors.map(formatAssistantFinding),
              ...(Array.isArray(result.stages) ? result.stages : [])
                .flatMap((stage) => Array.isArray(stage?.errors) ? stage.errors.map(formatAssistantFinding) : []),
              ...warnings.map(formatAssistantFinding),
              ...(Array.isArray(result.stages) ? result.stages : [])
                .flatMap((stage) => Array.isArray(stage?.warnings) ? stage.warnings.map(formatAssistantFinding) : []),
            ].filter((finding, index, all) => finding && all.indexOf(finding) === index);
            findings.slice(0, 8).forEach((finding) => lines.push(`• ${finding}`));
            if (findings.length > 8) {
              lines.push(`• ${findings.length - 8} additional finding${findings.length - 8 === 1 ? '' : 's'} are shown in the validation section.`);
            }
            return lines.join('\n');
          }
          if (typeof result.ready === 'boolean') {
            const blockers = Array.isArray(result.blockers) ? result.blockers : [];
            const lines = [
              result.ready
                ? 'Creation readiness review complete: the storyline is ready for final canonical review.'
                : `Creation readiness review complete: ${blockers.length} blocker${blockers.length === 1 ? '' : 's'} remain.`,
            ];
            blockers.slice(0, 8).forEach((blocker) => {
              const finding = formatAssistantFinding(blocker);
              if (finding) lines.push(`• ${finding}`);
            });
            return lines.join('\n');
          }
          if (Array.isArray(result.by_code)) {
            const lines = [
              `Findings review complete: ${Number(result.error_count || 0)} errors and ${Number(result.warning_count || 0)} warnings.`,
            ];
            result.by_code.slice(0, 8).forEach((group) => {
              lines.push(`• ${group.code}: ${group.count} (${group.severity})`);
            });
            return lines.join('\n');
          }
          if (typeof result.summary === 'string' && result.summary.trim() !== '') {
            return result.summary.trim();
          }
          if (typeof result.reasoning === 'string' && result.reasoning.trim() !== '') {
            return result.reasoning.trim();
          }
          if (typeof result.tool === 'string' && result.tool !== '') {
            return `${result.tool} completed.`;
          }
          return `GM task completed.\n${JSON.stringify(result, null, 2)}`;
        }

        function restoreConversation(messages) {
          if (conversationRestored || !Array.isArray(messages)) {
            return;
          }
          conversationRestored = true;
          messages.forEach((message) => {
            const metadata = message?.metadata || {};
            if (metadata.editor_gm_role === 'author') {
              pushAssistantLog('user', String(message.message || ''));
              return;
            }
            const response = metadata.editor_gm_response;
            const result = response?.tool_result;
            if (!result || typeof result !== 'object') {
              return;
            }
            pushAssistantToolCalls(response?.tool_calls || result?.tool_calls || []);
            if (result.intent === 'clarification') {
              const options = Array.isArray(result.options) ? result.options : [];
              const lines = [String(result.question || 'Choose a direction.')];
              options.forEach((option, index) => {
                lines.push(`${index + 1}. ${String(option.label || '')}: ${String(option.description || '')}`);
              });
              pushAssistantLog('assistant', lines.join('\n'));
            }
            else {
              pushAssistantLog('assistant', summarizeAssistantResult(result));
              pushAssistantNextSteps(result.next_steps);
            }
          });
        }

        function showAssistantChoices(question, options) {
          if (!assistantLog || !Array.isArray(options) || options.length !== 3) {
            throw new Error('assistant_three_options_required');
          }
          const item = document.createElement('li');
          item.className = 'gm-storyline-wizard__assistant-item gm-storyline-wizard__assistant-item--assistant';
          const prompt = document.createElement('p');
          prompt.textContent = question;
          item.appendChild(prompt);
          options.forEach((option, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'gm-storyline-wizard__assistant-choice';
            const label = document.createElement('strong');
            label.textContent = `${index + 1}. ${String(option.label || '')}`;
            const description = document.createElement('span');
            description.textContent = String(option.description || '');
            const tradeoff = document.createElement('small');
            tradeoff.textContent = String(option.tradeoff || '');
            button.append(label, description, tradeoff);
            button.addEventListener('click', async () => {
              item.querySelectorAll('button').forEach((choice) => {
                choice.disabled = true;
              });
              const succeeded = await askAssistant(`I select option ${index + 1}, ${option.label}. Use this direction: ${option.description}`);
              if (!succeeded) {
                item.querySelectorAll('button').forEach((choice) => {
                  choice.disabled = false;
                });
              }
            });
            item.appendChild(button);
          });
          assistantLog.appendChild(item);
          assistantLog.scrollTop = assistantLog.scrollHeight;
        }

        async function initAssistant(announce = true) {
          if (!assistantPanel || !assistantSettings.describeUrlTemplate) {
            return;
          }
          if (!draftId) {
            setAssistantStatus('Create or load a draft to activate assistant context.');
            return;
          }
          if (!draftCanEdit) {
            setAssistantStatus('Open the current working draft to use the GM.');
            return;
          }
          const describeUrl = resolveDraftUrl(assistantSettings.describeUrlTemplate, draftId);
          if (!describeUrl) {
            setAssistantStatus('Assistant context URL unavailable');
            return;
          }
          try {
            setAssistantStatus('Loading assistant context…');
            if (!csrfToken) {
              csrfToken = await fetchCsrfToken();
            }
            const response = await fetch(`${describeUrl}?profile=editing`, {
              credentials: 'same-origin',
              signal: AbortSignal.timeout(60000),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
              throw new Error(payload?.error?.code || `describe_${response.status}`);
            }
            setAssistantStatus(draftCanEdit
              ? ''
              : 'This published snapshot is read-only. Open its working draft to continue editing.');
            renderCurrentState(payload?.data?.context_snapshot?.current_state, storylineStatusElements);
            const activeWork = payload?.data?.context_snapshot?.authoring_workflow?.bounded_work?.active_work;
            if (activeWork) {
              applyWorkActionButtons(activeWork);
            } else {
              resumeWorkButton.hidden = true;
              stopWorkButton.hidden = true;
              skipWorkButton.hidden = true;
            }
            const closedWork = payload?.data?.context_snapshot?.authoring_workflow?.bounded_work?.closed_work;
            if (closedWork) {
              const notDone = (closedWork.not_done || []).filter(Boolean);
              setAssistantStatus(`Saved work from revision ${closedWork.job_revision} was closed: the draft has changed since (now revision ${closedWork.draft_revision}). What it saved is kept.${notDone.length ? ` Not done: ${notDone.join('; ')}.` : ''}`);
            } else if (activeWork?.status === 'paused') {
              setAssistantStatus(summarizeWorkFailure(activeWork));
            }
            renderValidationResult(payload?.data?.context_snapshot?.validation_summary);
            await renderNarrativeAnalysis(payload?.data?.context_snapshot?.narrative_analysis);
            restoreConversation(payload?.data?.conversation);
            if (announce) {
              const toolCount = Number(payload?.data?.context_snapshot?.tools?.tool_count || 0);
              pushAssistantLog('system', `Assistant connected to storyline builder GM tools (${toolCount}).`);
            }
          } catch (error) {
            setAssistantStatus('');
            pushAssistantLog('error', `Assistant setup failed: ${error.message}`);
          }
        }

        async function sendAssistantIntent(intent) {
          const executeUrl = resolveDraftUrl(assistantSettings.executeUrlTemplate, draftId);
          if (!executeUrl) {
            throw new Error('assistant_execute_url_unavailable');
          }
          if (!csrfToken) {
            csrfToken = await fetchCsrfToken();
          }
          const response = await fetch(executeUrl, {
            method: 'POST',
            credentials: 'same-origin',
            signal: AbortSignal.timeout(120000),
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify(buildEditorGmRequest({
              toolId: 'storyline_builder',
              draftId,
              intent,
            })),
          });
          const payload = await response.json().catch(() => ({}));
          if (!response.ok) {
            const finding = Array.isArray(payload?.error?.findings)
              ? payload.error.findings.find((item) => item?.message)
              : null;
            const baseError = payload?.error?.message || payload?.error?.code || `http_${response.status}`;
            const err = finding?.message ? `${baseError}: ${finding.message}` : baseError;
            throw new Error(String(err));
          }
          return payload;
        }

        async function askAssistant(message) {
          if (!assistantSettings.executeUrlTemplate || !assistantLog || assistantBusy) {
            return false;
          }
          const prompt = String(message || '').trim();
          if (!prompt) {
            return false;
          }
          pushAssistantLog('user', prompt);
          setAssistantBusy(true);
          setAssistantStatus('Waiting on the Game Master Agent to respond…');
          let succeeded = false;
          let lastWorkProgress = null;
          try {
            if (!draftId) {
              await createDraft(true);
            }
            if (!draftId) {
              throw new Error('assistant_draft_required');
            }
            if (draftDirty) {
              setAssistantStatus('Saving your changes before the Game Master Agent responds…');
              await saveDraft();
              setAssistantStatus('Waiting on the Game Master Agent to respond…');
            }
            let payload = await sendAssistantIntent({
              type: 'natural_language',
              utterance: prompt,
              author_message: prompt,
            });
            let batches = 0;
            while (payload?.data?.tool_result?.work_progress?.status === 'running') {
              const progress = payload.data.tool_result.work_progress;
              lastWorkProgress = progress;
              const declaredTotal = Math.max(1, Number(progress?.total_steps || 1));
              if (++batches > declaredTotal) {
                throw new Error('Saved work exceeded its declared continuation limit.');
              }
              applyWorkActionButtons(progress);
              const saved = payload.data.tool_result.saved;
              if (saved) {
                draftRevision = Number(saved.revision);
                draftDirty = false;
                hydrateFromPayload(saved.payload);
                refreshPreview();
                setAssistantBusy(true);
                setDraftStatus(`GM saved batch ${progress.step}/${progress.total_steps} (rev ${draftRevision})`);
              }
              renderCurrentState(payload.data.context_snapshot?.current_state, storylineStatusElements);
              setAssistantStatus(`Game Master Agent saved ${progress.step}/${progress.total_steps} batches. Continuing the next batch…`);
              payload = await sendAssistantIntent({
                type: 'tool_call',
                tool_name: 'apply_storyline_work',
                arguments: {
                  job_id: progress.job_id,
                  step: progress.step,
                  expected_revision: progress.expected_revision,
                },
              });
            }
            const finalProgress = payload?.data?.tool_result?.work_progress;
            if (finalProgress) {
              lastWorkProgress = finalProgress;
              applyWorkActionButtons(finalProgress);
            } else {
              resumeWorkButton.hidden = true;
              stopWorkButton.hidden = true;
              skipWorkButton.hidden = true;
            }
            const result = payload?.data?.tool_result || {};
            const notes = payload?.data?.messages || [];
            pushAssistantToolCalls(payload?.data?.tool_calls || result?.tool_calls || []);
            if (result?.intent === 'clarification') {
              showAssistantChoices(String(result.question || 'Choose a direction.'), result.options || []);
              setAssistantStatus('Waiting on your decision before the Game Master Agent continues.');
            } else {
              const saved = result?.saved;
              if (saved && typeof saved === 'object') {
                draftRevision = Number(saved.revision || draftRevision);
                draftDirty = false;
                hydrateFromPayload(saved.payload || {});
                refreshPreview();
                setDraftStatus(`GM updated draft ${draftId.slice(0, 8)} (rev ${draftRevision})`);
              }
              if (result?.publication_receipt) {
                draftLifecycle = 'published';
                draftTemplateId = String(result.publication_receipt.template_id);
                draftCanEdit = false;
                draftDirty = false;
                applyDraftOwnershipState();
                setDraftStatus(`Published storyline ${draftTemplateId}; final signoff recorded.`);
              }
              renderCurrentState(payload?.data?.context_snapshot?.current_state, storylineStatusElements);
              if (result?.work_progress?.status === 'paused') {
                setAssistantStatus(summarizeWorkFailure(result.work_progress));
              } else {
                setAssistantStatus('');
              }
            }
            if (result?.intent !== 'clarification') {
              pushAssistantLog('assistant', summarizeAssistantResult(result));
              pushAssistantEditorShortcut(result);
              pushAssistantNextSteps(result?.next_steps);
            }
            if (result?.intent !== 'clarification' && Array.isArray(notes)) {
              notes.filter((notice) => notice?.level !== 'success').slice(0, 2).forEach((notice) => {
                if (notice?.text) {
                  pushAssistantLog('assistant', String(notice.text));
                }
              });
            }
            succeeded = true;
          } catch (error) {
            await initAssistant(false);
            if (lastWorkProgress) {
              applyWorkActionButtons(lastWorkProgress);
            }
            setAssistantStatus('');
            pushAssistantLog('error', `Assistant request failed: ${error.message}`);
          } finally {
            setAssistantBusy(false);
          }
          return succeeded;
        }

        function activateStep(step) {
          const next = Math.max(1, Math.min(maxStep, Number(step) || 1));
          activeStep = next;

          stepPanels.forEach((panel) => {
            const panelStep = Number(panel.getAttribute('data-step-panel') || '0');
            panel.hidden = panelStep !== activeStep;
          });

          stepTabs.forEach((tab) => {
            const tabStep = Number(tab.getAttribute('data-step-tab') || '0');
            tab.disabled = false;
            tab.setAttribute('aria-disabled', 'false');
            tab.classList.toggle('is-active', tabStep === activeStep);
            tab.setAttribute('aria-selected', tabStep === activeStep ? 'true' : 'false');
          });

          if (titleEl) {
            titleEl.textContent = `Step ${activeStep} · ${stepLabels[activeStep] || 'Wizard'}`;
          }
          if (metaEl) {
            metaEl.textContent = `Step ${activeStep} of ${maxStep}`;
          }
          if (activeStep === maxStep) {
            refreshPreview();
          }
        }

        stepTabs.forEach((tab) => {
          tab.disabled = false;
          tab.setAttribute('aria-disabled', 'false');
          tab.addEventListener('click', (event) => {
            event.preventDefault();
            activateStep(tab.getAttribute('data-step-tab'));
          });
        });

        wizard.querySelectorAll('[data-add-row]').forEach((button) => {
          button.addEventListener('click', (event) => {
            event.preventDefault();
            const collection = button.getAttribute('data-add-row') || '';
            const row = addCollectionRow(collection);
            markDraftDirty();
            refreshObjectEditorLinks();
            row?.querySelector('input, select, textarea')?.focus();
            const label = button.closest('.gm-storyline-wizard__collection-header')?.querySelector('h4')?.textContent?.trim() || 'collection';
            setDraftStatus(`Added a row to ${label}. Unsaved changes.`);
            if (activeStep === maxStep) {
              refreshPreview();
            }
          });
        });

        wizard.addEventListener('click', (event) => {
          const target = event.target instanceof Element ? event.target : null;
          if (!target) {
            return;
          }
          const tab = target.closest('[data-step-tab]');
          if (tab) {
            event.preventDefault();
            activateStep(tab.getAttribute('data-step-tab'));
            return;
          }
          const next = target.closest('[data-step-next]');
          if (next) {
            event.preventDefault();
            activateStep(next.getAttribute('data-step-next'));
            return;
          }
          const back = target.closest('[data-step-back]');
          if (back) {
            event.preventDefault();
            activateStep(back.getAttribute('data-step-back'));
            return;
          }
          const addSub = target.closest('[data-add-subrow]');
          const removeSub = target.closest('[data-remove-subrow]');
          if (addSub || removeSub) {
            event.preventDefault();
            if (addSub) {
              const container = addSub.closest('[data-subrows]');
              addSubRow(container.closest('.gm-storyline-wizard__row'), container.getAttribute('data-subrows')).querySelector('select').focus();
            } else {
              removeSub.closest('.gm-storyline-wizard__subrow').remove();
            }
            markDraftDirty();
            return;
          }
          const removeRow = target.closest('[data-remove-row]');
          if (removeRow) {
            event.preventDefault();
            const row = removeRow.closest('.gm-storyline-wizard__row');
            row?.remove();
            markDraftDirty();
            refreshObjectEditorLinks();
            if (activeStep === maxStep) {
              refreshPreview();
            }
            return;
          }
          const moveUp = target.closest('[data-row-up]');
          const moveDown = target.closest('[data-row-down]');
          if (moveUp || moveDown) {
            event.preventDefault();
            const row = target.closest('.gm-storyline-wizard__row');
            const container = row?.parentElement;
            const sibling = moveUp ? row?.previousElementSibling : row?.nextElementSibling;
            if (row && container && sibling) {
              if (moveUp) {
                container.insertBefore(row, sibling);
              } else {
                container.insertBefore(sibling, row);
              }
              refreshRowOrder(container);
              markDraftDirty();
            }
            return;
          }
          const duplicateRow = target.closest('[data-row-duplicate]');
          if (duplicateRow) {
            event.preventDefault();
            const row = duplicateRow.closest('.gm-storyline-wizard__row');
            const collection = duplicateRow.getAttribute('data-row-duplicate') || '';
            if (row && collections[collection]) {
              duplicateCollectionRow(collection, row);
              markDraftDirty();
              refreshObjectEditorLinks();
            }
            return;
          }
          const assistantPrompt = target.closest('[data-gm-assistant-prompt]');
          if (assistantPrompt) {
            event.preventDefault();
            askAssistant(assistantPrompt.getAttribute('data-gm-assistant-prompt') || '');
            return;
          }
          const toolLink = target.closest('a.gm-storyline-wizard__tool-chip');
          if (toolLink && draftDirty) {
            event.preventDefault();
            const destination = new URL(toolLink.href, window.location.href);
            saveDraft().then(() => {
              destination.searchParams.set('storyline_draft_id', draftId);
              destination.searchParams.set('return', window.location.href);
              window.location.assign(destination.toString());
            }).catch(() => {});
          }
        });

        fields.forEach((field) => {
          field.addEventListener('input', () => {
            markDraftDirty();
            if (activeStep === maxStep) {
              refreshPreview();
            }
          });
        });

        // Quest cards: one per quest, showing and editing goal -> ways ->
        // results. Every control is bound to a field of the underlying row, so
        // the rows remain the single thing that is saved.
        const questOutlineEl = wizard.querySelector('[data-quest-outline]');
        const outlineRows = (collection) => Array.from(wizard.querySelectorAll(`[data-rows="${collection}"] .gm-storyline-wizard__row`));
        const rowValue = (row, key) => (row.querySelector(`[data-col-field="${key}"]`)?.value || '').trim();
        const setRowValue = (row, key, value) => {
          const field = row.querySelector(`[data-col-field="${key}"]`);
          if (field) {
            field.value = value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
          }
        };
        const outlineEl = (tag, className, text) => {
          const el = document.createElement(tag);
          if (className) el.className = className;
          if (text != null) el.textContent = text;
          return el;
        };
        const outlineButton = (text, onClick, className = 'btn btn-secondary btn-sm') => {
          const button = outlineEl('button', className, text);
          button.type = 'button';
          button.addEventListener('click', onClick);
          return button;
        };
        const showRow = (row) => {
          const details = row.closest('details');
          if (details) details.open = true;
          row.scrollIntoView({ behavior: 'smooth', block: 'center' });
          row.querySelector('input, select, textarea')?.focus({ preventScroll: true });
        };
        // A labelled control in a card that edits one field of one row.
        const boundField = (labelText, row, key, focusKey, options = {}) => {
          const label = outlineEl('label', `gm-storyline-wizard__card-field${options.wide ? ' gm-storyline-wizard__card-field--wide' : ''}`);
          label.append(outlineEl('span', '', labelText));
          let control;
          if (options.choices) {
            control = document.createElement('select');
            control.className = 'form-select';
            options.choices.forEach(([value, text]) => control.add(new Option(text, value)));
          } else {
            control = document.createElement('input');
            control.type = options.type || 'text';
            control.className = 'form-text';
            if (options.placeholder) control.placeholder = options.placeholder;
            if (options.type === 'number') control.min = '0';
          }
          control.value = rowValue(row, key);
          control.dataset.outlineFocus = focusKey;
          control.addEventListener('input', () => {
            setRowValue(row, key, control.value);
            options.onInput?.(control.value);
          });
          label.append(control);
          if (options.help) label.append(outlineEl('small', 'gm-storyline-wizard__outline-meta', options.help));
          return label;
        };
        const visibilityChoices = [
          ['known', 'Known to players from the start'],
          ['discoverable', 'Hidden until discovered'],
          ['secret', 'Secret until used'],
        ];
        function renderQuestOutline() {
          if (!questOutlineEl) {
            return;
          }
          const focusKey = questOutlineEl.contains(document.activeElement) ? document.activeElement.dataset.outlineFocus : null;
          const questRows = outlineRows('task_entries');
          const choiceRows = outlineRows('branch_choice_entries');
          const questIds = questRows.map((row) => rowValue(row, 'task_id')).filter(Boolean);
          const effectCount = (choiceId) => ['branch_actor_effect_entries', 'branch_environment_effect_entries', 'branch_storyline_effect_entries']
            .reduce((total, collection) => total + outlineRows(collection).filter((row) => choiceId !== '' && rowValue(row, 'choice_id') === choiceId).length, 0);
          questOutlineEl.replaceChildren();

          questRows.forEach((questRow, questIndex) => {
            const questId = rowValue(questRow, 'task_id');
            const card = outlineEl('article', 'gm-storyline-wizard__outline-card');
            const header = document.createElement('header');
            header.append(
              outlineEl('strong', '', questId || '(new quest: give it an ID)'),
              outlineEl('span', 'gm-storyline-wizard__outline-meta', [rowValue(questRow, 'chapter_id'), rowValue(questRow, 'scene_id')].filter(Boolean).join(' / ')),
              outlineButton('All fields', () => showRow(questRow)),
            );
            card.append(header);

            const goalText = rowValue(questRow, 'goal_text');
            const questFields = outlineEl('div', 'gm-storyline-wizard__card-fields');
            if (!questId) {
              questFields.append(boundField('Quest ID', questRow, 'task_id', `q${questIndex}:task_id`, { placeholder: 'clear-the-mill' }));
            }
            questFields.append(
              boundField('Goal — what the players are trying to achieve', questRow, 'goal_text', `q${questIndex}:goal_text`, {
                wide: true,
                placeholder: 'End the goblin threat to the mill',
                help: goalText ? '' : 'Leave empty for a quest that is finished by completing all of its objectives.',
              }),
            );
            if (goalText) {
              questFields.append(
                boundField('GM notes — never shown to players', questRow, 'gm_notes', `q${questIndex}:gm_notes`, { wide: true }),
                boundField('Unplanned ways', questRow, 'open_resolution', `q${questIndex}:open_resolution`, {
                  choices: [['false', 'Only the ways below count'], ['true', 'An unplanned way may also count (GM decides)']],
                }),
              );
            }
            card.append(questFields);

            const own = questId ? choiceRows.filter((row) => rowValue(row, 'source_task_id') === questId) : [];
            const ways = own.filter((row) => rowValue(row, 'approach_visibility') !== '');
            const decisions = own.filter((row) => rowValue(row, 'approach_visibility') === '');
            const leadsToChoices = [
              ...questIds.filter((id) => id !== questId).map((id) => [id, `Leads to ${id}`]),
              ['', 'Ends the storyline'],
            ];

            if (goalText || ways.length) {
              card.append(outlineEl('h5', '', 'Ways of achieving it (any one)'));
              ways.forEach((wayRow) => {
                const index = choiceRows.indexOf(wayRow);
                const way = outlineEl('div', `gm-storyline-wizard__way gm-storyline-wizard__way--${rowValue(wayRow, 'approach_visibility')}`);
                const fields = outlineEl('div', 'gm-storyline-wizard__card-fields');
                fields.append(
                  boundField('Way, as players read it', wayRow, 'label', `c${index}:label`, { wide: true, placeholder: 'Persuade Chief Grubnak to leave' }),
                  boundField('Who knows about it', wayRow, 'approach_visibility', `c${index}:approach_visibility`, { choices: visibilityChoices }),
                );
                if (rowValue(wayRow, 'approach_visibility') === 'discoverable') {
                  fields.append(boundField('Discovered when', wayRow, 'approach_reveal', `c${index}:approach_reveal`, {
                    placeholder: 'event:interact:captured_goblin',
                    help: 'objective:ID (that objective completes) or event:TYPE:REF (for example event:interact:actor-id).',
                  }));
                }
                fields.append(
                  boundField('Achieved by objectives', wayRow, 'approach_objective_ids', `c${index}:approach_objective_ids`, {
                    placeholder: 'blank: the Game Master judges it',
                    help: 'Objective IDs of this quest, comma separated.',
                  }),
                  boundField('Outcome players read afterwards', wayRow, 'approach_outcome_label', `c${index}:approach_outcome_label`, { wide: true, placeholder: 'The goblins left peacefully' }),
                  boundField('Reward XP', wayRow, 'approach_reward_xp', `c${index}:approach_reward_xp`, { type: 'number', placeholder: 'quest reward' }),
                  boundField('Reward gold', wayRow, 'approach_reward_gold', `c${index}:approach_reward_gold`, { type: 'number', placeholder: 'quest reward' }),
                );
                const leadsTo = boundField('Then', wayRow, 'target_task_id', `c${index}:target_task_id`, {
                  choices: leadsToChoices,
                  onInput: (value) => setRowValue(wayRow, 'terminal', value === '' ? 'true' : 'false'),
                });
                leadsTo.querySelector('select').value = rowValue(wayRow, 'terminal') === 'true' ? '' : rowValue(wayRow, 'target_task_id');
                fields.append(leadsTo);
                way.append(fields);

                const actions = outlineEl('div', 'gm-storyline-wizard__card-actions');
                const consequences = effectCount(rowValue(wayRow, 'choice_id'));
                actions.append(
                  outlineEl('span', 'gm-storyline-wizard__outline-meta', consequences ? `${consequences} consequence(s) authored` : 'No consequences authored'),
                  outlineButton('All fields', () => showRow(wayRow)),
                  outlineButton('Not a way of achieving the goal', () => { setRowValue(wayRow, 'approach_visibility', ''); renderQuestOutline(); }),
                  outlineButton('Delete', () => { wayRow.remove(); markDraftDirty(); }),
                );
                way.append(actions);
                card.append(way);
              });
              if (rowValue(questRow, 'open_resolution') === 'true') {
                card.append(outlineEl('p', 'gm-storyline-wizard__outline-meta', 'An unplanned way may also count; the Game Master decides.'));
              }

              const problems = [];
              const open = rowValue(questRow, 'open_resolution') === 'true';
              if (goalText && !ways.length && !open) {
                problems.push('This goal has no way of achieving it. Add a way, or allow an unplanned way.');
              }
              if (goalText && ways.length && !open && !ways.some((row) => rowValue(row, 'approach_visibility') === 'known')) {
                problems.push('Players start with no known way forward. Make one way known, or allow an unplanned way.');
              }
              if (!goalText && ways.length) {
                problems.push('A choice is marked as a way of achieving the goal, but this quest has no goal.');
              }
              ways.forEach((row) => {
                const name = rowValue(row, 'label') || rowValue(row, 'choice_id') || 'A way';
                if (!rowValue(row, 'label')) problems.push('A way has no text for players.');
                if (rowValue(row, 'approach_visibility') === 'discoverable' && !rowValue(row, 'approach_reveal')) problems.push(`“${name}” is hidden until discovered but has no “discovered when”.`);
                if (!rowValue(row, 'approach_outcome_label')) problems.push(`“${name}” has no outcome text for players.`);
              });
              problems.forEach((problem) => card.append(outlineEl('p', 'gm-storyline-wizard__outline-problem', `⚠ ${problem}`)));
            }

            if (questId && goalText) {
              card.append(outlineButton('Add a way', () => {
                const nextQuestId = questIds[questIds.indexOf(questId) + 1] || '';
                const decisionId = ways.length ? rowValue(ways[0], 'decision_id') : `${questId}-ways`;
                addCollectionRow('branch_choice_entries', {
                  decision_id: decisionId,
                  source_task_id: questId,
                  trigger_type: 'gm_decision',
                  trigger_interaction: 'other',
                  trigger_condition: 'The party achieves the goal this way.',
                  trigger_source_id: questId,
                  target_task_id: nextQuestId,
                  terminal: nextQuestId ? 'false' : 'true',
                  approach_visibility: 'known',
                });
                markDraftDirty();
              }, 'btn btn-primary btn-sm'));
            }

            if (decisions.length) {
              card.append(outlineEl('h5', '', 'Other story decisions on this quest'));
              decisions.forEach((choiceRow) => {
                const line = outlineEl('div', 'gm-storyline-wizard__card-actions');
                line.append(
                  outlineEl('strong', '', rowValue(choiceRow, 'label') || rowValue(choiceRow, 'choice_id') || '(unnamed choice)'),
                  outlineEl('span', 'gm-storyline-wizard__outline-meta', `decision ${rowValue(choiceRow, 'decision_id') || '?'}; ${rowValue(choiceRow, 'terminal') === 'true' ? 'ends the storyline' : `leads to ${rowValue(choiceRow, 'target_task_id') || '?'}`}`),
                  outlineButton('All fields', () => showRow(choiceRow)),
                );
                if (goalText) {
                  line.append(outlineButton('Make this a way of achieving the goal', () => { setRowValue(choiceRow, 'approach_visibility', 'known'); renderQuestOutline(); }));
                }
                card.append(line);
              });
            }
            questOutlineEl.append(card);
          });

          questOutlineEl.append(outlineButton('Add a quest', () => {
            const row = addCollectionRow('task_entries');
            markDraftDirty();
            renderQuestOutline();
            questOutlineEl.querySelector(`[data-outline-focus="q${outlineRows('task_entries').indexOf(row)}:task_id"]`)?.focus();
          }, 'btn btn-primary btn-sm'));

          if (focusKey) {
            questOutlineEl.querySelector(`[data-outline-focus="${focusKey}"]`)?.focus();
          }
        }
        let questOutlineTimer = null;
        const scheduleQuestOutline = () => {
          clearTimeout(questOutlineTimer);
          questOutlineTimer = setTimeout(renderQuestOutline, 150);
        };
        ['task_entries', 'branch_choice_entries', 'branch_actor_effect_entries', 'branch_environment_effect_entries', 'branch_storyline_effect_entries'].forEach((collection) => {
          const target = wizard.querySelector(`[data-rows="${collection}"]`);
          if (target) {
            new MutationObserver(scheduleQuestOutline).observe(target, { childList: true });
          }
        });
        // A finished edit (leaving a field, picking an option) redraws the
        // cards; typing does not, so the field being typed in keeps its caret.
        wizard.addEventListener('change', scheduleQuestOutline);
        renderQuestOutline();

        wizard.addEventListener('input', (event) => {
          // Typing in a row redraws the cards; typing in a card must not.
          if (!(questOutlineEl && questOutlineEl.contains(document.activeElement))) {
            scheduleQuestOutline();
          }
          if (event.target instanceof Element && event.target.closest('.gm-storyline-wizard__row') && activeStep === maxStep) {
            refreshPreview();
          }
          if (event.target instanceof Element && event.target.closest('.gm-storyline-wizard__row')) {
            markDraftDirty();
            refreshObjectEditorLinks();
          }
        });

        draftCreateBtn?.addEventListener('click', () => createDraft(true));
        draftPicker?.addEventListener('change', () => {
          const selectedValue = String(draftPicker.value || '');
          if (selectedValue.startsWith('draft:')) {
            loadDraftById(selectedValue.slice(6));
          } else if (selectedValue.startsWith('template:')) {
            createDraft(false, selectedValue.slice(9));
          }
        });
        draftRevisionPicker?.addEventListener('change', () => {
          const selectedRevision = Number(draftRevisionPicker.value || 0);
          if (selectedRevision > 0) {
            loadDraftRevision(selectedRevision);
          } else if (draftId) {
            loadDraftById(draftId);
          }
        });
        draftDeleteBtn?.addEventListener('click', deleteCurrentDraft);
        draftSaveBtn?.addEventListener('click', async () => {
          await saveDraft();
          await initAssistant(false);
        });
        validateBtn?.addEventListener('click', () => validateTemplateDraft());
        publishBtn?.addEventListener('click', () => publishTemplate());

        if (assistantSend && assistantInput) {
          const submitAssistantMessage = () => {
            if (assistantBusy) {
              return;
            }
            const message = assistantInput.value;
            if (!message.trim()) {
              return;
            }
            assistantInput.value = '';
            askAssistant(message);
          };
          assistantSend.addEventListener('click', submitAssistantMessage);
          assistantInput.addEventListener('keydown', (event) => {
            if (!shouldSubmitEditorGmKey(event)) {
              return;
            }
            event.preventDefault();
            submitAssistantMessage();
          });
        }

        Object.entries(initialFields).forEach(([key, value]) => {
          const field = wizard.querySelector(`[data-field="${key}"]`);
          if (!field) {
            return;
          }
          if (Array.isArray(value)) {
            field.value = value.join('\n');
            return;
          }
          field.value = value == null ? '' : String(value);
        });

        Object.entries(legacyToCollection).forEach(([legacyKey, config]) => {
          const raw = initialFields[legacyKey];
          if (typeof raw !== 'string' || raw.trim() === '') {
            return;
          }
          parseLegacyDelimitedRows(raw, config.keys).forEach((entry) => addCollectionRow(config.collection, entry));
        });
        Object.keys(collections).forEach((collection) => {
          const exists = wizard.querySelector(`[data-rows="${collection}"] .gm-storyline-wizard__row`);
          if (!exists) {
            addCollectionRow(collection, {});
          }
        });

        initAssistant();
        setDraftStatus('No draft loaded');
        setPublishStatus('');
        renderValidationResult(null);
        refreshPreview();
        refreshObjectEditorLinks();
        const params = new URLSearchParams(window.location.search);
        refreshDraftPicker().catch((error) => setDraftStatus(`Draft list failed: ${error.message}`));
        const existingDraftId = (params.get('draft_id') || '').trim();
        const requestedRevision = Number(params.get('revision') || 0);
        if (existingDraftId) {
          loadDraftById(existingDraftId, requestedRevision > 0 ? requestedRevision : null);
        } else if ((params.get('template_id') || '').trim() && draftsSettings.createUrl) {
          createDraft();
        }

        activateStep(1);
        window.addEventListener('beforeunload', (event) => {
          if (!draftDirty) {
            return;
          }
          event.preventDefault();
          event.returnValue = '';
        });
      });
    },
  };
})(Drupal, once, drupalSettings);
