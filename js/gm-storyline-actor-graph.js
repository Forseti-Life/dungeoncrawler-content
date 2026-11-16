/**
 * @file
 * Storyline actor relationship graph.
 *
 * Draws a draft's actors as nodes grouped by their institution in one domain
 * of the institution engine (family, state or nation, faction, class,
 * ancestry and so on), with the attitudes they hold toward each other as
 * links. Selecting an actor opens a panel to manage its affiliations and
 * relationships; every change saves a new draft revision. The domains and
 * attitudes come from the engines; the draft is the only store.
 */
(function (Drupal, once, drupalSettings) {
  'use strict';

  const SVG = 'http://www.w3.org/2000/svg';
  const UNAFFILIATED = 'None';
  const BOND_SUGGESTIONS = ['spouse', 'parent', 'child', 'sibling', 'ally', 'rival', 'enemy', 'serves', 'commands', 'mentor'];
  const ATTITUDE_COLORS = { hostile: '#ef4444', unfriendly: '#f97316', indifferent: '#94a3b8', friendly: '#22c55e', helpful: '#14b8a6' };
  const NODE = { width: 150, height: 44, gapX: 26, gapY: 30, perRow: 3, pad: 26, title: 34 };

  const svgEl = (name, attributes = {}, text = null) => {
    const element = document.createElementNS(SVG, name);
    Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, String(value)));
    if (text !== null) element.textContent = text;
    return element;
  };
  const el = (name, attributes = {}, text = null) => {
    const element = document.createElement(name);
    Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, String(value)));
    if (text !== null) element.textContent = text;
    return element;
  };

  Drupal.behaviors.gmStorylineActorGraph = {
    attach(context) {
      once('gmStorylineActorGraph', '[data-actor-graph-root]', context).forEach((root) => {
        const settings = drupalSettings.dungeoncrawlerStorylineActorGraph;
        const canvas = root.querySelector('[data-actor-graph-canvas]');
        const panel = root.querySelector('[data-actor-graph-panel]');
        const status = root.querySelector('[data-actor-graph-status]');
        let draft = null;
        let selectedId = null;
        let csrfToken = '';
        // Every domain the graph can group by: authored ones, then the two
        // that follow from the actor's sheet.
        const domainLabels = { ...settings.domains, ...settings.derivedDomains };
        let groupDomain = 'family';
        const groupSelect = el('select', { id: 'actor-graph-group', class: 'form-select' });
        root.querySelector('[data-actor-graph-group]').append(el('label', { for: 'actor-graph-group' }, 'Group by'), groupSelect);
        Object.entries(domainLabels).forEach(([domain, label]) => groupSelect.appendChild(el('option', { value: domain }, label)));
        groupSelect.value = groupDomain;
        groupSelect.addEventListener('change', () => { groupDomain = groupSelect.value; render(); });

        const setStatus = (message, isError = false) => {
          status.textContent = message;
          status.classList.toggle('is-error', isError);
        };
        const actors = () => draft.payload.objects.actors.generation_specs;
        const relationships = () => draft.payload.objects.actors.relationships || [];
        // The institutions an actor belongs to in one domain.
        const institutionsOf = (actor, domain) => {
          if (domain === 'ancestry') return actor.ancestry ? [actor.ancestry] : [];
          if (domain === 'profession') return actor.class ? [actor.class] : [];
          return (actor.affiliations || []).filter((affiliation) => affiliation.domain === domain).map((affiliation) => affiliation.name);
        };
        const groupOf = (actor) => institutionsOf(actor, groupDomain)[0] || UNAFFILIATED;
        const knownInstitutions = (domain) => [...new Set(actors().flatMap((actor) => institutionsOf(actor, domain)))].sort();
        const nameOf = (actorId) => (actors().find((actor) => actor.actor_id === actorId) || {}).display_name || actorId;
        const reviewUrl = (actorId) => settings.actorUrlTemplate.replace('{actor_id}', encodeURIComponent(actorId));

        async function load() {
          setStatus('Loading draft…');
          const response = await fetch(settings.loadUrl, { credentials: 'same-origin' });
          const body = await response.json();
          if (!response.ok) throw new Error(body?.error?.code || `load_${response.status}`);
          draft = body.data;
          draft.payload.objects.actors.relationships = draft.payload.objects.actors.relationships || [];
          render();
          setStatus(`Draft revision ${draft.revision}. ${actors().length} actors, ${relationships().length} relationships.${draft.can_edit === true ? '' : ' Read-only: this draft is not yours to edit.'}`);
        }

        async function save(description) {
          if (draft.can_edit !== true) throw new Error('This draft is read-only.');
          setStatus('Saving…');
          if (!csrfToken) {
            const token = await fetch('/session/token', { credentials: 'same-origin' });
            if (!token.ok) throw new Error(`token_${token.status}`);
            csrfToken = (await token.text()).trim();
          }
          const response = await fetch(settings.saveUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ expected_revision: draft.revision, payload: draft.payload }),
          });
          const body = await response.json().catch(() => ({}));
          if (!response.ok) {
            // The stored draft is unchanged; show it again, not our edit.
            const code = body?.error?.code || body?.error || `save_${response.status}`;
            await load();
            throw new Error(response.status === 409 ? 'The draft changed elsewhere. It has been reloaded; repeat your change.' : `Not saved: ${code}`);
          }
          draft = body.data;
          draft.payload.objects.actors.relationships = draft.payload.objects.actors.relationships || [];
          render();
          setStatus(`${description} Saved as revision ${draft.revision}.`);
        }

        function layout() {
          const groups = new Map();
          actors().forEach((actor) => {
            const faction = groupOf(actor);
            if (!groups.has(faction)) groups.set(faction, []);
            groups.get(faction).push(actor);
          });
          const ordered = [...groups.entries()].sort(([a], [b]) => (a === UNAFFILIATED) - (b === UNAFFILIATED) || a.localeCompare(b));
          const positions = new Map();
          const boxes = [];
          let x = 20;
          let y = 20;
          let rowHeight = 0;
          const maxWidth = Math.max(900, canvas.clientWidth || 900);
          ordered.forEach(([faction, members]) => {
            const columns = Math.min(NODE.perRow, members.length);
            const rows = Math.ceil(members.length / NODE.perRow);
            const width = NODE.pad * 2 + columns * NODE.width + (columns - 1) * NODE.gapX;
            const height = NODE.title + NODE.pad + rows * NODE.height + (rows - 1) * NODE.gapY + NODE.pad;
            if (x + width > maxWidth && x > 20) {
              x = 20;
              y += rowHeight + 28;
              rowHeight = 0;
            }
            boxes.push({ faction, x, y, width, height, count: members.length });
            members.forEach((actor, index) => {
              positions.set(actor.actor_id, {
                x: x + NODE.pad + (index % NODE.perRow) * (NODE.width + NODE.gapX),
                y: y + NODE.title + NODE.pad + Math.floor(index / NODE.perRow) * (NODE.height + NODE.gapY),
              });
            });
            x += width + 28;
            rowHeight = Math.max(rowHeight, height);
          });
          return { boxes, positions, width: Math.max(maxWidth, ...boxes.map((box) => box.x + box.width + 20)), height: y + rowHeight + 20 };
        }

        function render() {
          const { boxes, positions, width, height } = layout();
          const svg = svgEl('svg', { viewBox: `0 0 ${width} ${height}`, width, height, class: 'actor-graph__svg', role: 'img', 'aria-label': `Actors grouped by ${domainLabels[groupDomain]} with their relationships` });
          const defs = svgEl('defs');
          const marker = svgEl('marker', { id: 'actor-graph-arrow', viewBox: '0 0 10 10', refX: 9, refY: 5, markerWidth: 7, markerHeight: 7, orient: 'auto-start-reverse' });
          marker.appendChild(svgEl('path', { d: 'M 0 0 L 10 5 L 0 10 z', class: 'actor-graph__arrow' }));
          defs.appendChild(marker);
          svg.appendChild(defs);

          boxes.forEach((box) => {
            svg.appendChild(svgEl('rect', { x: box.x, y: box.y, width: box.width, height: box.height, rx: 14, class: `actor-graph__faction${box.faction === UNAFFILIATED ? ' actor-graph__faction--none' : ''}` }));
            svg.appendChild(svgEl('text', { x: box.x + NODE.pad, y: box.y + 24, class: 'actor-graph__faction-title' }, `${box.faction === UNAFFILIATED ? `No ${domainLabels[groupDomain].toLowerCase()}` : box.faction} (${box.count})`));
          });

          const center = (actorId) => {
            const position = positions.get(actorId);
            return { x: position.x + NODE.width / 2, y: position.y + NODE.height / 2 };
          };
          relationships().forEach((relationship, index) => {
            const from = center(relationship.source_actor_id);
            const to = center(relationship.target_actor_id);
            const dx = to.x - from.x;
            const dy = to.y - from.y;
            const length = Math.hypot(dx, dy) || 1;
            // Stop at the node edge and bow the line so parallel links separate.
            const trim = (point, sign) => ({ x: point.x + sign * (dx / length) * (Math.abs(dx) > Math.abs(dy) ? NODE.width / 2 : NODE.height), y: point.y + sign * (dy / length) * (Math.abs(dx) > Math.abs(dy) ? NODE.height / 4 : NODE.height / 2) });
            const start = trim(from, 1);
            const end = trim(to, -1);
            // Longer links bow further, so they arc around the nodes between.
            const bend = Math.max(26, length * 0.22) + (index % 3) * 10;
            const mid = { x: (start.x + end.x) / 2 - (dy / length) * bend, y: (start.y + end.y) / 2 + (dx / length) * bend };
            const touches = selectedId && (relationship.source_actor_id === selectedId || relationship.target_actor_id === selectedId);
            const group = svgEl('g', { class: `actor-graph__link${touches ? ' is-active' : ''}${selectedId && !touches ? ' is-dim' : ''}` });
            group.appendChild(svgEl('path', { d: `M ${start.x} ${start.y} Q ${mid.x} ${mid.y} ${end.x} ${end.y}`, 'marker-end': 'url(#actor-graph-arrow)', style: `stroke: ${ATTITUDE_COLORS[relationship.attitude]}` }));
            const linkText = relationship.bond ? `${relationship.bond} · ${relationship.attitude}` : relationship.attitude;
            const label = svgEl('text', { x: mid.x, y: mid.y, 'text-anchor': 'middle', class: 'actor-graph__link-label' }, linkText);
            group.appendChild(label);
            group.appendChild(svgEl('title', {}, `${nameOf(relationship.source_actor_id)} is ${relationship.attitude} toward ${nameOf(relationship.target_actor_id)}${relationship.bond ? ` (${relationship.bond})` : ''}${relationship.notes ? `: ${relationship.notes}` : ''}`));
            svg.appendChild(group);
          });

          actors().forEach((actor) => {
            const position = positions.get(actor.actor_id);
            const group = svgEl('g', { class: `actor-graph__node${actor.actor_id === selectedId ? ' is-selected' : ''}`, tabindex: 0, role: 'button', 'aria-label': `${actor.display_name}, ${actor.role}` });
            group.appendChild(svgEl('rect', { x: position.x, y: position.y, width: NODE.width, height: NODE.height, rx: 10 }));
            group.appendChild(svgEl('rect', { x: position.x, y: position.y, width: 6, height: NODE.height, rx: 3, fill: ATTITUDE_COLORS[(actor.attitude || '').toLowerCase()] || '#94a3b8' }));
            const name = actor.display_name.length > 21 ? `${actor.display_name.slice(0, 20)}…` : actor.display_name;
            group.appendChild(svgEl('text', { x: position.x + 14, y: position.y + 19, class: 'actor-graph__node-name' }, name));
            group.appendChild(svgEl('text', { x: position.x + 14, y: position.y + 35, class: 'actor-graph__node-role' }, (actor.role || '').replace(/_/g, ' ')));
            group.appendChild(svgEl('title', {}, `${actor.display_name} — select to manage, double-click to open the review page`));
            const select = () => { selectedId = actor.actor_id; render(); };
            group.addEventListener('click', select);
            group.addEventListener('dblclick', () => window.open(reviewUrl(actor.actor_id), '_blank', 'noopener'));
            group.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); select(); } });
            svg.appendChild(group);
          });

          canvas.replaceChildren(svg);
          renderPanel();
        }

        function renderPanel() {
          panel.replaceChildren();
          const actor = actors().find((candidate) => candidate.actor_id === selectedId);
          if (!actor) {
            panel.appendChild(el('p', { class: 'actor-graph__hint' }, 'Select an actor to see its details and manage its affiliations and relationships. Double-click an actor to open its review page.'));
            return;
          }
          const editable = draft.can_edit === true;
          panel.appendChild(el('h3', {}, actor.display_name));
          panel.appendChild(el('p', { class: 'actor-graph__meta' }, `${(actor.role || '').replace(/_/g, ' ')} · ${actor.attitude || 'no attitude set'}`));
          const open = el('a', { href: reviewUrl(actor.actor_id), target: '_blank', rel: 'noopener', class: 'btn btn-primary actor-graph__open' }, 'Open actor review page');
          panel.appendChild(open);
          if (actor.motivation) panel.appendChild(el('p', { class: 'actor-graph__motivation' }, actor.motivation));

          // Affiliations, by the institution engine's domains.
          panel.appendChild(el('h4', {}, 'Affiliations'));
          const affiliationList = el('ul', { class: 'actor-graph__relationships' });
          Object.entries(settings.derivedDomains).forEach(([domain, label]) => {
            const value = institutionsOf(actor, domain)[0];
            affiliationList.appendChild(el('li', { class: 'actor-graph__meta' }, `${label}: ${value || 'not set — it comes from the actor\'s sheet'}`));
          });
          (actor.affiliations || []).forEach((affiliation) => {
            const item = el('li', {}, `${settings.domains[affiliation.domain]}: ${affiliation.name}`);
            if (editable) {
              const remove = el('button', { type: 'button', class: 'btn btn-link btn-sm actor-graph__remove', 'aria-label': `Remove ${affiliation.name}` }, 'Remove');
              remove.addEventListener('click', () => {
                const index = actor.affiliations.indexOf(affiliation);
                actor.affiliations.splice(index, 1);
                save(`${actor.display_name} left ${affiliation.name}.`).catch((error) => { actor.affiliations.splice(index, 0, affiliation); setStatus(error.message, true); });
              });
              item.appendChild(remove);
            }
            affiliationList.appendChild(item);
          });
          panel.appendChild(affiliationList);
          if (editable) {
            const affiliationForm = el('form', { class: 'actor-graph__form' });
            const domainSelect = el('select', { class: 'form-select', 'aria-label': 'Kind of institution' });
            Object.entries(settings.domains).forEach(([domain, label]) => domainSelect.appendChild(el('option', { value: domain }, label)));
            domainSelect.value = settings.domains[groupDomain] ? groupDomain : 'family';
            const nameInput = el('input', { type: 'text', class: 'form-text', list: 'actor-graph-institutions', placeholder: 'Name (Blackroot Clan)', required: 'required', 'aria-label': 'Institution name' });
            const names = el('datalist', { id: 'actor-graph-institutions' });
            const fillNames = () => names.replaceChildren(...knownInstitutions(domainSelect.value).map((value) => el('option', { value })));
            domainSelect.addEventListener('change', fillNames);
            fillNames();
            affiliationForm.append(domainSelect, nameInput, names, el('button', { type: 'submit', class: 'btn btn-secondary btn-sm' }, 'Add affiliation'));
            affiliationForm.addEventListener('submit', (event) => {
              event.preventDefault();
              const affiliation = { domain: domainSelect.value, name: nameInput.value.trim() };
              actor.affiliations = actor.affiliations || [];
              actor.affiliations.push(affiliation);
              save(`${actor.display_name} joined ${affiliation.name} (${settings.domains[affiliation.domain]}).`)
                .catch((error) => { actor.affiliations.splice(actor.affiliations.indexOf(affiliation), 1); setStatus(error.message, true); });
            });
            panel.appendChild(affiliationForm);
          }

          // Existing relationships.
          panel.appendChild(el('h4', {}, 'Relationships'));
          const list = el('ul', { class: 'actor-graph__relationships' });
          const mine = relationships().filter((relationship) => relationship.source_actor_id === actor.actor_id || relationship.target_actor_id === actor.actor_id);
          if (mine.length === 0) list.appendChild(el('li', { class: 'actor-graph__hint' }, 'None yet.'));
          mine.forEach((relationship) => {
            const outgoing = relationship.source_actor_id === actor.actor_id;
            const otherId = outgoing ? relationship.target_actor_id : relationship.source_actor_id;
            const item = el('li');
            const other = el('button', { type: 'button', class: 'actor-graph__other' }, nameOf(otherId));
            other.addEventListener('click', () => { selectedId = otherId; render(); });
            item.append(el('span', {}, outgoing ? `is ${relationship.attitude} toward ` : `← ${relationship.attitude} from `), other);
            if (relationship.bond) item.appendChild(el('span', { class: 'actor-graph__meta' }, ` (${relationship.bond})`));
            if (relationship.notes) item.appendChild(el('div', { class: 'actor-graph__meta' }, relationship.notes));
            if (editable) {
              const remove = el('button', { type: 'button', class: 'btn btn-link btn-sm actor-graph__remove', 'aria-label': `Remove relationship with ${nameOf(otherId)}` }, 'Remove');
              remove.addEventListener('click', () => {
                const all = draft.payload.objects.actors.relationships;
                const index = all.indexOf(relationship);
                all.splice(index, 1);
                save('Relationship removed.').catch((error) => { all.splice(index, 0, relationship); setStatus(error.message, true); });
              });
              item.appendChild(remove);
            }
            list.appendChild(item);
          });
          panel.appendChild(list);

          if (!editable) return;
          // Add a relationship from this actor.
          const form = el('form', { class: 'actor-graph__form' });
          form.appendChild(el('h4', {}, `Add: how ${actor.display_name} regards another actor`));
          const target = el('select', { class: 'form-select', required: 'required', 'aria-label': 'Other actor' });
          target.appendChild(el('option', { value: '' }, 'Choose the other actor…'));
          const taken = new Set(relationships().filter((relationship) => relationship.source_actor_id === actor.actor_id).map((relationship) => relationship.target_actor_id));
          actors().filter((candidate) => candidate.actor_id !== actor.actor_id && !taken.has(candidate.actor_id))
            .sort((a, b) => a.display_name.localeCompare(b.display_name))
            .forEach((candidate) => target.appendChild(el('option', { value: candidate.actor_id }, `${candidate.display_name} (${groupOf(candidate)})`)));
          const attitude = el('select', { class: 'form-select', required: 'required', 'aria-label': 'Attitude toward the other actor' });
          settings.attitudes.forEach((value) => attitude.appendChild(el('option', { value }, value)));
          attitude.value = 'indifferent';
          const bond = el('input', { type: 'text', class: 'form-text', list: 'actor-graph-bonds', placeholder: 'What they are to them (spouse, rival, serves…) — optional', 'aria-label': 'Bond' });
          const bonds = el('datalist', { id: 'actor-graph-bonds' });
          BOND_SUGGESTIONS.forEach((value) => bonds.appendChild(el('option', { value })));
          const notes = el('input', { type: 'text', class: 'form-text', placeholder: 'Notes (optional)', 'aria-label': 'Notes' });
          form.append(target, attitude, bond, bonds, notes, el('button', { type: 'submit', class: 'btn btn-secondary btn-sm' }, 'Add relationship'));
          form.addEventListener('submit', (event) => {
            event.preventDefault();
            const relationship = { source_actor_id: actor.actor_id, target_actor_id: target.value, attitude: attitude.value };
            if (bond.value.trim()) relationship.bond = bond.value.trim().toLowerCase();
            if (notes.value.trim()) relationship.notes = notes.value.trim();
            const all = draft.payload.objects.actors.relationships;
            all.push(relationship);
            save(`${actor.display_name} is ${relationship.attitude} toward ${nameOf(relationship.target_actor_id)}.`)
              .catch((error) => { all.splice(all.indexOf(relationship), 1); setStatus(error.message, true); });
          });
          panel.appendChild(form);
        }

        canvas.addEventListener('click', (event) => {
          if (event.target === canvas || event.target.tagName === 'svg') { selectedId = null; render(); }
        });
        load().catch((error) => setStatus(`Could not load the draft: ${error.message}`, true));
      });
    },
  };
})(Drupal, once, drupalSettings);
