<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { NodeState } from '@/types/game';
import { router } from '@inertiajs/vue3';
import { Lock } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{ node: NodeState; questSlug: string }>();
const busy = ref(false);

function choose(choiceId: number, locked: boolean) {
    if (locked || busy.value) return;
    busy.value = true;
    router.post(
        route('quests.choose', props.questSlug),
        { choice_id: choiceId },
        { preserveScroll: true, onFinish: () => (busy.value = false) },
    );
}

const typeColors: Record<string, string> = {
    narrative: 'text-sky-500',
    choice: 'text-amber-500',
    reward: 'text-emerald-500',
    ending: 'text-violet-500',
};

const typeLabels: Record<string, string> = {
    narrative: 'Narasi',
    choice: 'Pilihan',
    combat: 'Pertarungan',
    reward: 'Hadiah',
    ending: 'Akhir',
};
</script>

<template>
    <div class="mx-auto max-w-2xl">
        <div v-if="node.image" class="mb-4 overflow-hidden rounded-xl border">
            <img :src="node.image" :alt="node.title ?? ''" class="h-56 w-full object-cover" />
        </div>

        <span class="text-xs font-semibold uppercase tracking-wider" :class="typeColors[node.type] ?? 'text-muted-foreground'">
            {{ typeLabels[node.type] ?? node.type }}
        </span>
        <h2 v-if="node.title" class="mt-1 text-2xl font-bold">{{ node.title }}</h2>
        <p v-if="node.body" class="mt-3 whitespace-pre-line font-serif text-lg leading-relaxed text-foreground/90">{{ node.body }}</p>

        <div v-if="node.choices.length" class="mt-6 flex flex-col gap-3">
            <Button
                v-for="c in node.choices"
                :key="c.id"
                :variant="c.locked ? 'outline' : 'default'"
                :disabled="c.locked || busy"
                class="h-auto justify-start whitespace-normal py-3 text-left"
                @click="choose(c.id, c.locked)"
            >
                <span>{{ c.label }}</span>
                <span v-if="c.locked && c.hint" class="ml-auto flex items-center gap-1 text-xs opacity-70">
                    <Lock class="h-3 w-3" /> {{ c.hint }}
                </span>
            </Button>
        </div>

        <div v-if="node.type === 'ending'" class="mt-8 flex flex-wrap gap-3">
            <Button variant="secondary" @click="router.visit(route('quests.index'))">Kembali ke Misi</Button>
            <!-- Hanya kekalahan yang bisa diulang; misi yang sukses sudah selesai. -->
            <Button v-if="(node.payload as any)?.result === 'defeat'" @click="router.post(route('quests.start', questSlug))">Coba Lagi</Button>
        </div>
    </div>
</template>
