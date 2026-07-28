<script setup lang="ts">
// Navigasi halaman sederhana (dipakai daftar topik & pesan Balai Warta).
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps<{
    current: number;
    last: number;
    prev: string | null;
    next: string | null;
}>();

function pergi(url: string | null) {
    if (url) router.visit(url, { preserveScroll: false });
}
</script>

<template>
    <div v-if="props.last > 1" class="flex items-center justify-center gap-3 text-sm">
        <Button size="sm" variant="outline" :disabled="!props.prev" @click="pergi(props.prev)">
            <ChevronLeft class="h-4 w-4" />
        </Button>
        <span class="text-muted-foreground">Halaman {{ props.current }} dari {{ props.last }}</span>
        <Button size="sm" variant="outline" :disabled="!props.next" @click="pergi(props.next)">
            <ChevronRight class="h-4 w-4" />
        </Button>
    </div>
</template>
