<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { CharacterState, ItemState } from '@/types/game';
import { BookOpen, FlaskConical, Package, Shield, Sword, Sparkles } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        items: ItemState[];
        /** Pemilik item — dipakai menilai potion berguna & syarat level equip. */
        character?: CharacterState | null;
        /** Nonaktifkan semua tombol aksi (mis. sedang memproses). */
        useDisabled?: boolean;
    }>(),
    { character: null, useDisabled: false },
);

const emit = defineEmits<{
    (e: 'use', item: ItemState): void;
    (e: 'equip', item: ItemState): void;
    (e: 'unequip', item: ItemState): void;
    (e: 'learn', item: ItemState): void;
}>();

const STAT_ABBR: Record<string, string> = {
    attack: 'ATK', defense: 'DEF', strength: 'STR', agility: 'AGI',
    dexterity: 'DEX', intelligence: 'INT', vitality: 'VIT', luck: 'LUK',
};

/** Daftar efek pemulihan sebuah item (mis. ["+30 HP", "+30 SP"]). */
function effects(item: ItemState): string[] {
    const out: string[] = [];
    if ((item.heal ?? 0) > 0) out.push(`+${item.heal} HP`);
    if ((item.restore_sp ?? 0) > 0) out.push(`+${item.restore_sp} SP`);
    if ((item.restore_mp ?? 0) > 0) out.push(`+${item.restore_mp} MP`);
    return out;
}

/** Bonus stat equipment sebagai label (mis. ["+2 ATK", "+1 STR"]). */
function gearBonuses(item: ItemState): string[] {
    const b = item.equip_bonuses ?? {};
    return Object.entries(b)
        .filter(([, v]) => v)
        .map(([k, v]) => `${v > 0 ? '+' : ''}${v} ${STAT_ABBR[k] ?? k.toUpperCase()}`);
}

function isConsumable(item: ItemState): boolean {
    return effects(item).length > 0;
}
function isEquippable(item: ItemState): boolean {
    return !!item.slot;
}
function isBook(item: ItemState): boolean {
    return !!item.book;
}
/** Buku tak bisa dipelajari: sudah dikuasai atau level kurang. */
function bookLocked(item: ItemState): boolean {
    if (!item.book) return true;
    if (item.book.known) return true;
    return !!props.character && item.book.req_level > props.character.level;
}

/** Apakah potion ini masih bisa memberi efek (ada pool relevan yang belum penuh). */
function canHelp(item: ItemState): boolean {
    const c = props.character;
    if (!c) return true;
    return (
        ((item.heal ?? 0) > 0 && c.hp < c.max_hp) ||
        ((item.restore_sp ?? 0) > 0 && c.sp < c.max_sp) ||
        ((item.restore_mp ?? 0) > 0 && c.mp < c.max_mp)
    );
}

/** Level kurang untuk memakai item. */
function levelLocked(item: ItemState): boolean {
    const req = item.req_level ?? 0;
    return !!props.character && req > props.character.level;
}
</script>

<template>
    <div>
        <div v-if="!items.length" class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
            Tasmu kosong.
        </div>
        <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div v-for="item in items" :key="item.id" class="flex items-center gap-3 rounded-lg border bg-card p-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-muted">
                    <img v-if="item.image" :src="item.image" :alt="item.name" class="h-full w-full rounded-md object-cover" />
                    <FlaskConical v-else-if="isConsumable(item)" class="h-5 w-5 text-emerald-500" />
                    <Sword v-else-if="item.slot === 'weapon'" class="h-5 w-5 text-rose-400" />
                    <Shield v-else-if="item.slot === 'armor'" class="h-5 w-5 text-sky-400" />
                    <Sparkles v-else-if="item.slot === 'accessory'" class="h-5 w-5 text-fuchsia-400" />
                    <BookOpen v-else-if="isBook(item)" class="h-5 w-5 text-violet-500" />
                    <Package v-else class="h-5 w-5 text-muted-foreground" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-medium">{{ item.name }}</div>
                    <div class="text-xs text-muted-foreground">
                        ×{{ item.quantity }}
                        <span v-for="(e, i) in effects(item)" :key="'h' + i" class="ml-1 text-emerald-600">{{ e }}</span>
                        <span v-for="(g, i) in gearBonuses(item)" :key="'g' + i" class="ml-1 text-sky-600">{{ g }}</span>
                        <span v-if="(item.req_level ?? 0) > 0" class="ml-1 text-muted-foreground">· Lv{{ item.req_level }}</span>
                        <span v-if="item.book" class="ml-1 text-violet-600">
                            {{ item.book.kind === 'spell' ? 'Sihir' : 'Skill' }}: {{ item.book.teaches }}
                            <template v-if="item.book.req_level > 1">· Lv{{ item.book.req_level }}</template>
                        </span>
                        <span v-if="item.book?.known" class="ml-1 rounded bg-violet-500/15 px-1 text-violet-600">dikuasai</span>
                        <span v-if="item.equipped" class="ml-1 rounded bg-emerald-500/15 px-1 text-emerald-600">terpasang</span>
                    </div>
                </div>
                <Button
                    v-if="isConsumable(item)"
                    size="sm"
                    variant="outline"
                    :disabled="useDisabled || !canHelp(item)"
                    @click="emit('use', item)"
                >
                    Gunakan
                </Button>
                <Button
                    v-else-if="isEquippable(item) && item.equipped"
                    size="sm"
                    variant="outline"
                    :disabled="useDisabled"
                    @click="emit('unequip', item)"
                >
                    Lepas
                </Button>
                <Button
                    v-else-if="isEquippable(item)"
                    size="sm"
                    variant="outline"
                    :disabled="useDisabled || levelLocked(item)"
                    :title="levelLocked(item) ? `Butuh level ${item.req_level}` : ''"
                    @click="emit('equip', item)"
                >
                    Pakai
                </Button>
                <Button
                    v-else-if="isBook(item)"
                    size="sm"
                    variant="outline"
                    :disabled="useDisabled || bookLocked(item)"
                    :title="item.book?.known ? 'Sudah dikuasai' : (bookLocked(item) ? `Butuh level ${item.book?.req_level}` : '')"
                    @click="emit('learn', item)"
                >
                    Pelajari
                </Button>
            </div>
        </div>
    </div>
</template>
