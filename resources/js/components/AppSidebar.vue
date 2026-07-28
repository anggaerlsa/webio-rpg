<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Castle, Crown, LayoutGrid, Map, ScrollText, UserRound } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage();
const isAdmin = computed(() => (page.props as Record<string, any>).auth?.user?.role === 'superadmin');
const userId = computed(() => (page.props as Record<string, any>).auth?.user?.id as number | undefined);

// Lencana Balai Warta: hitungan dari server + tambahan langsung lewat websocket.
const liveReplies = ref(0);
const forumUnread = computed(() => ((page.props as Record<string, any>).forum?.unread ?? 0) + liveReplies.value);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        { title: 'Beranda', href: '/dashboard', icon: LayoutGrid },
        { title: 'Kota', href: '/town', icon: Castle },
        { title: 'Misi', href: '/quests', icon: Map },
        { title: 'Balai Warta', href: '/forum', icon: ScrollText, badge: forumUnread.value },
        { title: 'Karakter', href: '/character', icon: UserRound },
    ];
    if (isAdmin.value) {
        items.push({ title: 'Panel Admin', href: '/admin', icon: Crown });
    }
    return items;
});

function echo(): any {
    return (window as any).Echo;
}

// Balasan baru menaikkan lencana tanpa polling — memakai channel pribadi yang sama
// dengan notifikasi pertemanan. Membuka Balai Warta menihilkan hitungan lokal.
onMounted(() => {
    if (!userId.value) return;
    echo()
        ?.private('App.Models.User.' + userId.value)
        .listen('.forum-reply', () => {
            if (!page.url.startsWith('/forum')) liveReplies.value += 1;
        });
});

onUnmounted(() => {
    // Hanya melepas pendengar ini — channel yang sama juga dipakai ChatDock.
    if (userId.value) echo()?.private('App.Models.User.' + userId.value).stopListening('.forum-reply');
});

watch(
    () => page.url,
    (url) => {
        if (url.startsWith('/forum')) liveReplies.value = 0;
    },
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
