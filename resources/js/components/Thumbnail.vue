<script setup>
defineProps({
    url: { type: String, default: null },
    alt: { type: String, default: '' },
})
</script>

<template>
    <span class="block size-14 shrink-0 overflow-hidden rounded-lg bg-ink-100">
        <img v-if="url" :src="url" :alt="alt" loading="lazy" class="size-full object-cover">
        <span v-else class="flex size-full items-center justify-center px-1 text-center text-[10px] leading-tight text-ink-400">
            Без фото
        </span>
    </span>
</template>
