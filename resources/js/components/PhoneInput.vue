<script setup>
import { computed } from 'vue'

defineOptions({ inheritAttrs: false })

const props = defineProps({ modelValue: { type: String, default: '' } })
const emit = defineEmits(['update:modelValue'])

/**
 * A Russian number and nothing else: whatever is typed or pasted is reduced to
 * its digits and laid back out as +7 XXX XXX XX XX. The server applies the same
 * rule, so what is shown is what is stored.
 */
function digitsOf(value) {
    const digits = (value ?? '').replace(/\D/g, '')

    if (digits.startsWith('8')) {
        return digits.slice(1, 11)
    }

    if (digits.startsWith('7')) {
        return digits.slice(1, 11)
    }

    return digits.slice(0, 10)
}

function mask(value) {
    const digits = digitsOf(value)

    if (!digits) {
        return ''
    }

    const parts = [digits.slice(0, 3), digits.slice(3, 6), digits.slice(6, 8), digits.slice(8, 10)]

    return '+7 ' + parts.filter(Boolean).join(' ')
}

const shown = computed(() => mask(props.modelValue))

function onInput(event) {
    const masked = mask(event.target.value)

    // The field is controlled: re-render it so a deleted separator cannot
    // leave the caret editing a shape the server would reject.
    event.target.value = masked
    emit('update:modelValue', masked)
}
</script>

<template>
    <input
        type="tel"
        inputmode="tel"
        autocomplete="tel"
        placeholder="+7 999 123 45 67"
        :value="shown"
        v-bind="$attrs"
        @input="onInput"
    >
</template>
