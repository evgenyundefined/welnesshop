<script setup>
import { onMounted, ref } from 'vue'
import api from '../api'
import { formatMoney } from '../money'
import { deliveryMethods } from '../labels'

const orders = ref([])
const loading = ref(true)

const statuses = {
    awaiting_payment: 'Ожидает оплаты',
    paid: 'Оплачен',
    cancelled: 'Отменён',
}

const payButton =
    'inline-block rounded-lg bg-gold-400 px-4 py-2 text-sm font-semibold text-ink-950 transition hover:bg-gold-300'

const detailsButton =
    'inline-block rounded-lg border border-ink-300 px-4 py-2 text-sm transition hover:border-ink-400'

onMounted(async () => {
    const { data } = await api.get('/orders')
    orders.value = data.data
    loading.value = false
})
</script>

<template>
    <h1 class="mb-6 text-2xl font-bold tracking-tight">Мои заказы</h1>

    <p v-if="loading" class="text-ink-400">Загрузка…</p>
    <p v-else-if="!orders.length" class="text-ink-500">Заказов пока нет.</p>

    <div v-else class="overflow-x-auto rounded-xl border border-ink-200 bg-white p-6">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-ink-200 text-xs uppercase tracking-wide text-ink-400">
                    <th class="py-2 text-left font-medium">Номер</th>
                    <th class="py-2 text-left font-medium">Позиций</th>
                    <th class="py-2 text-left font-medium">Сумма</th>
                    <th class="py-2 text-left font-medium">Доставка</th>
                    <th class="py-2 text-left font-medium">Статус</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="order in orders" :key="order.number" class="border-b border-ink-100 last:border-0">
                    <td class="py-3 pr-4 font-mono text-xs">{{ order.number }}</td>
                    <td class="py-3 pr-4">{{ order.items_count }}</td>
                    <td class="py-3 pr-4 font-semibold whitespace-nowrap">{{ formatMoney(order.total_minor, order.currency) }}</td>
                    <td class="py-3 pr-4">{{ deliveryMethods[order.delivery_method] ?? order.delivery_method }}</td>
                    <td class="py-3 pr-4">{{ statuses[order.status] ?? order.status }}</td>
                    <td class="py-3 text-right">
                        <RouterLink
                            :to="{ name: 'payment', params: { number: order.number } }"
                            :class="order.status === 'awaiting_payment' ? payButton : detailsButton"
                        >
                            {{ order.status === 'awaiting_payment' ? 'Оплатить' : 'Подробнее' }}
                        </RouterLink>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
