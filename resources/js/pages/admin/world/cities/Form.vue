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
    slug: string;
    name: string;
    description: string | null;
    image: string | null;
}

const props = defineProps<{ city: City | null; province: Province }>();

const isEdit = !!props.city;

const form = useForm({
    name: props.city?.name ?? '',
    slug: props.city?.slug ?? '',
    description: props.city?.description ?? '',
    image: props.city?.image ?? '',
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
    if (isEdit) form.put(route('admin.world.cities.update', props.city!.id));
    else form.post(route('admin.world.provinces.cities.store', props.province.id));
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Dunia', href: route('admin.world.countries.index') },
    { title: props.province.country.name, href: route('admin.world.countries.show', props.province.country.id) },
    { title: props.province.name, href: route('admin.world.provinces.show', props.province.id) },
    { title: isEdit ? `Ubah ${props.city!.name}` : 'Kota Baru', href: '#' },
];

const fieldClass = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring';
</script>

<template>
    <Head :title="isEdit ? 'Ubah Kota' : 'Tambah Kota'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <AdminTabs />

            <div class="rounded-xl border bg-card p-6">
                <h1 class="text-xl font-bold">{{ isEdit ? 'Ubah Kota' : 'Tambah Kota' }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Lokasi: <span class="font-medium text-foreground">{{ province.country.name }} / {{ province.name }}</span>
                </p>

                <form class="mt-5 flex flex-col gap-5" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="name">Nama Kota</Label>
                        <Input id="name" v-model="form.name" required placeholder="mis. Kota Eldoria" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input id="slug" v-model="form.slug" required placeholder="kota-eldoria" />
                        <p class="text-xs text-muted-foreground">Huruf kecil, angka, dan tanda hubung. Unik antar kota.</p>
                        <InputError :message="form.errors.slug" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Deskripsi (opsional)</Label>
                        <textarea id="description" v-model="form.description" rows="3" :class="fieldClass" placeholder="Kota dagang ramai dengan pelabuhan besar..."></textarea>
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="image">URL Gambar (opsional)</Label>
                        <Input id="image" v-model="form.image" placeholder="/images/world/eldoria.png" />
                        <InputError :message="form.errors.image" />
                    </div>

                    <div class="flex gap-3">
                        <Button type="submit" :disabled="form.processing">{{ isEdit ? 'Simpan Perubahan' : 'Buat Kota' }}</Button>
                        <Button type="button" variant="outline" @click="router.visit(isEdit ? route('admin.world.cities.show', city!.id) : route('admin.world.provinces.show', province.id))">Batal</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
