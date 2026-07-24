<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Globe, Package, ScrollText, Skull, Sparkles, UserCog, Users } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    stats: { cities: number; items: number; spells: number; quests: number; monsters: number; players: number };
}>();

const page = usePage();
const job = computed(() => (page.props as Record<string, any>).auth?.user?.job ?? 'Dewa Pencipta');

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin', href: '/admin' }];

const cards = [
    { key: 'cities', label: 'Kota (Dunia)', icon: Globe, href: route('admin.world.countries.index') },
    { key: 'items', label: 'Items', icon: Package, href: route('admin.items.index') },
    { key: 'spells', label: 'Sihir', icon: Sparkles, href: route('admin.spells.index') },
    { key: 'quests', label: 'Misi', icon: ScrollText, href: null },
    { key: 'monsters', label: 'Monster', icon: Skull, href: null },
    { key: 'players', label: 'Pemain', icon: Users, href: route('admin.players.index') },
] as const;
</script>

<template>
    <Head title="Panel Dewa" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl space-y-6 p-4">
            <AdminTabs />

            <div class="flex items-start justify-between gap-4 rounded-xl border bg-card p-6">
                <div>
                    <h1 class="text-2xl font-bold">Panel Dewa</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Selamat datang, <span class="font-semibold text-primary">{{ job }}</span>. Kelola dunia game dari sini.
                    </p>
                </div>
                <Link
                    :href="route('profile.edit')"
                    class="inline-flex shrink-0 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition hover:bg-accent"
                >
                    <UserCog class="h-4 w-4" /> Edit Profil
                </Link>
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                <component
                    :is="card.href ? Link : 'div'"
                    v-for="card in cards"
                    :key="card.key"
                    :href="card.href || undefined"
                    class="rounded-xl border bg-card p-4 transition"
                    :class="card.href ? 'hover:bg-accent' : 'opacity-80'"
                >
                    <component :is="card.icon" class="mb-2 h-6 w-6 text-primary" />
                    <div class="text-2xl font-bold">{{ stats[card.key] }}</div>
                    <div class="text-sm text-muted-foreground">{{ card.label }}</div>
                </component>
            </div>
        </div>
    </AppLayout>
</template>
