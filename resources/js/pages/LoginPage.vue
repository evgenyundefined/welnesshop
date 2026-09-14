<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { session } from '../stores/session'
import { fieldErrorsFrom, messageFrom } from '../api'
import FormField from '../components/FormField.vue'
import { useFormErrors } from '../formErrors'
import PasswordInput from '../components/PasswordInput.vue'

const router = useRouter()
const route = useRoute()

const form = reactive({ email: '', password: '', remember: false })
const { errors, message, reset: resetErrors } = useFormErrors(form)
const pending = ref(false)

const inputClass =
    'w-full rounded-lg border border-ink-300 bg-white px-3 py-2 text-sm outline-none focus:border-gold-500 focus:ring-1 focus:ring-gold-500'

async function submit() {
    resetErrors()
    pending.value = true

    try {
        await session.login({ ...form })
        router.push(route.query.redirect ?? { name: 'catalog' })
    } catch (e) {
        errors.value = fieldErrorsFrom(e)
        message.value = messageFrom(e, 'Не удалось войти')
    } finally {
        pending.value = false
    }
}
</script>

<template>
    <h1 class="mb-6 text-2xl font-bold tracking-tight">Вход</h1>

    <form class="max-w-md space-y-4 rounded-xl border border-ink-200 bg-white p-6" @submit.prevent="submit">
        <FormField id="email" label="E-mail" :error="errors.email">
            <input id="email" v-model="form.email" type="email" autocomplete="email" required :class="inputClass">
        </FormField>

        <FormField id="password" label="Пароль" :error="errors.password">
            <PasswordInput
                id="password"
                v-model="form.password"
                autocomplete="current-password"
                required
                :class="inputClass"
            />
        </FormField>

        <label class="flex items-center gap-2 text-sm text-ink-500">
            <input v-model="form.remember" type="checkbox" class="size-4 accent-gold-500"> Запомнить меня
        </label>

        <p v-if="message" class="text-sm text-red-700">{{ message }}</p>

        <button
            type="submit"
            class="w-full rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-ink-950 transition hover:bg-gold-300 disabled:bg-ink-200 disabled:text-ink-400"
            :disabled="pending"
        >
            Войти
        </button>

        <p class="text-sm text-ink-500">
            Нет аккаунта?
            <RouterLink :to="{ name: 'register' }" class="text-gold-700 underline">Зарегистрироваться</RouterLink>
        </p>
    </form>
</template>
