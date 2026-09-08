<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import api, { fieldErrorsFrom, messageFrom } from '../api'
import {
    card,
    dangerButton,
    deliveryMethods,
    ghostButton,
    input,
    paymentMethods,
    primaryButton,
    td,
    th,
} from '../ui'
import { formatMoney } from '../../money'

const router = useRouter()
const customers = ref([])
const products = ref([])
const errors = ref({})
const message = ref('')
const saving = ref(false)
const loaded = ref(false)

const form = reactive({
    customer_id: '',
    contact_name: '',
    contact_email: '',
    contact_phone: '',
    shipping_address: '',
    comment: '',
    payment_method: 'card',
    delivery_method: 'courier',
})

const lines = ref([])

const total = computed(() =>
    lines.value.reduce((sum, line) => {
        const product = products.value.find((candidate) => candidate.id === Number(line.product_id))

        return sum + (product?.price_minor ?? 0) * Number(line.quantity || 0)
    }, 0),
)

onMounted(async () => {
    const [{ data: customerPage }, { data: productPage }] = await Promise.all([
        api.get('/customers', { params: { per_page: 100, blocked: 0 } }),
        api.get('/products', { params: { per_page: 100, status: 'published' } }),
    ])

    customers.value = customerPage.data
    products.value = productPage.data
    loaded.value = true
})

function addLine() {
    lines.value.push({ product_id: products.value[0]?.id ?? '', quantity: 1 })
}

function removeLine(index) {
    lines.value.splice(index, 1)
}

function fillFromCustomer() {
    const customer = customers.value.find((candidate) => candidate.id === Number(form.customer_id))

    if (customer) {
        form.contact_name = customer.name
        form.contact_email = customer.email
        form.contact_phone = customer.phone ?? ''
    }
}

async function submit() {
    errors.value = {}
    message.value = ''
    saving.value = true

    try {
        const { data } = await api.post('/orders', {
            customer_id: Number(form.customer_id),
            lines: lines.value.map((line) => ({
                product_id: Number(line.product_id),
                quantity: Number(line.quantity),
            })),
            contact_name: form.contact_name,
            contact_email: form.contact_email,
            contact_phone: form.contact_phone,
            shipping_address: form.shipping_address,
            comment: form.comment || null,
            payment_method: form.payment_method,
            delivery_method: form.delivery_method,
        })

        router.push({ name: 'admin.orders.show', params: { id: data.data.id } })
    } catch (e) {
        errors.value = fieldErrorsFrom(e)
        message.value = messageFrom(e, 'Не удалось создать заказ')
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div v-if="loaded" class="max-w-4xl space-y-4">
        <h1 class="text-2xl font-bold tracking-tight">Новый заказ</h1>

        <form class="space-y-4" @submit.prevent="submit">
            <div :class="card" class="grid gap-4 p-6 sm:grid-cols-2">
                <div>
                    <label for="customer_id" class="mb-1.5 block text-sm text-ink-500">Покупатель</label>
                    <select id="customer_id" v-model="form.customer_id" required :class="input" @change="fillFromCustomer">
                        <option value="" disabled>Выберите покупателя</option>
                        <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                            {{ customer.name }} — {{ customer.email }}
                        </option>
                    </select>
                    <p v-if="errors.customer_id" class="mt-1 text-sm text-red-700">{{ errors.customer_id }}</p>
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
                </div>
            </div>

            <div :class="card" class="p-5">
                <div class="mb-3 flex items-center gap-3">
                    <h2 class="font-semibold">Позиции</h2>
                    <button type="button" :class="ghostButton" class="ml-auto" @click="addLine">Добавить позицию</button>
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink-200">
                            <th :class="th">Товар</th>
                            <th :class="th">Кол-во</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, index) in lines" :key="index" class="border-b border-ink-100 last:border-0">
                            <td :class="td">
                                <select v-model="line.product_id" :class="input">
                                    <option v-for="product in products" :key="product.id" :value="product.id">
                                        {{ product.name }} — {{ formatMoney(product.price_minor, product.currency) }}
                                        (остаток {{ product.stock }})
                                    </option>
                                </select>
                            </td>
                            <td :class="td" class="w-32">
                                <input v-model="line.quantity" type="number" min="1" :class="input">
                            </td>
                            <td :class="td" class="text-right">
                                <button type="button" :class="dangerButton" @click="removeLine(index)">Убрать</button>
                            </td>
                        </tr>
                        <tr v-if="!lines.length">
                            <td :class="td" colspan="3" class="text-ink-400">Добавьте хотя бы одну позицию.</td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="errors.lines" class="mt-2 text-sm text-red-700">{{ errors.lines }}</p>
                <p class="mt-3 text-right text-lg font-bold">Итого: {{ formatMoney(total) }}</p>
            </div>

            <p v-if="message" class="text-sm text-red-700">{{ message }}</p>

            <div class="flex justify-end gap-2">
                <RouterLink :to="{ name: 'admin.orders' }" :class="ghostButton">Отмена</RouterLink>
                <button type="submit" :class="primaryButton" :disabled="saving || !lines.length">Создать заказ</button>
            </div>
        </form>
    </div>
</template>
