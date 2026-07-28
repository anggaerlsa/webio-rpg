<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    category: { slug: string; name: string; description: string | null };
}>();

const form = useForm({ title: '', body: '' });

function submit() {
    form.post(route('forum.topic.store', props.category.slug));
}

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Balai Warta', href: '/forum' },
    { title: props.category.name, href: route('forum.category', props.category.slug) },
    { title: 'Topik Baru', href: '#' },
]);

const fieldClass = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring';
</script>

<template>
    <Head title="Topik Baru" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-5 p-4">
            <div class="rounded-xl border bg-card p-6">
                <h1 class="font-display text-xl font-bold">Buka Topik</h1>
                <p class="mt-1 text-sm text-muted-foreground">di {{ category.name }}</p>

                <form class="mt-5 flex flex-col gap-5" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="title">Judul</Label>
                        <Input id="title" v-model="form.title" required maxlength="120" placeholder="mis. Cara mengalahkan Goblin Gua tanpa ramuan" />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="body">Isi</Label>
                        <textarea
                            id="body"
                            v-model="form.body"
                            rows="10"
                            required
                            maxlength="5000"
                            :class="[fieldClass, 'font-serif']"
                            placeholder="Tuliskan wartamu di sini..."
                        ></textarea>
                        <p class="text-xs text-muted-foreground">{{ form.body.length }} / 5000 karakter · teks biasa, baris baru dipertahankan.</p>
                        <InputError :message="form.errors.body" />
                    </div>

                    <div class="flex gap-3">
                        <Button type="submit" :disabled="form.processing">Terbitkan</Button>
                        <Button type="button" variant="outline" @click="router.visit(route('forum.category', category.slug))">Batal</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
