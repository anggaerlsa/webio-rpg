<script setup lang="ts">
import CharacterHud from '@/components/game/CharacterHud.vue';
import CombatView from '@/components/game/CombatView.vue';
import NodeRenderer from '@/components/game/NodeRenderer.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { GameState } from '@/types/game';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps<{ state: GameState }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Misi', href: '/quests' },
    { title: props.state.quest?.title ?? 'Misi', href: '#' },
];
</script>

<template>
    <Head :title="state.quest?.title ?? 'Misi'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl space-y-6 p-4">
            <CharacterHud :character="state.character" />

            <div v-if="!state.node" class="rounded-xl border bg-card p-6 text-center text-muted-foreground">
                Tidak ada adegan aktif.
                <Button class="ml-2" variant="outline" @click="router.visit(route('quests.index'))">Kembali ke Misi</Button>
            </div>

            <CombatView
                v-else-if="state.node.type === 'combat'"
                :key="state.node.id"
                :node-id="state.node.id"
                :quest-slug="state.quest!.slug"
            />

            <NodeRenderer v-else :key="state.node.id" :node="state.node" :quest-slug="state.quest!.slug" />
        </div>
    </AppLayout>
</template>
