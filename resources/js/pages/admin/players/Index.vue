<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Pencil, Trash2, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface Player {
    id: number;
    name: string;
    email: string;
    role: string;
    job: string | null;
    character: { name: string; level: number } | null;
}

defineProps<{ players: Player[] }>();

const page = usePage();
const myId = computed(() => (page.props as Record<string, any>).auth?.user?.id);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Pemain', href: '/admin/players' },
];

const roleLabels: Record<string, string> = { player: 'Pemain', superadmin: 'Dewa' };

function hapus(player: Player) {
    if (
        confirm(
            `Hapus pemain "${player.name}" (${player.email})?\n\nKarakter${player.character ? ` "${player.character.name}"` : ''} beserta SELURUH progres (misi, inventaris, sesi pertarungan) ikut terhapus permanen. Tindakan ini tidak bisa dibatalkan.`,
        )
    ) {
        router.delete(route('admin.players.destroy', player.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Kelola Pemain" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl space-y-6 p-4">
            <AdminTabs />

            <div>
                <h1 class="flex items-center gap-2 text-xl font-bold"><Users class="h-5 w-5 text-primary" /> Pemain</h1>
                <p class="text-sm text-muted-foreground">Kelola akun & karakter pemain. Menghapus pemain juga menghapus karakter dan seluruh progresnya.</p>
            </div>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Peran</th>
                            <th class="px-4 py-3">Karakter</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="player in players" :key="player.id" class="border-t hover:bg-accent/40">
                            <td class="px-4 py-3 font-medium">
                                {{ player.name }}
                                <span v-if="player.id === myId" class="ml-1 text-xs text-muted-foreground">(kamu)</span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ player.email }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="player.role === 'superadmin' ? 'bg-primary/15 text-primary' : 'bg-muted text-muted-foreground'"
                                >
                                    {{ roleLabels[player.role] ?? player.role }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="player.character">{{ player.character.name }} <span class="text-muted-foreground">· Lv {{ player.character.level }}</span></span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button size="sm" variant="outline" @click="router.visit(route('admin.players.edit', player.id))">
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="text-red-600 disabled:opacity-40"
                                        :disabled="player.id === myId"
                                        :title="player.id === myId ? 'Tidak bisa menghapus akun sendiri' : 'Hapus pemain'"
                                        @click="hapus(player)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!players.length">
                            <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">Belum ada pemain.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
