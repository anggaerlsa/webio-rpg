<script setup lang="ts">
import type { CharacterState } from '@/types/game';
import { Brain, Clover, Shield, Sword, Target, Wind } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ character: CharacterState }>();

// Konstanta efek HARUS sinkron dengan App\Services\CombatService (PHP).
const PHYS_PER_STR = 2; // % dmg fisik per STR
const MAGIC_PER_INT = 2; // % dmg sihir per INT
const DODGE_PER_AGI = 1; // % hindar per AGI
const CRIT_PER_DEX = 1; // % kritikal per DEX
const CRIT_PER_LUK = 0.5; // % kritikal per LUK
const GOLD_PER_LUK = 1; // % emas per LUK

const c = computed(() => props.character);
// Pakai stat efektif (dasar + perlengkapan) bila tersedia.
const eff = computed(() => c.value.effective);
const gear = computed(() => c.value.equip_bonuses);

// Stat 1 = baseline → efek 0; hanya poin DI ATAS 1 yang berpengaruh.
const bonus = (v: number) => Math.max(0, v - 1);

const stats = computed(() => [
    { key: 'str', abbr: 'STR', name: 'Kekuatan', icon: Sword, value: eff.value.strength, gear: gear.value.strength, effect: `+${bonus(eff.value.strength) * PHYS_PER_STR}% dmg fisik` },
    { key: 'agi', abbr: 'AGI', name: 'Kelincahan', icon: Wind, value: eff.value.agility, gear: gear.value.agility, effect: `${Math.min(100, bonus(eff.value.agility) * DODGE_PER_AGI)}% hindar` },
    { key: 'dex', abbr: 'DEX', name: 'Ketangkasan', icon: Target, value: eff.value.dexterity, gear: gear.value.dexterity, effect: `+${bonus(eff.value.dexterity) * CRIT_PER_DEX}% kritikal` },
    { key: 'int', abbr: 'INT', name: 'Kecerdasan', icon: Brain, value: eff.value.intelligence, gear: gear.value.intelligence, effect: `+${bonus(eff.value.intelligence) * MAGIC_PER_INT}% dmg sihir` },
    { key: 'vit', abbr: 'VIT', name: 'Ketahanan', icon: Shield, value: eff.value.vitality, gear: gear.value.vitality, effect: `+${bonus(eff.value.vitality)} pertahanan` },
    { key: 'luk', abbr: 'LUK', name: 'Keberuntungan', icon: Clover, value: eff.value.luck, gear: gear.value.luck, effect: `+${bonus(eff.value.luck) * GOLD_PER_LUK}% emas` },
]);

const critChance = computed(() => Math.min(100, bonus(eff.value.dexterity) * CRIT_PER_DEX + bonus(eff.value.luck) * CRIT_PER_LUK));
const dodgeChance = computed(() => Math.min(100, bonus(eff.value.agility) * DODGE_PER_AGI));
</script>

<template>
    <div class="rounded-xl border bg-card p-5">
        <h2 class="mb-3 font-display text-lg font-semibold">Atribut</h2>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div v-for="s in stats" :key="s.key" class="flex items-center gap-3 rounded-lg border bg-background/40 p-3" :title="s.name">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                    <component :is="s.icon" class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <div class="flex items-baseline gap-1.5">
                        <span class="font-display text-sm font-bold">{{ s.abbr }}</span>
                        <span class="text-lg font-bold tabular-nums">{{ s.value }}</span>
                        <span v-if="s.gear" class="text-xs font-semibold text-emerald-600">(+{{ s.gear }})</span>
                    </div>
                    <div class="truncate text-xs text-muted-foreground">{{ s.effect }}</div>
                </div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-x-5 gap-y-1 border-t pt-3 text-xs text-muted-foreground">
            <span>Peluang Kritikal: <span class="font-semibold text-foreground">{{ critChance }}%</span> (×1.5 damage)</span>
            <span>Peluang Menghindar: <span class="font-semibold text-foreground">{{ dodgeChance }}%</span></span>
        </div>
    </div>
</template>
