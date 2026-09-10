<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import api, { messageFrom } from '../api'
import { card, input, orderStatuses, td, th } from '../ui'
import BarChart from '../components/BarChart.vue'
import { formatMoney } from '../../money'

const periods = {
    week: '7 дней',
    month: '30 дней',
    quarter: '90 дней',
    all: 'Всё время',
}

const period = ref('month')
const stats = ref(null)
const error = ref('')

async function load() {
    error.value = ''

    try {
        const { data } = await api.get('/statistics', { params: { period: period.value } })
        stats.value = data.data
    } catch (e) {
        error.value = messageFrom(e, 'Не удалось загрузить статистику')
    }
}

watch(period, load)
onMounted(load)

const currency = computed(() => stats.value?.revenue.currency)

const tiles = computed(() => {
    if (!stats.value) {
        return []
    }

    const { orders, revenue, customers } = stats.value

    return [
        { label: 'Заказов', value: orders.total },
        { label: orderStatuses.paid, value: orders.paid },
        { label: orderStatuses.awaiting_payment, value: orders.awaiting_payment },
        { label: orderStatuses.cancelled, value: orders.cancelled },
        { label: 'Выручка (оплачено)', value: formatMoney(revenue.paid_minor, currency.value) },
        {
            label: 'Средний чек',
            value: revenue.average_minor === null ? '—' : formatMoney(revenue.average_minor, currency.value),
        },
        { label: 'Новых покупателей', value: customers.new },
        { label: 'Заблокировано', value: customers.blocked },
    ]
})

const shortDate = date => new Date(date).toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' })
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Статистика</h1>
                <p v-if="stats" class="text-sm text-ink-400">
                    {{ stats.since ? `с ${shortDate(stats.since)}` : 'за всё время работы магазина' }}
                </p>
            </div>

            <select v-model="period" :class="input" class="ml-auto max-w-40">
                <option v-for="(label, value) in periods" :key="value" :value="value">{{ label }}</option>
            </select>
        </div>

        <p v-if="error" class="text-sm text-red-700">{{ error }}</p>

        <template v-if="stats">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="tile in tiles" :key="tile.label" :class="card" class="p-4">
                    <p class="text-xs uppercase tracking-wide text-ink-400">{{ tile.label }}</p>
                    <p class="mt-1 text-xl font-bold tabular-nums">{{ tile.value }}</p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div :class="card" class="p-5">
                    <h2 class="mb-4 font-semibold">Заказы по дням</h2>

                    <BarChart
                        :series="stats.daily.map(day => ({ date: day.date, value: day.orders }))"
                        :tooltip="point => `${shortDate(point.date)}: ${point.value} заказов`"
                    />
                </div>

                <div :class="card" class="p-5">
                    <div class="mb-4">
                        <h2 class="font-semibold">Просмотры товаров по дням</h2>
                        <p class="text-xs text-ink-400">Открытия карточек, суммарно по всем товарам.</p>
                    </div>

                    <BarChart
                        :series="stats.views_daily.map(day => ({ date: day.date, value: day.views }))"
                        :tooltip="point => `${shortDate(point.date)}: ${point.value} просмотров`"
                    />
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div :class="card" class="p-5">
                    <h2 class="mb-3 font-semibold">Продаётся лучше всего</h2>

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-ink-200">
                                <th :class="th">Товар</th>
                                <th :class="th">Штук</th>
                                <th :class="th">Сумма</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="product in stats.top_products"
                                :key="product.slug"
                                class="border-b border-ink-100 last:border-0"
                            >
                                <td :class="td">{{ product.name }}</td>
                                <td :class="td" class="tabular-nums">{{ product.quantity }}</td>
                                <td :class="td" class="whitespace-nowrap tabular-nums">
                                    {{ formatMoney(product.revenue_minor, currency) }}
                                </td>
                            </tr>
                            <tr v-if="!stats.top_products.length">
                                <td :class="td" colspan="3" class="text-ink-400">За период ничего не продано.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div :class="card" class="p-5">
                    <div class="mb-3">
                        <h2 class="font-semibold">Чаще всего открывают</h2>
                        <p class="text-xs text-ink-400">Счётчик открытий карточки за всё время, без учёта уникальности.</p>
                    </div>

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-ink-200">
                                <th :class="th">Товар</th>
                                <th :class="th">Просмотров</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="product in stats.most_viewed"
                                :key="product.id"
                                class="border-b border-ink-100 last:border-0"
                            >
                                <td :class="td">
                                    <RouterLink
                                        :to="{ name: 'admin.products.edit', params: { id: product.id } }"
                                        class="hover:text-gold-700"
                                    >
                                        {{ product.name }}
                                    </RouterLink>
                                </td>
                                <td :class="td" class="tabular-nums">{{ product.views }}</td>
                            </tr>
                            <tr v-if="!stats.most_viewed.length">
                                <td :class="td" colspan="2" class="text-ink-400">Карточки товаров ещё не открывали.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>
