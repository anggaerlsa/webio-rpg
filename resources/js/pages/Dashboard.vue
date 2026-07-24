<script setup lang="ts">
import CharacterHud from '@/components/game/CharacterHud.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { CharacterState } from '@/types/game';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Castle, Coins, Crown, Map, Scroll, Swords, UserPlus, UserRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineProps<{
    character: CharacterState | null;
    resume: { quest_slug: string; quest_title: string; node_key: string; last_played_at: string | null } | null;
    needsOnboarding: boolean;
}>();

const page = usePage();
const isAdmin = computed(() => (page.props as Record<string, any>).auth?.user?.role === 'superadmin');

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Beranda', href: '/dashboard' }];

const choosing = ref(false);
function chooseAffiliation(affiliation: 'adventurer' | 'merchant') {
    if (choosing.value) return;
    choosing.value = true;
    router.post(route('character.affiliation'), { affiliation }, { onFinish: () => (choosing.value = false) });
}
</script>

<template>
    <Head title="Beranda" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl space-y-6 p-4">
            <!-- Belum punya karakter -->
            <div v-if="!character" class="rounded-xl border bg-card p-8 text-center">
                <Swords class="mx-auto mb-3 h-12 w-12 text-primary" />
                <h1 class="text-2xl font-bold">Selamat datang, petualang</h1>
                <p class="mt-2 text-muted-foreground">Buat pahlawanmu untuk memulai petualangan.</p>
                <Button class="mt-4" @click="router.visit(route('character.create'))">
                    <UserPlus class="mr-2 h-4 w-4" /> Buat pahlawanmu
                </Button>
            </div>

            <template v-else>
                <CharacterHud :character="character" />

                <div v-if="resume" class="rounded-xl border bg-card p-5">
                    <div class="flex items-center gap-2 text-sm text-muted-foreground"><Scroll class="h-4 w-4" /> Lanjutkan petualanganmu</div>
                    <div class="mt-1 text-lg font-semibold">{{ resume.quest_title }}</div>
                    <div class="text-xs text-muted-foreground">Terakhir dimainkan {{ resume.last_played_at ?? 'baru saja' }}</div>
                    <Button class="mt-3" @click="router.visit(route('quests.play', resume.quest_slug))">Lanjutkan</Button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <Link :href="route('town.show')" class="rounded-xl border bg-card p-5 transition hover:bg-accent">
                        <Castle class="mb-2 h-6 w-6 text-primary" />
                        <div class="font-semibold">Kota</div>
                        <div class="text-sm text-muted-foreground">Istirahat, belanja, dan kunjungi guild.</div>
                    </Link>
                    <Link :href="route('quests.index')" class="rounded-xl border bg-card p-5 transition hover:bg-accent">
                        <Map class="mb-2 h-6 w-6 text-primary" />
                        <div class="font-semibold">Misi</div>
                        <div class="text-sm text-muted-foreground">Pilih dungeon untuk dijelajahi.</div>
                    </Link>
                    <Link :href="route('character.show')" class="rounded-xl border bg-card p-5 transition hover:bg-accent">
                        <UserRound class="mb-2 h-6 w-6 text-primary" />
                        <div class="font-semibold">Karakter</div>
                        <div class="text-sm text-muted-foreground">Lihat statistik dan inventaris.</div>
                    </Link>
                </div>
            </template>

            <!-- Akses superadmin -->
            <Link
                v-if="isAdmin"
                :href="route('admin.dashboard')"
                class="flex items-center gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-5 transition hover:bg-amber-500/20"
            >
                <Crown class="h-6 w-6 text-amber-500" />
                <div>
                    <div class="font-semibold">Panel Dewa</div>
                    <div class="text-sm text-muted-foreground">Kelola items, sihir, dan dunia game.</div>
                </div>
            </Link>
        </div>

        <!-- Onboarding: pilih afiliasi guild (wajib sebelum mulai) -->
        <div v-if="needsOnboarding" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
            <div class="w-full max-w-lg rounded-xl border bg-card p-6 shadow-xl">
                <h2 class="text-xl font-bold">Pilih Afiliasimu</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Sebagai Commoner, bergabunglah dengan sebuah guild untuk memulai. Pilihan ini menentukan job & rank awalmu.
                </p>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <button
                        type="button"
                        :disabled="choosing"
                        class="flex flex-col items-start gap-1 rounded-xl border p-4 text-left transition hover:border-primary hover:bg-primary/5 disabled:opacity-50"
                        @click="chooseAffiliation('adventurer')"
                    >
                        <Swords class="mb-1 h-6 w-6 text-amber-500" />
                        <div class="font-semibold">Guild Petualang</div>
                        <div class="text-xs text-muted-foreground">Jadi <b>adventurer</b>, mulai dari Rank F. Dapat Kartu Tanda Petualang.</div>
                    </button>
                    <button
                        type="button"
                        :disabled="choosing"
                        class="flex flex-col items-start gap-1 rounded-xl border p-4 text-left transition hover:border-primary hover:bg-primary/5 disabled:opacity-50"
                        @click="chooseAffiliation('merchant')"
                    >
                        <Coins class="mb-1 h-6 w-6 text-emerald-500" />
                        <div class="font-semibold">Guild Merchant</div>
                        <div class="text-xs text-muted-foreground">Jadi <b>merchant</b>, mulai dari Rank F. Dapat Kartu Tanda Dagang.</div>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
