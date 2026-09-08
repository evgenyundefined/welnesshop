<script setup>
import { ref } from 'vue'
import { session } from '../stores/session'
import { formatMoney } from '../money'
import { messageFrom } from '../api'

const props = defineProps({ product: { type: Object, required: true } })

const error = ref('')
const pending = ref(false)

async function addToCart() {
    error.value = ''
    pending.value = true

    try {
        await session.addToCart(props.product.id, 1)
    } catch (e) {
        error.value = messageFrom(e, 'Не удалось добавить товар')
    } finally {
        pending.value = false
    }
}
</script>

<template>
    <article class="flex flex-col gap-3 rounded-xl border border-ink-200 bg-white p-4 transition hover:border-ink-300">
        <RouterLink
            :to="{ name: 'product', params: { slug: product.slug } }"
            class="-m-4 mb-0 block aspect-4/3 overflow-hidden rounded-t-xl bg-ink-100"
        >
            <img
                v-if="product.cover"
                :src="product.cover.url"
                :alt="product.name"
                loading="lazy"
                class="size-full object-cover transition hover:scale-105"
            >
            <span v-else class="flex size-full items-center justify-center text-xs text-ink-400">Без фото</span>
        </RouterLink>

        <RouterLink
            :to="{ name: 'product', params: { slug: product.slug } }"
            class="font-semibold leading-snug hover:text-gold-700"
        >
            {{ product.name }}
        </RouterLink>

        <p class="line-clamp-4 text-sm text-ink-500">{{ product.summary }}</p>

        <p v-if="product.supplier" class="text-xs text-ink-400">Поставщик: {{ product.supplier }}</p>

        <div class="flex-1"></div>

        <div class="flex items-center justify-between gap-3">
            <span class="text-lg font-bold">{{ formatMoney(product.price_minor, product.currency) }}</span>
            <button
                type="button"
                class="rounded-lg bg-gold-400 px-3 py-2 text-sm font-semibold text-ink-950 transition hover:bg-gold-300 disabled:cursor-not-allowed disabled:bg-ink-200 disabled:text-ink-400"
                :disabled="!product.is_available || pending"
                @click="addToCart"
            >
                {{ product.is_available ? 'В корзину' : 'Нет в наличии' }}
            </button>
        </div>

        <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
    </article>
</template>
