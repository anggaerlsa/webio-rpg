<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, Globe, Pencil, Plus, Trash2 } from 'lucide-vue-next';

interface Country {
    id: number;
    slug: string;
    name: string;
    government_type: string | null;
    ruler_title: string | null;
    ruler_name: string | null;
    dominant_race: string | null;
    capital: string | null;
}

defineProps<{ countries: Country[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Dunia', href: route('admin.world.countries.index') },
];

function hapus(country: Country) {
    if (confirm(`Hapus negara "${country.name}"? Seluruh provinsi, kota, desa, dan tempat di dalamnya ikut terhapus. Tindakan ini tidak bisa dibatalkan.`)) {
        router.delete(route('admin.world.countries.destroy', country.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Dunia — Negara" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl space-y-6 p-4">
            <AdminTabs />

            <div class="flex items-center justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-xl font-bold"><Globe class="h-5 w-5 text-primary" /> Dunia</h1>
                    <p class="text-sm text-muted-foreground">Negara → Provinsi → Kota → Desa. Mulai dari membuat sebuah negara.</p>
                </div>
                <Button @click="router.visit(route('admin.world.countries.create'))">
                    <Plus class="mr-2 h-4 w-4" /> Tambah Negara
                </Button>
            </div>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Negara</th>
                            <th class="px-4 py-3">Jenis</th>
                            <th class="px-4 py-3">Penguasa</th>
                            <th class="px-4 py-3">Ras</th>
                            <th class="px-4 py-3">Ibukota</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="country in countries" :key="country.id" class="border-t hover:bg-accent/40">
                            <td class="px-4 py-3 font-medium">
                                <Link :href="route('admin.world.countries.show', country.id)" class="hover:underline">{{ country.name }}</Link>
                            </td>
                            <td class="px-4 py-3">{{ country.government_type ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span v-if="country.ruler_name">{{ country.ruler_title ? country.ruler_title + ' ' : '' }}{{ country.ruler_name }}</span>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-3">{{ country.dominant_race ?? '—' }}</td>
                            <td class="px-4 py-3">{{ country.capital ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button size="sm" @click="router.visit(route('admin.world.countries.show', country.id))">
                                        Kelola <ChevronRight class="ml-1 h-4 w-4" />
                                    </Button>
                                    <Button size="sm" variant="outline" @click="router.visit(route('admin.world.countries.edit', country.id))">
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button size="sm" variant="outline" class="text-red-600" @click="hapus(country)">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!countries.length">
                            <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                Belum ada negara. Klik "Tambah Negara" untuk mulai membangun dunia.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
