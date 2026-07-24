<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head as InertiaHead, router as inertiaRouter, useForm as inertiaUseForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { watch } from 'vue';

interface NodeLite {
    id: number;
    key: string;
    type: string;
    title: string | null;
}
interface Quest {
    id: number;
    slug: string;
    title: string;
    description: string | null;
    affiliation: string | null;
    required_rank: string | null;
    cover_image: string | null;
    min_level: number;
    order: number;
    is_published: boolean;
    start_node_id: number | null;
}

const RANKS = ['F', 'E', 'D', 'C', 'B', 'A', 'S'];

const props = defineProps<{ quest: Quest | null; nodes: NodeLite[] }>();

const isEdit = !!props.quest;
const nodeTypeLabels: Record<string, string> = {
    narrative: 'Narasi',
    choice: 'Pilihan',
    combat: 'Pertarungan',
    reward: 'Hadiah',
    ending: 'Akhir',
};

const initialStartKey = props.nodes.find((n) => n.id === props.quest?.start_node_id)?.key ?? '';

const form = inertiaUseForm({
    slug: props.quest?.slug ?? '',
    title: props.quest?.title ?? '',
    description: props.quest?.description ?? '',
    affiliation: props.quest?.affiliation ?? '',
    required_rank: props.quest?.required_rank ?? '',
    cover_image: props.quest?.cover_image ?? '',
    min_level: props.quest?.min_level ?? 1,
    order: props.quest?.order ?? 0,
    is_published: props.quest?.is_published ?? true,
    start_node: initialStartKey,
});

if (!isEdit) {
    watch(
        () => form.title,
        (t) => {
            form.slug = (t || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        },
    );
}

function submit() {
    if (isEdit) form.put(route('admin.quests.update', props.quest!.id));
    else form.post(route('admin.quests.store'));
}

function hapusNode(node: NodeLite) {
    if (confirm(`Hapus adegan "${node.key}"?`)) {
        inertiaRouter.delete(route('admin.quests.nodes.destroy', [props.quest!.id, node.id]), { preserveScroll: true });
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Misi', href: '/admin/quests' },
    { title: isEdit ? 'Ubah' : 'Tambah', href: '#' },
];

const fieldClass = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring';
</script>

<template>
    <InertiaHead :title="isEdit ? 'Ubah Misi' : 'Tambah Misi'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <AdminTabs />

            <div class="rounded-xl border bg-card p-6">
                <h1 class="text-xl font-bold">{{ isEdit ? 'Ubah Misi' : 'Tambah Misi' }}</h1>

                <form class="mt-5 flex flex-col gap-5" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="title">Judul</Label>
                        <Input id="title" v-model="form.title" required placeholder="mis. Gua Goblin" />
                        <InputError :message="form.errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input id="slug" v-model="form.slug" required placeholder="gua-goblin" />
                        <InputError :message="form.errors.slug" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Deskripsi (opsional)</Label>
                        <textarea id="description" v-model="form.description" rows="2" :class="fieldClass"></textarea>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="affiliation">Guild Penyelenggara</Label>
                            <select id="affiliation" v-model="form.affiliation" :class="fieldClass">
                                <option value="">— bukan misi guild —</option>
                                <option value="adventurer">Guild Petualang</option>
                                <option value="merchant">Guild Merchant</option>
                            </select>
                            <p class="text-xs text-muted-foreground">Misi muncul di papan guild ini bagi pemain.</p>
                            <InputError :message="form.errors.affiliation" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="required_rank">Rank Minimal</Label>
                            <select id="required_rank" v-model="form.required_rank" :class="fieldClass">
                                <option value="">— tanpa syarat (F) —</option>
                                <option v-for="r in RANKS" :key="r" :value="r">Rank {{ r }}</option>
                            </select>
                            <InputError :message="form.errors.required_rank" />
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="min_level">Level Minimum</Label>
                            <Input id="min_level" v-model.number="form.min_level" type="number" min="1" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="order">Urutan</Label>
                            <Input id="order" v-model.number="form.order" type="number" min="0" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="cover_image">URL Sampul (opsional)</Label>
                        <Input id="cover_image" v-model="form.cover_image" placeholder="/images/quests/gua.png" />
                    </div>

                    <div v-if="isEdit && nodes.length" class="grid gap-2">
                        <Label for="start_node">Adegan Awal</Label>
                        <select id="start_node" v-model="form.start_node" :class="fieldClass">
                            <option value="">— pilih —</option>
                            <option v-for="n in nodes" :key="n.id" :value="n.key">{{ n.key }} ({{ nodeTypeLabels[n.type] ?? n.type }})</option>
                        </select>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.is_published" type="checkbox" class="h-4 w-4 accent-primary" />
                        Terbitkan (tampil di daftar misi pemain)
                    </label>

                    <div class="flex gap-3">
                        <Button type="submit" :disabled="form.processing">{{ isEdit ? 'Simpan Perubahan' : 'Buat & Lanjut' }}</Button>
                        <Button type="button" variant="outline" @click="inertiaRouter.visit(route('admin.quests.index'))">Batal</Button>
                    </div>
                </form>
            </div>

            <!-- Daftar adegan (hanya saat edit) -->
            <div v-if="isEdit" class="rounded-xl border bg-card p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Adegan (Node)</h2>
                        <p class="text-sm text-muted-foreground">Susun alur cerita: narasi, pilihan, pertarungan, hadiah, akhir.</p>
                    </div>
                    <Button type="button" variant="outline" size="sm" @click="inertiaRouter.visit(route('admin.quests.nodes.create', quest!.id))">
                        <Plus class="mr-1 h-4 w-4" /> Adegan
                    </Button>
                </div>

                <div v-if="!nodes.length" class="mt-4 rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                    Belum ada adegan. Tambahkan adegan pertama, lalu set sebagai "Adegan Awal" di atas.
                </div>

                <div v-else class="mt-4 divide-y rounded-lg border">
                    <div v-for="n in nodes" :key="n.id" class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-sm font-medium">{{ n.key }}</span>
                                <span class="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">{{ nodeTypeLabels[n.type] ?? n.type }}</span>
                                <span v-if="n.id === quest!.start_node_id" class="rounded bg-amber-500/15 px-1.5 py-0.5 text-xs text-amber-600">awal</span>
                            </div>
                            <div v-if="n.title" class="truncate text-sm text-muted-foreground">{{ n.title }}</div>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <Button size="sm" variant="outline" @click="inertiaRouter.visit(route('admin.quests.nodes.edit', [quest!.id, n.id]))">
                                <Pencil class="h-4 w-4" />
                            </Button>
                            <Button size="sm" variant="outline" class="text-red-600" @click="hapusNode(n)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
