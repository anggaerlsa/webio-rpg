<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';

interface Spell {
    id: number;
    slug: string;
    name: string;
    element: string;
    power: number;
    mana_cost: number;
    min_level: number;
}

defineProps<{ spells: Spell[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Sihir', href: '/admin/spells' },
];

const elementLabels: Record<string, string> = {
    api: 'Api',
    air: 'Air',
    angin: 'Angin',
    tanah: 'Tanah',
    cahaya: 'Cahaya',
    kegelapan: 'Kegelapan',
    arcane: 'Arkana',
};

function hapus(spell: Spell) {
    if (confirm(`Hapus sihir "${spell.name}"? Tindakan ini tidak bisa dibatalkan.`)) {
        router.delete(route('admin.spells.destroy', spell.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Kelola Sihir" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl space-y-6 p-4">
            <AdminTabs />

            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold">Sihir</h1>
                <Button @click="router.visit(route('admin.spells.create'))">
                    <Plus class="mr-2 h-4 w-4" /> Tambah Sihir
                </Button>
            </div>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Slug</th>
                            <th class="px-4 py-3">Elemen</th>
                            <th class="px-4 py-3">Power</th>
                            <th class="px-4 py-3">Mana</th>
                            <th class="px-4 py-3">Lv. Min</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="spell in spells" :key="spell.id" class="border-t">
                            <td class="px-4 py-3 font-medium">{{ spell.name }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ spell.slug }}</td>
                            <td class="px-4 py-3">{{ elementLabels[spell.element] ?? spell.element }}</td>
                            <td class="px-4 py-3">{{ spell.power }}</td>
                            <td class="px-4 py-3">{{ spell.mana_cost }}</td>
                            <td class="px-4 py-3">{{ spell.min_level }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button size="sm" variant="outline" @click="router.visit(route('admin.spells.edit', spell.id))">
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button size="sm" variant="outline" class="text-red-600" @click="hapus(spell)">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!spells.length">
                            <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">
                                Belum ada sihir. Klik "Tambah Sihir" untuk membuat.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
