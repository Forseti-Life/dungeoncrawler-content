<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the multi-step character creation wizard.
 */
class CharacterCreationController extends ControllerBase {

  protected CharacterManager $characterManager;

  public function __construct(CharacterManager $character_manager) {
    $this->characterManager = $character_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
    );
  }

  /**
   * Display the character creation wizard.
   */
  public function createWizard() {
    // Prepare data for the character creation wizard
    $ancestries = $this->prepareAncestries();
    $classes = $this->prepareClasses();
    $backgrounds = $this->prepareBackgrounds();
    $alignments = $this->getAlignments();

    $build = [
      '#theme' => 'character_creation_wizard',
      '#ancestries' => $ancestries,
      '#classes' => $classes,
      '#backgrounds' => $backgrounds,
      '#alignments' => $alignments,
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/character-creation',
        ],
      ],
    ];

    return $build;
  }

  /**
   * Prepare ancestry data for the wizard.
   */
  private function prepareAncestries() {
    $ancestries = [];
    foreach (CharacterManager::ANCESTRIES as $name => $data) {
      $ancestries[] = [
        'id' => strtolower(str_replace(' ', '-', $name)),
        'name' => $name,
        'hp' => $data['hp'],
        'size' => $data['size'],
        'speed' => $data['speed'],
        'boosts' => $data['boosts'] ?? [],
        'flaws' => $data['flaws'] ?? [],
        'description' => $this->getAncestryDescription($name),
        'detail' => $this->getAncestryDetail($name),
        'heritages' => $this->getAncestryHeritages($name),
        'traits' => $this->getAncestryTraits($name),
      ];
    }
    return $ancestries;
  }

  /**
   * Prepare class data for the wizard.
   */
  private function prepareClasses() {
    $classes = [];
    foreach (CharacterManager::CLASSES as $name => $data) {
      $classes[] = [
        'id' => strtolower(str_replace(' ', '-', $name)),
        'name' => $name,
        'hp' => $data['hp'],
        'key_ability' => $data['key_ability'],
        'description' => $this->getClassDescription($name),
        'role' => $this->getClassRole($name),
        'complexity' => $this->getClassComplexity($name),
        'detail' => $this->getClassDetail($name),
        'proficiencies' => $this->getClassProficiencies($name),
      ];
    }
    return $classes;
  }

  /**
   * Prepare background options.
   */
  private function prepareBackgrounds() {
    return [
      ['id' => 'acolyte', 'name' => 'Acolyte', 'skill' => 'Religion', 'feat' => 'Student of the Canon'],
      ['id' => 'acrobat', 'name' => 'Acrobat', 'skill' => 'Acrobatics', 'feat' => 'Steady Balance'],
      ['id' => 'animal-whisperer', 'name' => 'Animal Whisperer', 'skill' => 'Nature', 'feat' => 'Train Animal'],
      ['id' => 'artisan', 'name' => 'Artisan', 'skill' => 'Crafting', 'feat' => 'Specialty Crafting'],
      ['id' => 'artist', 'name' => 'Artist', 'skill' => 'Performance', 'feat' => 'Specialty Crafting'],
      ['id' => 'barkeep', 'name' => 'Barkeep', 'skill' => 'Diplomacy', 'feat' => 'Hobnobber'],
      ['id' => 'charlatan', 'name' => 'Charlatan', 'skill' => 'Deception', 'feat' => 'Charming Liar'],
      ['id' => 'criminal', 'name' => 'Criminal', 'skill' => 'Stealth', 'feat' => 'Experienced Smuggler'],
      ['id' => 'entertainer', 'name' => 'Entertainer', 'skill' => 'Performance', 'feat' => 'Fascinating Performance'],
      ['id' => 'farmhand', 'name' => 'Farmhand', 'skill' => 'Athletics', 'feat' => 'Assurance'],
      ['id' => 'field-medic', 'name' => 'Field Medic', 'skill' => 'Medicine', 'feat' => 'Battle Medicine'],
      ['id' => 'guard', 'name' => 'Guard', 'skill' => 'Intimidation', 'feat' => 'Quick Coercion'],
      ['id' => 'herbalist', 'name' => 'Herbalist', 'skill' => 'Nature', 'feat' => 'Natural Medicine'],
      ['id' => 'hunter', 'name' => 'Hunter', 'skill' => 'Survival', 'feat' => 'Survey Wildlife'],
      ['id' => 'laborer', 'name' => 'Laborer', 'skill' => 'Athletics', 'feat' => 'Hefty Hauler'],
      ['id' => 'merchant', 'name' => 'Merchant', 'skill' => 'Diplomacy', 'feat' => 'Bargain Hunter'],
      ['id' => 'miner', 'name' => 'Miner', 'skill' => 'Survival', 'feat' => 'Terrain Expertise'],
      ['id' => 'noble', 'name' => 'Noble', 'skill' => 'Society', 'feat' => 'Courtly Graces'],
      ['id' => 'nomad', 'name' => 'Nomad', 'skill' => 'Survival', 'feat' => 'Assurance'],
      ['id' => 'scholar', 'name' => 'Scholar', 'skill' => 'Arcana', 'feat' => 'Assurance'],
      ['id' => 'scout', 'name' => 'Scout', 'skill' => 'Survival', 'feat' => 'Forager'],
      ['id' => 'sailor', 'name' => 'Sailor', 'skill' => 'Athletics', 'feat' => 'Underwater Marauder'],
      ['id' => 'street-urchin', 'name' => 'Street Urchin', 'skill' => 'Thievery', 'feat' => 'Pickpocket'],
      ['id' => 'tinker', 'name' => 'Tinker', 'skill' => 'Crafting', 'feat' => 'Specialty Crafting'],
      ['id' => 'warrior', 'name' => 'Warrior', 'skill' => 'Intimidation', 'feat' => 'Intimidating Glare'],
    ];
  }

  /**
   * Get alignment options.
   */
  private function getAlignments() {
    return [
      ['id' => 'LG', 'name' => 'Lawful Good', 'description' => 'Respectful and honorable'],
      ['id' => 'NG', 'name' => 'Neutral Good', 'description' => 'Kind without bias'],
      ['id' => 'CG', 'name' => 'Chaotic Good', 'description' => 'Benevolent rebel'],
      ['id' => 'LN', 'name' => 'Lawful Neutral', 'description' => 'Follows rules'],
      ['id' => 'N', 'name' => 'True Neutral', 'description' => 'Balanced approach'],
      ['id' => 'CN', 'name' => 'Chaotic Neutral', 'description' => 'Free spirit'],
      ['id' => 'LE', 'name' => 'Lawful Evil', 'description' => 'Tyrannical and cruel'],
      ['id' => 'NE', 'name' => 'Neutral Evil', 'description' => 'Selfish and malicious'],
      ['id' => 'CE', 'name' => 'Chaotic Evil', 'description' => 'Destructive and violent'],
    ];
  }

  /**
   * Get ancestry description.
   */
  private function getAncestryDescription($name) {
    $descriptions = [
      'Dwarf' => 'Hardy and traditional, dwarves are master craftspeople.',
      'Elf' => 'Graceful and long-lived, elves are attuned to magic.',
      'Gnome' => 'Small and curious, gnomes are driven by wanderlust.',
      'Goblin' => 'Chaotic and unpredictable, goblins are fierce survivors.',
      'Halfling' => 'Small and optimistic, halflings are lucky adventurers.',
      'Human' => 'Versatile and ambitious, humans adapt to anything.',
      'Mobians' => 'Anthropomorphic animals with unique abilities.',
      'Fungians' => 'Fungal beings with mysterious origins.',
      'Automaton' => 'Sentient constructs powered by ancient magic.',
    ];
    return $descriptions[$name] ?? 'A unique ancestry.';
  }

  /**
   * Get detailed ancestry information.
   */
  private function getAncestryDetail($name) {
    $details = [
      'Dwarf' => 'Dwarves are short and stocky, standing about a foot shorter than most humans. They have wide, compact bodies and burly frames. Dwarves of all genders pride themselves on the length of their hair, which they often braid into intricate patterns, some of which represent specific clans.',
      'Elf' => 'Elves are tall, slender humanoids with pointed ears and otherworldly grace. They have keen senses and deep appreciation for art, nature, and magic. Most elves have fair skin in shades common among humans, though occasionally an elf has skin in hues of blue or copper.',
      'Gnome' => 'Gnomes are short folk with a primal magical connection to the First World. They have an insatiable need for novelty and discovery that manifests as a physical necessity known as the Bleaching. Without new experiences, a gnome begins to lose color and enthusiasm.',
      'Goblin' => 'Goblins are short, scrappy humanoids who tend toward mayhem and mischief. They have large heads with beady eyes and wide mouths filled with sharp teeth. Their skin ranges from green to gray to blue, and they often have large, mobile ears.',
      'Halfling' => 'Halflings are short folk who seem far more at home traveling than staying put. They have a knack for getting along with others and an uncanny luck that sees them through dangerous situations. Halflings typically have brown or black hair in tight ringlets.',
      'Human' => 'Humans have incredible diversity in appearance, culture, and ambition. They have shorter lifespans than most ancestries, but their drive to achieve leaves powerful marks on the world. Humans can excel at any profession or pursuit.',
      'Mobians' => 'Anthropomorphic animals with speech and intelligence. These varied folk come from many different animal types, each with unique abilities.',
      'Fungians' => 'Strange fungal beings from deep underground. Their spore-based biology gives them mysterious powers and unique perspectives.',
      'Automaton' => 'Mechanical constructs animated by ancient magic or technology. Despite their artificial nature, they possess true consciousness and free will.',
    ];
    return $details[$name] ?? '';
  }

  /**
   * Get ancestry heritages.
   */
  private function getAncestryHeritages($name) {
    return CharacterManager::HERITAGES[$name] ?? [];
  }

  /**
   * Get ancestry special traits.
   */
  private function getAncestryTraits($name) {
    $traits = [
      'Dwarf' => ['Darkvision', 'Clan Dagger'],
      'Elf' => ['Low-Light Vision'],
      'Gnome' => ['Low-Light Vision'],
      'Goblin' => ['Darkvision'],
      'Halfling' => ['Keen Eyes'],
      'Human' => [],
      'Mobians' => [],
      'Fungians' => [],
      'Automaton' => [],
    ];
    return $traits[$name] ?? [];
  }

  /**
   * Get class description.
   */
  private function getClassDescription($name) {
    $descriptions = [
      'Alchemist' => 'Create powerful alchemical items and bombs.',
      'Barbarian' => 'Rage-fueled warrior with incredible strength.',
      'Bard' => 'Use magic and performance to inspire allies.',
      'Champion' => 'Holy warrior defending your deity\'s ideals.',
      'Cleric' => 'Divine caster channeling your deity\'s power.',
      'Druid' => 'Nature\'s guardian who can shapeshift.',
      'Fighter' => 'Master of weapons and combat techniques.',
      'Monk' => 'Martial artist with supernatural ki powers.',
      'Ranger' => 'Wilderness expert and hunter.',
      'Rogue' => 'Skilled infiltrator and precise striker.',
      'Sorcerer' => 'Innate magical power from your bloodline.',
      'Wizard' => 'Scholar who masters arcane spells.',
      'Inventor' => 'Create and modify technological gadgets.',
      'Mutant' => 'Transform your body with mutations.',
      'Gunslinger' => 'Expert marksman with firearms.',
      'Summoner' => 'Bond with a powerful eidolon companion.',
    ];
    return $descriptions[$name] ?? 'A powerful character class.';
  }

  /**
   * Get class role.
   */
  private function getClassRole($name) {
    $roles = [
      'Alchemist' => 'Support/Striker',
      'Barbarian' => 'Tank/Striker',
      'Bard' => 'Support/Caster',
      'Champion' => 'Tank/Support',
      'Cleric' => 'Support/Caster',
      'Druid' => 'Caster/Support',
      'Fighter' => 'Striker',
      'Monk' => 'Striker',
      'Ranger' => 'Striker',
      'Rogue' => 'Striker/Skill',
      'Sorcerer' => 'Caster',
      'Wizard' => 'Caster',
      'Inventor' => 'Support/Striker',
      'Mutant' => 'Striker',
      'Gunslinger' => 'Striker',
      'Summoner' => 'Caster/Pet',
    ];
    return $roles[$name] ?? 'Versatile';
  }

  /**
   * Get class complexity.
   */
  private function getClassComplexity($name) {
    $complexity = [
      'Alchemist' => 'Moderate',
      'Barbarian' => 'Low',
      'Bard' => 'Moderate',
      'Champion' => 'Low',
      'Cleric' => 'Low',
      'Druid' => 'Moderate',
      'Fighter' => 'Low',
      'Monk' => 'Moderate',
      'Ranger' => 'Moderate',
      'Rogue' => 'Moderate',
      'Sorcerer' => 'Moderate',
      'Wizard' => 'High',
      'Inventor' => 'High',
      'Mutant' => 'Moderate',
      'Gunslinger' => 'Moderate',
      'Summoner' => 'High',
    ];
    return $complexity[$name] ?? 'Moderate';
  }

  /**
   * Get detailed class information.
   */
  private function getClassDetail($name) {
    $details = [
      'Alchemist' => 'Alchemists are masters of alchemy, using their knowledge to brew powerful concoctions. They can create elixirs to heal allies, mutagens to enhance physical abilities, and bombs to devastate foes. Their versatility comes from daily preparations.',
      'Barbarian' => 'Barbarians tap into primal fury to become unstoppable forces of destruction. With the highest Hit Points of any class, they excel at taking and dealing damage. Their rage grants incredible bonuses but limits their options.',
      'Bard' => 'Bards use occult magic through performance, inspiring allies and confounding enemies. They\'re excellent support characters with versatile spellcasting and the ability to boost their party\'s capabilities in combat.',
      'Champion' => 'Champions are divine warriors sworn to a deity and code. They protect allies with defensive reactions and gain divine spells. Choose from causes like paladin (good), antipaladin (evil), or liberator (chaotic good).',
      'Cleric' => 'Clerics channel the power of their deity through divine spells. They\'re unmatched healers and can prepare a wide variety of spells. Their doctrine (cloistered or warpriest) determines their combat capability.',
      'Druid' => 'Druids wield primal magic and can transform into animals. They can be powerful spellcasters (wild order) or savage shapeshifters (wild shape orders). Their connection to nature gives them unique abilities.',
      'Fighter' => 'Fighters are supreme weapon masters with the best attack accuracy. Simple but devastatingly effective, they excel with any weapon or combat style. Perfect for beginners who want straightforward power.',
      'Monk' => 'Monks are martial artists with supernatural ki abilities. They fight unarmored, using unarmed strikes with impressive mobility. Ki powers grant them mystical abilities like stunning foes or running on water.',
      'Ranger' => 'Rangers are wilderness warriors who hunt their prey. They can specialize in ranged or melee combat, and often have animal companions. Their hunt prey ability grants bonuses against specific enemies.',
      'Rogue' => 'Rogues are cunning strikers who deal devastating sneak attack damage. They\'re also the best skill users, excelling at stealth, thievery, and other intricate tasks. Highly versatile in and out of combat.',
      'Sorcerer' => 'Sorcerers have innate magic from their bloodline. Unlike wizards, they don\'t need spellbooks and gain powerful focus spells. Their bloodline determines their spell tradition and special abilities.',
      'Wizard' => 'Wizards are scholarly arcane spellcasters who learn magic through study. They have the largest spell selection and can specialize in spell schools. Their spellbook contains immense magical knowledge.',
      'Gunslinger' => 'Gunslingers are firearm masters with incredible precision. Their ways grant unique combat styles and abilities. They\'re deadly strikers who can make called shots to cripple specific body parts.',
      'Inventor' => 'Inventors create and customize technological devices. Their innovation (armor, weapon, or construct) is central to their abilities. They blend crafting expertise with tactical combat capabilities.',
      'Summoner' => 'Summoners bond with powerful eidolons—manifestations of magical beings. You and your eidolon share actions and hit points, fighting as one entity. Extremely powerful but requires managing two stat blocks.',
      'Mutant' => 'Mutants transform their bodies with powerful mutations, gaining incredible abilities at the cost of their humanity. Each mutation grants unique powers but may have drawbacks. They balance risk and reward in combat.',
    ];
    return $details[$name] ?? '';
  }

  /**
   * Get class proficiencies summary.
   */
  private function getClassProficiencies($name) {
    $prof = [
      'Alchemist' => ['Perception: Trained', 'Fort: Expert', 'Ref: Expert', 'Will: Trained', 'Skills: 3 + Int'],
      'Barbarian' => ['Perception: Expert', 'Fort: Expert', 'Ref: Trained', 'Will: Expert', 'Skills: 3 + Int'],
      'Bard' => ['Perception: Expert', 'Fort: Trained', 'Ref: Trained', 'Will: Expert', 'Skills: 4 + Int'],
      'Champion' => ['Perception: Trained', 'Fort: Expert', 'Ref: Trained', 'Will: Expert', 'Skills: 2 + Int'],
      'Cleric' => ['Perception: Trained', 'Fort: Trained', 'Ref: Trained', 'Will: Expert', 'Skills: 2 + Int'],
      'Druid' => ['Perception: Trained', 'Fort: Trained', 'Ref: Trained', 'Will: Expert', 'Skills: 2 + Int'],
      'Fighter' => ['Perception: Expert', 'Fort: Expert', 'Ref: Expert', 'Will: Trained', 'Skills: 3 + Int'],
      'Monk' => ['Perception: Trained', 'Fort: Expert', 'Ref: Expert', 'Will: Expert', 'Skills: 4 + Int'],
      'Ranger' => ['Perception: Expert', 'Fort: Expert', 'Ref: Expert', 'Will: Trained', 'Skills: 4 + Int'],
      'Rogue' => ['Perception: Expert', 'Fort: Trained', 'Ref: Expert', 'Will: Expert', 'Skills: 7 + Int'],
      'Sorcerer' => ['Perception: Trained', 'Fort: Trained', 'Ref: Trained', 'Will: Expert', 'Skills: 2 + Int'],
      'Wizard' => ['Perception: Trained', 'Fort: Trained', 'Ref: Trained', 'Will: Expert', 'Skills: 2 + Int'],
      'Gunslinger' => ['Perception: Expert', 'Fort: Expert', 'Ref: Expert', 'Will: Trained', 'Skills: 3 + Int'],
      'Inventor' => ['Perception: Trained', 'Fort: Expert', 'Ref: Expert', 'Will: Trained', 'Skills: 3 + Int'],
      'Summoner' => ['Perception: Trained', 'Fort: Trained', 'Ref: Trained', 'Will: Expert', 'Skills: 4 + Int'],
      'Mutant' => ['Perception: Trained', 'Fort: Expert', 'Ref: Trained', 'Will: Trained', 'Skills: 3 + Int'],
    ];
    return $prof[$name] ?? [];
  }

}
