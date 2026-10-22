<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { MessageCircle, UserRound } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

export interface NpcState {
    slug: string;
    name: string;
    role: string;
    image: string | null;
    greeting: string;
    lines: string[];
}

const props = defineProps<{ npcs: NpcState[] }>();

const current = ref(0);
const npc = computed(() => props.npcs[current.value]);
const text = ref(npc.value.greeting);
watch(npc, (n) => (text.value = n.greeting));

// Baris acak, tak pernah sama dengan yang sedang tampil.
function talk() {
    const options = npc.value.lines.filter((l) => l !== text.value);
    if (options.length) text.value = options[Math.floor(Math.random() * options.length)];
}
</script>

<template>
    <div class="overflow-hidden rounded-xl border bg-card">
        <div v-if="npcs.length > 1" class="flex flex-wrap gap-1 border-b p-2">
            <button
                v-for="(n, i) in npcs"
                :key="n.slug"
                type="button"
                class="rounded-md px-3 py-1 text-sm transition"
                :class="i === current ? 'bg-primary text-primary-foreground' : 'hover:bg-accent'"
                @click="current = i"
            >
                {{ n.name }}
            </button>
        </div>

        <div class="flex gap-4 p-4">
            <!-- Aset full body; tampil setengah badan: diperbesar 200% dan dipotong dari atas. -->
            <div class="relative aspect-[3/4] w-28 shrink-0 self-start overflow-hidden rounded-lg bg-muted sm:w-40">
                <img v-if="npc.image" :src="npc.image" :alt="npc.name" class="absolute left-1/2 top-0 w-[200%] max-w-none -translate-x-1/2" />
                <UserRound v-else class="absolute inset-0 m-auto h-12 w-12 text-muted-foreground" />
            </div>

            <div class="flex min-w-0 flex-1 flex-col">
                <div class="font-display text-lg font-bold leading-tight">{{ npc.name }}</div>
                <div class="text-xs uppercase tracking-wide text-muted-foreground">{{ npc.role }}</div>
                <p class="mt-2 flex-1 rounded-lg bg-muted/60 p-3 font-serif italic leading-relaxed" aria-live="polite">“{{ text }}”</p>
                <div v-if="npc.lines.length" class="mt-3">
                    <Button size="sm" variant="outline" @click="talk"><MessageCircle class="mr-1 h-4 w-4" /> Bicara</Button>
                </div>
            </div>
        </div>
    </div>
</template>
