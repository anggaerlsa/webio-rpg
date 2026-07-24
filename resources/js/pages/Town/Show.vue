<script setup lang="ts">
import CharacterHud from '@/components/game/CharacterHud.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { placeMeta } from '@/lib/places';
import { type BreadcrumbItem } from '@/types';
import type { CharacterState } from '@/types/game';
import { Head, Link } from '@inertiajs/vue3';
import { BedDouble, MapPin, Store } from 'lucide-vue-next';

interface PlaceCard {
    slug: string;
    name: string;
    category: string;
    category_label: string;
    description: string | null;
    is_shop: boolean;
    city: { slug: string; name: string } | null;
}

interface CityState {
    slug: string;
    name: string;
    description: string | null;
    province: string | null;
    country: string | null;
}

defineProps<{
    character: CharacterState;
    city: CityState | null;
    places: PlaceCard[];
    rest_cost: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Kota', href: '/town' }];
</script>

<template>
    <Head title="Kota" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl space-y-6 p-4">
            <CharacterHud :character="character" />

            <!-- Dunia belum punya kota -->
            <div v-if="!city" class="rounded-xl border border-dashed bg-card p-8 text-center text-muted-foreground">
                <MapPin class="mx-auto mb-3 h-10 w-10 opacity-60" />
                <p>Dunia belum memiliki kota. Para Dewa belum menempa tanah ini.</p>
            </div>

            <template v-else>
                <!-- Header kota -->
                <div class="rounded-xl border bg-card p-5">
                    <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-muted-foreground">
                        <MapPin class="h-4 w-4" />
                        <span>{{ [city.country, city.province].filter(Boolean).join(' · ') || 'Kota Asal' }}</span>
                    </div>
                    <h1 class="font-display mt-1 text-2xl font-bold">{{ city.name }}</h1>
                    <p v-if="city.description" class="font-serif mt-1 text-muted-foreground">{{ city.description }}</p>
                </div>

                <!-- Daftar Tempat -->
                <div>
                    <h2 class="mb-3 text-lg font-semibold">Tempat di {{ city.name }}</h2>
                    <div v-if="!places.length" class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Belum ada bangunan di kota ini.
                    </div>
                    <div v-else class="grid gap-3 sm:grid-cols-2">
                        <Link
                            v-for="place in places"
                            :key="place.slug"
                            :href="route('town.place', place.slug)"
                            class="group flex items-start gap-3 rounded-xl border bg-card p-4 transition hover:border-primary hover:bg-accent"
                        >
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-muted">
                                <component :is="placeMeta(place.category).icon" class="h-6 w-6" :class="placeMeta(place.category).tint" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-semibold">{{ place.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ place.category_label }}</div>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <span
                                        v-if="place.is_shop"
                                        class="inline-flex items-center gap-1 rounded bg-emerald-500/15 px-1.5 py-0.5 text-[11px] text-emerald-600"
                                    >
                                        <Store class="h-3 w-3" /> Toko
                                    </span>
                                    <span
                                        v-if="place.category === 'inn'"
                                        class="inline-flex items-center gap-1 rounded bg-rose-500/15 px-1.5 py-0.5 text-[11px] text-rose-500"
                                    >
                                        <BedDouble class="h-3 w-3" /> Istirahat · {{ rest_cost }} emas
                                    </span>
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
