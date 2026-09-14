<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import api, { fieldErrorsFrom, messageFrom } from '../api'
import Modal from '../components/Modal.vue'
import Pagination from '../components/Pagination.vue'
import { card, dangerButton, ghostButton, input, primaryButton, td, th } from '../ui'
import PhoneInput from '../../components/PhoneInput.vue'

const customers = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const search = ref('')
const blocked = ref('')
const page = ref(1)
const error = ref('')

const editing = ref(null)
const form = reactive({ name: '', email: '', phone: '', password: '' })
const formErrors = ref({})
const formMessage = ref('')
const saving = ref(false)

async function load() {
    const { data } = await api.get('/customers', {
        params: {
            search: search.value || undefined,
            blocked: blocked.value === '' ? undefined : blocked.value,
            page: page.value,
        },
    })
    customers.value = data.data
    meta.value = data.meta
}

let searchTimer
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        page.value = 1
        load()
    }, 300)
})

watch(blocked, () => {
    page.value = 1
    load()
})

watch(page, load)
onMounted(load)

function openEdit(customer) {
    editing.value = customer
    Object.assign(form, {
        name: customer.name,
        email: customer.email,
        phone: customer.phone ?? '',
        password: '',
    })
    formErrors.value = {}
    formMessage.value = ''
}

async function save() {
    formErrors.value = {}
    formMessage.value = ''
    saving.value = true

    try {
        await api.patch(`/customers/${editing.value.id}`, {
            name: form.name,
            email: form.email,
            phone: form.phone || null,
            password: form.password || null,
        })

        editing.value = null
        await load()
    } catch (e) {
        formErrors.value = fieldErrorsFrom(e)
        formMessage.value = messageFrom(e, 'Не удалось сохранить покупателя')
    } finally {
        saving.value = false
    }
}

async function toggleBlock(customer) {
    error.value = ''

    try {
        if (customer.is_blocked) {
            await api.delete(`/customers/${customer.id}/block`)
        } else {
            await api.post(`/customers/${customer.id}/block`)
        }

        await load()
    } catch (e) {
        error.value = messageFrom(e, 'Не удалось изменить статус покупателя')
    }
}
</script>

<template>
    <div class="space-y-4">
        <h1 class="text-2xl font-bold tracking-tight">Покупатели</h1>

        <div class="flex flex-wrap gap-3">
            <input v-model="search" type="search" placeholder="Имя, e-mail или телефон" :class="input" class="max-w-xs">
            <select v-model="blocked" :class="input" class="max-w-xs">
                <option value="">Все</option>
                <option value="0">Только активные</option>
                <option value="1">Только заблокированные</option>
            </select>
        </div>

        <p v-if="error" class="text-sm text-red-700">{{ error }}</p>

        <div :class="card" class="overflow-x-auto p-5">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink-200">
                        <th :class="th">Имя</th>
                        <th :class="th">E-mail</th>
                        <th :class="th">Телефон</th>
                        <th :class="th">Заказов</th>
                        <th :class="th">Статус</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="customer in customers" :key="customer.id" class="border-b border-ink-100 last:border-0">
                        <td :class="td">{{ customer.name }}</td>
                        <td :class="td" class="text-ink-500">{{ customer.email }}</td>
                        <td :class="td" class="text-ink-500">{{ customer.phone ?? '—' }}</td>
                        <td :class="td">{{ customer.orders_count }}</td>
                        <td :class="td">
                            <span
                                class="rounded-full px-2 py-1 text-xs font-medium"
                                :class="customer.is_blocked
                                    ? 'bg-red-50 text-red-700'
                                    : 'bg-ink-100 text-ink-600'"
                            >
                                {{ customer.is_blocked ? 'Заблокирован' : 'Активен' }}
                            </span>
                        </td>
                        <td :class="td" class="text-right whitespace-nowrap">
                            <button type="button" :class="ghostButton" @click="openEdit(customer)">Изменить</button>
                            <button
                                type="button"
                                class="ml-2"
                                :class="customer.is_blocked ? ghostButton : dangerButton"
                                @click="toggleBlock(customer)"
                            >
                                {{ customer.is_blocked ? 'Разблокировать' : 'Заблокировать' }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!customers.length">
                        <td :class="td" colspan="6" class="text-ink-400">Ничего не найдено.</td>
                    </tr>
                </tbody>
            </table>

            <Pagination v-model:page="page" :meta="meta" />
        </div>

        <Modal v-if="editing" title="Покупатель" @close="editing = null">
            <form class="space-y-4" @submit.prevent="save">
                <div>
                    <label for="c-name" class="mb-1.5 block text-sm text-ink-500">Имя</label>
                    <input id="c-name" v-model="form.name" type="text" required :class="input">
                    <p v-if="formErrors.name" class="mt-1 text-sm text-red-700">{{ formErrors.name }}</p>
                </div>

                <div>
                    <label for="c-email" class="mb-1.5 block text-sm text-ink-500">E-mail</label>
                    <input id="c-email" v-model="form.email" type="email" required :class="input">
                    <p v-if="formErrors.email" class="mt-1 text-sm text-red-700">{{ formErrors.email }}</p>
                </div>

                <div>
                    <label for="c-phone" class="mb-1.5 block text-sm text-ink-500">Телефон</label>
                    <PhoneInput id="c-phone" v-model="form.phone" :class="input" />
                    <p v-if="formErrors.phone" class="mt-1 text-sm text-red-700">{{ formErrors.phone }}</p>
                </div>

                <div>
                    <label for="c-password" class="mb-1.5 block text-sm text-ink-500">
                        Новый пароль (оставьте пустым, чтобы не менять)
                    </label>
                    <input id="c-password" v-model="form.password" type="password" autocomplete="new-password" :class="input">
                    <p v-if="formErrors.password" class="mt-1 text-sm text-red-700">{{ formErrors.password }}</p>
                </div>

                <p v-if="formMessage" class="text-sm text-red-700">{{ formMessage }}</p>

                <div class="flex justify-end gap-2">
                    <button type="button" :class="ghostButton" @click="editing = null">Отмена</button>
                    <button type="submit" :class="primaryButton" :disabled="saving">Сохранить</button>
                </div>
            </form>
        </Modal>
    </div>
</template>
