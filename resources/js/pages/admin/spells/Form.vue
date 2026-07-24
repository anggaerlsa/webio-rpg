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

interface Spell {
    id: number;
    slug: string;
    name: string;
    element: string;
    description: string | null;
    image: string | null;
    mana_cost: number;
    power: number;
    min_level: number;
    effects: Record<string, unknown> | null;
}

const props = defineProps<{ spell: Spell | null; elements: string[] }>();

const isEdit = !!props.spell;

const elementLabels: Record<string, string> = {
    api: 'Api',
    air: 'Air',
    angin: 'Angin',
    tanah: 'Tanah',
    cahaya: 'Cahaya',
    kegelapan: 'Kegelapan',
    arcane: 'Arkana',
};

const form = useForm({
    name: props.spell?.name ?? '',
    slug: props.spell?.slug ?? '',
    element: props.spell?.element ?? 'arcane',
    power: props.spell?.power ?? 0,
    mana_cost: props.spell?.mana_cost ?? 0,
    min_level: props.spell?.min_level ?? 1,
    image: props.spell?.image ?? '',
    description: props.spell?.description ?? '',
    effects: props.spell?.effects ? JSON.stringify(props.spell.effects, null, 2) : '',
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
    if (isEdit) form.put(route('admin.spells.update', props.spell!.id));
    else form.post(route('admin.spells.store'));
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Sihir', href: '/admin/spells' },
    { title: isEdit ? 'Ubah' : 'Tambah', href: '#' },
];

const fieldClass = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring';
</script>

<template>
    <Head :title="isEdit ? 'Ubah Sihir' : 'Tambah Sihir'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <AdminTabs />

            <div class="rounded-xl border bg-card p-6">
                <h1 class="text-xl font-bold">{{ isEdit ? 'Ubah Sihir' : 'Tambah Sihir' }}</h1>

                <form class="mt-5 flex flex-col gap-5" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="name">Nama</Label>
                        <Input id="name" v-model="form.name" required placeholder="mis. Bola Api" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input id="slug" v-model="form.slug" required placeholder="bola-api" />
                        <p class="text-xs text-muted-foreground">Huruf kecil, angka, dan tanda hubung. Dipakai sebagai kunci unik.</p>
                        <InputError :message="form.errors.slug" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="element">Elemen</Label>
                            <select id="element" v-model="form.element" :class="fieldClass">
                                <option v-for="e in elements" :key="e" :value="e">{{ elementLabels[e] ?? e }}</option>
                            </select>
                            <InputError :message="form.errors.element" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="min_level">Level Minimum</Label>
                            <Input id="min_level" v-model.number="form.min_level" type="number" min="1" />
                            <InputError :message="form.errors.min_level" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="power">Power (besar damage/heal)</Label>
                            <Input id="power" v-model.number="form.power" type="number" />
                            <InputError :message="form.errors.power" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="mana_cost">Biaya Mana</Label>
                            <Input id="mana_cost" v-model.number="form.mana_cost" type="number" min="0" />
                            <InputError :message="form.errors.mana_cost" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="image">URL Gambar (opsional)</Label>
                        <Input id="image" v-model="form.image" placeholder="/images/spells/bola-api.png" />
                        <InputError :message="form.errors.image" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Deskripsi (opsional)</Label>
                        <textarea id="description" v-model="form.description" rows="3" :class="fieldClass" placeholder="Melontarkan bola api yang membakar musuh..."></textarea>
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="effects">Efek — JSON (opsional)</Label>
                        <textarea id="effects" v-model="form.effects" rows="3" :class="fieldClass" placeholder='{"type": "damage", "burn": 3}'></textarea>
                        <p class="text-xs text-muted-foreground">Contoh: <code>{"type": "damage"}</code> atau <code>{"type": "heal"}</code></p>
                        <InputError :message="form.errors.effects" />
                    </div>

                    <div class="flex gap-3">
                        <Button type="submit" :disabled="form.processing">{{ isEdit ? 'Simpan Perubahan' : 'Buat Sihir' }}</Button>
                        <Button type="button" variant="outline" @click="router.visit(route('admin.spells.index'))">Batal</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
