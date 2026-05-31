/**
 * @file utils/quest-utils.js
 *
 * Quest data normalization and rendering helpers ported verbatim from hexmap.js.
 */

export function resolveQuestTitle(quest) {
  if (!quest || typeof quest !== 'object') {
    return 'Unknown Quest';
  }
  return quest.title || quest.quest_name || quest.name || quest.quest_key || quest.quest_id || quest.id || 'Unknown Quest';
}

export function normalizeQuestObjectivePayload(objective) {
  if (!objective || typeof objective !== 'object') {
    return null;
  }

  const objectiveId = String(objective.objective_id || '').trim();
  const description = String(objective.description || objectiveId || '').trim();
  const type = String(objective.type || '').trim();
  if (!objectiveId || !description || !type) {
    return null;
  }

  const normalized = {
    objective_id: objectiveId,
    type,
    description,
    completed: Boolean(objective.completed),
  };

  if (objective.current != null) {
    normalized.current = Math.max(0, Number(objective.current || 0));
  }
  if (objective.target_count != null) {
    normalized.target_count = Math.max(0, Number(objective.target_count || 0));
  }
  if (objective.target != null && String(objective.target).trim()) {
    normalized.target = String(objective.target).trim();
  }
  if (objective.item != null && String(objective.item).trim()) {
    normalized.item = String(objective.item).trim();
  }
  if (objective.location != null && String(objective.location).trim()) {
    normalized.location = String(objective.location).trim();
  }
  if (objective.location_id != null && String(objective.location_id).trim()) {
    normalized.location_id = String(objective.location_id).trim();
  }
  if (objective.destination != null && String(objective.destination).trim()) {
    normalized.destination = String(objective.destination).trim();
  }
  if (objective.destination_id != null && String(objective.destination_id).trim()) {
    normalized.destination_id = String(objective.destination_id).trim();
  }
  if (objective.npc_id != null && Number.isFinite(Number(objective.npc_id))) {
    normalized.npc_id = Math.max(0, Number(objective.npc_id));
  }
  if (objective.discovered != null) {
    normalized.discovered = Boolean(objective.discovered);
  }
  if (objective.arrived != null) {
    normalized.arrived = Boolean(objective.arrived);
  }
  if (objective.revealed != null) {
    normalized.revealed = Boolean(objective.revealed);
  }
  if (objective.completion_criteria && typeof objective.completion_criteria === 'object') {
    normalized.completion_criteria = normalizeQuestCompletionCriteriaPayload(objective.completion_criteria);
  }
  if (Array.isArray(objective.children)) {
    normalized.children = objective.children
      .map(normalizeQuestObjectivePayload)
      .filter(Boolean);
  }

  return normalized;
}

export function normalizeQuestPhasePayload(phase, fallbackPhase = 1) {
  if (!phase || typeof phase !== 'object') {
    return null;
  }

  return {
    phase: Math.max(1, Number(phase.phase || fallbackPhase || 1)),
    objectives: (Array.isArray(phase.objectives) ? phase.objectives : [])
      .map(normalizeQuestObjectivePayload)
      .filter(Boolean),
  };
}

export function normalizeQuestEntryPayload(quest) {
  if (!quest || typeof quest !== 'object') {
    return null;
  }

  const questId = String(quest.quest_id || quest.id || '').trim();
  const questKey = String(quest.quest_key || quest.source_template_id || questId).trim();
  const questName = String(quest.quest_name || quest.title || quest.name || questId).trim();
  const title = String(quest.title || questName || questKey).trim();
  if (!questId || !questKey || !questName || !title) {
    return null;
  }

  const parseArray = (value) => {
    if (Array.isArray(value)) {
      return value;
    }
    if (typeof value !== 'string' || !value.trim()) {
      return [];
    }
    try {
      const decoded = JSON.parse(value);
      return Array.isArray(decoded) ? decoded : [];
    } catch (error) {
      return [];
    }
  };
  const parseObject = (value) => {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      return value;
    }
    if (typeof value !== 'string' || !value.trim()) {
      return {};
    }
    try {
      const decoded = JSON.parse(value);
      return decoded && typeof decoded === 'object' && !Array.isArray(decoded) ? decoded : {};
    } catch (error) {
      return {};
    }
  };

  const storyline = quest.storyline && typeof quest.storyline === 'object'
    ? quest.storyline
    : {
        storyline_id: quest.storyline_id,
        chapter_id: quest.storyline_chapter_id,
        scene_id: quest.storyline_scene_id,
      };

  return {
    quest_id: questId,
    quest_key: questKey,
    source_template_id: quest.source_template_id == null || quest.source_template_id === '' ? null : String(quest.source_template_id),
    title,
    quest_name: questName,
    status: String(quest.status || 'lead').trim() || 'lead',
    current_phase: Math.max(1, Number(quest.current_phase || 1)),
    generated_objectives: parseArray(quest.generated_objectives)
      .map((phase, index) => normalizeQuestPhasePayload(phase, index + 1))
      .filter(Boolean),
    objective_states: parseArray(quest.objective_states)
      .map((phase, index) => normalizeQuestPhasePayload(phase, index + 1))
      .filter(Boolean),
    generated_rewards: parseObject(quest.generated_rewards),
    quest_data: parseObject(quest.quest_data),
    location_id: quest.location_id == null || quest.location_id === '' ? null : String(quest.location_id),
    storyline: {
      storyline_id: storyline.storyline_id == null || storyline.storyline_id === '' ? null : String(storyline.storyline_id),
      chapter_id: storyline.chapter_id == null || storyline.chapter_id === '' ? null : String(storyline.chapter_id),
      scene_id: storyline.scene_id == null || storyline.scene_id === '' ? null : String(storyline.scene_id),
    },
  };
}

export function normalizeQuestSummaryPayload(payload) {
  const source = payload && typeof payload === 'object' ? payload : {};
  const schemaVersion = String(source.schema_version || '').trim();
  if (schemaVersion && schemaVersion !== QUEST_SUMMARY_SCHEMA_VERSION) {
    console.warn(`Quest summary schema ${schemaVersion} may not be fully compatible. Expected ${QUEST_SUMMARY_SCHEMA_VERSION}.`);
  } else if (!schemaVersion) {
    console.warn(`Quest summary payload missing schema_version. Assuming ${QUEST_SUMMARY_SCHEMA_VERSION}.`);
  }

  const active = (Array.isArray(source.active) ? source.active : [])
    .map(normalizeQuestEntryPayload)
    .filter(Boolean);
  const offers = (Array.isArray(source.offers) ? source.offers : [])
    .map(normalizeQuestEntryPayload)
    .filter(Boolean);
  const leads = (Array.isArray(source.leads) ? source.leads : [])
    .map(normalizeQuestEntryPayload)
    .filter(Boolean);
  const managementTree = (Array.isArray(source.management_tree) ? source.management_tree : [])
    .map(normalizeQuestManagementNpcPayload)
    .filter(Boolean);

  return {
    schema_version: QUEST_SUMMARY_SCHEMA_VERSION,
    location_id: String(source.location_id || '').trim(),
    active,
    offers,
    leads,
    management_tree: managementTree,
    counts: {
      active: active.length,
      offers: offers.length,
      leads: leads.length,
    },
  };
}

export function normalizeQuestManagementLocation(location) {
  const source = location && typeof location === 'object'
    ? location
    : { id: location, label: location };
  const id = source.id == null || String(source.id).trim() === '' ? null : String(source.id).trim();
  const label = source.label == null || String(source.label).trim() === ''
    ? (id ? String(id).replace(/[_-]+/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase()) : null)
    : String(source.label).trim();
  return { id, label };
}

export function normalizeQuestManagementAccess(access) {
  const source = access && typeof access === 'object' ? access : {};
  const sortBucket = String(source.sort_bucket || 'unclear').trim() || 'unclear';
  const sortRank = Number.isFinite(Number(source.sort_rank))
    ? Number(source.sort_rank)
    : ({ current: 0, ready: 1, completed: 2, blocked: 3, unclear: 4 }[sortBucket] ?? 4);
  return {
    is_clear: Boolean(source.is_clear),
    is_accessible: Boolean(source.is_accessible),
    sort_bucket: sortBucket,
    sort_rank: sortRank,
  };
}

export function normalizeQuestCompletionCriteriaPayload(criteria) {
  const source = criteria && typeof criteria === 'object' ? criteria : {};
  const kind = String(source.kind || 'flag').trim() || 'flag';
  const normalized = {
    kind,
    metric: String(source.metric || 'completed').trim() || 'completed',
    description: String(source.description || 'Complete this objective.').trim() || 'Complete this objective.',
  };

  if (kind === 'count') {
    normalized.target_count = Math.max(1, Number(source.target_count || 1));
  } else {
    normalized.required_value = source.required_value == null ? true : Boolean(source.required_value);
  }

  return normalized;
}

export function normalizeQuestManagementObjectivePayload(objective) {
  if (!objective || typeof objective !== 'object') {
    return null;
  }

  const objectiveId = String(objective.objective_id || '').trim();
  const description = String(objective.description || objectiveId || '').trim();
  const type = String(objective.type || '').trim();
  if (!objectiveId || !description || !type) {
    return null;
  }

  const normalized = {
    objective_id: objectiveId,
    phase: Math.max(1, Number(objective.phase || 1)),
    type,
    description,
    completed: Boolean(objective.completed),
    revealed: objective.revealed == null ? undefined : Boolean(objective.revealed),
    location: normalizeQuestManagementLocation(objective.location),
    next_step: String(objective.next_step || description).trim(),
    access: normalizeQuestManagementAccess(objective.access),
  };

  ['current', 'target_count'].forEach((field) => {
    if (objective[field] != null && Number.isFinite(Number(objective[field]))) {
      normalized[field] = Math.max(0, Number(objective[field]));
    }
  });
  ['item', 'target'].forEach((field) => {
    if (objective[field] != null && String(objective[field]).trim()) {
      normalized[field] = String(objective[field]).trim();
    }
  });
  if (objective.completion_criteria && typeof objective.completion_criteria === 'object') {
    normalized.completion_criteria = normalizeQuestCompletionCriteriaPayload(objective.completion_criteria);
  }
  normalized.children = (Array.isArray(objective.children) ? objective.children : [])
    .map(normalizeQuestManagementObjectivePayload)
    .filter(Boolean);

  return normalized;
}

export function normalizeQuestManagementQuestPayload(quest) {
  if (!quest || typeof quest !== 'object') {
    return null;
  }

  const questId = String(quest.quest_id || '').trim();
  const questName = String(quest.quest_name || quest.title || questId).trim();
  if (!questId || !questName) {
    return null;
  }

  return {
    ...quest,
    quest_id: questId,
    quest_name: questName,
    title: String(quest.title || questName).trim(),
    status: String(quest.status || 'lead').trim() || 'lead',
    location: normalizeQuestManagementLocation(quest.location),
    next_step: String(quest.next_step || questName).trim(),
    access: normalizeQuestManagementAccess(quest.access),
    objectives: (Array.isArray(quest.objectives) ? quest.objectives : [])
      .map(normalizeQuestManagementObjectivePayload)
      .filter(Boolean),
  };
}

export function normalizeQuestManagementStorylinePayload(storyline) {
  if (!storyline || typeof storyline !== 'object') {
    return null;
  }

  const storylineId = String(storyline.storyline_id || '').trim();
  const name = String(storyline.name || storylineId).trim();
  if (!storylineId || !name) {
    return null;
  }

  return {
    ...storyline,
    storyline_id: storylineId,
    name,
    status: String(storyline.status || 'available').trim() || 'available',
    synopsis: String(storyline.synopsis || '').trim(),
    location: normalizeQuestManagementLocation(storyline.location || storyline.lead_location),
    next_step: String(storyline.next_step || name).trim(),
    access: normalizeQuestManagementAccess(storyline.access),
    quests: (Array.isArray(storyline.quests) ? storyline.quests : [])
      .map(normalizeQuestManagementQuestPayload)
      .filter(Boolean),
  };
}

export function normalizeQuestManagementNpcPayload(npc) {
  if (!npc || typeof npc !== 'object') {
    return null;
  }

  const npcId = String(npc.npc_id || '').trim();
  const npcName = String(npc.npc_name || npcId).trim();
  if (!npcId || !npcName) {
    return null;
  }

  return {
    ...npc,
    npc_id: npcId,
    npc_name: npcName,
    role: String(npc.role || 'quest_giver').trim() || 'quest_giver',
    location: normalizeQuestManagementLocation(npc.location),
    next_step: String(npc.next_step || npcName).trim(),
    access: normalizeQuestManagementAccess(npc.access),
    storylines: (Array.isArray(npc.storylines) ? npc.storylines : [])
      .map(normalizeQuestManagementStorylinePayload)
      .filter(Boolean),
  };
}

export function formatQuestManagementLocation(location) {
  if (!location || typeof location !== 'object') {
    return 'Unknown location';
  }
  return String(location.label || location.id || 'Unknown location').trim() || 'Unknown location';
}

export function formatQuestManagementAccess(access) {
  const normalized = normalizeQuestManagementAccess(access);
  switch (normalized.sort_bucket) {
    case 'current':
      return 'Current location';
    case 'ready':
      return 'Ready';
    case 'completed':
      return 'Completed';
    case 'blocked':
      return 'Blocked';
    default:
      return 'Unclear';
  }
}

export function formatQuestCompletionCriteria(criteria) {
  const normalized = normalizeQuestCompletionCriteriaPayload(criteria);
  return normalized.description;
}

export function renderQuestTreeNodeHtml(options) {
  const {
    itemClass,
    title,
    metaLines = [],
    bodyHtml = '',
    titlePrefix = '',
  } = options;
  const titleHtml = titlePrefix
    ? `${escapeQuestHtml(titlePrefix)} ${escapeQuestHtml(title)}`
    : escapeQuestHtml(title);
  const summaryHtml = metaLines
    .filter((line) => line && String(line).trim() !== '')
    .map((line) => `<div class="quest-status">${escapeQuestHtml(line)}</div>`)
    .join('');

  return `<li class="${itemClass}">
    <details class="quest-tree-node" data-quest-collapsible>
      <summary class="quest-tree-node__summary">
        <strong class="quest-title">${titleHtml}</strong>
        ${summaryHtml}
      </summary>
      <div class="quest-tree-node__content">${bodyHtml}</div>
    </details>
  </li>`;
}

export function renderQuestManagementObjectiveHtml(objective) {
  const progress = objective.current != null && objective.target_count != null
    ? ` (${escapeQuestHtml(objective.current)}/${escapeQuestHtml(objective.target_count)})`
    : '';
  const details = [objective.item, objective.target].filter(Boolean).join(' · ');
  const detailLines = [
    `Location: ${formatQuestManagementLocation(objective.location)} · ${formatQuestManagementAccess(objective.access)}`,
    `Next: ${objective.next_step || 'Review this objective.'}`,
  ];
  if (details) {
    detailLines.push(`Detail: ${details}`);
  }
  if (objective.completion_criteria) {
    detailLines.push(`Complete when: ${formatQuestCompletionCriteria(objective.completion_criteria)}`);
  }
  const childrenHtml = Array.isArray(objective.children) && objective.children.length > 0
    ? `<ul class="quest-objectives">${objective.children.map(renderQuestManagementObjectiveHtml).join('')}</ul>`
    : '';
  return renderQuestTreeNodeHtml({
    itemClass: `quest-objective quest-objective--tree quest-objective--${escapeQuestHtml(objective.access.sort_bucket)}`,
    title: `${objective.completed ? '✅' : '⬜'} ${objective.description}${progress}`,
    metaLines: detailLines,
    bodyHtml: childrenHtml || '<div class="quest-status">No nested objectives.</div>',
  });
}

export function renderQuestManagementQuestHtml(quest) {
  const objectiveHtml = (Array.isArray(quest.objectives) ? quest.objectives : []).map(renderQuestManagementObjectiveHtml).join('');
  return renderQuestTreeNodeHtml({
    itemClass: `quest-entry quest-entry--quest quest-entry--${escapeQuestHtml(quest.access.sort_bucket)}`,
    title: quest.quest_name,
    titlePrefix: '📜',
    metaLines: [
      `Status: ${quest.status} · Location: ${formatQuestManagementLocation(quest.location)} · ${formatQuestManagementAccess(quest.access)}`,
      `Next: ${quest.next_step || 'Review this quest.'}`,
    ],
    bodyHtml: `<ul class="quest-objectives">${objectiveHtml || '<li class="quest-objective">No objectives recorded.</li>'}</ul>`,
  });
}

export function renderQuestManagementStorylineHtml(storyline) {
  const questHtml = (Array.isArray(storyline.quests) ? storyline.quests : []).map(renderQuestManagementQuestHtml).join('');
  return renderQuestTreeNodeHtml({
    itemClass: `quest-entry quest-entry--storyline quest-entry--${escapeQuestHtml(storyline.access.sort_bucket)}`,
    title: storyline.name,
    titlePrefix: '🧭',
    metaLines: [
      `Status: ${storyline.status} · Location: ${formatQuestManagementLocation(storyline.location)} · ${formatQuestManagementAccess(storyline.access)}`,
      `Next: ${storyline.next_step || 'Review this storyline.'}`,
      ...(storyline.synopsis ? [storyline.synopsis] : []),
    ],
    bodyHtml: `<ul class="quest-objectives">${questHtml || '<li class="quest-objective">No storyline quests recorded.</li>'}</ul>`,
  });
}

export function renderQuestManagementNpcHtml(npc) {
  const storylineHtml = (Array.isArray(npc.storylines) ? npc.storylines : []).map(renderQuestManagementStorylineHtml).join('');
  return renderQuestTreeNodeHtml({
    itemClass: `quest-entry quest-entry--npc quest-entry--${escapeQuestHtml(npc.access.sort_bucket)}`,
    title: npc.npc_name,
    titlePrefix: '👤',
    metaLines: [
      `Location: ${formatQuestManagementLocation(npc.location)} · ${formatQuestManagementAccess(npc.access)}`,
      `Next: ${npc.next_step || 'Review available leads.'}`,
    ],
    bodyHtml: `<ul class="quest-objectives">${storylineHtml || '<li class="quest-objective">No storyline leads available.</li>'}</ul>`,
  });
}

export function normalizeQuestUpdatePayload(update) {
  if (!update || typeof update !== 'object') {
    return null;
  }

  const schemaVersion = String(update.schema_version || '').trim();
  if (schemaVersion && schemaVersion !== QUEST_UPDATE_SCHEMA_VERSION) {
    console.warn(`Quest update schema ${schemaVersion} may not be fully compatible. Expected ${QUEST_UPDATE_SCHEMA_VERSION}.`);
  }

  const questId = String(update.quest_id || '').trim();
  const questName = String(update.quest_name || questId).trim();
  const type = String(update.type || '').trim();
  const source = String(update.source || '').trim();
  if (!questId || !questName || !['quest_started', 'quest_surfaced'].includes(type)) {
    return null;
  }

  return {
    schema_version: QUEST_UPDATE_SCHEMA_VERSION,
    type,
    quest_id: questId,
    quest_name: questName,
    status: String(update.status || 'active').trim() || 'active',
    objectives: (Array.isArray(update.objectives) ? update.objectives : [])
      .map((objective) => String(objective || '').trim())
      .filter(Boolean),
    source: source || 'available_quest',
    storyline_id: update.storyline_id == null || update.storyline_id === '' ? null : String(update.storyline_id),
  };
}

export function escapeQuestHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

export function extractQuestPhases(quest) {
  if (!quest || typeof quest !== 'object') {
    return [];
  }
  if (Array.isArray(quest.generated_objectives) && quest.generated_objectives.length > 0) {
    return quest.generated_objectives;
  }
  if (Array.isArray(quest.objective_states) && quest.objective_states.some(phase => phase && Array.isArray(phase.objectives))) {
    return quest.objective_states;
  }
  return [];
}

export function buildObjectiveStateIndex(quest) {
  const index = {};
  if (!quest || !Array.isArray(quest.objective_states)) {
    return index;
  }

  for (const entry of quest.objective_states) {
    if (!entry || typeof entry !== 'object') {
      continue;
    }

    if (Array.isArray(entry.objectives)) {
      addObjectiveStatesToIndex(index, entry.objectives);
      continue;
    }

    const objectiveId = entry.objective_id;
    if (!objectiveId) {
      continue;
    }
    index[objectiveId] = {
      current: Number(entry.current || 0),
      target: Number(entry.target || entry.target_count || 1),
      description: entry.description || objectiveId,
      completed: Boolean(entry.completed),
    };
  }

  return index;
}

export function addObjectiveStatesToIndex(index, objectives) {
  for (const objective of (Array.isArray(objectives) ? objectives : [])) {
    const objectiveId = objective?.objective_id;
    if (objectiveId) {
      index[objectiveId] = {
        current: Number(objective.current || 0),
        target: Number(objective.target_count || 1),
        description: objective.description || objectiveId,
        completed: Boolean(objective.completed),
      };
    }
    if (Array.isArray(objective?.children) && objective.children.length > 0) {
      addObjectiveStatesToIndex(index, objective.children);
    }
  }
}

export function mergeObjectiveProgress(baseObjective, objectiveIndex) {
  const objectiveId = baseObjective?.objective_id;
  const merged = {
    objective_id: objectiveId,
    type: baseObjective?.type || '',
    description: baseObjective?.description || objectiveId || '',
    target_count: Number(baseObjective?.target_count || 1),
    current: Number(baseObjective?.current || 0),
    completed: Boolean(baseObjective?.completed),
  };

  if (objectiveId && objectiveIndex[objectiveId]) {
    const state = objectiveIndex[objectiveId];
    merged.current = Math.max(merged.current, Number(state.current || 0));
    merged.target_count = Number(merged.target_count || state.target || 1);
    if (!baseObjective?.description) {
      merged.description = state.description || merged.description;
    }
    merged.completed = merged.completed || Boolean(state.completed) || merged.current >= merged.target_count;
  } else {
    merged.completed = merged.completed || merged.current >= merged.target_count;
  }

  return merged;
}

export function flattenQuestObjectives(objectives, options = {}) {
  const includeCompleted = Boolean(options.includeCompleted);
  const flattened = [];
  for (const objective of (Array.isArray(objectives) ? objectives : [])) {
    if (!objective || typeof objective !== 'object') {
      continue;
    }

    if (Array.isArray(objective.children) && objective.children.length > 0) {
      flattened.push(...flattenQuestObjectives(objective.children, options));
      continue;
    }

    if (!includeCompleted && objective.completed) {
      continue;
    }

    flattened.push(objective);
  }

  return flattened;
}

export function incrementObjectiveProgressInTree(objectives, objectiveId, increment) {
  for (const objective of (Array.isArray(objectives) ? objectives : [])) {
    if (!objective || typeof objective !== 'object') {
      continue;
    }
    if (objective.objective_id === objectiveId) {
      objective.current = (objective.current || 0) + increment;
      return true;
    }
    if (incrementObjectiveProgressInTree(objective.children, objectiveId, increment)) {
      return true;
    }
  }
  return false;
}
