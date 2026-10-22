<script setup lang="ts">
import CharacterHud from '@/components/game/CharacterHud.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { placeMeta } from '@/lib/places';
import { type BreadcrumbItem } from '@/types';
import type { CharacterState } from '@/types/game';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BedDouble, Compass, MapPin, Store } from 'lucide-vue-next';
import { computed } from 'vue';

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
    image: string | null;
    type_label: string;
    province: string | null;
    country: string | null;
    country_slug: string | null;
}

const props = defineProps<{
    character: CharacterState;
    city: CityState | null;
    places: PlaceCard[];
    rest_cost: number;
}>();

const page = usePage();
const flashSuccess = computed(() => (page.props as Record<string, any>).flash?.success as string | undefined);
const flashError = computed(() => (page.props as Record<string, any>).flash?.error as string | undefined);

const breadcrumbs: BreadcrumbItem[] = props.city?.country_slug
    ? [
          { title: 'Peta', href: '/peta' },
          { title: props.city.country ?? '', href: route('map.kingdom', props.city.country_slug) },
          { title: props.city.name, href: '/town' },
      ]
    : [{ title: 'Kota', href: '/town' }];
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
                <div v-if="flashError" class="rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-2 text-sm text-red-600">
                    {{ flashError }}
                </div>
                <div v-if="flashSuccess" class="rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-600">
                    {{ flashSuccess }}
                </div>

                <!-- Header kota -->
                <div class="overflow-hidden rounded-xl border bg-card">
                    <img v-if="city.image" :src="city.image" :alt="city.name" class="aspect-[3/1] w-full object-cover" />
                    <div class="flex flex-wrap items-start justify-between gap-3 p-5">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-muted-foreground">
                                <MapPin class="h-4 w-4" />
                                <span>{{ [city.type_label, city.country].filter(Boolean).join(' · ') }}</span>
                            </div>
                            <h1 class="mt-1 font-display text-2xl font-bold">{{ city.name }}</h1>
                            <p v-if="city.description" class="mt-1 font-serif text-muted-foreground">{{ city.description }}</p>
                        </div>
                        <Link
                            :href="route('map.index')"
                            class="inline-flex shrink-0 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition hover:border-primary hover:bg-accent"
                        >
                            <Compass class="h-4 w-4" /> Keluar Kota
                        </Link>
                    </div>
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
