<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Droplets, Hammer, Heart, Landmark, Sparkles } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, type Component } from 'vue';

interface Deity {
    slug: string;
    name: string;
    title: string;
    domain: string | null;
    description: string | null;
    image: string | null;
    is_gm: boolean;
    blessing: string | null;
    needs_gender: boolean;
}

const props = defineProps<{
    deities: Deity[];
    active_blessing: { deity: string | null; label: string; until: string } | null;
    cooldown_until: string | null;
    has_gender: boolean;
}>();

const page = usePage();
const flashSuccess = computed(() => (page.props as Record<string, any>).flash?.success as string | undefined);
const flashError = computed(() => (page.props as Record<string, any>).flash?.error as string | undefined);

// Jam berjalan untuk hitung mundur berkah & cooldown.
const now = ref(Date.now());
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => (timer = setInterval(() => (now.value = Date.now()), 30_000)));
onUnmounted(() => clearInterval(timer));

function remaining(iso: string | null): string | null {
    if (!iso) return null;
    const ms = new Date(iso).getTime() - now.value;
    if (ms <= 0) return null;
    const total = Math.ceil(ms / 60_000);
    const [h, m] = [Math.floor(total / 60), total % 60];
    return [h && `${h} jam`, m && `${m} menit`].filter(Boolean).join(' ');
}

const blessingLeft = computed(() => remaining(props.active_blessing?.until ?? null));
const cooldownLeft = computed(() => remaining(props.cooldown_until));

// Lambang untuk dewa tanpa potret, ditebak dari ranahnya.
function emblem(d: Deity): Component {
    const domain = (d.domain ?? '').toLowerCase();
    if (domain.includes('cipta')) return Hammer;
    if (domain.includes('air') || domain.includes('hujan')) return Droplets;
    if (domain.includes('cinta')) return Heart;
    return Sparkles;
}

const praying = ref(false);
function pray(d: Deity) {
    router.post(
        route('temple.pray', d.slug),
        {},
        { preserveScroll: true, onStart: () => (praying.value = true), onFinish: () => (praying.value = false) },
    );
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Kuil', href: '/kuil' }];
</script>

<template>
    <Head title="Kuil Para Dewa" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-5xl space-y-6 p-4">
            <div class="rounded-xl border bg-[radial-gradient(ellipse_at_top,#3a2a14_0%,#140f08_70%)] p-6 text-amber-50">
                <div class="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-amber-200/70"><Landmark class="h-4 w-4" /> Kuil</div>
                <h1 class="mt-1 font-display text-3xl font-bold text-amber-100">Kuil Para Dewa</h1>
                <p class="mt-2 max-w-3xl font-serif text-amber-50/80">
                    Di bawah kubah marmer ini, para dewa yang menenun dunia mendengar doa para petualang. Sebagian datang sebagai penjaga hukum dunia;
                    sebagian lagi menurunkan berkah bagi yang berdoa dengan tulus.
                </p>
                <p class="mt-3 text-xs italic text-amber-200/70">Para dewa hanya mendengar satu doa setiap 24 jam.</p>
            </div>

            <div v-if="flashSuccess" class="rounded-lg border border-pink-500/40 bg-pink-500/10 px-4 py-2 font-serif text-pink-600">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-2 text-sm text-red-600">{{ flashError }}</div>

            <div v-if="active_blessing && blessingLeft" class="flex items-center gap-3 rounded-xl border border-pink-400/40 bg-pink-500/10 p-4">
                <Heart class="h-6 w-6 shrink-0 fill-pink-400 text-pink-400" />
                <div>
                    <div class="font-semibold">Berkah {{ active_blessing.deity }} sedang menyertaimu</div>
                    <div class="text-sm text-muted-foreground">{{ active_blessing.label }} · tersisa {{ blessingLeft }}</div>
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <article v-for="d in deities" :key="d.slug" class="flex flex-col overflow-hidden rounded-xl border bg-card">
                    <!-- Potret full body, ditampilkan setengah badan seperti adegan NPC. -->
                    <div class="relative aspect-[4/5] overflow-hidden bg-[radial-gradient(ellipse_at_top,#4a2a40_0%,#1c1118_60%,#0b070a_100%)]">
                        <img v-if="d.image" :src="d.image" :alt="d.name" class="absolute left-1/2 top-0 w-[170%] max-w-none -translate-x-1/2" />
                        <div v-else class="absolute inset-0 flex flex-col items-center justify-center gap-3 text-amber-100/80">
                            <component :is="emblem(d)" class="h-16 w-16" />
                            <span class="font-display text-5xl font-bold">{{ d.name.charAt(0) }}</span>
                        </div>
                        <div class="absolute left-3 top-3 flex flex-wrap gap-1">
                            <span
                                v-if="d.is_gm"
                                class="rounded bg-black/60 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-amber-200"
                                >Game Master</span
                            >
                            <span
                                v-if="d.blessing"
                                class="rounded bg-pink-500/80 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-white"
                                >Pemberi Berkah</span
                            >
                        </div>
                    </div>

                    <div class="flex flex-1 flex-col p-4">
                        <h2 class="font-display text-xl font-bold">{{ d.name }}</h2>
                        <div class="text-sm text-muted-foreground">
                            {{ d.title }}<template v-if="d.domain"> · {{ d.domain }}</template>
                        </div>
                        <p v-if="d.description" class="mt-2 flex-1 font-serif italic text-muted-foreground">“{{ d.description }}”</p>

                        <div class="mt-4">
                            <p v-if="!d.blessing" class="text-sm text-muted-foreground">Penjaga hukum dunia — tidak menurunkan berkah.</p>
                            <template v-else>
                                <p class="mb-2 text-sm"><span class="font-semibold">Berkah:</span> {{ d.blessing }}</p>
                                <p v-if="d.needs_gender && !has_gender" class="text-sm text-red-500">
                                    {{ d.name }} menatapmu lembut, tapi berkahnya belum bisa menyentuhmu. (Karaktermu belum memiliki jenis kelamin.)
                                </p>
                                <Button v-else-if="cooldownLeft" disabled class="w-full">Bisa berdoa lagi dalam {{ cooldownLeft }}</Button>
                                <Button v-else class="w-full bg-pink-600 hover:bg-pink-700" :disabled="praying" @click="pray(d)">
                                    <Sparkles class="mr-2 h-4 w-4" /> Berdoa kepada {{ d.name }}
                                </Button>
                            </template>
                        </div>
                    </div>
                </article>
            </div>

            <p v-if="!deities.length" class="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
                Kuil ini masih sepi. Para dewa belum turun.
            </p>
        </div>
    </AppLayout>
</template>
