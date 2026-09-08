<script setup>
import { onMounted, ref, watch } from 'vue'
import api, { messageFrom } from '../api'
import Pagination from '../components/Pagination.vue'
import { card, dangerButton, ghostButton, input, primaryButton, productStatuses, td, th } from '../ui'
import { formatMoney } from '../../money'

const products = ref([])
const categories = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const search = ref('')
const categoryId = ref('')
const status = ref('')
const page = ref(1)
const error = ref('')

async function load() {
    const { data } = await api.get('/products', {
        params: {
            search: search.value || undefined,
            category_id: categoryId.value || undefined,
            status: status.value || undefined,
            page: page.value,
        },
    })
    products.value = data.data
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

watch([categoryId, status], () => {
    page.value = 1
    load()
})

watch(page, load)

onMounted(async () => {
    const [{ data }] = await Promise.all([api.get('/categories', { params: { per_page: 100 } }), load()])
    categories.value = data.data
})

async function remove(product) {
    if (!window.confirm(`Удалить товар «${product.name}»?`)) {
        return
    }

    error.value = ''

    try {
        await api.delete(`/products/${product.id}`)
        await load()
    } catch (e) {
        error.value = messageFrom(e, 'Не удалось удалить товар')
    }
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-bold tracking-tight">Товары</h1>
            <RouterLink :to="{ name: 'admin.products.create' }" :class="primaryButton" class="ml-auto">
                Добавить
            </RouterLink>
        </div>

        <div class="flex flex-wrap gap-3">
            <input v-model="search" type="search" placeholder="Название или slug" :class="input" class="max-w-xs">
            <select v-model="categoryId" :class="input" class="max-w-xs">
                <option value="">Все категории</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
            </select>
            <select v-model="status" :class="input" class="max-w-xs">
                <option value="">Любой статус</option>
                <option v-for="(label, value) in productStatuses" :key="value" :value="value">{{ label }}</option>
            </select>
        </div>

        <p v-if="error" class="text-sm text-red-700">{{ error }}</p>

        <div :class="card" class="overflow-x-auto p-5">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink-200">
                        <th :class="th"><span class="sr-only">Обложка</span></th>
                        <th :class="th">Название</th>
                        <th :class="th">Категория</th>
                        <th :class="th">Статус</th>
                        <th :class="th">Цена</th>
                        <th :class="th">Остаток</th>
                        <th :class="th">Просмотров</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="product in products" :key="product.id" class="border-b border-ink-100 last:border-0">
                        <td :class="td" class="w-12">
                            <div class="size-10 overflow-hidden rounded-lg bg-ink-100">
                                <img
                                    v-if="product.cover"
                                    :src="product.cover.url"
                                    alt=""
                                    loading="lazy"
                                    class="size-full object-cover"
                                >
                            </div>
                        </td>
                        <td :class="td">
                            <RouterLink
                                :to="{ name: 'admin.products.edit', params: { id: product.id } }"
                                class="hover:text-gold-700"
                            >
                                {{ product.name }}
                            </RouterLink>
                        </td>
                        <td :class="td" class="text-ink-500">{{ product.category?.name }}</td>
                        <td :class="td">{{ productStatuses[product.status] ?? product.status }}</td>
                        <td :class="td" class="whitespace-nowrap">{{ formatMoney(product.price_minor, product.currency) }}</td>
                        <td :class="td">{{ product.stock }}</td>
                        <td :class="td" class="tabular-nums">{{ product.views }}</td>
                        <td :class="td" class="text-right whitespace-nowrap">
                            <RouterLink
                                :to="{ name: 'admin.products.edit', params: { id: product.id } }"
                                :class="ghostButton"
                            >
                                Изменить
                            </RouterLink>
                            <button type="button" :class="dangerButton" class="ml-2" @click="remove(product)">Удалить</button>
                        </td>
                    </tr>
                    <tr v-if="!products.length">
                        <td :class="td" colspan="8" class="text-ink-400">Ничего не найдено.</td>
                    </tr>
                </tbody>
            </table>

            <Pagination v-model:page="page" :meta="meta" />
        </div>
    </div>
</template>
