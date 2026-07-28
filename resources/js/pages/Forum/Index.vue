<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { waktuLalu } from '@/lib/waktu';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Lock, MessagesSquare, ScrollText, ShieldHalf } from 'lucide-vue-next';
import { computed } from 'vue';

interface Category {
    slug: string;
    name: string;
    description: string | null;
    is_locked: boolean;
    min_rank: string | null;
    topics: number;
    posts: number;
    can_post: boolean;
    latest: { title: string; slug: string; at: string | null; by: string | null } | null;
}

defineProps<{ categories: Category[]; is_moderator: boolean }>();

const page = usePage();
const flashSuccess = computed(() => (page.props as Record<string, any>).flash?.success as string | undefined);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Balai Warta', href: '/forum' }];
</script>

<template>
    <Head title="Balai Warta" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl space-y-6 p-4">
            <div v-if="flashSuccess" class="rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-600">
                {{ flashSuccess }}
            </div>

            <!-- Kepala halaman -->
            <div class="rounded-xl border bg-card p-6">
                <h1 class="font-display flex items-center gap-2 text-2xl font-bold">
                    <ScrollText class="h-6 w-6 text-amber-500" /> Balai Warta
                </h1>
                <p class="font-serif mt-2 text-sm text-muted-foreground">
                    Papan kabar bersihir yang menghubungkan seluruh benua. Berbeda dengan bisik-bisik di kedai — apa yang tertulis
                    di sini tersimpan selamanya.
                </p>
            </div>

            <!-- Kategori -->
            <div class="space-y-3">
                <Link
                    v-for="c in categories"
                    :key="c.slug"
                    :href="route('forum.category', c.slug)"
                    class="block rounded-xl border bg-card p-5 transition hover:border-primary/60 hover:bg-accent/40"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-display text-lg font-semibold">{{ c.name }}</span>
                                <span
                                    v-if="c.is_locked"
                                    class="inline-flex items-center gap-1 rounded-full border border-amber-500/40 bg-amber-500/10 px-2 py-0.5 text-[10px] font-medium text-amber-600"
                                >
                                    <Lock class="h-3 w-3" /> Maklumat Dewa
                                </span>
                                <span
                                    v-if="c.min_rank"
                                    class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                                >
                                    <ShieldHalf class="h-3 w-3" /> Rank {{ c.min_rank }}+
                                </span>
                            </div>
                            <p v-if="c.description" class="font-serif mt-1 text-sm text-muted-foreground">{{ c.description }}</p>
                        </div>

                        <div class="flex shrink-0 items-center gap-4 text-xs text-muted-foreground">
                            <div class="text-center">
                                <div class="text-base font-semibold text-foreground">{{ c.topics }}</div>
                                topik
                            </div>
                            <div class="text-center">
                                <div class="text-base font-semibold text-foreground">{{ c.posts }}</div>
                                pesan
                            </div>
                        </div>
                    </div>

                    <div v-if="c.latest" class="mt-3 flex items-center gap-2 border-t pt-3 text-xs text-muted-foreground">
                        <MessagesSquare class="h-3.5 w-3.5" />
                        <span class="truncate font-medium text-foreground">{{ c.latest.title }}</span>
                        <span class="shrink-0">· {{ c.latest.by ?? 'seseorang' }} · {{ waktuLalu(c.latest.at) }}</span>
                    </div>
                    <div v-else class="mt-3 border-t pt-3 text-xs italic text-muted-foreground">Belum ada topik di sini.</div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
