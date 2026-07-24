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

interface Monster {
    id: number;
    slug: string;
    name: string;
    image: string | null;
    max_hp: number;
    attack: number;
    defense: number;
    xp_reward: number;
    gold_reward: number;
    loot: unknown;
}

const props = defineProps<{ monster: Monster | null }>();

const isEdit = !!props.monster;

const form = useForm({
    name: props.monster?.name ?? '',
    slug: props.monster?.slug ?? '',
    image: props.monster?.image ?? '',
    max_hp: props.monster?.max_hp ?? 5,
    attack: props.monster?.attack ?? 2,
    defense: props.monster?.defense ?? 0,
    xp_reward: props.monster?.xp_reward ?? 20,
    gold_reward: props.monster?.gold_reward ?? 5,
    loot: props.monster?.loot ? JSON.stringify(props.monster.loot, null, 2) : '',
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
    if (isEdit) form.put(route('admin.monsters.update', props.monster!.id));
    else form.post(route('admin.monsters.store'));
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Monster', href: '/admin/monsters' },
    { title: isEdit ? 'Ubah' : 'Tambah', href: '#' },
];

const fieldClass = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring';
</script>

<template>
    <Head :title="isEdit ? 'Ubah Monster' : 'Tambah Monster'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <AdminTabs />

            <div class="rounded-xl border bg-card p-6">
                <h1 class="text-xl font-bold">{{ isEdit ? 'Ubah Monster' : 'Tambah Monster' }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Combat memakai serangan pemain (mis. Pukul, damage 1). Sesuaikan HP monster agar pas dengan kekuatan skill.
                </p>

                <form class="mt-5 flex flex-col gap-5" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="name">Nama</Label>
                        <Input id="name" v-model="form.name" required placeholder="mis. Goblin Gua" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="slug">Slug</Label>
                        <Input id="slug" v-model="form.slug" required placeholder="goblin-gua" />
                        <InputError :message="form.errors.slug" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="max_hp">HP Maks</Label>
                            <Input id="max_hp" v-model.number="form.max_hp" type="number" min="1" />
                            <InputError :message="form.errors.max_hp" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="attack">Serangan</Label>
                            <Input id="attack" v-model.number="form.attack" type="number" min="0" />
                            <InputError :message="form.errors.attack" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="defense">Pertahanan</Label>
                            <Input id="defense" v-model.number="form.defense" type="number" min="0" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="xp_reward">Hadiah XP</Label>
                            <Input id="xp_reward" v-model.number="form.xp_reward" type="number" min="0" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="gold_reward">Hadiah Emas</Label>
                            <Input id="gold_reward" v-model.number="form.gold_reward" type="number" min="0" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="image">URL Gambar (opsional)</Label>
                        <Input id="image" v-model="form.image" placeholder="/images/monsters/goblin.png" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="loot">Loot — JSON (opsional)</Label>
                        <textarea id="loot" v-model="form.loot" rows="2" :class="fieldClass" placeholder='[{"item_slug": "potion", "chance": 0.5, "qty": 1}]'></textarea>
                        <InputError :message="form.errors.loot" />
                    </div>

                    <div class="flex gap-3">
                        <Button type="submit" :disabled="form.processing">{{ isEdit ? 'Simpan Perubahan' : 'Buat Monster' }}</Button>
                        <Button type="button" variant="outline" @click="router.visit(route('admin.monsters.index'))">Batal</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
