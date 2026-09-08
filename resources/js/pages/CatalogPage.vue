<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import api from '../api'
import ProductCard from '../components/ProductCard.vue'

const categories = ref([])
const products = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const loading = ref(true)

const filters = reactive({ category: null, search: '', sort: 'name', in_stock: false, page: 1 })

const inputClass =
    'w-full rounded-lg border border-ink-300 bg-white px-3 py-2 text-sm outline-none focus:border-gold-500 focus:ring-1 focus:ring-gold-500'

async function loadProducts() {
    loading.value = true

    const { data } = await api.get('/products', {
        params: {
            category: filters.category ?? undefined,
            search: filters.search || undefined,
            sort: filters.sort,
            in_stock: filters.in_stock ? 1 : undefined,
            page: filters.page,
        },
    })

    products.value = data.data
    meta.value = data.meta
    loading.value = false
}

function selectCategory(slug) {
    filters.category = filters.category === slug ? null : slug
    filters.page = 1
}

let searchTimer
watch(
    () => filters.search,
    () => {
        clearTimeout(searchTimer)
        searchTimer = setTimeout(() => {
            filters.page = 1
            loadProducts()
        }, 300)
    },
)

watch(() => [filters.category, filters.sort, filters.in_stock, filters.page], loadProducts)

onMounted(async () => {
    const [{ data }] = await Promise.all([api.get('/categories'), loadProducts()])
    categories.value = data.data
})
</script>

<template>
    <h1 class="mb-6 text-2xl font-bold tracking-tight">Каталог</h1>

    <div class="grid gap-6 lg:grid-cols-[240px_1fr]">
        <aside class="h-fit space-y-4 rounded-xl border border-ink-200 bg-white p-4">
            <div>
                <h2 class="mb-2 font-semibold">Категории</h2>
                <ul class="space-y-0.5">
                    <li v-for="category in categories" :key="category.id">
                        <button
                            type="button"
                            class="w-full rounded-lg px-2 py-1.5 text-left text-sm transition"
                            :class="filters.category === category.slug
                                ? 'bg-gold-100 font-semibold text-gold-700'
                                : 'text-ink-500 hover:bg-ink-50'"
                            @click="selectCategory(category.slug)"
                        >
                            {{ category.name }} ({{ category.products_count }})
                        </button>
                    </li>
                </ul>
            </div>

            <div>
                <label for="search" class="mb-1.5 block text-sm text-ink-500">Поиск</label>
                <input id="search" v-model="filters.search" type="text" :class="inputClass" placeholder="Название или назначение">
            </div>

            <div>
                <label for="sort" class="mb-1.5 block text-sm text-ink-500">Сортировка</label>
                <select id="sort" v-model="filters.sort" :class="inputClass">
                    <option value="name">По названию</option>
                    <option value="price_asc">Сначала дешевле</option>
                    <option value="price_desc">Сначала дороже</option>
                    <option value="newest">Сначала новые</option>
                </select>
            </div>

            <label class="flex items-center gap-2 text-sm text-ink-500">
                <input v-model="filters.in_stock" type="checkbox" class="size-4 accent-gold-500"> Только в наличии
            </label>
        </aside>

        <section>
            <p v-if="loading" class="text-ink-400">Загрузка…</p>
            <p v-else-if="!products.length" class="text-ink-400">Ничего не найдено.</p>

            <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <ProductCard v-for="product in products" :key="product.id" :product="product" />
            </div>

            <div v-if="meta.last_page > 1" class="mt-6 flex items-center justify-center gap-3 text-sm text-ink-500">
                <button
                    type="button"
                    class="rounded-lg border border-ink-300 px-3 py-1.5 transition hover:border-ink-400 disabled:opacity-40"
                    :disabled="filters.page <= 1"
                    @click="filters.page--"
                >
                    Назад
                </button>
                <span>{{ meta.current_page }} / {{ meta.last_page }}</span>
                <button
                    type="button"
                    class="rounded-lg border border-ink-300 px-3 py-1.5 transition hover:border-ink-400 disabled:opacity-40"
                    :disabled="filters.page >= meta.last_page"
                    @click="filters.page++"
                >
                    Вперёд
                </button>
            </div>
        </section>
    </div>
</template>
