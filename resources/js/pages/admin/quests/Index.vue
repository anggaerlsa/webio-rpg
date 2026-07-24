<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';

interface Quest {
    id: number;
    slug: string;
    title: string;
    min_level: number;
    is_published: boolean;
    nodes_count: number;
}

defineProps<{ quests: Quest[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Misi', href: '/admin/quests' },
];

function hapus(quest: Quest) {
    if (confirm(`Hapus misi "${quest.title}"? Semua adegan & pilihannya ikut terhapus.`)) {
        router.delete(route('admin.quests.destroy', quest.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Kelola Misi" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl space-y-6 p-4">
            <AdminTabs />

            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold">Misi</h1>
                <Button @click="router.visit(route('admin.quests.create'))">
                    <Plus class="mr-2 h-4 w-4" /> Tambah Misi
                </Button>
            </div>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Judul</th>
                            <th class="px-4 py-3">Slug</th>
                            <th class="px-4 py-3">Lv. Min</th>
                            <th class="px-4 py-3">Adegan</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="q in quests" :key="q.id" class="border-t">
                            <td class="px-4 py-3 font-medium">{{ q.title }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ q.slug }}</td>
                            <td class="px-4 py-3">{{ q.min_level }}</td>
                            <td class="px-4 py-3">{{ q.nodes_count }}</td>
                            <td class="px-4 py-3">
                                <span v-if="q.is_published" class="rounded bg-emerald-500/15 px-2 py-0.5 text-xs text-emerald-600">Terbit</span>
                                <span v-else class="rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground">Draf</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button size="sm" variant="outline" @click="router.visit(route('admin.quests.edit', q.id))">
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button size="sm" variant="outline" class="text-red-600" @click="hapus(q)">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!quests.length">
                            <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                Belum ada misi. Klik "Tambah Misi" untuk membuat.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
