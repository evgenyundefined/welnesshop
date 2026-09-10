<script setup>
import { computed } from 'vue'

const props = defineProps({
    series: { type: Array, required: true },
    tooltip: { type: Function, required: true },
})

const peak = computed(() => Math.max(1, ...props.series.map(point => point.value)))

const shortDate = date => new Date(date).toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' })
</script>

<template>
    <div>
        <div class="flex h-40 items-stretch gap-px overflow-x-auto">
            <div
                v-for="point in series"
                :key="point.date"
                class="flex min-w-2 flex-1 flex-col justify-end"
                :title="tooltip(point)"
            >
                <div
                    class="rounded-t transition"
                    :class="point.value ? 'bg-gold-400 hover:bg-gold-500' : 'bg-ink-200'"
                    :style="{ height: `${Math.max(2, (point.value / peak) * 100)}%` }"
                ></div>
            </div>
        </div>

        <div class="mt-2 flex justify-between text-xs text-ink-400">
            <span>{{ shortDate(series[0].date) }}</span>
            <span>{{ shortDate(series[series.length - 1].date) }}</span>
        </div>
    </div>
</template>
