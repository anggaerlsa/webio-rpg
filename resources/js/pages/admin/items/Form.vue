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

interface Item {
    id: number;
    slug: string;
    name: string;
    type: string;
    description: string | null;
    image: string | null;
    value: number;
    stats: Record<string, unknown> | null;
}

const props = defineProps<{ item: Item | null; types: string[] }>();

const isEdit = !!props.item;

const typeLabels: Record<string, string> = {
    weapon: 'Senjata',
    armor: 'Zirah',
    accessory: 'Aksesori',
    consumable: 'Konsumsi',
    book: 'Buku',
    key: 'Kunci',
    misc: 'Lainnya',
};

const form = useForm({
    name: props.item?.name ?? '',
    slug: props.item?.slug ?? '',
    type: props.item?.type ?? 'misc',
    value: props.item?.value ?? 0,
    image: props.item?.image ?? '',
    description: props.item?.description ?? '',
    stats: props.item?.stats ? JSON.stringify(props.item.stats, null, 2) : '',
});

// Auto-isi slug dari nama saat membuat item baru.
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
    if (isEdit) form.put(route('admin.items.update', props.item!.id));
    else form.post(route('admin.items.store'));
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Items', href: '/admin/items' },
    { title: isEdit ? 'Ubah' : 'Tambah', href: '#' },
];

const fieldClass = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring';
</script>

<template>
    <Head :title="isEdit ? 'Ubah Item' : 'Tambah Item'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <AdminTabs />

            <div class="rounded-xl border bg-card p-6">
                <h1 class="text-xl font-bold">{{ isEdit ? 'Ubah Item' : 'Tambah Item' }}</h1>

                <form class="mt-5 flex flex-col gap-5" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="name">Nama</Label>
                        <Input id="name" v-model="form.name" required placeholder="mis. Pedang Baja" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input id="slug" v-model="form.slug" required placeholder="pedang-baja" />
                        <p class="text-xs text-muted-foreground">Huruf kecil, angka, dan tanda hubung. Dipakai sebagai kunci unik.</p>
                        <InputError :message="form.errors.slug" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="type">Tipe</Label>
                            <select id="type" v-model="form.type" :class="fieldClass">
                                <option v-for="t in types" :key="t" :value="t">{{ typeLabels[t] ?? t }}</option>
                            </select>
                            <InputError :message="form.errors.type" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="value">Nilai (harga emas)</Label>
                            <Input id="value" v-model.number="form.value" type="number" min="0" />
                            <InputError :message="form.errors.value" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="image">URL Gambar (opsional)</Label>
                        <Input id="image" v-model="form.image" placeholder="/images/items/pedang.png" />
                        <InputError :message="form.errors.image" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Deskripsi (opsional)</Label>
                        <textarea id="description" v-model="form.description" rows="3" :class="fieldClass" placeholder="Senjata tajam tempaan kerajaan..."></textarea>
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="stats">Statistik / Efek — JSON (opsional)</Label>
                        <textarea id="stats" v-model="form.stats" rows="3" :class="fieldClass" placeholder='{"attack": 5}'></textarea>
                        <p class="text-xs text-muted-foreground">Contoh senjata: <code>{"attack": 5}</code> · Contoh ramuan: <code>{"heal": 30}</code></p>
                        <InputError :message="form.errors.stats" />
                    </div>

                    <div class="flex gap-3">
                        <Button type="submit" :disabled="form.processing">{{ isEdit ? 'Simpan Perubahan' : 'Buat Item' }}</Button>
                        <Button type="button" variant="outline" @click="router.visit(route('admin.items.index'))">Batal</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
