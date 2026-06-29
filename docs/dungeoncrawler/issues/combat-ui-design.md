# Combat UI Design

**Part of**: [Issue #4: Combat & Encounter System Design](./issue-4-combat-encounter-system-design.md)  
**Status**: Design Document  
**Last Updated**: 2026-02-12

## Overview

This document specifies the frontend user interface for the combat encounter system. The UI is designed for real-time combat management with intuitive controls, clear visual feedback, and responsive design for desktop and mobile devices.

## Design Principles

1. **Clarity**: Combat state immediately visible at a glance
2. **Efficiency**: Common actions require minimal clicks
3. **Feedback**: Clear visual/audio feedback for all actions
4. **Accessibility**: Screen reader support, keyboard navigation
5. **Responsiveness**: Works on desktop, tablet, and mobile
6. **Real-time**: Live updates for all participants

## UI Architecture

```
┌──────────────────────────────────────────────────────────┐
│                    Combat Tracker                         │
│  ┌────────────────────────────────────────────────────┐  │
│  │              Header Bar                            │  │
│  │  [Encounter Name] Round: 3  [Pause] [End]         │  │
│  └────────────────────────────────────────────────────┘  │
│                                                          │
│  ┌──────────────┐  ┌─────────────────────────────────┐  │
│  │              │  │                                 │  │
│  │  Initiative  │  │      Combat Map / Theater      │  │
│  │    Track     │  │      of the Mind Display       │  │
│  │              │  │                                 │  │
│  │  [Valeros]   │  │                                 │  │
│  │  HP: 30/45   │  │                                 │  │
│  │  Actions: 2  │  │                                 │  │
│  │              │  │                                 │  │
│  └──────────────┘  └─────────────────────────────────┘  │
│                                                          │
│  ┌────────────────────────────────────────────────────┐  │
│  │           Action Panel (Current Turn)             │  │
│  │  [Strike] [Stride] [Cast Spell] [More...]        │  │
│  └────────────────────────────────────────────────────┘  │
│                                                          │
│  ┌────────────────────────────────────────────────────┐  │
│  │               Combat Log / Chat                    │  │
│  │  Valeros strikes Goblin 1 for 12 damage           │  │
│  └────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────┘
```

---

## Main Components

### 1. Combat Header

**Purpose**: Display encounter metadata and global controls

**Layout**:
```
┌─────────────────────────────────────────────────────────┐
│ ⚔️ Goblin Ambush          Round: 3 / ~8          [⏸︎ Pause]│
│ Moderate Encounter        Duration: 8:45         [⏹ End] │
│                                                    [⚙️]   │
└─────────────────────────────────────────────────────────┘
```

**Elements**:
- **Encounter Icon**: ⚔️ (combat), 🎭 (social), 🗺️ (exploration)
- **Encounter Name**: Editable by GM
- **Round Counter**: Current round / estimated total
- **Duration**: Real-time elapsed time (excludes pauses)
- **Pause Button**: Pause/resume combat (GM only)
- **End Button**: Conclude encounter (GM only)
- **Settings Menu**: Grid settings, display options

**Color Coding**:
- Active: Green border
- Paused: Yellow border
- Concluded: Gray border

---

### 2. Initiative Tracker

**Purpose**: Display turn order and participant status

**Desktop Layout** (Vertical Sidebar):
```
┌─────────────────────────────┐
│    INITIATIVE TRACKER       │
├─────────────────────────────┤
│ ▶ Valeros (18)              │ ← Current Turn
│   ❤️ 30/45  🛡️ 20  ⚡2      │
│   😱 Frightened 1           │
├─────────────────────────────┤
│   Goblin Warrior 1 (15)     │
│   ❤️ 11/23  🛡️ 17           │
│   [Hidden from Players]     │
├─────────────────────────────┤
│   Seoni (14)                │
│   ❤️ 42/42  🛡️ 15  ⚡3      │
│                             │
├─────────────────────────────┤
│   Goblin Warrior 2 (12)     │
│   ❤️ 0/23  💀 Defeated      │
└─────────────────────────────┘
```

**Mobile Layout** (Horizontal Scrolling):
```
┌──────────────────────────────────────────────────────┐
│ ▶ Valeros (18) → Goblin 1 (15) → Seoni (14) → ...  │
│   ❤️ 30/45        ❤️ 11/23       ❤️ 42/42          │
└──────────────────────────────────────────────────────┘
```

**Participant Card Elements**:

**Header**:
- **Play Arrow** (▶): Indicates current turn
- **Name**: Participant display name
- **Initiative** (parentheses): Initiative total
- **Team Badge**: Color-coded (Blue=PC, Red=Enemy, Yellow=Neutral)

**Stats Row**:
- **HP**: ❤️ current/max (color-coded: green >50%, yellow 25-50%, red <25%)
- **AC**: 🛡️ armor class value
- **Actions**: ⚡ actions remaining (only on current turn)
- **Reaction**: 🔄 if available

**Conditions**:
- **Icons + Text**: Display active conditions
  - 😱 Frightened 2
  - 🔥 Persistent Fire 5
  - 😵 Unconscious
  - ⬇️ Prone
  - 👁️‍🗨️ Flat-footed

**Interaction**:
- **Click**: Select participant (shows details panel)
- **Right-click/Long-press**: Context menu (apply damage, add condition, etc.)
- **Drag**: Reorder initiative (GM only)

**Visual States**:
- **Current Turn**: Green highlight, play arrow
- **Delayed**: Orange tint, "Delaying" badge
- **Defeated**: Greyed out, strikethrough
- **Hidden**: Red eye icon (GM only)

---

### 3. Combat Map Display

**Purpose**: Visual representation of combat space

**Grid Mode**:
```
┌─────────────────────────────────────────────┐
│  Grid: 5ft squares                          │
│  ┌───┬───┬───┬───┬───┬───┬───┬───┐         │
│  │   │   │ 🧱│   │   │   │   │   │         │
│  ├───┼───┼───┼───┼───┼───┼───┼───┤         │
│  │ 🏃│   │ 🧱│   │   │   │   │   │         │
│  ├───┼───┼───┼───┼───┼───┼───┼───┤         │
│  │   │   │   │   │👹│   │   │   │         │
│  ├───┼───┼───┼───┼───┼───┼───┼───┤         │
│  │   │   │   │   │   │   │   │   │         │
│  └───┴───┴───┴───┴───┴───┴───┴───┘         │
│                                             │
│  🏃 Valeros (Selected)                      │
│  👹 Goblin Warrior 1                        │
└─────────────────────────────────────────────┘
```

**Features**:
- **Grid Overlay**: Toggleable 5ft/10ft squares
- **Participant Tokens**: 
  - PCs: Blue circle with portrait/initial
  - Enemies: Red circle with icon/initial
  - NPCs: Yellow circle
- **Token States**:
  - Current turn: Pulsing border
  - Selected: Thick border
  - Bloodied (<50% HP): Red tint
  - Dying: Skull overlay
- **Movement**: Click and drag tokens
- **Range Indicators**: Circles showing weapon range, spell range
- **Area Effects**: Colored overlays (cone, burst, line)
- **Terrain**: Wall icons, difficult terrain shading
- **Zoom/Pan**: Mouse wheel zoom, click-drag pan

**Theater of the Mind Mode**:
```
┌─────────────────────────────────────────────┐
│         THEATER OF THE MIND                  │
│                                             │
│  FRONT LINE:                                │
│   🏃 Valeros    👹 Goblin 1                 │
│                                             │
│  MIDDLE:                                    │
│   🧙 Seoni (30 ft back)                     │
│                                             │
│  REAR:                                      │
│   👹 Goblin 2 (on building, 15 ft up)       │
│                                             │
│  [Edit Positions]                           │
└─────────────────────────────────────────────┘
```

**Features**:
- Text-based positioning
- Relative distances
- Elevation notes
- GM can edit freely

---

### 4. Action Panel

**Purpose**: Execute combat actions for current turn

**Default View**:
```
┌───────────────────────────────────────────────────────┐
│  Valeros's Turn  •  Actions: ⚡⚡⚡  Reaction: 🔄    │
├───────────────────────────────────────────────────────┤
│  COMMON ACTIONS                                       │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │
│  │ ⚔️ Strike │ │ 👣 Stride│ │ 🪜 Step  │ │ 🛡️ Raise││
│  │          │ │          │ │          │ │  Shield ││
│  └──────────┘ └──────────┘ └──────────┘ └─────────┘ │
│                                                       │
│  CLASS ACTIONS                                        │
│  ┌──────────┐ ┌──────────┐                           │
│  │ 💥 Power │ │ ⚡ Sudden│                           │
│  │  Attack  │ │  Charge │                           │
│  └──────────┘ └──────────┘                           │
│                                                       │
│  MORE  ▼                                              │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐             │
│  │ 🎯 Ready │ │ ⏳ Delay │ │ 🤝 Aid   │             │
│  └──────────┘ └──────────┘ └──────────┘             │
│                                                       │
│  [End Turn]                                           │
└───────────────────────────────────────────────────────┘
```

**Action Button States**:
- **Available**: Full color, clickable
- **Insufficient Actions**: Grayed out, shows required count
- **Disabled**: Grayed out with condition icon (e.g., 😵 can't act)
- **Quickened**: Green highlight on allowed actions
- **Hover**: Shows tooltip with description and cost

**Strike Action Modal**:
```
┌─────────────────────────────────────────────┐
│  ⚔️ STRIKE                                   │
├─────────────────────────────────────────────┤
│  Weapon: [Longsword ▼]                      │
│                                             │
│  Target: [Goblin Warrior 1 ▼]              │
│  Distance: 5 ft (in reach)                  │
│                                             │
│  Attack Bonus: +10                          │
│    Base: +5                                 │
│    Strength: +4                             │
│    Item: +1                                 │
│    MAP: -0 (first attack)                   │
│                                             │
│  Target AC: 17                              │
│  Hit Chance: ~70%                           │
│                                             │
│  Expected Damage: 1d8+4 (avg 8.5)           │
│                                             │
│  [Roll Attack]  [Cancel]                    │
└─────────────────────────────────────────────┘
```

**Cast Spell Modal**:
```
┌─────────────────────────────────────────────┐
│  ✨ CAST SPELL                               │
├─────────────────────────────────────────────┤
│  Spell: [Magic Missile ▼]                   │
│  Level: ● 1st  ○ 2nd  ○ 3rd                 │
│  Slots Remaining: 2 of 3                    │
│                                             │
│  Targets (up to 3):                         │
│  ☑ Goblin Warrior 1                         │
│  ☑ Goblin Warrior 2                         │
│  ☐ Goblin Warrior 3                         │
│                                             │
│  Range: 120 ft                              │
│  Damage: 1d4+1 per missile                  │
│  Actions: ⚡⚡ (2 actions)                   │
│                                             │
│  [Cast Spell]  [Cancel]                     │
└─────────────────────────────────────────────┘
```

**Action Cost Display**:
- Single action: ⚡ (one bolt)
- Two actions: ⚡⚡ (two bolts)
- Three actions: ⚡⚡⚡ (three bolts)
- Reaction: 🔄 (circular arrow)
- Free action: ✋ (hand)

---

### 5. Combat Log / Chat

**Purpose**: Record of all combat events and player communication

**Layout**:
```
┌───────────────────────────────────────────────────────┐
│  COMBAT LOG                          [⚙️ Filters] [💬]│
├───────────────────────────────────────────────────────┤
│  🕐 10:45:30 - ROUND 3 BEGINS                         │
├───────────────────────────────────────────────────────┤
│  🏃 Valeros                                            │
│  ⚔️ Strikes Goblin Warrior 1                          │
│  🎲 Attack: 18+7=25 vs AC 17  ✅ HIT                  │
│  💥 Damage: 1d8+4 = 12 slashing                       │
│  💔 Goblin HP: 23 → 11                                │
├───────────────────────────────────────────────────────┤
│  🔄 Goblin Warrior 1 (Reaction)                        │
│  ⚔️ Attack of Opportunity vs Valeros                  │
│  🎲 Attack: 12+6=18 vs AC 20  ❌ MISS                 │
├───────────────────────────────────────────────────────┤
│  💬 Player: "Should I heal or attack?"                │
├───────────────────────────────────────────────────────┤
│  📢 GM: "Two more goblins appear from the trees!"     │
├───────────────────────────────────────────────────────┤
│  [Type message...]                         [Send]     │
└───────────────────────────────────────────────────────┘
```

**Message Types**:
- **System** (🕐): Round start/end, encounter events
- **Action** (⚔️/👣/✨): Combat actions
- **Dice Roll** (🎲): Attack rolls, saving throws, checks
- **Damage** (💥): Damage dealt
- **Healing** (💚): Healing received
- **Condition** (😱): Conditions applied/removed
- **HP Change** (💔/💚): HP modifications
- **Chat** (💬): Player messages
- **GM Note** (📢): GM announcements

**Features**:
- **Filters**: Show/hide message types
- **Search**: Find specific actions or text
- **Export**: Download log as text/PDF
- **Timestamps**: Real time or turn-based
- **Dice Rolls**: Click to see details
- **Auto-scroll**: Stick to bottom on new messages
- **Compact/Expanded**: Toggle detail level

---

### 6. Participant Detail Panel

**Purpose**: Show detailed stats for selected participant

**Layout**:
```
┌─────────────────────────────────────────────┐
│  VALEROS (Fighter 5)                    [X] │
├─────────────────────────────────────────────┤
│  HP: ████████░░░ 30/45 (67%)                │
│  Temp HP: 0                                 │
│                                             │
│  AC: 20  |  Fort: +9  Ref: +7  Will: +5    │
│                                             │
│  CONDITIONS:                                │
│  😱 Frightened 1 (2 rounds)                 │
│     -1 to all checks and DCs                │
│                                             │
│  [➕ Add Condition]                          │
│                                             │
│  WEAPONS:                                   │
│  ⚔️ Longsword +1                            │
│     Attack: +10  Damage: 1d8+4 slashing     │
│                                             │
│  🏹 Composite Longbow                       │
│     Attack: +8   Damage: 1d8+2 piercing     │
│     Range: 100 ft                           │
│                                             │
│  ABILITIES:                                 │
│  💥 Power Attack (1/day) - Available        │
│  ⚡ Sudden Charge - Available               │
│                                             │
│  POSITION:                                  │
│  X: 10, Y: 5  Elevation: 0 ft               │
│                                             │
│  [Apply Damage]  [Heal]  [Edit Stats]       │
└─────────────────────────────────────────────┘
```

**Features**:
- **HP Bar**: Color-coded (green/yellow/red)
- **Stat Display**: Key combat stats
- **Condition List**: Active conditions with effects
- **Quick Actions**: Apply damage, heal, add conditions
- **Weapons**: Equipped weapons with stats
- **Abilities**: Available class features and feats
- **Position**: Current map location

---

### 7. Quick Damage/Heal Modal

**Purpose**: Rapidly apply damage or healing

**Damage Modal**:
```
┌─────────────────────────────────────────────┐
│  💥 APPLY DAMAGE TO VALEROS                 │
├─────────────────────────────────────────────┤
│  Current HP: 30/45                          │
│  Temp HP: 0                                 │
│                                             │
│  Damage Amount: [___15___]                  │
│                                             │
│  Damage Type: [Slashing ▼]                  │
│                                             │
│  Source: [Goblin Warrior's shortsword]      │
│                                             │
│  Resistances:                               │
│  None                                       │
│                                             │
│  After Damage: 15/45 HP                     │
│  Status: ⚠️ Bloodied                        │
│                                             │
│  [Apply]  [Cancel]                          │
└─────────────────────────────────────────────┘
```

**Healing Modal**:
```
┌─────────────────────────────────────────────┐
│  💚 HEAL VALEROS                             │
├─────────────────────────────────────────────┤
│  Current HP: 15/45                          │
│                                             │
│  Healing Amount: [___20___]                 │
│                                             │
│  Source: [Lay on Hands]                     │
│                                             │
│  After Healing: 35/45 HP                    │
│                                             │
│  [Apply]  [Cancel]                          │
└─────────────────────────────────────────────┘
```

**Quick Presets** (Buttons):
- `5 dmg` `10 dmg` `15 dmg` `20 dmg`
- `Half HP` `Full HP`

---

### 8. Condition Management Interface

**Apply Condition Modal**:
```
┌─────────────────────────────────────────────┐
│  ➕ APPLY CONDITION TO VALEROS              │
├─────────────────────────────────────────────┤
│  Condition: [Frightened ▼]                  │
│                                             │
│  Value: [__2__] (for valued conditions)     │
│                                             │
│  Duration:                                  │
│  ● Rounds: [__3__]                          │
│  ○ Turns                                    │
│  ○ Unlimited                                │
│  ○ Sustained                                │
│                                             │
│  Source: [Demoralize action]                │
│                                             │
│  EFFECTS:                                   │
│  -2 to all checks and DCs                   │
│  Decreases by 1 each turn                   │
│                                             │
│  [Apply]  [Cancel]                          │
└─────────────────────────────────────────────┘
```

**Common Conditions Quick-Add**:
- Buttons for frequent conditions: `Prone` `Flat-footed` `Grabbed` `Stunned`

---

### 9. Dice Roller

**Purpose**: Roll dice for various checks

**Inline Roller**:
```
┌─────────────────────────────────────────────┐
│  🎲 ROLL DICE                                │
├─────────────────────────────────────────────┤
│  Dice: [1d20 + 5] [🎲 Roll]                 │
│                                             │
│  Quick Rolls:                               │
│  [d4] [d6] [d8] [d10] [d12] [d20] [d100]    │
│                                             │
│  LAST ROLL:                                 │
│  1d20+5 = [14] + 5 = 19                     │
│                                             │
│  ROLL HISTORY:                              │
│  • 1d20+5 = 19 (Attack roll)                │
│  • 1d8+4 = 12 (Damage)                      │
│  • 1d20+7 = 23 (Reflex save)                │
└─────────────────────────────────────────────┘
```

**Features**:
- **Dice Notation**: Standard notation (1d20+5)
- **Quick Buttons**: Common dice
- **History**: Recent rolls
- **Labels**: Name your rolls
- **Public/Private**: Toggle visibility

---

### 10. Mobile View Adaptations

**Collapsed Initiative**:
```
┌──────────────────────────────────┐
│ ▶ Valeros (18) - HP: 30/45 ⚡2   │
│ ▼ Show All                       │
└──────────────────────────────────┘
```

**Tabbed Interface**:
```
┌──────────────────────────────────┐
│ [Map] [Actions] [Log] [Init]     │
├──────────────────────────────────┤
│                                  │
│    (Current Tab Content)         │
│                                  │
└──────────────────────────────────┘
```

**Gesture Controls**:
- **Swipe Left/Right**: Switch tabs
- **Long Press**: Context menu
- **Pinch**: Zoom map
- **Double Tap**: Quick select

---

## Interaction Flows

### Starting Combat

```
1. GM clicks "Start Combat" button
   ↓
2. Initiative roll modal appears
   - Auto-rolls for all participants
   - Shows results
   - Allows manual adjustment
   ↓
3. Initiative order displayed
   ↓
4. First participant's turn starts
   ↓
5. Action panel activates for current player
```

### Taking an Action

```
1. Player clicks action button (e.g., "Strike")
   ↓
2. Action modal opens with options
   - Select weapon
   - Select target
   - See modifiers
   ↓
3. Player clicks "Roll Attack"
   ↓
4. Dice animation plays
   ↓
5. Result displayed in modal and log
   ↓
6. If hit, "Roll Damage" button appears
   ↓
7. Damage rolled and applied
   ↓
8. Action economy updated (actions remaining)
   ↓
9. Map/initiative tracker updates
```

### Using a Reaction

```
1. Trigger action occurs (e.g., enemy moves)
   ↓
2. Notification appears for eligible reactors
   "Goblin moves. Attack of Opportunity?"
   [Yes] [No]
   ↓
3. If yes, reaction modal opens
   ↓
4. Resolve reaction
   ↓
5. Mark reaction as used
   ↓
6. Resume triggering action
```

---

## Visual Design Specifications

### Color Palette

**Primary Colors**:
- **PC Team**: `#4A90E2` (Blue)
- **Enemy Team**: `#E24A4A` (Red)
- **Neutral Team**: `#F5A623` (Orange)
- **Ally Team**: `#7ED321` (Green)

**Status Colors**:
- **Healthy** (>75% HP): `#7ED321` (Green)
- **Bloodied** (25-75% HP): `#F5A623` (Orange)
- **Critical** (<25% HP): `#E24A4A` (Red)
- **Dying**: `#8B0000` (Dark Red)
- **Dead**: `#696969` (Gray)

**Action States**:
- **Available**: Full saturation
- **Disabled**: 30% opacity, grayscale
- **Hover**: +20% brightness
- **Active**: Border glow

**Conditions**:
- **Buff**: `#7ED321` (Green)
- **Debuff**: `#E24A4A` (Red)
- **Neutral**: `#F5A623` (Orange)

### Typography

- **Headings**: Roboto Bold, 18-24px
- **Body**: Roboto Regular, 14-16px
- **Stats**: Roboto Mono, 12-14px (monospace for alignment)
- **Log**: Roboto Regular, 13px

### Icons

- **Actions**: Material Icons or custom SVG
- **Conditions**: Emoji or custom icons
- **Status**: Colored dots, bars, badges

### Spacing

- **Padding**: 8px (small), 16px (medium), 24px (large)
- **Margins**: 8px between elements, 16px between sections
- **Border Radius**: 4px (buttons), 8px (cards)

### Animations

- **Turn Change**: 300ms fade transition
- **HP Change**: 500ms smooth bar animation
- **Dice Roll**: 800ms rotation animation
- **Action Feedback**: 200ms button press
- **Notification**: 300ms slide-in from top

---

## Accessibility Features

### Keyboard Navigation

- **Tab**: Navigate between controls
- **Enter/Space**: Activate buttons
- **Arrow Keys**: Navigate initiative tracker
- **Esc**: Close modals
- **?**: Show keyboard shortcuts

### Screen Reader Support

- **ARIA Labels**: All interactive elements
- **Live Regions**: Combat log updates
- **Role Attributes**: Proper semantic HTML
- **Alt Text**: All icons and images

### Visual Accessibility

- **High Contrast Mode**: Toggle for better visibility
- **Font Scaling**: Support browser zoom up to 200%
- **Color Blindness**: Patterns in addition to colors
- **Focus Indicators**: Clear focus outlines

### Audio Feedback

- **Turn Start**: Chime sound
- **Action Taken**: Click sound
- **Damage Dealt**: Hit sound
- **Critical Hit**: Special sound
- **Healing**: Positive chime
- **Defeat**: Defeat sound

---

## Responsive Breakpoints

### Desktop (>1200px)
- Full layout with sidebars
- Large map area
- Expanded initiative tracker

### Tablet (768px - 1200px)
- Collapsible sidebars
- Medium map area
- Compact initiative tracker

### Mobile (<768px)
- Tabbed interface
- Full-width components
- Horizontal initiative scroll
- Bottom action sheet

---

## Performance Considerations

### Optimization Techniques

1. **Virtual Scrolling**: For long combat logs
2. **Lazy Loading**: Load participant details on demand
3. **Debounced Updates**: Group rapid state changes
4. **Canvas Rendering**: For complex maps
5. **Memoization**: Cache calculated values
6. **Web Workers**: Offload calculations

### Loading States

- **Skeleton Screens**: During initial load
- **Spinners**: For action processing
- **Progress Bars**: For long operations

---

## Error Handling

### User-Facing Errors

**Toast Notifications**:
```
┌─────────────────────────────────────┐
│ ⚠️ Not enough actions remaining     │
│    Need 2 actions, have 1           │
│                           [Dismiss] │
└─────────────────────────────────────┘
```

**Inline Errors**:
```
┌─────────────────────────────────────┐
│  Target: [Select target ▼]          │
│  ⚠️ Target out of range (120 ft)    │
└─────────────────────────────────────┘
```

**Error Types**:
- **Warning**: Yellow, dismissible
- **Error**: Red, requires action
- **Info**: Blue, informational
- **Success**: Green, confirmation

---

## Summary

The Combat UI provides:

- ✅ **Clear Visual Hierarchy**: Important info prominent
- ✅ **Efficient Workflows**: Minimal clicks for common actions
- ✅ **Real-time Updates**: Live synchronization
- ✅ **Responsive Design**: Works on all devices
- ✅ **Accessible**: WCAG 2.1 AA compliant
- ✅ **Intuitive**: Easy to learn, efficient to use
- ✅ **Feedback-Rich**: Clear indication of all states
- ✅ **Professional**: Clean, modern design

This UI design creates an engaging, efficient, and accessible combat experience for both players and GMs.

**Related Documents**:
- [Combat API Endpoints](./combat-api-endpoints.md)
- [Combat State Machine](./combat-state-machine.md)
- [Combat Engine Service](./combat-engine-service.md)
- [Combat Database Schema](./combat-database-schema.md)
