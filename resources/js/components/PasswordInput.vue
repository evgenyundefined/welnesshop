<script setup>
import { ref } from 'vue'

defineOptions({ inheritAttrs: false })
defineProps({ modelValue: { type: String, default: '' } })
defineEmits(['update:modelValue'])

const visible = ref(false)
</script>

<template>
    <div class="relative">
        <input
            :type="visible ? 'text' : 'password'"
            :value="modelValue"
            v-bind="$attrs"
            class="pr-11"
            @input="$emit('update:modelValue', $event.target.value)"
        >
        <button
            type="button"
            class="absolute inset-y-0 right-0 flex items-center px-3 text-ink-400 transition hover:text-ink-700"
            :aria-label="visible ? 'Скрыть пароль' : 'Показать пароль'"
            :title="visible ? 'Скрыть пароль' : 'Показать пароль'"
            tabindex="-1"
            @click="visible = !visible"
        >
            <svg v-if="visible" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M3 3l18 18" stroke-linecap="round" />
                <path d="M10.6 5.2A9.9 9.9 0 0 1 12 5c5 0 9 4.5 9.9 6.4.2.4.2.8 0 1.2-.3.7-1.1 1.9-2.3 3.1" stroke-linecap="round" />
                <path d="M6.5 6.9C4.4 8.4 2.8 10.5 2.1 11.4c-.2.4-.2.8 0 1.2C3 14.5 7 19 12 19c1.6 0 3-.4 4.3-1.1" stroke-linecap="round" />
                <path d="M9.9 9.9a3 3 0 1 0 4.2 4.2" stroke-linecap="round" />
            </svg>
            <svg v-else class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M2.1 12.6c-.2-.4-.2-.8 0-1.2C3 9.5 7 5 12 5s9 4.5 9.9 6.4c.2.4.2.8 0 1.2C21 14.5 17 19 12 19s-9-4.5-9.9-6.4Z" />
                <circle cx="12" cy="12" r="3" />
            </svg>
        </button>
    </div>
</template>
