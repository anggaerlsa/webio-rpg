<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';

interface Skill {
    id: number;
    slug: string;
    name: string;
    type: string;
    power: number;
    level_req: number;
    is_default: boolean;
}

defineProps<{ skills: Skill[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Skill', href: '/admin/skills' },
];

const typeLabels: Record<string, string> = { physical: 'Fisik', magic: 'Sihir', ranged: 'Jarak jauh' };

function hapus(skill: Skill) {
    if (confirm(`Hapus skill "${skill.name}"?`)) {
        router.delete(route('admin.skills.destroy', skill.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Kelola Skill" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl space-y-6 p-4">
            <AdminTabs />

            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold">Skill</h1>
                <Button @click="router.visit(route('admin.skills.create'))">
                    <Plus class="mr-2 h-4 w-4" /> Tambah Skill
                </Button>
            </div>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Slug</th>
                            <th class="px-4 py-3">Tipe</th>
                            <th class="px-4 py-3">Power</th>
                            <th class="px-4 py-3">Lv. Min</th>
                            <th class="px-4 py-3">Default</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in skills" :key="s.id" class="border-t">
                            <td class="px-4 py-3 font-medium">{{ s.name }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ s.slug }}</td>
                            <td class="px-4 py-3">{{ typeLabels[s.type] ?? s.type }}</td>
                            <td class="px-4 py-3">{{ s.power }}</td>
                            <td class="px-4 py-3">{{ s.level_req }}</td>
                            <td class="px-4 py-3">
                                <span v-if="s.is_default" class="rounded bg-emerald-500/15 px-2 py-0.5 text-xs text-emerald-600">ya</span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button size="sm" variant="outline" @click="router.visit(route('admin.skills.edit', s.id))">
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button size="sm" variant="outline" class="text-red-600" @click="hapus(s)">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!skills.length">
                            <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">
                                Belum ada skill. Klik "Tambah Skill" untuk membuat.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
