<script setup lang="ts">
import CharacterHud from '@/components/game/CharacterHud.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { CharacterState } from '@/types/game';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Castle, Scroll, ShieldHalf } from 'lucide-vue-next';
import { computed } from 'vue';

interface RankProgress {
    current: string;
    next: string | null;
    completed: number;
    required: number | null;
}
interface ActiveMission {
    slug: string;
    title: string;
    description: string | null;
    failed: boolean;
}
interface CompletedMission {
    slug: string;
    title: string;
    completed_at: string | null;
}

defineProps<{
    character: CharacterState;
    rank: RankProgress;
    active: ActiveMission | null;
    completed: CompletedMission[];
}>();

const page = usePage();
const flashError = computed(() => (page.props as Record<string, any>).flash?.error as string | undefined);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Misi', href: '/quests' }];
</script>

<template>
    <Head title="Misi" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl space-y-6 p-4">
            <div v-if="flashError" class="rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-2 text-sm text-red-600">
                {{ flashError }}
            </div>

            <CharacterHud :character="character" />

            <!-- Progres rank -->
            <div class="rounded-xl border bg-card p-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 font-semibold"><ShieldHalf class="h-5 w-5 text-amber-500" /> Rank</div>
                    <div class="font-display text-2xl font-bold text-amber-500">{{ rank.current }}</div>
                </div>
                <template v-if="rank.next && rank.required">
                    <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full bg-amber-500 transition-all"
                            :style="{ width: Math.min(100, (rank.completed / rank.required) * 100) + '%' }"
                        />
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ rank.completed }} / {{ rank.required }} misi menuju Rank <b>{{ rank.next }}</b>
                    </div>
                </template>
                <div v-else class="mt-2 text-xs text-muted-foreground">Rank tertinggi tercapai.</div>
            </div>

            <!-- Misi aktif -->
            <div class="rounded-xl border bg-card p-5">
                <h2 class="mb-3 flex items-center gap-2 text-lg font-semibold"><Scroll class="h-5 w-5 text-primary" /> Misi Aktif</h2>
                <div v-if="active">
                    <div class="text-lg font-semibold">{{ active.title }}</div>
                    <p v-if="active.description" class="font-serif mt-1 text-sm text-muted-foreground">{{ active.description }}</p>
                    <div class="mt-3 flex gap-2">
                        <Button @click="router.visit(route('quests.play', active.slug))">
                            {{ active.failed ? 'Ulangi misi' : 'Lanjutkan' }}
                        </Button>
                    </div>
                </div>
                <div v-else class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                    <p>Belum ada misi aktif.</p>
                    <Link :href="route('town.show')" class="mt-3 inline-flex items-center gap-1 text-primary hover:underline">
                        <Castle class="h-4 w-4" /> Pergi ke guild di kota untuk mengambil misi
                    </Link>
                </div>
            </div>

            <!-- Riwayat misi selesai -->
            <div v-if="completed.length" class="rounded-xl border bg-card p-5">
                <h2 class="mb-3 flex items-center gap-2 text-lg font-semibold"><CheckCircle2 class="h-5 w-5 text-emerald-500" /> Misi Selesai</h2>
                <ul class="space-y-1">
                    <li v-for="m in completed" :key="m.slug" class="flex items-center justify-between rounded-lg border bg-background px-3 py-2 text-sm">
                        <span class="font-medium">{{ m.title }}</span>
                        <span class="text-xs text-muted-foreground">{{ m.completed_at }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
