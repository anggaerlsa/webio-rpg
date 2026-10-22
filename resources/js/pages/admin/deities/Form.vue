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

interface Deity {
    id: number;
    slug: string;
    name: string;
    title: string;
    domain: string | null;
    description: string | null;
    image: string | null;
    user_id: number | null;
    blessing: string | null;
    blessing_message: string | null;
}

const props = defineProps<{
    deity: Deity | null;
    blessings: Record<string, string>;
    gms: { id: number; name: string; email: string }[];
}>();

const isEdit = !!props.deity;

const form = useForm({
    name: props.deity?.name ?? '',
    slug: props.deity?.slug ?? '',
    title: props.deity?.title ?? '',
    domain: props.deity?.domain ?? '',
    description: props.deity?.description ?? '',
    image: props.deity?.image ?? '',
    user_id: props.deity?.user_id ?? null,
    blessing: props.deity?.blessing ?? null,
    blessing_message: props.deity?.blessing_message ?? '',
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
    if (isEdit) form.put(route('admin.deities.update', props.deity!.id));
    else form.post(route('admin.deities.store'));
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Kuil', href: route('admin.deities.index') },
    { title: isEdit ? `Ubah ${props.deity!.name}` : 'Dewa Baru', href: '#' },
];

const fieldClass = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring';
</script>

<template>
    <Head :title="isEdit ? 'Ubah Dewa' : 'Tambah Dewa'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <AdminTabs />

            <div class="rounded-xl border bg-card p-6">
                <h1 class="text-xl font-bold">{{ isEdit ? 'Ubah Dewa' : 'Tambah Dewa' }}</h1>

                <form class="mt-5 flex flex-col gap-5" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="name">Nama</Label>
                            <Input id="name" v-model="form.name" required placeholder="mis. Aurelia" />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="slug">Slug</Label>
                            <Input id="slug" v-model="form.slug" required placeholder="aurelia" />
                            <InputError :message="form.errors.slug" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="title">Gelar</Label>
                            <Input id="title" v-model="form.title" required placeholder="mis. Dewi Cinta Kasih" />
                            <InputError :message="form.errors.title" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="domain">Ranah (opsional)</Label>
                            <Input id="domain" v-model="form.domain" placeholder="mis. Cinta, kasih sayang, dan ikatan hati" />
                            <InputError :message="form.errors.domain" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Deskripsi (opsional)</Label>
                        <textarea id="description" v-model="form.description" rows="3" :class="fieldClass"></textarea>
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="image">URL Potret Full Body (opsional)</Label>
                        <Input id="image" v-model="form.image" placeholder="/images/deities/aurelia.webp" />
                        <InputError :message="form.errors.image" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="user_id">Akun GM Pemeran (opsional)</Label>
                        <select id="user_id" v-model="form.user_id" :class="fieldClass">
                            <option :value="null">— Tidak diperankan GM —</option>
                            <option v-for="gm in gms" :key="gm.id" :value="gm.id">{{ gm.name }} ({{ gm.email }})</option>
                        </select>
                        <InputError :message="form.errors.user_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="blessing">Berkah</Label>
                        <select id="blessing" v-model="form.blessing" :class="fieldClass">
                            <option :value="null">Tidak menurunkan berkah</option>
                            <option v-for="(label, key) in blessings" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <InputError :message="form.errors.blessing" />
                    </div>

                    <div v-if="form.blessing" class="grid gap-2">
                        <Label for="blessing_message">Pesan setelah berdoa</Label>
                        <textarea id="blessing_message" v-model="form.blessing_message" rows="2" :class="fieldClass"></textarea>
                        <InputError :message="form.errors.blessing_message" />
                    </div>

                    <div class="flex gap-3">
                        <Button type="submit" :disabled="form.processing">{{ isEdit ? 'Simpan Perubahan' : 'Tambah ke Kuil' }}</Button>
                        <Button type="button" variant="outline" @click="router.visit(route('admin.deities.index'))">Batal</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
