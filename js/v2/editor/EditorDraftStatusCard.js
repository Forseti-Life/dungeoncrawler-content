/**
 * @file
 * Draft status card shared by the Dungeon Editor and Room Editor GM panels.
 *
 * Renders the server's status projection (context_snapshot.current_state);
 * nothing here is computed client-side.
 */

export function renderEditorDraftStatus(card, status) {
  if (!card) return;
  const part = (name) => card.querySelector(`[data-editor-status-${name}]`);
  const missing = part('missing');
  const setMissing = (labels) => {
    missing.replaceChildren(...labels.map((label) => {
      const item = document.createElement('li');
      item.textContent = label;
      return item;
    }));
  };
  const stories = part('storylines');
  const setStories = (rows) => {
    if (!rows.length) {
      const none = document.createElement('li');
      none.textContent = 'No storyline uses this yet.';
      stories.replaceChildren(none);
      return;
    }
    stories.replaceChildren(...rows.map((row) => {
      const item = document.createElement('li');
      const link = document.createElement('a');
      link.href = `/game-master/storyline-builder/scenes?draft_id=${encodeURIComponent(row.draft_id)}`;
      link.textContent = row.title || 'Untitled storyline';
      item.append(link, ` (${row.status})`);
      return item;
    }));
  };
  if (!status) {
    setStories([]);
    part('percent').textContent = '0%';
    part('progress').value = 0;
    part('validity').textContent = 'No draft';
    part('stage').textContent = '—';
    setMissing(['Create or load a draft.']);
    return;
  }
  const stage = status.stages.find((row) => row.stage === status.current_stage);
  part('percent').textContent = `${status.completion_percent}%`;
  part('progress').value = status.completion_percent;
  part('progress').textContent = `${status.completion_percent}%`;
  part('validity').textContent = status.canonical_valid
    ? `Valid · ${status.warning_count} warnings`
    : `${status.error_count} validation errors`;
  part('stage').textContent = `${status.current_stage} of ${status.stages.length}: ${stage.label}`;
  setStories(status.storylines);
  setMissing(status.missing_content.length ? status.missing_content : ['Nothing: minimum content is complete.']);
}
