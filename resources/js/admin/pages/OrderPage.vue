<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import api, { fieldErrorsFrom, messageFrom } from '../api'
import {
    card,
    dangerButton,
    ghostButton,
    input,
    orderStatuses,
    deliveryMethods,
    paymentMethods,
    paymentStatuses,
    primaryButton,
    td,
    th,
} from '../ui'
import { formatMoney } from '../../money'

const props = defineProps({ id: { type: String, required: true } })

const router = useRouter()
const order = ref(null)
const errors = ref({})
const message = ref('')
const saved = ref(false)
const saving = ref(false)

const form = reactive({
    status: '',
    payment_status: '',
    payment_method: '',
    delivery_method: '',
    delivery_cost_minor: 0,
    contact_name: '',
    contact_email: '',
    contact_phone: '',
    shipping_address: '',
    comment: '',
})

onMounted(async () => {
    const { data } = await api.get(`/orders/${props.id}`)
    order.value = data.data
    Object.assign(form, {
        status: data.data.status,
        payment_status: data.data.payment_status,
        payment_method: data.data.payment_method,
        delivery_method: data.data.delivery_method,
        delivery_cost_minor: data.data.delivery_cost_minor,
        contact_name: data.data.contact_name,
        contact_email: data.data.contact_email,
        contact_phone: data.data.contact_phone,
        shipping_address: data.data.shipping_address,
        comment: data.data.comment ?? '',
    })
})

async function submit() {
    errors.value = {}
    message.value = ''
    saved.value = false
    saving.value = true

    try {
        const { data } = await api.patch(`/orders/${props.id}`, { ...form, comment: form.comment || null })
        order.value = data.data
        saved.value = true
    } catch (e) {
        errors.value = fieldErrorsFrom(e)
        message.value = messageFrom(e, 'Не удалось сохранить заказ')
    } finally {
        saving.value = false
    }
}

async function remove() {
    if (!window.confirm(`Удалить заказ ${order.value.number}? Товары вернутся на остаток.`)) {
        return
    }

    message.value = ''

    try {
        await api.delete(`/orders/${props.id}`)
        router.push({ name: 'admin.orders' })
    } catch (e) {
        message.value = messageFrom(e, 'Не удалось удалить заказ')
    }
}
</script>

<template>
    <div v-if="order" class="max-w-4xl space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-bold tracking-tight">Заказ {{ order.number }}</h1>
            <button type="button" :class="dangerButton" class="ml-auto" @click="remove">Удалить заказ</button>
        </div>

        <div :class="card" class="overflow-x-auto p-5">
            <h2 class="mb-3 font-semibold">Состав</h2>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink-200">
                        <th :class="th">Товар</th>
                        <th :class="th">Цена</th>
                        <th :class="th">Кол-во</th>
                        <th :class="th">Сумма</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in order.items" :key="item.id" class="border-b border-ink-100 last:border-0">
                        <td :class="td">{{ item.product_name }}</td>
                        <td :class="td" class="whitespace-nowrap">{{ formatMoney(item.unit_price_minor, order.currency) }}</td>
                        <td :class="td">{{ item.quantity }}</td>
                        <td :class="td" class="font-semibold whitespace-nowrap">
                            {{ formatMoney(item.total_minor, order.currency) }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="mt-3 text-right text-lg font-bold">
                Итого: {{ formatMoney(order.total_minor, order.currency) }}
            </p>
            <p class="text-right text-xs text-ink-400">
                Состав заказа не редактируется — оформите новый заказ, если нужно изменить позиции.
            </p>
        </div>

        <form :class="card" class="grid gap-4 p-6 sm:grid-cols-2" @submit.prevent="submit">
            <div>
                <label for="status" class="mb-1.5 block text-sm text-ink-500">Статус заказа</label>
                <select id="status" v-model="form.status" :class="input">
                    <option v-for="(label, value) in orderStatuses" :key="value" :value="value">{{ label }}</option>
                </select>
                <p v-if="errors.status" class="mt-1 text-sm text-red-700">{{ errors.status }}</p>
            </div>

            <div>
                <label for="payment_status" class="mb-1.5 block text-sm text-ink-500">Статус оплаты</label>
                <select id="payment_status" v-model="form.payment_status" :class="input">
                    <option v-for="(label, value) in paymentStatuses" :key="value" :value="value">{{ label }}</option>
                </select>
                <p v-if="errors.payment_status" class="mt-1 text-sm text-red-700">{{ errors.payment_status }}</p>
            </div>

            <div>
                <label for="payment_method" class="mb-1.5 block text-sm text-ink-500">Способ оплаты</label>
                <select id="payment_method" v-model="form.payment_method" :class="input">
                    <option v-for="(label, value) in paymentMethods" :key="value" :value="value">{{ label }}</option>
                </select>
                <p v-if="errors.payment_method" class="mt-1 text-sm text-red-700">{{ errors.payment_method }}</p>
            </div>

            <div>
                <label for="delivery_method" class="mb-1.5 block text-sm text-ink-500">Доставка</label>
                <select id="delivery_method" v-model="form.delivery_method" :class="input">
                    <option v-for="(label, value) in deliveryMethods" :key="value" :value="value">{{ label }}</option>
                </select>
                <p v-if="errors.delivery_method" class="mt-1 text-sm text-red-700">{{ errors.delivery_method }}</p>
            </div>

            <div>
                <label for="delivery_cost" class="mb-1.5 block text-sm text-ink-500">Стоимость доставки, копейки</label>
                <input id="delivery_cost" v-model="form.delivery_cost_minor" type="number" min="0" :class="input">
                <p class="mt-1 text-xs text-ink-400">Для заказов СДЭК её посчитал перевозчик — правьте только вручную оформленные.</p>
                <p v-if="errors.delivery_cost_minor" class="mt-1 text-sm text-red-700">{{ errors.delivery_cost_minor }}</p>
            </div>

            <div>
                <label for="contact_name" class="mb-1.5 block text-sm text-ink-500">Получатель</label>
                <input id="contact_name" v-model="form.contact_name" type="text" required :class="input">
                <p v-if="errors.contact_name" class="mt-1 text-sm text-red-700">{{ errors.contact_name }}</p>
            </div>

            <div>
                <label for="contact_email" class="mb-1.5 block text-sm text-ink-500">E-mail</label>
                <input id="contact_email" v-model="form.contact_email" type="email" required :class="input">
                <p v-if="errors.contact_email" class="mt-1 text-sm text-red-700">{{ errors.contact_email }}</p>
            </div>

            <div>
                <label for="contact_phone" class="mb-1.5 block text-sm text-ink-500">Телефон</label>
                <input id="contact_phone" v-model="form.contact_phone" type="tel" required :class="input">
                <p v-if="errors.contact_phone" class="mt-1 text-sm text-red-700">{{ errors.contact_phone }}</p>
            </div>

            <div class="sm:col-span-2">
                <label for="shipping_address" class="mb-1.5 block text-sm text-ink-500">Адрес доставки</label>
                <textarea id="shipping_address" v-model="form.shipping_address" rows="2" required :class="input"></textarea>
                <p v-if="errors.shipping_address" class="mt-1 text-sm text-red-700">{{ errors.shipping_address }}</p>
            </div>

            <div class="sm:col-span-2">
                <label for="comment" class="mb-1.5 block text-sm text-ink-500">Комментарий</label>
                <textarea id="comment" v-model="form.comment" rows="2" :class="input"></textarea>
                <p v-if="errors.comment" class="mt-1 text-sm text-red-700">{{ errors.comment }}</p>
            </div>

            <p v-if="message" class="text-sm text-red-700 sm:col-span-2">{{ message }}</p>
            <p v-if="saved" class="text-sm text-gold-700 sm:col-span-2">Сохранено.</p>

            <div class="flex justify-end gap-2 sm:col-span-2">
                <RouterLink :to="{ name: 'admin.orders' }" :class="ghostButton">К списку</RouterLink>
                <button type="submit" :class="primaryButton" :disabled="saving">Сохранить</button>
            </div>
        </form>
    </div>
</template>
