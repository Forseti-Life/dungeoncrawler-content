/**
 * @file
 * Character Creation Wizard JavaScript
 * 
 * Handles the interactive multi-step character creation process for PF2E.
 * 
 * Data Storage Architecture:
 * -------------------------
 * Character data is saved to the dc_campaign_characters table with a hybrid
 * hot-column + JSON design pattern:
 * 
 * Hot Columns (indexed, frequently accessed during gameplay):
 * - name, ancestry, class, level (identity/filtering)
 * - hp_current, hp_max, armor_class, experience_points (combat state)
 * - position_q, position_r, last_room_id (movement/location)
 * 
 * JSON Storage (character_data field, accessed during creation/viewing):
 * - concept, heritage, deity, alignment, age, gender (flavor/roleplay)
 * - abilities (str, dex, con, int, wis, cha scores)
 * - appearance, personality, backstory (character details)
 * - equipment, gold (inventory state)
 * 
 * This pattern optimizes for:
 * - Fast combat/movement queries using hot columns
 * - Flexible character creation data in JSON
 * - Reduced database columns for rarely-queried fields
 */

(function ($, Drupal) {
  'use strict';

  // Constants
  const CONSTANTS = {
    // Ability score defaults and limits
    DEFAULT_ABILITY_SCORE: 10,
    MAX_ABILITY_SCORE: 18,
    ABILITY_BOOST_AMOUNT: 2,
    ABILITY_BOOST_AT_18: 1,
    ABILITY_FLAW_AMOUNT: 2,
    
    // Character creation defaults
    STARTING_GOLD: 15,
    MIN_NAME_LENGTH: 2,
    FINAL_STEP: 8,
    
    // Background boosts
    REQUIRED_BG_BOOSTS: 2,
    REQUIRED_FREE_BOOSTS: 4,
    
    // Base values for calculations
    BASE_AC: 10,
    
    // API endpoints
    API_SAVE_CHARACTER: '/api/character/save',
    API_SESSION_TOKEN: '/session/token'
  };

  // CSRF Token cache — cleared on 403 so the next request fetches a fresh one.
  let csrfToken = null;

  /**
   * Get CSRF token for API requests.
   * @param {boolean} [forceRefresh=false] - Bypass cache and fetch a new token.
   * @returns {Promise<string>} The CSRF token.
   */
  async function getCsrfToken(forceRefresh = false) {
    if (csrfToken && !forceRefresh) {
      return csrfToken;
    }

    try {
      const response = await fetch(CONSTANTS.API_SESSION_TOKEN);
      csrfToken = await response.text();
      return csrfToken;
    } catch (error) {
      console.error('Failed to fetch CSRF token:', error);
      throw error;
    }
  }

  /**
   * Show an inline error message inside a wizard step.
   *
   * Replaces any existing error banner in the step. Auto-dismissed when the
   * user interacts with the step again (via clearStepError).
   *
   * @param {string} message - Human-readable error text.
   * @param {string|null} [stepId=null] - DOM id of the step container, e.g.
   *   'step1'. Defaults to the currently active step.
   */
  function showStepError(message, stepId = null) {
    const container = stepId
      ? document.getElementById(stepId)
      : document.querySelector('.creation-step:not(.hidden)');
    if (!container) {
      // Fallback: log + show a non-blocking banner at the top of the wizard.
      console.error('Character creation error:', message);
      showWizardBanner(message, 'error');
      return;
    }

    let banner = container.querySelector('.cc-step-error');
    if (!banner) {
      banner = document.createElement('div');
      banner.className = 'cc-step-error';
      banner.setAttribute('role', 'alert');
      container.insertBefore(banner, container.firstChild);
    }
    banner.textContent = message;
    banner.style.display = 'block';
  }

  /**
   * Clear the inline error banner for a step.
   * @param {string|null} [stepId=null]
   */
  function clearStepError(stepId = null) {
    const container = stepId
      ? document.getElementById(stepId)
      : document.querySelector('.creation-step:not(.hidden)');
    if (!container) return;
    const banner = container.querySelector('.cc-step-error');
    if (banner) banner.style.display = 'none';
  }

  /**
   * Show a wizard-level (full-width) dismissable banner.
   *
   * @param {string} message
   * @param {'error'|'info'|'success'} [type='error']
   */
  function showWizardBanner(message, type = 'error') {
    let banner = document.getElementById('cc-wizard-banner');
    if (!banner) {
      banner = document.createElement('div');
      banner.id = 'cc-wizard-banner';
      const wizard = document.getElementById('characterCreationWizard');
      if (wizard) wizard.insertBefore(banner, wizard.firstChild);
    }
    banner.className = 'cc-wizard-banner cc-wizard-banner--' + type;
    banner.setAttribute('role', 'alert');
    banner.innerHTML =
      '<span class="cc-wizard-banner__msg">' + message + '</span>' +
      '<button class="cc-wizard-banner__close" aria-label="Dismiss" onclick="this.parentElement.style.display=\'none\'">✕</button>';
    banner.style.display = 'flex';
  }

  // Character state management
  const characterData = {
    character_id: null, // Tracks the database ID for draft saves
    step: 1,
    name: '',
    concept: '',
    ancestry: null,
    heritage: null,
    background: null,
    class: null,
    abilities: {
      str: CONSTANTS.DEFAULT_ABILITY_SCORE,
      dex: CONSTANTS.DEFAULT_ABILITY_SCORE,
      con: CONSTANTS.DEFAULT_ABILITY_SCORE,
      int: CONSTANTS.DEFAULT_ABILITY_SCORE,
      wis: CONSTANTS.DEFAULT_ABILITY_SCORE,
      cha: CONSTANTS.DEFAULT_ABILITY_SCORE
    },
    alignment: '',
    deity: '',
    age: null,
    gender: '',
    appearance: '',
    personality: '',
    backstory: '',
    equipment: [],
    gold: CONSTANTS.STARTING_GOLD
  };

  // Ancestry data
  const ancestryData = {
    'dwarf': {
      name: 'Dwarf',
      boosts: ['con', 'wis', 'free'],
      flaws: ['cha'],
      hp: 10,
      detail: 'Dwarves are short and stocky, standing about a foot shorter than most humans. They have wide, compact bodies and burly frames. Dwarves of all genders pride themselves on the length of their hair, which they often braid into intricate patterns, some of which represent specific clans.',
      heritages: [
        {id: 'ancient-blooded', name: 'Ancient-Blooded', benefit: 'Resistance to magic'},
        {id: 'forge', name: 'Forge Dwarf', benefit: 'Fire resistance'},
        {id: 'rock', name: 'Rock Dwarf', benefit: 'Extended darkvision'},
        {id: 'strong-blooded', name: 'Strong-Blooded', benefit: 'Poison resistance'}
      ],
      traits: ['Darkvision', 'Clan Dagger']
    },
    'elf': {
      name: 'Elf',
      boosts: ['dex', 'int', 'free'],
      flaws: ['con'],
      hp: 6,
      detail: 'Elves are tall, slender humanoids with pointed ears and otherworldly grace. They have keen senses and deep appreciation for art, nature, and magic. Most elves have fair skin in shades common among humans, though occasionally an elf has skin in hues of blue or copper.',
      heritages: [
        {id: 'arctic', name: 'Arctic Elf', benefit: 'Cold resistance'},
        {id: 'cavern', name: 'Cavern Elf', benefit: 'Darkvision'},
        {id: 'seer', name: 'Seer Elf', benefit: 'Detect magic'},
        {id: 'whisper', name: 'Whisper Elf', benefit: 'Enhanced hearing'},
        {id: 'woodland', name: 'Woodland Elf', benefit: 'Climb speed'}
      ],
      traits: ['Low-Light Vision']
    },
    'gnome': {
      name: 'Gnome',
      boosts: ['con', 'cha', 'free'],
      flaws: ['str'],
      hp: 8,
      detail: 'Gnomes are short folk with a primal magical connection to the First World. They have an insatiable need for novelty and discovery that manifests as a physical necessity known as the Bleaching. Without new experiences, a gnome begins to lose color and enthusiasm.',
      heritages: [
        {id: 'chameleon', name: 'Chameleon Gnome', benefit: 'Change colors'},
        {id: 'fey-touched', name: 'Fey-Touched', benefit: 'First World magic'},
        {id: 'sensate', name: 'Sensate Gnome', benefit: 'Enhanced senses'},
        {id: 'umbral', name: 'Umbral Gnome', benefit: 'Darkvision'}
      ],
      traits: ['Low-Light Vision']
    },
    'goblin': {
      name: 'Goblin',
      boosts: ['dex', 'cha', 'free'],
      flaws: ['wis'],
      hp: 6,
      detail: 'Goblins are short, scrappy humanoids who tend toward mayhem and mischief. They have large heads with beady eyes and wide mouths filled with sharp teeth. Their skin ranges from green to gray to blue, and they often have large, mobile ears.',
      heritages: [
        {id: 'charhide', name: 'Charhide Goblin', benefit: 'Fire resistance'},
        {id: 'irongut', name: 'Irongut Goblin', benefit: 'Eat anything safely'},
        {id: 'razortooth', name: 'Razortooth Goblin', benefit: 'Bite attack'},
        {id: 'snow', name: 'Snow Goblin', benefit: 'Cold resistance'}
      ],
      traits: ['Darkvision']
    },
    'halfling': {
      name: 'Halfling',
      boosts: ['dex', 'wis', 'free'],
      flaws: ['str'],
      hp: 6,
      detail: 'Halflings are short folk who seem far more at home traveling than staying put. They have a knack for getting along with others and an uncanny luck that sees them through dangerous situations. Halflings typically have brown or black hair in tight ringlets.',
      heritages: [
        {id: 'gutsy', name: 'Gutsy Halfling', benefit: 'Bonus vs fear'},
        {id: 'hillock', name: 'Hillock Halfling', benefit: 'Faster healing'},
        {id: 'nomadic', name: 'Nomadic Halfling', benefit: 'Extra languages'},
        {id: 'twilight', name: 'Twilight Halfling', benefit: 'Low-light vision'}
      ],
      traits: ['Keen Eyes']
    },
    'human': {
      name: 'Human',
      boosts: ['free', 'free'],
      flaws: [],
      hp: 8,
      detail: 'Humans have incredible diversity in appearance, culture, and ambition. They have shorter lifespans than most ancestries, but their drive to achieve leaves powerful marks on the world. Humans can excel at any profession or pursuit.',
      heritages: [
        {id: 'versatile', name: 'Versatile Heritage', benefit: 'Extra general feat'}
      ],
      traits: []
    },
    'mobians': {
      name: 'Mobians',
      boosts: ['dex', 'free'],
      flaws: [],
      hp: 8,
      detail: 'Anthropomorphic animals with unique abilities based on their animal type.',
      heritages: [],
      traits: []
    },
    'fungians': {
      name: 'Fungians',
      boosts: ['con', 'wis', 'free'],
      flaws: ['dex'],
      hp: 10,
      detail: 'Fungal beings with mysterious origins and unique biological traits.',
      heritages: [],
      traits: []
    },
    'automaton': {
      name: 'Automaton',
      boosts: ['str', 'free'],
      flaws: [],
      hp: 8,
      detail: 'Sentient constructs powered by ancient magic, blending mechanical precision with conscious thought.',
      heritages: [],
      traits: []
    },
    'undead': {
      name: 'Undead',
      boosts: ['free', 'free'],
      flaws: ['con'],
      hp: 6,
      detail: 'Unliving creatures bound by dark forces, existing beyond death.',
      heritages: [],
      traits: []
    },
    'talking-animal': {
      name: 'Talking Animal',
      boosts: ['wis', 'free'],
      flaws: [],
      hp: 6,
      detail: 'Animals gifted with speech and intelligence through magical means.',
      heritages: [],
      traits: []
    }
  };

  // Class data
  const classData = {
    'alchemist': { 
      name: 'Alchemist', 
      keyAbility: 'int', 
      hp: 8,
      role: 'Support/Striker',
      complexity: 'Moderate',
      detail: 'Alchemists are masters of alchemy, using their knowledge to brew powerful concoctions. They can create elixirs to heal allies, mutagens to enhance physical abilities, and bombs to devastate foes. Their versatility comes from daily preparations.',
      proficiencies: ['Perception: Trained', 'Fort: Expert', 'Ref: Expert', 'Will: Trained', 'Skills: 3 + Int']
    },
    'barbarian': { 
      name: 'Barbarian', 
      keyAbility: 'str', 
      hp: 12,
      role: 'Tank/Striker',
      complexity: 'Low',
      detail: 'Barbarians tap into primal fury to become unstoppable forces of destruction. With the highest Hit Points of any class, they excel at taking and dealing damage. Their rage grants incredible bonuses but limits their options.',
      proficiencies: ['Perception: Expert', 'Fort: Expert', 'Ref: Trained', 'Will: Expert', 'Skills: 3 + Int']
    },
    'bard': { 
      name: 'Bard', 
      keyAbility: 'cha', 
      hp: 8,
      role: 'Support/Caster',
      complexity: 'Moderate',
      detail: 'Bards use occult magic through performance, inspiring allies and confounding enemies. They\'re excellent support characters with versatile spellcasting and the ability to boost their party\'s capabilities in combat.',
      proficiencies: ['Perception: Expert', 'Fort: Trained', 'Ref: Trained', 'Will: Expert', 'Skills: 4 + Int']
    },
    'champion': { 
      name: 'Champion', 
      keyAbility: 'str', 
      hp: 10,
      role: 'Tank/Support',
      complexity: 'Low',
      detail: 'Champions are divine warriors sworn to a deity and code. They protect allies with defensive reactions and gain divine spells. Choose from causes like paladin (good), antipaladin (evil), or liberator (chaotic good).',
      proficiencies: ['Perception: Trained', 'Fort: Expert', 'Ref: Trained', 'Will: Expert', 'Skills: 2 + Int']
    },
    'cleric': { 
      name: 'Cleric', 
      keyAbility: 'wis', 
      hp: 8,
      role: 'Support/Caster',
      complexity: 'Low',
      detail: 'Clerics channel the power of their deity through divine spells. They\'re unmatched healers and can prepare a wide variety of spells. Their doctrine (cloistered or warpriest) determines their combat capability.',
      proficiencies: ['Perception: Trained', 'Fort: Trained', 'Ref: Trained', 'Will: Expert', 'Skills: 2 + Int']
    },
    'druid': { 
      name: 'Druid', 
      keyAbility: 'wis', 
      hp: 8,
      role: 'Caster/Support',
      complexity: 'Moderate',
      detail: 'Druids wield primal magic and can transform into animals. They can be powerful spellcasters (wild order) or savage shapeshifters (wild shape orders). Their connection to nature gives them unique abilities.',
      proficiencies: ['Perception: Trained', 'Fort: Trained', 'Ref: Trained', 'Will: Expert', 'Skills: 2 + Int']
    },
    'fighter': { 
      name: 'Fighter', 
      keyAbility: 'str', 
      hp: 10,
      role: 'Striker',
      complexity: 'Low',
      detail: 'Fighters are supreme weapon masters with the best attack accuracy. Simple but devastatingly effective, they excel with any weapon or combat style. Perfect for beginners who want straightforward power.',
      proficiencies: ['Perception: Expert', 'Fort: Expert', 'Ref: Expert', 'Will: Trained', 'Skills: 3 + Int']
    },
    'monk': { 
      name: 'Monk', 
      keyAbility: 'str', 
      hp: 10,
      role: 'Striker',
      complexity: 'Moderate',
      detail: 'Monks are martial artists with supernatural ki abilities. They fight unarmored, using unarmed strikes with impressive mobility. Ki powers grant them mystical abilities like stunning foes or running on water.',
      proficiencies: ['Perception: Trained', 'Fort: Expert', 'Ref: Expert', 'Will: Expert', 'Skills: 4 + Int']
    },
    'ranger': { 
      name: 'Ranger', 
      keyAbility: 'str', 
      hp: 10,
      role: 'Striker',
      complexity: 'Moderate',
      detail: 'Rangers are wilderness warriors who hunt their prey. They can specialize in ranged or melee combat, and often have animal companions. Their hunt prey ability grants bonuses against specific enemies.',
      proficiencies: ['Perception: Expert', 'Fort: Expert', 'Ref: Expert', 'Will: Trained', 'Skills: 4 + Int']
    },
    'rogue': { 
      name: 'Rogue', 
      keyAbility: 'dex', 
      hp: 8,
      role: 'Striker/Skill',
      complexity: 'Moderate',
      detail: 'Rogues are cunning strikers who deal devastating sneak attack damage. They\'re also the best skill users, excelling at stealth, thievery, and other intricate tasks. Highly versatile in and out of combat.',
      proficiencies: ['Perception: Expert', 'Fort: Trained', 'Ref: Expert', 'Will: Expert', 'Skills: 7 + Int']
    },
    'sorcerer': { 
      name: 'Sorcerer', 
      keyAbility: 'cha', 
      hp: 6,
      role: 'Caster',
      complexity: 'High',
      detail: 'Sorcerers have innate magic from their bloodline. Unlike wizards, they don\'t need spellbooks and gain powerful focus spells. Their bloodline determines their spell tradition and special abilities.',
      proficiencies: ['Perception: Trained', 'Fort: Trained', 'Ref: Trained', 'Will: Expert', 'Skills: 2 + Int']
    },
    'wizard': { 
      name: 'Wizard', 
      keyAbility: 'int', 
      hp: 6,
      role: 'Caster',
      complexity: 'High',
      detail: 'Wizards are scholarly arcane spellcasters who learn magic through study. They have the largest spell selection and can specialize in spell schools. Their spellbook contains immense magical knowledge.',
      proficiencies: ['Perception: Trained', 'Fort: Trained', 'Ref: Trained', 'Will: Expert', 'Skills: 2 + Int']
    },
    'inventor': { 
      name: 'Inventor', 
      keyAbility: 'int', 
      hp: 8,
      role: 'Support/Striker',
      complexity: 'High',
      detail: 'Inventors create and customize technological devices. Their innovation (armor, weapon, or construct) is central to their abilities. They blend crafting expertise with tactical combat capabilities.',
      proficiencies: ['Perception: Trained', 'Fort: Expert', 'Ref: Expert', 'Will: Trained', 'Skills: 3 + Int']
    },
    'mutant': { 
      name: 'Mutant', 
      keyAbility: 'con', 
      hp: 10,
      role: 'Striker',
      complexity: 'Moderate',
      detail: 'Mutants transform their bodies with powerful mutations, gaining incredible abilities at the cost of their humanity. Each mutation grants unique powers but may have drawbacks. They balance risk and reward in combat.',
      proficiencies: ['Perception: Trained', 'Fort: Expert', 'Ref: Trained', 'Will: Trained', 'Skills: 3 + Int']
    },
    'gunslinger': { 
      name: 'Gunslinger', 
      keyAbility: 'dex', 
      hp: 8,
      role: 'Striker',
      complexity: 'Moderate',
      detail: 'Gunslingers are firearm masters with incredible precision. Their ways grant unique combat styles and abilities. They\'re deadly strikers who can make called shots to cripple specific body parts.',
      proficiencies: ['Perception: Expert', 'Fort: Expert', 'Ref: Expert', 'Will: Trained', 'Skills: 3 + Int']
    },
    'summoner': { 
      name: 'Summoner', 
      keyAbility: 'cha', 
      hp: 10,
      role: 'Caster/Pet',
      complexity: 'High',
      detail: 'Summoners bond with powerful eidolons—manifestations of magical beings. You and your eidolon share actions and hit points, fighting as one entity. Extremely powerful but requires managing two stat blocks.',
      proficiencies: ['Perception: Trained', 'Fort: Trained', 'Ref: Trained', 'Will: Expert', 'Skills: 4 + Int']
    }
  };

  // Equipment catalog
  const equipmentCatalog = {
    weapons: [
      { id: 'longsword', name: 'Longsword', cost: 1, damage: '1d8 S', traits: ['Versatile P'] },
      { id: 'shortsword', name: 'Shortsword', cost: 0.9, damage: '1d6 P', traits: ['Agile', 'Finesse'] },
      { id: 'dagger', name: 'Dagger', cost: 0.2, damage: '1d4 P', traits: ['Agile', 'Finesse', 'Thrown'] },
      { id: 'rapier', name: 'Rapier', cost: 2, damage: '1d6 P', traits: ['Deadly d8', 'Finesse'] },
      { id: 'battleaxe', name: 'Battleaxe', cost: 1, damage: '1d8 S', traits: ['Sweep'] },
      { id: 'greataxe', name: 'Greataxe', cost: 2, damage: '1d12 S', traits: ['Sweep', 'Two-Hand'] },
      { id: 'greatsword', name: 'Greatsword', cost: 2, damage: '1d12 S', traits: ['Versatile P', 'Two-Hand'] },
      { id: 'shortbow', name: 'Shortbow', cost: 3, damage: '1d6 P', traits: ['Deadly d10', 'Range 60ft'] },
      { id: 'longbow', name: 'Longbow', cost: 6, damage: '1d8 P', traits: ['Deadly d10', 'Range 100ft'] },
      { id: 'crossbow', name: 'Crossbow', cost: 3, damage: '1d8 P', traits: ['Range 120ft', 'Reload 1'] },
      { id: 'club', name: 'Club', cost: 0, damage: '1d6 B', traits: ['Thrown 10ft'] },
      { id: 'staff', name: 'Staff', cost: 0, damage: '1d4 B', traits: ['Two-Hand d8'] }
    ],
    armor: [
      { id: 'leather', name: 'Leather Armor', cost: 2, ac: '+1', dexCap: 4, checkPenalty: '-1' },
      { id: 'studded-leather', name: 'Studded Leather', cost: 3, ac: '+2', dexCap: 3, checkPenalty: '-1' },
      { id: 'chain-shirt', name: 'Chain Shirt', cost: 5, ac: '+2', dexCap: 3, checkPenalty: '-1' },
      { id: 'chain-mail', name: 'Chain Mail', cost: 6, ac: '+4', dexCap: 1, checkPenalty: '-2' },
      { id: 'breastplate', name: 'Breastplate', cost: 8, ac: '+4', dexCap: 1, checkPenalty: '-2' },
      { id: 'half-plate', name: 'Half Plate', cost: 18, ac: '+5', dexCap: 1, checkPenalty: '-3' },
      { id: 'shield-wooden', name: 'Wooden Shield', cost: 1, ac: '+2 (Raise Shield)', hardness: 3 },
      { id: 'shield-steel', name: 'Steel Shield', cost: 2, ac: '+2 (Raise Shield)', hardness: 5 }
    ],
    gear: [
      { id: 'backpack', name: 'Backpack', cost: 0.1, bulk: 'L' },
      { id: 'bedroll', name: 'Bedroll', cost: 0.1, bulk: 'L' },
      { id: 'rope', name: 'Rope (50ft)', cost: 0.5, bulk: 'L' },
      { id: 'torch', name: 'Torch (5)', cost: 0.5, bulk: 'L' },
      { id: 'rations', name: 'Rations (1 week)', cost: 0.4, bulk: 'L' },
      { id: 'waterskin', name: 'Waterskin', cost: 0.5, bulk: 'L' },
      { id: 'grappling-hook', name: 'Grappling Hook', cost: 0.1, bulk: 'L' },
      { id: 'flint-steel', name: 'Flint and Steel', cost: 0.5, bulk: '—' },
      { id: 'healers-tools', name: "Healer's Tools", cost: 5, bulk: '1' },
      { id: 'thieves-tools', name: "Thieves' Tools", cost: 3, bulk: 'L' },
      { id: 'climbing-kit', name: 'Climbing Kit', cost: 0.5, bulk: '1' },
      { id: 'chalk', name: 'Chalk (10)', cost: 0.1, bulk: '—' },
      { id: 'crowbar', name: 'Crowbar', cost: 0.5, bulk: 'L' },
      { id: 'lantern', name: 'Lantern', cost: 0.7, bulk: 'L' },
      { id: 'oil', name: 'Oil (flask)', cost: 0.1, bulk: 'L' }
    ]
  };

  /**
   * Navigation between steps
   * @param {number} stepNumber - The step number to navigate to
   */
  window.nextStep = function(stepNumber) {
    // Validate current step before moving forward
    if (!validateStep(characterData.step)) {
      return;
    }

    // Update character data based on current step
    saveStepData(characterData.step);

    // Save progress to database
    saveCharacterProgress();

    // Hide current step
    $('.creation-step').addClass('hidden');
    
    // Show new step
    $('#step' + stepNumber).removeClass('hidden');
    
    // Update progress tracker
    $('.progress-step').removeClass('active completed');
    $('.progress-step').each(function() {
      const step = $(this).data('step');
      if (step < stepNumber) {
        $(this).addClass('completed');
      } else if (step === stepNumber) {
        $(this).addClass('active');
      }
    });
    
    characterData.step = stepNumber;
    updatePreview();
    
    // Scroll to top
    $('.creation-content').scrollTop(0);
  };

  /**
   * Navigate to previous step
   * @param {number} stepNumber - The step number to navigate to
   */
  window.prevStep = function(stepNumber) {
    nextStep(stepNumber);
  };

  /**
   * Validate current step before proceeding
   * @param {number} step - The step number to validate
   * @returns {boolean} True if validation passes, false otherwise
   */
  function validateStep(step) {
    clearStepError('step' + step);
    switch(step) {
      case 1:
        const name = $('#characterName').val().trim();
        if (!name || name.length < CONSTANTS.MIN_NAME_LENGTH) {
          showStepError('Please enter a character name (at least ' + CONSTANTS.MIN_NAME_LENGTH + ' characters).', 'step1');
          return false;
        }
        return true;
      
      case 2:
        if (!characterData.ancestry) {
          showStepError('Please select an ancestry.', 'step2');
          return false;
        }
        return true;
      
      case 3:
        if (!characterData.background) {
          showStepError('Please select a background.', 'step3');
          return false;
        }
        if ($('input[name="bgBoost"]:checked').length !== CONSTANTS.REQUIRED_BG_BOOSTS) {
          showStepError('Please select exactly ' + CONSTANTS.REQUIRED_BG_BOOSTS + ' ability boosts from your background.', 'step3');
          return false;
        }
        return true;
      
      case 4:
        if (!characterData.class) {
          showStepError('Please select a class.', 'step4');
          return false;
        }
        return true;
      
      case 5:
        if ($('input[name="freeBoost"]:checked').length !== CONSTANTS.REQUIRED_FREE_BOOSTS) {
          showStepError('Please select exactly ' + CONSTANTS.REQUIRED_FREE_BOOSTS + ' free ability boosts.', 'step5');
          return false;
        }
        return true;
      
      default:
        return true;
    }
  }

  /**
   * Save data from current step to character state
   * @param {number} step - The step number to save data from
   */
  function saveStepData(step) {
    switch(step) {
      case 1:
        characterData.name = $('#characterName').val().trim();
        characterData.concept = $('#characterConcept').val().trim();
        break;
      
      case 3:
        // Save background ability boosts
        $('input[name="bgBoost"]:checked').each(function() {
          applyAbilityBoost($(this).val());
        });
        break;
      
      case 5:
        // Apply free ability boosts
        $('input[name="freeBoost"]:checked').each(function() {
          applyAbilityBoost($(this).val());
        });
        break;
      
      case 6:
        characterData.alignment = $('#alignment').val();
        characterData.deity = $('#deity').val();
        characterData.age = $('#age').val();
        characterData.gender = $('#gender').val();
        characterData.appearance = $('#appearance').val();
        characterData.personality = $('#personality').val();
        characterData.backstory = $('#backstory').val();
        break;
    }
  }

  /**
   * Save character progress to database via API
   */
  function saveCharacterProgress() {
    // Prepare data for API
    const saveData = {
      character_id: characterData.character_id,
      step: characterData.step,
      name: characterData.name,
      concept: characterData.concept,
      ancestry: characterData.ancestry,
      heritage: characterData.heritage,
      background: characterData.background,
      class: characterData.class,
      abilities: characterData.abilities,
      alignment: characterData.alignment,
      deity: characterData.deity,
      age: characterData.age,
      gender: characterData.gender,
      appearance: characterData.appearance,
      personality: characterData.personality,
      backstory: characterData.backstory,
      equipment: characterData.equipment,
      gold: characterData.gold,
      wizard_complete: false
    };

    // Send to API with CSRF token. On 403, clear token cache and retry once.
    function doDraftSave(token, isRetry) {
      $.ajax({
        url: CONSTANTS.API_SAVE_CHARACTER,
        method: 'POST',
        contentType: 'application/json',
        headers: {
          'X-CSRF-Token': token
        },
        data: JSON.stringify(saveData),
        success: function(response) {
          if (response.success) {
            if (!characterData.character_id && response.character_id) {
              characterData.character_id = response.character_id;
            }
            console.log('Character progress saved:', response);
          } else {
            console.error('Failed to save character:', response.error);
          }
        },
        error: function(xhr, status, error) {
          console.error('AJAX error saving character:', error);
          if (xhr.status === 403 && !isRetry) {
            csrfToken = null;
            getCsrfToken(true).then(freshToken => doDraftSave(freshToken, true)).catch(() => {
              console.error('Session expired — could not refresh CSRF token for draft save.');
            });
          }
        }
      });
    }

    getCsrfToken().then(token => doDraftSave(token, false)).catch(error => {
      console.error('Failed to save character:', error);
    });
  }

  /**
   * Show detailed ancestry information in the UI
   * @param {string} ancestryId - The ancestry identifier
   * @param {Event} event - Optional click event to stop propagation
   */
  window.showAncestryDetails = function(ancestryId, event) {
    if(event) {
      event.stopPropagation();
    }
    
    const ancestry = ancestryData[ancestryId];
    if (!ancestry) return;

    $('#ancestryDetailName').text(ancestry.name);
    $('#ancestryDetailDescription').text(ancestry.detail);
    updateAncestryDetails(ancestry);

    // Show heritages
    let heritageHtml = '';
    ancestry.heritages.forEach(heritage => {
      heritageHtml += '<div class="heritage-option" data-heritage="' + heritage.id + '">';
      heritageHtml += '<strong>' + heritage.name + '</strong>';
      heritageHtml += '<p>' + heritage.benefit + '</p>';
      heritageHtml += '</div>';
    });
    $('#heritageOptions').html(heritageHtml);

    // Show traits
    if (ancestry.traits && ancestry.traits.length > 0) {
      let traitsHtml = '<ul class="trait-list">';
      ancestry.traits.forEach(trait => {
        traitsHtml += '<li>' + trait + '</li>';
      });
      traitsHtml += '</ul>';
      $('#ancestryTraits').html(traitsHtml);
    }

    // Scroll to details
    $('#ancestryDetails').removeClass('hidden');
    $('#ancestryDetails')[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  };

  /**
   * Show detailed class information in the UI
   * @param {string} classId - The class identifier
   * @param {Event} event - Optional click event to stop propagation
   */
  window.showClassDetails = function(classId,event) {
    if (event) {
      event.stopPropagation();
    }

    const classInfo = classData[classId];
    if (!classInfo) return;

    $('#classDetailName').text(classInfo.name);
    $('#classDetailDescription').text(classInfo.detail || '');
    $('#classRole').text(classInfo.role);
    $('#classComplexity').text(classInfo.complexity);

    // Show proficiencies
    let profHtml = '<ul class="proficiency-list">';
    if (classInfo.proficiencies) {
      classInfo.proficiencies.forEach(prof => {
        profHtml += '<li>' + prof + '</li>';
      });
    }
    profHtml += '</ul>';
    $('#classProficiencies').html(profHtml);

    // Show details panel
    $('#classDetails').removeClass('hidden');
    $('#classDetails')[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  };

  /**
   * Select an ancestry for the character
   * @param {string} ancestryId - The ancestry identifier to select
   */
  window.selectAncestry = function(ancestryId) {
    // Validate ancestry exists
    const ancestry = ancestryData[ancestryId];
    if (!ancestry) {
      console.error('Invalid ancestry ID:', ancestryId);
      return;
    }

    // Reset ability scores
    characterData.abilities = {
      str: CONSTANTS.DEFAULT_ABILITY_SCORE,
      dex: CONSTANTS.DEFAULT_ABILITY_SCORE,
      con: CONSTANTS.DEFAULT_ABILITY_SCORE,
      int: CONSTANTS.DEFAULT_ABILITY_SCORE,
      wis: CONSTANTS.DEFAULT_ABILITY_SCORE,
      cha: CONSTANTS.DEFAULT_ABILITY_SCORE
    };

    characterData.ancestry = ancestryId;

    // Highlight selected card
    $('.ancestry-card').removeClass('selected');
    $('.ancestry-card[data-ancestry="' + ancestryId + '"]').addClass('selected');

    // Apply ancestry boosts and flaws
    ancestry.boosts.forEach(boost => {
      if (boost !== 'free') {
        applyAbilityBoost(boost);
      }
    });
    ancestry.flaws.forEach(flaw => {
      applyAbilityFlaw(flaw);
    });

    // Show details panel
    $('#ancestryDetails').removeClass('hidden');
    updateAncestryDetails(ancestry);

    // Enable next button
    $('#ancestryNextBtn').prop('disabled', false);

    updatePreview();
  };

  /**
   * Select a background for the character
   * @param {string} backgroundId - The background identifier to select
   */
  window.selectBackground = function(backgroundId) {
    if (!backgroundId) {
      console.error('Invalid background ID');
      return;
    }

    characterData.background = backgroundId;

    $('.background-card').removeClass('selected');
    $('.background-card[data-background="' + backgroundId + '"]').addClass('selected');

    $('#backgroundBoosts').removeClass('hidden');

    // Enable next when 2 boosts are selected
    $('input[name="bgBoost"]').on('change', function() {
      const checked = $('input[name="bgBoost"]:checked').length;
      // Limit to 2 selections
      if (checked > CONSTANTS.REQUIRED_BG_BOOSTS) {
        $(this).prop('checked', false);
      }
      $('#backgroundNextBtn').prop('disabled', checked !== CONSTANTS.REQUIRED_BG_BOOSTS);
    });

    updatePreview();
  };

  /**
   * Select a class for the character
   * @param {string} classId - The class identifier to select
   */
  window.selectClass = function(classId) {
    // Validate class exists
    const classInfo = classData[classId];
    if (!classInfo) {
      console.error('Invalid class ID:', classId);
      return;
    }

    characterData.class = classId;

    $('.class-card').removeClass('selected');
    $('.class-card[data-class="' + classId + '"]').addClass('selected');

    // Apply class key ability boost
    applyAbilityBoost(classInfo.keyAbility);

    $('#classNextBtn').prop('disabled', false);

    updatePreview();
  };

  /**
   * Get ability modifier from ability score.
   * @param {number} score - The ability score
   * @return {number} The ability modifier
   */
  function getAbilityModifier(score) {
    return Math.floor((score - 10) / 2);
  }

  /**
   * Apply ability boost to character.
   * @param {string} ability - The ability to boost (str, dex, con, int, wis, cha)
   */
  function applyAbilityBoost(ability) {
    if (!ability || !characterData.abilities[ability]) {
      console.error('Invalid ability:', ability);
      return;
    }
    if (characterData.abilities[ability] < CONSTANTS.MAX_ABILITY_SCORE) {
      characterData.abilities[ability] += CONSTANTS.ABILITY_BOOST_AMOUNT;
    } else {
      characterData.abilities[ability] += CONSTANTS.ABILITY_BOOST_AT_18;
    }
  }

  /**
   * Apply ability flaw to character.
   * @param {string} ability - The ability to reduce (str, dex, con, int, wis, cha)
   */
  function applyAbilityFlaw(ability) {
    if (!ability || !characterData.abilities[ability]) {
      console.error('Invalid ability:', ability);
      return;
    }
    characterData.abilities[ability] -= CONSTANTS.ABILITY_FLAW_AMOUNT;
  }

  /**
   * Calculate Armor Class (AC).
   * @returns {number} The calculated AC.
   */
  function calculateAC() {
    return CONSTANTS.BASE_AC + getAbilityModifier(characterData.abilities.dex);
  }

  /**
   * Update character preview
   */
  function updatePreview() {
    $('#previewName').text(characterData.name || 'Unnamed Character');
    
    // Show ancestry with heritage if selected
    let ancestryText = characterData.ancestry ? ancestryData[characterData.ancestry].name : '—';
    if (characterData.heritage && characterData.ancestry) {
      const ancestry = ancestryData[characterData.ancestry];
      const heritage = ancestry.heritages.find(h => h.id === characterData.heritage);
      if (heritage) {
        ancestryText += ' (' + heritage.name + ')';
      }
    }
    $('#previewAncestry').text(ancestryText);
    
    $('#previewClass').text(characterData.class ? classData[characterData.class].name : '—');
    $('#previewBackground').text(characterData.background || '—');

    // Update ability scores
    ['str', 'dex', 'con', 'int', 'wis', 'cha'].forEach(ability => {
      const score = characterData.abilities[ability];
      const mod = getAbilityModifier(score);
      $('#preview' + ability.charAt(0).toUpperCase() + ability.slice(1)).text(score);
      $('#preview' + ability.charAt(0).toUpperCase() + ability.slice(1) + 'Mod').text(mod >= 0 ? '+' + mod : mod);
    });

    // Calculate HP
    let hp = 0;
    if (characterData.ancestry) {
      hp += ancestryData[characterData.ancestry].hp;
    }
    if (characterData.class) {
      hp += classData[characterData.class].hp;
    }
    hp += getAbilityModifier(characterData.abilities.con);
    $('#previewHp').text(hp);

    // Calculate AC
    const ac = calculateAC();
    $('#previewAc').text(ac);

    // Calculate Perception
    const perception = getAbilityModifier(characterData.abilities.wis);
    $('#previewPerception').text(perception >= 0 ? '+' + perception : perception);
  }

  /**
   * Update ancestry details panel
   */
  function updateAncestryDetails(ancestry) {
    let html = '<ul>';
    ancestry.boosts.forEach(boost => {
      html += '<li><strong>+2</strong> to ' + (boost === 'free' ? 'Free Choice' : boost.toUpperCase()) + '</li>';
    });
    ancestry.flaws.forEach(flaw => {
      html += '<li><strong>-2</strong> to ' + flaw.toUpperCase() + '</li>';
    });
    html += '</ul>';
    $('#ancestryAbilityChanges').html(html);
  }

  /**
   * Equipment shop - show category
   */
  window.showShopCategory = function(category) {
    // Validate category exists
    if (!equipmentCatalog[category]) {
      console.error('Invalid equipment category:', category);
      return;
    }

    $('.category-btn').removeClass('active');
    $('.category-btn:contains("' + category.charAt(0).toUpperCase() + category.slice(1) + '")').addClass('active');

    let html = '<div class="shop-items">';
    equipmentCatalog[category].forEach(item => {
      html += '<div class="shop-item">';
      html += '<h4>' + item.name + '</h4>';
      html += '<p class="item-cost">' + item.cost + ' gp</p>';
      if (item.damage) html += '<p><strong>Damage:</strong> ' + item.damage + '</p>';
      if (item.ac) html += '<p><strong>AC:</strong> ' + item.ac + '</p>';
      if (item.traits) html += '<p><small>' + item.traits.join(', ') + '</small></p>';
      html += '<button class="btn btn-sm" onclick="buyEquipment(\'' + category + '\', \'' + item.id + '\')">Buy</button>';
      html += '</div>';
    });
    html += '</div>';
    $('#shopInventory').html(html);
  };

  /**
   * Buy equipment item
   */
  window.buyEquipment = function(category, itemId) {
    // Validate inputs
    if (!equipmentCatalog[category]) {
      console.error('Invalid equipment category:', category);
      return;
    }

    const item = equipmentCatalog[category].find(i => i.id === itemId);
    if (!item) {
      console.error('Item not found:', itemId);
      return;
    }

    if (characterData.gold < item.cost) {
      showStepError('Not enough gold! You need ' + item.cost.toFixed(1) + ' gp but only have ' + characterData.gold.toFixed(1) + ' gp.', 'step7');
      return;
    }

    characterData.equipment.push(item);
    characterData.gold -= item.cost;
    $('#goldRemaining').text(characterData.gold.toFixed(1));
    updateEquipmentList();
  };

  /**
   * Update equipment list display
   */
  function updateEquipmentList() {
    if (characterData.equipment.length === 0) {
      $('#equipmentList').html('<p class="empty-message">No equipment purchased yet.</p>');
      return;
    }

    let html = '<ul>';
    characterData.equipment.forEach((item, index) => {
      html += '<li>' + item.name + ' (' + item.cost + ' gp) ';
      html += '<button class="btn-tiny" onclick="removeEquipment(' + index + ')">Remove</button></li>';
    });
    html += '</ul>';
    $('#equipmentList').html(html);
  }

  /**
   * Remove equipment item
   */
  window.removeEquipment = function(index) {
    if (index < 0 || index >= characterData.equipment.length) {
      console.error('Invalid equipment index:', index);
      return;
    }

    const item = characterData.equipment[index];
    characterData.gold += item.cost;
    characterData.equipment.splice(index, 1);
    $('#goldRemaining').text(characterData.gold.toFixed(1));
    updateEquipmentList();
  };

  /**
   * Free ability boost tracking
   */
  $(document).on('change', 'input[name="freeBoost"]', function() {
    const checked = $('input[name="freeBoost"]:checked').length;
    if (checked > CONSTANTS.REQUIRED_FREE_BOOSTS) {
      $(this).prop('checked', false);
      return;
    }
    $('#boostCount').text(checked);
    $('#abilitiesNextBtn').prop('disabled', checked !== CONSTANTS.REQUIRED_FREE_BOOSTS);

    // Update preview with temporary boosts
    const tempAbilities = {...characterData.abilities};
    $('input[name="freeBoost"]:checked').each(function() {
      const ability = $(this).val();
      if (tempAbilities[ability] < CONSTANTS.MAX_ABILITY_SCORE) {
        tempAbilities[ability] += CONSTANTS.ABILITY_BOOST_AMOUNT;
      } else {
        tempAbilities[ability] += CONSTANTS.ABILITY_BOOST_AT_18;
      }
    });
  });

  /**
   * Submit character - final save marking wizard as complete
   */
  window.submitCharacter = function() {
    // Build character summary
    generateCharacterSummary();

    // Prepare final save data
    const finalData = {
      character_id: characterData.character_id,
      step: CONSTANTS.FINAL_STEP,
      name: characterData.name,
      concept: characterData.concept,
      ancestry: characterData.ancestry,
      heritage: characterData.heritage,
      background: characterData.background,
      class: characterData.class,
      abilities: characterData.abilities,
      alignment: characterData.alignment,
      deity: characterData.deity,
      age: characterData.age,
      gender: characterData.gender,
      appearance: characterData.appearance,
      personality: characterData.personality,
      backstory: characterData.backstory,
      equipment: characterData.equipment,
      gold: characterData.gold,
      wizard_complete: true
    };

    // Send final save with CSRF token. On a 403 (stale token), clear the cache
    // and retry once with a fresh token before surfacing an error to the user.
    function doSave(token, isRetry) {
      $.ajax({
        url: CONSTANTS.API_SAVE_CHARACTER,
        method: 'POST',
        data: JSON.stringify(finalData),
        contentType: 'application/json',
        headers: {
          'X-CSRF-Token': token
        },
        success: function(response) {
          if (response.success) {
            window.location.href = '/characters/' + response.character_id;
          } else {
            showWizardBanner('Error creating character: ' + (response.error || 'Unknown error'), 'error');
          }
        },
        error: function(xhr, status, error) {
          console.error('AJAX error:', error);
          if (xhr.status === 403 && !isRetry) {
            // Stale CSRF token — clear cache and retry with a fresh one.
            csrfToken = null;
            getCsrfToken(true).then(freshToken => doSave(freshToken, true)).catch(() => {
              showWizardBanner('Your session has expired. Please <a href="/user/login">log in again</a> to save your character.', 'error');
            });
          } else {
            showWizardBanner('Error saving character. Please try again. If the problem persists, refresh the page.', 'error');
          }
        }
      });
    }

    getCsrfToken().then(token => doSave(token, false)).catch(error => {
      console.error('Failed to get CSRF token:', error);
      showWizardBanner('Error creating character. Please try again.', 'error');
    });
  };

  /**
   * Generate character summary HTML for final review
   */
  function generateCharacterSummary() {
    const ancestry = ancestryData[characterData.ancestry];
    const classInfo = classData[characterData.class];

    let html = '<div class="summary-section">';
    html += '<h3>' + characterData.name + '</h3>';
    html += '<p><strong>Level 1 ' + ancestry.name + ' ' + classInfo.name + '</strong></p>';
    html += '<p><strong>Alignment:</strong> ' + characterData.alignment + '</p>';
    html += '</div>';

    html += '<div class="summary-section">';
    html += '<h4>Ability Scores</h4>';
    html += '<div class="ability-grid">';
    ['str', 'dex', 'con', 'int', 'wis', 'cha'].forEach(ability => {
      const score = characterData.abilities[ability];
      const mod = getAbilityModifier(score);
      html += '<div><strong>' + ability.toUpperCase() + ':</strong> ' + score + ' (' + (mod >= 0 ? '+' : '') + mod + ')</div>';
    });
    html += '</div>';
    html += '</div>';

    html += '<div class="summary-section">';
    html += '<h4>Equipment</h4>';
    if (characterData.equipment.length > 0) {
      html += '<ul>';
      characterData.equipment.forEach(item => {
        html += '<li>' + item.name + '</li>';
      });
      html += '</ul>';
    } else {
      html += '<p>No equipment purchased.</p>';
    }
    html += '<p><strong>Gold Remaining:</strong> ' + characterData.gold.toFixed(1) + ' gp</p>';
    html += '</div>';

    $('#characterSummary').html(html);
  }

  /**
   * Initialize
   */
  Drupal.behaviors.characterCreationWizard = {
    attach: function (context, settings) {
      // Initialize shop with weapons
      showShopCategory('weapons');
      
      // Initialize current ability scores display
      updatePreview();
      
      // Attach heritage selection handler (delegated event)
      $(document).on('click', '.heritage-option', function() {
        $('.heritage-option').removeClass('selected');
        $(this).addClass('selected');
        const heritageId = $(this).data('heritage');
        characterData.heritage = heritageId;
        updatePreview();
      });
    }
  };

})(jQuery, Drupal);
