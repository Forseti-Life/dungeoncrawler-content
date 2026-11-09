import {
  buildEditorGmRequest,
  shouldSubmitEditorGmKey,
} from './v2/editor/EditorGmChatClient.js?v=20261023-gm-chat-2';

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
      storylineStatusFocus.textContent = Number(current.current_stage || 1) <= 6
        ? `Stage ${Number(current.current_stage || 1)} of 6`
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
        const stepTabs = Array.from(document.querySelectorAll('[data-step-tab]'));
        const stepPanels = Array.from(wizard.querySelectorAll('[data-step-panel]'));
        const titleEl = document.querySelector('[data-gm-storyline-active-step-title]');
        const metaEl = document.querySelector('[data-gm-storyline-active-step-meta]');
        const previewEl = wizard.querySelector('[data-gm-blueprint-preview]');
        const copyBtn = wizard.querySelector('[data-gm-blueprint-copy]');
        const validateBtn = wizard.querySelector('[data-gm-template-validate]');
        const publishBtn = wizard.querySelector('[data-gm-template-publish]');
        const publishStatusEl = wizard.querySelector('[data-gm-template-publish-status]');
        const validationSummaryEl = wizard.querySelector('[data-gm-template-validation-summary]');
        const validationListEl = wizard.querySelector('[data-gm-template-validation-list]');
        const analysisSummaryEl = wizard.querySelector('[data-gm-template-analysis-summary]');
        const analysisGraphEl = wizard.querySelector('[data-gm-template-analysis-graph]');
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
          task_entries: ['task_id', 'chapter_id', 'scene_id', 'status'],
          branch_choice_entries: ['decision_id', 'source_task_id', 'choice_id', 'label', 'trigger_type', 'trigger_interaction', 'trigger_condition', 'trigger_source_id', 'effect_summary', 'target_task_id', 'target_scene_id', 'terminal'],
          branch_actor_effect_entries: ['choice_id', 'actor_id', 'interaction', 'reaction', 'effect', 'state_changes'],
          branch_environment_effect_entries: ['choice_id', 'target_type', 'target_id', 'effect', 'state_changes'],
          branch_storyline_effect_entries: ['choice_id', 'target_type', 'target_id', 'source_actor_id', 'operation', 'effect', 'follow_up_required', 'state_changes'],
          objective_entries: ['objective_id', 'kind', 'metric', 'description', 'required'],
          actor_entries: ['contact_id', 'actor_id', 'role', 'display_name', 'motivation', 'dialogue', 'knowledge', 'attitude', 'availability', 'chapter_id', 'scene_id', 'location_id'],
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
        let draftDirty = false;
        let draftCanEdit = false;
        let draftIsOwner = false;
        let draftLibrary = new Map();
        let assistantBusy = false;
        let conversationRestored = false;

        const stepLabels = {
          1: 'Title, Description, Premise',
          2: 'Journal/Task Objective Flow',
          3: 'Actors Framework',
          4: 'Dungeons & Rooms Framework',
          5: 'Items Framework',
          6: 'Composition & Validation',
          7: 'Review Canonical Object',
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
            validationSummaryEl.textContent = `Validation stages: ${stages.length} • passed: ${stages.length - stageErrors.length} • failed: ${stageErrors.length} • warnings: ${warningCount}`;
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

        function renderNarrativeAnalysis(analysis) {
          if (analysisSummaryEl) {
            analysisSummaryEl.textContent = '';
          }
          if (analysisGraphEl) {
            analysisGraphEl.innerHTML = '';
          }
          if (!analysis || typeof analysis !== 'object' || !analysisGraphEl) {
            return;
          }
          const metrics = analysis.metrics || {};
          if (analysisSummaryEl) {
            analysisSummaryEl.textContent = `${Number(metrics.chapter_count || 0)} chapters • ${Number(metrics.scene_count || 0)} scenes • ${Number(metrics.actor_count || 0)} actors • ${Number(metrics.conflict_count || 0)} conflicts • ${Number(metrics.branch_decision_count || 0)} decisions • ${Number(metrics.branch_choice_count || 0)} choices`;
          }
          const nodes = Array.isArray(analysis?.graph?.nodes) ? analysis.graph.nodes : [];
          const edges = Array.isArray(analysis?.graph?.edges) ? analysis.graph.edges : [];
          if (nodes.length === 0) {
            analysisGraphEl.textContent = 'No graph nodes were generated.';
            return;
          }

          const namespace = 'http://www.w3.org/2000/svg';
          const groups = {
            chapter: nodes.filter((node) => node?.type === 'chapter'),
            scene: nodes.filter((node) => node?.type === 'scene'),
            actor: nodes.filter((node) => node?.type === 'actor'),
            conflict: nodes.filter((node) => node?.type === 'conflict'),
            quest: nodes.filter((node) => node?.type === 'quest'),
            decision: nodes.filter((node) => node?.type === 'decision'),
            choice: nodes.filter((node) => node?.type === 'choice'),
            environment: nodes.filter((node) => node?.type === 'environment'),
            storyline_implication: nodes.filter((node) => node?.type === 'storyline_implication'),
            terminal_outcome: nodes.filter((node) => node?.type === 'terminal_outcome'),
          };
          const maxGroupSize = Math.max(1, ...Object.values(groups).map((group) => group.length));
          const width = Math.max(720, 180 * maxGroupSize);
          const height = 990;
          const positions = new Map();
          const bands = [
            ['chapter', 55],
            ['scene', 155],
            ['actor', 275],
            ['conflict', 375],
            ['quest', 475],
            ['decision', 575],
            ['choice', 675],
            ['environment', 775],
            ['storyline_implication', 855],
            ['terminal_outcome', 925],
          ];
          bands.forEach(([type, y]) => {
            const group = groups[type] || [];
            group.forEach((node, index) => {
              positions.set(String(node.id), {
                x: ((index + 1) * width) / (group.length + 1),
                y,
              });
            });
          });

          const svg = document.createElementNS(namespace, 'svg');
          svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
          svg.setAttribute('aria-hidden', 'true');
          svg.classList.add('gm-storyline-wizard__analysis-svg');
          const colors = {
            chapter: '#f0c87a',
            scene: '#4fc3f7',
            actor: '#81c784',
            conflict: '#ef6c6c',
            quest: '#ce93d8',
            decision: '#ffb74d',
            choice: '#4dd0e1',
            environment: '#aed581',
            storyline_implication: '#7986cb',
            terminal_outcome: '#ef5350',
          };
          edges.forEach((edge) => {
            const source = positions.get(String(edge?.source || ''));
            const target = positions.get(String(edge?.target || ''));
            if (!source || !target) {
              return;
            }
            const line = document.createElementNS(namespace, 'line');
            line.setAttribute('x1', String(source.x));
            line.setAttribute('y1', String(source.y));
            line.setAttribute('x2', String(target.x));
            line.setAttribute('y2', String(target.y));
            line.setAttribute('class', `gm-storyline-wizard__analysis-edge gm-storyline-wizard__analysis-edge--${String(edge?.type || 'link')}`);
            svg.appendChild(line);
          });
          nodes.forEach((node) => {
            const position = positions.get(String(node?.id || ''));
            if (!position) {
              return;
            }
            const group = document.createElementNS(namespace, 'g');
            group.setAttribute('class', `gm-storyline-wizard__analysis-node gm-storyline-wizard__analysis-node--${String(node?.type || 'unknown')}`);
            const circle = document.createElementNS(namespace, 'circle');
            circle.setAttribute('cx', String(position.x));
            circle.setAttribute('cy', String(position.y));
            circle.setAttribute('r', node?.type === 'conflict' ? '18' : '14');
            circle.setAttribute('fill', colors[node?.type] || '#ffffff');
            const label = document.createElementNS(namespace, 'text');
            label.setAttribute('x', String(position.x));
            label.setAttribute('y', String(position.y + 31));
            label.setAttribute('text-anchor', 'middle');
            label.textContent = String(node?.label || node?.id || '');
            const title = document.createElementNS(namespace, 'title');
            title.textContent = `${String(node?.type || 'node')}: ${String(node?.label || node?.id || '')}`;
            group.append(circle, label, title);
            svg.appendChild(group);
          });
          analysisGraphEl.appendChild(svg);
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
          if (assistantSend) {
            assistantSend.disabled = busy || !draftCanEdit;
          }
          wizard.querySelectorAll('[data-gm-assistant-prompt]').forEach((button) => {
            button.disabled = busy;
          });
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

        function listFromCollection(collection, keys) {
          const rows = wizard.querySelectorAll(`[data-rows="${collection}"] .gm-storyline-wizard__row`);
          return Array.from(rows).map((row) => {
            const item = {};
            keys.forEach((key) => {
              item[key] = (row.querySelector(`[data-col-field="${key}"]`)?.value || '').trim();
            });
            return item;
          }).filter((item) => Object.values(item).some((value) => value !== ''));
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
            Object.entries(seed).forEach(([key, value]) => {
              const input = row.querySelector(`[data-col-field="${key}"]`);
              if (input) {
                input.value = value == null ? '' : String(value);
              }
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
          const relationshipChanges = listFromCollection('relationship_entries', collections.relationship_entries);
          const actorEffects = listFromCollection('branch_actor_effect_entries', collections.branch_actor_effect_entries);
          const environmentEffects = listFromCollection('branch_environment_effect_entries', collections.branch_environment_effect_entries);
          const storylineEffects = listFromCollection('branch_storyline_effect_entries', collections.branch_storyline_effect_entries);
          const branchChoices = listFromCollection('branch_choice_entries', collections.branch_choice_entries)
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
            .map((choice) => ({
            decision_id: choice.decision_id,
            source_task_id: choice.source_task_id,
            choice_id: choice.choice_id,
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
            target_scene_id: choice.target_scene_id,
            terminal: choice.terminal === 'true',
          }));
          const journalEntries = listFromCollection('journal_entries', collections.journal_entries).map((entry) => ({
            ...entry,
            relationship_changes: relationshipChanges
              .filter((change) => change.scene_id === entry.scene_id)
              .map(({ scene_id, ...change }) => change),
          }));
          return {
            schema_version: 'storyline-authoring-draft-v1',
            storyline_id: get('storyline_id'),
            title: get('storyline_title'),
            description: get('storyline_description'),
            premise: get('storyline_premise'),
            level_range: get('storyline_level_range'),
            tags: parseCommaList(get('storyline_tags')),
            player_hook: get('storyline_player_hook'),
            entry_actor_id: get('storyline_entry_actor'),
            entry_location_id: get('storyline_entry_location'),
            ending: get('storyline_ending'),
            process_flow: {
              journal_entries: journalEntries,
              tasks: listFromCollection('task_entries', collections.task_entries),
              branch_choices: branchChoices,
              objective_completion_criteria: listFromCollection('objective_entries', collections.objective_entries),
              conflicts: listFromCollection('conflict_entries', collections.conflict_entries).map((conflict) => ({
                ...conflict,
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
                generation_specs: listFromCollection('actor_entries', collections.actor_entries),
                validation_criteria: parseLines(get('actor_validation')),
              },
              dungeons: {
                generation_specs: listFromCollection('dungeon_entries', collections.dungeon_entries),
              },
              rooms: {
                generation_specs: listFromCollection('room_entries', collections.room_entries),
                validation_criteria: parseLines(get('room_validation')),
              },
              items: {
                generation_specs: listFromCollection('item_entries', collections.item_entries),
                validation_criteria: parseLines(get('item_validation')),
              },
            },
            orchestration: {
              generation: parseLines(get('generation_orchestration')),
              validation: parseLines(get('validation_orchestration')),
            },
          };
        }

        function refreshObjectEditorLinks() {
          if (!objectEditorLinks) {
            return;
          }
          const normalizeActorId = (value) => {
            const raw = String(value || '').trim();
            if (!raw) {
              return '';
            }
            const explicitPathMatch = raw.match(/\/actors\/([A-Za-z0-9:_-]+)/i)
              || raw.match(/\/canonical-library\/actor\/([A-Za-z0-9:_-]+)/i);
            if (explicitPathMatch && explicitPathMatch[1]) {
              return explicitPathMatch[1];
            }
            const firstUrlMatch = raw.match(/https?:\/\/[^\s]+/i);
            if (firstUrlMatch && firstUrlMatch[0]) {
              try {
                const candidate = new URL(firstUrlMatch[0]).pathname.split('/').filter(Boolean).pop();
                if (candidate) {
                  return decodeURIComponent(candidate);
                }
              } catch (error) {
                // Fall through to raw value when URL parsing fails.
              }
            }
            return raw.replace(/^content:/i, '');
          };
          const blueprint = buildBlueprint();
          const objects = [
            ...(blueprint.objects?.actors?.generation_specs || []).map((actor) => ({
              kind: 'actor',
              id: normalizeActorId(actor.actor_id),
              label: String(actor.display_name || actor.actor_id || '').trim(),
            })),
            ...(blueprint.objects?.items?.generation_specs || []).map((item) => ({
              kind: 'item',
              id: String(item.item_id || '').trim(),
              label: String(item.item_id || '').trim(),
            })),
            ...(blueprint.objects?.dungeons?.generation_specs || []).map((dungeon) => ({
              kind: 'dungeon',
              id: String(dungeon.dungeon_id || '').trim(),
              label: String(dungeon.dungeon_id || '').trim(),
            })),
            ...(blueprint.objects?.rooms?.generation_specs || []).map((room) => ({
              kind: 'room',
              id: String(room.room_id || '').trim(),
              label: String(room.room_id || '').trim(),
            })),
          ];
          const unique = new Map();
          objects.forEach((object) => {
            if (object.id && objectEditorTemplates[object.kind]) {
              unique.set(`${object.kind}:${object.id}`, object);
            }
          });
          objectEditorLinks.replaceChildren();
          if (unique.size === 0) {
            const empty = document.createElement('li');
            empty.textContent = 'Add or load storyline objects to open their exact editors.';
            objectEditorLinks.appendChild(empty);
            return;
          }
          unique.forEach((object) => {
            const item = document.createElement('li');
            const link = document.createElement('a');
            const href = String(objectEditorTemplates[object.kind]).replace('{object_id}', encodeURIComponent(object.id));
            if (object.kind === 'actor') {
              const characterMatch = String(object.id).match(/^(?:character:)?(\d+)$/i);
              const campaignId = new URL(window.location.href).searchParams.get('campaign_id');
              if (characterMatch) {
                const characterUrl = new URL(`/characters/${characterMatch[1]}`, window.location.origin);
                if (campaignId) {
                  characterUrl.searchParams.set('campaign_id', campaignId);
                }
                link.href = characterUrl.toString();
              } else if (campaignId) {
                const actorUrl = new URL(href, window.location.href);
                actorUrl.searchParams.set('campaign_id', campaignId);
                link.href = actorUrl.toString();
              } else {
                link.href = href;
              }
            } else {
              link.href = href;
            }
            link.target = '_blank';
            link.rel = 'noopener';
            link.textContent = `${object.kind[0].toUpperCase()}${object.kind.slice(1)}: ${object.label}`;
            link.title = object.kind === 'actor'
              ? `Open ${object.id} in the actor character sheet`
              : `Open ${object.id} in the canonical ${object.kind} editor`;
            item.appendChild(link);
            objectEditorLinks.appendChild(item);
          });
        }

        function refreshPreview() {
          if (!previewEl) {
            return;
          }
          previewEl.textContent = JSON.stringify(buildBlueprint(), null, 2);
        }

        function hydrateFromPayload(payload) {
          if (!payload || typeof payload !== 'object') {
            return;
          }
          const setField = (key, value) => {
            const field = wizard.querySelector(`[data-field="${key}"]`);
            if (field) {
              field.value = value == null ? '' : String(value);
            }
          };
          setField('storyline_id', payload.storyline_id || '');
          setField('storyline_title', payload.title || '');
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
            ['task_entries', payload?.process_flow?.tasks || []],
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
            draftIsOwner = true;
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
            draftDirty = false;
            applyDraftOwnershipState();
            updateDraftUrl();
            hydrateFromPayload(draft.payload || {});
            refreshPreview();
            const redirected = String(draft.redirected_from_draft_id || '');
            setDraftStatus(redirected
              ? `Recovered retired draft ${redirected.slice(0, 8)} as active draft ${draftId.slice(0, 8)} (rev ${draftRevision}).`
              : (draftCanEdit
                ? `Loaded your draft ${draftId.slice(0, 8)} (rev ${draftRevision})`
                : `Viewing another author's draft ${draftId.slice(0, 8)} (rev ${draftRevision}); saving creates your own copy.`));
            if (draftCanEdit || draftIsOwner) {
              initAssistant();
            } else {
              setAssistantStatus('Create your own copy to use the GM or save changes.');
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
            draftIsOwner = true;
            draftDirty = false;
            hydrateFromPayload(draft.payload || {});
            refreshPreview();
            applyDraftOwnershipState();
            updateDraftUrl();
            if (draftRevisionPicker) {
              draftRevisionPicker.value = String(draftRevision);
            }
            setDraftStatus(`Viewing immutable revision ${draftRevision}; saving creates a new draft copy from this version.`);
            setAssistantStatus('Historical revisions are read-only. Save as your copy to continue from this version.');
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
            await copyCurrentDraft();
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
            draftDirty = false;
            applyDraftOwnershipState();
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
              draftSaveBtn.textContent = draftCanEdit ? 'Save draft' : 'Save as my copy';
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
          }

        async function copyCurrentDraft() {
            const copyUrl = resolveDraftUrl(draftsSettings.copyUrlTemplate, draftId);
            if (!copyUrl) {
              throw new Error('storyline_draft_copy_url_unavailable');
            }
            if (!csrfToken) {
              csrfToken = await fetchCsrfToken();
            }
            setDraftStatus('Creating your copy…');
            const response = await fetch(copyUrl, {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
              },
              body: JSON.stringify(viewedRevision === null ? {} : { revision: viewedRevision }),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
              throw new Error(payload?.error?.code || `copy_${response.status}`);
            }
            const draft = payload?.data || {};
            draftId = String(draft.draft_id || '');
            draftRevision = Number(draft.revision || 1);
            viewedRevision = null;
            draftCanEdit = true;
            draftIsOwner = true;
            updateDraftUrl();
            applyDraftOwnershipState();
            await refreshDraftPicker();
            await refreshRevisionPicker();
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
            const mine = document.createElement('optgroup');
            mine.label = 'My storylines';
            const shared = document.createElement('optgroup');
            shared.label = 'Shared and canonical storylines';
            drafts.forEach((draft) => {
              const selectorType = draft.selector_type || 'draft';
              if (selectorType === 'template') {
                const option = new Option(`${draft.title || 'Untitled'} · Canonical · no draft yet`, `template:${draft.template_id || ''}`);
                shared.appendChild(option);
                return;
              }
              const ownership = draft.is_owner ? 'Mine' : `By ${draft.owner_name || `User ${draft.owner_uid}`}`;
              const source = draftLibrary.get(String(draft.source_draft_id || ''));
              const lineage = source
                ? ` · from ${source.title || 'Untitled'} rev ${Number(draft.source_revision || source.revision || 0)}`
                : '';
              const versions = ` · ${Number(draft.revision_count || 0)} saved ${Number(draft.revision_count || 0) === 1 ? 'version' : 'versions'}`;
              const option = new Option(`${draft.title || 'Untitled'} · ${ownership} · rev ${Number(draft.revision || 0)}${versions}${lineage}`, `draft:${String(draft.draft_id || '')}`);
              option.selected = String(draft.draft_id || '') === draftId;
              (draft.is_owner ? mine : shared).appendChild(option);
            });
            if (mine.children.length > 0) {
              draftPicker.appendChild(mine);
            }
            if (shared.children.length > 0) {
              draftPicker.appendChild(shared);
            }
        }

        async function refreshRevisionPicker() {
            if (!draftRevisionPicker) {
              return;
            }
            draftRevisionPicker.replaceChildren(new Option('Current revision', ''));
            draftRevisionPicker.disabled = !draftId;
            if (draftId && !draftIsOwner) {
              draftRevisionPicker.options[0].textContent = `Latest shared version — revision ${draftRevision}`;
              draftRevisionPicker.disabled = true;
              return;
            }
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
            draftRevisionPicker.options[0].textContent = `Current — revision ${Number(current?.revision || draftRevision || 0)}`;
            revisions.filter((revision) => !revision.is_current).forEach((revision) => {
              const date = new Date(Number(revision.created_at || 0) * 1000);
              const label = Number.isNaN(date.getTime())
                ? `Revision ${Number(revision.revision || 0)}`
                : `Revision ${Number(revision.revision || 0)} — ${date.toLocaleString()}`;
              draftRevisionPicker.appendChild(new Option(label, String(revision.revision || '')));
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
            renderNarrativeAnalysis(validation.analysis);
            if (previewEl && validation.definition) {
              previewEl.textContent = JSON.stringify(validation.definition, null, 2);
            }
            if (validation.valid) {
              setPublishStatus('Validation passed. The generated canonical storyline object is ready for creation review.');
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

        function pushAssistantNextSteps(nextSteps) {
          const steps = Array.isArray(nextSteps)
            ? nextSteps.filter((step) => typeof step === 'string' && step.trim() !== '')
            : [];
          if (steps.length > 0 && assistantLog) {
            const item = document.createElement('li');
            item.className = 'gm-storyline-wizard__assistant-item gm-storyline-wizard__assistant-item--assistant gm-storyline-wizard__assistant-next-steps';
            const heading = document.createElement('strong');
            heading.textContent = 'Next steps';
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
              `Content: ${Number(counts.scenes || 0)} scenes, ${Number(counts.tasks || 0)} tasks, ${Number(counts.objectives || 0)} objectives, ${Number(counts.conflicts || 0)} conflicts, ${Number(counts.actors || 0)} actors, ${Number(counts.dungeons || 0)} dungeons, ${Number(counts.rooms || 0)} rooms, and ${Number(counts.items || 0)} items.`,
              Number(result.current_stage || 1) <= 6
                ? `Current focus: Stage ${Number(result.current_stage || 1)}.`
                : 'All six authoring stages are complete; final review is next.',
            ];
            const missing = Array.isArray(result.missing_content) ? result.missing_content : [];
            if (missing.length > 0) {
              lines.push(`Minimum content still needed: ${missing.join(', ')}.`);
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
              const succeeded = await askAssistant(`I select option ${index + 1}, ${option.label}. Use this direction: ${option.selection}`);
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
          if (!draftCanEdit && !draftIsOwner) {
            setAssistantStatus('Create your own copy to use the GM.');
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
              : 'This published draft is read-only. Save as your copy to continue editing.');
            renderCurrentState(payload?.data?.context_snapshot?.current_state, storylineStatusElements);
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
            const payload = await sendAssistantIntent({
              type: 'natural_language',
              utterance: prompt,
              author_message: prompt,
            });
            const result = payload?.data?.tool_result || {};
            const notes = payload?.data?.messages || [];
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
              renderCurrentState(payload?.data?.context_snapshot?.current_state, storylineStatusElements);
              setAssistantStatus('');
            }
            if (result?.intent !== 'clarification') {
              pushAssistantLog('assistant', summarizeAssistantResult(result));
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
          if (activeStep === 7) {
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
            if (activeStep === 7) {
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
          const removeRow = target.closest('[data-remove-row]');
          if (removeRow) {
            event.preventDefault();
            const row = removeRow.closest('.gm-storyline-wizard__row');
            row?.remove();
            markDraftDirty();
            refreshObjectEditorLinks();
            if (activeStep === 7) {
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
            if (activeStep === 7) {
              refreshPreview();
            }
          });
        });

        wizard.addEventListener('input', (event) => {
          if (event.target instanceof Element && event.target.closest('.gm-storyline-wizard__row') && activeStep === 7) {
            refreshPreview();
          }
          if (event.target instanceof Element && event.target.closest('.gm-storyline-wizard__row')) {
            markDraftDirty();
            refreshObjectEditorLinks();
          }
        });

        if (copyBtn) {
          copyBtn.addEventListener('click', async (event) => {
            event.preventDefault();
            refreshPreview();
            const payload = previewEl?.textContent || '';
            if (!payload) {
              return;
            }
            try {
              await navigator.clipboard.writeText(payload);
              copyBtn.textContent = 'Copied';
              setTimeout(() => {
                copyBtn.textContent = 'Copy object JSON';
              }, 1200);
            } catch (_) {
              copyBtn.textContent = 'Copy failed';
              setTimeout(() => {
                copyBtn.textContent = 'Copy object JSON';
              }, 1200);
            }
          });
        }

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
