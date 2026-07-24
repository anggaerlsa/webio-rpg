<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Sword, Wand2 } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Buat Karakter', href: '/character/create' }];

const genders = [
    { value: 'male', label: 'Pria' },
    { value: 'female', label: 'Wanita' },
];
const classes = [
    { value: 'Warrior', label: 'Ksatria (Warrior)', icon: Sword },
    { value: 'Mage', label: 'Penyihir (Mage)', icon: Wand2 },
];

const form = useForm<{ name: string; gender: string; birth_date: string; class: string }>({
    name: '',
    gender: '',
    birth_date: '',
    class: '',
});

const submit = () => form.post(route('character.store'));
</script>

<template>
    <Head title="Buat Pahlawan" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-md space-y-6 p-4">
            <div class="rounded-xl border bg-card p-6">
                <h1 class="text-2xl font-bold">Buat pahlawanmu</h1>
                <p class="mt-1 text-sm text-muted-foreground">Satu akun hanya punya satu karakter. Pilih dengan bijak.</p>

                <form class="mt-6 flex flex-col gap-5" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="name">Nama</Label>
                        <Input id="name" v-model="form.name" required autofocus placeholder="mis. Aria sang Pemberani" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Gender</Label>
                        <div class="flex gap-2">
                            <button
                                v-for="g in genders"
                                :key="g.value"
                                type="button"
                                class="flex-1 rounded-lg border px-3 py-2 text-sm transition"
                                :class="form.gender === g.value ? 'border-primary bg-primary/10 font-semibold' : 'hover:bg-accent'"
                                @click="form.gender = g.value"
                            >
                                {{ g.label }}
                            </button>
                        </div>
                        <InputError :message="form.errors.gender" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="birth_date">Tanggal lahir</Label>
                        <Input id="birth_date" v-model="form.birth_date" type="date" required />
                        <p class="text-xs text-muted-foreground">Menentukan usia karaktermu.</p>
                        <InputError :message="form.errors.birth_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Class</Label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                v-for="c in classes"
                                :key="c.value"
                                type="button"
                                class="flex flex-col items-center gap-1 rounded-lg border px-3 py-4 text-sm transition"
                                :class="form.class === c.value ? 'border-primary bg-primary/10 font-semibold' : 'hover:bg-accent'"
                                @click="form.class = c.value"
                            >
                                <component :is="c.icon" class="h-6 w-6" />
                                {{ c.label }}
                            </button>
                        </div>
                        <InputError :message="form.errors.class" />
                    </div>

                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" /> Mulai petualangan
                    </Button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
