<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { CharacterState } from '@/types/game';
import { Shield, Sparkles, Sword } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ character: CharacterState; disabled?: boolean }>();
const emit = defineEmits<{ (e: 'unequip', itemId: number): void }>();

const SLOTS = [
    { key: 'weapon', label: 'Senjata', icon: Sword, tint: 'text-rose-400' },
    { key: 'armor', label: 'Zirah', icon: Shield, tint: 'text-sky-400' },
    { key: 'accessory', label: 'Aksesori', icon: Sparkles, tint: 'text-fuchsia-400' },
];

const equipment = computed(() => props.character.equipment ?? {});
</script>

<template>
    <div class="rounded-xl border bg-card p-5">
        <h2 class="mb-3 font-display text-lg font-semibold">Perlengkapan</h2>
        <div class="grid gap-3 sm:grid-cols-3">
            <div
                v-for="slot in SLOTS"
                :key="slot.key"
                class="flex flex-col gap-2 rounded-lg border bg-background/40 p-3"
            >
                <div class="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                    <component :is="slot.icon" class="h-4 w-4" :class="slot.tint" /> {{ slot.label }}
                </div>
                <template v-if="equipment[slot.key]">
                    <div class="truncate text-sm font-semibold">{{ equipment[slot.key]!.name }}</div>
                    <Button size="sm" variant="outline" :disabled="disabled" @click="emit('unequip', equipment[slot.key]!.id)">
                        Lepas
                    </Button>
                </template>
                <div v-else class="text-sm text-muted-foreground/60 italic">kosong</div>
            </div>
        </div>
    </div>
</template>
