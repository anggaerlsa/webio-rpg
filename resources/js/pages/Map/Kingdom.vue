<script setup lang="ts">
import MapView, { type MapMarker } from '@/components/game/MapView.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Castle, Coins, Shield } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Settlement {
    slug: string;
    name: string;
    type: string;
    type_label: string;
    description: string | null;
    image: string | null;
    map_x: number | null;
    map_y: number | null;
    is_here: boolean;
    travel_cost: number;
    can_travel: boolean;
}

const props = defineProps<{
    kingdom: { slug: string; name: string; description: string | null; government_type: string | null; map_image: string | null };
    settlements: Settlement[];
    in_combat: boolean;
    gold: number;
}>();

const page = usePage();
const flashError = computed(() => (page.props as Record<string, any>).flash?.error as string | undefined);

const iconFor = (s: Settlement) => (s.type === 'fortress' ? Shield : Castle);

const markers = computed<MapMarker[]>(() =>
    props.settlements
        .filter((s) => s.map_x !== null && s.map_y !== null)
        .map((s) => ({ key: s.slug, x: s.map_x!, y: s.map_y!, label: s.name, icon: iconFor(s), here: s.is_here })),
);

// Kota tempat karakter berada → langsung masuk; selain itu konfirmasi perjalanan.
const target = ref<Settlement | null>(null);
function choose(slug: string) {
    const s = props.settlements.find((x) => x.slug === slug);
    if (!s) return;
    if (s.is_here) router.visit(route('town.show'));
    else target.value = s;
}

const travelling = ref(false);
function travel() {
    if (!target.value) return;
    router.post(
        route('map.travel', target.value.slug),
        {},
        {
            onStart: () => (travelling.value = true),
            onFinish: () => {
                travelling.value = false;
                target.value = null;
            },
        },
    );
}

function blockedReason(s: Settlement): string | null {
    if (props.in_combat) return 'Selesaikan pertarunganmu dulu — lanjutkan di menu Misi.';
    if (!s.can_travel) return `Emasmu kurang (butuh ${s.travel_cost}).`;
    return null;
}

const costLabel = (s: Settlement) => (s.travel_cost ? `${s.travel_cost} emas` : 'Gratis');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Peta', href: '/peta' },
    { title: props.kingdom.name, href: route('map.kingdom', props.kingdom.slug) },
];
</script>

<template>
    <Head :title="kingdom.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-5xl space-y-6 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div v-if="kingdom.government_type" class="text-xs uppercase tracking-wide text-muted-foreground">
                        {{ kingdom.government_type }}
                    </div>
                    <h1 class="font-display text-2xl font-bold">{{ kingdom.name }}</h1>
                    <p v-if="kingdom.description" class="max-w-2xl font-serif text-muted-foreground">{{ kingdom.description }}</p>
                </div>
                <Link :href="route('map.index')" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="h-4 w-4" /> Peta Benua
                </Link>
            </div>

            <div v-if="flashError" class="rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-2 text-sm text-red-600">{{ flashError }}</div>

            <MapView :image="kingdom.map_image" :markers="markers" @select="(m) => choose(m.key)" />

            <div>
                <h2 class="mb-3 text-lg font-semibold">Kota & Benteng</h2>
                <div v-if="!settlements.length" class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                    Belum ada kota atau benteng di kerajaan ini.
                </div>
                <div v-else class="grid gap-3 sm:grid-cols-2">
                    <button
                        v-for="s in settlements"
                        :key="s.slug"
                        type="button"
                        class="flex gap-3 overflow-hidden rounded-xl border bg-card p-3 text-left transition hover:border-primary hover:bg-accent"
                        @click="choose(s.slug)"
                    >
                        <img v-if="s.image" :src="s.image" alt="" class="h-20 w-28 shrink-0 rounded-lg object-cover" />
                        <div v-else class="flex h-20 w-28 shrink-0 items-center justify-center rounded-lg bg-muted">
                            <component :is="iconFor(s)" class="h-8 w-8 text-amber-600" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-semibold">{{ s.name }}</div>
                            <div class="text-xs text-muted-foreground">{{ s.type_label }}</div>
                            <div v-if="s.is_here" class="mt-1 text-xs font-medium text-amber-600">Kau di sini · Masuk</div>
                            <div v-else class="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground">
                                <Coins class="h-3 w-3" /> Pindah · {{ costLabel(s) }}
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <Dialog :open="!!target" @update:open="(open) => !open && (target = null)">
            <DialogContent v-if="target">
                <DialogHeader>
                    <DialogTitle>Berangkat ke {{ target.name }}?</DialogTitle>
                    <DialogDescription>{{ target.description || target.type_label }}</DialogDescription>
                </DialogHeader>
                <img v-if="target.image" :src="target.image" alt="" class="aspect-[3/2] w-full rounded-lg object-cover" />
                <div class="flex items-center justify-between rounded-lg bg-muted px-4 py-2 text-sm">
                    <span>Biaya perjalanan</span>
                    <span class="font-semibold"
                        >{{ costLabel(target) }} <span class="font-normal text-muted-foreground">· emasmu {{ gold }}</span></span
                    >
                </div>
                <p v-if="blockedReason(target)" class="text-sm text-red-600">{{ blockedReason(target) }}</p>
                <DialogFooter>
                    <Button variant="outline" @click="target = null">Batal</Button>
                    <Button :disabled="!!blockedReason(target) || travelling" @click="travel">Berangkat</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
