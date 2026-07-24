<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';

interface CharacterData {
    name: string;
    class: string | null;
    level: number; xp: number; gold: number;
    hp: number; max_hp: number; sp: number; max_sp: number; mp: number; max_mp: number;
    attack: number; defense: number;
    strength: number; agility: number; dexterity: number; intelligence: number; vitality: number; luck: number;
    rank: string | null;
}
interface Player {
    id: number;
    name: string;
    email: string;
    role: string;
    job: string | null;
    is_self: boolean;
    character: CharacterData | null;
}

const props = defineProps<{ player: Player; roles: string[] }>();

const form = useForm({
    name: props.player.name,
    email: props.player.email,
    role: props.player.role,
    job: props.player.job ?? '',
    password: '',
    character: props.player.character ? { ...props.player.character } : null,
});

const roleLabels: Record<string, string> = { player: 'Pemain', superadmin: 'Dewa' };

// Field karakter numerik, dikelompokkan agar rapi.
const charGroups: { title: string; fields: { key: keyof CharacterData; label: string }[] }[] = [
    { title: 'Progres', fields: [
        { key: 'level', label: 'Level' }, { key: 'xp', label: 'XP' }, { key: 'gold', label: 'Emas' },
    ] },
    { title: 'Vitalitas', fields: [
        { key: 'hp', label: 'HP' }, { key: 'max_hp', label: 'HP Maks' },
        { key: 'sp', label: 'SP' }, { key: 'max_sp', label: 'SP Maks' },
        { key: 'mp', label: 'MP' }, { key: 'max_mp', label: 'MP Maks' },
    ] },
    { title: 'Tempur', fields: [
        { key: 'attack', label: 'Serangan' }, { key: 'defense', label: 'Pertahanan' },
    ] },
    { title: 'Atribut', fields: [
        { key: 'strength', label: 'STR' }, { key: 'agility', label: 'AGI' }, { key: 'dexterity', label: 'DEX' },
        { key: 'intelligence', label: 'INT' }, { key: 'vitality', label: 'VIT' }, { key: 'luck', label: 'LUK' },
    ] },
];

function submit() {
    form.put(route('admin.players.update', props.player.id));
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Pemain', href: '/admin/players' },
    { title: `Ubah ${props.player.name}`, href: '#' },
];

const fieldClass = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring';
</script>

<template>
    <Head :title="`Ubah Pemain — ${player.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <AdminTabs />

            <form class="space-y-6" @submit.prevent="submit">
                <!-- Akun -->
                <div class="rounded-xl border bg-card p-6">
                    <h1 class="text-xl font-bold">Ubah Pemain</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Akun pemain.</p>

                    <div class="mt-5 flex flex-col gap-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="name">Nama</Label>
                                <Input id="name" v-model="form.name" required />
                                <InputError :message="form.errors.name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="email">Email</Label>
                                <Input id="email" v-model="form.email" type="email" required />
                                <InputError :message="form.errors.email" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="role">Peran</Label>
                                <select id="role" v-model="form.role" :class="fieldClass" :disabled="player.is_self">
                                    <option v-for="r in roles" :key="r" :value="r">{{ roleLabels[r] ?? r }}</option>
                                </select>
                                <p v-if="player.is_self" class="text-xs text-muted-foreground">Kamu tidak bisa mengubah peran akunmu sendiri.</p>
                                <InputError :message="form.errors.role" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="job">Gelar / Job</Label>
                                <Input id="job" v-model="form.job" placeholder="mis. adventurer / Dewa Hujan" />
                                <InputError :message="form.errors.job" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="password">Kata sandi baru (opsional)</Label>
                            <Input id="password" v-model="form.password" type="password" autocomplete="new-password" placeholder="Kosongkan untuk membiarkan tetap" />
                            <InputError :message="form.errors.password" />
                        </div>
                    </div>
                </div>

                <!-- Karakter -->
                <div v-if="form.character" class="rounded-xl border bg-card p-6">
                    <h2 class="font-display text-lg font-semibold">Karakter — {{ player.character?.name }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ player.character?.class ?? 'Tanpa class' }} — sunting progres & statistik sebagai Dewa.
                    </p>

                    <div class="mt-5 space-y-5">
                        <div v-for="g in charGroups" :key="g.title">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ g.title }}</p>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <div v-for="f in g.fields" :key="f.key" class="grid gap-1.5">
                                    <Label :for="`c-${f.key}`" class="text-xs">{{ f.label }}</Label>
                                    <Input :id="`c-${f.key}`" v-model.number="form.character[f.key] as number" type="number" min="0" />
                                    <InputError :message="form.errors[`character.${f.key}` as keyof typeof form.errors]" />
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-1.5 sm:max-w-[10rem]">
                            <Label for="c-rank" class="text-xs">Rank</Label>
                            <Input id="c-rank" v-model="form.character.rank as string" placeholder="F" />
                            <InputError :message="form.errors['character.rank' as keyof typeof form.errors]" />
                        </div>
                    </div>
                </div>

                <div v-else class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                    Pemain ini belum membuat karakter.
                </div>

                <div class="flex gap-3">
                    <Button type="submit" :disabled="form.processing">Simpan Perubahan</Button>
                    <Button type="button" variant="outline" @click="router.visit(route('admin.players.index'))">Batal</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
