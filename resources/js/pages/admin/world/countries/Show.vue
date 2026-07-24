<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Building2, ChevronRight, Pencil, Store, Tent } from 'lucide-vue-next';

interface Country {
    id: number;
    slug: string;
    name: string;
    government_type: string | null;
    ideology: string | null;
    ruler_title: string | null;
    ruler_name: string | null;
    dominant_race: string | null;
    description: string | null;
}
interface Capital {
    id: number;
    name: string;
    slug: string;
    places_count: number;
    villages_count: number;
}

const props = defineProps<{ country: Country; capital: Capital | null }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Dunia', href: route('admin.world.countries.index') },
    { title: props.country.name, href: route('admin.world.countries.show', props.country.id) },
];
</script>

<template>
    <Head :title="`Negara — ${country.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl space-y-6 p-4">
            <AdminTabs />

            <div class="rounded-xl border bg-card p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">
                            {{ country.government_type || 'Negara' }}<template v-if="country.ideology"> · {{ country.ideology }}</template>
                        </p>
                        <h1 class="text-2xl font-bold">{{ country.name }}</h1>
                        <p class="mt-1 text-sm text-muted-foreground">{{ country.description || 'Tanpa deskripsi.' }}</p>
                    </div>
                    <Button variant="outline" size="sm" @click="router.visit(route('admin.world.countries.edit', country.id))">
                        <Pencil class="mr-2 h-4 w-4" /> Ubah Negara
                    </Button>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 border-t pt-4 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="text-xs uppercase text-muted-foreground">Jenis</dt>
                        <dd class="font-medium">{{ country.government_type || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-muted-foreground">Nasionalitas</dt>
                        <dd class="font-medium">{{ country.ideology || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-muted-foreground">Penguasa</dt>
                        <dd class="font-medium">
                            <span v-if="country.ruler_name">{{ country.ruler_title ? country.ruler_title + ' ' : '' }}{{ country.ruler_name }}</span>
                            <span v-else>—</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-muted-foreground">Ras Dominan</dt>
                        <dd class="font-medium">{{ country.dominant_race || '—' }}</dd>
                    </div>
                </dl>
            </div>

            <h2 class="flex items-center gap-2 text-lg font-semibold"><Building2 class="h-5 w-5 text-primary" /> Ibukota</h2>

            <!-- Untuk permulaan, tiap negara fokus pada satu kota: ibukotanya. -->
            <button
                v-if="capital"
                type="button"
                class="group flex w-full items-center justify-between rounded-xl border bg-card p-5 text-left transition hover:border-primary hover:bg-accent/40"
                @click="router.visit(route('admin.world.cities.show', capital.id))"
            >
                <div>
                    <div class="text-lg font-bold">{{ capital.name }}</div>
                    <div class="mt-1 flex flex-wrap gap-4 text-sm text-muted-foreground">
                        <span class="flex items-center gap-1"><Store class="h-4 w-4" /> {{ capital.places_count }} tempat</span>
                        <span class="flex items-center gap-1"><Tent class="h-4 w-4" /> {{ capital.villages_count }} desa</span>
                    </div>
                </div>
                <span class="flex items-center gap-1 text-sm font-medium text-primary">
                    Kelola Ibukota <ChevronRight class="h-4 w-4 transition group-hover:translate-x-0.5" />
                </span>
            </button>

            <div v-else class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                Negara ini belum punya ibukota.
            </div>

            <p class="text-xs text-muted-foreground">
                Semua aktivitas (Guild, Pasar, Blacksmith, Penginapan, dll.) dikelola di dalam ibukota. Struktur provinsi & banyak kota disiapkan untuk pengembangan berikutnya.
            </p>
        </div>
    </AppLayout>
</template>
