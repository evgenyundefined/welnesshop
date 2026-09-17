<script setup>
import { computed, onMounted, ref } from 'vue'
import { setDocumentTitle, setMetaDescription } from '../documentTitle'
import api, { messageFrom } from '../api'
import { session } from '../stores/session'
import { site } from '../stores/site'
import { formatMoney, settlementHint } from '../money'

const props = defineProps({ slug: { type: String, required: true } })

const product = ref(null)
const shown = ref(null)
const quantity = ref(1)
const error = ref('')
const added = ref(false)

const priceHint = computed(() => (product.value
    ? settlementHint(product.value.price_minor, product.value.currency, site.state)
    : ''))

onMounted(async () => {
    const { data } = await api.get(`/products/${props.slug}`)
    product.value = data.data
    quantity.value = product.value.min_order_quantity
    setDocumentTitle(product.value.name)
    setMetaDescription(product.value.summary)
    shown.value = data.data.images?.[0] ?? null
})

async function addToCart() {
    error.value = ''
    added.value = false

    try {
        await session.addToCart(product.value.id, Number(quantity.value))
        added.value = true
    } catch (e) {
        error.value = messageFrom(e, 'Не удалось добавить товар')
    }
}
</script>

<template>
    <div v-if="product" class="space-y-4">
        <RouterLink :to="{ name: 'catalog' }" class="text-sm text-ink-400 hover:text-ink-700">
            ← В каталог
        </RouterLink>

        <h1 class="text-2xl font-bold tracking-tight">{{ product.name }}</h1>

        <div
            class="grid gap-6 lg:items-start"
            :class="shown ? 'lg:grid-cols-[minmax(0,420px)_1fr]' : ''"
        >
            <div v-if="shown" class="space-y-3 rounded-xl border border-ink-200 bg-white p-4">
                <div class="aspect-square overflow-hidden rounded-lg bg-ink-100">
                    <img :src="shown.url" :alt="product.name" class="size-full object-contain">
                </div>

                <ul v-if="product.images.length > 1" class="flex flex-wrap gap-2">
                    <li v-for="image in product.images" :key="image.id">
                        <button
                            type="button"
                            class="size-16 overflow-hidden rounded-lg border transition"
                            :class="image.id === shown.id ? 'border-gold-400 ring-2 ring-gold-300' : 'border-ink-200'"
                            @click="shown = image"
                        >
                            <img :src="image.url" alt="" class="size-full object-cover">
                        </button>
                    </li>
                </ul>
            </div>

            <div class="space-y-4 rounded-xl border border-ink-200 bg-white p-6">
                <p>{{ product.summary }}</p>

                <dl class="grid gap-2 text-sm sm:grid-cols-[180px_1fr]">
                    <template v-if="product.category">
                        <dt class="text-ink-400">Категория</dt>
                        <dd>{{ product.category.name }}</dd>
                    </template>
                    <template v-if="product.maturity">
                        <dt class="text-ink-400">Статус категории</dt>
                        <dd>{{ product.maturity }}</dd>
                    </template>
                    <template v-if="product.supplier">
                        <dt class="text-ink-400">Поставщик</dt>
                        <dd>{{ product.supplier }}</dd>
                    </template>
                    <template v-if="product.source_url">
                        <dt class="text-ink-400">Источник</dt>
                        <dd class="truncate">
                            <a :href="product.source_url" target="_blank" rel="noopener" class="text-gold-700 underline">
                                {{ product.source_url }}
                            </a>
                        </dd>
                    </template>
                    <dt class="text-ink-400">В наличии</dt>
                    <dd :class="product.stock_level === 'last' ? 'font-semibold text-gold-700' : ''">
                        {{ product.stock_label }}
                    </dd>
                </dl>

                <div class="flex flex-wrap items-center justify-between gap-4 border-t border-ink-200 pt-4">
                    <div class="flex flex-col gap-0.5">
                        <span class="text-2xl font-bold">{{ formatMoney(product.price_minor, product.currency) }}</span>
                        <span v-if="priceHint" class="text-xs text-ink-500">{{ priceHint }} при оплате</span>
                        <span
                            v-if="product.wholesale_only"
                            class="rounded-md bg-gold-100 px-2 py-1 text-xs font-semibold text-gold-800"
                        >
                            Только для оптовых закупок
                        </span>
                        <span v-if="product.min_order_quantity > 1" class="text-xs text-ink-500">
                            Минимальный заказ — {{ product.min_order_quantity }} шт.
                        </span>
                    </div>

                    <div class="flex items-center gap-3">
                        <input
                            v-model="quantity"
                            type="number"
                            :min="product.min_order_quantity"
                            :step="product.min_order_quantity > 1 ? product.min_order_quantity : 1"
                            :max="site.state.max_item_quantity"
                            class="w-20 rounded-lg border border-ink-300 px-3 py-2 text-sm outline-none focus:border-gold-500 focus:ring-1 focus:ring-gold-500"
                        >
                        <button
                            type="button"
                            class="rounded-lg bg-gold-400 px-4 py-2 text-sm font-semibold text-ink-950 transition hover:bg-gold-300 disabled:cursor-not-allowed disabled:bg-ink-200 disabled:text-ink-400"
                            :disabled="!product.is_available"
                            @click="addToCart"
                        >
                            {{ product.is_available ? 'В корзину' : 'Нет в наличии' }}
                        </button>
                    </div>
                </div>

                <p v-if="added" class="text-sm text-gold-700">Товар добавлен в корзину.</p>
                <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
            </div>
        </div>
    </div>

    <p v-else class="text-ink-400">Загрузка…</p>
</template>
