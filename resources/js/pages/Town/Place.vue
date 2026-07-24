<script setup lang="ts">
import CharacterHud from '@/components/game/CharacterHud.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { placeMeta } from '@/lib/places';
import { type BreadcrumbItem } from '@/types';
import type { CharacterState } from '@/types/game';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, BedDouble, Coins, FlaskConical, ScrollText, ShieldHalf, Sparkles } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface PlaceState {
    slug: string;
    name: string;
    category: string;
    category_label: string;
    description: string | null;
    is_shop: boolean;
    is_guild: boolean;
    city: { slug: string; name: string } | null;
}

interface RankProgress {
    current: string;
    next: string | null;
    completed: number;
    required: number | null;
}
interface Mission {
    slug: string;
    title: string;
    description: string | null;
    min_level: number;
    required_rank: string;
    is_active: boolean;
    block_reason: string | null;
}

interface ShopItem {
    id: number;
    slug: string;
    name: string;
    type: string;
    description: string | null;
    image: string | null;
    value: number;
    heal: number;
    restore_sp: number;
    restore_mp: number;
}

interface SellItem extends ShopItem {
    quantity: number;
    equipped: boolean;
    sell_price: number;
}

const props = defineProps<{
    character: CharacterState;
    place: PlaceState;
    rest_cost?: number;
    is_fully_rested?: boolean;
    stock?: ShopItem[];
    sellable?: SellItem[];
    rank?: RankProgress;
    can_accept?: boolean;
    active_mission?: { slug: string; title: string } | null;
    missions?: Mission[];
}>();

const page = usePage();
const flashSuccess = computed(() => (page.props as Record<string, any>).flash?.success as string | undefined);
const flashError = computed(() => (page.props as Record<string, any>).flash?.error as string | undefined);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Kota', href: '/town' },
    { title: props.place.name, href: route('town.place', props.place.slug) },
];

const isGuildOrHall = computed(() => ['city_hall', 'adventurer_guild', 'merchant_guild'].includes(props.place.category));

const processing = ref(false);
function post(url: string, payload: Record<string, unknown> = {}) {
    if (processing.value) return;
    processing.value = true;
    router.post(url, payload, { preserveScroll: true, onFinish: () => (processing.value = false) });
}

const canRest = computed(() => !props.is_fully_rested && props.character.gold >= (props.rest_cost ?? 0));

function restoreLabel(item: ShopItem): string[] {
    const out: string[] = [];
    if (item.heal > 0) out.push(`+${item.heal} HP`);
    if (item.restore_sp > 0) out.push(`+${item.restore_sp} SP`);
    if (item.restore_mp > 0) out.push(`+${item.restore_mp} MP`);
    return out;
}

function acceptMission(slug: string) {
    if (processing.value) return;
    processing.value = true;
    router.post(route('town.mission.accept', props.place.slug), { quest_slug: slug }, { onFinish: () => (processing.value = false) });
}
</script>

<template>
    <Head :title="place.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl space-y-6 p-4">
            <Link :href="route('town.show')" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                <ArrowLeft class="h-4 w-4" /> Kembali ke {{ place.city?.name ?? 'Kota' }}
            </Link>

            <div v-if="flashSuccess" class="rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-600">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-2 text-sm text-red-600">
                {{ flashError }}
            </div>

            <!-- Header tempat -->
            <div class="rounded-xl border bg-card p-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-muted">
                        <component :is="placeMeta(place.category).icon" class="h-7 w-7" :class="placeMeta(place.category).tint" />
                    </div>
                    <div>
                        <h1 class="font-display text-2xl font-bold">{{ place.name }}</h1>
                        <div class="text-xs uppercase tracking-wide text-muted-foreground">{{ place.category_label }}</div>
                    </div>
                </div>
                <p v-if="place.description" class="font-serif mt-3 text-muted-foreground">{{ place.description }}</p>
            </div>

            <CharacterHud :character="character" />

            <!-- PENGINAPAN: istirahat -->
            <div v-if="place.category === 'inn'" class="rounded-xl border bg-card p-5">
                <div class="flex items-center gap-2 font-semibold"><BedDouble class="h-5 w-5 text-rose-400" /> Istirahat</div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Beristirahat memulihkan HP, SP, dan MP-mu sepenuhnya dengan biaya
                    <span class="font-medium text-amber-600">{{ rest_cost }} emas</span>.
                </p>
                <div class="mt-4 flex items-center gap-3">
                    <Button :disabled="!canRest || processing" @click="post(route('town.rest', place.slug))">
                        <BedDouble class="mr-2 h-4 w-4" /> Istirahat ({{ rest_cost }} emas)
                    </Button>
                    <span v-if="is_fully_rested" class="text-xs text-muted-foreground">Tenagamu sudah penuh.</span>
                    <span v-else-if="character.gold < (rest_cost ?? 0)" class="text-xs text-red-500">Emasmu tidak cukup.</span>
                </div>
            </div>

            <!-- TOKO: beli & jual -->
            <template v-if="place.is_shop">
                <!-- Beli -->
                <div class="rounded-xl border bg-card p-5">
                    <h2 class="mb-3 flex items-center gap-2 font-semibold"><Coins class="h-5 w-5 text-amber-500" /> Barang Dagangan</h2>
                    <div v-if="!stock || !stock.length" class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Toko ini sedang kosong.
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="item in stock" :key="item.id" class="flex items-center gap-3 rounded-lg border bg-background p-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-muted">
                                <img v-if="item.image" :src="item.image" :alt="item.name" class="h-full w-full rounded-md object-cover" />
                                <FlaskConical v-else-if="restoreLabel(item).length" class="h-5 w-5 text-emerald-500" />
                                <Sparkles v-else class="h-5 w-5 text-muted-foreground" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium">{{ item.name }}</div>
                                <div class="text-xs text-muted-foreground">
                                    <span v-for="(r, i) in restoreLabel(item)" :key="i" class="mr-1 text-emerald-600">{{ r }}</span>
                                    <span v-if="!restoreLabel(item).length && item.description" class="line-clamp-1">{{ item.description }}</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-amber-600">{{ item.value }} emas</div>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="mt-1"
                                    :disabled="processing || character.gold < item.value"
                                    @click="post(route('town.buy', place.slug), { item_id: item.id, qty: 1 })"
                                >
                                    Beli
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Jual -->
                <div class="rounded-xl border bg-card p-5">
                    <h2 class="mb-3 flex items-center gap-2 font-semibold"><Coins class="h-5 w-5 text-emerald-500" /> Jual Barangmu</h2>
                    <div v-if="!sellable || !sellable.length" class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Tak ada barang yang bisa kamu jual di sini.
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="item in sellable" :key="item.id" class="flex items-center gap-3 rounded-lg border bg-background p-3">
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium">{{ item.name }} <span class="text-muted-foreground">×{{ item.quantity }}</span></div>
                                <div class="text-xs text-muted-foreground">Harga jual {{ item.sell_price }} emas / unit</div>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="processing || item.equipped"
                                @click="post(route('town.sell', place.slug), { item_id: item.id, qty: 1 })"
                            >
                                Jual 1
                            </Button>
                        </div>
                    </div>
                </div>
            </template>

            <!-- GUILD: papan misi -->
            <template v-if="place.is_guild">
                <!-- Progres rank -->
                <div v-if="rank" class="rounded-xl border bg-card p-5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 font-semibold"><ShieldHalf class="h-5 w-5 text-amber-500" /> Rank-mu</div>
                        <div class="font-display text-2xl font-bold text-amber-500">{{ rank.current }}</div>
                    </div>
                    <template v-if="rank.next && rank.required">
                        <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full bg-amber-500 transition-all" :style="{ width: Math.min(100, (rank.completed / rank.required) * 100) + '%' }" />
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">{{ rank.completed }} / {{ rank.required }} misi menuju Rank <b>{{ rank.next }}</b></div>
                    </template>
                </div>

                <!-- Misi aktif -->
                <div v-if="active_mission" class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-5">
                    <div class="text-sm text-muted-foreground">Misi aktifmu</div>
                    <div class="mt-1 text-lg font-semibold">{{ active_mission.title }}</div>
                    <Button class="mt-3" @click="router.visit(route('quests.play', active_mission.slug))">Lanjutkan misi</Button>
                    <p class="mt-2 text-xs text-muted-foreground">Selesaikan misi ini sebelum mengambil yang baru.</p>
                </div>

                <!-- Papan misi -->
                <div class="rounded-xl border bg-card p-5">
                    <h2 class="mb-3 flex items-center gap-2 font-semibold"><ScrollText class="h-5 w-5 text-primary" /> Papan Misi</h2>
                    <div v-if="!missions || !missions.length" class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Tidak ada misi yang tersedia untukmu saat ini.
                    </div>
                    <div v-else class="space-y-3">
                        <div v-for="m in missions" :key="m.slug" class="rounded-lg border bg-background p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-semibold">{{ m.title }}</div>
                                    <p v-if="m.description" class="font-serif mt-0.5 text-sm text-muted-foreground">{{ m.description }}</p>
                                    <div class="mt-1 flex flex-wrap gap-1 text-[11px]">
                                        <span class="rounded bg-muted px-1.5 py-0.5">Level min {{ m.min_level }}</span>
                                        <span class="rounded bg-amber-500/15 px-1.5 py-0.5 text-amber-600">Rank {{ m.required_rank }}</span>
                                    </div>
                                </div>
                                <Button
                                    v-if="!m.is_active"
                                    size="sm"
                                    :disabled="processing || !!m.block_reason"
                                    @click="acceptMission(m.slug)"
                                >
                                    Ambil
                                </Button>
                                <Button v-else size="sm" variant="outline" @click="router.visit(route('quests.play', m.slug))">Lanjutkan</Button>
                            </div>
                            <div v-if="m.block_reason && !m.is_active" class="mt-2 text-xs text-red-500">{{ m.block_reason }}</div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- CITY HALL / GUILD: info -->
            <div v-if="isGuildOrHall" class="rounded-xl border bg-card p-5">
                <p class="font-serif text-muted-foreground">
                    <template v-if="place.category === 'city_hall'">
                        Aula kota tempat para birokrat mengatur urusan {{ place.city?.name }}. Layanan resmi akan dibuka seiring berkembangnya dunia.
                    </template>
                    <template v-else>
                        Markas guild. Papan misi dan kenaikan rank akan tersedia di sini setelah sistem rank dibuka.
                        <span v-if="character.affiliation">
                            Kamu seorang <b class="capitalize">{{ character.affiliation }}</b> dengan Rank <b>{{ character.rank ?? 'F' }}</b>.
                        </span>
                    </template>
                </p>
            </div>
        </div>
    </AppLayout>
</template>
