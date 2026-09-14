<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { session } from '../stores/session'
import { formatMoney } from '../money'
import api, { fieldErrorsFrom, messageFrom } from '../api'
import FormField from '../components/FormField.vue'
import PhoneInput from '../components/PhoneInput.vue'
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
    cdek_city_code: null,
    cdek_destination: 'point',
    cdek_point_code: null,
    cdek_tariff_code: null,
})

const byCarrier = computed(() => form.delivery_method === 'cdek')
const toPoint = computed(() => byCarrier.value && form.cdek_destination === 'point')
const needsAddress = computed(() => !toPoint.value)

const cityQuery = ref('')
const cityName = ref('')
const cities = ref([])
const points = ref([])
const tariffs = ref([])
const loadingTariffs = ref(false)
const deliveryMessage = ref('')

const tariff = computed(() => tariffs.value.find((row) => row.code === form.cdek_tariff_code) ?? null)
const deliveryCost = computed(() => (byCarrier.value ? (tariff.value?.cost_minor ?? 0) : 0))
const payable = computed(() => cart.value.total_minor + deliveryCost.value)

function term(row) {
    if (row.days_min === null) {
        return ''
    }

    return row.days_max && row.days_max !== row.days_min
        ? `${row.days_min}–${row.days_max} дн.`
        : `${row.days_min} дн.`
}

let citySearchTimer

watch(cityQuery, (value) => {
    clearTimeout(citySearchTimer)

    if (value.trim().length < 2 || value === cityName.value) {
        cities.value = []

        return
    }

    citySearchTimer = setTimeout(async () => {
        try {
            const { data } = await api.get('/delivery/cdek/cities', { params: { query: value.trim() } })
            cities.value = data.data
        } catch (e) {
            deliveryMessage.value = messageFrom(e, 'Не удалось найти город')
        }
    }, 350)
})

async function chooseCity(city) {
    form.cdek_city_code = city.code
    cityName.value = city.full_name
    cityQuery.value = city.full_name
    cities.value = []
    form.cdek_point_code = null
    points.value = []

    if (toPoint.value) {
        await loadPoints()
    }

    await loadTariffs()
}

async function loadPoints() {
    if (!form.cdek_city_code) {
        return
    }

    try {
        const { data } = await api.get('/delivery/cdek/points', { params: { city_code: form.cdek_city_code } })
        points.value = data.data
    } catch (e) {
        points.value = []
        deliveryMessage.value = messageFrom(e, 'Не удалось получить пункты выдачи')
    }
}

async function loadTariffs() {
    form.cdek_tariff_code = null
    tariffs.value = []

    if (!form.cdek_city_code || (toPoint.value && !form.cdek_point_code)) {
        return
    }

    deliveryMessage.value = ''
    loadingTariffs.value = true

    try {
        const { data } = await api.post('/delivery/cdek/tariffs', {
            city_code: form.cdek_city_code,
            destination: form.cdek_destination,
            point_code: form.cdek_point_code,
        })
        tariffs.value = data.data
        form.cdek_tariff_code = tariffs.value[0]?.code ?? null

        if (!tariffs.value.length) {
            deliveryMessage.value = 'Для этого направления СДЭК не предложил ни одного тарифа.'
        }
    } catch (e) {
        deliveryMessage.value = messageFrom(e, 'Не удалось рассчитать доставку')
    } finally {
        loadingTariffs.value = false
    }
}

watch(() => form.cdek_destination, async () => {
    if (toPoint.value && !points.value.length) {
        await loadPoints()
    }

    await loadTariffs()
})

watch(() => form.cdek_point_code, loadTariffs)

watch(() => form.delivery_method, () => {
    deliveryMessage.value = ''
    tariffs.value = []
    form.cdek_tariff_code = null
})

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
            <PhoneInput id="contact_phone" v-model="form.contact_phone" required :class="inputClass" />
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

        <div v-if="byCarrier" class="space-y-4 rounded-lg border border-ink-200 bg-ink-50 p-4 sm:col-span-2">
            <div class="relative">
                <FormField id="cdek_city" label="Город доставки" :error="errors.cdek_city_code">
                    <input
                        id="cdek_city"
                        v-model="cityQuery"
                        type="text"
                        autocomplete="off"
                        placeholder="Начните вводить название"
                        :class="inputClass"
                    >
                </FormField>

                <ul
                    v-if="cities.length"
                    class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-ink-200 bg-white shadow-lg"
                >
                    <li v-for="city in cities" :key="city.code">
                        <button
                            type="button"
                            class="block w-full px-3 py-2 text-left text-sm hover:bg-ink-100"
                            @click="chooseCity(city)"
                        >
                            {{ city.full_name }}
                        </button>
                    </li>
                </ul>
            </div>

            <fieldset>
                <legend class="mb-1.5 text-sm text-ink-500">Куда доставить</legend>
                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="flex items-center gap-2">
                        <input v-model="form.cdek_destination" type="radio" value="point" class="accent-gold-500">
                        В пункт выдачи
                    </label>
                    <label class="flex items-center gap-2">
                        <input v-model="form.cdek_destination" type="radio" value="door" class="accent-gold-500">
                        До двери
                    </label>
                </div>
            </fieldset>

            <FormField v-if="toPoint" id="cdek_point" label="Пункт выдачи" :error="errors.cdek_point_code">
                <select id="cdek_point" v-model="form.cdek_point_code" :class="inputClass" :disabled="!points.length">
                    <option :value="null">
                        {{ points.length ? 'Выберите пункт' : 'Сначала выберите город' }}
                    </option>
                    <option v-for="point in points" :key="point.code" :value="point.code">
                        {{ point.address }}<template v-if="point.work_time"> — {{ point.work_time }}</template>
                    </option>
                </select>
            </FormField>

            <div v-if="loadingTariffs" class="text-sm text-ink-400">Считаем доставку…</div>

            <fieldset v-else-if="tariffs.length">
                <legend class="mb-1.5 text-sm text-ink-500">Тариф СДЭК</legend>
                <div class="space-y-2">
                    <label
                        v-for="row in tariffs"
                        :key="row.code"
                        class="flex cursor-pointer items-center gap-3 rounded-lg border bg-white px-3 py-2 text-sm"
                        :class="form.cdek_tariff_code === row.code ? 'border-gold-500' : 'border-ink-200'"
                    >
                        <input v-model="form.cdek_tariff_code" type="radio" :value="row.code" class="accent-gold-500">
                        <span class="flex-1">
                            {{ row.name }}
                            <span v-if="term(row)" class="text-ink-400">· {{ term(row) }}</span>
                        </span>
                        <span class="font-semibold whitespace-nowrap">{{ formatMoney(row.cost_minor, cart.currency) }}</span>
                    </label>
                </div>
                <p v-if="errors.cdek_tariff_code" class="mt-1 text-sm text-red-700">{{ errors.cdek_tariff_code }}</p>
            </fieldset>

            <p v-if="deliveryMessage" class="text-sm text-red-700">{{ deliveryMessage }}</p>
        </div>

        <div v-if="needsAddress" class="sm:col-span-2">
            <FormField id="shipping_address" label="Адрес доставки" :error="errors.shipping_address">
                <textarea id="shipping_address" v-model="form.shipping_address" required rows="3" :class="inputClass"></textarea>
                <p class="mt-1 text-xs text-ink-400">
                    {{ byCarrier ? 'Улица, дом, квартира — куда курьер СДЭК привезёт заказ.' : 'Улица, дом, квартира — куда приехать курьеру.' }}
                </p>
            </FormField>
        </div>

        <div class="sm:col-span-2">
            <FormField id="comment" label="Комментарий" :error="errors.comment">
                <textarea id="comment" v-model="form.comment" rows="2" :class="inputClass"></textarea>
            </FormField>
        </div>

        <p v-if="message" class="text-sm text-red-700 sm:col-span-2">{{ message }}</p>

        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-ink-200 pt-4 sm:col-span-2">
            <div>
                <span class="text-lg font-bold">К оплате: {{ formatMoney(payable, cart.currency) }}</span>
                <p v-if="deliveryCost" class="text-xs text-ink-400">
                    Товары {{ formatMoney(cart.total_minor, cart.currency) }} + доставка
                    {{ formatMoney(deliveryCost, cart.currency) }}
                </p>
            </div>
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
