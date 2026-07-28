<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

interface Category {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    position: number;
    is_locked: boolean;
    min_rank: string | null;
}

const props = defineProps<{ category: Category | null; ranks: string[] }>();

const isEdit = !!props.category;

const form = useForm({
    name: props.category?.name ?? '',
    slug: props.category?.slug ?? '',
    description: props.category?.description ?? '',
    position: props.category?.position ?? 0,
    is_locked: props.category?.is_locked ?? false,
    min_rank: props.category?.min_rank ?? '',
});

if (!isEdit) {
    watch(
        () => form.name,
        (n) => {
            form.slug = (n || '')
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        },
    );
}

function submit() {
    form.transform((data) => ({ ...data, min_rank: data.min_rank || null }));
    if (isEdit) form.put(route('admin.forum-categories.update', props.category!.id));
    else form.post(route('admin.forum-categories.store'));
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Forum', href: '/admin/forum-categories' },
    { title: isEdit ? 'Ubah' : 'Tambah', href: '#' },
];

const fieldClass = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring';
</script>

<template>
    <Head :title="isEdit ? 'Ubah Kategori' : 'Tambah Kategori'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <AdminTabs />

            <div class="rounded-xl border bg-card p-6">
                <h1 class="text-xl font-bold">{{ isEdit ? 'Ubah Kategori' : 'Tambah Kategori' }}</h1>

                <form class="mt-5 flex flex-col gap-5" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="name">Nama</Label>
                        <Input id="name" v-model="form.name" required placeholder="mis. Kedai Minum" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input id="slug" v-model="form.slug" required placeholder="kedai-minum" />
                        <InputError :message="form.errors.slug" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Deskripsi (opsional)</Label>
                        <textarea id="description" v-model="form.description" rows="3" :class="fieldClass"></textarea>
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="position">Urutan tampil</Label>
                            <Input id="position" v-model.number="form.position" type="number" min="0" />
                            <InputError :message="form.errors.position" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="min_rank">Rank minimal (opsional)</Label>
                            <select id="min_rank" v-model="form.min_rank" :class="fieldClass">
                                <option value="">Tanpa batasan</option>
                                <option v-for="r in ranks" :key="r" :value="r">Rank {{ r }}</option>
                            </select>
                            <InputError :message="form.errors.min_rank" />
                        </div>
                    </div>

                    <div class="flex items-start gap-3 rounded-lg border p-3">
                        <Checkbox id="is_locked" v-model:checked="form.is_locked" />
                        <div class="grid gap-1">
                            <Label for="is_locked">Terkunci — hanya Dewa boleh membuka topik</Label>
                            <p class="text-xs text-muted-foreground">
                                Untuk kategori maklumat. Pemain tetap bisa membalas topik yang ada (kecuali topiknya dikunci).
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <Button type="submit" :disabled="form.processing">{{ isEdit ? 'Simpan Perubahan' : 'Buat Kategori' }}</Button>
                        <Button type="button" variant="outline" @click="router.visit(route('admin.forum-categories.index'))">Batal</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
