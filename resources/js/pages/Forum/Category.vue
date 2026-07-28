<script setup lang="ts">
import Pagination from '@/components/game/Pagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { waktuLalu } from '@/lib/waktu';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, Lock, MessagesSquare, Pin, Plus } from 'lucide-vue-next';
import { computed } from 'vue';

interface Topic {
    slug: string;
    title: string;
    author: string;
    is_pinned: boolean;
    is_locked: boolean;
    replies: number;
    views: number;
    created_at: string | null;
    last_post_at: string | null;
    last_post_by: string | null;
}
interface Paginator {
    data: Topic[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    category: { slug: string; name: string; description: string | null; is_locked: boolean; min_rank: string | null };
    topics: Paginator;
    block_reason: string | null;
    is_moderator: boolean;
}>();

const page = usePage();
const flashSuccess = computed(() => (page.props as Record<string, any>).flash?.success as string | undefined);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Balai Warta', href: '/forum' },
    { title: props.category.name, href: route('forum.category', props.category.slug) },
]);
</script>

<template>
    <Head :title="category.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl space-y-5 p-4">
            <div v-if="flashSuccess" class="rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-600">
                {{ flashSuccess }}
            </div>

            <div class="rounded-xl border bg-card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 class="font-display text-xl font-bold">{{ category.name }}</h1>
                        <p v-if="category.description" class="font-serif mt-1 text-sm text-muted-foreground">{{ category.description }}</p>
                    </div>
                    <Button v-if="!block_reason" @click="router.visit(route('forum.topic.create', category.slug))">
                        <Plus class="mr-2 h-4 w-4" /> Buka Topik
                    </Button>
                </div>
                <p v-if="block_reason" class="mt-3 rounded-lg border border-dashed px-3 py-2 text-xs text-muted-foreground">
                    {{ block_reason }}
                </p>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card">
                <div
                    v-for="t in topics.data"
                    :key="t.slug"
                    class="flex items-center gap-3 border-b px-4 py-3 last:border-b-0 hover:bg-accent/40"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <Pin v-if="t.is_pinned" class="h-3.5 w-3.5 shrink-0 text-amber-500" />
                            <Lock v-if="t.is_locked" class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                            <Link :href="route('forum.topic', t.slug)" class="truncate font-medium hover:text-primary hover:underline">
                                {{ t.title }}
                            </Link>
                        </div>
                        <div class="mt-0.5 text-xs text-muted-foreground">
                            oleh {{ t.author }} · {{ waktuLalu(t.created_at) }}
                        </div>
                    </div>

                    <div class="hidden shrink-0 items-center gap-4 text-xs text-muted-foreground sm:flex">
                        <span class="inline-flex items-center gap-1"><MessagesSquare class="h-3.5 w-3.5" /> {{ t.replies }}</span>
                        <span class="inline-flex items-center gap-1"><Eye class="h-3.5 w-3.5" /> {{ t.views }}</span>
                    </div>

                    <div class="hidden w-36 shrink-0 text-right text-xs text-muted-foreground md:block">
                        <div class="truncate">{{ t.last_post_by ?? '—' }}</div>
                        <div>{{ waktuLalu(t.last_post_at) }}</div>
                    </div>
                </div>

                <div v-if="!topics.data.length" class="px-4 py-10 text-center text-sm text-muted-foreground">
                    Belum ada topik di kategori ini.
                </div>
            </div>

            <Pagination
                :current="topics.current_page"
                :last="topics.last_page"
                :prev="topics.prev_page_url"
                :next="topics.next_page_url"
            />
        </div>
    </AppLayout>
</template>
