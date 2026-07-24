<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';

interface Item {
    id: number;
    slug: string;
    name: string;
    type: string;
    value: number;
}

defineProps<{ items: Item[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Items', href: '/admin/items' },
];

const typeLabels: Record<string, string> = {
    weapon: 'Senjata',
    armor: 'Zirah',
    consumable: 'Konsumsi',
    key: 'Kunci',
    misc: 'Lainnya',
};

function hapus(item: Item) {
    if (confirm(`Hapus item "${item.name}"? Tindakan ini tidak bisa dibatalkan.`)) {
        router.delete(route('admin.items.destroy', item.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Kelola Items" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl space-y-6 p-4">
            <AdminTabs />

            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold">Items</h1>
                <Button @click="router.visit(route('admin.items.create'))">
                    <Plus class="mr-2 h-4 w-4" /> Tambah Item
                </Button>
            </div>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Slug</th>
                            <th class="px-4 py-3">Tipe</th>
                            <th class="px-4 py-3">Nilai</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items" :key="item.id" class="border-t">
                            <td class="px-4 py-3 font-medium">{{ item.name }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ item.slug }}</td>
                            <td class="px-4 py-3">{{ typeLabels[item.type] ?? item.type }}</td>
                            <td class="px-4 py-3">{{ item.value }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button size="sm" variant="outline" @click="router.visit(route('admin.items.edit', item.id))">
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button size="sm" variant="outline" class="text-red-600" @click="hapus(item)">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!items.length">
                            <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">
                                Belum ada item. Klik "Tambah Item" untuk membuat.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
