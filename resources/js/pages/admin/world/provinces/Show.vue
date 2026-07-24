<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Building2, ChevronRight, Pencil, Plus, Trash2 } from 'lucide-vue-next';

interface Country {
    id: number;
    name: string;
}
interface Province {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    country: Country;
}
interface City {
    id: number;
    slug: string;
    name: string;
    villages_count: number;
    places_count: number;
}

const props = defineProps<{ province: Province; cities: City[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Dunia', href: route('admin.world.countries.index') },
    { title: props.province.country.name, href: route('admin.world.countries.show', props.province.country.id) },
    { title: props.province.name, href: route('admin.world.provinces.show', props.province.id) },
];

function hapus(city: City) {
    if (confirm(`Hapus kota "${city.name}"? Seluruh desa & tempat di dalamnya ikut terhapus.`)) {
        router.delete(route('admin.world.cities.destroy', city.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="`Provinsi — ${province.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl space-y-6 p-4">
            <AdminTabs />

            <div class="rounded-xl border bg-card p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">
                            Provinsi · <Link :href="route('admin.world.countries.show', province.country.id)" class="hover:underline">{{ province.country.name }}</Link>
                        </p>
                        <h1 class="text-2xl font-bold">{{ province.name }}</h1>
                        <p class="mt-1 text-sm text-muted-foreground">{{ province.description || 'Tanpa deskripsi.' }}</p>
                    </div>
                    <Button variant="outline" size="sm" @click="router.visit(route('admin.world.provinces.edit', province.id))">
                        <Pencil class="mr-2 h-4 w-4" /> Ubah Provinsi
                    </Button>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-lg font-semibold"><Building2 class="h-5 w-5 text-primary" /> Kota</h2>
                <Button @click="router.visit(route('admin.world.provinces.cities.create', province.id))">
                    <Plus class="mr-2 h-4 w-4" /> Tambah Kota
                </Button>
            </div>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Kota</th>
                            <th class="px-4 py-3">Slug</th>
                            <th class="px-4 py-3">Tempat</th>
                            <th class="px-4 py-3">Desa</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="city in cities" :key="city.id" class="border-t hover:bg-accent/40">
                            <td class="px-4 py-3 font-medium">
                                <Link :href="route('admin.world.cities.show', city.id)" class="hover:underline">{{ city.name }}</Link>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ city.slug }}</td>
                            <td class="px-4 py-3">{{ city.places_count }}</td>
                            <td class="px-4 py-3">{{ city.villages_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button size="sm" @click="router.visit(route('admin.world.cities.show', city.id))">
                                        Kelola <ChevronRight class="ml-1 h-4 w-4" />
                                    </Button>
                                    <Button size="sm" variant="outline" @click="router.visit(route('admin.world.cities.edit', city.id))">
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button size="sm" variant="outline" class="text-red-600" @click="hapus(city)">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!cities.length">
                            <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">
                                Belum ada kota di {{ province.name }}. Klik "Tambah Kota".
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
