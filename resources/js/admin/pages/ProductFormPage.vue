<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import api, { fieldErrorsFrom, messageFrom } from '../api'
import ProductGallery from '../components/ProductGallery.vue'
import { card, ghostButton, input, primaryButton, productStatuses } from '../ui'

const props = defineProps({ id: { type: String, default: null } })

const router = useRouter()
const categories = ref([])
const errors = ref({})
const message = ref('')
const saving = ref(false)
const loaded = ref(false)

const form = reactive({
    category_id: '',
    name: '',
    slug: '',
    summary: '',
    maturity: '',
    supplier: '',
    source_url: '',
    status: 'published',
    price: '',
    currency: 'RUB',
    stock: 0,
    weight_grams: '',
})

onMounted(async () => {
    const { data } = await api.get('/categories', { params: { per_page: 100 } })
    categories.value = data.data

    if (props.id) {
        const { data: product } = await api.get(`/products/${props.id}`)
        Object.assign(form, {
            category_id: product.data.category_id,
            name: product.data.name,
            slug: product.data.slug,
            summary: product.data.summary ?? '',
            maturity: product.data.maturity ?? '',
            supplier: product.data.supplier ?? '',
            source_url: product.data.source_url ?? '',
            status: product.data.status,
            price: product.data.price_minor / 100,
            currency: product.data.currency,
            stock: product.data.stock,
            weight_grams: product.data.weight_grams ?? '',
        })
    } else {
        form.category_id = categories.value[0]?.id ?? ''
    }

    loaded.value = true
})

async function submit() {
    errors.value = {}
    message.value = ''
    saving.value = true

    const payload = {
        category_id: Number(form.category_id),
        name: form.name,
        slug: form.slug || null,
        summary: form.summary || null,
        maturity: form.maturity || null,
        supplier: form.supplier || null,
        source_url: form.source_url || null,
        status: form.status,
        price_minor: Math.round(Number(form.price) * 100),
        currency: form.currency,
        stock: Number(form.stock),
        weight_grams: form.weight_grams === '' || form.weight_grams === null ? null : Number(form.weight_grams),
    }

    try {
        if (props.id) {
            await api.patch(`/products/${props.id}`, payload)
            router.push({ name: 'admin.products' })
        } else {
            const { data } = await api.post('/products', payload)

            // Straight to the saved product, because the gallery needs one.
            router.push({ name: 'admin.products.edit', params: { id: data.data.id } })
        }
    } catch (e) {
        errors.value = fieldErrorsFrom(e)
        message.value = messageFrom(e, 'Не удалось сохранить товар')
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div v-if="loaded" class="max-w-3xl space-y-4">
        <h1 class="text-2xl font-bold tracking-tight">{{ id ? 'Товар' : 'Новый товар' }}</h1>

        <form :class="card" class="grid gap-4 p-6 sm:grid-cols-2" @submit.prevent="submit">
            <div class="sm:col-span-2">
                <label for="name" class="mb-1.5 block text-sm text-ink-500">Название</label>
                <input id="name" v-model="form.name" type="text" required :class="input">
                <p v-if="errors.name" class="mt-1 text-sm text-red-700">{{ errors.name }}</p>
            </div>

            <div>
                <label for="category_id" class="mb-1.5 block text-sm text-ink-500">Категория</label>
                <select id="category_id" v-model="form.category_id" required :class="input">
                    <option v-for="category in categories" :key="category.id" :value="category.id">
                        {{ category.name }}
                    </option>
                </select>
                <p v-if="errors.category_id" class="mt-1 text-sm text-red-700">{{ errors.category_id }}</p>
            </div>

            <div>
                <label for="status" class="mb-1.5 block text-sm text-ink-500">Статус</label>
                <select id="status" v-model="form.status" :class="input">
                    <option v-for="(label, value) in productStatuses" :key="value" :value="value">{{ label }}</option>
                </select>
                <p v-if="errors.status" class="mt-1 text-sm text-red-700">{{ errors.status }}</p>
            </div>

            <div>
                <label for="price" class="mb-1.5 block text-sm text-ink-500">Цена</label>
                <div class="flex gap-2">
                    <input
                        id="price"
                        v-model="form.price"
                        type="number"
                        min="0.01"
                        step="0.01"
                        required
                        :class="input"
                    >
                    <select v-model="form.currency" :class="input" class="w-28" aria-label="Валюта цены">
                        <option value="RUB">₽ RUB</option>
                        <option value="USD">$ USD</option>
                    </select>
                </div>
                <p class="mt-1 text-xs text-ink-400">
                    Цена в долларах пересчитывается в рубли по курсу ЦБ на момент заказа.
                </p>
                <p v-if="errors.price_minor" class="mt-1 text-sm text-red-700">{{ errors.price_minor }}</p>
                <p v-if="errors.currency" class="mt-1 text-sm text-red-700">{{ errors.currency }}</p>
            </div>

            <div>
                <label for="stock" class="mb-1.5 block text-sm text-ink-500">Остаток</label>
                <input id="stock" v-model="form.stock" type="number" min="0" required :class="input">
            </div>

            <div>
                <label for="weight_grams" class="mb-1.5 block text-sm text-ink-500">Вес, граммы</label>
                <input id="weight_grams" v-model="form.weight_grams" type="number" min="1" :class="input" placeholder="по умолчанию">
                <p class="mt-1 text-xs text-ink-400">По нему СДЭК считает доставку. Пусто — берётся значение из настроек.</p>
                <p v-if="errors.weight_grams" class="mt-1 text-sm text-red-700">{{ errors.weight_grams }}</p>
                <p v-if="errors.stock" class="mt-1 text-sm text-red-700">{{ errors.stock }}</p>
            </div>

            <div class="sm:col-span-2">
                <label for="slug" class="mb-1.5 block text-sm text-ink-500">Slug (необязательно)</label>
                <input id="slug" v-model="form.slug" type="text" :class="input" placeholder="сгенерируется из названия">
                <p v-if="errors.slug" class="mt-1 text-sm text-red-700">{{ errors.slug }}</p>
            </div>

            <div class="sm:col-span-2">
                <label for="summary" class="mb-1.5 block text-sm text-ink-500">Назначение</label>
                <textarea id="summary" v-model="form.summary" rows="3" :class="input"></textarea>
                <p v-if="errors.summary" class="mt-1 text-sm text-red-700">{{ errors.summary }}</p>
            </div>

            <div class="sm:col-span-2">
                <label for="maturity" class="mb-1.5 block text-sm text-ink-500">Статус категории / зрелость</label>
                <textarea id="maturity" v-model="form.maturity" rows="2" :class="input"></textarea>
                <p v-if="errors.maturity" class="mt-1 text-sm text-red-700">{{ errors.maturity }}</p>
            </div>

            <div>
                <label for="supplier" class="mb-1.5 block text-sm text-ink-500">Поставщик</label>
                <input id="supplier" v-model="form.supplier" type="text" :class="input">
                <p v-if="errors.supplier" class="mt-1 text-sm text-red-700">{{ errors.supplier }}</p>
            </div>

            <div>
                <label for="source_url" class="mb-1.5 block text-sm text-ink-500">Ссылка на источник</label>
                <input id="source_url" v-model="form.source_url" type="url" :class="input">
                <p v-if="errors.source_url" class="mt-1 text-sm text-red-700">{{ errors.source_url }}</p>
            </div>

            <p v-if="message" class="text-sm text-red-700 sm:col-span-2">{{ message }}</p>

            <div class="flex justify-end gap-2 sm:col-span-2">
                <RouterLink :to="{ name: 'admin.products' }" :class="ghostButton">Отмена</RouterLink>
                <button type="submit" :class="primaryButton" :disabled="saving">Сохранить</button>
            </div>
        </form>

        <ProductGallery v-if="id" :product-id="id" />

        <p v-else :class="card" class="p-6 text-sm text-ink-400">
            Галерея откроется после сохранения товара.
        </p>
    </div>
</template>
