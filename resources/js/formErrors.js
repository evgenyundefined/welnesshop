import { ref, watch } from 'vue'

/**
 * A field that has just been corrected should stop complaining. Server errors
 * live until the next submit otherwise, which reads as if the fix did not
 * count — the buyer picks a city and the form still says it is missing.
 */
export function useFormErrors(form) {
    const errors = ref({})
    const message = ref('')

    Object.keys(form).forEach((field) => {
        watch(() => form[field], () => {
            if (errors.value[field] !== undefined) {
                delete errors.value[field]
            }

            message.value = ''
        })
    })

    function reset() {
        errors.value = {}
        message.value = ''
    }

    return { errors, message, reset }
}
