<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';

interface Monster {
    id: number;
    slug: string;
    name: string;
    max_hp: number;
    attack: number;
    xp_reward: number;
}

defineProps<{ monsters: Monster[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Monster', href: '/admin/monsters' },
];

function hapus(monster: Monster) {
    if (confirm(`Hapus monster "${monster.name}"? Pertanyaan combat-nya ikut terhapus.`)) {
        router.delete(route('admin.monsters.destroy', monster.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Kelola Monster" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl space-y-6 p-4">
            <AdminTabs />

            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold">Monster</h1>
                <Button @click="router.visit(route('admin.monsters.create'))">
                    <Plus class="mr-2 h-4 w-4" /> Tambah Monster
                </Button>
            </div>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Slug</th>
                            <th class="px-4 py-3">HP</th>
                            <th class="px-4 py-3">Serangan</th>
                            <th class="px-4 py-3">XP</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in monsters" :key="m.id" class="border-t">
                            <td class="px-4 py-3 font-medium">{{ m.name }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ m.slug }}</td>
                            <td class="px-4 py-3">{{ m.max_hp }}</td>
                            <td class="px-4 py-3">{{ m.attack }}</td>
                            <td class="px-4 py-3">{{ m.xp_reward }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button size="sm" variant="outline" @click="router.visit(route('admin.monsters.edit', m.id))">
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button size="sm" variant="outline" class="text-red-600" @click="hapus(m)">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!monsters.length">
                            <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                Belum ada monster. Klik "Tambah Monster" untuk membuat.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
