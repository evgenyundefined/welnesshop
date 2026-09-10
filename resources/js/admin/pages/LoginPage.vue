<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { session } from '../stores/session'
import { fieldErrorsFrom, messageFrom } from '../api'
import { card, input, primaryButton } from '../ui'

const router = useRouter()
const route = useRoute()

const form = reactive({ email: '', password: '' })
const errors = ref({})
const message = ref('')
const pending = ref(false)

async function submit() {
    errors.value = {}
    message.value = ''
    pending.value = true

    try {
        await session.login({ ...form })
        router.push(route.query.redirect ?? { name: 'admin.statistics' })
    } catch (e) {
        errors.value = fieldErrorsFrom(e)
        message.value = messageFrom(e, 'Не удалось войти')
    } finally {
        pending.value = false
    }
}
</script>

<template>
    <div class="flex min-h-full items-center justify-center bg-ink-950 p-6">
        <form :class="card" class="w-full max-w-sm space-y-4 p-6" @submit.prevent="submit">
            <div>
                <h1 class="text-xl font-bold tracking-tight">Админка</h1>
                <p class="text-sm text-gold-700">agelesscode</p>
            </div>

            <div>
                <label for="email" class="mb-1.5 block text-sm text-ink-500">E-mail</label>
                <input id="email" v-model="form.email" type="email" autocomplete="username" required :class="input">
                <p v-if="errors.email" class="mt-1 text-sm text-red-700">{{ errors.email }}</p>
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm text-ink-500">Пароль</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                    :class="input"
                >
                <p v-if="errors.password" class="mt-1 text-sm text-red-700">{{ errors.password }}</p>
            </div>

            <p v-if="message" class="text-sm text-red-700">{{ message }}</p>

            <button type="submit" :class="primaryButton" class="w-full" :disabled="pending">Войти</button>
        </form>
    </div>
</template>
