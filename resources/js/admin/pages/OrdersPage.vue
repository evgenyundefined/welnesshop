<script setup>
import { onMounted, ref, watch } from 'vue'
import api, { messageFrom } from '../api'
import Pagination from '../components/Pagination.vue'
import {
    card,
    dangerButton,
    deliveryMethods,
    ghostButton,
    input,
    orderStatuses,
    primaryButton,
    td,
    th,
} from '../ui'
import { formatMoney } from '../../money'

const orders = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const search = ref('')
const status = ref('')
const page = ref(1)
const error = ref('')

async function load() {
    const { data } = await api.get('/orders', {
        params: { search: search.value || undefined, status: status.value || undefined, page: page.value },
    })
    orders.value = data.data
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

watch(status, () => {
    page.value = 1
    load()
})

watch(page, load)
onMounted(load)

async function remove(order) {
    if (!window.confirm(`Удалить заказ ${order.number}? Товары вернутся на остаток.`)) {
        return
    }

    error.value = ''

    try {
        await api.delete(`/orders/${order.id}`)
        await load()
    } catch (e) {
        error.value = messageFrom(e, 'Не удалось удалить заказ')
    }
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-bold tracking-tight">Заказы</h1>
            <RouterLink :to="{ name: 'admin.orders.create' }" :class="primaryButton" class="ml-auto">
                Создать заказ
            </RouterLink>
        </div>

        <div class="flex flex-wrap gap-3">
            <input v-model="search" type="search" placeholder="Номер, имя или e-mail" :class="input" class="max-w-xs">
            <select v-model="status" :class="input" class="max-w-xs">
                <option value="">Любой статус</option>
                <option v-for="(label, value) in orderStatuses" :key="value" :value="value">{{ label }}</option>
            </select>
        </div>

        <p v-if="error" class="text-sm text-red-700">{{ error }}</p>

        <div :class="card" class="overflow-x-auto p-5">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink-200">
                        <th :class="th">Номер</th>
                        <th :class="th">Покупатель</th>
                        <th :class="th">Позиций</th>
                        <th :class="th">Сумма</th>
                        <th :class="th">Доставка</th>
                        <th :class="th">Статус</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="order in orders" :key="order.id" class="border-b border-ink-100 last:border-0">
                        <td :class="td">
                            <RouterLink
                                :to="{ name: 'admin.orders.show', params: { id: order.id } }"
                                class="font-mono text-xs hover:text-gold-700"
                            >
                                {{ order.number }}
                            </RouterLink>
                        </td>
                        <td :class="td" class="text-ink-500">{{ order.customer?.name ?? order.contact_name }}</td>
                        <td :class="td">{{ order.items_count }}</td>
                        <td :class="td" class="whitespace-nowrap">{{ formatMoney(order.total_minor, order.currency) }}</td>
                        <td :class="td">{{ deliveryMethods[order.delivery_method] ?? order.delivery_method }}</td>
                        <td :class="td">{{ orderStatuses[order.status] ?? order.status }}</td>
                        <td :class="td" class="text-right whitespace-nowrap">
                            <RouterLink
                                :to="{ name: 'admin.orders.show', params: { id: order.id } }"
                                :class="ghostButton"
                            >
                                Открыть
                            </RouterLink>
                            <button type="button" :class="dangerButton" class="ml-2" @click="remove(order)">Удалить</button>
                        </td>
                    </tr>
                    <tr v-if="!orders.length">
                        <td :class="td" colspan="7" class="text-ink-400">Заказов не найдено.</td>
                    </tr>
                </tbody>
            </table>

            <Pagination v-model:page="page" :meta="meta" />
        </div>
    </div>
</template>
