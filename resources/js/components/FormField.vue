<script setup>
defineProps({
    label: { type: String, required: true },
    id: { type: String, required: true },
    error: { type: String, default: '' },
})
</script>

<template>
    <div>
        <label :for="id" class="mb-1.5 block text-sm text-ink-500">{{ label }}</label>
        <slot />
        <p v-if="error" class="mt-1 text-sm text-red-700">{{ error }}</p>
    </div>
</template>
