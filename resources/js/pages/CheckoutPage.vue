<script setup>
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { session } from '../stores/session'
import { formatMoney } from '../money'
import { fieldErrorsFrom, messageFrom } from '../api'
import FormField from '../components/FormField.vue'
import { deliveryMethods } from '../labels'

const router = useRouter()
const cart = computed(() => session.state.cart)

const form = reactive({
    contact_name: session.state.customer?.name ?? '',
    contact_email: session.state.customer?.email ?? '',
    contact_phone: session.state.customer?.phone ?? '',
    delivery_method: 'courier',
    shipping_address: '',
    comment: '',
    payment_method: 'card',
})

const addressHint = computed(() => form.delivery_method === 'transport_company'
    ? 'Город и терминал транспортной компании, куда доставить заказ.'
    : 'Улица, дом, квартира — куда приехать курьеру.')

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
        const order = await session.checkout({ ...form })
        router.push({ name: 'payment', params: { number: order.number } })
    } catch (e) {
        errors.value = fieldErrorsFrom(e)
        message.value = messageFrom(e, 'Не удалось оформить заказ')
    } finally {
        pending.value = false
    }
}
</script>

<template>
    <h1 class="mb-6 text-2xl font-bold tracking-tight">Оформление заказа</h1>

    <p v-if="!cart.items.length" class="text-ink-500">
        Корзина пуста.
        <RouterLink :to="{ name: 'catalog' }" class="text-gold-700 underline">Перейти в каталог</RouterLink>
    </p>

    <form v-else class="grid max-w-3xl gap-4 rounded-xl border border-ink-200 bg-white p-6 sm:grid-cols-2" @submit.prevent="submit">
        <FormField id="contact_name" label="Имя получателя" :error="errors.contact_name">
            <input id="contact_name" v-model="form.contact_name" type="text" required :class="inputClass">
        </FormField>

        <FormField id="contact_email" label="E-mail" :error="errors.contact_email">
            <input id="contact_email" v-model="form.contact_email" type="email" required :class="inputClass">
        </FormField>

        <FormField id="contact_phone" label="Телефон" :error="errors.contact_phone">
            <input id="contact_phone" v-model="form.contact_phone" type="tel" required :class="inputClass">
        </FormField>

        <FormField id="payment_method" label="Способ оплаты" :error="errors.payment_method">
            <select id="payment_method" v-model="form.payment_method" :class="inputClass">
                <option value="card">Банковская карта</option>
                <option value="sbp">СБП</option>
                <option value="invoice">Счёт для юридических лиц</option>
            </select>
        </FormField>

        <FormField id="delivery_method" label="Доставка" :error="errors.delivery_method">
            <select id="delivery_method" v-model="form.delivery_method" :class="inputClass">
                <option v-for="(label, value) in deliveryMethods" :key="value" :value="value">{{ label }}</option>
            </select>
        </FormField>

        <div class="sm:col-span-2">
            <FormField id="shipping_address" label="Адрес доставки" :error="errors.shipping_address">
                <textarea id="shipping_address" v-model="form.shipping_address" required rows="3" :class="inputClass"></textarea>
                <p class="mt-1 text-xs text-ink-400">{{ addressHint }}</p>
            </FormField>
        </div>

        <div class="sm:col-span-2">
            <FormField id="comment" label="Комментарий" :error="errors.comment">
                <textarea id="comment" v-model="form.comment" rows="2" :class="inputClass"></textarea>
            </FormField>
        </div>

        <p v-if="message" class="text-sm text-red-700 sm:col-span-2">{{ message }}</p>

        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-ink-200 pt-4 sm:col-span-2">
            <span class="text-lg font-bold">К оплате: {{ formatMoney(cart.total_minor, cart.currency) }}</span>
            <button
                type="submit"
                class="rounded-lg bg-gold-400 px-4 py-2.5 text-sm font-semibold text-ink-950 transition hover:bg-gold-300 disabled:bg-ink-200 disabled:text-ink-400"
                :disabled="pending"
            >
                Перейти к оплате
            </button>
        </div>
    </form>
</template>
