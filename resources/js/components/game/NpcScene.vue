<script setup lang="ts">
import { postJson } from '@/lib/gameApi';
import { router } from '@inertiajs/vue3';
import { BedDouble, DoorOpen, HandHeart, Heart, Lock, MessageCircle, ScrollText, Store } from 'lucide-vue-next';
import { computed, ref, watch, type Component } from 'vue';

export interface NpcChoice {
    label: string;
    reply: string | null;
    locked: boolean;
    level_label: string | null;
}

export interface Affection {
    level: string;
    label: string;
    value: number;
}

export interface NpcState {
    slug: string;
    name: string;
    role: string;
    image: string | null;
    greeting: string;
    affection: Affection;
    can_apologize: boolean;
    choices: NpcChoice[];
}

export type SceneAction = 'shop' | 'rest' | 'missions';

const props = defineProps<{
    npcs: NpcState[];
    place: { slug: string; name: string; category: string; is_shop: boolean; is_guild: boolean };
    compact?: boolean;
}>();
const emit = defineEmits<{ action: [kind: SceneAction]; talk: [] }>();

const current = ref(0);
const npc = computed(() => props.npcs[current.value]);
const text = ref(npc.value.greeting);
watch(
    () => npc.value.slug,
    () => (text.value = npc.value.greeting),
);

// Afeksi terbaru dari server (props ikut diperbarui saat level berubah).
const affection = ref<Affection>(npc.value.affection);
watch(npc, (n) => (affection.value = n.affection));
const hostile = computed(() => ['disliked', 'hated'].includes(affection.value.level));
const barWidth = computed(() => `${(affection.value.value + 100) / 2}%`);

const busy = ref(false);
const delta = ref<number | null>(null);
let deltaTimer: ReturnType<typeof setTimeout> | undefined;

async function talk(choice: number | 'apology') {
    if (busy.value) return;
    busy.value = true;
    try {
        const res = await postJson<{ reply: string; delta: number; affection: Affection }>(
            route('town.npc.talk', [props.place.slug, npc.value.slug]),
            { choice },
        );
        text.value = res.reply;
        if (res.delta !== 0) {
            delta.value = res.delta;
            clearTimeout(deltaTimer);
            deltaTimer = setTimeout(() => (delta.value = null), 1800);
        }
        const levelChanged = res.affection.level !== affection.value.level;
        affection.value = res.affection;
        // Level berubah = sapaan, pilihan yang terbuka, dan harga ikut berubah.
        if (levelChanged) router.reload({ only: ['npcs', 'price_modifier', 'stock', 'rest_cost'] });
    } catch (e) {
        text.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}

// Aksi fungsi tempat muncul otomatis menurut kategori, jadi tak bisa hilang karena admin lupa.
const actions = computed(() => {
    const list: { kind: SceneAction; label: string; icon: Component }[] = [];
    if (props.place.is_shop) list.push({ kind: 'shop', label: 'Lihat barang dagangan', icon: Store });
    if (props.place.category === 'inn') list.push({ kind: 'rest', label: 'Aku mau istirahat', icon: BedDouble });
    if (props.place.is_guild) list.push({ kind: 'missions', label: 'Lihat papan misi', icon: ScrollText });
    return list;
});

// Suasana latar per kategori tempat (sementara, sampai ada gambar interior).
const BACKDROPS: Record<string, string> = {
    city_hall: 'radial-gradient(ellipse at 30% 20%, #3b3f73 0%, #161a33 55%, #07080f 100%)',
    adventurer_guild: 'radial-gradient(ellipse at 30% 25%, #6b2a22 0%, #2a1210 55%, #0b0605 100%)',
    merchant_guild: 'radial-gradient(ellipse at 30% 25%, #2f5a45 0%, #13261d 55%, #060c09 100%)',
    market: 'radial-gradient(ellipse at 30% 25%, #8a5a24 0%, #3a2610 55%, #120b05 100%)',
    blacksmith: 'radial-gradient(ellipse at 25% 70%, #b8501c 0%, #4a1d0c 45%, #0f0604 100%)',
    potion_shop: 'radial-gradient(ellipse at 30% 25%, #3f6b3a 0%, #1a2e18 55%, #070d06 100%)',
    magic_shop: 'radial-gradient(ellipse at 30% 25%, #5a3a86 0%, #241638 55%, #0a0611 100%)',
    inn: 'radial-gradient(ellipse at 25% 65%, #a8622a 0%, #45250f 50%, #120904 100%)',
};
const backdrop = computed(() => BACKDROPS[props.place.category] ?? BACKDROPS.city_hall);
</script>

<template>
    <!-- Mode ringkas: adegan menyusut jadi baris kecil saat pemain melihat isi tempat. -->
    <div v-if="compact" class="flex items-center gap-3 rounded-xl border p-2 pr-3 text-white" :style="{ background: backdrop }">
        <div class="relative h-12 w-12 shrink-0 overflow-hidden rounded-full border border-white/30 bg-black/30">
            <img v-if="npc.image" :src="npc.image" alt="" class="absolute left-1/2 top-[-2%] w-[420%] max-w-none -translate-x-1/2" />
        </div>
        <div class="min-w-0 flex-1">
            <div class="truncate font-display font-bold leading-tight">{{ npc.name }}</div>
            <div class="truncate text-xs text-white/70">{{ place.name }} · {{ affection.label }}</div>
        </div>
        <button
            type="button"
            class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-white/15 px-3 py-1.5 text-sm hover:bg-white/25"
            @click="emit('talk')"
        >
            <MessageCircle class="h-4 w-4" /> Kembali ngobrol
        </button>
    </div>

    <div v-else class="relative h-[68vh] max-h-[680px] min-h-[500px] overflow-hidden rounded-xl border text-white" :style="{ background: backdrop }">
        <div class="absolute left-4 top-3 z-20 text-xs uppercase tracking-[0.25em] text-white/70">{{ place.name }}</div>

        <div v-if="npcs.length > 1" class="absolute right-3 top-2 z-20 flex flex-wrap justify-end gap-1">
            <button
                v-for="(n, i) in npcs"
                :key="n.slug"
                type="button"
                class="rounded-md px-2.5 py-1 text-xs backdrop-blur transition"
                :class="i === current ? 'bg-white/90 text-black' : 'bg-black/40 hover:bg-black/60'"
                @click="current = i"
            >
                {{ n.name }}
            </button>
        </div>

        <!-- Aset full body; dua kali tinggi adegan & rata atas = yang tampak setengah badan. -->
        <img
            v-if="npc.image"
            :key="npc.slug"
            :src="npc.image"
            :alt="npc.name"
            class="pointer-events-none absolute left-1/2 top-0 h-[200%] w-auto max-w-none -translate-x-1/2 select-none drop-shadow-[0_10px_30px_rgba(0,0,0,0.6)] md:left-[28%]"
        />

        <!-- Kotak dialog menimpa bagian bawah adegan. -->
        <div class="absolute inset-x-0 bottom-0 z-10 p-2 sm:p-3">
            <div class="rounded-xl border border-white/15 bg-black/75 p-3 shadow-2xl backdrop-blur-sm sm:p-4">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="font-display text-lg font-bold text-amber-200">{{ npc.name }}</span>
                    <span class="text-xs uppercase tracking-wide text-white/60">{{ npc.role }}</span>
                    <span class="ml-auto inline-flex items-center gap-1.5 text-xs" :title="`Afeksi ${affection.value}`">
                        <Heart class="h-3.5 w-3.5" :class="hostile ? 'text-red-400' : 'fill-pink-400 text-pink-400'" />
                        <span :class="hostile ? 'text-red-300' : 'text-pink-200'">{{ affection.label }}</span>
                        <span class="relative h-1.5 w-16 overflow-hidden rounded-full bg-white/15">
                            <span
                                class="absolute inset-y-0 left-0 rounded-full"
                                :class="hostile ? 'bg-red-400' : 'bg-pink-400'"
                                :style="{ width: barWidth }"
                            />
                        </span>
                        <Transition
                            enter-from-class="opacity-0 -translate-y-1"
                            leave-to-class="opacity-0"
                            enter-active-class="transition"
                            leave-active-class="transition duration-500"
                        >
                            <span v-if="delta" class="font-bold" :class="delta > 0 ? 'text-pink-300' : 'text-red-400'">{{
                                delta > 0 ? `+${delta} ♥` : delta
                            }}</span>
                        </Transition>
                    </span>
                </div>
                <p class="mt-1 font-serif text-[15px] leading-snug sm:text-base sm:leading-relaxed" aria-live="polite">“{{ text }}”</p>

                <!-- Aksi tempat & Pamit selalu terlihat; obrolan berupa chip kecil yang bisa di-scroll. -->
                <div class="mt-2.5 flex flex-wrap gap-1.5">
                    <button
                        v-for="a in actions"
                        :key="a.kind"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full border border-amber-300/50 bg-amber-400/20 px-3 py-1 text-[13px] font-medium text-amber-100 transition hover:bg-amber-400/30"
                        @click="emit('action', a.kind)"
                    >
                        <component :is="a.icon" class="h-3.5 w-3.5 shrink-0" /> {{ a.label }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full border border-white/15 px-3 py-1 text-[13px] text-white/70 transition hover:bg-white/10"
                        @click="router.visit(route('town.show'))"
                    >
                        <DoorOpen class="h-3.5 w-3.5 shrink-0" /> Pamit
                    </button>
                </div>
                <div class="mt-2 flex max-h-24 flex-wrap gap-1.5 overflow-y-auto pr-1 sm:max-h-28">
                    <button
                        v-if="npc.can_apologize"
                        type="button"
                        :disabled="busy"
                        class="inline-flex items-center gap-1.5 rounded-full border border-pink-300/50 bg-pink-400/15 px-3 py-1 text-[13px] text-pink-100 transition hover:bg-pink-400/25"
                        @click="talk('apology')"
                    >
                        <HandHeart class="h-3.5 w-3.5" /> Minta maaf
                    </button>
                    <button
                        v-for="(c, i) in npc.choices"
                        :key="c.label"
                        type="button"
                        :disabled="c.locked || busy"
                        class="inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-left text-[13px] transition enabled:hover:border-amber-200/60 enabled:hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="talk(i)"
                    >
                        <template v-if="c.locked"><Lock class="h-3 w-3 shrink-0" /> {{ c.level_label }} ·</template>
                        {{ c.label }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
