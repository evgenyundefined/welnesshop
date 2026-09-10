<script setup>
import { onMounted, ref } from 'vue'
import api, { messageFrom } from '../api'
import { formatMoney } from '../money'
import { deliveryMethods } from '../labels'
import Thumbnail from '../components/Thumbnail.vue'

const props = defineProps({ number: { type: String, required: true } })

const order = ref(null)
const payment = ref(null)
const error = ref('')
const pending = ref(false)

const orderStatuses = {
    awaiting_payment: 'Ожидает оплаты',
    paid: 'Оплачен',
    cancelled: 'Отменён',
}

const paymentStatuses = {
    pending: 'Оплата не проведена',
    awaiting_gateway: 'Ожидает подтверждения платёжной системы',
    succeeded: 'Оплачен',
    failed: 'Платёж отклонён',
}

onMounted(async () => {
    const { data } = await api.get(`/orders/${props.number}`)
    order.value = data.data
})

async function pay() {
    error.value = ''
    pending.value = true

    try {
        const { data } = await api.post(`/orders/${props.number}/pay`)
        payment.value = data.data

        if (payment.value.confirmation_url) {
            window.location.assign(payment.value.confirmation_url)
        }
    } catch (e) {
        error.value = messageFrom(e, 'Не удалось создать платёж')
    } finally {
        pending.value = false
    }
}
</script>

<template>
    <div v-if="order" class="space-y-5">
        <h1 class="text-2xl font-bold tracking-tight">Оплата заказа {{ order.number }}</h1>

        <p class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Платёжный провайдер ещё не подключён. Заказ создан и зарезервирован, оплата станет доступна после
            подключения платёжного шлюза.
        </p>

        <dl class="grid gap-3 rounded-xl border border-ink-200 bg-white p-6 text-sm sm:grid-cols-[200px_1fr]">
            <dt class="text-ink-400">Статус заказа</dt>
            <dd class="font-semibold">{{ orderStatuses[order.status] ?? order.status }}</dd>

            <dt class="text-ink-400">Статус оплаты</dt>
            <dd class="font-semibold">{{ paymentStatuses[order.payment_status] ?? order.payment_status }}</dd>

            <dt class="text-ink-400">Получатель</dt>
            <dd>{{ order.contact_name }}, {{ order.contact_phone }}</dd>

            <dt class="text-ink-400">Доставка</dt>
            <dd>{{ deliveryMethods[order.delivery_method] ?? order.delivery_method }}</dd>

            <dt class="text-ink-400">Адрес доставки</dt>
            <dd>{{ order.shipping_address }}</dd>

            <template v-if="order.comment">
                <dt class="text-ink-400">Комментарий</dt>
                <dd>{{ order.comment }}</dd>
            </template>
        </dl>

        <div class="rounded-xl border border-ink-200 bg-white p-6">
            <h2 class="mb-3 font-semibold">Состав заказа</h2>
            <table class="w-full text-sm">
                <tbody>
                    <tr v-for="item in order.items" :key="item.id" class="border-b border-ink-100 last:border-0">
                        <td class="py-3 pr-4">
                            <RouterLink
                                :to="{ name: 'product', params: { slug: item.product_slug } }"
                                class="flex items-center gap-3 hover:text-gold-700"
                            >
                                <Thumbnail :url="item.cover_url" :alt="item.product_name" />
                                <span>{{ item.product_name }}</span>
                            </RouterLink>
                        </td>
                        <td class="py-3 pr-4 whitespace-nowrap text-ink-500">{{ item.quantity }} шт.</td>
                        <td class="py-3 text-right font-semibold whitespace-nowrap">{{ formatMoney(item.total_minor, order.currency) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-ink-200 bg-white p-6">
            <span class="text-lg font-bold">К оплате: {{ formatMoney(order.total_minor, order.currency) }}</span>
            <button
                type="button"
                class="rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-ink-950 transition hover:bg-gold-300 disabled:bg-ink-200 disabled:text-ink-400"
                :disabled="pending"
                @click="pay"
            >
                Оплатить
            </button>
        </div>

        <p v-if="payment" class="text-sm text-ink-500">{{ payment.message }}</p>
        <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
    </div>

    <p v-else class="text-ink-400">Загрузка…</p>
</template>
