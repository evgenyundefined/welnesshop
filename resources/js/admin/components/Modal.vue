<script setup>
defineProps({ title: { type: String, required: true } })
defineEmits(['close'])
</script>

<template>
    <div class="fixed inset-0 z-20 flex items-start justify-center overflow-y-auto bg-ink-900/40 p-6">
        <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
            <div class="mb-4 flex items-start justify-between gap-4">
                <h2 class="text-lg font-semibold">{{ title }}</h2>
                <button type="button" class="text-ink-400 hover:text-ink-700" @click="$emit('close')">✕</button>
            </div>
            <slot />
        </div>
    </div>
</template>
