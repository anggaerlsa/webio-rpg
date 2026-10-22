<script setup lang="ts">
import MapView, { type MapMarker } from '@/components/game/MapView.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, Crown } from 'lucide-vue-next';
import { computed } from 'vue';

interface Kingdom {
    slug: string;
    name: string;
    map_x: number | null;
    map_y: number | null;
    is_here: boolean;
}

const props = defineProps<{ kingdoms: Kingdom[] }>();

// Kerajaan tanpa posisi tetap muncul di daftar, hanya tidak di peta.
const markers = computed<MapMarker[]>(() =>
    props.kingdoms
        .filter((k) => k.map_x !== null && k.map_y !== null)
        .map((k) => ({ key: k.slug, x: k.map_x!, y: k.map_y!, label: k.name, icon: Crown, here: k.is_here })),
);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Peta', href: '/peta' }];
</script>

<template>
    <Head title="Peta Benua" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-5xl space-y-6 p-4">
            <div>
                <h1 class="font-display text-2xl font-bold">Peta Benua</h1>
                <p class="font-serif text-muted-foreground">Pilih kerajaan untuk melihat kota dan benteng di dalamnya.</p>
            </div>

            <MapView image="/images/maps/world.webp" :markers="markers" @select="(m) => router.visit(route('map.kingdom', m.key))" />

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="k in kingdoms"
                    :key="k.slug"
                    :href="route('map.kingdom', k.slug)"
                    class="flex items-center gap-3 rounded-xl border bg-card p-4 transition hover:border-primary hover:bg-accent"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-muted">
                        <Crown class="h-5 w-5 text-amber-600" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-semibold">{{ k.name }}</div>
                        <div v-if="k.is_here" class="text-xs text-amber-600">Kau di sini</div>
                    </div>
                    <ChevronRight class="h-4 w-4 text-muted-foreground" />
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
