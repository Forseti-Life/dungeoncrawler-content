/**
 * @file utils/quest-utils.js
 *
 * Pure utility functions for quest data.
 *
 * No dependencies. No side effects.
 */

/**
 * Build a nested objective tree from a flat quest objective list.
 * Objectives with `parent_id` are nested under their parent.
 * Top-level objectives (no parent_id) are returned in order.
 *
 * @param {object[]} objectives
 * @returns {object[]} nested tree
 */
export function buildObjectiveTree(objectives = []) {
  const map = new Map();
  objectives.forEach((o) => map.set(o.objective_id, { ...o, children: [] }));
  const roots = [];
  map.forEach((obj) => {
    if (obj.parent_id && map.has(obj.parent_id)) {
      map.get(obj.parent_id).children.push(obj);
    } else {
      roots.push(obj);
    }
  });
  return roots;
}

/**
 * Return true if all required objectives for a quest are complete.
 * @param {object} quest  — { objectives: [{ status, required? }] }
 * @returns {boolean}
 */
export function isQuestComplete(quest) {
  const objectives = quest?.objectives ?? [];
  if (objectives.length === 0) return quest?.status === 'completed';
  return objectives
    .filter((o) => o.required !== false)
    .every((o) => o.status === 'complete' || o.status === 'completed');
}

/**
 * Calculate quest progress as { completed, total, percent }.
 * @param {object} quest
 * @returns {{ completed: number, total: number, percent: number }}
 */
export function getQuestProgress(quest) {
  const objectives = (quest?.objectives ?? []).filter((o) => o.required !== false);
  const total     = objectives.length;
  const completed = objectives.filter((o) => o.status === 'complete' || o.status === 'completed').length;
  const percent   = total > 0 ? Math.round((completed / total) * 100) : 0;
  return { completed, total, percent };
}

/**
 * Flatten a nested objective tree to a sorted list (breadth-first).
 * @param {object[]} tree
 * @returns {object[]}
 */
export function flattenObjectiveTree(tree = []) {
  const result = [];
  const queue  = [...tree];
  while (queue.length > 0) {
    const node = queue.shift();
    result.push(node);
    if (node.children?.length) queue.push(...node.children);
  }
  return result;
}

