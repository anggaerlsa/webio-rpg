<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

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
    name: string;
    province: Province;
}
interface Place {
    id: number;
    slug: string;
    name: string;
    category: string;
    description: string | null;
    image: string | null;
}

const props = defineProps<{ place: Place | null; city: City; categories: Record<string, string> }>();

const isEdit = !!props.place;

const form = useForm({
    name: props.place?.name ?? '',
    slug: props.place?.slug ?? '',
    category: props.place?.category ?? Object.keys(props.categories)[0],
    description: props.place?.description ?? '',
    image: props.place?.image ?? '',
});

if (!isEdit) {
    watch(
        () => form.name,
        (n) => {
            form.slug = (n || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        },
    );
}

function submit() {
    if (isEdit) form.put(route('admin.world.places.update', props.place!.id));
    else form.post(route('admin.world.cities.places.store', props.city.id));
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Dunia', href: route('admin.world.countries.index') },
    { title: props.city.province.country.name, href: route('admin.world.countries.show', props.city.province.country.id) },
    { title: props.city.province.name, href: route('admin.world.provinces.show', props.city.province.id) },
    { title: props.city.name, href: route('admin.world.cities.show', props.city.id) },
    { title: isEdit ? `Ubah ${props.place!.name}` : 'Tempat Baru', href: '#' },
];

const fieldClass = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring';
</script>

<template>
    <Head :title="isEdit ? 'Ubah Tempat' : 'Tambah Tempat'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <AdminTabs />

            <div class="rounded-xl border bg-card p-6">
                <h1 class="text-xl font-bold">{{ isEdit ? 'Ubah Tempat' : 'Tambah Tempat' }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">Kota: <span class="font-medium text-foreground">{{ city.name }}</span></p>

                <form class="mt-5 flex flex-col gap-5" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="category">Kategori</Label>
                        <select id="category" v-model="form.category" :class="fieldClass">
                            <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <p class="text-xs text-muted-foreground">Jenis fasilitas — menentukan fungsinya di game.</p>
                        <InputError :message="form.errors.category" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="name">Nama Tempat</Label>
                        <Input id="name" v-model="form.name" required placeholder='mis. "Rabbit Moon Inn"' />
                        <p class="text-xs text-muted-foreground">Nama bebas/custom untuk tempat ini.</p>
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input id="slug" v-model="form.slug" required placeholder="rabbit-moon-inn" />
                        <p class="text-xs text-muted-foreground">Huruf kecil, angka, dan tanda hubung. Unik antar tempat.</p>
                        <InputError :message="form.errors.slug" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Deskripsi (opsional)</Label>
                        <textarea id="description" v-model="form.description" rows="3" :class="fieldClass" placeholder="Penginapan hangat dengan tungku perapian besar..."></textarea>
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="image">URL Gambar (opsional)</Label>
                        <Input id="image" v-model="form.image" placeholder="/images/places/rabbit-moon-inn.png" />
                        <InputError :message="form.errors.image" />
                    </div>

                    <div class="flex gap-3">
                        <Button type="submit" :disabled="form.processing">{{ isEdit ? 'Simpan Perubahan' : 'Tambah Tempat' }}</Button>
                        <Button type="button" variant="outline" @click="router.visit(route('admin.world.cities.show', city.id))">Batal</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
