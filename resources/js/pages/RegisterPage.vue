<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { session } from '../stores/session'
import { fieldErrorsFrom, messageFrom } from '../api'
import FormField from '../components/FormField.vue'

const router = useRouter()
const route = useRoute()

const form = reactive({ name: '', email: '', phone: '', password: '', password_confirmation: '' })
const errors = ref({})
const message = ref('')
const pending = ref(false)

const inputClass =
    'w-full rounded-lg border border-ink-300 bg-white px-3 py-2 text-sm outline-none focus:border-gold-500 focus:ring-1 focus:ring-gold-500'

async function submit() {
    errors.value = {}
    message.value = ''
    pending.value = true

    try {
        await session.register({ ...form })
        router.push(route.query.redirect ?? { name: 'catalog' })
    } catch (e) {
        errors.value = fieldErrorsFrom(e)
        message.value = messageFrom(e, 'Не удалось зарегистрироваться')
    } finally {
        pending.value = false
    }
}
</script>

<template>
    <h1 class="mb-6 text-2xl font-bold tracking-tight">Регистрация</h1>

    <form class="max-w-md space-y-4 rounded-xl border border-ink-200 bg-white p-6" @submit.prevent="submit">
        <FormField id="name" label="Имя" :error="errors.name">
            <input id="name" v-model="form.name" type="text" autocomplete="name" required :class="inputClass">
        </FormField>

        <FormField id="email" label="E-mail" :error="errors.email">
            <input id="email" v-model="form.email" type="email" autocomplete="email" required :class="inputClass">
        </FormField>

        <FormField id="phone" label="Телефон" :error="errors.phone">
            <input id="phone" v-model="form.phone" type="tel" autocomplete="tel" :class="inputClass">
        </FormField>

        <FormField id="password" label="Пароль" :error="errors.password">
            <input id="password" v-model="form.password" type="password" autocomplete="new-password" required :class="inputClass">
        </FormField>

        <FormField id="password_confirmation" label="Повторите пароль">
            <input
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                autocomplete="new-password"
                required
                :class="inputClass"
            >
        </FormField>

        <p v-if="message" class="text-sm text-red-700">{{ message }}</p>

        <button
            type="submit"
            class="w-full rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-ink-950 transition hover:bg-gold-300 disabled:bg-ink-200 disabled:text-ink-400"
            :disabled="pending"
        >
            Зарегистрироваться
        </button>

        <p class="text-sm text-ink-500">
            Уже есть аккаунт?
            <RouterLink :to="{ name: 'login' }" class="text-gold-700 underline">Войти</RouterLink>
        </p>
    </form>
</template>
