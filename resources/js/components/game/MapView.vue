<script setup lang="ts">
import { MapPin } from 'lucide-vue-next';
import type { Component } from 'vue';

export interface MapMarker {
    key: string;
    x: number;
    y: number;
    label: string;
    icon?: Component;
    here?: boolean;
}

// Peta bergambar dengan titik-titik berposisi persen (0–100). `pickable`
// dipakai Panel Dewa: klik di peta memancarkan posisi baru.
const props = defineProps<{ image: string | null; markers: MapMarker[]; pickable?: boolean }>();
const emit = defineEmits<{ select: [marker: MapMarker]; pick: [pos: { x: number; y: number }] }>();

function onClick(e: MouseEvent) {
    if (!props.pickable) return;
    const r = (e.currentTarget as HTMLElement).getBoundingClientRect();
    const pct = (v: number) => Math.round(Math.min(100, Math.max(0, v)) * 100) / 100;
    emit('pick', { x: pct(((e.clientX - r.left) / r.width) * 100), y: pct(((e.clientY - r.top) / r.height) * 100) });
}
</script>

<template>
    <!-- Di layar sempit peta lebih lebar dari layar dan bisa digeser.
         contain:inline-size supaya lebar minimum peta tak ikut melebarkan halaman. -->
    <div class="overflow-x-auto rounded-xl border bg-muted [contain:inline-size]">
        <div class="relative aspect-[3/2] min-w-[640px] md:min-w-0" :class="{ 'cursor-crosshair': pickable }" @click="onClick">
            <img v-if="image" :src="image" alt="" draggable="false" class="absolute inset-0 h-full w-full select-none object-cover" />
            <div v-else class="absolute inset-0 flex items-center justify-center text-sm text-muted-foreground">Peta belum tersedia.</div>

            <button
                v-for="m in markers"
                :key="m.key"
                type="button"
                class="group absolute -translate-x-1/2 -translate-y-1/2 focus:outline-none"
                :style="{ left: `${m.x}%`, top: `${m.y}%` }"
                :aria-label="m.label"
                @click.stop="emit('select', m)"
            >
                <span v-if="m.here" class="absolute inset-0 rounded-full bg-amber-300/60 motion-safe:animate-ping" />
                <span
                    class="relative flex h-9 w-9 items-center justify-center rounded-full border-2 border-amber-100 bg-amber-700 text-amber-50 shadow-lg ring-2 ring-black/40 transition group-hover:scale-110 group-focus-visible:ring-amber-300"
                >
                    <component :is="m.icon ?? MapPin" class="h-4 w-4" />
                </span>
                <span
                    class="pointer-events-none absolute left-1/2 top-full mt-1 -translate-x-1/2 whitespace-nowrap rounded bg-black/75 px-2 py-0.5 font-display text-xs text-amber-50"
                >
                    {{ m.label }}<template v-if="m.here"> · Kau di sini</template>
                </span>
            </button>
        </div>
    </div>
</template>
