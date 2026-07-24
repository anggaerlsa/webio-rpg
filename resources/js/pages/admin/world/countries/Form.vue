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
    slug: string;
    name: string;
    government_type: string | null;
    ideology: string | null;
    ruler_title: string | null;
    ruler_name: string | null;
    dominant_race: string | null;
    description: string | null;
    image: string | null;
}

const props = defineProps<{ country: Country | null }>();

const isEdit = !!props.country;

const form = useForm({
    name: props.country?.name ?? '',
    slug: props.country?.slug ?? '',
    government_type: props.country?.government_type ?? '',
    ideology: props.country?.ideology ?? '',
    ruler_title: props.country?.ruler_title ?? '',
    ruler_name: props.country?.ruler_name ?? '',
    dominant_race: props.country?.dominant_race ?? '',
    description: props.country?.description ?? '',
    image: props.country?.image ?? '',
    capital_name: '', // hanya dipakai saat membuat negara baru (otomatis bikin ibukota)
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
    if (isEdit) form.put(route('admin.world.countries.update', props.country!.id));
    else form.post(route('admin.world.countries.store'));
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Dunia', href: route('admin.world.countries.index') },
    { title: isEdit ? `Ubah ${props.country!.name}` : 'Negara Baru', href: '#' },
];

const fieldClass = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring';
</script>

<template>
    <Head :title="isEdit ? 'Ubah Negara' : 'Tambah Negara'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <AdminTabs />

            <div class="rounded-xl border bg-card p-6">
                <h1 class="text-xl font-bold">{{ isEdit ? 'Ubah Negara' : 'Tambah Negara' }}</h1>

                <form class="mt-5 flex flex-col gap-5" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="name">Nama Negara</Label>
                        <Input id="name" v-model="form.name" required placeholder="mis. Kerajaan Astoria" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input id="slug" v-model="form.slug" required placeholder="kerajaan-astoria" />
                        <p class="text-xs text-muted-foreground">Huruf kecil, angka, dan tanda hubung. Kunci unik untuk referensi game.</p>
                        <InputError :message="form.errors.slug" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="government_type">Jenis</Label>
                            <Input id="government_type" v-model="form.government_type" placeholder="mis. Kekaisaran" />
                            <InputError :message="form.errors.government_type" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="ideology">Nasionalitas</Label>
                            <Input id="ideology" v-model="form.ideology" placeholder="mis. Imperialis" />
                            <InputError :message="form.errors.ideology" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="ruler_title">Gelar Penguasa</Label>
                            <Input id="ruler_title" v-model="form.ruler_title" placeholder="mis. Kaisar" />
                            <InputError :message="form.errors.ruler_title" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="ruler_name">Nama Penguasa</Label>
                            <Input id="ruler_name" v-model="form.ruler_name" placeholder="mis. Edwin Astoria XII" />
                            <InputError :message="form.errors.ruler_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="dominant_race">Ras Dominan</Label>
                            <Input id="dominant_race" v-model="form.dominant_race" placeholder="mis. Manusia" />
                            <InputError :message="form.errors.dominant_race" />
                        </div>
                    </div>

                    <div v-if="!isEdit" class="grid gap-2 rounded-lg border border-dashed bg-muted/30 p-4">
                        <Label for="capital_name">Nama Ibukota</Label>
                        <Input id="capital_name" v-model="form.capital_name" :placeholder="form.name || 'mis. Kota Eldoria'" />
                        <p class="text-xs text-muted-foreground">
                            Tiap negara langsung dibuatkan satu kota ibukota. Kosongkan untuk memakai nama negara — bisa diubah nanti.
                        </p>
                        <InputError :message="form.errors.capital_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Deskripsi (opsional)</Label>
                        <textarea id="description" v-model="form.description" rows="3" :class="fieldClass" placeholder="Negeri makmur di utara benua..."></textarea>
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="image">URL Gambar (opsional)</Label>
                        <Input id="image" v-model="form.image" placeholder="/images/world/astoria.png" />
                        <InputError :message="form.errors.image" />
                    </div>

                    <div class="flex gap-3">
                        <Button type="submit" :disabled="form.processing">{{ isEdit ? 'Simpan Perubahan' : 'Buat Negara' }}</Button>
                        <Button type="button" variant="outline" @click="router.visit(isEdit ? route('admin.world.countries.show', country!.id) : route('admin.world.countries.index'))">Batal</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
