<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Globe, LayoutDashboard, MessagesSquare, Package, ScrollText, ShieldHalf, Skull, Sparkles, Sword, Users } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();

const tabs = [
    { label: 'Beranda', href: route('admin.dashboard'), icon: LayoutDashboard, match: '/admin' },
    { label: 'Dunia', href: route('admin.world.countries.index'), icon: Globe, match: '/admin/world' },
    { label: 'Items', href: route('admin.items.index'), icon: Package, match: '/admin/items' },
    { label: 'Skill', href: route('admin.skills.index'), icon: Sword, match: '/admin/skills' },
    { label: 'Sihir', href: route('admin.spells.index'), icon: Sparkles, match: '/admin/spells' },
    { label: 'Monster', href: route('admin.monsters.index'), icon: Skull, match: '/admin/monsters' },
    { label: 'Misi', href: route('admin.quests.index'), icon: ScrollText, match: '/admin/quests' },
    { label: 'Rank', href: route('admin.ranks.index'), icon: ShieldHalf, match: '/admin/ranks' },
    { label: 'Forum', href: route('admin.forum-categories.index'), icon: MessagesSquare, match: '/admin/forum-categories' },
    { label: 'Pemain', href: route('admin.players.index'), icon: Users, match: '/admin/players' },
];

const url = computed(() => page.url);

function active(match: string): boolean {
    if (match === '/admin') return url.value === '/admin' || url.value === '/admin/';
    return url.value.startsWith(match);
}

const flashSuccess = computed(() => (page.props as Record<string, any>).flash?.success as string | undefined);
</script>

<template>
    <div>
        <div class="flex flex-wrap gap-2 border-b pb-3">
            <Link
                v-for="t in tabs"
                :key="t.href"
                :href="t.href"
                class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition"
                :class="active(t.match) ? 'bg-primary text-primary-foreground' : 'hover:bg-accent'"
            >
                <component :is="t.icon" class="h-4 w-4" /> {{ t.label }}
            </Link>
        </div>

        <div v-if="flashSuccess" class="mt-4 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-600">
            {{ flashSuccess }}
        </div>
    </div>
</template>
