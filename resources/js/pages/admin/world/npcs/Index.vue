<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, UserRound } from 'lucide-vue-next';

interface Npc {
    id: number;
    slug: string;
    name: string;
    role: string;
    image: string | null;
    greeting: string;
    choices: unknown[] | null;
}
interface Place {
    id: number;
    name: string;
    city: { id: number; name: string; province: { country: { id: number; name: string } } };
    npcs: Npc[];
}

const props = defineProps<{ place: Place }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Dunia', href: route('admin.world.countries.index') },
    { title: props.place.city.province.country.name, href: route('admin.world.countries.show', props.place.city.province.country.id) },
    { title: props.place.city.name, href: route('admin.world.cities.show', props.place.city.id) },
    { title: `NPC ${props.place.name}`, href: '#' },
];

function hapus(npc: Npc) {
    if (confirm(`Hapus NPC "${npc.name}"?`)) {
        router.delete(route('admin.world.npcs.destroy', npc.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="`NPC — ${place.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl space-y-6 p-4">
            <AdminTabs />

            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">NPC · {{ place.city.name }}</p>
                    <h1 class="text-2xl font-bold">{{ place.name }}</h1>
                </div>
                <Button @click="router.visit(route('admin.world.places.npcs.create', place.id))"><Plus class="mr-2 h-4 w-4" /> Tambah NPC</Button>
            </div>

            <div v-if="!place.npcs.length" class="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                Belum ada NPC di {{ place.name }}.
            </div>

            <div v-for="npc in place.npcs" :key="npc.id" class="flex gap-4 rounded-xl border bg-card p-4">
                <div class="relative aspect-[3/4] w-20 shrink-0 self-start overflow-hidden rounded-lg bg-muted">
                    <img v-if="npc.image" :src="npc.image" :alt="npc.name" class="absolute left-1/2 top-0 w-[200%] max-w-none -translate-x-1/2" />
                    <UserRound v-else class="absolute inset-0 m-auto h-8 w-8 text-muted-foreground" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="font-semibold">{{ npc.name }}</div>
                    <div class="text-xs text-muted-foreground">{{ npc.role }} · {{ npc.choices?.length ?? 0 }} pilihan dialog</div>
                    <p class="mt-1 line-clamp-2 text-sm italic text-muted-foreground">“{{ npc.greeting }}”</p>
                </div>
                <div class="flex shrink-0 items-start gap-2">
                    <Button size="sm" variant="outline" @click="router.visit(route('admin.world.npcs.edit', npc.id))"
                        ><Pencil class="h-4 w-4"
                    /></Button>
                    <Button size="sm" variant="outline" class="text-red-600" @click="hapus(npc)"><Trash2 class="h-4 w-4" /></Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
