<script setup lang="ts">
import StatBar from '@/components/game/StatBar.vue';
import type { CharacterState } from '@/types/game';
import { Coins, Shield, ShieldHalf, Sword, Wand2 } from 'lucide-vue-next';

defineProps<{ character: CharacterState }>();

const jobLabels: Record<string, string> = { adventurer: 'Petualang', merchant: 'Pedagang', Commoner: 'Commoner' };
const classLabels: Record<string, string> = { Warrior: 'Ksatria', Mage: 'Penyihir' };
const genderLabels: Record<string, string> = { male: 'Pria', female: 'Wanita' };
</script>

<template>
    <div class="rounded-xl border bg-card p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="font-display text-lg font-bold">{{ character.name }}</span>
                    <span v-if="character.rank" class="rounded bg-amber-500/15 px-1.5 py-0.5 text-xs font-semibold text-amber-600">Rank {{ character.rank }}</span>
                </div>
                <div class="text-xs text-muted-foreground">
                    <span v-if="character.job">{{ jobLabels[character.job] ?? character.job }}</span>
                    <template v-if="character.class"> · {{ classLabels[character.class] ?? character.class }}</template>
                    · Level {{ character.level }}
                </div>
                <div v-if="character.gender || character.age !== null" class="text-xs text-muted-foreground">
                    <span v-if="character.gender">{{ genderLabels[character.gender] ?? character.gender }}</span>
                    <template v-if="character.age !== null"> · {{ character.age }} tahun</template>
                </div>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <span class="flex items-center gap-1" title="Serangan (termasuk perlengkapan)">
                    <Sword class="h-4 w-4 text-muted-foreground" />{{ character.effective?.attack ?? character.attack }}
                    <span v-if="character.equip_bonuses?.attack" class="text-xs text-emerald-600">(+{{ character.equip_bonuses.attack }})</span>
                </span>
                <span class="flex items-center gap-1" title="Pertahanan (termasuk perlengkapan)">
                    <Shield class="h-4 w-4 text-muted-foreground" />{{ character.effective?.defense ?? character.defense }}
                    <span v-if="character.equip_bonuses?.defense" class="text-xs text-emerald-600">(+{{ character.equip_bonuses.defense }})</span>
                </span>
                <span class="flex items-center gap-1" title="Serangan Sihir (termasuk perlengkapan)">
                    <Wand2 class="h-4 w-4 text-indigo-400" />{{ character.effective?.magic_attack ?? character.magic_attack }}
                    <span v-if="character.equip_bonuses?.magic_attack" class="text-xs text-emerald-600">(+{{ character.equip_bonuses.magic_attack }})</span>
                </span>
                <span class="flex items-center gap-1" title="Pertahanan Sihir (termasuk perlengkapan)">
                    <ShieldHalf class="h-4 w-4 text-indigo-400" />{{ character.effective?.magic_defense ?? character.magic_defense }}
                    <span v-if="character.equip_bonuses?.magic_defense" class="text-xs text-emerald-600">(+{{ character.equip_bonuses.magic_defense }})</span>
                </span>
                <span class="flex items-center gap-1" title="Emas"><Coins class="h-4 w-4 text-amber-500" />{{ character.gold }}</span>
            </div>
        </div>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            <StatBar label="HP" :value="character.hp" :max="character.max_hp" color="bg-emerald-500" />
            <StatBar label="SP" :value="character.sp" :max="character.max_sp" color="bg-amber-500" />
            <StatBar label="MP" :value="character.mp" :max="character.max_mp" color="bg-indigo-500" />
            <StatBar label="XP" :value="character.xp" :max="character.xp_to_next" color="bg-sky-500" />
        </div>
    </div>
</template>
