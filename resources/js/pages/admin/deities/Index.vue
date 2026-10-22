<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Sparkles, Trash2 } from 'lucide-vue-next';

interface Deity {
    id: number;
    slug: string;
    name: string;
    title: string;
    domain: string | null;
    image: string | null;
    blessing: string | null;
    user: { id: number; name: string; email: string } | null;
}

defineProps<{ deities: Deity[]; blessings: Record<string, string> }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Kuil', href: '#' },
];

function hapus(d: Deity) {
    if (confirm(`Hapus "${d.name}" dari kuil?`)) router.delete(route('admin.deities.destroy', d.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Kuil — Panel Dewa" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl space-y-6 p-4">
            <AdminTabs />

            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold">Kuil</h1>
                    <p class="text-sm text-muted-foreground">Dewa-dewi yang tampil di kuil pemain, akun GM pemerannya, dan berkahnya.</p>
                </div>
                <Button @click="router.visit(route('admin.deities.create'))"><Plus class="mr-2 h-4 w-4" /> Tambah Dewa</Button>
            </div>

            <div v-if="!deities.length" class="rounded-xl border border-dashed p-8 text-center text-muted-foreground">Kuil masih kosong.</div>

            <div v-for="d in deities" :key="d.id" class="flex gap-4 rounded-xl border bg-card p-4">
                <div class="relative aspect-[3/4] w-20 shrink-0 self-start overflow-hidden rounded-lg bg-muted">
                    <img v-if="d.image" :src="d.image" :alt="d.name" class="absolute left-1/2 top-0 w-[200%] max-w-none -translate-x-1/2" />
                    <Sparkles v-else class="absolute inset-0 m-auto h-8 w-8 text-muted-foreground" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="font-semibold">{{ d.name }}</div>
                    <div class="text-xs text-muted-foreground">
                        {{ d.title }}<template v-if="d.domain"> · {{ d.domain }}</template>
                    </div>
                    <div class="mt-1 text-xs">
                        <span class="text-muted-foreground">GM:</span> {{ d.user ? `${d.user.name} (${d.user.email})` : '—' }} ·
                        <span class="text-muted-foreground">Berkah:</span> {{ d.blessing ? (blessings[d.blessing] ?? d.blessing) : 'tidak ada' }}
                    </div>
                </div>
                <div class="flex shrink-0 items-start gap-2">
                    <Button size="sm" variant="outline" @click="router.visit(route('admin.deities.edit', d.id))"><Pencil class="h-4 w-4" /></Button>
                    <Button size="sm" variant="outline" class="text-red-600" @click="hapus(d)"><Trash2 class="h-4 w-4" /></Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
