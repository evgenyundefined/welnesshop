<script setup>
import { ghostButton } from '../ui'

defineProps({ meta: { type: Object, required: true } })
const page = defineModel('page', { type: Number, required: true })
</script>

<template>
    <div v-if="meta.last_page > 1" class="mt-4 flex items-center justify-center gap-3 text-sm text-ink-500">
        <button type="button" :class="ghostButton" :disabled="page <= 1" @click="page--">Назад</button>
        <span>{{ meta.current_page }} / {{ meta.last_page }} · всего {{ meta.total }}</span>
        <button type="button" :class="ghostButton" :disabled="page >= meta.last_page" @click="page++">Вперёд</button>
    </div>
</template>
