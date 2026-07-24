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

interface Skill {
    id: number;
    slug: string;
    name: string;
    type: string;
    description: string | null;
    image: string | null;
    power: number;
    stamina_cost: number;
    level_req: number;
    is_default: boolean;
    effects: Record<string, unknown> | null;
}

const props = defineProps<{ skill: Skill | null; types: string[] }>();

const isEdit = !!props.skill;
const typeLabels: Record<string, string> = { physical: 'Fisik', magic: 'Sihir', ranged: 'Jarak jauh' };

const form = useForm({
    name: props.skill?.name ?? '',
    slug: props.skill?.slug ?? '',
    type: props.skill?.type ?? 'physical',
    power: props.skill?.power ?? 1,
    stamina_cost: props.skill?.stamina_cost ?? 0,
    level_req: props.skill?.level_req ?? 1,
    is_default: props.skill?.is_default ?? false,
    image: props.skill?.image ?? '',
    description: props.skill?.description ?? '',
    effects: props.skill?.effects ? JSON.stringify(props.skill.effects, null, 2) : '',
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
    if (isEdit) form.put(route('admin.skills.update', props.skill!.id));
    else form.post(route('admin.skills.store'));
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Skill', href: '/admin/skills' },
    { title: isEdit ? 'Ubah' : 'Tambah', href: '#' },
];

const fieldClass = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring';
</script>

<template>
    <Head :title="isEdit ? 'Ubah Skill' : 'Tambah Skill'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <AdminTabs />

            <div class="rounded-xl border bg-card p-6">
                <h1 class="text-xl font-bold">{{ isEdit ? 'Ubah Skill' : 'Tambah Skill' }}</h1>

                <form class="mt-5 flex flex-col gap-5" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="name">Nama</Label>
                        <Input id="name" v-model="form.name" required placeholder="mis. Tebas" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input id="slug" v-model="form.slug" required placeholder="tebas" />
                        <InputError :message="form.errors.slug" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="type">Tipe</Label>
                            <select id="type" v-model="form.type" :class="fieldClass">
                                <option v-for="t in types" :key="t" :value="t">{{ typeLabels[t] ?? t }}</option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="power">Power (damage)</Label>
                            <Input id="power" v-model.number="form.power" type="number" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="level_req">Level Min</Label>
                            <Input id="level_req" v-model.number="form.level_req" type="number" min="1" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="stamina_cost">Biaya Stamina / SP</Label>
                        <Input id="stamina_cost" v-model.number="form.stamina_cost" type="number" min="0" />
                        <p class="text-xs text-muted-foreground">SP yang dipakai tiap serangan. <strong>0 = otomatis mengikuti Power</strong> (makin besar power, makin besar biaya). Sihir memakai MP lewat "Mana Cost".</p>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.is_default" type="checkbox" class="h-4 w-4 accent-primary" />
                        Default — dimiliki semua karakter sejak awal (mis. Pukul)
                    </label>

                    <div class="grid gap-2">
                        <Label for="image">URL Gambar (opsional)</Label>
                        <Input id="image" v-model="form.image" placeholder="/images/skills/tebas.png" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Deskripsi (opsional)</Label>
                        <textarea id="description" v-model="form.description" rows="2" :class="fieldClass"></textarea>
                    </div>
                    <div class="grid gap-2">
                        <Label for="effects">Efek — JSON (opsional)</Label>
                        <textarea id="effects" v-model="form.effects" rows="2" :class="fieldClass" placeholder='{"stun": true}'></textarea>
                        <InputError :message="form.errors.effects" />
                    </div>

                    <div class="flex gap-3">
                        <Button type="submit" :disabled="form.processing">{{ isEdit ? 'Simpan Perubahan' : 'Buat Skill' }}</Button>
                        <Button type="button" variant="outline" @click="router.visit(route('admin.skills.index'))">Batal</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
