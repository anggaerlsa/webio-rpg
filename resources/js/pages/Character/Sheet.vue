<script setup lang="ts">
import CharacterHud from '@/components/game/CharacterHud.vue';
import CharacterStats from '@/components/game/CharacterStats.vue';
import EquipmentPanel from '@/components/game/EquipmentPanel.vue';
import InventoryGrid from '@/components/game/InventoryGrid.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { CharacterState, ItemState } from '@/types/game';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Sparkles, Swords } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ character: CharacterState }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Karakter', href: '/character' }];

const page = usePage();
const flashSuccess = computed(() => (page.props as Record<string, any>).flash?.success as string | undefined);
const flashError = computed(() => (page.props as Record<string, any>).flash?.error as string | undefined);

const processing = ref(false);

function postAction(url: string, itemId: number) {
    if (processing.value) return;
    processing.value = true;
    router.post(url, { item_id: itemId }, { preserveScroll: true, onFinish: () => (processing.value = false) });
}

function useItem(item: ItemState) {
    postAction(route('character.use-item'), item.id);
}
function equipItem(item: ItemState) {
    postAction(route('character.equip'), item.id);
}
function unequipItem(itemId: number) {
    postAction(route('character.unequip'), itemId);
}
function learnItem(item: ItemState) {
    postAction(route('character.learn'), item.id);
}
</script>

<template>
    <Head title="Karakter" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl space-y-6 p-4">
            <div v-if="flashSuccess" class="rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-600">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-2 text-sm text-red-600">
                {{ flashError }}
            </div>

            <CharacterHud :character="character" />

            <CharacterStats :character="character" />

            <EquipmentPanel :character="character" :disabled="processing" @unequip="unequipItem" />

            <div class="rounded-xl border bg-card p-5">
                <h2 class="mb-3 font-display text-lg font-semibold">Kemampuan</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <div class="mb-2 flex items-center gap-2 text-sm font-medium text-amber-600"><Swords class="h-4 w-4" /> Skill (SP)</div>
                        <ul v-if="character.skills.length" class="space-y-1">
                            <li v-for="s in character.skills" :key="s.id" class="flex items-center justify-between rounded-lg border bg-background px-3 py-1.5 text-sm">
                                <span>{{ s.name }}</span><span class="text-xs text-muted-foreground">pwr {{ s.power }}</span>
                            </li>
                        </ul>
                        <p v-else class="text-xs text-muted-foreground">Belum ada skill.</p>
                    </div>
                    <div>
                        <div class="mb-2 flex items-center gap-2 text-sm font-medium text-indigo-500"><Sparkles class="h-4 w-4" /> Sihir (MP)</div>
                        <ul v-if="character.spells.length" class="space-y-1">
                            <li v-for="s in character.spells" :key="s.id" class="flex items-center justify-between rounded-lg border bg-background px-3 py-1.5 text-sm">
                                <span>{{ s.name }}</span><span class="text-xs text-muted-foreground">pwr {{ s.power }}</span>
                            </li>
                        </ul>
                        <p v-else class="text-xs text-muted-foreground">Belum ada sihir.</p>
                    </div>
                </div>
                <p class="mt-3 text-xs text-muted-foreground">Pelajari skill/sihir baru dari buku yang dijual di Toko Sihir.</p>
            </div>

            <div class="rounded-xl border bg-card p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Inventaris</h2>
                </div>
                <InventoryGrid
                    :items="character.items"
                    :character="character"
                    :use-disabled="processing"
                    @use="useItem"
                    @equip="equipItem"
                    @unequip="(item) => unequipItem(item.id)"
                    @learn="learnItem"
                />
            </div>
        </div>
    </AppLayout>
</template>
