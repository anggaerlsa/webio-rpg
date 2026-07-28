<script setup lang="ts">
import Pagination from '@/components/game/Pagination.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { waktuLalu } from '@/lib/waktu';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Heart, Lock, LockOpen, Pencil, Pin, Quote, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Post {
    id: number;
    user_id: number;
    name: string;
    job: string | null;
    rank: string | null;
    level: number | null;
    reputation: number;
    body: string;
    is_first: boolean;
    at: string | null;
    edited_at: string | null;
    appreciations: number;
    appreciated: boolean;
    can_appreciate: boolean;
    can_edit: boolean;
    can_delete: boolean;
    reply_to: { id: number; name: string; excerpt: string } | null;
}
interface Paginator {
    data: Post[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    topic: {
        slug: string;
        title: string;
        is_pinned: boolean;
        is_locked: boolean;
        author: string | null;
        created_at: string | null;
        views: number;
        replies: number;
        can_delete: boolean;
    };
    category: { slug: string; name: string };
    posts: Paginator;
    block_reason: string | null;
    is_moderator: boolean;
    edit_window: number;
}>();

const page = usePage();
const flashSuccess = computed(() => (page.props as Record<string, any>).flash?.success as string | undefined);
const flashError = computed(() => (page.props as Record<string, any>).flash?.error as string | undefined);

// Job pemain tersimpan sebagai kunci (adventurer/merchant); gelar Dewa ditulis bebas.
const jobLabels: Record<string, string> = { adventurer: 'Petualang', merchant: 'Pedagang', Commoner: 'Commoner' };

const balas = useForm({ body: '', reply_to_id: null as number | null });
const ubah = useForm({ body: '' });
const sedangUbah = ref<number | null>(null);

const dikutip = computed(() => props.posts.data.find((p) => p.id === balas.reply_to_id) ?? null);

function kirimBalasan() {
    balas.post(route('forum.reply', props.topic.slug), {
        onSuccess: () => balas.reset(),
    });
}

function kutip(post: Post) {
    balas.reply_to_id = post.id;
    document.getElementById('kotak-balasan')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function mulaiUbah(post: Post) {
    sedangUbah.value = post.id;
    ubah.body = post.body;
}

function simpanUbah(post: Post) {
    ubah.put(route('forum.post.update', post.id), {
        preserveScroll: true,
        onSuccess: () => {
            sedangUbah.value = null;
            ubah.reset();
        },
    });
}

function apresiasi(post: Post) {
    router.post(route('forum.post.appreciate', post.id), {}, { preserveScroll: true });
}

function hapusPesan(post: Post) {
    if (confirm('Hapus pesan ini?')) {
        router.delete(route('forum.post.destroy', post.id), { preserveScroll: true });
    }
}

function hapusTopik() {
    if (confirm(`Hapus topik "${props.topic.title}" beserta seluruh pesannya?`)) {
        router.delete(route('forum.topic.destroy', props.topic.slug));
    }
}

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Balai Warta', href: '/forum' },
    { title: props.category.name, href: route('forum.category', props.category.slug) },
    { title: props.topic.title, href: '#' },
]);

const fieldClass = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring';
</script>

<template>
    <Head :title="topic.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl space-y-4 p-4">
            <div v-if="flashSuccess" class="rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-600">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-2 text-sm text-red-600">
                {{ flashError }}
            </div>

            <!-- Kepala topik -->
            <div class="rounded-xl border bg-card p-5">
                <h1 class="font-display flex flex-wrap items-center gap-2 text-xl font-bold">
                    <Pin v-if="topic.is_pinned" class="h-4 w-4 text-amber-500" />
                    <Lock v-if="topic.is_locked" class="h-4 w-4 text-muted-foreground" />
                    {{ topic.title }}
                </h1>
                <div class="mt-1 text-xs text-muted-foreground">
                    dibuka {{ topic.author ?? 'seseorang' }} · {{ waktuLalu(topic.created_at) }} · {{ topic.replies }} balasan ·
                    {{ topic.views }} dilihat
                </div>

                <div v-if="is_moderator" class="mt-4 flex flex-wrap gap-2 border-t pt-3">
                    <Button size="sm" variant="outline" @click="router.post(route('forum.topic.pin', topic.slug), {}, { preserveScroll: true })">
                        <Pin class="mr-1.5 h-3.5 w-3.5" /> {{ topic.is_pinned ? 'Lepas sematan' : 'Sematkan' }}
                    </Button>
                    <Button size="sm" variant="outline" @click="router.post(route('forum.topic.lock', topic.slug), {}, { preserveScroll: true })">
                        <component :is="topic.is_locked ? LockOpen : Lock" class="mr-1.5 h-3.5 w-3.5" />
                        {{ topic.is_locked ? 'Buka kunci' : 'Kunci' }}
                    </Button>
                    <Button size="sm" variant="outline" class="text-red-600" @click="hapusTopik">
                        <Trash2 class="mr-1.5 h-3.5 w-3.5" /> Hapus topik
                    </Button>
                </div>
            </div>

            <!-- Pesan -->
            <div v-for="post in posts.data" :key="post.id" class="rounded-xl border bg-card p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-display font-semibold">{{ post.name }}</span>
                        <span v-if="post.rank" class="rounded-full border border-amber-500/40 bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold text-amber-600">
                            Rank {{ post.rank }}
                        </span>
                        <span v-if="post.level" class="rounded-full border px-2 py-0.5 text-[10px] text-muted-foreground">Lv {{ post.level }}</span>
                        <span v-if="post.job" class="text-xs italic text-muted-foreground">{{ jobLabels[post.job] ?? post.job }}</span>
                        <span v-if="post.reputation" class="inline-flex items-center gap-1 text-[10px] text-muted-foreground">
                            <Heart class="h-3 w-3" /> {{ post.reputation }} reputasi
                        </span>
                    </div>
                    <div class="text-xs text-muted-foreground">
                        {{ waktuLalu(post.at) }}
                        <span v-if="post.edited_at">· diubah</span>
                    </div>
                </div>

                <!-- Kutipan -->
                <div v-if="post.reply_to" class="mt-3 rounded-lg border-l-2 border-primary/50 bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                    <span class="font-medium text-foreground">{{ post.reply_to.name }}</span> menulis:
                    <span class="font-serif italic">{{ post.reply_to.excerpt }}</span>
                </div>

                <!-- Isi / form ubah -->
                <div v-if="sedangUbah === post.id" class="mt-3 space-y-2">
                    <textarea v-model="ubah.body" rows="6" :class="[fieldClass, 'font-serif']"></textarea>
                    <InputError :message="ubah.errors.body" />
                    <div class="flex gap-2">
                        <Button size="sm" :disabled="ubah.processing" @click="simpanUbah(post)">Simpan</Button>
                        <Button size="sm" variant="outline" @click="sedangUbah = null">Batal</Button>
                    </div>
                </div>
                <p v-else class="font-serif mt-3 whitespace-pre-wrap text-sm leading-relaxed">{{ post.body }}</p>

                <!-- Aksi -->
                <div class="mt-4 flex flex-wrap items-center gap-2 border-t pt-3">
                    <Button
                        size="sm"
                        :variant="post.appreciated ? 'default' : 'outline'"
                        :disabled="!post.can_appreciate"
                        :title="post.can_appreciate ? 'Beri apresiasi' : 'Tidak bisa mengapresiasi pesan sendiri'"
                        @click="apresiasi(post)"
                    >
                        <Heart class="mr-1.5 h-3.5 w-3.5" /> {{ post.appreciations }}
                    </Button>
                    <Button v-if="!block_reason" size="sm" variant="ghost" @click="kutip(post)">
                        <Quote class="mr-1.5 h-3.5 w-3.5" /> Kutip
                    </Button>
                    <Button v-if="post.can_edit" size="sm" variant="ghost" @click="mulaiUbah(post)">
                        <Pencil class="mr-1.5 h-3.5 w-3.5" /> Ubah
                    </Button>
                    <Button v-if="post.can_delete" size="sm" variant="ghost" class="text-red-600" @click="hapusPesan(post)">
                        <Trash2 class="mr-1.5 h-3.5 w-3.5" /> Hapus
                    </Button>
                </div>
            </div>

            <Pagination
                :current="posts.current_page"
                :last="posts.last_page"
                :prev="posts.prev_page_url"
                :next="posts.next_page_url"
            />

            <!-- Kotak balasan -->
            <div id="kotak-balasan" class="rounded-xl border bg-card p-5">
                <template v-if="!block_reason">
                    <h2 class="font-display text-sm font-semibold">Tulis balasan</h2>
                    <div v-if="dikutip" class="mt-2 flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2 text-xs">
                        <span class="truncate">Membalas <b>{{ dikutip.name }}</b>: <i class="font-serif">{{ dikutip.body.slice(0, 80) }}</i></span>
                        <button type="button" class="ml-2 shrink-0 text-muted-foreground hover:text-foreground" @click="balas.reply_to_id = null">
                            batal kutip
                        </button>
                    </div>
                    <form class="mt-3 space-y-3" @submit.prevent="kirimBalasan">
                        <textarea
                            v-model="balas.body"
                            rows="5"
                            required
                            maxlength="5000"
                            :class="[fieldClass, 'font-serif']"
                            placeholder="Sampaikan pendapatmu..."
                        ></textarea>
                        <InputError :message="balas.errors.body" />
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-muted-foreground">
                                Pesan bisa diubah dalam {{ edit_window }} menit pertama.
                            </span>
                            <Button type="submit" :disabled="balas.processing">Kirim</Button>
                        </div>
                    </form>
                </template>
                <p v-else class="text-sm text-muted-foreground">{{ block_reason }}</p>
            </div>
        </div>
    </AppLayout>
</template>
