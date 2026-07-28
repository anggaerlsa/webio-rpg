<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Lock, Pencil, Plus, Trash2 } from 'lucide-vue-next';

interface Category {
    id: number;
    slug: string;
    name: string;
    position: number;
    is_locked: boolean;
    min_rank: string | null;
    topics_count: number;
}

defineProps<{ categories: Category[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Forum', href: '/admin/forum-categories' },
];

function hapus(category: Category) {
    if (confirm(`Hapus kategori "${category.name}"? Semua topik & pesan di dalamnya ikut terhapus.`)) {
        router.delete(route('admin.forum-categories.destroy', category.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Kelola Forum" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl space-y-6 p-4">
            <AdminTabs />

            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold">Kategori Balai Warta</h1>
                <Button @click="router.visit(route('admin.forum-categories.create'))">
                    <Plus class="mr-2 h-4 w-4" /> Tambah Kategori
                </Button>
            </div>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Urutan</th>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Slug</th>
                            <th class="px-4 py-3">Topik</th>
                            <th class="px-4 py-3">Batasan</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in categories" :key="c.id" class="border-t">
                            <td class="px-4 py-3 text-muted-foreground">{{ c.position }}</td>
                            <td class="px-4 py-3 font-medium">{{ c.name }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ c.slug }}</td>
                            <td class="px-4 py-3">{{ c.topics_count }}</td>
                            <td class="px-4 py-3">
                                <span v-if="c.is_locked" class="inline-flex items-center gap-1 text-xs text-amber-600">
                                    <Lock class="h-3 w-3" /> Hanya Dewa
                                </span>
                                <span v-else-if="c.min_rank" class="text-xs text-muted-foreground">Rank {{ c.min_rank }}+</span>
                                <span v-else class="text-xs text-muted-foreground">Terbuka</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button size="sm" variant="outline" @click="router.visit(route('admin.forum-categories.edit', c.id))">
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button size="sm" variant="outline" class="text-red-600" @click="hapus(c)">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!categories.length">
                            <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                Belum ada kategori. Klik "Tambah Kategori" untuk membuat.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
