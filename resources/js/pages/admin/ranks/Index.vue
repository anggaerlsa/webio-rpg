<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { ShieldHalf } from 'lucide-vue-next';

interface RankRule {
    rank: string;
    next: string | null;
    missions_required: number;
}

const props = defineProps<{ rules: RankRule[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Rank', href: '/admin/ranks' },
];

const form = useForm({
    rules: props.rules.map((r) => ({ rank: r.rank, missions_required: r.missions_required })),
});

function submit() {
    form.put(route('admin.ranks.update'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Rank" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <AdminTabs />

            <div class="rounded-xl border bg-card p-6">
                <div class="flex items-center gap-2">
                    <ShieldHalf class="h-6 w-6 text-amber-500" />
                    <h1 class="text-xl font-bold">Ambang Naik Rank</h1>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Berapa misi yang harus diselesaikan pemain untuk naik dari tiap rank. Misi guild di-gate oleh rank minimal,
                    jadi pemain otomatis mengerjakan misi sesuai tingkatnya.
                </p>

                <form class="mt-5 space-y-3" @submit.prevent="submit">
                    <div
                        v-for="(rule, i) in form.rules"
                        :key="rule.rank"
                        class="flex items-center gap-3 rounded-lg border bg-background p-3"
                    >
                        <div class="font-display w-24 text-lg font-bold">
                            Rank {{ rule.rank }}
                            <span class="text-xs font-normal text-muted-foreground">→ {{ props.rules[i].next }}</span>
                        </div>
                        <div class="flex flex-1 items-center gap-2">
                            <Input v-model.number="rule.missions_required" type="number" min="1" max="999" class="w-24" />
                            <span class="text-sm text-muted-foreground">misi</span>
                        </div>
                    </div>

                    <Button type="submit" :disabled="form.processing">Simpan Ambang</Button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
