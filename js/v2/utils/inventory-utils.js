/**
 * @file utils/inventory-utils.js
 *
 * Inventory, equipment, and skill helpers ported verbatim from hexmap.js.
 */

export function normalizeInventoryState(rawInventory, fallbackCurrency = {}) {
  if (Array.isArray(rawInventory)) {
    return {
      carried: rawInventory,
      worn: {},
      equipped: [],
      stashed: [],
      currency: fallbackCurrency,
      totalBulk: null,
      bodyShape: 'humanoid',
      slotFramework: {},
      slotState: {},
    };
  }
  if (!rawInventory || typeof rawInventory !== 'object') {
    return {
      carried: [],
      worn: {},
      equipped: [],
      stashed: [],
      currency: fallbackCurrency,
      totalBulk: null,
      bodyShape: 'humanoid',
      slotFramework: {},
      slotState: {},
    };
  }
  return {
    carried: Array.isArray(rawInventory.carried) ? rawInventory.carried : [],
    worn: rawInventory.worn && typeof rawInventory.worn === 'object' ? rawInventory.worn : {},
    equipped: Array.isArray(rawInventory.equipped) ? rawInventory.equipped : [],
    stashed: Array.isArray(rawInventory.stashed) ? rawInventory.stashed : [],
    currency: rawInventory.currency && typeof rawInventory.currency === 'object'
      ? rawInventory.currency
      : fallbackCurrency,
    totalBulk: Number.isFinite(Number(rawInventory.totalBulk ?? rawInventory.total_bulk))
      ? Number(rawInventory.totalBulk ?? rawInventory.total_bulk)
      : null,
    bodyShape: String(rawInventory.bodyShape || rawInventory.body_shape || 'humanoid'),
    slotFramework: rawInventory.slotFramework && typeof rawInventory.slotFramework === 'object' ? rawInventory.slotFramework : {},
    slotState: rawInventory.slotState && typeof rawInventory.slotState === 'object' ? rawInventory.slotState : {},
  };
}

export function normalizeSkillsList(skills) {
  if (Array.isArray(skills)) {
    return skills.map((skill) => {
      if (skill && typeof skill === 'object') {
        return {
          ...skill,
          name: skill.name || skill.label || skill.id || 'Skill',
          modifier: Number(skill.modifier ?? skill.bonus ?? 0),
          proficiency: skill.proficiency || skill.proficiencyRank || skill.rank || '',
        };
      }
      return {
        name: String(skill || 'Skill'),
        modifier: 0,
        proficiency: '',
      };
    });
  }
  if (!skills || typeof skills !== 'object') {
    return [];
  }
  return Object.entries(skills).map(([name, skillState]) => ({
    name,
    modifier: Number(
      (skillState && typeof skillState === 'object')
        ? (skillState.bonus ?? skillState.modifier ?? 0)
        : 0
    ),
    proficiency: (skillState && typeof skillState === 'object')
      ? (skillState.proficiencyRank || skillState.proficiency || skillState.rank || '')
      : String(skillState || ''),
  }));
}

export function collectCharacterSkillEntries(source) {
  const state = source?.data || source || {};
  const features = state.features || source?.features || {};
  const featTraining = features.featTraining || {};
  const conditionalSkillMods = Array.isArray(features?.featConditionalModifiers?.skills)
    ? features.featConditionalModifiers.skills
    : [];

  const skillMap = new Map();
  const upsert = (entry) => {
    const name = String(entry?.name || '').trim();
    if (!name) {
      return;
    }
    const key = name.toLowerCase();
    const existing = skillMap.get(key) || { name, modifier: 0, proficiency: '' };
    const nextModifier = Number(entry?.modifier ?? existing.modifier ?? 0);
    const nextProficiency = entry?.proficiency || existing.proficiency || '';
    skillMap.set(key, {
      name,
      modifier: Number.isFinite(nextModifier) ? nextModifier : 0,
      proficiency: nextProficiency,
    });
  };

  normalizeSkillsList(state.skills || source?.skills || []).forEach(upsert);

  if (Array.isArray(featTraining.skills)) {
    featTraining.skills.forEach((name) => {
      upsert({ name, modifier: 0, proficiency: 'trained' });
    });
  }

  if (Array.isArray(featTraining.lore)) {
    featTraining.lore.forEach((name) => {
      upsert({ name: `${name} Lore`, modifier: 0, proficiency: 'trained' });
    });
  }

  conditionalSkillMods.forEach((entry) => {
    const targetName = String(entry?.target || entry?.skill || entry?.name || '').trim();
    if (!targetName) {
      return;
    }
    const modifier = Number(entry?.modifier ?? entry?.value ?? 0);
    const existing = skillMap.get(targetName.toLowerCase()) || { name: targetName, modifier: 0, proficiency: '' };
    upsert({
      name: existing.name,
      modifier: (Number.isFinite(existing.modifier) ? existing.modifier : 0) + (Number.isFinite(modifier) ? modifier : 0),
      proficiency: existing.proficiency,
    });
  });

  return Array.from(skillMap.values());
}

export function formatInventoryItemList(items) {
  if (!Array.isArray(items) || items.length === 0) {
    return '';
  }
  return items
    .map((item) => {
      const itemName = item?.name || item;
      const itemId = item?.item_id || item?.id || '';
      const qty = item?.quantity || 1;
      const qtyLabel = qty > 1 ? ` x${qty}` : '';
      const equipped = item?.equipped ? ' <span class="item-tag equipped">E</span>' : '';
      const type = item?.type || item?.category || '';
      const bulk = item?.bulk != null ? item.bulk : '';
      const desc = item?.description || '';
      return `<li data-item-id="${itemId}" data-type="${type}" data-qty="${qty}" data-bulk="${bulk}" data-desc="${String(desc).replace(/"/g, '&quot;')}">${itemName}${qtyLabel}${equipped}</li>`;
    })
    .join('');
}

export function collectWornInventoryItems(worn = {}) {
  return [
    ...(Array.isArray(worn.weapons) ? worn.weapons : []),
    ...(Array.isArray(worn.accessories) ? worn.accessories : []),
    ...(worn.armor ? [worn.armor] : []),
    ...(worn.shield ? [worn.shield] : []),
  ].filter((item) => item && typeof item === 'object');
}

export function collectInventoryItems(inventory, equipment = []) {
  const normalizedInventory = normalizeInventoryState(inventory);
  const items = [
    ...collectWornInventoryItems(normalizedInventory.worn).map((item) => ({ ...item, __inventoryLocation: 'worn' })),
    ...normalizedInventory.carried.map((item) => ({ ...item, __inventoryLocation: 'carried' })),
    ...normalizedInventory.equipped.map((item) => ({ ...item, __inventoryLocation: 'equipped' })),
    ...normalizedInventory.stashed.map((item) => ({ ...item, __inventoryLocation: 'stashed' })),
    ...(Array.isArray(equipment) ? equipment.map((item) => ({ ...item, __inventoryLocation: item?.location || 'carried' })) : []),
  ];

  const dedupe = new Map();
  items.forEach((item) => {
    if (!item || typeof item !== 'object') {
      return;
    }
    const key = String(item.item_instance_id || `${item.item_id || item.id || item.name || 'item'}:${item.__inventoryLocation || 'carried'}`);
    if (!dedupe.has(key)) {
      dedupe.set(key, item);
    }
  });

  return Array.from(dedupe.values());
}

export function resolveInventoryFilterType(item) {
  if (!item || typeof item !== 'object') {
    return 'misc';
  }
  if (isWeaponItem(item)) {
    return 'weapon';
  }
  const equipSlot = String(item?.inventory_metadata?.equip_slot || item?.equip_slot || '').toLowerCase();
  const itemType = String(item?.item_type || item?.type || item?.category || '').toLowerCase();
  if (equipSlot === 'armor' || equipSlot === 'shield' || ['armor', 'shield'].includes(itemType)) {
    return 'armor';
  }
  if (isConsumableItem(item)) {
    return 'consumable';
  }
  if (item?.inventory_metadata?.container || itemType.includes('gear') || itemType.includes('tool') || itemType.includes('utility')) {
    return 'utility';
  }
  return 'misc';
}

export function getSlotLabel(slotKey, definition, slotIndex = null) {
  const baseLabel = String(definition?.label || slotKey || 'Slot');
  return slotIndex == null ? baseLabel : `${baseLabel} ${slotIndex + 1}`;
}

export function findItemSlotAssignment(slotState, item) {
  const itemInstanceId = String(item?.item_instance_id || '');
  const itemId = String(item?.item_id || item?.id || '');
  if (!slotState || typeof slotState !== 'object') {
    return null;
  }

  for (const [slotKey, slotValue] of Object.entries(slotState)) {
    if (slotKey === 'unassigned') {
      continue;
    }
    if (Array.isArray(slotValue)) {
      for (let index = 0; index < slotValue.length; index += 1) {
        const entry = slotValue[index];
        if (!entry || typeof entry !== 'object') {
          continue;
        }
        if ((itemInstanceId && String(entry.item_instance_id || '') === itemInstanceId) || (!itemInstanceId && itemId && String(entry.item_id || '') === itemId)) {
          return { slotKey, slotIndex: index, entry };
        }
      }
      continue;
    }
    if (slotValue && typeof slotValue === 'object') {
      if ((itemInstanceId && String(slotValue.item_instance_id || '') === itemInstanceId) || (!itemInstanceId && itemId && String(slotValue.item_id || '') === itemId)) {
        return { slotKey, slotIndex: null, entry: slotValue };
      }
    }
  }

  return null;
}

export function getCompatibleSlotOptions(item, inventory) {
  const framework = inventory?.slotFramework || {};
  const slotState = inventory?.slotState || {};
  const metadata = item?.inventory_metadata || {};
  const equipSlot = String(metadata.equip_slot || item?.equip_slot || '').toLowerCase();
  const preferredWornSlot = String(metadata.worn_slot || item?.worn_slot || '').toLowerCase();
  const handSlotsRequired = Math.max(0, Number(metadata.hand_slots_required ?? item?.hand_slots_required ?? 0));
  const explicitlyEquippable = Boolean(metadata.equippable ?? item?.equippable);
  const hasEquipHint = explicitlyEquippable || equipSlot !== '' || preferredWornSlot !== '' || handSlotsRequired > 0;
  const currentAssignment = findItemSlotAssignment(slotState, item);
  const options = [];

  const canUseSlot = (slotKey, slotIndex = null) => {
    if (!Object.prototype.hasOwnProperty.call(framework, slotKey)) {
      return false;
    }
    if (currentAssignment && currentAssignment.slotKey === slotKey && currentAssignment.slotIndex === slotIndex) {
      return true;
    }
    const slotValue = slotState[slotKey];
    if (Array.isArray(slotValue)) {
      if (slotIndex == null || !Object.prototype.hasOwnProperty.call(slotValue, slotIndex)) {
        return false;
      }
      return !slotValue[slotIndex];
    }
    return !slotValue;
  };

  const pushSlotOptions = (slotKey) => {
    if (!Object.prototype.hasOwnProperty.call(framework, slotKey)) {
      return;
    }
    const definition = framework[slotKey] || {};
    const count = definition.count;
    if (count == null) {
      options.push({ slotKey, slotIndex: null, label: getSlotLabel(slotKey, definition) });
      return;
    }
    if (count === 1) {
      if (canUseSlot(slotKey, null)) {
        options.push({ slotKey, slotIndex: null, label: getSlotLabel(slotKey, definition) });
      }
      return;
    }
    for (let index = 0; index < count; index += 1) {
      if (canUseSlot(slotKey, index)) {
        options.push({ slotKey, slotIndex: index, label: getSlotLabel(slotKey, definition, index) });
      }
    }
  };

  if (equipSlot === 'held') {
    if (handSlotsRequired >= 2) {
      if (canUseSlot('main_hand', null) && canUseSlot('off_hand', null)) {
        options.push({ slotKey: 'main_hand', slotIndex: null, label: 'Both Hands' });
      }
      return options;
    }
    if (canUseSlot('main_hand', null)) {
      options.push({ slotKey: 'main_hand', slotIndex: null, label: 'Main Hand' });
    }
    if (canUseSlot('off_hand', null)) {
      options.push({ slotKey: 'off_hand', slotIndex: null, label: 'Off Hand' });
    }
    return options;
  }

  if (equipSlot === 'armor') {
    if (Object.prototype.hasOwnProperty.call(framework, 'armor')) {
      pushSlotOptions('armor');
    } else {
      pushSlotOptions('body');
    }
    return options;
  }

  if (equipSlot === 'shield') {
    pushSlotOptions('shield');
    return options;
  }

  if (!hasEquipHint) {
    return options;
  }

  const resolvedWornSlot = preferredWornSlot || 'worn';
  if (Object.prototype.hasOwnProperty.call(framework, resolvedWornSlot)) {
    pushSlotOptions(resolvedWornSlot);
  } else {
    pushSlotOptions('worn');
  }

  return options;
}

export function renderInventorySlotGrid(inventory, feedback = null) {
  const framework = inventory?.slotFramework || {};
  const slotState = inventory?.slotState || {};
  const entries = [];
  const highlightedSlotKey = String(feedback?.slotKey || '').trim();
  const highlightedSlotIndex = Number.isInteger(feedback?.slotIndex) ? feedback.slotIndex : null;
  const slotTone = String(feedback?.tone || '').trim();
  const buildUnequipButton = (occupant, slotKey, slotIndex = null) => {
    const itemInstanceId = String(occupant?.item_instance_id || '').trim();
    if (!itemInstanceId) {
      return '';
    }
    const slotIndexAttr = Number.isInteger(slotIndex) ? ` data-slot-index="${slotIndex}"` : '';
    return `
      <div class="inventory-slot__actions">
        <button
          type="button"
          class="inventory-slot__button"
          data-inventory-action="unequip"
          data-item-instance-id="${escapeTooltipAttr(itemInstanceId)}"
          data-item-id="${escapeTooltipAttr(occupant?.item_id || '')}"
          data-item-name="${escapeTooltipAttr(occupant?.name || occupant?.item_id || 'Item')}"
          data-slot-key="${escapeTooltipAttr(slotKey)}"${slotIndexAttr}
        >Unequip</button>
      </div>
    `;
  };

  Object.entries(framework).forEach(([slotKey, definition]) => {
    const count = definition?.count;
    const isSlotHighlighted = highlightedSlotKey !== '' && highlightedSlotKey === slotKey;
    if (count == null) {
      const assigned = Array.isArray(slotState[slotKey]) ? slotState[slotKey].filter(Boolean) : [];
      entries.push(`
        <div class="inventory-slot${isSlotHighlighted ? ` inventory-slot--highlight inventory-slot--highlight-${escapeTooltipAttr(slotTone || 'info')}` : ''}">
          <div class="inventory-slot__label">${escapeTooltipAttr(getSlotLabel(slotKey, definition))}</div>
          <div class="inventory-slot__item">${assigned.length ? escapeTooltipAttr(assigned.map((entry) => entry?.name || entry?.item_id || 'Item').join(', ')) : 'Empty'}</div>
          <div class="inventory-slot__meta">${assigned.length ? `${assigned.length} assigned` : 'Available'}</div>
        </div>
      `);
      return;
    }

    if (count === 1) {
      const occupant = slotState[slotKey];
      entries.push(`
        <div class="inventory-slot${occupant ? '' : ' inventory-slot--empty'}${isSlotHighlighted ? ` inventory-slot--highlight inventory-slot--highlight-${escapeTooltipAttr(slotTone || 'info')}` : ''}">
          <div class="inventory-slot__label">${escapeTooltipAttr(getSlotLabel(slotKey, definition))}</div>
          <div class="inventory-slot__item">${escapeTooltipAttr(occupant?.name || occupant?.item_id || 'Empty')}</div>
          <div class="inventory-slot__meta">${occupant ? escapeTooltipAttr(occupant.equip_slot || 'Equipped') : 'Available'}</div>
          ${occupant ? buildUnequipButton(occupant, slotKey) : ''}
        </div>
      `);
      return;
    }

    for (let index = 0; index < count; index += 1) {
      const occupant = Array.isArray(slotState[slotKey]) ? slotState[slotKey][index] : null;
      const isIndexedSlotHighlighted = isSlotHighlighted && highlightedSlotIndex === index;
      entries.push(`
        <div class="inventory-slot${occupant ? '' : ' inventory-slot--empty'}${isIndexedSlotHighlighted ? ` inventory-slot--highlight inventory-slot--highlight-${escapeTooltipAttr(slotTone || 'info')}` : ''}">
          <div class="inventory-slot__label">${escapeTooltipAttr(getSlotLabel(slotKey, definition, index))}</div>
          <div class="inventory-slot__item">${escapeTooltipAttr(occupant?.name || occupant?.item_id || 'Empty')}</div>
          <div class="inventory-slot__meta">${occupant ? escapeTooltipAttr(occupant.equip_slot || 'Equipped') : 'Available'}</div>
          ${occupant ? buildUnequipButton(occupant, slotKey, index) : ''}
        </div>
      `);
    }
  });

  return entries.join('') || '<div class="inventory-slot inventory-slot--empty">No slot data loaded</div>';
}

export function renderInventoryPanelList(items, inventory, feedback = null) {
  if (!Array.isArray(items) || items.length === 0) {
    return '<li class="inventory-panel__empty">No items in inventory</li>';
  }

  return items.map((item) => {
    const itemName = item?.name || item?.item_id || item?.id || 'Item';
    const itemId = item?.item_id || item?.id || '';
    const itemInstanceId = item?.item_instance_id || '';
    const qty = Math.max(1, Number(item?.quantity ?? 1));
    const bulk = item?.bulk != null ? String(item.bulk) : '';
    const desc = item?.description || item?.desc || '';
    const type = resolveInventoryFilterType(item);
    const icon = { weapon: 'W', armor: 'A', consumable: 'C', utility: 'U', misc: 'M' }[type] || 'I';
    const assignment = findItemSlotAssignment(inventory?.slotState || {}, item);
    const assignmentLabel = assignment
      ? getSlotLabel(assignment.slotKey, inventory?.slotFramework?.[assignment.slotKey] || { label: assignment.slotKey }, assignment.slotIndex)
      : '';
    const slotOptions = getCompatibleSlotOptions(item, inventory);
    const canAssign = Boolean(itemInstanceId) && slotOptions.length > 0;
    const isWorn = String(item?.__inventoryLocation || item?.location || '') === 'worn';
    const isFeedbackItem = String(feedback?.itemInstanceId || '') !== '' && String(feedback?.itemInstanceId || '') === String(itemInstanceId);
    const feedbackTone = String(feedback?.tone || '').trim();
    const isPending = isFeedbackItem && feedbackTone === 'pending';
    const locationLabel = isWorn ? `Equipped${assignmentLabel ? ` · ${assignmentLabel}` : ''}` : String(item?.__inventoryLocation || item?.location || 'carried').replace(/_/g, ' ');
    const optionMarkup = slotOptions.map((option) => {
      const value = `${option.slotKey}::${option.slotIndex == null ? '' : option.slotIndex}`;
      const selected = (assignment && assignment.slotKey === option.slotKey && assignment.slotIndex === option.slotIndex)
        || (!assignment && slotOptions[0] === option)
        ? ' selected'
        : '';
      return `<option value="${escapeTooltipAttr(value)}"${selected}>${escapeTooltipAttr(option.label)}</option>`;
    }).join('');
    const assignButtonLabel = isPending && feedback?.action === 'assign' ? 'Assigning...' : 'Assign';
    const unequipButtonLabel = isPending && feedback?.action === 'unequip' ? 'Unequipping...' : 'Unequip';
    const assignButtonsMarkup = slotOptions.map((option, index) => {
      const slotButtonLabel = slotOptions.length === 1 ? `Assign to ${option.label}` : option.label;
      return `
        <button
          type="button"
          class="inv-item__button${index === 0 ? ' inv-item__button--primary' : ''}"
          data-inventory-action="assign"
          data-item-instance-id="${escapeTooltipAttr(itemInstanceId)}"
          data-item-id="${escapeTooltipAttr(itemId)}"
          data-item-name="${escapeTooltipAttr(itemName)}"
          data-slot-key="${escapeTooltipAttr(option.slotKey)}"
          data-slot-label="${escapeTooltipAttr(option.label)}"${option.slotIndex == null ? '' : ` data-slot-index="${escapeTooltipAttr(option.slotIndex)}"`}
          ${isPending ? ' disabled' : ''}
        >${escapeTooltipAttr(isPending && feedback?.action === 'assign' && index === 0 ? assignButtonLabel : slotButtonLabel)}</button>
      `;
    }).join('');
    const actionMarkup = !itemInstanceId
      ? '<span class="inv-item__status">Static item</span>'
      : (canAssign
        ? `
          ${assignButtonsMarkup}
          <select class="inv-item__slot-select" data-slot-select hidden aria-hidden="true"${isPending ? ' disabled' : ''}>
            ${optionMarkup}
          </select>
          ${isWorn ? `<button type="button" class="inv-item__button" data-inventory-action="unequip" data-item-instance-id="${escapeTooltipAttr(itemInstanceId)}" data-item-id="${escapeTooltipAttr(itemId)}" data-item-name="${escapeTooltipAttr(itemName)}"${isPending ? ' disabled' : ''}>${unequipButtonLabel}</button>` : ''}
        `
        : (isWorn
          ? `<button type="button" class="inv-item__button" data-inventory-action="unequip" data-item-instance-id="${escapeTooltipAttr(itemInstanceId)}" data-item-id="${escapeTooltipAttr(itemId)}" data-item-name="${escapeTooltipAttr(itemName)}"${isPending ? ' disabled' : ''}>${unequipButtonLabel}</button>`
          : '<span class="inv-item__status">No valid slot</span>'));

    return `
      <li
        class="inv-item${isWorn ? ' inv-item--equipped' : ''}${isPending ? ' inv-item--pending' : ''}${isFeedbackItem && feedbackTone === 'success' ? ' inv-item--highlight-success' : ''}${isFeedbackItem && feedbackTone === 'error' ? ' inv-item--highlight-error' : ''}"
        data-type="${escapeTooltipAttr(type)}"
        data-item-id="${escapeTooltipAttr(itemId)}"
        data-item-instance-id="${escapeTooltipAttr(itemInstanceId)}"
        data-desc="${escapeTooltipAttr(desc)}"
        data-bulk="${escapeTooltipAttr(bulk)}"
        data-tooltip-type="${escapeTooltipAttr(type)}"
      >
        <span class="inv-item__icon">${icon}</span>
        <div class="inv-item__info">
          <div class="inv-item__name">${escapeTooltipAttr(itemName)}${qty > 1 ? ` ×${qty}` : ''}</div>
          <div class="inv-item__meta">${escapeTooltipAttr(locationLabel)}${bulk ? ` · Bulk ${escapeTooltipAttr(bulk)}` : ''}</div>
        </div>
        <div class="inv-item__actions">
          ${actionMarkup}
        </div>
      </li>
    `;
  }).join('');
}

export function isConsumableItem(item) {
  if (!item || typeof item !== 'object') {
    return false;
  }
  const searchSpace = [
    item.type,
    item.category,
    item.subtype,
    item.traits,
    item.name,
  ]
    .flatMap((value) => Array.isArray(value) ? value : [value])
    .filter(Boolean)
    .join(' ')
    .toLowerCase();

  return [
    'consumable',
    'potion',
    'elixir',
    'bomb',
    'scroll',
    'oil',
    'mutagen',
    'talisman',
    'wand',
    'ammo',
    'ammunition',
    'food',
    'ration',
    'water',
    'waterskin',
  ].some((keyword) => searchSpace.includes(keyword));
}

export function extractConsumableItems(inventory, equipment = []) {
  const normalizedInventory = normalizeInventoryState(inventory);
  const worn = normalizedInventory.worn || {};
  const wornItems = [
    ...(Array.isArray(worn.weapons) ? worn.weapons : []),
    ...(Array.isArray(worn.accessories) ? worn.accessories : []),
    ...(worn.armor ? [worn.armor] : []),
  ];
  return [...normalizedInventory.carried, ...wornItems, ...(Array.isArray(equipment) ? equipment : [])]
    .filter((item) => item && typeof item === 'object')
    .filter(isConsumableItem);
}

export function isWeaponItem(item) {
  if (!item || typeof item !== 'object') {
    return false;
  }
  const searchSpace = [
    item.type,
    item.category,
    item.subtype,
    item.group,
    item.weapon_group,
    item.traits,
    item.name,
    item.damage,
  ]
    .flatMap((value) => Array.isArray(value) ? value : [value])
    .filter(Boolean)
    .join(' ')
    .toLowerCase();

  return ['weapon', 'strike', 'bow', 'blade', 'hammer', 'sword', 'axe', 'spear', 'crossbow'].some((keyword) => searchSpace.includes(keyword));
}

export function extractReadyWeapons(inventory, equipment = []) {
  const normalizedInventory = normalizeInventoryState(inventory);
  const worn = normalizedInventory.worn || {};
  const wornWeapons = (Array.isArray(worn.weapons) ? worn.weapons : [])
    .filter((item) => item && typeof item === 'object')
    .map((item) => ({ ...item, __ready: true, __source: 'equipped' }));
  const packedWeapons = [...normalizedInventory.carried, ...(Array.isArray(equipment) ? equipment : [])]
    .filter((item) => item && typeof item === 'object')
    .filter(isWeaponItem)
    .map((item) => ({
      ...item,
      __ready: Boolean(item.equipped || item.readied || item.ready || item.worn),
      __source: item.equipped || item.readied || item.ready || item.worn ? 'equipped' : 'carried',
    }));
  const dedupe = new Map();
  [...wornWeapons, ...packedWeapons].forEach((item) => {
    const key = String(item?.item_id || item?.id || item?.name || '').trim().toLowerCase();
    if (!key || dedupe.has(key)) {
      return;
    }
    dedupe.set(key, item);
  });

  const allWeapons = Array.from(dedupe.values());
  const readyWeapons = allWeapons.filter((item) => item.__ready);
  return (readyWeapons.length ? readyWeapons : allWeapons).map((item) => ({
    id: item.item_id || item.id || item.name || 'weapon',
    name: item.name || item.item_id || 'Weapon',
    damage: item.damage || item.weapon_damage || '',
    traits: Array.isArray(item.traits) ? item.traits.join(', ') : (item.traits || ''),
    description: item.description || item.desc || '',
    sourceLabel: item.__source === 'equipped' ? 'Ready' : 'Carried',
  }));
}

export function resolveConsumableHealing(item) {
  const candidates = [
    item?.healing?.amount,
    item?.healing_amount,
    item?.heal_amount,
    item?.healing,
    item?.effects?.healing,
  ];

  for (const candidate of candidates) {
    const numeric = Number(candidate);
    if (Number.isFinite(numeric) && numeric > 0) {
      return numeric;
    }
  }

  return 0;
}

export function buildActionRailEntrySummary(parts) {
  return (Array.isArray(parts) ? parts : [])
    .map((part) => String(part ?? '').trim())
    .filter(Boolean)
    .join(' • ');
}

export function estimateInventoryBulk(items) {
  if (!Array.isArray(items) || items.length === 0) {
    return 0;
  }
  let total = 0;
  items.forEach((item) => {
    const qty = Math.max(1, Number(item?.quantity ?? 1));
    const rawBulk = item?.bulk;
    if (rawBulk == null || rawBulk === '') {
      return;
    }
    if (typeof rawBulk === 'number') {
      total += rawBulk * qty;
      return;
    }
    const normalized = String(rawBulk).trim().toLowerCase();
    if (normalized === 'l') {
      total += 0.1 * qty;
      return;
    }
    const numeric = Number(normalized);
    if (Number.isFinite(numeric)) {
      total += numeric * qty;
    }
  });
  return total;
}

export function formatBulkValue(value) {
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) {
    return '0';
  }
  return Number.isInteger(numeric) ? String(numeric) : numeric.toFixed(1).replace(/\.0$/, '');
}

export function extractRuntimeResourcesFromEntityState(statePayload, baseResources = {}) {
  if (!statePayload || typeof statePayload !== 'object') {
    return null;
  }

  const nextResources = { ...(baseResources || {}) };
  const nestedState = statePayload.state && typeof statePayload.state === 'object'
    ? statePayload.state
    : {};
  let hasChanges = false;

  const slotSource = (statePayload.spell_slots && typeof statePayload.spell_slots === 'object')
    ? statePayload.spell_slots
    : ((nestedState.spell_slots && typeof nestedState.spell_slots === 'object') ? nestedState.spell_slots : null);
  if (slotSource) {
    const spellSlots = {};
    Object.entries(slotSource).forEach(([slotKey, slotState]) => {
      const rank = getSpellRankNumber(slotKey);
      if (rank == null || rank === 0) {
        return;
      }
      const max = Math.max(0, Number(slotState?.max ?? 0));
      const used = Math.max(0, Number(slotState?.used ?? 0));
      spellSlots[String(rank)] = {
        current: Math.max(0, max - used),
        max,
      };
    });
    if (Object.keys(spellSlots).length > 0) {
      nextResources.spellSlots = {
        ...normalizeDisplayedSpellSlots(baseResources?.spellSlots, null),
        ...spellSlots,
      };
      hasChanges = true;
    }
  }

  const focusCurrent = statePayload.focus_points ?? nestedState.focus_points;
  if (focusCurrent != null && Number.isFinite(Number(focusCurrent))) {
    const current = Math.max(0, Number(focusCurrent));
    const existingFocus = (baseResources && typeof baseResources.focusPoints === 'object')
      ? baseResources.focusPoints
      : {};
    nextResources.focusPoints = {
      ...existingFocus,
      current,
      max: Math.max(current, Number(existingFocus.max ?? current)),
    };
    hasChanges = true;
  }

  return hasChanges ? nextResources : null;
}
