<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Castle, Crown, LayoutGrid, Map, UserRound } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage();
const isAdmin = computed(() => (page.props as Record<string, any>).auth?.user?.role === 'superadmin');

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        { title: 'Beranda', href: '/dashboard', icon: LayoutGrid },
        { title: 'Kota', href: '/town', icon: Castle },
        { title: 'Misi', href: '/quests', icon: Map },
        { title: 'Karakter', href: '/character', icon: UserRound },
    ];
    if (isAdmin.value) {
        items.push({ title: 'Panel Admin', href: '/admin', icon: Crown });
    }
    return items;
});
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
