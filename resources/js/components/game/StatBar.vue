<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        label: string;
        value: number;
        max: number;
        color?: string;
        flash?: boolean;
    }>(),
    { color: 'bg-red-500', flash: false },
);

const pct = computed(() => {
    if (props.max <= 0) return 0;
    return Math.max(0, Math.min(100, (props.value / props.max) * 100));
});
</script>

<template>
    <div class="w-full" :class="{ 'animate-pulse': flash }">
        <div class="mb-1 flex justify-between text-xs font-medium text-muted-foreground">
            <span>{{ label }}</span>
            <span>{{ value }} / {{ max }}</span>
        </div>
        <div class="h-3 w-full overflow-hidden rounded-full bg-muted shadow-inner ring-1 ring-black/10 dark:ring-white/5">
            <div
                class="relative h-full rounded-full transition-all duration-500 ease-out"
                :class="color"
                :style="{ width: pct + '%' }"
            >
                <!-- kilau gauge -->
                <div class="absolute inset-x-0 top-0 h-1/2 rounded-t-full bg-white/25" />
            </div>
        </div>
    </div>
</template>
