<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Store, Tent, Trash2 } from 'lucide-vue-next';

interface Country {
    id: number;
    name: string;
}
interface Province {
    id: number;
    name: string;
    country: Country;
}
interface City {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    province: Province;
}
interface Village {
    id: number;
    slug: string;
    name: string;
}
interface Place {
    id: number;
    slug: string;
    name: string;
    category: string;
    description: string | null;
}

const props = defineProps<{
    city: City;
    villages: Village[];
    places: Place[];
    placeCategories: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Dunia', href: route('admin.world.countries.index') },
    { title: props.city.province.country.name, href: route('admin.world.countries.show', props.city.province.country.id) },
    { title: props.city.province.name, href: route('admin.world.provinces.show', props.city.province.id) },
    { title: props.city.name, href: route('admin.world.cities.show', props.city.id) },
];

function hapusTempat(place: Place) {
    if (confirm(`Hapus tempat "${place.name}"?`)) {
        router.delete(route('admin.world.places.destroy', place.id), { preserveScroll: true });
    }
}

function hapusDesa(village: Village) {
    if (confirm(`Hapus desa "${village.name}"?`)) {
        router.delete(route('admin.world.villages.destroy', village.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="`Kota — ${city.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl space-y-6 p-4">
            <AdminTabs />

            <div class="rounded-xl border bg-card p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">
                            Kota ·
                            <Link :href="route('admin.world.countries.show', city.province.country.id)" class="hover:underline">{{ city.province.country.name }}</Link>
                            /
                            <Link :href="route('admin.world.provinces.show', city.province.id)" class="hover:underline">{{ city.province.name }}</Link>
                        </p>
                        <h1 class="text-2xl font-bold">{{ city.name }}</h1>
                        <p class="mt-1 text-sm text-muted-foreground">{{ city.description || 'Tanpa deskripsi.' }}</p>
                    </div>
                    <Button variant="outline" size="sm" @click="router.visit(route('admin.world.cities.edit', city.id))">
                        <Pencil class="mr-2 h-4 w-4" /> Ubah Kota
                    </Button>
                </div>
            </div>

            <!-- Tempat: pusat aktivitas kota (guild, pasar, blacksmith, penginapan, dst.) -->
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-lg font-semibold"><Store class="h-5 w-5 text-primary" /> Tempat</h2>
                <Button @click="router.visit(route('admin.world.cities.places.create', city.id))">
                    <Plus class="mr-2 h-4 w-4" /> Tambah Tempat
                </Button>
            </div>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Kategori</th>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Slug</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="place in places" :key="place.id" class="border-t hover:bg-accent/40">
                            <td class="px-4 py-3">
                                <span class="inline-block rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">
                                    {{ placeCategories[place.category] ?? place.category }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-medium">{{ place.name }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ place.slug }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button size="sm" variant="outline" @click="router.visit(route('admin.world.places.edit', place.id))">
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button size="sm" variant="outline" class="text-red-600" @click="hapusTempat(place)">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!places.length">
                            <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">
                                Belum ada tempat di {{ city.name }}. Tambahkan Guild Petualang, Pasar, Penginapan, dll.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Desa: wilayah kecil di bawah kota -->
            <div class="flex items-center justify-between pt-2">
                <h2 class="flex items-center gap-2 text-lg font-semibold"><Tent class="h-5 w-5 text-primary" /> Desa</h2>
                <Button @click="router.visit(route('admin.world.cities.villages.create', city.id))">
                    <Plus class="mr-2 h-4 w-4" /> Tambah Desa
                </Button>
            </div>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Desa</th>
                            <th class="px-4 py-3">Slug</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="village in villages" :key="village.id" class="border-t hover:bg-accent/40">
                            <td class="px-4 py-3 font-medium">{{ village.name }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ village.slug }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button size="sm" variant="outline" @click="router.visit(route('admin.world.villages.edit', village.id))">
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button size="sm" variant="outline" class="text-red-600" @click="hapusDesa(village)">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!villages.length">
                            <td colspan="3" class="px-4 py-8 text-center text-muted-foreground">
                                Belum ada desa di {{ city.name }}. Klik "Tambah Desa".
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
