// Shared types for the RPG game state returned by the Laravel backend.

export interface ItemState {
    id: number;
    slug: string;
    name: string;
    type: string;
    image: string | null;
    value?: number;
    quantity: number;
    equipped: boolean;
    heal?: number; // > 0 → potion HP
    restore_sp?: number; // > 0 → potion SP
    restore_mp?: number; // > 0 → potion MP
    slot?: string | null; // weapon/armor/accessory bila bisa dipakai
    equip_bonuses?: Record<string, number>; // bonus stat saat dipakai
    req_level?: number; // level minimum untuk memakai
    book?: { kind: string; teaches: string; req_level: number; known: boolean } | null; // buku pengajar skill/sihir
}

export interface EffectiveStats {
    attack: number;
    defense: number;
    magic_attack: number;
    magic_defense: number;
    strength: number;
    agility: number;
    dexterity: number;
    intelligence: number;
    vitality: number;
    luck: number;
}

export interface EquippedItem {
    id: number;
    name: string;
    slug: string;
}

export interface PotionState {
    id: number;
    name: string;
    heal: number;
    restore_sp?: number;
    restore_mp?: number;
    quantity: number;
}

export interface CharacterState {
    id: number;
    name: string;
    class: string | null;
    gender: string | null;
    birth_date: string | null;
    age: number | null;
    affiliation: string | null;
    rank: string | null;
    rank_progress: number;
    job: string | null;
    level: number;
    xp: number;
    xp_to_next: number;
    hp: number;
    max_hp: number;
    sp: number;
    max_sp: number;
    mp: number;
    max_mp: number;
    strength: number;
    agility: number;
    dexterity: number;
    intelligence: number;
    vitality: number;
    luck: number;
    attack: number;
    defense: number;
    magic_attack: number;
    magic_defense: number;
    gold: number;
    is_alive: boolean;
    avatar: string | null;
    effective: EffectiveStats;
    equip_bonuses: EffectiveStats;
    equipment: Record<string, EquippedItem | null>;
    skills: { id: number; name: string; power: number; type: string }[];
    spells: { id: number; name: string; power: number; element: string }[];
    items: ItemState[];
}

export type NodeType = 'narrative' | 'choice' | 'combat' | 'reward' | 'ending';

export interface ChoiceState {
    id: number;
    label: string;
    is_auto: boolean;
    locked: boolean;
    hint: string | null;
}

export interface NodeState {
    id: number;
    key: string;
    type: NodeType;
    title: string | null;
    body: string | null;
    image: string | null;
    monster_id: number | null;
    payload: Record<string, unknown> | null;
    choices: ChoiceState[];
}

export interface GameState {
    character: CharacterState;
    quest: { id: number; slug: string; title: string } | null;
    node: NodeState | null;
    save: { slot: number; current_node_key: string | null; flags: Record<string, unknown> };
}

export interface CombatQuestion {
    id: number;
    kind: string;
    prompt: string;
    options: string[];
}

export interface CombatView {
    session_id: number;
    status: 'active' | 'won' | 'lost';
    turn: number;
    monster: { name: string; image: string | null };
    monster_hp: number;
    max_monster_hp: number;
    player_hp: number;
    max_player_hp: number;
    question: CombatQuestion | null;
}

export interface CombatRewards {
    xp: number;
    gold: number;
    items: { slug: string; qty: number }[];
    leveled_up: boolean;
    new_level: number;
}

export interface CombatTurn {
    session_id: number;
    correct: boolean;
    damage: number;
    target: 'monster' | 'player';
    monster_hp: number;
    max_monster_hp: number;
    player_hp: number;
    max_player_hp: number;
    status: 'active' | 'won' | 'lost';
    turn: number;
    next_question?: CombatQuestion | null;
    rewards?: CombatRewards;
    next_node?: { quest_id: number; node_key: string } | null;
}
